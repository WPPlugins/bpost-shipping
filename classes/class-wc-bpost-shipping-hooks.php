<?php

use WC_BPost_Shipping\Adapter\WC_BPost_Shipping_Adapter_Woocommerce;
use WC_BPost_Shipping\Api\WC_BPost_Shipping_Api_Factory;
use WC_BPost_Shipping\Assets\WC_BPost_Shipping_Assets_Detector;
use WC_BPost_Shipping\Assets\WC_BPost_Shipping_Assets_Management;
use WC_BPost_Shipping\Assets\WC_BPost_Shipping_Assets_Resources;
use WC_BPost_Shipping\Checkout\WC_BPost_Shipping_Checkout_Order_Review;
use WC_BPost_Shipping\Cron\WC_BPost_Shipping_Cron_Runner;
use WC_BPost_Shipping\Label\WC_BPost_Shipping_Label_Attachment;
use WC_BPost_Shipping\Label\WC_BPost_Shipping_Label_Controller;
use WC_BPost_Shipping\Label\WC_BPost_Shipping_Label_Meta_Box_Controller;
use WC_BPost_Shipping\Label\WC_BPost_Shipping_Label_Order_Overview;
use WC_BPost_Shipping\Label\WC_BPost_Shipping_Label_Post;
use WC_BPost_Shipping\Label\WC_BPost_Shipping_Label_Retriever;
use WC_BPost_Shipping\Label\WC_BPost_Shipping_Label_Url_Generator;
use WC_BPost_Shipping\Options\WC_BPost_Shipping_Options_Base;
use WC_BPost_Shipping\Options\WC_BPost_Shipping_Options_Label;
use WC_BPost_Shipping\Status\WC_BPost_Shipping_Status_Controller;
use WC_BPost_Shipping\Street\WC_BPost_Shipping_Street_Builder;
use WC_BPost_Shipping\Street\WC_BPost_Shipping_Street_Solver;
use WC_BPost_Shipping\Zip\WC_BPost_Shipping_Zip_Filename;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class WC_BPost_Shipping_Address retrieves specific data address
 * It is capable to use WC_BPost_Shipping_Street_Builder|Solver to return a well parsed address
 */
class WC_BPost_Shipping_Hooks {

