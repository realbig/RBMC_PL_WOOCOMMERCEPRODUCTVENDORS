<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

function wc_mlm_array_key_exists_r( $needle, $haystack ) {
	$result = array_key_exists( $needle, $haystack );
	if ( $result ) {
		return $result;
	}
	foreach ( $haystack as $v ) {
		if ( is_array( $v ) ) {
			$result = wc_mlm_array_key_exists_r( $needle, $v );
		}
		if ( $result ) {
			return $result;
		}
	}

	return $result;
}