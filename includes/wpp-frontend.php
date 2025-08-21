<?php

class WPP_Frontend
{
  public function __construct()
  {
    // Classic WooCommerce checkout hooks
    add_action('woocommerce_review_order_after_cart_contents', [$this, 'add_priority_checkbox']);
    add_action('woocommerce_checkout_before_order_review', [$this, 'add_priority_checkbox_fallback']);

    // Additional fallback hooks for different themes/plugins
    add_action('woocommerce_checkout_order_review', [$this, 'add_priority_checkbox_fallback']);
    add_action('woocommerce_checkout_after_customer_details', [$this, 'add_priority_checkbox_fallback']);
    add_action('woocommerce_checkout_billing', [$this, 'add_priority_checkbox_fallback']);

    // AJAX and fee handling
    add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
    add_action('wp_ajax_wpp_update_priority', [$this, 'ajax_update_priority']);
    add_action('wp_ajax_nopriv_wpp_update_priority', [$this, 'ajax_update_priority']);

    // Fee hook for final order processing only
    add_action('woocommerce_cart_calculate_fees', [$this, 'add_priority_fee']);
    add_action('woocommerce_checkout_create_order', [$this, 'save_priority_to_order'], 10, 2);

    // SAFE shipping plugin integration - only adds metadata, doesn't modify calculations
    add_filter('woocommerce_cart_shipping_packages', [$this, 'add_priority_metadata_to_packages']);

    // Hook into specific shipping plugin API calls (non-intrusive)
    add_action('init', [$this, 'setup_shipping_plugin_hooks']);

    // Clear priority session when order is completed
    add_action('woocommerce_thankyou', [$this, 'clear_priority_session']);
    add_action('woocommerce_cart_emptied', [$this, 'clear_priority_session']);

    // Initialize session properly
    add_action('init', [$this, 'init_session']);
  }

  public function init_session()
  {
    if (!is_admin() && !defined('DOING_AJAX')) {
      if (WC()->session && !WC()->session->has_session()) {
        WC()->session->set_customer_session_cookie(true);
      }
    }
  }

  public function clear_priority_session($order_id = null)
  {
    if (WC()->session) {
      WC()->session->set('priority_processing', false);
    }
  }

  /**
   * SAFE: Only add metadata to packages - doesn't modify shipping calculations
   */
  public function add_priority_metadata_to_packages($packages)
  {
    // Only add metadata if priority processing is active
    if (!$this->is_priority_processing_active()) {
      return $packages;
    }

    $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

    if ($priority_fee <= 0) {
      return $packages;
    }

    error_log("WPP: Adding priority metadata to shipping packages (fee: {$priority_fee})");

    foreach ($packages as $package_key => &$package) {
      // SAFE: Only add metadata - don't modify contents_cost or line_total
      $packages[$package_key]['priority_processing'] = [
        'enabled' => true,
        'fee_amount' => $priority_fee,
        'service_level' => 'express',
        'requires_priority_handling' => true,
        'total_declared_value' => ($package['contents_cost'] ?? 0) + $priority_fee
      ];

      error_log("WPP: Added priority metadata to package {$package_key}");
    }

    return $packages;
  }

  /**
   * Setup hooks for specific shipping plugins (non-intrusive approach)
   */
  public function setup_shipping_plugin_hooks()
  {
    // Only hook into shipping APIs, not core WooCommerce calculations

    // FedEx plugin hooks
    add_filter('woocommerce_fedex_api_request', [$this, 'modify_fedex_api_request'], 10, 2);
    add_filter('fedex_woocommerce_shipping_api_request', [$this, 'modify_fedex_api_request'], 10, 2);

    // UPS plugin hooks  
    add_filter('woocommerce_ups_api_request', [$this, 'modify_ups_api_request'], 10, 2);
    add_filter('ups_woocommerce_shipping_api_request', [$this, 'modify_ups_api_request'], 10, 2);

    // USPS plugin hooks
    add_filter('woocommerce_usps_api_request', [$this, 'modify_usps_api_request'], 10, 2);

    // DHL plugin hooks
    add_filter('woocommerce_dhl_api_request', [$this, 'modify_dhl_api_request'], 10, 2);

    // Generic shipping calculator hooks (many plugins use these)
    add_filter('woocommerce_shipping_calculator_get_rates_request', [$this, 'modify_generic_shipping_request'], 10, 2);

    // TableRate and other plugins
    add_filter('woocommerce_shipping_method_get_rates_for_package', [$this, 'check_priority_for_rates'], 10, 2);
  }

