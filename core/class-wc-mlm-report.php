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
	public $customers;
	public $sales = 0;
	public $commission = 0;
	public $cos = 0;

	/**
	 * @param string $type
	 * @param bool|WC_MLM_Vendor   $vendor
	 * @param array  $date_query
	 */
	function __construct( $type = 'vendor', $vendor = false, $date_query = array() ) {

		// Setup properties
		$this->type       = $type;
		$this->date_query = $this->_set_date_query( $date_query );
		$this->vendor     = $vendor;
		$this->orders     = $this->_get_orders();
		$this->items      = $this->_get_items();
		$this->products   = $this->_get_products();
		$this->customers = $this->_get_customers();

		if ( empty( $this->items ) ) {
			return;
		}

		$this->sales      = $this->_get_sales();
		$this->commission = $this->_get_commission();
		$this->cos = $this->_get_cos();
	}

	private function _set_date_query( $date_query ) {

		return wp_parse_args( $date_query, array(
			'inclusive' => true,
			'after'     => 'last day of -1 months',
		) );
	}

	private function _get_orders( $args = array() ) {

		$args = wp_parse_args( $args, array(
			'post_type'   => 'shop_order',
			'numberposts' => - 1,
			'post_status' => array( 'wc-completed' ),
			'date_query'  => $this->date_query,
		) );

		$order_posts = get_posts( $args );
		$orders      = array();

		if ( $this->type == 'vendor' && ! empty( $order_posts ) ) {
			foreach ( $order_posts as $i => $order ) {

				$vendors = get_post_meta( $order->ID, '_vendors', true );

				if ( is_array( $vendors ) && in_array( $this->vendor->ID, $vendors ) ) {

					$orders[ $order->ID ] = array(
						'order'    => new WC_Order( $order->ID ),
						'customer' => get_post_meta( $order->ID, '_customer_user', true ),
					);
				}
			}
		}

		return $orders;
	}

	private function _get_items() {

		$order_items = array();

		foreach ( $this->orders as $ID => $_order ) {

			$order = new WC_Order( $ID );
			$items = $order->get_items();

			foreach ( $items as $item ) {

				if ( $this->vendor->ID != $item['vendor'] ) {
					continue;
				}

				$item = array_merge( $item, array( 'date' => $order->order_date, 'order' => $ID ) );

				$this->orders[ $ID ]['items'][] = $item;
				$order_items[]                  = $item;
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
				$this->orders[ $item['order'] ]['products'][ $product_ID ] = $product;
			}
		}

		return $products;
	}

	private function _get_customers() {

		if ( ! WC()->session ) {
			return;
		}

		$customers = array();
		foreach ( $this->orders as $ID => $order ) {

			if ( $order['customer'] ) {

				if ( isset( $this->customers[ (int) $order['customer'] ] ) ) {
					continue;
				}

				$customer = new WC_Customer( $order['customer'] );
				$user = new WP_User( $order['customer'] );

				$name = $user->data->display_name ? $user->data->display_name : false;
				$name = $name ? $name : $user->data->user_nicename;
				$name = $name ? $name : false;

				$customer_info = array(
					'customer' => $customer,
					'user' => $user,
					'name' => $name,
					'ID' => $order['customer'],
				);
				$customers[ (int) $order['customer'] ] = $customer_info;

				$this->orders[ $ID ]['customer'] = $customer_info;
			}
		}

		return $customers;
	}

	private function _get_sales() {

		$sales = (int) $this->sales;

		foreach ( $this->items as $item ) {
			$sales = $sales + (int) $item['line_total'];
		}

		return $sales;
	}

	private function _get_commission() {

		$commission = array(
			'pending' => 0,
			'final' => 0,
		);

		$return_time = time() - DAY_IN_SECONDS * 30;

		foreach ( $this->items as $item ) {

			// Pending until passed the return window
			$order_time = strtotime( $item['date'] );

			if ( $order_time < $return_time ) {
				$commission['final'] = $commission['final'] + ( (int) $item['line_total'] / ( 100 / (int) $item['commission'] ) );
			} else {
				$commission['pending'] = $commission['pending'] + ( (int) $item['line_total'] / ( 100 / (int) $item['commission'] ) );
			}
		}

		return $commission;
	}

	private function _get_cos() {

		$cos = 0;
		if ( ! empty( $this->items ) ) {
			foreach ( $this->items as $item ) {

				$item_cos = get_post_meta( $item['product_id'], '_wc_mlm_cos', true );
				$cos += $item_cos ? (int) $item_cos : 5;
			}
		}

		return $cos;
	}
}