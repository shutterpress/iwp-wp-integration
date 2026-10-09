<?php
/**
 * Sites List Table for FluentCart InstaWP Provisioning
 *
 * @package FCIP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load WP_List_Table if not already loaded
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * FCIP_Sites_List_Table class
 */
class FCIP_Sites_List_Table extends WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'fcip-site',
            'plural'   => 'fcip-sites',
            'ajax'     => false
        ));
    }

    /**
     * Get list table columns
     *
     * @return array
     */
    public function get_columns() {
        $columns = array(
            'site_url'    => __('Site URL', 'fluentcart-instawp-provisioning'),
            'username'    => __('Username', 'fluentcart-instawp-provisioning'),
            'password'    => __('Password', 'fluentcart-instawp-provisioning'),
            'plan_id'     => __('Plan', 'fluentcart-instawp-provisioning'),
            'user'        => __('User', 'fluentcart-instawp-provisioning'),
            'source'      => __('Source', 'fluentcart-instawp-provisioning'),
            'status'      => __('Status', 'fluentcart-instawp-provisioning'),
            'created'     => __('Created', 'fluentcart-instawp-provisioning')
            // Removed 'actions' column - action buttons now appear below site URL
        );

        // On the Trash view password/status are irrelevant — drop them entirely.
        if (isset($_GET['status']) && sanitize_key(wp_unslash($_GET['status'])) === FCIP_Sites_Model::STATUS_TRASHED) {
            unset($columns['password'], $columns['status']);
        }

        return $columns;
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'site_url' => array('site_url', false),
            'plan_id'  => array('plan_id', false),
            'user'     => array('user', false),
            'source'   => array('source', false),
            'status'   => array('status', false),
            'created'  => array('created', true)
        );
    }

    /**
     * Get bulk actions
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array(); // No bulk actions
    }

    /**
     * Prepare table items
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        // Get all sites data
        $sites = $this->get_all_sites();

        // Apply search filter
        $sites = $this->apply_search_filter($sites);

        // Apply status filter
        $sites = $this->apply_status_filter($sites);

        // Apply source filter
        $sites = $this->apply_source_filter($sites);

        // Handle sorting
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'created';
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'desc';
        $sites = $this->sort_sites($sites, $orderby, $order);

        // Handle pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count($sites);

        $sites = array_slice($sites, (($current_page - 1) * $per_page), $per_page);

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));

        $this->items = $sites;
    }

    /**
     * Get view links (All, Active, Creating, etc.)
     *
     * @return array
     */
    protected function get_views() {
        $all_sites = $this->get_all_sites();
        $total = count($all_sites);

        // Count by status
        $status_counts = array(
            'all' => $total,
            'completed' => 0,
            'progress' => 0,
            'failed' => 0,
            'expired' => 0,
            FCIP_Sites_Model::STATUS_TRASHED => 0,
        );

        foreach ($all_sites as $site) {
            // Count expired sites separately
            if (!empty($site['is_expired'])) {
                $status_counts['expired']++;
            } elseif (isset($site['status']) && isset($status_counts[$site['status']])) {
                $status_counts[$site['status']]++;
            }
        }

        // Exclude trashed from All (WP convention).
        $status_counts['all'] -= $status_counts[FCIP_Sites_Model::STATUS_TRASHED];

        $current_status = isset($_GET['status']) ? $_GET['status'] : 'all';
        $base_url = admin_url('admin.php?page=fcip-sites');

        $views = array();

        // All
        $class = ($current_status === 'all') ? 'current' : '';
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url($base_url),
            $class,
            __('All', 'fluentcart-instawp-provisioning'),
            $status_counts['all']
        );

        // Active
        if ($status_counts['completed'] > 0) {
            $class = ($current_status === 'completed') ? 'current' : '';
            $views['completed'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', 'completed', $base_url)),
                $class,
                __('Active', 'fluentcart-instawp-provisioning'),
                $status_counts['completed']
            );
        }

        // Creating
        if ($status_counts['progress'] > 0) {
            $class = ($current_status === 'progress') ? 'current' : '';
            $views['progress'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', 'progress', $base_url)),
                $class,
                __('Creating', 'fluentcart-instawp-provisioning'),
                $status_counts['progress']
            );
        }

        // Failed
        if ($status_counts['failed'] > 0) {
            $class = ($current_status === 'failed') ? 'current' : '';
            $views['failed'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', 'failed', $base_url)),
                $class,
                __('Failed', 'fluentcart-instawp-provisioning'),
                $status_counts['failed']
            );
        }

        // Expired
        if ($status_counts['expired'] > 0) {
            $class = ($current_status === 'expired') ? 'current' : '';
            $views['expired'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', 'expired', $base_url)),
                $class,
                __('Expired', 'fluentcart-instawp-provisioning'),
                $status_counts['expired']
            );
        }

        // Trash
        $trashed_count = $status_counts[FCIP_Sites_Model::STATUS_TRASHED];
        if ($trashed_count > 0 || $current_status === FCIP_Sites_Model::STATUS_TRASHED) {
            $class = ($current_status === FCIP_Sites_Model::STATUS_TRASHED) ? 'current' : '';
            $views[FCIP_Sites_Model::STATUS_TRASHED] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', FCIP_Sites_Model::STATUS_TRASHED, $base_url)),
                $class,
                __('Trash', 'fluentcart-instawp-provisioning'),
                $trashed_count
            );
        }

        return $views;
    }

    /**
     * Display extra tablenav (filters and search)
     *
     * @param string $which Top or bottom
     */
    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        ?>
        <div class="alignleft actions">
            <?php $this->source_filter_dropdown(); ?>
            <?php submit_button(__('Filter', 'fluentcart-instawp-provisioning'), '', 'filter_action', false); ?>
        </div>
        <?php
    }

    /**
     * Display source filter dropdown
     */
    protected function source_filter_dropdown() {
        $all_sites = $this->get_all_sites();
        $sources = array();

        // Collect unique sources
        foreach ($all_sites as $site) {
            if (!empty($site['source'])) {
                $source_key = strtolower(str_replace(' ', '_', $site['source']));
                if (!isset($sources[$source_key])) {
                    $sources[$source_key] = $site['source'];
                }
            }
        }

        $current_source = isset($_GET['source_filter']) ? $_GET['source_filter'] : '';

        ?>
        <select name="source_filter" id="source-filter">
            <option value=""><?php _e('All Sources', 'fluentcart-instawp-provisioning'); ?></option>
            <?php foreach ($sources as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($current_source, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Apply search filter to sites
     *
     * @param array $sites Sites array
     * @return array Filtered sites
     */
    private function apply_search_filter($sites) {
        if (empty($_GET['s'])) {
            return $sites;
        }

        $search = strtolower(trim($_GET['s']));

        return array_filter($sites, function($site) use ($search) {
            // Search in site URL
            if (stripos($site['site_url'], $search) !== false) {
                return true;
            }

            // Search in username
            if (stripos($site['username'], $search) !== false) {
                return true;
            }

            // Search in user
            if (stripos($site['user'], $search) !== false) {
                return true;
            }

            // Search in site ID
            if (stripos($site['site_id'], $search) !== false) {
                return true;
            }

            // Search in order ID
            if (!empty($site['order_id']) && stripos((string)$site['order_id'], $search) !== false) {
                return true;
            }

            return false;
        });
    }

    /**
     * Apply status filter to sites
     *
     * @param array $sites Sites array
     * @return array Filtered sites
     */
    private function apply_status_filter($sites) {
        if (empty($_GET['status']) || $_GET['status'] === 'all') {
            // Default/All view hides trashed sites (WP convention).
            return array_filter($sites, function ($site) {
                return !isset($site['status']) || $site['status'] !== FCIP_Sites_Model::STATUS_TRASHED;
            });
        }

        $status = $_GET['status'];

        return array_filter($sites, function($site) use ($status) {
            // Handle expired status (calculated, not a database status)
            if ($status === 'expired') {
                return !empty($site['is_expired']);
            }

            // Handle regular database statuses
            return isset($site['status']) && $site['status'] === $status;
        });
    }

    /**
     * Apply source filter to sites
     *
     * @param array $sites Sites array
     * @return array Filtered sites
     */
    private function apply_source_filter($sites) {
        if (empty($_GET['source_filter'])) {
            return $sites;
        }

        $source_filter = strtolower($_GET['source_filter']);

        return array_filter($sites, function($site) use ($source_filter) {
            $site_source = strtolower(str_replace(' ', '_', $site['source']));
            return $site_source === $source_filter;
        });
    }

    /**
     * Get all sites from various sources
     *
     * @return array
     */
    private function get_all_sites() {
        return $this->get_sites_from_database();
    }

    /**
     * Get sites from database table
     *
     * @return array
     */
    private function get_sites_from_database() {
        $sites = array();

        // Get sites from database table
        $db_sites = FCIP_Sites_Model::get_all(array('limit' => 100));

        foreach ($db_sites as $db_site) {
            $order = null;
            $order_link = '';

            if (!empty($db_site->order_id)) {
                $order = $db_site->source === 'fluentcart' && class_exists('FluentCart\App\Models\Order') ? \FluentCart\App\Models\Order::find($db_site->order_id) : null;
                if ($order) {
                    $order_link = $order->getViewUrl('admin');
                }
            }

            // Check if this site has upgrade history
            $upgrade_info = '';
            $is_upgraded = false;
            if (!empty($db_site->api_response)) {
                $api_data = json_decode($db_site->api_response, true);
                if (is_array($api_data)) {
                    if (isset($api_data['created_from_upgrade']) && $api_data['created_from_upgrade']) {
                        $is_upgraded = true;
                        $upgrade_info = ' (Upgraded)';
                    } elseif (isset($api_data['upgrade_history']) && is_array($api_data['upgrade_history']) && count($api_data['upgrade_history']) > 0) {
                        $is_upgraded = true;
                        $upgrade_count = count($api_data['upgrade_history']);
                        $upgrade_info = sprintf(' (%d upgrade%s)', $upgrade_count, $upgrade_count > 1 ? 's' : '');
                    }
                }
            }

            // Determine source type and badges
            $source_type = $this->determine_source_type($db_site->source, $order);

            // Check if credentials have been released
            $credentials_released = false;
            $site_name = '';
            $user_name = '';
            if (!empty($db_site->source_data)) {
                $source_data_parsed = json_decode($db_site->source_data, true);
                if (is_array($source_data_parsed)) {
                    if (!empty($source_data_parsed['credentials_released'])) {
                        $credentials_released = true;
                    }
                    
                    if (!empty($source_data_parsed['site_data']) && is_array($source_data_parsed['site_data'])) {
                        if (!empty($source_data_parsed['site_data']['site_name'])) {
                            $site_name = $source_data_parsed['site_data']['site_name'];
                        }

                        if (!empty($source_data_parsed['site_data']['user_name'])) {
                            $user_name = $source_data_parsed['site_data']['user_name'];
                        }
                    }
                }
            }

            // Calculate if site is expired (for temporary sites with expiry_hours)
            $is_expired = false;
            $hours_remaining = null;
            if (!empty($db_site->expiry_hours) && !empty($db_site->created_at)) {
                $created_time = strtotime($db_site->created_at);
                $current_time = current_time('timestamp');
                $hours_elapsed = ($current_time - $created_time) / 3600;

                if ($hours_elapsed >= $db_site->expiry_hours) {
                    $is_expired = true;
                } else {
                    $hours_remaining = $db_site->expiry_hours - $hours_elapsed;
                }
            }

            $site = array(
                'site_url'     => $db_site->site_url ?: $site_name,
                'username'     => $db_site->wp_username ?: $user_name,
                'password'     => $db_site->wp_password ?: '',
                'user'         => $this->get_user_display_name($order, $db_site->user_id),
                'source'       => $source_type['text'],
                'source_link'  => $order_link,
                'source_id'    => $db_site->order_id ?: '',
                'source_badge' => $source_type['badge'],
                'status'       => $db_site->status,
                'created'      => $db_site->created_at,
                'site_id'      => $db_site->site_id,
                'order_id'     => $db_site->order_id,
                's_hash'       => $db_site->s_hash ?: '',
                'plan_id'      => $db_site->plan_id ?: '',
                'is_upgraded'  => $is_upgraded,
                'is_expired'   => $is_expired,
                'hours_remaining' => $hours_remaining,
                'expiry_hours' => $db_site->expiry_hours,
                'credentials_released' => $credentials_released,
                // Pass api_response through so column_status can decode
                // the failure message on demand for failed rows (via
                // FCIP_Site_Manager::resolve_failure_message). No
                // precompute — the work only runs when a failed row
                // actually renders.
                'api_response' => $db_site->api_response,
            );

            $sites[] = $site;
        }

        return $sites;
    }

    /**
     * Get sites from FluentCart orders
     *
     * @return array
     */

    /**
     * Format order site data for display
     *
     * @param array $site_data Raw site data
     * @param FluentCart Order $order Order object
     * @return array|null Formatted site data
     */

    /**
     * Determine source type and badge for a site
     *
     * @param string $source Source from database
     * @param FluentCart Order|null $order Order object if available
     * @return array Array with 'text' and 'badge' keys
     */
    private function determine_source_type($source, $order = null) {
        $result = array(
            'text' => '',
            'badge' => null
        );

        // FluentCart sources (with or without order)
        if ($source === 'fluentcart') {
            $result['text'] = 'FluentCart';

        }
        // Shortcode and other sources
        else {
            $result['text'] = $this->format_source_display($source, 'paid');
        }

        return $result;
    }

    /**
     * Get badge HTML for source column
     *
     * @param string $badge_type Type of badge: 'upgraded'.
     * @return string Badge HTML
     */
    private function get_source_badge_html($badge_type) {
        switch ($badge_type) {
            case 'upgraded':
                return '<span class="fcip-badge fcip-badge-upgraded" title="Site plan was upgraded">Upgraded</span>';

            default:
                return '';
        }
    }

    /**
     * Format source display text based on source type
     *
     * @param string $source Source from database
     * @param string $site_type Site type (demo, paid, trial)
     * @return string Formatted source display text
     */
    private function format_source_display($source, $site_type = 'paid') {
        // Handle different source types
        switch ($source) {
            case 'shortcode':
                if ($site_type === 'demo') {
                    return 'Shortcode (Demo)';
                }
                return 'Shortcode';

            case 'fluentcart':
                return 'FluentCart';

            default:
                // Fallback: capitalize and replace underscores
                return ucwords(str_replace('_', ' ', $source));
        }
    }

    /**
     * Get user display name from order or user_id
     *
     * @param FluentCart Order|null $order Order object
     * @param int|null $user_id WordPress user ID from database
     * @return string User display name
     */
    private function get_user_display_name($order = null, $user_id = null) {
        // Try to use user_id from database first
        if ($user_id && $user_id > 0) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                return sprintf('%s (%s)', $user->display_name, $user->user_login);
            }
        }

        // Fallback to order if available
        if ($order) {
            $order_user_id = ($order->customer->user_id ?? 0);

            if ($order_user_id) {
                // Order has a WordPress user
                $user = get_user_by('id', $order_user_id);
                if ($user) {
                    return sprintf('%s (%s)', $user->display_name, $user->user_login);
                }
            }

            // Guest order - use billing information
            $first_name = ($order->customer->first_name ?? '');
            $last_name = ($order->customer->last_name ?? '');
            $email = ($order->customer->email ?? '');

            if ($first_name || $last_name) {
                $name = trim($first_name . ' ' . $last_name);
                return sprintf('%s (guest) <%s>', $name, $email);
            }

            return sprintf('Guest <%s>', $email);
        }

        // No order and user_id is 0 (guest demo site)
        if ($user_id === 0 || $user_id === '0') {
            return 'Guest (Demo)';
        }

        return 'Unknown';
    }

    /**
     * Sort sites array
     *
     * @param array $sites Sites data
     * @param string $orderby Column to sort by
     * @param string $order Sort direction
     * @return array Sorted sites
     */
    private function sort_sites($sites, $orderby, $order) {
        usort($sites, function($a, $b) use ($orderby, $order) {
            $val_a = $a[$orderby] ?? '';
            $val_b = $b[$orderby] ?? '';

            if ($orderby === 'created') {
                $val_a = strtotime($val_a);
                $val_b = strtotime($val_b);
            }

            $result = 0;
            if ($val_a < $val_b) {
                $result = -1;
            } elseif ($val_a > $val_b) {
                $result = 1;
            }

            return ($order === 'desc') ? -$result : $result;
        });

        return $sites;
    }

    /**
     * Display checkbox column - Removed as bulk actions are disabled
     *
     * @param array $item Site data
     * @return string
     */
    /*
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="site[]" value="%s" />',
            $item['site_id'] ?: md5($item['site_url'])
        );
    }
    */

    /**
     * Display site URL column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_site_url($item) {
        $url = esc_url($item['site_url']);

        // Trashed sites are remote-deleted — render URL as plain text, no row actions.
        if (isset($item['status']) && $item['status'] === FCIP_Sites_Model::STATUS_TRASHED) {
            return sprintf('<strong>%s</strong>', esc_html($item['site_url']));
        }

        $site_link = sprintf('<a href="%s" target="_blank"><strong>%s</strong></a>', $url, esc_html($url));

        // Build row actions - consolidating all actions from the removed Actions column
        $actions = array();
        
        // Add "Open in InstaWP" action first (moved from Actions column)
        if (!empty($item['site_id'])) {
            $actions['open_instawp'] = sprintf('<a href="%s" target="_blank">%s</a>', 
                esc_url(FCIP_PLUGIN_APP_URL . '/sites/' . $item['site_id'] . '/dashboard'),
                __('Open in InstaWP', 'fluentcart-instawp-provisioning')
            );
        }
        
        // Always show "Visit Site" action
        $actions['visit'] = sprintf('<a href="%s" target="_blank">%s</a>', $url, __('Visit Site', 'fluentcart-instawp-provisioning'));
        
        // Add Magic Login if s_hash is available
        if (!empty($item['s_hash'])) {
            $magic_login_url = FCIP_PLUGIN_APP_URL . '/wordpress-auto-login?site=' . urlencode($item['s_hash']);
            $actions['magic_login'] = sprintf('<a href="%s" target="_blank">%s</a>', esc_url($magic_login_url), __('Magic Login', 'fluentcart-instawp-provisioning'));
        } else {
            // Fallback to regular wp-admin if no s_hash
            $admin_url = trailingslashit($url) . 'wp-admin';
            $actions['admin_login'] = sprintf('<a href="%s" target="_blank">%s</a>', esc_url($admin_url), __('Admin Login', 'fluentcart-instawp-provisioning'));
        }
        
        // Add "Send Credentials" action if delay_customer_credentials is enabled and not yet released
        $options = get_option('fcip_options', array());
        if (
            isset($options['delay_customer_credentials']) && $options['delay_customer_credentials'] === 'yes'
            && !empty($item['site_id'])
            && ($item['status'] === 'completed')
            && empty($item['credentials_released'])
        ) {
            $actions['send_credentials'] = sprintf(
                '<a href="#" class="fcip-send-credentials" data-site-id="%s" data-nonce="%s" style="color: #0073aa; font-weight: 600;">%s</a>',
                esc_attr($item['site_id']),
                esc_attr(wp_create_nonce('fcip_admin_nonce')),
                __('Send Credentials', 'fluentcart-instawp-provisioning')
            );
        } elseif (!empty($item['credentials_released'])) {
            $actions['credentials_sent'] = '<span style="color: #46b450;">' . __('Credentials Sent', 'fluentcart-instawp-provisioning') . '</span>';
        }

        // Add delete action if we have a site_id
        if (!empty($item['site_id'])) {
            $delete_url = wp_nonce_url(
                admin_url('admin.php?page=fcip-sites&action=delete&site_id=' . urlencode($item['site_id'])),
                'delete_site_' . $item['site_id']
            );
            $actions['delete'] = sprintf('<a href="%s" onclick="return confirm(\'%s\')">%s</a>', 
                esc_url($delete_url), 
                esc_js(__('Are you sure you want to delete this site?', 'fluentcart-instawp-provisioning')),
                __('Delete', 'fluentcart-instawp-provisioning')
            );
        }
        
        return $site_link . $this->row_actions($actions);
    }

    /**
     * Display username column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_username($item) {
        return esc_html($item['username']);
    }

    /**
     * Display password column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_password($item) {
        if (empty($item['password'])) {
            return '—';
        }

        return sprintf(
            '<span class="fcip-password-hidden">••••••••</span> <button type="button" class="fcip-show-password button-link" data-password="%s">%s</button><span class="fcip-password-revealed"></span>',
            esc_attr($item['password']),
            __('Show', 'fluentcart-instawp-provisioning')
        );
    }

    /**
     * Display plan column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_plan_id($item) {
        if (empty($item['plan_id'])) {
            return '—';
        }

        // Get plan name from plan ID using the service helper
        require_once FCIP_PLUGIN_PATH . 'includes/core/class-fcip-service.php';
        $plan_name = FCIP_Service::get_plan_name_by_id($item['plan_id']);
        
        // If plan name is different from plan ID, show name with ID as title
        if ($plan_name !== $item['plan_id']) {
            $plan_display = '<span title="Plan ID: ' . esc_attr($item['plan_id']) . '">' . esc_html($plan_name) . '</span>';
        } else {
            // Fallback to showing plan ID if name not found
            $plan_display = esc_html($item['plan_id']);
        }
        
        // Add upgrade indicator if site has been upgraded
        if (!empty($item['is_upgraded'])) {
            $plan_display .= ' <span class="fcip-upgrade-badge" title="This site has been upgraded">⬆</span>';
        }
        
        return $plan_display;
    }

    /**
     * Display user column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_user($item) {
        return esc_html($item['user']);
    }

    /**
     * Display source column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_source($item) {
        $output = '';

        // Build the base source display
        if (!empty($item['source_link'])) {
            // Has order link
            $output = sprintf(
                '<a href="%s">Order #%s</a>',
                esc_url($item['source_link']),
                esc_html($item['source_id'])
            );
        } else {
            // No order link - just show source text
            $output = esc_html($item['source']);
        }

        // Add badge if present (reusing badge logic)
        if (!empty($item['source_badge'])) {
            $output .= ' ' . $this->get_source_badge_html($item['source_badge']);
        }

        return $output;
    }

    /**
     * Display status column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_status($item) {
        // Check if site is expired first
        if (!empty($item['is_expired'])) {
            return sprintf(
                '<span class="fcip-status fcip-status-expired" title="%s">%s</span>',
                esc_attr__('Site has passed its expiry time', 'fluentcart-instawp-provisioning'),
                __('Expired', 'fluentcart-instawp-provisioning')
            );
        }

        $status = $item['status'];
        $class = '';
        $text = '';
        $title = '';

        switch ($status) {
            case 'completed':
                $class = 'fcip-status-completed';
                $text = __('Active', 'fluentcart-instawp-provisioning');

                // Show time remaining for temporary sites
                if (!empty($item['hours_remaining']) && !empty($item['expiry_hours'])) {
                    $hours_remaining = round($item['hours_remaining'], 1);
                    if ($hours_remaining < 1) {
                        $minutes_remaining = round($hours_remaining * 60);
                        $text .= sprintf(' <span class="fcip-expiry-warning" title="%s">(%d min)</span>',
                            esc_attr__('Time remaining until expiry', 'fluentcart-instawp-provisioning'),
                            $minutes_remaining
                        );
                    } else {
                        $text .= sprintf(' <span class="fcip-expiry-info" title="%s">(%d hrs)</span>',
                            esc_attr__('Time remaining until expiry', 'fluentcart-instawp-provisioning'),
                            $hours_remaining
                        );
                    }
                }
                break;
            case 'progress':
                $class = 'fcip-status-progress';
                $text = __('Creating', 'fluentcart-instawp-provisioning');
                break;
            case 'failed':
                $class = 'fcip-status-failed';
                $text = __('Failed', 'fluentcart-instawp-provisioning');
                // Append the humanized error so the Failed tab shows
                // why each row failed (subdomain taken, etc.) instead
                // of just a Failed badge. Decode on demand from the
                // api_response carried in $item — no precompute.
                $title = class_exists('FCIP_Site_Manager')
                    ? FCIP_Site_Manager::resolve_failure_message($item['api_response'] ?? '')
                    : '';
                break;
            case FCIP_Sites_Model::STATUS_TRASHED:
                $class = 'fcip-status-trashed';
                $text = __('Trashed', 'fluentcart-instawp-provisioning');
                break;
            default:
                $class = 'fcip-status-unknown';
                $text = __('Unknown', 'fluentcart-instawp-provisioning');
        }

        return sprintf('<span class="fcip-status %s" title="%s">%s</span>', $class, esc_html($title), $text);
    }

    /**
     * Display created column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_created($item) {
        if (empty($item['created'])) {
            return '—';
        }

        $created = strtotime($item['created']);
        if ($created) {
            return sprintf(
                '<time datetime="%s" title="%s">%s</time>',
                date('c', $created),
                date('Y-m-d H:i:s', $created),
                human_time_diff($created, current_time('timestamp')) . ' ago'
            );
        }

        return esc_html($item['created']);
    }

    // Note: column_actions method removed - all actions now consolidated in site_url column

    /**
     * Handle default column display
     *
     * @param array $item Site data
     * @param string $column_name Column name
     * @return string
     */
    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '—';
    }

    /**
     * Display when no items found
     */
    public function no_items() {
        esc_html_e('No InstaWP sites found. Sites will appear here after they are created through FluentCart orders or shortcodes.', 'fluentcart-instawp-provisioning');
    }
}