  /**
   * Modify FedEx API requests to include priority fee in declared value
   */
  public function modify_fedex_api_request($request_data, $package_data = null)
  {
    if (!$this->is_priority_processing_active()) {
      return $request_data;
    }

    $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

    error_log("WPP: Modifying FedEx API request - adding priority fee: {$priority_fee}");

    // Modify declared value for insurance (common FedEx API structure)
    if (isset($request_data['RequestedShipment']['RequestedPackageLineItems'])) {
      foreach ($request_data['RequestedShipment']['RequestedPackageLineItems'] as $key => &$item) {
        if (isset($item['InsuredValue']['Amount'])) {
          $original_value = $item['InsuredValue']['Amount'];
          $request_data['RequestedShipment']['RequestedPackageLineItems'][$key]['InsuredValue']['Amount'] = $original_value + $priority_fee;

          error_log("WPP: FedEx declared value: {$original_value} -> " . ($original_value + $priority_fee));
        }
      }
    }

    // Also modify customs value for international shipments
    if (isset($request_data['RequestedShipment']['CustomsClearanceDetail']['CustomsValue']['Amount'])) {
      $original_customs = $request_data['RequestedShipment']['CustomsClearanceDetail']['CustomsValue']['Amount'];
      $request_data['RequestedShipment']['CustomsClearanceDetail']['CustomsValue']['Amount'] = $original_customs + $priority_fee;

      error_log("WPP: FedEx customs value: {$original_customs} -> " . ($original_customs + $priority_fee));
    }

    return $request_data;
  }

  /**
   * Modify UPS API requests to include priority fee
   */
  public function modify_ups_api_request($request_data, $package_data = null)
  {
    if (!$this->is_priority_processing_active()) {
      return $request_data;
    }

    $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

    error_log("WPP: Modifying UPS API request - adding priority fee: {$priority_fee}");

    // UPS API structure for declared value
    if (isset($request_data['Package']['PackageServiceOptions']['DeclaredValue']['MonetaryValue'])) {
      $original_value = $request_data['Package']['PackageServiceOptions']['DeclaredValue']['MonetaryValue'];
      $request_data['Package']['PackageServiceOptions']['DeclaredValue']['MonetaryValue'] = $original_value + $priority_fee;

      error_log("WPP: UPS declared value: {$original_value} -> " . ($original_value + $priority_fee));
    }

    return $request_data;
  }

  /**
   * Modify USPS API requests
   */
  public function modify_usps_api_request($request_data, $package_data = null)
  {
    if (!$this->is_priority_processing_active()) {
      return $request_data;
    }

    $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

    error_log("WPP: Modifying USPS API request - adding priority fee: {$priority_fee}");

    // USPS typically uses 'Value' field for declared value
    if (isset($request_data['Value'])) {
      $original_value = $request_data['Value'];
      $request_data['Value'] = $original_value + $priority_fee;

      error_log("WPP: USPS declared value: {$original_value} -> " . ($original_value + $priority_fee));
    }

    return $request_data;
  }

  /**
   * Modify DHL API requests
   */
  public function modify_dhl_api_request($request_data, $package_data = null)
  {
    if (!$this->is_priority_processing_active()) {
      return $request_data;
    }

    $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

    error_log("WPP: Modifying DHL API request - adding priority fee: {$priority_fee}");

    // DHL API structure varies, but commonly uses 'DeclaredValue'
    if (isset($request_data['DeclaredValue'])) {
      $original_value = $request_data['DeclaredValue'];
      $request_data['DeclaredValue'] = $original_value + $priority_fee;

      error_log("WPP: DHL declared value: {$original_value} -> " . ($original_value + $priority_fee));
    }

    return $request_data;
  }

