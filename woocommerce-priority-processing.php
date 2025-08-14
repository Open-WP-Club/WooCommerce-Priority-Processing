<?php

/**
 * Plugin Name: WooCommerce Priority Processing
 * Description: Add priority processing and express shipping option at checkout
 * Version: 1.1.0
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
define('WPP_VERSION', '1.1.0');
define('WPP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
  if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
});

// Include class files
require_once WPP_PLUGIN_DIR . 'includes/wpp-permissions.php';
require_once WPP_PLUGIN_DIR . 'includes/wpp-statistics.php';
require_once WPP_PLUGIN_DIR . 'includes/wpp-admin.php';
require_once WPP_PLUGIN_DIR . 'includes/wpp-frontend.php';
require_once WPP_PLUGIN_DIR . 'includes/wpp-orders.php';

class WooCommerce_Priority_Processing
{
  private static $instance = null;

  public $admin;
  public $frontend;
  public $orders;
  public $statistics;

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
    // Initialize components in proper order
    $this->statistics = new WPP_Statistics();
    $this->admin = new WPP_Admin();
    $this->frontend = new WPP_Frontend();
    $this->orders = new WPP_Orders();

    // Register settings and defaults
    add_action('admin_init', [$this, 'register_settings']);

    // Add cleanup hooks
    add_action('woocommerce_cart_emptied', [$this, 'clear_priority_session']);
    add_action('wp_logout', [$this, 'clear_priority_session']);

    // Plugin lifecycle hooks
    register_activation_hook(__FILE__, [$this, 'on_activation']);
    register_deactivation_hook(__FILE__, [$this, 'on_deactivation']);
  }

  public function woocommerce_missing_notice()
  {
?>
    <div class="notice notice-error">
      <p><?php _e('WooCommerce Priority Processing requires WooCommerce to be installed and active.', 'woo-priority'); ?></p>
    </div>
<?php
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
    if (get_option('wpp_section_title') === false) {
      update_option('wpp_section_title', __('Express Options', 'woo-priority'));
    }
    if (get_option('wpp_enabled') === false) {
      update_option('wpp_enabled', '1');
    }
    if (get_option('wpp_allowed_user_roles') === false) {
      update_option('wpp_allowed_user_roles', ['customer']);
    }
    if (get_option('wpp_allow_guests') === false) {
      update_option('wpp_allow_guests', '1');
    }
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

  public function clear_priority_session()
  {
    if (WC()->session) {
      WC()->session->set('priority_processing', false);
      error_log('WPP: Priority session cleared by main class');
    }
  }

  /**
   * Plugin activation
   */
  public function on_activation()
  {
    // Create default options
    add_option('wpp_enabled', '0');
    add_option('wpp_fee_amount', '5.00');
    add_option('wpp_checkbox_label', __('Priority processing + Express shipping', 'woo-priority'));
    add_option('wpp_description', __('Your order will be processed with priority and shipped via express delivery', 'woo-priority'));
    add_option('wpp_fee_label', __('Priority Processing & Express Shipping', 'woo-priority'));
    add_option('wpp_section_title', __('Express Options', 'woo-priority'));
    add_option('wpp_allowed_user_roles', ['customer']);
    add_option('wpp_allow_guests', '1');

    // Optionally schedule daily statistics refresh
    if ($this->statistics) {
      $this->statistics->schedule_daily_refresh();
    }

    error_log('WPP: Plugin activated successfully');
  }

  /**
   * Plugin deactivation
   */
  public function on_deactivation()
  {
    // Clear any sessions
    if (class_exists('WooCommerce') && WC()->session) {
      WC()->session->set('priority_processing', false);
    }

    // Clear statistics cache
    if ($this->statistics) {
      $this->statistics->clear_cache();
      $this->statistics->cleanup_scheduled_events();
    }

    error_log('WPP: Plugin deactivated and cleaned up');
  }

  /**
   * Get statistics instance
   */
  public function get_statistics()
  {
    return $this->statistics;
  }

  /**
   * Get admin instance
   */
  public function get_admin()
  {
    return $this->admin;
  }
}

// Initialize plugin
add_action('plugins_loaded', function () {
  WooCommerce_Priority_Processing::instance();
});

// Legacy activation/deactivation hooks (for backward compatibility)
register_activation_hook(__FILE__, function () {
  // Create default options
  add_option('wpp_enabled', '0');
  add_option('wpp_fee_amount', '5.00');
  add_option('wpp_checkbox_label', __('Priority processing + Express shipping', 'woo-priority'));
  add_option('wpp_description', __('Your order will be processed with priority and shipped via express delivery', 'woo-priority'));
  add_option('wpp_fee_label', __('Priority Processing & Express Shipping', 'woo-priority'));
  add_option('wpp_section_title', __('Express Options', 'woo-priority'));
  add_option('wpp_allowed_user_roles', ['customer']);
  add_option('wpp_allow_guests', '1');
});

register_deactivation_hook(__FILE__, function () {
  // Clear any sessions
  if (class_exists('WooCommerce') && WC()->session) {
    WC()->session->set('priority_processing', false);
  }
});
