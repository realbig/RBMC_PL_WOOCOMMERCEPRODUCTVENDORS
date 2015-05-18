<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

$current_vendor = WC_MLM_Vendors::get_vendor( $user->ID );

if ( ! $current_vendor ) {
	return;
}

$can_view   = current_user_can( 'manage_options' ) || $user->ID == get_current_user_id() || is_admin();
$admin_view = current_user_can( 'manage_options' ) || $current_vendor->is_descendant( get_current_user_id() );
?>

<h3><?php echo _wc_mlm_setting( 'vendor_verbage' ); ?> Settings</h3>

<table class="form-table">

	<?php if ( $admin_view ) : ?>
		<tr>
			<th>
				<label for="_vendor_parent"><?php echo _wc_mlm_setting( 'vendor_verbage' ); ?> Parent</label>
			</th>

			<td>
				<?php
				$vendor_parent = get_user_meta( $user->ID, '_vendor_parent', true );

				$vendors = get_users( array(
					'role' => 'vendor',
				) );

				$descendants = $current_vendor->get_descendants();

				if ( ! empty( $vendors ) ) {
					?>
					<select id="_vendor_parent" name="_vendor_parent">
						<option value="">- No Parent -</option>
						<?php
						foreach ( $vendors as $vendor ) {

							// Don't show current user, also don't show descendants
							if ( ( $descendants && wc_mlm_array_key_exists_r( $vendor->ID, $descendants ) ) ||
							     $vendor->ID === $user->ID
							) {
								continue;
							}
							?>
							<option
								value="<?php echo $vendor->ID; ?>" <?php selected( $vendor->ID, $vendor_parent ); ?>>
								<?php echo $vendor->display_name; ?>
							</option>
						<?php
						}
						?>
					</select>
				<?php
				}
				?>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( $can_view ) : ?>

		<tr>
			<th>
				<label for="_vendor_name"><?php echo _wc_mlm_setting( 'vendor_verbage' ); ?> Name</label>
			</th>

			<td>
				<input type="text" id="_vendor_name" name="_vendor_name" class="regular-text"
				       value="<?php echo esc_attr( $current_vendor->name ); ?>"/>
			</td>
		</tr>

		<tr>
			<th>
				<label for="_vendor_image"><?php echo _wc_mlm_setting( 'vendor_verbage' ); ?> Image</label>
			</th>

			<td>
				<?php
				if ( ! is_admin() ) {

					wp_enqueue_media();

					$vendor_image         = $current_vendor->image;
					$vendor_image_preview = $vendor_image ? wp_get_attachment_image_src( $vendor_image, 'medium' ) : '';
					?>
						<img src="<?php echo $vendor_image_preview[0]; ?>" class="image-preview"
						     style="max-width: 100%; width: 300px;"/>
						<br/>
						<input type="hidden" class="image-id" name="_vendor_image"
						       value="<?php echo $vendor_image; ?>"/>
						<a class="image-button button">Upload / Choose Image</a>
					<script type="text/javascript">
						(function ($) {
							'use strict';

							$(function () {

								// Instantiates the variable that holds the media library frame.
								var vendor_image;

								// Runs when the image button is clicked.
								$('.image-button').click(function (e) {

									var $button = $(this);

									// Prevents the default action from occurring.
									e.preventDefault();

									// If the frame already exists, re-open it.
									if (vendor_image) {
										vendor_image.open();
										return;
									}

									// Sets up the media library frame
									vendor_image = wp.media.frames.vendor_image = wp.media({
										title: 'Select Author Image',
										button: {text: 'Use Image'},
										library: {
											type: 'image'
										}
									});

									// Runs when an image is selected.
									vendor_image.on('select', function () {

										// Grabs the attachment selection and creates a JSON representation of the model.
										var media_attachment = vendor_image.state().get('selection').first().toJSON();

										console.log(media_attachment);

										// Sends the attachment URL to our custom image input field.
										$button.siblings('.image-id').val(media_attachment.id);

										$button.siblings('.image-preview').attr('src', media_attachment.url);
									});

									// Opens the media library frame.
									vendor_image.open();
								});
							});
						})(jQuery);
					</script>
				<?php
				}
				?>
			</td>
		</tr>

		<tr>
			<th>
				<label for="_vendor_description"><?php echo _wc_mlm_setting( 'vendor_verbage' ); ?> Description</label>
			</th>

			<td>
				<textarea id="_vendor_description" rows="8"
				          name="_vendor_description"><?php echo $current_vendor->description ; ?></textarea>
			</td>
		</tr>

		<tr>
			<th>
				<label for="_vendor_slug"><?php echo _wc_mlm_setting( 'vendor_verbage' ); ?> Slug</label>
			</th>

			<td>
				<input type="text" id="_vendor_slug"
				       name="_vendor_slug" class="regular-text"
				       value="<?php echo $current_vendor->slug; ?>"/>
			</td>
		</tr>

	<?php endif; ?>

	<?php if ( $can_view ) : ?>

		<tr>
			<th>
				<label for="_vendor_email"><?php echo _wc_mlm_setting( 'vendor_verbage' ); ?> Email</label>
			</th>

			<td>
				<input type="text" id="_vendor_email" name="_vendor_email" class="regular-text"
				       value="<?php echo esc_attr( $current_vendor->email ); ?>"/>
			</td>
		</tr>

		<tr>
			<th>
				<label for="_vendor_website"><?php echo _wc_mlm_setting( 'vendor_verbage' ); ?> Website</label>
			</th>

			<td>
				<input type="text" id="_vendor_website" name="_vendor_website" class="regular-text"
				       value="<?php echo esc_attr( $current_vendor->website ); ?>"/>
			</td>
		</tr>

		<tr>
			<th>
				<label for="_vendor_phone"><?php echo _wc_mlm_setting( 'vendor_verbage' ); ?> Phone Number</label>
			</th>

			<td>
				<input type="text" id="_vendor_phone" name="_vendor_phone" class="regular-text"
				       value="<?php echo esc_attr( $current_vendor->phone ); ?>"/>
			</td>
		</tr>

	<?php endif; ?>

	<?php if ( $admin_view ) : ?>
		<tr>
			<th>
				<label for="_vendor_commission_tier"><?php echo _wc_mlm_setting( 'vendor_verbage' ); ?> Commission Tier</label>
			</th>

			<td>
				<select id="_vendor_commission_tier" name="_vendor_commission_tier">
					<?php foreach ( WC_MLM_Vendors::$commission_tiers as $tier_ID => $tier ) : ?>
						<option
							value="<?php echo $tier_ID; ?>" <?php selected( $current_vendor->commission_tier, $tier_ID ); ?>>
							<?php echo $tier['name']; ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	<?php endif; ?>
</table>