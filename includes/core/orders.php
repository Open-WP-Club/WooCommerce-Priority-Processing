<?php

/**
 * Core Orders Handler
 * Manages order display, admin functionality, and priority order handling
 */
class Core_Orders
{
  public function __construct()
  {
    // Order list display functionality
    add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_priority_in_admin']);
    add_action('admin_head', [$this, 'orders_list_styles']);
    add_action('manage_shop_order_posts_custom_column', [$this, 'modify_order_number_display'], 10, 2);
    add_action('woocommerce_shop_order_list_table_custom_column', [$this, 'modify_order_number_display_hpos'], 10, 2);

    // Order admin functionality
    add_action('add_meta_boxes', [$this, 'add_order_meta_box']);
    add_action('wp_ajax_wpp_toggle_order_priority', [$this, 'ajax_toggle_order_priority']);
    add_action('admin_enqueue_scripts', [$this, 'order_admin_scripts']);
  }

  /**
   * Display priority processing info in individual order admin
   */
  public function display_priority_in_admin($order)
  {
    if ($order->get_meta('_priority_processing') === 'yes') {
?>
      <p style="margin-top: 10px;">
        <strong style="color: #d63638;">⚡ <?php _e('Priority Processing', 'woo-priority'); ?></strong><br>
        <?php _e('This order has priority processing and express shipping', 'woo-priority'); ?>
      </p>
    <?php
    }
  }

  /**
   * Add styles and scripts for orders list page
   */
  public function orders_list_styles()
  {
    if (!$this->is_orders_page()) {
      return;
    }
    ?>
    <style>
      .wpp-priority-flash {
        color: #d63638;
        margin-right: 4px;
        animation: flash 2s infinite;
      }

      .wpp-priority-order-number {
        color: #d63638 !important;
        font-weight: bold !important;
      }

      @keyframes flash {

        0%,
        50% {
          opacity: 1;
        }

        25%,
        75% {
          opacity: 0.3;
        }
      }
    </style>
    <script>
      jQuery(document).ready(function($) {
        // Add priority indicators to existing order numbers
        $('.wpp-priority-marker').each(function() {
          var $marker = $(this);
          var orderNum = $marker.data('order');
          var $orderCell = $marker.closest('tr').find('.order_number');

          // Prepend flash to order number
          if ($orderCell.find('a').length) {
            $orderCell.find('a').addClass('wpp-priority-order-number').prepend('<span class="wpp-priority-flash">⚡</span>');
          } else {
            $orderCell.find('strong').addClass('wpp-priority-order-number').prepend('<span class="wpp-priority-flash">⚡</span>');
          }

          // Remove the hidden marker
          $marker.remove();
        });
      });
    </script>
  <?php
  }

  /**
   * Modify order number display for traditional post-based orders
   */
  public function modify_order_number_display($column, $post_id)
  {
    if ($column === 'order_number') {
      $order = wc_get_order($post_id);
      if ($order && $order->get_meta('_priority_processing') === 'yes') {
        // Add hidden marker that will be processed by JavaScript
        echo '<span class="wpp-priority-marker" data-order="' . esc_attr($order->get_order_number()) . '" style="display:none;"></span>';
      }
    }
  }

  /**
   * Modify order number display for HPOS orders
   */
  public function modify_order_number_display_hpos($column, $order)
  {
    if ($column === 'order_number') {
      if ($order && $order->get_meta('_priority_processing') === 'yes') {
        // Add hidden marker that will be processed by JavaScript
        echo '<span class="wpp-priority-marker" data-order="' . esc_attr($order->get_order_number()) . '" style="display:none;"></span>';
      }
    }
  }

  /**
   * Check if we're on an orders page
   */
  private function is_orders_page()
  {
    global $pagenow, $typenow;

    // Traditional orders page
    if ($pagenow === 'edit.php' && $typenow === 'shop_order') {
      return true;
    }

    // HPOS orders page
    if (isset($_GET['page']) && $_GET['page'] === 'wc-orders') {
      return true;
    }

    return false;
  }

