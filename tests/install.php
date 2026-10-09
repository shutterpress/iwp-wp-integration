<?php
/** Exercise activation, reactivation and uninstall without a WordPress server. */
$fixture = sys_get_temp_dir() . '/fcip-install-' . uniqid();
mkdir($fixture . '/wp-admin/includes', 0777, true);
file_put_contents($fixture . '/wp-admin/includes/upgrade.php', '<?php');
define('ABSPATH', $fixture . '/');
define('FCIP_VERSION', '1.0.0');
$options = array('iwp_options' => array('api_key' => 'original-key'), 'iwp_version' => '0.0.15');
$schemas = array(); $actions = array(); $cleared = array();
function get_bloginfo($key) { return '6.8'; }
function current_time($key) { return '2026-10-09 12:00:00'; }
function add_option($key, $value) { global $options; if (array_key_exists($key, $options)) return false; $options[$key] = $value; return true; }
function delete_option($key) { global $options; unset($options[$key]); }
function dbDelta($sql) { global $schemas; $schemas[] = $sql; }
function do_action($key) { global $actions; $actions[] = $key; }
function wp_clear_scheduled_hook($key) { global $cleared; $cleared[] = $key; }
function wp_upload_dir() { global $fixture; return array('basedir' => $fixture . '/uploads'); }
function wp_mkdir_p($path) { return mkdir($path, 0777, true); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
class TestDatabase {
    public $prefix = 'wp_', $options = 'wp_options', $queries = array();
    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
    public function query($sql) { $this->queries[] = $sql; return 1; }
    public function esc_like($value) { return addcslashes($value, '_%\\'); }
    public function prepare($sql, $value) { return str_replace('%s', "'" . $value . "'", $sql); }
}
$wpdb = new TestDatabase();
require __DIR__ . '/../includes/core/class-fcip-installer.php';
FCIP_Installer::install();
check(count($schemas) === 2, 'Activation creates exactly two owned tables');
check(strpos($schemas[0], 'CREATE TABLE wp_fcip_logs') !== false && strpos($schemas[1], 'CREATE TABLE wp_fcip_sites') !== false, 'Schemas use the dedicated namespace');
check(strpos($schemas[1], 'has_cdn') !== false && strpos($schemas[1], 'site_type') !== false, 'Complete schema is installed immediately');
check($options['fcip_options']['api_key'] === '' && $options['fcip_options']['enable_integration'] === 'yes', 'Fresh defaults do not import another plugin’s settings');
check(!isset($options['fcip_options']['enabled']), 'No compatibility option is created');
$options['fcip_options']['api_key'] = 'own-key';
FCIP_Installer::install();
check($options['fcip_options']['api_key'] === 'own-key', 'Reactivation preserves this plugin’s configured API key');
check(!method_exists('FCIP_Installer', 'update_database') && !method_exists('FCIP_Installer', 'needs_database_update'), 'No installation upgrade callbacks remain');
FCIP_Installer::deactivate();
check($cleared === array('fcip_check_pending_sites'), 'Deactivation clears only the owned cron hook');
FCIP_Installer::uninstall();
check(!isset($options['fcip_options']) && !isset($options['fcip_version']), 'Uninstall removes owned options');
check($options['iwp_options']['api_key'] === 'original-key' && $options['iwp_version'] === '0.0.15', 'Activation and uninstall leave the original plugin’s options untouched');
check(count($wpdb->queries) === 4 && strpos(implode('\n', $wpdb->queries), 'iwp_') === false, 'Uninstall targets only owned tables and transients');
check(strpos($wpdb->queries[2], '\\_transient\\_fcip\\_') !== false, 'Transient deletion escapes SQL wildcard characters');
check(!is_dir($fixture . '/uploads/fcip'), 'Owned upload directory is removed');
unlink($fixture . '/wp-admin/includes/upgrade.php');
rmdir($fixture . '/wp-admin/includes'); rmdir($fixture . '/wp-admin'); rmdir($fixture . '/uploads'); rmdir($fixture);
echo "Fresh installation, reactivation and uninstall isolation checks passed.\n";
