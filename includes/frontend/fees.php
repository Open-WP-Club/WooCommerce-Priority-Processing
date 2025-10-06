<?php

/**
 * Frontend Fees Handler
 * Manages fee calculation and application during checkout
 * 
 * FIXED: Consistent session value checking with 'yes'/'no' format
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
   * FIXED: Consistent session value checking
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
      error_log('WPP: Session not available in add_priority_fee');
      return;
    }

    // Get priority state from session - check for 'yes' string
    $priority = WC()->session->get('priority_processing', 'no');
    $should_add_fee = ($priority === 'yes' || $priority === true || $priority === '1' || $priority === 1);

    error_log("WPP: add_priority_fee - Session value: {$priority}, Should add fee: " . ($should_add_fee ? 'YES' : 'NO'));

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
      error_log('WPP: Fee amount is zero or negative, not adding fee');
      return;
    }

    // Check if fee already exists to avoid duplicates
    if ($this->fee_already_exists($fee_label)) {
      error_log('WPP: Fee already exists, skipping');
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
    if (!WC()->cart) {
      return false;
    }

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
   * FIXED: Consistent session value checking
   */
  public function save_priority_to_order($order, $data)
  {
    if (!WC()->session) {
      return;
    }

    $priority = WC()->session->get('priority_processing', 'no');
    $should_save = ($priority === 'yes' || $priority === true || $priority === '1' || $priority === 1);

    error_log("WPP: save_priority_to_order - Session value: {$priority}, Should save: " . ($should_save ? 'YES' : 'NO'));

    if ($should_save) {
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
   * Check if priority processing is active in current session
   */
  private function is_priority_active()
  {
    if (!WC()->session) {
      return false;
    }

    $priority = WC()->session->get('priority_processing', 'no');
    return ($priority === 'yes' || $priority === true || $priority === '1' || $priority === 1);
  }
}