  /**
   * Generic shipping calculator hook
   */
  public function modify_generic_shipping_request($request_data, $package = null)
  {
    if (!$this->is_priority_processing_active()) {
      return $request_data;
    }

    $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

    // Add priority information to generic requests
    $request_data['priority_processing'] = [
      'enabled' => true,
      'fee_amount' => $priority_fee,
      'service_level' => 'express'
    ];

    error_log("WPP: Added priority info to generic shipping request");

    return $request_data;
  }

  /**
   * Check for priority processing when calculating shipping rates
   */
  public function check_priority_for_rates($rates, $package)
  {
    if (!$this->is_priority_processing_active()) {
      return $rates;
    }

    // Add priority metadata to all shipping rates
    foreach ($rates as $rate_id => &$rate) {
      $rates[$rate_id]->add_meta_data('priority_processing', 'yes');
      $rates[$rate_id]->add_meta_data('priority_fee_amount', get_option('wpp_fee_amount', '5.00'));
    }

    error_log("WPP: Added priority metadata to " . count($rates) . " shipping rates");

    return $rates;
  }

  /**
   * Check if priority processing is currently active
   */
  private function is_priority_processing_active()
  {
    if (!WC()->session) {
      return false;
    }

    $priority = WC()->session->get('priority_processing', false);
    return ($priority === true || $priority === '1' || $priority === 1);
  }

  public function add_priority_checkbox()
  {
    if (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1') {
      return;
    }

    // Check user permissions
    if (!WPP_Permissions::can_access_priority_processing()) {
      WPP_Permissions::log_permission_check('add_priority_checkbox');
      return;
    }

    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $checkbox_label = get_option('wpp_checkbox_label', 'Priority processing + Express shipping');
    $description = get_option('wpp_description', '');
    $section_title = get_option('wpp_section_title', 'Express Options');

    // Simple session check
    $is_checked = false;
    if (WC()->session) {
      $session_priority = WC()->session->get('priority_processing', false);
      $is_checked = ($session_priority === true || $session_priority === '1' || $session_priority === 1);
    }

?>
    <tr class="wpp-priority-row">
      <td colspan="2" style="padding: 15px 0 10px 0;">
        <div id="wpp-priority-section">
          <h4>⚡ <?php echo esc_html($section_title); ?></h4>
          <label>
            <input type="checkbox" id="wpp_priority_checkbox" class="wpp-priority-checkbox"
              name="priority_processing" value="1" <?php checked($is_checked, true); ?> />
            <span>
              <strong>
                <?php echo esc_html($checkbox_label); ?>
                <span class="wpp-price">( + <?php echo wc_price($fee_amount); ?>)</span>
              </strong>
              <?php if ($description): ?>
                <small><?php echo esc_html($description); ?></small>
              <?php endif; ?>
            </span>
          </label>
        </div>
      </td>
    </tr>
  <?php
  }

  public function add_priority_checkbox_fallback()
  {
    if (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1') {
      return;
    }

    // Check user permissions
    if (!WPP_Permissions::can_access_priority_processing()) {
      WPP_Permissions::log_permission_check('add_priority_checkbox_fallback');
      return;
    }

    $fee_amount = get_option('wpp_fee_amount', '5.00');
    $checkbox_label = get_option('wpp_checkbox_label', 'Priority processing + Express shipping');
    $description = get_option('wpp_description', '');
    $section_title = get_option('wpp_section_title', 'Express Options');

    // Simple session check
    $is_checked = false;
    if (WC()->session) {
      $session_priority = WC()->session->get('priority_processing', false);
      $is_checked = ($session_priority === true || $session_priority === '1' || $session_priority === 1);
    }

  ?>
    <div id="wpp-priority-option-fallback">
      <h4>⚡ <?php echo esc_html($section_title); ?></h4>
      <label>
        <input type="checkbox" id="wpp_priority_checkbox_fallback" class="wpp-priority-checkbox"
          name="priority_processing" value="1" <?php checked($is_checked, true); ?> />
        <span>
          <strong>
            <?php echo esc_html($checkbox_label); ?>:
            <span class="wpp-price"><?php echo wc_price($fee_amount); ?></span>
          </strong>
          <?php if ($description): ?>
            <small><?php echo esc_html($description); ?></small>
          <?php endif; ?>
        </span>
      </label>
    </div>
    <script>
      jQuery(function($) {
        // Remove fallback if main section exists
        if ($('#wpp-priority-section').length > 0) {
          $('#wpp-priority-option-fallback').remove();
        }
      });
    </script>
  <?php
  }

