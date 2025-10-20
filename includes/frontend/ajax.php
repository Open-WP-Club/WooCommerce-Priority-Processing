<?php

/**
 * Frontend AJAX Handler
 * Handles AJAX requests for priority processing updates
 */
class Frontend_AJAX
{
  public function __construct()
  {
    // AJAX handler for both logged-in and guest users
    add_action('wp_ajax_wpp_update_priority', [$this, 'update_priority_status']);
    add_action('wp_ajax_nopriv_wpp_update_priority', [$this, 'update_priority_status']);
  }

  /**
   * Handle AJAX request to update priority processing status
   */
  public function update_priority_status()
  {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpp_nonce')) {
      wp_send_json_error(['message' => __('Security check failed', 'woo-priority')]);
      return;
    }

    // Check if WooCommerce session is available
    if (!WC()->session) {
      wp_send_json_error(['message' => __('Session not available', 'woo-priority')]);
      return;
    }

    // Get priority status from request
    $priority_enabled = isset($_POST['priority_enabled']) && $_POST['priority_enabled'] === 'true';

    // Update session
    WC()->session->set('priority_processing', $priority_enabled);

    // Recalculate cart totals
    WC()->cart->calculate_totals();

    // Return success response
    wp_send_json_success([
      'message'  => __('Priority status updated', 'woo-priority'),
      'priority' => $priority_enabled,
      'cart'     => [
        'subtotal' => WC()->cart->get_cart_subtotal(),
        'total'    => WC()->cart->get_total()
      ]
    ]);
  }
}
