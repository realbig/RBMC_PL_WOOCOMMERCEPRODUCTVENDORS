<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class WC_MLM_VendorModifications {

	public static $available_modifications = array();
	public $modifications;
	public $modifications_list;

	function __construct() {

		// Set here due to need of function calling
		self::$available_modifications = array(
			'parent'          => array(
				'label' => 'Parent',
			),
			'commission_tier' => array(
				'label' => 'Commission Tier',
			),
			'active' => array(
				'label' => wc_mlm_setting( 'vendor_verbage' ) . ' not approved',
			),
			'delete' => array(
				'label' => 'Delete ' . wc_mlm_setting( 'vendor_verbage' ),
			),
		);

		$this->_add_actions();
	}

	private function _add_actions() {

		add_action( 'admin_menu', array( $this, '_add_menu_page' ) );
		add_action( 'admin_init', array( $this, '_update_modifications' ) );
		add_action( 'admin_head', array( $this, '_add_menu_count' ) );
	}

	function _add_menu_page() {

		$hook = add_submenu_page(
			'woocommerce',
			wc_mlm_setting( 'vendor_verbage' ) . ' Updates',
			wc_mlm_setting( 'vendor_verbage' ) . ' Updates',
			'manage_options',
			'vendor-updates',
			array( $this, '_page_output' )
		);

		add_action( "load-$hook", array( $this, '_get_modifications' ) );
	}

	function _update_modifications() {

		if ( isset( $_GET['wc_mlm_approve'] ) ) {

			$hash = $_GET['wc_mlm_approve'];
			self::approve_modification( $hash );
		}

		if ( isset( $_GET['wc_mlm_delete'] ) ) {

			$hash = $_GET['wc_mlm_delete'];
			self::delete_modification( $hash );
		}

		if ( isset( $_GET['wc_mlm_approve'] ) || isset( $_GET['wc_mlm_delete'] ) ) {

			wp_redirect( remove_query_arg( array(
				'wc_mlm_approve',
				'wc_mlm_delete',
			) ) );

			exit();
		}
	}

	public function _add_menu_count() {

		global $submenu;

		if ( isset( $submenu['woocommerce'] ) ) {

			// Add count if user has access
			if ( current_user_can( 'manage_options' ) ) {
				foreach ( $submenu['woocommerce'] as $key => $menu_item ) {

					if ( 0 === strpos( $menu_item[0], wc_mlm_setting( 'vendor_verbage' ) . ' Updates' ) ) {

						$this->_get_modifications();
						$count = count( $this->modifications );

						$submenu['woocommerce'][ $key ][0] .= ' <span class="awaiting-mod update-plugins count-' . $count . '"><span class="processing-count">' . number_format_i18n( $count ) . '</span></span>';
						break;
					}
				}
			}
		}
	}

	function _page_output() {

		include_once __DIR__ . '/views/html-vendor-modifications.php';
	}

	function _get_modifications() {

		$this->modifications_list = get_option( '_wc_mlm_vendor_modifications' );
		$this->modifications = self::get_modifications();
	}

	public static function get_verbage( $modification ) {

		$date = date( 'F jS, Y - g:ia', $modification['date'] );

		$type = WC_MLM_VendorModifications::$available_modifications[ $modification['type'] ]['label'];

		$instigator = WC_MLM_Vendors::get_vendor( $modification['instigator'] );
		$instigator = $instigator ? $instigator->name : false;

		if ( $instigator === false ) {
			$user = get_userdata( $modification['instigator'] );

			if ( ! $user ) {
				$instigator = '- N/A -';
			} else {
				$instigator = $user->display_name;
				$instigator = $instigator ? $instigator : $user->user_nicename;
			}
		}

		$victim = WC_MLM_Vendors::get_vendor( $modification['victim'] );
		$victim = $victim ? $victim->name : false;

		if ( $victim === false ) {
			$user = get_userdata( $modification['victim'] );

			if ( ! $user ) {
				$victim = 'User No Longer Exists';
			} else {
				$victim = $user->display_name;
				$victim = $victim ? $victim : $user->user_nicename;
			}
		}

		switch ( $modification['type'] ) {

			case 'commission_tier':

				$old_value = WC_MLM_Vendors::$commission_tiers[ $modification['old_value'] ]['name'];
				$new_value = WC_MLM_Vendors::$commission_tiers[ $modification['new_value'] ]['name'];
				break;

			case 'parent':

				$old_value = WC_MLM_Vendors::get_vendor( $modification['old_value'] );
				$old_value = $old_value ? $old_value->name : '- NA -';

				$new_value = WC_MLM_Vendors::get_vendor( $modification['new_value'] );
				$new_value = $new_value ? $new_value->name : '- NA -';
				break;


			default:
				$old_value = $modification['old_value'];
				$new_value = $modification['new_value'];
		}

		return array(
			'hash'       => $modification['hash'],
			'date'       => $date,
			'type'       => $type,
			'instigator' => $instigator,
			'victim'     => $victim,
			'old_value'  => $old_value,
			'new_value'  => $new_value,
		);
	}

	public static function get_modifications() {

		$modifications_list = get_option( '_wc_mlm_vendor_modifications', array() );

		// Also get non-approved vendors as modifications
		$users = get_users( array(
			'role'         => 'vendor',
			'meta_query' => array(
				array(
					'key' => '_vendor_active',
					'compare' => 'NOT EXISTS',
				),
			),
		) );

		foreach ( $users as $user ) {

			$user_modification = array(
				'type'       => 'active',
				'date'       => strtotime( 'now' ),
				'instigator' => '',
				'victim'     => $user->ID,
				'old_value'  => 'Not Approved',
				'new_value'  => 'Approved',
			);

			self::add_modification( $user_modification );
		}

		$modifications = array();
		foreach ( $modifications_list as $hash ) {
			$modifications[] = get_option( "_wc_mlm_vendor_modification_$hash" );
		}

		return $modifications;
	}

	public static function delete_modification( $hash ) {

		$modifications_list = get_option( '_wc_mlm_vendor_modifications', array() );

		if ( ( $key = array_search( $hash, $modifications_list ) ) !== false ) {
			unset( $modifications_list[ $key ] );
		}

		delete_option( "_wc_mlm_vendor_modification_$hash" );
		update_option( '_wc_mlm_vendor_modifications', $modifications_list );
	}

	public static function approve_modification( $hash ) {

		$modification       = get_option( "_wc_mlm_vendor_modification_$hash" );

		switch ( $modification['type'] ) {

			case 'delete':

				$vendor = WC_MLM_Vendors::get_vendor( $modification['victim'] );
				self::delete_modification( $hash );
				$vendor->delete();
				break;

			default:
				self::delete_modification( $hash );
				update_user_meta( $modification['victim'], "_vendor_{$modification['type']}", $modification['new_value'] );
		}
	}

	public static function add_modification( $args ) {

		$modification = wp_parse_args( $args, array(
			'type'       => 'parent',
			'date'       => strtotime( 'now' ),
			'instigator' => false,
			'victim'     => false,
			'old_value'  => false,
			'new_value'  => false,
		) );

		if ( $modification['instigator'] === false ||
		     $modification['victim'] === false ||
		     $modification['old_value'] === false ||
		     $modification['new_value'] === false ||
		     $modification['old_value'] == $modification['new_value']
		) {
			return false;
		}

		// Create hash, but don't include time (to prevent duplicates)
		$hash_array = $modification;
		unset( $hash_array['date'] );
		$hash = md5( serialize( $hash_array ) );

		$modification['hash'] = $hash;

		$modifications_list = get_option( '_wc_mlm_vendor_modifications', array() );

		if ( in_array( $hash, $modifications_list ) ) {
			return false;
		}

		$modifications_list[] = $hash;

		update_option( "_wc_mlm_vendor_modification_$hash", $modification );
		update_option( '_wc_mlm_vendor_modifications', $modifications_list );

		return $hash;
	}
}