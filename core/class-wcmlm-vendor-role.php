<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class WCMLM_Vendor_Role {

	public $vendor_role_exists = false;

	public $meta_fields = array(
		'_vendor_parent',
	);

	function __construct() {

		$this->_add_actions();
	}

	private function _add_actions() {

		// Create Vendor role
		add_action( 'init', array( $this, '_create_vendor_role' ) );

		// Add extra user fields
		add_action( 'edit_user_profile', array( $this, '_add_user_vendor_fields' ) );

		// Save extra user fields
		add_action( 'edit_user_profile_update', array( $this, '_save_user_vendor_fields' ) );
	}

	function _create_vendor_role() {

		global $wp_roles;

		$all_roles = $wp_roles->roles;

		// Don't bother if already created
		if ( isset( $all_roles['vendor'] ) ) {
			$this->vendor_role_exists = true;
			return;
		}

		// TODO Set actual capabilities
		add_role( 'vendor', 'Vendor', $all_roles['administrator']['capabilities']);
		$this->vendor_role_exists = true;
	}

	function _add_user_vendor_fields( $user ) {
		?>
		<h3>Vendor Settings</h3>

		<table class="form-table">
			<tr>
				<th>
					<label for="_vendor_parent">Vendor Parent</label>
				</th>

				<td>
					<?php
					$vendor_parent = get_user_meta( $user->ID, '_vendor_parent', true );

					$vendors = get_users( array(
						'role' => 'vendor',
					));

					if ( ! empty( $vendors ) ) {
						?>
						<select id="_vendor_parent" name="_vendor_parent">
							<option>- No Parent -</option>
							<?php
							foreach( $vendors as $vendor ) {

								if ( $vendor->ID === $user->ID ) {
									continue;
								}
								?>
								<option value="<?php echo $vendor->ID; ?>" <?php selected( $vendor->ID, $vendor_parent ); ?>>
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
		</table>
	<?php
	}

	function _save_user_vendor_fields( $user_ID ) {

		foreach ( $this->meta_fields as $meta ) {

			if ( isset( $_POST[ $meta ] ) ) {
				update_user_meta( $user_ID, $meta, $_POST[ $meta ] );
			} else {
				delete_user_meta( $user_ID, $meta );
			}
		}
	}
}