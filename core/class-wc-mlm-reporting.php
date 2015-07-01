<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class WC_MLM_Reporting {

	private $page_title;
	private $page_content;
	private $messages = array();
	private $body_classes;

	function __construct() {

		$this->_add_actions();
	}

	private function _add_actions() {

		// Setup pages
		add_action( 'init', array( $this, '_setup_pages' ) );

		// Add reporting pages
		add_action( 'init', array( $this, '_add_rewrite' ), 20 );

		// Setup the frontend reporting page
		if ( ! is_admin() ) {
			add_action( 'wp', array( $this, '_setup_vendor_page' ) );
		}

		// Add the sales leader admin reporting page
		add_filter( 'woocommerce_admin_reports', array( $this, '_add_vendor_report' ) );
		add_action( 'admin_enqueue_scripts', array( $this, '_vendor_report_scripts' ) );

		// Filter order results on order page
		if ( isset( $_REQUEST['post_type'] ) &&
		     $_REQUEST['post_type'] == 'shop_order' &&
		     isset( $_REQUEST['wc_mlm_filter_vendor'] )
		) {
			add_filter( 'wp', array( $this, '_view_vendor_orders' ) );
		}
	}

	function _setup_pages() {

		global $WC_MLM;

		$WC_MLM->pages['reporting'] = get_option( '_wc_mlm_pages_reporting' );

		if ( ! $WC_MLM->pages['reporting'] || ! get_post( $WC_MLM->pages['reporting'] ) ) {

			$super_admins = get_super_admins();
			$super_admin  = get_user_by( 'login', $super_admins[0] );

			$ID = wp_insert_post( array(
				'post_type'   => 'page',
				'post_title'  => 'Vendor Reporting',
				'post_name'   => 'vendor-reporting',
				'post_status' => 'publish',
				'post_author' => $super_admin->ID,
			) );

			$WC_MLM->pages['reporting'] = $ID;
			update_option( '_wc_mlm_pages_reporting', $ID );
		}
	}

	function _add_rewrite() {

		global $WC_MLM;

		add_rewrite_tag( '%vendor_action%', '([^&]+)' );

		add_rewrite_rule(
			wc_mlm_setting( 'vendor_slug' ) . "/([^/]+)/?([^/]+)?$",
			'index.php?vendor=$matches[1]&vendor_action=$matches[2]&page_id=' . $WC_MLM->pages['reporting'],
			'top'
		);

		if ( get_option( '_wc_mlm_flush_rewrite' ) ) {
			flush_rewrite_rules();
			delete_option( '_wc_mlm_flush_rewwrite' );
		}
	}

	function _setup_vendor_page() {

		global $wp_query;

		$vendor_slug = isset( $wp_query->query_vars['vendor'] ) ? $wp_query->query_vars['vendor'] : false;
		$action      = isset( $wp_query->query_vars['vendor_action'] ) ? $wp_query->query_vars['vendor_action'] : false;

		// Not a vendor page
		if ( ! $vendor_slug || $action === false || $action == 'shop' ) {
			return;
		}

		if ( empty( $action ) ) {
			$action = 'vendor_report';
		}

		$vendor = WC_MLM_Vendors::get_vendor_by_slug( $vendor_slug );

		if ( ! $vendor ) {
			return;
		}

		$this->page_title = $vendor->name;

		// Security check
		$can_view = true;

		$current_user_vendor             = get_current_user_id() != $vendor->ID ? WC_MLM_Vendors::get_vendor( get_current_user_id() ) : $vendor;
		$current_user_vendor_descendants = $current_user_vendor !== false ? $current_user_vendor->get_descendants() : false;

		// Admins automatically can view
		if ( ! current_user_can( 'manage_options' ) ) {

			// Not a vendor
			$can_view = WC_MLM_Vendors::is_vendor( get_current_user_id() );

			// Not the current user's vendor page
			if ( $vendor->ID != $current_user_vendor->ID ) {

				// Not a descendant
				$can_view = $vendor->is_descendant( $current_user_vendor->ID );
			}
		}

		// Not enough privileges
		if ( ! $can_view ) {

			$this->page_title = 'Cannot View';
			add_action( 'the_title', array( $this, '_report_page_title' ), 9999 );
			add_action( 'the_content', array( $this, '_cannot_view_vendor' ), 30 );

			return;
		}

		// Show pending changes as messages to the current vendor
		$modifications = WC_MLM_VendorModifications::get_modifications();
		foreach ( $modifications as $modification ) {

			if ( $modification['victim'] != $vendor->ID ) {
				continue;
			}

			$modification = WC_MLM_VendorModifications::get_verbage( $modification );

			$this->messages[] = array(
				'type'    => 'error',
				'message' => 'Pending change from ' . $modification['instigator'] . '<br/><strong>' . $modification['type'] . ':</strong> <em>' . $modification['old_value'] . '</em> to <em>' . $modification['new_value'] . '</em>.',
			);
		}

		add_filter( 'vendor_messages', array( $this, '_report_page_messages' ) );

		switch ( $action ) {

			case 'vendor_report':

				$this->page_title .= ': Report';

				$this->page_title = get_avatar( $vendor->ID, 300, '', '', array( 'class' => 'vendor-report-title-avatar' ) ) . $this->page_title;
				$this->page_title .= '<br/><small><a href="' . $vendor->get_admin_url( 'modify' ) . '" class="button">(Modify ' . wc_mlm_setting( 'vendor_verbage' ) . ')</a>
</small>';

				ob_start();
				include_once __DIR__ . '/views/html-vendor-report.php';
				$this->page_content = ob_get_clean();

				break;

			case 'modify':

				$this->page_title .= ': Edit';

				// Update vendor
				if ( isset( $_POST['vendor-frontend-modify'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'update-vendor_' . $vendor->ID ) ) {

					if ( current_user_can( 'manage_options' ) || get_current_user_id() == $vendor->ID ) {

						update_user_meta( get_current_user_id(), '_vendor_edit_messages', array(
							array(
								'type'    => 'success',
								'message' => wc_mlm_setting( 'vendor_verbage' ) . ' changes successful.',
							)
						) );

						WC_MLM_Vendors::save_user_vendor_fields( $vendor->ID );

					} else {

						update_user_meta( get_current_user_id(), '_vendor_edit_messages', array(
							array(
								'type'    => 'notice',
								'message' => wc_mlm_setting( 'vendor_verbage' ) . ' changes sent for approval.',
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
								'message' => wc_mlm_setting( 'vendor_verbage' ) . ' deletion sent for approval.',
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

				ob_start();
				include_once __DIR__ . '/views/html-vendor-modify.php';
				$this->page_content = ob_get_clean();

				break;
		}

		add_action( 'the_title', array( $this, '_report_page_title' ), 9999 );
		add_action( 'the_content', array( $this, '_report_page_content' ), 9999 );
		add_filter( 'body_class', array( $this, '_report_page_body_classes' ) );
	}

	function output_vendor_descendants( $vendors, $user_ID ) {

		static $depth;

		if ( $depth === null ) {
			$depth = 0;
		}

		$vendor = WC_MLM_Vendors::get_vendor( $user_ID );

		if ( ! $vendor ) {
			return;
		}
		?>
		<tr>
			<td>
				<?php echo get_avatar( $user_ID, 150 ); ?>
			</td>
			<td>
				<?php echo str_repeat( '&nbsp;&nbsp;&nbsp;&nbsp;', $depth ); ?>
				<a href="<?php echo $vendor->get_admin_url(); ?>">
					<?php echo $vendor->name; ?>
				</a>
			</td>
		</tr>

		<?php
		if ( is_array( $vendors ) ) {
			$depth ++;
			array_walk( $vendors, array( $this, 'output_vendor_descendants' ) );
			$depth --;
		}
		?>
	<?php
	}

	function _cannot_view_vendor() {

		echo '<div class="woocommerce">';
		wc_print_notice( 'Cannot view this ' . strtolower( wc_mlm_setting( 'vendor_verbage' ) ) . '.', 'error' );
		echo '</div>';
	}

	function _report_page_title( $title ) {

		global $WC_MLM;

		$post = get_post( $WC_MLM->pages['reporting'] );

		if ( $post->post_title == $title ) {
			return $this->page_title;
		}

		return $title;
	}

	function _report_page_content( $content ) {
		return $this->page_content;
	}

	function _report_page_messages( $messages = array() ) {

		return array_merge( $messages, $this->messages );
	}

	function _report_page_body_classes( $classes ) {

		$classes[] = 'vendor-report';

		return $classes;
	}

	function _add_vendor_report( $reports ) {

		$reports['vendors'] = array(
			'title'   => wc_mlm_setting( 'vendor_verbage' ) . 's',
			'reports' => array(
				"vendors" => array(
					'title'       => wc_mlm_setting( 'vendor_verbage' ) . 's',
					'description' => 'A basic rundown of all ' . strtolower( wc_mlm_setting( 'vendor_verbage' ) ) . 's.',
					'callback'    => array( $this, '_vendor_report_output' )
				),
			),
		);

		return $reports;
	}

	function _vendor_report_scripts() {

		$screen = get_current_screen();

		if ( $screen->id == 'woocommerce_page_wc-reports' && isset( $_GET['tab'] ) && $_GET['tab'] == 'vendors' ) {
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'wc-mlm-reporting' );
			wp_enqueue_style( 'wc-mlm-jquery-ui-style' );
		}
	}

	function _vendor_report_output() {

		// TODO Use WC reporting views via hacks like this
		/*
		// This actually goes up in __construct()
		add_action( 'admin_enqueue_scripts', function () {
			global $current_screen;
			$current_screen->id = 'toplevel_page_wc-reports';
		}, 9);

		add_action( 'admin_enqueue_scripts', function () {
			global $current_screen;
			$current_screen->id = 'toplevel_page_sales-leader-report';
		}, 11);

		require_once plugin_dir_path( WC_PLUGIN_FILE ) . '/includes/admin/class-wc-admin-reports.php';
		require_once plugin_dir_path( WC_PLUGIN_FILE ) . '/includes/admin/reports/class-wc-admin-report.php';
		WC_Admin_Reports::get_report( 'sales_by_product' );
		*/

		global $wc_mlm_sales_leader_report_table;

		require_once __DIR__ . '/class-wc-mlm-report.php';

		// Date query
		$date_query = array();
		$date_from  = array();
		$date_to    = array();

		if ( isset( $_GET['vendor-report-period-from'] ) ) {

			$date_from = explode( '_', $_GET['vendor-report-period-from'] );

			$date_query['after'] = array(
				'month' => $date_from[0],
				'day'   => $date_from[1],
				'year'  => $date_from[2],
			);
		}

		if ( isset( $_GET['vendor-report-period-to'] ) ) {

			$date_to = explode( '_', $_GET['vendor-report-period-to'] );

			$date_query['before'] = array(
				'month' => $date_to[0],
				'day'   => $date_to[1],
				'year'  => $date_to[2],
			);
		}

		$vendors = WC_MLM_Vendors::get_vendors();

		require_once __DIR__ . '/class-wc-mlm-report-table.php';
		$report_table = new WC_MLM_ReportTable( array(
			'vendor'             => array(
				'label'        => wc_mlm_setting( 'vendor_verbage' ),
				'type'         => 'name',
				'order'        => 'char',
				'orderdefault' => 'asc',
			),
			'total_sales'        => array(
				'label'        => 'Total Sales',
				'type'         => 'price',
				'order'        => 'int',
				'orderdefault' => 'desc',
			),
			'commission_pending' => array(
				'label'        => 'Commission (Pending)',
				'type'         => 'price',
				'order'        => 'int',
				'orderdefault' => 'desc',
			),
			'commission_final'   => array(
				'label'        => 'Commission (Final)',
				'type'         => 'price',
				'order'        => 'int',
				'orderdefault' => 'desc',
			),
		), 'vendor' );

		$total_cos = 0;

		if ( $vendors ) {

			foreach ( $vendors as $vendor ) {
				$report = new WC_MLM_Report( 'vendor', $vendor, $date_query );

				$report_table->add_row( array(
					'vendor'             => '<a href="' . admin_url( "edit.php?post_type=shop_order&wc_mlm_filter_vendor=$vendor->ID" ) . "\">{$vendor->name}</a>",
					'total_sales'        => (int) $report->sales,
					'commission_pending' => (int) $report->commission['pending'],
					'commission_final'   => (int) $report->commission['final'],
				) );

				$total_cos = $total_cos + $report->cos;
			}
		}

		// Custom footer
		ob_start();
		?>
		<tr>
			<?php foreach ( $report_table->columns as $column_ID => $column ) : ?>

				<th>
					<?php
					if ( $column['order'] == 'int' ) {
						$total = 0;
						foreach ( $report_table->body as $row ) {
							$total = $total + $row[ $column_ID ];
						}

						echo $column['type'] == 'price' ? wc_price( $total ) : $total;
					} elseif ( $column_ID == $report_table->default_orderby ) {
						echo 'Total:';
					}
					?>
				</th>

			<?php endforeach; ?>
		</tr>
		<?php
		$report_table->custom_footer( ob_get_clean() );

		include_once __DIR__ . '/views/html-sales-leader-report.php';
	}

	function _view_vendor_orders() {

		global $wp_query, $WC_MLM;

		$vendor = $_REQUEST['wc_mlm_filter_vendor'];

		if ( ! $vendor = WC_MLM_Vendors::get_vendor( $vendor ) ) {

			$WC_MLM->admin->admin_notice( 'This vendor does not exist. No filtering applied.' );
			return;
		}

		$WC_MLM->admin->admin_notice( "Viewing orders for <strong>{$vendor->name}</strong>.", 'notice' );

		if ( ! empty( $wp_query->posts ) ) {

			foreach ( $wp_query->posts as $i => $post ) {

				$vendor_meta = (array) get_post_meta( $post->ID, '_vendors', true );

				if ( ! in_array( $vendor->ID, $vendor_meta ) ) {

					unset( $wp_query->posts[ $i ] );

					$wp_query->post_count  = $wp_query->post_count - 1;
					$wp_query->found_posts = (string) ( (int) $wp_query->found_posts - 1 );

					if ( $wp_query->post == $post ) {
						$wp_query->post = null;
					}
				}
			}
		}
	}

	public static function show_vendor_messages( $messages = array() ) {

		$user_messages = get_user_meta( get_current_user_id(), '_vendor_edit_messages', true );
		$user_messages = $user_messages ? $user_messages : array();
		$messages      = array_merge( $messages, $user_messages );

		$messages = apply_filters( 'vendor_messages', $messages );

		if ( $messages ) {

			foreach ( $messages as $message ) {
				wc_print_notice( $message['message'], $message['type'] );
			}

			delete_user_meta( get_current_user_id(), '_vendor_edit_messages' );
		}
	}
}