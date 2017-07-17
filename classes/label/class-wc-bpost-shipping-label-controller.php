<?php
namespace WC_BPost_Shipping\Label;

use WC_BPost_Shipping\Adapter\WC_BPost_Shipping_Adapter_Woocommerce;
use WC_BPost_Shipping\Controller\WC_BPost_Shipping_Controller_Base;
use WC_BPost_Shipping\Options\WC_BPost_Shipping_Options_Base;
use WC_BPost_Shipping\Options\WC_BPost_Shipping_Options_Label;
use WC_BPost_Shipping\Zip\WC_BPost_Shipping_Zip_Archiver;
use WC_BPost_Shipping\Zip\WC_BPost_Shipping_Zip_Filename;

/**
 * Class WC_BPost_Shipping_Label_Controller
 * @package WC_BPost_Shipping\Label
 */
class WC_BPost_Shipping_Label_Controller extends WC_BPost_Shipping_Controller_Base {
	const ORDER_REFERENCE_KEY = 'order_reference';
	const ATTACHMENT_ID_KEY = 'attachment_id';

	private $wp_once;
	/** @var int[] */
	private $post_ids;

	/** @var WC_BPost_Shipping_Label_Retriever */
	private $label_retriever;
	/** @var WC_BPost_Shipping_Label_Url_Generator */
	private $url_generator;
	/** @var WC_BPost_Shipping_Options_Base */
	private $options_label;
	/** @var WC_BPost_Shipping_Zip_Filename */
	private $zip_filename;

	/**
	 * WC_BPost_Shipping_Label_Controller constructor.
	 *
	 * @param WC_BPost_Shipping_Adapter_Woocommerce $adapter
	 * @param WC_BPost_Shipping_Options_Label $options_label
	 * @param WC_BPost_Shipping_Label_Url_Generator $url_generator
	 * @param WC_BPost_Shipping_Label_Retriever $label_retriever
	 * @param WC_BPost_Shipping_Zip_Filename $zip_filename
	 * @param array $external_data
	 */
	public function __construct(
		WC_BPost_Shipping_Adapter_Woocommerce $adapter,
		WC_BPost_Shipping_Options_Label $options_label,
		WC_BPost_Shipping_Label_Url_Generator $url_generator,
		WC_BPost_Shipping_Label_Retriever $label_retriever,
		WC_BPost_Shipping_Zip_Filename $zip_filename,
		$external_data
	) {
		parent::__construct( $adapter );
		$this->zip_filename = $zip_filename;

		$this->label_retriever = $label_retriever;

		$this->wp_once       = $external_data['wp_once']; //wp_verify_nonce don't need to filter it
		$this->post_ids      = filter_var( $external_data['post_ids'], FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY );
		$this->url_generator = $url_generator;
		$this->options_label = $options_label;
	}


	/**
	 * @return bool
	 */
	public function verify_wp_one() {
		return (bool) wp_verify_nonce( $this->wp_once );
	}

	/**
	 * This function provides a contract to use to load a template using controller.
	 */
	public function load_template() {
		if ( ! $this->verify_wp_one() ) {
			return new \WP_Error( 'verify_wp_once', __( 'Security issue.' ) );
		}


		/** @var WC_BPost_Shipping_Label_Attachment[] $attached_files */
		$attached_files = array();

		foreach ( $this->post_ids as $post_id ) {
			$meta_handler = new \WC_BPost_Shipping_Meta_Handler(
				$this->adapter,
				new \WC_BPost_Shipping_Meta_Type( $this->adapter ),
				$post_id
			);
			$label_post   = new WC_BPost_Shipping_Label_Post( $meta_handler, new \WC_Order( $post_id ) );
			$label_attach = new WC_BPost_Shipping_Label_Attachment(
				$this->adapter,
				$this->options_label,
				$this->url_generator,
				$this->label_retriever,
				$label_post
			);

			$attached_files[] = $label_attach;
		}

		if ( count( $attached_files ) === 1 ) {
			wp_redirect( $attached_files[0]->get_url() );
			die();
		}

		$zip_archiver = new WC_BPost_Shipping_Zip_Archiver( $this->adapter, new \ZipArchive() );
		$zip_archiver->build_archive( $attached_files );
		$zip_archiver->send_archive( $this->zip_filename->get_filename() );
		die();
	}
}