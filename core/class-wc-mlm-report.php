<?php

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class WC_MLM_Report {

	public $type;

	/**
	 * @var WC_MLM_Vendor
	 */
	public $vendor;
	public $date_query;
	public $orders;
	public $items;
	public $products;
	public $total_sales = 0;
	public $total_commission = 0;

	function __construct( $type = 'vendor', $vendor = false, $date_query = array() ) {

		// Setup properties
		$this->type       = $type;
		$this->date_query = $this->_set_date_query( $date_query );
		$this->vendor     = $vendor;
		$this->orders     = $this->_get_orders();
		$this->items      = $this->_get_items();
		$this->products   = $this->_get_products();

		if ( empty( $this->items ) ) {
			return;
		}

		$this->total_sales      = $this->_get_total_sales();
		$this->total_commission = $this->_get_total_commission();
	}

	private function _set_date_query( $date_query ) {

		return wp_parse_args( $date_query, array(
			'inclusive' => true,
			'after'     => '- 30 days'
		) );
	}

	private function _get_orders( $args = array() ) {

		$args = wp_parse_args( $args, array(
			'post_type'   => 'shop_order',
			'numberposts' => - 1,
			'post_status' => array_keys( wc_get_order_statuses() ),
			'date_query'  => $this->date_query,
		) );

		$orders = get_posts( $args );

		if ( $this->type == 'vendor' && ! empty( $orders ) ) {
			foreach ( $orders as $i => $order ) {

				$vendors = get_post_meta( $order->ID, '_vendors', true );

				if ( ! is_array( $vendors ) || ! in_array( $this->vendor->ID, $vendors ) ) {
					unset( $orders[ $i ] );
				}
			}
		}

		return $orders;
	}

	private function _get_items() {

		$order_items = array();

		foreach ( $this->orders as $_order ) {

			$order = new WC_Order( $_order->ID );
			$items = $order->get_items();

			foreach ( $items as $item ) {

				if ( $this->vendor->ID != $item['vendor'] ) {
					continue;
				}

				$order_items[] = array_merge( $item, array( 'date' => $order->order_date ) );
			}
		}

		return $order_items;
	}

	private function _get_products() {

		$products = array();

		foreach ( $this->items as $item ) {

			$product_ID = $item['item_meta']['_product_id'][0];

			if ( ! isset( $products[ $product_ID ] ) ) {
				$product                 = get_post( $product_ID );
				$products[ $product_ID ] = $product;
			}
		}

		return $products;
	}

	private function _get_total_sales() {

		$sales = (int) $this->total_sales;

		foreach ( $this->items as $item ) {
			$sales = $sales + (int) $item['line_total'];
		}

		return $sales;
	}

	private function _get_total_commission() {

		global $WC_MLM;

		$commission = array(
			'pending' => 0,
			'final' => 0,
		);

		$return_time = time() - DAY_IN_SECONDS * 30;

		foreach ( $this->items as $item ) {

			// Pending until passed the return window
			$order_time = strtotime( $item['date'] );

			if ( $order_time < $return_time ) {
				$commission['final'] = $commission['final'] + (int) $item['line_total'];
			} else {
				$commission['pending'] = $commission['final'] + (int) $item['line_total'];
			}
		}

		$percentage = (int) $WC_MLM->vendors->commission_tiers[ $this->vendor->commission_tier ]['percentage'] / 100;

		$commission['pending'] = $commission['pending'] * $percentage;
		$commission['final'] = $commission['final'] * $percentage;

		return $commission;
	}
}