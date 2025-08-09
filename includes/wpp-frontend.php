<?php

class WPP_Frontend
{
  private static $fee_added_this_request = false;
  private static $session_manager_initialized = false;

  public function __construct()
  {
    // Classic WooCommerce checkout hooks
    add_action('woocommerce_review_order_after_cart_contents', [$this, 'add_priority_checkbox']);
    add_action('woocommerce_checkout_before_order_review', [$this, 'add_priority_checkbox_fallback']);

    // Additional fallback hooks for different themes/plugins
    add_action('woocommerce_checkout_order_review', [$this, 'add_priority_checkbox_fallback']);
    add_action('woocommerce_checkout_after_customer_details', [$this, 'add_priority_checkbox_fallback']);
    add_action('woocommerce_checkout_billing', [$this, 'add_priority_checkbox_fallback']);

    // AJAX and fee handling
    add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
    add_action('wp_ajax_wpp_update_priority', [$this, 'ajax_update_priority']);
    add_action('wp_ajax_nopriv_wpp_update_priority', [$this, 'ajax_update_priority']);

    // Fee hook for final order processing only
    add_action('woocommerce_cart_calculate_fees', [$this, 'add_priority_fee']);
    add_action('woocommerce_checkout_create_order', [$this, 'save_priority_to_order'], 10, 2);

    // Enhanced session clearing hooks
    add_action('woocommerce_thankyou', [$this, 'clear_priority_session']);
    add_action('woocommerce_cart_emptied', [$this, 'clear_priority_session']);
    add_action('wp_logout', [$this, 'clear_priority_session']);
    add_action('woocommerce_checkout_order_processed', [$this, 'clear_priority_session']);

    // Clear on payment failure/cancellation
    add_action('woocommerce_checkout_order_review', [$this, 'validate_session_state']);

    // Reset fee tracking on new requests
    add_action('init', [$this, 'reset_request_tracking']);

    // Initialize session properly
    add_action('init', [$this, 'init_session']);
  }

  /**
   * Reset per-request tracking variables
   */
  public function reset_request_tracking()
  {
    self::$fee_added_this_request = false;
  }

  /**
   * Initialize session with better error handling
   */
  public function init_session()
  {
    if (!is_admin() && !defined('DOING_AJAX') && !self::$session_manager_initialized) {
      if (WC()->session && !WC()->session->has_session()) {
        try {
          WC()->session->set_customer_session_cookie(true);
          self::$session_manager_initialized = true;
        } catch (Exception $e) {
          error_log('WPP: Session initialization failed: ' . $e->getMessage());
        }
      }
    }
  }

