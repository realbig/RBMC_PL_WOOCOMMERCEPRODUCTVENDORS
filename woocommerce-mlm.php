<?php
/*
 * Plugin Name: WooCommerce MLM
 * Description: Adds a multi-level-marketing strategy into WooCommerce for reporting purposes.
 * Author: Real Big Marketing
 * Author URI: http://realbigmarketing.com
 * Version: 0.1.0
 */

define( 'WC_MLM_VERSION', '0.1.0' );
define( 'WC_MLM_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_MLM_URL', plugins_url( '', __FILE__ ) );

/**
 * Class WC_MLM
 *
 * The main plugin class.
 */
class WC_MLM {

	public $vendors;

	/**
	 * Initializes the plugin.
	 */
	function __construct() {

		$this->_init();
		$this->_add_actions();
	}

	function _init() {

		// Create Vendor role
		require_once __DIR__ . '/core/class-wc-mlm-vendor-role.php';
		$this->vendors = new WC_MLM_Vendor_Role();
	}

	/**
	 * Adds all initial plugin actions and filters.
	 *
	 * @access private
	 */
	function _add_actions() {
	}
}

$VendorModifications = new WC_MLM();