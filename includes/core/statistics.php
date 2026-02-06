<?php

/**
 * Core Statistics Handler
 * Manages statistics calculation, caching, and display for priority orders
 */
class Core_Statistics
{
  private $cache_key = 'wpp_statistics_data';
  private $cache_duration;

  public function __construct()
  {
    // Cache for 24 hours (1 day)
    $this->cache_duration = DAY_IN_SECONDS;

    // Register AJAX handler for statistics refresh
    add_action('wp_ajax_wpp_refresh_stats', [$this, 'ajax_refresh_stats']);
  }

  /**
   * Get statistics data with caching
   */
  public function get_statistics($force_refresh = false)
  {
    if (!$force_refresh) {
      $cached_stats = get_transient($this->cache_key);
      if ($cached_stats !== false) {
        wpp_log( 'Statistics loaded from cache' );
        return $cached_stats;
      }
    }

    // Calculate fresh statistics
    wpp_log( 'Calculating fresh statistics...' );
    $stats = $this->calculate_statistics();

    // Cache the results for 24 hours
    set_transient($this->cache_key, $stats, $this->cache_duration);
    wpp_log( 'Statistics cached for 24 hours' );

    return $stats;
  }

  /**
   * Calculate statistics from database
   */
  private function calculate_statistics()
  {
    global $wpdb;

    $stats = [
      'total_priority_orders' => 0,
      'total_priority_revenue' => 0,
      'today_priority_orders' => 0,
      'this_week_priority_orders' => 0,
      'this_month_priority_orders' => 0,
      'priority_percentage' => 0,
      'average_priority_fee' => 0,
      'total_orders' => 0,
      'last_updated' => current_time('mysql')
    ];

    // Check if we're using HPOS or traditional post meta
    $using_hpos = $this->is_using_hpos();

    if ($using_hpos) {
      $stats = $this->calculate_hpos_statistics($stats);
    } else {
      $stats = $this->calculate_traditional_statistics($stats);
    }

    // Calculate revenue from priority fees
    $stats = $this->calculate_priority_revenue($stats);

    wpp_log( 'Statistics calculation completed - Total Priority Orders: ' . $stats['total_priority_orders'] );

    return $stats;
  }

  /**
   * Check if WooCommerce is using High-Performance Order Storage (HPOS)
   */
  private function is_using_hpos()
  {
    return class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') &&
      \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
  }

