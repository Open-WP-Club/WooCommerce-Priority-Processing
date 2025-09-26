<?php

/**
 * Frontend Shipping Handler
 * Manages shipping plugin integrations and declared value modifications
 */
class Frontend_Shipping
{
  public function __construct()
  {
    // SAFE shipping plugin integration - only adds metadata, doesn't modify calculations
    add_filter('woocommerce_cart_shipping_packages', [$this, 'add_priority_metadata_to_packages']);

    // Hook into specific shipping plugin API calls (non-intrusive)
    add_action('init', [$this, 'setup_shipping_plugin_hooks']);
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
