<?php
/**
 * Plugin Name: LYpay Payment
 * Plugin URI: https://github.com/mezoghi/wc-lypay-payment
 * Description: LYpay Payment Gateway for WooCommerce created based on user request.
 * Version: 1.0.0
 * Author: mohammed fathi almozoghi
 * Author URI: https://github.com/mezoghi
 * Text Domain: wc-lypay-payment
 * Domain Path: /i18n/languages/
 *
 * @package WC_LYpay_Payment
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Plugin Class if needed, or just function to init.
 */
function wc_lypay_payment_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-gateway-lypay.php';
}
add_action( 'plugins_loaded', 'wc_lypay_payment_init' );

/**
 * Add the gateway to WooCommerce.
 */
function wc_lypay_add_to_gateways( $gateways ) {
	if ( class_exists( 'WC_Gateway_LYpay' ) ) {
		$gateways[] = 'WC_Gateway_LYpay';
	}
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_lypay_add_to_gateways' );