  /**
   * Centralized session management for priority processing
   */
  private function get_priority_session_state()
  {
    if (!WC()->session) {
      return false;
    }

    try {
      $session_priority = WC()->session->get('priority_processing', false);
      return ($session_priority === true || $session_priority === '1' || $session_priority === 1);
    } catch (Exception $e) {
      error_log('WPP: Error reading session state: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Set priority session state with error handling
   */
  private function set_priority_session_state($state)
  {
    if (!WC()->session) {
      error_log('WPP: Cannot set session state - WC session not available');
      return false;
    }

    try {
      WC()->session->set('priority_processing', $state);
      return true;
    } catch (Exception $e) {
      error_log('WPP: Error setting session state: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Validate and repair session state if needed
   */
  public function validate_session_state()
  {
    if (!is_checkout()) {
      return;
    }

    // If we're on checkout but session is somehow lost, reset everything
    if (WC()->session && !WC()->session->has_session()) {
      $this->init_session();
    }
  }

  /**
   * Enhanced session clearing with multiple fallbacks
   */
  public function clear_priority_session($order_id = null)
  {
    // Clear WooCommerce session
    if (WC()->session) {
      try {
        WC()->session->set('priority_processing', false);
        WC()->session->set('wpp_last_state', null);
      } catch (Exception $e) {
        error_log('WPP: Error clearing WC session: ' . $e->getMessage());
      }
    }

    // Clear any persistent user meta if user is logged in
    if (is_user_logged_in()) {
      delete_user_meta(get_current_user_id(), '_wpp_priority_processing');
    }

    // Reset request-level tracking
    self::$fee_added_this_request = false;

    error_log('WPP: Priority session cleared' . ($order_id ? ' for order #' . $order_id : ''));
  }

  public function add_priority_checkbox()
  {
    if (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1') {
      return;
    }

    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $checkbox_label = get_option('wpp_checkbox_label', 'Priority processing + Express shipping');
    $description = get_option('wpp_description', '');
    $section_title = get_option('wpp_section_title', 'Express Options');

    // Use centralized session management
    $is_checked = $this->get_priority_session_state();

?>
    <tr class="wpp-priority-row">
      <td colspan="2" style="border-top: 2px solid #e0e0e0; padding: 15px 0 10px 0;">
        <div id="wpp-priority-section" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6;">
          <h4 style="margin: 0 0 10px 0; color: #495057; font-size: 16px;">
            ⚡ <?php echo esc_html($section_title); ?>
          </h4>
          <label style="display: flex; align-items: flex-start; cursor: pointer; font-size: 14px;">
            <input type="checkbox" id="wpp_priority_checkbox" class="wpp-priority-checkbox"
              name="priority_processing" value="1" <?php checked($is_checked, true); ?>
              style="margin-right: 10px; margin-top: 2px; transform: scale(1.1);" />
            <span>
              <strong style="color: #28a745;">
                <?php echo esc_html($checkbox_label); ?>
                <span style="color: #dc3545;">( + <?php echo wc_price($fee_amount); ?>)</span>
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
  }

  public function add_priority_checkbox_fallback()
  {
    if (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1') {
      return;
    }

    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $checkbox_label = get_option('wpp_checkbox_label', 'Priority processing + Express shipping');
    $description = get_option('wpp_description', '');
    $section_title = get_option('wpp_section_title', 'Express Options');

    // Use centralized session management
    $is_checked = $this->get_priority_session_state();

  ?>
    <div id="wpp-priority-option-fallback" style="margin: 20px 0; padding: 15px; background: #f7f7f7; border: 1px solid #e0e0e0; border-radius: 4px;">
      <h4 style="margin: 0 0 10px 0; color: #495057; font-size: 16px;">
        ⚡ <?php echo esc_html($section_title); ?>
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
        // Remove fallback if main section exists
        if ($('#wpp-priority-section').length > 0) {
          $('#wpp-priority-option-fallback').remove();
        }
      });
    </script>
  <?php
  }

  public function frontend_scripts()
  {
    if (is_checkout()) {
      // Check if using blocks
      $using_blocks = has_block('woocommerce/checkout');

      if ($using_blocks) {
        wp_enqueue_script('wpp-frontend-blocks', WPP_PLUGIN_URL . 'assets/frontend-blocks.js', ['jquery'], WPP_VERSION, true);
        wp_localize_script('wpp-frontend-blocks', 'wpp_ajax', [
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('wpp_nonce'),
          'fee_amount' => get_option('wpp_fee_amount', '5.00'),
          'checkbox_label' => get_option('wpp_checkbox_label', 'Priority processing + Express shipping'),
          'description' => get_option('wpp_description', ''),
          'section_title' => get_option('wpp_section_title', 'Express Options'),
          'using_blocks' => true
        ]);
      } else {
        wp_enqueue_script('wpp-frontend', WPP_PLUGIN_URL . 'assets/frontend.js', ['jquery'], WPP_VERSION, true);
        wp_localize_script('wpp-frontend', 'wpp_ajax', [
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('wpp_nonce'),
          'using_blocks' => false,
          'fee_amount' => get_option('wpp_fee_amount', '5.00'),
          'fee_label' => get_option('wpp_fee_label', 'Priority Processing & Express Shipping')
        ]);
      }
    }
  }

  /**
   * Enhanced AJAX handler with better error recovery
   */
  public function ajax_update_priority()
  {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpp_nonce')) {
      wp_send_json_error([
        'message' => 'Invalid nonce',
        'action' => 'reload_page'
      ]);
      return;
    }

    // Check WooCommerce session
    if (!WC()->session) {
      wp_send_json_error([
        'message' => 'WooCommerce session not available',
        'action' => 'reload_page'
      ]);
      return;
    }

    $priority = isset($_POST['priority']) && $_POST['priority'] === '1';
    $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
    $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

    // Store previous state for rollback
    $previous_state = $this->get_priority_session_state();

    // Attempt to set new session state
    if (!$this->set_priority_session_state($priority)) {
      wp_send_json_error([
        'message' => 'Failed to update session state',
        'action' => 'rollback',
        'previous_state' => $previous_state
      ]);
      return;
    }

    // Store state for validation
    if (WC()->session) {
      WC()->session->set('wpp_last_state', $priority);
    }

    // Generate updated checkout HTML
    try {
      $cart_subtotal = WC()->cart->get_subtotal();
      $cart_tax = WC()->cart->get_total_tax();
      $shipping_total = WC()->cart->get_shipping_total();

      // Calculate new total with or without priority fee
      $priority_fee_amount = $priority ? $fee_amount : 0;
      $new_total = $cart_subtotal + $cart_tax + $shipping_total + $priority_fee_amount;

      ob_start();
      $this->render_checkout_table($priority, $priority_fee_amount, $fee_label);
      $checkout_html = ob_get_clean();

      wp_send_json_success([
        'fragments' => ['.woocommerce-checkout-review-order-table' => $checkout_html],
        'debug' => [
          'priority' => $priority,
          'fee_amount' => $priority_fee_amount,
          'new_total' => wc_price($new_total),
          'session_set' => true
        ]
      ]);
    } catch (Exception $e) {
      // Rollback session state on any error
      $this->set_priority_session_state($previous_state);

      wp_send_json_error([
        'message' => 'Error generating checkout: ' . $e->getMessage(),
        'action' => 'rollback',
        'previous_state' => $previous_state
      ]);
    }
  }

  /**
   * Render checkout table - extracted for reusability
   */
  private function render_checkout_table($priority, $priority_fee_amount, $fee_label)
  {
    $cart_subtotal = WC()->cart->get_subtotal();
    $cart_tax = WC()->cart->get_total_tax();
    $shipping_total = WC()->cart->get_shipping_total();
    $new_total = $cart_subtotal + $cart_tax + $shipping_total + $priority_fee_amount;

  ?>
    <table class="shop_table woocommerce-checkout-review-order-table">
      <thead>
        <tr>
          <th class="product-name"><?php _e('Product', 'woocommerce'); ?></th>
          <th class="product-total"><?php _e('Subtotal', 'woocommerce'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
          $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
          if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
        ?>
            <tr class="<?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>">
              <td class="product-name">
                <?php echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key)); ?>
                <strong class="product-quantity"><?php echo sprintf('&times;&nbsp;%s', $cart_item['quantity']); ?></strong>
              </td>
              <td class="product-total">
                <?php echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); ?>
              </td>
            </tr>
        <?php
          }
        }

