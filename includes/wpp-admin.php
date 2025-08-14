<?php

class WPP_Admin
{
  private $statistics;

  public function __construct()
  {
    // Always add the admin menu as primary approach
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_init', [$this, 'register_settings']);
    add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

    // Initialize statistics handler
    $this->statistics = new WPP_Statistics();

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
    register_setting('wpp_settings', 'wpp_allowed_user_roles', [
      'sanitize_callback' => [$this, 'sanitize_user_roles']
    ]);
    register_setting('wpp_settings', 'wpp_allow_guests');
  }

  /**
   * Sanitize user roles to ensure they're saved as an array
   */
  public function sanitize_user_roles($roles)
  {
    if (empty($roles)) {
      return ['customer']; // Default fallback
    }

    // Ensure it's an array
    if (!is_array($roles)) {
      $roles = [$roles];
    }

    // Sanitize each role
    $sanitized_roles = [];
    foreach ($roles as $role) {
      $clean_role = sanitize_text_field($role);
      if (!empty($clean_role)) {
        $sanitized_roles[] = $clean_role;
      }
    }

    // Return array or default if empty
    return !empty($sanitized_roles) ? $sanitized_roles : ['customer'];
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

    // Get statistics from the statistics handler
    $stats = $this->statistics->get_statistics();
    $cache_info = $this->statistics->get_cache_info();
?>
    <div class="wrap wpp-admin-container">
      <h1><?php _e('Priority Processing Settings', 'woo-priority'); ?></h1>

      <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
          <p><strong><?php _e('Settings saved successfully!', 'woo-priority'); ?></strong> <?php _e('Your priority processing options are now active.', 'woo-priority'); ?></p>
        </div>
      <?php endif; ?>

      <!-- Statistics Section -->
      <div class="wpp-statistics-section">
        <div class="wpp-statistics-header">
          <h2><?php _e('📊 Priority Processing Statistics', 'woo-priority'); ?></h2>
          <div class="wpp-stats-controls">
            <span class="wpp-cache-info">
              <?php if ($cache_info['is_cached']): ?>
                <small><?php printf(__('Cached for %d hours', 'woo-priority'), $cache_info['cache_duration_hours']); ?></small>
              <?php else: ?>
                <small><?php _e('Live data', 'woo-priority'); ?></small>
              <?php endif; ?>
            </span>
            <button type="button" id="wpp-refresh-stats" class="button button-secondary">
              <span class="dashicons dashicons-update"></span>
              <?php _e('Refresh Stats', 'woo-priority'); ?>
            </button>
          </div>
        </div>

        <div class="wpp-statistics-grid" id="wpp-statistics-container">
          <div class="wpp-stat-card">
            <div class="wpp-stat-icon">⚡</div>
            <div class="wpp-stat-content">
              <div class="wpp-stat-value" id="stat-total-orders"><?php echo number_format($stats['total_priority_orders']); ?></div>
              <div class="wpp-stat-label"><?php _e('Total Priority Orders', 'woo-priority'); ?></div>
            </div>
          </div>

          <div class="wpp-stat-card">
            <div class="wpp-stat-icon">💰</div>
            <div class="wpp-stat-content">
              <div class="wpp-stat-value" id="stat-total-revenue"><?php echo wc_price($stats['total_priority_revenue']); ?></div>
              <div class="wpp-stat-label"><?php _e('Total Priority Revenue', 'woo-priority'); ?></div>
            </div>
          </div>

          <div class="wpp-stat-card">
            <div class="wpp-stat-icon">📈</div>
            <div class="wpp-stat-content">
              <div class="wpp-stat-value" id="stat-percentage"><?php echo $stats['priority_percentage']; ?>%</div>
              <div class="wpp-stat-label"><?php _e('Priority Rate', 'woo-priority'); ?></div>
            </div>
          </div>

          <div class="wpp-stat-card">
            <div class="wpp-stat-icon">💵</div>
            <div class="wpp-stat-content">
              <div class="wpp-stat-value" id="stat-avg-fee"><?php echo wc_price($stats['average_priority_fee']); ?></div>
              <div class="wpp-stat-label"><?php _e('Average Fee', 'woo-priority'); ?></div>
            </div>
          </div>

          <div class="wpp-stat-card">
            <div class="wpp-stat-icon">📅</div>
            <div class="wpp-stat-content">
              <div class="wpp-stat-value" id="stat-today"><?php echo number_format($stats['today_priority_orders']); ?></div>
              <div class="wpp-stat-label"><?php _e('Today', 'woo-priority'); ?></div>
            </div>
          </div>

          <div class="wpp-stat-card">
            <div class="wpp-stat-icon">📊</div>
            <div class="wpp-stat-content">
              <div class="wpp-stat-value" id="stat-this-week"><?php echo number_format($stats['this_week_priority_orders']); ?></div>
              <div class="wpp-stat-label"><?php _e('This Week', 'woo-priority'); ?></div>
            </div>
          </div>

          <div class="wpp-stat-card">
            <div class="wpp-stat-icon">📆</div>
            <div class="wpp-stat-content">
              <div class="wpp-stat-value" id="stat-this-month"><?php echo number_format($stats['this_month_priority_orders']); ?></div>
              <div class="wpp-stat-label"><?php _e('This Month', 'woo-priority'); ?></div>
            </div>
          </div>

          <div class="wpp-stat-card">
            <div class="wpp-stat-icon">🔄</div>
            <div class="wpp-stat-content">
              <div class="wpp-stat-value" id="stat-last-updated"><?php echo esc_html(gmdate('H:i', strtotime($stats['last_updated']))); ?></div>
              <div class="wpp-stat-label"><?php _e('Last Updated', 'woo-priority'); ?></div>
            </div>
          </div>
        </div>

        <div class="wpp-statistics-note">
          <p><strong><?php _e('Note:', 'woo-priority'); ?></strong> <?php _e('Statistics are cached for 24 hours to improve performance. Click "Refresh Stats" to get the latest data.', 'woo-priority'); ?></p>
        </div>
      </div>

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
              <div class="wpp-feature-title"><?php _e('User Permissions', 'woo-priority'); ?></div>

              <table class="form-table">
                <tr>
                  <th scope="row">
                    <label for="wpp_allowed_user_roles"><?php _e('Allowed User Roles', 'woo-priority'); ?></label>
                  </th>
                  <td>
                    <?php
                    $allowed_roles = WPP_Permissions::get_allowed_user_roles();
                    $available_roles = WPP_Permissions::get_available_user_roles();
                    ?>
                    <div class="wpp-roles-container" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #fafafa;">
                      <?php foreach ($available_roles as $role_key => $role_name): ?>
                        <label style="display: block; margin: 5px 0; cursor: pointer;">
                          <input type="checkbox" name="wpp_allowed_user_roles[]" value="<?php echo esc_attr($role_key); ?>"
                            <?php checked(in_array($role_key, $allowed_roles)); ?>
                            style="margin-right: 8px;" />
                          <strong><?php echo esc_html($role_name); ?></strong>
                          <small style="color: #666;"> (<?php echo esc_html($role_key); ?>)</small>
                        </label>
                      <?php endforeach; ?>
                    </div>
                    <p class="description">
                      <?php _e('Select which user roles can access the priority processing option. ', 'woo-priority'); ?>
                      <strong><?php _e('Shop Managers and Administrators always have access.', 'woo-priority'); ?></strong>
                    </p>
                  </td>
                </tr>

                <tr>
                  <th scope="row">
                    <label for="wpp_allow_guests"><?php _e('Guest Access', 'woo-priority'); ?></label>
                  </th>
                  <td>
                    <div class="wpp-toggle-wrapper">
                      <label class="wpp-toggle">
                        <input type="checkbox" id="wpp_allow_guests" name="wpp_allow_guests" value="1"
                          <?php checked(get_option('wpp_allow_guests', '1'), '1'); ?> />
                        <span class="wpp-toggle-slider"></span>
                      </label>
                      <span class="wpp-guest-status">
                        <?php echo (get_option('wpp_allow_guests', '1') === '1') ? __('Allowed', 'woo-priority') : __('Denied', 'woo-priority'); ?>
                      </span>
                    </div>
                    <p class="description"><?php _e('Allow non-logged-in users (guests) to access priority processing option', 'woo-priority'); ?></p>
                  </td>
                </tr>

                <tr>
                  <th scope="row">
                    <?php _e('Current Access', 'woo-priority'); ?>
                  </th>
                  <td>
                    <div class="wpp-permission-summary" id="wpp-permission-summary">
                      <?php
                      $summary = WPP_Permissions::get_permission_summary();
                      if (!empty($summary)):
                      ?>
                        <div style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 10px; border-radius: 4px;">
                          <strong><?php _e('Priority processing is available to:', 'woo-priority'); ?></strong>
                          <ul style="margin: 5px 0 0 20px;">
                            <?php foreach ($summary as $role): ?>
                              <li><?php echo esc_html($role); ?></li>
                            <?php endforeach; ?>
                          </ul>
                        </div>
                      <?php else: ?>
                        <div style="background: #fff2e5; border: 1px solid #ffcc99; padding: 10px; border-radius: 4px;">
                          <strong><?php _e('⚠️ No users currently have access to priority processing', 'woo-priority'); ?></strong>
                        </div>
                      <?php endif; ?>
                    </div>
                    <p class="description"><?php _e('This shows who can currently see and use the priority processing option', 'woo-priority'); ?></p>
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
            <h4><?php _e('Permission Summary', 'woo-priority'); ?></h4>
            <div id="preview-permission-summary" style="font-size: 12px; color: #646970;">
              <?php
              $summary = WPP_Permissions::get_permission_summary();
              if (!empty($summary)) {
                echo '<strong>Available to:</strong> ' . implode(', ', $summary);
              } else {
                echo '<strong>⚠️ No access granted</strong>';
              }
              ?>
            </div>
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
              <li><?php _e('Control access with user roles and guest permissions for security', 'woo-priority'); ?></li>
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

          // Update stats - only show numbers
          $('.wpp-stat-value').first().text(feeAmount);
          $('.wpp-stat-value').last().text(enabled ? '✅' : '❌');
        }

