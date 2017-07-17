<?php
namespace WC_BPost_Shipping\Label;

use Bpost\BpostApiClient\Bpost\Label;
use WC_BPost_Shipping\Adapter\WC_BPost_Shipping_Adapter_Woocommerce;
use WC_BPost_Shipping\Api\WC_BPost_Shipping_Api_Factory;
use WC_BPost_Shipping\Api\WC_BPost_Shipping_Api_Label;
use WC_BPost_Shipping\Label\Exception\WC_BPost_Shipping_Label_Exception_Not_Printable;
use WC_BPost_Shipping\Label\Exception\WC_BPost_Shipping_Label_Exception_Temporary_File;

class WC_BPost_Shipping_Label_Retriever {

	/** @var WC_BPost_Shipping_Adapter_Woocommerce */
	private $adapter_woocommerce;

	/** @var WC_BPost_Shipping_Api_Label */
	private $api_label;


	/**
	 * WC_BPost_Shipping_Label_Retriever constructor.
	 *
	 * @param WC_BPost_Shipping_Adapter_Woocommerce $adapter_woocommerce
	 * @param WC_BPost_Shipping_Api_Factory $api_factory
	 */
	public function __construct(
		WC_BPost_Shipping_Adapter_Woocommerce $adapter_woocommerce,
		WC_BPost_Shipping_Api_Factory $api_factory
	) {
		$this->adapter_woocommerce = $adapter_woocommerce;
		$this->api_label           = $api_factory->get_label();
	}


	/**
	 * @param string $order_reference
	 * @param string $format A4 or A6
	 * @param bool $with_return_labels
	 *
	 * @return string
	 * @throws WC_BPost_Shipping_Label_Exception_Not_Printable
	 * @throws WC_BPost_Shipping_Label_Exception_Temporary_File
	 */
	public function get_label_as_file( $order_reference, $format, $with_return_labels ) {
		$label = $this->api_label->get_label( $order_reference, $format, $with_return_labels );

		return $this->save_tmp_label( $label );
	}

	/**
	 * Save into temp file (/tmp/xxx or whatever a label provided as attachement)
	 *
	 * @param Label $label_retrieved
	 *
	 * @return string temp filename
	 * @throws WC_BPost_Shipping_Label_Exception_Not_Printable
	 * @throws WC_BPost_Shipping_Label_Exception_Temporary_File
	 */
	public function save_tmp_label( Label $label_retrieved = null ) {

		if ( ! $label_retrieved ) {
			throw new WC_BPost_Shipping_Label_Exception_Not_Printable( bpost__( 'This label is not available for print.' ) );
		}

		$tmpfname = $this->adapter_woocommerce->wp_tempnam();
		if ( ! $tmpfname ) {
			throw new WC_BPost_Shipping_Label_Exception_Temporary_File( bpost__( 'Could not create Temporary file.' ) );
		}

		$file = fopen( $tmpfname, 'w' );
		fwrite( $file, $label_retrieved->getBytes() );
		fclose( $file );

		return $tmpfname;
	}

}
