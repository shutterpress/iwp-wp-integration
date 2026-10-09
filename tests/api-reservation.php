<?php
/** Check the real API client's reservation transport without sending live requests. */
define('ABSPATH', __DIR__);
define('FCIP_PLUGIN_APP_URL', 'https://app.instawp.io');
$options = array('api_key' => 'test-key', 'selected_team_id' => 7);
$requests = array();
$response_code = 200;
function get_option($key, $default = false) { global $options; return $key === 'fcip_options' ? $options : $default; }
function __($value, $domain = '') { return $value; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_request($url, $args) {
    global $requests, $response_code;
    $requests[] = array($url, $args);
    return array('code' => $response_code, 'body' => json_encode($response_code === 200 ? array('data' => array('is_reserved' => true)) : array('message' => 'api_not_found')));
}
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_remote_retrieve_headers($response) { return array(); }
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
class FCIP_Logger {
    public static function debug(...$args) {}
    public static function info(...$args) {}
    public static function error(...$args) {}
}
require __DIR__ . '/../includes/core/class-fcip-api-client.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$client = new FCIP_API_Client();
$result = $client->toggle_site_reservation(123);
check(!is_wp_error($result) && $result['data']['is_reserved'] === true, 'Reservation response is returned');
check($requests[0][0] === 'https://app.instawp.io/api/v2/sites/123/reserve-toggle?team_id=7', 'Reservation uses the v2 site route and selected team');
check($requests[0][1]['method'] === 'PUT', 'Reservation toggle uses PUT, not the unsupported POST route');
check($requests[0][1]['headers']['Authorization'] === 'Bearer test-key', 'Reservation uses the configured API token');
$options['selected_team_id'] = null;
$client = new FCIP_API_Client();
$client->toggle_site_reservation('site-hash');
check($requests[1][0] === 'https://app.instawp.io/api/v2/sites/site-hash/reserve-toggle', 'Hash site IDs use the same v2 route');
$response_code = 404;
$result = $client->toggle_site_reservation(123);
check(is_wp_error($result) && $result->get_error_message() === 'api_not_found', 'API failures propagate instead of reporting a reservation change');
check(count($requests) === 3, 'Each toggle sends exactly one request without retrying a mutation');
echo "Reservation HTTP method, route, authentication and error checks passed.\n";
