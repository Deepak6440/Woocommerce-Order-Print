<?php
if (!defined('ABSPATH'))
    exit;

class APIFW_Invoice
{
    /**
     * The token.
     * @var     string
     * @access  public
     * @since   1.0.0
    */
    public $_token;

    /**
     * @var array
     * @access  public
     * @since   1.0.0
    */
    public $general_settings;

    /**
     * @var array
     * @access  public
     * @since   1.0.0
    */
    public $invoice_settings;

    /**
     * @var array
     * @access  public
     * @since   1.0.0
    */
    public $pdf_template;

    /**
     * @var string
     * @access  public
     * @since   1.0.0
    */
    public $invoice_name = 'Invoice';

    /**
     * Constructor function.
     * @access  public
     * @return  void
     * @since   1.0.0
    */
    public function __construct()
    {
        $this->_token = APIFW_TOKEN;
        // getting general settings
        $this->general_settings = unserialize( get_option( $this->_token.'_general_settings' ) );

        // getting invoice settings
        $this->invoice_settings = unserialize( get_option( $this->_token.'_invoice_settings' ) );

        // getting invoice template
        $template_pid = get_option( $this->_token.'_invoice_active_template_id' );
        if( !$template_pid )
            return;

        $iv_template = get_post_meta( $template_pid, $this->_token.'_invoice_template', true );
        if( !$iv_template )
            return;
        $iv_template_array = unserialize( $iv_template );
        $this->pdf_template = $iv_template_array;

        //checking invoice status for further actions
        if( $this->invoice_settings['status'] === true ){
            // adding action button to frontend
            if( $this->invoice_settings['print_customer'] === true ){
                // adding button to frontend user dashboard orders detail pages
                add_action('woocommerce_order_details_after_order_table', array( $this, 'add_frontend_action_btns' ), 10, 2 );
                // adding button to frontend myaccount order list table
                add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_my_account_order_action_btns' ), 10, 2 );
            }

            // adding invoice pdf attachment to order emails
            if( $this->invoice_settings['attach_email'] === true ){
                add_filter( 'woocommerce_email_attachments', array( $this, 'add_attachment_order_email' ), 10, 3 );
            } else {
                // checking new order for generating invoice number
                add_action( 'woocommerce_thankyou', array( $this, 'handle_new_order' ), 10, 1 );
                // handling order status updates
                add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_update' ), 10, 3 ); 
            }
        }
    }

    /**
     * Handling pdf generation
     * @access  public
     * @param order_ids
     * @param action
    */
    public function invoice_pdf_gen_handler( $order_ids, $action )
    {
        if ( !$order_ids )
            return false;

        // getting invoice pdf name
        $this->invoice_name = $this->get_invoice_pdf_name( $order_ids, $action );
        // pdf generator
        $pdf_status = $this->generate_invoice_pdf( $order_ids, $action );

        return $pdf_status;
    }

    /**
     * Returning pdf file name
     * @access  public
     * @param order_ids
     * @param action
    */
    public function get_invoice_pdf_name( $order_ids, $action )
    {
        if( $action != 'inv_sample' ){
            if ( !$order_ids )
                return false;

            if( $this->invoice_settings['label'] && $this->invoice_settings['label'] != '' ) {
                $invoice_label = $this->invoice_settings['label'];
            } else {
                $invoice_label = 'Invoice';
            }

            if( count( $order_ids ) > 1 ) {
                $invoice_name = $invoice_label;
            } else {
                $invoice_no = $this->get_invoice_number( $order_ids[0] );
                $invoice_name = $invoice_label.'-'.$order_ids[0].'-'.$invoice_no;
            }
        } else {
            if( $this->invoice_settings['label'] && $this->invoice_settings['label'] != '' ) {
                $invoice_name = $this->invoice_settings['label'];
            } else {
                $invoice_name = 'Invoice';
            }
        }
        return $invoice_name;
    }

    /**
     * Generating pdf file
     * @access  public
     * @param order_ids
     * @param action
    */
    public function generate_invoice_pdf( $order_ids, $action )
    {
        if ( !$order_ids )
            return false;

        require_once __DIR__ . '/vendor/autoload.php';
        $mpdf = new \Mpdf\Mpdf(
            [
                'format' => 'A4-P',
                'debugfonts'=> false,
                'mode' => 'utf-8',
                'autoScriptToLang'=>true,
                'autoLangToFont' => true,
                'default_font_size' => 14,
                'default_font' => 'roboto',
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 10,
                'margin_bottom' => 10,
            ]
        );
        $html = $this->get_invoice_html_template( $order_ids, $action );
        if( $html ) {
            $mpdf->WriteHTML( $html );
            // Finalise the document and send it to specified destination
            if( $action == 'inv_save' ) {
                if( is_dir( APIFW_UPLOAD_INVOICE_DIR ) ){
                    $inv_pdf_path = APIFW_UPLOAD_INVOICE_DIR.'/'.$this->invoice_name.'.pdf';
                    $mpdf->Output($inv_pdf_path, 'F');
                    return $inv_pdf_path;
                }
            } elseif( $action == 'inv_preview' ){
                $mpdf->Output($this->invoice_name.'.pdf', 'I');
            } elseif( $action == 'inv_download' ) {
                $mpdf->Output($this->invoice_name.'.pdf', 'D');
            } elseif( $action == 'inv_sample' ) {
                $mpdf->Output($this->invoice_name.'.pdf', 'I');
            } else {
                return false;
            }
            return 1;
        } else {
            return false;
        }
    }

