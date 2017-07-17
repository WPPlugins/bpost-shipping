<?php
namespace WC_BPost_Shipping\Api;

use Bpost\BpostApiClient\Bpost\Order\Box;

/**
 * Class WC_BPost_Shipping_Api_Status
 * @package WC_BPost_Shipping\Api
 */
class WC_BPost_Shipping_Api_Status {

	/** @var WC_BPost_Shipping_Api_Connector */
	private $connector;
	/** @var \WC_BPost_Shipping_Logger */
	private $logger;

	/**
	 * WC_BPost_Shipping_Api_Status constructor.
	 *
	 * @param WC_BPost_Shipping_Api_Connector $connector
	 * @param \WC_BPost_Shipping_Logger $logger
	 */
	public function __construct( WC_BPost_Shipping_Api_Connector $connector, \WC_BPost_Shipping_Logger $logger ) {
		$this->connector = $connector;
		$this->logger    = $logger;
	}

	/**
	 * @param string $order_reference
	 *
	 * @return string
	 */
	public function get_status( $order_reference ) {
		$order = $this->connector->fetchOrder( $order_reference );
		$boxes = $order->getBoxes();
		/** @var Box $box */
		$box   = current( $boxes );

		return $box->getStatus();
	}
}