        // Permission settings handlers
        function updatePermissionSummary() {
          var selectedRoles = [];
          var allowGuests = $('#wpp_allow_guests').is(':checked');

          // Always include shop managers
          selectedRoles.push('Shop Managers');

          // Get selected user roles
          $('input[name="wpp_allowed_user_roles[]"]:checked').each(function() {
            var roleLabel = $(this).parent().find('strong').text();
            selectedRoles.push(roleLabel);
          });

          // Add guests if allowed
          if (allowGuests) {
            selectedRoles.push('Guest Users');
          }

          // Update summary display
          var $summary = $('#wpp-permission-summary');
          if (selectedRoles.length > 1) { // More than just shop managers
            var summaryHtml = '<div style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 10px; border-radius: 4px;">' +
              '<strong>Priority processing is available to:</strong>' +
              '<ul style="margin: 5px 0 0 20px;">';

            selectedRoles.forEach(function(role) {
              summaryHtml += '<li>' + role + '</li>';
            });

            summaryHtml += '</ul></div>';
            $summary.html(summaryHtml);
          } else {
            $summary.html('<div style="background: #fff2e5; border: 1px solid #ffcc99; padding: 10px; border-radius: 4px;">' +
              '<strong>⚠️ Only Shop Managers currently have access to priority processing</strong></div>');
          }

          // Update guest status text
          $('.wpp-guest-status').text(allowGuests ? 'Allowed' : 'Denied');

          // Update preview permission summary
          $('#preview-permission-summary').html(
            selectedRoles.length > 1 ?
            '<strong>Available to:</strong> ' + selectedRoles.join(', ') :
            '<strong>⚠️ No access granted</strong>'
          );
        }

