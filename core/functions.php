<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

function _wc_mlm_setting( $setting ) {

	require_once __DIR__ . '/class-wc-mlm-admin.php';

	return get_option(
		"_wc_mlm_$setting",
		WC_MLM_Admin::$settings[ $setting ]['default']
	);
}