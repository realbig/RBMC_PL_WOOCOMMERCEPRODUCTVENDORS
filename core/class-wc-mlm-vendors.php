<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class WC_MLM_Vendors {

	public static $commission_tiers = array();

	public static $meta_fields = array(
		'_vendor_parent',
		'_vendor_name',
		'_vendor_slug',
		'_vendor_description',
		'_vendor_image',
		'_vendor_website',
		'_vendor_email',
		'_vendor_phone',
		'_vendor_commission_tier',
	);

	/**
	 * @var bool|WC_MLM_Vendor
	 */
	private $current_vendor_archive = false;

	function __construct() {

		// Here for ability to use functions
		self::$commission_tiers = $this->_get_commission_tiers();

		$this->_add_actions();
	}

	private function _add_actions() {

		// Flush permalinks on user register as vendor
		add_action( 'set_user_role', array( $this, 'flush_permalinks' ), 10, 3 );

		// Add commission disable to products
		add_action( 'woocommerce_product_options_general_product_data', array(
			$this,
			'_show_custom_product_fields'
		) );
		add_action( 'woocommerce_process_product_meta', array( $this, '_save_custom_product_fields' ) );

		// Shortcodes
		add_shortcode( 'customer_shop_link', array( $this, '_sc_customer_shop_link' ) );

		// Add extra user fields
		add_action( 'edit_user_profile', array( $this, '_add_user_vendor_fields' ) );

		// Save extra user fields
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_vendor_fields' ) );

		// Add to toolbar for Vendors
		add_action( 'show_admin_bar', array( $this, '_show_toolbar' ), 9999 );
		add_action( 'admin_bar_menu', array( $this, '_add_to_toolbar' ), 9999 );

		// Create Vendor shop page
		add_action( 'init', array( $this, '_add_rewrite' ) );
		add_action( 'wp', array( $this, '_setup_wc_pages' ) );

		add_action( 'woocommerce_calculate_totals', array( $this, '_vendor_discount' ), 1 );

		add_action( 'pre_get_posts', array( $this, '_user_upload_own_images' ) );

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

	function _get_commission_tiers() {

		$tiers = wc_mlm_setting( 'commission_tiers' );

		$tiers = explode( "\n", $tiers );

		$final_tiers = array();
		foreach ( $tiers as $i => $tier ) {

			$tier_parts        = explode( ':', $tier );
			$final_tiers[ $i ] = array(
				'label' => trim( $tier_parts[0] ),
				'value' => trim( $tier_parts[1] ),
			);
		}

		return $final_tiers;
	}

	function _sc_customer_shop_link( $atts = array(), $content ) {

		$atts = shortcode_atts( array(
			'class' => 'button customer-shop-link',
			'link'  => get_permalink( wc_get_page_id( 'shop' ) ),
		), $atts );

		if ( ! $content ) {
			$content = 'Shop Now!';
		}

		$html = "<a href=\"$atts[link]\" class=\"$atts[class]\">$content</a>";

		$orders = get_posts( array(
			'meta_key'    => '_customer_user',
			'meta_value'  => get_current_user_id(),
			'post_type'   => 'shop_order',
			'numberposts' => - 1
		) );

		if ( ! $orders ) {
			return $html;
		}

		$order = $orders[0];

		$vendors = get_post_meta( $order->ID, '_vendors', true );

		if ( count( $vendors ) !== 1 ) {
			return $html;
		}

		$vendor = $vendors[0];

		$vendor = WC_MLM_Vendors::get_vendor( $vendor );

		if ( ! $vendor ) {
			return $html;
		}

		$vendor_shop_link = $vendor->get_shop_url();

		return str_replace( $atts['link'], $vendor_shop_link, $html );
	}

	function _show_custom_product_fields() {
		?>
		<div class="options_group">
			<?php

			woocommerce_wp_checkbox(
				array(
					'id'            => '_wc_mlm_disable_commission',
					'wrapper_class' => 'show_if_simple',
					'label'         => 'Disable Commission',
					'description'   => '',
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'            => '_wc_mlm_cos',
					'wrapper_class' => 'show_if_simple',
					'label'         => 'Cost of Sales',
					'placeholder'   => '5',
					'data_type' => 'price',
				)
			);

			?>
		</div>
	<?php
	}

	function _save_custom_product_fields( $post_ID ) {

		if ( isset( $_POST['_wc_mlm_disable_commission'] ) ) {
			update_post_meta( $post_ID, '_wc_mlm_disable_commission', 'yes' );
		} else {
			delete_post_meta( $post_ID, '_wc_mlm_disable_commission' );
		}

		if ( isset( $_POST['_wc_mlm_cos'] ) ) {
			update_post_meta( $post_ID, '_wc_mlm_cos', $_POST['_wc_mlm_cos'] );
		} else {
			delete_post_meta( $post_ID, '_wc_mlm_cos' );
		}
	}

	/**
	 * @param $cart WC_Cart
	 */
	function _vendor_discount( $cart ) {

		$discount = 0;
		foreach ( $cart->cart_contents as $key => $item ) {

			if ( ! isset( $item['vendor'] ) ||
			     $item['vendor'] != get_current_user_id()
			) {
				continue;
			}

			$discount += (int) $item['line_subtotal'] * ( (int) $item['commission'] / 100 );
		}

		if ( $discount <= 0 ) {
			return;
		}

		$cart->cart_contents_total = (int) $cart->cart_contents_total - $discount;
		$cart->add_fee( 'Seller Discount', $discount );
	}

	function _user_upload_own_images( $wp_query_obj ) {
		global $current_user, $pagenow;

		if ( ! is_a( $current_user, 'WP_User' ) ) {
			return;
		}

		if ( 'admin-ajax.php' != $pagenow ) {
			return;
		}

		$wp_query_obj->set( 'author', $current_user->id );

		return;
	}

	function _add_cart_table_vendor( $price, $cart_item ) {

		$vendor = self::get_vendor( $cart_item['vendor'] );
		$price .= '</td><td class="product-vendor">' . ( $vendor->name ? $vendor->name : '- NA -' );

		return $price;
	}

	function _add_cart_header_vendor( $translations, $text, $domain ) {

		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return $translations;
		}

		if ( $text == 'Price' && $domain == 'woocommerce' ) {
			$translations .= '</th><th class="product-vendor">' . wc_mlm_setting( 'vendor_verbage' );
		}

		return $translations;
	}

	function _add_to_cart_vendor_input() {

		global $product;

		$disable_commission = get_post_meta( $product->id, '_wc_mlm_disable_commission', true );

		if ( ! $this->current_vendor_archive || $disable_commission ) {
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

		if ( isset( $values['vendor'] ) && (int) $values['vendor'] == get_current_user_id() ) {
			return;
		}

		if ( isset( $values['vendor'] ) ) {
			wc_add_order_item_meta( $item_id, '_vendor', $values['vendor'] );
		}

		if ( isset( $values['commission'] ) ) {
			wc_add_order_item_meta( $item_id, '_commission', $values['commission'] );
		}
	}

	function _checkout_order_delete_coupon() {

		$vendor = WC_MLM_Vendors::get_vendor( get_current_user_id() );

		if ( ! $vendor ) {
			return;
		}

		$coupon = new WC_Coupon( $vendor->name );

		if ( $coupon->exists ) {
			wp_delete_post( $coupon->id, true );
		}
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

		global $product;

		$disable_commission = get_post_meta( $product->id, '_wc_mlm_disable_commission', true );

		if ( ! $this->current_vendor_archive || $disable_commission ) {
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
			$this->current_vendor_archive = self::get_vendor( $_POST['vendor'] );
		}

		$cart_item_data['vendor']     = $this->current_vendor_archive->ID;
		$cart_item_data['commission'] = self::$commission_tiers[ $this->current_vendor_archive->commission_tier ]['value'];

		return $cart_item_data;
	}

	public function flush_permalinks( $user_ID, $role, $old_roles ) {

		if ( $role == 'vendor' ) {
			update_option( '_wc_mlm_flush_rewrite', 'true' );
		}
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

		include_once __DIR__ . '/views/html-vendor-user-edit.php';
	}

	/**
	 * Attaches the child Vendor ID to the parent Vendor ID when setting a Vendor parent.
	 *
	 * @param $user_ID int The currently being updated user ID.
	 */
	static function _update_vendor_children( $user_ID ) {

		$current_parent = get_user_meta( $user_ID, '_vendor_parent', true );

		if ( isset( $_POST['_vendor_parent'] ) && ! empty( $_POST['_vendor_parent'] ) ) {

			// If we're switching parents, make sure to delete the reference in the current parent
			if ( $current_parent && $current_parent != $_POST['_vendor_parent'] ) {
				$current_parent_children = get_user_meta( $current_parent, '_vendor_children', true );
				unset( $current_parent_children[ $user_ID ] );
				update_user_meta( $current_parent, '_vendor_children', $current_parent_children );
			}

			$current_children = get_user_meta( $_POST['_vendor_parent'], '_vendor_children', true );
			$current_children = ! empty( $current_children ) ? $current_children : array();

			if ( ! in_array( $user_ID, $current_children ) ) {
				$current_children[] = $user_ID;
			}

			update_user_meta( $_POST['_vendor_parent'], '_vendor_children', $current_children );

		} elseif ( $current_parent ) {

			$current_children = get_user_meta( $current_parent, '_vendor_children', true );
			$current_children = ! empty( $current_children ) ? $current_children : array();

			unset( $current_children[ $user_ID ] );

			update_user_meta( $current_parent, '_vendor_children', $current_children );
		}
	}

	/**
	 * Saves the custom Vendor user fields.
	 *
	 * @access private
	 *
	 * @param $user_ID int The currently being updated user ID.
	 */
	public static function save_user_vendor_fields( $user_ID ) {

		foreach ( self::$meta_fields as $meta ) {

			// Update parent's meta to reflect the child
			if ( $meta == '_vendor_parent' ) {
				self::_update_vendor_children( $user_ID );
			}

			if ( isset( $_POST[ $meta ] ) && ! empty( $_POST[ $meta ] ) ) {
				update_user_meta( $user_ID, $meta, $_POST[ $meta ] );
			} else {
				delete_user_meta( $user_ID, $meta );
			}
		}
	}

	function _show_toolbar( $show ) {

		$vendor = WC_MLM_Vendors::get_vendor( get_current_user_id() );

		if ( ! $vendor ) {
			return $show;
		}

		return true;
	}

	/**
	 * @param $wp_admin_bar WP_Admin_Bar
	 */
	function _add_to_toolbar( $wp_admin_bar ) {

		$vendor = WC_MLM_Vendors::get_vendor( get_current_user_id() );

		if ( ! $vendor ) {
			return;
		}

		// Remove some
		$wp_admin_bar->remove_menu( 'site-name' );
		$wp_admin_bar->remove_menu( 'my-account' );
		$wp_admin_bar->remove_menu( 'new-content' );
		$wp_admin_bar->remove_node( 'search' );
		$wp_admin_bar->remove_node( 'wp-logo' );

		// Add parent
		$wp_admin_bar->add_node( array(
			'id'    => 'mlm_vendor_menu',
			'title' => $vendor->name,
			'href'  => '#',
		) );

		// Add Children
		$wp_admin_bar->add_node( array(
			'parent' => 'mlm_vendor_menu',
			'id'     => 'mlm_vendor_menu_my_account',
			'title'  => 'My Account',
			'href'   => '/my-account',
		) );

		$wp_admin_bar->add_node( array(
			'parent' => 'mlm_vendor_menu',
			'id'     => 'mlm_vendor_menu_my_account_edit',
			'title'  => 'Edit Account',
			'href'   => '/my-account/edit-account',
		) );

		$wp_admin_bar->add_node( array(
			'parent' => 'mlm_vendor_menu',
			'id'     => 'mlm_vendor_menu_edit_vendor',
			'title'  => 'Edit My ' . wc_mlm_setting( 'vendor_verbage' ) . ' Settings',
			'href'   => $vendor->get_admin_url( 'modify' ),
		) );

		$wp_admin_bar->add_node( array(
			'parent' => 'mlm_vendor_menu',
			'id'     => 'mlm_vendor_menu_report',
			'title'  => 'My Report',
			'href'   => $vendor->get_admin_url(),
		) );

		$wp_admin_bar->add_node( array(
			'parent' => 'mlm_vendor_menu',
			'id'     => 'mlm_vendor_menu_shop',
			'title'  => 'My Store',
			'href'   => $vendor->get_shop_url(),
		) );
	}

	/**
	 * Adds the ability to access Vendor pages in the syntax /shop/{vendor}/.
	 *
	 * @access private
	 */
	function _add_rewrite() {

		global $wp_taxonomies;

		$shop_page = get_post( wc_get_page_id( 'shop' ) );
		$shop_slug = $shop_page->post_name;

		add_rewrite_tag( '%vendor%', '([^&]+)' );

		// Base vendor page
		add_rewrite_rule( "{$shop_slug}/([^/]+)/?$", 'index.php?post_type=product&vendor=$matches[1]&vendor_action=shop', 'top' );

		// Vendor taxonomy pages
		$taxonomies = get_object_taxonomies( 'product' );

		foreach ( $taxonomies as $tax ) {

			$slug = isset( $wp_taxonomies[ $tax ]->rewrite['slug'] ) ? $wp_taxonomies[ $tax ]->rewrite['slug'] : $tax;

			add_rewrite_rule(
				"{$shop_slug}/([^/]+)/{$slug}/([^/]+)/?$",
				'index.php?post_type=product&taxonomy=' . $tax . '&term=$matches[2]&vendor=$matches[1]&vendor_action=shop',
				'top'
			);
		}

		// Vendor single product
		add_rewrite_rule( "{$shop_slug}/([^/]+)/([^/]+)/?$", 'index.php?post_type=product&name=$matches[2]&vendor=$matches[1]&vendor_action=shop', 'top' );

		if ( get_option( '_wc_mlm_flush_rewrite' ) ) {
			flush_rewrite_rules();
			delete_option( '_wc_mlm_flush_rewwrite' );
		}
	}

	/**
	 * Modifies the default shop page when viewing a Vendor.
	 *
	 * @access private
	 */
	function _setup_wc_pages() {

		global $wp_query;

		if ( ! isset( $wp_query->query_vars['vendor_action'] ) || $wp_query->query_vars['vendor_action'] != 'shop' ) {
			return;
		}

		$vendor_slug = isset( $wp_query->query_vars['vendor'] ) ? $wp_query->query_vars['vendor'] : false;

		// Not on a vendor slug
		if ( ! $vendor_slug ) {
			return;
		}

		$vendors      = self::get_vendors();
		$vendor_slugs = wp_list_pluck( $vendors, 'slug' );

		// If vendor is ID, change to slug
		if ( isset( $vendor_slugs[ $vendor_slug ] ) ) {
			$vendor_slug = $vendor_slugs[ $vendor_slug ];
		}

		$disabled = false;

		// Slug does not match existing vendor
		if ( ! in_array( $vendor_slug, $vendor_slugs ) ) {
			$disabled = true;
		}

		$this->current_vendor_archive = self::get_vendor_by_slug( $vendor_slug );

		if ( ! $this->current_vendor_archive->active ) {
			$disabled = true;
		}

		if ( $disabled ) {

			// Disable post display
			$wp_query->post_count = 0;
			add_action( 'woocommerce_before_main_content', array( $this, '_vendor_page_not_exist' ), 20 );

			return;
		}

		add_action( 'woocommerce_before_main_content', array( $this, '_vendor_page_description' ), 30 );

		add_filter( 'the_permalink', array( $this, '_product_link_add_vendor' ) );
		add_filter( 'term_link', array( $this, '_product_term_link_add_vendor' ), 10, 3 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( $this, '_product_link_add_vendor' ) );
	}

	function _product_link_add_vendor() {

		global $post;

		$permalink = get_permalink( wc_get_page_id( 'shop' ) );
		$permalink .= $this->current_vendor_archive->slug . '/';
		$permalink .= $post->post_name;

		return $permalink;
	}

	function _product_term_link_add_vendor( $termlink, $term, $taxonomy ) {

		global $wp_rewrite;

		// Get taxonomy base
		$base = $wp_rewrite->get_extra_permastruct( $taxonomy );
		$base = str_replace( "%$taxonomy%", '', $base );

		// Get the current category
		preg_match( '/([^\/]+)\/?$/', $termlink, $matches );
		$category = $matches[1];

		$link = $this->current_vendor_archive->get_shop_url() . '/' . $base . '/' . $category;

		return $link;
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

		<?php if ( $vendor->image ) : ?>
			<div class="image">
				<?php echo wp_get_attachment_image( $vendor->image, 'full' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $vendor->description ) : ?>
			<div class="description">
				<?php echo wpautop( do_shortcode( $vendor->description ) ); ?>
			</div>
		<?php endif; ?>

		<p class="vendor-meta">

			<?php if ( $site = $vendor->website ) : ?>
				<span class="site">
					<a href="<?php echo $site; ?>"><?php echo $site; ?></a>
				</span>
			<?php endif; ?>

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
		wc_print_notice( 'This ' . wc_mlm_setting( 'vendor_verbage' ) . ' does not exist.', 'error' );
	}

	public static function is_vendor( $user_ID ) {
		return user_can( $user_ID, 'is_vendor' );
	}

	public static function get_vendors() {

		$users = get_users( array(
			'role' => 'vendor',
		) );

		if ( ! $users ) {
			return false;
		}

		$vendors = array();

		foreach ( $users as $user ) {
			$vendors[ $user->ID ] = self::get_vendor( $user->ID );
		}

		return $vendors;
	}

	public static function get_vendor( $user_ID ) {

		if ( ! user_can( $user_ID, 'is_vendor' ) ) {
			return false;
		}

		require_once __DIR__ . '/class-wc-mlm-vendor.php';

		return new WC_MLM_Vendor( $user_ID );
	}

	public static function get_vendor_by_slug( $slug ) {

		$users = get_users( array(
			'meta_key'   => '_vendor_slug',
			'meta_value' => $slug,
		) );

		if ( empty( $users ) ) {
			return false;
		}

		$user = array_shift( $users );

		return self::get_vendor( $user->ID );
	}

	public static function get_vendor_slugs_regex() {

		$vendors      = self::get_vendors();
		$vendor_slugs = wp_list_pluck( $vendors, 'slug' );

		$vendors_regex = '(';
		$i             = 0;
		foreach ( $vendor_slugs as $slug ) {

			$i ++;

			$vendors_regex .= preg_quote( $slug ) . ( $i < count( $vendor_slugs ) ? '|' : '' );
		}

		$vendors_regex .= ')';

		return $vendors_regex;
	}
}