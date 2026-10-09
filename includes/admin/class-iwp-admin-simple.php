<?php
/**
 * Simplified Admin class for IWP Integration
 *
 * @package IWP
 * @since 0.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Admin_Simple class - Clean, focused admin management
 */
class IWP_Admin_Simple {

    /**
     * Settings page instance
     *
     * @var IWP_Settings_Page
     */
    private $settings_page;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings_page = new IWP_Settings_Page();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_iwp_refresh_templates', array($this, 'ajax_refresh_templates'));
        add_action('wp_ajax_iwp_refresh_plans', array($this, 'ajax_refresh_plans'));
        add_action('wp_ajax_iwp_refresh_teams', array($this, 'ajax_refresh_teams'));
        add_action('wp_ajax_iwp_set_team', array($this, 'ajax_set_team'));
        
        // Domain mapping AJAX handlers (for frontend domain mapping)

        // Send credentials AJAX handler
        add_action('wp_ajax_iwp_send_credentials', array($this, 'ajax_send_credentials'));

        // Documentation AJAX handler
        add_action('wp_ajax_iwp_load_doc', array('IWP_Docs_Renderer', 'ajax_load_doc'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Add top-level InstaWP menu
        add_menu_page(
            esc_html__('InstaWP Sites', 'iwp-wp-integration'),
            esc_html__('InstaWP', 'iwp-wp-integration'),
            'manage_options',
            'instawp-sites',
            array($this, 'sites_page'),
            $this->get_menu_icon(),
            30
        );

        // Override the default first submenu with "Sites" 
        add_submenu_page(
            'instawp-sites',
            esc_html__('InstaWP Sites', 'iwp-wp-integration'),
            esc_html__('Sites', 'iwp-wp-integration'),
            'manage_options',
            'instawp-sites',
            array($this, 'sites_page')
        );

        // Add Settings submenu
        add_submenu_page(
            'instawp-sites',
            esc_html__('InstaWP Settings', 'iwp-wp-integration'),
            esc_html__('Settings', 'iwp-wp-integration'),
            'manage_options',
            'instawp-settings',
            array($this, 'settings_page')
        );

        // Add Docs submenu
        add_submenu_page(
            'instawp-sites',
            esc_html__('InstaWP Documentation', 'iwp-wp-integration'),
            esc_html__('Docs', 'iwp-wp-integration'),
            'manage_options',
            'instawp-docs',
            array($this, 'docs_page')
        );
    }

    /**
     * Get menu icon
     */
    private function get_menu_icon() {
        // InstaWP logo SVG - custom design with lightning bolt for "instant" and blue branding
        $svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.9"/>
            <path d="M13.5 7L9.5 13H11L10.5 17L14.5 11H13L13.5 7Z" fill="white"/>
            <path d="M7 16C7.5 15.5 8 15 8.5 14.5C9 15 9.5 15.5 10 16C10.5 15.5 11 15 11.5 14.5C12 15 12.5 15.5 13 16C13.5 15.5 14 15 14.5 14.5C15 15 15.5 15.5 16 16C16.5 15.5 17 15 17.5 14.5" stroke="white" stroke-width="0.5" fill="none" opacity="0.4"/>
        </svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Sites page callback
     */
    public function sites_page() {
        // Handle row actions first
        $this->handle_sites_page_actions();
        
        // Load the sites list table class
        if (!class_exists('IWP_Sites_List_Table')) {
            require_once IWP_PLUGIN_PATH . 'includes/admin/class-iwp-sites-list-table.php';
        }
        
        // Check if we have an existing sites management class
        if (class_exists('IWP_Sites_List_Table')) {
            // Use the existing sites management functionality
            $sites_table = new IWP_Sites_List_Table();
            $sites_table->prepare_items();

            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php esc_html_e('InstaWP Sites', 'iwp-wp-integration'); ?></h1>
                <hr class="wp-header-end">

                <?php
                // Display any admin notices
                if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Site deleted successfully.', 'iwp-wp-integration') . '</p></div>';
                }
                ?>

                <form method="get">
                    <input type="hidden" name="page" value="instawp-sites" />
                    <?php
                    // Display view links (All, Active, Creating, etc.)
                    $sites_table->views();

                    // Display search box
                    $sites_table->search_box(__('Search Sites', 'iwp-wp-integration'), 'iwp-sites');

                    // Display the table
                    $sites_table->display();
                    ?>
                </form>
            </div>
            <?php
        } else {
            // Fallback to a basic sites display
            $this->render_basic_sites_page();
        }
    }
    
    /**
     * Render basic sites page if advanced table not available
     */
    private function render_basic_sites_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('InstaWP Sites', 'iwp-wp-integration'); ?></h1>
            
            <div class="iwp-sites-basic">
                <p><?php esc_html_e('Here are the InstaWP sites from recent orders:', 'iwp-wp-integration'); ?></p>
                
                <?php
                // Get sites from recent completed orders
                $sites = array();
                
                if (empty($sites)) {
                    echo '<p>' . esc_html__('No InstaWP sites found. Create some test orders to see sites here.', 'iwp-wp-integration') . '</p>';
                } else {
                    echo '<div class="iwp-sites-grid">';
                    foreach ($sites as $site) {
                        $this->render_site_card($site);
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        
        <style>
        .iwp-sites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .iwp-site-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .iwp-site-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #1d2327;
        }
        
        .iwp-site-info {
            margin-bottom: 15px;
        }
        
        .iwp-site-info strong {
            color: #50575e;
        }
        
        .iwp-site-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .iwp-site-actions .button {
            flex: 1;
            text-align: center;
        }
        
        .iwp-status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .iwp-status-completed { background: #46b450; }
        .iwp-status-progress { background: #ffb900; }
        .iwp-status-failed { background: #dc3232; }
        </style>
        <?php
    }
    
    /**
     * Get sites from FluentCart orders
     */
    
    /**
     * Render individual site card
     */
    private function render_site_card($site) {
        $status = $site['status'] ?? 'unknown';
        $site_url = $site['wp_url'] ?? '#';
        $admin_url = trailingslashit($site_url) . 'wp-admin';
        $magic_login_url = !empty($site['s_hash']) ? 
            IWP_PLUGIN_APP_URL . '/wordpress-auto-login?site=' . urlencode($site['s_hash']) :
            $admin_url;
        
        ?>
        <div class="iwp-site-card">
            <h3>
                <span class="iwp-status-indicator iwp-status-<?php echo esc_attr($status); ?>"></span>
                <?php echo esc_html($site['site_name'] ?? 'InstaWP Site'); ?>
            </h3>
            
            <div class="iwp-site-info">
                <strong><?php esc_html_e('URL:', 'iwp-wp-integration'); ?></strong>
                <a href="<?php echo esc_url($site_url); ?>" target="_blank"><?php echo esc_html($site_url); ?></a>
            </div>
            
            <div class="iwp-site-info">
                <strong><?php esc_html_e('Status:', 'iwp-wp-integration'); ?></strong>
                <?php echo esc_html(ucfirst($status)); ?>
            </div>
            
            <?php if (!empty($site['wp_username'])) : ?>
            <div class="iwp-site-info">
                <strong><?php esc_html_e('Admin Username:', 'iwp-wp-integration'); ?></strong>
                <?php echo esc_html($site['wp_username']); ?>
            </div>
            <?php endif; ?>
            
            <div class="iwp-site-info">
                <strong><?php esc_html_e('Created:', 'iwp-wp-integration'); ?></strong>
                <?php echo esc_html($site['order_date'] ?? 'Unknown'); ?>
            </div>
            
            <div class="iwp-site-info">
                <strong><?php esc_html_e('Order:', 'iwp-wp-integration'); ?></strong>
                <a href="<?php echo esc_url(admin_url('post.php?post=' . $site['order_id'] . '&action=edit')); ?>">
                    #<?php echo esc_html($site['order_id']); ?>
                </a>
            </div>
            
            <div class="iwp-site-actions">
                <a href="<?php echo esc_url($site_url); ?>" class="button button-secondary" target="_blank">
                    <?php esc_html_e('Visit Site', 'iwp-wp-integration'); ?>
                </a>
                <a href="<?php echo esc_url($magic_login_url); ?>" class="button button-primary" target="_blank">
                    <?php echo !empty($site['s_hash']) ? esc_html__('Magic Login', 'iwp-wp-integration') : esc_html__('Admin Login', 'iwp-wp-integration'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Settings page callback
     */
    public function settings_page() {
        $this->settings_page->render();
    }

    /**
     * Render the standalone Docs page
     */
    public function docs_page() {
        $active_doc = isset($_GET['doc']) ? sanitize_key($_GET['doc']) : 'index';
        $renderer = new IWP_Docs_Renderer();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('InstaWP Documentation', 'iwp-wp-integration'); ?></h1>
            <?php $renderer->render($active_doc); ?>
        </div>
        <?php
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('iwp_settings', 'iwp_options', array($this, 'sanitize_settings'));

        // API Settings Section
        add_settings_section(
            'iwp_api',
            esc_html__('InstaWP API Configuration', 'iwp-wp-integration'),
            array($this, 'api_section_callback'),
            'iwp_settings'
        );

        add_settings_field(
            'api_key',
            esc_html__('API Key', 'iwp-wp-integration'),
            array($this, 'api_key_callback'),
            'iwp_settings',
            'iwp_api'
        );

        // General Settings Section
        add_settings_section(
            'iwp_general',
            esc_html__('FluentCart Integration Settings', 'iwp-wp-integration'),
            array($this, 'general_section_callback'),
            'iwp_settings'
        );

        add_settings_field(
            'enable_integration',
            esc_html__('Enable Integration', 'iwp-wp-integration'),
            array($this, 'checkbox_callback'),
            'iwp_settings',
            'iwp_general',
            array('field' => 'enable_integration', 'label' => 'Enable the InstaWP FluentCart integration')
        );

        add_settings_field(
            'auto_create_sites_on_purchase',
            esc_html__('Auto-Create Sites', 'iwp-wp-integration'),
            array($this, 'checkbox_callback'),
            'iwp_settings',
            'iwp_general',
            array('field' => 'auto_create_sites_on_purchase', 'label' => 'Automatically create sites when FluentCart orders are paid')
        );


        add_settings_field(
            'delay_customer_credentials',
            esc_html__('Delay Customer Credentials', 'iwp-wp-integration'),
            array($this, 'checkbox_callback'),
            'iwp_settings',
            'iwp_general',
            array(
                'field' => 'delay_customer_credentials',
                'label' => 'Hide login credentials from customers until manually released. Use "Send Credentials" action from InstaWP > Sites to share credentials when ready.'
            )
        );


        add_settings_field(
            'enable_cache_purge',
            esc_html__('Allow Customers to Clear Cache', 'iwp-wp-integration'),
            array($this, 'checkbox_callback'),
            'iwp_settings',
            'iwp_general',
            array(
                'field' => 'enable_cache_purge',
                'label' => 'Show a "Clear Cache" button on the customer\'s My Account dashboard and order pages, so they can clear their own site\'s cache.',
                // Default ON so existing stores get the button without re-saving.
                'default' => 'yes'
            )
        );
    }

    /**
     * API section callback
     */
    public function api_section_callback() {
        echo '<p>' . esc_html__('Configure your InstaWP API settings below.', 'iwp-wp-integration') . '</p>';
    }

    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . esc_html__('Configure general integration settings.', 'iwp-wp-integration') . '</p>';
        
        // Show subscription integration status
        if (class_exists('FluentCart\App\Models\Subscription')) {
            echo '<div class="iwp-integration-status" style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 10px; margin: 10px 0;">';
            echo '<strong>✅ ' . esc_html__('FluentCart Subscriptions Integration Active', 'iwp-wp-integration') . '</strong><br>';
            echo esc_html__('Sites will automatically be marked as temporary when subscriptions are unpaid and permanent when paid.', 'iwp-wp-integration');
            echo '</div>';
        } else {
            echo '<div class="iwp-integration-status" style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 10px; margin: 10px 0;">';
            echo '<strong>ℹ️ ' . esc_html__('FluentCart Subscriptions Not Detected', 'iwp-wp-integration') . '</strong><br>';
            echo esc_html__('Install FluentCart to enable automatic site status management based on payment status.', 'iwp-wp-integration');
            echo '</div>';
        }
    }

    /**
     * API key field callback
     */
    public function api_key_callback() {
        $options = get_option('iwp_options', array());
        $value = isset($options['api_key']) ? $options['api_key'] : '';
        
        printf(
            '<input type="password" id="api_key" name="iwp_options[api_key]" value="%s" class="regular-text" />',
            esc_attr($value)
        );
        
        printf(
            '<p class="description">%s <a href="' . esc_url(IWP_PLUGIN_APP_URL . '/user/api-tokens') . '" target="_blank">%s</a></p>',
            esc_html__('Enter your InstaWP API key.', 'iwp-wp-integration'),
            esc_html__('Get your API key here', 'iwp-wp-integration')
        );
    }

    /**
     * Checkbox field callback
     */
    public function checkbox_callback($args) {
        $options = get_option('iwp_options', array());
        // Fields may opt into a default of 'yes'; everything else stays 'no'.
        $default = isset($args['default']) ? $args['default'] : 'no';
        $value = isset($options[$args['field']]) ? $options[$args['field']] : $default;
        $checked = $value === 'yes' ? 'checked="checked"' : '';
        
        printf(
            '<label><input type="checkbox" name="iwp_options[%s]" value="yes" %s /> %s</label>',
            esc_attr($args['field']),
            $checked,
            esc_html($args['label'])
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        // Get existing options to merge with
        $existing = get_option('iwp_options', array());
        $sanitized = $existing; // Start with existing values to preserve settings from other tabs
        
        // Determine which form was submitted based on the fields present
        $is_general_form = isset($input['_form_submitted']) && $input['_form_submitted'] === 'general_settings';
        $is_debug_form = isset($input['_form_submitted']) && $input['_form_submitted'] === 'debug_settings';
        
        // If no form identifier, try to detect based on fields
        if (!$is_general_form && !$is_debug_form) {
            // Check for specific fields to identify the form
            if (isset($input['api_key']) || isset($input['enable_integration']) || isset($input['auto_create_sites_on_purchase'])) {
                $is_general_form = true;
            } elseif (isset($input['debug_mode']) || isset($input['log_level'])) {
                $is_debug_form = true;
            }
        }
        
        // API key - always process if present
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        }
        
        // General form fields - only process if this is the general form
        if ($is_general_form) {
            // Enable integration - checkbox, so absence means 'no'
            $sanitized['enable_integration'] = isset($input['enable_integration']) && $input['enable_integration'] === 'yes' ? 'yes' : 'no';
            
            // Auto-create sites - checkbox, so absence means 'no'
            $sanitized['auto_create_sites_on_purchase'] = isset($input['auto_create_sites_on_purchase']) && $input['auto_create_sites_on_purchase'] === 'yes' ? 'yes' : 'no';
            
            // Use site_id parameter - checkbox, so absence means 'no'

            // Delay customer credentials - checkbox, so absence means 'no'
            $sanitized['delay_customer_credentials'] = isset($input['delay_customer_credentials']) && $input['delay_customer_credentials'] === 'yes' ? 'yes' : 'no';

            // Show site credentials on customer dashboard - checkbox, so absence means 'no'

            // Allow customers to clear their own site cache - checkbox, so absence means 'no'
            $sanitized['enable_cache_purge'] = isset($input['enable_cache_purge']) && $input['enable_cache_purge'] === 'yes' ? 'yes' : 'no';
        }
        
        // Debug form fields - only process if this is the debug form
        if ($is_debug_form) {
            // Debug mode - checkbox, so absence means 'no'
            $sanitized['debug_mode'] = isset($input['debug_mode']) && $input['debug_mode'] === 'yes' ? 'yes' : 'no';
            
            // Log level
            if (isset($input['log_level'])) {
                $allowed_levels = array('debug', 'info', 'warning', 'error');
                $sanitized['log_level'] = in_array($input['log_level'], $allowed_levels) ? $input['log_level'] : 'error';
            }
        }
        
        // Always preserve team selection and other settings that aren't in forms
        // This ensures team selection and other AJAX-set values are not lost
        // Get the most current options in case they were updated after page load (e.g., via AJAX)
        $current_options = get_option('iwp_options', array());
        if (isset($current_options['selected_team_id'])) {
            $sanitized['selected_team_id'] = $current_options['selected_team_id'];
        }
        
        return $sanitized;
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'instawp') === false) {
            return;
        }

        wp_enqueue_script('jquery');

        // Sites list page UI (password show/hide toggle, etc.).
        wp_enqueue_script(
            'iwp-sites-list',
            IWP_PLUGIN_URL . 'assets/js/sites-list.js',
            array('jquery'),
            IWP_VERSION,
            true
        );
        wp_localize_script('iwp-sites-list', 'iwpSitesList', array(
            'labels' => array(
                'show' => __('Show', 'iwp-wp-integration'),
                'hide' => __('Hide', 'iwp-wp-integration'),
            ),
        ));

        // Send Credentials button handler
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                $(document).on("click", ".iwp-send-credentials", function(e) {
                    e.preventDefault();
                    var $link = $(this);
                    var siteId = $link.data("site-id");
                    var nonce = $link.data("nonce");

                    if (!confirm("Send login credentials to the customer for this site?")) {
                        return;
                    }

                    $link.text("Sending...").css("pointer-events", "none");

                    $.post(ajaxurl, {
                        action: "iwp_send_credentials",
                        site_id: siteId,
                        nonce: nonce
                    }, function(response) {
                        if (response.success) {
                            $link.replaceWith("<span style=\"color: #46b450;\">Credentials Sent</span>");
                            alert(response.data.message);
                        } else {
                            $link.text("Send Credentials").css("pointer-events", "");
                            alert("Error: " + (response.data || "Unknown error"));
                        }
                    }).fail(function() {
                        $link.text("Send Credentials").css("pointer-events", "");
                        alert("Network error occurred");
                    });
                });
            });
        ');

        // Inline styles for clean tab interface
        wp_add_inline_style('wp-admin', '
            .iwp-admin-tabs { 
                border-bottom: 1px solid #ccd0d4; 
                margin: 20px 0 0 0; 
                padding: 0;
            }
            .iwp-admin-tabs .nav-tab { 
                margin: 0 5px -1px 0; 
                padding: 12px 16px;
                font-weight: 600;
            }
            .iwp-tab-content { 
                display: none; 
                background: #fff;
                border: 1px solid #ccd0d4;
                border-top: none;
                padding: 20px;
            }
            .iwp-tab-content.active {
                display: block;
            }
            .iwp-password-revealed {
                display: block;
                font-family: monospace;
                word-break: break-all;
                margin-top: 2px;
            }
            .iwp-password-revealed:empty {
                display: none;
            }
        ');
    }

    /**
     * AJAX: Refresh templates
     */
    public function ajax_refresh_templates() {
        check_ajax_referer('iwp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Create API client with current team selection
        $api_client = new IWP_API_Client();
        
        // Clear team-specific cache
        $api_client->clear_snapshots_cache();
        
        // Try to fetch new data
        $snapshots = $api_client->get_snapshots();
        
        if (is_wp_error($snapshots)) {
            wp_send_json_error('Failed to fetch snapshots: ' . $snapshots->get_error_message());
        }
        
        wp_send_json_success('Snapshots refreshed successfully');
    }

    /**
     * AJAX: Refresh plans
     */
    public function ajax_refresh_plans() {
        check_ajax_referer('iwp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Create API client with current team selection  
        $api_client = new IWP_API_Client();
        
        // Clear team-specific plans cache
        $team_id = $api_client->get_team_id();
        $cache_key = $team_id ? 'iwp_plans_team_' . $team_id : 'iwp_plans';
        delete_transient($cache_key);
        
        // Try to fetch new data
        $plans = $api_client->get_plans();
        
        if (is_wp_error($plans)) {
            wp_send_json_error('Failed to fetch plans: ' . $plans->get_error_message());
        }
        
        wp_send_json_success('Plans refreshed successfully');
    }

    /**
     * AJAX: Create test order
     */

    /**
     * AJAX: Refresh teams
     */
    public function ajax_refresh_teams() {
        check_ajax_referer('iwp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Clear cache
        delete_transient('iwp_teams');
        
        // Try to fetch new data
        require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-service.php';
        $teams = IWP_Service::refresh_teams();
        
        if (is_wp_error($teams)) {
            wp_send_json_error('Failed to fetch teams: ' . $teams->get_error_message());
        }
        
        wp_send_json_success('Teams refreshed successfully');
    }

    /**
     * AJAX: Set selected team
     */
    public function ajax_set_team() {
        check_ajax_referer('iwp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $team_id = isset($_POST['team_id']) && !empty($_POST['team_id']) ? intval($_POST['team_id']) : null;
        
        require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-service.php';
        $result = IWP_Service::set_selected_team($team_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Team selection updated successfully',
                'team_id' => $team_id
            ));
        } else {
            wp_send_json_error('Failed to update team selection');
        }
    }
    
    /**
     * AJAX: Add domain to site
     */
    
    /**
     * Update site URL with mapped domain in all relevant database locations
     *
     * @param int $order_id
     * @param int $site_id
     * @param string $domain_name
     */

    /**
     * Handle sites page actions (delete, etc.)
     */
    private function handle_sites_page_actions() {
        if (!isset($_GET['action']) || !isset($_GET['site_id'])) {
            return;
        }

        $action = sanitize_text_field($_GET['action']);
        $site_id = sanitize_text_field($_GET['site_id']);

        if ($action === 'delete') {
            // Verify nonce
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_site_' . $site_id)) {
                wp_die(__('Security check failed', 'iwp-wp-integration'));
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to perform this action.', 'iwp-wp-integration'));
            }

            // Delete the site
            try {
                $options    = get_option('iwp_options', array());
                $api_key    = $options['api_key'] ?? '';
                $api_client = null;

                if (!empty($api_key)) {
                    $api_client = new IWP_API_Client();
                    $api_client->set_api_key($api_key);
                }

                IWP_Sites_Model::trash($site_id, $api_client);

                IWP_Logger::info('Site moved to trash via row action', 'admin', array(
                    'site_id' => $site_id,
                    'user_id' => get_current_user_id()
                ));

                // Show success message and redirect
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         esc_html__('Site deleted successfully.', 'iwp-wp-integration') . 
                         '</p></div>';
                });

            } catch (Exception $e) {
                // Show error message
                add_action('admin_notices', function() use ($e) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . 
                         sprintf(esc_html__('Failed to delete site: %s', 'iwp-wp-integration'), esc_html($e->getMessage())) . 
                         '</p></div>';
                });
            }
        }
    }

