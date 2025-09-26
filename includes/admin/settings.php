<?php

/**
 * Admin Settings Handler
 * Manages settings registration, validation, and form processing
 */
class Admin_Settings
{
  public function __construct()
  {
    add_action('admin_init', [$this, 'register_settings']);
  }

  /**
   * Register all plugin settings
   */
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

  /**
   * Get current settings values
   */
  public function get_settings()
  {
    return [
      'enabled' => get_option('wpp_enabled', '1'),
      'fee_amount' => get_option('wpp_fee_amount', '5.00'),
      'section_title' => get_option('wpp_section_title', 'Express Options'),
      'checkbox_label' => get_option('wpp_checkbox_label', 'Priority processing + Express shipping'),
      'description' => get_option('wpp_description', 'Your order will be processed with priority and shipped via express delivery'),
      'fee_label' => get_option('wpp_fee_label', 'Priority Processing & Express Shipping'),
      'allowed_user_roles' => get_option('wpp_allowed_user_roles', ['customer']),
      'allow_guests' => get_option('wpp_allow_guests', '1')
    ];
  }

  /**
   * Render basic settings section
   */
  public function render_basic_settings($settings)
  {
?>
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
                <input type="checkbox" id="wpp_enabled" name="wpp_enabled" value="1" <?php checked($settings['enabled'], '1'); ?> />
                <span class="wpp-toggle-slider"></span>
              </label>
              <span class="wpp-status <?php echo ($settings['enabled'] === '1') ? 'wpp-status-enabled' : 'wpp-status-disabled'; ?>">
                <?php echo ($settings['enabled'] === '1') ? __('Active', 'woo-priority') : __('Inactive', 'woo-priority'); ?>
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
              value="<?php echo esc_attr($settings['fee_amount']); ?>" />
            <p class="description"><?php _e('Amount to charge for priority processing and express shipping', 'woo-priority'); ?></p>
          </td>
        </tr>
      </table>
    </div>
  <?php
  }

  /**
   * Render user permissions section
   */
  public function render_permissions_settings($settings)
  {
  ?>
    <div class="wpp-feature-section">
      <div class="wpp-feature-title"><?php _e('User Permissions', 'woo-priority'); ?></div>

      <table class="form-table">
        <tr>
          <th scope="row">
            <label for="wpp_allowed_user_roles"><?php _e('Allowed User Roles', 'woo-priority'); ?></label>
          </th>
          <td>
            <?php
            $allowed_roles = Core_Permissions::get_allowed_user_roles();
            $available_roles = Core_Permissions::get_available_user_roles();
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
                  <?php checked($settings['allow_guests'], '1'); ?> />
                <span class="wpp-toggle-slider"></span>
              </label>
              <span class="wpp-guest-status">
                <?php echo ($settings['allow_guests'] === '1') ? __('Allowed', 'woo-priority') : __('Denied', 'woo-priority'); ?>
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
              $summary = Core_Permissions::get_permission_summary();
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
                  <strong><?php _e('No users currently have access to priority processing', 'woo-priority'); ?></strong>
                </div>
              <?php endif; ?>
            </div>
            <p class="description"><?php _e('This shows who can currently see and use the priority processing option', 'woo-priority'); ?></p>
          </td>
        </tr>
      </table>
    </div>
  <?php
  }

