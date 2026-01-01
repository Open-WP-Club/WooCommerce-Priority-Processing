<?php

/**
 * Frontend Shipping Handler
 * Manages shipping plugin integrations and declared value modifications
 */
class Frontend_Shipping
{
  public function __construct()
  {
    // Hook into specific shipping plugin API calls (safe, non-intrusive)
    add_action('init', [$this, 'setup_shipping_plugin_hooks']);

    // Add early hook to ensure session is set before packages are built
    add_action('woocommerce_checkout_update_order_review', [$this, 'ensure_priority_session_before_shipping'], 5);

    // MAIN APPROACH: Add priority fee directly to shipping rates
    add_filter('woocommerce_package_rates', [$this, 'add_priority_to_shipping_rates'], 100, 2);
  }

  /**
   * Ensure priority session is set before shipping packages are calculated
   * This runs early in the checkout update process to avoid shipping methods disappearing
   */
  public function ensure_priority_session_before_shipping($post_data = '')
  {
    if (!WC()->session) {
      return;
    }

    // Parse the post data if it's a string
    $posted_data = [];
    if (is_string($post_data) && !empty($post_data)) {
      parse_str($post_data, $posted_data);
    } elseif (is_array($post_data)) {
      $posted_data = $post_data;
    }

    // Check if priority processing checkbox is checked in the posted data
    $priority_enabled = false;

    if (isset($posted_data['priority_processing']) && $posted_data['priority_processing'] === '1') {
      $priority_enabled = true;
    } elseif (isset($_POST['priority_processing']) && $_POST['priority_processing'] === '1') {
      $priority_enabled = true;
    }

    // Update session BEFORE shipping is calculated
    WC()->session->set('priority_processing', $priority_enabled);

    error_log("WPP: Priority session set to " . ($priority_enabled ? 'true' : 'false') . " before shipping calculation");
  }

