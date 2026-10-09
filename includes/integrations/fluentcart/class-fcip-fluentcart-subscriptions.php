<?php
/** Reservation and hosting-plan changes for FluentCart subscriptions. */
defined('ABSPATH') || exit;

class FCIP_FluentCart_Subscriptions {
    public function __construct() {
        foreach (array('activated', 'reactivated', 'renewed', 'resumed') as $event) {
            add_action('fluent_cart/subscription_' . $event, array($this, 'activate'), 20);
        }
        foreach (array('canceled', 'paused', 'renewal_failed', 'eot', 'expired_validity') as $event) {
            add_action('fluent_cart/subscription_' . $event, array($this, 'deactivate'), 20);
        }
        add_action('fluent_cart/plan_changed', array($this, 'plan_changed'), 20);
        add_action('fcip_fluentcart_site_status_updated', array($this, 'sync_completed_site'), 20, 2);
    }

    public function activate($data) {
        $this->set_reservation($data['subscription'] ?? null, true);
    }

    public function deactivate($data) {
        $subscription = $data['subscription'] ?? null;
        // A replaced subscription must not expire the site of its successor.
        if ($subscription && !empty($subscription->config['upgraded_to_sub_id'])) {
            return;
        }
        $this->set_reservation($subscription, false);
    }

    private function enabled() {
        $options = get_option('fcip_options', array());
        return ($options['enable_integration'] ?? 'yes') === 'yes' && !empty($options['api_key']);
    }

    private function set_reservation($subscription, $permanent) {
        if (!$subscription || !$this->enabled()) {
            return;
        }
        $order = \FluentCart\App\Models\Order::find($subscription->parent_order_id);
        if (!$order) {
            return;
        }
        foreach (FCIP_FluentCart_Order_Processor::sites($order->id) as $site) {
            $context = json_decode($site->source_data ?? '', true) ?: array();
            $matches = !empty($context['subscription_id'])
                ? (int) $context['subscription_id'] === (int) $subscription->id
                : (int) ($context['variation_id'] ?? 0) === (int) $subscription->variation_id;
            if (!$matches || ($context['manage_subscription'] ?? 'no') !== 'yes'
                || !in_array($site->status, array('completed', 'success'), true)) {
                continue;
            }
            $result = FCIP_Service::set_permanent($site->site_id, $permanent);
            if (is_wp_error($result)) {
                $order->addLog('InstaWP subscription update failed', $result->get_error_message(), 'error');
            } else {
                FCIP_Sites_Model::update($site->site_id, array('is_reserved' => (int) $permanent));
                $order->addLog('InstaWP subscription updated', $permanent ? 'Site is permanent.' : 'Site is temporary.');
            }
        }
    }

    public function sync_completed_site($site, $update) {
        if (($update['status'] ?? '') !== 'completed') {
            return;
        }
        $context = json_decode($site->source_data ?? '', true) ?: array();
        $subscription = !empty($context['subscription_id']) ? \FluentCart\App\Models\Subscription::find($context['subscription_id']) : null;
        if ($subscription) {
            $this->set_reservation($subscription, in_array($subscription->status, array('active', 'trialing'), true));
        }
    }

    public function plan_changed($data) {
        if (!$this->enabled()) {
            return;
        }
        // Unlike subscription events, PlanChanged::toArray() returns arrays.
        $order_data = $data['order'] ?? array();
        $old = $data['old_variation'] ?? array();
        $new = $data['new_variation'] ?? array();
        $order = \FluentCart\App\Models\Order::find(absint($order_data['id'] ?? 0));
        if (!$order || !FCIP_FluentCart_Order_Processor::is_paid($order) || empty($old['id']) || empty($new['id'])) {
            return;
        }
        $feeds = \FluentCart\App\Models\ProductMeta::query()->where('object_id', $new['post_id'])
            ->where('object_type', 'product_integration')->where('meta_key', 'fcip')->get();
        $plan_ids = array();
        foreach ($feeds as $meta) {
            $feed = $meta->meta_value;
            $variants = array_map('absint', (array) ($feed['conditional_variation_ids'] ?? array()));
            if (($feed['enabled'] ?? 'no') === 'yes' && !empty($feed['plan_id'])
                && (!$variants || in_array((int) $new['id'], $variants, true))) {
                $plan_ids[] = (string) $feed['plan_id'];
            }
        }
        $plan_ids = array_values(array_unique($plan_ids));
        if (count($plan_ids) !== 1) {
            $order->addLog('InstaWP plan change skipped', 'Configure one unambiguous InstaWP hosting plan for the new variant.', 'error');
            return;
        }
        if (!add_option('fcip_fc_lock_' . $order->id, current_time('mysql'), '', false)) {
            return;
        }
        try {
            foreach (FCIP_FluentCart_Order_Processor::sites($order->id) as $site) {
                $context = json_decode($site->source_data ?? '', true) ?: array();
                if ((int) ($context['variation_id'] ?? 0) !== (int) $old['id']
                    || !in_array($site->status, array('completed', 'success'), true)) {
                    continue;
                }
                if ((string) $site->plan_id !== $plan_ids[0]) {
                    $result = FCIP_Service::upgrade_site_plan($site->site_id, $plan_ids[0]);
                    if (is_wp_error($result)) {
                        $order->addLog('InstaWP plan change failed', $result->get_error_message(), 'error');
                        continue;
                    }
                    FCIP_Sites_Model::update_plan($site->site_id, $plan_ids[0], array('order_id' => $order->id, 'product_id' => $new['post_id'], 'upgrade_response' => $result));
                }
                $context['variation_id'] = (int) $new['id'];
                $replacement = \FluentCart\App\Models\Subscription::query()->where('parent_order_id', $order->id)->where('variation_id', $new['id'])->first();
                if ($replacement) {
                    $context['subscription_id'] = (int) $replacement->id;
                }
                FCIP_Sites_Model::update($site->site_id, array('product_id' => $new['post_id'], 'source_data' => $context));
                $order->addLog('InstaWP hosting plan changed', 'Plan: ' . $plan_ids[0]);
            }
        } finally {
            delete_option('fcip_fc_lock_' . $order->id);
        }
    }
}
