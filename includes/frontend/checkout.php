<?php
/**
 * Frontend Checkout Handler
 * Handles priority processing checkbox display and integration
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.0.0
 */

declare(strict_types=1);

/**
 * Frontend Checkout Class
 *
 * @since 1.0.0
 */
class Frontend_Checkout {

  public function __construct() {
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);

    // Handle checkout field display (our custom styled version only)
    add_action('woocommerce_after_order_notes', [$this, 'display_priority_section']);

    // Save the field value when order is created
    add_action('woocommerce_checkout_update_order_meta', [$this, 'save_priority_field'], 10, 1);
  }

  /**
   * Display priority section with custom styling
   */
  public function display_priority_section($checkout) {
    if (!$this->should_display_field()) {
      return;
    }

    $section_title = get_option('wpp_section_title', __('Express Options', 'woo-priority'));
    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $checkbox_label = get_option('wpp_checkbox_label', __('Priority processing + Express shipping', 'woo-priority'));
    $description = get_option('wpp_description', __('Your order will be processed with priority and shipped via express delivery', 'woo-priority'));
    $is_checked = $this->get_checkbox_state();

?>
    <div id="wpp-priority-section" class="wpp-priority-section" style="background: #f8f9fa; border: 2px solid #dee2e6; border-radius: 6px; padding: 20px; margin: 20px 0;">
      <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px; font-weight: 600;">⚡ <?php echo esc_html($section_title); ?></h3>
      <div class="wpp-priority-field-wrapper">
        <label class="wpp-priority-label" style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
          <input
            type="checkbox"
            name="priority_processing"
            id="priority_processing"
            class="wpp-priority-checkbox input-checkbox"
            value="1"
            style="margin: 2px 0 0 0; cursor: pointer; flex-shrink: 0;"
            <?php checked($is_checked, true); ?> />
          <span class="wpp-label-content" style="flex: 1;">
            <strong style="color: #28a745; font-weight: 600; display: block; font-size: 14px;">
              <?php echo esc_html($checkbox_label); ?>
              <span class="wpp-price" style="color: #dc3545; font-weight: 600; margin-left: 5px;">(+ <?php echo wc_price($fee_amount); ?> )</span>
            </strong>
            <?php if (!empty($description)): ?>
              <small class="description" style="color: #6c757d; font-size: 13px; line-height: 1.4; display: block;"><?php echo esc_html($description); ?></small>
            <?php endif; ?>
          </span>
        </label>
      </div>
    </div>
<?php
  }

  /**
   * Save priority field value to order meta
   */
  public function save_priority_field($order_id) {
    // Verify nonce for security
    if (!isset($_POST['woocommerce-process-checkout-nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce'])), 'woocommerce-process_checkout')) {
      return;
    }

    if (isset($_POST['priority_processing']) && sanitize_text_field(wp_unslash($_POST['priority_processing'])) === '1') {
      // Update session
      if (WC()->session) {
        WC()->session->set('priority_processing', true);
      }
    } else {
      // Clear session if not checked
      if (WC()->session) {
        WC()->session->set('priority_processing', false);
      }
    }
  }

  /**
   * Check if priority field should be displayed
   */
  private function should_display_field() {
    // Check if feature is enabled
    if (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1') {
      return false;
    }

    // Check user permissions
    if (!Core_Permissions::can_access_priority_processing()) {
      return false;
    }

    return true;
  }

  /**
   * Get current checkbox state from session
   */
  private function get_checkbox_state() {
    if (!WC()->session) {
      return false;
    }

    $session_priority = WC()->session->get('priority_processing', false);
    return ($session_priority === true || $session_priority === '1' || $session_priority === 1);
  }

  /**
   * Enqueue frontend scripts and styles
   */
  public function frontend_scripts() {
    if (!is_checkout()) {
      return;
    }

    // Enqueue frontend CSS
    wp_enqueue_style(
      'wpp-frontend',
      WPP_PLUGIN_URL . 'assets/css/frontend.css',
      [],
      WPP_VERSION
    );

    // Enqueue JavaScript for AJAX handling
    wp_enqueue_script(
      'wpp-frontend-blocks',
      WPP_PLUGIN_URL . 'assets/js/frontend-blocks.js',
      ['jquery', 'wc-checkout'],
      WPP_VERSION,
      true
    );

    // Localize script with necessary data
    wp_localize_script('wpp-frontend-blocks', 'wppData', [
      'ajax_url'   => admin_url('admin-ajax.php'),
      'nonce'      => wp_create_nonce('wpp_nonce'),
      'fee_amount' => get_option('wpp_fee_amount', '5.00'),
      'fee_label'  => get_option('wpp_fee_label', __('Priority Processing & Express Shipping', 'woo-priority'))
    ]);
  }
}
