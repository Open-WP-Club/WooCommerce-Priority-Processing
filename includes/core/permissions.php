<?php
/**
 * Core Permissions Handler
 * Manages access control for priority processing feature
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.0.0
 */

declare(strict_types=1);

/**
 * Core Permissions Class
 *
 * @since 1.0.0
 */
class Core_Permissions {
  /**
   * Check if current user can access priority processing
   * 
   * @return bool
   */
  public static function can_access_priority_processing() {
    // Shop managers and administrators always have access
    if (current_user_can('manage_woocommerce') || current_user_can('administrator')) {
      return true;
    }

    // Get allowed user roles
    $allowed_roles = self::get_allowed_user_roles();

    // Check if guests are allowed
    $allow_guests = get_option('wpp_allow_guests', '1');

    // If user is not logged in
    if (!is_user_logged_in()) {
      return $allow_guests === '1' || $allow_guests === 'yes';
    }

    // Get current user
    $user = wp_get_current_user();

    // If no roles are set, allow all logged-in users
    if (empty($allowed_roles)) {
      return true;
    }

    // Check if user has any of the allowed roles
    if (!empty($user->roles)) {
      foreach ($user->roles as $role) {
        if (in_array($role, $allowed_roles)) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Check if a specific user ID can access priority processing
   * 
   * @param int $user_id User ID to check
   * @return bool
   */
  public static function user_can_access($user_id)
    if (empty($user_id)) {
      return self::can_access_priority_processing();
    }

    $user = get_user_by('id', $user_id);
    if (!$user) {
      return false;
    }

    // Check for admin/shop manager capabilities
    if (user_can($user_id, 'manage_woocommerce') || user_can($user_id, 'administrator')) {
      return true;
    }

    $allowed_roles = self::get_allowed_user_roles();

    // If no roles are set, allow all users
    if (empty($allowed_roles)) {
      return true;
    }

    // Check if user has any of the allowed roles
    if (!empty($user->roles)) {
      foreach ($user->roles as $role) {
        if (in_array($role, $allowed_roles)) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Get list of all available user roles
   * 
   * @return array
   */
  public static function get_available_roles() {
    global $wp_roles;

    if (!isset($wp_roles)) {
      $wp_roles = new WP_Roles();
    }

    $roles = [];
    foreach ($wp_roles->get_names() as $role_slug => $role_name) {
      $roles[$role_slug] = $role_name;
    }

    return $roles;
  }

  /**
   * Get allowed user roles as array (for settings page)
   * Ensures proper format
   * 
   * @return array
   */
  public static function get_allowed_user_roles() {
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
   * Get all available user roles for the settings (excludes admin roles)
   * 
   * @return array
   */
  public static function get_available_user_roles() {
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
   *
   * @return array
   */
  public static function get_permission_summary() {
    $allowed_roles = self::get_allowed_user_roles();
    $allow_guests = get_option('wpp_allow_guests', '1');

    $summary = [];

    // Always include shop managers
    $summary[] = __('Shop Managers & Administrators', 'woo-priority');

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
   * Check if priority processing is active in current session
   * Shared utility method used across multiple classes
   *
   * @since 1.4.2
   * @return bool True if priority processing is active
   */
  public static function is_priority_active(): bool {
    if (!WC()->session) {
      return false;
    }

    $priority = WC()->session->get('priority_processing', false);
    return ($priority === true || $priority === '1' || $priority === 1);
  }
}
