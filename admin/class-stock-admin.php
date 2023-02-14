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
	}

	function product_sales_history (WP_REST_Request $request) {

		$params = $request->get_url_params('id');
		$id = intval($params['id']);

		$timestamp_start = '2022-7-01';
		$timestamp_end   = '2022-10-01';

		$orders = wc_get_orders(array(
			'posts_per_page' => -1,
			'date_created' => strtotime($timestamp_start ) .'...'. strtotime($timestamp_end)
		));

		$data = array();





		// TODO: This is a specific products history - we want key group history e.g. A2141 history of right arrow sales.




		foreach($orders as $order) {
			$date = $order->get_date_created();
			$timestamp = strtotime($date);

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

					$data[$timestamp]['count'] +=  $item->get_quantity();
				}
			}
		}

		$sortedObject = array();
		asort($data);

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
			'2023/jan',
		);
		
		$files = array(
			// 'wc'   => 'wc-mrk',
			'ebay' => 'ebay-lrk',
			// 'ebay' => 'ebay-mrk',
		);

		foreach($months as $month) {
			error_log("PROCESSING: " . $month);
			foreach($files as $platform => $file) {

				$url = __DIR__ . "/../csv/${month}/${file}.csv";
				error_log("PROCESSING: " . $url);

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
}
