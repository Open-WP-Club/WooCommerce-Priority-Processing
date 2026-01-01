<?php

/**
 * Frontend Fees Handler
 * Manages fee calculation and application during checkout
 */
class Frontend_Fees
{
  public function __construct()
  {
    // NOTE: We NO LONGER add a separate cart fee
    // The priority fee is added directly to shipping rates in Frontend_Shipping class
    // This class now only handles saving priority status to orders

    add_action('woocommerce_checkout_create_order', [$this, 'save_priority_to_order'], 10, 2);
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

    // Fire action hook for shipping plugins that might want to integrate
    do_action('wpp_priority_order_created', $order, $fee_amount);

    $order->save();
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
