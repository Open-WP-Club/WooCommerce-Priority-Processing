<?php
/**
 * Frontend AJAX Handler
 *
 * Handles AJAX requests for priority processing updates
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend AJAX Class
 *
 * @since 1.0.0
 */
class Frontend_AJAX {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// AJAX handler for both logged-in and guest users.
		add_action( 'wp_ajax_wpp_update_priority', array( $this, 'update_priority_status' ) );
		add_action( 'wp_ajax_nopriv_wpp_update_priority', array( $this, 'update_priority_status' ) );
	}

	/**
	 * Handle AJAX request to update priority processing status
	 *
	 * Works with both classic and block checkout.
	 * Properly sanitizes all inputs and validates nonce.
	 *
	 * @since 1.0.0
	 * @since 1.4.2 Added proper sanitization and type checking
	 * @return void
	 */
	public function update_priority_status(): void {
		// Verify nonce for security.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpp_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed', 'woo-priority' ),
				),
				403
			);
		}

		// Check if WooCommerce session is available.
		if ( ! WC()->session ) {
			wp_send_json_error(
				array(
					'message' => __( 'Session not available', 'woo-priority' ),
				),
				500
			);
		}

		// Get priority status from request (support both parameter formats).
		$priority_enabled = $this->get_priority_status_from_request();

		// Update session EARLY to ensure it's set before any calculations.
		WC()->session->set( 'priority_processing', $priority_enabled );

		// Ensure cart exists.
		if ( ! WC()->cart ) {
			wp_send_json_error(
				array(
					'message' => __( 'Cart not available', 'woo-priority' ),
				),
				500
			);
		}

		// Clear any cached shipping packages to force recalculation.
		$packages = WC()->cart->get_shipping_packages();
		WC()->shipping()->calculate_shipping( $packages );

		// Recalculate cart totals AFTER shipping is recalculated.
		WC()->cart->calculate_totals();

		// Return success response with updated cart data.
		wp_send_json_success(
			array(
				'message'   => __( 'Priority status updated', 'woo-priority' ),
				'priority'  => $priority_enabled,
				'fragments' => $this->get_cart_fragments(),
				'cart'      => $this->get_cart_data(),
			)
		);
	}

	/**
	 * Get priority status from request
	 *
	 * Safely extracts and sanitizes priority status from POST data.
	 * Supports both 'priority_enabled' and 'priority' parameter names.
	 *
	 * @since 1.4.2
	 * @return bool Whether priority processing should be enabled
	 */
	private function get_priority_status_from_request(): bool {
		$priority_enabled = false;

		// Check 'priority_enabled' parameter (Block checkout).
		if ( isset( $_POST['priority_enabled'] ) ) {
			$value            = sanitize_text_field( wp_unslash( $_POST['priority_enabled'] ) );
			$priority_enabled = in_array( $value, array( 'true', '1', 1 ), true );
		}

		// Check 'priority' parameter (Classic checkout).
		if ( ! $priority_enabled && isset( $_POST['priority'] ) ) {
			$value            = sanitize_text_field( wp_unslash( $_POST['priority'] ) );
			$priority_enabled = in_array( $value, array( 'true', '1', 1 ), true );
		}

		return $priority_enabled;
	}

	/**
	 * Get cart fragments for AJAX update
	 *
	 * Returns comprehensive fragments to update checkout without full refresh.
	 * Includes order totals, shipping costs, and uses WooCommerce's filter
	 * to allow other plugins to add their fragments.
	 *
	 * @since 1.4.2
	 * @return array Cart fragments
	 */
	private function get_cart_fragments(): array {
		$fragments = array();

		// Get order total fragment.
		if ( function_exists( 'wc_cart_totals_order_total_html' ) ) {
			ob_start();
			wc_cart_totals_order_total_html();
			$order_total = ob_get_clean();

			$fragments['.order-total'] = '<tr class="order-total"><th>' . esc_html__( 'Total', 'woocommerce' ) . '</th><td>' . $order_total . '</td></tr>';
		}

		// Get full order review table fragment.
		$order_review_table = $this->get_order_review_table();
		if ( ! empty( $order_review_table ) ) {
			$fragments['.woocommerce-checkout-review-order-table'] = $order_review_table;
		}

		/**
		 * Filter cart fragments
		 *
		 * Allows other plugins/themes to add their own fragments.
		 * This is the standard WooCommerce filter used during checkout updates.
		 *
		 * @since 1.4.2
		 * @param array $fragments Cart fragments
		 */
		return apply_filters( 'woocommerce_update_order_review_fragments', $fragments );
	}

	/**
	 * Get full order review table HTML
	 *
	 * @since 1.4.2
	 * @return string Order review table HTML
	 */
	private function get_order_review_table(): string {
		if ( ! function_exists( 'woocommerce_order_review' ) ) {
			// Try alternative function.
			if ( ! function_exists( 'wc_get_template' ) ) {
				return '';
			}

			ob_start();
			wc_get_template( 'checkout/review-order.php' );
			return ob_get_clean();
		}

		ob_start();
		woocommerce_order_review();
		return ob_get_clean();
	}

	/**
	 * Get cart data for AJAX response
	 *
	 * @since 1.4.2
	 * @return array Cart data
	 */
	private function get_cart_data(): array {
		return array(
			'subtotal'  => WC()->cart->get_cart_subtotal(),
			'total'     => WC()->cart->get_total(),
			'total_raw' => WC()->cart->get_total( '' ),
		);
	}
}