  /**
   * Add priority processing meta box to order edit page
   */
  public function add_order_meta_box()
  {
    // Traditional post-based orders
    add_meta_box(
      'wpp_order_priority',
      __('⚡ Priority Processing', 'woo-priority'),
      [$this, 'order_priority_meta_box'],
      'shop_order',
      'side',
      'high'
    );

    // HPOS orders
    if (class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
      add_meta_box(
        'wpp_order_priority',
        __('⚡ Priority Processing', 'woo-priority'),
        [$this, 'order_priority_meta_box'],
        wc_get_page_screen_id('shop-order'),
        'side',
        'high'
      );
    }
  }

  /**
   * Display the priority processing meta box
   */
  public function order_priority_meta_box($post_or_order)
  {
    // Get order object
    $order = ($post_or_order instanceof WP_Post) ? wc_get_order($post_or_order->ID) : $post_or_order;

    if (!$order) {
      return;
    }

    $order_id = $order->get_id();
    $has_priority = $order->get_meta('_priority_processing') === 'yes';
    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

    // Check if order already has priority fee
    $existing_fee = null;
    foreach ($order->get_fees() as $fee) {
      if (strpos($fee->get_name(), 'Priority') !== false || $fee->get_name() === $fee_label) {
        $existing_fee = $fee;
        break;
      }
    }

    $order_status = $order->get_status();
    $can_modify = !in_array($order_status, ['completed', 'refunded', 'cancelled']);

    wp_nonce_field('wpp_order_priority_nonce', 'wpp_order_priority_nonce');
  ?>

    <div id="wpp-order-priority-container">
      <?php if ($has_priority): ?>
        <div class="wpp-priority-active">
          <p><strong style="color: #0f5132;">✅ <?php _e('Priority Processing Active', 'woo-priority'); ?></strong></p>
          <?php if ($existing_fee): ?>
            <p><?php printf(__('Fee: %s'), wc_price($existing_fee->get_total())); ?></p>
          <?php endif; ?>

          <?php if ($can_modify): ?>
            <button type="button" id="wpp-remove-priority" class="button button-secondary"
              data-order-id="<?php echo $order_id; ?>" style="width: 100%; margin-top: 10px;">
              <?php _e('Remove Priority Processing', 'woo-priority'); ?>
            </button>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="wpp-priority-inactive">
          <p><strong><?php _e('Standard Processing', 'woo-priority'); ?></strong></p>

          <?php if ($can_modify): ?>
            <button type="button" id="wpp-add-priority" class="button button-primary"
              data-order-id="<?php echo $order_id; ?>" style="width: 100%; margin-top: 10px;">
              <?php printf(__('Add Priority Processing (+%s)', 'woo-priority'), wc_price($fee_amount)); ?>
            </button>

            <p style="font-size: 12px; color: #666; margin-top: 8px;">
              <?php printf(__('New total: %s'), wc_price($order->get_total() + floatval($fee_amount))); ?>
            </p>
          <?php else: ?>
            <p style="font-size: 12px; color: #666;">
              <?php _e('Cannot modify completed/cancelled orders', 'woo-priority'); ?>
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div id="wpp-loading" style="display: none; text-align: center; padding: 10px;">
        <span class="spinner is-active" style="float: none;"></span>
        <div style="margin-top: 5px; font-size: 12px;"><?php _e('Processing...', 'woo-priority'); ?></div>
      </div>
    </div>
<?php
  }

  /**
   * AJAX handler for toggling order priority
   */
  public function ajax_toggle_order_priority()
  {
    // Security checks
    if (!current_user_can('manage_woocommerce')) {
      wp_send_json_error(__('Permission denied', 'woo-priority'));
      return;
    }

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpp_order_priority_nonce')) {
      wp_send_json_error(__('Invalid nonce', 'woo-priority'));
      return;
    }

    $order_id = intval($_POST['order_id'] ?? 0);
    $action = sanitize_text_field($_POST['priority_action'] ?? '');

    if (!$order_id || !in_array($action, ['add', 'remove'])) {
      wp_send_json_error(__('Invalid parameters', 'woo-priority'));
      return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
      wp_send_json_error(__('Order not found', 'woo-priority'));
      return;
    }

    // Check if order can be modified
    $order_status = $order->get_status();
    if (in_array($order_status, ['completed', 'refunded', 'cancelled'])) {
      wp_send_json_error(__('Cannot modify this order status', 'woo-priority'));
      return;
    }

