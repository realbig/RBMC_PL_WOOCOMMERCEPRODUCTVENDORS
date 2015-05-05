<?php
/**
 * @global $vendor              WC_MLM_Vendor
 * @global $current_user_vendor WC_MLM_Vendor|bool
 */

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

$this->page_title .= ': Edit';
$user = get_user_by( 'id', $vendor->ID );/* Get user info. */

// Update vendor
if ( isset( $_POST['vendor-frontend-modify'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'update-vendor_' . $vendor->ID ) ) {

	if ( current_user_can( 'manage_options' ) || get_current_user_id() == $vendor->ID ) {

		update_user_meta( get_current_user_id(), '_vendor_edit_messages', array(
			array(
				'type'    => 'success',
				'message' => _wc_mlm_setting( 'vendor_verbage' ) . ' changes successful.',
			)
		) );

		WC_MLM_Vendors::save_user_vendor_fields( $vendor->ID );

	} else {

		update_user_meta( get_current_user_id(), '_vendor_edit_messages', array(
			array(
				'type'    => 'notice',
				'message' => _wc_mlm_setting( 'vendor_verbage' ) . ' changes sent for approval.',
			)
		) );

		foreach ( WC_MLM_VendorModifications::$available_modifications as $type => $info ) {

			WC_MLM_VendorModifications::add_modification( array(
				'type'       => $type,
				'instigator' => get_current_user_id(),
				'victim'     => $vendor->ID,
				'old_value'  => get_user_meta( $vendor->ID, "_vendor_$type", true ),
				'new_value'  => isset( $_POST["_vendor_$type"] ) ? $_POST["_vendor_$type"] : '',
			) );
		}
	}

	// Get vendor slug again, just in-case the slug has changed
	$vendor->refresh_slug();

	wp_redirect( $vendor->get_admin_url( 'modify' ) );
	exit;
}

// Delete vendor
if ( isset( $_POST['vendor-frontend-delete'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'update-vendor_' . $vendor->ID ) ) {

	if ( current_user_can( 'manage_options' ) || get_current_user_id() == $vendor->ID ) {

		$vendor->delete( $current_user_vendor->ID );

	} else {

		update_user_meta( get_current_user_id(), '_vendor_edit_messages', array(
			array(
				'type'    => 'error',
				'message' => _wc_mlm_setting( 'vendor_verbage' ) . ' deletion sent for approval.',
			)
		) );

		WC_MLM_VendorModifications::add_modification( array(
			'type'       => 'delete',
			'instigator' => get_current_user_id(),
			'victim'     => $vendor->ID,
			'old_value'  => 'Exists',
			'new_value'  => 'Deleted',
		) );
	}
}

// Show pending changes
$modifications = WC_MLM_VendorModifications::get_modifications();

$messages = array();
foreach ( $modifications as $modification ) {

	if ( $modification['victim'] != $vendor->ID ) {
		continue;
	}

	$modification = WC_MLM_VendorModifications::get_verbage( $modification );

	$messages[] = array(
		'type' => 'error',
		'message' => 'Pending change from ' . $modification['instigator'] . '<br/><strong>' . $modification['type'] . ':</strong> <em>' . $modification['old_value'] . '</em> to <em>' . $modification['new_value'] . '</em>.',
	);
}

ob_start();
?>
	<div class="woocommerce">

		<?php WC_MLM_Reporting::show_vendor_messages( $messages ); ?>

		<form method="post">

			<?php wp_nonce_field( 'update-vendor_' . $vendor->ID ) ?>

			<?php include_once __DIR__ . '/html-vendor-user-edit.php'; ?>

			<input type="hidden" name="_vendor_active" value="Active" />

			<input type="submit" class="button" name="vendor-frontend-modify" value="Update <?php echo _wc_mlm_setting( 'vendor_verbage' ); ?>"/>
			<input type="submit" class="button warning" name="vendor-frontend-delete" value="Delete <?php echo _wc_mlm_setting( 'vendor_verbage' ); ?>"
			       onclick="return confirm('WARNING: You are about to DELETE this <?php echo strtolower( _wc_mlm_setting( 'vendor_verbage' ) ); ?>.\nThis cannot be done\n\nAre you sure?')"/>
		</form>
	</div>
<?php
$html = ob_get_clean();

$this->page_content = $html;