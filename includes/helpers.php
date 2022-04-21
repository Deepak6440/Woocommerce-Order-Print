<?php
/**
 * Getting order details from order id
 * @access  public
 * @param order
*/
if (!function_exists('apifw_get_order_detail')) {
    function apifw_get_order_detail( $order_id ){
        if ( !$order_id )
            return false;

        // Get an instance of the WC_Order object
        $order = wc_get_order( $order_id );
        if ( $order === false )
            return false;

        // Get the decimal precession
        $dp = wc_get_price_decimals();
        if( !$dp ){
            $dp = 2;
        }

        // billing country, state and its formatted data
        $billing_state = $order->get_billing_state();
        $billing_country = $order->get_billing_country();
        $billing_formated_country = '';
        $billing_formated_state = '';
        // state
        if( ! empty( $billing_country ) && ! empty( $billing_state ) ){
            if( isset( WC()->countries->states[$billing_country][$billing_state] ) ){
                $billing_formated_state = WC()->countries->states[$billing_country][$billing_state];
            } else {
                $billing_formated_state = $billing_state;
            }
        }

        // formatted billing country
        if( ! empty( $billing_country ) ){
            if( isset( WC()->countries->countries[$billing_country] ) ){
                $billing_formated_country = WC()->countries->countries[$billing_country];
            } else {
                $billing_formated_country = $billing_country;
            }
        }

        // shipping country, state and its formatted data
        $shipping_state = $order->get_shipping_state();
        $shipping_country = $order->get_shipping_country();
        $shipping_formated_state = '';
        $shipping_formated_country = '';
        // state
        if( ! empty( $shipping_country ) && ! empty( $shipping_state ) ){
            if( isset( WC()->countries->states[$shipping_country][$shipping_state] ) ){
                $shipping_formated_state = WC()->countries->states[$shipping_country][$shipping_state];
            } else {
                $shipping_formated_state = $shipping_state;
            }
        }

        // formatted shipping country
        if( ! empty( $shipping_country ) ){
            if( isset( WC()->countries->countries[$shipping_country] ) ){
                $shipping_formated_country = WC()->countries->countries[$shipping_country];
            } else {
                $shipping_formated_country = $shipping_country;
            }
        }

        $order_data = array(
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'created_at' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'updated_at' => $order->get_date_modified()->date('Y-m-d H:i:s'),
            'completed_at' => !empty($order->get_date_completed()) ? $order->get_date_completed()->date('Y-m-d H:i:s') : '',
            'status' => $order->get_status(),
            'type' => $order->get_type(),
            'currency' => $order->get_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'total' => wc_format_decimal($order->get_total(), $dp),
            'subtotal' => wc_format_decimal($order->get_subtotal(), $dp),
            'total_items_quantity' => $order->get_item_count(),
            'total_tax' => wc_format_decimal($order->get_total_tax(), $dp),
            'total_shipping' => wc_format_decimal($order->get_total_shipping(), $dp),
            'cart_tax' => wc_format_decimal($order->get_cart_tax(), $dp),
            'shipping_tax' => wc_format_decimal($order->get_shipping_tax(), $dp),
            'total_discount' => wc_format_decimal($order->get_total_discount(), $dp),
            'discount_tax' =>  wc_format_decimal($order->get_discount_tax(), $dp),
            'shipping_method' => $order->get_shipping_method(),
            //'shipping_methods_array' => $order->get_shipping_methods(),
            //'shipping_methods_display' => $order->get_shipping_to_display(),
            'order_key' => $order->get_order_key(),
            'payment_details' => array(
                'method_id' => $order->get_payment_method(),
                'method_title' => $order->get_payment_method_title(),
                'paid_at' => !empty($order->get_date_paid()) ? $order->get_date_paid()->date('Y-m-d H:i:s') : '',
            ),
            'billing_address' => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $billing_state,
                'formated_state' => $billing_formated_state, //human readable formated state name
                'postcode' => $order->get_billing_postcode(),
                'country' => $billing_country,
                'formated_country' => $billing_formated_country, //human readable formated country name
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone()
            ),
            'shipping_address' => array(
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $shipping_state,
                'formated_state' => $shipping_formated_state, //human readable formated state name
                'postcode' => $order->get_shipping_postcode(),
                'country' => $shipping_country,
                'formated_country' => $shipping_formated_country, //human readable formated country name
                'phone' => $order->get_shipping_phone()
            ),
            'customer_note' => $order->get_customer_note(),
            'customer_ip' => $order->get_customer_ip_address(),
            'customer_user_agent' => $order->get_customer_user_agent(),
            'customer_id' => $order->get_user_id(),
            'view_order_url' => $order->get_view_order_url(),
            'items' => array(),
            'shipping_lines' => array(),
            'tax_lines' => array(),
            'fee_lines' => array(),
            'coupon_lines' => array(),
        );

        // order tax datas
        $order_tax_datas = array();
        $order_tax_infos = $order->get_items('tax');
        if( ! empty( $order_tax_infos ) ){
            foreach ( $order_tax_infos as $tax_item ) {
                $order_tax_datas[$tax_item->get_rate_id()]['label'] = $tax_item->get_label();
                $order_tax_datas[$tax_item->get_rate_id()]['percentage'] = $tax_item->get_rate_percent();
            }
        }

        //getting items in order
        $items = $order->get_items();
        foreach( $items as $item_id => $item ) {
            $product = $item->get_product();
            $product_id = null;
            $product_sku = null;
            $tax_percent = 0;
            $tax_label = __('N/A', 'woocommerce-order-print');
            $weight = __('N/A', 'woocommerce-order-print');
            $total_weight = __('N/A', 'woocommerce-order-print');
            // Check if the product exists.
            if (is_object($product)) {
                $product_id = $product->get_id();
                $product_sku = $product->get_sku();
                
                //geting tax percentage & name
                if( ( $product->is_taxable() || $product->is_shipping_taxable() ) && ( isset( $item['line_subtotal_tax'] ) && $item['line_subtotal_tax'] > 0 ) ){
                    $taxes = $item->get_taxes();
                    $item_tax_labels = array();
                    $item_tax_rates = array();

                    if( ! empty( $order_tax_datas ) && ! empty( $taxes ) && isset( $taxes['subtotal'] ) ){
                        foreach( $taxes['subtotal'] as $rate_id => $tax ){
                            $tax_data = $order_tax_datas[$rate_id];
                            if( ! empty( $tax_data ) ){
                                if( isset( $tax_data['label'] ) ){
                                    $item_tax_labels[] = $tax_data['label'];
                                }

                                if( isset( $tax_data['percentage'] ) ){
                                    $item_tax_rates[] = $tax_data['percentage'];
                                }
                            }
                        }

                        // converting to string
                        if( ! empty( $item_tax_labels ) ){
                            $tax_label = implode( ", ", $item_tax_labels );
                        }

                        if( ! empty( $item_tax_rates ) ){
                            $tax_percent = implode( "%, ", $item_tax_rates );
                        }
                    }
                }

                //product weight
                $pro_weight = $product->get_weight();
                if( $pro_weight ){
                    $weight = (float) $pro_weight;
                    $total_weight = floatval( $weight * $item['qty'] );
                }
            }

            //Weight Unit
            $weight_unit = get_option( 'woocommerce_weight_unit' );

            // meta data
            $meta_data = $item->get_formatted_meta_data( '_', true );
            $formated_meta = '';
            $c = count( $meta_data );
            $i = 0;
            if( $meta_data ){
                foreach( $meta_data as $m ){
                    $formated_meta .= $m->key.' : '.$m->value;
                    if( $i < ($c-1) ){
                        $formated_meta .= ', ';
                    }
                    $i++;
                }
            }

            // main
            $order_data['items'][] = array(
                'id' => $item_id,
                'subtotal' => wc_format_decimal($order->get_line_subtotal($item, false, false), $dp),
                'subtotal_tax' => wc_format_decimal($item['line_subtotal_tax'], $dp),
                'total' => wc_format_decimal($order->get_line_total($item, false, false), $dp),
                'total_tax' => wc_format_decimal($item['line_tax'], $dp),
                'price' => wc_format_decimal($order->get_item_subtotal($item, false, false), $dp),
                'price_after_discount' => wc_format_decimal($order->get_item_total($item, false, false), $dp),
                'quantity' => wc_stock_amount($item['qty']),
                'weight' => $weight,
                'total_weight' => $total_weight,
                'weight_unit' => $weight_unit,
                'tax_class' => (!empty($item['tax_class']) ) ? $item['tax_class'] : null,
                'tax_status' => $item->get_tax_status(),
                'tax_percent' => $tax_percent,
                'tax_label' => $tax_label,
                'tax_array' => $item->get_taxes(),
                'name' => $item['name'],
                'product_id' => (!empty($item->get_variation_id()) && ('product_variation' === $product->post_type )) ? $product->get_parent_id() : $product_id,
                'variation_id' => (!empty($item->get_variation_id()) && ('product_variation' === $product->post_type )) ? $product_id : 0,
                'product_url' => get_permalink($product_id),
                'product_thumbnail_url' => wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'thumbnail', TRUE)[0],
                'sku' => $product_sku,
                'meta' => wc_display_item_meta($item, ['echo' => false]),
                'formatted_meta' => $formated_meta
            );
        }

        //getting shipping
        foreach ($order->get_shipping_methods() as $shipping_item_id => $shipping_item) {
            $order_data['shipping_lines'][] = array(
                'id' => $shipping_item_id,
                'method_id' => $shipping_item['method_id'],
                'method_title' => $shipping_item['name'],
                'total' => wc_format_decimal($shipping_item['cost'], $dp),
            );
        }

        //getting taxes
        foreach ($order->get_tax_totals() as $tax_code => $tax) {
            $order_data['tax_lines'][] = array(
                'id' => $tax->id,
                'rate_id' => $tax->rate_id,
                'code' => $tax_code,
                'title' => $tax->label,
                'total' => wc_format_decimal($tax->amount, $dp),
                'compound' => (bool) $tax->is_compound,
            );
        }

        //getting fees
        foreach ($order->get_fees() as $fee_item_id => $fee_item) {
            $order_data['fee_lines'][] = array(
                'id' => $fee_item_id,
                'title' => $fee_item['name'],
                'tax_class' => (!empty($fee_item['tax_class']) ) ? $fee_item['tax_class'] : null,
                'total' => wc_format_decimal($order->get_line_total($fee_item), $dp),
                'total_tax' => wc_format_decimal($order->get_line_tax($fee_item), $dp),
            );
        }

        //getting coupons
        // foreach ($order->get_items('coupon') as $coupon_item_id => $coupon_item) {
        //     $order_data['coupon_lines'][] = array(
        //         'id' => $coupon_item_id,
        //         'code' => $coupon_item['name'],
        //         'amount' => wc_format_decimal($coupon_item['discount_amount'], $dp),
        //     );
        // }

        return $order_data;
    }
}