<?php

class WPP_Admin
{
  public function __construct()
  {
    // Always add the admin menu as primary approach
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_init', [$this, 'register_settings']);
    add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

    // Add AJAX handler for clearing all user sessions
    add_action('wp_ajax_wpp_clear_all_sessions', [$this, 'ajax_clear_all_sessions']);

    // Try WooCommerce integration as secondary approach
    add_action('woocommerce_loaded', [$this, 'try_woocommerce_integration']);
  }

  public function try_woocommerce_integration()
  {
    // Only try if WC_Settings_Page exists and we haven't already integrated
    if (class_exists('WC_Settings_Page') && !get_transient('wpp_wc_integration_attempted')) {
      add_filter('woocommerce_get_settings_pages', [$this, 'add_woocommerce_settings_page']);
      set_transient('wpp_wc_integration_attempted', true, HOUR_IN_SECONDS);
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

  /**
   * AJAX handler to clear all users' priority processing sessions
   */
  public function ajax_clear_all_sessions()
  {
    // Security checks
    if (!current_user_can('manage_woocommerce')) {
      wp_send_json_error('Insufficient permissions');
      return;
    }

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpp_clear_all_nonce')) {
      wp_send_json_error('Invalid nonce');
      return;
    }

    try {
      $cleared_count = $this->clear_all_priority_sessions();

      wp_send_json_success([
        'message' => sprintf(__('Successfully cleared priority processing for %d users', 'woo-priority'), $cleared_count),
        'cleared_count' => $cleared_count
      ]);
    } catch (Exception $e) {
      wp_send_json_error('Error clearing sessions: ' . $e->getMessage());
    }
  }

  /**
   * Clear all users' priority processing sessions
   */
  private function clear_all_priority_sessions()
  {
    global $wpdb;
    $cleared_count = 0;

    // 1. Clear from user meta (logged-in users)
    $user_meta_cleared = $wpdb->query($wpdb->prepare(
      "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
      '_wpp_priority_processing'
    ));
    if ($user_meta_cleared !== false) {
      $cleared_count += $user_meta_cleared;
    }

    // 2. Clear from WooCommerce sessions table (if it exists)
    $sessions_table = $wpdb->prefix . 'woocommerce_sessions';
    if ($wpdb->get_var("SHOW TABLES LIKE '$sessions_table'") == $sessions_table) {
      // Get all sessions that have priority processing
      $sessions_with_priority = $wpdb->get_results($wpdb->prepare(
        "SELECT session_key, session_value FROM {$sessions_table} WHERE session_value LIKE %s",
        '%priority_processing%'
      ));

      foreach ($sessions_with_priority as $session) {
        $session_data = maybe_unserialize($session->session_value);

        if (is_array($session_data) && isset($session_data['priority_processing'])) {
          // Remove priority processing from session data
          unset($session_data['priority_processing']);
          unset($session_data['wpp_last_state']);

          // Update the session
          $updated_session = maybe_serialize($session_data);
          $wpdb->update(
            $sessions_table,
            ['session_value' => $updated_session],
            ['session_key' => $session->session_key],
            ['%s'],
            ['%s']
          );
          $cleared_count++;
        }
      }
    }

    // 3. Clear from WordPress options (if sessions stored there)
    $transients_cleared = $wpdb->query(
      "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_sess_%' AND option_value LIKE '%priority_processing%'"
    );
    if ($transients_cleared !== false) {
      $cleared_count += $transients_cleared;
    }

    // 4. Clear any persistent cache
    if (function_exists('wp_cache_flush_group')) {
      wp_cache_flush_group('woocommerce-sessions');
    }

    return $cleared_count;
  }

  /**
   * Get count of users with active priority processing
   */
  private function get_active_priority_count()
  {
    global $wpdb;
    $count = 0;

    // Count from user meta
    $user_meta_count = $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != %s",
      '_wpp_priority_processing',
      ''
    ));
    $count += intval($user_meta_count);

