<?php
/** FluentCart's native product integration editor. */
defined('ABSPATH') || exit;

class IWP_FluentCart_Integration extends \FluentCart\App\Modules\Integrations\BaseIntegrationManager {
    protected $runOnBackgroundForProduct = false;
    public $scopes = array('product');
    public $disableGlobalSettings = true;
    private $processor;

    public function __construct($processor) {
        parent::__construct('InstaWP', 'instawp', 20);
        $this->processor = $processor;
        $this->description = __('Provision an InstaWP site when this product is paid for.', 'iwp-wp-integration');
        $this->logo = IWP_PLUGIN_URL . 'assets/images/instawp-logo.svg';
    }

    public function isConfigured() {
        $options = get_option('iwp_options', array());
        return !empty($options['api_key']) && ($options['enable_integration'] ?? 'yes') === 'yes';
    }

    public function getIntegrationDefaults($settings) {
        return array(
            'enabled' => 'yes', 'name' => 'InstaWP site',
            'snapshot_slug' => '', 'plan_id' => '',
            'expiry_type' => 'permanent', 'expiry_hours' => 24,
            'defer_creation' => 'no', 'manage_subscription' => 'yes',
            'conditional_variation_ids' => array(),
            'event_trigger' => array('order_paid_done'),
        );
    }

    public function getSettingsFields($settings, $args = array()) {
        $fields = array(
            array('key' => 'name', 'label' => __('Feed title', 'iwp-wp-integration'), 'component' => 'text', 'required' => true),
            array('key' => 'snapshot_slug', 'label' => __('InstaWP snapshot', 'iwp-wp-integration'), 'component' => 'select', 'required' => true, 'options' => IWP_Service::get_snapshots_for_dropdown()),
            array('key' => 'plan_id', 'label' => __('InstaWP hosting plan', 'iwp-wp-integration'), 'component' => 'select', 'options' => IWP_Service::get_plans_for_dropdown()),
            array('key' => 'expiry_type', 'label' => __('Site expiry', 'iwp-wp-integration'), 'component' => 'select', 'options' => array('permanent' => __('Permanent', 'iwp-wp-integration'), 'temporary' => __('Temporary', 'iwp-wp-integration'))),
            array('key' => 'expiry_hours', 'label' => __('Temporary site lifetime (hours)', 'iwp-wp-integration'), 'component' => 'text'),
            array('key' => 'defer_creation', 'component' => 'yes-no-checkbox', 'checkbox_label' => __('Let the customer choose their username and subdomain after payment', 'iwp-wp-integration')),
            array('key' => 'manage_subscription', 'component' => 'yes-no-checkbox', 'checkbox_label' => __('Keep subscription sites permanent while active; make them temporary on failed payment, pause, cancellation or expiry', 'iwp-wp-integration')),
        );
        return array('fields' => $fields, 'button_require_list' => false, 'integration_title' => 'InstaWP');
    }

    public function validateFeedData($data, $args) {
        // Provision only after payment, never on order creation or a renewal.
        $data['event_trigger'] = array('order_paid_done');
        $data['snapshot_slug'] = sanitize_text_field($data['snapshot_slug'] ?? '');
        $data['plan_id'] = sanitize_text_field($data['plan_id'] ?? '');
        $data['expiry_type'] = ($data['expiry_type'] ?? '') === 'temporary' ? 'temporary' : 'permanent';
        $data['expiry_hours'] = max(1, absint($data['expiry_hours'] ?? 24));
        foreach (array('defer_creation', 'manage_subscription') as $key) {
            $data[$key] = ($data[$key] ?? '') === 'yes' ? 'yes' : 'no';
        }
        if (!$data['snapshot_slug']) {
            return new WP_Error('snapshot_required', __('Select an InstaWP snapshot.', 'iwp-wp-integration'));
        }
        return $data;
    }

    public function processAction($order, $eventData) {
        if (($eventData['trigger'] ?? '') !== 'order_paid_done' || ($eventData['scope'] ?? '') !== 'product') {
            return;
        }
        $meta = \FluentCart\App\Models\ProductMeta::find(absint($eventData['integration_id'] ?? 0));
        if (!$meta || $meta->object_type !== 'product_integration' || $meta->meta_key !== 'instawp') {
            return;
        }
        $this->processor->process_feed($order, $meta->object_id, $eventData['feed'] ?? array(), $meta->id);
    }
}
