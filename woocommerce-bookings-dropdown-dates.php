<?php
/**
 * Plugin Name: WooCommerce Bookings Dropdown Dates
 * Plugin URI: https://sestrainingsolutions.co.uk
 * Description: Displays WooCommerce Bookings dates as a dropdown with remaining places. Updated for modern WC Bookings.
 * Version: 2.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Kodr
 * Author URI: https://kodr.io
 * License: GPL v3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wc-bookings-dropdown
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'WC_BOOKINGS_DROPDOWN_VERSION', '2.0.0' );
define( 'WC_BOOKINGS_DROPDOWN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_BOOKINGS_DROPDOWN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce features
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', __FILE__, true );
	}
} );

/**
 * Check if WooCommerce Bookings is active
 */
function wc_bookings_dropdown_check_dependencies() {
	if ( ! class_exists( 'WC_Bookings' ) ) {
		add_action( 'admin_notices', 'wc_bookings_dropdown_missing_wc_bookings_notice' );
		return false;
	}
	return true;
}

/**
 * Admin notice if WooCommerce Bookings is not active
 */
function wc_bookings_dropdown_missing_wc_bookings_notice() {
	?>
	<div class="error">
		<p><?php esc_html_e( 'WooCommerce Bookings Dropdown Dates requires WooCommerce Bookings to be installed and active.', 'wc-bookings-dropdown' ); ?></p>
	</div>
	<?php
}

/**
 * Initialize the plugin
 */
function wc_bookings_dropdown_init() {
    if ( ! wc_bookings_dropdown_check_dependencies() ) {
        return;
    }
    
    // TEMPORARY TEST - Remove after testing
    add_filter( 'booking_form_fields', function( $fields ) {
        error_log( 'TEST: booking_form_fields filter IS BEING CALLED!' );
        error_log( 'TEST: Fields count: ' . count($fields) );
        return $fields;
    }, 1, 1 ); // Priority 1 to run first
    
    // Load required files
    require_once WC_BOOKINGS_DROPDOWN_PLUGIN_DIR . 'includes/class-dropdown-dates.php';
    require_once WC_BOOKINGS_DROPDOWN_PLUGIN_DIR . 'includes/class-ajax-handler.php';
    
    // Initialize classes
    WC_Bookings_Dropdown_Dates::instance();
    WC_Bookings_Dropdown_Ajax_Handler::instance();
}
add_action( 'plugins_loaded', 'wc_bookings_dropdown_init' );

/**
 * Load plugin text domain for translations
 */
function wc_bookings_dropdown_load_textdomain() {
	load_plugin_textdomain( 'wc-bookings-dropdown', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'wc_bookings_dropdown_load_textdomain' );