  /**
   * Calculate statistics using HPOS tables
   */
  private function calculate_hpos_statistics($stats)
  {
    global $wpdb;

    $orders_table = $wpdb->prefix . 'wc_orders';
    $order_meta_table = $wpdb->prefix . 'wc_orders_meta';

    // Total priority orders
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe.
    $total_priority = $wpdb->get_var( "
      SELECT COUNT(DISTINCT o.id)
      FROM {$orders_table} o
      INNER JOIN {$order_meta_table} om ON o.id = om.order_id
      WHERE om.meta_key = '_priority_processing'
      AND om.meta_value = 'yes'
      AND o.type = 'shop_order'
      AND o.status NOT IN ('trash', 'auto-draft')
    " );

    // Total orders for percentage calculation
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe.
    $total_orders = $wpdb->get_var( "
      SELECT COUNT(*)
      FROM {$orders_table}
      WHERE type = 'shop_order'
      AND status NOT IN ('trash', 'auto-draft')
    " );

    // Today's priority orders
    $today = gmdate('Y-m-d');
    $today_priority = $wpdb->get_var($wpdb->prepare("
      SELECT COUNT(DISTINCT o.id) 
      FROM {$orders_table} o
      INNER JOIN {$order_meta_table} om ON o.id = om.order_id
      WHERE om.meta_key = '_priority_processing' 
      AND om.meta_value = 'yes'
      AND o.type = 'shop_order'
      AND DATE(o.date_created_gmt) = %s
    ", $today));

    // This week's priority orders
    $week_start = gmdate('Y-m-d', strtotime('monday this week'));
    $this_week_priority = $wpdb->get_var($wpdb->prepare("
      SELECT COUNT(DISTINCT o.id) 
      FROM {$orders_table} o
      INNER JOIN {$order_meta_table} om ON o.id = om.order_id
      WHERE om.meta_key = '_priority_processing' 
      AND om.meta_value = 'yes'
      AND o.type = 'shop_order'
      AND DATE(o.date_created_gmt) >= %s
    ", $week_start));

    // This month's priority orders
    $month_start = gmdate('Y-m-01');
    $this_month_priority = $wpdb->get_var($wpdb->prepare("
      SELECT COUNT(DISTINCT o.id) 
      FROM {$orders_table} o
      INNER JOIN {$order_meta_table} om ON o.id = om.order_id
      WHERE om.meta_key = '_priority_processing' 
      AND om.meta_value = 'yes'
      AND o.type = 'shop_order'
      AND DATE(o.date_created_gmt) >= %s
    ", $month_start));

    // Update stats array
    $stats['total_priority_orders'] = intval($total_priority);
    $stats['total_orders'] = intval($total_orders);
    $stats['today_priority_orders'] = intval($today_priority);
    $stats['this_week_priority_orders'] = intval($this_week_priority);
    $stats['this_month_priority_orders'] = intval($this_month_priority);

    return $stats;
  }

  /**
   * Calculate statistics using traditional post meta tables
   */
  private function calculate_traditional_statistics($stats)
  {
    global $wpdb;

    $posts_table = $wpdb->posts;
    $postmeta_table = $wpdb->postmeta;

    // Total priority orders
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe.
    $total_priority = $wpdb->get_var( "
      SELECT COUNT(DISTINCT p.ID)
      FROM {$posts_table} p
      INNER JOIN {$postmeta_table} pm ON p.ID = pm.post_id
      WHERE pm.meta_key = '_priority_processing'
      AND pm.meta_value = 'yes'
      AND p.post_type = 'shop_order'
      AND p.post_status NOT IN ('trash', 'auto-draft')
    " );

    // Total orders
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe.
    $total_orders = $wpdb->get_var( "
      SELECT COUNT(*)
      FROM {$posts_table}
      WHERE post_type = 'shop_order'
      AND post_status NOT IN ('trash', 'auto-draft')
    " );

    // Today's priority orders
    $today = gmdate('Y-m-d');
    $today_priority = $wpdb->get_var($wpdb->prepare("
      SELECT COUNT(DISTINCT p.ID) 
      FROM {$posts_table} p
      INNER JOIN {$postmeta_table} pm ON p.ID = pm.post_id
      WHERE pm.meta_key = '_priority_processing' 
      AND pm.meta_value = 'yes'
      AND p.post_type = 'shop_order'
      AND DATE(p.post_date) = %s
    ", $today));

    // This week's priority orders
    $week_start = gmdate('Y-m-d', strtotime('monday this week'));
    $this_week_priority = $wpdb->get_var($wpdb->prepare("
      SELECT COUNT(DISTINCT p.ID) 
      FROM {$posts_table} p
      INNER JOIN {$postmeta_table} pm ON p.ID = pm.post_id
      WHERE pm.meta_key = '_priority_processing' 
      AND pm.meta_value = 'yes'
      AND p.post_type = 'shop_order'
      AND DATE(p.post_date) >= %s
    ", $week_start));

    // This month's priority orders
    $month_start = gmdate('Y-m-01');
    $this_month_priority = $wpdb->get_var($wpdb->prepare("
      SELECT COUNT(DISTINCT p.ID) 
      FROM {$posts_table} p
      INNER JOIN {$postmeta_table} pm ON p.ID = pm.post_id
      WHERE pm.meta_key = '_priority_processing' 
      AND pm.meta_value = 'yes'
      AND p.post_type = 'shop_order'
      AND DATE(p.post_date) >= %s
    ", $month_start));

    // Update stats array
    $stats['total_priority_orders'] = intval($total_priority);
    $stats['total_orders'] = intval($total_orders);
    $stats['today_priority_orders'] = intval($today_priority);
    $stats['this_week_priority_orders'] = intval($this_week_priority);
    $stats['this_month_priority_orders'] = intval($this_month_priority);

    return $stats;
  }

  /**
   * Calculate revenue from priority fees
   */
  private function calculate_priority_revenue($stats)
  {
    $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');
    $priority_revenue = 0;
    $total_fee_amount = 0;
    $fee_count = 0;

    // Only proceed if we have priority orders
    if ($stats['total_priority_orders'] > 0) {
      try {
        $priority_order_args = [
          'limit' => -1,
          'meta_key' => '_priority_processing',
          'meta_value' => 'yes',
          'status' => ['completed', 'processing', 'on-hold', 'pending']
        ];

        $priority_orders = wc_get_orders($priority_order_args);

        foreach ($priority_orders as $order) {
          $fees = $order->get_fees();
          foreach ($fees as $fee) {
            // Check if this fee is related to priority processing
            if (
              strpos($fee->get_name(), 'Priority') !== false ||
              $fee->get_name() === $fee_label
            ) {
              $fee_amount = floatval($fee->get_total());
              $priority_revenue += $fee_amount;
              $total_fee_amount += $fee_amount;
              $fee_count++;
            }
          }
        }

        wpp_log( 'Processed ' . count($priority_orders) . ' priority orders for revenue calculation' );
      } catch (Exception $e) {
        wpp_log( 'Error calculating priority revenue: ' . $e->getMessage() );
      }
    }

    // Update stats with revenue calculations
    $stats['total_priority_revenue'] = floatval($priority_revenue);

    // Calculate percentage
    if ($stats['total_orders'] > 0) {
      $stats['priority_percentage'] = round(($stats['total_priority_orders'] / $stats['total_orders']) * 100, 1);
    }

    // Calculate average fee
    if ($fee_count > 0) {
      $stats['average_priority_fee'] = round($total_fee_amount / $fee_count, 2);
    }

    return $stats;
  }

  /**
   * Clear statistics cache manually
   */
  public function clear_cache()
  {
    delete_transient($this->cache_key);
    wpp_log( 'Statistics cache cleared manually' );
  }

  /**
   * Get formatted statistics for display
   */
  public function get_formatted_statistics($stats = null)
  {
    if ($stats === null) {
      $stats = $this->get_statistics();
    }

    return [
      'total_priority_orders' => number_format($stats['total_priority_orders']),
      'total_priority_revenue' => wc_price($stats['total_priority_revenue']),
      'today_priority_orders' => number_format($stats['today_priority_orders']),
      'this_week_priority_orders' => number_format($stats['this_week_priority_orders']),
      'this_month_priority_orders' => number_format($stats['this_month_priority_orders']),
      'priority_percentage' => $stats['priority_percentage'] . '%',
      'average_priority_fee' => wc_price($stats['average_priority_fee']),
      'last_updated' => gmdate('Y-m-d H:i:s', strtotime($stats['last_updated']))
    ];
  }

  /**
   * AJAX handler for refreshing statistics
   */
  public function ajax_refresh_stats()
  {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpp_admin_nonce')) {
      wp_send_json_error('Invalid nonce');
      return;
    }

    // Check user capabilities
    if (!current_user_can('manage_woocommerce')) {
      wp_send_json_error('Insufficient permissions');
      return;
    }

    try {
      // Force refresh statistics
      $stats = $this->get_statistics(true);
      $formatted = $this->get_formatted_statistics($stats);

      // Update last updated time to current time
      $formatted['last_updated'] = gmdate('H:i');

      wpp_log( 'Statistics refreshed via AJAX' );

      // Return updated stats with formatted values
      wp_send_json_success([
        'stats' => $stats,
        'formatted' => $formatted,
        'message' => __('Statistics updated successfully', 'woo-priority')
      ]);
    } catch (Exception $e) {
      wpp_log( 'Error refreshing statistics: ' . $e->getMessage() );
      wp_send_json_error('Error refreshing statistics: ' . $e->getMessage());
    }
  }

  /**
   * Get cache information
   */
  public function get_cache_info()
  {
    $cached_stats = get_transient($this->cache_key);

    return [
      'is_cached' => ($cached_stats !== false),
      'cache_duration_hours' => $this->cache_duration / HOUR_IN_SECONDS,
      'cache_key' => $this->cache_key
    ];
  }

  /**
   * Schedule daily cache refresh (optional - for automated updates)
   */
  public function schedule_daily_refresh()
  {
    if (!wp_next_scheduled('wpp_daily_stats_refresh')) {
      wp_schedule_event(time(), 'daily', 'wpp_daily_stats_refresh');
    }

    add_action('wpp_daily_stats_refresh', [$this, 'daily_cache_refresh']);
  }

  /**
   * Daily cache refresh callback
   */
  public function daily_cache_refresh()
  {
    wpp_log( 'Running daily statistics cache refresh' );
    $this->get_statistics(true);
  }

  /**
   * Clean up scheduled events on deactivation
   */
  public function cleanup_scheduled_events()
  {
    wp_clear_scheduled_hook('wpp_daily_stats_refresh');
  }
}
