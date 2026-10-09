<?php
/** Test the real shared reservation service against InstaWP response envelopes. */
define('ABSPATH', __DIR__);
function get_option($key, $default = false) { return array('api_key' => 'test'); }
function __($message, $domain = '') { return $message; }
function is_wp_error($result) { return $result instanceof WP_Error; }
class WP_Error {
    public function __construct($code, $message, $data = null) {}
}
class FCIP_Logger {
    public static function info(...$args) {}
    public static function warning(...$args) {}
}
class FCIP_API_Client {
    public static $details = array('data' => array('is_reserved' => true));
    public static $toggles = 0;
    public function set_api_key($key) {}
    public function get_site_details($id) { return self::$details; }
    public function toggle_site_reservation($id) { self::$toggles++; return array('data' => array('is_reserved' => true)); }
    public function disable_demo_helper($id) { return array('message' => 'Disabled'); }
}
require __DIR__ . '/../includes/core/class-fcip-service.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$result = FCIP_Service::set_permanent(123, true);
check(!is_wp_error($result) && !$result['changed'] && FCIP_API_Client::$toggles === 0, 'Nested permanent reservation must not be toggled on repeated payment');
FCIP_Service::set_permanent(123, false);
check(FCIP_API_Client::$toggles === 1, 'Nested permanent reservation is toggled on cancellation');
FCIP_API_Client::$details = array('data' => array('is_reserved' => 'false'));
FCIP_Service::set_permanent(123, false);
check(FCIP_API_Client::$toggles === 1, 'String false remains temporary');
FCIP_Service::set_permanent(123, true);
check(FCIP_API_Client::$toggles === 2, 'Temporary site becomes permanent on activation');
FCIP_API_Client::$details = array('is_reserved' => true);
FCIP_Service::set_permanent(123, true);
check(FCIP_API_Client::$toggles === 2, 'Flat API response reports its reservation status');
FCIP_API_Client::$details = array('data' => array());
check(is_wp_error(FCIP_Service::set_permanent(123, true)) && FCIP_API_Client::$toggles === 2, 'Unknown reservation status must not toggle a site');
echo "Reservation envelope and idempotency checks passed.\n";
