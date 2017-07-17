<?php

namespace WC_BPost_Shipping\Api\Exception;

abstract class WC_BPost_Shipping_Api_Exception_Abstract extends \LogicException {

	public function get_short_name() {
		return str_replace( 'WC_BPost_Shipping_Api_', '', get_class( $this ) );
	}
}
