<?php

class WPP_Frontend
{
  public function __construct()
  {
    error_log('WPP: Frontend class constructor called');

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
    add_action('woocommerce_cart_calculate_fees', [$this, 'add_priority_fee']);
    add_action('woocommerce_checkout_create_order', [$this, 'save_priority_to_order'], 10, 2);

    // Clear priority session when order is completed
    add_action('woocommerce_thankyou', [$this, 'clear_priority_session']);
    add_action('woocommerce_cart_emptied', [$this, 'clear_priority_session']);

    // Initialize session properly
    add_action('init', [$this, 'init_session']);

    // Debug hooks to see which ones fire
    $debug_hooks = [
      'woocommerce_review_order_after_cart_contents',
      'woocommerce_checkout_before_order_review',
      'woocommerce_checkout_order_review',
      'woocommerce_checkout_after_customer_details',
      'woocommerce_checkout_billing'
    ];

    foreach ($debug_hooks as $hook) {
      add_action($hook, function () use ($hook) {
        error_log('WPP: Hook fired: ' . $hook);
      }, 1);
    }

    // Check if we're using blocks
    add_action('wp', function () {
      if (is_checkout()) {
        $using_blocks = has_block('woocommerce/checkout');
        error_log('WPP: Checkout page detected. Using blocks: ' . ($using_blocks ? 'YES' : 'NO'));

        if ($using_blocks) {
          error_log('WPP: Block-based checkout detected');
        } else {
          error_log('WPP: Classic checkout detected');
        }
      }
    });

    error_log('WPP: All hooks registered');
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

  public function add_priority_checkbox()
  {
    // Always show if enabled - no complex state checking
    if (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1') {
      error_log('WPP: Checkbox not shown - plugin disabled. Setting: ' . get_option('wpp_enabled'));
      return;
    }

    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $checkbox_label = get_option('wpp_checkbox_label', 'Priority processing + Express shipping');
    $description = get_option('wpp_description', '');
    $section_title = get_option('wpp_section_title', 'Express Options');

    // Simple session check - default to false (unchecked)
    // Handle WooCommerce session quirks where false is stored as empty string
    $is_checked = false;
    if (WC()->session) {
      $session_priority = WC()->session->get('priority_processing', false);
      $is_checked = ($session_priority === true || $session_priority === '1' || $session_priority === 1);

      // Enhanced debugging
      error_log('WPP: Checkbox render - Session priority: ' . var_export($session_priority, true));
      error_log('WPP: Checkbox render - Is checked: ' . ($is_checked ? 'true' : 'false'));
      error_log('WPP: Checkbox render - Session ID: ' . WC()->session->get_customer_id());
    } else {
      error_log('WPP: Checkbox render - No WC session available');
    }

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
  }

  public function add_priority_checkbox_fallback()
  {
    // Always show if enabled - fallback position
    if (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1') {
      return;
    }

    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $checkbox_label = get_option('wpp_checkbox_label', 'Priority processing + Express shipping');
    $description = get_option('wpp_description', '');
    $section_title = get_option('wpp_section_title', 'Express Options');

    // Simple session check
    $is_checked = false;
    if (WC()->session) {
      $session_priority = WC()->session->get('priority_processing', false);
      $is_checked = ($session_priority === true || $session_priority === '1' || $session_priority === 1);
    }

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
        error_log('WPP: Loading block-compatible scripts');
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
        error_log('WPP: Loading classic scripts');
        wp_enqueue_script('wpp-frontend', WPP_PLUGIN_URL . 'assets/frontend.js', ['jquery'], WPP_VERSION, true);
        wp_localize_script('wpp-frontend', 'wpp_ajax', [
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('wpp_nonce'),
          'using_blocks' => false
        ]);
      }
    }
  }

  public function ajax_update_priority()
  {
    error_log('WPP: AJAX request received - Raw POST data: ' . print_r($_POST, true));

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpp_nonce')) {
      error_log('WPP: Nonce verification failed');
      wp_send_json_error('Invalid nonce');
      return;
    }

    if (!WC()->session) {
      error_log('WPP: WC session not available in AJAX');
      wp_send_json_error('Session not available');
      return;
    }

    $priority = isset($_POST['priority']) && $_POST['priority'] === '1';

    error_log('WPP: AJAX - Priority input: ' . ($_POST['priority'] ?? 'not set'));
    error_log('WPP: AJAX - Priority boolean: ' . ($priority ? 'true' : 'false'));
    error_log('WPP: AJAX - Session ID before: ' . WC()->session->get_customer_id());

    // Store as proper boolean - this should prevent empty string storage
    if ($priority) {
      WC()->session->set('priority_processing', true);
    } else {
      // Explicitly set to false, not empty string
      WC()->session->set('priority_processing', false);
    }

    // Verify it was stored
    $stored_priority = WC()->session->get('priority_processing', 'NOT_FOUND');
    error_log('WPP: AJAX - Stored priority verification: ' . var_export($stored_priority, true));
    error_log('WPP: AJAX - Session ID after: ' . WC()->session->get_customer_id());

    if (WC()->cart) {
      error_log('WPP: AJAX - Cart available, processing fees');

      // Clear existing fees
      $fees_before = count(WC()->cart->get_fees());
      WC()->cart->fees = [];
      error_log('WPP: AJAX - Cleared ' . $fees_before . ' existing fees');

      // Add priority fee if enabled
      if ($priority) {
        $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
        $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

        error_log('WPP: AJAX - Adding fee: ' . $fee_label . ' = ' . $fee_amount);

        if ($fee_amount > 0) {
          WC()->cart->add_fee($fee_label, $fee_amount, true);
          error_log('WPP: AJAX - Fee added successfully');
        }
      } else {
        error_log('WPP: AJAX - Priority false, no fee added');
      }

      // Recalculate totals
      WC()->cart->calculate_fees();
      WC()->cart->calculate_totals();

      // Verify final state
      $final_fees = WC()->cart->get_fees();
      error_log('WPP: AJAX - Final fee count: ' . count($final_fees));
      foreach ($final_fees as $fee) {
        error_log('WPP: AJAX - Final fee: ' . $fee->name . ' = ' . $fee->amount);
      }
    } else {
      error_log('WPP: AJAX - No cart available');
    }

    // Generate checkout fragments
    ob_start();
    woocommerce_order_review();
    $order_review = ob_get_clean();

    $fragments = [
      '.woocommerce-checkout-review-order-table' => $order_review
    ];

    error_log('WPP: AJAX - Sending success response');

    wp_send_json_success([
      'fragments' => $fragments,
      'priority' => $priority,
      'debug' => [
        'stored_priority' => $stored_priority,
        'fee_count' => WC()->cart ? count(WC()->cart->get_fees()) : 0,
        'session_id' => WC()->session ? WC()->session->get_customer_id() : 'none'
      ]
    ]);
  }

  public function add_priority_fee()
  {
    // Only run on checkout and if enabled
    if (!is_checkout() || (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1')) {
      error_log('WPP: Fee calculation skipped - not checkout or plugin disabled');
      return;
    }

    if (!WC()->session) {
      error_log('WPP: Fee calculation skipped - no WC session');
      return;
    }

    $priority = WC()->session->get('priority_processing', false);

    // Enhanced debugging
    error_log('WPP: Fee calculation hook - Priority state: ' . var_export($priority, true));
    error_log('WPP: Fee calculation hook - Session ID: ' . WC()->session->get_customer_id());

    // Debug all session data
    $all_session_data = WC()->session->get_session_data();
    error_log('WPP: All session data: ' . print_r($all_session_data, true));

    // FIXED: Handle WooCommerce session storage quirks
    // WooCommerce stores false as empty string, so we need proper comparison
    $is_priority_enabled = ($priority === true || $priority === '1' || $priority === 1);

    error_log('WPP: Is priority enabled: ' . ($is_priority_enabled ? 'YES' : 'NO'));

    // Add fee if priority is enabled
    if ($is_priority_enabled) {
      $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
      $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

      // Check if fee already exists to avoid duplicates
      $existing_fees = WC()->cart->get_fees();
      $fee_exists = false;

      foreach ($existing_fees as $fee) {
        if ($fee->name === $fee_label) {
          $fee_exists = true;
          error_log('WPP: Fee already exists, skipping: ' . $fee->name);
          break;
        }
      }

      if (!$fee_exists && $fee_amount > 0) {
        WC()->cart->add_fee($fee_label, $fee_amount, true);
        error_log('WPP: Added priority fee: ' . $fee_amount);
      }
    } else {
      error_log('WPP: Priority disabled, empty, or false - no fee will be added by hook');
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
      error_log('WPP: Priority saved to order: ' . $order->get_id());
    }
  }
}
