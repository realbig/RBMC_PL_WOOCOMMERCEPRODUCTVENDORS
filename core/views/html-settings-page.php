<?php
// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}
?>

<div class="wrap">

	<h2>MLM Settings</h2>

	<form method="post" action="options.php">

		<?php settings_fields( 'wc-mlm-settings' ); ?>

		<table class="form-table">
			<tbody>
			<?php foreach ( WC_MLM_Admin::$settings as $setting_ID => $setting ) : ?>

				<tr valign="top">
					<th scope="row">
						<label for="<?php echo "_wc_mlm_$setting_ID"; ?>">
							<?php echo $setting['label']; ?>
						</label>
					</th>

					<td>
						<?php
						if ( is_callable( array( $this, "_setting_output_$setting_ID" ) ) ) {
							call_user_func( array( $this, "_setting_output_$setting_ID" ) );
						}
						?>
					</td>
				</tr>

			<?php endforeach; ?>
			</tbody>
		</table>

		<?php submit_button(); ?>
	</form>
</div>

<?php

// Because of slug changes
flush_rewrite_rules();