        // Bind permission change events
        $('input[name="wpp_allowed_user_roles[]"], #wpp_allow_guests').on('change', function() {
          updatePermissionSummary();
          updatePreview(); // Also update main preview
        });

        // Bind events for live preview
        $('#wpp_section_title, #wpp_checkbox_label, #wpp_description, #wpp_fee_amount').on('input', updatePreview);
        $('#wpp_enabled').on('change', updatePreview);

        // Force immediate update on page load to clean any cached content
        setTimeout(function() {
          updatePreview();
          updatePermissionSummary();

          // Force clean the stat value specifically
          var currentFee = $('#wpp_fee_amount').val() || '0.00';
          $('.wpp-stat-value').first().text(currentFee);

          // Clean any HTML entities that might be lingering
          $('#preview-fee-amount').text(currentFee);

          // Extra cleanup - remove any HTML entities from all text elements
          $('[id*="preview-"]').each(function() {
            var $el = $(this);
            var text = $el.text();
            if (text.indexOf('&#') !== -1) {
              // If it contains HTML entities, clean it based on element type
              if ($el.attr('id') === 'preview-fee-amount') {
                $el.text(currentFee);
              } else {
                $el.text(text.replace(/&#\d+;/g, ''));
              }
            }
          });
        }, 100);

        // Initial preview update
        updatePreview();
        updatePermissionSummary();

        // Statistics refresh handler
        $('#wpp-refresh-stats').on('click', function(e) {
          e.preventDefault();

          var $button = $(this);
          var originalText = $button.html();

          // Show loading state
          $button.prop('disabled', true);
          $button.html('<span class="dashicons dashicons-update spin"></span> ' + wpp_admin_ajax.refreshing_text);

          // Add loading class to statistics container
          $('#wpp-statistics-container').addClass('wpp-loading');

          $.ajax({
            url: wpp_admin_ajax.ajax_url,
            type: 'POST',
            data: {
              action: 'wpp_refresh_stats',
              nonce: wpp_admin_ajax.nonce
            },
            success: function(response) {
              if (response.success && response.data.formatted) {
                // Update all statistics with formatted values
                $('#stat-total-orders').text(response.data.formatted.total_priority_orders);
                $('#stat-total-revenue').html(response.data.formatted.total_priority_revenue);
                $('#stat-percentage').text(response.data.formatted.priority_percentage);
                $('#stat-avg-fee').html(response.data.formatted.average_priority_fee);
                $('#stat-today').text(response.data.formatted.today_priority_orders);
                $('#stat-this-week').text(response.data.formatted.this_week_priority_orders);
                $('#stat-this-month').text(response.data.formatted.this_month_priority_orders);

                // Update last updated time
                var now = new Date();
                var timeString = now.getHours().toString().padStart(2, '0') + ':' +
                  now.getMinutes().toString().padStart(2, '0');
                $('#stat-last-updated').text(timeString);

                // Show success feedback
                $('#wpp-statistics-container').addClass('wpp-updated');
                setTimeout(function() {
                  $('#wpp-statistics-container').removeClass('wpp-updated');
                }, 2000);

                // Show success message if provided
                if (response.data.message) {
                  console.log('WPP: ' + response.data.message);
                }
              } else {
                alert('Failed to refresh statistics. Please try again.');
              }
            },
            error: function(xhr, status, error) {
              console.error('Statistics refresh error:', error);
              alert('Error refreshing statistics: ' + error);
            },
            complete: function() {
              // Reset button state
              $button.prop('disabled', false);
              $button.html(originalText);
              $('#wpp-statistics-container').removeClass('wpp-loading');
            }
          });
        });
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

      // Localize script for AJAX
      wp_localize_script('jquery', 'wpp_admin_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpp_admin_nonce'),
        'refreshing_text' => __('Refreshing...', 'woo-priority'),
        'refresh_text' => __('Refresh Stats', 'woo-priority')
      ]);
    }

    // Also load on WooCommerce settings page if our tab is active
    if ($hook === 'woocommerce_page_wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'wpp_priority') {
      wp_enqueue_style('wpp-admin', WPP_PLUGIN_URL . 'assets/admin.css', [], WPP_VERSION);
      wp_enqueue_script('jquery');
    }
  }

  /**
   * Get the statistics handler instance
   */
  public function get_statistics_handler()
  {
    return $this->statistics;
  }
}
