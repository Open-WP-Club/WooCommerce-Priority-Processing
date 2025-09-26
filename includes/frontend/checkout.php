<?php

/**
 * Frontend Checkout Handler
 * Manages checkout display, forms, and user interface
 */
class Frontend_Checkout
{
  public function __construct()
  {
    // Classic WooCommerce checkout hooks
    add_action('woocommerce_review_order_after_cart_contents', [$this, 'add_priority_checkbox']);
    add_action('woocommerce_checkout_before_order_review', [$this, 'add_priority_checkbox_fallback']);

    // Additional fallback hooks for different themes/plugins
    add_action('woocommerce_checkout_order_review', [$this, 'add_priority_checkbox_fallback']);
    add_action('woocommerce_checkout_after_customer_details', [$this, 'add_priority_checkbox_fallback']);
    add_action('woocommerce_checkout_billing', [$this, 'add_priority_checkbox_fallback']);

    // Frontend scripts and styles
    add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);

    // Initialize session properly
    add_action('init', [$this, 'init_session']);

    // Clear priority session when needed
    add_action('woocommerce_thankyou', [$this, 'clear_priority_session']);
    add_action('woocommerce_cart_emptied', [$this, 'clear_priority_session']);
  }

  /**
   * Initialize WooCommerce session properly
   */
  public function init_session()
  {
    if (!is_admin() && !defined('DOING_AJAX')) {
      if (WC()->session && !WC()->session->has_session()) {
        WC()->session->set_customer_session_cookie(true);
      }
    }
  }

  /**
   * Clear priority session when order completed or cart emptied
   */
  public function clear_priority_session($order_id = null)
  {
    if (WC()->session) {
      WC()->session->set('priority_processing', false);
      error_log('WPP: Priority session cleared from checkout');
    }
  }

  /**
   * Add priority processing checkbox to checkout (main method)
   */
  public function add_priority_checkbox()
  {
    if (!$this->should_display_checkbox()) {
      return;
    }

    $this->render_priority_checkbox_section();
  }

  /**
   * Add priority processing checkbox as fallback
   */
  public function add_priority_checkbox_fallback()
  {
    if (!$this->should_display_checkbox()) {
      return;
    }

    $this->render_priority_checkbox_fallback();
  }

  /**
   * Check if priority checkbox should be displayed
   */
  private function should_display_checkbox()
  {
    // Check if feature is enabled
    if (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1') {
      return false;
    }

    // Check user permissions
    if (!Core_Permissions::can_access_priority_processing()) {
      Core_Permissions::log_permission_check('checkout_display');
      return false;
    }

    return true;
  }

  /**
   * Get current checkbox state from session
   */
  private function get_checkbox_state()
  {
    if (!WC()->session) {
      return false;
    }

    $session_priority = WC()->session->get('priority_processing', false);
    return ($session_priority === true || $session_priority === '1' || $session_priority === 1);
  }

  /**
   * Get checkout display options
   */
  private function get_display_options()
  {
    return [
      'fee_amount' => get_option('wpp_fee_amount', '5.00'),
      'checkbox_label' => get_option('wpp_checkbox_label', 'Priority processing + Express shipping'),
      'description' => get_option('wpp_description', ''),
      'section_title' => get_option('wpp_section_title', 'Express Options'),
      'is_checked' => $this->get_checkbox_state()
    ];
  }

  /**
   * Render the main priority checkbox section
   */
  private function render_priority_checkbox_section()
  {
    $options = $this->get_display_options();
?>
    <tr class="wpp-priority-row">
      <td colspan="2" style="padding: 15px 0 10px 0;">
        <div id="wpp-priority-section">
          <h4>⚡ <?php echo esc_html($options['section_title']); ?></h4>
          <label>
            <input type="checkbox" id="wpp_priority_checkbox" class="wpp-priority-checkbox"
              name="priority_processing" value="1" <?php checked($options['is_checked'], true); ?> />
            <span>
              <strong>
                <?php echo esc_html($options['checkbox_label']); ?>
                <span class="wpp-price">( + <?php echo wc_price($options['fee_amount']); ?>)</span>
              </strong>
              <?php if ($options['description']): ?>
                <small><?php echo esc_html($options['description']); ?></small>
              <?php endif; ?>
            </span>
          </label>
        </div>
      </td>
    </tr>
  <?php
  }

  /**
   * Render fallback priority checkbox (for themes that don't support the main method)
   */
  private function render_priority_checkbox_fallback()
  {
    $options = $this->get_display_options();
  ?>
    <div id="wpp-priority-option-fallback">
      <h4>⚡ <?php echo esc_html($options['section_title']); ?></h4>
      <label>
        <input type="checkbox" id="wpp_priority_checkbox_fallback" class="wpp-priority-checkbox"
          name="priority_processing" value="1" <?php checked($options['is_checked'], true); ?> />
        <span>
          <strong>
            <?php echo esc_html($options['checkbox_label']); ?>:
            <span class="wpp-price"><?php echo wc_price($options['fee_amount']); ?></span>
          </strong>
          <?php if ($options['description']): ?>
            <small><?php echo esc_html($options['description']); ?></small>
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

  /**
   * Enqueue frontend scripts and styles
   */
  public function frontend_scripts()
  {
    if (!is_checkout()) {
      return;
    }

    // Enqueue frontend CSS for priority processing styling
    wp_enqueue_style(
      'wpp-frontend',
      WPP_PLUGIN_URL . 'assets/css/frontend.css',
      ['woocommerce-general'],
      WPP_VERSION
    );

    // Check if using blocks
    $using_blocks = has_block('woocommerce/checkout');
    $script_data = [
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('wpp_nonce'),
      'fee_amount' => get_option('wpp_fee_amount', '5.00'),
      'fee_label' => get_option('wpp_fee_label', 'Priority Processing & Express Shipping'),
      'using_blocks' => $using_blocks
    ];

    if ($using_blocks) {
      wp_enqueue_script('wpp-frontend-blocks', WPP_PLUGIN_URL . 'assets/js/frontend-blocks.js', ['jquery'], WPP_VERSION, true);
      wp_localize_script('wpp-frontend-blocks', 'wpp_ajax', array_merge($script_data, [
        'checkbox_label' => get_option('wpp_checkbox_label', 'Priority processing + Express shipping'),
        'description' => get_option('wpp_description', ''),
        'section_title' => get_option('wpp_section_title', 'Express Options')
      ]));
    } else {
      wp_enqueue_script('wpp-frontend', WPP_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], WPP_VERSION, true);
      wp_localize_script('wpp-frontend', 'wpp_ajax', $script_data);
    }
  }

  /**
   * Generate custom checkout HTML for AJAX responses
   */
  public function generate_checkout_html($priority_enabled = false)
  {
    $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
    $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

    // Calculate totals
    $cart_subtotal = WC()->cart->get_subtotal();
    $cart_tax = WC()->cart->get_total_tax();
    $shipping_total = WC()->cart->get_shipping_total();
    $priority_fee_amount = $priority_enabled ? $fee_amount : 0;
    $new_total = $cart_subtotal + $cart_tax + $shipping_total + $priority_fee_amount;

    // Get display options for checkbox
    $options = $this->get_display_options();
    $options['is_checked'] = $priority_enabled;

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

        // Add checkbox row
        ?>
        <tr class="wpp-priority-row">
          <td colspan="2" style="padding: 15px 0 10px 0;">
            <div id="wpp-priority-section">
              <h4>⚡ <?php echo esc_html($options['section_title']); ?></h4>
              <label>
                <input type="checkbox" id="wpp_priority_checkbox" class="wpp-priority-checkbox"
                  name="priority_processing" value="1" <?php checked($options['is_checked'], true); ?> />
                <span>
                  <strong>
                    <?php echo esc_html($options['checkbox_label']); ?>
                    <span class="wpp-price">( + <?php echo wc_price($fee_amount); ?>)</span>
                  </strong>
                  <?php if ($options['description']): ?>
                    <small><?php echo esc_html($options['description']); ?></small>
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

        <?php if ($priority_enabled && $priority_fee_amount > 0): ?>
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
    return ob_get_clean();
  }
}
