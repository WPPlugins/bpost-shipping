<?php
namespace WC_BPost_Shipping\Api;


use Bpost\BpostApiClient\Bpost\Label;
use Bpost\BpostApiClient\Bpost\Order\Box;
use Bpost\BpostApiClient\BpostException;
use Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidValueException;

class WC_BPost_Shipping_Api_Label {

	/** @var WC_BPost_Shipping_Api_Connector */
	private $connector;
	/** @var \WC_BPost_Shipping_Logger */
	private $logger;

	public function __construct( WC_BPost_Shipping_Api_Connector $connector, \WC_BPost_Shipping_Logger $logger ) {
		$this->connector = $connector;
		$this->logger = $logger;
	}

	/**
	 * @param string $order_reference
	 * @param string $format A6 or A4 (please check consts)
	 * @param bool $with_return_labels
	 *
	 * @return Label
	 */
	public function get_label( $order_reference, $format, $with_return_labels ) {
		$labels = array();
		try {
			$labels = $this->connector->createLabelForOrder( $order_reference, $format, $with_return_labels, true );
		}catch (BpostException $ex) {
			$this->logger->log_exception($ex);
		}
		if (1 !== count($labels)) {
			$labels = $this->get_already_printed_labels( $order_reference, $format );
			if (1 !== count($labels)) {
				return null;
			}
		}

		return $labels[0];
	}

	/**
	 * @param array $order_references
	 * @param $format
	 *
	 * @return Label[]
	 * @throws BpostInvalidValueException
	 */
	public function get_labels( array $order_references, $format ) {
		return $this->connector->createLabelInBulkForOrders( $order_references, $format, false, true );
	}

	/**
	 * @param string $order_reference
	 * @return string[]
	 */
	public function get_barcodes($order_reference) {
		return array_map(
			function(Box $box ){
				return $box->getBarcode();
			},
			$this->connector->fetchOrder($order_reference)->getBoxes()
		);
	}

	/**
	 * @param $order_reference
	 * @param $format
	 *
	 * @return Label[]
	 */
	private function get_already_printed_labels( $order_reference, $format ) {
		$barcodes = $this->get_barcodes( $order_reference );

		$labels = array();
		try {
			// $withReturnLabels provided by createLabelForBox doesn't work.
			// It's hardcoded to false to prevent any dream
			$labels = $this->connector->createLabelForBox( $barcodes[0], $format, false, true );
		}catch(BpostException $ex) {
			$this->logger->log_exception($ex);
		}
		return $labels;
	}
}
