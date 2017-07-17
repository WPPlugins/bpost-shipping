<?php

namespace WC_BPost_Shipping\Factory;

use Bpost\BpostApiClient\Bpost;
use WC_BPost_Shipping\Adapter\WC_BPost_Shipping_Adapter_Woocommerce;

/**
 * Class WC_BPost_Shipping_Factory_Form builds form for bpost admin page
 * @package WC_BPost_Shipping\Factory
 */
class WC_BPost_Shipping_Factory_Form {
	/** @var WC_BPost_Shipping_Adapter_Woocommerce */
	private $adapter;

	/**
	 * WC_BPost_Shipping_Factory_Form constructor.
	 *
	 * @param WC_BPost_Shipping_Adapter_Woocommerce $adapter
	 */
	public function __construct( WC_BPost_Shipping_Adapter_Woocommerce $adapter ) {
		$this->adapter = $adapter;
	}

	/**
	 * @param string $title Plugin id/title used for activation/deactivation
	 *
	 * @return array
	 */
	public function get_settings_form( $title ) {
		return array_merge(
			array(
				'enabled' => array(
					'title'       => bpost__( 'Enable' ),
					'type'        => 'checkbox',
					'label'       => sprintf( bpost__( 'Enable %s' ), $title ),
					'default'     => 'yes',
					'description' => '',
				),
			),
			$this->get_api(),
			$this->get_logs(),
			$this->get_free_shipping(),
			$this->get_label(),
			$this->get_google()
		);
	}

	/**
	 * API part
	 * @return array
	 */
	public function get_api() {
		return array(
			'api_title'      => array(
				'title' => bpost__( 'API: Shipping Manager connection info' ),
				'type'  => 'title',
			),
			'api_account_id' => array(
				'title'       => bpost__( 'Account id' ),
				'type'        => 'text',
				'description' => bpost__( 'You need a user account from bpost to use this module. Call 02/201 11 11 for more information or visit:  http://bpost.freshdesk.com/solution/articles/174847' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'api_passphrase' => array(
				'title'       => bpost__( 'Passphrase' ),
				'type'        => 'text',
				'description' => bpost__( 'Enter your account id' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'api_url'        => array(
				'title'       => bpost__( 'API URL' ),
				'type'        => 'text',
				'description' => bpost__( 'Enter the front-end URL of the API' ),
				'desc_tip'    => true,
				// TODO Bpost::API_URL is repeated at three linked position. Is it really needed ?
				'default'     => Bpost::API_URL,
			),
		);
	}

	/**
	 * Logs part
	 * @return array
	 */
	public function get_logs() {
		return array(
			'logs_title'      => array(
				'title'       => bpost__( 'Logs' ),
				'type'        => 'title',
				'description' => sprintf(
					bpost__( '<p>When errors occur, the plugin write logs in an file. You can see the file in WooCommerce status (%sWooCommerce > System Status > Logs%s).
					<p>To see interactions between the plugin and the bpost API, and the bpost Shipping Manager, you can check the "Debug mode".<br />
					To clean the log file, check the "Clean" checkbox.</p>' ),
					'<a href="' . $this->adapter->admin_url( 'admin.php?page=wc-status&tab=logs' ) . '" title="' . bpost__( 'Go to the logs page' ) . '">',
					'</a>'
				),
			),
			'logs_debug_mode' => array(
				'title'   => bpost__( 'Debug mode' ),
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => bpost__( 'Log interactions between plugin and bpost services (API/Shipping Manager)' ),
			),
			'logs_clean'      => array(
				'title'   => bpost__( 'Clean' ),
				'type'    => 'checkbox',
				'label'   => bpost__( 'Clear the log file' ),
				'default' => 'no',
			),
		);
	}

	/**
	 * Free Shipping part
	 * @return array
	 */
	public function get_free_shipping() {
		return array(
			'free_shipping_title' => array(
				'title'       => bpost__( 'Free shipping' ),
				'type'        => 'title',
				'description' => sprintf(
					bpost__( 'This plugin manages the free shipping coupons. Setup them %s here %s
					<p>Free shipping is allowed only for countries configured in SHM backend and %s Woocommerce > Settings > General %s > Specific countries.<br />
					Make sure you ship to corresponding countries (%sWoocommerce > Settings > Shipping > Shipping options%s > Restrict shipping to Location(s)).</p>' ),
					'<a href="' . $this->adapter->admin_url( 'edit.php?post_type=shop_coupon' ) . '" title="' . bpost__( 'Go to the coupons management page' ) . '">',
					'</a>',
					'<a href="' . $this->adapter->admin_url( 'admin.php?page=wc-settings&tab=general' ) . '">',
					'</a>',
					'<a href="' . $this->adapter->admin_url( 'admin.php?page=wc-settings&tab=shipping' ) . '">',
					'</a>'
				),
			),
			'free_shipping_items' => array(
				'title'   => bpost__( 'Free shipping items' ),
				'type'    => 'jsonarray',
				'default' => ''
			),
		);
	}

	/**
	 * Labelling part
	 * @return array
	 */
	public function get_label() {
		return array(
			'label_title' => array(
				'title' => bpost__( 'Label params' ),
				'type'  => 'title',
			),

			'label_format' => array(
				'title'   => bpost__( 'Print format' ),
				'default' => 'A4',
				'type'    => 'select',
				'options' => array(
					'A4' => bpost__( 'A4' ),
					'A6' => bpost__( 'A6' ),

				),
			),
			'label_return' => array(
				'title'   => bpost__( 'Print return label' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),

			'label_cache_time' => array(
				'default' => '',
				'title'   => bpost__( 'Cache time' ),
				'type'    => 'select',
				'options' => array(
					'P0W' => bpost__( 'No cache (could be slower)' ),
					'P1W' => bpost__( '1 week' ),
					'P2W' => bpost__( '2 weeks' ),
					'P3W' => bpost__( '3 weeks' ),
					'P1M' => bpost__( '1 month' ),
					'P2M' => bpost__( '2 month' ),
					'P6M' => bpost__( '6 months' ),
					'P1Y' => bpost__( '1 year' ),
					''    => bpost__( "Infinity (we don't clean cache)" ),

				),
			),
		);
	}

	/**
	 * Google API key part
	 * @return array
	 */
	public function get_google() {
		return array(
			'google_title' => array(
				'title' => bpost__( 'Google' ),
				'type'  => 'title',
			),

			'google_api_key' => array(
				'title' => bpost__( 'API key for maps' ),
			),

		);
	}
}
