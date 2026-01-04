<?php
/**
 * Frontend Shipping Handler
 * Manages shipping plugin integrations and declared value modifications
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.0.0
 */

declare(strict_types=1);

/**
 * Frontend Shipping Class
 *
 * @since 1.0.0
 */
class Frontend_Shipping {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Hook into specific shipping plugin API calls (safe, non-intrusive).
		add_action( 'init', array( $this, 'setup_shipping_plugin_hooks' ) );

		// Add early hook to ensure session is set before packages are built.
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'ensure_priority_session_before_shipping' ), 5 );

		// NOTE: Priority fee is now added as a separate line item via Frontend_Fees class.
		// We no longer add it to shipping rates to keep it visible and separate.
		// Hook kept for potential future use or metadata tracking.
		add_filter( 'woocommerce_package_rates', array( $this, 'add_priority_metadata_to_rates' ), 100, 2 );
	}

	/**
	 * Ensure priority session is set before shipping packages are calculated
	 * This runs early in the checkout update process to avoid shipping methods disappearing
	 *
	 * @since 1.0.0
	 * @param string|array<string, mixed> $post_data Post data from checkout update.
	 * @return void
	 */
	public function ensure_priority_session_before_shipping( $post_data = '' ): void {
		if ( ! WC()->session ) {
			return;
		}

		// Parse the post data if it's a string.
		$posted_data = array();
		if ( is_string( $post_data ) && ! empty( $post_data ) ) {
			parse_str( $post_data, $posted_data );
			// Sanitize all parsed values.
			$posted_data = array_map( 'sanitize_text_field', $posted_data );
		} elseif ( is_array( $post_data ) ) {
			// Sanitize array values.
			$posted_data = array_map( 'sanitize_text_field', $post_data );
		}

		// Check if priority processing checkbox is checked in the posted data.
		$priority_enabled = false;

		if ( isset( $posted_data['priority_processing'] ) && $posted_data['priority_processing'] === '1' ) {
			$priority_enabled = true;
		} elseif ( isset( $_POST['priority_processing'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$priority_processing = sanitize_text_field( wp_unslash( $_POST['priority_processing'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $priority_processing === '1' ) {
				$priority_enabled = true;
			}
		}

		// Update session BEFORE shipping is calculated.
		WC()->session->set( 'priority_processing', $priority_enabled );

		$this->log_debug( 'WPP: Priority session set to ' . ( $priority_enabled ? 'true' : 'false' ) . ' before shipping calculation' );
	}

	/**
	 * Add priority metadata to shipping rates (no longer modifies cost)
	 * Fee is now added as a separate cart line item in Frontend_Fees class
	 *
	 * @since 1.0.0
	 * @since 1.4.2 Changed to only add metadata, not modify shipping cost
	 * @param array<string, \WC_Shipping_Rate> $rates   Shipping rates.
	 * @param array<string, mixed>             $package Package data.
	 * @return array<string, \WC_Shipping_Rate> Rates with priority metadata
	 */
	public function add_priority_metadata_to_rates( array $rates, array $package ): array {
		// Only add metadata if priority processing is active.
		if ( ! Core_Permissions::is_priority_active() ) {
			return $rates;
		}

		$priority_fee = floatval( get_option( 'wpp_fee_amount', '5.00' ) );

		if ( $priority_fee <= 0 ) {
			return $rates;
		}

		$this->log_debug( 'WPP: Adding priority metadata to ' . count( $rates ) . ' shipping rates' );

		// Add metadata to shipping rates for tracking (don't modify cost).
		foreach ( $rates as $rate_key => $rate ) {
			// Add metadata for tracking (cost is NOT modified).
			$rates[ $rate_key ]->add_meta_data( 'wpp_priority_processing', 'yes', true );
			$rates[ $rate_key ]->add_meta_data( 'wpp_priority_fee_amount', $priority_fee, true );
			$rates[ $rate_key ]->add_meta_data( 'wpp_requires_express', 'yes', true );

			$this->log_debug( "WPP: Added priority metadata to rate '{$rate->label}'" );
		}

		return $rates;
	}

	/**
	 * Setup hooks for specific shipping plugins (non-intrusive approach)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function setup_shipping_plugin_hooks(): void {
		// Only hook into shipping APIs, not core WooCommerce calculations.

		// FedEx plugin hooks.
		add_filter( 'woocommerce_fedex_api_request', array( $this, 'modify_fedex_api_request' ), 10, 2 );
		add_filter( 'fedex_woocommerce_shipping_api_request', array( $this, 'modify_fedex_api_request' ), 10, 2 );

		// UPS plugin hooks.
		add_filter( 'woocommerce_ups_api_request', array( $this, 'modify_ups_api_request' ), 10, 2 );
		add_filter( 'ups_woocommerce_shipping_api_request', array( $this, 'modify_ups_api_request' ), 10, 2 );

		// USPS plugin hooks.
		add_filter( 'woocommerce_usps_api_request', array( $this, 'modify_usps_api_request' ), 10, 2 );

		// DHL plugin hooks.
		add_filter( 'woocommerce_dhl_api_request', array( $this, 'modify_dhl_api_request' ), 10, 2 );

		// Generic shipping calculator hooks (many plugins use these).
		add_filter( 'woocommerce_shipping_calculator_get_rates_request', array( $this, 'modify_generic_shipping_request' ), 10, 2 );

		// TableRate and other plugins.
		add_filter( 'woocommerce_shipping_method_get_rates_for_package', array( $this, 'check_priority_for_rates' ), 10, 2 );
	}

	/**
	 * Modify FedEx API requests to include priority fee in declared value
	 * This is safe and doesn't break shipping calculations
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>      $request_data Request data for FedEx API.
	 * @param array<string, mixed>|null $package_data Package data.
	 * @return array<string, mixed> Modified request data
	 */
	public function modify_fedex_api_request( array $request_data, ?array $package_data = null ): array {
		if ( ! Core_Permissions::is_priority_active() ) {
			return $request_data;
		}

		$priority_fee = floatval( get_option( 'wpp_fee_amount', '5.00' ) );

		if ( $priority_fee <= 0 ) {
			return $request_data;
		}

		$this->log_debug( "WPP: Modifying FedEx API request - adding priority fee: {$priority_fee}" );

		try {
			// Modify declared value for insurance (common FedEx API structure).
			if ( isset( $request_data['RequestedShipment']['RequestedPackageLineItems'] ) ) {
				foreach ( $request_data['RequestedShipment']['RequestedPackageLineItems'] as $key => &$item ) {
					if ( isset( $item['InsuredValue']['Amount'] ) ) {
						$original_value                                                                                   = floatval( $item['InsuredValue']['Amount'] );
						$request_data['RequestedShipment']['RequestedPackageLineItems'][ $key ]['InsuredValue']['Amount'] = $original_value + $priority_fee;

						$this->log_debug( 'WPP: FedEx declared value: ' . $original_value . ' -> ' . ( $original_value + $priority_fee ) );
					}
				}
			}

			// Also modify customs value for international shipments.
			if ( isset( $request_data['RequestedShipment']['CustomsClearanceDetail']['CustomsValue']['Amount'] ) ) {
				$original_customs                                                                     = floatval( $request_data['RequestedShipment']['CustomsClearanceDetail']['CustomsValue']['Amount'] );
				$request_data['RequestedShipment']['CustomsClearanceDetail']['CustomsValue']['Amount'] = $original_customs + $priority_fee;

				$this->log_debug( 'WPP: FedEx customs value: ' . $original_customs . ' -> ' . ( $original_customs + $priority_fee ) );
			}
		} catch ( Exception $e ) {
			$this->log_debug( 'WPP: Error modifying FedEx request: ' . $e->getMessage() );
		}

		return $request_data;
	}

	/**
	 * Modify UPS API requests to include priority fee
	 * This is safe and doesn't break shipping calculations
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>      $request_data Request data for UPS API.
	 * @param array<string, mixed>|null $package_data Package data.
	 * @return array<string, mixed> Modified request data
	 */
	public function modify_ups_api_request( array $request_data, ?array $package_data = null ): array {
		if ( ! Core_Permissions::is_priority_active() ) {
			return $request_data;
		}

		$priority_fee = floatval( get_option( 'wpp_fee_amount', '5.00' ) );

		if ( $priority_fee <= 0 ) {
			return $request_data;
		}

		$this->log_debug( "WPP: Modifying UPS API request - adding priority fee: {$priority_fee}" );

		try {
			// UPS API structure for declared value.
			if ( isset( $request_data['Package']['PackageServiceOptions']['DeclaredValue']['MonetaryValue'] ) ) {
				$original_value                                                                       = floatval( $request_data['Package']['PackageServiceOptions']['DeclaredValue']['MonetaryValue'] );
				$request_data['Package']['PackageServiceOptions']['DeclaredValue']['MonetaryValue'] = $original_value + $priority_fee;

				$this->log_debug( 'WPP: UPS declared value: ' . $original_value . ' -> ' . ( $original_value + $priority_fee ) );
			}
		} catch ( Exception $e ) {
			$this->log_debug( 'WPP: Error modifying UPS request: ' . $e->getMessage() );
		}

		return $request_data;
	}

	/**
	 * Modify USPS API requests
	 * This is safe and doesn't break shipping calculations
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>      $request_data Request data for USPS API.
	 * @param array<string, mixed>|null $package_data Package data.
	 * @return array<string, mixed> Modified request data
	 */
	public function modify_usps_api_request( array $request_data, ?array $package_data = null ): array {
		if ( ! Core_Permissions::is_priority_active() ) {
			return $request_data;
		}

		$priority_fee = floatval( get_option( 'wpp_fee_amount', '5.00' ) );

		if ( $priority_fee <= 0 ) {
			return $request_data;
		}

		$this->log_debug( "WPP: Modifying USPS API request - adding priority fee: {$priority_fee}" );

		try {
			// USPS typically uses 'Value' field for declared value.
			if ( isset( $request_data['Value'] ) ) {
				$original_value           = floatval( $request_data['Value'] );
				$request_data['Value'] = $original_value + $priority_fee;

				$this->log_debug( 'WPP: USPS declared value: ' . $original_value . ' -> ' . ( $original_value + $priority_fee ) );
			}
		} catch ( Exception $e ) {
			$this->log_debug( 'WPP: Error modifying USPS request: ' . $e->getMessage() );
		}

		return $request_data;
	}

	/**
	 * Modify DHL API requests
	 * This is safe and doesn't break shipping calculations
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>      $request_data Request data for DHL API.
	 * @param array<string, mixed>|null $package_data Package data.
	 * @return array<string, mixed> Modified request data
	 */
	public function modify_dhl_api_request( array $request_data, ?array $package_data = null ): array {
		if ( ! Core_Permissions::is_priority_active() ) {
			return $request_data;
		}

		$priority_fee = floatval( get_option( 'wpp_fee_amount', '5.00' ) );

		if ( $priority_fee <= 0 ) {
			return $request_data;
		}

		$this->log_debug( "WPP: Modifying DHL API request - adding priority fee: {$priority_fee}" );

		try {
			// DHL API structure varies, but commonly uses 'DeclaredValue'.
			if ( isset( $request_data['DeclaredValue'] ) ) {
				$original_value                   = floatval( $request_data['DeclaredValue'] );
				$request_data['DeclaredValue'] = $original_value + $priority_fee;

				$this->log_debug( 'WPP: DHL declared value: ' . $original_value . ' -> ' . ( $original_value + $priority_fee ) );
			}
		} catch ( Exception $e ) {
			$this->log_debug( 'WPP: Error modifying DHL request: ' . $e->getMessage() );
		}

		return $request_data;
	}

	/**
	 * Generic shipping calculator hook
	 * This is safe and doesn't break shipping calculations
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>      $request_data Request data for shipping calculator.
	 * @param array<string, mixed>|null $package      Package data.
	 * @return array<string, mixed> Modified request data
	 */
	public function modify_generic_shipping_request( array $request_data, ?array $package = null ): array {
		if ( ! Core_Permissions::is_priority_active() ) {
			return $request_data;
		}

		$priority_fee = floatval( get_option( 'wpp_fee_amount', '5.00' ) );

		if ( $priority_fee <= 0 ) {
			return $request_data;
		}

		try {
			// Add priority information to generic requests (doesn't modify core data).
			if ( ! isset( $request_data['wpp_priority_processing'] ) ) {
				$request_data['wpp_priority_processing'] = array(
					'enabled'       => true,
					'fee_amount'    => $priority_fee,
					'service_level' => 'express',
				);

				$this->log_debug( 'WPP: Added priority info to generic shipping request' );
			}
		} catch ( Exception $e ) {
			$this->log_debug( 'WPP: Error modifying generic shipping request: ' . $e->getMessage() );
		}

		return $request_data;
	}

	/**
	 * Check for priority processing when calculating shipping rates
	 * This method is very defensive to avoid breaking shipping calculations
	 *
	 * @since 1.0.0
	 * @param array<string, \WC_Shipping_Rate> $rates   Shipping rates.
	 * @param array<string, mixed>             $package Package data.
	 * @return array<string, \WC_Shipping_Rate> Modified shipping rates
	 */
	public function check_priority_for_rates( array $rates, array $package ): array {
		if ( ! Core_Permissions::is_priority_active() ) {
			return $rates;
		}

		try {
			$priority_fee = floatval( get_option( 'wpp_fee_amount', '5.00' ) );

			if ( $priority_fee <= 0 ) {
				return $rates;
			}

			// Add priority metadata to all shipping rates (doesn't modify rate costs).
			foreach ( $rates as $rate_id => $rate ) {
				if ( is_object( $rate ) && method_exists( $rate, 'add_meta_data' ) ) {
					$rates[ $rate_id ]->add_meta_data( 'wpp_priority_processing', 'yes', true );
					$rates[ $rate_id ]->add_meta_data( 'wpp_priority_fee_amount', $priority_fee, true );
				}
			}

			$this->log_debug( 'WPP: Added priority metadata to ' . count( $rates ) . ' shipping rates' );
		} catch ( Exception $e ) {
			$this->log_debug( 'WPP: Error in check_priority_for_rates: ' . $e->getMessage() );
		}

		return $rates;
	}

	/**
	 * Get shipping integration status
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Integration status information
	 */
	public function get_integration_status(): array {
		$active_integrations    = array();
		$available_integrations = array(
			'fedex'   => 'FedEx',
			'ups'     => 'UPS',
			'usps'    => 'USPS',
			'dhl'     => 'DHL',
			'generic' => 'Generic Shipping Calculators',
		);

		foreach ( $available_integrations as $key => $name ) {
			$active_integrations[ $key ] = array(
				'name'             => $name,
				'active'           => $this->is_integration_active( $key ),
				'priority_enabled' => Core_Permissions::is_priority_active(),
			);
		}

		return $active_integrations;
	}

	/**
	 * Check if specific shipping integration is active
	 *
	 * @since 1.0.0
	 * @param string $integration_type Type of shipping integration.
	 * @return bool True if integration is active
	 */
	private function is_integration_active( string $integration_type ): bool {
		// This could be expanded to check for specific plugin activations.
		switch ( $integration_type ) {
			case 'fedex':
				return class_exists( 'WC_Shipping_Fedex' ) || has_filter( 'woocommerce_fedex_api_request' );
			case 'ups':
				return class_exists( 'WC_Shipping_UPS' ) || has_filter( 'woocommerce_ups_api_request' );
			case 'usps':
				return class_exists( 'WC_Shipping_USPS' ) || has_filter( 'woocommerce_usps_api_request' );
			case 'dhl':
				return class_exists( 'WC_Shipping_DHL' ) || has_filter( 'woocommerce_dhl_api_request' );
			case 'generic':
				return true; // Generic hooks are always available.
			default:
				return false;
		}
	}

	/**
	 * Get priority fee for shipping calculations
	 *
	 * @since 1.0.0
	 * @return float Priority fee amount
	 */
	public function get_priority_fee(): float {
		return floatval( get_option( 'wpp_fee_amount', '5.00' ) );
	}

	/**
	 * Get shipping metadata for priority orders
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Shipping metadata
	 */
	public function get_priority_shipping_metadata(): array {
		return array(
			'service_level'                => 'express',
			'requires_priority_handling'   => true,
			'fee_amount'                   => $this->get_priority_fee(),
			'declared_value_adjustment'    => $this->get_priority_fee(),
		);
	}

	/**
	 * Log debug messages when WordPress debugging is enabled
	 *
	 * @since 1.0.0
	 * @param string $message Debug message to log.
	 * @return void
	 */
	private function log_debug( string $message ): void {
		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