  /**
   * Add priority fee directly to shipping rates
   * This is the main method - fee is included in shipping cost, not as separate line item
   */
  public function add_priority_to_shipping_rates($rates, $package)
  {
    // Only modify rates if priority processing is active
    if (!$this->is_priority_processing_active()) {
      return $rates;
    }

    $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

    if ($priority_fee <= 0) {
      return $rates;
    }

    error_log("WPP: Adding {$priority_fee} to " . count($rates) . " shipping rates");

    // Modify each shipping rate to include the priority fee
    foreach ($rates as $rate_key => $rate) {
      // Add the priority fee to the shipping cost
      $original_cost = $rate->cost;
      $rates[$rate_key]->cost = $original_cost + $priority_fee;

      // Optional: Update the label to indicate priority is included
      $show_priority_in_label = apply_filters('wpp_show_priority_in_shipping_label', false);
      if ($show_priority_in_label) {
        $rates[$rate_key]->label = $rate->label . ' ' . __('(Priority)', 'woo-priority');
      }

      // Add metadata for tracking
      $rates[$rate_key]->add_meta_data('wpp_priority_processing', 'yes', true);
      $rates[$rate_key]->add_meta_data('wpp_priority_fee_added', $priority_fee, true);
      $rates[$rate_key]->add_meta_data('wpp_original_cost', $original_cost, true);

      error_log("WPP: Modified rate '{$rate->label}': {$original_cost} -> " . $rates[$rate_key]->cost);
    }

    return $rates;
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
   * This is safe and doesn't break shipping calculations
   */
  public function modify_fedex_api_request($request_data, $package_data = null)
  {
    if (!$this->is_priority_processing_active()) {
      return $request_data;
    }

    $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

    if ($priority_fee <= 0) {
      return $request_data;
    }

    error_log("WPP: Modifying FedEx API request - adding priority fee: {$priority_fee}");

    try {
      // Modify declared value for insurance (common FedEx API structure)
      if (isset($request_data['RequestedShipment']['RequestedPackageLineItems'])) {
        foreach ($request_data['RequestedShipment']['RequestedPackageLineItems'] as $key => &$item) {
          if (isset($item['InsuredValue']['Amount'])) {
            $original_value = floatval($item['InsuredValue']['Amount']);
            $request_data['RequestedShipment']['RequestedPackageLineItems'][$key]['InsuredValue']['Amount'] = $original_value + $priority_fee;

            error_log("WPP: FedEx declared value: {$original_value} -> " . ($original_value + $priority_fee));
          }
        }
      }

      // Also modify customs value for international shipments
      if (isset($request_data['RequestedShipment']['CustomsClearanceDetail']['CustomsValue']['Amount'])) {
        $original_customs = floatval($request_data['RequestedShipment']['CustomsClearanceDetail']['CustomsValue']['Amount']);
        $request_data['RequestedShipment']['CustomsClearanceDetail']['CustomsValue']['Amount'] = $original_customs + $priority_fee;

        error_log("WPP: FedEx customs value: {$original_customs} -> " . ($original_customs + $priority_fee));
      }
    } catch (Exception $e) {
      error_log("WPP: Error modifying FedEx request: " . $e->getMessage());
    }

    return $request_data;
  }

  /**
   * Modify UPS API requests to include priority fee
   * This is safe and doesn't break shipping calculations
   */
  public function modify_ups_api_request($request_data, $package_data = null)
  {
    if (!$this->is_priority_processing_active()) {
      return $request_data;
    }

    $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

    if ($priority_fee <= 0) {
      return $request_data;
    }

    error_log("WPP: Modifying UPS API request - adding priority fee: {$priority_fee}");

    try {
      // UPS API structure for declared value
      if (isset($request_data['Package']['PackageServiceOptions']['DeclaredValue']['MonetaryValue'])) {
        $original_value = floatval($request_data['Package']['PackageServiceOptions']['DeclaredValue']['MonetaryValue']);
        $request_data['Package']['PackageServiceOptions']['DeclaredValue']['MonetaryValue'] = $original_value + $priority_fee;

        error_log("WPP: UPS declared value: {$original_value} -> " . ($original_value + $priority_fee));
      }
    } catch (Exception $e) {
      error_log("WPP: Error modifying UPS request: " . $e->getMessage());
    }

    return $request_data;
  }

  /**
   * Modify USPS API requests
   * This is safe and doesn't break shipping calculations
   */
  public function modify_usps_api_request($request_data, $package_data = null)
  {
    if (!$this->is_priority_processing_active()) {
      return $request_data;
    }

    $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

    if ($priority_fee <= 0) {
      return $request_data;
    }

    error_log("WPP: Modifying USPS API request - adding priority fee: {$priority_fee}");

    try {
      // USPS typically uses 'Value' field for declared value
      if (isset($request_data['Value'])) {
        $original_value = floatval($request_data['Value']);
        $request_data['Value'] = $original_value + $priority_fee;

        error_log("WPP: USPS declared value: {$original_value} -> " . ($original_value + $priority_fee));
      }
    } catch (Exception $e) {
      error_log("WPP: Error modifying USPS request: " . $e->getMessage());
    }

    return $request_data;
  }

  /**
   * Modify DHL API requests
   * This is safe and doesn't break shipping calculations
   */
  public function modify_dhl_api_request($request_data, $package_data = null)
  {
    if (!$this->is_priority_processing_active()) {
      return $request_data;
    }

    $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

    if ($priority_fee <= 0) {
      return $request_data;
    }

    error_log("WPP: Modifying DHL API request - adding priority fee: {$priority_fee}");

    try {
      // DHL API structure varies, but commonly uses 'DeclaredValue'
      if (isset($request_data['DeclaredValue'])) {
        $original_value = floatval($request_data['DeclaredValue']);
        $request_data['DeclaredValue'] = $original_value + $priority_fee;

        error_log("WPP: DHL declared value: {$original_value} -> " . ($original_value + $priority_fee));
      }
    } catch (Exception $e) {
      error_log("WPP: Error modifying DHL request: " . $e->getMessage());
    }

    return $request_data;
  }

  /**
   * Generic shipping calculator hook
   * This is safe and doesn't break shipping calculations
   */
  public function modify_generic_shipping_request($request_data, $package = null)
  {
    if (!$this->is_priority_processing_active()) {
      return $request_data;
    }

    $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

    if ($priority_fee <= 0) {
      return $request_data;
    }

    try {
      // Add priority information to generic requests (doesn't modify core data)
      if (!isset($request_data['wpp_priority_processing'])) {
        $request_data['wpp_priority_processing'] = [
          'enabled' => true,
          'fee_amount' => $priority_fee,
          'service_level' => 'express'
        ];

        error_log("WPP: Added priority info to generic shipping request");
      }
    } catch (Exception $e) {
      error_log("WPP: Error modifying generic shipping request: " . $e->getMessage());
    }

    return $request_data;
  }

  /**
   * Check for priority processing when calculating shipping rates
   * This method is very defensive to avoid breaking shipping calculations
   */
  public function check_priority_for_rates($rates, $package)
  {
    if (!$this->is_priority_processing_active()) {
      return $rates;
    }

    try {
      $priority_fee = floatval(get_option('wpp_fee_amount', '5.00'));

      if ($priority_fee <= 0) {
        return $rates;
      }

      // Add priority metadata to all shipping rates (doesn't modify rate costs)
      foreach ($rates as $rate_id => $rate) {
        if (is_object($rate) && method_exists($rate, 'add_meta_data')) {
          $rates[$rate_id]->add_meta_data('wpp_priority_processing', 'yes', true);
          $rates[$rate_id]->add_meta_data('wpp_priority_fee_amount', $priority_fee, true);
        }
      }

      error_log("WPP: Added priority metadata to " . count($rates) . " shipping rates");
    } catch (Exception $e) {
      error_log("WPP: Error in check_priority_for_rates: " . $e->getMessage());
    }

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

  /**
   * Get shipping integration status
   */
  public function get_integration_status()
  {
    $active_integrations = [];
    $available_integrations = [
      'fedex' => 'FedEx',
      'ups' => 'UPS',
      'usps' => 'USPS',
      'dhl' => 'DHL',
      'generic' => 'Generic Shipping Calculators'
    ];

    foreach ($available_integrations as $key => $name) {
      $active_integrations[$key] = [
        'name' => $name,
        'active' => $this->is_integration_active($key),
        'priority_enabled' => $this->is_priority_processing_active()
      ];
    }

    return $active_integrations;
  }

  /**
   * Check if specific shipping integration is active
   */
  private function is_integration_active($integration_type)
  {
    // This could be expanded to check for specific plugin activations
    switch ($integration_type) {
      case 'fedex':
        return class_exists('WC_Shipping_Fedex') || has_filter('woocommerce_fedex_api_request');
      case 'ups':
        return class_exists('WC_Shipping_UPS') || has_filter('woocommerce_ups_api_request');
      case 'usps':
        return class_exists('WC_Shipping_USPS') || has_filter('woocommerce_usps_api_request');
      case 'dhl':
        return class_exists('WC_Shipping_DHL') || has_filter('woocommerce_dhl_api_request');
      case 'generic':
        return true; // Generic hooks are always available
      default:
        return false;
    }
  }

  /**
   * Get priority fee for shipping calculations
   */
  public function get_priority_fee()
  {
    return floatval(get_option('wpp_fee_amount', '5.00'));
  }

  /**
   * Get shipping metadata for priority orders
   */
  public function get_priority_shipping_metadata()
  {
    return [
      'service_level' => 'express',
      'requires_priority_handling' => true,
      'fee_amount' => $this->get_priority_fee(),
      'declared_value_adjustment' => $this->get_priority_fee()
    ];
  }
}
