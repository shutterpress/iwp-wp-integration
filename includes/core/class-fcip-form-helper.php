<?php
/**
 * Form Helper Class
 *
 * @package FCIP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FCIP_Form_Helper class
 * 
 * Centralized form rendering and HTML generation
 */
class FCIP_Form_Helper {

    /**
     * Render form field
     *
     * @param string $type Field type
     * @param array $args Field arguments
     * @return string
     */
    public static function render_field($type, $args = array()) {
        $defaults = array(
            'id' => '',
            'name' => '',
            'value' => '',
            'label' => '',
            'description' => '',
            'placeholder' => '',
            'class' => '',
            'required' => false,
            'options' => array()
        );

        $args = wp_parse_args($args, $defaults);
        
        $output = '';
        
        // Start field wrapper
        $output .= '<div class="fcip-form-field fcip-form-field-' . esc_attr($type) . '">';
        
        // Add label
        if (!empty($args['label'])) {
            $required_star = $args['required'] ? ' <span class="required">*</span>' : '';
            $output .= '<label for="' . esc_attr($args['id']) . '" class="fcip-form-label">';
            $output .= esc_html($args['label']) . $required_star;
            $output .= '</label>';
        }
        
        // Render field based on type
        switch ($type) {
            case 'text':
            case 'email':
            case 'password':
            case 'url':
                $output .= self::render_input_field($type, $args);
                break;
            case 'textarea':
                $output .= self::render_textarea_field($args);
                break;
            case 'select':
                $output .= self::render_select_field($args);
                break;
            case 'checkbox':
                $output .= self::render_checkbox_field($args);
                break;
            case 'radio':
                $output .= self::render_radio_field($args);
                break;
            case 'hidden':
                $output .= self::render_hidden_field($args);
                break;
            default:
                $output .= self::render_input_field('text', $args);
        }
        
        // Add description
        if (!empty($args['description'])) {
            $output .= '<p class="fcip-form-description">' . wp_kses_post($args['description']) . '</p>';
        }
        
        // End field wrapper
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Render input field
     *
     * @param string $type
     * @param array $args
     * @return string
     */
    private static function render_input_field($type, $args) {
        $attributes = array(
            'type' => $type,
            'id' => $args['id'],
            'name' => $args['name'],
            'value' => $args['value'],
            'class' => 'fcip-form-input ' . $args['class'],
            'placeholder' => $args['placeholder']
        );

        if ($args['required']) {
            $attributes['required'] = 'required';
        }

        return '<input ' . self::build_attributes($attributes) . ' />';
    }

    /**
     * Render textarea field
     *
     * @param array $args
     * @return string
     */
    private static function render_textarea_field($args) {
        $attributes = array(
            'id' => $args['id'],
            'name' => $args['name'],
            'class' => 'fcip-form-textarea ' . $args['class'],
            'placeholder' => $args['placeholder'],
            'rows' => isset($args['rows']) ? $args['rows'] : 4,
            'cols' => isset($args['cols']) ? $args['cols'] : 50
        );

        if ($args['required']) {
            $attributes['required'] = 'required';
        }

        return '<textarea ' . self::build_attributes($attributes) . '>' . esc_textarea($args['value']) . '</textarea>';
    }

    /**
     * Render select field
     *
     * @param array $args
     * @return string
     */
    private static function render_select_field($args) {
        $attributes = array(
            'id' => $args['id'],
            'name' => $args['name'],
            'class' => 'fcip-form-select ' . $args['class']
        );

        if ($args['required']) {
            $attributes['required'] = 'required';
        }

        $output = '<select ' . self::build_attributes($attributes) . '>';
        
        foreach ($args['options'] as $value => $label) {
            $selected = selected($args['value'], $value, false);
            $output .= '<option value="' . esc_attr($value) . '"' . $selected . '>';
            $output .= esc_html($label);
            $output .= '</option>';
        }
        
        $output .= '</select>';
        
        return $output;
    }

    /**
     * Render checkbox field
     *
     * @param array $args
     * @return string
     */
    private static function render_checkbox_field($args) {
        $attributes = array(
            'type' => 'checkbox',
            'id' => $args['id'],
            'name' => $args['name'],
            'value' => isset($args['checkbox_value']) ? $args['checkbox_value'] : '1',
            'class' => 'fcip-form-checkbox ' . $args['class']
        );

        if ($args['value']) {
            $attributes['checked'] = 'checked';
        }

        return '<input ' . self::build_attributes($attributes) . ' />';
    }

    /**
     * Render radio field
     *
     * @param array $args
     * @return string
     */
    private static function render_radio_field($args) {
        $output = '';
        
        foreach ($args['options'] as $value => $label) {
            $attributes = array(
                'type' => 'radio',
                'id' => $args['id'] . '_' . $value,
                'name' => $args['name'],
                'value' => $value,
                'class' => 'fcip-form-radio ' . $args['class']
            );

            if ($args['value'] == $value) {
                $attributes['checked'] = 'checked';
            }

            $output .= '<label class="fcip-radio-label">';
            $output .= '<input ' . self::build_attributes($attributes) . ' />';
            $output .= ' ' . esc_html($label);
            $output .= '</label>';
        }
        
        return $output;
    }

    /**
     * Render hidden field
     *
     * @param array $args
     * @return string
     */
    private static function render_hidden_field($args) {
        $attributes = array(
            'type' => 'hidden',
            'id' => $args['id'],
            'name' => $args['name'],
            'value' => $args['value']
        );

        return '<input ' . self::build_attributes($attributes) . ' />';
    }

    /**
     * Build HTML attributes string
     *
     * @param array $attributes
     * @return string
     */
    private static function build_attributes($attributes) {
        $output = '';
        
        foreach ($attributes as $key => $value) {
            if ($value !== '' && $value !== null) {
                $output .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
            }
        }
        
        return $output;
    }

    /**
     * Render site card
     *
     * @param array $site_data
     * @param string $context
     * @param array $args
     * @return string
     */
    public static function render_site_card($site_data, $context = 'default', $args = array()) {
        $defaults = array(
            'show_actions' => true,
            'show_details' => true,
            'card_class' => '',
            'order_id' => null
        );

        $args = wp_parse_args($args, $defaults);
        
        $output = '';
        
        // Start card
        $card_classes = 'fcip-site-card fcip-site-card-' . esc_attr($context) . ' ' . $args['card_class'];
        $output .= '<div class="' . esc_attr($card_classes) . '">';
        
        // Card header
        $output .= '<div class="fcip-site-card-header">';
        $output .= '<h4 class="fcip-site-title">';
        $output .= isset($site_data['name']) ? esc_html($site_data['name']) : __('InstaWP Site', 'fluentcart-instawp-provisioning');
        $output .= '</h4>';
        
        // Status badge
        if (isset($site_data['status'])) {
            $status_class = 'fcip-status-' . esc_attr($site_data['status']);
            $status_text = self::get_status_text($site_data['status']);
            $output .= '<span class="fcip-status-badge ' . $status_class . '">' . esc_html($status_text) . '</span>';
        }
        
        $output .= '</div>';
        
        // Card content
        if ($args['show_details']) {
            $output .= '<div class="fcip-site-card-content">';
            
            // Site URL
            if (!empty($site_data['wp_url']) || !empty($site_data['site_url'])) {
                $site_url = !empty($site_data['wp_url']) ? $site_data['wp_url'] : $site_data['site_url'];
                $output .= '<div class="fcip-site-detail">';
                $output .= '<strong>' . __('Site URL:', 'fluentcart-instawp-provisioning') . '</strong> ';
                $output .= '<a href="' . esc_url($site_url) . '" target="_blank" rel="noopener noreferrer">';
                $output .= esc_html($site_url);
                $output .= '</a>';
                $output .= '</div>';
            }
            
            // Admin credentials
            if (!empty($site_data['wp_username'])) {
                $output .= '<div class="fcip-site-detail">';
                $output .= '<strong>' . __('Admin Username:', 'fluentcart-instawp-provisioning') . '</strong> ';
                $output .= '<code class="fcip-copyable" data-copy="' . esc_attr($site_data['wp_username']) . '">';
                $output .= esc_html($site_data['wp_username']);
                $output .= '</code>';
                $output .= ' <button type="button" class="fcip-copy-btn" data-copy="' . esc_attr($site_data['wp_username']) . '">';
                $output .= __('Copy', 'fluentcart-instawp-provisioning');
                $output .= '</button>';
                $output .= '</div>';
            }
            
            if (!empty($site_data['wp_password'])) {
                $output .= '<div class="fcip-site-detail">';
                $output .= '<strong>' . __('Admin Password:', 'fluentcart-instawp-provisioning') . '</strong> ';
                $output .= '<span class="fcip-password-field">';
                $output .= '<code class="fcip-password fcip-password-hidden">••••••••</code>';
                $output .= '<code class="fcip-password fcip-password-visible" style="display:none;">';
                $output .= esc_html($site_data['wp_password']);
                $output .= '</code>';
                $output .= '</span>';
                $output .= ' <button type="button" class="fcip-toggle-password">' . __('Show', 'fluentcart-instawp-provisioning') . '</button>';
                $output .= ' <button type="button" class="fcip-copy-btn" data-copy="' . esc_attr($site_data['wp_password']) . '">';
                $output .= __('Copy', 'fluentcart-instawp-provisioning');
                $output .= '</button>';
                $output .= '</div>';
            }
            
            // Creation date
            if (!empty($site_data['created_at'])) {
                $output .= '<div class="fcip-site-detail">';
                $output .= '<strong>' . __('Created:', 'fluentcart-instawp-provisioning') . '</strong> ';
                $output .= esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($site_data['created_at'])));
                $output .= '</div>';
            }
            
            $output .= '</div>';
        }
        
