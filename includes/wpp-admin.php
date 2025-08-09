<?php

class WPP_Admin
{
  public function __construct()
  {
    // Always add the admin menu as primary approach
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_init', [$this, 'register_settings']);
    add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

    // Try WooCommerce integration as secondary approach
    add_action('woocommerce_loaded', [$this, 'try_woocommerce_integration']);
  }

  public function try_woocommerce_integration()
  {
    // Only try if WC_Settings_Page exists and we haven't already integrated
    if (class_exists('WC_Settings_Page') && !get_transient('wpp_wc_integration_attempted')) {
      add_filter('woocommerce_get_settings_pages', [$this, 'add_woocommerce_settings_page']);
      set_transient('wpp_wc_integration_attempted', true, HOUR_IN_SECONDS);
      error_log('WPP: WooCommerce settings integration attempted');
    }
  }

  public function add_woocommerce_settings_page($settings)
  {
    // Include and create the settings page class
    if (!class_exists('WPP_WooCommerce_Settings')) {
      $settings_file = WPP_PLUGIN_DIR . 'includes/wpp-wc-settings.php';
      if (file_exists($settings_file)) {
        include_once $settings_file;
      }
    }

    if (class_exists('WPP_WooCommerce_Settings')) {
      $settings[] = new WPP_WooCommerce_Settings();
      error_log('WPP: Added WooCommerce settings page');
    }

    return $settings;
  }

