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
		// Add priority fee to cart as a separate line item.
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_priority_fee_to_cart' ), 10 );

		// Save priority processing status to orders during checkout.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_priority_to_order' ), 10, 2 );
	}

	/**
	 * Add priority processing fee to cart as a separate line item
	 *
	 * @since 1.4.2
	 * @return void
	 */
	public function add_priority_fee_to_cart(): void {
		if ( ! Core_Permissions::is_priority_active() ) {
			return;
		}

		$fee_amount = floatval( get_option( 'wpp_fee_amount', '5.00' ) );
		$fee_label  = get_option( 'wpp_fee_label', 'Priority Processing & Express Shipping' );

		if ( $fee_amount > 0 && WC()->cart ) {
			// Add fee as a separate line item in cart.
			WC()->cart->add_fee( $fee_label, $fee_amount, true );
		}
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


}