    /**
     * Generating html template for pdf 
     * @access  public
     * @param order_ids
     * @param action
    */
    public function get_invoice_html_template( $order_ids, $action )
    {
        if ( !$order_ids )
            return false;

        //general datas
        $company_name = $this->general_settings['company_name'];
        $company_logo = $this->general_settings['company_logo'];
        $sender_name = $this->general_settings['sender_name'];
        $sender_addr1 = $this->general_settings['sender_addr1'];
        $sender_addr2 = $this->general_settings['sender_addr2'];
        $sender_city = $this->general_settings['sender_city'];
        $sender_country = $this->general_settings['sender_country'];
        $sender_postal_code = $this->general_settings['sender_postal_code'];
        $sender_email = $this->general_settings['sender_email'];
        $sender_number = $this->general_settings['sender_number'];
        $tax_reg_no = $this->general_settings['gen_vat'];
        $footer_txt = $this->general_settings['gen_footer'];
        $rtl = $this->general_settings['rtl_support'];
        //handling custom logo set for invoice settings
        if( $this->invoice_settings['invoice_logo'] && $this->invoice_settings['invoice_logo'] != '' ) {
            $company_logo = $this->invoice_settings['invoice_logo'];
        }

        //getting choosen template
        $invoice_template = $this->pdf_template;

        //global color and font family
        $selected_color = $invoice_template['color'];
        $fontfamily = strtolower( str_replace( ' ', '', $invoice_template['fontFamily'] ) );

        // logo properties
        $logo = $invoice_template['logo'];

        $logo_name_style = 'font-family: '.strtolower( str_replace( ' ', '', $logo['fontFamily'] ) ).'; ';
        $logo_name_style .= 'font-weight: '.$logo['fontWeight'].'; ';
        $logo_name_style .= 'font-style: '.$logo['fontStyle'].'; ';
        $logo_name_style .= 'font-size: '.$logo['fontSize'].'px; ';
        $logo_name_style .= 'color: '.$logo['fontColor'].';';
        
        $logo_img_style = '';
        if( $logo['width'] != '' &&  $logo['height'] != '' ) {
            $logo_img_style = 'width: '.$logo['width'].'px; height: '.$logo['height'].'px;';
        } elseif( $logo['width'] != '' &&  $logo['height'] == '' ) {
            $logo_img_style = 'width: '.$logo['width'].'px;';
        } else {
            $logo_img_style = 'height: '.$logo['height'].'px;';
        }

        $logo_extra_cont_style = 'font-family: '.strtolower( str_replace( ' ', '', $logo['extra']['fontFamily'] ) ).'; ';
        $logo_extra_cont_style .= 'font-weight: '.$logo['extra']['fontWeight'].'; ';
        $logo_extra_cont_style .= 'font-style: '.$logo['extra']['fontStyle'].'; ';
        $logo_extra_cont_style .= 'font-size: '.$logo['extra']['fontSize'].'px; ';
        $logo_extra_cont_style .= 'color: '.$logo['extra']['fontColor'].';';

        // from address properties
        $from_address = $invoice_template['fromAddress'];

        $from_address_title_style = 'font-family: '.strtolower( str_replace( ' ', '', $from_address['title']['fontFamily'] ) ).'; ';
        $from_address_title_style .= 'font-weight: '.$from_address['title']['fontWeight'].'; ';
        $from_address_title_style .= 'font-style: '.$from_address['title']['fontStyle'].'; ';
        $from_address_title_style .= 'font-size: '.$from_address['title']['fontSize'].'px; ';
        $from_address_title_style .= 'text-align: '.$from_address['title']['aligns'].'; ';
        $from_address_title_style .= 'color: '.$from_address['title']['fontColor'].';';

        $from_address_content_style = 'font-family: '.strtolower( str_replace( ' ', '', $from_address['content']['fontFamily'] ) ).'; ';
        $from_address_content_style .= 'font-weight: '.$from_address['content']['fontWeight'].'; ';
        $from_address_content_style .= 'font-style: '.$from_address['content']['fontStyle'].'; ';
        $from_address_content_style .= 'font-size: '.$from_address['content']['fontSize'].'px; ';
        $from_address_content_style .= 'text-align: '.$from_address['content']['aligns'].'; ';
        $from_address_content_style .= 'color: '.$from_address['content']['fontColor'].';';

        // invoice number properties
        $invoice_number_set = $invoice_template['invoiceNumber'];
        $invoice_number_style = 'font-family: '.strtolower( str_replace( ' ', '', $invoice_number_set['fontFamily'] ) ).'; ';
        $invoice_number_style .= 'font-weight: '.$invoice_number_set['fontWeight'].'; ';
        $invoice_number_style .= 'font-style: '.$invoice_number_set['fontStyle'].'; ';
        $invoice_number_style .= 'font-size: '.$invoice_number_set['fontSize'].'px; ';
        $invoice_number_style .= 'color: '.$invoice_number_set['NumColor'].';';

        $invoice_number_title_style = 'color: '.$invoice_number_set['labelColor'].';';

        // invoice date properties
        $invoice_date_set = $invoice_template['invoiceDate'];
        $invoice_date_style = 'font-family: '.strtolower( str_replace( ' ', '', $invoice_date_set['fontFamily'] ) ).'; ';
        $invoice_date_style .= 'font-weight: '.$invoice_date_set['fontWeight'].'; ';
        $invoice_date_style .= 'font-style: '.$invoice_date_set['fontStyle'].'; ';
        $invoice_date_style .= 'font-size: '.$invoice_date_set['fontSize'].'px; ';
        $invoice_date_style .= 'color: '.$invoice_date_set['dateColor'].';';

        $invoice_date_title_style = 'color: '.$invoice_date_set['labelColor'].';';

        // invoice order date properties
        $order_date_set = $invoice_template['orderDate'];
        $order_date_style = 'font-family: '.strtolower( str_replace( ' ', '', $order_date_set['fontFamily'] ) ).'; ';
        $order_date_style .= 'font-weight: '.$order_date_set['fontWeight'].'; ';
        $order_date_style .= 'font-style: '.$order_date_set['fontStyle'].'; ';
        $order_date_style .= 'font-size: '.$order_date_set['fontSize'].'px; ';
        $order_date_style .= 'color: '.$order_date_set['dateColor'].';';

        $order_date_title_style = 'color: '.$order_date_set['labelColor'].';';

        // invoice order no properties
        $order_no_set = $invoice_template['orderNumber'];
        $order_no_style = 'font-family: '.strtolower( str_replace( ' ', '', $order_no_set['fontFamily'] ) ).'; ';
        $order_no_style .= 'font-weight: '.$order_no_set['fontWeight'].'; ';
        $order_no_style .= 'font-style: '.$order_no_set['fontStyle'].'; ';
        $order_no_style .= 'font-size: '.$order_no_set['fontSize'].'px; ';
        $order_no_style .= 'color: '.$order_no_set['NumColor'].';';

        $order_no_title_style = 'color: '.$order_no_set['labelColor'].';';

        // invoice payment method properties
        $payment_method_set = $invoice_template['paymentMethod'];
        $payment_method_style = 'font-family: '.strtolower( str_replace( ' ', '', $payment_method_set['fontFamily'] ) ).'; ';
        $payment_method_style .= 'font-weight: '.$payment_method_set['fontWeight'].'; ';
        $payment_method_style .= 'font-style: '.$payment_method_set['fontStyle'].'; ';
        $payment_method_style .= 'font-size: '.$payment_method_set['fontSize'].'px; ';
        $payment_method_style .= 'color: '.$payment_method_set['methodColor'].';';

        $payment_method_title_style = 'color: '.$payment_method_set['labelColor'].';';

        // invoice shipping method properties
        $shipping_method_set = $invoice_template['shippingMethod'];
        $shipping_method_style = 'font-family: '.strtolower( str_replace( ' ', '', $shipping_method_set['fontFamily'] ) ).'; ';
        $shipping_method_style .= 'font-weight: '.$shipping_method_set['fontWeight'].'; ';
        $shipping_method_style .= 'font-style: '.$shipping_method_set['fontStyle'].'; ';
        $shipping_method_style .= 'font-size: '.$shipping_method_set['fontSize'].'px; ';
        $shipping_method_style .= 'color: '.$shipping_method_set['methodColor'].';';

        $shipping_method_title_style = 'color: '.$shipping_method_set['labelColor'].';';

        // invoice customer note properties
        $customer_note_set = $invoice_template['customerNote'];
        $customer_note_style = 'font-family: '.strtolower( str_replace( ' ', '', $customer_note_set['fontFamily'] ) ).'; ';
        $customer_note_style .= 'font-weight: '.$customer_note_set['fontWeight'].'; ';
        $customer_note_style .= 'font-style: '.$customer_note_set['fontStyle'].'; ';
        $customer_note_style .= 'font-size: '.$customer_note_set['fontSize'].'px; ';
        $customer_note_style .= 'color: '.$customer_note_set['contentColor'].';';

        $customer_note_title_style = 'color: '.$customer_note_set['labelColor'].';';

        // billing address properties
        $billing_addr_set = $invoice_template['billingAddress'];

        $billing_addr_title_style = 'font-family: '.strtolower( str_replace( ' ', '', $billing_addr_set['title']['fontFamily'] ) ).'; ';
        $billing_addr_title_style .= 'font-weight: '.$billing_addr_set['title']['fontWeight'].'; ';
        $billing_addr_title_style .= 'font-style: '.$billing_addr_set['title']['fontStyle'].'; ';
        $billing_addr_title_style .= 'font-size: '.$billing_addr_set['title']['fontSize'].'px; ';
        $billing_addr_title_style .= 'text-align: '.$billing_addr_set['title']['aligns'].'; ';
        $billing_addr_title_style .= 'color: '.$billing_addr_set['title']['fontColor'].';';

        $billing_addr_content_style = 'font-family: '.strtolower( str_replace( ' ', '', $billing_addr_set['content']['fontFamily'] ) ).'; ';
        $billing_addr_content_style .= 'font-weight: '.$billing_addr_set['content']['fontWeight'].'; ';
        $billing_addr_content_style .= 'font-style: '.$billing_addr_set['content']['fontStyle'].'; ';
        $billing_addr_content_style .= 'font-size: '.$billing_addr_set['content']['fontSize'].'px; ';
        $billing_addr_content_style .= 'text-align: '.$billing_addr_set['content']['aligns'].'; ';
        $billing_addr_content_style .= 'color: '.$billing_addr_set['content']['fontColor'].';';

        // shipping address properties
        $shipping_addr_set = $invoice_template['shippingAddress'];

        $shipping_addr_title_style = 'font-family: '.strtolower( str_replace( ' ', '', $shipping_addr_set['title']['fontFamily'] ) ).'; ';
        $shipping_addr_title_style .= 'font-weight: '.$shipping_addr_set['title']['fontWeight'].'; ';
        $shipping_addr_title_style .= 'font-style: '.$shipping_addr_set['title']['fontStyle'].'; ';
        $shipping_addr_title_style .= 'font-size: '.$shipping_addr_set['title']['fontSize'].'px; ';
        $shipping_addr_title_style .= 'text-align: '.$shipping_addr_set['title']['aligns'].'; ';
        $shipping_addr_title_style .= 'color: '.$shipping_addr_set['title']['fontColor'].';';

        $shipping_addr_content_style = 'font-family: '.strtolower( str_replace( ' ', '', $shipping_addr_set['content']['fontFamily'] ) ).'; ';
        $shipping_addr_content_style .= 'font-weight: '.$shipping_addr_set['content']['fontWeight'].'; ';
        $shipping_addr_content_style .= 'font-style: '.$shipping_addr_set['content']['fontStyle'].'; ';
        $shipping_addr_content_style .= 'font-size: '.$shipping_addr_set['content']['fontSize'].'px; ';
        $shipping_addr_content_style .= 'text-align: '.$shipping_addr_set['content']['aligns'].'; ';
        $shipping_addr_content_style .= 'color: '.$shipping_addr_set['content']['fontColor'].';';

        //product table properties
        $product_table_set = $invoice_template['productTable'];

        $product_table_elements = $product_table_set['elements'];

        $product_table_head_style = 'font-family: '.strtolower( str_replace( ' ', '', $product_table_set['head']['fontFamily'] ) ).'; ';
        $product_table_head_style .= 'font-weight: '.$product_table_set['head']['fontWeight'].'; ';
        $product_table_head_style .= 'font-style: '.$product_table_set['head']['fontStyle'].'; ';
        $product_table_head_style .= 'font-size: '.$product_table_set['head']['fontSize'].'px; ';
        $product_table_head_style .= 'text-align: '.$product_table_set['head']['aligns'].'; ';
        $product_table_head_style .= 'color: '.$product_table_set['head']['fontColor'].'; ';
        $product_table_head_style .= 'background-color: '.$product_table_set['head']['bgcolor'].'; ';
        $product_table_head_style .= 'border-color: '.$product_table_set['head']['borderColor'].';';

        $product_table_body_style = 'font-family: '.strtolower( str_replace( ' ', '', $product_table_set['body']['fontFamily'] ) ).'; ';
        $product_table_body_style .= 'font-weight: '.$product_table_set['body']['fontWeight'].'; ';
        $product_table_body_style .= 'font-style: '.$product_table_set['body']['fontStyle'].'; ';
        $product_table_body_style .= 'font-size: '.$product_table_set['body']['fontSize'].'px; ';
        $product_table_body_style .= 'text-align: '.$product_table_set['body']['aligns'].'; ';
        $product_table_body_style .= 'color: '.$product_table_set['body']['fontColor'].'; ';
        $product_table_body_style .= 'background-color: '.$product_table_set['body']['bgcolor'].'; ';
        $product_table_body_style .= 'border-color: '.$product_table_set['body']['borderColor'].';';

        //footer properties
        $iv_footer_set = $invoice_template['footer'];
        $iv_footer_style = 'font-family: '.strtolower( str_replace( ' ', '', $iv_footer_set['fontFamily'] ) ).'; ';
        $iv_footer_style .= 'font-weight: '.$iv_footer_set['fontWeight'].'; ';
        $iv_footer_style .= 'font-style: '.$iv_footer_set['fontStyle'].'; ';
        $iv_footer_style .= 'font-size: '.$iv_footer_set['fontSize'].'px; ';
        $iv_footer_style .= 'text-align: '.$iv_footer_set['aligns'].'; ';
        $iv_footer_style .= 'color: '.$iv_footer_set['color'].';';

        //html code for pdf
        $html = "<!DOCTYPE html>
        <html ".($rtl ? "dir='rtl'" : '' ).">
            <head>
                <meta charset='utf-8'>
                <title>".$this->invoice_name."</title>
                <style>
					@page :first {
                        margin-top: 0;
                    }
                    body {
                        position: relative;
                        font-family: ".$fontfamily.";
                    }
                    p {
                        line-height: 1.6;
                        margin: 0;
                        padding: 0;
                    }
                    h4 {
                        line-height: 1.8;
                        margin: 0 0 4px;
                        padding: 0;
                        color: ".$selected_color.";
                    }
                    h2 {
                        margin: 0;
                        padding: 0;
                        line-height: 34px;
                        color: ".$selected_color.";
                    }
                    .invoice_wrapper {
                        width: 93%;
                        padding: 20px 3.5% 0;
                        border-top: 7px solid ".$selected_color.";
                        background: #ffffff; ";
                        if( count( $order_ids ) > 1 ):
                            $html .= "page-break-after: always;";
                        endif;
                        $html .= "
                    }
                    .top_sec {
                        width: 100%; 
                        clear:both;
                        margin-top: 14px;
                        margin-bottom: 20px;
                    }
                    .logowrap {
                        float:left;
                        width: 33.333%;
                    }
                    .logowrap img {
                        max-width: 100%;
                    }
                    .logowrap p {
                        width: 100%
                    }
                    .from_address_wrap {
                        float:left; 
                        width: 62.666%;
                        padding-left: 4%;
                    }
                    .from_address_inner {
                        width: 100%;
                        clear: both;
                    }
                    .from_address_inner p {
                        float: left;
                        width: 49%;
                        padding-left: 0%;
                        padding-right: 1%;
                    }
                    .invoice_midsec_wrap {
                        width: 100%; 
                        clear:both;
                        margin-bottom: 30px;
                    }
                    .invoice_main_info {
                        float:left; 
                        width: 33.333%;
                    }
                    .billaddress_wrap {
                        float:left; 
                        width: 29.333%;
                        padding-left: 4%;
                    }
                    .shipping_address_wrap {
                        float:left; 
                        width: 29.333%;
                        padding-left: 4%;
                    }
                    .invoice_bottom_wrap {
                        width: 100%;
                    }
                    .invoice_prdlist_wrap {
                        width: 100%;
                    }
                    .invoice_prdlist_table {
                        width: 100%;
                        border-collapse: collapse;
                    }
                    .invoice_prdlist_table th {
                        padding: 10px;
                        border-top-width: 2px;
                        border-top-style: solid;
                    }
                    .invoice_prdlist_table td {
                        padding: 10px;
                        border-bottom-width: 1px;
                        border-bottom-style: solid;
                    }
                    .invoice_prdsubtbl_wrap {
                        width: 100%;
                        clear: both;
                        margin-top: 5px;
                    }
                    .invoice_prdlist_subtable {
                        width: 240px;
                        padding-right: 0px;
						border-collapse: collapse;
                    }
                    .invoice_prdlist_subtable td {
                        padding: 5px 10px;
                        border-bottom-width: 0px;
                    }
                    .invoice_prdlist_subtable tr:nth-child(6) td {
                        padding-bottom: 10px;
                    }
                    .invoice_prdlist_subtable tr:nth-child(7) td {
                        border-top-style: solid;
                        border-top-width: 1px;
                        padding-top: 10px;
                    }
                    .invoice_footer_wrap {
                        width: 100%;
                        clear: both;
                        margin-bottom: 12px;
                        margin-top: 6px;
                    }
                    .invoice_footer_wrap p {
                        line-height: 24px;
                    }
                    .doc_rtl .logowrap {
                        float: right;
                    }
                    .doc_rtl .from_address_wrap {
                        float: right;
                        padding-left: 0%;
                        padding-right: 4%;
                    }
                    .doc_rtl .from_address_inner p {
                        float: left;
                        padding-right: 1%;
                        padding-left: 0%;
                    }
                    .doc_rtl .invoice_main_info {
                        float: right; 
                    }
                    .doc_rtl .billaddress_wrap {
                        float: right; 
                        padding-left: 0%;
                        padding-right: 4%;
                    }
                    .doc_rtl .shipping_address_wrap {
                        float: right;
                        padding-left: 0%;
                        padding-right: 4%;
                    }
                    .doc_rtl .invoice_prdlist_subtable {
                        width: 215px;
                        padding-right: 0px;
                    }
                    .doc_rtl .invoice_prdlist_subtable td:nth-child(2){
                        padding-left: 0px;
                    } ";
                    if( isset( $this->invoice_settings['customCss'] ) && $this->invoice_settings['customCss'] != '' ):
                        $html .= $this->invoice_settings['customCss'];
                    endif;
                $html .= "</style>
            </head>
            <body ".($rtl ? "dir='rtl'" : '' ).">";
                if( $action != 'inv_sample' ) {
                    $count = 1;
                    foreach( $order_ids as $order_id ):
                        $breake_avoid = '';
                        // get order details
                        $order_data = apifw_get_order_detail( $order_id );
                        $order_prd_infos = $order_data['items'];
                        // fee
                        $order_fee = '0.00';
                        $fee_lines = $order_data['fee_lines'];
                        if( $fee_lines ) {
                            foreach( $fee_lines as $f ) {
                                $t = $f['total'] + $f['total_tax'];
                                $order_fee = $order_fee + $t;
                            }
                        }
                        $order_fee = wc_format_decimal( $order_fee );

                        //invoice number
                        $invoice_no = $this->get_invoice_number( $order_id );

                        //invoice date
                        $invoice_date = $this->get_invoice_date( $order_id, $action );

                        // avoiding page breake issue for last item
                        if( $count == count($order_ids) ){
                            $breake_avoid = 'page-break-after: avoid;';
                        }

                        // template html
                        $html .= "<div class='invoice_wrapper ".($rtl ? "doc_rtl" : "" )."' style='".$breake_avoid."'>
                            <div class='top_sec'>";
                                if( $logo['status'] == true ):
                                    $html .= "<div class='logowrap'>";
                                        if( $logo['display'] == 'Company Logo' ):
                                            $html .= "<img src='".$company_logo."' style='".$logo_img_style."' alt='".$company_name."'/>";
                                        else:
                                            $html .= "<h2 style='".$logo_name_style."'>".$company_name."</h2>";
                                        endif;
                                        if( $logo['extra']['content'] != '' && $logo['extra']['content'] != ' ' ):
                                            $html .= "<p style='".$logo_extra_cont_style."'>".__($logo['extra']['content'], 'woocommerce-order-print')."</p>";
                                        endif;
                                    $html .= "</div>";
                                endif;
                                if( $from_address['status'] == true ):
                                    $html .= "<div class='from_address_wrap'>";
                                        if( $from_address['title']['value'] != '' && $from_address['title']['value'] != ' ' ):
                                            $html .= "<h4 style='".$from_address_title_style."'>".__( $from_address['title']['value'], 'woocommerce-order-print' )."</h4>";
                                        endif;
                                        if( $from_address['visbility']['sender'] == true ):
                                            $html .= "<p style='".$from_address_content_style."'>".$sender_name."</p>";
                                        endif;
                                        $html .= "<div class='from_address_inner'>";
                                            if( $from_address['visbility']['addr1'] == true ):   
                                                $html .= "<p style='".$from_address_content_style."'>".$sender_addr1."</p>";
                                            endif;
                                            if( $from_address['visbility']['addr2'] == true ):
                                                $html .= "<p style='".$from_address_content_style."'>".$sender_addr2."</p>";
                                            endif;
                                            if( $from_address['visbility']['city'] == true ):
                                                $html .= "<p style='".$from_address_content_style."'>".$sender_city."</p>";
                                            endif;
                                            if( $from_address['visbility']['postCode'] == true ):
                                                $html .= "<p style='".$from_address_content_style."'>".$sender_postal_code."</p>";
                                            endif;
                                            if( $from_address['visbility']['country'] == true ):
                                                $html .= "<p style='".$from_address_content_style."'>".$sender_country."</p>";
                                            endif;
                                            if( $from_address['visbility']['email'] == true ):
                                                $html .= "<p style='".$from_address_content_style."'>".$sender_email."</p>";
                                            endif;
                                            if( $from_address['visbility']['phone'] == true ):
                                                $html .= "<p style='".$from_address_content_style."'>".$sender_number."</p>";
                                            endif;
                                            if( $from_address['visbility']['vat'] == true ):
                                                $html .= "<p style='".$from_address_content_style."'>".__($from_address['vatLabel'], 'woocommerce-order-print')." ".$tax_reg_no."</p>";
                                            endif;
                                        $html .= "</div>
                                    </div>";
                                endif;
                            $html .= "</div>
                            <div class='invoice_midsec_wrap'>
                                <div class='invoice_main_info'>";
                                    if( $invoice_number_set['status'] == true ):
                                        $html .= "<p style='".$invoice_number_style."'>";
                                        if( $invoice_number_set['label'] != '' && $invoice_number_set['label'] != ' '):
                                            $html .= "<span style='".$invoice_number_title_style."'>".__($invoice_number_set['label'], 'woocommerce-order-print')." </span>";
                                        endif;
                                        $html .= $invoice_no."</p>";
                                    endif;
                                    if( $invoice_date_set['status'] == true ):
                                        $html .= "<p style='".$invoice_date_style."'>";
                                        if( $invoice_date_set['label'] != '' && $invoice_date_set['label'] != ' '):
                                            $html .= "<span style='".$invoice_date_title_style."'>".__($invoice_date_set['label'], 'woocommerce-order-print')." </span>";
                                        endif;
                                        $html .= $invoice_date."</p>";
                                    endif;
                                    if( $order_date_set['status'] == true ):
                                        $html .= "<p style='".$order_date_style."'>";
                                        if( $order_date_set['label'] != '' && $order_date_set['label'] != ' '):
                                            $html .= "<span style='".$order_date_title_style."'>".__($order_date_set['label'], 'woocommerce-order-print')." </span>";
                                        endif;
                                        $date = date_create( $order_data['created_at'] );
                                        $html .= date_format( $date, $order_date_set['format'] )."</p>";
                                    endif;
                                    if( $order_no_set['status'] == true ):
                                        $html .= "<p style='".$order_no_style."'>";
                                        if( $order_no_set['label'] != '' && $order_no_set['label'] != ' '):
                                            $html .= "<span style='".$order_no_title_style."'>".__($order_no_set['label'], 'woocommerce-order-print')." </span>";
                                        endif;
                                        $html .= $order_data['order_number']."</p>";
                                    endif;
                                    if( $payment_method_set['status'] == true ):
                                        $html .= "<p style='".$payment_method_style."'>";
                                        if( $payment_method_set['label'] != '' && $payment_method_set['label'] != ' '):
                                            $html .= "<span style='".$payment_method_title_style."'>".__($payment_method_set['label'], 'woocommerce-order-print')." </span>";
                                        endif;
                                        $html .= $order_data['payment_details']['method_title']."</p>";
                                    endif;
                                    if( $shipping_method_set['status'] == true ):
                                        $html .= "<p style='".$shipping_method_style."'>";
                                        if( $shipping_method_set['label'] != '' && $shipping_method_set['label'] != ' '):
                                            $html .= "<span style='".$shipping_method_title_style."'>".__($shipping_method_set['label'], 'woocommerce-order-print')." </span>";
                                        endif;
                                        $html .= $order_data['shipping_method'] ? $order_data['shipping_method']: 'Free'."</p>";
                                    endif;
                                    if( $customer_note_set['status'] == true && $order_data['customer_note'] != '' ):
                                        $html .= "<p style='".$customer_note_style."'>";
                                        if( $customer_note_set['label'] != '' && $customer_note_set['label'] != ' '):
                                            $html .= "<span style='".$customer_note_title_style."'>".__($customer_note_set['label'], 'woocommerce-order-print')." </span>";
                                        endif;
                                        $html .= $order_data['customer_note']."</p>";
                                    endif;
                                    $html .= "
                                </div>";
                                if( $billing_addr_set['status'] == true ):
                                    $html .= "<div class='billaddress_wrap'>";
                                        if( $billing_addr_set['title']['value'] != '' && $billing_addr_set['title']['value'] != ' ' ):
                                            $html .= "<h4 style='".$billing_addr_title_style."'>".__($billing_addr_set['title']['value'], 'woocommerce-order-print')."</h4>";
                                        endif;
                                        $html .= "<div>
                                            <p style='".$billing_addr_content_style."'>";
                                                if( $order_data['billing_address']['first_name'] != '' ):
                                                    $html .= $order_data['billing_address']['first_name'];
                                                endif;
                                                if( $order_data['billing_address']['last_name'] != '' ):
                                                    $html .= " ".$order_data['billing_address']['last_name']."<br/>";
                                                endif;
                                                if( $order_data['billing_address']['company'] != '' ):
                                                    $html .= $order_data['billing_address']['company']."<br/>";
                                                endif;
                                                if( $order_data['billing_address']['address_1'] != '' ):
                                                    $html .= $order_data['billing_address']['address_1']."<br/>";
                                                endif;
                                                if( $order_data['billing_address']['address_2'] != '' ):
                                                    $html .= $order_data['billing_address']['address_2']."<br/>";
                                                endif;
                                                if( $order_data['billing_address']['city'] != '' ):
                                                    $html .= $order_data['billing_address']['city'].", ";
                                                endif;
                                                if( $order_data['billing_address']['postcode'] != '' ):
                                                    $html .= $order_data['billing_address']['postcode']."<br/>";
                                                endif;
                                                if( $order_data['billing_address']['formated_state'] != '' ):
                                                    $html .= $order_data['billing_address']['formated_state'].", ";
                                                endif;
                                                if( $order_data['billing_address']['formated_country'] != '' ):
                                                    $html .= $order_data['billing_address']['formated_country']."<br/>";
                                                endif;
                                                if( $order_data['billing_address']['email'] != '' ):
                                                    $html .= $order_data['billing_address']['email']."<br/>";
                                                endif;
                                                if( $order_data['billing_address']['phone'] != '' ):
                                                    $html .= $order_data['billing_address']['phone'];
                                                endif;
                                            $html .= "</p>
                                        </div>
                                    </div>";
                                endif;
                                if( $shipping_addr_set['status'] == true ):
                                    $html .= "<div class='shipping_address_wrap'>";
                                        if( $shipping_addr_set['title']['value'] != '' && $shipping_addr_set['title']['value'] != ' ' ):
                                            $html .= "<h4 style='".$shipping_addr_title_style."'>".__($shipping_addr_set['title']['value'], 'woocommerce-order-print')."</h4>";
                                        endif;
                                        $html .= "<div>
                                            <p style='".$shipping_addr_content_style."'>";
                                                if( $order_data['shipping_address']['first_name'] != '' ):
                                                    $html .= $order_data['shipping_address']['first_name'];
                                                endif;
                                                if( $order_data['shipping_address']['last_name'] != '' ):
                                                    $html .= " ".$order_data['shipping_address']['last_name']."<br/>";
                                                endif;
                                                if( $order_data['shipping_address']['company'] != '' ):
                                                    $html .= $order_data['shipping_address']['company']."<br/>";
                                                endif;
                                                if( $order_data['shipping_address']['address_1'] != '' ):
                                                    $html .= $order_data['shipping_address']['address_1']."<br/>";
                                                endif;
                                                if( $order_data['shipping_address']['address_2'] != '' ):
                                                    $html .= $order_data['shipping_address']['address_2']."<br/>";
                                                endif;
                                                if( $order_data['shipping_address']['city'] != '' ):
                                                    $html .= $order_data['shipping_address']['city'].", ";
                                                endif;
                                                if( $order_data['shipping_address']['postcode'] != '' ):
                                                    $html .= $order_data['shipping_address']['postcode']."<br/>";
                                                endif;
                                                if( $order_data['shipping_address']['formated_state'] != '' ):
                                                    $html .= $order_data['shipping_address']['formated_state'].", ";
                                                endif;
                                                if( $order_data['shipping_address']['formated_country'] != '' ):
                                                    $html .= $order_data['shipping_address']['formated_country'];
                                                endif;
                                                if( $order_data['shipping_address']['phone'] != '' ):
                                                    $html .= "<br/>".$order_data['shipping_address']['phone'];
                                                endif;
                                            $html .= "</p>
                                        </div>
                                    </div>";
                                endif;
                            $html .= "</div>";
                            if( $product_table_set['status'] == true ):
                                $html .= "<div class='invoice_bottom_wrap'>
                                    <div class='invoice_prdlist_wrap'>
                                        <table class='invoice_prdlist_table'>
                                            <thead>
                                                <tr>";
                                                    foreach( $product_table_elements as $elements ):
                                                        if( $elements['status'] == true ):
                                                            $html .= "<th style='".$product_table_head_style."'>".__($elements['label'], 'woocommerce-order-print')."</th>";
                                                        endif;
                                                    endforeach;
                                                $html .= "</tr>
                                            </thead>
                                            <tbody>";
                                                $cart_subtotal = 0;
                                                foreach( $order_prd_infos as $prd ):
                                                    if( ! empty( $this->invoice_settings['freeLineItems'] ) ){
                                                        $html .= "<tr>";
                                                            if( $product_table_elements['sku']['status'] == true ): 
                                                                $html .= "<td style='".$product_table_body_style."' width='11%'>".($prd['sku'] ? $prd['sku']: '--')."</td>";
                                                            endif;
                                                            if( $product_table_elements['productName']['status'] == true ): 
                                                                $html .= "<td style='".$product_table_body_style."' width='22%'>";
                                                                    $html .= $prd['name'];
                                                                    if( $prd['formatted_meta'] ){
                                                                        $html .= "<br/><small>".$prd['formatted_meta']."</small>";
                                                                    }
                                                                $html .= "</td>";
                                                            endif;
                                                            if( $product_table_elements['quantity']['status'] == true ): 
                                                                $html .= "<td style='".$product_table_body_style."'>".$prd['quantity']."</td>";
                                                            endif;
                                                            if( $product_table_elements['price']['status'] == true ): 
                                                                $html .= "<td style='".$product_table_body_style."'>".wc_price($prd['price'], array('currency' => $order_data['currency']))."</td>";
                                                            endif;
                                                            if( $product_table_elements['taxrate']['status'] == true ): 
                                                                $html .= "<td style='".$product_table_body_style."'>".$prd['tax_percent']."%</td>";
                                                            endif;
                                                            if( $product_table_elements['taxtype']['status'] == true ): 
                                                                $html .= "<td style='".$product_table_body_style."'>".$prd['tax_label']."</td>";
                                                            endif;
                                                            if( $product_table_elements['taxvalue']['status'] == true ): 
                                                                $html .= "<td style='".$product_table_body_style."'>".wc_price($prd['subtotal_tax'], array('currency' => $order_data['currency']))."</td>";
                                                            endif;
                                                            $product_total = $prd['subtotal'] + $prd['subtotal_tax'];
                                                            $cart_subtotal = $cart_subtotal + $product_total;
                                                            if( $product_table_elements['total']['status'] == true ): 
                                                                $html .= "<td style='".$product_table_body_style."'>".wc_price($product_total, array('currency' => $order_data['currency']))."</td>";
                                                            endif;
                                                        $html .= "</tr>";
                                                    } else {
                                                        if( (float) $prd['price'] != 0 ){
                                                            $html .= "<tr>";
                                                                if( $product_table_elements['sku']['status'] == true ): 
                                                                    $html .= "<td style='".$product_table_body_style."' width='11%'>".($prd['sku'] ? $prd['sku']: '--')."</td>";
                                                                endif;
                                                                if( $product_table_elements['productName']['status'] == true ): 
                                                                    $html .= "<td style='".$product_table_body_style."' width='22%'>";
                                                                        $html .= $prd['name'];
                                                                        if( $prd['formatted_meta'] ){
                                                                            $html .= "<br/><small>".$prd['formatted_meta']."</small>";
                                                                        }
                                                                    $html .= "</td>";
                                                                endif;
                                                                if( $product_table_elements['quantity']['status'] == true ): 
                                                                    $html .= "<td style='".$product_table_body_style."'>".$prd['quantity']."</td>";
                                                                endif;
                                                                if( $product_table_elements['price']['status'] == true ): 
                                                                    $html .= "<td style='".$product_table_body_style."'>".wc_price($prd['price'], array('currency' => $order_data['currency']))."</td>";
                                                                endif;
                                                                if( $product_table_elements['taxrate']['status'] == true ): 
                                                                    $html .= "<td style='".$product_table_body_style."'>".$prd['tax_percent']."%</td>";
                                                                endif;
                                                                if( $product_table_elements['taxtype']['status'] == true ): 
                                                                    $html .= "<td style='".$product_table_body_style."'>".$prd['tax_label']."</td>";
                                                                endif;
                                                                if( $product_table_elements['taxvalue']['status'] == true ): 
                                                                    $html .= "<td style='".$product_table_body_style."'>".wc_price($prd['subtotal_tax'], array('currency' => $order_data['currency']))."</td>";
                                                                endif;
                                                                $product_total = $prd['subtotal'] + $prd['subtotal_tax'];
                                                                $cart_subtotal = $cart_subtotal + $product_total;
                                                                if( $product_table_elements['total']['status'] == true ): 
                                                                    $html .= "<td style='".$product_table_body_style."'>".wc_price($product_total, array('currency' => $order_data['currency']))."</td>";
                                                                endif;
                                                            $html .= "</tr>";
                                                        }
                                                    }
                                                endforeach;
                                            $html .= "</tbody>
                                        </table>
                                    </div>
                                    <div class='invoice_prdsubtbl_wrap'>
                                        <table class='invoice_prdlist_subtable' ".($rtl ? "align='left'" : "align='right'" ).">
                                            <tbody>
                                                <tr>
                                                    <td style='".$product_table_body_style."'>".__('Subtotal', 'woocommerce-order-print')."</td>
                                                    <td style='".$product_table_body_style."text-align: right;'>".wc_price($cart_subtotal, array('currency' => $order_data['currency']))."</td>
                                                </tr>
                                                <tr>
                                                    <td style='".$product_table_body_style."'>".__('Discount', 'woocommerce-order-print')."</td>
                                                    <td style='".$product_table_body_style."text-align: right;'>"."- ".wc_price($order_data['total_discount'], array('currency' => $order_data['currency']))."</td>
                                                </tr>
                                                <tr>
                                                    <td style='".$product_table_body_style."'>".__('Discount Tax', 'woocommerce-order-print')."</td>
                                                    <td style='".$product_table_body_style."text-align: right;'>"."- ".wc_price($order_data['discount_tax'], array('currency' => $order_data['currency']))."</td>
                                                </tr>
                                                <tr>
                                                    <td style='".$product_table_body_style."'>".__('Shipping', 'woocommerce-order-print')."</td>
                                                    <td style='".$product_table_body_style."text-align: right;'>".wc_price($order_data['total_shipping'], array('currency' => $order_data['currency']))."</td>
                                                </tr>
                                                <tr>
                                                    <td style='".$product_table_body_style."'>".__('Shipping Tax', 'woocommerce-order-print')."</td>
                                                    <td style='".$product_table_body_style."text-align: right;'>".wc_price($order_data['shipping_tax'], array('currency' => $order_data['currency']))."</td>
                                                </tr>
                                                <tr>
                                                    <td style='".$product_table_body_style."'>".__('Fee', 'woocommerce-order-print')."</td>
                                                    <td style='".$product_table_body_style."text-align: right;'>".wc_price($order_fee, array('currency' => $order_data['currency']))."</td>
                                                </tr>
                                                <tr>
                                                    <td style='".$product_table_body_style."'>".__('Total', 'woocommerce-order-print')."</td>
                                                    <td style='".$product_table_body_style."text-align: right;'>".wc_price($order_data['total'], array('currency' => $order_data['currency'])).apply_filters( 'apifw_invoice_deposit', '', $order_id )."</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>";
                            endif;
                            if( $iv_footer_set['status'] == true && $footer_txt != '' ):
                                $html .= "<div class='invoice_footer_wrap'>
                                    <p style='".$iv_footer_style."'>".__($footer_txt, 'woocommerce-order-print')."</p>
                                </div>";
                            endif;
                        $html .= "</div>";
                        $count++;
                    endforeach;
                } else {
                    //invoice number
                    $invoice_no = $this->format_invoice_number( 1 );
                    //order date
                    $order_date_format = $order_date_set['format'];
                    $order_date = date( $order_date_format, strtotime("-1 days") );
                    //invoice date
                    $ivdate_format = $invoice_date_set['format'];
                    // check invoice date same as order date flag
                    if( $this->invoice_settings['invoice_date'] == true ) {
                        $invoice_date = date( $ivdate_format, strtotime("-1 days") );
                    } else {
                        $invoice_date = date_i18n( $ivdate_format );
                    }
                    $html .= "<div class='invoice_wrapper ".($rtl ? "doc_rtl" : "" )."' style='page-break-after:avoid;'>
                        <div class='top_sec'>";
                            if( $logo['status'] == true ):
                                $html .= "<div class='logowrap'>";
                                    if( $logo['display'] == 'Company Logo' ):
                                        $html .= "<img src='".$company_logo."' style='".$logo_img_style."' alt='".$company_name."'/>";
                                    else:
                                        $html .= "<h2 style='".$logo_name_style."'>".$company_name."</h2>";
                                    endif;
                                    if( $logo['extra']['content'] != '' && $logo['extra']['content'] != ' ' ):
                                        $html .= "<p style='".$logo_extra_cont_style."'>".__($logo['extra']['content'], 'woocommerce-order-print')."</p>";
                                    endif;
                                $html .= "</div>";
                            endif;
                            if( $from_address['status'] == true ):
                                $html .= "<div class='from_address_wrap'>";
                                    if( $from_address['title']['value'] != '' && $from_address['title']['value'] != ' ' ):
                                        $html .= "<h4 style='".$from_address_title_style."'>".__( $from_address['title']['value'], 'woocommerce-order-print' )."</h4>";
                                    endif;
                                    if( $from_address['visbility']['sender'] == true ):
                                        $html .= "<p style='".$from_address_content_style."'>".$sender_name."</p>";
                                    endif;
                                    $html .= "<div class='from_address_inner'>";
                                        if( $from_address['visbility']['addr1'] == true ):   
                                            $html .= "<p style='".$from_address_content_style."'>".$sender_addr1."</p>";
                                        endif;
                                        if( $from_address['visbility']['addr2'] == true ):
                                            $html .= "<p style='".$from_address_content_style."'>".$sender_addr2."</p>";
                                        endif;
                                        if( $from_address['visbility']['city'] == true ):
                                            $html .= "<p style='".$from_address_content_style."'>".$sender_city."</p>";
                                        endif;
                                        if( $from_address['visbility']['postCode'] == true ):
                                            $html .= "<p style='".$from_address_content_style."'>".$sender_postal_code."</p>";
                                        endif;
                                        if( $from_address['visbility']['country'] == true ):
                                            $html .= "<p style='".$from_address_content_style."'>".$sender_country."</p>";
                                        endif;
                                        if( $from_address['visbility']['email'] == true ):
                                            $html .= "<p style='".$from_address_content_style."'>".$sender_email."</p>";
                                        endif;
                                        if( $from_address['visbility']['phone'] == true ):
                                            $html .= "<p style='".$from_address_content_style."'>".$sender_number."</p>";
                                        endif;
                                        if( $from_address['visbility']['vat'] == true ):
                                            $html .= "<p style='".$from_address_content_style."'>".__($from_address['vatLabel'], 'woocommerce-order-print')." ".$tax_reg_no."</p>";
                                        endif;
                                    $html .= "</div>
                                </div>";
                            endif;
                        $html .= "</div>
                        <div class='invoice_midsec_wrap'>
                            <div class='invoice_main_info'>";
                                if( $invoice_number_set['status'] == true ):
                                    $html .= "<p style='".$invoice_number_style."'>";
                                    if( $invoice_number_set['label'] != '' && $invoice_number_set['label'] != ' '):
                                        $html .= "<span style='".$invoice_number_title_style."'>".__($invoice_number_set['label'], 'woocommerce-order-print')." </span>";
                                    endif;
                                    $html .= $invoice_no."</p>";
                                endif;
                                if( $invoice_date_set['status'] == true ):
                                    $html .= "<p style='".$invoice_date_style."'>";
                                    if( $invoice_date_set['label'] != '' && $invoice_date_set['label'] != ' '):
                                        $html .= "<span style='".$invoice_date_title_style."'>".__($invoice_date_set['label'], 'woocommerce-order-print')." </span>";
                                    endif;
                                    $html .= $invoice_date."</p>";
                                endif;
                                if( $order_date_set['status'] == true ):
                                    $html .= "<p style='".$order_date_style."'>";
                                    if( $order_date_set['label'] != '' && $order_date_set['label'] != ' '):
                                        $html .= "<span style='".$order_date_title_style."'>".__($order_date_set['label'], 'woocommerce-order-print')." </span>";
                                    endif;
                                    $html .= $order_date."</p>";
                                endif;
                                if( $order_no_set['status'] == true ):
                                    $html .= "<p style='".$order_no_style."'>";
                                    if( $order_no_set['label'] != '' && $order_no_set['label'] != ' '):
                                        $html .= "<span style='".$order_no_title_style."'>".__($order_no_set['label'], 'woocommerce-order-print')." </span>";
                                    endif;
                                    $html .= "36</p>";
                                endif;
                                if( $payment_method_set['status'] == true ):
                                    $html .= "<p style='".$payment_method_style."'>";
                                    if( $payment_method_set['label'] != '' && $payment_method_set['label'] != ' '):
                                        $html .= "<span style='".$payment_method_title_style."'>".__($payment_method_set['label'], 'woocommerce-order-print')." </span>";
                                    endif;
                                    $html .= "Paypal</p>";
                                endif;
                                if( $shipping_method_set['status'] == true ):
                                    $html .= "<p style='".$shipping_method_style."'>";
                                    if( $shipping_method_set['label'] != '' && $shipping_method_set['label'] != ' '):
                                        $html .= "<span style='".$shipping_method_title_style."'>".__($shipping_method_set['label'], 'woocommerce-order-print')." </span>";
                                    endif;
                                    $html .= "FedEx</p>";
                                endif;
                                if( $customer_note_set['status'] == true ):
                                    $html .= "<p style='".$customer_note_style."'>";
                                    if( $customer_note_set['label'] != '' && $customer_note_set['label'] != ' '):
                                        $html .= "<span style='".$customer_note_title_style."'>".__($customer_note_set['label'], 'woocommerce-order-print')." </span>";
                                    endif;
                                    $html .= "This is a customer note</p>";
                                endif;
                                $html .= "
                            </div>";
                            if( $billing_addr_set['status'] == true ):
                                $html .= "<div class='billaddress_wrap'>";
                                    if( $billing_addr_set['title']['value'] != '' && $billing_addr_set['title']['value'] != ' ' ):
                                        $html .= "<h4 style='".$billing_addr_title_style."'>".__($billing_addr_set['title']['value'], 'woocommerce-order-print')."</h4>";
                                    endif;
                                    $html .= "<div>
                                        <p style='".$billing_addr_content_style."'>";
                                            $html .= "John smith <br/>";
                                            $html .= "My Company <br/>";
                                            $html .= "286 Mill Pond St. <br/>";
                                            $html .= "Millville, NJ <br/>";
                                            $html .= "New Rochelle, 10583 <br/>";
                                            $html .= "New York, USA <br/>";
                                            $html .= "ann@exmple.com <br/>";
                                            $html .= "+1-541-754-3010 <br/>";
                                        $html .= "</p>
                                    </div>
                                </div>";
                            endif;
                            if( $shipping_addr_set['status'] == true ):
                                $html .= "<div class='shipping_address_wrap'>";
                                    if( $shipping_addr_set['title']['value'] != '' && $shipping_addr_set['title']['value'] != ' ' ):
                                        $html .= "<h4 style='".$shipping_addr_title_style."'>".__($shipping_addr_set['title']['value'], 'woocommerce-order-print')."</h4>";
                                    endif;
                                    $html .= "<div>
                                        <p style='".$shipping_addr_content_style."'>";
                                            $html .= "John smith <br/>";
                                            $html .= "My Company <br/>";
                                            $html .= "286 Mill Pond St. <br/>";
                                            $html .= "Millville, NJ <br/>";
                                            $html .= "New Rochelle, 10583 <br/>";
                                            $html .= "New York, USA <br/>";
                                        $html .= "</p>
                                    </div>
                                </div>";
                            endif;
                        $html .= "</div>";
                        if( $product_table_set['status'] == true ):
                            $html .= "<div class='invoice_bottom_wrap'>
                                <div class='invoice_prdlist_wrap'>
                                    <table class='invoice_prdlist_table'>
                                        <thead>
                                            <tr>";
                                                foreach( $product_table_elements as $elements ):
                                                    if( $elements['status'] == true ):
                                                        $html .= "<th style='".$product_table_head_style."'>".__($elements['label'], 'woocommerce-order-print')."</th>";
                                                    endif;
                                                endforeach;
                                            $html .= "</tr>
                                        </thead>
                                        <tbody>";
                                            $html .= "<tr>";
                                                if( $product_table_elements['sku']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."' width='11%'>S123</td>";
                                                endif;
                                                if( $product_table_elements['productName']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."' width='23%'>Flying Ninja</td>";
                                                endif;
                                                if( $product_table_elements['quantity']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."'>1</td>";
                                                endif;
                                                if( $product_table_elements['price']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."'>$100.00</td>";
                                                endif;
                                                if( $product_table_elements['taxrate']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."'>15%</td>";
                                                endif;
                                                if( $product_table_elements['taxtype']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."'>VAT</td>";
                                                endif;
                                                if( $product_table_elements['taxvalue']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."'>$15.00</td>";
                                                endif;
                                                if( $product_table_elements['total']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."'>$115.00</td>";
                                                endif;
                                            $html .= "</tr>";
                                            $html .= "<tr>";
                                                if( $product_table_elements['sku']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."' width='11%'>A102</td>";
                                                endif;
                                                if( $product_table_elements['productName']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."' width='23%'>T Shirt</td>";
                                                endif;
                                                if( $product_table_elements['quantity']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."'>2</td>";
                                                endif;
                                                if( $product_table_elements['price']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."'>$100.00</td>";
                                                endif;
                                                if( $product_table_elements['taxrate']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."'>10%</td>";
                                                endif;
                                                if( $product_table_elements['taxtype']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."'>GST</td>";
                                                endif;
                                                if( $product_table_elements['taxvalue']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."'>$20.00</td>";
                                                endif;
                                                if( $product_table_elements['total']['status'] == true ): 
                                                    $html .= "<td style='".$product_table_body_style."'>$220.00</td>";
                                                endif;
                                            $html .= "</tr>";
                                        $html .= "</tbody>
                                    </table>
                                </div>
                                <div class='invoice_prdsubtbl_wrap'>
                                    <table class='invoice_prdlist_subtable' ".($rtl ? "align='left'" : "align='right'" ).">
                                        <tbody>
                                            <tr>
                                                <td style='".$product_table_body_style."'>".__('Subtotal', 'woocommerce-order-print')."</td>";
                                                $html .= "<td style='".$product_table_body_style."text-align: right;'>$335.00</td>
                                            </tr>
                                            <tr>
                                                <td style='".$product_table_body_style."'>".__('Discount', 'woocommerce-order-print')."</td>
                                                <td style='".$product_table_body_style."text-align: right;'>$0.00</td>
                                            </tr>
                                            <tr>
                                                <td style='".$product_table_body_style."'>".__('Discount Tax', 'woocommerce-order-print')."</td>
                                                <td style='".$product_table_body_style."text-align: right;'>$0.00</td>
                                            </tr>
                                            <tr>
                                                <td style='".$product_table_body_style."'>".__('Shipping', 'woocommerce-order-print')."</td>
                                                <td style='".$product_table_body_style."text-align: right;'>$0.00</td>
                                            </tr>
                                            <tr>
                                                <td style='".$product_table_body_style."'>".__('Shipping Tax', 'woocommerce-order-print')."</td>
                                                <td style='".$product_table_body_style."text-align: right;'>$0.00</td>
                                            </tr>
                                            <tr>
                                                <td style='".$product_table_body_style."'>".__('Fee', 'woocommerce-order-print')."</td>
                                                <td style='".$product_table_body_style."text-align: right;'>$0.00</td>
                                            </tr>
                                            <tr>
                                                <td style='".$product_table_body_style."'>".__('Total', 'woocommerce-order-print')."</td>
                                                <td style='".$product_table_body_style."text-align: right;'>$335.00</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>";
                        endif;
                        if( $iv_footer_set['status'] == true && $footer_txt != '' ):
                            $html .= "<div class='invoice_footer_wrap'>
                                <p style='".$iv_footer_style."'>".__($footer_txt, 'woocommerce-order-print')."</p>
                            </div>";
                        endif;
                    $html .= "</div>";
                }
            $html .= "</body>
        </html>";
        //returning html
        return $html;
    }

    /**
     * Returning pdf invoice number
     * @access  public
     * @param order_id
    */
    public function get_invoice_number( $order_id )
    {
        if ( !$order_id )
            return false;

        $invoice_number = get_post_meta( $order_id, $this->_token.'_ord_invoice_no', true );
        if( $invoice_number && $invoice_number != '' ) {
            //invoice number already exisisted
            return $invoice_number;
        } else {
            // finding invoice number
            if( $this->invoice_settings['next_no'] && $this->invoice_settings['next_no'] != '' ) {
                $invoice_number = $this->invoice_settings['next_no'];
            } else {
                $invoice_number = 1;
            }

            // formating invoice number
            $invoice_number = $this->format_invoice_number( $invoice_number );

            //updating next invoice number
            $inv_settings = $this->invoice_settings;
            $new_next_no = $inv_settings['next_no'] + 1;
            $inv_settings['next_no'] = $new_next_no; 
            $new_inv_settings = serialize( $inv_settings );
            update_option( $this->_token.'_invoice_settings', $new_inv_settings );

            //saving order invoice number meta
            add_post_meta( $order_id, $this->_token.'_ord_invoice_no', $invoice_number, true );

            // returning formatted invoice number
            return $invoice_number;
        }
    }

    /**
     * Formating pdf invoice number
     * @access  public
     * @param invoice_number
    */
    public function format_invoice_number( $invoice_number )
    {
        if ( !$invoice_number )
            return false;

        // getting invoice number length
        if( $this->invoice_settings['no_length'] && $this->invoice_settings['no_length'] != '') {
            $invno_length = $this->invoice_settings['no_length'];
            $invoice_number = str_pad( $invoice_number, $invno_length, "0", STR_PAD_LEFT );
        }

        // getting format
        $invoice_number_format = $this->invoice_settings['number_format'];

        // formating invoice number
        if( $invoice_number_format === '[prefix][number][suffix]' ) {
            $prefix = $this->invoice_settings['no_prefix'];
            $suffix = $this->invoice_settings['no_suffix'];
            $invoice_number_formatted = $prefix.$invoice_number.$suffix;
        } elseif( $invoice_number_format === '[prefix][number]' ) {
            $prefix = $this->invoice_settings['no_prefix'];
            $invoice_number_formatted = $prefix.$invoice_number;
        } elseif( $invoice_number_format === '[number][suffix]' ) {
            $suffix = $this->invoice_settings['no_suffix'];
            $invoice_number_formatted = $invoice_number.$suffix;
        } else {
            $invoice_number_formatted = $invoice_number;
        }

        return $invoice_number_formatted;
    }

    /**
     * Returning invoice date
     * @access  public
     * @param order_id
     * @param action
    */
    public function get_invoice_date( $order_id, $action )
    {
        if ( !$order_id )
            return false;

        $ivdate_format = $this->pdf_template['invoiceDate']['format'];
        // new order or update order case
        if( $action == 'inv_save' ) {
            if( $this->invoice_settings['invoice_date'] == true ) { // order date as invoice date check
                $order_data = apifw_get_order_detail( $order_id );
                $order_timestamp = $order_data['updated_at'];
                $date = date_create( $order_timestamp );
                $ivdate = date_format( $date, $ivdate_format );
            } else {
                $ivdate = date_i18n( $ivdate_format );
            }
            //saving order invoice date meta
            if( !get_post_meta( $order_id, $this->_token.'_ord_invoice_date', true ) ) {
                add_post_meta( $order_id, $this->_token.'_ord_invoice_date', $ivdate, true );
            } else {
                update_post_meta( $order_id, $this->_token.'_ord_invoice_date', $ivdate );
            }
        } else {
            $current_ivdate = get_post_meta( $order_id, $this->_token.'_ord_invoice_date', true );
            if( $current_ivdate && $current_ivdate != '' ) {
                $current_ivdate = str_replace( "/", "-", $current_ivdate );
                $date = date_create( $current_ivdate );
                if( $date ){
                    $ivdate = date_format( $date, $ivdate_format );
                } else {
                    $ivdate = $current_ivdate;
                }
            } else {
                if( $this->invoice_settings['invoice_date'] == true ) { // order date as invoice date check
                    $order_data = apifw_get_order_detail( $order_id );
                    $order_timestamp = $order_data['updated_at'];
                    $date = date_create( $order_timestamp );
                    $ivdate = date_format( $date, $ivdate_format );
                } else {
                    $ivdate = date_i18n( $ivdate_format );
                }
                //saving order invoice date meta
                add_post_meta( $order_id, $this->_token.'_ord_invoice_date', $ivdate, true );
            }
        }

        return $ivdate;
    }

    /**
     * Adding bttons to front end user dashboard orders detail pages
     * @param order object
    */
    public function add_frontend_action_btns( $order )
    {
        // Avoiding errors and problems
        if ( ! is_a( $order, 'WC_Order' ) )
            return;

        $order_id = $order->get_id();

        // only if invoice number exisist
		if( get_post_meta( $order_id, $this->_token.'_ord_invoice_no', true ) ) {
            // adding btn
            $orderids_array = array( $order_id );
            $orderid_enc = urlencode( serialize( $orderids_array ) );
            $inv_preview_url = get_home_url().'?apifw_document=true&order_id='.$orderid_enc.'&type=invoice&action=preview';
            $inv_download_url = get_home_url().'?apifw_document=true&order_id='.$orderid_enc.'&type=invoice&action=download';
            $inv_buttons = '<div class="apifw_frnd_btn_wrap">';
            $inv_buttons .= '<a class="apifw_frntd_ivp_btn woocommerce-button button" target="_blank" href="'.$inv_preview_url.'">'.__( 'Print Invoice', 'woocommerce-order-print' ).'</a>';
            $inv_buttons .= '<a class="apifw_frntd_ivd_btn woocommerce-button button" target="_blank" href="'.$inv_download_url.'">'.__( 'Download Invoice', 'woocommerce-order-print' ).'</a><br/>';
            $inv_buttons .= '</div>';
            echo $inv_buttons;
        }
    }

    /**
     * Adding buttons to front end my Orders Table
     * @param order object
     * @param actions array
    */
    public function add_my_account_order_action_btns( $actions, $order )
    {
        // Avoiding errors and problems
        if ( ! is_a( $order, 'WC_Order' ) )
            return;

        $order_id = $order->get_id();
        // only if invoice number exisist
		if( get_post_meta( $order_id, $this->_token.'_ord_invoice_no', true ) ) {
            $orderids_array = array( $order_id );
            $orderid_enc = urlencode( serialize( $orderids_array ) );
            $inv_preview_url = get_home_url().'?apifw_document=true&order_id='.$orderid_enc.'&type=invoice&action=preview';
            $actions['apifw_myacnt_invoice_btn'] = array(
                'url'  => $inv_preview_url,
                'name' => __( 'Invoice', 'woocommerce-order-print' ),
            );
        }

        return $actions;
    }

    /**
     * Adding PDF invoice attachment in order mails
    */
    public function add_attachment_order_email( $attachments, $email_id, $order )
    {
        // Avoiding errors and problems
        if ( ! is_a( $order, 'WC_Order' ) || ! isset( $email_id ) ) {
            return $attachments;
        }

        $order_id = $order->get_id();
        // checking order status matching to invoice settings
        $order_status = 'wc-'.$order->get_status();
        $status_list = $this->invoice_settings['order_status'];
        $pdf_gen_flag = false;
        foreach( $status_list as $status ) {
            if( $status['value'] == $order_status ) {
                $pdf_gen_flag = true;
                break;
            }
        }

        // gen invoice free order case
        if( empty( $this->invoice_settings['freeOrder'] ) && ( (float) $order->get_total() == 0 ) ){
            $pdf_gen_flag = false;
        }

        // only attach if admin select order status in invoice settings
		if( $pdf_gen_flag === true ) {
            //getting order invoice and attaching to email
            $order_ids = array( $order_id );
            $invoice_pdf_path = $this->invoice_pdf_gen_handler( $order_ids, 'inv_save' );

            if( $invoice_pdf_path && $invoice_pdf_path != '' ) {
                $attachments[] = $invoice_pdf_path;
            }
        }

        return $attachments;
    }

    /**
     * Handling New Order
     * @access  public
     * @param order_id
    */
    public function handle_new_order( $order_id )
	{
		if( !$order_id ){
        	return;
        }

        $order = wc_get_order( $order_id );

    	// Allow code execution only once 
    	if( !get_post_meta( $order_id, $this->_token.'_thankyou_action_done', true ) ) {
    		// Get WC_Order status
            $order_status = get_post_status( $order_id );
            $status_list = $this->invoice_settings['order_status'];
            $pdf_gen_flag = false;
            foreach( $status_list as $status ) {
                if( $status['value'] == $order_status ) {
                    $pdf_gen_flag = true;
                    break;
                }
            }

            // gen invoice free order case
            if( empty( $this->invoice_settings['freeOrder'] ) && ( (float) $order->get_total() == 0 ) ){
                $pdf_gen_flag = false;
            }

            // only generate if user select status in invoice settings
			if( $pdf_gen_flag === true ) {
                //invoice number generation call
                $this->get_invoice_number( $order_id );
            }
            
        	// Prevention to fire thankyou hook multiple times
            add_post_meta( $order_id, $this->_token.'_thankyou_action_done', true, true );
        }
    }

    /**
     * Handling Order Update
     * @access  public
     * @param order_id
     * @param old_status
     * @param new_status
    */
    public function handle_order_update( $order_id, $old_status, $new_status )
    {
        if( !$order_id ){
        	return;
        }

        // Get WC_Order status
        $order_status = get_post_status( $order_id );
        $status_list = $this->invoice_settings['order_status'];
        $pdf_gen_flag = false;
        foreach( $status_list as $status ) {
            if( $status['value'] == $order_status ) {
                $pdf_gen_flag = true;
                break;
            }
        }

        // gen invoice free order case
        if( empty( $this->invoice_settings['freeOrder'] ) && ( (float) $order->get_total() == 0 ) ){
            $pdf_gen_flag = false;
        }

        // only execute if user select status in invoice settings
        if( $pdf_gen_flag === true ) {
            // invoice number and date generation call
            $this->get_invoice_number( $order_id );
            $this->get_invoice_date( $order_id, 'inv_save' );
        }    
    }
}