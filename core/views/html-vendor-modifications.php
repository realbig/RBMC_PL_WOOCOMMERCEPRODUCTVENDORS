<?php
/**
 * @global $this WC_MLM_VendorModifications
 */

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}
?>

<div class="wrap">

	<h2><?php echo _wc_mlm_setting( 'vendor_verbage' ); ?> Modifications</h2>

	<?php if ( ! $this->modifications && false ) : ?>
		No pending modifications.
	<?php
	else :
		require_once WC_MLM_PATH . '/core/class-wc-mlm-report-table.php';
		$report_table = new WC_MLM_ReportTable( array(
			'actions'    => array(
				'label'    => 'Actions',
				'type'     => 'name',
				'no_order' => true,
			),
			'date'       => array(
				'label'        => 'Date',
				'type'         => 'name',
				'order'        => 'char',
				'orderdefault' => 'desc',
			),
			'type'       => array(
				'label'        => 'Type',
				'type'         => 'name',
				'order'        => 'char',
				'orderdefault' => 'asc',
			),
			'instigator' => array(
				'label'        => 'Instigator',
				'type'         => 'name',
				'order'        => 'char',
				'orderdefault' => 'asc',
			),
			'victim'     => array(
				'label'        => 'Victim',
				'type'         => 'name',
				'order'        => 'char',
				'orderdefault' => 'asc',
			),
			'old_value'  => array(
				'label'        => 'Old Value',
				'type'         => 'name',
				'order'        => 'char',
				'orderdefault' => 'asc',
			),
			'new_value'  => array(
				'label'        => 'New Value',
				'type'         => 'name',
				'order'        => 'char',
				'orderdefault' => 'asc',
			),
		), 'date' );

		foreach ( $this->modifications as $modification ) {

			$modification = WC_MLM_VendorModifications::get_verbage( $modification );

			$approve_link = add_query_arg( 'wc_mlm_approve', $modification['hash'] );
			$delete_link  = add_query_arg( 'wc_mlm_delete', $modification['hash'] );

			$actions_html = '<div class="wc-mlm-vendor-actions">';
			$actions_html .= '<a href="' . $approve_link . '" class="dashicons dashicons-yes button wc-mlm-approve"></a>';
			$actions_html .= '<a href="' . $delete_link . '" class="dashicons dashicons-no button wc-mlm-delete"></a>';
			$actions_html .= '</div>';


			$report_table->add_row( array(
				'actions'    => $actions_html,
				'date'       => $modification['date'],
				'type'       => $modification['type'],
				'instigator' => $modification['instigator'],
				'victim'     => $modification['victim'],
				'old_value'  => $modification['old_value'],
				'new_value'  => $modification['new_value'],
			) );
		}

		$report_table->output();
		?>

	<?php endif; ?>
</div>