        // Add checkbox row to preserve it
        $this->render_checkbox_row($priority);
        ?>
      </tbody>
      <tfoot>
        <tr class="cart-subtotal">
          <th><?php _e('Subtotal', 'woocommerce'); ?></th>
          <td><?php echo wc_price($cart_subtotal); ?></td>
        </tr>

        <?php if ($priority && $priority_fee_amount > 0): ?>
          <tr class="priority-fee-row">
            <th>⚡ <?php echo esc_html($fee_label); ?></th>
            <td><?php echo wc_price($priority_fee_amount); ?></td>
          </tr>
        <?php endif; ?>

        <tr class="order-total">
          <th><?php _e('Total', 'woocommerce'); ?></th>
          <td><strong><?php echo wc_price($new_total); ?></strong></td>
        </tr>
      </tfoot>
    </table>
  <?php
  }

  /**
   * Render checkbox row - extracted for reusability
   */
  private function render_checkbox_row($priority)
  {
    $checkbox_label = get_option('wpp_checkbox_label', 'Priority processing + Express shipping');
    $section_title = get_option('wpp_section_title', 'Express Options');
    $description = get_option('wpp_description', '');
    $fee_amount = get_option('wpp_fee_amount', '5.00');
  ?>
    <tr class="wpp-priority-row">
      <td colspan="2" style="border-top: 2px solid #e0e0e0; padding: 15px 0 10px 0;">
        <div id="wpp-priority-section" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6;">
          <h4 style="margin: 0 0 10px 0; color: #495057; font-size: 16px;">
            ⚡ <?php echo esc_html($section_title); ?>
          </h4>
          <label style="display: flex; align-items: flex-start; cursor: pointer; font-size: 14px;">
            <input type="checkbox" id="wpp_priority_checkbox" class="wpp-priority-checkbox"
              name="priority_processing" value="1" <?php checked($priority, true); ?>
              style="margin-right: 10px; margin-top: 2px; transform: scale(1.1);" />
            <span>
              <strong style="color: #28a745;">
                <?php echo esc_html($checkbox_label); ?>
                <span style="color: #dc3545;">( + <?php echo wc_price($fee_amount); ?>)</span>
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
  }

  /**
   * Enhanced fee addition with duplicate prevention
   */
  public function add_priority_fee()
  {
    if (!is_checkout() || (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1')) {
      return;
    }

    if (!WC()->session) {
      return;
    }

    // Prevent multiple fee additions in the same request
    if (self::$fee_added_this_request) {
      return;
    }

    $priority = $this->get_priority_session_state();

    if ($priority) {
      $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
      $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

      if ($fee_amount > 0) {
        // Enhanced duplicate check
        $existing_fees = WC()->cart->get_fees();
        $fee_exists = false;

        foreach ($existing_fees as $fee) {
          if ($fee->name === $fee_label || strpos($fee->name, 'Priority') !== false) {
            $fee_exists = true;
            break;
          }
        }

        if (!$fee_exists) {
          WC()->cart->add_fee($fee_label, $fee_amount);
          self::$fee_added_this_request = true;
        }
      }
    }
  }

  /**
   * Enhanced order saving with validation
   */
  public function save_priority_to_order($order, $data)
  {
    if (!WC()->session) {
      return;
    }

    $priority = $this->get_priority_session_state();

    if ($priority) {
      // Validate that the session state matches what we expect
      $last_state = WC()->session->get('wpp_last_state', null);
      if ($last_state !== null && $last_state !== $priority) {
        error_log('WPP: Session state mismatch detected during order creation');
      }

      // Save priority meta data
      $order->update_meta_data('_priority_processing', 'yes');
      $order->update_meta_data('_wpp_session_state', $priority ? 'yes' : 'no');
      $order->update_meta_data('_wpp_fee_applied', self::$fee_added_this_request ? 'yes' : 'no');

      // Verify fee exists in order
      $order_fees = $order->get_fees();
      $priority_fee_exists = false;
      $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

      foreach ($order_fees as $fee) {
        if ($fee->get_name() === $fee_label || strpos($fee->get_name(), 'Priority') !== false) {
          $priority_fee_exists = true;
          break;
        }
      }

      if (!$priority_fee_exists) {
        error_log('WPP: WARNING - Priority order created but no fee found in order #' . $order->get_id());
      }

      $order->save();
    }
  }
}
