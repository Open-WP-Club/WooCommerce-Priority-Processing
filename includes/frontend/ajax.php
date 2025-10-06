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

    // Store in session - this is all we need to do
    WC()->session->set('priority_processing', $priority);

    error_log("WPP: AJAX update - Priority set to: " . ($priority ? 'YES' : 'NO'));

    // Return simple success response
    // WooCommerce will handle fee calculation and DOM updates via its native refresh
    wp_send_json_success([
      'priority' => $priority,
      'message' => 'Session updated successfully'
    ]);
  }
}