    /**
     * AJAX: Send credentials to customer
     *
     * Marks site credentials as released and sends an email to the customer.
     */
    public function ajax_send_credentials() {
        check_ajax_referer('iwp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $site_id = sanitize_text_field($_POST['site_id'] ?? '');
        if (empty($site_id)) {
            wp_send_json_error('Missing site ID');
        }

        // Get site from database
        IWP_Sites_Model::init();
        $site = IWP_Sites_Model::get_by_site_id($site_id);

        if (!$site) {
            wp_send_json_error('Site not found');
        }

        // Mark credentials as released in source_data
        $source_data = !empty($site->source_data) ? json_decode($site->source_data, true) : array();
        if (!is_array($source_data)) {
            $source_data = array();
        }
        $source_data['credentials_released'] = true;
        $source_data['credentials_released_at'] = current_time('mysql');
        $source_data['credentials_released_by'] = get_current_user_id();

        IWP_Sites_Model::update($site_id, array(
            'source_data' => $source_data,
        ));

        // Send email to customer
        $email_sent = $this->send_credentials_email($site);

        // Add order note if order exists
        if (!empty($site->order_id)) {
            $order = class_exists('FluentCart\App\Models\Order') ? \FluentCart\App\Models\Order::find($site->order_id) : null;
            if ($order) {
                $order->addLog('InstaWP credentials released', $site->site_url ?: $site_id);

            }
        }

        IWP_Logger::info('Credentials released for site', 'admin', array(
            'site_id' => $site_id,
            'email_sent' => $email_sent,
            'user_id' => get_current_user_id()
        ));

        wp_send_json_success(array(
            'message' => $email_sent
                ? __('Credentials sent to customer successfully!', 'iwp-wp-integration')
                : __('Credentials released but email could not be sent. Customer can now see credentials in their account.', 'iwp-wp-integration'),
            'email_sent' => $email_sent,
        ));
    }

    /**
     * Send credentials email to the customer for a site
     *
     * @param object $site Database site row
     * @return bool Whether email was sent
     */
    private function send_credentials_email($site) {
        // Determine recipient email
        $to = '';

        // Try order billing email first
        if (!empty($site->order_id)) {
            $order = class_exists('FluentCart\App\Models\Order') ? \FluentCart\App\Models\Order::find($site->order_id) : null;
            if ($order) {
                $to = ($order->customer->email ?? '');
            }
        }

        // Fallback to user email
        if (empty($to) && !empty($site->user_id)) {
            $user = get_user_by('id', $site->user_id);
            if ($user) {
                $to = $user->user_email;
            }
        }

        // Try source_data email (for demo sites)
        if (empty($to) && !empty($site->source_data)) {
            $source_data = json_decode($site->source_data, true);
            if (!empty($source_data['email'])) {
                $to = $source_data['email'];
            }
        }

        if (empty($to)) {
            IWP_Logger::warning('Cannot send credentials email: no recipient found', 'admin', array(
                'site_id' => $site->site_id,
            ));
            return false;
        }

        $site_name = get_option('blogname');
        $subject = sprintf(__('Your site login details are ready - %s', 'iwp-wp-integration'), $site_name);

        // Build magic login URL
        $login_url = '';
        $login_label = '';
        if (!empty($site->s_hash)) {
            $login_url = IWP_PLUGIN_APP_URL . '/wordpress-auto-login?site=' . urlencode($site->s_hash);
            $login_label = __('Magic Login', 'iwp-wp-integration');
        } elseif (!empty($site->site_url)) {
            $login_url = trailingslashit($site->site_url) . 'wp-admin';
            $login_label = __('Admin Login', 'iwp-wp-integration');
        }

        // Build HTML email
        $message = '<div style="max-width: 600px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';
        $message .= '<div style="background: #0073aa; padding: 20px; border-radius: 4px 4px 0 0;">';
        $message .= '<h1 style="color: white; margin: 0; font-size: 22px;">' . esc_html__('Your Site is Ready!', 'iwp-wp-integration') . '</h1>';
        $message .= '</div>';
        $message .= '<div style="padding: 20px; background: #ffffff; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 4px 4px;">';

        if (!empty($site->site_url)) {
            $message .= '<p><strong>' . esc_html__('Site URL:', 'iwp-wp-integration') . '</strong> <a href="' . esc_url($site->site_url) . '">' . esc_html($site->site_url) . '</a></p>';
        }
        if (!empty($site->wp_username)) {
            $message .= '<p><strong>' . esc_html__('Username:', 'iwp-wp-integration') . '</strong> <code style="background: #f1f1f1; padding: 2px 6px;">' . esc_html($site->wp_username) . '</code></p>';
        }
        if (!empty($site->wp_password)) {
            $message .= '<p><strong>' . esc_html__('Password:', 'iwp-wp-integration') . '</strong> <code style="background: #f1f1f1; padding: 2px 6px;">' . esc_html($site->wp_password) . '</code></p>';
        }
        if (!empty($login_url)) {
            $message .= '<p style="margin-top: 20px;"><a href="' . esc_url($login_url) . '" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">' . esc_html($login_label) . '</a></p>';
        }

        $message .= '<p style="color: #666; font-size: 13px; margin-top: 20px;">' . esc_html__('We recommend changing your password after your first login.', 'iwp-wp-integration') . '</p>';
        $message .= '</div>';
        $message .= '</div>';

        return wp_mail($to, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
}
