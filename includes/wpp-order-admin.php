<?php

/**
 * WooCommerce Priority Processing - Order Admin Functions
 * Handles admin functionality for individual orders
 */
class WPP_Order_Admin
{
  public function __construct()
  {
    // Order admin functionality
    add_action('add_meta_boxes', [$this, 'add_order_meta_box']);
    add_action('wp_ajax_wpp_toggle_order_priority', [$this, 'ajax_toggle_order_priority']);
    add_action('admin_enqueue_scripts', [$this, 'order_admin_scripts']);
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
      'normal', // Changed from 'side' to 'normal' for better positioning
      'default' // Changed from 'high' to 'default' to position after notes
    );

    // HPOS orders
    if (class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
      add_meta_box(
        'wpp_order_priority',
        __('Priority Processing', 'woo-priority'),
        [$this, 'order_priority_meta_box'],
        wc_get_page_screen_id('shop-order'),
        'normal',
        'default'
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

    <div id="wpp-order-priority-container" class="wpp-order-priority-wrapper">
      <!-- Status Section -->
      <div class="wpp-priority-status-section">
        <div class="wpp-status-grid">
          <div class="wpp-status-card <?php echo $has_priority ? 'wpp-status-active' : 'wpp-status-inactive'; ?>">
            <div class="wpp-status-icon">
              <?php echo $has_priority ? '✅' : '❌'; ?>
            </div>
            <div class="wpp-status-content">
              <h4><?php echo $has_priority ? __('Priority Processing Active', 'woo-priority') : __('Standard Processing', 'woo-priority'); ?></h4>
              <?php if ($has_priority && $existing_fee): ?>
                <p class="wpp-fee-display"><?php printf(__('Fee: %s'), wc_price($existing_fee->get_total())); ?></p>
              <?php else: ?>
                <p class="wpp-status-description">
                  <?php echo $has_priority ?
                    __('This order has priority processing', 'woo-priority') :
                    __('This order uses standard processing', 'woo-priority'); ?>
                </p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Order Info -->
          <div class="wpp-order-info-card">
            <div class="wpp-info-item">
              <span class="wpp-info-label"><?php _e('Order Status:', 'woo-priority'); ?></span>
              <span class="wpp-info-value"><?php echo ucfirst($order_status); ?></span>
            </div>
            <div class="wpp-info-item">
              <span class="wpp-info-label"><?php _e('Current Total:', 'woo-priority'); ?></span>
              <span class="wpp-info-value"><?php echo $order->get_formatted_order_total(); ?></span>
            </div>
            <?php if (!$has_priority): ?>
              <div class="wpp-info-item">
                <span class="wpp-info-label"><?php _e('With Priority:', 'woo-priority'); ?></span>
                <span class="wpp-info-value">
                  <?php echo wc_price($order->get_total() + floatval($fee_amount)); ?>
                </span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Actions Section -->
      <?php if ($can_modify): ?>
        <div class="wpp-priority-actions-section">
          <div class="wpp-actions-grid">
            <?php if ($has_priority): ?>
              <button type="button" id="wpp-remove-priority" class="button button-large wpp-remove-button"
                data-order-id="<?php echo $order_id; ?>">
                <span class="dashicons dashicons-minus-alt"></span>
                <?php _e('Remove Priority Processing', 'woo-priority'); ?>
              </button>
            <?php else: ?>
              <button type="button" id="wpp-add-priority" class="button button-large button-primary wpp-add-button"
                data-order-id="<?php echo $order_id; ?>">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Add Priority Processing', 'woo-priority'); ?>
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="wpp-disabled-section">
          <div class="wpp-disabled-notice">
            <span class="dashicons dashicons-lock"></span>
            <div>
              <strong><?php _e('Cannot Modify Priority Processing', 'woo-priority'); ?></strong>
              <p><?php _e('Orders with status "completed", "refunded", or "cancelled" cannot be modified.', 'woo-priority'); ?></p>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Loading State -->
      <div id="wpp-loading-overlay" class="wpp-loading-overlay" style="display: none;">
        <div class="wpp-loading-content">
          <span class="spinner is-active"></span>
          <h4><?php _e('Processing...', 'woo-priority'); ?></h4>
          <p><?php _e('Updating order with priority processing changes', 'woo-priority'); ?></p>
        </div>
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

        // Add priority meta
        $order->update_meta_data('_priority_processing', 'yes');

        // Add fee if not exists
        $existing_fee = null;
        foreach ($order->get_fees() as $fee) {
          if (strpos($fee->get_name(), 'Priority') !== false || $fee->get_name() === $fee_label) {
            $existing_fee = $fee;
            break;
          }
        }

        if (!$existing_fee && $fee_amount > 0) {
          $fee = new WC_Order_Item_Fee();
          $fee->set_name($fee_label);
          $fee->set_amount($fee_amount);
          $fee->set_total($fee_amount);
          $fee->set_order_id($order_id);
          $order->add_item($fee);
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
      wp_enqueue_style('wpp-order-admin', WPP_PLUGIN_URL . 'assets/order-admin.css', [], WPP_VERSION);
      wp_enqueue_script('wpp-order-admin', WPP_PLUGIN_URL . 'assets/order-admin.js', ['jquery'], WPP_VERSION, true);

      // Localize script
      wp_localize_script('wpp-order-admin', 'wpp_order_admin', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'confirm_add' => __('Add priority processing to this order? This will add the fee and recalculate totals.', 'woo-priority'),
        'confirm_remove' => __('Remove priority processing from this order? This will remove the fee and recalculate totals.', 'woo-priority'),
        'error_title' => __('Error:', 'woo-priority'),
        'connection_error' => __('Connection error. Please try again.', 'woo-priority'),
        'processing_text' => __('Processing...', 'woo-priority'),
        'success_reload_delay' => 2000
      ]);
    }
  }
}
