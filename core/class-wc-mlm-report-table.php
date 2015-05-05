<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class WC_MLM_ReportTable {

	public $columns = array();
	public $body = array();
	public $default_orderby;
	public $custom_footer = false;

	function __construct( $columns, $default_orderby ) {

		$this->columns         = $columns;
		$this->default_orderby = $default_orderby;
	}

	public function add_row( $row ) {
		$this->body[] = $row;
	}

	function _sort( $a, $b ) {

		$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : $this->default_orderby;
		$order   = isset( $_GET['order'] ) ? $_GET['order'] : $this->columns[ $orderby ]['orderdefault'];

		// Flip $a and $b
		if ( $order == 'desc' ) {
			$c = $a;
			$a = $b;
			$b = $c;
		}

		switch ( $this->columns[ $orderby ]['order'] ) {
			case 'char':
				return strcasecmp( $a[ $orderby ], $b[ $orderby ] );
				break;

			case 'int':
			default:
				return $a[ $orderby ] - $b[ $orderby ];
				break;
		}
	}

	private function _column_headings() {
		?>
		<tr>
			<?php
			foreach ( $this->columns as $column_ID => $column ) :

				$classes = array(
					$column_ID,
				);

				if ( ! isset( $column['no_order'] ) ) {

					$sorted = isset( $_GET['orderby'] ) && $_GET['orderby'] == $column_ID;
					$order  = $sorted ? ( $_GET['order'] == 'asc' ? 'desc' : 'asc' ) : $column['orderdefault'];

					$classes[] = 'sortable';
					$classes[] = $column_ID;
					$classes[] = $sorted ? 'sorted' : '';
					$classes[] = $order == 'asc' ? 'desc' : 'asc';

					$link = add_query_arg( array(
						'order'   => $order,
						'orderby' => $column_ID,
					) );
				}
				?>
				<th class="<?php echo implode( ' ', array_filter( $classes ) ); ?>">

					<?php if ( ! isset( $column['no_order'] ) ) : ?>
						<a href="<?php echo $link; ?>">
							<span class="title"><?php echo $column['label']; ?></span>
							<span class="sorting-indicator"></span>
						</a>
					<?php else : ?>
						<?php echo $column['label']; ?>
					<?php endif; ?>
				</th>
			<?php endforeach; ?>
		</tr>
	<?php
	}

	public function custom_footer( $html ) {
		$this->custom_footer = $html;
	}

	public function output() {

		usort( $this->body, array( $this, '_sort' ) );
		?>
		<table class="widefat striped fixed">

			<thead>
			<?php $this->_column_headings(); ?>
			</thead>

			<tbody>

			<?php foreach ( $this->body as $row ) : ?>
				<tr>

					<?php foreach ( $row as $row_ID => $cell ) : ?>
						<td>
							<?php
							switch ( $this->columns[ $row_ID ]['type'] ) {
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

			<tfoot>

			<?php
			if ( $this->custom_footer ) {
				echo $this->custom_footer;
			} else {
				$this->_column_headings();
			}
			?>

			</tfoot>

		</table>
	<?php
	}
}