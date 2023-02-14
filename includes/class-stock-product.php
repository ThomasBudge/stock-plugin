<?php

class Stock_Product {
    function __construct () {
        
    }

    /**
     * Insert product 
     */
    static function insert_product ($title, $price, $item_number, $meta = array()) {
        $title = preg_replace('/\[.*\]/', '', $title); // Replace from one [ to the last ]

		$product_slug = sanitize_title($title);

		$product = new WC_Product_Simple();

		$product->set_name( $title );

		$product->set_slug( $product_slug );

		$product->set_regular_price( $price );

        foreach ($meta as $key => $value) {
            $product->update_meta_data($key, $value);
        }

		return $product->save();
    }
}