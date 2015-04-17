<?php

/**
 * Class VendorModifications
 *
 * The main plugin class.
 */
class VendorModifications_BAK {

	/**
	 * The percentage to decrease commission.
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	public $commission_percentage = 15;

	public $current_vendor_ID_filter;

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

		// Register / Add scripts
//		add_action( 'init', array( $this, '_register_scripts' ) );
//		add_action( 'admin_enqueue_scripts', array( $this, '_add_admin_scripts' ) );
//		add_action( 'wp_enqueue_scripts', array( $this, '_add_front_scripts' ) );

		// Create a commission column for Shop Vendors page
//		add_filter( 'manage_edit-shop_vendor_columns', array( $this, '_add_commission_column_shop_vendor' ) );
//		add_filter( 'manage_shop_vendor_custom_column', array(
//			$this,
//			'_commission_column_shop_vendor_output'
//		), 10, 3 );

		// Shortcode
//		add_shortcode( 'vendor_total_earnings_report_downstream', array(
//			$this,
//			'sc_vendor_total_earnings_report_downstream'
//		) );

		// Setup shop_commission columns
//		add_filter( 'manage_edit-shop_commission_columns', array( $this, '_add_parent_column_shop_commission' ) );
//		add_filter( 'manage_shop_commission_posts_custom_column', array( $this, '_parent_column_shop_commission_output' ), 10, 2 );
//		add_filter( 'manage_edit-shop_commission_sortable_columns', array( $this, '_shop_commission_sortable_columns' ) );
//		add_action( 'pre_get_posts', array( $this, '_shop_commission_sort_columns' ) );
//
	}

	/**
	 * Registers all plugin scripts.
	 *
	 * @since  0.1.0
	 * @access private
	 */
	function _register_scripts() {

		wp_register_script(
			'wc-vendor-modifications',
			WC_VENDOR_MODIFICATIONS_URL . '/assets/js/wc-vendor-modifications.min.js',
			array( 'jquery' ),
			WC_VENDOR_MODIFICATIONS_VERSION,
			true
		);

		wp_register_style(
			'wc-vendor-modifications-front',
			WC_VENDOR_MODIFICATIONS_URL . '/assets/css/wc-vendor-modifications-front.min.css',
			array(),
			WC_VENDOR_MODIFICATIONS_VERSION
		);
	}

	/**
	 * Includes admins cripts.
	 *
	 * @since  0.1.0
	 * @access private
	 */
	function _add_admin_scripts() {

		// Localize data
		wp_localize_script(
			'wc-vendor-modifications',
			'WC_Vendor_Modifications_Data',
			apply_filters( 'wc_vendor_modifications_data', array(
				'commission_percentage' => $this->commission_percentage,
			) )
		);

		wp_enqueue_script( 'wc-vendor-modifications' );
	}

	/**
	 * Includes front-end scripts.
	 *
	 * @since  0.1.0
	 * @access private
	 */
	function _add_front_scripts() {

		wp_enqueue_style( 'wc-vendor-modifications-front' );
	}

	/**
	 * Adds the "Commission" column to the "Shop Vendor" taxonomy page.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param $columns array The old columns.
	 *
	 * @return array The new columns.
	 */
	function _add_commission_column_shop_vendor( $columns ) {

		$columns['commission'] = 'Commission';

		return $columns;
	}

	/**
	 * The output for the "Commission" column.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param $output      mixed The column output.
	 * @param $column_name string The name of the current column.
	 * @param $term_ID     int The ID of the current term.
	 *
	 * @return mixed The column output.
	 */
	function _commission_column_shop_vendor_output( $output, $column_name, $term_ID ) {

		if ( $column_name != 'commission' ) {
			return $output;
		}

		$data = get_option( "shop_vendor_$term_ID" );

		return isset( $data['commission'] ) ? $data['commission'] . '%' : 'Not Set';
	}

	/**
	 * Adds the "Parent" column to the "Shop Commission" posts page.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param $columns array The old columns.
	 *
	 * @return array The new columns.
	 */
	function _add_parent_column_shop_commission ( $columns ) {

		$original_columns = $columns;
		$columns          = array();

		foreach ( $original_columns as $ID => $label ) {

			$columns[ $ID ] = $label;

			if ( $ID == '_commission_vendor' ) {
				$columns['_commission_parent'] = 'Parent';

			}
		}

		return $columns;
	}

