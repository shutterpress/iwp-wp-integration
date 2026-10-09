<?php
/** Run with php tests/fluentcart.php; no WordPress/WooCommerce installation needed. */
namespace FluentCart\App\Models {
    class Query {
        private $rows;
        public function __construct($rows) { $this->rows = $rows; }
        public function where($key, $value) {
            $this->rows = array_filter($this->rows, function ($row) use ($key, $value) { return $row->$key == $value; });
            return $this;
        }
        public function first() { return reset($this->rows) ?: null; }
        public function get() { return array_values($this->rows); }
    }
    class Subscription {
        public static $rows = array();
        public $id, $parent_order_id, $variation_id, $status, $config = array();
        public static function query() { return new Query(self::$rows); }
        public static function find($id) { return self::$rows[$id] ?? null; }
    }
    class ProductMeta {
        public static $rows = array();
        public $id, $object_id, $object_type = 'product_integration', $meta_key = 'fcip', $meta_value;
        public static function query() { return new Query(self::$rows); }
        public static function find($id) { return self::$rows[$id] ?? null; }
    }
    class Order {
        public static $rows = array();
        public $id, $customer_id = 17, $uuid = 'secret-order-hash', $type = 'onetime', $payment_status = 'paid', $status = 'completed';
        public $customer, $order_items, $meta = array(), $logs = array(), $config = array();
        public function __construct($id) {
            $this->id = $id;
            $this->customer = (object) array('user_id' => 42, 'email' => 'customer@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe');
            $this->order_items = array((object) array('id' => 99, 'post_id' => 55, 'object_id' => 66, 'payment_type' => 'onetime', 'title' => 'Website', 'post_title' => 'Website', 'quantity' => 3));
            self::$rows[$id] = $this;
        }
        public static function find($id) { return self::$rows[$id] ?? null; }
        public function getMeta($key, $default = false) { return $this->meta[$key] ?? $default; }
        public function updateMeta($key, $value) { $this->meta[$key] = $value; }
        public function addLog($title, $description = '', $type = 'info') { $this->logs[] = array($title, $description, $type); }
    }
}
namespace FluentCart\App\Modules\Integrations {
    abstract class BaseIntegrationManager {
        protected $runOnBackgroundForProduct = true;
        public $scopes = array('global', 'product'), $disableGlobalSettings = false, $logo;
        protected $description;
        public function __construct($title, $key, $priority = 10) {}
        abstract public function processAction($order, $data);
        abstract public function getIntegrationDefaults($settings);
        abstract public function getSettingsFields($settings, $args = array());
    }
}
namespace {
    define('ABSPATH', __DIR__);
    define('FCIP_PLUGIN_URL', 'https://store.example.com/plugins/fcip/');
    define('FCIP_VERSION', '1.0.0');
    $options = array('api_key' => 'test-key', 'enable_integration' => 'yes', 'auto_create_sites_on_purchase' => 'yes');
    $locks = array(); $hooks = array(); $current_user = 0; $admin = false;
    function get_option($key, $default = false) { global $options; return $key === 'fcip_options' ? $options : $default; }
    function add_option($key, $value, $deprecated = '', $autoload = false) { global $locks; if (isset($locks[$key])) return false; $locks[$key] = $value; return true; }
    function delete_option($key) { global $locks; unset($locks[$key]); }
    function add_action($name, $callback, $priority = 10, $accepted = 1) { global $hooks; $hooks[$name][] = $callback; }
    function add_filter($name, $callback, $priority = 10, $accepted = 1) { add_action($name, $callback, $priority, $accepted); }
    function add_shortcode($name, $callback) { global $shortcodes; $shortcodes[$name] = $callback; }
    function do_action($name, ...$args) { global $hooks; foreach ($hooks[$name] ?? array() as $callback) call_user_func_array($callback, $args); }
    function wp_next_scheduled($name) { return false; }
    function wp_schedule_event($time, $schedule, $name) {}
    function current_time($format) { return '2026-10-09 12:00:00'; }
    function absint($value) { return abs((int) $value); }
    function sanitize_text_field($value) { return trim(strip_tags($value)); }
    function sanitize_title($value) { return strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $value)); }
    function sanitize_user($value, $strict = false) { return preg_replace('/[^a-zA-Z0-9_.@-]/', '', $value); }
    function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
    function __($value, $domain = '') { return $value; }
    function trailingslashit($value) { return rtrim($value, '/') . '/'; }
    function is_wp_error($value) { return $value instanceof WP_Error; }
    function current_user_can($capability) { global $admin; return $admin; }
    function get_current_user_id() { global $current_user; return $current_user; }
    class WP_Error {
        private $code, $message;
        public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
    }
    class FCIP_Logger {
        public static function info(...$args) {}
        public static function error(...$args) {}
        public static function debug(...$args) {}
        public static function warning(...$args) {}
    }
    class FCIP_API_Client {
        public static $requests = array(), $mode = 'ready';
        public function create_site_from_snapshot($snapshot, $data, $plan) {
            self::$requests[] = array($snapshot, $data, $plan);
            if (self::$mode === 'timeout') return new WP_Error('http_request_failed', 'Request timed out');
            if (self::$mode === 'throw') throw new \RuntimeException('Connection interrupted');
            return array('data' => array('id' => 1000 + count(self::$requests), 'wp_url' => 'https://site.example.com', 'wp_username' => 'janedoe', 'wp_password' => 'secret', 'is_pool' => self::$mode === 'ready', 'task_id' => 'task', 'status' => self::$mode === 'ready' ? 0 : 1));
        }
        public function get_task_status($id) { return array('data' => array('status' => 'completed')); }
        public function get_site_details($id) { return array('data' => array('site_meta' => array('wp_password' => 'fresh-password'))); }
        public static function flatten_site_details($response) { return array('wp_password' => $response['data']['site_meta']['wp_password']); }
    }
    class FCIP_Sites_Model {
        const STATUS_TRASHED = 'trashed';
        public static $rows = array(), $fail_tracking = false;
        public static function create($data) {
            if (self::$fail_tracking) return false;
            $data += array('site_url' => '', 'wp_username' => '', 'wp_password' => '', 'wp_admin_url' => '', 's_hash' => '', 'task_id' => null, 'api_response' => null);
            $data['source_data'] = json_encode($data['source_data']);
            self::$rows[(string) $data['site_id']] = (object) $data;
            return count(self::$rows);
        }
        public static function update($id, $data) {
            $row = self::$rows[(string) $id];
            if (isset($data['source_data']) && is_array($data['source_data'])) $data['source_data'] = json_encode($data['source_data']);
            foreach ($data as $key => $value) $row->$key = $value;
            unset(self::$rows[(string) $id]);
            self::$rows[(string) $row->site_id] = $row;
            return true;
        }
        public static function get_by_site_id($id) { return self::$rows[(string) $id] ?? null; }
        public static function get_by_order_id($id) { return array_values(array_filter(self::$rows, function ($row) use ($id) { return $row->order_id == $id; })); }
        public static function get_pending_sites() { return array_filter(self::$rows, function ($row) { return in_array($row->status, array('creating', 'progress'), true) && $row->task_id; }); }
        public static function update_plan($id, $plan, $data) { return self::update($id, array('plan_id' => $plan)); }
    }
    class FCIP_Service {
        public static $reservations = array(), $plans = array();
        public static function set_permanent($id, $permanent) { self::$reservations[] = array($id, $permanent); return array('changed' => true); }
        public static function upgrade_site_plan($id, $plan) { self::$plans[] = array($id, $plan); return array('success' => true); }
    }
    require __DIR__ . '/../includes/core/class-fcip-site-manager.php';
    foreach (array('order-processor', 'integration', 'portal', 'subscriptions') as $file) require __DIR__ . '/../includes/integrations/fluentcart/class-fcip-fluentcart-' . $file . '.php';
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    function count_requests() { return count(FCIP_API_Client::$requests); }
    $processor = new FCIP_FluentCart_Order_Processor();
    $shortcodes = array();
    new FCIP_FluentCart_Portal($processor);
    check(array_keys($shortcodes) === array('fcip_sites'), 'Portal registers only its standalone shortcode');
    $integration = new FCIP_FluentCart_Integration($processor);
    $feed = $integration->getIntegrationDefaults(array());
    $feed['snapshot_slug'] = 'shop-template'; $feed['plan_id'] = 'hosting-plan';

    $order = new \FluentCart\App\Models\Order(1);
    $processor->process_feed($order, 55, $feed, 7);
    check(count_requests() === 1, 'Paid order provisions a site');
    check(FCIP_API_Client::$requests[0][0] === 'shop-template' && FCIP_API_Client::$requests[0][2] === 'hosting-plan', 'Snapshot and plan passed to existing API');
    $site = FCIP_FluentCart_Order_Processor::sites(1)[0];
    check($site->user_id === 42 && $site->source === 'fluentcart', 'WordPress user is distinct from FluentCart customer ID');
    check(json_decode($site->source_data, true)['item_id'] === 99, 'Line-item context saved');
    $processor->process_feed($order, 55, $feed, 7);
    $processor->process_feed($order, 55, $feed, 8);
    check(count_requests() === 1, 'Repeated payment hooks, duplicate feeds and quantity do not duplicate sites');

    foreach (array('pending', 'partially_paid', 'refunded', 'failed') as $status) {
        $unpaid = new \FluentCart\App\Models\Order(10 + count(\FluentCart\App\Models\Order::$rows));
        $unpaid->payment_status = $status;
        $processor->process_feed($unpaid, 55, $feed, 7);
    }
    $renewal = new \FluentCart\App\Models\Order(20); $renewal->type = 'renewal';
    $processor->process_feed($renewal, 55, $feed, 7);
    $disabled = new \FluentCart\App\Models\Order(21); $options['auto_create_sites_on_purchase'] = 'no';
    $processor->process_feed($disabled, 55, $feed, 7); $options['auto_create_sites_on_purchase'] = 'yes';
    $processor->process_feed($disabled, 55, array_merge($feed, array('conditional_variation_ids' => array(999))), 7);
    add_option('fcip_fc_lock_21', true);
    $processor->process_feed($disabled, 55, $feed, 7); delete_option('fcip_fc_lock_21');
    check(count_requests() === 1, 'Unpaid orders, renewals, disabled auto-create, wrong variants and concurrent workers cannot provision');

    $deferred = new \FluentCart\App\Models\Order(30);
    $defer_feed = array_merge($feed, array('defer_creation' => 'yes', 'expiry_type' => 'temporary', 'expiry_hours' => 12));
    $processor->process_feed($deferred, 55, $defer_feed, 7);
    check(count_requests() === 1, 'Deferred setup does not call InstaWP before customer submission');
    check(is_wp_error($processor->create_deferred($deferred, 99, 'invalid user', 'shop')), 'Invalid usernames rejected');
    check(is_wp_error($processor->create_deferred($deferred, 99, 'owner', 'a')), 'Invalid subdomains rejected');
    $result = $processor->create_deferred($deferred, 99, 'owner', 'my-store');
    check(!is_wp_error($result) && count_requests() === 2, 'Deferred setup provisions exactly once');
    $request = FCIP_API_Client::$requests[1][1];
    check($request['user_name'] === 'owner' && $request['site_name'] === 'my-store' && $request['expiry_hours'] === 12 && !$request['is_reserved'], 'Customer credentials and temporary expiry reach InstaWP');
    check(is_wp_error($processor->create_deferred($deferred, 99, 'owner', 'my-store')) && count_requests() === 2, 'Repeated setup submissions cannot provision twice');

    FCIP_API_Client::$mode = 'timeout'; $uncertain = new \FluentCart\App\Models\Order(31);
    $processor->process_feed($uncertain, 55, $feed, 7);
    check($uncertain->getMeta(FCIP_FluentCart_Order_Processor::META_KEY)['99']['status'] === 'needs_review', 'Ambiguous remote errors require review');
    check(is_wp_error($processor->create_deferred($uncertain, 99, '', '')), 'Ambiguous remote request cannot be retried');
    FCIP_API_Client::$mode = 'ready'; FCIP_Sites_Model::$fail_tracking = true;
    $tracking = new \FluentCart\App\Models\Order(32); $before = count_requests();
    $processor->process_feed($tracking, 55, $feed, 7);
    check(count_requests() === $before, 'Tracking failure stops API call'); FCIP_Sites_Model::$fail_tracking = false;

    check(!FCIP_FluentCart_Portal::can_access($order), 'Anonymous access needs a receipt credential');
    check(!FCIP_FluentCart_Portal::can_access($order, 'wrong'), 'Wrong order hash denied');
    check(FCIP_FluentCart_Portal::can_access($order, 'secret-order-hash'), 'Guest receipt credential accepted');
    $current_user = 50; check(!FCIP_FluentCart_Portal::can_access($order), 'Other account denied');
    $current_user = 42; check(FCIP_FluentCart_Portal::can_access($order), 'Owner account accepted'); $current_user = 0;

    $subscription = new \FluentCart\App\Models\Subscription();
    $subscription->id = 3; $subscription->parent_order_id = 40; $subscription->variation_id = 66; $subscription->status = 'active';
    \FluentCart\App\Models\Subscription::$rows[3] = $subscription;
    FCIP_API_Client::$mode = 'pending'; $async = new \FluentCart\App\Models\Order(40); $async->type = 'subscription';
    $processor->process_feed($async, 55, $feed, 7);
    $manager = new FCIP_Site_Manager(); $manager->check_pending_sites();
    $site = FCIP_FluentCart_Order_Processor::sites(40)[0];
    check($site->status === 'completed' && $site->wp_password === 'fresh-password', 'Async polling fetches credentials without any WooCommerce functions');
    $subscriptions = new FCIP_FluentCart_Subscriptions();
    $subscriptions->activate(array('subscription' => $subscription));
    $subscriptions->deactivate(array('subscription' => $subscription));
    check(count(FCIP_Service::$reservations) === 2 && FCIP_Service::$reservations[0][1] === true && FCIP_Service::$reservations[1][1] === false, 'Subscription payment/status events change site reservations');
    $subscription->config['upgraded_to_sub_id'] = 4;
    $subscriptions->deactivate(array('subscription' => $subscription));
    check(count(FCIP_Service::$reservations) === 2, 'Superseded subscription cannot expire successor site');

    $mapping = new \FluentCart\App\Models\ProductMeta(); $mapping->id = 7; $mapping->object_id = 55;
    $mapping->meta_value = array_merge($feed, array('plan_id' => 'higher-plan', 'conditional_variation_ids' => array(77)));
    \FluentCart\App\Models\ProductMeta::$rows[7] = $mapping;
    $subscriptions->plan_changed(array('order' => array('id' => 40), 'old_variation' => array('id' => 66), 'new_variation' => array('id' => 77, 'post_id' => 55)));
    check(count(FCIP_Service::$plans) === 1 && FCIP_Service::$plans[0][1] === 'higher-plan', 'Plan changes upgrade existing site instead of provisioning');
    $subscriptions->plan_changed(array('order' => array('id' => 40), 'old_variation' => array('id' => 66), 'new_variation' => array('id' => 77, 'post_id' => 55)));
    check(count(FCIP_Service::$plans) === 1, 'Repeated plan events do not change the site twice');
    $validated = $integration->validateFeedData(array_merge($feed, array('event_trigger' => array('order_created'))), array());
    check($validated['event_trigger'] === array('order_paid_done'), 'Feed saving cannot enable pre-payment provisioning');
    echo "FluentCart provisioning, lifecycle, ownership and deduplication checks passed.\n";
}
