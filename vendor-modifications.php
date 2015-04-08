<?php
/*
 * Plugin Name: Vendor Modifications
 * Description: Modifies the WooCommerce Product Vendors plugin to work with hierarchical commissions.
 * Author: Joel Worsham
 * Author URI: http://realbigmarketing.com/about/#staff
 * Version: 1.0.0
 */

/**
 * Class VendorModifications
 *
 * The main plugin class.
 */
class VendorModifications {

	/**
	 * Initializes the plugin.
	 */
	function __construct() {

		add_action( 'plugins_loaded', array( $this, '_add_actions' ) );
	}

	/**
	 * Adds all initial plugin actions and filters.
	 *
	 * @access private
	 */
	function _add_actions() {

		// Allow the taxonomy to have a hierarchy
		add_action( 'init', array( $this, '_modify_vendor_taxonomy' ), 999 );

		// Remove WooCommerce generated metabox
		add_action( 'admin_menu', array( $this, 'remove_meta_box' ), 999 );

		// Filter commission based on hierarchy level
		add_action( 'created_shop_vendor', array( $this, '_modify_default_vendor_commission' ) , 11 );
	}

	/**
	 * Removing default vendor meta box
	 * @return void
	 */
	public function remove_meta_box() {
		remove_meta_box( 'tagdiv-shop_vendor' , 'product', 'side');
	}

	/**
	 * Modifies the "Vendor" taxonomy to allow hierarchical sorting.
	 *
	 * @access private
	 */
	function _modify_vendor_taxonomy() {

		$original_vendor_args = get_taxonomy( 'shop_vendor' );

		$original_vendor_args->hierarchical = true;
		$original_vendor_args->meta_box_cb = null;
		$original_vendor_args->rewrite['hierarchical'] = true;

		register_taxonomy( 'shop_vendor', 'product', (array) $original_vendor_args );
	}

	/**
	 * Modifies the default commission when creating a term to be based on its depth.
	 *
	 * @access private
	 *
	 * @param $term_ID int The term being created.
	 */
	function _modify_default_vendor_commission( $term_ID ) {

		$ancestors = get_ancestors( $term_ID, 'shop_vendor' );
		$depth     = count( $ancestors ) + 1;

		// Modify commission based on depth (if it hasn't been changed from default)
		$vendor_data = get_option( 'shop_vendor_' . (string) $term_ID );

		if ( $vendor_data['commission'] == '50' ) {
			update_option( 'shop_vendor_' . (string) $term_ID, wp_parse_args( array(
				'commission' => (string) round( ( 50 / (int) $depth ) ),
			), $vendor_data ) );
		}

	}
}

$VendorModifications = new VendorModifications();