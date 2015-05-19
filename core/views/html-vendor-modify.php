<?php
/**
 * @global $vendor              WC_MLM_Vendor
 * @global $current_user_vendor WC_MLM_Vendor|bool
 */

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

?>
	<div class="woocommerce">

		<?php WC_MLM_Reporting::show_vendor_messages(); ?>

		<form method="post">

			<?php wp_nonce_field( 'update-vendor_' . $vendor->ID ); ?>

			<?php
			$user = get_user_by( 'id', $vendor->ID );
			include_once __DIR__ . '/html-vendor-user-edit.php';
			?>

			<input type="hidden" name="_vendor_active" value="Active" />

			<input type="submit" class="button" name="vendor-frontend-modify" value="Update <?php echo _wc_mlm_setting( 'vendor_verbage' ); ?>"/>
			<input type="submit" class="button warning" name="vendor-frontend-delete" value="Delete <?php echo _wc_mlm_setting( 'vendor_verbage' ); ?>"
			       onclick="return confirm('WARNING: You are about to DELETE this <?php echo strtolower( _wc_mlm_setting( 'vendor_verbage' ) ); ?>.\nThis cannot be done\n\nAre you sure?')"/>
		</form>

	</div>