<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       thomasbudge.com
 * @since      1.0.0
 *
 * @package    Stock
 * @subpackage Stock/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Stock
 * @subpackage Stock/admin
 * @author     Thomas <t.budge@hotmail.co.uk>
 */
require_once(__DIR__ . '/../includes/class-stock-import.php');		

class Stock_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_action('rest_api_init', function () {
			register_rest_route('wc/v3', '/stock', [
				'methods'  => 'GET',
				'callback' => array($this, 'stock_history'),
			]);
		});		

		add_action('rest_api_init', function () {
			register_rest_route('wc/v3', '/history/sales/(?P<id>\d+)', [
				'methods'  => 'GET',
				'callback' => array($this, 'product_sales_history'),
			]);
		});		

		add_action('rest_api_init', function () {
			register_rest_route('wc/v3', '/categories', [
				'methods'  => 'GET',
				'callback' => array($this, 'category_analytics'),
			]);
		});	

		add_action( 'add_meta_boxes', array($this, 'op_register_menu_meta_box') );
		add_action( 'add_meta_boxes', array($this, 'ebay_description_meta_box') );
	}

	function product_sales_history (WP_REST_Request $request) {
		$params = $request->get_url_params('id');
		$id = (int) $params['id'];

		$timestamp_start = date('Y-m-d', strtotime('-3 month'));
		$timestamp_end   = date('Y-m-d', strtotime('today'));

		$begin = new DateTime($timestamp_start);
		$end = new DateTime($timestamp_end);

		$interval = DateInterval::createFromDateString('1 day');
		$period = new DatePeriod($begin, $interval, $end);

		$data = array();

		foreach ($period as $dt) {
			$date = $dt->format("Y-m-d");
			$timestamp = strtotime($date);
			$data[$timestamp] = array( 'count' => 0, 'date' => $date );

			$orders = wc_get_orders(array(
				'posts_per_page' => -1,
				'date_created' => $timestamp
			));

			// TODO: This is a specific products history - we want key group history e.g. A2141 history of right arrow sales.
			if(count($orders)) {
				foreach($orders as $order) {

					foreach($order->get_items() as $item) {
						$product = $item->get_product();
						$product_id = $item->get_product_id();
						$variation = false;
	
						if($product->get_type() === 'variation') {
							$variation = $product;
							$product = wc_get_product($product->get_parent_id());
							$product_id = $product->get_id();
						}
	
						if ($product_id === $id) {
							if(!isset($data[$timestamp])) {
								$data[$timestamp] = array( 'count' => 0, 'date' => $date );
							}
	
							$data[$timestamp]['count'] += $item->get_quantity();
						}
					}
				}
			}
		}


		$sortedObject = array();
		ksort($data);

		foreach($data as $date => $value){
			array_push($sortedObject, array($date, $value));
		}

		wp_send_json($sortedObject, 200);
		exit;
	}

	function stock_history (WP_REST_Request $request) {
		// if (!is_user_logged_in() && (int)wp_get_current_user()->ID !== 123) {
		// 	return new WP_Error('unauthorized', __('You shall not pass'), [ 'status' => 401 ]);
		// }

		$data = array();

		$timestamp_start = '2022-7-01';
		$timestamp_end   = '2022-10-01';

		$orders = wc_get_orders(array(
			'posts_per_page' => -1,
			'date_created' => strtotime($timestamp_start ) .'...'. strtotime($timestamp_end)
		));

		foreach($orders as $order) {
			foreach($order->get_items() as $item) {
				$product = $item->get_product();

				$variation = false;
				if($product->get_type() === 'variation') {
					$variation = $product;
					$product = wc_get_product($product->get_parent_id());
				}

				$key_group = $product->get_meta('product_group');

				if(!$key_group) continue;

				if (!isset($data[$key_group])) {
					$data[$key_group] = array();
				}

				$product_type = $product->get_type();

				if ($product_type === 'simple') {
					$title = $product->get_name();
					if(!isset($data[$key_group][$title])) {
						$data[$key_group][$title] = 0;
					}

					$data[$key_group][$title]++;
				} else if ($product_type === 'variable' && $variation) {
					$attributes = $variation->get_attributes();

					foreach($attributes as $attr_key => $attr_value) {
						$attr_value = strtolower($attr_value);
						$attr_value = str_replace('& clip', '', $attr_value);
						$attr_value = str_replace('+ clip', '', $attr_value);
						$attr_value = trim($attr_value);

						if(!isset($data[$key_group][$attr_value])) {
							$data[$key_group][$attr_value] = 0;
						}

						$data[$key_group][$attr_value]++;
					}
				}
			}
		}

		$sortedObject = array();

		foreach($data as $type => $data) { 
			arsort($data);
			$sortedObject[$type] = array();

			foreach($data as $key => $value){
				$sortedObject[$type][] = array($key, $value);
			}
		}

		wp_send_json($sortedObject, 200);
		exit;

		// foreach($data as $type => $data) { 
		// 	arsort($data);
		// 	?>
		 	<h2><?php echo $type; ?></h2>
		 	<table>
		 		<tr>
		 			<th>Attribute</th>
		 			<th>Count</th>
		 		</tr>
		 		<?php foreach($data as $key => $value): ?>
		 			<tr>
		 				<td><?php echo $key; ?></td>
		 				<td><?php echo $value; ?></td>
		 			</tr>
		 		<?php endforeach ?>
		 	</table>
		 	<?php
		// }
	}

	function category_analytics (WP_REST_Request $request) {
		$categories = get_terms('product_categories');

		$cat_data_arr = array();

		foreach($categories as $cat) {
			$cat_data = array(
				'sold' => 0,
				'cost' => 0,
				'name' => $cat->name,
			);

			$args = array(
				'post_type' => 'stock_product',
				'posts_per_page' => -1,
				'tax_query' => array(
					array(
						'taxonomy' => 'product_categories',
						'field' => 'slug',
						'terms' => $cat->slug
					)
				)
			);

			$product_query = new WP_Query($args);

			if($product_query->have_posts()){
				while ($product_query->have_posts()): $product_query->the_post();

					$current_stock_product_id = get_the_ID();
					$wc_product_ids = get_field('related_products', $current_stock_product_id);

					if($wc_product_ids) {
						foreach($wc_product_ids as $product_id) {

							$order_ids = get_all_orders_ids_by_product_id($product_id);

							if($order_ids) {
								foreach ($order_ids as $order_id) {
									$order = wc_get_order($order_id);
									$order_items = $order->get_items();
									//iterate through an order's items
									foreach ($order_items as $item) {
										//if one item has the product id, add it to the array and exit the loop
										if ($item->get_product_id() === $product_id) {
												$cat_data['sold'] += $item->get_total() * $item->get_quantity();
												// break;
											}
										}
								}
							}
						}
					}

					$args = array(
						'post_type' => 'stock_order',
						'posts_per_page' => -1,
						'field' => 'ids'
					);

					$stock_orders = new WP_Query($args);

					if($stock_orders->have_posts()){
						while ($stock_orders->have_posts()): $stock_orders->the_post();

						$exchange = get_field('exchange_rate') ? get_field('exchange_rate') : 1;
						$exchange = floatval($exchange);
	
						if(have_rows('products')):
							while(have_rows('products')): the_row();	
								$stock_product_id = get_sub_field('product');
								
								if ($stock_product_id === $current_stock_product_id) {
									$cat_data['cost'] += $this->get_gbp_price(get_sub_field('price'), $exchange) * get_sub_field('quantity');
									break;
								}
							endwhile;
						endif;

						endwhile;
					}


				endwhile;
			}

			$cat_data_arr[] = $cat_data;
		}	

		wp_send_json($cat_data_arr, 200);
		exit;
	}

	private function get_gbp_price ($price, $exchange = 1) {
		return floatval($price) * floatval($exchange);
	}

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/stock-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/stock-admin.js', array( 'jquery' ), $this->version, false );
	}

	function add_dashboard_widget () {
		global $wp_meta_boxes;
		wp_add_dashboard_widget('custom_help_widget', 'CSV Import', array($this, 'custom_dashboard_help'));
		wp_add_dashboard_widget('import_everymac_scrape', 'Everymac Import', array($this, 'import_everymac_scrape'));
	}

	function custom_dashboard_help() {
		?>
		<form action="<?php echo get_admin_url() . 'admin-post.php'; ?>">
			Run the import hard coded
			<input type="hidden" name="action" value="csv_processing">
			<button class="button" type="submit">Import</button>
		</form>
		<?php
	}

	function csv_processing () {
		$months = array(
			// '2022/jul',
			// '2022/aug',
			// '2022/sep',
			// '2022/oct',
			// '2022/nov',
			// '2022/dec',
			// '2023/jan',
			'2023/feb',
		);
		
		$files = array(
			'wc'   => 'mrk-woo',
			// 'ebay' => 'lrk-ebay',
			// 'ebay' => 'mrk-ebay',
		);

		foreach($months as $month) {
			foreach($files as $platform => $file) {

				$url = __DIR__ . "/../csv/${month}/${file}.csv";

				try {
					$stock_import = new Stock_Import($url, $platform, true);
					$stock_import->import();
				} catch(Exception $e) {
					error_log("ERROR: " . $e->getMessage());
				}
			}
		}

		wp_safe_redirect(get_admin_url());
	}

	function import_everymac_scrape() {
		?>
		<form action="<?php echo get_admin_url() . 'admin-post.php'; ?>">
			Import Everymac scrape JSON
			<input type="hidden" name="action" value="everymac_processing">
			<button class="button" type="submit">Import</button>
		</form>
		<?php
	}

	function everymac_processing () {

		$filenames = [
			'macbook',
			'macbookair',
			'macbookpro',
		];

		foreach($filenames as $file) {
			$url = __DIR__ . "/../everymac/${file}.json";
			$data = file_get_contents($url);

			if($data) {
				$json = json_decode($data);
				foreach($json as $model_number => $model_data) {
					// MODEL NO.
					$post_id = wp_insert_post(array(
						'post_status' => 'publish',
						'post_type' => 'models',
						'post_title' => $model_number,
					));

					update_post_meta($post_id, 'model_data', $model_data);
				}
			}
		}

		wp_safe_redirect(get_admin_url());
	}

	function op_register_menu_meta_box() {
		global $post;
		
		$model_meta = get_post_meta($post->ID, 'model_data', true);

		foreach($model_meta as $data) {
			add_meta_box(
				strtolower($data->title),
				esc_html__($data->title, 'text-domain' ),
				array($this, 'op_render_menu_meta_box'),
				'models',
				'advanced',
				'default', 
				array( $data, $a ),
			);
		}
	}

	function op_render_menu_meta_box($post, $data) {
		$model = $data['args'][0];
		
		foreach($model as $key => $value) {
			echo "<p>{$key} - ${value}</p>";
		}
	}

	function ebay_description_meta_box () {
		add_meta_box(
			'ebay_description',
			esc_html__('eBay Description', 'text-domain' ),
			array($this, 'ebay_descripion'),
			'stock_product',
			'advanced',
			'default', 
			// array( $model_meta ),
		);
	}

	function ebay_descripion ($post, $data) {
		$model_ids = get_field('compatible_models', $post->ID);
		// "intro":"January 10, 2006*",
		// "disc":"February 14, 2006",
		// "order":"MA090LL",
		// "model":"A1150 (EMC 2101)",
		// "family":"MacBook Pro",
		// "id":"MacBookPro1,1",
		// "ram":"512 MB",
		// "vram":"128 MB",
		// "storage":"80 GB HDD",
		// "optical":"4X SL \"SuperDrive\"",
		// "title":"MacBook Pro \"Core Duo\" 1.67 15\""
		?>
		<!-- OUTPUT START -->
		<?php ob_start();?>
		
		<h2 style="font-size:20px;">Description</h2>

		<?php echo the_content(); ?>

		<h2 style="font-size:20px;">Compatability</h2>

		<p style="font-size:16px;">This part is compatible with the following models. Please check your device model is compatible before purchasing. If you are unsure please get in touch and we will be more than happy to help.</p>

		<table style="border:1px solid #ededed;text-align:left;border-collapse: collapse;width: 100%;max-width:800px;">

			<tr style="background:#186587;color:#ffffff;">
				<th style="padding:5px 10px;">Model</th>
				<th style="padding:5px 10px;">Model No.</th>
				<th style="padding:5px 10px;">ID</th>
			</tr>
				
			<?php 
			$i = 0;
			foreach($model_ids as $index => $id): 
				$data = get_post_meta($id, 'model_data', true); 
				$added = array();

				foreach($data as $index => $item):
					if(in_array($item->family, $added)) {
						continue;
					}

					array_push($added, $item->family);

				?>
				
				<tr <?php echo $i % 2 ? 'style="background:#ededed"' : ''; ?>>
					<td style="padding:5px 10px;"><?php echo str_replace('*', '', $item->family); ?></td>
					<td style="padding:5px 10px;"><?php echo str_replace('*', '', str_replace('-', '', $item->model)); ?></td>
					<td style="padding:5px 10px;"><?php echo $item->id; ?></td>
				</tr>

					<?php 
					$i++;
			
				endforeach;

			endforeach;
			?>
		</table>

		<h2 style="font-size:20px;">Shipping</h2>

		<h3 style="font-size:18px;">UK Shipping</h3>

		<table style="border:1px solid #ededed;text-align:left;border-collapse: collapse;width: 100%;max-width:800px;">

			<tr style="background:#186587;color:#ffffff;">
				<th style="padding:5px 10px;">Service</th>
				<th style="padding:5px 10px;">Cost</th>
			</tr>
				
			<?php 
			$shipping_method = array(
				array(
					'name' => 'Royal Mail 1st Class',
					'price' => 'FREE'
				),
				array(
					'name' => 'Evri Next Day',
					'price' => '£6.99'
				),
				array(
					'name' => 'Royal Mail Special Delivery 1pm',
					'price' => '£11.99'
				)
			);

			foreach($shipping_method as $index => $method): ?>
				<tr <?php echo $index % 2 ? 'style="background:#ededed"' : ''; ?>>
					<td style="padding:5px 10px;"><?php echo $method['name']; ?></td>
					<td style="padding:5px 10px;"><?php echo $method['price']; ?></td>
				</tr>
			<?php endforeach; ?>

		</table>

		<h3 style="font-size:18px;">International Shipping</h3>

		<table style="border:1px solid #ededed;text-align:left;border-collapse: collapse;width: 100%;max-width:800px;">

			<tr style="background:#186587;color:#ffffff;">
				<th style="padding:5px 10px;">Service</th>
				<th style="padding:5px 10px;">Cost</th>
			</tr>
				
			<?php 
			$shipping_method = array(
				array(
					'name' => 'Royal Mail International Standard (non-tracked)',
					'price' => '£6.99'
				),
				array(
					'name' => 'Royal Mail International Tracked',
					'price' => '£11.99'
				)
			);

			foreach($shipping_method as $index => $method): ?>
				<tr <?php echo $index % 2 ? 'style="background:#ededed"' : ''; ?>>
					<td style="padding:5px 10px;"><?php echo $method['name']; ?></td>
					<td style="padding:5px 10px;"><?php echo $method['price']; ?></td>
				</tr>
			<?php endforeach; ?>

		</table>


		<h3 style="font-size:18px;">About Laptop Replacement Keys</h3>

		<p style="font-size:16px;">We are a UK based company specialising in MacBook replacement keys, keyboards and components. All orders are shipped from the UK within 1 working day.</p>

		<?php $output = ob_get_contents();
		ob_end_clean();?>

		<!-- OUTPUT START -->
		<textarea name="" id="" width="100%" cols="30" rows="10" ><?php echo $output; ?></textarea>

		<h3>Preview</h3>
		<hr>
		<div><?php echo $output; ?></div>
		<?php
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