    try {
      $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
      $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');
      $current_user = wp_get_current_user();

      if ($action === 'add') {
        // Add priority processing

        // Check if already has priority
        if ($order->get_meta('_priority_processing') === 'yes') {
          wp_send_json_error(__('Order already has priority processing', 'woo-priority'));
          return;
        }

        // Check if priority fee already exists before adding
        $existing_fee = null;
        foreach ($order->get_fees() as $fee) {
          if (strpos($fee->get_name(), 'Priority') !== false || $fee->get_name() === $fee_label) {
            $existing_fee = $fee;
            break;
          }
        }

        if ($existing_fee) {
          wp_send_json_error(__('Order already has a priority processing fee', 'woo-priority'));
          return;
        }

        // Add priority meta
        $order->update_meta_data('_priority_processing', 'yes');

        // Add fee only if it doesn't exist and amount > 0
        if ($fee_amount > 0) {
          $fee = new WC_Order_Item_Fee();
          $fee->set_name($fee_label);
          $fee->set_amount($fee_amount);
          $fee->set_total($fee_amount);
          $fee->set_order_id($order_id);
          $order->add_item($fee);

          error_log("WPP: Added priority fee of {$fee_amount} to order #{$order_id}");
        }

        // Add order note
        $order->add_order_note(
          sprintf(
            __('⚡ Priority processing added by %s. Fee: %s', 'woo-priority'),
            $current_user->display_name,
            wc_price($fee_amount)
          ),
          false
        );

        $message = __('Priority processing added successfully!', 'woo-priority');
      } else {
        // Remove priority processing

        // Remove priority meta
        $order->delete_meta_data('_priority_processing');

        // Remove priority fee
        $fees_to_remove = [];
        foreach ($order->get_fees() as $fee_id => $fee) {
          if (strpos($fee->get_name(), 'Priority') !== false || $fee->get_name() === $fee_label) {
            $fees_to_remove[] = $fee_id;
          }
        }

        foreach ($fees_to_remove as $fee_id) {
          $order->remove_item($fee_id);
        }

        // Add order note
        $order->add_order_note(
          sprintf(
            __('❌ Priority processing removed by %s', 'woo-priority'),
            $current_user->display_name
          ),
          false
        );

        $message = __('Priority processing removed successfully!', 'woo-priority');
      }

      // Recalculate totals
      $order->calculate_totals();
      $order->save();

      // Clear any caches
      if (function_exists('wc_delete_shop_order_transients')) {
        wc_delete_shop_order_transients($order_id);
      }

      // Trigger action for other plugins
      do_action('wpp_order_priority_toggled', $order_id, $action, $current_user->ID);

      // Clear statistics cache to ensure updated counts
      $wpp_instance = WooCommerce_Priority_Processing::instance();
      if ($wpp_instance && $wpp_instance->get_statistics()) {
        $wpp_instance->get_statistics()->clear_cache();
      }

      error_log("WPP: Priority processing {$action}ed for order #{$order_id} by user {$current_user->display_name}");

      wp_send_json_success([
        'message' => $message,
        'order_id' => $order_id,
        'action' => $action,
        'new_total' => $order->get_formatted_order_total(),
        'has_priority' => ($action === 'add')
      ]);
    } catch (Exception $e) {
      error_log('WPP Order Priority Toggle Error: ' . $e->getMessage());
      wp_send_json_error(__('An error occurred while updating the order', 'woo-priority'));
    }
  }

  /**
   * Enqueue scripts and styles for order admin pages
   */
  public function order_admin_scripts($hook)
  {
    global $post_type;
    $screen = get_current_screen();

    // Traditional order pages or HPOS order pages
    if (
      ($post_type === 'shop_order') ||
      ($screen && $screen->id === wc_get_page_screen_id('shop-order')) ||
      ($screen && strpos($screen->id, 'shop_order') !== false)
    ) {
      // Enqueue order admin styles
      wp_enqueue_style('wpp-order-admin', WPP_PLUGIN_URL . 'assets/css/order-admin.css', [], WPP_VERSION);
      wp_enqueue_script('wpp-order-admin', WPP_PLUGIN_URL . 'assets/js/order-admin.js', ['jquery'], WPP_VERSION, true);

      // Localize script with minimal parameters
      wp_localize_script('wpp-order-admin', 'wpp_order_admin', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'error_title' => __('Error:', 'woo-priority'),
        'connection_error' => __('Connection error. Please try again.', 'woo-priority')
      ]);
    }
  }
}
