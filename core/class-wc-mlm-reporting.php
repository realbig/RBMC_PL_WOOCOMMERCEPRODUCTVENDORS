<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class WC_MLM_Reporting {

	private $page_title;
	private $page_content;

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
			add_action( 'wp', array( $this, '_setup_reporting_page' ) );
		}

		// Add the sales leader admin reporting page
		add_action( 'admin_menu', array( $this, '_sales_leader_report_page' ) );
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

		$vendors_regex = $WC_MLM->vendors->get_vendor_slugs_regex();

		add_rewrite_tag( '%vendor_action%', '([^&]+)' );

		add_rewrite_rule(
			"vendor/{$vendors_regex}/?([^/]+)?$",
			'index.php?vendor=$matches[1]&vendor_action=$matches[2]&page_id=' . $WC_MLM->pages['reporting'],
			'top'
		);
	}

	function _setup_reporting_page() {

		global $wp_query, $WC_MLM;

		$vendor_slug = isset( $wp_query->query_vars['vendor'] ) ? $wp_query->query_vars['vendor'] : false;
		$action      = isset( $wp_query->query_vars['vendor_action'] ) ? $wp_query->query_vars['vendor_action'] : false;

		// Not a vendor page
		if ( ! $vendor_slug || $action === false ) {
			return;
		}

		if ( empty( $action ) ) {
			$action = 'vendor_report';
		}

		$vendor = $WC_MLM->vendors->get_vendor_by_slug( $vendor_slug );

		$this->page_title = $vendor->name;

		// Security check
		$can_view = true;

		$current_user_vendor             = get_current_user_id() != $vendor->ID ? $WC_MLM->vendors->get_vendor( get_current_user_id() ) : $vendor;
		$current_user_vendor_descendants = $current_user_vendor !== false ? $current_user_vendor->get_descendants : false;

		// Admins automatically can view
		if ( ! current_user_can( 'manage_options' ) ) {

			// Not a vendor
			$can_view = $WC_MLM->vendors->is_vendor( get_current_user_id() );

			// Not the current user's vendor page
			if ( $vendor->ID != $current_user_vendor->ID ) {

				// Not a descendant
				if ( ! in_array( $vendor->ID, $current_user_vendor_descendants ) ) {
					$can_view = false;
				}
			}
		}

		// Not enough privileges
		if ( ! $can_view ) {

			$this->page_title = 'Cannot View';
			add_action( 'the_title', array( $this, '_report_page_title' ), 9999 );
			add_action( 'the_content', array( $this, '_cannot_view_vendor' ), 30 );

			return;
		}

		require_once __DIR__ . '/class-wc-mlm-report.php';
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'wc-mlm-jquery-ui-style' );

		$this->page_title .= ': Report';

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

		$report = new WC_MLM_Report(
			'vendor',
			$vendor,
			$date_query
		);

		ob_start();
		?>
		<div class="woocommerce wc-mlm-report">

			<form class="wc-mlm-report-actions" method="get">
				<label>
					From
					<input type="text" class="vendor-report-period-from"
					       value="<?php echo implode( '/', $date_from ); ?>"/>
					<input type="hidden" name="vendor-report-period-from"/>
				</label>

				<label>
					To
					<input type="text" class="vendor-report-period-to"
					       value="<?php echo implode( '/', $date_to ); ?>"/>
					<input type="hidden" name="vendor-report-period-to"/>
				</label>

				<input type="submit" value="Go"/>
			</form>

			<p class="total-sales">
				<strong>Total Sales:</strong> <?php echo wc_price( $report->total_sales ); ?>
			</p>

			<p class="total-commission">
				<strong>Total Pending
					Commission:</strong> <?php echo wc_price( $report->total_commission['pending'] ); ?>
				<br/>
				<strong>Total Final Commission:</strong> <?php echo wc_price( $report->total_commission['final'] ); ?>
			</p>

			<?php if ( ! empty( $report->products ) ) : ?>

				<h3>Products</h3>

				<table class="vendor-report-descendants shop_table">
					<thead>
					<tr>
						<th>
							Product
						</th>
						<th>
							Price
						</th>
						<th>
							Commission
						</th>
					</tr>
					</thead>

					<tbody>
					<?php
					foreach ( $report->items as $item ) :
						$product = $report->products[ (int) $item['item_meta']['_product_id'][0] ];
						?>

						<tr>
							<td>
								<a href="<?php echo get_permalink( $product->ID ); ?>">
									<?php echo get_the_title( $product->ID ); ?>
								</a>
							</td>

							<td>
								<?php echo wc_price( $item['line_total'] ); ?>
							</td>

							<td>
								<?php
								$percentage = (int) $WC_MLM->vendors->commission_tiers[ $vendor->commission_tier ]['percentage'] / 100;
								echo wc_price(
									(int) $item['line_total'] * $percentage
								);
								?>
							</td>
						</tr>

					<?php endforeach; ?>
					</tbody>
				</table>

			<?php endif; ?>

			<?php if ( $descendants = $vendor->get_descendants() ) : ?>

				<h3>Your Vendors</h3>

				<?php array_walk( $descendants, array( $this, 'output_vendor_descendants' ) ); ?>

			<?php endif; ?>
		</div>

		<?php
		$html = ob_get_clean();

		$this->page_content = $html;

		add_action( 'the_title', array( $this, '_report_page_title' ), 9999 );
		add_action( 'the_content', array( $this, '_report_page_content' ), 9999 );
	}

	function output_vendor_descendants( $vendors, $user_ID ) {

		global $WC_MLM;

		static $depth;

		if ( ! $depth ) {
			$depth = 1;
		} else {
			$depth ++;
		}

		$vendor = $WC_MLM->vendors->get_vendor( $user_ID );
		?>
		<ul class="<?php echo $depth === 1 ? 'vendor-descendants' : 'vendor-descendants-sub'; ?>">
			<li>
				<a href="<?php echo $vendor->get_admin_url(); ?>">
					<?php echo $vendor->name; ?>
				</a>

				<?php
				if ( is_array( $vendors ) ) {
					array_walk( $vendors, array( $this, 'output_vendor_descendants' ) );
				}
				?>
			</li>
		</ul>
	<?php
	}

	function _cannot_view_vendor() {

		echo '<div class="woocommerce">';
		wc_print_notice( 'Cannot view this vendor.', 'error' );
		echo '</div>';
	}

	function _report_page_title() {
		return $this->page_title;
	}

	function _report_page_content() {
		return $this->page_content;
	}

	function _sales_leader_report_page() {

		$hook = add_menu_page(
			'Sales Report',
			'Sales Report',
			'manage_options',
			'sales-leader-report',
			array( $this, '_sales_leader_report_page_output' ),
			'dashicons-businessman',
			58
		);

		//		add_action( 'admin_print_styles-' . $hook, array( $this, '_sales_leader_report_styles' ) );
		add_action( 'admin_print_scripts-' . $hook, array( $this, '_sales_leader_report_scripts' ) );
	}

	function _sales_leader_report_scripts() {

		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'wc-mlm-reporting' );
		wp_enqueue_style( 'wc-mlm-jquery-ui-style' );
	}

	function _sales_leader_report_page_output() {

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

		global $WC_MLM, $wc_mlm_sales_leader_report_table;

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

		$vendors = $WC_MLM->vendors->get_vendors();


		$report_table = array(
			'head' => array(),
			'body' => array(),
		);

		$report_table['head'] = array(
			'vendor'             => array(
				'label' => 'Vendor',
				'type'  => 'name',
				'order' => 'char',
				'orderdefault' => 'asc',
			),
			'total_sales'        => array(
				'label' => 'Total Sales',
				'type'  => 'price',
				'order' => 'int',
				'orderdefault' => 'desc',
			),
			'commission_pending' => array(
				'label' => 'Commission (Pending)',
				'type'  => 'price',
				'order' => 'int',
				'orderdefault' => 'desc',
			),
			'commission_final'   => array(
				'label' => 'Commission (Final)',
				'type'  => 'price',
				'order' => 'int',
				'orderdefault' => 'desc',
			),
		);

		if ( $vendors ) {

			foreach ( $vendors as $vendor ) {
				$report = new WC_MLM_Report( 'vendor', $vendor, $date_query );

				$report_table['body'][] = array(
					'vendor'             => $vendor->name,
					'total_sales'        => (int) $report->total_sales,
					'commission_pending' => (int) $report->total_commission['pending'],
					'commission_final'   => (int) $report->total_commission['final'],
				);
			}
		}

		$wc_mlm_sales_leader_report_table = $report_table;

		usort( $report_table['body'], function ( $a, $b ) {

			global $wc_mlm_sales_leader_report_table;

			$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'vendor';
			$order   = isset( $_GET['order'] ) ? $_GET['order'] : 'desc';

			// Flip $a and $b
			if ( $order == 'desc' ) {
				$c = $a;
				$a = $b;
				$b = $c;
			}

			switch ( $wc_mlm_sales_leader_report_table['head'][ $orderby ]['order'] ) {
				case 'char':
					return strcasecmp( $a[ $orderby ], $b[ $orderby ] );
					break;

				case 'int':
				default:
					return $a[ $orderby ] - $b[ $orderby ];
					break;
			}
		} );
		?>
		<div class="wrap">

			<h2>Sales Report</h2>

			<div class="wc-mlm-report sales-leader-report">
				<form class="wc-mlm-report-actions" method="get">

					<input type="hidden" name="page" value="<?php echo $_GET['page']; ?>"/>

					<label>
						From
						<input type="text" class="vendor-report-period-from"
						       value="<?php echo implode( '/', $date_from ); ?>"/>
						<input type="hidden" name="vendor-report-period-from"/>
					</label>

					<label>
						To
						<input type="text" class="vendor-report-period-to"
						       value="<?php echo implode( '/', $date_to ); ?>"/>
						<input type="hidden" name="vendor-report-period-to"/>
					</label>

					<input type="submit" class="button" value="Go"/>
				</form>

				<?php if ( $report_table ) : ?>

					<table class="wc-mlm-sales-leader-report">

						<thead>
						<tr>
							<?php
							foreach ( $report_table['head'] as $head_ID => $head ) :
								$sorted = isset( $_GET['orderby'] ) && $_GET['orderby'] == $head_ID;
								$order  = isset( $_GET['order'] ) ? $_GET['order'] : $head['orderdefault'];
								?>
								<th class="sortable <?php echo "$head_ID $order"; echo $sorted ? ' sorted' : '' ?>">

									<?php
									$link = add_query_arg( array(
										'order' => $sorted ? $order : $head['orderdefault'],
										'orderby' => $head_ID,
									));
									?>
									<a href="<?php echo $link; ?>">
										<span class="title"><?php echo $head['label']; ?></span>
										<span class="sorting-indicator"></span>
									</a>
								</th>
							<?php endforeach; ?>
						</tr>
						</thead>

						<tbody>

						<?php foreach ( $report_table['body'] as $row ) : ?>
							<tr>

								<?php foreach ( $row as $row_ID => $cell ) : ?>
									<td>
										<?php
										switch ( $report_table['head'][ $row_ID ]['type'] ) {
											case 'name':
												echo $cell;
												break;

											case 'price':
												echo wc_price( $cell );
												break;
										}
										?>
									</td>
								<?php endforeach; ?>

							</tr>
						<?php endforeach; ?>

						</tbody>

					</table>

				<?php endif; ?>
			</div>
		</div>
	<?php
	}
}