<?php
/** Paid-order provisioning using FluentCart models, not FluentCart post meta. */
defined('ABSPATH') || exit;

class FCIP_FluentCart_Order_Processor {
    const META_KEY = '_fcip_fluentcart_items';
    private $site_manager;

    public function __construct($site_manager = null) {
        add_filter('cron_schedules', array($this, 'cron_schedules'));
        $this->site_manager = $site_manager ?: new FCIP_Site_Manager();
        add_action('fcip_fluentcart_site_status_updated', array($this, 'site_status_updated'), 10, 2);
    }

    public function cron_schedules($schedules) {
        $schedules['every_minute'] = array('interval' => 60, 'display' => __('Every Minute', 'fluentcart-instawp-provisioning'));
        return $schedules;
    }

    public static function is_paid($order) {
        // Partially paid orders do not buy access to a provisioned site.
        return $order && in_array($order->payment_status, array('paid', 'partially_refunded'), true)
            && !in_array($order->status, array('canceled', 'failed'), true);
    }

    public static function sites($order_id) {
        // IDs in fct_orders and other commerce tables can be identical. Always scope by source.
        return array_values(array_filter(FCIP_Sites_Model::get_by_order_id($order_id), function ($site) {
            return $site->source === 'fluentcart' && $site->status !== FCIP_Sites_Model::STATUS_TRASHED;
        }));
    }

    public function process_feed($order, $product_id, $feed, $feed_id) {
        $options = get_option('fcip_options', array());
        if (!self::is_paid($order) || $order->type === 'renewal'
            || ($options['auto_create_sites_on_purchase'] ?? 'yes') !== 'yes'
            || ($options['enable_integration'] ?? 'yes') !== 'yes' || empty($options['api_key'])
            || empty($feed['snapshot_slug']) || ($feed['enabled'] ?? 'yes') !== 'yes') {
            return;
        }
        // Atomic lock across all feeds/items for an order. If the worker crashes,
        // leave the lock for administrator review rather than risk another site.
        if (!add_option('fcip_fc_lock_' . $order->id, current_time('mysql'), '', false)) {
            return;
        }
        try {
            $states = (array) $order->getMeta(self::META_KEY, array());
            foreach ($order->order_items as $item) {
                if ((int) $item->post_id !== (int) $product_id || in_array($item->payment_type, array('fee', 'signup_fee'), true)) {
                    continue;
                }
                $variants = array_map('absint', (array) ($feed['conditional_variation_ids'] ?? array()));
                if ($variants && !in_array((int) $item->object_id, $variants, true)) {
                    continue;
                }
                // One site per line item, as in the FluentCart integration.
                // Multiple feeds/paid notifications must not duplicate that site.
                $key = (string) $item->id;
                if (isset($states[$key])) {
                    continue;
                }
                $subscription = \FluentCart\App\Models\Subscription::query()
                    ->where('parent_order_id', $order->id)->where('variation_id', $item->object_id)->first();
                $states[$key] = array(
                    'status' => 'deferred', 'product_id' => (int) $item->post_id,
                    'variation_id' => (int) $item->object_id, 'product_name' => $item->title ?: $item->post_title,
                    'feed_id' => (int) $feed_id, 'feed' => $feed,
                    'subscription_id' => $subscription ? (int) $subscription->id : 0,
                );
                $order->updateMeta(self::META_KEY, $states);
                if (($feed['defer_creation'] ?? 'no') !== 'yes') {
                    $this->provision($order, $key, $states);
                }
            }
            $order->updateMeta('_fcip_processed_date', current_time('mysql'));
        } finally {
            delete_option('fcip_fc_lock_' . $order->id);
        }
    }

