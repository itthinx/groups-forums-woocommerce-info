<?php
/**
 * groups-forums-woocommerce-info.php
 *
 * Copyright (c) 2017 "kento" Karim Rahimpur www.itthinx.com
 *
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This header and all notices must be kept intact.
 *
 * @author itthinx
 * @author George Tsiokos
 * @author Karim Rahimpur
 * 
 * @package groups-forums-woocommerce-info
 * @since groups-forums-woocommerce-info 1.0.0
 *
 * Plugin Name: Groups Forums WooCommerce Info
 * Plugin URI: http://www.itthinx.com/plugins/groups
 * Description: This WordPress plugin is an extension for Groups Forums and WooCommerce. It will show order info on topics for the topic's author. This is useful when you allow your customers to post topics and use Groups Forums as your support system.
 * Version: 2.0.0
 * Author: itthinx
 * Author URI: http://www.itthinx.com
 * Donate-Link: http://www.itthinx.com
 * Text Domain: groups-forums-woocommerce-info
 * Domain Path: /languages
 * License: GPLv3
 */
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// @since 3.1.0 HPOS compatibility
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

class Groups_Forums_WooCommerce_Info {

	/**
	 * Adds our action on plugins_loaded.
	 */
	public static function boot() {
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
	}

	/**
	 * Adds our add_meta_boxes action if WooCommerce is detected.
	 */
	public static function plugins_loaded() {
		if ( defined( 'WC_VERSION' ) ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ), 10, 2 );
		}
	}

	/**
	 * Adds our meta box to topics.
	 * @param string $post_type
	 * @param WP_Post $post
	 */
	public static function add_meta_boxes( $post_type, $post = null ) {
		add_meta_box(
			'groups-forums-woocommerce-info',
			__( 'Orders', 'groups-forums-woocommerce-info' ),
			array( __CLASS__, 'meta_box' ),
			'topic',
			'normal',
			'low'
		);
	}

	/**
	 * Renders our meta box with order info based on the topic's author.
	 */
	public static function meta_box() {
		global $post;

		$output = '';

		if ( empty( $post->post_author ) ) {
			return '';
		}

		$author_id = $post->post_author;
		$author_user = get_user_by( 'id', $author_id );

		if ( !$author_user ) {
			return;
		}

		$customer_orders = wc_get_orders(
			array(
				'return'      => 'ids',
				'limit'       => -1,
				'customer_id' => $author_id,
				'status'      => array( 'wc-processing', 'wc-completed' ),
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$orders = array();
		foreach ( $customer_orders as $o ) {
			$orders[] = wc_get_order( $o );
		}

		$output .= '<div style="border: 1px solid #ccc; padding: 1em; margin: 0.62em;">';
		$output .= sprintf( '<p>Number of processing and completed orders: %d</p>', count( $orders ) );
		$output .= '</div>';

		ob_start();
		foreach( $orders as $order ) {

			if ( method_exists( $order, 'get_id' ) ) {
				$order_id = $order->get_id();
			} else {
				$order_id = $order->id;
			}

			echo '<div style="border: 1px dashed #ccc; padding: 1em; margin: 0.62em;">';

			if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_order' ) ) {
				$order_link = sprintf( '<a href="%s">#%d</a>', esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ), intval( $order_id ) );
			} else {
				$order_link = sprintf( '#%s', intval( $order_id ) );
			}

			printf( '<h3>Order %s</h3>', $order_link );

			wc_get_template( 'order/order-details-customer.php', array( 'order' =>  $order ) );

			// Backwards compatibility
			$status       = new stdClass();
			$status->name = wc_get_order_status_name( $order->get_status() );

			wc_get_template(
				'myaccount/view-order.php',
				array(
					'status'    => $status, // @deprecated 2.2
					'order'     => wc_get_order( $order_id ),
					'order_id'  => $order_id
				)
			);
			echo '</div>';
		}
		$output .= ob_get_clean();

		echo $output;
	}
}
Groups_Forums_WooCommerce_Info::boot();
