<?php

if (!defined('ABSPATH'))
    exit;

class APIFW_Front_End
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
    
    /**
     * The main plugin file.
     * @var     string
     * @access  public
     * @since   1.0.0
    */
    public $file;

    /**
     * The token.
     * @var     string
     * @access  public
     * @since   1.0.0
    */
    public $_token;

    /**
     * The plugin assets URL.
     * @var     string
     * @access  public
     * @since   1.0.0
    */
    public $assets_url;

    /**
     * Constructor function.
     * @access  public
     * @return  void
     * @since   1.0.0
    */
    function __construct( $file = '', $version = '1.0.0' )
    {
        $this->_version = $version;
        $this->_token = APIFW_TOKEN;
        $this->file = $file;
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));
        // Load frontend CSS
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_styles'), 15);
        // handling order documents access
        add_action( 'init',  array( $this, 'handle_order_documents' ), 10 );
    }

    /**
     * Ensures only one instance of APIFW_Front_End is loaded or can be loaded.
     * @return Main APIFW_Front_End instance
     * @since 1.0.0
     * @static
    */
    public static function instance($file = '', $version = '1.0.0')
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($file, $version);
        }
        return self::$_instance;
    }

    /**
     * Load Front End CSS.
     * @access  public
     * @return  void
     * @since   1.0.0
    */
    public function frontend_enqueue_styles($hook = '')
    {
        wp_register_style($this->_token . '-frontend', esc_url($this->assets_url) . 'css/frontend.css', array(), $this->_version);
        wp_enqueue_style($this->_token . '-frontend');
    }

    /**
     * Handling order documents views
    */
    public function handle_order_documents()
    {
        if( isset( $_GET['apifw_document'] ) ) {
        	//checkes user is logged in
        	if( !is_user_logged_in() ) {
        		auth_redirect();
        	}
        	$not_allowed_msg = __( 'You are not allowed to view this page.', 'woocommerce-order-print' );
            $not_allowed_title = __( 'Access denied !!!.', 'woocommerce-order-print' );

            // check for type is sample doc or order actual documents
            if( isset( $_GET['type'] ) && $_GET['type'] && $_GET['type'] != 'invoice_sample' ) {
                if( $_GET['order_id'] ) {
                    //parse to array
                    $order_ids = unserialize( stripslashes( urldecode( $_GET['order_id'] ) ) );
                    if( $order_ids ) {
                        // order owner verification flag
                        $ord_customer = true;
                        foreach( $order_ids as $order_id ) {
                            $order = wc_get_order( $order_id );
                            if( $order->get_user_id() != get_current_user_id() ) {
                                $ord_customer = false;
                                break;
                            }
                        }

                        //checking user roles
                        if( current_user_can( 'administrator' ) || current_user_can( 'manage_woocommerce' ) || $ord_customer ) {
                            // checking document type
                            if( $_GET['type'] == 'invoice' ) {
                                $invoice_generator = new APIFW_Invoice();
                                if( isset( $_GET['action'] ) && $_GET['action'] && $_GET['action'] == 'download' ) {
                                    $invoice_generator->invoice_pdf_gen_handler( $order_ids, 'inv_download' );
                                } elseif( isset( $_GET['action'] ) && $_GET['action'] && $_GET['action'] == 'preview' ){
                                    $invoice_generator->invoice_pdf_gen_handler( $order_ids, 'inv_preview' );
                                } else {
                                    wp_die( __('Wrong action', 'woocommerce-order-print') );
                                }
                            } elseif( $_GET['type'] == 'packing_slip' ) {
                                new APIFW_Packing_Slip( $order_ids );
                            } elseif( $_GET['type'] == 'delivery_note' ) {
                                new APIFW_Delivery_Note( $order_ids );
                            }
                            elseif( $_GET['type'] == 'shipping_label' ) {
                                new APIFW_Shipping_Label( $order_ids );
                            } elseif( $_GET['type'] == 'dispatch_label') {
                                new APIFW_Dispatch_Label( $order_ids );
                            } else {
                                wp_die( __('Wrong document', 'woocommerce-order-print') );
                            }
                        } else {
                            wp_die( $not_allowed_msg, $not_allowed_title );
                        }
                    } else {
                        wp_die( __('Order not found', 'woocommerce-order-print') );
                    }
                } else {
                    wp_die( __('Order not found', 'woocommerce-order-print') );
                }
            } else {
                if( isset( $_GET['type'] ) && $_GET['type'] == 'invoice_sample' ){
                    //checking user roles & calling invoice generator
                    if( current_user_can( 'administrator' ) || current_user_can( 'manage_woocommerce' ) ) {
                        $order_ids = array(10);
                        $invoice_generator = new APIFW_Invoice();
                        $invoice_generator->invoice_pdf_gen_handler( $order_ids, 'inv_sample' );
                    } else {
                        wp_die( $not_allowed_msg, $not_allowed_title );
                    }
                } else {
                    wp_die( __('Wrong document', 'woocommerce-order-print') );
                }
            }
            exit;
        }
    }
}