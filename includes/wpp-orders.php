<?php

class WPP_Orders
{
  public function __construct()
  {
    add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_priority_in_admin']);
    add_action('admin_head', [$this, 'orders_list_styles']);
    add_action('manage_shop_order_posts_custom_column', [$this, 'modify_order_number_display'], 10, 2);
    add_action('woocommerce_shop_order_list_table_custom_column', [$this, 'modify_order_number_display_hpos'], 10, 2);
  }

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

  public function modify_order_number_display_hpos($column, $order)
  {
    if ($column === 'order_number') {
      if ($order && $order->get_meta('_priority_processing') === 'yes') {
        // Add hidden marker that will be processed by JavaScript
        echo '<span class="wpp-priority-marker" data-order="' . esc_attr($order->get_order_number()) . '" style="display:none;"></span>';
      }
    }
  }

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
}
