<?php

class WPP_Frontend
{
  public function __construct()
  {
    add_action('woocommerce_review_order_after_cart_contents', [$this, 'add_priority_checkbox']);
    add_action('woocommerce_checkout_before_order_review', [$this, 'add_priority_checkbox_fallback']);
    add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
    add_action('wp_ajax_wpp_update_priority', [$this, 'ajax_update_priority']);
    add_action('wp_ajax_nopriv_wpp_update_priority', [$this, 'ajax_update_priority']);
    add_action('woocommerce_cart_calculate_fees', [$this, 'add_priority_fee']);
    add_action('woocommerce_checkout_create_order', [$this, 'save_priority_to_order'], 10, 2);
    
    // Clear priority session when order is completed
    add_action('woocommerce_thankyou', [$this, 'clear_priority_session']);
    add_action('woocommerce_order_status_completed', [$this, 'clear_priority_session']);
    add_action('woocommerce_order_status_processing', [$this, 'clear_priority_session']);
    
    // Reset priority on cart emptied
    add_action('woocommerce_cart_emptied', [$this, 'clear_priority_session']);
    
    // Initialize session properly
    add_action('init', [$this, 'init_session']);
    
    // Reset priority when accessing checkout page without explicit session
    add_action('template_redirect', [$this, 'maybe_reset_priority_on_checkout']);
    
    // Clear priority when starting fresh checkout
    add_action('woocommerce_before_checkout_form', [$this, 'check_and_reset_priority'], 5);
    
    // Force clear stale fees
    add_action('woocommerce_before_calculate_totals', [$this, 'remove_stale_fees'], 5);
  }

  public function init_session()
  {
    if (!is_admin() && !defined('DOING_AJAX')) {
      if (WC()->session && !WC()->session->has_session()) {
        WC()->session->set_customer_session_cookie(true);
      }
    }
  }

  public function clear_priority_session($order_id = null)
  {
    if (WC()->session) {
      WC()->session->set('priority_processing', false);
      error_log('WPP: Priority session cleared' . ($order_id ? " for order {$order_id}" : ''));
    }
  }

  public function maybe_reset_priority_on_checkout()
  {
    if (is_checkout() && !is_wc_endpoint_url('order-received')) {
      // Check if this is a fresh checkout access (not an AJAX request)
      if (!defined('DOING_AJAX') && !isset($_POST['wpp_priority_set'])) {
        $this->check_and_reset_priority();
      }
    }
  }

  public function check_and_reset_priority()
  {
    if (!WC()->session) {
      return;
    }

    // Get current cart hash to detect if it's a new session
    $current_cart_hash = WC()->cart ? WC()->cart->get_cart_hash() : '';
    $stored_cart_hash = WC()->session->get('wpp_cart_hash', '');

    // If cart hash has changed or is empty, reset priority
    if ($current_cart_hash !== $stored_cart_hash || empty($stored_cart_hash)) {
      error_log('WPP: Cart hash changed or empty, resetting priority. Current: ' . $current_cart_hash . ', Stored: ' . $stored_cart_hash);
      WC()->session->set('priority_processing', false);
      WC()->session->set('wpp_cart_hash', $current_cart_hash);
      
      // Force remove any existing priority fees
      if (WC()->cart) {
        $fee_label = get_option('wpp_fee_label');
        $fees = WC()->cart->get_fees();
        
        foreach ($fees as $fee_key => $fee) {
          if ($fee->name === $fee_label) {
            unset(WC()->cart->fees[$fee_key]);
            error_log('WPP: Manually removed priority fee during reset');
          }
        }
        
        // Force cart recalculation
        WC()->cart->calculate_fees();
        WC()->cart->calculate_totals();
      }
    }
  }