  public function add_admin_menu()
  {
    // Always add as WooCommerce submenu - this is reliable
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
    // Get current settings
    $enabled = get_option('wpp_enabled', '1');
    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $section_title = get_option('wpp_section_title', 'Express Options');
    $checkbox_label = get_option('wpp_checkbox_label', 'Priority processing + Express shipping');
    $description = get_option('wpp_description', 'Your order will be processed with priority and shipped via express delivery');
    $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

    // Get currency symbol
    $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
?>
    <div class="wrap wpp-admin-container">
      <h1><?php _e('Priority Processing Settings', 'woo-priority'); ?></h1>

      <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
          <p><strong><?php _e('Settings saved successfully!', 'woo-priority'); ?></strong> <?php _e('Your priority processing options are now active.', 'woo-priority'); ?></p>
        </div>
      <?php endif; ?>

      <div class="wpp-settings-grid">
        <!-- Main Settings Panel -->
        <div class="wpp-settings-card">
          <h2>⚙️ <?php _e('Configuration', 'woo-priority'); ?></h2>

          <form method="post" action="options.php" id="wpp-settings-form">
            <?php settings_fields('wpp_settings'); ?>

            <div class="wpp-feature-section">
              <div class="wpp-feature-title">🔧 <?php _e('Basic Settings', 'woo-priority'); ?></div>

              <table class="form-table">
                <tr>
                  <th scope="row">
                    <label for="wpp_enabled"><?php _e('Enable Feature', 'woo-priority'); ?></label>
                  </th>
                  <td>
                    <div class="wpp-toggle-wrapper">
                      <label class="wpp-toggle">
                        <input type="checkbox" id="wpp_enabled" name="wpp_enabled" value="1" <?php checked($enabled, '1'); ?> />
                        <span class="wpp-toggle-slider"></span>
                      </label>
                      <span class="wpp-status <?php echo ($enabled === '1') ? 'wpp-status-enabled' : 'wpp-status-disabled'; ?>">
                        <?php echo ($enabled === '1') ? __('Active', 'woo-priority') : __('Inactive', 'woo-priority'); ?>
                      </span>
                    </div>
                    <p class="description"><?php _e('Enable or disable the priority processing option at checkout', 'woo-priority'); ?></p>
                  </td>
                </tr>

                <tr>
                  <th scope="row">
                    <label for="wpp_fee_amount"><?php _e('Additional Fee', 'woo-priority'); ?></label>
                  </th>
                  <td>
                    <div class="wpp-currency-wrapper">
                      <span class="wpp-currency-symbol"><?php echo esc_html($currency_symbol); ?></span>
                      <input type="number" step="0.01" min="0" id="wpp_fee_amount" name="wpp_fee_amount"
                        value="<?php echo esc_attr($fee_amount); ?>" />
                    </div>
                    <p class="description"><?php _e('Amount to charge for priority processing and express shipping', 'woo-priority'); ?></p>
                  </td>
                </tr>
              </table>
            </div>

            <div class="wpp-feature-section">
              <div class="wpp-feature-title">🎨 <?php _e('Display Settings', 'woo-priority'); ?></div>

              <table class="form-table">
                <tr>
                  <th scope="row">
                    <label for="wpp_section_title"><?php _e('Section Title', 'woo-priority'); ?></label>
                  </th>
                  <td>
                    <input type="text" id="wpp_section_title" name="wpp_section_title"
                      value="<?php echo esc_attr($section_title); ?>" />
                    <p class="description"><?php _e('Heading shown above the priority processing option', 'woo-priority'); ?></p>
                  </td>
                </tr>

                <tr>
                  <th scope="row">
                    <label for="wpp_checkbox_label"><?php _e('Checkbox Label', 'woo-priority'); ?></label>
                  </th>
                  <td>
                    <input type="text" id="wpp_checkbox_label" name="wpp_checkbox_label"
                      value="<?php echo esc_attr($checkbox_label); ?>" />
                    <p class="description"><?php _e('Text displayed next to the checkbox option', 'woo-priority'); ?></p>
                  </td>
                </tr>

                <tr>
                  <th scope="row">
                    <label for="wpp_description"><?php _e('Help Text', 'woo-priority'); ?></label>
                  </th>
                  <td>
                    <textarea id="wpp_description" name="wpp_description"><?php echo esc_textarea($description); ?></textarea>
                    <p class="description"><?php _e('Additional explanation shown below the checkbox', 'woo-priority'); ?></p>
                  </td>
                </tr>

                <tr>
                  <th scope="row">
                    <label for="wpp_fee_label"><?php _e('Fee Label', 'woo-priority'); ?></label>
                  </th>
                  <td>
                    <input type="text" id="wpp_fee_label" name="wpp_fee_label"
                      value="<?php echo esc_attr($fee_label); ?>" />
                    <p class="description"><?php _e('How the fee appears in cart totals and order summaries', 'woo-priority'); ?></p>
                  </td>
                </tr>
              </table>
            </div>

            <?php submit_button(__('Save Changes', 'woo-priority'), 'primary', 'submit', false); ?>
          </form>

          <div class="wpp-help-section">
            <h4>💡 <?php _e('Quick Tips', 'woo-priority'); ?></h4>
            <ul>
              <li><?php _e('Set competitive but profitable fee amounts based on your fulfillment costs', 'woo-priority'); ?></li>
              <li><?php _e('Use clear, benefit-focused language in your checkbox label', 'woo-priority'); ?></li>
              <li><?php _e('Keep descriptions concise but informative about delivery timeframes', 'woo-priority'); ?></li>
              <li><?php _e('Priority orders are automatically marked with ⚡ in your admin area', 'woo-priority'); ?></li>
            </ul>
          </div>
        </div>

        <!-- Preview Panel -->
        <div class="wpp-preview-card">
          <h3>👁️ <?php _e('Live Preview', 'woo-priority'); ?></h3>

          <div class="wpp-quick-stats">
            <div class="wpp-stat-item">
              <span class="wpp-stat-value"><?php echo esc_html($currency_symbol . $fee_amount); ?></span>
              <span class="wpp-stat-label"><?php _e('Current Fee', 'woo-priority'); ?></span>
            </div>
            <div class="wpp-stat-item">
              <span class="wpp-stat-value"><?php echo ($enabled === '1') ? '✅' : '❌'; ?></span>
              <span class="wpp-stat-label"><?php _e('Status', 'woo-priority'); ?></span>
            </div>
          </div>

          <p><strong><?php _e('How it appears at checkout:', 'woo-priority'); ?></strong></p>

          <div class="wpp-checkout-preview" id="checkout-preview">
            <h4>⚡ <span id="preview-section-title"><?php echo esc_html($section_title); ?></span></h4>
            <label>
              <input type="checkbox" disabled <?php echo ($enabled === '1') ? '' : 'style="opacity: 0.5;"'; ?> />
              <span>
                <strong id="preview-checkbox-label"><?php echo esc_html($checkbox_label); ?></strong>
                <span class="preview-price">(+<span id="preview-fee-amount"><?php echo esc_html($currency_symbol . $fee_amount); ?></span>)</span>
                <?php if ($description): ?>
                  <small class="preview-description" id="preview-description"><?php echo esc_html($description); ?></small>
                <?php endif; ?>
              </span>
            </label>
          </div>

          <div style="margin-top: 20px;">
            <h4>📊 <?php _e('Order Management', 'woo-priority'); ?></h4>
            <p style="font-size: 13px; color: #646970; line-height: 1.4;">
              <?php _e('Priority orders will be clearly marked with ⚡ lightning bolts in your order list and individual order pages for easy identification.', 'woo-priority'); ?>
            </p>
          </div>

          <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
            <h4 style="margin: 0 0 8px 0; color: #856404;">⚠️ <?php _e('Important', 'woo-priority'); ?></h4>
            <p style="margin: 0; font-size: 12px; color: #856404;">
              <?php _e('Make sure to fulfill priority orders faster than regular orders to maintain customer satisfaction and justify the additional fee.', 'woo-priority'); ?>
            </p>
          </div>
        </div>
      </div>
    </div>

    <script>
      jQuery(document).ready(function($) {
        // Live preview updates
        function updatePreview() {
          var sectionTitle = $('#wpp_section_title').val() || 'Express Options';
          var checkboxLabel = $('#wpp_checkbox_label').val() || 'Priority processing + Express shipping';
          var description = $('#wpp_description').val() || '';
          var feeAmount = $('#wpp_fee_amount').val() || '0.00';
          var enabled = $('#wpp_enabled').is(':checked');
          var currencySymbol = '<?php echo esc_js($currency_symbol); ?>';

          $('#preview-section-title').text(sectionTitle);
          $('#preview-checkbox-label').text(checkboxLabel);
          $('#preview-fee-amount').text(currencySymbol + feeAmount);

          if (description) {
            $('#preview-description').text(description).show();
          } else {
            $('#preview-description').hide();
          }

          // Update preview styling based on enabled state
          var $preview = $('#checkout-preview');
          var $checkbox = $preview.find('input[type="checkbox"]');

          if (enabled) {
            $preview.css('opacity', '1');
            $checkbox.css('opacity', '1');
          } else {
            $preview.css('opacity', '0.6');
            $checkbox.css('opacity', '0.5');
          }

          // Update status indicator
          var $status = $('.wpp-status');
          if (enabled) {
            $status.removeClass('wpp-status-disabled').addClass('wpp-status-enabled').text('Active');
          } else {
            $status.removeClass('wpp-status-enabled').addClass('wpp-status-disabled').text('Inactive');
          }

          // Update stats
          $('.wpp-stat-value').first().text(currencySymbol + feeAmount);
          $('.wpp-stat-value').last().text(enabled ? '✅' : '❌');
        }

        // Bind events for live preview
        $('#wpp_section_title, #wpp_checkbox_label, #wpp_description, #wpp_fee_amount').on('input', updatePreview);
        $('#wpp_enabled').on('change', updatePreview);

        // Initial preview update
        updatePreview();
      });
    </script>
<?php
  }

  public function admin_scripts($hook)
  {
    // Load styles on our admin page
    if ($hook === 'woocommerce_page_woo-priority-processing') {
      wp_enqueue_style('wpp-admin', WPP_PLUGIN_URL . 'assets/admin.css', [], WPP_VERSION);
      wp_enqueue_script('jquery');
    }

    // Also load on WooCommerce settings page if our tab is active
    if ($hook === 'woocommerce_page_wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'wpp_priority') {
      wp_enqueue_style('wpp-admin', WPP_PLUGIN_URL . 'assets/admin.css', [], WPP_VERSION);
      wp_enqueue_script('jquery');
    }
  }
}
