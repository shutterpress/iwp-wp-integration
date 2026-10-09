<?php
/**
 * Autoloader for FluentCart InstaWP Provisioning Plugin
 *
 * @package FCIP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FCIP_Autoloader class
 */
class FCIP_Autoloader {

    /**
     * Initialize the autoloader
     */
    public static function init() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * Autoload classes
     *
     * @param string $class_name The class name to load
     */
    public static function autoload($class_name) {
        // Only load classes that start with our prefixes
        if (strpos($class_name, 'FCIP_') !== 0) {
            return;
        }

        // Convert class name to file name
        $class_file = str_replace('_', '-', strtolower($class_name));
        $class_file = 'class-' . $class_file . '.php';

        // Define possible paths based on class name patterns
        $paths = array();

        $paths[] = FCIP_PLUGIN_PATH . 'includes/core/';
        $paths[] = FCIP_PLUGIN_PATH . 'includes/admin/';
        $paths[] = FCIP_PLUGIN_PATH . 'includes/integrations/fluentcart/';


        // Try to load the file from each path
        foreach ($paths as $path) {
            $file_path = $path . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }


    }
}

// Initialize the autoloader
FCIP_Autoloader::init();
