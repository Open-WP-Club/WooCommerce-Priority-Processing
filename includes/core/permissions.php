<?php

/**
 * Core Permission Handler
 * Handles user role-based access control for priority processing feature
 */
class Core_Permissions
{
  /**
   * Get allowed user roles as array (ensures proper format)
   */
  public static function get_allowed_user_roles()
  {
    $allowed_roles = get_option('wpp_allowed_user_roles', ['customer']);

    // Ensure it's always an array
    if (!is_array($allowed_roles)) {
      $allowed_roles = !empty($allowed_roles) ? [$allowed_roles] : ['customer'];
      // Fix the option in database to prevent future issues
      update_option('wpp_allowed_user_roles', $allowed_roles);
    }

    return $allowed_roles;
  }

  /**
   * Check if current user can access priority processing
   */
  public static function can_access_priority_processing()
  {
    // Shop managers and administrators always have access
    if (current_user_can('manage_woocommerce') || current_user_can('administrator')) {
      return true;
    }

    // Get permission settings
    $allowed_roles = self::get_allowed_user_roles();
    $allow_guests = get_option('wpp_allow_guests', '1');

    // Handle guest users
    if (!is_user_logged_in()) {
      return $allow_guests === '1';
    }

    // Get current user's roles
    $current_user = wp_get_current_user();
    $user_roles = $current_user->roles;

    // Check if user has any of the allowed roles
    if (!empty($allowed_roles)) {
      foreach ($user_roles as $role) {
        if (in_array($role, $allowed_roles)) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Get all available user roles for the settings
   */
  public static function get_available_user_roles()
  {
    global $wp_roles;

    if (!isset($wp_roles)) {
      $wp_roles = new WP_Roles();
    }

    $roles = [];
    foreach ($wp_roles->roles as $role_key => $role_data) {
      // Skip administrator and shop_manager as they have special handling
      if (!in_array($role_key, ['administrator', 'shop_manager'])) {
        $roles[$role_key] = $role_data['name'];
      }
    }

    return $roles;
  }

  /**
   * Get current permission summary for admin display
   */
  public static function get_permission_summary()
  {
    $allowed_roles = self::get_allowed_user_roles();
    $allow_guests = get_option('wpp_allow_guests', '1');

    $summary = [];

    // Always include shop managers
    $summary[] = __('Shop Managers', 'woo-priority');

    // Add selected roles
    $available_roles = self::get_available_user_roles();
    foreach ($allowed_roles as $role_key) {
      if (isset($available_roles[$role_key])) {
        $summary[] = $available_roles[$role_key];
      }
    }

    // Add guests if allowed
    if ($allow_guests === '1') {
      $summary[] = __('Guest Users', 'woo-priority');
    }

    return $summary;
  }

  /**
   * Log permission check for debugging
   */
  public static function log_permission_check($context = '')
  {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
      return;
    }

    $user_info = is_user_logged_in() ?
      'User ID: ' . get_current_user_id() . ', Roles: ' . implode(', ', wp_get_current_user()->roles) :
      'Guest user';

    $allowed_roles = self::get_allowed_user_roles();
    $allow_guests = get_option('wpp_allow_guests', '1');
    $has_access = self::can_access_priority_processing();

    error_log("WPP Permission Check [{$context}]: {$user_info} | Allowed roles: " . implode(', ', $allowed_roles) . " | Allow guests: {$allow_guests} | Access granted: " . ($has_access ? 'YES' : 'NO'));
  }

  /**
   * Validate permission for AJAX requests
   */
  public static function validate_ajax_permission()
  {
    if (!self::can_access_priority_processing()) {
      self::log_permission_check('ajax_validation');
      wp_send_json_error([
        'message' => __('You do not have permission to access priority processing.', 'woo-priority'),
        'code' => 'permission_denied'
      ]);
      return false;
    }
    return true;
  }

  /**
   * Get permission settings for admin display
   */
  public static function get_permission_settings()
  {
    return [
      'allowed_roles' => self::get_allowed_user_roles(),
      'allow_guests' => get_option('wpp_allow_guests', '1'),
      'available_roles' => self::get_available_user_roles(),
      'summary' => self::get_permission_summary()
    ];
  }
}
