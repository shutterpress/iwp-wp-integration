<?php
/**
 * Plugin Name: InstaWP Integration
 * Plugin URI: https://instawp.com
 * Description: InstaWP site provisioning for FluentCart, with standalone site creation tools.
 * Version: 0.0.15
 * Author: InstaWP
 * Author URI: https://instawp.com
 * Text Domain: iwp-wp-integration
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
define('IWP_VERSION', '0.0.15');
define('IWP_PLUGIN_FILE', __FILE__);
define('IWP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('IWP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IWP_PLUGIN_BASENAME', plugin_basename(__FILE__));
defined( 'IWP_PLUGIN_APP_URL' ) || define('IWP_PLUGIN_APP_URL', 'https://app.instawp.io');

// Include the autoloader
require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-autoloader.php';

// Initialize GitHub auto-updater
require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-github-updater.php';
new IWP_GitHub_Updater();

// Initialize the plugin
function iwp_init() {
    // Load core classes (needed by both admin and frontend)
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-installer.php';
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-api-client.php';
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-logger.php';
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-service.php';

    // Check if database needs updating
    if (IWP_Installer::needs_database_update()) {
        IWP_Installer::update_database();
    }

    // Set default settings if this is a fresh installation or settings are missing
    $options = get_option('iwp_options', array());
    if (empty($options) || !isset($options['enable_integration'])) {
        IWP_Installer::set_default_settings();
    }
    
    if (is_admin()) {
        require_once IWP_PLUGIN_PATH . 'includes/admin/class-iwp-settings-page.php';
        require_once IWP_PLUGIN_PATH . 'includes/admin/class-iwp-admin-simple.php';
        require_once IWP_PLUGIN_PATH . 'includes/admin/class-iwp-db-update-notice.php';
        new IWP_Admin_Simple();
    }

    // Initialize site manager and other dependencies (needed for shortcode functionality)
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-site-manager.php';
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-database.php';
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-sites-model.php';
    
    // IWP_Service is already loaded above

    // FluentCart keeps orders and variants in its own tables. Register its
    // integration after all plugins have booted, regardless of plugin order.
    add_action('init', 'iwp_init_fluentcart', 20);
    
    // Initialize shortcode functionality (both admin and frontend)
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-shortcode.php';
    new IWP_Shortcode();


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
                // class_exists guard: a PHP error can fire before iwp_init()
                // has loaded the plugin's classes.
                if (class_exists('IWP_Logger')) {
                    IWP_Logger::error('wpdb::prepare called incorrectly', 'debug', array(
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
                if (class_exists('IWP_Logger')) {
                    IWP_Logger::debug('Admin/AJAX request detected, monitoring for wpdb issues', 'debug');
                }
            }
        });
    }
});

// Hook into plugins_loaded to ensure dependencies are loaded first
add_action('plugins_loaded', 'iwp_init');

function iwp_init_fluentcart() {
    if (!class_exists('FluentCart\\App\\Modules\\Integrations\\BaseIntegrationManager')) {
        return;
    }
    require_once IWP_PLUGIN_PATH . 'includes/integrations/fluentcart/class-iwp-fluentcart-order-processor.php';
    require_once IWP_PLUGIN_PATH . 'includes/integrations/fluentcart/class-iwp-fluentcart-integration.php';
    require_once IWP_PLUGIN_PATH . 'includes/integrations/fluentcart/class-iwp-fluentcart-portal.php';
    require_once IWP_PLUGIN_PATH . 'includes/integrations/fluentcart/class-iwp-fluentcart-subscriptions.php';
    $processor = new IWP_FluentCart_Order_Processor();
    (new IWP_FluentCart_Integration($processor))->register();
    new IWP_FluentCart_Portal($processor);
    new IWP_FluentCart_Subscriptions();
}

// Activation hook
register_activation_hook(__FILE__, 'iwp_activate');

function iwp_activate() {
    // The autoloader is registered at file scope (class-iwp-autoloader.php
    // calls IWP_Autoloader::init() on include), so IWP_Logger and IWP_Database
    // resolve on demand here -- the installer can log during activation
    // without them being required explicitly.
    if (!class_exists('IWP_Installer')) {
        require_once plugin_dir_path(__FILE__) . 'includes/core/class-iwp-installer.php';
    }

    // Create necessary database tables or options
    IWP_Installer::install();

    // Run any pending database migrations
    IWP_Installer::update_database();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'iwp_deactivate');

function iwp_deactivate() {
    // Clean up temporary data
    if (class_exists('IWP_Installer')) {
        IWP_Installer::deactivate();
    }
    
    wp_clear_scheduled_hook('iwp_check_pending_sites');

}

// Uninstall hook
register_uninstall_hook(__FILE__, 'iwp_uninstall');

function iwp_uninstall() {
    // Clean up all plugin data
    if (class_exists('IWP_Installer')) {
        IWP_Installer::uninstall();
    }
}
