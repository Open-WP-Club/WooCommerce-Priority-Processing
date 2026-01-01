<?php
/**
 * WooCommerce Blocks Integration
 * Handles integration with WooCommerce Cart and Checkout Blocks
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.4.0
 */

declare(strict_types=1);

use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;

/**
 * Frontend Blocks Integration Class
 *
 * @since 1.4.0
 */
class Frontend_Blocks_Integration {

	/**
	 * Constructor
	 *
	 * @since 1.4.0
	 */
	public function __construct() {
		// Register the block integration.
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_blocks_integration' ) );

		// Enqueue block scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_block_scripts' ) );
	}

	/**
	 * Register integration with WooCommerce Blocks
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function register_blocks_integration(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Package' ) ) {
			return;
		}

		// Extend the Store API with our custom data.
		$this->extend_store_api();
	}

	/**
	 * Extend WooCommerce Store API
	 *
	 * @since 1.4.0
	 * @return void
	 */
	private function extend_store_api(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			$this->log_debug( 'WPP Blocks: Store API registration function not available' );
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CheckoutSchema::IDENTIFIER,
				'namespace'       => 'wpp-priority',
				'data_callback'   => array( $this, 'extend_checkout_data' ),
				'schema_callback' => array( $this, 'extend_checkout_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
				'namespace'       => 'wpp-priority',
				'data_callback'   => array( $this, 'extend_cart_data' ),
				'schema_callback' => array( $this, 'extend_cart_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		// Register update callback for priority processing checkbox.
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => 'wpp-priority',
				'callback'  => array( $this, 'update_priority_from_blocks' ),
			)
		);
	}

	/**
	 * Extend checkout data for blocks
	 *
	 * @since 1.4.0
	 * @return array<string, mixed> Priority processing data
	 */
	public function extend_checkout_data(): array {
		return $this->get_priority_data();
	}

	/**
	 * Extend cart data for blocks
	 *
	 * @since 1.4.0
	 * @return array<string, mixed> Priority processing data
	 */
	public function extend_cart_data(): array {
		return $this->get_priority_data();
	}

	/**
	 * Get priority processing data for blocks
	 *
	 * @since 1.4.0
	 * @return array<string, mixed> Priority processing configuration
	 */
	private function get_priority_data(): array {
		$is_enabled = get_option( 'wpp_enabled' ) === 'yes' || get_option( 'wpp_enabled' ) === '1';
		$can_access = Core_Permissions::can_access_priority_processing();
		$is_active  = $this->is_priority_active();

		return array(
			'enabled'        => $is_enabled && $can_access,
			'is_active'      => $is_active,
			'fee_amount'     => floatval( get_option( 'wpp_fee_amount', '5.00' ) ),
			'fee_label'      => get_option( 'wpp_fee_label', __( 'Priority Processing & Express Shipping', 'woo-priority' ) ),
			'section_title'  => get_option( 'wpp_section_title', __( 'Express Options', 'woo-priority' ) ),
			'checkbox_label' => get_option( 'wpp_checkbox_label', __( 'Priority processing + Express shipping', 'woo-priority' ) ),
			'description'    => get_option( 'wpp_description', __( 'Your order will be processed with priority and shipped via express delivery', 'woo-priority' ) ),
		);
	}

	/**
	 * Define checkout schema extension
	 *
	 * @since 1.4.0
	 * @return array<string, array<string, mixed>> Schema definition
	 */
	public function extend_checkout_schema(): array {
		return array(
			'enabled'        => array(
				'description' => __( 'Whether priority processing is available', 'woo-priority' ),
				'type'        => 'boolean',
				'readonly'    => true,
			),
			'is_active'      => array(
				'description' => __( 'Whether priority processing is currently active', 'woo-priority' ),
				'type'        => 'boolean',
				'readonly'    => true,
			),
			'fee_amount'     => array(
				'description' => __( 'Priority processing fee amount', 'woo-priority' ),
				'type'        => 'number',
				'readonly'    => true,
			),
			'fee_label'      => array(
				'description' => __( 'Fee label text', 'woo-priority' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'section_title'  => array(
				'description' => __( 'Section title', 'woo-priority' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'checkbox_label' => array(
				'description' => __( 'Checkbox label text', 'woo-priority' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'description'    => array(
				'description' => __( 'Description text', 'woo-priority' ),
				'type'        => 'string',
				'readonly'    => true,
			),
		);
	}

	/**
	 * Define cart schema extension
	 *
	 * @since 1.4.0
	 * @return array<string, array<string, mixed>> Schema definition
	 */
	public function extend_cart_schema(): array {
		return $this->extend_checkout_schema();
	}

	/**
	 * Update priority processing from blocks checkout
	 *
	 * @since 1.4.0
	 * @param array<string, mixed> $data Request data from blocks checkout.
	 * @return void
	 */
	public function update_priority_from_blocks( array $data ): void {
		if ( ! isset( $data['priority_enabled'] ) ) {
			return;
		}

		$priority_enabled = filter_var( $data['priority_enabled'], FILTER_VALIDATE_BOOLEAN );

		if ( WC()->session ) {
			WC()->session->set( 'priority_processing', $priority_enabled );

			// Recalculate cart totals.
			if ( WC()->cart ) {
				WC()->cart->calculate_totals();
			}

			$this->log_debug( 'WPP Blocks: Priority processing ' . ( $priority_enabled ? 'enabled' : 'disabled' ) );
		}
	}

	/**
	 * Check if priority processing is active
	 *
	 * @since 1.4.0
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
	 * Enqueue scripts for blocks checkout
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function enqueue_block_scripts(): void {
		// Only load on checkout page.
		if ( ! is_checkout() && ! has_block( 'woocommerce/checkout' ) ) {
			return;
		}

		// Check if feature is enabled.
		if ( get_option( 'wpp_enabled' ) !== 'yes' && get_option( 'wpp_enabled' ) !== '1' ) {
			return;
		}

		// Check permissions.
		if ( ! Core_Permissions::can_access_priority_processing() ) {
			return;
		}

		// Register and enqueue the block script.
		$script_path = WPP_PLUGIN_DIR . 'assets/js/blocks-checkout.js';
		$script_url  = WPP_PLUGIN_URL . 'assets/js/blocks-checkout.js';

		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'wpp-blocks-checkout',
				$script_url,
				array( 'wp-element', 'wp-i18n', 'wp-components', 'wc-blocks-checkout' ),
				WPP_VERSION,
				true
			);

			// Pass data to the script.
			wp_localize_script(
				'wpp-blocks-checkout',
				'wppBlocksData',
				array(
					'ajax_url'       => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( 'wpp_nonce' ),
					'fee_amount'     => get_option( 'wpp_fee_amount', '5.00' ),
					'fee_label'      => get_option( 'wpp_fee_label', __( 'Priority Processing & Express Shipping', 'woo-priority' ) ),
					'section_title'  => get_option( 'wpp_section_title', __( 'Express Options', 'woo-priority' ) ),
					'checkbox_label' => get_option( 'wpp_checkbox_label', __( 'Priority processing + Express shipping', 'woo-priority' ) ),
					'description'    => get_option( 'wpp_description', __( 'Your order will be processed with priority and shipped via express delivery', 'woo-priority' ) ),
				)
			);
		}
	}

	/**
	 * Log debug messages when WordPress debugging is enabled
	 *
	 * @since 1.4.0
	 * @param string $message Debug message to log.
	 * @return void
	 */
	private function log_debug( string $message ): void {
		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
