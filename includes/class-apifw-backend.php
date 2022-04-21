<?php
if (!defined('ABSPATH'))
    exit;

class APIFW_Backend
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
     * The token.
     * @var     string
     * @access  public
     * @since   1.0.0
    */
    public $_token;

    /**
     * The main plugin file.
     * @var     string
     * @access  public
     * @since   1.0.0
    */
    public $file;

    /**
     * The main plugin directory.
     * @var     string
     * @access  public
     * @since   1.0.0
    */
    public $dir;

    /**
     * The plugin assets directory.
     * @var     string
     * @access  public
     * @since   1.0.0
    */
    public $assets_dir;

    /**
     * Suffix for Javascripts.
     * @var     string
     * @access  public
     * @since   1.0.0
    */
    public $script_suffix;

    /**
     * The plugin assets URL.
     * @var     string
     * @access  public
     * @since   1.0.0
    */
    public $assets_url;
    /**
     * The plugin hook suffix.
     * @var     array
     * @access  public
     * @since   1.0.0
    */
    public $hook_suffix = array();

    /**
     * The Invoice Settings
     * @var     array
     * @access  public
     * @since   1.0.0
    */
    public $invoice_settings;

    /**
     * The Packing Slip Settings
     * @var     array
     * @access  public
     * @since   1.0.0
    */
   
    public function __construct( $file = '', $version = '1.0.0' )
    {
        $this->_version = $version;
        $this->_token = APIFW_TOKEN;
        $this->file = $file;
        $this->dir = dirname( $this->file );
        $this->assets_dir = trailingslashit( $this->dir ) . 'assets';
        $this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );
        $this->script_suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        $plugin = plugin_basename($this->file);
        //add action links to link to link list display on the plugins page
       // add_filter( "plugin_action_links_$plugin", array( $this, 'add_settings_link' ) );
        // post type reg
        add_action( 'init', array ( $this, 'apifw_posttypes' ) );
        //reg activation hook
        register_activation_hook( $this->file, array( $this, 'install' ) );
        //reg admin menu
        add_action( 'admin_menu', array( $this, 'register_root_page' ), 30 );
        //enqueue scripts & styles
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );
            
        //add new column in order table
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_new_orderlist_column' ) );
        //process data for new column in order table
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'new_orderlist_column_content' ) );
        // adding bulk actions to order table
     
        //reg deactivation hook
        register_deactivation_hook( $this->file, array( $this, 'apifw_deactivation' ) );
    }

    /**
     * Ensures only one instance of APIFw is loaded or can be loaded.
     * @return Main APIFw instance
     * @see WordPress_Plugin_Template()
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
     * Installation. Runs on activation.
     * @access  public
     * @return  void
     * @since   1.0.0
    */
    public function install()
    {
        if ( $this->is_woocommerce_activated() === false ) {
            add_action( 'admin_notices', array ( $this, 'notice_need_woocommerce' ) );
            return;
        }
        $this->add_settings_options();
        $this->create_secure_upload_dir();
        //$this->register_custom_cron();
    }

    /**
     * Check if woocommerce is activated
     * @access  public
     * @return  boolean woocommerce install status
    */
    public function is_woocommerce_activated()
    {
        $blog_plugins = get_option( 'active_plugins', array() );
        $site_plugins = is_multisite() ? (array) maybe_unserialize( get_site_option('active_sitewide_plugins' ) ) : array();

        if ( in_array( 'woocommerce/woocommerce.php', $blog_plugins ) || isset( $site_plugins['woocommerce/woocommerce.php'] ) ) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * WooCommerce not active notice.
     * @access  public
     * @return string Fallack notice.
    */
    public function notice_need_woocommerce()
    {
        $error = sprintf( __( APIFW_PLUGIN_NAME.' requires %sWooCommerce%s to be installed & activated!' , '' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">', '</a>' );
        $message = '<div class="error"><p>' . $error . '</p></div>';
        echo $message;
    }
    
    /**
     * Add plugin basic settings
     * @access private
    */
    private function add_settings_options()
    {
        // Log the plugin version number
        if ( false === get_option( $this->_token.'_version' ) ){
            add_option( $this->_token.'_version', $this->_version, '', 'yes' );
        } else {
            update_option( $this->_token . '_version', $this->_version );
        }
        // getting woocommerce store address
        $woo_store_address = $this->get_woo_store_address();
        //general settings array
        $general_settings_array = array();
        $general_settings_array['company_name'] = '';
        $general_settings_array['company_logo'] = '';
        $general_settings_array['gen_footer'] = '';
        $general_settings_array['sender_name'] = '';
        $general_settings_array['sender_addr1'] = $woo_store_address['address1'];
        $general_settings_array['sender_addr2'] = $woo_store_address['address2'];
        $general_settings_array['sender_city'] = $woo_store_address['city'];
        $general_settings_array['sender_country'] = $woo_store_address['country'].', '.$woo_store_address['state'];
        $general_settings_array['sender_postal_code'] = $woo_store_address['postcode'];
        $general_settings_array['sender_number'] = '';
        $general_settings_array['sender_email'] = '';
        $general_settings_array['gen_vat'] = '';
        $general_settings_array['rtl_support'] = false;
        $general_settings_serialize = maybe_serialize( $general_settings_array );
        // adding general settings options
        if ( false === get_option($this->_token.'_general_settings') ){
            add_option( $this->_token.'_general_settings', $general_settings_serialize, '', 'yes' );
        }

        // invoice settings array
        $ord_sts = array();
        $ord_sts[] =  array(
            'value' => 'wc-processing',
            'label' => 'Processing',
        );
        $invoice_settings_array = array();
        $invoice_settings_array['status'] = true;
        $invoice_settings_array['label'] = 'Invoice';
        $invoice_settings_array['invoice_date'] = false;
        $invoice_settings_array['order_status'] = $ord_sts;
        $invoice_settings_array['next_no'] = 1;
        $invoice_settings_array['no_length'] = 3;
        $invoice_settings_array['no_prefix'] = '';
        $invoice_settings_array['no_suffix'] = '';
        $invoice_settings_array['attach_email'] = true;
        $invoice_settings_array['print_customer'] = false;
        $invoice_settings_array['invoice_logo'] = '';
        $invoice_settings_array['number_format'] = '[number]';
        $invoice_settings_array['freeOrder'] = false;
        $invoice_settings_array['freeLineItems'] = false;
        $invoice_settings_array['customCss'] = '';
        $invoice_settings_serialize = maybe_serialize( $invoice_settings_array );
        // adding invoice settings options
        if ( false === get_option($this->_token.'_invoice_settings') ){
            add_option( $this->_token.'_invoice_settings', $invoice_settings_serialize, '', 'yes' );
        }

        // packing slip settings array
      

        // handling invoice templates settings
        $default_invoice_template = array(
            'thumbnail' => $this->assets_url.'images/cedcoss-logo.png',
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
        $invoice_template_serialize = maybe_serialize( $default_invoice_template );

        // adding default invoice template && saving its id 
        if ( false === get_option($this->_token.'_invoice_active_template_id') ){
            $inv_templt_post = array(
                'post_type' => 'apifw_inv_templates',
                'post_title'    => __( 'Invoice 001', '' ),
                'post_status'   => 'publish',
            );
            $inv_templt_id = wp_insert_post( $inv_templt_post );
            if( $inv_templt_id ) {
                add_post_meta( $inv_templt_id, $this->_token.'_invoice_template', $invoice_template_serialize );
                add_post_meta( $inv_templt_id, $this->_token.'_invoice_template_status', true );
                add_option( $this->_token.'_invoice_active_template_id', $inv_templt_id, '', 'yes' );
            }
        }
    }

    /**
     * getting woocommerce store address
     * @access  public
     * @return array store address
    */
    public function get_woo_store_address()
    {
        $store_address = array();
        $store_address['address1'] = get_option( 'woocommerce_store_address' );
        $store_address['address2'] = get_option( 'woocommerce_store_address_2' );
        $store_address['city'] = get_option( 'woocommerce_store_city' );
        $store_address['postcode'] = get_option( 'woocommerce_store_postcode' );
        // The country/state codes
        $store_raw_country = get_option( 'woocommerce_default_country' );
        // Split the country/state codes
        $split_country = explode( ":", $store_raw_country );
        $store_country_code = $split_country[0];
        // get country name from code
        $store_country_name = WC()->countries->countries[$store_country_code];
        // get state name from codes
        if( isset( $split_country[1] ) ) {
            $store_state_code = $split_country[1];
            $country_states = WC()->countries->get_states( $store_country_code );
            $store_state_name = !empty( $country_states[$store_state_code] ) ? $country_states[$store_state_code] : '';
        } else {
            $store_state_name = '';
        }
        // getting country and state to store address array
        $store_address['country'] = $store_country_name;
        $store_address['state'] = $store_state_name;

        return $store_address;
    }

    /**
     * Adding post types
    */
    public function apifw_posttypes() {
        if( !post_type_exists( 'apifw_inv_templates' ) ) {
            register_post_type('apifw_inv_templates', array(
                'label' => __('Invoice Templates', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                'description' => '',
                'public' => false,
                'show_ui' => false,
                'show_in_menu' => false,
                'capability_type' => 'post',
                'hierarchical' => false,
                'query_var' => true, 
                'exclude_from_search' => true,
                'supports' => array('title', 'thumbnail'),
                'show_in_rest' => true,
                'menu_icon'   => 'dashicons-buddicons-topics',      
                'labels' => array(
                    'name' => __('Invoice Templates', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'singular_name' => __('Invoice Templates', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'menu_name' => __('Invoice Templates', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'add_new' => __('Add New Template', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'add_new_item' => __('Add New Template', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'edit' => __('Edit Template', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'edit_item' => __('Edit Template', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'new_item' => __('New Template', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'view' => __('View Template', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'view_item' => __('View Template', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'search_items' => __('Search Templates', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'not_found' => __('No Templates Found', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'not_found_in_trash' => __('No Templates Found in Trash', 'pdf-invoices-and-packing-slips-for-woocommerce'),
                    'parent' => __('Parent Template', 'pdf-invoices-and-packing-slips-for-woocommerce')
                )
            ));
        }
    }

     /**
     * Creating upload directory
     * Secure directory with htaccess  
    */
    public function create_secure_upload_dir()
    {
        //creating directory
        if( !is_dir( APIFW_UPLOAD_TEMPLATE_DIR ) )
        {
            @mkdir( APIFW_UPLOAD_TEMPLATE_DIR, 0700 );
        }

        $files_to_create = array('.htaccess' => 'deny from all', 'index.php'=>'<?php // acowebs');
        foreach( $files_to_create as $file=>$file_content )
        {
            if( !file_exists( APIFW_UPLOAD_TEMPLATE_DIR.'/'.$file ) )
            {
                $fh = @fopen( APIFW_UPLOAD_TEMPLATE_DIR.'/'.$file, "w" );
                if( is_resource( $fh ) )
                {
                    fwrite( $fh, $file_content );
                    fclose( $fh );
                }
            }
        }   
}

   
    public function register_root_page()
    {
        
        // getting document settings
        $this->get_document_settings();
    }

    /**
     * Calling view function for admin page components
    */
    public function admin_ui()
    {
        APIFW_Backend::view('admin-root', []);
    }

    /**
     * Including View templates
    */
    static function view( $view, $data = array() )
    {
        //extract( $data );
        include( plugin_dir_path(__FILE__) . 'views/' . $view . '.php' );
    }

    /**
     * Getting document(invoice, packing slip etc) settings
    */
    public function get_document_settings()
    {
        $this->invoice_settings = unserialize( get_option( $this->_token.'_invoice_settings' ) );
        
    }

    /**
     * Load admin CSS.
     * @access  public
     * @return  void
     * @since   1.0.0
    */
    public function admin_enqueue_styles($hook = '')
    {
        wp_register_style($this->_token . '-admin', esc_url($this->assets_url) . 'css/backend.css', array(), $this->_version);
        wp_enqueue_style($this->_token . '-admin');
    }

    /**
     * Load admin Javascript.
     * @access  public
     * @return  void
     * @since   1.0.0
    */
    public function admin_enqueue_scripts($hook = '')
    {
        if (!isset($this->hook_suffix) || empty($this->hook_suffix)) {
            return;
        }

        $screen = get_current_screen();

        wp_enqueue_script('jquery');

        if ( in_array( $screen->id, $this->hook_suffix ) ) {
            // Enqueue WordPress media scripts
            if ( !did_action( 'wp_enqueue_media' ) ) {
                wp_enqueue_media();
            }
            //transilation script
            if ( !wp_script_is( 'wp-i18n', 'registered' ) ) {
                wp_register_script( 'wp-i18n', esc_url( $this->assets_url ) . 'js/i18n.min.js', array('jquery'), $this->_version, true );
            }
            //Enqueue custom backend script
            wp_enqueue_script( $this->_token . '-backend', esc_url( $this->assets_url ) . 'js/backend.js', array('wp-i18n'), $this->_version, true );
            //Localize a script.
            wp_localize_script( $this->_token . '-backend', 
                'apifw_object', array(
                    'api_nonce' => wp_create_nonce('wp_rest'),
                    'root' => rest_url('apifw/v1/'),
                    'assets_url' => $this->assets_url,
                    'text_domain' => 'pdf-invoices-and-packing-slips-for-woocommerce',
                    'invoice_sample_url' => admin_url( '?apifw_document=true&type=invoice_sample&action=preview' )
                )
            );

            // backend js transilations
            if( APIFW_WP_VERSION >= 5 ) {
                $plugin_lang_path = trailingslashit( $this->dir ) . 'languages';
                wp_set_script_translations( $this->_token . '-backend', 'pdf-invoices-and-packing-slips-for-woocommerce', $plugin_lang_path );
            }
        }
    }
    
   
    /**
     * Handling order page metabox content
    */
    
    /**
     * Adding New Column In Admin Order List Table
    */
    public function add_new_orderlist_column( $columns )
    {
        $columns['apifw_doc_links'] = __( 'Order Print');
        return $columns;
    }

    /**
     * Handling Content For New Column In Admin Order List Table
    */
    public function new_orderlist_column_content( $column )
    {
        global $post;
        $orderids_array = array( $post->ID );
        $orderid_enc = urlencode( serialize( $orderids_array ) );
        if( $column == 'apifw_doc_links' ) {
            $content = '<div class="apifw_order_col_links">';
            //invoice link
            if( $this->invoice_settings['status'] == true || get_post_meta( $post->ID, $this->_token.'_ord_invoice_no', true ) ):
                $inv_preview_url = admin_url( '?apifw_document=true&order_id='.$orderid_enc.'&type=invoice&action=preview' );
                $content .= '<a href="'.$inv_preview_url.'" class="apifw_ordtbl_inv_link" target="_blank" title="'.__( 'Order Print', '' ).'">'.__( 'Preview Order', '' ).'</a>';
            endif;
            //packing slip btn
            
            $content .= '</div>';

            echo $content;
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
     * Deactivation hook
    */
    public function apifw_deactivation()
    {
        wp_clear_scheduled_hook( 'apifw_invoice_delete_cron' );
    }
}