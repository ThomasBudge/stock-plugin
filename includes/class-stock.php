<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       thomasbudge.com
 * @since      1.0.0
 *
 * @package    Stock
 * @subpackage Stock/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Stock
 * @subpackage Stock/includes
 * @author     Thomas <t.budge@hotmail.co.uk>
 */
class Stock {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Stock_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'STOCK_VERSION' ) ) {
			$this->version = STOCK_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'stock';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->hooks();
	}

	function hooks() {
		$this->loader->add_action('init', $this, 'add_cors_http_header');
		$this->loader->add_action('init', $this, 'handle_preflight');
		$this->loader->add_action('init', $this, 'register_post_types');
		$this->loader->add_action('init', $this, 'custom_taxonomy_category');
		$this->loader->add_action('init', $this, 'custom_taxonomy_year');
		$this->loader->add_action('init', $this, 'custom_taxonomy_model_number');
		$this->loader->add_action('init', $this, 'custom_taxonomy_family');
		$this->loader->add_filter('rest_authentication_errors', $this, 'rest_filter_incoming_connections');
		$this->loader->add_action('manage_stock_product_posts_custom_column', $this, 'bbloomer_add_new_order_admin_list_column_content');
		$this->loader->add_filter('manage_edit-stock_product_columns', $this, 'bbloomer_add_new_order_admin_list_column');
		$this->loader->add_action('admin_enqueue_scripts', $this, 'load_admin_style');
	}

	function load_admin_style() {
		global $pagenow;
	
		if (isset($_GET['post_type']) && 'stock_product' === $_GET['post_type'] ) {
			?>
			<style>
				.wp-list-table thead {
					position: sticky;
					top: 32px;
					background: white;
				}
			</style>
			<?php
		}
	}

	function handle_preflight() {
		header("Access-Control-Allow-Origin: http://localhost:3000");
		header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
		header("Access-Control-Allow-Credentials: true");
		header('Access-Control-Allow-Headers: Origin, X-Requested-With, X-WP-Nonce, Content-Type, Accept, Authorization');
		if ('OPTIONS' == $_SERVER['REQUEST_METHOD']) {
			exit();
		}
	}

	function rest_filter_incoming_connections($errors) {
		$request_server = $_SERVER['REMOTE_ADDR'];
		$origin = get_http_origin();
		return $errors;
	}

	function add_cors_http_header(){
		header("Access-Control-Allow-Origin: *");
	}

	function register_post_types() {
		register_post_type('stock_order', array(
			'label' => 'Stock Order',
			'show_ui' => true,
			'public' => true,'supports' => array( 'title' )
		));

		register_post_type('stock_product', array(
			'label' => 'Stock Product',
			'show_ui' => true,
			'public' => true,
		));

		register_post_type('models', array(
			'label' => 'Models',
			'show_ui' => true,
			'public' => true,
		));
	}

	function custom_taxonomy_category () {
		$labels = array(
			'name'                       => 'Product Category',
			'singular_name'              => 'Product Category',
			'menu_name'                  => 'Product Category',
			'all_items'                  => 'Product Categories',
			'parent_item'                => 'Parent Item',
			'parent_item_colon'          => 'Parent Item:',
			'new_item_name'              => 'New Product Category',
			'add_new_item'               => 'Add New Product Category',
			'edit_item'                  => 'Edit Product Category',
			'update_item'                => 'Update Product Category',
			'separate_items_with_commas' => 'Separate Product Categories with commas',
			'search_items'               => 'Search Product Categories',
			'add_or_remove_items'        => 'Add or remove Product Categories',
			'choose_from_most_used'      => 'Choose from the most used Product Categories',
		);

		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => true,
			'public'                     => true,
			'show_ui'                    => true,
			'has_archive'                => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
			'query_var'                  => true,
			'publicly_queryable'         => true,
		);


		register_taxonomy( 'product_categories', array('stock_product'), $args );
		register_taxonomy_for_object_type( 'product_categories', array('product_stock') );
	}
	
	function custom_taxonomy_year()  {
		$labels = array(
			'name'                       => 'Year',
			'singular_name'              => 'Year',
			'menu_name'                  => 'Year',
			'all_items'                  => 'Years',
			'parent_item'                => 'Parent Item',
			'parent_item_colon'          => 'Parent Item:',
			'new_item_name'              => 'New Year',
			'add_new_item'               => 'Add New Year',
			'edit_item'                  => 'Edit Year',
			'update_item'                => 'Update Year',
			'separate_items_with_commas' => 'Separate Years with commas',
			'search_items'               => 'Search Years',
			'add_or_remove_items'        => 'Add or remove Years',
			'choose_from_most_used'      => 'Choose from the most used Years',
		);

		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => true,
			'public'                     => true,
			'show_ui'                    => true,
			'has_archive'                => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
			'query_var'                  => true,
			'publicly_queryable'         => true,
			'rewrite'                    => array( 'slug' => 'year' ),
		);


		register_taxonomy( 'years', array('models'), $args );
		register_taxonomy_for_object_type( 'years', array('models') );
	}

	function custom_taxonomy_model_number()  {
		$labels = array(
			'name'                       => 'Model Number',
			'singular_name'              => 'Model Number',
			'menu_name'                  => 'Model Number',
			'all_items'                  => 'Model Numbers',
			'parent_item'                => 'Parent Item',
			'parent_item_colon'          => 'Parent Item:',
			'new_item_name'              => 'New Device Model Number',
			'add_new_item'               => 'Add New Device Model Number',
			'edit_item'                  => 'Edit Device Model Number',
			'update_item'                => 'Update Device Model Number',
			'separate_items_with_commas' => 'Separate Device Model Numbers with commas',
			'search_items'               => 'Search Device Model Number',
			'add_or_remove_items'        => 'Add or remove Device Families',
			'choose_from_most_used'      => 'Choose from the most used Device Model Numbers',
		);
		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => true,
			'public'                     => true,
			'show_ui'                    => true,
			'has_archive'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
			'rewrite'                    => array( 'slug' => 'model' ),
		);
		register_taxonomy( 'model_number', array('models'), $args );
		register_taxonomy_for_object_type( 'model_number', array('models') );
	}

	function custom_taxonomy_family()  {
		$labels = array(
			'name'                       => 'Family',
			'singular_name'              => 'Family',
			'menu_name'                  => 'Family',
			'all_items'                  => 'Families',
			// 'parent_item'                => 'Parent Item',
			// 'parent_item_colon'          => 'Parent Item:',
			// 'new_item_name'              => 'New Device Model Number',
			// 'add_new_item'               => 'Add New Device Model Number',
			// 'edit_item'                  => 'Edit Device Model Number',
			// 'update_item'                => 'Update Device Model Number',
			// 'separate_items_with_commas' => 'Separate Device Model Numbers with commas',
			// 'search_items'               => 'Search Device Model Number',
			// 'add_or_remove_items'        => 'Add or remove Device Families',
			// 'choose_from_most_used'      => 'Choose from the most used Device Model Numbers',
		);

		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => true,
			'public'                     => true,
			'show_ui'                    => true,
			'has_archive'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
			'rewrite'                    => array( 'slug' => 'model' ),
		);

		register_taxonomy( 'family', array('models'), $args );
		register_taxonomy_for_object_type( 'family', array('models') );
	}
 
	function bbloomer_add_new_order_admin_list_column( $columns ) {
		$columns['sold'] = 'Sold Qty';
		$columns['sold_value_total'] = 'Total Sold';
		$columns['cost_value_total'] = 'Total Cost';
		$columns['cost'] = 'Cost (Average ppc)';
		$columns['sold_value_average'] = 'Sold (Average ppc)';
		$columns['margin'] = 'Margin';
		$columns['rrp'] = 'RRP';
		$columns['has_related_product'] = 'Configured';

		return $columns;
	}

	function bbloomer_add_new_order_admin_list_column_content( $column ) {
		$shipping_cost = 1; // First class shipping avg.

		global $post;

		$related_products = get_field('related_products', $post->ID);
		$is_bundle = get_field('bundle', $post->ID);
		$bundle_products = get_field('bundle_products', $post->ID);

		if ($column === 'has_related_product') {
			if ($related_products) {
				echo '<span style="height:20px;width:20px;background:green;border-radius:50%;display:block;"></span>';
			} else {
				echo '<span style="height:20px;width:20px;background:red;border-radius:50%;display:block;"></span>';
			}

			return;
		}

		$stock_order_ids = new WP_Query(array(
			'post_type' => 'stock_order',
			'posts_per_page' => -1,
			'field' => 'ids',
		));

		$stock_order_ids = wp_list_pluck($stock_order_ids->posts, 'ID');

		$RRP = 0;

		$cost = 0;

		if($stock_order_ids) {
			if($is_bundle) {
				foreach($stock_order_ids as $stock_order_id):

					$exchange = get_field('exchange_rate', $stock_order_id) ? get_field('exchange_rate', $stock_order_id) : 1;
					$exchange = floatval($exchange);

					if(have_rows('products', $stock_order_id)):
						while(have_rows('products', $stock_order_id)): the_row();	
							$product_id = get_sub_field('product');
							if (in_array($product_id, $bundle_products)) {
								$cost += $this->get_gbp_price(get_sub_field('price'), $exchange);
							}
						endwhile;
					endif;

	
					$RRP = $cost + ($cost * 0.2); // 20% for import costs

					$RRP = $cost * 2; // 100% markup to double that $$$

					$RRP = $RRP + $RRP * 0.10; // Add 10% to account for ebay fees

					$RRP += $shipping_cost;
				endforeach;
			} else {
				foreach($stock_order_ids as $stock_order_id):
					$exchange = get_field('exchange_rate', $stock_order_id) ? get_field('exchange_rate', $stock_order_id) : 1;
					$exchange = floatval($exchange);

					if(have_rows('products', $stock_order_id)) {
						while(have_rows('products', $stock_order_id)): the_row();	
							if (get_sub_field('product') === $post->ID) {
								$cost = $this->get_gbp_price(get_sub_field('price'), $exchange);
	
								$RRP = $cost + ($cost * 0.2); // 20% for import costs
	
								$RRP = $cost * 2; // 100% markup to double that $$$
	
								$RRP = $RRP + $RRP * 0.10; // Add 10% to account for ebay fees
	
								$RRP += $shipping_cost;
								// break;
							}
						endwhile;
					}
	
				endforeach;
			}
		}

		if ('rrp' === $column) {
			echo '£' . number_format($RRP, 2);
		}

		if ( 'cost' === $column) {
			$value = '-';

			if($stock_order_ids) $value = '£' . number_format($cost, 2);

			echo $value;
		}

		$is_bundle = get_field('bundle', $post->ID);
		$bundle_products = get_field('bundle_products');

		$total_cost = 0;

		if ( 'cost_value_total' === $column) {
			if($stock_order_ids) {
				foreach($stock_order_ids as $id):

					$exchange = get_field('exchange_rate', $id) ? get_field('exchange_rate', $id) : 1;
					$exchange = floatval($exchange);

					if(have_rows('products', $id)) {
						while(have_rows('products', $id)): the_row();	
							$product_id = get_sub_field('product');
							$price = $this->get_gbp_price(get_sub_field('price'), $exchange);
							$qty = (int) get_sub_field('quantity');

							if($is_bundle && in_array($product_id, $bundle_products)) {
								$total_cost += $price * $qty;
							} else {
								if ($product_id === $post->ID) {
									$total_cost = $price * $qty;
								}
							}

						endwhile;
					}

				endforeach;
			}

			if ($total_cost) {
				echo '£' . number_format($total_cost, 2);
			} else {
				echo "-";
			}
		}

		if (!$related_products) return;

		$sold_quantity = 0;
		$sold_value = 0;

		foreach($related_products as $index => $product_id) {
			global $wpdb;
		
			// $args = array(
			// 	'limit' => -1,
			// 	'return' => 'ids',
			// );
		
			# Get All defined statuses Orders IDs for a defined product ID (or variation ID)
			//first get all the order ids
			// $query = new WC_Order_Query( $args );
			$order_ids = get_all_orders_ids_by_product_id($product_id);
			// print_r($order_ids);
			// var_dump($order_ids);
			//iterate through order
			$filtered_order_ids = array();

			if($order_ids) {
				foreach ($order_ids as $order_id) {
					$order = wc_get_order($order_id);
					$order_items = $order->get_items();
					//iterate through an order's items
					foreach ($order_items as $item) {
						//if one item has the product id, add it to the array and exit the loop
						if ($item->get_product_id() === $product_id) {
								$sold_quantity += $item->get_quantity();
								$sold_value += $item->get_total();
								break;
							}
						}
				}
			}
		}

		$avg = 0;

		if ($sold_quantity) {
			$avg = $sold_value / $sold_quantity;
		}

		if ( 'sold' === $column ) {
			echo $sold_quantity;
		}
	
		if ( 'sold_value_average' === $column ) {

			if ($avg && $avg < $RRP) {
				echo '<span style="color:red;">' . '£' . $avg . '</span>';
				return;
			}

			echo '£' . number_format($avg, 2);
		}

		if ( 'sold_value_total' === $column ) {
			echo '£' . number_format($sold_value, 2);
		}

		if ('margin' === $column && $cost && $avg) {
			$avg = $avg - ($avg * 0.10);
			
			$avg_revenue = $avg + $shipping_cost;

			$avg_gross_profit = $avg - $shipping_cost;

			$margin = ($avg_gross_profit / $avg_revenue) * 100;

			echo number_format($margin, 2) . '%';
		}
	}

	private function get_gbp_price ($price, $exchange = 1) {
		return floatval($price) * floatval($exchange);
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Stock_Loader. Orchestrates the hooks of the plugin.
	 * - Stock_i18n. Defines internationalization functionality.
	 * - Stock_Admin. Defines all hooks for the admin area.
	 * - Stock_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-stock-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-stock-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-stock-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-stock-public.php';

		$this->loader = new Stock_Loader();
}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Stock_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Stock_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Stock_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_dashboard_setup', $plugin_admin, 'add_dashboard_widget' );
		$this->loader->add_action( 'admin_post_csv_processing', $plugin_admin, 'csv_processing' );
		$this->loader->add_action( 'admin_post_everymac_processing', $plugin_admin, 'everymac_processing' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Stock_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Stock_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	public function get_sales_table () {
		// add_action('init', function () {
		// $product_sales = array();

		// $orders = wc_get_orders(array('limit' => -1));

		//     foreach ($orders as $order) {

		//         foreach($order->get_items() as $item) {

		//             $is_variation = $item['variation_id'] > 0 ? true : false;
		//             $product_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
		//             $quantity = $item['quantity'];

		//             if ($is_variation) {
		//                 if(!isset($product_sales[$product_id])) $product_sales[$product_id] = [];
		//                 $variation = wc_get_product($product_id);
		//                 $variation_name = implode( ' / ', $variation->get_variation_attributes() );
		//                 // echo "<pre>";
		//                 // print_r($variation_name);
		//                 // echo "<pre>";

		//                 // FIXME: VARIATIONS ARE NOT SET ON PRODUCTS
		//                 // FIXME: VARIATIONS ARE NOT SET ON PRODUCTS
		//                 // FIXME: VARIATIONS ARE NOT SET ON PRODUCTS
		//                 // FIXME: VARIATIONS ARE NOT SET ON PRODUCTS

		//                 isset($product_sales[$product_id][$variation_name]) ? $product_sales[$product_id][$variation_name]++ : $product_sales[$product_id][$variation_name] = 0;
		//             } else {
		//                 isset($product_sales[$product_id]) ? $product_sales[$product_id]++ : $product_sales[$product_id] = 0;
		//             }
		//         }
		//     }
		// });
	}

    public function count_orders_from_variation($variation_id){
        global $wpdb;
    
        // DEFINE below your orders statuses
        $statuses = array('wc-completed', 'wc-processing');
    
        $statuses = implode("','", $statuses);
    
        return $wpdb->get_var("
            SELECT count(p.ID) FROM {$wpdb->prefix}woocommerce_order_items AS woi
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS woim ON woi.order_item_id = woim.order_item_id
            JOIN {$wpdb->prefix}posts AS p ON woi.order_id = p.ID
            WHERE p.post_type = 'shop_order' AND p.post_status IN ('$statuses')
            AND woim.meta_key LIKE '_variation_id' AND woim.meta_value = $variation_id
        ");
    }
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