        // Card actions
        if ($args['show_actions']) {
            $output .= '<div class="fcip-site-card-actions">';
            
            // Visit site button
            if (!empty($site_data['wp_url']) || !empty($site_data['site_url'])) {
                $site_url = !empty($site_data['wp_url']) ? $site_data['wp_url'] : $site_data['site_url'];
                $output .= '<a href="' . esc_url($site_url) . '" target="_blank" class="fcip-btn fcip-btn-primary">';
                $output .= __('Visit Site', 'fluentcart-instawp-provisioning');
                $output .= '</a>';
            }
            
            // Admin login button
            if (!empty($site_data['s_hash'])) {
                $magic_login_url = FCIP_PLUGIN_APP_URL . '/wordpress-auto-login?site=' . urlencode($site_data['s_hash']);
                $output .= '<a href="' . esc_url($magic_login_url) . '" target="_blank" class="fcip-btn fcip-btn-secondary">';
                $output .= __('Magic Login', 'fluentcart-instawp-provisioning');
                $output .= '</a>';
            } elseif (!empty($site_data['wp_url']) || !empty($site_data['site_url'])) {
                $site_url = !empty($site_data['wp_url']) ? $site_data['wp_url'] : $site_data['site_url'];
                $admin_url = rtrim($site_url, '/') . '/wp-admin';
                $output .= '<a href="' . esc_url($admin_url) . '" target="_blank" class="fcip-btn fcip-btn-secondary">';
                $output .= __('Admin Login', 'fluentcart-instawp-provisioning');
                $output .= '</a>';
            }
            
            // Domain mapping button (for completed sites with site_id)
            if (isset($site_data['status']) && $site_data['status'] === 'completed' && !empty($site_data['site_id'])) {
                $output .= '<button type="button" class="fcip-btn fcip-btn-tertiary fcip-map-domain-btn" ';
                $output .= 'data-site-id="' . esc_attr($site_data['site_id']) . '" ';
                $output .= 'data-order-id="' . esc_attr($args['order_id']) . '">';
                $output .= __('Map Domain', 'fluentcart-instawp-provisioning');
                $output .= '</button>';
            }
            
            $output .= '</div>';
        }
        
