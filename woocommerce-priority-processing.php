<?php
/**
 * Plugin Name: WooCommerce Priority Processing
 * Description: Add priority processing and express shipping option at checkout
 * Version: 1.4.4
 * Author: OpenWPClub.com
 * Author URI: https://openwpclub.com
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woo-priority
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 * Requires Plugins: woocommerce
 *
 * @package WooCommerce_Priority_Processing
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WPP_VERSION', '1.4.4' );
define( 'WPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Log message only when WP_DEBUG_LOG is enabled
 *
 * @since 1.4.4
 * @param string $message Message to log.
 * @return void
 */
function wpp_log( string $message ): void {
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( 'WPP: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

// Declare HPOS and Block compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Main Plugin Class
 *
 * @since 1.0.0
 */
class WooCommerce_Priority_Processing {

	/**
	 * Single instance of the class
	 *
	 * @var WooCommerce_Priority_Processing|null
	 */
	private static ?WooCommerce_Priority_Processing $instance = null;

	/**
	 * Admin menu instance
	 *
	 * @var Admin_Menu|null
	 */
	public ?Admin_Menu $admin_menu = null;

	/**
	 * Admin settings instance
	 *
	 * @var Admin_Settings|null
	 */
	public ?Admin_Settings $admin_settings = null;

	/**
	 * Admin dashboard instance
	 *
	 * @var Admin_Dashboard|null
	 */
	public ?Admin_Dashboard $admin_dashboard = null;

	/**
	 * Frontend checkout instance
	 *
	 * @var Frontend_Checkout|null
	 */
	public ?Frontend_Checkout $frontend_checkout = null;

	/**
	 * Frontend AJAX instance
	 *
	 * @var Frontend_AJAX|null
	 */
	public ?Frontend_AJAX $frontend_ajax = null;

	/**
	 * Frontend fees instance
	 *
	 * @var Frontend_Fees|null
	 */
	public ?Frontend_Fees $frontend_fees = null;

	/**
	 * Frontend shipping instance
	 *
	 * @var Frontend_Shipping|null
	 */
	public ?Frontend_Shipping $frontend_shipping = null;

	/**
	 * Frontend blocks integration instance
	 *
	 * @var Frontend_Blocks_Integration|null
	 */
	public ?Frontend_Blocks_Integration $frontend_blocks = null;

	/**
	 * Core orders instance
	 *
	 * @var Core_Orders|null
	 */
	public ?Core_Orders $core_orders = null;

	/**
	 * Core statistics instance
	 *
	 * @var Core_Statistics|null
	 */
	public ?Core_Statistics $core_statistics = null;

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @return WooCommerce_Priority_Processing
	 */
	public static function instance(): WooCommerce_Priority_Processing {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Load plugin textdomain.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Include all required files.
		$this->include_files();

		// Initialize plugin.
		$this->init();
	}

	/**
	 * Load plugin textdomain for translations
	 *
	 * @since 1.4.2
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'woo-priority', false, dirname( WPP_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Include all required files
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function include_files(): void {
		// Include core files.
		require_once WPP_PLUGIN_DIR . 'includes/core/permissions.php';
		require_once WPP_PLUGIN_DIR . 'includes/core/statistics.php';
		require_once WPP_PLUGIN_DIR . 'includes/core/orders.php';

		// Include admin files.
		require_once WPP_PLUGIN_DIR . 'includes/admin/menu.php';
		require_once WPP_PLUGIN_DIR . 'includes/admin/settings.php';
		require_once WPP_PLUGIN_DIR . 'includes/admin/dashboard.php';

		// Include frontend files.
		require_once WPP_PLUGIN_DIR . 'includes/frontend/checkout.php';
		require_once WPP_PLUGIN_DIR . 'includes/frontend/ajax.php';
		require_once WPP_PLUGIN_DIR . 'includes/frontend/fees.php';
		require_once WPP_PLUGIN_DIR . 'includes/frontend/shipping.php';
		require_once WPP_PLUGIN_DIR . 'includes/frontend/blocks-integration.php';
	}

	/**
	 * Initialize plugin components
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init(): void {
		// Initialize core components.
		$this->core_statistics = new Core_Statistics();
		$this->core_orders     = new Core_Orders();

		// Initialize admin components.
		$this->admin_menu      = new Admin_Menu( $this->core_statistics );
		$this->admin_settings  = new Admin_Settings();
		$this->admin_dashboard = new Admin_Dashboard( $this->core_statistics );

		// Initialize frontend components.
		$this->frontend_checkout = new Frontend_Checkout();
		$this->frontend_ajax     = new Frontend_AJAX();
		$this->frontend_fees     = new Frontend_Fees();
		$this->frontend_shipping = new Frontend_Shipping();
		$this->frontend_blocks   = new Frontend_Blocks_Integration();

		// Register settings and defaults.
		add_action( 'admin_init', array( $this, 'register_default_settings' ) );

		// Add cleanup hooks.
		add_action( 'woocommerce_cart_emptied', array( $this, 'clear_priority_session' ) );
		add_action( 'wp_logout', array( $this, 'clear_priority_session' ) );
	}

	/**
	 * Display admin notice when WooCommerce is not active
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function woocommerce_missing_notice(): void {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				esc_html_e( 'WooCommerce Priority Processing requires WooCommerce to be installed and active.', 'woo-priority' );
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Register default plugin settings
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_default_settings(): void {
		$defaults = array(
			'wpp_fee_amount'         => '5.00',
			'wpp_checkbox_label'     => __( 'Priority processing + Express shipping', 'woo-priority' ),
			'wpp_description'        => __( 'Your order will be processed with priority and shipped via express delivery', 'woo-priority' ),
			'wpp_fee_label'          => __( 'Priority Processing & Express Shipping', 'woo-priority' ),
			'wpp_section_title'      => __( 'Express Options', 'woo-priority' ),
			'wpp_enabled'            => '1',
			'wpp_allowed_user_roles' => array( 'customer' ),
			'wpp_allow_guests'       => '1',
		);

		foreach ( $defaults as $option_name => $default_value ) {
			if ( false === get_option( $option_name ) ) {
				update_option( $option_name, $default_value );
			}
		}
	}

	/**
	 * Clear priority processing session
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function clear_priority_session(): void {
		if ( WC()->session && WC()->session->get( 'priority_processing' ) ) {
			WC()->session->set( 'priority_processing', false );
			wpp_log( 'Priority session cleared by main class' );
		}
	}

	/**
	 * Plugin activation handler
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function on_activation(): void {
		// Create default options.
		$defaults = array(
			'wpp_enabled'            => '0',
			'wpp_fee_amount'         => '5.00',
			'wpp_checkbox_label'     => __( 'Priority processing + Express shipping', 'woo-priority' ),
			'wpp_description'        => __( 'Your order will be processed with priority and shipped via express delivery', 'woo-priority' ),
			'wpp_fee_label'          => __( 'Priority Processing & Express Shipping', 'woo-priority' ),
			'wpp_section_title'      => __( 'Express Options', 'woo-priority' ),
			'wpp_allowed_user_roles' => array( 'customer' ),
			'wpp_allow_guests'       => '1',
		);

		foreach ( $defaults as $option_name => $default_value ) {
			add_option( $option_name, $default_value );
		}

		// Initialize statistics scheduling on activation, if available.
		if ( class_exists( 'Core_Statistics' ) ) {
			$statistics = new Core_Statistics();
			if ( method_exists( $statistics, 'schedule_daily_refresh' ) ) {
				$statistics->schedule_daily_refresh();
			}
		}
	}

	/**
	 * Plugin deactivation handler
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function on_deactivation(): void {
		// Clear any sessions.
		if ( class_exists( 'WooCommerce' ) && WC()->session ) {
			WC()->session->set( 'priority_processing', false );
		}

		// Clear statistics cache if available.
		if ( class_exists( 'Core_Statistics' ) ) {
			$statistics = new Core_Statistics();
			if ( method_exists( $statistics, 'clear_cache' ) ) {
				$statistics->clear_cache();
			}
			if ( method_exists( $statistics, 'cleanup_scheduled_events' ) ) {
				$statistics->cleanup_scheduled_events();
			}
		}
	}

	/**
	 * Get statistics instance
	 *
	 * @since 1.0.0
	 * @return Core_Statistics|null
	 */
	public function get_statistics(): ?Core_Statistics {
		return $this->core_statistics;
	}

	/**
	 * Get admin menu instance
	 *
	 * @since 1.0.0
	 * @return Admin_Menu|null
	 */
	public function get_admin_menu(): ?Admin_Menu {
		return $this->admin_menu;
	}

	/**
	 * Get orders instance
	 *
	 * @since 1.0.0
	 * @return Core_Orders|null
	 */
	public function get_orders(): ?Core_Orders {
		return $this->core_orders;
	}
}

// Initialize plugin after all plugins are loaded.
add_action(
	'plugins_loaded',
	function () {
		WooCommerce_Priority_Processing::instance();
	}
);

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'WooCommerce_Priority_Processing', 'on_activation' ) );
register_deactivation_hook( __FILE__, array( 'WooCommerce_Priority_Processing', 'on_deactivation' ) );
