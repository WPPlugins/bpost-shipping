<?php
namespace WC_BPost_Shipping\Api;

use Bpost\BpostApiClient\Exception;
use Bpost\BpostApiClient\Geo6\Poi;
use WC_BPost_Shipping\Api\Exception\WC_BPost_Shipping_Api_Exception_Poi_Not_Found;
use WC_BPost_Shipping\Street\WC_BPost_Shipping_Street_Formatter;

/**
 * Class WC_BPost_Shipping_Api_Geo6_Search
 * @package WC_BPost_Shipping\Api
 */
class WC_BPost_Shipping_Api_Geo6_Search {

	/** @var WC_BPost_Shipping_Api_Geo6_Connector */
	private $connector;
	/** @var \WC_BPost_Shipping_Logger */
	private $logger;

	/**
	 * WC_BPost_Shipping_Api_Geo6_Search constructor.
	 *
	 * @param WC_BPost_Shipping_Api_Geo6_Connector $connector
	 * @param \WC_BPost_Shipping_Logger $logger
	 */
	public function __construct(
		WC_BPost_Shipping_Api_Geo6_Connector $connector,
		\WC_BPost_Shipping_Logger $logger
	) {
		$this->connector = $connector;
		$this->logger    = $logger;
	}

	/**
	 * @param string $street
	 * @param string $number
	 * @param string $postal_code
	 * @param int $point_id
	 *
	 * @return string
	 * @internal param array $address
	 */
	public function get_point_type( $point_id, $street, $number, $postal_code ) {
		$results = $this->connector->getNearestServicePoint(
			$street,
			$number,
			$postal_code,
			'nl',
			15, // 1+2+4+8
			20
		);

		foreach ( $results as $result ) {
			/** @var Poi $poi */
			$poi = $result['poi'];
			if ( $poi->getId() === $point_id ) {
				return $poi->getType();
			}
		}

		throw new WC_BPost_Shipping_Api_Exception_Poi_Not_Found();
	}

}
