<?php
/** Customer site credentials and deferred setup on FluentCart's thank-you page. */
defined('ABSPATH') || exit;

class IWP_FluentCart_Portal {
    private $processor;

    public function __construct($processor) {
        $this->processor = $processor;
        add_shortcode('iwp_fluentcart_sites', array($this, 'shortcode'));
        add_shortcode('iwp_onboarding', array($this, 'shortcode'));
        add_action('fluent_cart/receipt/thank_you/after_body', array($this, 'receipt'));
        add_action('wp_ajax_iwp_fc_create_site', array($this, 'create_site'));
        add_action('wp_ajax_nopriv_iwp_fc_create_site', array($this, 'create_site'));
        add_action('wp_ajax_iwp_fc_site_action', array($this, 'site_action'));
        add_action('wp_ajax_nopriv_iwp_fc_site_action', array($this, 'site_action'));
    }

    public static function can_access($order, $hash = '') {
        if (!$order) {
            return false;
        }
        if (current_user_can('manage_options')) {
            return true;
        }
        if (get_current_user_id() && $order->customer && (int) $order->customer->user_id === get_current_user_id()) {
            return true;
        }
        // FluentCart's receipt links use the order UUID as a bearer credential.
        return $hash !== '' && !empty($order->uuid) && hash_equals((string) $order->uuid, (string) $hash);
    }

    public function receipt($config) {
        $order = $config['order'] ?? null;
        $hash = sanitize_text_field(wp_unslash($_GET['order_hash'] ?? ''));
        if (self::can_access($order, $hash)) {
            echo $this->render_order($order); // Escaped inside render_order().
        }
    }

    public function shortcode($atts = array()) {
        $hash = sanitize_text_field(wp_unslash($_GET['order_hash'] ?? ''));
        if ($hash) {
            $order = \FluentCart\App\Models\Order::query()->where('uuid', $hash)->first();
            return self::can_access($order, $hash) ? $this->render_order($order) : '';
        }
        if (!get_current_user_id()) {
            return '<p>' . esc_html__('Log in or use your order confirmation link to view your sites.', 'iwp-wp-integration') . '</p>';
        }
        // Resolve the customer's current verified WordPress account association,
        // including guest purchases subsequently linked by FluentCart.
        $customers = \FluentCart\App\Models\Customer::query()->where('user_id', get_current_user_id())->get();
        $output = '';
        foreach ($customers as $customer) {
            $orders = \FluentCart\App\Models\Order::query()->where('customer_id', $customer->id)->where('type', '!=', 'renewal')->get();
            foreach ($orders as $order) {
                $output .= $this->render_order($order);
            }
        }
        return $output ?: '<p>' . esc_html__('No InstaWP sites found.', 'iwp-wp-integration') . '</p>';
    }

