<?php
/** Customer site credentials and deferred setup on FluentCart's thank-you page. */
defined('ABSPATH') || exit;

class FCIP_FluentCart_Portal {
    private $processor;

    public function __construct($processor) {
        $this->processor = $processor;
        add_shortcode('fcip_sites', array($this, 'shortcode'));
        add_action('fluent_cart/receipt/thank_you/after_body', array($this, 'receipt'));
        add_action('wp_ajax_fcip_fc_create_site', array($this, 'create_site'));
        add_action('wp_ajax_nopriv_fcip_fc_create_site', array($this, 'create_site'));
        add_action('wp_ajax_fcip_fc_site_action', array($this, 'site_action'));
        add_action('wp_ajax_nopriv_fcip_fc_site_action', array($this, 'site_action'));
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
            return '<p>' . esc_html__('Log in or use your order confirmation link to view your sites.', 'fluentcart-instawp-provisioning') . '</p>';
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
        return $output ?: '<p>' . esc_html__('No InstaWP sites found.', 'fluentcart-instawp-provisioning') . '</p>';
    }

    private function render_order($order) {
        $states = (array) $order->getMeta(FCIP_FluentCart_Order_Processor::META_KEY, array());
        $sites = FCIP_FluentCart_Order_Processor::sites($order->id);
        if (!$states && !$sites) {
            return '';
        }
        wp_enqueue_script('fcip-fluentcart', FCIP_PLUGIN_URL . 'assets/js/integrations/fluentcart/sites.js', array(), FCIP_VERSION, true);
        $options = get_option('fcip_options', array());
        ob_start();
        echo '<section class="fcip-fluentcart-sites"><h3>' . esc_html__('Your InstaWP sites', 'fluentcart-instawp-provisioning') . '</h3>';
        foreach ($sites as $site) {
            $context = json_decode($site->source_data ?? '', true) ?: array();
            $released = current_user_can('manage_options') || ($options['delay_customer_credentials'] ?? 'no') !== 'yes' || !empty($context['credentials_released']);
            echo '<div class="fcip-fluentcart-site"><p>' . esc_html($site->status) . '</p>';
            if ($site->site_url) {
                echo '<p><a href="' . esc_url($site->site_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($site->site_url) . '</a></p>';
            }
            if ($released && $site->wp_admin_url) {
                $login = !empty($site->s_hash) ? FCIP_PLUGIN_APP_URL . '/wordpress-auto-login?site=' . rawurlencode($site->s_hash) : $site->wp_admin_url;
                echo '<p><a href="' . esc_url($login) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('WordPress admin', 'fluentcart-instawp-provisioning') . '</a></p>';
            }
            if ($released && $site->wp_username) {
                echo '<p>' . esc_html__('Username: ', 'fluentcart-instawp-provisioning') . esc_html($site->wp_username) . '</p>';
            }
            if ($released && $site->wp_password) {
                echo '<details><summary>' . esc_html__('Show password', 'fluentcart-instawp-provisioning') . '</summary><code>' . esc_html($site->wp_password) . '</code></details>';
            }
            if (!$released) {
                echo '<p>' . esc_html__('Your login credentials will be released by the store administrator.', 'fluentcart-instawp-provisioning') . '</p>';
            }
            if (in_array($site->status, array('completed', 'success'), true) && FCIP_FluentCart_Order_Processor::is_paid($order)) {
                $this->action_form($order, $site, $options);
            }
            echo '</div>';
        }
        foreach ($states as $key => $state) {
            if (in_array($state['status'], array('needs_review', 'requesting'), true)) {
                echo '<p>' . esc_html__('Site setup is awaiting administrator review. Please contact support.', 'fluentcart-instawp-provisioning') . '</p>';
            }
            if (!FCIP_FluentCart_Order_Processor::is_paid($order) || !in_array($state['status'], array('deferred', 'failed'), true)) {
                continue;
            }
            echo '<form class="fcip-fc-setup" method="post" action="' . esc_url(admin_url('admin-ajax.php')) . '">';
            $hidden = array('action' => 'fcip_fc_create_site', 'order_id' => $order->id, 'order_hash' => $order->uuid, 'item_id' => $key, 'nonce' => wp_create_nonce('fcip_fc_setup_' . $order->id));
            foreach ($hidden as $name => $value) {
                echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
            }
            echo '<h4>' . esc_html($state['product_name']) . '</h4>';
            echo '<p><label>' . esc_html__('Admin username (optional)', 'fluentcart-instawp-provisioning') . '<input name="username" maxlength="60" autocomplete="off"></label></p>';
            echo '<p><label>' . esc_html__('Subdomain (optional)', 'fluentcart-instawp-provisioning') . '<input name="subdomain" minlength="3" maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9-]{1,28}[A-Za-z0-9]" autocomplete="off"></label></p>';
            echo '<button type="submit">' . esc_html__('Create my site', 'fluentcart-instawp-provisioning') . '</button><p role="status" class="fcip-fc-message"></p></form>';
        }
        echo '</section>';
        return ob_get_clean();
    }

    private function action_form($order, $site, $options) {
        echo '<form class="fcip-fc-action" method="post" action="' . esc_url(admin_url('admin-ajax.php')) . '">';
        foreach (array('action' => 'fcip_fc_site_action', 'order_id' => $order->id, 'order_hash' => $order->uuid,
            'site_id' => $site->site_id, 'nonce' => wp_create_nonce('fcip_fc_setup_' . $order->id)) as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
        if (($options['enable_cache_purge'] ?? 'yes') === 'yes') {
            echo '<button type="submit" name="operation" value="purge">' . esc_html__('Clear cache', 'fluentcart-instawp-provisioning') . '</button>';
        }
        if (!empty($site->plan_id)) {
            echo '<p><label>' . esc_html__('Custom domain', 'fluentcart-instawp-provisioning') . '<input name="domain" placeholder="example.com"></label></p>';
            echo '<button type="submit" name="operation" value="domain">' . esc_html__('Map domain', 'fluentcart-instawp-provisioning') . '</button>';
        }
        echo '<p role="status" class="fcip-fc-message"></p></form>';
    }

    public function site_action() {
        $id = absint($_POST['order_id'] ?? 0);
        $order = \FluentCart\App\Models\Order::find($id);
        $hash = sanitize_text_field(wp_unslash($_POST['order_hash'] ?? ''));
        $site_id = sanitize_text_field(wp_unslash($_POST['site_id'] ?? ''));
        $site = FCIP_Sites_Model::get_by_site_id($site_id);
        if (!self::can_access($order, $hash) || !check_ajax_referer('fcip_fc_setup_' . $id, 'nonce', false)
            || !FCIP_FluentCart_Order_Processor::is_paid($order) || !$site || $site->source !== 'fluentcart'
            || (int) $site->order_id !== $id || !in_array($site->status, array('completed', 'success'), true)) {
            wp_send_json_error(array('message' => __('You cannot manage this site.', 'fluentcart-instawp-provisioning')), 403);
            return;
        }
        $options = get_option('fcip_options', array());
        if (($options['enable_integration'] ?? 'yes') !== 'yes') {
            wp_send_json_error(array('message' => __('Site management is disabled.', 'fluentcart-instawp-provisioning')), 403);
            return;
        }
        $operation = sanitize_key($_POST['operation'] ?? '');
        if ($operation === 'purge' && ($options['enable_cache_purge'] ?? 'yes') === 'yes') {
            $result = FCIP_Service::purge_site_cache($site_id);
        } elseif ($operation === 'domain' && !empty($site->plan_id)) {
            $domain = strtolower(trim(sanitize_text_field(wp_unslash($_POST['domain'] ?? ''))));
            if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || strpos($domain, '.') === false) {
                wp_send_json_error(array('message' => __('Enter a valid domain name.', 'fluentcart-instawp-provisioning')), 400);
                return;
            }
            $result = FCIP_Service::add_domain_to_site($site_id, $domain);
            if (!is_wp_error($result)) {
                FCIP_Sites_Model::update($site_id, array('site_url' => 'https://' . $domain, 'wp_admin_url' => 'https://' . $domain . '/wp-admin/'));
                $order->addLog('InstaWP domain mapped', $domain);
            }
        } else {
            wp_send_json_error(array('message' => __('This action is unavailable.', 'fluentcart-instawp-provisioning')), 400);
            return;
        }
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
            return;
        }
        wp_send_json_success(array('message' => __('Site updated.', 'fluentcart-instawp-provisioning')));
    }

    public function create_site() {
        $id = absint($_POST['order_id'] ?? 0);
        $hash = sanitize_text_field(wp_unslash($_POST['order_hash'] ?? ''));
        $order = \FluentCart\App\Models\Order::find($id);
        if (!self::can_access($order, $hash) || !check_ajax_referer('fcip_fc_setup_' . $id, 'nonce', false)) {
            wp_send_json_error(array('message' => __('You cannot access this order.', 'fluentcart-instawp-provisioning')), 403);
            return;
        }
        $result = $this->processor->create_deferred($order, absint($_POST['item_id'] ?? 0),
            sanitize_text_field(wp_unslash($_POST['username'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['subdomain'] ?? '')));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
            return;
        }
        wp_send_json_success(array('message' => __('Your site has been requested. Refresh this page shortly to see its status.', 'fluentcart-instawp-provisioning')));
    }
}
