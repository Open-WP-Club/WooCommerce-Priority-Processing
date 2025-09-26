<?php

/**
 * Admin Menu Handler
 * Manages admin menu registration and basic admin setup
 */
class Admin_Menu
{
  private $statistics;

  public function __construct($statistics_instance)
  {
    $this->statistics = $statistics_instance;

    // Always add the admin menu as primary approach
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

    // Add settings link to plugins page
    add_filter('plugin_action_links_' . plugin_basename(WPP_PLUGIN_DIR . 'woocommerce-priority-processing.php'), [$this, 'add_plugin_action_links']);

    // Add plugin metadata links
    add_filter('plugin_row_meta', [$this, 'add_plugin_row_meta'], 10, 2);

    // Try WooCommerce integration as secondary approach
    add_action('woocommerce_loaded', [$this, 'try_woocommerce_integration']);
  }

  /**
   * Add settings link to plugins page
   */
  public function add_plugin_action_links($links)
  {
    $plugin_links = [];

    // Settings link
    $plugin_links['settings'] = sprintf(
      '<a href="%s" style="color: #2271b1; font-weight: 600;">%s</a>',
      admin_url('admin.php?page=woo-priority-processing'),
      __('Settings', 'woo-priority')
    );

    // Documentation link (if you have documentation)
    $plugin_links['docs'] = sprintf(
      '<a href="%s" target="_blank" style="color: #50575e;">%s</a>',
      'https://openwpclub.com/docs/woocommerce-priority-processing/',
      __('Docs', 'woo-priority')
    );

    // Support link 
    $plugin_links['support'] = sprintf(
      '<a href="%s" target="_blank" style="color: #50575e;">%s</a>',
      'https://openwpclub.com/support/',
      __('Support', 'woo-priority')
    );

    // Merge with existing links (Settings first, then others, then existing links)
    return array_merge($plugin_links, $links);
  }

  /**
   * Add plugin metadata links (in plugin description area)
   */
  public function add_plugin_row_meta($links, $file)
  {
    $plugin_basename = plugin_basename(WPP_PLUGIN_DIR . 'woocommerce-priority-processing.php');

    if ($file === $plugin_basename) {
      $meta_links = [];

      // GitHub/Source link (if applicable)
      $meta_links[] = sprintf(
        '<a href="%s" target="_blank" style="color: #50575e;">%s</a>',
        'https://github.com/openwpclub/woocommerce-priority-processing',
        __('GitHub', 'woo-priority')
      );

      $links = array_merge($links, $meta_links);
    }

    return $links;
  }

  /**
   * Try to integrate with WooCommerce settings if possible
   */
  public function try_woocommerce_integration()
  {
    // Only try if WC_Settings_Page exists and we haven't already integrated
    if (class_exists('WC_Settings_Page') && !get_transient('wpp_wc_integration_attempted')) {
      add_filter('woocommerce_get_settings_pages', [$this, 'add_woocommerce_settings_page']);
      set_transient('wpp_wc_integration_attempted', true, HOUR_IN_SECONDS);
      error_log('WPP: WooCommerce settings integration attempted');
    }
  }

  /**
   * Add WooCommerce settings page integration
   */
  public function add_woocommerce_settings_page($settings)
  {
    // Include and create the settings page class
    if (!class_exists('WC_Settings_Priority_Processing')) {
      $settings_file = WPP_PLUGIN_DIR . 'includes/admin/wc-settings.php';
      if (file_exists($settings_file)) {
        include_once $settings_file;
      }
    }

    if (class_exists('WC_Settings_Priority_Processing')) {
      $settings[] = new WC_Settings_Priority_Processing();
      error_log('WPP: Added WooCommerce settings page');
    }

    return $settings;
  }

  /**
   * Add admin menu page
   */
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

  /**
   * Display admin page - delegates to dashboard class
   */
  public function admin_page()
  {
    // Create dashboard instance and display
    $dashboard = new Admin_Dashboard($this->statistics);
    $dashboard->display_page();
  }

  /**
   * Enqueue admin scripts and styles
   */
  public function admin_scripts($hook)
  {
    // Load styles on our admin page
    if ($hook === 'woocommerce_page_woo-priority-processing') {
      wp_enqueue_style('wpp-admin', WPP_PLUGIN_URL . 'assets/css/admin.css', [], WPP_VERSION);
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
      wp_enqueue_style('wpp-admin', WPP_PLUGIN_URL . 'assets/css/admin.css', [], WPP_VERSION);
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
