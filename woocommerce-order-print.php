<?php 
/*
 * Plugin Name: WooCommerce Order Print
 * Version: 1.0
 * Description: Print woocommerce orders
 * Author: Deepak Kumar Gupta
 * Author URI: https://cedcoss.com
*/

//While actiave the plugins if we got some error to save the error on txt file

add_action('activated_plugin','my_save_error');
function my_save_error()
{
    file_put_contents(dirname(__file__).'/error_activation.txt', ob_get_contents());
}
define('APIFW_TOKEN', 'apifw');
define('APIFW_VERSION', '1.0');
define('APIFW_FILE', __FILE__);
define('APIFW_STORE_URL', 'https://cedcoss.com');
define('APIFW_PLUGIN_NAME', 'WooCommerce Order Print');
define('APIFW_WP_VERSION', get_bloginfo('version'));
// /defining template saving directory name
if ( !defined('APIFW_TEMPLATE_DIR_NAME') ){
    define('APIFW_TEMPLATE_DIR_NAME', 'apifw_uploads');
}

//defining template saving directory
$upload = wp_upload_dir();
$upload_dir = $upload['basedir'];
$upload_url = $upload['baseurl'];
$upload_dir = $upload_dir.'/'.APIFW_TEMPLATE_DIR_NAME;
$upload_url = $upload_url.'/'.APIFW_TEMPLATE_DIR_NAME;
define('APIFW_UPLOAD_TEMPLATE_DIR', $upload_dir);
define('APIFW_UPLOAD_TEMPLATE_URL', $upload_url);

//defining invoice saving directory
$invoice_dir = APIFW_UPLOAD_TEMPLATE_DIR.'/invoice';
$invoice_url = APIFW_UPLOAD_TEMPLATE_URL.'/invoice';
define('APIFW_UPLOAD_INVOICE_DIR', $invoice_dir);
define('APIFW_UPLOAD_INVOICE_URL', $invoice_url);

//Helpers
require_once(realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'includes/helpers.php');




//Loading Classes
if (!function_exists('APIFW_autoloader')) {

    function APIFW_autoloader($class_name)
    {
        if (0 === strpos($class_name, 'APIFW')) {
            $classes_dir = realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;
            $class_file = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
            require_once $classes_dir . $class_file;
        }
    }

}
spl_autoload_register('APIFW_autoloader');

//Backend UI
if (!function_exists('APIFW')) {
    function APIFW()
    {
        $instance = APIFW_Backend::instance(__FILE__, APIFW_VERSION);
        return $instance;
    }
}

if ( is_admin() ) {
    APIFW();
}

//API
new APIFW_Api();

// calling invoice gen class
new APIFW_Invoice();

// Front end
new APIFW_Front_End( __FILE__, APIFW_VERSION );
?>