    private function render_order($order) {
        $states = (array) $order->getMeta(IWP_FluentCart_Order_Processor::META_KEY, array());
        $sites = IWP_FluentCart_Order_Processor::sites($order->id);
        if (!$states && !$sites) {
            return '';
        }
        wp_enqueue_script('iwp-fluentcart', IWP_PLUGIN_URL . 'assets/js/integrations/fluentcart/sites.js', array(), IWP_VERSION, true);
        $options = get_option('iwp_options', array());
        ob_start();
        echo '<section class="iwp-fluentcart-sites"><h3>' . esc_html__('Your InstaWP sites', 'iwp-wp-integration') . '</h3>';
        foreach ($sites as $site) {
            $context = json_decode($site->source_data ?? '', true) ?: array();
            $released = current_user_can('manage_options') || ($options['delay_customer_credentials'] ?? 'no') !== 'yes' || !empty($context['credentials_released']);
            echo '<div class="iwp-fluentcart-site"><p>' . esc_html($site->status) . '</p>';
            if ($site->site_url) {
                echo '<p><a href="' . esc_url($site->site_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($site->site_url) . '</a></p>';
            }
            if ($released && $site->wp_admin_url) {
                $login = !empty($site->s_hash) ? IWP_PLUGIN_APP_URL . '/wordpress-auto-login?site=' . rawurlencode($site->s_hash) : $site->wp_admin_url;
                echo '<p><a href="' . esc_url($login) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('WordPress admin', 'iwp-wp-integration') . '</a></p>';
            }
            if ($released && $site->wp_username) {
                echo '<p>' . esc_html__('Username: ', 'iwp-wp-integration') . esc_html($site->wp_username) . '</p>';
            }
            if ($released && $site->wp_password) {
                echo '<details><summary>' . esc_html__('Show password', 'iwp-wp-integration') . '</summary><code>' . esc_html($site->wp_password) . '</code></details>';
            }
            if (!$released) {
                echo '<p>' . esc_html__('Your login credentials will be released by the store administrator.', 'iwp-wp-integration') . '</p>';
            }
            if (in_array($site->status, array('completed', 'success'), true) && IWP_FluentCart_Order_Processor::is_paid($order)) {
                $this->action_form($order, $site, $options);
            }
            echo '</div>';
        }
        foreach ($states as $key => $state) {
            if (in_array($state['status'], array('needs_review', 'requesting'), true)) {
                echo '<p>' . esc_html__('Site setup is awaiting administrator review. Please contact support.', 'iwp-wp-integration') . '</p>';
            }
            if (!IWP_FluentCart_Order_Processor::is_paid($order) || !in_array($state['status'], array('deferred', 'failed'), true)) {
                continue;
            }
            echo '<form class="iwp-fc-setup" method="post" action="' . esc_url(admin_url('admin-ajax.php')) . '">';
            $hidden = array('action' => 'iwp_fc_create_site', 'order_id' => $order->id, 'order_hash' => $order->uuid, 'item_id' => $key, 'nonce' => wp_create_nonce('iwp_fc_setup_' . $order->id));
            foreach ($hidden as $name => $value) {
                echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
            }
            echo '<h4>' . esc_html($state['product_name']) . '</h4>';
            echo '<p><label>' . esc_html__('Admin username (optional)', 'iwp-wp-integration') . '<input name="username" maxlength="60" autocomplete="off"></label></p>';
            echo '<p><label>' . esc_html__('Subdomain (optional)', 'iwp-wp-integration') . '<input name="subdomain" minlength="3" maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9-]{1,28}[A-Za-z0-9]" autocomplete="off"></label></p>';
            echo '<button type="submit">' . esc_html__('Create my site', 'iwp-wp-integration') . '</button><p role="status" class="iwp-fc-message"></p></form>';
        }
        echo '</section>';
        return ob_get_clean();
    }

    private function action_form($order, $site, $options) {
        echo '<form class="iwp-fc-action" method="post" action="' . esc_url(admin_url('admin-ajax.php')) . '">';
        foreach (array('action' => 'iwp_fc_site_action', 'order_id' => $order->id, 'order_hash' => $order->uuid,
            'site_id' => $site->site_id, 'nonce' => wp_create_nonce('iwp_fc_setup_' . $order->id)) as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
        if (($options['enable_cache_purge'] ?? 'yes') === 'yes') {
            echo '<button type="submit" name="operation" value="purge">' . esc_html__('Clear cache', 'iwp-wp-integration') . '</button>';
        }
        if (!empty($site->plan_id)) {
            echo '<p><label>' . esc_html__('Custom domain', 'iwp-wp-integration') . '<input name="domain" placeholder="example.com"></label></p>';
            echo '<button type="submit" name="operation" value="domain">' . esc_html__('Map domain', 'iwp-wp-integration') . '</button>';
        }
        echo '<p role="status" class="iwp-fc-message"></p></form>';
    }

    public function site_action() {
        $id = absint($_POST['order_id'] ?? 0);
        $order = \FluentCart\App\Models\Order::find($id);
        $hash = sanitize_text_field(wp_unslash($_POST['order_hash'] ?? ''));
        $site_id = sanitize_text_field(wp_unslash($_POST['site_id'] ?? ''));
        $site = IWP_Sites_Model::get_by_site_id($site_id);
        if (!self::can_access($order, $hash) || !check_ajax_referer('iwp_fc_setup_' . $id, 'nonce', false)
            || !IWP_FluentCart_Order_Processor::is_paid($order) || !$site || $site->source !== 'fluentcart'
            || (int) $site->order_id !== $id || !in_array($site->status, array('completed', 'success'), true)) {
            wp_send_json_error(array('message' => __('You cannot manage this site.', 'iwp-wp-integration')), 403);
            return;
        }
        $options = get_option('iwp_options', array());
        if (($options['enable_integration'] ?? 'yes') !== 'yes') {
            wp_send_json_error(array('message' => __('Site management is disabled.', 'iwp-wp-integration')), 403);
            return;
        }
        $operation = sanitize_key($_POST['operation'] ?? '');
        if ($operation === 'purge' && ($options['enable_cache_purge'] ?? 'yes') === 'yes') {
            $result = IWP_Service::purge_site_cache($site_id);
        } elseif ($operation === 'domain' && !empty($site->plan_id)) {
            $domain = strtolower(trim(sanitize_text_field(wp_unslash($_POST['domain'] ?? ''))));
            if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || strpos($domain, '.') === false) {
                wp_send_json_error(array('message' => __('Enter a valid domain name.', 'iwp-wp-integration')), 400);
                return;
            }
            $result = IWP_Service::add_domain_to_site($site_id, $domain);
            if (!is_wp_error($result)) {
                IWP_Sites_Model::update($site_id, array('site_url' => 'https://' . $domain, 'wp_admin_url' => 'https://' . $domain . '/wp-admin/'));
                $order->addLog('InstaWP domain mapped', $domain);
            }
        } else {
            wp_send_json_error(array('message' => __('This action is unavailable.', 'iwp-wp-integration')), 400);
            return;
        }
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
            return;
        }
        wp_send_json_success(array('message' => __('Site updated.', 'iwp-wp-integration')));
    }

    public function create_site() {
        $id = absint($_POST['order_id'] ?? 0);
        $hash = sanitize_text_field(wp_unslash($_POST['order_hash'] ?? ''));
        $order = \FluentCart\App\Models\Order::find($id);
        if (!self::can_access($order, $hash) || !check_ajax_referer('iwp_fc_setup_' . $id, 'nonce', false)) {
            wp_send_json_error(array('message' => __('You cannot access this order.', 'iwp-wp-integration')), 403);
            return;
        }
        $result = $this->processor->create_deferred($order, absint($_POST['item_id'] ?? 0),
            sanitize_text_field(wp_unslash($_POST['username'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['subdomain'] ?? '')));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
            return;
        }
        wp_send_json_success(array('message' => __('Your site has been requested. Refresh this page shortly to see its status.', 'iwp-wp-integration')));
    }
}
