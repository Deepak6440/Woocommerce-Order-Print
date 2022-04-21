<?php

if (!defined('ABSPATH'))
    exit;

class APIFW_Api
{

    /**
     * @var    object
     * @access  private
     * @since    1.0.0
     */
    private static $_instance = null;

    /**
     * The version number.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_version;
    private $_active = false;

    public function __construct()
    {
        add_action('rest_api_init', function () {
            register_rest_route('apifw/v1', '/save_base_settings/', array(
                'methods' => 'POST',
                'callback' => array($this, 'save_base_settings'),
                'permission_callback' => array($this, 'get_permission')
            ));

            register_rest_route('apifw/v1', '/get_base_settings/', array(
                'methods' => 'POST',
                'callback' => array($this, 'get_base_settings'),
                'permission_callback' => array($this, 'get_permission')
            ));

            register_rest_route('apifw/v1', '/reset_invoice_template/', array(
                'methods' => 'POST',
                'callback' => array($this, 'reset_invoice_template'),
                'permission_callback' => array($this, 'get_permission')
            ));
        });
    }

    /**
     *
     * Ensures only one instance of APIFW is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @see WordPress_Plugin_Template()
     * @return Main APIFW instance
    */
    public static function instance($file = '', $version = '1.0.0')
    {
        if ( is_null(self::$_instance) ) {
            self::$_instance = new self($file, $version);
        }
        return self::$_instance;
    }

