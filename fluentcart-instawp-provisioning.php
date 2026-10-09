<?php
/**
 * Plugin Name: FluentCart InstaWP Provisioning
 * Plugin URI: https://instawp.com
 * Description: InstaWP site provisioning for FluentCart, with standalone site creation tools.
 * Version: 1.0.0
 * Author: InstaWP
 * Author URI: https://instawp.com
 * Text Domain: fluentcart-instawp-provisioning
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FCIP_VERSION', '1.0.0');
define('FCIP_PLUGIN_FILE', __FILE__);
define('FCIP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FCIP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FCIP_PLUGIN_BASENAME', plugin_basename(__FILE__));
defined( 'FCIP_PLUGIN_APP_URL' ) || define('FCIP_PLUGIN_APP_URL', 'https://app.instawp.io');

// Include the autoloader
require_once FCIP_PLUGIN_PATH . 'includes/core/class-fcip-autoloader.php';

// Initialize GitHub auto-updater
require_once FCIP_PLUGIN_PATH . 'includes/core/class-fcip-github-updater.php';
new FCIP_GitHub_Updater();

// Initialize the plugin
function fcip_init() {
    // Load core classes (needed by both admin and frontend)
    require_once FCIP_PLUGIN_PATH . 'includes/core/class-fcip-installer.php';
    require_once FCIP_PLUGIN_PATH . 'includes/core/class-fcip-api-client.php';
    require_once FCIP_PLUGIN_PATH . 'includes/core/class-fcip-logger.php';
    require_once FCIP_PLUGIN_PATH . 'includes/core/class-fcip-service.php';

    if (is_admin()) {
        require_once FCIP_PLUGIN_PATH . 'includes/admin/class-fcip-settings-page.php';
        require_once FCIP_PLUGIN_PATH . 'includes/admin/class-fcip-admin-simple.php';
        new FCIP_Admin_Simple();
    }

    // Initialize site manager and other dependencies (needed for shortcode functionality)
    require_once FCIP_PLUGIN_PATH . 'includes/core/class-fcip-site-manager.php';
    require_once FCIP_PLUGIN_PATH . 'includes/core/class-fcip-database.php';
    require_once FCIP_PLUGIN_PATH . 'includes/core/class-fcip-sites-model.php';
    
    // FCIP_Service is already loaded above

    // FluentCart keeps orders and variants in its own tables. Register its
    // integration after all plugins have booted, regardless of plugin order.
    add_action('init', 'fcip_init_fluentcart', 20);
    
    // Initialize shortcode functionality (both admin and frontend)
    require_once FCIP_PLUGIN_PATH . 'includes/core/class-fcip-shortcode.php';
    new FCIP_Shortcode();


}

// Enhanced debug logging for wpdb::prepare errors
add_action('plugins_loaded', function() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        // Hook into WordPress error handling to catch wpdb::prepare notices
        add_filter('wp_php_error_args', function($error, $type = '', $message = '', $file = '', $line = '') {
            if (is_array($error) && isset($error['message'])) {
                $message = $error['message'];
                $file = isset($error['file']) ? $error['file'] : '';
                $line = isset($error['line']) ? $error['line'] : '';
            }
            
            if (!empty($message) && strpos($message, 'wpdb::prepare was called incorrectly') !== false) {
                // class_exists guard: a PHP error can fire before fcip_init()
                // has loaded the plugin's classes.
                if (class_exists('FCIP_Logger')) {
                    FCIP_Logger::error('wpdb::prepare called incorrectly', 'debug', array(
                        'message'   => $message,
                        'file'      => $file,
                        'line'      => $line,
                        'backtrace' => wp_debug_backtrace_summary(),
                    ));
                }
            }
            return $error;
        }, 10, 5);
        
        // Additional debug output for our plugin specifically
        add_action('init', function() {
            if (strpos($_SERVER['REQUEST_URI'] ?? '', 'wp-admin') !== false || 
                strpos($_SERVER['REQUEST_URI'] ?? '', 'wp-json') !== false) {
                if (class_exists('FCIP_Logger')) {
                    FCIP_Logger::debug('Admin/AJAX request detected, monitoring for wpdb issues', 'debug');
                }
            }
        });
    }
});

// Hook into plugins_loaded to ensure dependencies are loaded first
add_action('plugins_loaded', 'fcip_init');

function fcip_init_fluentcart() {
    if (!class_exists('FluentCart\\App\\Modules\\Integrations\\BaseIntegrationManager')) {
        return;
    }
    require_once FCIP_PLUGIN_PATH . 'includes/integrations/fluentcart/class-fcip-fluentcart-order-processor.php';
    require_once FCIP_PLUGIN_PATH . 'includes/integrations/fluentcart/class-fcip-fluentcart-integration.php';
    require_once FCIP_PLUGIN_PATH . 'includes/integrations/fluentcart/class-fcip-fluentcart-portal.php';
    require_once FCIP_PLUGIN_PATH . 'includes/integrations/fluentcart/class-fcip-fluentcart-subscriptions.php';
    $processor = new FCIP_FluentCart_Order_Processor();
    (new FCIP_FluentCart_Integration($processor))->register();
    new FCIP_FluentCart_Portal($processor);
    new FCIP_FluentCart_Subscriptions();
}

// Activation hook
register_activation_hook(__FILE__, 'fcip_activate');

function fcip_activate() {
    // The autoloader is registered at file scope (class-fcip-autoloader.php
    // calls FCIP_Autoloader::init() on include), so FCIP_Logger and FCIP_Database
    // resolve on demand here -- the installer can log during activation
    // without them being required explicitly.
    if (!class_exists('FCIP_Installer')) {
        require_once plugin_dir_path(__FILE__) . 'includes/core/class-fcip-installer.php';
    }

    // Create necessary database tables or options
    FCIP_Installer::install();

}

// Deactivation hook
register_deactivation_hook(__FILE__, 'fcip_deactivate');

function fcip_deactivate() {
    // Clean up temporary data
    if (class_exists('FCIP_Installer')) {
        FCIP_Installer::deactivate();
    }
    
    wp_clear_scheduled_hook('fcip_check_pending_sites');

}

// Uninstall hook
register_uninstall_hook(__FILE__, 'fcip_uninstall');

function fcip_uninstall() {
    // Clean up all plugin data
    if (class_exists('FCIP_Installer')) {
        FCIP_Installer::uninstall();
    }
}
