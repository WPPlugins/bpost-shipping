<?php
/**
 * Plugin Name: bpost shipping
 * Plugin URI:
 * Description: Bpost Shipping Manager is a service offered by bpost, allowing your customer to choose their preferred delivery method when ordering in your Woocommerce webshop.
 * Author: Antidot
 * Author URI: https://antidot.com/
 * Version: 2.2.8
 */

define( 'BPOST_PLUGIN_ID', 'bpost_shipping' );
define( 'BPOST_PLUGIN_DIR', __DIR__ );
define( 'BPOST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BPOST_PLUGIN_VERSION', '2.2.8' );

/**
 * Check if WooCommerce is active
 */
if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {

	add_action( 'admin_notices', function () {
		echo '<div id="message" class="error">
			<p>Woocommerce is required to use bpost shipping plugin.</p>
		</div>';
	} );

	return;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoloader.php';

$bpost_shipping_hooks = new WC_BPost_Shipping_Hooks();

/**
 * Hooks creation
 */
register_activation_hook( __FILE__, array( $bpost_shipping_hooks, 'bpost_shipping_cron_cache_activation' ) );
register_deactivation_hook( __FILE__, array( $bpost_shipping_hooks, 'bpost_shipping_cron_cache_deactivation' ) );

/**
 * Actions
 */
// Everywhere: Init when we use the shipping
add_action( 'plugins_loaded', array( $bpost_shipping_hooks, 'bpost_shipping_init' ) );

add_action( 'wp_enqueue_scripts', array( $bpost_shipping_hooks, 'enqueue_scripts_frontend' ), 1 );

add_action( 'admin_enqueue_scripts', array( $bpost_shipping_hooks, 'enqueue_scripts_admin' ) );

// Checkout: Stop the checkout process to call the SHM, if not yet shown
add_action(
	'woocommerce_after_checkout_validation',
	array( $bpost_shipping_hooks, 'bpost_shipping_stop_checkout_process' )
);

// Checkout: After the closing of the SHM, save bpost data into the order
add_action( 'woocommerce_checkout_order_processed', array( $bpost_shipping_hooks, 'bpost_shipping_feed_info' ), 10, 2 );

// Order-received: Add a bpost block to show the shipping info
add_action(
	'woocommerce_order_details_after_order_table',
	array( $bpost_shipping_hooks, 'bpost_shipping_info_block' )
);

// Admin: We add a block in the order details page with the bpost shipping info
add_action(
	'woocommerce_admin_order_data_after_shipping_address',
	array( $bpost_shipping_hooks, 'bpost_shipping_admin_details' )
);

// Before checkout: api for param validation
add_action( 'woocommerce_api_shm-loader', array( $bpost_shipping_hooks, 'bpost_shipping_api_loader' ) );

// After shm popin: create virtual page for shm callback
add_action(
	'woocommerce_api_shm-callback',
	array( $bpost_shipping_hooks, 'bpost_shipping_virtual_page_shm_callback' )
);

add_action( 'woocommerce_api_page-label', array( $bpost_shipping_hooks, 'bpost_virtual_page_label' ) );

// Refresh bpost box status for the given order ID
add_action( 'woocommerce_api_bpost-refresh-status', array( $bpost_shipping_hooks, 'bpost_refresh_bpost_status' ) );

add_action( 'add_meta_boxes', array( $bpost_shipping_hooks, 'bpost_order_details_box_meta' ) );

//Catch custom bulk actions when occurs (triggered by js above)
add_action( 'load-edit.php', array( $bpost_shipping_hooks, 'bpost_shipping_bulk_action' ) );

//On fixed intervals (check cron_cache_(des)?activation)
add_action( 'cache_clean', array( $bpost_shipping_hooks, 'bpost_shipping_cron_cache_clean_run' ) );

/**
 * Filters
 */
// Admin: Add the plugin to the shipping methods list
add_filter( 'woocommerce_shipping_methods', array( $bpost_shipping_hooks, 'bpost_shipping_add_method' ) );

// Checkout: Put 'as from' at the estimated shipping cost
add_filter(
	'woocommerce_cart_shipping_method_full_label',
	array( $bpost_shipping_hooks, 'bpost_shipping_prefix_estimated_cost' ),
	10, 2
);

add_filter(
	'woocommerce_admin_order_actions',
	array( $bpost_shipping_hooks, 'bpost_order_review_admin_actions' ),
	10, 2 );

// Checkout: Add fields to include into checkout process
add_filter( 'woocommerce_checkout_fields', array( $bpost_shipping_hooks, 'bpost_shipping_filter_checkout_fields' ) );

add_filter( 'woocommerce_order_shipping_method', array( $bpost_shipping_hooks, 'bpost_shipping_order_shipping_method' ), 10, 2 );


/**
 * @param string $text
 *
 * @return string
 */
function bpost__( $text ) {
	return __( $text, BPOST_PLUGIN_ID );
}
