<?php

/**
 * Plugin Name: CDO - Integração XPay
 * Plugin URI:  https://github.com/hiraygui//xpay-cdo-plugin
 * Author: Gizo Digital
 * Author URI: 
 * Description: Plugin de pagamento do XPay para woocommerce
 * Version: 1.0.0
 * License:     GPL-2.0+
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: xpay-cdo-woocommerce
 * 
 * Class WC_Xpay_Cdo_Gateway file.
 *
 * @package WooCommerce\xpay-cdo-woocommerce
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

//condição verifica se plugin woocommerce está ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

//função permite ativação de plugin
add_action('plugins_loaded', 'xpay_cdo_init', 11);
add_filter('woocommerce_payment_gateways', 'add_to_woo_xpay_cdo_payment_gateway');


/**
 * Construtor da classe
 */
function xpay_cdo_init()
{
	if (class_exists('WC_Payment_Gateway')) {
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-xpay-cdo-gateway.php';
	}
}

/**
 * Função adiciona método de pagamento ao woocommerce
 */
function add_to_woo_xpay_cdo_payment_gateway($gateways){
   $gateways[] = 'WC_Xpay_Cdo_Gateway';
   return $gateways;
}