  public function frontend_scripts()
  {
    if (is_checkout()) {
      // Enqueue frontend CSS for priority processing styling
      wp_enqueue_style(
        'wpp-frontend',
        WPP_PLUGIN_URL . 'assets/css/frontend.css',
        ['woocommerce-general'],
        WPP_VERSION
      );

      // Check if using blocks
      $using_blocks = has_block('woocommerce/checkout');

      if ($using_blocks) {
        wp_enqueue_script('wpp-frontend-blocks', WPP_PLUGIN_URL . 'assets/js/frontend-blocks.js', ['jquery'], WPP_VERSION, true);
        wp_localize_script('wpp-frontend-blocks', 'wpp_ajax', [
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('wpp_nonce'),
          'fee_amount' => get_option('wpp_fee_amount', '5.00'),
          'checkbox_label' => get_option('wpp_checkbox_label', 'Priority processing + Express shipping'),
          'description' => get_option('wpp_description', ''),
          'section_title' => get_option('wpp_section_title', 'Express Options'),
          'using_blocks' => true
        ]);
      } else {
        wp_enqueue_script('wpp-frontend', WPP_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], WPP_VERSION, true);
        wp_localize_script('wpp-frontend', 'wpp_ajax', [
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('wpp_nonce'),
          'using_blocks' => false,
          'fee_amount' => get_option('wpp_fee_amount', '5.00'),
          'fee_label' => get_option('wpp_fee_label', 'Priority Processing & Express Shipping')
        ]);
      }
    }
  }

  public function ajax_update_priority()
  {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpp_nonce')) {
      wp_send_json_error('Invalid nonce');
      return;
    }

    // Check user permissions
    if (!WPP_Permissions::can_access_priority_processing()) {
      WPP_Permissions::log_permission_check('ajax_update_priority');
      wp_send_json_error('Permission denied');
      return;
    }

    if (!WC()->session) {
      wp_send_json_error('WooCommerce session not available');
      return;
    }

    $priority = isset($_POST['priority']) && $_POST['priority'] === '1';
    $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
    $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

    // Store in session for final order processing
    WC()->session->set('priority_processing', $priority);

    error_log("WPP: AJAX update - Priority set to: " . ($priority ? 'YES' : 'NO'));

    // Generate custom checkout HTML with calculated totals
    $cart_subtotal = WC()->cart->get_subtotal();
    $cart_tax = WC()->cart->get_total_tax();
    $shipping_total = WC()->cart->get_shipping_total();

    // Calculate new total with or without priority fee
    $priority_fee_amount = $priority ? $fee_amount : 0;
    $new_total = $cart_subtotal + $cart_tax + $shipping_total + $priority_fee_amount;

    // Generate custom checkout HTML with our calculated totals
    ob_start();
  ?>
    <table class="shop_table woocommerce-checkout-review-order-table">
      <thead>
        <tr>
          <th class="product-name"><?php _e('Product', 'woocommerce'); ?></th>
          <th class="product-total"><?php _e('Subtotal', 'woocommerce'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
          $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
          if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
        ?>
            <tr class="<?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>">
              <td class="product-name">
                <?php echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key)); ?>
                <strong class="product-quantity"><?php echo sprintf('&times;&nbsp;%s', $cart_item['quantity']); ?></strong>
              </td>
              <td class="product-total">
                <?php echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); ?>
              </td>
            </tr>
        <?php
          }
        }

        // Add checkbox row to preserve it - only if user has permission
        $checkbox_label = get_option('wpp_checkbox_label', 'Priority processing + Express shipping');
        $section_title = get_option('wpp_section_title', 'Express Options');
        $description = get_option('wpp_description', '');
        ?>
        <tr class="wpp-priority-row">
          <td colspan="2" style="padding: 15px 0 10px 0;">
            <div id="wpp-priority-section">
              <h4>⚡ <?php echo esc_html($section_title); ?></h4>
              <label>
                <input type="checkbox" id="wpp_priority_checkbox" class="wpp-priority-checkbox"
                  name="priority_processing" value="1" <?php checked($priority, true); ?> />
                <span>
                  <strong>
                    <?php echo esc_html($checkbox_label); ?>
                    <span class="wpp-price">( + <?php echo wc_price($fee_amount); ?>)</span>
                  </strong>
                  <?php if ($description): ?>
                    <small><?php echo esc_html($description); ?></small>
                  <?php endif; ?>
                </span>
              </label>
            </div>
          </td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="cart-subtotal">
          <th><?php _e('Subtotal', 'woocommerce'); ?></th>
          <td><?php echo wc_price($cart_subtotal); ?></td>
        </tr>

        <?php if ($priority && $priority_fee_amount > 0): ?>
          <tr class="priority-fee-row">
            <th>⚡ <?php echo esc_html($fee_label); ?></th>
            <td><?php echo wc_price($priority_fee_amount); ?></td>
          </tr>
        <?php endif; ?>

        <tr class="order-total">
          <th><?php _e('Total', 'woocommerce'); ?></th>
          <td><strong><?php echo wc_price($new_total); ?></strong></td>
        </tr>
      </tfoot>
    </table>
