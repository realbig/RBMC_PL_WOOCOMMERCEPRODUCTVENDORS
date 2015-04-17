<?php
/*
 * Plugin Name: WooCommerce Vendor Modifications
 * Description: Modifies the WooCommerce Product Vendors plugin to work with hierarchical commissions.
 * Author: Real Big Marketing
 * Author URI: http://realbigmarketing.com
 * Version: 0.1.0
 */

define( 'WC_VENDOR_MODIFICATIONS_VERSION', '0.1.0' );
define( 'WC_VENDOR_MODIFICATIONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_VENDOR_MODIFICATIONS_URL', plugins_url( '', __FILE__ ) );

/**
 * Class VendorModifications
 *
 * The main plugin class.
 */
class VendorModifications {

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
		require_once __DIR__ . '/core/class-wcmlm-vendor-role.php';
		$this->vendors = new WCMLM_Vendor_Role();
	}

	/**
	 * Adds all initial plugin actions and filters.
	 *
	 * @access private
	 */
	function _add_actions() {
	}
}

$VendorModifications = new VendorModifications();