<?php

/**
 * Frontend AJAX Handler
 * Manages AJAX requests from the checkout page
 */
class Frontend_Ajax
{
  public function __construct()
  {
    // AJAX handlers for both logged-in and guest users
    add_action('wp_ajax_wpp_update_priority', [$this, 'ajax_update_priority']);
    add_action('wp_ajax_nopriv_wpp_update_priority', [$this, 'ajax_update_priority']);
  }

  /**
   * Handle AJAX request to update priority processing
   */
  public function ajax_update_priority()
  {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpp_nonce')) {
      wp_send_json_error('Invalid nonce');
      return;
    }

    // Check user permissions
    if (!Core_Permissions::can_access_priority_processing()) {
      Core_Permissions::log_permission_check('ajax_update_priority');
      wp_send_json_error('Permission denied');
      return;
    }

    // Check WooCommerce session availability
    if (!WC()->session) {
      wp_send_json_error('WooCommerce session not available');
      return;
    }

    // Get and validate priority setting
    $priority = isset($_POST['priority']) && $_POST['priority'] === '1';
    $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
    $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

    // Store in session for final order processing
    WC()->session->set('priority_processing', $priority);

    error_log("WPP: AJAX update - Priority set to: " . ($priority ? 'YES' : 'NO'));

    // Generate updated checkout HTML
    $checkout_handler = new Frontend_Checkout();
    $checkout_html = $checkout_handler->generate_checkout_html($priority);

    // Prepare response data
    $response_data = [
      'fragments' => ['.woocommerce-checkout-review-order-table' => $checkout_html],
      'debug' => [
        'priority' => $priority,
        'fee_amount' => $priority ? $fee_amount : 0,
        'new_total' => $this->calculate_new_total($priority, $fee_amount),
        'permission_check' => 'passed',
        'shipping_integration' => 'safe_mode_active'
      ]
    ];

    wp_send_json_success($response_data);
  }

  /**
   * Calculate new total for debugging/display purposes
   */
  private function calculate_new_total($priority_enabled, $fee_amount)
  {
    if (!WC()->cart) {
      return 'N/A';
    }

    $cart_subtotal = WC()->cart->get_subtotal();
    $cart_tax = WC()->cart->get_total_tax();
    $shipping_total = WC()->cart->get_shipping_total();
    $priority_fee_amount = $priority_enabled ? $fee_amount : 0;
    $new_total = $cart_subtotal + $cart_tax + $shipping_total + $priority_fee_amount;

    return wc_price($new_total);
  }

  /**
   * Validate AJAX request security
   */
  private function validate_ajax_request()
  {
    // Check nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpp_nonce')) {
      wp_send_json_error([
        'message' => __('Security check failed', 'woo-priority'),
        'code' => 'invalid_nonce'
      ]);
      return false;
    }

    // Check permissions
    if (!Core_Permissions::can_access_priority_processing()) {
      Core_Permissions::log_permission_check('ajax_validation');
      wp_send_json_error([
        'message' => __('Access denied', 'woo-priority'),
        'code' => 'access_denied'
      ]);
      return false;
    }

    // Check WooCommerce
    if (!WC()->session) {
      wp_send_json_error([
        'message' => __('WooCommerce session not available', 'woo-priority'),
        'code' => 'no_session'
      ]);
      return false;
    }

    return true;
  }

  /**
   * Get current session priority state
   */
  private function get_session_priority()
  {
    if (!WC()->session) {
      return false;
    }

    $priority = WC()->session->get('priority_processing', false);
    return ($priority === true || $priority === '1' || $priority === 1);
  }

  /**
   * Update session priority state
   */
  private function update_session_priority($priority_state)
  {
    if (!WC()->session) {
      return false;
    }

    WC()->session->set('priority_processing', $priority_state);
    error_log("WPP: Session priority updated to: " . ($priority_state ? 'YES' : 'NO'));
    return true;
  }

  /**
   * Generate fragments for checkout update
   */
  private function generate_checkout_fragments($priority_enabled)
  {
    $checkout_handler = new Frontend_Checkout();
    $checkout_html = $checkout_handler->generate_checkout_html($priority_enabled);

    return [
      '.woocommerce-checkout-review-order-table' => $checkout_html
    ];
  }

  /**
   * Prepare AJAX success response
   */
  private function prepare_success_response($priority_enabled)
  {
    $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));

    return [
      'fragments' => $this->generate_checkout_fragments($priority_enabled),
      'debug' => [
        'priority' => $priority_enabled,
        'fee_amount' => $priority_enabled ? $fee_amount : 0,
        'session_updated' => true,
        'timestamp' => current_time('mysql')
      ]
    ];
  }

  /**
   * Handle AJAX error with proper logging
   */
  private function handle_ajax_error($message, $code = 'unknown_error', $data = [])
  {
    error_log("WPP AJAX Error [{$code}]: {$message} - Data: " . json_encode($data));

    wp_send_json_error([
      'message' => $message,
      'code' => $code,
      'debug' => $data
    ]);
  }

  /**
   * Alternative AJAX handler with enhanced error handling
   */
  public function ajax_update_priority_enhanced()
  {
    try {
      // Validate request
      if (!$this->validate_ajax_request()) {
        return; // Error already sent in validation
      }

      // Get priority state from request
      $priority = isset($_POST['priority']) && $_POST['priority'] === '1';

      // Update session
      if (!$this->update_session_priority($priority)) {
        $this->handle_ajax_error('Failed to update session', 'session_error');
        return;
      }

      // Prepare and send success response
      $response = $this->prepare_success_response($priority);
      wp_send_json_success($response);
    } catch (Exception $e) {
      $this->handle_ajax_error(
        'Unexpected error occurred',
        'exception',
        ['message' => $e->getMessage(), 'line' => $e->getLine()]
      );
    }
  }
}
