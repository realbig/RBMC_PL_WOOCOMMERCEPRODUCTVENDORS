<?php
/*
 * Plugin Name: WooCommerce MLM
 * Description: Adds a multi-level-marketing strategy into WooCommerce for reporting purposes.
 * Author: Real Big Marketing
 * Author URI: http://realbigmarketing.com
 * Version: 0.1.9
 */

define( 'WC_MLM_VERSION', '0.1.9' );
define( 'WC_MLM_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_MLM_URL', plugins_url( '', __FILE__ ) );

/**
 * Class WC_MLM_Reporting
 *
 * The main plugin class.
 */
class WC_MLM {

	/**
	 * @var WC_MLM_Vendors
	 */
	public $vendors;

	/**
	 * @var WC_MLM_Reporting
	 */
	public $reporting;

	/**
	 * @var WC_MLM_VendorModifications
	 */
	public $vendor_modifications;

	/**
	 * @var WC_MLM_Admin
	 */
	public $admin;
	public $pages = array();

	/**
	 * Initializes the plugin.
	 */
	function __construct() {

		$this->_init();
		$this->_add_actions();
	}

	function _init() {

		// Includes
		require_once __DIR__ . '/core/includes.php';

		// Helpers
		require_once __DIR__ . '/core/functions.php';

		// Create Vendor system
		require_once __DIR__ . '/core/class-wc-mlm-vendors.php';
		$this->vendors = new WC_MLM_Vendors();

		// Create Reporting system
		require_once __DIR__ . '/core/class-wc-mlm-reporting.php';
		$this->reporting = new WC_MLM_Reporting();

		// Create vendor modifications system
		require_once __DIR__ . '/core/class-wc-mlm-vendor-modifications.php';
		$this->vendor_modifications = new WC_MLM_VendorModifications();

		// Admin pages
		if ( is_admin() ) {
			require_once __DIR__ . '/core/class-wc-mlm-admin.php';
			$this->admin = new WC_MLM_Admin();
		}
	}

	/**
	 * Adds all initial plugin actions and filters.
	 *
	 * @access private
	 */
	function _add_actions() {

		add_action( 'init', array( $this, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Activation
		register_activation_hook( __FILE__, array( $this, '_activate' ) );

		// Deactivation
		register_deactivation_hook( __FILE__, array( $this, '_deactivate' ) );
	}

	function _activate() {

		$this->_create_vendor_role();
	}

	function _deactivate() {

		$this->_delete_vendor_role();
	}

	function _create_vendor_role() {

		global $wp_roles;

		// Just in case
		$this->_delete_vendor_role();

		$all_roles = $wp_roles->roles;

		$capabilities              = $all_roles['subscriber']['capabilities'];
		$capabilities['is_vendor'] = true;
		$capabilities['upload_files'] = true;

		add_role( 'vendor', wc_mlm_setting( 'vendor_verbage' ), $capabilities );
	}

	function _delete_vendor_role() {

		global $wp_roles;

		$all_roles = $wp_roles->roles;

		if ( isset( $all_roles['vendor'] ) ) {
			remove_role( 'vendor' );
		}
	}

	function register_scripts() {

		// Admin
		wp_register_style(
			'wc-mlm-admin',
			WC_MLM_URL . '/assets/css/wc-mlm-admin.min.css',
			null,
			WC_MLM_VERSION
		);

		// Reporting
		wp_register_script(
			'wc-mlm-reporting',
			WC_MLM_URL . '/assets/js/source/nomin/reporting.js',
			array( 'jquery' ),
			WC_MLM_VERSION
		);

		// Front
		wp_register_style(
			'wc-mlm-front',
			WC_MLM_URL . '/assets/css/wc-mlm-front.min.css',
			array(),
			WC_MLM_VERSION
		);

		// Vendor

		// jQuery UI
		wp_register_style(
			'wc-mlm-jquery-ui-style',
			'https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css',
			null,
			'1.11.4'
		);
	}

	function enqueue_scripts() {

		wp_enqueue_script( 'wc-mlm-reporting' );
		wp_enqueue_style( 'wc-mlm-front' );
	}

	function admin_enqueue_scripts() {

		wp_enqueue_style( 'wc-mlm-admin' );
	}
}

$WC_MLM = new WC_MLM();