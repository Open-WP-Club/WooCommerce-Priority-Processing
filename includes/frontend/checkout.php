<?php

/**
 * Frontend Checkout Handler
 * Manages checkout display, forms, and user interface
 * 
 * FIXED: Removed fallback checkboxes and HTML generation to prevent flickering
 */
class Frontend_Checkout
{
  public function __construct()
  {
    // Classic WooCommerce checkout hook - single display point
    add_action('woocommerce_review_order_after_cart_contents', [$this, 'add_priority_checkbox']);

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
   * Add priority processing checkbox to checkout
   * FIXED: Single display point, no fallbacks
   */
  public function add_priority_checkbox()
  {
    if (!$this->should_display_checkbox()) {
      return;
    }

    $this->render_priority_checkbox_section();
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
   * FIXED: Consistent with 'yes'/'no' format
   */
  private function get_checkbox_state()
  {
    if (!WC()->session) {
      return false;
    }

    $session_priority = WC()->session->get('priority_processing', 'no');

    // Check for 'yes' string (WooCommerce standard) or legacy boolean values
    $is_checked = ($session_priority === 'yes' || $session_priority === true || $session_priority === '1' || $session_priority === 1);

    error_log("WPP: Getting checkbox state - Session value: {$session_priority}, Returning: " . ($is_checked ? 'checked' : 'unchecked'));

    return $is_checked;
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
   * Render the priority checkbox section
   * FIXED: Single, clean rendering without fallbacks
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
            <input type="checkbox"
              id="wpp_priority_checkbox"
              class="wpp-priority-checkbox"
              name="priority_processing"
              value="1"
              <?php checked($options['is_checked'], true); ?> />
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
   * Enqueue frontend scripts and styles
   * FIXED: Simplified script loading
   */
  public function frontend_scripts()
  {
    if (!is_checkout()) {
      return;
    }

    // Enqueue frontend CSS
    wp_enqueue_style(
      'wpp-frontend',
      WPP_PLUGIN_URL . 'assets/css/frontend.css',
      ['woocommerce-general'],
      WPP_VERSION
    );

    // Check if using block-based checkout
    $using_blocks = has_block('woocommerce/checkout');

    // Prepare script data
    $script_data = [
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('wpp_nonce'),
      'fee_amount' => get_option('wpp_fee_amount', '5.00'),
      'fee_label' => get_option('wpp_fee_label', 'Priority Processing & Express Shipping'),
      'using_blocks' => $using_blocks
    ];

    // Load appropriate script based on checkout type
    if ($using_blocks) {
      wp_enqueue_script(
        'wpp-frontend-blocks',
        WPP_PLUGIN_URL . 'assets/js/frontend-blocks.js',
        ['jquery'],
        WPP_VERSION,
        true
      );
      wp_localize_script('wpp-frontend-blocks', 'wpp_ajax', $script_data);
    } else {
      wp_enqueue_script(
        'wpp-frontend',
        WPP_PLUGIN_URL . 'assets/js/frontend.js',
        ['jquery'],
        WPP_VERSION,
        true
      );
      wp_localize_script('wpp-frontend', 'wpp_ajax', $script_data);
    }
  }
}