  /**
   * Render display settings section
   */
  public function render_display_settings($settings)
  {
  ?>
    <div class="wpp-feature-section">
      <div class="wpp-feature-title"><?php _e('Display Settings', 'woo-priority'); ?></div>

      <table class="form-table">
        <tr>
          <th scope="row">
            <label for="wpp_section_title"><?php _e('Section Title', 'woo-priority'); ?></label>
          </th>
          <td>
            <input type="text" id="wpp_section_title" name="wpp_section_title"
              value="<?php echo esc_attr($settings['section_title']); ?>" />
            <p class="description"><?php _e('Heading shown above the priority processing option', 'woo-priority'); ?></p>
          </td>
        </tr>

        <tr>
          <th scope="row">
            <label for="wpp_checkbox_label"><?php _e('Checkbox Label', 'woo-priority'); ?></label>
          </th>
          <td>
            <input type="text" id="wpp_checkbox_label" name="wpp_checkbox_label"
              value="<?php echo esc_attr($settings['checkbox_label']); ?>" />
            <p class="description"><?php _e('Text displayed next to the checkbox option', 'woo-priority'); ?></p>
          </td>
        </tr>

        <tr>
          <th scope="row">
            <label for="wpp_description"><?php _e('Help Text', 'woo-priority'); ?></label>
          </th>
          <td>
            <textarea id="wpp_description" name="wpp_description"><?php echo esc_textarea($settings['description']); ?></textarea>
            <p class="description"><?php _e('Additional explanation shown below the checkbox', 'woo-priority'); ?></p>
          </td>
        </tr>

        <tr>
          <th scope="row">
            <label for="wpp_fee_label"><?php _e('Fee Label', 'woo-priority'); ?></label>
          </th>
          <td>
            <input type="text" id="wpp_fee_label" name="wpp_fee_label"
              value="<?php echo esc_attr($settings['fee_label']); ?>" />
            <p class="description"><?php _e('How the fee appears in cart totals and order summaries', 'woo-priority'); ?></p>
          </td>
        </tr>
      </table>
    </div>
  <?php
  }

  /**
   * Render preview panel
   */
  public function render_preview_panel($settings)
  {
  ?>
    <div class="wpp-preview-card">
      <h3><?php _e('Live Preview', 'woo-priority'); ?></h3>

      <div class="wpp-quick-stats">
        <div class="wpp-stat-item">
          <span class="wpp-stat-value"><?php echo esc_html($settings['fee_amount']); ?></span>
          <span class="wpp-stat-label"><?php _e('Current Fee', 'woo-priority'); ?></span>
        </div>
        <div class="wpp-stat-item">
          <span class="wpp-stat-value"><?php echo ($settings['enabled'] === '1') ? '✅' : '❌'; ?></span>
          <span class="wpp-stat-label"><?php _e('Status', 'woo-priority'); ?></span>
        </div>
      </div>

      <p><strong><?php _e('How it appears at checkout:', 'woo-priority'); ?></strong></p>

      <div class="wpp-checkout-preview" id="checkout-preview">
        <h4>⚡ <span id="preview-section-title"><?php echo esc_html($settings['section_title']); ?></span></h4>
        <label>
          <input type="checkbox" disabled <?php echo ($settings['enabled'] === '1') ? '' : 'style="opacity: 0.5;"'; ?> />
          <span>
            <strong id="preview-checkbox-label"><?php echo esc_html($settings['checkbox_label']); ?></strong>
            <span class="preview-price">(+<span id="preview-fee-amount"><?php echo esc_html($settings['fee_amount']); ?></span>)</span>
            <?php if ($settings['description']): ?>
              <small class="preview-description" id="preview-description"><?php echo esc_html($settings['description']); ?></small>
            <?php endif; ?>
          </span>
        </label>
      </div>

      <div style="margin-top: 20px;">
        <h4><?php _e('Permission Summary', 'woo-priority'); ?></h4>
        <div id="preview-permission-summary" style="font-size: 12px; color: #646970;">
          <?php
          $summary = Core_Permissions::get_permission_summary();
          if (!empty($summary)) {
            echo '<strong>Available to:</strong> ' . implode(', ', $summary);
          } else {
            echo '<strong>No access granted</strong>';
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
        <h4><?php _e('Quick Tips', 'woo-priority'); ?></h4>
        <ul>
          <li><?php _e('Set competitive but profitable fee amounts based on your fulfillment costs', 'woo-priority'); ?></li>
          <li><?php _e('Use clear, benefit-focused language in your checkbox label', 'woo-priority'); ?></li>
          <li><?php _e('Keep descriptions concise but informative about delivery timeframes', 'woo-priority'); ?></li>
          <li><?php _e('Priority orders are automatically marked with ⚡ in your admin area', 'woo-priority'); ?></li>
          <li><?php _e('Control access with user roles and guest permissions for security', 'woo-priority'); ?></li>
        </ul>
      </div>

      <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
        <h4 style="margin: 0 0 8px 0; color: #856404;"><?php _e('Important', 'woo-priority'); ?></h4>
        <p style="margin: 0; font-size: 12px; color: #856404;">
          <?php _e('Make sure to fulfill priority orders faster than regular orders to maintain customer satisfaction and justify the additional fee.', 'woo-priority'); ?>
        </p>
      </div>
    </div>
<?php
  }
}
