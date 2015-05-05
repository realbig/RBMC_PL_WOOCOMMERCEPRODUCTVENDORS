<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class WC_MLM_Admin {

	public static $settings = array(
		'vendor_verbage' => array(
			'label'   => '"Vendor" Verbage',
			'default' => 'Vendor',
		),
		'vendor_slug' => array(
			'label'   => '"Vendor" Slug',
			'default' => 'vendor',
		),
		'commission_tier_1' => array(
			'label'   => 'Commission Tier 1 Label',
			'default' => '(20%) Gold',
		),
		'commission_tier_2' => array(
			'label'   => 'Commission Tier 2 Label',
			'default' => '(15%) Silver',
		),
		'commission_tier_3' => array(
			'label'   => 'Commission Tier 3 Label',
			'default' => '(10%) Bronze',
		),
	);

	function __construct() {

		$this->_add_actions();
	}

	private function _add_actions() {

		// Settings page
		add_action( 'admin_menu', array( $this, '_add_settings_page' ) );

		// Register settings
		add_action( 'admin_init', array( $this, '_register_settings' ) );
	}

	function _add_settings_page() {

		add_options_page(
			'MLM Settings',
			'MLM Settings',
			'manage_options',
			'mlm-settings',
			array( $this, '_settings_page_output' )
		);
	}

	function _register_settings() {

		foreach ( self::$settings as $setting_ID => $setting ) {
			register_setting( 'wc-mlm-settings', "_wc_mlm_$setting_ID" );
		}
	}

	function _settings_page_output() {
		include_once __DIR__ . '/views/html-settings-page.php';
	}

	function _setting_output_vendor_verbage() {
		?>
		<input type="text" name="_wc_mlm_vendor_verbage" id="_wc_mlm_vendor_verbage"
		       value="<?php echo esc_attr( _wc_mlm_setting( 'vendor_verbage' ) ); ?>"/>
	<?php
	}

	function _setting_output_vendor_slug() {
		?>
		<input type="text" name="_wc_mlm_vendor_slug" id="_wc_mlm_vendor_slug"
		       value="<?php echo esc_attr( _wc_mlm_setting( 'vendor_slug' ) ); ?>"/>
	<?php
	}

	function _setting_output_commission_tier_1() {
		?>
		<input type="text" name="_wc_mlm_commission_tier_1" id="_wc_mlm_commission_tier_1"
		       value="<?php echo esc_attr( _wc_mlm_setting( 'commission_tier_1' ) ); ?>"/>
	<?php
	}

	function _setting_output_commission_tier_2() {
		?>
		<input type="text" name="_wc_mlm_commission_tier_2" id="_wc_mlm_commission_tier_2"
		       value="<?php echo esc_attr( _wc_mlm_setting( 'commission_tier_2' ) ); ?>"/>
	<?php
	}

	function _setting_output_commission_tier_3() {
		?>
		<input type="text" name="_wc_mlm_commission_tier_3" id="_wc_mlm_commission_tier_3"
		       value="<?php echo esc_attr( _wc_mlm_setting( 'commission_tier_3' ) ); ?>"/>
	<?php
	}
}