<?php
use Bpost\BpostApiClient\Bpost\Order\Box;
use WC_BPost_Shipping\Adapter\WC_BPost_Shipping_Adapter_Woocommerce;
use WC_BPost_Shipping\Api\Exception\WC_BPost_Shipping_Api_Exception_Poi_Not_Found;
use WC_BPost_Shipping\Api\WC_BPost_Shipping_Api_Geo6_Search;
use WC_BPost_Shipping\Street\WC_BPost_Shipping_Street_Builder;
use WC_BPost_Shipping\Street\WC_BPost_Shipping_Street_Solver;

/**
 * Class WC_BPost_Shipping_Order_Updater adds bpost info to the order
 */
class WC_BPost_Shipping_Order_Updater {

	/** @var  array */
	private $data;
	/** @var  WC_Order */
	private $order;
	/** @var WC_BPost_Shipping_Api_Geo6_Search */
	private $api_geo6_search;

	/** @var WC_BPost_Shipping_Meta_Handler */
	private $meta_handler;

	/**
	 * @param WC_Order $order
	 * @param array $data
	 * @param WC_BPost_Shipping_Api_Geo6_Search $api_geo6_search
	 */
	public function __construct( WC_Order $order, array $data, WC_BPost_Shipping_Api_Geo6_Search $api_geo6_search ) {
		$this->order           = $order;
		$this->data            = $data;
		$this->api_geo6_search = $api_geo6_search;

		$this->init_meta_handler();
	}

	private function init_meta_handler() {
		$adapter            = new WC_BPost_Shipping_Adapter_Woocommerce();
		$this->meta_handler = new WC_BPost_Shipping_Meta_Handler(
			$adapter,
			new WC_BPost_Shipping_Meta_Type( $adapter ),
			$this->order->get_id()
		);
	}

	/**
	 * do the update
	 */
	public function update_order() {
		$shipping_item_id = $this->get_shipping_item_id();

		$this->update_shipping_method( $shipping_item_id );
		$this->update_meta();
	}

	/**
	 * @return int
	 */
	private function get_shipping_item_id() {
		$shipping_items      = $this->order->get_items( 'shipping' );
		$shipping_items_keys = array_keys( $shipping_items );

		return $shipping_items_keys[0];
	}

	/**
	 * @param $shipping_item_id
	 */
	private function update_shipping_method( $shipping_item_id ) {
		wc_update_order_item( $shipping_item_id, array(
			'order_item_name' => $this->get_delivery_method_translated(),
		) );
	}

	/**
	 * @return string return translated delivery method
	 */
	private function get_delivery_method_translated() {
		$delivery_method = new WC_BPost_Shipping_Delivery_Method( $this->get_data( 'bpost_delivery_method' ) );

		return bpost__( 'bpost -' ) . ' ' . bpost__( $delivery_method->get_delivery_method_as_string() );
	}

	/**
	 * @param string $item
	 *
	 * @return string
	 */
	private function get_data( $item ) {
		return $this->data[ $item ];
	}

	private function update_meta() {
		$delivery_method = new WC_BPost_Shipping_Delivery_Method( $this->get_data( 'bpost_delivery_method' ) );

		$this->meta_handler->set_delivery_date( $this->get_data( 'bpost_delivery_date' ) );
		$this->meta_handler->set_delivery_method( $delivery_method->get_delivery_method_as_string() );
		$this->meta_handler->set_order_reference( $this->get_data( 'bpost_order_reference' ) );
		$this->meta_handler->set_phone( $this->get_data( 'bpost_phone' ) );
		$this->meta_handler->set_email( $this->get_data( 'bpost_email' ) );
		$this->meta_handler->set_status( Box::BOX_STATUS_OPEN );

		if ( $this->get_data( 'bpost_delivery_point_id' ) ) {
			$this->meta_handler->set_delivery_point( $delivery_method->get_delivery_point( $this->get_data( 'bpost_postal_location' ) ) );
			$this->meta_handler->set_delivery_point_id( $this->get_data( 'bpost_delivery_point_id' ) );

			$this->update_bpost_point_type();
		}
	}

	public function update_bpost_point_type() {
		$point_type = 0;

		$shipping_address = $this->order->get_address( 'shipping' );

		$street_builder = new WC_BPost_Shipping_Street_Builder(
			new WC_BPost_Shipping_Street_Solver()
		);

		$street = $street_builder->get_street_items(
			$shipping_address['address_1'],
			$shipping_address['address_2']
		);

		try {
			$point_type = $this->api_geo6_search->get_point_type(
				$this->get_data( 'bpost_delivery_point_id' ),
				$street->get_street(),
				$street->get_number(),
				$shipping_address['postcode']
			);
		} catch( WC_BPost_Shipping_Api_Exception_Poi_Not_Found $ex ) {
		}

		$this->meta_handler->set_delivery_point_type( $point_type );
	}
}
