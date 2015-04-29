<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class WC_MLM_Vendor_Role {

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

		// Only add these settings for Vendors
		if ( ! in_array( 'vendor', $user->roles ) ) {
			return;
		}
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
							<option value="">- No Parent -</option>
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

			// Update parent's meta to reflect the child
			if ( $meta == '_vendor_parent' ) {

				if ( isset( $_POST['_vendor_parent'] ) && ! empty( $_POST[ $meta ] ) ) {
					update_user_meta( $_POST['_vendor_parent'], '_vendor_child', $user_ID );
				} else {

					if ( $current_parent = get_user_meta( $user_ID, '_vendor_parent', true ) ) {
						delete_user_meta( $current_parent, '_vendor_child' );
					}
				}
			}

			if ( isset( $_POST[ $meta ] ) && ! empty( $_POST[ $meta ] ) ) {
				update_user_meta( $user_ID, $meta, $_POST[ $meta ] );
			} else {
				delete_user_meta( $user_ID, $meta );
			}
		}
	}

	public function get_child( $user_ID ) {

		$child = get_user_meta( $user_ID, '_vendor_child', true );

		if ( empty( $child ) ) {
			return false;
		}

		return $child;
	}

	public function get_parent( $user_ID ) {

		$parent = get_user_meta( $user_ID, '_vendor_parent', true );

		if ( empty( $parent ) ) {
			return false;
		}

		return $parent;
	}

	public function get_ancestors( $user_ID ) {

		$ancestors = array();

		while ( $user_ID = get_user_meta( $user_ID, '_vendor_parent', true ) ) {
			$ancestors[] = $user_ID;
		}

		return ! empty( $ancestors ) ? $ancestors : false;
	}

	public function get_descendants( $user_ID ) {

		$descendants = array();

		while ( $user_ID = get_user_meta( $user_ID, '_vendor_child', true ) ) {
			$descendants[] = $user_ID;
		}

		return ! empty( $descendants ) ? $descendants : false;
	}
}