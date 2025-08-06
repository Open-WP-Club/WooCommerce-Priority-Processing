<?php

class WPP_Admin
{
  public function __construct()
  {
    // Try WooCommerce integration first
    add_action('woocommerce_loaded', [$this, 'init_woocommerce_settings']);
    
    // Fallback: Create separate admin page if WooCommerce integration fails
    add_action('admin_menu', [$this, 'add_fallback_admin_menu']);
    add_action('admin_init', [$this, 'register_settings']);
    add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
  }

  public function init_woocommerce_settings()
  {
    // Only load WooCommerce settings integration if WC_Settings_Page exists
    if (class_exists('WC_Settings_Page')) {
      add_filter('woocommerce_get_settings_pages', [$this, 'add_woocommerce_settings_page']);
      error_log('WPP: WooCommerce settings integration loaded');
    } else {
      error_log('WPP: WC_Settings_Page not available, using fallback admin page');
    }
  }

  public function add_woocommerce_settings_page($settings)
  {
    // Include and create the settings page class
    if (!class_exists('WPP_WooCommerce_Settings')) {
      include_once WPP_PLUGIN_DIR . 'includes/wpp-wc-settings.php';
    }
    
    if (class_exists('WPP_WooCommerce_Settings')) {
      $settings[] = new WPP_WooCommerce_Settings();
      error_log('WPP: Added WooCommerce settings page');
    }
    
    return $settings;
  }

  public function add_fallback_admin_menu()
  {
    // Only add fallback menu if we're not successfully integrated with WooCommerce
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
    register_setting('wpp_settings', 'wpp_section_title');
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
              <span><?php echo function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : ''; ?></span>
              <p class="description"><?php _e('The additional fee for priority processing', 'woo-priority'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="wpp_section_title"><?php _e('Section Title', 'woo-priority'); ?></label>
            </th>
            <td>
              <input type="text" id="wpp_section_title" name="wpp_section_title"
                value="<?php echo esc_attr(get_option('wpp_section_title', 'Express Options')); ?>"
                class="regular-text" />
              <p class="description"><?php _e('The title shown above the priority processing option', 'woo-priority'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="wpp_checkbox_label"><?php _e('Checkbox Label', 'woo-priority'); ?></label>
            </th>
            <td>
              <input type="text" id="wpp_checkbox_label" name="wpp_checkbox_label"
                value="<?php echo esc_attr(get_option('wpp_checkbox_label', 'Priority processing + Express shipping')); ?>"
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
                echo esc_textarea(get_option('wpp_description', 'Your order will be processed with priority and shipped via express delivery'));
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
                value="<?php echo esc_attr(get_option('wpp_fee_label', 'Priority Processing & Express Shipping')); ?>"
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

  public function admin_scripts($hook)
  {
    // Load styles on WooCommerce settings page when our tab is active
    if ($hook === 'woocommerce_page_wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'wpp_priority') {
      wp_enqueue_style('wpp-admin', WPP_PLUGIN_URL . 'assets/admin.css', [], WPP_VERSION);
    }
    
    // Also load on fallback admin page
    if ($hook === 'woocommerce_page_woo-priority-processing') {
      wp_enqueue_style('wpp-admin', WPP_PLUGIN_URL . 'assets/admin.css', [], WPP_VERSION);
    }
  }
}