    /**
     * Permission Callback
     **/
    public function get_permission()
    {
        if (current_user_can('administrator') || current_user_can('manage_woocommerce')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), $this->_version);
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), $this->_version);
    }

    /**
     * Saving Plugin Basic Settings
     * @param $data
     * @return WP_REST_Response
     * @throws Exception
    */
    public function save_base_settings($data) {
        $request_body = $data->get_params();
        $settings = $request_body['settings'];
        $settings_type =  $settings['settings_type'];
        switch ($settings_type) {
            case 'general':
                $general_settings = array();
				$general_settings['company_name'] = $settings['company_name'];
				$general_settings['company_logo'] = $settings['company_logo'];
				$general_settings['gen_footer'] = $settings['gen_footer'];
				$general_settings['sender_name'] = $settings['sender_name'];
				$general_settings['sender_addr1'] = $settings['sender_addr1'];
				$general_settings['sender_addr2'] = $settings['sender_addr2'];
				$general_settings['sender_city'] = $settings['sender_city'];
				$general_settings['sender_country'] = $settings['sender_country'];
				$general_settings['sender_postal_code'] = $settings['sender_postal_code'];
                $general_settings['sender_number'] = $settings['sender_number'];
                $general_settings['sender_email'] = $settings['sender_email'];
                $general_settings['gen_vat'] =  $settings['gen_vat'];
                // $general_settings['gen_preview'] = $settings['gen_preview'];
                $general_settings['rtl_support'] = $settings['rtl_support'];
				$general_settings_serialize = maybe_serialize( $general_settings );
                if ( false === get_option(APIFW_TOKEN.'_general_settings') ){
					$r = add_option( APIFW_TOKEN.'_general_settings', $general_settings_serialize, '', 'yes' );
					if($r) {
						$response = 1;
					} else {
						$response = 0;
					}
                }  else {
					$r = update_option( APIFW_TOKEN.'_general_settings', $general_settings_serialize );
					if($r) {
						$response = 1;
					} else {
						$response = 0;
					}
                }
				break;
            case 'documents':
                // invoice settings
				$new_invoice_settings = $settings['invoice'];
                $current_invoice_settings = get_option(APIFW_TOKEN.'_invoice_settings');
				if ( false === $current_invoice_settings ){
                    $new_inv_set_serialize = serialize( $new_invoice_settings );
					$r1 = add_option( APIFW_TOKEN.'_invoice_settings', $new_inv_set_serialize, '', 'yes' );
				} else {
                    $current_inv_set_array = unserialize( $current_invoice_settings );
                    if( ( $new_invoice_settings['next_no'] > $current_inv_set_array['next_no'] ) || ( $new_invoice_settings['next_no'] == 1 ) ) {
                        $new_inv_set_serialize = serialize( $new_invoice_settings );
                    } else {
                        $new_invoice_settings['next_no'] = $current_inv_set_array['next_no'];
                        $new_inv_set_serialize = serialize( $new_invoice_settings );
                    }
					$r1 = update_option( APIFW_TOKEN.'_invoice_settings', $new_inv_set_serialize );
                }

                // other document settings
                $packing_slip_settings = serialize( $settings['packing_slip'] );
				$shipping_label_settings = serialize( $settings['shipping_label'] );
				$delivery_note_settings = serialize( $settings['delivery_note'] );
				$dispatch_label_settings = serialize( $settings['dispatch_label'] );

				if ( false === get_option(APIFW_TOKEN.'_packing_slip_settings') ){
					$r2 = add_option( APIFW_TOKEN.'_packing_slip_settings', $packing_slip_settings, '', 'yes' );
				} else {
					$r2 = update_option( APIFW_TOKEN.'_packing_slip_settings', $packing_slip_settings );
				}

				if ( false === get_option(APIFW_TOKEN.'_shipping_label_settings') ){
					$r3 = add_option( APIFW_TOKEN.'_shipping_label_settings', $shipping_label_settings, '', 'yes' );
				} else {
					$r3 = update_option( APIFW_TOKEN.'_shipping_label_settings', $shipping_label_settings );
				}

				if ( false === get_option(APIFW_TOKEN.'_delivery_note_settings') ){
					$r4 = add_option( APIFW_TOKEN.'_delivery_note_settings', $delivery_note_settings, '', 'yes' );
				} else {
					$r4 = update_option( APIFW_TOKEN.'_delivery_note_settings', $delivery_note_settings );
				}

				if ( false === get_option(APIFW_TOKEN.'_dispatch_label_settings') ){
					$r5 = add_option( APIFW_TOKEN.'_dispatch_label_settings', $dispatch_label_settings, '', 'yes' );
				} else {
					$r5 = update_option( APIFW_TOKEN.'_dispatch_label_settings', $dispatch_label_settings );
				}

				if($r1 || $r2 || $r3 || $r4 || $r5) {
					$response = 1;
				} else {
					$response = 0;
				}
                break;
            case 'invoice_template':
                $template_pid = $settings['template_id'];
                $templateSettings = $settings['settings'];
                if( $template_pid && $templateSettings ){
                    $inv_templt_post = array(
                        'ID' => $template_pid,
                        'post_title' => $templateSettings['tempName'],
                    );
                    // Update the post into the database
                    wp_update_post( $inv_templt_post );

                    // unset template name from template array
                    unset( $templateSettings['tempName'] );
                    // serialising & updating template
                    $templt_serialize = serialize($templateSettings);
                    // updating template settings
                    update_post_meta( $template_pid, APIFW_TOKEN.'_invoice_template', $templt_serialize );
                    $active_template_pid = get_option(APIFW_TOKEN.'_invoice_active_template_id');
                    //updating template status
                    if( $template_pid === $active_template_pid ) {
                        update_post_meta( $template_pid, APIFW_TOKEN.'_invoice_template_status', true );
                    } else {
                        update_post_meta( $template_pid, APIFW_TOKEN.'_invoice_template_status', true );
                        update_post_meta( $active_template_pid, APIFW_TOKEN.'_invoice_template_status', false );
                        // updating active template id
                        update_option( APIFW_TOKEN.'_invoice_active_template_id', $template_pid );
                    }
                    $response = 1;
                } else {
                    $response = 0;
                }
                break;
            default:
                break;
        }
        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Getting Plugin Basic Settings
     * @return WP_REST_Response
     * @throws Exception
    */
    public function get_base_settings($data) {
		$request_body = $data->get_params();
		$settings_type =  $request_body['settings_type'];
		switch ($settings_type) {
			case 'general':
				$result =  array();
				$settings = get_option( APIFW_TOKEN.'_general_settings' );
				if( $settings ) {
					$settings_array = unserialize($settings);
					$result['settings'] = json_encode($settings_array);
					$result['status'] = 1;
				} else {
					$result['settings'] = '';
					$result['status'] = 0;
				}
				break;
			case 'documents':
				$result =  array();
				$invoice_settings = get_option( APIFW_TOKEN.'_invoice_settings' );
				$packing_slip_settings = get_option( APIFW_TOKEN.'_packing_slip_settings' );
				$shipping_label_settings = get_option( APIFW_TOKEN.'_shipping_label_settings' );
				$delivery_note_settings = get_option( APIFW_TOKEN.'_delivery_note_settings' );
				$dispatch_label_settings = get_option( APIFW_TOKEN.'_dispatch_label_settings' );
				if($invoice_settings) {
                    $invoice_settings_array = unserialize($invoice_settings);
                    // handling new features added
                    if( !isset( $invoice_settings_array['customCss'] ) ){
                        $invoice_settings_array['customCss'] = '';
                    }
                    // handling free orders gen flag
                    if( !isset( $invoice_settings_array['freeOrder'] ) ){
                        $invoice_settings_array['freeOrder'] = false;
                    }
                    // handling free line items gen flag
                    if( !isset( $invoice_settings_array['freeLineItems'] ) ){
                        $invoice_settings_array['freeLineItems'] = false;
                    }
					$result['invoice_settings'] = json_encode($invoice_settings_array);
				} else {
					$result['invoice_settings'] = '';
				}

				if($packing_slip_settings) {
					$packing_slip_settings_array = unserialize($packing_slip_settings);
                     // handling new features added
                     if( !isset( $packing_slip_settings_array['perItem'] ) ){
                        $packing_slip_settings_array['perItem'] = '';
                    }
					$result['packing_slip_settings'] = json_encode($packing_slip_settings_array);
				} else {
					$result['packing_slip_settings'] = '';
				}

				if($shipping_label_settings) {
					$shipping_label_settings_array = unserialize($shipping_label_settings);
					$result['shipping_label_settings'] = json_encode($shipping_label_settings_array);
				} else {
					$result['shipping_label_settings'] = '';
				}

				if($delivery_note_settings) {
					$delivery_note_settings_array = unserialize($delivery_note_settings);
					$result['delivery_note_settings'] = json_encode($delivery_note_settings_array);
				} else {
					$result['delivery_note_settings'] = '';
				}

				if($dispatch_label_settings) {
					$dispatch_label_settings_array = unserialize($dispatch_label_settings);
					$result['dispatch_label_settings'] = json_encode($dispatch_label_settings_array);
				} else {
					$result['dispatch_label_settings'] = '';
				}

				if($invoice_settings || $packing_slip_settings || $shipping_label_settings || $delivery_note_settings || $dispatch_label_settings) {
					$result['status'] = 1;
				} else {
					$result['status'] = 0;
                }
                break;
            case 'invoice_template':
                $result =  array();
                if( isset( $request_body['template_id'] ) ) {
                    $template_pid = $request_body['template_id'];
                    $template = get_post_meta( $template_pid, APIFW_TOKEN.'_invoice_template', true );
                    if( $template ) {
                        $template_array = unserialize( $template );
                        if( $template_array ) {
                            $template_array['tempName'] = get_the_title($template_pid);
                            $result['templates'] = json_encode($template_array);
                            $result['status'] = 1;
                        } else {
                            $result['templates'] = '';
                            $result['status'] = 0;
                        }
                    } else {
                        $result['templates'] = '';
                        $result['status'] = 0;
                    }
                } else {
                    $templates_list = array();
                    $args = array(
                        'post_type' => 'apifw_inv_templates',
                        'posts_per_page' => -1
                    );
                    $query = new WP_Query( $args );
                    if ( $query->have_posts() ) :
                        while ( $query->have_posts() ) : $query->the_post();
                            $template = get_post_meta( get_the_ID(), APIFW_TOKEN.'_invoice_template', true );
                            $templt_status = get_post_meta( get_the_ID(), APIFW_TOKEN.'_invoice_template_status', true );
                            if( $template ) {
                                $template_array = unserialize( $template );
                                if( $template_array ){
                                    $template_array['id'] = get_the_ID();
                                    $template_array['status'] = $templt_status;
                                    $template_array['tempName'] = get_the_title();
                                    $templates_list[] = $template_array;
                                }
                            }
                        endwhile;
                    endif;
                    wp_reset_postdata();

                    if( $templates_list ){
                        $result['templates'] = json_encode($templates_list);
                        $result['status'] = 1;
                    } else {
                        $result['templates'] = '';
                        $result['status'] = 0;
                    }
                }
                break;
			default:
				break;
		}
		return new WP_REST_Response($result, 200);
    }

    /**
     * Reset invoice template
     * @return WP_REST_Response
     * @throws Exception
    */
    public function reset_invoice_template($data)
    {
        $request_body = $data->get_params();
        $template_pid = $request_body['template_id'];
        $default_invoice_template = array(
            'thumbnail' =>  plugin_dir_url( __DIR__ ).'assets/images/invoice-temp1.png',
            'color' => '#4647C6',
            'fontFamily' => 'Roboto',
            'logo' => 
            array (
                'status' => true,
                'display' => 'Company Logo',
                'url' => '',
                'width' => 150,
                'height' => '',
                'fontFamily' => 'Roboto',
                'fontSize' => 20,
                'fontWeight' => 'bold',
                'fontStyle' => 'normal',
                'fontColor' => '#4647C6',
                'extra' => 
                array (
                    'content' => '',
                    'fontFamily' => 'Roboto',
                    'fontSize' => 14,
                    'fontWeight' => 'normal',
                    'fontStyle' => 'normal',
                    'fontColor' => '#545d66',
                ),
            ),
            'invoiceNumber' => 
            array (
                'status' => true,
                'label' => 'INVOICE:',
                'fontFamily' => 'Roboto',
                'fontSize' => 14,
                'fontWeight' => 'normal',
                'fontStyle' => 'normal',
                'NumColor' => '#545d66',
                'labelColor' => '#545d66',
            ),
            'orderNumber' => 
            array (
                'status' => true,
                'label' => 'Order No:',
                'fontFamily' => 'Roboto',
                'fontSize' => 14,
                'fontWeight' => 'normal',
                'fontStyle' => 'normal',
                'NumColor' => '#545d66',
                'labelColor' => '#545d66',
            ),
            'invoiceDate' => 
            array (
                'status' => true,
                'label' => 'Invoice Date:',
                'format' => 'd/M/Y',
                'fontFamily' => 'Roboto',
                'fontSize' => 14,
                'fontWeight' => 'normal',
                'fontStyle' => 'normal',
                'dateColor' => '#545d66',
                'labelColor' => '#545d66',
            ),
            'orderDate' => 
            array (
                'status' => true,
                'label' => 'Order Date:',
                'format' => 'd/M/Y',
                'fontFamily' => 'Roboto',
                'fontSize' => 14,
                'fontWeight' => 'normal',
                'fontStyle' => 'normal',
                'dateColor' => '#545d66',
                'labelColor' => '#545d66',
            ),
            'customerNote' => 
            array (
                'status' => true,
                'label' => 'Customer Note:',
                'fontFamily' => 'Roboto',
                'fontSize' => 14,
                'fontWeight' => 'normal',
                'fontStyle' => 'normal',
                'contentColor' => '#545d66',
                'labelColor' => '#545d66',
            ),
            'fromAddress' => 
            array (
                'status' => true,
                'title' => 
                array (
                    'value' => 'From Address',
                    'fontFamily' => 'Roboto',
                    'fontSize' => 16,
                    'fontWeight' => 'bold',
                    'fontStyle' => 'normal',
                    'fontColor' => '#4647C6',
                    'aligns' => 'left',
                ),
                'content' => 
                array (
                    'fontFamily' => 'Roboto',
                    'fontSize' => 14,
                    'fontWeight' => 'normal',
                    'fontStyle' => 'normal',
                    'fontColor' => '#545d66',
                    'aligns' => 'left',
                ),
                'vatLabel' => 'VAT Registration Number:',
                'visbility' => 
                array (
                    'sender' => true,
                    'addr1' => true,
                    'addr2' => true,
                    'city' => true,
                    'country' => true,
                    'postCode' => true,
                    'email' => true,
                    'phone' => true,
                    'vat' => true,
                ),
            ),
            'billingAddress' => 
            array (
                'status' => true,
                'title' => 
                array (
                    'value' => 'Billing Address',
                    'fontFamily' => 'Roboto',
                    'fontSize' => 16,
                    'fontWeight' => 'bold',
                    'fontStyle' => 'normal',
                    'fontColor' => '#4647C6',
                    'aligns' => 'left',
                ),
                'content' => 
                array (
                    'fontFamily' => 'Roboto',
                    'fontSize' => 14,
                    'fontWeight' => 'normal',
                    'fontStyle' => 'normal',
                    'fontColor' => '#545d66',
                    'aligns' => 'left',
                ),
            ),
            'shippingAddress' => 
            array (
                'status' => true,
                'title' => 
                array (
                    'value' => 'Shipping Address',
                    'fontFamily' => 'Roboto',
                    'fontSize' => 16,
                    'fontWeight' => 'bold',
                    'fontStyle' => 'normal',
                    'fontColor' => '#4647C6',
                    'aligns' => 'left',
                ),
                'content' => 
                array (
                    'fontFamily' => 'Roboto',
                    'fontSize' => 14,
                    'fontWeight' => 'normal',
                    'fontStyle' => 'normal',
                    'fontColor' => '#545d66',
                    'aligns' => 'left',
                ),
            ),
            'paymentMethod' => 
            array (
                'status' => true,
                'label' => 'Payment Method:',
                'fontFamily' => 'Roboto',
                'fontSize' => 14,
                'fontWeight' => 'normal',
                'fontStyle' => 'normal',
                'methodColor' => '#545d66',
                'labelColor' => '#545d66',
            ),
            'shippingMethod' => 
            array (
                'status' => true,
                'label' => 'Shipping Method:',
                'fontFamily' => 'Roboto',
                'fontSize' => 14,
                'fontWeight' => 'normal',
                'fontStyle' => 'normal',
                'methodColor' => '#545d66',
                'labelColor' => '#545d66',
            ),
            'footer' => 
            array (
                'status' => true,
                'fontFamily' => 'Roboto',
                'fontSize' => 14,
                'fontWeight' => 'normal',
                'fontStyle' => 'normal',
                'aligns' => 'left',
                'color' => '#545d66',
            ),
            'productTable' => 
            array (
                'status' => true,
                'elements' => 
                array (
                    'sku' => 
                    array (
                        'status' => true,
                        'label' => 'SKU',
                    ),
                    'productName' => 
                    array (
                        'status' => true,
                        'label' => 'Product',
                    ),
                    'quantity' => 
                    array (
                        'status' => true,
                        'label' => 'Quantity',
                    ),
                    'price' => 
                    array (
                        'status' => true,
                        'label' => 'Price',
                    ),
                    'taxrate' => 
                    array (
                        'status' => true,
                        'label' => 'Tax Rate',
                    ),
                    'taxtype' => 
                    array (
                        'status' => true,
                        'label' => 'Tax Type',
                    ),
                    'taxvalue' => 
                    array (
                        'status' => true,
                        'label' => 'Tax Value',
                    ),
                    'total' => 
                    array (
                        'status' => true,
                        'label' => 'Total',
                    ),
                ),
                'head' => 
                array (
                    'bgcolor' => '#fff',
                    'fontColor' => '#4647C6',
                    'fontFamily' => 'Roboto',
                    'fontSize' => 14,
                    'fontWeight' => 'bold',
                    'fontStyle' => 'normal',
                    'aligns' => 'center',
                    'borderColor' => '#4647C6',
                ),
                'body' => 
                array (
                    'bgcolor' => '#fff',
                    'fontColor' => '#1B2733',
                    'fontFamily' => 'Roboto',
                    'fontSize' => 14,
                    'fontWeight' => 'normal',
                    'fontStyle' => 'normal',
                    'aligns' => 'center',
                    'borderColor' => '#dddee0',
                ),
            ),
        );
        $templt_serialize = maybe_serialize( $default_invoice_template );

        $inv_templt_post = array(
            'ID' => $template_pid,
            'post_title' => __( 'Invoice 001', 'woocommerce-order-print' ),
        );
        // Update the post into the database
        wp_update_post( $inv_templt_post );
        // updating template settings
        update_post_meta( $template_pid, APIFW_TOKEN.'_invoice_template', $templt_serialize );

        // Returing template settings
        $default_invoice_template['tempName'] = __( 'Invoice 001', 'woocommerce-order-print' );
        $result['templates'] = json_encode($default_invoice_template);
        $result['status'] = 1;
        return new WP_REST_Response($result, 200);
    }
}