	/**
	 * Everywhere: Init when we use the shipping
	 */
	public function bpost_shipping_init() {
		load_plugin_textdomain(
			'bpost_shipping',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * Admin: Add the plugin to the shipping methods list
	 *
	 * @param array $methods
	 *
	 * @return array
	 */
	public function bpost_shipping_add_method( $methods ) {
		$methods[ BPOST_PLUGIN_ID ] = 'WC_BPost_Shipping_Method';

		return $methods;
	}

	/**
	 * @param WC_Abstract_Order $order
	 *
	 * @return string
	 * @todo Externalize me!
	 */
	private function bpost_shipping_get_shipping_method_id( WC_Abstract_Order $order ) {
		$shipping_methods = $order->get_shipping_methods();
		if ( ! $shipping_methods ) {
			return '';
		}
		$shipping_method = array_pop( $shipping_methods );
		if ( ! array_key_exists( 'method_id', $shipping_method ) ) {
			return '';
		}

		return $shipping_method['method_id'];
	}

	/**
	 * Checkout: Stop the checkout process to call the SHM, if not yet shown
	 *
	 * @param array $posted
	 */
	public function bpost_shipping_stop_checkout_process( $posted ) {
		$posted = (array) $posted;
		if ( ! $this->is_bpost_shipping_from_post( $posted ) ) {
			return;
		}

		if ( ! empty( $posted['bpost_shm_already_called'] ) ) {
			// We already called the SHM, we will not recall it
			return;
		}

		$bpost_posted = new WC_BPost_Shipping_Posted( $posted );

		$checkout_order_review = new WC_BPost_Shipping_Checkout_Order_Review(
			new WC_BPost_Shipping_Adapter_Woocommerce(),
			new WC_BPost_Shipping_Api_Factory(
				new WC_BPost_Shipping_Options_Base(),
				$this->bpost_shipping_get_logger()
			),
			new \WC_BPost_Shipping_Limitations(),
			new WC_BPost_Shipping_Cart(
				WC()->cart
			),
			$bpost_posted
		);

		$checkout_order_review->review_order();

		if ( wc_notice_count( 'error' ) == 0 ) {
			wc_add_notice( '<span id="shm_must_be_open">' . bpost__( 'bpost pop-in is opening' ) . '</span>', 'error' );
		}
	}

	/**
	 * Checkout: After the closing of the SHM, save bpost data into the order
	 *
	 * @param int $order_id
	 * @param array $posted
	 */
	public function bpost_shipping_feed_info( $order_id, $posted ) {
		if ( ! $this->is_bpost_shipping_from_post( $posted ) ) {
			return;
		}
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			// Log error
			return;
		}

		$logger = $this->bpost_shipping_get_logger();

		$logger->debug( __FUNCTION__ . '.posted', $posted );

		$api_factory = new WC_BPost_Shipping_Api_Factory(
			new WC_BPost_Shipping_Options_Base(),
			$this->bpost_shipping_get_logger()
		);

		$order_updater = new WC_BPost_Shipping_Order_Updater( $order, $posted, $api_factory->get_geo6_search() );
		$order_updater->update_order();
	}

	/**
	 * Order-received: Add a bpost block to show the shipping info
	 *
	 * @param WC_Order $order
	 */
	public function bpost_shipping_info_block( WC_Order $order ) {
		if ( ! $this->is_bpost_shipping_from_order( $order ) ) {
			return;
		}

		$adapter = new WC_BPost_Shipping_Adapter_Woocommerce();

		$order_display = new WC_BPost_Shipping_Order_Details_Controller(
			$this->bpost_shipping_get_adapter(),
			new WC_BPost_Shipping_Assets_Management(
				new WC_BPost_Shipping_Assets_Detector(
					$this->bpost_shipping_get_adapter()
				),
				new WC_BPost_Shipping_Assets_Resources()
			),
			new WC_BPost_Shipping_Meta_Handler(
				$adapter,
				new WC_BPost_Shipping_Meta_Type( $adapter ),
				$order->get_id()
			),
			new WC_BPost_Shipping_Options_Base(),
			$order
		);
		$order_display->load_template();
	}

	/**
	 * Checkout: Put 'as from' at the estimated shipping cost
	 *
	 * @param $method_label
	 * @param WC_Shipping_Rate $method
	 *
	 * @return string
	 */
	public function bpost_shipping_prefix_estimated_cost( $method_label, WC_Shipping_Rate $method ) {
		if ( $method->method_id !== BPOST_PLUGIN_ID ) {
			return $method_label;
		}

		if ( strpos( $method->id, '_error' ) !== false ) {
			return $method->label;
		}

		$as_from_text = ' ' . bpost__( '(as from)' );

		if ( $method->cost == 0 ) { // $method->cost was a float, is now a string. Don't use '==='.
			// We want « bpost shipping as from (Free) » instead of « bpost shipping (as from) (Free) »
			$as_from_text = ': ' . bpost__( 'Free shipping available' );
		}

		$img = '<img
		style="display: inline-block; width: 32px; margin: 0 4px;"
		id="bpost-logo-list"
		alt="bpost-logo"
		src="' . BPOST_PLUGIN_URL . '/public/images/bpost-logo.png"
		/>';

		return str_replace(
			$method->label,
			$img . $method->label . $as_from_text,
			$method_label
		);
	}

	/**
	 * Admin: We add a block in the order details page with the bpost shipping info
	 *
	 * @param WC_Order $order
	 */
	public function bpost_shipping_admin_details( WC_Order $order ) {
		if ( ! $this->is_bpost_shipping_from_order( $order ) ) {
			return;
		}

		$adapter = new WC_BPost_Shipping_Adapter_Woocommerce();

		$admin_order_data = new WC_BPost_Shipping_Admin_Order_Data_Controller(
			$this->bpost_shipping_get_adapter(),
			new WC_BPost_Shipping_Assets_Management(
				new WC_BPost_Shipping_Assets_Detector( $this->bpost_shipping_get_adapter() ),
				new WC_BPost_Shipping_Assets_Resources()
			),
			new WC_BPost_Shipping_Meta_Handler(
				$adapter,
				new WC_BPost_Shipping_Meta_Type( $adapter ),
				$order->get_id()
			),
			$order
		);
		$admin_order_data->load_template();
	}

	/**
	 * Create and maintains a adapter instance
	 * @return WC_BPost_Shipping_Adapter_Woocommerce
	 */
	private function bpost_shipping_get_adapter() {
		return WC_BPost_Shipping_Adapter_Woocommerce::get_instance();
	}

	/**
	 * After shm popin: create virtual page for shm callback
	 */
	public function bpost_shipping_virtual_page_shm_callback() {
		$logger = $this->bpost_shipping_get_logger();

		$regexpList = implode( '|', array(
			\WC_BPost_Shipping_Shm_Callback_Controller::RESULT_SHM_CALLBACK_CONFIRM,
			\WC_BPost_Shipping_Shm_Callback_Controller::RESULT_SHM_CALLBACK_CANCEL,
			\WC_BPost_Shipping_Shm_Callback_Controller::RESULT_SHM_CALLBACK_ERROR,
		) );

		$callback = new WC_BPost_Shipping_Shm_Callback_Controller(
			$this->bpost_shipping_get_adapter(),
			new WC_BPost_Shipping_Assets_Management(
				new WC_BPost_Shipping_Assets_Detector( $this->bpost_shipping_get_adapter() ),
				new WC_BPost_Shipping_Assets_Resources()
			),
			$logger,
			filter_input( INPUT_GET, 'result', FILTER_VALIDATE_REGEXP, array(
				'options' => array(
					'regexp' => '#^(' . $regexpList . ')$#',
				)
			) ),
			get_option( 'home' )
		);
		$callback->load_template();

		$logger->debug( __FUNCTION__ . '._POST', $_POST );

		ob_flush();
		die();
	}

	public function bpost_virtual_page_label() {
		$adapter          = $this->bpost_shipping_get_adapter();
		$label_controller = new WC_BPost_Shipping_Label_Controller(
			$adapter,
			new WC_BPost_Shipping_Options_Label( $adapter ),
			new WC_BPost_Shipping_Label_Url_Generator( $adapter, WC() ),
			$this->bpost_shipping_get_label_retriever(),
			new WC_BPost_Shipping_Zip_Filename( new DateTime() ),
			$_GET
		);
		$label_controller->load_template();

		ob_flush();
		die();
	}

	public function bpost_refresh_bpost_status() {
		$adapter = $this->bpost_shipping_get_adapter();

		$api_factory = new WC_BPost_Shipping_Api_Factory(
			new WC_BPost_Shipping_Options_Base(),
			$this->bpost_shipping_get_logger()
		);

		$status_controller = new WC_BPost_Shipping_Status_Controller(
			$adapter,
			$api_factory->get_api_status(),
			$_GET
		);
		$status_controller->load_template();

		die();
	}

	/**
	 * Before checkout: api for param validation
	 */
	public function bpost_shipping_api_loader() {
		$posted_obj           = new WC_BPost_Shipping_Posted( $_POST );
		$bpost_street_builder = new WC_BPost_Shipping_Street_Builder( new WC_BPost_Shipping_Street_Solver() );

		$cart = new WC_BPost_Shipping_Cart( WC()->cart );

		$api_factory = new WC_BPost_Shipping_Api_Factory(
			new WC_BPost_Shipping_Options_Base(),
			$this->bpost_shipping_get_logger()
		);
		$data_builder = new WC_BPost_Shipping_Data_Builder(
			$cart,
			new WC_BPost_Shipping_Address( $bpost_street_builder, WC()->customer, $posted_obj ),
			new WC_BPost_Shipping_Options_Base(),
			$bpost_street_builder,
			new WC_BPost_Shipping_Delivery_Methods( $api_factory->get_api_connector()->fetchProductConfig() )
		);

		header( 'Content-Type: application/json', true );

		$result                     = array( 'status' => true );
		$result['bpost_data']       = $data_builder->get_bpost_data();
		$result['shipping_address'] = $data_builder->get_shipping_address();

		echo json_encode( $result );
		ob_flush();
		die();
	}

	/**
	 * Checkout: add fields to include into checkout process
	 *
	 * @param array $checkout_fields
	 *
	 * @return array
	 */
	public function bpost_shipping_filter_checkout_fields( $checkout_fields ) {
		$checkout_fields['bpost'] = array(
			'bpost_email'              => array(),
			'bpost_phone'              => array(),
			'bpost_delivery_method'    => array(),
			'bpost_delivery_price'     => array(),
			'bpost_delivery_date'      => array(),
			'bpost_delivery_point_id'  => array(),
			'bpost_postal_location'    => array(),
			'bpost_order_reference'    => array(),
			'bpost_shm_already_called' => array()
		);

		return $checkout_fields;
	}

	/**
	 * Checkout: add bpost status after the shipping method
	 *
	 * @param string $shipping_method
	 * @param WC_Abstract_Order $order
	 *
	 * @return string
	 */
	public function bpost_shipping_order_shipping_method( $shipping_method, WC_Abstract_Order $order ) {

		if ( ! $this->is_bpost_shipping_from_order( $order ) ) {
			return $shipping_method;
		}

		$adapter = $this->bpost_shipping_get_adapter();

		if ( ! $adapter->is_admin() ) {
			return $shipping_method;
		}

		$meta_handler = new \WC_BPost_Shipping_Meta_Handler(
			$adapter,
			new \WC_BPost_Shipping_Meta_Type( $adapter ),
			$order->get_id()
		);

		return $shipping_method . ' - ' . bpost__( 'status: ' ) . $meta_handler->get_status();

	}

	/**
	 * @param array $posted
	 *
	 * @return bool
	 */
	private function is_bpost_shipping_from_post( array $posted ) {
		return
			array_key_exists( 'shipping_method', $posted )
			&& is_array( $posted['shipping_method'] )
			&& (
				in_array( BPOST_PLUGIN_ID, $posted['shipping_method'] )
				|| in_array( BPOST_PLUGIN_ID . '_error', $posted['shipping_method'] )
			);
	}

	/**
	 * @param WC_Abstract_Order $order
	 *
	 * @return bool
	 */
	private function is_bpost_shipping_from_order( WC_Abstract_Order $order ) {
		return $this->bpost_shipping_get_shipping_method_id( $order ) === BPOST_PLUGIN_ID;
	}

	/**
	 * Get logger
	 * @return WC_BPost_Shipping_Logger
	 */
	/**
	 * @return WC_BPost_Shipping_Label_Retriever
	 */
	private function bpost_shipping_get_label_retriever() {
		return new WC_BPost_Shipping_Label_Retriever(
			$this->bpost_shipping_get_adapter(),
			new WC_BPost_Shipping_Api_Factory(
				new WC_BPost_Shipping_Options_Base(),
				$this->bpost_shipping_get_logger()
			)
		);
	}

	/**
	 * @return WC_BPost_Shipping_Logger
	 */
	private function bpost_shipping_get_logger() {
		$logger_factory = new WC_BPost_Shipping_Logger_Factory();

		return $logger_factory->get_bpost_logger();

	}

	public function bpost_order_details_box_meta() {
		add_meta_box(
			'bpost-order-box',
			bpost__( 'bpost labels' ),
			array( $this, 'bpost_order_details_box_meta_add' ),
			'shop_order',
			'side',
			'high'
		);
	}

	/**
	 * @param WP_Post $post
	 */
	public function bpost_order_details_box_meta_add( WP_Post $post ) {
		$adapter      = $this->bpost_shipping_get_adapter();
		$meta_handler = new \WC_BPost_Shipping_Meta_Handler(
			$adapter,
			new \WC_BPost_Shipping_Meta_Type( $adapter ),
			$post->ID
		);

		$label_meta_box_controller = new WC_BPost_Shipping_Label_Meta_Box_Controller(
			$adapter,
			new WC_BPost_Shipping_Label_Attachment(
				$adapter,
				new WC_BPost_Shipping_Options_Label( $adapter ),
				new WC_BPost_Shipping_Label_Url_Generator( $adapter, WC() ),
				$this->bpost_shipping_get_label_retriever(),
				new WC_BPost_Shipping_Label_Post( $meta_handler, new WC_Order( $post->ID ) )
			)
		);

		$label_meta_box_controller->load_template();
	}

	/**
	 * @param $actions
	 * @param WC_Order $the_order
	 *
	 * @return string[]
	 */
	public function bpost_order_review_admin_actions( $actions, WC_Order $the_order ) {
		if ( ! $this->is_bpost_shipping_from_order( $the_order ) ) {
			return $actions;
		}

		$adapter      = $this->bpost_shipping_get_adapter();
		$meta_handler = new \WC_BPost_Shipping_Meta_Handler(
			$adapter,
			new \WC_BPost_Shipping_Meta_Type( $adapter ),
			$the_order->get_id()
		);

		$label_attachment = new WC_BPost_Shipping_Label_Attachment(
			$adapter,
			new WC_BPost_Shipping_Options_Label( $adapter ),
			new WC_BPost_Shipping_Label_Url_Generator( $adapter, WC() ),
			$this->bpost_shipping_get_label_retriever(),
			new WC_BPost_Shipping_Label_Post( $meta_handler, $the_order )
		);
		$order_overview   = new WC_BPost_Shipping_Label_Order_Overview( $label_attachment );

		return $order_overview->filter_actions( $actions );

	}

	/**
	 * Process the new bulk actions for changing order status
	 */
	public function bpost_shipping_bulk_action() {
		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action        = $wp_list_table->current_action();

		// Bail out if this is not a bpost action
		if ( strpos( $action, 'bpost_' ) === false ) {
			return;
		}

		$post_ids      = array_map( 'absint', (array) $_REQUEST['post'] );
		$url_generator = new WC_BPost_Shipping_Label_Url_Generator( $this->bpost_shipping_get_adapter(), WC() );

		wp_redirect( $url_generator->get_generate_url( $post_ids ) );
		exit();
	}

	/**
	 * Schedule the cache cleaning for each day on plugin activation
	 */
	public function bpost_shipping_cron_cache_activation() {
		wp_schedule_event( current_time( 'timestamp' ), 'daily', 'cache_clean' );
	}

	/**
	 * Unschedule the cache cleaning
	 */
	public function bpost_shipping_cron_cache_deactivation() {
		wp_clear_scheduled_hook( 'cache_clean' );
	}

	/**
	 * Run bpost cache cleaning cron
	 */
	public function bpost_shipping_cron_cache_clean_run() {
		$cron_runner = new WC_BPost_Shipping_Cron_Runner(
			$this->bpost_shipping_get_adapter(),
			new WC_BPost_Shipping_Options_Base()
		);
		$cron_runner->execute();
	}

	public function enqueue_scripts_frontend() {
		$this->get_assets_management()->wp_enqueue_script();
	}

	public function enqueue_scripts_admin() {
		$this->get_assets_management()->admin_enqueue_script();
	}

	/**
	 * @return WC_BPost_Shipping_Assets_Management
	 */
	private function get_assets_management() {
		return new WC_BPost_Shipping_Assets_Management(
			new WC_BPost_Shipping_Assets_Detector( $this->bpost_shipping_get_adapter() ),
			new WC_BPost_Shipping_Assets_Resources()
		);
	}
}