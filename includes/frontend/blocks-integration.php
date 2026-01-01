<?php

use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;

/**
 * WooCommerce Blocks Integration
 * Handles integration with WooCommerce Cart and Checkout Blocks
 */
class Frontend_Blocks_Integration
{
  /**
   * Constructor
   */
  public function __construct()
  {
    // Register the block integration
    add_action('woocommerce_blocks_loaded', [$this, 'register_blocks_integration']);

    // Enqueue block scripts
    add_action('wp_enqueue_scripts', [$this, 'enqueue_block_scripts']);
  }

  /**
   * Register integration with WooCommerce Blocks
   */
  public function register_blocks_integration()
  {
    if (!class_exists('\Automattic\WooCommerce\Blocks\Package')) {
      return;
    }

    // Extend the Store API with our custom data
    $this->extend_store_api();
  }

  /**
   * Extend WooCommerce Store API
   */
  private function extend_store_api()
  {
    if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
      return;
    }

    woocommerce_store_api_register_endpoint_data([
      'endpoint'        => CheckoutSchema::IDENTIFIER,
      'namespace'       => 'wpp-priority',
      'data_callback'   => [$this, 'extend_checkout_data'],
      'schema_callback' => [$this, 'extend_checkout_schema'],
      'schema_type'     => ARRAY_A,
    ]);

    woocommerce_store_api_register_endpoint_data([
      'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
      'namespace'       => 'wpp-priority',
      'data_callback'   => [$this, 'extend_cart_data'],
      'schema_callback' => [$this, 'extend_cart_schema'],
      'schema_type'     => ARRAY_A,
    ]);

    // Register update callback for priority processing checkbox
    woocommerce_store_api_register_update_callback([
      'namespace' => 'wpp-priority',
      'callback'  => [$this, 'update_priority_from_blocks'],
    ]);
  }

  /**
   * Extend checkout data for blocks
   */
  public function extend_checkout_data()
  {
    return $this->get_priority_data();
  }

  /**
   * Extend cart data for blocks
   */
  public function extend_cart_data()
  {
    return $this->get_priority_data();
  }

  /**
   * Get priority processing data for blocks
   */
  private function get_priority_data()
  {
    $is_enabled = get_option('wpp_enabled') === 'yes' || get_option('wpp_enabled') === '1';
    $can_access = Core_Permissions::can_access_priority_processing();
    $is_active = $this->is_priority_active();

    return [
      'enabled'       => $is_enabled && $can_access,
      'is_active'     => $is_active,
      'fee_amount'    => floatval(get_option('wpp_fee_amount', '5.00')),
      'fee_label'     => get_option('wpp_fee_label', __('Priority Processing & Express Shipping', 'woo-priority')),
      'section_title' => get_option('wpp_section_title', __('Express Options', 'woo-priority')),
      'checkbox_label' => get_option('wpp_checkbox_label', __('Priority processing + Express shipping', 'woo-priority')),
      'description'   => get_option('wpp_description', __('Your order will be processed with priority and shipped via express delivery', 'woo-priority')),
    ];
  }

  /**
   * Define checkout schema extension
   */
  public function extend_checkout_schema()
  {
    return [
      'enabled'        => [
        'description' => __('Whether priority processing is available', 'woo-priority'),
        'type'        => 'boolean',
        'readonly'    => true,
      ],
      'is_active'      => [
        'description' => __('Whether priority processing is currently active', 'woo-priority'),
        'type'        => 'boolean',
        'readonly'    => true,
      ],
      'fee_amount'     => [
        'description' => __('Priority processing fee amount', 'woo-priority'),
        'type'        => 'number',
        'readonly'    => true,
      ],
      'fee_label'      => [
        'description' => __('Fee label text', 'woo-priority'),
        'type'        => 'string',
        'readonly'    => true,
      ],
      'section_title'  => [
        'description' => __('Section title', 'woo-priority'),
        'type'        => 'string',
        'readonly'    => true,
      ],
      'checkbox_label' => [
        'description' => __('Checkbox label text', 'woo-priority'),
        'type'        => 'string',
        'readonly'    => true,
      ],
      'description'    => [
        'description' => __('Description text', 'woo-priority'),
        'type'        => 'string',
        'readonly'    => true,
      ],
    ];
  }

  /**
   * Define cart schema extension
   */
  public function extend_cart_schema()
  {
    return $this->extend_checkout_schema();
  }

  /**
   * Update priority processing from blocks checkout
   */
  public function update_priority_from_blocks($data)
  {
    if (!isset($data['priority_enabled'])) {
      return;
    }

    $priority_enabled = filter_var($data['priority_enabled'], FILTER_VALIDATE_BOOLEAN);

    if (WC()->session) {
      WC()->session->set('priority_processing', $priority_enabled);

      // Recalculate cart totals
      if (WC()->cart) {
        WC()->cart->calculate_totals();
      }

      error_log("WPP Blocks: Priority processing " . ($priority_enabled ? 'enabled' : 'disabled'));
    }
  }

  /**
   * Check if priority processing is active
   */
  private function is_priority_active()
  {
    if (!WC()->session) {
      return false;
    }

    $priority = WC()->session->get('priority_processing', false);
    return ($priority === true || $priority === '1' || $priority === 1);
  }

  /**
   * Enqueue scripts for blocks checkout
   */
  public function enqueue_block_scripts()
  {
    // Only load on checkout page
    if (!is_checkout() && !has_block('woocommerce/checkout')) {
      return;
    }

    // Check if feature is enabled
    if (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1') {
      return;
    }

    // Check permissions
    if (!Core_Permissions::can_access_priority_processing()) {
      return;
    }

    // Register and enqueue the block script
    $script_path = WPP_PLUGIN_DIR . 'assets/js/blocks-checkout.js';
    $script_url = WPP_PLUGIN_URL . 'assets/js/blocks-checkout.js';

    if (file_exists($script_path)) {
      wp_enqueue_script(
        'wpp-blocks-checkout',
        $script_url,
        ['wp-element', 'wp-i18n', 'wp-components', 'wc-blocks-checkout'],
        WPP_VERSION,
        true
      );

      // Pass data to the script
      wp_localize_script('wpp-blocks-checkout', 'wppBlocksData', [
        'ajax_url'       => admin_url('admin-ajax.php'),
        'nonce'          => wp_create_nonce('wpp_nonce'),
        'fee_amount'     => get_option('wpp_fee_amount', '5.00'),
        'fee_label'      => get_option('wpp_fee_label', __('Priority Processing & Express Shipping', 'woo-priority')),
        'section_title'  => get_option('wpp_section_title', __('Express Options', 'woo-priority')),
        'checkbox_label' => get_option('wpp_checkbox_label', __('Priority processing + Express shipping', 'woo-priority')),
        'description'    => get_option('wpp_description', __('Your order will be processed with priority and shipped via express delivery', 'woo-priority')),
      ]);
    }
  }
}
