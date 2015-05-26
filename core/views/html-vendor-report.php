<?php
/**
 * @global $vendor WC_MLM_Vendor
 */

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

require_once WC_MLM_PATH . '/core/class-wc-mlm-report.php';

// Date query
$date_query = array();
$date_from = '';
$date_to = '';

if ( isset( $_GET['date_from'] ) ) {

	$date_from = (int) $_GET['date_from'];

	$date_query['after'] = 'last day of -' . ( $date_from + 1 ) . ' months';
}

if ( isset( $_GET['date_to'] ) ) {

	$date_to = (int) $_GET['date_to'];

	$month_text           = $date_to > 0 ? "-$date_to months" : 'this month';
	$date_query['before'] = "last day of $month_text";
}

$report = new WC_MLM_Report(
	'vendor',
	$vendor,
	$date_query
);

$vendor_descedants = $vendor->get_descendants();

$sales_bonus = $vendor->get_sales_bonus( $date_query );

?>
<div class="clear"></div>

<div class="woocommerce wc-mlm-report">

	<?php WC_MLM_Reporting::show_vendor_messages(); ?>

	<div class="month-select">

		<?php
		$count = isset( $_GET['add_months'] ) ? (int) $_GET['add_months'] + 5 : 5;
		for ( $i = 0; $i < $count; $i ++ ) :
			?>
			<a href="#" class="month-button" data-month="<?php echo $i; ?>">
				<?php echo date( 'M', strtotime( "-$i month" ) ); ?>
			</a>

			<?php if ( date( 'M', strtotime( "-$i month" ) ) == 'Jan' && $i !== $count - 1 ) : ?>
			<div class="clear"></div>
			<div class="year-sep">
				-<?php echo date( 'Y', strtotime( '-' . ( $i + 1 ) . ' month' ) );; ?>-
			</div>
		<?php endif; ?>
		<?php endfor; ?>

	</div>

	<div class="clear"></div>

	<div class="month-actions">
		<a href="#" class="go-button">Go</a>

		<a href="#" class="more-button"
		   data-add="<?php echo isset( $_GET['add_months'] ) ? (int) $_GET['add_months'] + 3 : 3; ?>">+</a>
		<a href="#" class="less-button"
		   data-add="<?php echo isset( $_GET['add_months'] ) ? max( (int) $_GET['add_months'] - 3, 0 ) : 0; ?>">-</a>
	</div>

	<div class="clear"></div>

	<div class="totals">

		<div class="total-sales result-container">

			<h3>Sales</h3>

			<div class="result-highlight">
				<div class="container">
					<span class="result-text">
						<?php echo wc_price( $report->sales ); ?>

						<?php if ( $vendor->level === 1 ) : ?>
							<br/>
							<span class="result-secondary">
								Bonus: <?php echo $sales_bonus; ?>
							</span>
						<?php endif; ?>

					</span>
				</div>
			</div>
		</div>

		<div class="total-commission result-container secondary">

			<h3>Commission</h3>

			<div class="result-highlight">
				<div class="container">
					<span class="result-text">
						<?php echo wc_price( $report->commission['final'] ); ?>

						<br/>
						<span class="result-secondary">
							Pending: <?php echo wc_price( $report->commission['pending'] ); ?>
						</span>
					</span>
				</div>
			</div>
		</div>

	</div>

	<?php if ( ! empty( $report->orders ) ) : ?>

		<h3>Orders</h3>
		<?php
		foreach ( $report->orders as $order ) :
			?>

			<p class="order-meta">
				<span class="order-title">
					Order: #<?php echo $order['order']->id; ?>
				</span>

				<br/>

				<?php if ( $order['customer'] ) : ?>
					<span class="order-customer">
						Customer: <a href="#customer_<?php echo $order['customer']['ID']; ?>">
							<?php echo $order['customer']['name'] ? $order['customer']['name'] : ''; ?>
						</a>
					</span>
				<?php endif; ?>
			</p>

			<h4>Products:</h4>

			<table class="vendor-report-products shop_table">
				<thead>
				<tr>
					<th>
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
				foreach ( $order['items'] as $item ) :
					$product = get_post( $item['product_id'] );
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
							$percentage = (int) WC_MLM_Vendors::$commission_tiers[ $vendor->commission_tier ]['value'] / 100;
							echo wc_price(
								(int) $item['line_total'] * $percentage
							);
							?>
						</td>
					</tr>

				<?php endforeach; ?>

				</tbody>
			</table>

			<hr/>

		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( ! empty( $report->customers ) ) : ?>

		<h3>Customers</h3>

		<table class="vendor-report-products shop_table">
			<thead>
			<tr>
				<th>

				</th>
				<th>
					Email
				</th>
				<th>
					Address
				</th>
			</tr>
			</thead>

			<tbody>

			<?php foreach ( $report->customers as $customer ) : ?>

				<tr id="customer_<?php echo $customer['ID']; ?>">
					<td>
						<?php echo $customer['name']; ?>
					</td>

					<td>
						<?php echo $customer['user']->data->user_email ? $customer['user']->data->user_email : '- NA -'; ?>
					</td>

					<td>
						<?php
						echo $customer['customer']->get_address() . "&nbsp;";
						echo $customer['customer']->get_address_2();
						?>
						<br/>
						<?php
						echo $customer['customer']->get_state() . "&nbsp;";
						echo $customer['customer']->get_city() . "&nbsp;";
						echo $customer['customer']->get_shipping_postcode();
						?>
					</td>
				</tr>

			<?php endforeach; ?>

			</tbody>
		</table>

	<?php endif; ?>

	<?php if ( $vendor_descedants ) : ?>

		<h3>Your Vendors</h3>

		<table class="vendor-report-descendants shop_table">

			<tbody>
			<?php array_walk( $vendor_descedants, array( $this, 'output_vendor_descendants' ) ); ?>
			</tbody>
		</table>

	<?php endif; ?>
</div>