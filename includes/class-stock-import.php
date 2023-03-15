<?php

class Stock_Import {
    private $channel;

    private $file_url;

    private $contains_header;

    function __construct ($file_url, $channel, $contains_header = true) {
        require_once(__DIR__ . '/class-stock-csv.php');
        require_once(__DIR__ . '/class-stock-product.php');

        $this->channel = $channel;
        $this->item_id_meta_key = $this->channel === 'ebay' ? 'ebay_item_number' : 'wc_item_number';
        $this->order_id_meta_key = $this->channel === 'ebay' ? 'ebay_order_number' : 'wc_order_number';
        $this->product_group_meta_key = 'product_group';
        $this->file_url = $file_url;
        $this->contains_header = $contains_header;
        $this->keys = array(
            'ebay' => array(
                'order_number' => 0,
                'billing_name' => 3,
                'billing_address_line_1' => 6,
                'billing_address_line_2' => 7,
                'billing_city' => 8,
                'billing_state' => 9,
                'billing_postcode' => 10,
                'billing_country' => 11,
                'shipping_name' => 14,
                'shipping_address_line_1' => 16,
                'shipping_address_line_2' => 17,
                'shipping_city' => 18,
                'shipping_state' => 19,
                'shipping_postcode' => 20,
                'shipping_country' => 21,
                'item_number' => 22,
                'title' => 23, // Escape \
                'quantity' => 26,
                'price' => 27,
                'order_date' => 49,
                'transaction_id' => 61, // Header variation orders don't have this \
                'variation' => 62, // Escape \
                'shipping_total' => 28,
                'order_total' => 46,
                'ebay_collected_tax' => 39,
                'ebay_collected_tax_type' => 34,
            ),
            'wc' => array(
                'order_number' => 0,
                'billing_first_name' => 4,
                'billing_last_name' => 5,
                'billing_address_line_1' => 7,
                'billing_address_line_2' => 7,
                'billing_city' => 8,
                'billing_county' => 9,
                'billing_state' => 9,
                'billing_postcode' => 10,
                'billing_country' => 11,
                'shipping_first_name' => 14,
                'shipping_last_name' => 15,
                'shipping_address_line_1' => 16,
                'shipping_address_line_2' => 16,
                'shipping_city' => 17,
                'shipping_county' => 18,
                'shipping_state' => 18,
                'shipping_postcode' => 19,
                'shipping_country' => 20,
                'item_number' => 34,
                'quantity' => 32,
                'title' => 31, // Escape \
                'price' => 33,
                'order_date' => 2,
                'variation_id' => 35,
                'variation' => 36, // Escape \
                'shipping_total' => 25
            )
        );
    }

    /**
     * Handle importing products and then orders.
     */
    function import () {
        $csv_rows = Stock_Csv::get_data($this->file_url, $this->contains_header);
        
        $orders = array();

        // Import all products
        $current = 0;
        $total = count($csv_rows);
        foreach ($csv_rows as $index => $row) {
            $item_number = $this->get_column_data($row, 'item_number');
            $product_exists = $this->product_exists($item_number);
            $product_type = $this->get_product_type($row);

            $title = $this->get_column_data($row, 'title');

            error_log($title . ' -- ' . $product_type . ' -- ' . $product_exists);

            if ($product_exists && $product_type === 'variation') {
                $this->import_variation_product($row, $product_exists);
            } else if(!$product_exists && $product_type === 'simple') {
                $title = $this->get_column_data($row, 'title');

                $price = $this->get_column_data($row, 'price');
                $key_group = $this->get_key_type($title);

                $meta = array($this->item_id_meta_key => $item_number);

                if ($key_group) $meta[$this->product_group_meta_key] = $key_group;

                Stock_Product::insert_product($title, $price, $item_number, $meta);
            } else if (!$product_exists && $product_type === 'variation') {
                $this->import_variation_product($row);
            }

            $order_num = $this->get_column_data($row, 'order_number');

            if ($order_num) $orders[$order_num][] = $row;

            error_log('Product: ' . $current . '/' . $total . ' - Product No.: ' . $item_number);
            $current++;
        }

        // Import all orders
        $current = 0;
        $total = count($orders);

        foreach($orders as $order_number => $items) {
            if ($this->order_exists($order_number)) continue;

            $this->import_order($items, $order_number);
            $current++;
            error_log('Order: ' . $current . '/' . $total . ' - Order No.: ' . $order_number);
        }
    }