    // Count from WooCommerce sessions table
    $sessions_table = $wpdb->prefix . 'woocommerce_sessions';
    if ($wpdb->get_var("SHOW TABLES LIKE '$sessions_table'") == $sessions_table) {
      $sessions_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$sessions_table} WHERE session_value LIKE %s",
        '%priority_processing%'
      ));
      $count += intval($sessions_count);
    }

    return $count;
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

    // Get active priority count
    $active_count = $this->get_active_priority_count();
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
          <h2><?php _e('Configuration', 'woo-priority'); ?></h2>

          <form method="post" action="options.php" id="wpp-settings-form">
            <?php settings_fields('wpp_settings'); ?>

            <div class="wpp-feature-section">
              <div class="wpp-feature-title"><?php _e('Basic Settings', 'woo-priority'); ?></div>

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
                    <input type="number" step="0.01" min="0" id="wpp_fee_amount" name="wpp_fee_amount"
                      value="<?php echo esc_attr($fee_amount); ?>" />
                    <p class="description"><?php _e('Amount to charge for priority processing and express shipping', 'woo-priority'); ?></p>
                  </td>
                </tr>
              </table>
            </div>

            <div class="wpp-feature-section">
              <div class="wpp-feature-title"><?php _e('Display Settings', 'woo-priority'); ?></div>

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

            <!-- Session Management Section -->
            <div class="wpp-feature-section">
              <div class="wpp-feature-title"><?php _e('Session Management', 'woo-priority'); ?></div>

              <table class="form-table">
                <tr>
                  <th scope="row"><?php _e('Active Sessions', 'woo-priority'); ?></th>
                  <td>
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                      <span class="wpp-stat-value" style="font-size: 24px; font-weight: 700; color: #2271b1;">
                        <?php echo $active_count; ?>
                      </span>
                      <span><?php _e('users currently have priority processing active', 'woo-priority'); ?></span>
                    </div>

                    <button type="button" id="wpp-clear-all-sessions" class="button button-secondary"
                      style="background: #dc3545; color: white; border-color: #dc3545;">
                      🗑️ <?php _e('Clear All Priority Sessions', 'woo-priority'); ?>
                    </button>

                    <p class="description" style="margin-top: 8px;">
                      <?php _e('This will remove priority processing from all user sessions. Useful for debugging or if you need to reset all active priority selections.', 'woo-priority'); ?>
                    </p>

                    <div id="wpp-clear-result" style="margin-top: 10px;"></div>
                  </td>
                </tr>
              </table>
            </div>

            <?php submit_button(__('Save Changes', 'woo-priority'), 'primary', 'submit', false); ?>
          </form>
        </div>

        <!-- Preview Panel -->
        <div class="wpp-preview-card">
          <h3><?php _e('Live Preview', 'woo-priority'); ?></h3>

          <div class="wpp-quick-stats">
            <div class="wpp-stat-item">
              <span class="wpp-stat-value"><?php echo esc_html($fee_amount); ?></span>
              <span class="wpp-stat-label"><?php _e('Current Fee', 'woo-priority'); ?></span>
            </div>
            <div class="wpp-stat-item">
              <span class="wpp-stat-value"><?php echo ($enabled === '1') ? '✅' : '❌'; ?></span>
              <span class="wpp-stat-label"><?php _e('Status', 'woo-priority'); ?></span>
            </div>
            <div class="wpp-stat-item">
              <span class="wpp-stat-value"><?php echo $active_count; ?></span>
              <span class="wpp-stat-label"><?php _e('Active Sessions', 'woo-priority'); ?></span>
            </div>
          </div>

          <p><strong><?php _e('How it appears at checkout:', 'woo-priority'); ?></strong></p>

          <div class="wpp-checkout-preview" id="checkout-preview">
            <h4>⚡ <span id="preview-section-title"><?php echo esc_html($section_title); ?></span></h4>
            <label>
              <input type="checkbox" disabled <?php echo ($enabled === '1') ? '' : 'style="opacity: 0.5;"'; ?> />
              <span>
                <strong id="preview-checkbox-label"><?php echo esc_html($checkbox_label); ?></strong>
                <span class="preview-price">(+<span id="preview-fee-amount"><?php echo esc_html($fee_amount); ?></span>)</span>
                <?php if ($description): ?>
                  <small class="preview-description" id="preview-description"><?php echo esc_html($description); ?></small>
                <?php endif; ?>
              </span>
            </label>
          </div>

          <div style="margin-top: 20px;">
            <h4><?php _e('Order Management', 'woo-priority'); ?></h4>
            <p style="font-size: 13px; color: #646970; line-height: 1.4;">
              <?php _e('Priority orders will be clearly marked with ⚡ lightning bolts in your order list and individual order pages for easy identification.', 'woo-priority'); ?>
            </p>
          </div>

          <div class="wpp-help-section">
            <h4>💡 <?php _e('Quick Tips', 'woo-priority'); ?></h4>
            <ul>
              <li><?php _e('Set competitive but profitable fee amounts based on your fulfillment costs', 'woo-priority'); ?></li>
              <li><?php _e('Use clear, benefit-focused language in your checkbox label', 'woo-priority'); ?></li>
              <li><?php _e('Keep descriptions concise but informative about delivery timeframes', 'woo-priority'); ?></li>
              <li><?php _e('Priority orders are automatically marked with ⚡ in your admin area', 'woo-priority'); ?></li>
            </ul>
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

          $('#preview-section-title').text(sectionTitle);
          $('#preview-checkbox-label').text(checkboxLabel);
          $('#preview-fee-amount').text(feeAmount);

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
          $('.wpp-stat-value').first().text(feeAmount);
          $('.wpp-stat-value').eq(1).text(enabled ? '✅' : '❌');
        }

        // Clear all sessions handler
        $('#wpp-clear-all-sessions').on('click', function() {
          var $button = $(this);
          var $result = $('#wpp-clear-result');

          if (!confirm('<?php echo esc_js(__('Are you sure you want to clear all priority processing sessions? This action cannot be undone.', 'woo-priority')); ?>')) {
            return;
          }

          $button.prop('disabled', true).text('<?php echo esc_js(__('Clearing...', 'woo-priority')); ?>');
          $result.empty();

          $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
              action: 'wpp_clear_all_sessions',
              nonce: '<?php echo wp_create_nonce('wpp_clear_all_nonce'); ?>'
            },
            success: function(response) {
              if (response.success) {
                $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                // Update the active count in stats
                $('.wpp-stat-value').last().text('0');
                // Refresh the page after 2 seconds to update counts
                setTimeout(function() {
                  window.location.reload();
                }, 2000);
              } else {
                $result.html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
              }
            },
            error: function() {
              $result.html('<div class="notice notice-error inline"><p><?php echo esc_js(__('Connection error. Please try again.', 'woo-priority')); ?></p></div>');
            },
            complete: function() {
              $button.prop('disabled', false).html('🗑️ <?php echo esc_js(__('Clear All Priority Sessions', 'woo-priority')); ?>');
            }
          });
        });

        // Bind events for live preview
        $('#wpp_section_title, #wpp_checkbox_label, #wpp_description, #wpp_fee_amount').on('input', updatePreview);
        $('#wpp_enabled').on('change', updatePreview);

        // Force immediate update on page load
        setTimeout(function() {
          updatePreview();
          var currentFee = $('#wpp_fee_amount').val() || '0.00';
          $('.wpp-stat-value').first().text(currentFee);
          $('#preview-fee-amount').text(currentFee);
        }, 100);

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
