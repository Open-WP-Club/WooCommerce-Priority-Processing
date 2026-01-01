<?php
/**
 * Frontend Fees Handler
 * Manages fee calculation and order metadata for priority processing
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.0.0
 */

declare(strict_types=1);

/**
 * Frontend Fees Class
 *
 * This class handles saving priority status and related fee information to order meta.
 * The priority fee is added directly to shipping rates in the Frontend_Shipping class.
 *
 * @since 1.0.0
 */
class Frontend_Fees {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Save priority processing status to orders during checkout.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_priority_to_order' ), 10, 2 );
	}

	/**
	 * Save priority processing data to order
	 *
	 * @since 1.0.0
	 * @param \WC_Order                $order Order object.
	 * @param array<string, mixed>     $data  Checkout data.
	 * @return void
	 */
	public function save_priority_to_order( \WC_Order $order, array $data ): void {
		if ( ! WC()->session ) {
			return;
		}

		$priority = WC()->session->get( 'priority_processing', false );
		if ( $priority === true || $priority === 1 || $priority === '1' ) {
			$this->apply_priority_to_order( $order );
		}
	}

	/**
	 * Apply priority processing to the order
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	private function apply_priority_to_order( \WC_Order $order ): void {
		$fee_amount = floatval( get_option( 'wpp_fee_amount', '5.00' ) );
		$fee_label  = get_option( 'wpp_fee_label', 'Priority Processing & Express Shipping' );

		// Save priority meta data - WooCommerce handles fee transfer automatically.
		$order->update_meta_data( '_priority_processing', 'yes' );

		// Add shipping-specific meta data for shipping plugin integration.
		$order->update_meta_data( '_requires_express_shipping', 'yes' );
		$order->update_meta_data( '_priority_fee_amount', $fee_amount );
		$order->update_meta_data( '_priority_service_level', 'express' );

		// Fire action hook for shipping plugins that might want to integrate.
		do_action( 'wpp_priority_order_created', $order, $fee_amount );

		$order->save();
	}

	/**
	 * Calculate fee amount based on cart contents (if needed for complex logic)
	 *
	 * @since 1.0.0
	 * @return float Calculated fee amount
	 */
	public function calculate_dynamic_fee(): float {
		$base_fee   = floatval( get_option( 'wpp_fee_amount', '5.00' ) );
		$cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;

		// Example: could implement percentage-based fees or tiered pricing.
		// For now, just return the base fee.
		return $base_fee;
	}

	/**
	 * Get fee display information
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Fee information
	 */
	public function get_fee_info(): array {
		return array(
			'amount'           => floatval( get_option( 'wpp_fee_amount', '5.00' ) ),
			'label'            => get_option( 'wpp_fee_label', 'Priority Processing & Express Shipping' ),
			'formatted_amount' => wc_price( get_option( 'wpp_fee_amount', '5.00' ) ),
			'is_enabled'       => ( get_option( 'wpp_enabled' ) === '1' || get_option( 'wpp_enabled' ) === 'yes' ),
		);
	}

	/**
	 * Remove priority processing fee (if needed)
	 *
	 * @since 1.0.0
	 * @return bool True if fee was removed, false otherwise
	 */
	public function remove_priority_fee(): bool {
		if ( ! WC()->cart ) {
			return false;
		}

		$fee_label = get_option( 'wpp_fee_label', 'Priority Processing & Express Shipping' );
		$fees      = WC()->cart->get_fees();

		foreach ( $fees as $fee_key => $fee ) {
			if ( $fee->name === $fee_label ) {
				unset( $fees[ $fee_key ] );
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if priority processing is active in current session
	 *
	 * @since 1.0.0
	 * @return bool True if priority processing is active
	 */
	private function is_priority_active(): bool {
		if ( ! WC()->session ) {
			return false;
		}

		$priority = WC()->session->get( 'priority_processing', false );
		return ( $priority === true || $priority === '1' || $priority === 1 );
	}

	/**
	 * Get current cart total including priority fee
	 *
	 * @since 1.0.0
	 * @return float Cart total with priority fee
	 */
	public function get_total_with_priority(): float {
		if ( ! WC()->cart ) {
			return 0;
		}

		$cart_total   = WC()->cart->get_total( '' );
		$priority_fee = $this->is_priority_active() ? floatval( get_option( 'wpp_fee_amount', '5.00' ) ) : 0;

		return $cart_total + $priority_fee;
	}

	/**
	 * Format fee for display
	 *
	 * @since 1.0.0
	 * @param float $amount         Fee amount to format.
	 * @param bool  $include_symbol Whether to include currency symbol.
	 * @return string Formatted fee amount
	 */
	public function format_fee_display( float $amount, bool $include_symbol = true ): string {
		if ( $include_symbol ) {
			return wc_price( $amount );
		}

		return number_format( $amount, 2 );
	}
}
