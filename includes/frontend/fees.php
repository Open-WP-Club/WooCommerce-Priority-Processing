<?php

/**
 * Frontend Fees Handler
 * Manages fee calculation and application during checkout
 */
class Frontend_Fees
{
  public function __construct()
  {
    // Fee calculation and application
    add_action('woocommerce_cart_calculate_fees', [$this, 'add_priority_fee']);
    add_action('woocommerce_checkout_create_order', [$this, 'save_priority_to_order'], 10, 2);
  }

  /**
   * Add priority processing fee to cart
   */
  public function add_priority_fee()
  {
    // Only process on checkout pages
    if (!is_checkout()) {
      return;
    }

    // Check if feature is enabled
    if (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1') {
      return;
    }

    // Check user permissions
    if (!Core_Permissions::can_access_priority_processing()) {
      Core_Permissions::log_permission_check('add_priority_fee');
      return;
    }

    // Check session availability
    if (!WC()->session) {
      return;
    }

    // Get priority state from session
    $priority = WC()->session->get('priority_processing', false);
    $should_add_fee = ($priority === true || $priority === 1 || $priority === '1');

    if ($should_add_fee) {
      $this->apply_priority_fee();
    }
  }

  /**
   * Apply the priority processing fee
   */
  private function apply_priority_fee()
  {
    $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
    $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

    // Don't add fee if amount is zero or negative
    if ($fee_amount <= 0) {
      return;
    }

    // Check if fee already exists to avoid duplicates
    if ($this->fee_already_exists($fee_label)) {
      return;
    }

    // Add the fee to cart
    WC()->cart->add_fee($fee_label, $fee_amount);
    error_log("WPP: Priority fee added to cart: {$fee_amount}");
  }

  /**
   * Check if priority fee already exists in cart
   */
  private function fee_already_exists($fee_label)
  {
    $existing_fees = WC()->cart->get_fees();

    foreach ($existing_fees as $fee) {
      if ($fee->name === $fee_label) {
        return true;
      }
    }

    return false;
  }

  /**
   * Save priority processing data to order
   */
  public function save_priority_to_order($order, $data)
  {
    if (!WC()->session) {
      return;
    }

    $priority = WC()->session->get('priority_processing', false);
    if ($priority === true || $priority === 1 || $priority === '1') {
      $this->apply_priority_to_order($order);
    }
  }

  /**
   * Apply priority processing to the order
   */
  private function apply_priority_to_order($order)
  {
    $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
    $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

    // Save priority meta data - WooCommerce handles fee transfer automatically
    $order->update_meta_data('_priority_processing', 'yes');

    // Add shipping-specific meta data for shipping plugin integration
    $order->update_meta_data('_requires_express_shipping', 'yes');
    $order->update_meta_data('_priority_fee_amount', $fee_amount);
    $order->update_meta_data('_priority_service_level', 'express');

    // Check if order already has the priority processing fee
    $this->validate_order_fee($order, $fee_label);

    // Fire action hook for shipping plugins that might want to integrate
    do_action('wpp_priority_order_created', $order, $fee_amount);

    $order->save();

    error_log("WPP: Priority processing saved to order #{$order->get_id()} with fee amount: {$fee_amount}");
  }

  /**
   * Validate that the order has the correct priority fee
   */
  private function validate_order_fee($order, $fee_label)
  {
    $order_fees = $order->get_fees();
    $priority_fee_exists = false;

    foreach ($order_fees as $fee) {
      if ($fee->get_name() === $fee_label) {
        $priority_fee_exists = true;
        error_log('WPP: Priority fee confirmed in order: ' . $fee->get_name());
        break;
      }
    }

    if (!$priority_fee_exists) {
      error_log('WPP: WARNING - Priority fee not found in order #' . $order->get_id());
    }
  }

  /**
   * Calculate fee amount based on cart contents (if needed for complex logic)
   */
  public function calculate_dynamic_fee()
  {
    $base_fee = floatval(get_option('wpp_fee_amount', '5.00'));
    $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;

    // Example: could implement percentage-based fees or tiered pricing
    // For now, just return the base fee
    return $base_fee;
  }

  /**
   * Get fee display information
   */
  public function get_fee_info()
  {
    return [
      'amount' => floatval(get_option('wpp_fee_amount', '5.00')),
      'label' => get_option('wpp_fee_label', 'Priority Processing & Express Shipping'),
      'formatted_amount' => wc_price(get_option('wpp_fee_amount', '5.00')),
      'is_enabled' => (get_option('wpp_enabled') === '1' || get_option('wpp_enabled') === 'yes')
    ];
  }

  /**
   * Remove priority processing fee (if needed)
   */
  public function remove_priority_fee()
  {
    if (!WC()->cart) {
      return false;
    }

    $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');
    $fees = WC()->cart->get_fees();

    foreach ($fees as $fee_key => $fee) {
      if ($fee->name === $fee_label) {
        unset($fees[$fee_key]);
        error_log("WPP: Priority fee removed from cart");
        return true;
      }
    }

    return false;
  }

  /**
   * Check if priority processing is active in current session
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
   * Get current cart total including priority fee
   */
  public function get_total_with_priority()
  {
    if (!WC()->cart) {
      return 0;
    }

    $cart_total = WC()->cart->get_total('');
    $priority_fee = $this->is_priority_active() ? floatval(get_option('wpp_fee_amount', '5.00')) : 0;

    return $cart_total + $priority_fee;
  }

  /**
   * Format fee for display
   */
  public function format_fee_display($amount, $include_symbol = true)
  {
    if ($include_symbol) {
      return wc_price($amount);
    }

    return number_format($amount, 2);
  }
}
