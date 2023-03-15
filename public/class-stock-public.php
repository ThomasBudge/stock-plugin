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

		$from_month = $_REQUEST['fromMonth'];
		$from_year = $_REQUEST['fromYear'];

		$to_month = $_REQUEST['toMonth'];
		$to_year = $_REQUEST['toYear'];

		$args = array(
			'limit' => -1,
			'status' => 'completed',
			'date_created' => "{$from_year}-{$from_month}-01 00:00:00 ... {$to_year}-{$to_month}-31 23:59:59",
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

		$from_month = $_REQUEST['fromMonth'];
		$from_year = $_REQUEST['fromYear'];

		$to_month = $_REQUEST['toMonth'];
		$to_year = $_REQUEST['toYear'];

		$date_end = date('Y-m-d', $timestamp_end);
		$date_start = date('Y-m-d', $timestamp_start);

		$orders = wc_get_orders(array(
			'limit' => -1,
			'status' => 'completed',
			'date_created' => "{$from_year}-{$from_month}-01 00:00:00 ... {$to_year}-{$to_month}-31 23:59:59",
		));

		$heatmap = array();

		foreach($orders as $order) {
			foreach($order->get_items() as $item) {


				// $p = $item->get_product();

				// echo $p->get_name();
				// print_r($item->get_product_id());


				$stock_products = new WP_Query(array(
					'post_type' => 'stock_product',
					'posts_per_page' => 1,
					'meta_query' => array(array(
						'key' => 'related_products',
						'value' => $item->get_product_id(),
						'compare' => 'LIKE',
					))
				));
				
				if(!$stock_products->posts) {
					continue;
				}

				$stock_prod = $stock_products->posts[0];

				$stock_prod_title = $stock_prod->post_title;
				$stock_prod_id = $stock_prod->ID;

				$variation_id = 0;
				$product = $item->get_product(); 
				$quantity = $item->get_quantity();
				$product_id = $product->get_id();

				if ($product->get_type() === 'variation') {
					$variation_id = $product_id;

					$product_id = $product->get_parent_id();
				}

				$product_title = $product->get_title();

				if (isset($heatmap[$stock_prod_id])) {
					$heatmap[$stock_prod_id]['count'] += $quantity;
					$heatmap[$stock_prod_id]['total'] += $item->get_total();

					if ($variation_id) {
						$attr_summary = $product->get_attribute_summary();
						$attr_summary = str_replace('& Clip', ' ', $attr_summary);
						$attr_summary = strtolower($attr_summary);
						$attr_summary = explode(':', $attr_summary);
						// print_r($attr_summary);
						// die;
						$attr_summary = count($attr_summary) === 2 ? $attr_summary[1] : $attr_summary[0];
						$attr_summary = ucfirst(trim($attr_summary));

						if(isset($heatmap[$stock_prod_id]['product']['variations'][$attr_summary])) {
							$heatmap[$stock_prod_id]['product']['variations'][$attr_summary]['total'] += $item->get_total();
							$heatmap[$stock_prod_id]['product']['variations'][$attr_summary]['count'] += $quantity;
						} else {
							$heatmap[$stock_prod_id]['product']['variations'][$attr_summary] = array(
								'variation_id' => $variation_id,
								'attributes' => $product->get_attribute_summary(),
								'total' => $item->get_total(),
								'count' => $quantity,
							);
						}
					}
				} else {
					$heatmap[$stock_prod_id] = array(
						'product' => array(
							'product_id' => $stock_prod_id,
							'title' => $stock_prod_title,
							'variations' => array(),
						),
						'total' => $item->get_total(),
						'count' => 1,
					);
				}
			}
		}

		foreach($heatmap as $product_id => $data) {
			$heatmap[$product_id]['total'] = number_format($data['total'], 2);

			$variations = array_values($heatmap[$product_id]['product']['variations']);

			foreach ($variations as $index => $v) {
				$variations[$index]['total'] = number_format($v['total'], 2);
			}

			usort($variations, function ($item1, $item2) {
				return $item1['count'] < $item2['count'];
			});

			$heatmap[$product_id]['product']['variations'] = $variations;
		} 

		$values = array_values($heatmap);

		usort($values, function ($item1, $item2) {
			return $item1['count'] < $item2['count'];
		});

		wp_send_json($values);
	}

	function get_all_orders_ids_by_product_id( $product_id ) {
		global $wpdb;
		
		// Define HERE the orders status to include in  <==  <==  <==  <==  <==  <==  <==
		$orders_statuses = "'wc-completed', 'wc-processing', 'wc-on-hold'";
	
		# Get All defined statuses Orders IDs for a defined product ID (or variation ID)
		return $wpdb->get_col( "
			SELECT DISTINCT woi.order_id
			FROM {$wpdb->prefix}woocommerce_order_itemmeta as woim, 
				{$wpdb->prefix}woocommerce_order_items as woi, 
				{$wpdb->prefix}posts as p
			WHERE  woi.order_item_id = woim.order_item_id
			AND woi.order_id = p.ID
			AND p.post_status IN ( $orders_statuses )
			AND woim.meta_key IN ( '_product_id', '_variation_id' )
			AND woim.meta_value LIKE '$product_id'
			ORDER BY woi.order_item_id DESC"
		);
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