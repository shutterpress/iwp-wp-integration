<?php
/**
 * Database Helper Class
 *
 * @package FCIP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FCIP_Database class
 * 
 * Centralized database operations for order meta, options, and common queries
 */
class FCIP_Database {

    /**
     * Append data to order meta array
     *
     * @param int $order_id
     * @param string $meta_key
     * @param mixed $data
     * @return bool
     */

    /**
     * Update order meta with validation
     *
     * @param int $order_id
     * @param string $meta_key
     * @param mixed $data
     * @param bool $merge_arrays Whether to merge if existing data is array
     * @return bool
     */

    /**
     * Get all InstaWP sites for an order
     *
     * @param int $order_id
     * @return array
     */

    /**
     * Get mapped domains for an order
     *
     * @param int $order_id
     * @return array
     */

    /**
     * Add mapped domain to order
     *
     * @param int $order_id
     * @param array $domain_info
     * @return bool
     */

    /**
     * Get plugin options with caching
     *
     * @param string $key Optional specific key to retrieve
     * @return mixed
     */
    public static function get_plugin_options($key = null) {
        static $options = null;

        if ($options === null) {
            $options = get_option('fcip_options', array());
        }

        if ($key !== null) {
            return isset($options[$key]) ? $options[$key] : null;
        }

        return $options;
    }

    /**
     * Update plugin option
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function update_plugin_option($key, $value) {
        $options = self::get_plugin_options();
        $options[$key] = $value;
        return update_option('fcip_options', $options);
    }

    /**
     * Get products with InstaWP snapshots configured
     *
     * @param int $limit
     * @return array
     */

    /**
     * Get users for dropdown (with caching)
     *
     * @param int $limit
     * @return array
     */
    public static function get_users_for_dropdown($limit = 50) {
        $cache_key = 'fcip_users_dropdown_' . $limit;
        $cached_users = get_transient($cache_key);

        if ($cached_users !== false) {
            return $cached_users;
        }

        $users = get_users(array(
            'number' => $limit,
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));

        $formatted_users = array();
        foreach ($users as $user) {
            $formatted_users[$user->ID] = sprintf(
                '%s (%s) - %s',
                $user->display_name,
                $user->user_login,
                $user->user_email
            );
        }

        // Cache for 5 minutes
        set_transient($cache_key, $formatted_users, 300);

        return $formatted_users;
    }

    /**
     * Get orders with InstaWP sites
     *
     * @param array $args
     * @return array
     */

    /**
     * Clean up old transients
     *
     * @param string $prefix Optional prefix to filter transients
     */
    public static function cleanup_transients($prefix = 'fcip_') {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_' . $prefix . '%',
            '_transient_timeout_' . $prefix . '%'
        ));
    }

    /**
     * Get site creation statistics
     *
     * @return array
     */




    /**
     * Check if table exists
     *
     * @param string $table_name
     * @return bool
     */
    public static function table_exists($table_name) {
        global $wpdb;

        $query = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
        return $wpdb->get_var($query) === $table_name;
    }

    /**
     * Create custom table for plugin logs
     *
     * @return bool
     */
    public static function create_logs_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'fcip_logs';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
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
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        return dbDelta($sql);
    }

    /**
     * Log plugin activity to custom table
     *
     * @param string $action
     * @param string $message
     * @param array $data
     * @param int $order_id
     * @param int $user_id
     * @return bool
     */
    public static function log_activity($action, $message, $data = array(), $order_id = null, $user_id = null) {
        try {
            global $wpdb;

            $table_name = $wpdb->prefix . 'fcip_logs';

            if ($user_id === null) {
                $user_id = get_current_user_id();
            }

            $encoded = wp_json_encode($data);

            // Same gate as the file log: a row here is persisted indefinitely, so
            // an unencodable, oversized or credential-bearing payload is dropped
            // rather than stored. The reason is logged so the drop is traceable.
            $rejection = FCIP_Logger::payload_rejection_reason($encoded);

            if ($rejection !== null) {
                FCIP_Logger::warning('Activity log entry skipped: ' . $rejection, 'database', array(
                    'action' => $action,
                ));

                return false;
            }

            return $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'order_id' => $order_id,
                    'action' => $action,
                    'message' => $message,
                    'data' => $encoded,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s')
            );
        } catch (\Throwable $e) {
            // Never let an audit-log write break the operation it
            // was recording.
            FCIP_Logger::error('Activity log write failed', 'database', array(
                'action' => $action,
                'error'  => $e->getMessage(),
            ));

            return false;
        }
    }

    /**
     * Get plugin activity logs
     *
     * @param array $args
     * @return array
     */
    public static function get_activity_logs($args = array()) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'fcip_logs';

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'action' => null,
            'user_id' => null,
            'order_id' => null,
            'start_date' => null,
            'end_date' => null
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array('1=1');
        $where_values = array();

        if ($args['action']) {
            $where_conditions[] = 'action = %s';
            $where_values[] = $args['action'];
        }

        if ($args['user_id']) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }

        if ($args['order_id']) {
            $where_conditions[] = 'order_id = %d';
            $where_values[] = $args['order_id'];
        }

        if ($args['start_date']) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['start_date'];
        }

        if ($args['end_date']) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['end_date'];
        }

        $where_clause = implode(' AND ', $where_conditions);

        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        if (!empty($where_values)) {
            FCIP_Logger::debug('database get_activity_logs() - where values', 'database', $where_values);
            FCIP_Logger::debug('database get_activity_logs() - query', 'database', array('query' => $query));
            FCIP_Logger::debug('database get_activity_logs() - values type check', 'database', array('is_array' => is_array($where_values), 'count' => is_array($where_values) ? count($where_values) : null));
            
            try {
                $query = $wpdb->prepare($query, $where_values);
                FCIP_Logger::debug('database get_activity_logs() - SQL prepared successfully', 'database');
            } catch (\Throwable $e) {
                FCIP_Logger::error('database get_activity_logs() - exception during prepare', 'database', array('error' => $e->getMessage()));
                throw $e;
            }
        }

        return $wpdb->get_results($query);
    }
}

