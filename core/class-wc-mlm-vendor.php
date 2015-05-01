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

	function __construct( $user_ID ) {

		$this->ID              = $user_ID;
		$this->name            = $this->_get_name();
		$this->slug            = $this->_get_slug();
		$this->commission_tier = $this->_get_commission_tier();
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

		global $WC_MLM;

		$tier = get_user_meta( $this->ID, '_vendor_commission_tier', true );

		if ( ! $tier ) {

			$tiers = $WC_MLM->vendors->commission_tiers;
			end( $tiers );
			$tier = key( $tiers );
			update_user_meta( $this->ID, '_vendor_commission_tier', $tier );
		}

		return $tier;
	}

	public function get_children() {

		$children = get_user_meta( $this->ID, '_vendor_children', true );

		if ( empty( $children ) ) {
			return false;
		}

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

		global $WC_MLM;

		$descendants = array();

		if ( ! $user_ID ) {
			$user_ID = $this->ID;
		}

		$vendor = $WC_MLM->vendors->get_vendor( $user_ID );

		$children = $vendor->get_children();

		if ( empty( $children ) ) {
			return '';
		}

		foreach ( $children as $child ) {
			$descendants[ $child ] = $this->get_descendants( $child );
		}

		return ! empty( $descendants ) ? $descendants : false;
	}

	public function get_shop_url() {
		return get_permalink( wc_get_page_id( 'shop' ) ) . '/' . $this->slug;
	}

	public function get_admin_url() {
		return get_bloginfo( 'url' ) . '/vendor/' . $this->slug;
	}
}