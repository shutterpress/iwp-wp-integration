<?php
/**
 * Settings Page class for FCIP Integration
 *
 * @package FCIP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FCIP_Settings_Page class
 */
class FCIP_Settings_Page {

    /**
     * API Client instance
     *
     * @var FCIP_API_Client
     */
    private $api_client;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new FCIP_API_Client();
    }

    /**
     * Render the complete settings page with tabs
     */
    public function render() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('FluentCart InstaWP Provisioning Settings', 'fluentcart-instawp-provisioning'); ?></h1>
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper fcip-admin-tabs">
                <a href="#general-settings" class="nav-tab nav-tab-active" id="general-settings-tab">
                    <?php esc_html_e('General Settings', 'fluentcart-instawp-provisioning'); ?>
                </a>
                <a href="#instawp-data" class="nav-tab" id="instawp-data-tab">
                    <?php esc_html_e('InstaWP Data', 'fluentcart-instawp-provisioning'); ?>
                </a>
                <a href="#testing" class="nav-tab" id="testing-tab">
                    <?php esc_html_e('Testing & Development', 'fluentcart-instawp-provisioning'); ?>
                </a>
                <a href="#documentation" class="nav-tab" id="documentation-tab">
                    <?php esc_html_e('Documentation', 'fluentcart-instawp-provisioning'); ?>
                </a>
            </nav>
            
            <!-- Tab Content -->
            <div class="tab-content-wrapper">
                <!-- Tab 1: General Settings -->
                <div id="general-settings" class="fcip-tab-content active">
                    <?php $this->render_general_settings_tab(); ?>
                </div>
                
                <!-- Tab 2: InstaWP Data -->
                <div id="instawp-data" class="fcip-tab-content">
                    <?php $this->render_instawp_data_tab(); ?>
                </div>
                
                <!-- Tab 3: Testing & Development -->
                <div id="testing" class="fcip-tab-content">
                    <?php $this->render_testing_tab(); ?>
                </div>
                
                <!-- Tab 4: Documentation -->
                <div id="documentation" class="fcip-tab-content">
                    <?php $this->render_documentation_tab(); ?>
                </div>
            </div>
        </div>
        
        <?php $this->render_inline_scripts(); ?>
        <?php
    }

    /**
     * Render General Settings tab content
     */
    private function render_general_settings_tab() {
        ?>
        <form method="post" action="options.php" class="fcip-settings-form">
            <?php
            settings_fields('fcip_settings');
            ?>
            <input type="hidden" name="fcip_options[_form_submitted]" value="general_settings" />
            <?php
            do_settings_sections('fcip_settings');
            submit_button(__('Save Settings', 'fluentcart-instawp-provisioning'), 'primary', 'submit', false);
            ?>
        </form>
        <?php
    }

    /**
     * Render InstaWP Data tab content
     */
    private function render_instawp_data_tab() {
        // Check if API key is configured
        $options = get_option('fcip_options', array());
        $api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
        
        if (empty($api_key)) {
            ?>
            <div class="fcip-data-section">
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('API key is not configured. Please configure your InstaWP API key in the General Settings tab to view snapshots and plans.', 'fluentcart-instawp-provisioning'); ?>
                    </p>
                    <p>
                        <a href="#general-settings" class="button button-primary fcip-switch-tab" data-tab="general-settings">
                            <?php esc_html_e('Go to General Settings', 'fluentcart-instawp-provisioning'); ?>
                        </a>
                    </p>
                </div>
            </div>
            <?php
            return;
        }
        
        ?>
        <div class="fcip-data-section">
            <!-- Team Selection Section -->
            <div class="fcip-team-section">
                <h3><?php esc_html_e('Team Selection', 'fluentcart-instawp-provisioning'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Select a team to filter snapshots and plans. By default, your logged-in team data will be shown.', 'fluentcart-instawp-provisioning'); ?>
                </p>
                
                <div class="fcip-team-controls">
                    <div class="fcip-team-dropdown-container">
                        <select id="fcip-team-select" class="fcip-team-select">
                            <?php $this->render_team_options(); ?>
                        </select>
                        <button type="button" id="fcip-refresh-teams" class="button button-secondary">
                            <?php esc_html_e('Refresh Teams', 'fluentcart-instawp-provisioning'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <h3><?php esc_html_e('Snapshots & Plans', 'fluentcart-instawp-provisioning'); ?></h3>
            <p class="description">
                <?php esc_html_e('View and manage your InstaWP snapshots and plans. Click refresh buttons to update cached data.', 'fluentcart-instawp-provisioning'); ?>
            </p>
            
            <div class="fcip-data-grid">
                <div class="fcip-data-box">
                    <h4><?php esc_html_e('InstaWP Snapshots', 'fluentcart-instawp-provisioning'); ?></h4>
                    <div id="fcip-snapshots-list">
                        <?php $this->display_snapshots(); ?>
                    </div>
                    <button type="button" id="fcip-refresh-snapshots" class="button button-secondary">
                        <?php esc_html_e('Refresh Snapshots', 'fluentcart-instawp-provisioning'); ?>
                    </button>
                </div>
                
                <div class="fcip-data-box">
                    <h4><?php esc_html_e('InstaWP Plans', 'fluentcart-instawp-provisioning'); ?></h4>
                    <div id="fcip-plans-list">
                        <?php $this->display_plans(); ?>
                    </div>
                    <button type="button" id="fcip-refresh-plans" class="button button-secondary">
                        <?php esc_html_e('Refresh Plans', 'fluentcart-instawp-provisioning'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Testing & Development tab content
     */
    private function render_testing_tab() {
        ?>
        <div class="fcip-testing-section">
            <!-- Debug Settings Section -->
            <div class="fcip-debug-settings">
                <h3><?php esc_html_e('Debug Settings', 'fluentcart-instawp-provisioning'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Configure debugging and logging options for troubleshooting.', 'fluentcart-instawp-provisioning'); ?>
                </p>
                
                <form method="post" action="options.php">
                    <?php settings_fields('fcip_settings'); ?>
                    <input type="hidden" name="fcip_options[_form_submitted]" value="debug_settings" />
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Debug Mode', 'fluentcart-instawp-provisioning'); ?></th>
                            <td>
                                <?php 
                                $options = get_option('fcip_options', array());
                                $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : 'no';
                                ?>
                                <label>
                                    <input type="checkbox" name="fcip_options[debug_mode]" value="yes" <?php checked($debug_mode, 'yes'); ?> />
                                    <?php esc_html_e('Enable debug mode for troubleshooting', 'fluentcart-instawp-provisioning'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Log Level', 'fluentcart-instawp-provisioning'); ?></th>
                            <td>
                                <?php 
                                $log_level = isset($options['log_level']) ? $options['log_level'] : 'error';
                                ?>
                                <select name="fcip_options[log_level]">
                                    <option value="debug" <?php selected($log_level, 'debug'); ?>><?php esc_html_e('Debug', 'fluentcart-instawp-provisioning'); ?></option>
                                    <option value="info" <?php selected($log_level, 'info'); ?>><?php esc_html_e('Info', 'fluentcart-instawp-provisioning'); ?></option>
                                    <option value="warning" <?php selected($log_level, 'warning'); ?>><?php esc_html_e('Warning', 'fluentcart-instawp-provisioning'); ?></option>
                                    <option value="error" <?php selected($log_level, 'error'); ?>><?php esc_html_e('Error', 'fluentcart-instawp-provisioning'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Select the logging level for plugin operations.', 'fluentcart-instawp-provisioning'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Debug Settings', 'fluentcart-instawp-provisioning')); ?>
                </form>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <p><?php esc_html_e('To test provisioning, configure an InstaWP feed on a FluentCart product and complete a purchase using your payment gateway test mode.', 'fluentcart-instawp-provisioning'); ?></p>

        </div>
        <?php
    }

    /**
     * Render Documentation tab content
     */
    private function render_documentation_tab() {
        $active_doc = isset($_GET['doc']) ? sanitize_key($_GET['doc']) : 'index';
        $renderer = new FCIP_Docs_Renderer();
        $renderer->render($active_doc);
    }

    /**
     * Display snapshots data
     */
    private function display_snapshots() {
        // Get team-specific or general cache key
        $options = get_option('fcip_options', array());
        $team_id = isset($options['selected_team_id']) ? intval($options['selected_team_id']) : null;
        $cache_key = $team_id ? 'fcip_snapshots_team_' . $team_id : 'fcip_snapshots';
        $snapshots = get_transient($cache_key);
        
        if (empty($snapshots)) {
            echo '<p>' . esc_html__('No snapshots cached. Click refresh to load data.', 'fluentcart-instawp-provisioning') . '</p>';
            return;
        }

        if (!is_array($snapshots)) {
            echo '<p>' . esc_html__('Invalid snapshot data format. Click refresh to reload.', 'fluentcart-instawp-provisioning') . '</p>';
            return;
        }

        echo '<div class="fcip-snapshots-grid">';
        foreach ($snapshots as $snapshot) {
            if (!is_array($snapshot)) {
                continue;
            }
            
            $name = $snapshot['name'] ?? $snapshot['title'] ?? 'Unnamed';
            $slug = $snapshot['slug'] ?? $snapshot['id'] ?? 'No slug';
            
            printf(
                '<div class="fcip-snapshot-item"><strong>%s</strong><br><small>Slug: %s</small></div>',
                esc_html($name),
                esc_html($slug)
            );
        }
        echo '</div>';
    }

    /**
     * Display plans data
     */
    private function display_plans() {
        // Get team-specific or general cache key
        $options = get_option('fcip_options', array());
        $team_id = isset($options['selected_team_id']) ? intval($options['selected_team_id']) : null;
        $cache_key = $team_id ? 'fcip_plans_team_' . $team_id : 'fcip_plans';
        $plans = get_transient($cache_key);
        
        if (empty($plans)) {
            echo '<p>' . esc_html__('No plans cached. Click refresh to load data.', 'fluentcart-instawp-provisioning') . '</p>';
            return;
        }

        if (!is_array($plans)) {
            echo '<p>' . esc_html__('Invalid plan data format. Click refresh to reload.', 'fluentcart-instawp-provisioning') . '</p>';
            return;
        }

        // Check if we have any plans
        if (count($plans) === 0) {
            echo '<p>' . esc_html__('No plans found. Click refresh to reload.', 'fluentcart-instawp-provisioning') . '</p>';
            return;
        }

        echo '<div class="fcip-plans-grid">';
        foreach ($plans as $plan) {
            // Process each plan in the array
            if (!is_array($plan) || !isset($plan['id'])) {
                continue;
            }
            
            // Use the same structure as the admin file
            $plan_id = isset($plan['id']) ? sanitize_text_field($plan['id']) : 'No ID';
            $plan_name = isset($plan['display_name']) ? sanitize_text_field($plan['display_name']) : 
                        (isset($plan['name']) ? sanitize_text_field($plan['name']) : 'Unnamed Plan');
            $plan_description = isset($plan['short_description']) ? sanitize_text_field($plan['short_description']) : '';
            
            echo '<div class="fcip-plan-item">';
            echo '<strong>' . esc_html($plan_name) . '</strong><br>';
            if ($plan_description) {
                echo '<small style="color: #666;">' . esc_html($plan_description) . '</small><br>';
            }
            echo '<small>ID: ' . esc_html($plan_id) . '</small>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Render team selection dropdown options
     */
    private function render_team_options() {
        require_once FCIP_PLUGIN_PATH . 'includes/core/class-fcip-service.php';
        
        $teams = FCIP_Service::get_teams_for_dropdown();
        $selected_team = FCIP_Service::get_selected_team();
        
        foreach ($teams as $value => $label) {
            $selected = selected($selected_team, $value, false);
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
    }

    /**
     * Render test order form
     */

    /**
     * Render inline scripts for tab functionality
     */
    private function render_inline_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Simple tab switching
            $('.fcip-admin-tabs .nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var targetTab = $(this).attr('href').substring(1);
                
                // Update nav tabs
                $('.fcip-admin-tabs .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Update content
                $('.fcip-tab-content').removeClass('active');
                $('#' + targetTab).addClass('active');
                
                // Save state
                localStorage.setItem('fcip_active_tab', targetTab);
            });
            
            // Restore active tab
            var savedTab = localStorage.getItem('fcip_active_tab');
            if (savedTab && $('#' + savedTab).length) {
                $('.fcip-admin-tabs .nav-tab[href="#' + savedTab + '"]').trigger('click');
            }
            
            // Handle tab switching from buttons
            $('.fcip-switch-tab').on('click', function(e) {
                e.preventDefault();
                var targetTab = $(this).data('tab');
                $('.fcip-admin-tabs .nav-tab[href="#' + targetTab + '"]').trigger('click');
            });
            
            // Team selection handler
            $('#fcip-team-select').on('change', function() {
                var teamId = $(this).val();
                var $select = $(this);
                var originalHtml = $select.html();
                
                // Show loading state
                $select.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'fcip_set_team',
                    team_id: teamId || null,
                    nonce: '<?php echo wp_create_nonce('fcip_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Refresh snapshots and plans for the new team
                        $('#fcip-refresh-snapshots').trigger('click');
                        setTimeout(function() {
                            $('#fcip-refresh-plans').trigger('click');
                        }, 500);
                    } else {
                        alert('<?php esc_html_e('Failed to set team', 'fluentcart-instawp-provisioning'); ?>');
                        // Restore original selection on error
                        $select.html(originalHtml);
                    }
                }).fail(function() {
                    alert('<?php esc_html_e('Network error occurred', 'fluentcart-instawp-provisioning'); ?>');
                    $select.html(originalHtml);
                }).always(function() {
                    $select.prop('disabled', false);
                });
            });
            
            // Refresh teams handler
            $('#fcip-refresh-teams').on('click', function() {
                var $btn = $(this);
                var $select = $('#fcip-team-select');
                var originalText = $btn.text();
                var selectedValue = $select.val();
                
                $btn.text('<?php esc_html_e('Loading...', 'fluentcart-instawp-provisioning'); ?>').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'fcip_refresh_teams',
                    nonce: '<?php echo wp_create_nonce('fcip_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Reload the page to refresh the dropdown
                        location.reload();
                    } else {
                        alert('<?php esc_html_e('Failed to refresh teams', 'fluentcart-instawp-provisioning'); ?>');
                    }
                }).fail(function() {
                    alert('<?php esc_html_e('Network error occurred', 'fluentcart-instawp-provisioning'); ?>');
                }).always(function() {
                    $btn.text(originalText).prop('disabled', false);
                });
            });
            
            // AJAX handlers
            $('#fcip-refresh-snapshots').on('click', function() {
                var $btn = $(this);
                var originalText = $btn.text();
                
                $btn.text('<?php esc_html_e('Loading...', 'fluentcart-instawp-provisioning'); ?>').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'fcip_refresh_snapshots',
                    nonce: '<?php echo wp_create_nonce('fcip_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('<?php esc_html_e('Failed to refresh snapshots', 'fluentcart-instawp-provisioning'); ?>');
                    }
                }).always(function() {
                    $btn.text(originalText).prop('disabled', false);
                });
            });
            
            $('#fcip-refresh-plans').on('click', function() {
                var $btn = $(this);
                var originalText = $btn.text();
                
                $btn.text('<?php esc_html_e('Loading...', 'fluentcart-instawp-provisioning'); ?>').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'fcip_refresh_plans',
                    nonce: '<?php echo wp_create_nonce('fcip_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('<?php esc_html_e('Failed to refresh plans', 'fluentcart-instawp-provisioning'); ?>');
                    }
                }).always(function() {
                    $btn.text(originalText).prop('disabled', false);
                });
            });
            

        });
        </script>
        
        <style>
        .fcip-admin-tabs {
            border-bottom: 1px solid #ccd0d4;
            margin: 0 0 0px 0;
        }
        
        .fcip-tab-content {
            display: none;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-top: none;
            padding: 20px;
            border-radius: 0 0 5px 5px;
        }
        
        .fcip-tab-content.active {
            display: block;
        }
        
        .fcip-data-grid,
        .fcip-docs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .fcip-data-box,
        .fcip-docs-box {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 20px;
        }
        
        .fcip-data-box h4,
        .fcip-docs-box h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #1d2327;
        }
        
        .fcip-snapshots-grid,
        .fcip-plans-grid {
            margin: 15px 0;
        }
        
        .fcip-snapshot-item,
        .fcip-plan-item {
            padding: 8px 12px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-bottom: 5px;
        }
        
        .fcip-test-form-container {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .fcip-test-form-container label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .fcip-test-form-container select {
            margin-bottom: 15px;
        }
        
        .fcip-test-results {
            margin-top: 20px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
            display: none;
        }
        
        .fcip-test-results.has-content {
            display: block;
        }
        
        .fcip-team-section {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .fcip-team-controls {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .fcip-team-label {
            font-weight: 600;
            color: #1d2327;
        }
        
        .fcip-team-dropdown-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .fcip-team-select {
            min-width: 250px;
            height: 32px;
        }
        
        /* Documentation Viewer styles are rendered by FCIP_Docs_Renderer */

        @media (max-width: 768px) {
            .fcip-data-grid {
                grid-template-columns: 1fr;
            }

            .fcip-team-dropdown-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .fcip-team-select {
                width: 100%;
                min-width: auto;
            }
        }
        </style>
        <?php
    }
}
