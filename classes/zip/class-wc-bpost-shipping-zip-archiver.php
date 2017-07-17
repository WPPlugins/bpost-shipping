<?php
namespace WC_BPost_Shipping\Zip;

use WC_BPost_Shipping\Adapter\WC_BPost_Shipping_Adapter_Woocommerce;
use WC_BPost_Shipping\Label\WC_BPost_Shipping_Label_Attachment;

class WC_BPost_Shipping_Zip_Archiver {
	/** @var \ZipArchive */
	private $zip_archive;
	private $zip_temp_file;
	/** @var WC_BPost_Shipping_Adapter_Woocommerce */
	private $adapter;

	/**
	 * WC_BPost_Shipping_Zip_Archiver constructor.
	 *
	 * @param WC_BPost_Shipping_Adapter_Woocommerce $adapter
	 * @param \ZipArchive $zip_archive
	 */
	public function __construct( WC_BPost_Shipping_Adapter_Woocommerce $adapter, \ZipArchive $zip_archive ) {
		$this->adapter      = $adapter;
		$this->zip_archive  = $zip_archive;

		$this->zip_temp_file = $adapter->wp_tempnam( "zip" );

		$openStatus = $this->zip_archive->open( $this->zip_temp_file, \ZipArchive::OVERWRITE );
		if (true !== $openStatus) {
			throw new \InvalidArgumentException("Zip file opening error");
		}
	}

	/**
	 * @param WC_BPost_Shipping_Label_Attachment[] $attached_files
	 */
	public function build_archive( array $attached_files ) {
		$upload = $this->adapter->wp_upload_dir();


		// Stuff with content
		foreach ( $attached_files as $attached_file ) {
			$final_filename = $this->get_filename( $attached_file->get_url(), $upload );
			$this->zip_archive->addFile( $final_filename, basename( $final_filename ) );
		}

		// Close
		$this->zip_archive->close();
	}

	/**
	 * @param string $zip_filename
	 */
	public function send_archive( $zip_filename ) {
		header( 'Content-Type: application/zip' );
		header( 'Content-Length: ' . filesize( $this->zip_temp_file ) );
		header( 'Content-Disposition: attachment; filename="' . $zip_filename . '.zip"' );
		readfile( $this->zip_temp_file );
		unlink( $this->zip_temp_file );
	}

	/**
	 * Search and use the common part to do an overlap with string.
	 * Check Unit tests to see how it's work
	 * @param string $left
	 * @param string $right
	 *
	 * @return string
	 */
	public function merge_overlap( $left, $right ) {
		$l = strlen( $right );
		// keep checking smaller portions of right
		while ( $l > 0 && substr( $left, $l * - 1 ) != substr( $right, 0, $l ) ) {
			$l --;
		}

		return $left . substr( $right, $l );
	}

	/**
	 * Provide filename using overlap
	 * @param string[] $url
	 * @param string[] $upload
	 *
	 * @return string
	 */
	public function get_filename( $url, $upload ) {
		$url = parse_url( $url );

		return $this->merge_overlap( $upload['basedir'], $url['path'] );
	}


}