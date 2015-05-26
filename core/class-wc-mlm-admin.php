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
		'commission_tiers' => array(
			'label' => 'Commission Tiers',
			'default' => "(30%) Platinum:30\n(20%) Gold:20\n(15%) Silver:15\n(10%) Bronze:10",
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
		       value="<?php echo esc_attr( wc_mlm_setting( 'vendor_verbage' ) ); ?>"/>
	<?php
	}

	function _setting_output_vendor_slug() {
		?>
		<input type="text" name="_wc_mlm_vendor_slug" id="_wc_mlm_vendor_slug"
		       value="<?php echo esc_attr( wc_mlm_setting( 'vendor_slug' ) ); ?>"/>
	<?php
	}

	function _setting_output_commission_tiers() {

		$tiers = wc_mlm_setting( 'commission_tiers' );
		?>
		<textarea rows="6" name="_wc_mlm_commission_tiers" class="regular-text"><?php echo $tiers; ?></textarea>
		<p class="description">
			Enter each tier on it's own line in the format <code>Tier Label:Value</code>.
		</p>
	<?php
	}
}