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

    // Fee hook for final order processing only
    add_action('woocommerce_cart_calculate_fees', [$this, 'add_priority_fee']);
    add_action('woocommerce_checkout_create_order', [$this, 'save_priority_to_order'], 10, 2);

    // Clear priority session when order is completed
    add_action('woocommerce_thankyou', [$this, 'clear_priority_session']);
    add_action('woocommerce_cart_emptied', [$this, 'clear_priority_session']);

    // Initialize session properly
    add_action('init', [$this, 'init_session']);
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
          'using_blocks' => false,
          'fee_amount' => get_option('wpp_fee_amount', '5.00'),
          'fee_label' => get_option('wpp_fee_label', 'Priority Processing & Express Shipping')
        ]);
      }
    }
  }

  // **NEW APPROACH: Don't manage fees in AJAX, just return calculated HTML**
  public function ajax_update_priority()
  {
    error_log('WPP: AJAX started');

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpp_nonce')) {
      wp_send_json_error('Invalid nonce');
      return;
    }

    if (!WC()->session) {
      wp_send_json_error('WooCommerce session not available');
      return;
    }

    $priority = isset($_POST['priority']) && $_POST['priority'] === '1';
    $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
    $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

    error_log('WPP: Priority requested: ' . ($priority ? 'TRUE' : 'FALSE'));

    // Store in session for final order processing
    WC()->session->set('priority_processing', $priority);

    // **GENERATE CUSTOM CHECKOUT HTML WITH CALCULATED TOTALS**
    $cart_subtotal = WC()->cart->get_subtotal();
    $cart_tax = WC()->cart->get_total_tax();
    $shipping_total = WC()->cart->get_shipping_total();

    // Calculate new total with or without priority fee
    $priority_fee_amount = $priority ? $fee_amount : 0;
    $new_total = $cart_subtotal + $cart_tax + $shipping_total + $priority_fee_amount;

    error_log('WPP: Calculated totals - Subtotal: ' . $cart_subtotal . ', Fee: ' . $priority_fee_amount . ', New Total: ' . $new_total);

    // Generate custom checkout HTML with our calculated totals
    ob_start();
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

        // **ADD CHECKBOX ROW HERE TO PRESERVE IT**
        $checkbox_label = get_option('wpp_checkbox_label', 'Priority processing + Express shipping');
        $section_title = get_option('wpp_section_title', 'Express Options');
        $description = get_option('wpp_description', '');
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
    $checkout_html = ob_get_clean();

    error_log('WPP: Generated custom HTML with ' . ($priority ? 'priority fee' : 'no priority fee'));

    wp_send_json_success([
      'fragments' => ['.woocommerce-checkout-review-order-table' => $checkout_html],
      'debug' => [
        'priority' => $priority,
        'fee_amount' => $priority_fee_amount,
        'new_total' => wc_price($new_total)
      ]
    ]);
  }

  // **Fee hook only for final order processing, not AJAX**
  public function add_priority_fee()
  {
    // Skip during AJAX - we handle display manually
    if (defined('DOING_AJAX') && DOING_AJAX) {
      return;
    }

    if (!is_checkout() || (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1')) {
      return;
    }

    if (!WC()->session) {
      return;
    }

    $priority = WC()->session->get('priority_processing', false);
    $should_add_fee = ($priority === true || $priority === 1 || $priority === '1');

    error_log('WPP: Fee hook (non-AJAX) - Priority: ' . var_export($priority, true) . ', Should add: ' . ($should_add_fee ? 'YES' : 'NO'));

    if ($should_add_fee) {
      $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
      $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

      if ($fee_amount > 0) {
        WC()->cart->add_fee($fee_label, $fee_amount);
        error_log('WPP: Fee hook (non-AJAX) - Added fee: ' . $fee_amount);
      }
    }
  }

  public function save_priority_to_order($order, $data)
  {
    if (!WC()->session) {
      return;
    }

    $priority = WC()->session->get('priority_processing', false);
    if ($priority === true || $priority === 1 || $priority === '1') {
      $order->update_meta_data('_priority_processing', 'yes');
      $order->save_meta_data();
      error_log('WPP: Priority saved to order: ' . $order->get_id());
    }
  }
}