  public function add_priority_checkbox()
  {
    error_log('WPP: add_priority_checkbox() called');
    
    if (get_option('wpp_enabled') !== '1') {
      error_log('WPP: Plugin disabled, not showing checkbox. wpp_enabled = ' . get_option('wpp_enabled'));
      return;
    }

    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $checkbox_label = get_option('wpp_checkbox_label');
    $description = get_option('wpp_description');
    
    error_log('WPP: Settings - Fee: ' . $fee_amount . ', Label: ' . $checkbox_label);

    // Ensure session is initialized
    if (WC()->session && !WC()->session->has_session()) {
      WC()->session->set_customer_session_cookie(true);
    }

    // FORCE RESET - if we're on a fresh checkout page without any cart actions, reset priority
    if (!did_action('woocommerce_cart_calculate_fees') && !isset($_POST['wpp_priority_set'])) {
      if (WC()->session) {
        WC()->session->set('priority_processing', false);
        error_log('WPP: Force reset priority session for fresh checkout');
      }
    }

    // Get current state, but default to false for new checkouts
    $is_checked = false;
    if (WC()->session) {
      $session_priority = WC()->session->get('priority_processing', null);
      $is_checked = $session_priority === true || $session_priority === '1';
    }

    // Add debug logging
    error_log('WPP: Rendering checkbox, checked state: ' . ($is_checked ? 'true' : 'false'));
    error_log('WPP: About to output HTML for checkbox');

?>
    <tr class="wpp-priority-row">
      <td colspan="2" style="border-top: 2px solid #e0e0e0; padding: 15px 0 10px 0;">
        <div id="wpp-priority-section" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6;">
          <h4 style="margin: 0 0 10px 0; color: #495057; font-size: 16px;">
            ⚡ <?php _e('Express Options', 'woo-priority'); ?>
          </h4>
          <label style="display: flex; align-items: flex-start; cursor: pointer; font-size: 14px;">
            <input type="checkbox" id="wpp_priority_checkbox" class="wpp-priority-checkbox"
              name="priority_processing" value="1" <?php checked($is_checked, true); ?>
              style="margin-right: 10px; margin-top: 2px; transform: scale(1.1);" />
            <span>
              <strong style="color: #28a745;">
                <?php echo esc_html($checkbox_label); ?>
                <span style="color: #dc3545;">(+<?php echo wc_price($fee_amount); ?>)</span>
              </strong>
              <?php if ($description): ?>
                <br><small style="color: #6c757d; display: block; margin-top: 4px; line-height: 1.4;">
                  <?php echo esc_html($description); ?>
                </small>
              <?php endif; ?>
            </span>
          </label>
        </div>
      </td>
    </tr>
    <?php
    error_log('WPP: Checkbox HTML output completed');
  }
  }

  public function add_priority_checkbox_fallback()
  {
    error_log('WPP: add_priority_checkbox_fallback() called');
    
    if (get_option('wpp_enabled') !== '1') {
      error_log('WPP: Plugin disabled, not showing fallback checkbox');
      return;
    }

    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $checkbox_label = get_option('wpp_checkbox_label');
    $description = get_option('wpp_description');

    // Ensure session is initialized
    if (WC()->session && !WC()->session->has_session()) {
      WC()->session->set_customer_session_cookie(true);
    }

    // Get current state, but default to false for new checkouts
    $is_checked = false;
    if (WC()->session) {
      $session_priority = WC()->session->get('priority_processing', null);
      $is_checked = $session_priority === true || $session_priority === '1';
    }

    error_log('WPP: Rendering fallback checkbox, checked state: ' . ($is_checked ? 'true' : 'false'));

  ?>
    <div id="wpp-priority-option-fallback" style="margin: 20px 0; padding: 15px; background: #f7f7f7; border: 1px solid #e0e0e0; border-radius: 4px;">
      <h4 style="margin: 0 0 10px 0; color: #495057; font-size: 16px;">
        ⚡ <?php _e('Express Options (Fallback)', 'woo-priority'); ?>
      </h4>
      <label style="display: flex; align-items: flex-start; cursor: pointer;">
        <input type="checkbox" id="wpp_priority_checkbox_fallback" class="wpp-priority-checkbox" name="priority_processing" value="1"
          <?php checked($is_checked, true); ?> style="margin-right: 8px; margin-top: 2px;" />
        <span>
          <strong><?php echo esc_html($checkbox_label); ?>:
            <?php echo wc_price($fee_amount); ?></strong>
          <?php if ($description): ?>
            <br><small style="color: #666; display: block; margin-top: 4px;">
              <?php echo esc_html($description); ?>
            </small>
          <?php endif; ?>
        </span>
      </label>
    </div>
    <script>
      jQuery(function($) {
        if ($('#wpp-priority-section').length > 0) {
          $('#wpp-priority-option-fallback').remove();
          console.log('WPP: Removed fallback because main section exists');
        } else {
          console.log('WPP: Fallback checkbox is being used');
        }
      });
    </script>
<?php
    error_log('WPP: Fallback checkbox HTML output completed');
  }

  public function frontend_scripts()
  {
    if (is_checkout()) {
      wp_enqueue_script('wpp-frontend', WPP_PLUGIN_URL . 'assets/frontend.js', ['jquery'], WPP_VERSION, true);
      wp_localize_script('wpp-frontend', 'wpp_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpp_nonce')
      ]);
    }
  }

  public function ajax_update_priority()
  {
    error_log('WPP: AJAX request received');

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpp_nonce')) {
      error_log('WPP: Nonce verification failed');
      wp_send_json_error('Invalid nonce');
      return;
    }

    if (!WC()->session) {
      error_log('WPP: WC session not available');
      wp_send_json_error('Session not available');
      return;
    }

    $priority = isset($_POST['priority']) && $_POST['priority'] === '1';
    error_log('WPP: Setting priority to: ' . ($priority ? 'true' : 'false'));

    // Store as boolean for consistency
    WC()->session->set('priority_processing', $priority);
    
    // Store cart hash to track session state
    if (WC()->cart) {
      WC()->session->set('wpp_cart_hash', WC()->cart->get_cart_hash());
    }
    
    // Mark that priority has been explicitly set
    $_POST['wpp_priority_set'] = true;

    if (WC()->cart) {
      WC()->cart->calculate_fees();
      WC()->cart->calculate_totals();
      error_log('WPP: Cart totals recalculated');
    }

    // Force fragments generation
    ob_start();
    woocommerce_order_review();
    $order_review = ob_get_clean();

    ob_start();
    woocommerce_checkout_payment();
    $payment_methods = ob_get_clean();

    $fragments = [
      '.woocommerce-checkout-review-order-table' => $order_review,
      '.woocommerce-checkout-payment' => $payment_methods,
      'div.woocommerce-checkout-review-order' => '<div class="woocommerce-checkout-review-order">' . $order_review . '</div>',
    ];

    $fragments = apply_filters('woocommerce_update_order_review_fragments', $fragments);

    error_log('WPP: Generated ' . count($fragments) . ' fragments');

    wp_send_json_success([
      'fragments' => $fragments,
      'priority' => $priority,
      'cart_hash' => WC()->cart->get_cart_hash()
    ]);
  }

  public function remove_stale_fees($cart)
  {
    if (!is_checkout() || get_option('wpp_enabled') !== '1') {
      error_log('WPP: remove_stale_fees - Not checkout or plugin disabled');
      return;
    }

    if (!WC()->session) {
      error_log('WPP: remove_stale_fees - No session');
      return;
    }

    $priority = WC()->session->get('priority_processing', false);
    $fee_label = get_option('wpp_fee_label');
    
    error_log('WPP: remove_stale_fees - Priority state: ' . ($priority ? 'true' : 'false'));
    
    // If priority is disabled, remove any existing priority fees
    if ($priority !== true && $priority !== '1') {
      $fees = $cart->get_fees();
      error_log('WPP: remove_stale_fees - Found ' . count($fees) . ' existing fees');
      
      $removed_count = 0;
      foreach ($fees as $fee_key => $fee) {
        error_log('WPP: remove_stale_fees - Checking fee: ' . $fee->name . ' vs ' . $fee_label);
        if ($fee->name === $fee_label) {
          unset($cart->fees[$fee_key]);
          $removed_count++;
          error_log('WPP: Removed stale priority fee: ' . $fee->name);
        }
      }
      
      if ($removed_count === 0) {
        error_log('WPP: No priority fees found to remove');
      }
    } else {
      error_log('WPP: Priority is enabled, not removing fees');
    }
  }

  public function add_priority_fee()
  {
    if (!is_checkout() || get_option('wpp_enabled') !== '1') {
      return;
    }

    if (!WC()->session) {
      error_log('WPP: No WC session available for fee calculation');
      return;
    }

    $priority = WC()->session->get('priority_processing', false);
    $fee_label = get_option('wpp_fee_label');
    
    error_log('WPP: Fee calculation - Priority state: ' . ($priority ? 'true' : 'false'));
    
    // Only add fee if priority is explicitly enabled
    if ($priority === true || $priority === '1') {
      $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
      
      // Check if fee already exists
      $existing_fees = WC()->cart->get_fees();
      $fee_exists = false;
      
      foreach ($existing_fees as $fee) {
        if ($fee->name === $fee_label) {
          $fee_exists = true;
          break;
        }
      }
      
      if (!$fee_exists && $fee_amount > 0) {
        WC()->cart->add_fee($fee_label, $fee_amount, true);
        error_log('WPP: Priority fee added: ' . $fee_amount);
      }
    }
  }

  public function save_priority_to_order($order, $data)
  {
    if (!WC()->session) {
      return;
    }

    $priority = WC()->session->get('priority_processing', false);
    if ($priority === true || $priority === '1') {
      $order->update_meta_data('_priority_processing', 'yes');
      $order->save_meta_data();
      error_log('WPP: Priority processing saved to order: ' . $order->get_id());
    }
  }
}