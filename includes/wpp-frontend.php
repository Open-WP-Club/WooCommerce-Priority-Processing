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
  }

  public function add_priority_checkbox()
  {
    if (get_option('wpp_enabled') !== '1') {
      return;
    }

    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $checkbox_label = get_option('wpp_checkbox_label');
    $description = get_option('wpp_description');

    if (WC()->session && !WC()->session->has_session()) {
      WC()->session->set_customer_session_cookie(true);
    }

    $is_checked = WC()->session ? WC()->session->get('priority_processing', false) : false;

?>
    <tr class="wpp-priority-row">
      <td colspan="2" style="border-top: 2px solid #e0e0e0; padding: 15px 0 10px 0;">
        <div id="wpp-priority-section" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6;">
          <h4 style="margin: 0 0 10px 0; color: #495057; font-size: 16px;">
            ⚡ <?php _e('Express Options', 'woo-priority'); ?>
          </h4>
          <label style="display: flex; align-items: flex-start; cursor: pointer; font-size: 14px;">
            <input type="checkbox" id="wpp_priority_checkbox" class="wpp-priority-checkbox"
              name="priority_processing" value="1" <?php checked($is_checked); ?>
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
    if (get_option('wpp_enabled') !== '1') {
      return;
    }

    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $checkbox_label = get_option('wpp_checkbox_label');
    $description = get_option('wpp_description');

    if (WC()->session && !WC()->session->has_session()) {
      WC()->session->set_customer_session_cookie(true);
    }

    $is_checked = WC()->session ? WC()->session->get('priority_processing', false) : false;

  ?>
    <div id="wpp-priority-option-fallback" style="margin: 20px 0; padding: 15px; background: #f7f7f7; border: 1px solid #e0e0e0; border-radius: 4px;">
      <label style="display: flex; align-items: flex-start; cursor: pointer;">
        <input type="checkbox" id="wpp_priority_checkbox_fallback" class="wpp-priority-checkbox" name="priority_processing" value="1"
          <?php checked($is_checked); ?> style="margin-right: 8px; margin-top: 2px;" />
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
        }
      });
    </script>
<?php
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

    WC()->session->set('priority_processing', $priority);

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

  public function add_priority_fee()
  {
    if (!is_checkout() || get_option('wpp_enabled') !== '1') {
      return;
    }

    $priority = WC()->session ? WC()->session->get('priority_processing', false) : false;

    if ($priority) {
      $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
      $fee_label = get_option('wpp_fee_label');
      WC()->cart->add_fee($fee_label, $fee_amount, true);
    }
  }

  public function save_priority_to_order($order, $data)
  {
    $priority = WC()->session ? WC()->session->get('priority_processing', false) : false;
    if ($priority) {
      $order->update_meta_data('_priority_processing', 'yes');
      $order->save_meta_data();
    }
  }
}