	/**
	 * The output for the "Parent" column.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param $column_name string The name of the current column.
	 * @param $post_ID     int The ID of the current term.
	 *
	 * @return mixed The column output.
	 */
	function _parent_column_shop_commission_output( $column_name, $post_ID ) {

		if ( $column_name != '_commission_parent' ) {
			return;
		}

		$vendor = get_post_meta( $post_ID, '_commission_vendor', true );
		$parent = get_ancestors( $vendor, 'shop_vendor' );

		if ( isset( $parent[0] ) ) {

			$parent = get_vendor( $parent[0] );

			if ( $parent ) {

				$edit_url = 'edit-tags.php?action=edit&taxonomy=shop_vendor&tag_ID=' . $parent->ID . '&post_type=product';
				echo '<a href="' . esc_url( $edit_url ) . '">' . $parent->title . '</a>';
			}
		} else {
			echo 'No parent';
		}
	}

	/**
	 * Allows sorting of specific columns.
	 *
	 * @since 0.1.0
	 * @access private
	 *
	 * @param $columns array The old columns.
	 *
	 * @return array The new columns.
	 */
	function _shop_commission_sortable_columns( $columns ) {

		$columns['_commission_parent'] = '_commission_parent';
		$columns['_commission_vendor'] = '_commission_vendor';

		return $columns;
	}

	/**
	 * Modifies the query when sorting by custom columns.
	 *
	 * @since 0.1.0
	 * @access private
	 *
	 * @param $query WP_Query The current post query.
	 */
	function _shop_commission_sort_columns ( $query ) {

		if ( ! is_admin() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( '_commission_parent' == $orderby ) {
			$query->set( 'meta_key', '_commission_vendor_parent_name' );
			$query->set( 'orderby', 'meta_value' );
		}

		if ( '_commission_vendor' == $orderby ) {
			$query->set( 'meta_key', '_commission_vendor_name' );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Adds on commission meta of the vendor name (slug) for sorting in the admin columns.
	 *
	 * @since 0.1.0
	 * @access private
	 *
	 * @param $post_ID int The current post ID.
	 */
	function _add_commission_meta( $post_ID ) {

		$vendor = get_post_meta( $post_ID, '_commission_vendor', true );

		if ( ! $vendor ) {
			return;
		}

		if ( $vendor = get_vendor( $vendor ) ) {
			update_post_meta( $post_ID, '_commission_vendor_name', $vendor->slug );
		}

		$parent = get_ancestors( $vendor->ID, 'shop_vendor' );

		if ( ! isset( $parent[0] ) ) {
			return;
		}
		if ( $parent = get_vendor( $parent[0] ) ) {
			update_post_meta( $post_ID, '_commission_vendor_parent_name', $parent->slug );
		}
	}

	/**
	 * Outputs vendor sales information including all downstream vendors.
	 *
	 * @since 0.1.0
	 *
	 * @return string The HTML.
	 */
	public function sc_vendor_total_earnings_report_downstream() {


		global $wc_product_vendors;

		if ( ! ( $vendor_ID = is_vendor() ) ) {
			return '';
		}

		$vendor_children = get_term_children( $vendor_ID, 'shop_vendor' );

		$vendors = array_merge( array(
			(int) $vendor_ID,
		), $vendor_children );

		$html = '<div class="wcvm-vendor-report">';

		add_filter( 'product_vendors_is_vendor', array( $this, 'filter_current_vendor_ID' ) );

		foreach ( $vendors as $vendor_ID ) {

			$this->current_vendor_ID_filter = $vendor_ID;

			if ( $current_vendor = get_vendor( $vendor_ID ) ) {
				$link = get_term_link( $vendor_ID, 'shop_vendor' );
				$html .= "<h2 class=\"wcvm-vendor-title\"><a href=\"$link\">$current_vendor->title</a></h2>";
			}

			$html .= $wc_product_vendors->vendor_total_earnings_report();
		}

		remove_filter( 'product_vendors_is_vendor', array( $this, 'filter_current_vendor_ID' ) );

		$html .= '</div>';

		return $html;
	}

	/**
	 * Filters the current vendor ID.
	 *
	 * @since 0.1.0
	 *
	 * @return int The custom vendor ID.
	 */
	public function filter_current_vendor_ID() {
		return $this->current_vendor_ID_filter;
	}
}

$VendorModifications = new VendorModifications();