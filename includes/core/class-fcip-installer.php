<?php
/**
 * Installer class for FluentCart InstaWP Provisioning
 *
 * @package FCIP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FCIP_Installer class
 */
class FCIP_Installer {

    /**
     * Install the plugin
     */
    public static function install() {
        if (!defined('FCIP_INSTALLING')) {
            define('FCIP_INSTALLING', true);
        }

        // Check if we have the minimum requirements
        self::check_requirements();

        // Create tables
        self::create_tables();

        // Create default options
        self::create_options();

        // Create files/directories
        self::create_files();

        // Update version
        self::update_version();

        // Trigger action
        do_action('fcip_installed');
    }

    /**
     * Check requirements
     */
    private static function check_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die(
                esc_html__('FluentCart InstaWP Provisioning requires PHP 7.4 or higher.', 'fluentcart-instawp-provisioning'),
                esc_html__('Requirements Error', 'fluentcart-instawp-provisioning'),
                array('back_link' => true)
            );
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            wp_die(
                esc_html__('FluentCart InstaWP Provisioning requires WordPress 5.0 or higher.', 'fluentcart-instawp-provisioning'),
                esc_html__('Requirements Error', 'fluentcart-instawp-provisioning'),
                array('back_link' => true)
            );
        }

    }

    /**
     * Create tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tables = array(
            // Activity logs
            "CREATE TABLE {$wpdb->prefix}fcip_logs (
                id bigint(20) unsigned NOT NULL auto_increment,
                user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                order_id bigint(20) unsigned NULL DEFAULT NULL,
                action varchar(100) NOT NULL,
                message text NOT NULL,
                data longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY order_id (order_id),
                KEY action (action),
                KEY created_at (created_at)
            ) $charset_collate;",


            // Sites tracking table for real-time status updates
            "CREATE TABLE {$wpdb->prefix}fcip_sites (
                id bigint(20) unsigned NOT NULL auto_increment,
                site_id varchar(100) NOT NULL,
                site_url varchar(255) NULL,
                wp_username varchar(100) NULL,
                wp_password varchar(255) NULL,
                wp_admin_url varchar(255) NULL,
                s_hash varchar(255) NULL,
                status varchar(20) NOT NULL DEFAULT 'creating',
                site_type varchar(50) DEFAULT 'paid',
                task_id varchar(100) NULL,
                snapshot_slug varchar(100) NULL,
                plan_id varchar(100) NULL,
                product_id bigint(20) unsigned NULL,
                order_id bigint(20) unsigned NULL,
                user_id bigint(20) unsigned NULL DEFAULT 0,
                source varchar(50) NOT NULL DEFAULT 'fluentcart',
                source_data longtext NULL,
                is_pool tinyint(1) NOT NULL DEFAULT 0,
                is_reserved tinyint(1) NOT NULL DEFAULT 1,
                has_cdn tinyint(1) NULL DEFAULT NULL,
                expiry_hours int(11) NULL DEFAULT NULL,
                api_response longtext NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY site_id (site_id),
                KEY status (status),
                KEY site_type (site_type),
                KEY order_id (order_id),
                KEY user_id (user_id),
                KEY source (source),
                KEY task_id (task_id),
                KEY created_at (created_at),
                KEY updated_at (updated_at)
            ) $charset_collate;"
        );

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($tables as $table) {
            dbDelta($table);
        }
    }

    /**
     * Create default options
     */
    private static function create_options() {
        $default_options = array(
            'version' => FCIP_VERSION,
            'installed_at' => current_time('mysql'),
            // General Settings - All enabled by default for better user experience
            'enable_integration' => 'yes',
            // Debug Settings - Reasonable defaults
            'debug_mode' => 'no',
            'log_level' => 'info',
            'api_key' => '',
            'selected_team_id' => null,
            'delay_customer_credentials' => 'no',
            'enable_cache_purge' => 'yes',
            'auto_create_sites_on_purchase' => 'yes',
        );

        add_option('fcip_options', $default_options);
    }

    /**
     * Create files and directories
     */
    private static function create_files() {
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $fcip_upload_dir = $upload_dir['basedir'] . '/fcip';

        if (!file_exists($fcip_upload_dir)) {
            wp_mkdir_p($fcip_upload_dir);
        }

        // Create .htaccess file to protect directory
        $htaccess_file = $fcip_upload_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = 'deny from all';
            file_put_contents($htaccess_file, $htaccess_content);
        }

        // Create index.php file
        $index_file = $fcip_upload_dir . '/index.php';
        if (!file_exists($index_file)) {
            $index_content = '<?php // Silence is golden';
            file_put_contents($index_file, $index_content);
        }
    }

    /**
     * Update version
     */
    private static function update_version() {
        delete_option('fcip_version');
        add_option('fcip_version', FCIP_VERSION);
    }

    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('fcip_check_pending_sites');

        // Trigger action
        do_action('fcip_deactivated');
    }

    /**
     * Uninstall the plugin
     */
    public static function uninstall() {
        global $wpdb;

        // Delete options
        delete_option('fcip_options');
        delete_option('fcip_version');

        // Delete tables
        $tables = array(
            $wpdb->prefix . 'fcip_logs',
            $wpdb->prefix . 'fcip_sites'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        // Delete only transients owned by this plugin.
        foreach (array('_transient_fcip_', '_transient_timeout_fcip_') as $prefix) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like($prefix) . '%'));
        }

        // Delete files
        $upload_dir = wp_upload_dir();
        $fcip_upload_dir = $upload_dir['basedir'] . '/fcip';
        
        if (is_dir($fcip_upload_dir)) {
            self::delete_directory($fcip_upload_dir);
        }

        // Trigger action
        do_action('fcip_uninstalled');
    }

    /**
     * Delete directory recursively
     *
     * @param string $dir The directory to delete
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

}