    /** Called only with the order lock held; configuration is saved at purchase. */
    private function provision($order, $key, &$states, $username = '', $subdomain = '') {
        $state = $states[$key];
        $feed = $state['feed'];
        $customer = $order->customer;
        if (!$customer || !is_email($customer->email)) {
            return new WP_Error('customer_required', __('A valid customer email is required.', 'fluentcart-instawp-provisioning'));
        }
        $name = $username ?: sanitize_user(strtolower(($customer->first_name ?? '') . ($customer->last_name ?? '')), true);
        $suffix = '-fc-' . $order->id . '-' . substr(md5($key), 0, 5);
        $site_name = $subdomain ?: substr(sanitize_title($state['product_name']), 0, max(1, 30 - strlen($suffix))) . $suffix;
        $data = array(
            'site_name' => $site_name, 'user_name' => $name ?: 'admin', 'email' => $customer->email,
            'order_id' => $order->id, 'product_id' => $state['product_id'],
            'customer_id' => (int) $customer->user_id, 'subscription_id' => $state['subscription_id'],
            'is_reserved' => ($feed['expiry_type'] ?? 'permanent') !== 'temporary',
        );
        if (!$data['is_reserved']) {
            $data['expiry_hours'] = max(1, absint($feed['expiry_hours'] ?? 24));
        }
        $context = array('source' => 'fluentcart', 'user_id' => (int) $customer->user_id, 'source_data' => array(
            'customer_id' => (int) $order->customer_id, 'item_id' => (int) $key,
            'variation_id' => $state['variation_id'], 'subscription_id' => $state['subscription_id'],
            'manage_subscription' => $feed['manage_subscription'] ?? 'yes',
        ));
        $states[$key]['status'] = 'requesting';
        $order->updateMeta(self::META_KEY, $states);
        try {
            $result = $this->site_manager->create_site_with_tracking($feed['snapshot_slug'], $data, $order->id, $state['product_id'], $feed['plan_id'] ?? '', $context);
        } catch (Throwable $error) {
            // A timeout/crash may occur after remote creation. Never auto-retry.
            $result = new WP_Error('request_uncertain', __('Provisioning was interrupted. Contact the store administrator before retrying.', 'fluentcart-instawp-provisioning'));
        }
        if (is_wp_error($result)) {
            $states[$key]['status'] = in_array($result->get_error_code(), array('tracking_failed', 'no_api_key'), true) ? 'failed' : 'needs_review';
            $states[$key]['error'] = $result->get_error_message();
            $order->addLog('InstaWP provisioning failed', $result->get_error_message(), 'error');
        } else {
            $states[$key]['status'] = 'requested';
            $states[$key]['site_id'] = $result['site_id'];
            $states[$key]['site_data'] = $result;
            unset($states[$key]['error']);
            $order->addLog('InstaWP site requested', $result['wp_url'] ?: __('Provisioning in progress.', 'fluentcart-instawp-provisioning'));
        }
        $order->updateMeta(self::META_KEY, $states);
        return $result;
    }

    public function create_deferred($order, $item_id, $username, $subdomain) {
        $options = get_option('fcip_options', array());
        if (!self::is_paid($order) || empty($options['api_key']) || ($options['enable_integration'] ?? 'yes') !== 'yes') {
            return new WP_Error('unpaid_order', __('Site setup is unavailable for this order.', 'fluentcart-instawp-provisioning'));
        }
        if (($username && (sanitize_user($username, true) !== $username || strlen($username) > 60))
            || ($subdomain && !preg_match('/^[a-z0-9][a-z0-9-]{1,28}[a-z0-9]$/i', $subdomain))) {
            return new WP_Error('invalid_credentials', __('Use a valid username and a 3–30 character subdomain with letters, numbers and internal hyphens.', 'fluentcart-instawp-provisioning'));
        }
        if (!add_option('fcip_fc_lock_' . $order->id, current_time('mysql'), '', false)) {
            return new WP_Error('order_locked', __('Site setup is already running or requires administrator review.', 'fluentcart-instawp-provisioning'));
        }
        try {
            $states = (array) $order->getMeta(self::META_KEY, array());
            $key = (string) absint($item_id);
            if (!isset($states[$key]) || !in_array($states[$key]['status'], array('deferred', 'failed'), true)) {
                return new WP_Error('already_requested', __('This item has already been submitted or requires administrator review.', 'fluentcart-instawp-provisioning'));
            }
            return $this->provision($order, $key, $states, $username, sanitize_title($subdomain));
        } finally {
            delete_option('fcip_fc_lock_' . $order->id);
        }
    }

    public function site_status_updated($site, $update) {
        $order = \FluentCart\App\Models\Order::find($site->order_id);
        if (!$order) {
            return;
        }
        $states = (array) $order->getMeta(self::META_KEY, array());
        foreach ($states as &$state) {
            if ((string) ($state['site_id'] ?? '') === (string) $site->site_id) {
                $state['site_data'] = array_merge($state['site_data'] ?? array(), $update);
            }
        }
        unset($state);
        $order->updateMeta(self::META_KEY, $states);
        $order->addLog('InstaWP site ' . $update['status'], $update['site_url'] ?? '');
    }
}
