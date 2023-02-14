<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              thomasbudge.com
 * @since             1.0.0
 * @package           Stock
 *
 * @wordpress-plugin
 * Plugin Name:       Stock
 * Plugin URI:        stock.test
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            Thomas
 * Author URI:        thomasbudge.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       stock
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'STOCK_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-stock-activator.php
 */
function activate_stock() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-stock-activator.php';
	Stock_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-stock-deactivator.php
 */
function deactivate_stock() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-stock-deactivator.php';
	Stock_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_stock' );
register_deactivation_hook( __FILE__, 'deactivate_stock' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-stock.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_stock() {
	$plugin = new Stock();
	$plugin->run();
}
run_stock();



add_action('__init', function () {
	$items = array(
		'A1706-A1707-A1708' => array( 'E', 'Command Left', 'Spacebar', 'T', 'S', 'N', 'M' ),
		'A1989-A1990-A2159' => array( 'Spacebar', 'Command Left', 'Spacebar', 'T', 'S', 'N', 'M' ),
		'A2485-A2442-A2338-A2289-A2251-A2141-A2337-A2179' => array( 'Right Arrow', 'Left Arrow', 'Up Arrow', 'Down Arrow', 'Command Left', 'Spacebar' ),
	);

	foreach($items as $set => $keys){
		$args = array(
			'post_type' => 'product',
			'meta_key' => 'product_group',
			'meta_value' => $set,
			'posts_per_page' => -1,
			'fields' => 'ids',
		);
		
		?>
		<h4><?php echo $set; ?></h4>
		<?php

		$query = new WP_Query($args);
		
		foreach($keys as  $key) {
			$variation_ids = array();

			if($query->have_posts()) {
				while($query->have_posts()): $query->the_post();
					$id = get_variation_id_from_attributes(get_the_ID(), array('key' => $key));

					if($id) {
						$variation_ids[] = $id;
					}
				endwhile;
			}

			$total_count = 0;

			echo "<table>";

			foreach($variation_ids as $id) {
				$count = get_orders_ids_by_product_id($id);
				$total_count += $count;
			}

			?>
			<tr>
				<td><a href="<?php the_permalink($id); ?>"><?php echo $key; ?></a></td>
				<td><?php echo $total_count; ?></td>
			</tr>
			<?php
		}


		echo "</table>";
	}

	// die;
});

function get_variation_id_from_attributes ($varable_id, $attr) {
	$variation = new WC_Product_Variable($varable_id);

	$new_attribute_arr = [];

	foreach($attr as $key => $value) {
		$new_attribute_arr['attribute_' . $key] = $value;
	}

	foreach($variation->get_available_variations() as $v) {
		$attributes = $v['attributes'];

		foreach($new_attribute_arr as $key => $value) {
			$key = str_replace(' ', '-', $key);
			if(isset($attributes[$key]) && $attributes[$key] == $value)  {
				return $v['variation_id'];
			}
		}
	}

	return false;
}

/**
 * Get All orders IDs for a given product ID.
 *
 * @param  integer  $product_id (required)
 * @param  array    $order_status (optional) Default is 'wc-completed'
 *
 * @return array
 */
function get_orders_ids_by_product_id( $variation_id ){
    global $wpdb;

	return $wpdb->get_var("
		SELECT count(p.ID) 
			FROM {$wpdb->prefix}woocommerce_order_items AS woi
			JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS woim ON woi.order_item_id = woim.order_item_id 
			JOIN {$wpdb->prefix}posts AS p ON woi.order_id = p.ID
				WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-completed'
				AND woim.meta_key LIKE '_variation_id' 
				AND woim.meta_value = $variation_id
				AND CAST(p.post_date AS DATE) BETWEEN '2022-07-01' AND '2022-12-31'
		");

	// AND m.meta_key = '_completed_date' AND CAST(m.meta_value AS DATE) BETWEEN '2022-09-01' AND '2022-12-31';
}



// SELECT * FROM wp_postmeta AS m
// JOIN wp_posts AS p ON m.post_id = p.ID 
// WHERE m.meta_key = '_completed_date' AND 
// CAST(m.meta_value AS DATE) > CAST('2022-09-01' as DATE) AND CAST(m.meta_value AS DATE) < CAST('2022-12-31' as DATE);



// SELECT * FROM wp_postmeta AS m
// JOIN wp_posts AS p ON m.post_id = p.ID 
// WHERE m.meta_key = '_completed_date' AND 
// CAST(m.meta_value AS DATE) BETWEEN '2022-09-01' AND '2022-12-31';


// SELECT * FROM wp_postmeta AS m
// JOIN wp_posts AS p ON m.post_id = p.ID 
// WHERE m.meta_key = '_completed_date' AND 
// CAST(m.meta_value AS DATE) BETWEEN CAST('2022-09-01' AS DATE) AND CAST('2022-12-31' AS DATE);
