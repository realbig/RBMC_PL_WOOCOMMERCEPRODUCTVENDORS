<?php
/**
 * @global $report_table WC_MLM_ReportTable
 * @global $total_cos    int
 */

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}
?>
<div class="wrap">

	<div class="wc-mlm-report sales-leader-report">
		<form class="wc-mlm-report-actions" method="get">

			<input type="hidden" name="page" value="<?php echo $_GET['page']; ?>"/>
			<input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>"/>

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

			<div class="wc-mlm-sales-leader-report">
				<h3 class="wc-mlm-table-title">Overview</h3>

				<?php $report_table->output(); ?>

				<p class="total-cos">
					<em>
						Total COS: <?php echo wc_price( $total_cos ); ?>
					</em>
				</p>

			</div>

		<?php endif; ?>
	</div>
</div>