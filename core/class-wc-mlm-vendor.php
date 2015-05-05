<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class WC_MLM_Vendor {

	public $ID;
	public $name;
	public $slug;
	public $email;
	public $phone;
	public $commission_tier;
	public $level;
	public $active;
	public $children = null;

	function __construct( $user_ID ) {

		$this->ID              = $user_ID;
		$this->name            = $this->_get_name();
		$this->slug            = $this->_get_slug();
		$this->commission_tier = $this->_get_commission_tier();
		$this->level           = $this->_get_level();
		$this->active          = $this->_get_active();
		$this->email           = get_user_meta( $user_ID, '_vendor_email', true );
		$this->phone           = get_user_meta( $user_ID, '_vendor_phone', true );
	}

	private function _get_name() {

		$name = get_user_meta( $this->ID, '_vendor_name', true );

		if ( ! $name ) {
			$userdata = get_userdata( $this->ID );
			$name     = $userdata->data->display_name ? $userdata->data->display_name : $userdata->data->user_nicename;
		}

		return $name;
	}

	private function _get_slug() {

		$slug = get_user_meta( $this->ID, '_vendor_slug', true );

		if ( ! $slug ) {
			$slug = urlencode( strtolower( str_replace( ' ', '-', $this->name ) ) );
			update_user_meta( $this->ID, '_vendor_slug', $slug );
		}

		return $slug;
	}

	private function _get_commission_tier() {

		$tier = get_user_meta( $this->ID, '_vendor_commission_tier', true );

		if ( ! $tier ) {

			$tiers = WC_MLM_Vendors::$commission_tiers;
			end( $tiers );
			$tier = key( $tiers );
			update_user_meta( $this->ID, '_vendor_commission_tier', $tier );
		}

		return $tier;
	}

	private function _get_level() {

		$ancestors = $this->get_ancestors();

		// Counting "false" will count for 1, which isn't right
		if ( $ancestors === false ) {
			$ancestors = array();
		}

		return 1 + count( $ancestors );
	}

	private function _get_active() {

		if ( get_user_meta( $this->ID, '_vendor_active', true ) ) {
			return true;
		} else {
			return false;
		}
	}

	public function get_children() {

		if ( $this->children !== null ) {
			return $this->children;
		}

		$children = get_user_meta( $this->ID, '_vendor_children', true );

		if ( empty( $children ) ) {
			$children = false;
		}

		$this->children = $children;

		return $children;
	}

	public function get_parent() {

		$parent = get_user_meta( $this->ID, '_vendor_parent', true );

		if ( empty( $parent ) ) {
			return false;
		}

		return $parent;
	}

	public function get_ancestors() {

		$ancestors = array();

		$user_ID = $this->ID;

		while ( $user_ID = get_user_meta( $user_ID, '_vendor_parent', true ) ) {
			$ancestors[] = $user_ID;
		}

		return ! empty( $ancestors ) ? $ancestors : false;
	}

	public function get_descendants( $user_ID = false ) {

		$descendants = array();

		if ( ! $user_ID ) {
			$vendor = $this;
		} else {
			$vendor = WC_MLM_Vendors::get_vendor( $user_ID );
		}

		if ( ! $vendor ) {
			return '';
		}

		$children = $vendor->get_children();

		if ( empty( $children ) ) {
			return '';
		}

		foreach ( $children as $i => $child ) {

			// Correct improper meta
			if ( ! WC_MLM_Vendors::is_vendor( $child ) ) {
				unset( $children[ $i ] );
				update_user_meta( $this->ID, '_vendor_children', $children );
			}

			$descendants[ $child ] = $this->get_descendants( $child );
		}

		return ! empty( $descendants ) ? $descendants : false;
	}

	public function get_sales_bonus( $date_query = array() ) {

		if ( $this->level !== 1 ) {
			return false;
		}

		// Report is only within current month
		if ( isset( $date_query['after'] ) && isset( $date_query['before'] ) ) {

			$after_month = date( 'm', strtotime( $date_query['after'] ) );
			$before_month = date( 'm', strtotime( $date_query['before'] ) );

			if ( (int) $before_month - (int) $after_month > 1) {
				return '<br/>Invalid date range';
			}
		}

		$children = $this->get_children();

		$total_sales = 0;

		if ( count( $children ) < 10 ) {
			return wc_price( 0 );
		}

		if ( $children ) {
			foreach ( $children as $child ) {

				$child_vendor = WC_MLM_Vendors::get_vendor( $child );
				$report       = new WC_MLM_Report( 'vendor', $child_vendor, $date_query );

				// To get bonus, all children must have at least $100 in sales
				if ( $report->sales < 100 ) {
					return wc_price( 0 );
				}

				$total_sales = $total_sales + $report->sales;
			}
		}

		// 3% of total sales
		return wc_price( $total_sales * 0.03 );
	}

	public function get_all_children_sales( $date_query = array() ) {

		$children = $this->get_children();

		$sales = 0;

		if ( ! $children ) {
			return $sales;
		}

		foreach ( $children as $child ) {

			$child_vendor = WC_MLM_Vendors::get_vendor( $child );
			$report       = new WC_MLM_Report( 'vendor', $child_vendor, $date_query );

			$sales = $sales + $report->sales;
		}

		return $sales;
	}

	public function get_all_descendants_sales( $date_query = array() ) {

		$descendants = $this->get_descendants();
		$sales_bonus = 0;

		return $this->_descendants_sales_walk( $sales_bonus, $descendants, $date_query );
	}

	private function _descendants_sales_walk( &$sales_bonus, $descendants, $date_query = array() ) {

		foreach ( $descendants as $user_ID => $children ) {

			$vendor      = WC_MLM_Vendors::get_vendor( $user_ID );
			$report      = new WC_MLM_Report( 'vendor', $vendor, $date_query );
			$sales_bonus = $sales_bonus + $report->sales;

			if ( ! empty( $children ) ) {
				$sales_bonus = $this->_descendants_sales_walk( $sales_bonus, $children, $date_query );
			}
		}

		return $sales_bonus;
	}

	public function is_descendant( $user_ID ) {

		$vendor = WC_MLM_Vendors::get_vendor( $user_ID );

		if ( ! $vendor ) {
			return false;
		}

		$descendants = $vendor->get_descendants();

		return $this->_is_descendant_walk( $descendants );
	}

	private function _is_descendant_walk( $descendants ) {

		if ( isset( $descendants[ $this->ID ] ) ) {
			return true;
		}

		if ( ! empty( $descendants ) ) {

			foreach ( $descendants as $_descendants ) {
				return $this->_is_descendant_walk( $_descendants );
			}
		}
	}

	public function delete( $referrer_user_ID = false ) {

		// Update any parent's meta first
		if ( $parent = $this->get_parent() ) {

			$children = get_user_meta( $parent, '_vendor_children', true );

			if ( ( $key = array_search( $this->ID, $children ) ) !== false ) {
				unset( $children[ $key ] );
			}

			update_user_meta( $parent, '_vendor_children', $children );
		}

		require_once ABSPATH . '/wp-admin/includes/user.php';
		wp_delete_user( $this->ID );

		if ( $referrer_user_ID ) {

			update_user_meta( $referrer_user_ID, '_vendor_edit_messages', array(
				array(
					'type'    => 'success',
					'message' => _wc_mlm_setting( 'vendor_verbage' ) . ' successfully deleted.',
				),
			) );

			$referrer_vendor = WC_MLM_Vendors::get_vendor( $referrer_user_ID );

			if ( $referrer_vendor ) {
				wp_redirect( $referrer_vendor->get_admin_url() );
				exit();
			}

		}

		wp_redirect( admin_url( 'users.php' ) );
		exit;
	}

	public function refresh_slug() {
		$this->slug = get_user_meta( $this->ID, '_vendor_slug', true );
	}

	public function get_shop_url() {
		return get_permalink( wc_get_page_id( 'shop' ) ) . '/' . $this->slug;
	}

	public function get_admin_url( $trail = false ) {
		return get_bloginfo( 'url' ) . '/' . strtolower( _wc_mlm_setting( 'vendor_verbage' ) ) . '/' . $this->slug . ( $trail ? "/$trail" : '' );
	}
}