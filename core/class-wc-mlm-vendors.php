<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class WC_MLM_Vendors {

	public $vendor_role_exists = false;

	public $commission_tiers = array(
		'1' => array(
			'name'       => '(20%) Gold',
			'percentage' => 20,
		),
		'2' => array(
			'name'       => '(15%) Silver',
			'percentage' => 15,
		),
		'3' => array(
			'name'       => '(10%) Bronze',
			'percentage' => 10,
		),
	);

	private $meta_fields = array(
		'_vendor_parent',
		'_vendor_name',
		'_vendor_slug',
		'_vendor_email',
		'_vendor_phone',
		'_vendor_commission_tier',
	);

	/**
	 * @var bool|WC_MLM_Vendor
	 */
	private $current_vendor_archive = false;

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

		// Create Vendor shop page
		add_action( 'init', array( $this, '_add_rewrite' ) );
		add_action( 'wp', array( $this, '_setup_wc_pages' ) );

		// Style cart
		add_filter( 'gettext', array( $this, '_add_cart_header_vendor' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_price', array( $this, '_add_cart_table_vendor' ), 10, 2 );

		// Make sure vendor is associated with cart items
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, '_add_to_cart_vendor_input' ) );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, '_filter_add_to_cart_link' ) );
		add_action( 'woocommerce_add_cart_item_data', array( $this, '_cart_item_add_vendor_meta' ) );
		add_action( 'woocommerce_add_order_item_meta', array( $this, '_checkout_add_order_item_vendor_meta' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, '_checkout_order_add_vendor_meta' ) );

	}

	function _add_cart_table_vendor( $price, $cart_item ) {

		$vendor = $this->get_vendor( $cart_item['vendor'] );
		$price .= '</td><td class="product-vendor">' . ( $vendor->name ? $vendor->name : '- NA -' );

		return $price;
	}

	function _add_cart_header_vendor( $translations, $text, $domain ) {

		if ( $text == 'Price' && $domain == 'woocommerce' ) {
			// TODO Make this editable or just the correct one
			$translations .= '</th><th class="product-vendor">Vendor';
		}

		return $translations;
	}

	function _add_to_cart_vendor_input() {

		if ( ! $this->current_vendor_archive ) {
			return;
		}
		?>
		<input type="hidden" name="vendor" value="<?php echo $this->current_vendor_archive->ID; ?>"/>
	<?php
	}

	/**
	 * Attaches the vendor meta to the item in the order.
	 *
	 * @access private
	 *
	 * @param $item_id int The item ID.
	 * @param $values  array The meta values of the item.
	 */
	function _checkout_add_order_item_vendor_meta( $item_id, $values ) {
		wc_add_order_item_meta( $item_id, '_vendor', $values['vendor'] );
	}

	/**
	 * Attaches the order ID to all vendors in it.
	 *
	 * @access private
	 *
	 * @param $order_ID int The ID of the just created order.
	 */
	function _checkout_order_add_vendor_meta( $order_ID ) {

		$order   = new WC_Order( $order_ID );
		$items   = $order->get_items();
		$vendors = array();

		foreach ( $items as $item_ID => $item ) {

			if ( isset( $item['vendor'] ) && ! empty( $item['vendor'] ) ) {

				$vendor_ID     = $item['vendor'];
				$vendor_orders = get_user_meta( $vendor_ID, '_vendor_orders', true );

				if ( ! in_array( $vendor_ID, $vendors ) ) {
					$vendors[] = $vendor_ID;
				}

				if ( ! $vendor_orders ) {
					$vendor_orders = array();
				}

				if ( ! in_array( $order_ID, $vendor_orders ) ) {
					$vendor_orders[] = $order_ID;
					update_user_meta( $vendor_ID, '_vendor_orders', $vendor_orders );
				}
			}
		}

		if ( ! empty( $vendors ) ) {
			update_post_meta( $order_ID, '_vendors', $vendors );
		}
	}

	/**
	 * Adds the vendor ID to the data.
	 *
	 * @access private
	 *
	 * @param $link string The link HTML.
	 *
	 * @return string The link HTML.
	 */
	function _filter_add_to_cart_link( $link ) {

		if ( ! $this->current_vendor_archive ) {
			return $link;
		}

		return str_replace( 'data-product_id', 'data-vendor="' . $this->current_vendor_archive->ID . '" data-product_id', $link );
	}

	/**
	 * Attaches the vendor ID to the cart item when added.
	 *
	 * @access private
	 *
	 * @param $cart_item_data array The cart item meta.
	 *
	 * @return array The cart item meta.
	 */
	function _cart_item_add_vendor_meta( $cart_item_data ) {

		if ( ! $this->current_vendor_archive && ! isset( $_POST['vendor'] ) ) {
			return $cart_item_data;
		}

		if ( ! $this->current_vendor_archive ) {
			$this->current_vendor_archive = $this->get_vendor( $_POST['vendor'] );
		}

		$cart_item_data['vendor'] = $this->current_vendor_archive->ID;

		return $cart_item_data;
	}

	/**
	 * Creates the user "Vendor" role.
	 *
	 * @access private
	 */
	function _create_vendor_role() {

		global $wp_roles;

		$all_roles = $wp_roles->roles;

		// Don't bother if already created
		if ( isset( $all_roles['vendor'] ) ) {
			$this->vendor_role_exists = true;

			return;
		}

		// TODO Set actual capabilities
		$capabilities              = $all_roles['editor']['capabilities'];
		$capabilities['is_vendor'] = true;

		add_role( 'vendor', 'Vendor', $capabilities );
		$this->vendor_role_exists = true;
	}

	/**
	 * Adds all new Vendor fields to the user edit page.
	 *
	 * @access private
	 *
	 * @param $user WP_User The user object for the current user edit page.
	 */
	function _add_user_vendor_fields( $user ) {

		// Only add these settings for Vendors
		if ( ! in_array( 'vendor', $user->roles ) ) {
			return;
		}

		$current_vendor = $this->get_vendor( $user->ID );
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
					) );

					if ( ! empty( $vendors ) ) {
						?>
						<select id="_vendor_parent" name="_vendor_parent">
							<option value="">- No Parent -</option>
							<?php
							foreach ( $vendors as $vendor ) {

								if ( $vendor->ID === $user->ID ) {
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

			<tr>
				<th>
					<label for="_vendor_name">Vendor Name</label>
				</th>

				<td>
					<input type="text" id="_vendor_name" name="_vendor_name" class="regular-text"
					       value="<?php echo esc_attr( $current_vendor->name ); ?>"/>
				</td>
			</tr>

			<tr>
				<th>
					<label for="_vendor_slug">Vendor URL</label>
				</th>

				<td>
					<code><?php echo get_permalink( wc_get_page_id( 'shop' ) ); ?></code>
					<input type="text" id="_vendor_slug"
					       name="_vendor_slug" class="regular-text"
					       value="<?php echo $current_vendor->slug; ?>"/>
				</td>
			</tr>

			<tr>
				<th>
					<label for="_vendor_email">Vendor Email</label>
				</th>

				<td>
					<input type="text" id="_vendor_email" name="_vendor_email" class="regular-text"
					       value="<?php echo esc_attr( $current_vendor->email ); ?>"/>
				</td>
			</tr>

			<tr>
				<th>
					<label for="_vendor_phone">Vendor Phone Number</label>
				</th>

				<td>
					<input type="text" id="_vendor_phone" name="_vendor_phone" class="regular-text"
					       value="<?php echo esc_attr( $current_vendor->phone ); ?>"/>
				</td>
			</tr>

			<tr>
				<th>
					<label for="_vendor_commission_tier">Vendor Commission Tier</label>
				</th>

				<td>
					<select id="_vendor_commission_tier" name="_vendor_commission_tier">
						<?php foreach ( $this->commission_tiers as $tier_ID => $tier ) : ?>
							<option
								value="<?php echo $tier_ID; ?>" <?php selected( $current_vendor->commission_tier, $tier_ID ); ?>>
								<?php echo $tier['name']; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
	<?php
	}

	/**
	 * Attaches the child Vendor ID to the parent Vendor ID when setting a Vendor parent.
	 *
	 * @param $user_ID int The currently being updated user ID.
	 */
	function _update_vendor_children( $user_ID ) {

		if ( isset( $_POST['_vendor_parent'] ) && ! empty( $_POST['_vendor_parent'] ) ) {

			$current_children = get_user_meta( $_POST['_vendor_parent'], '_vendor_children', true );
			$current_children = ! empty( $current_children ) ? $current_children : array();

			if ( ! in_array( $user_ID, $current_children ) ) {
				$current_children[] = $user_ID;
			}

			update_user_meta( $_POST['_vendor_parent'], '_vendor_children', $current_children );

		} else {

			if ( $current_parent = get_user_meta( $user_ID, '_vendor_parent', true ) ) {

				$current_children = get_user_meta( $current_parent, '_vendor_children', true );
				$current_children = ! empty( $current_children ) ? $current_children : array();

				unset( $current_children[ $user_ID ] );

				update_user_meta( $current_parent, '_vendor_children', $current_children );
			}
		}
	}

	/**
	 * Saves the custom Vendor user fields.
	 *
	 * @access private
	 *
	 * @param $user_ID int The currently being updated user ID.
	 */
	function _save_user_vendor_fields( $user_ID ) {

		$user = get_user_by( 'id', $user_ID );

		if ( in_array( 'vendor', $user->roles ) ) {

			// Slug is required
//			if ( ! isset( $_POST['_vendor_slug'] ) || empty( $_POST['_vendor_slug'] ) ) {
//				$current_vendor        = $this->get_vendor( $user_ID );
//				$_POST['_vendor_slug'] = $current_vendor->slug;
//			}
//
//			// Tier is required
//			if ( ! isset( $_POST['_vendor_commission_tier'] ) || empty( $_POST['_vendor_commission_tier'] ) ) {
//
//				$tiers = $this->commission_tiers;
//				end( $tiers );
//				$tier_ID                          = key( $tiers );
//				$_POST['_vendor_commission_tier'] = $tier_ID;
//			}
		}

		foreach ( $this->meta_fields as $meta ) {

			// Update parent's meta to reflect the child
			if ( $meta == '_vendor_parent' ) {
				$this->_update_vendor_children( $user_ID );
			}

			if ( isset( $_POST[ $meta ] ) && ! empty( $_POST[ $meta ] ) ) {
				update_user_meta( $user_ID, $meta, $_POST[ $meta ] );
			} else {
				delete_user_meta( $user_ID, $meta );
			}
		}
	}

	/**
	 * Adds the ability to access Vendor pages in the syntax /shop/{vendor}/.
	 *
	 * @access private
	 */
	function _add_rewrite() {

		global $WC_MLM, $wp_taxonomies;

		add_rewrite_tag( '%vendor%', '([^&]+)' );

		$vendors_regex = $WC_MLM->vendors->get_vendor_slugs_regex();

		// Base vendor page
		add_rewrite_rule( "shop/{$vendors_regex}/?$", 'index.php?post_type=product&vendor=$matches[1]', 'top' );

		// Vendor taxonomy pages
		$taxonomies = get_object_taxonomies( 'product' );

		foreach ( $taxonomies as $tax ) {

			$slug = isset( $wp_taxonomies[ $tax ]->rewrite['slug'] ) ? $wp_taxonomies[ $tax ]->rewrite['slug'] : $tax;

			add_rewrite_rule(
				"shop/{$vendors_regex}/{$slug}/([^/]+)/?$",
				'index.php?post_type=product&taxonomy=' . $tax . '&term=$matches[2]&vendor=$matches[1]',
				'top'
			);
		}

		// Vendor single product
		add_rewrite_rule( "shop/{$vendors_regex}/([^/]+)/?$", 'index.php?post_type=product&name=$matches[2]&vendor=$matches[1]', 'top' );
	}

	/**
	 * Modifies the default shop page when viewing a Vendor.
	 *
	 * @access private
	 */
	function _setup_wc_pages() {

		global $wp_query;

		$vendor_slug = isset( $wp_query->query_vars['vendor'] ) ? $wp_query->query_vars['vendor'] : false;

		// Not on a vendor slug
		if ( ! $vendor_slug ) {
			return;
		}

		$vendors      = $this->get_vendors();
		$vendor_slugs = wp_list_pluck( $vendors, 'slug' );

		// If vendor is ID, change to slug
		if ( isset( $vendor_slugs[ $vendor_slug ] ) ) {
			$vendor_slug = $vendor_slugs[ $vendor_slug ];
		}

		// Slug does not match existing vendor
		if ( ! in_array( $vendor_slug, $vendor_slugs ) ) {

			// Disable post display
			$wp_query->post_count = 0;
			add_action( 'woocommerce_before_main_content', array( $this, '_vendor_page_not_exist' ), 20 );

			return;
		}

		$this->current_vendor_archive = $this->get_vendor_by_slug( $vendor_slug );

		add_action( 'woocommerce_before_main_content', array( $this, '_vendor_page_description' ), 30 );

		add_filter( 'the_permalink', array( $this, '_product_link_add_vendor' ) );
	}

	function _product_link_add_vendor() {

		global $post;

		$permalink = get_permalink( wc_get_page_id( 'shop' ) );
		$permalink .= $this->current_vendor_archive->slug . '/';
		$permalink .= $post->post_name;

		return $permalink;
	}

	/**
	 * Adds the Vendor meta to the Shop page.
	 *
	 * @access private
	 */
	function _vendor_page_description() {

		$vendor = $this->current_vendor_archive;

		?>
		<h2 class="vendor-title">
			<?php echo $vendor->name; ?>
		</h2>

		<p class="vendor-meta">
			<?php if ( $phone = $vendor->phone ) : ?>
				<span class="phone">
					<?php echo $phone; ?>
				</span>
			<?php endif; ?>

			<?php if ( $email = $vendor->email ) : ?>
				<br/>
				<span class="email">
					<a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a>
				</span>
			<?php endif; ?>
		</p>
	<?php
	}

	/**
	 * Shows a WC error when trying to access a Vendor that doesn't exist.
	 *
	 * @access private
	 */
	function _vendor_page_not_exist() {
		wc_print_notice( 'This Vendor does not exist.', 'error' );
	}

	public function is_vendor( $user_ID ) {
		return user_can( $user_ID, 'is_vendor' );
	}

	public function get_vendors() {

		$users = get_users( array(
			'role' => 'vendor',
		) );

		if ( ! $users ) {
			return false;
		}

		$vendors = array();

		foreach ( $users as $user ) {
			$vendors[ $user->ID ] = $this->get_vendor( $user->ID );
		}

		return $vendors;
	}

	public function get_vendor( $user_ID ) {

		if ( ! user_can( $user_ID, 'is_vendor' ) ) {
			return false;
		}

		require_once __DIR__ . '/class-wc-mlm-vendor.php';

		return new WC_MLM_Vendor( $user_ID );
	}

	public function get_vendor_by_slug( $slug ) {

		$users = get_users( array(
			'meta_key'   => '_vendor_slug',
			'meta_value' => $slug,
		) );

		if ( empty( $users ) ) {
			return false;
		}

		$user = array_shift( $users );

		return $this->get_vendor( $user->ID );
	}

	public function get_vendor_slugs_regex() {

		global $WC_MLM;

		$vendors      = $WC_MLM->vendors->get_vendors();
		$vendor_slugs = wp_list_pluck( $vendors, 'slug' );

		$vendors_regex = '(';
		$i             = 0;
		foreach ( $vendor_slugs as $slug ) {

			$i ++;

			$vendors_regex .= $slug . ( $i < count( $vendor_slugs ) ? '|' : '' );
		}

		$vendors_regex .= ')';

		return $vendors_regex;
	}
}