<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       thomasbudge.com
 * @since      1.0.0
 *
 * @package    Stock
 * @subpackage Stock/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Stock
 * @subpackage Stock/public
 * @author     Thomas <t.budge@hotmail.co.uk>
 */
class Stock_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		$this->process();

		add_action( 'rest_api_init', array($this, 'shipping_endpoint'));
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Stock_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Stock_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/stock-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Stock_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Stock_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/stock-public.js', array( 'jquery' ), $this->version, false );

	}

	function process () {
		add_action( 'rest_api_init', array($this, 'shipping_endpoint') );
		add_action( 'rest_api_init', array($this, 'heatmap') );
	}

	public function shipping_endpoint () {
		register_rest_route( 'mrk/v1', '/shipping', array(
			'methods' => 'GET',
			'callback' => array($this, 'my_awesome_func'),
		) );
	}

	public function my_awesome_func () {

		$current_month = $_REQUEST['month'];
		$current_year = $_REQUEST['year'];

		$args = array(
			'limit' => -1,
			'status' => 'completed',
			'date_created' => "{$current_year}-{$current_month}-01 00:00:00 ... {$current_year}-{$current_month}-31 23:59:59",
		);

		$orders = wc_get_orders( $args );

		$total_shipping_cost = 0;

		foreach ( $orders as $order ) {
			$total_shipping_cost += $order->get_shipping_total();
		}

		wp_send_json( array('total' => $total_shipping_cost) );

		exit;
	}

	public function heatmap () {
		register_rest_route( 'mrk/v1', '/heatmap', array(
			'methods' => 'GET',
			'callback' => array($this, 'get_heatmap'),
		) );
	}

	public function get_heatmap () {
		// get all orders over past month
		
		// loop them with product ID as key

		// count as value

		// return assoc arr


		// $params = $request->get_url_params('id');
		// $id = intval($params['id']);

		$timestamp_end = time();
		$timestamp_start = $timestamp_end - 30 * 24 * 60 * 60;

		$date_end = date('Y-m-d', $timestamp_end);
		$date_start = date('Y-m-d', $timestamp_start);

		$orders = wc_get_orders(array(
			'limit' => -1,
			'status' => 'completed',
			'date_created' => $timestamp_start . '...' . $date_end
		));

		$heatmap = array();

		foreach($orders as $order) {
			foreach($order->get_items() as $item) {

				$product = $item->get_product(); 
				$quantity = $item->get_quantity();
				$product_id = $product->get_id();

				if ($product->get_type() === 'variation') {
					$product_id = $product->get_parent_id();
				}

				$product_title = $product->get_title();

				if (isset($heatmap[$product_id])) {
					$heatmap[$product_id]['count'] += $quantity;
					$heatmap[$product_id]['total'] += $item->get_total();
				} else {
					$heatmap[$product_id] = array(
						'product' => array(
							'product_id' => $product->get_id(),
							'title' => $product_title,
						),
						'total' => $item->get_total(),
						'count' => 1,
					);
				}
			}
		}

		$values = array_values($heatmap);

		usort($values, function ($item1, $item2) {
			return $item1['total'] <=> $item2['total'];
		});

		wp_send_json($values);
	}
}


// DELETE relations.*, taxes.*, terms.*
// FROM wp_term_relationships AS relations
// INNER JOIN wp_term_taxonomy AS taxes
// ON relations.term_taxonomy_id=taxes.term_taxonomy_id
// INNER JOIN wp_terms AS terms
// ON taxes.term_id=terms.term_id
// WHERE object_id IN (SELECT ID FROM wp_posts WHERE post_type='product');

// DELETE FROM wp_postmeta WHERE post_id IN (SELECT ID FROM wp_posts WHERE post_type = 'product');
// DELETE FROM wp_posts WHERE post_type = 'product';

// DELETE FROM wp_woocommerce_order_itemmeta;
// DELETE FROM wp_woocommerce_order_items;
// DELETE FROM wp_comments WHERE comment_type = 'order_note';
// DELETE FROM wp_postmeta WHERE post_id IN ( SELECT ID FROM wp_posts WHERE post_type = 'shop_order' );
// DELETE FROM wp_posts WHERE post_type = 'shop_order';