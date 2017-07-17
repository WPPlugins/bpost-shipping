<?php
namespace WC_BPost_Shipping\Options;

use WC_BPost_Shipping\Adapter\WC_BPost_Shipping_Adapter_Woocommerce;

/**
 * Class WC_BPost_Shipping_Options_Decorator adds functions to Options without touching Options class
 * @package WC_BPost_Shipping\Decorator
 */
class WC_BPost_Shipping_Options_Label extends WC_BPost_Shipping_Options_Base {
	/** @var WC_BPost_Shipping_Adapter_Woocommerce */
	private $adapter_woocommerce;

	/**
	 * WC_BPost_Shipping_Options_Decorator constructor.
	 *
	 * @param WC_BPost_Shipping_Adapter_Woocommerce $adapter_woocommerce
	 */
	public function __construct( WC_BPost_Shipping_Adapter_Woocommerce $adapter_woocommerce ) {
		$this->adapter_woocommerce = $adapter_woocommerce;
	}

	/**
	 * Returns if the shop is defined in Belgium or not
	 * @return bool true if it, false if not
	 */
	public function is_local_shop() {
		$base_location = $this->adapter_woocommerce->wc_get_base_location();
		if ( ! array_key_exists( 'country', $base_location ) ) {
			return false;
		}

		return 'BE' === $base_location['country'];
	}


	/**
	 * @return bool
	 */
	public function is_return_label_enabled() {
		return $this->get_option( 'label_return' ) === 'yes';
	}

	/**
	 * @return string A4 or A6
	 */
	public function get_label_format() {
		return $this->get_option( 'label_format' ) ?: 'A6';
	}

}
