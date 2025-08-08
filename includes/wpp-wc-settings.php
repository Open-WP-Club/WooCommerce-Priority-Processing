<?php

// Only define the class if WC_Settings_Page exists
if (!class_exists('WPP_WooCommerce_Settings') && class_exists('WC_Settings_Page')) {

  class WPP_WooCommerce_Settings extends WC_Settings_Page
  {
    public function __construct()
    {
      $this->id = 'wpp_priority';
      $this->label = __('Priority Processing', 'woo-priority');

      parent::__construct();

      // Add custom output for enhanced UI
      add_action('woocommerce_settings_' . $this->id, [$this, 'output_enhanced_settings']);
      add_action('woocommerce_settings_save_' . $this->id, [$this, 'save_enhanced_settings']);
    }

    public function get_settings($current_section = '')
    {
      // Return empty array since we're using custom output
      return array();
    }

    public function output_enhanced_settings()
    {
      // Get current settings
      $enabled = get_option('wpp_enabled', 'yes');
      $fee_amount = get_option('wpp_fee_amount', '5.00');
      $section_title = get_option('wpp_section_title', 'Express Options');
      $checkbox_label = get_option('wpp_checkbox_label', 'Priority processing + Express shipping');
      $description = get_option('wpp_description', 'Your order will be processed with priority and shipped via express delivery');
      $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

      // Get currency symbol
      $currency_symbol = get_woocommerce_currency_symbol();

      // Add custom CSS and JS inline since we're in WC settings
?>
      <style>
        .woocommerce-settings .wpp-settings-container {
          max-width: 1200px;
          margin: 20px 0;
        }

        .wpp-wc-grid {
          display: grid;
          grid-template-columns: 2fr 1fr;
          gap: 30px;
          margin-top: 20px;
        }

        @media (max-width: 1024px) {
          .wpp-wc-grid {
            grid-template-columns: 1fr;
          }
        }

        .wpp-wc-card {
          background: #fff;
          border: 1px solid #ddd;
          border-radius: 8px;
          padding: 20px;
          box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .wpp-wc-section {
          margin-bottom: 25px;
          padding-bottom: 20px;
          border-bottom: 1px solid #f0f0f1;
        }

        .wpp-wc-section:last-child {
          border-bottom: none;
          margin-bottom: 0;
        }

        .wpp-wc-section h3 {
          margin: 0 0 15px 0;
          font-size: 15px;
          display: flex;
          align-items: center;
          gap: 8px;
        }

        .wpp-toggle-wrapper {
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .wpp-toggle {
          position: relative;
          display: inline-block;
          width: 50px;
          height: 24px;
        }

        .wpp-toggle input {
          opacity: 0;
          width: 0;
          height: 0;
        }

        .wpp-toggle-slider {
          position: absolute;
          cursor: pointer;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background-color: #ccc;
          transition: 0.3s;
          border-radius: 24px;
        }

        .wpp-toggle-slider:before {
          position: absolute;
          content: "";
          height: 18px;
          width: 18px;
          left: 3px;
          bottom: 3px;
          background-color: white;
          transition: 0.3s;
          border-radius: 50%;
        }

        .wpp-toggle input:checked+.wpp-toggle-slider {
          background-color: #2271b1;
        }

        .wpp-toggle input:checked+.wpp-toggle-slider:before {
          transform: translateX(26px);
        }

        .wpp-status-enabled {
          color: #00a32a;
        }

        .wpp-status-disabled {
          color: #d63638;
        }

        .wpp-currency-wrapper {
          display: flex;
          align-items: center;
          gap: 8px;
        }

        .wpp-currency-symbol {
          font-weight: 600;
          color: #2271b1;
          font-size: 16px;
        }

        .wpp-field-row {
          margin-bottom: 20px;
        }

        .wpp-field-label {
          display: block;
          font-weight: 600;
          margin-bottom: 5px;
          color: #1d2327;
        }

        .wpp-field-input {
          width: 100%;
          max-width: 400px;
          padding: 8px 12px;
          border: 1px solid #8c8f94;
          border-radius: 4px;
        }

        .wpp-field-description {
          font-size: 13px;
          color: #646970;
          margin-top: 5px;
        }

        .wpp-checkout-preview {
          background: #f8f9fa;
          border: 1px solid #dee2e6;
          border-radius: 6px;
          padding: 15px;
          margin-top: 15px;
        }

        .wpp-checkout-preview h4 {
          margin: 0 0 10px 0;
          color: #495057;
          font-size: 14px;
        }

        .wpp-checkout-preview label {
          display: flex;
          align-items: flex-start;
          cursor: pointer;
          font-size: 13px;
          gap: 8px;
        }

        .wpp-checkout-preview .preview-price {
          color: #dc3545;
          font-weight: 600;
        }

        .wpp-checkout-preview .preview-description {
          color: #6c757d;
          display: block;
          margin-top: 4px;
          line-height: 1.4;
          font-size: 12px;
        }

        .wpp-help-box {
          background: #f0f6fc;
          border: 1px solid #c6e2ff;
          border-radius: 6px;
          padding: 15px;
          margin-top: 20px;
        }

        .wpp-help-box h4 {
          margin: 0 0 10px 0;
          color: #0073aa;
          font-size: 14px;
        }

        .wpp-help-box ul {
          margin: 0;
          padding-left: 20px;
          font-size: 13px;
        }

        .wpp-help-box li {
          margin-bottom: 5px;
        }
      </style>

      <div class="wpp-settings-container">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
          <span style="font-size: 24px;">⚡</span>
          <h2 style="margin: 0;"><?php _e('Priority Processing Configuration', 'woo-priority'); ?></h2>
        </div>

        <form method="post" action="">
          <?php wp_nonce_field('wpp_save_settings', 'wpp_nonce'); ?>

          <div class="wpp-wc-grid">
            <!-- Settings Panel -->
            <div class="wpp-wc-card">
              <div class="wpp-wc-section">
                <h3>🔧 <?php _e('Basic Settings', 'woo-priority'); ?></h3>

                <div class="wpp-field-row">
                  <label class="wpp-field-label"><?php _e('Enable Priority Processing', 'woo-priority'); ?></label>
                  <div class="wpp-toggle-wrapper">
                    <label class="wpp-toggle">
                      <input type="checkbox" name="wpp_enabled" value="yes" <?php checked($enabled, 'yes'); ?> />
                      <span class="wpp-toggle-slider"></span>
                    </label>
                    <span class="wpp-status <?php echo ($enabled === 'yes') ? 'wpp-status-enabled' : 'wpp-status-disabled'; ?>">
                      <?php echo ($enabled === 'yes') ? __('Active', 'woo-priority') : __('Inactive', 'woo-priority'); ?>
                    </span>
                  </div>
                  <p class="wpp-field-description"><?php _e('Enable or disable the priority processing option at checkout', 'woo-priority'); ?></p>
                </div>

                <div class="wpp-field-row">
                  <label class="wpp-field-label" for="wpp_fee_amount"><?php _e('Additional Fee', 'woo-priority'); ?></label>
                  <div class="wpp-currency-wrapper">
                    <span class="wpp-currency-symbol"><?php echo esc_html($currency_symbol); ?></span>
                    <input type="number" step="0.01" min="0" id="wpp_fee_amount" name="wpp_fee_amount"
                      class="wpp-field-input" value="<?php echo esc_attr($fee_amount); ?>" style="max-width: 150px;" />
                  </div>
                  <p class="wpp-field-description"><?php _e('Amount to charge for priority processing and express shipping', 'woo-priority'); ?></p>
                </div>
              </div>

              <div class="wpp-wc-section">
                <h3>🎨 <?php _e('Display Settings', 'woo-priority'); ?></h3>

                <div class="wpp-field-row">
                  <label class="wpp-field-label" for="wpp_section_title"><?php _e('Section Title', 'woo-priority'); ?></label>
                  <input type="text" id="wpp_section_title" name="wpp_section_title"
                    class="wpp-field-input" value="<?php echo esc_attr($section_title); ?>" />
                  <p class="wpp-field-description"><?php _e('Heading shown above the priority processing option', 'woo-priority'); ?></p>
                </div>

                <div class="wpp-field-row">
                  <label class="wpp-field-label" for="wpp_checkbox_label"><?php _e('Checkbox Label', 'woo-priority'); ?></label>
                  <input type="text" id="wpp_checkbox_label" name="wpp_checkbox_label"
                    class="wpp-field-input" value="<?php echo esc_attr($checkbox_label); ?>" />
                  <p class="wpp-field-description"><?php _e('Text displayed next to the checkbox option', 'woo-priority'); ?></p>
                </div>

                <div class="wpp-field-row">
                  <label class="wpp-field-label" for="wpp_description"><?php _e('Help Text', 'woo-priority'); ?></label>
                  <textarea id="wpp_description" name="wpp_description" class="wpp-field-input"
                    rows="3"><?php echo esc_textarea($description); ?></textarea>
                  <p class="wpp-field-description"><?php _e('Additional explanation shown below the checkbox', 'woo-priority'); ?></p>
                </div>

                <div class="wpp-field-row">
                  <label class="wpp-field-label" for="wpp_fee_label"><?php _e('Fee Label', 'woo-priority'); ?></label>
                  <input type="text" id="wpp_fee_label" name="wpp_fee_label"
                    class="wpp-field-input" value="<?php echo esc_attr($fee_label); ?>" />
                  <p class="wpp-field-description"><?php _e('How the fee appears in cart totals and order summaries', 'woo-priority'); ?></p>
                </div>
              </div>

              <div style="margin-top: 20px;">
                <?php submit_button(__('Save Settings', 'woo-priority')); ?>
              </div>
            </div>

            <!-- Preview Panel -->
            <div class="wpp-wc-card">
              <h3>👁️ <?php _e('Live Preview', 'woo-priority'); ?></h3>

              <p><strong><?php _e('How it appears at checkout:', 'woo-priority'); ?></strong></p>

              <div class="wpp-checkout-preview" id="wc-checkout-preview">
                <h4>⚡ <span id="wc-preview-section-title"><?php echo esc_html($section_title); ?></span></h4>
                <label>
                  <input type="checkbox" disabled />
                  <span>
                    <strong id="wc-preview-checkbox-label"><?php echo esc_html($checkbox_label); ?></strong>
                    <span class="preview-price">(+<span id="wc-preview-fee-amount"><?php echo esc_html($currency_symbol . $fee_amount); ?></span>)</span>
                    <?php if ($description): ?>
                      <small class="preview-description" id="wc-preview-description"><?php echo esc_html($description); ?></small>
                    <?php endif; ?>
                  </span>
                </label>
              </div>

              <div class="wpp-help-box">
                <h4>💡 <?php _e('Tips for Success', 'woo-priority'); ?></h4>
                <ul>
                  <li><?php _e('Set fees that cover your expedited processing costs', 'woo-priority'); ?></li>
                  <li><?php _e('Use action-oriented language that highlights benefits', 'woo-priority'); ?></li>
                  <li><?php _e('Priority orders appear with ⚡ in your order admin', 'woo-priority'); ?></li>
                  <li><?php _e('Consider offering guaranteed delivery timeframes', 'woo-priority'); ?></li>
                </ul>
              </div>
            </div>
          </div>
        </form>
      </div>

      <script>
        jQuery(document).ready(function($) {
          function updateWCPreview() {
            var sectionTitle = $('#wpp_section_title').val() || 'Express Options';
            var checkboxLabel = $('#wpp_checkbox_label').val() || 'Priority processing + Express shipping';
            var description = $('#wpp_description').val() || '';
            var feeAmount = $('#wpp_fee_amount').val() || '0.00';
            var enabled = $('input[name="wpp_enabled"]').is(':checked');
            var currencySymbol = '<?php echo esc_js($currency_symbol); ?>';

            $('#wc-preview-section-title').text(sectionTitle);
            $('#wc-preview-checkbox-label').text(checkboxLabel);
            $('#wc-preview-fee-amount').text(currencySymbol + feeAmount);

            if (description) {
              $('#wc-preview-description').text(description).show();
            } else {
              $('#wc-preview-description').hide();
            }

            // Update preview styling
            var $preview = $('#wc-checkout-preview');
            if (enabled) {
              $preview.css('opacity', '1');
            } else {
              $preview.css('opacity', '0.6');
            }

            // Update status
            var $status = $('.wpp-status');
            if (enabled) {
              $status.removeClass('wpp-status-disabled').addClass('wpp-status-enabled').text('Active');
            } else {
              $status.removeClass('wpp-status-enabled').addClass('wpp-status-disabled').text('Inactive');
            }
          }

          $('#wpp_section_title, #wpp_checkbox_label, #wpp_description, #wpp_fee_amount').on('input', updateWCPreview);
          $('input[name="wpp_enabled"]').on('change', updateWCPreview);

          updateWCPreview();
        });
      </script>
<?php
    }

    public function save_enhanced_settings()
    {
      if (isset($_POST['wpp_nonce']) && wp_verify_nonce($_POST['wpp_nonce'], 'wpp_save_settings')) {
        update_option('wpp_enabled', isset($_POST['wpp_enabled']) ? 'yes' : 'no');
        update_option('wpp_fee_amount', sanitize_text_field($_POST['wpp_fee_amount'] ?? '5.00'));
        update_option('wpp_section_title', sanitize_text_field($_POST['wpp_section_title'] ?? 'Express Options'));
        update_option('wpp_checkbox_label', sanitize_text_field($_POST['wpp_checkbox_label'] ?? 'Priority processing + Express shipping'));
        update_option('wpp_description', sanitize_textarea_field($_POST['wpp_description'] ?? ''));
        update_option('wpp_fee_label', sanitize_text_field($_POST['wpp_fee_label'] ?? 'Priority Processing & Express Shipping'));

        WC_Admin_Settings::add_message(__('Priority Processing settings saved successfully!', 'woo-priority'));
      }
    }

    public function save()
    {
      // This method is required by WC_Settings_Page but we handle saving in save_enhanced_settings
      $this->save_enhanced_settings();
    }
  }
}
