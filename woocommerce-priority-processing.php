<?php

/**
 * Plugin Name: WooCommerce Priority Processing
 * Description: Add priority processing and express shipping option at checkout
 * Version: 1.0.0
 * Author: OpenWPClub.com
 * Author URI: https://openwpclub.com
 * License: GPL v2 or later
 * Text Domain: woo-priority
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

// Define plugin constants
define('WPP_VERSION', '1.0.0');
define('WPP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
  if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
});

class WooCommerce_Priority_Processing
{

  private static $instance = null;

  public static function instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function __construct()
  {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
      add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
      return;
    }

    // Initialize plugin
    $this->init();
  }

  private function init()
  {
    // Admin hooks
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_init', [$this, 'register_settings']);
    add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

    // Frontend hooks
    add_action('woocommerce_review_order_after_cart_contents', [$this, 'add_priority_checkbox']);
    add_action('woocommerce_checkout_before_order_review', [$this, 'add_priority_checkbox_fallback']);
    add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
    add_action('wp_ajax_wpp_update_priority', [$this, 'ajax_update_priority']);
    add_action('wp_ajax_nopriv_wpp_update_priority', [$this, 'ajax_update_priority']);

    // Block-based checkout support
    add_action('woocommerce_blocks_checkout_block_registration', [$this, 'register_checkout_block']);
    add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'update_order_from_block_checkout'], 10, 2);

    // Cart and checkout hooks
    add_action('woocommerce_cart_calculate_fees', [$this, 'add_priority_fee']);
    add_action('woocommerce_checkout_create_order', [$this, 'save_priority_to_order'], 10, 2);

    // Order admin display
    add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_priority_in_admin']);
  }

  public function woocommerce_missing_notice()
  {
?>
    <div class="notice notice-error">
      <p><?php _e('WooCommerce Priority Processing requires WooCommerce to be installed and active.', 'woo-priority'); ?></p>
    </div>
  <?php
  }

  public function add_admin_menu()
  {
    add_submenu_page(
      'woocommerce',
      __('Priority Processing', 'woo-priority'),
      __('Priority Processing', 'woo-priority'),
      'manage_woocommerce',
      'woo-priority-processing',
      [$this, 'admin_page']
    );
  }

  public function register_settings()
  {
    register_setting('wpp_settings', 'wpp_enabled');
    register_setting('wpp_settings', 'wpp_fee_amount');
    register_setting('wpp_settings', 'wpp_checkbox_label');
    register_setting('wpp_settings', 'wpp_description');
    register_setting('wpp_settings', 'wpp_fee_label');

    // Set defaults if not set
    if (get_option('wpp_fee_amount') === false) {
      update_option('wpp_fee_amount', '5.00');
    }
    if (get_option('wpp_checkbox_label') === false) {
      update_option('wpp_checkbox_label', __('Priority processing + Express shipping', 'woo-priority'));
    }
    if (get_option('wpp_description') === false) {
      update_option('wpp_description', __('Your order will be processed with priority and shipped via express delivery', 'woo-priority'));
    }
    if (get_option('wpp_fee_label') === false) {
      update_option('wpp_fee_label', __('Priority Processing & Express Shipping', 'woo-priority'));
    }
    if (get_option('wpp_enabled') === false) {
      update_option('wpp_enabled', '1');
    }
  }

  public function admin_page()
  {
  ?>
    <div class="wrap">
      <h1><?php _e('Priority Processing Settings', 'woo-priority'); ?></h1>

      <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
          <p><?php _e('Settings saved successfully!', 'woo-priority'); ?></p>
        </div>
      <?php endif; ?>

      <form method="post" action="options.php">
        <?php settings_fields('wpp_settings'); ?>

        <table class="form-table">
          <tr>
            <th scope="row">
              <label for="wpp_enabled"><?php _e('Enable Priority Processing', 'woo-priority'); ?></label>
            </th>
            <td>
              <input type="checkbox" id="wpp_enabled" name="wpp_enabled" value="1" <?php checked(get_option('wpp_enabled'), '1'); ?> />
              <p class="description"><?php _e('Enable or disable the priority processing option at checkout', 'woo-priority'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="wpp_fee_amount"><?php _e('Fee Amount', 'woo-priority'); ?></label>
            </th>
            <td>
              <input type="number" step="0.01" min="0" id="wpp_fee_amount" name="wpp_fee_amount"
                value="<?php echo esc_attr(get_option('wpp_fee_amount', '5.00')); ?>" />
              <span><?php echo get_woocommerce_currency_symbol(); ?></span>
              <p class="description"><?php _e('The additional fee for priority processing', 'woo-priority'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="wpp_checkbox_label"><?php _e('Checkbox Label', 'woo-priority'); ?></label>
            </th>
            <td>
              <input type="text" id="wpp_checkbox_label" name="wpp_checkbox_label"
                value="<?php echo esc_attr(get_option('wpp_checkbox_label')); ?>"
                class="regular-text" />
              <p class="description"><?php _e('The label shown next to the checkbox', 'woo-priority'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="wpp_description"><?php _e('Description Text', 'woo-priority'); ?></label>
            </th>
            <td>
              <textarea id="wpp_description" name="wpp_description" rows="3" cols="50" class="large-text"><?php
                                                                                                          echo esc_textarea(get_option('wpp_description'));
                                                                                                          ?></textarea>
              <p class="description"><?php _e('Additional description shown below the checkbox', 'woo-priority'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="wpp_fee_label"><?php _e('Fee Label in Cart', 'woo-priority'); ?></label>
            </th>
            <td>
              <input type="text" id="wpp_fee_label" name="wpp_fee_label"
                value="<?php echo esc_attr(get_option('wpp_fee_label')); ?>"
                class="regular-text" />
              <p class="description"><?php _e('The label shown in cart and checkout totals', 'woo-priority'); ?></p>
            </td>
          </tr>
        </table>

        <?php submit_button(); ?>
      </form>
    </div>
  <?php
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
      <td colspan="2" style="padding: 10px;">
        <div id="wpp-priority-option" style="background: #f7f7f7; padding: 12px; border-radius: 4px;">
          <label style="display: flex; align-items: flex-start; cursor: pointer;">
            <input type="checkbox" id="wpp_priority_checkbox" class="wpp-priority-checkbox" name="priority_processing" value="1"
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
        // Remove duplicate if table version exists
        if ($('#wpp-priority-option').length > 0) {
          $('#wpp-priority-option-fallback').remove();
        }
      });
    </script>
    <?php
  }

  public function register_checkout_block()
  {
    // For block-based checkout, we need to inject via JavaScript
    add_action('wp_footer', function () {
      if (!is_checkout() || get_option('wpp_enabled') !== '1') {
        return;
      }

      $fee_amount = get_option('wpp_fee_amount', '5.00');
      $checkbox_label = get_option('wpp_checkbox_label');
      $description = get_option('wpp_description');
      $is_checked = WC()->session ? WC()->session->get('priority_processing', false) : false;
    ?>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          // Wait for checkout to be ready
          setTimeout(function() {
            var checkoutForm = document.querySelector('.wc-block-checkout__form');
            var orderSummary = document.querySelector('.wc-block-components-order-summary');

            if (checkoutForm && !document.getElementById('wpp-block-priority-option')) {
              var priorityHtml = `
                            <div id="wpp-block-priority-option" style="margin: 20px; padding: 15px; background: #f7f7f7; border: 1px solid #e0e0e0; border-radius: 4px;">
                                <label style="display: flex; align-items: flex-start; cursor: pointer;">
                                    <input type="checkbox" id="wpp_priority_checkbox_block" 
                                           class="wpp-priority-checkbox" 
                                           <?php echo $is_checked ? 'checked' : ''; ?> 
                                           style="margin-right: 8px; margin-top: 2px;" />
                                    <span>
                                        <strong><?php echo esc_js($checkbox_label); ?>: 
                                            <?php echo get_woocommerce_currency_symbol() . number_format($fee_amount, 2); ?></strong>
                                        <?php if ($description): ?>
                                            <br><small style="color: #666; display: block; margin-top: 4px;">
                                                <?php echo esc_js($description); ?>
                                            </small>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            </div>
                        `;

              // Try to insert before order summary
              if (orderSummary) {
                orderSummary.insertAdjacentHTML('beforebegin', priorityHtml);
              } else {
                // Fallback: insert at the beginning of checkout form
                checkoutForm.insertAdjacentHTML('afterbegin', priorityHtml);
              }
            }
          }, 1000);
        });
      </script>
    <?php
    });
  }

  public function update_order_from_block_checkout($order, $request)
  {
    $priority = WC()->session ? WC()->session->get('priority_processing', false) : false;
    if ($priority) {
      $order->update_meta_data('_priority_processing', 'yes');
    }
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

  public function admin_scripts($hook)
  {
    if ($hook === 'woocommerce_page_woo-priority-processing') {
      wp_enqueue_style('wpp-admin', WPP_PLUGIN_URL . 'assets/admin.css', [], WPP_VERSION);
    }
  }

  public function ajax_update_priority()
  {
    check_ajax_referer('wpp_nonce', 'nonce');

    $priority = isset($_POST['priority']) && $_POST['priority'] === '1';
    WC()->session->set('priority_processing', $priority);

    // Trigger cart recalculation
    WC()->cart->calculate_fees();
    WC()->cart->calculate_totals();

    wp_send_json_success([
      'fragments' => apply_filters('woocommerce_update_order_review_fragments', [])
    ]);
  }

  public function add_priority_fee()
  {
    if (!is_checkout()) {
      return;
    }

    if (get_option('wpp_enabled') !== '1') {
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

  public function display_priority_in_admin($order)
  {
    $priority = $order->get_meta('_priority_processing');
    if ($priority === 'yes') {
    ?>
      <p style="margin-top: 10px;">
        <strong style="color: #d63638;">⚡ <?php _e('Priority Processing', 'woo-priority'); ?></strong><br>
        <?php _e('This order has priority processing and express shipping', 'woo-priority'); ?>
      </p>
<?php
    }
  }
}

// Initialize plugin
add_action('plugins_loaded', function () {
  WooCommerce_Priority_Processing::instance();
});

// Activation hook
register_activation_hook(__FILE__, function () {
  // Create default options
  add_option('wpp_enabled', '1');
  add_option('wpp_fee_amount', '5.00');
  add_option('wpp_checkbox_label', __('Priority processing + Express shipping', 'woo-priority'));
  add_option('wpp_description', __('Your order will be processed with priority and shipped via express delivery', 'woo-priority'));
  add_option('wpp_fee_label', __('Priority Processing & Express Shipping', 'woo-priority'));
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
  // Clear any sessions
  if (class_exists('WooCommerce') && WC()->session) {
    WC()->session->set('priority_processing', false);
  }
});