    /**
     * Helper function for getting data by key from csv.
     */
    function get_column_data($row, $key) {
        if (isset($this->keys[$this->channel]) && isset($row[$this->keys[$this->channel][$key]])) {

            $value = $row[$this->keys[$this->channel][$key]];

            if ($key === 'variation') {
                $value = str_replace("\\", "Backslash", $value);
                // error_log( 'Variation: ' . $value);
            }

            return $value;
        }

        return false;
    }

    function get_variation_id_from_attributes ($varable_id, $attr) {
        $variation = new WC_Product_Variable($varable_id);

        $new_attribute_arr = [];

        // foreach($attr as $key => $value) {
        //     $new_attribute_arr['attribute_' . $key] = $value;
        // }

        // foreach($variation->get_available_variations() as $v) {
        //     $attributes = $v['attributes'];
        //     $found = true;

        //     foreach($new_attribute_arr as $key => $value) {
        //         $key = str_replace(' ', '-', $key);
        //         if(!isset($attributes[$key]) && $attributes[$key] !== $value)  {
        //             $found = false;
        //             break;
        //         }
        //     }

        //     if ($found === true) {
        //         return $v['variation_id'];
        //     }
        // }





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

    function import_order($order_items, $order_number) {
        $row = $order_items[0];

        $order = wc_create_order();

        // BILLING
        if ($this->channel === 'ebay') {
            $billing_name = $this->get_column_data($row, 'billing_name');
            $billing_name_arr = explode(' ', $billing_name);
        } else if($this->channel === 'wc') {
            $billing_name_arr = [$this->get_column_data($row, 'billing_first_name'), $this->get_column_data($row, 'billing_last_name')];
        }

        $billing_address = array(
            'first_name' => $billing_name_arr[0],
            'last_name' => $billing_name_arr[count($billing_name_arr) - 1],
            'address_line_1' => $this->get_column_data($row, 'billing_address_line_1'),
            'address_line_2' => $this->get_column_data($row, 'billing_address_line_2'),
            'city' => $this->get_column_data($row, 'billing_city'),
            'state' => $this->get_column_data($row, 'billing_state'),
            'postcode' => $this->get_column_data($row, 'billing_postcode'),
            'county' => $this->get_column_data($row, 'billing_country'),
        );

        if ($this->channel === 'ebay') {
            $shipping_name = $this->get_column_data($row, 'shipping_name');
            $shipping_name_arr = explode(' ', $billing_name);
        } else if($this->channel === 'wc') {
            $shipping_name_arr = [$this->get_column_data($row, 'shipping_first_name'), $this->get_column_data($row, 'shipping_last_name')];
        }

        $shipping_address = array(
            'first_name' => $shipping_name_arr[0],
            'last_name' => $shipping_name_arr[count($shipping_name_arr) - 1],
            'address_line_1' => $this->get_column_data($row, 'shipping_address_line_1'),
            'address_line_2' => $this->get_column_data($row, 'shipping_address_line_2'),
            'city' => $this->get_column_data($row, 'shipping_city'),
            'state' => $this->get_column_data($row, 'shipping_state'),
            'postcode' => $this->get_column_data($row, 'shipping_postcode'),
            'country' => $this->get_column_data($row, 'shipping_country'),
        );

        $order->set_address($billing_address, 'billing' );
        $order->set_address($shipping_address, 'shipping' );

        for($i = 0; $i < count($order_items); $i++) {
            $item_row = $order_items[$i];
            if ($this->channel === 'ebay' && !$this->get_column_data($item_row, 'transaction_id')) continue; // Ignore the first row as this is the order total for variation orders containing more thsan one item

            $item_id = $this->get_column_data($item_row, 'item_number');
            $item_variation = $this->get_column_data($item_row, 'variation');
            $product_id = $this->product_exists($item_id);
            $product = wc_get_product($product_id);

            $quantity = (int) $this->get_column_data($item_row, 'quantity');
            $price = $this->get_column_data($item_row, 'price');
            $price = str_replace('£', '', $price);
            $price = str_replace('US $', '', $price);
            $price = (float) str_replace('$', '', $price);

            $total = $price * $quantity;

            if($product) {

                if (($this->get_product_type($item_row) === 'simple' && $this->channel === 'wc') || ($this->get_product_type($item_row) === 'simple' && $this->channel === 'ebay' && $this->get_column_data($item_row, 'transaction_id'))) {
                    $order->add_product($product, $quantity, array('total' => $total));
                } else {
                    $ebay_variation_data = $this->get_column_data($item_row, 'variation');

                    $attribute_data = $this->get_variation_data($ebay_variation_data);

                    if($attribute_data) {
                        $variation_attributes = [];

                        foreach($attribute_data as $key => $value) {
                            $variation_attributes[strtolower($key)] = trim($value[0]);
                        }

                        $variation_id = $this->get_variation_id_from_attributes($product_id, $variation_attributes);

                        if ($variation_id) {
                            $variation = new WC_Product_Variation($variation_id);

                            $order->add_product($variation, $quantity, array('total' => $total, 'variation' => $variation_attributes));
                        }
                    }
                } 
            }
        }

        if($this->channel === 'ebay' && $this->get_column_data($row, 'ebay_collected_tax') && $this->get_column_data($row, 'ebay_collected_tax_type')) {
            $tax_amount = $this->get_column_data($row, 'ebay_collected_tax');
            $tax_amount = str_replace('£', '', $tax_amount);
            $tax_amount = str_replace('US $', '', $tax_amount);
            $tax_amount = (float) str_replace('$', '', $tax_amount);
            $tax_type = $this->get_column_data($row, 'ebay_collected_tax_type');
            

            // Get a new instance of the WC_Order_Item_Fee Object
            $item_fee = new WC_Order_Item_Fee();

            $item_fee->set_name( $tax_type ); // Generic fee name
            $item_fee->set_amount( $tax_amount ); // Fee amount
            $item_fee->set_tax_class( '' ); // default for ''
            $item_fee->set_tax_status( 'none' ); // or 'none'
            $item_fee->set_total( $tax_amount ); // Fee amount

            // Calculating Fee taxes
            $item_fee->calculate_taxes();

            // Add Fee item to the order
            $order->add_item( $item_fee );
        }


        $shipping_total = $this->get_column_data($row, 'shipping_total');
        $shipping_total = str_replace('£', '', $shipping_total);     
        $shipping_total = str_replace('US $', '', $shipping_total);     
        $shipping_total = str_replace('$', '', $shipping_total);       
        $shipping_item = new WC_Order_Item_Shipping();
        $shipping_item->set_method_title( "Flat rate" );
        $shipping_item->set_method_id( "flat_rate:14" ); // set an existing Shipping method rate ID
        $shipping_item->set_total( floatval($shipping_total) ); // (optional)
        $order->add_item( $shipping_item );

        $order->calculate_totals();
        // $order->calculate_shipping();
        $date = $this->get_column_data($row, 'order_date');
        $order->set_date_created($date);

        // if($this->channel === 'ebay') {
        //     $order_total = $this->get_column_data($row, 'order_total');
        //     $order_total = str_replace('£', '', $order_total);
        //     $order_total = str_replace('US $', '', $order_total);
        //     $order_total = (float) str_replace('$', '', $order_total);

        //     error_log("ORDER TOTAL: " . $order_total);
        //     error_log($order_total === 0);

        //     if(!$order_total) {
        //         error_log('ORDER TOTAL REFUNDED');
        //         $order->set_status('refunded');
        //     } else {
        //         error_log('ORDER TOTAL COMPLETED');
        //         $order->set_status('completed');
        //     }
        // } else {
            $order->set_status('completed');
        // }

        $order->update_meta_data($this->order_id_meta_key, $order_number);

        $order->update_meta_data('items_data', $order_items);

        return $order->save();
    }

    function order_exists ($order_number) {

		$args = array(
            'meta_key' => $this->order_id_meta_key,
            'meta_value' => $order_number,
            'meta_compare' => '=',
            'return' => 'ids',
		);

        $wp_query = wc_get_orders($args);

        return count($wp_query);
    }

	function product_exists ($item_number) {
		$args = array(
			'post_type' => 'product',
			'meta_query' => array (
				array(
					'key' => $this->item_id_meta_key,
					'value' => $item_number,
					'compare' => '=',
				),
			),
		);

		$wp_query = new WP_Query($args);

		if ($wp_query->have_posts()) {
			return $wp_query->posts[0]->ID;
		}

		return false;
	}

	function get_product_type ($row) {
        $product_type = 'simple';
    	$variation = $this->get_column_data($row, 'variation');

        if($this->channel === 'wc') {
            $variation_id = $this->get_column_data($row, 'variation_id');
            if($variation_id !== '0') {
                $product_type = 'variation';
            }
        } else {
            if (strlen($this->get_column_data($row, 'variation'))) $product_type = 'variation';
        }

        return $product_type;
	}

    function import_variation_product ($row, $product_id = false) {
        // error_log('import_variation_product');
        $ebay_variation_data = $this->get_column_data($row, 'variation');

        $attributes = $this->get_variation_data($ebay_variation_data);

        $price = $this->get_column_data($row, 'price');

        if(!$product_id && $attributes) {
            $title = $this->get_column_data($row, 'title');
            $item_number = $this->get_column_data($row, 'item_number');

            if ($this->channel === 'ebay') {
                $title = preg_replace('/\[.*\]/', '', $title); // Replace from one [ to the last ]
            } else if ($this->channel === 'wc' && strpos($title, '-') !== false) {
                $title = preg_split('~-(?=[^-]*$)~', $title)[0];
            }

            $product_data = array(
                'title'         => $title,
                'content'       => '',
                'excerpt'       => '',
                'regular_price' => $price,
                'sale_price'    => '', 
                'stock'         => '10',
                'attributes'    => $attributes,
            );

            $parent_product_id = $this->create_variation_product($product_data, $item_number);
        } else {
            $parent_product_id = $product_id;
        }

        $this->create_variations($parent_product_id, $attributes, $price);
    }

    function get_variation_data ($variation_string) {
        if(!$variation_string) {
            $variation_string = 'Key: 0'; // bug with 0
        }

        if ($variation_string[0] === '[' && $variation_string[strlen($variation_string) - 1] === ']') {
            $variation_string = substr($variation_string, 1, -1);
        }

        $preg_match = str_replace('-:', ':', $variation_string);

        // Some attributes have more than one comma such as Arrow-:Full Arrow Set (Left, Right, Up, Down)
        if(substr_count($preg_match, ',') === 1 && !substr_count($preg_match, ', ')) {
            $string_arr = explode(',', $preg_match);
        } else {
            $string_arr = array($preg_match);
        }

        $attributes = [];

        foreach($string_arr as $attribute) {
            // error_log(print_r($attribute, true));
            $explode = explode(':', $attribute);

            if (!isset($explode[1])) {
                $explode[1] = '0'; // Woocommerce treats variation 0 as empty for some reason
            }

            $key = $explode[0];
            $key = str_replace(' ', '', $key);
            $value = $explode[1];
            $attributes[$key] = array($value);
        }

        return $attributes;
    }

    function create_variation_product( $data, $item_number ){
        $postname = sanitize_title( $data['title'] );
        $author = empty( $data['author'] ) ? '1' : $data['author'];

        $post_data = array(
            'post_author'   => $author,
            'post_name'     => $postname,
            'post_title'    => $data['title'],
            'post_content'  => $data['content'],
            'post_excerpt'  => $data['excerpt'],
            'post_status'   => 'publish',
            'ping_status'   => 'closed',
            'post_type'     => 'product',
            'guid'          => home_url( '/product/'.$postname.'/' ),
        );

        // Creating the product (post data)
        $product_id = wp_insert_post( $post_data );

        // Get an instance of the WC_Product_Variable object and save it
        $product = new WC_Product_Variable( $product_id );
        $product->update_meta_data($this->item_id_meta_key, $item_number);

        $key_group = $this->get_key_type($product->get_name());

        if ($key_group) {
            $product->update_meta_data($this->product_group_meta_key, $key_group);
        }

        return $product->save(); // Save the data
    }

    function create_variations ($parent_product_id, $attribute_data, $price) {
        // error_log("parent_product_id: {$parent_product_id}");
        $variation_attributes = array();

        // error_log('attribute_data');
        // error_log(print_r($attribute_data, true));

        foreach($attribute_data as $key => $value) {
            $variation_attributes[strtolower($key)] = trim($value[0]);
        }

        // error_log('variation_attributes');
        // error_log(print_r($variation_attributes, true));

        // Running...
        $variable_product = wc_get_product($parent_product_id);

        if($variable_product) {
            // Array of new values that don't already exist
            $existing_attribute_data = $variable_product->get_attributes();
            $existing_attribute_keys = array_keys($existing_attribute_data);

            // error_log('existing_attribute_keys');
            // error_log(print_r($existing_attribute_keys, true));

            // error_log('existing_attribute_data');
            // error_log(print_r($existing_attribute_data, true));

            $attribute_count = count($attribute_data);
            $attribute_exists_count = 0;

            foreach($attribute_data as $attr_key => $attr_values) {
                $lowercasekey = str_replace('-', '', strtolower($attr_key));
                $lowercasekey = str_replace(' ', '-', strtolower($attr_key));

                // error_log('lowercasekey');
                // error_log(print_r($lowercasekey, true));
                
                if (key_exists($lowercasekey, $existing_attribute_data)) {
                    $existing_options = array_values($existing_attribute_data[$lowercasekey]->get_options());
                    // error_log('existing_options');
                    // error_log(print_r($existing_options, true));
                    if (in_array($attr_values[0], $existing_options)) {
                        // if all values already exist then do not create variation
                        $attribute_exists_count++;
                        continue;
                    }

                    $attr_values = array_unique(array_merge($existing_options, $attr_values));
                }
                // error_log('existing_options');
                // error_log(print_r($attr_values, true));
                $attribute = new WC_Product_Attribute();
                
                // error_log('existing_attribute_key');
                // error_log(print_r($attr_key, true));
                // error_log('existing_attribute_values');
                // error_log(print_r($attr_values, true));

                $attribute->set_id( 0 );
                $attribute->set_name( $attr_key ); 
                $attribute->set_options( $attr_values );
                $attribute->set_position( 0 );
                $attribute->set_visible( 1 );
                $attribute->set_variation( 1 );

                $existing_attribute_data[$attr_key] = $attribute;
            }

            if($attribute_count === $attribute_exists_count) {
                return;
            }

            // Set attributes on product
            $variable_product->set_attributes($existing_attribute_data);
            $parent_product_id = $variable_product->save();

            // Create variation
            $variation = new WC_Product_Variation();
            $variation->set_regular_price($price);
            $variation->set_parent_id($parent_product_id);

            // If this variation alreadu ecosts, do not create and set!!!
            $variation->set_attributes($variation_attributes);

            $variation->save();
        }
    }

    public function get_key_type ($title) {

        preg_match_all("([A[0-9]{5})", $title, $model_numbers);

        $keycap_groups = array(
            'A1706' => 'A1706-A1707-A1708',
            'A1707' => 'A1706-A1707-A1708',
            'A1708' => 'A1706-A1707-A1708',

            'A1989' => 'A1989-A1990-A2159',
            'A1990' => 'A1989-A1990-A2159',
            'A2159' => 'A1989-A1990-A2159',

            'A2141' => 'A2485-A2442-A2338-A2289-A2251-A2141-A2337-A2179',
            'A2289' => 'A2485-A2442-A2338-A2289-A2251-A2141-A2337-A2179',
            'A2251' => 'A2485-A2442-A2338-A2289-A2251-A2141-A2337-A2179',
            'A2338' => 'A2485-A2442-A2338-A2289-A2251-A2141-A2337-A2179',
            'A2485' => 'A2485-A2442-A2338-A2289-A2251-A2141-A2337-A2179',
            'A2442' => 'A2485-A2442-A2338-A2289-A2251-A2141-A2337-A2179',
            'A2337' => 'A2485-A2442-A2338-A2289-A2251-A2141-A2337-A2179',
            'A2179' => 'A2485-A2442-A2338-A2289-A2251-A2141-A2337-A2179',
            'A2681' => 'A2485-A2442-A2338-A2289-A2251-A2141-A2337-A2179',
        );

        if($model_numbers) {
            foreach($model_numbers[0] as $number) {
                if(isset($keycap_groups[$number])) {
                    return $keycap_groups[$number];
                    break;
                }
            }
        }

        return false;
    }
}

// Add meta box
add_action( 'add_meta_boxes', 'tcg_tracking_box' );
function tcg_tracking_box() {
    add_meta_box(
        'tcg-tracking-modal',
        'Order Items Meta',
        'tcg_meta_box_callback',
        'shop_order',
        'normal',
        'core'
    );
}

// Callback
function tcg_meta_box_callback( $post )
{

    $order = wc_get_order($post->ID);

    echo $post->ID;
    ?>
    <pre>
        <?php print_r($order->get_meta('items_data')); ?>
    </pre>
    <?php
}

