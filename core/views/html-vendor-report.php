<?php
/**
 * @global $vendor WC_MLM_Vendor
 */

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

$this->page_title .= ': Report';

require_once WC_MLM_PATH . '/core/class-wc-mlm-report.php';
wp_enqueue_script( 'jquery-ui-datepicker' );
wp_enqueue_style( 'wc-mlm-jquery-ui-style' );

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

$vendor_descedants = $vendor->get_descendants();

$sales_bonus = $vendor->get_sales_bonus( $date_query );

ob_start();
?>
	<div class="woocommerce wc-mlm-report">

		<?php WC_MLM_Reporting::show_vendor_messages( $vendor->ID ); ?>

		<form class="wc-mlm-report-actions" method="get">
			<label class="wc-mlm-date-query-from">
				<input type="text" class="vendor-report-period-from"
				       value="<?php echo implode( '/', $date_from ); ?>"/>
				<input type="hidden" name="vendor-report-period-from"/>
			</label>

			<label class="wc-mlm-date-query-to">
				<input type="text" class="vendor-report-period-to"
				       value="<?php echo implode( '/', $date_to ); ?>"/>
				<input type="hidden" name="vendor-report-period-to"/>
			</label>

			<input type="submit" class="button" value="Go"/>
		</form>

		<div class="clear"></div>

		<p class="total-sales">
			<strong>Total Sales:</strong> <?php echo wc_price( $report->sales ); ?>

			<?php if ( $vendor->level === 1 ) : ?>
				<br/>
				<strong>Sales Bonus:</strong> <?php echo wc_price( $sales_bonus ); ?>
			<?php endif; ?>
		</p>

		<p class="total-commission">
			<strong>Total Pending
				Commission:</strong> <?php echo wc_price( $report->commission['pending'] ); ?>
			<br/>
			<strong>Total Final Commission:</strong> <?php echo wc_price( $report->commission['final'] ); ?>
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
							$percentage = (int) WC_MLM_Vendors::$commission_tiers[ $vendor->commission_tier ]['percentage'] / 100;
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

		<?php if ( $vendor_descedants ) : ?>

			<h3>Your Vendors</h3>

			<?php array_walk( $vendor_descedants, array( $this, 'output_vendor_descendants' ) ); ?>

		<?php endif; ?>
	</div>

<?php
$html = ob_get_clean();

$this->page_content = $html;