<?php

/**
 * Plugin Name: WooCommerce Priority Processing
 * Description: Add priority processing and express shipping option at checkout
 * Version: 1.3.0
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
define('WPP_VERSION', '1.3.0');
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

  public $admin_menu;
  public $admin_settings;
  public $admin_dashboard;
  public $frontend_checkout;
  public $frontend_ajax;
  public $frontend_fees;
  public $frontend_shipping;
  public $core_orders;
  public $core_statistics;

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

    // Include all required files first
    $this->include_files();

    // Initialize plugin
    $this->init();
  }

  /**
   * Include all required files
   */
  private function include_files()
  {
    // Include core files
    require_once WPP_PLUGIN_DIR . 'includes/core/permissions.php';
    require_once WPP_PLUGIN_DIR . 'includes/core/statistics.php';
    require_once WPP_PLUGIN_DIR . 'includes/core/orders.php';

    // Include admin files
    require_once WPP_PLUGIN_DIR . 'includes/admin/menu.php';
    require_once WPP_PLUGIN_DIR . 'includes/admin/settings.php';
    require_once WPP_PLUGIN_DIR . 'includes/admin/dashboard.php';

    // Include frontend files
    require_once WPP_PLUGIN_DIR . 'includes/frontend/checkout.php';
    require_once WPP_PLUGIN_DIR . 'includes/frontend/ajax.php';
    require_once WPP_PLUGIN_DIR . 'includes/frontend/fees.php';
    require_once WPP_PLUGIN_DIR . 'includes/frontend/shipping.php';
  }

  private function init()
  {
    // Initialize core components
    $this->core_statistics = new Core_Statistics();
    $this->core_orders = new Core_Orders();

    // Initialize admin components
    $this->admin_menu = new Admin_Menu($this->core_statistics);
    $this->admin_settings = new Admin_Settings();
    $this->admin_dashboard = new Admin_Dashboard($this->core_statistics);

    // Initialize frontend components
    $this->frontend_checkout = new Frontend_Checkout();
    $this->frontend_ajax = new Frontend_Ajax();
    $this->frontend_fees = new Frontend_Fees();
    $this->frontend_shipping = new Frontend_Shipping();

    // Register settings and defaults
    add_action('admin_init', [$this, 'register_default_settings']);

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

  public function register_default_settings()
  {
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
    if ($this->core_statistics) {
      $this->core_statistics->schedule_daily_refresh();
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
    if ($this->core_statistics) {
      $this->core_statistics->clear_cache();
      $this->core_statistics->cleanup_scheduled_events();
    }

    error_log('WPP: Plugin deactivated and cleaned up');
  }

  /**
   * Get statistics instance
   */
  public function get_statistics()
  {
    return $this->core_statistics;
  }

  /**
   * Get admin menu instance
   */
  public function get_admin_menu()
  {
    return $this->admin_menu;
  }

  /**
   * Get orders instance
   */
  public function get_orders()
  {
    return $this->core_orders;
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
