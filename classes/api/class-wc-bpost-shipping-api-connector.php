<?php

namespace WC_BPost_Shipping\Api;

//TODO don't extend bpost && use an instance. Make bridge to avoid any direct call to parent (isolation principle)
use Bpost\BpostApiClient\Bpost;
use Bpost\BpostApiClient\BpostException;

class WC_BPost_Shipping_Api_Connector extends Bpost {

	/**
	 * @return Bpost\ProductConfiguration
	 */
	private $productConfig;

	/**
	 * @return bool
	 */
	public function is_online() {
		try {
			$this->fetchProductConfig();

			return true;
		} catch( BpostException $exception ) {
			return false;
		}
	}

	/**
	 * @return Bpost\ProductConfiguration
	 */
	public function fetchProductConfig() {
		if ( ! $this->productConfig ) {
			$this->productConfig = parent::fetchProductConfig();
		}

		return $this->productConfig;
	}
}