<?php
    $checkout_html = ob_get_clean();

    wp_send_json_success([
      'fragments' => ['.woocommerce-checkout-review-order-table' => $checkout_html],
      'debug' => [
        'priority' => $priority,
        'fee_amount' => $priority_fee_amount,
        'new_total' => wc_price($new_total),
        'permission_check' => 'passed',
        'shipping_integration' => 'safe_mode_active'
      ]
    ]);
  }

  public function add_priority_fee()
  {
    if (!is_checkout() || (get_option('wpp_enabled') !== 'yes' && get_option('wpp_enabled') !== '1')) {
      return;
    }

    // Check user permissions
    if (!WPP_Permissions::can_access_priority_processing()) {
      WPP_Permissions::log_permission_check('add_priority_fee');
      return;
    }

    if (!WC()->session) {
      return;
    }

    $priority = WC()->session->get('priority_processing', false);
    $should_add_fee = ($priority === true || $priority === 1 || $priority === '1');

    if ($should_add_fee) {
      $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));
      $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

      // Check if fee already exists to avoid duplicates
      $existing_fees = WC()->cart->get_fees();
      $fee_exists = false;

      foreach ($existing_fees as $fee) {
        if ($fee->name === $fee_label) {
          $fee_exists = true;
          break;
        }
      }

      if (!$fee_exists && $fee_amount > 0) {
        WC()->cart->add_fee($fee_label, $fee_amount);
        error_log("WPP: Priority fee added to cart: {$fee_amount}");
      }
    }
  }

  public function save_priority_to_order($order, $data)
  {
    if (!WC()->session) {
      return;
    }

    $priority = WC()->session->get('priority_processing', false);
    if ($priority === true || $priority === 1 || $priority === '1') {
      $fee_amount = floatval(get_option('wpp_fee_amount', '5.00'));

      // Save priority meta data only - WooCommerce handles fee transfer automatically
      $order->update_meta_data('_priority_processing', 'yes');

      // Add shipping-specific meta data for shipping plugin integration
      $order->update_meta_data('_requires_express_shipping', 'yes');
      $order->update_meta_data('_priority_fee_amount', $fee_amount);
      $order->update_meta_data('_priority_service_level', 'express');

      // Check if order already has the priority processing fee from this plugin
      $order_fees = $order->get_fees();
      $priority_fee_exists = false;
      $fee_label = get_option('wpp_fee_label', 'Priority Processing & Express Shipping');

      foreach ($order_fees as $fee) {
        if ($fee->get_name() === $fee_label) {
          $priority_fee_exists = true;
          error_log('WPP: Priority fee already exists in order: ' . $fee->get_name());
          break;
        }
      }

      if ($priority_fee_exists) {
        error_log('WPP: WARNING - Priority fee already found in order #' . $order->get_id());
      }

      // Fire action hook for shipping plugins that might want to integrate
      do_action('wpp_priority_order_created', $order, $fee_amount);

      $order->save();

      error_log("WPP: Priority processing saved to order #{$order->get_id()} with fee amount: {$fee_amount}");
    }
  }
}