        // End card
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Get status text for display
     *
     * @param string $status
     * @return string
     */
    private static function get_status_text($status) {
        $status_texts = array(
            'completed' => __('Ready', 'fluentcart-instawp-provisioning'),
            'progress' => __('Creating...', 'fluentcart-instawp-provisioning'),
            'failed' => __('Failed', 'fluentcart-instawp-provisioning'),
            'pending' => __('Pending', 'fluentcart-instawp-provisioning')
        );

        return isset($status_texts[$status]) ? $status_texts[$status] : ucfirst($status);
    }

    /**
     * Render admin notice
     *
     * @param string $message
     * @param string $type
     * @param bool $dismissible
     * @return string
     */
    public static function render_admin_notice($message, $type = 'info', $dismissible = true) {
        $classes = array(
            'notice',
            'notice-' . $type
        );

        if ($dismissible) {
            $classes[] = 'is-dismissible';
        }

        $output = '<div class="' . implode(' ', $classes) . '">';
        $output .= '<p>' . wp_kses_post($message) . '</p>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render loading spinner
     *
     * @param string $text
     * @return string
     */
    public static function render_spinner($text = '') {
        $output = '<div class="fcip-spinner-container">';
        $output .= '<div class="fcip-spinner"></div>';
        if (!empty($text)) {
            $output .= '<span class="fcip-spinner-text">' . esc_html($text) . '</span>';
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Render modal
     *
     * @param string $id
     * @param string $title
     * @param string $content
     * @param array $args
     * @return string
     */
    public static function render_modal($id, $title, $content, $args = array()) {
        $defaults = array(
            'size' => 'medium',
            'close_button' => true,
            'backdrop_close' => true
        );

        $args = wp_parse_args($args, $defaults);

        $output = '<div id="' . esc_attr($id) . '" class="fcip-modal fcip-modal-' . esc_attr($args['size']) . '" style="display:none;">';
        $output .= '<div class="fcip-modal-backdrop"></div>';
        $output .= '<div class="fcip-modal-content">';
        
        // Modal header
        $output .= '<div class="fcip-modal-header">';
        $output .= '<h3 class="fcip-modal-title">' . esc_html($title) . '</h3>';
        if ($args['close_button']) {
            $output .= '<button type="button" class="fcip-modal-close">&times;</button>';
        }
        $output .= '</div>';
        
        // Modal body
        $output .= '<div class="fcip-modal-body">';
        $output .= $content;
        $output .= '</div>';
        
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render progress bar
     *
     * @param int $percentage
     * @param string $text
     * @return string
     */
    public static function render_progress_bar($percentage, $text = '') {
        $percentage = max(0, min(100, $percentage));
        
        $output = '<div class="fcip-progress-container">';
        $output .= '<div class="fcip-progress-bar">';
        $output .= '<div class="fcip-progress-fill" style="width: ' . esc_attr($percentage) . '%"></div>';
        $output .= '</div>';
        if (!empty($text)) {
            $output .= '<div class="fcip-progress-text">' . esc_html($text) . '</div>';
        }
        $output .= '</div>';

        return $output;
    }
}
