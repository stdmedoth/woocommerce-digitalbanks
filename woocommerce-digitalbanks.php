<?php
/*
* Plugin Name: Pagamento com Digital Banks
* Description: Uma integração do WooCommerce com API Digital Banks
* Version: 1.0.0
* Author: João Calisto
*/

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_init', 'wc_digitalbanks_load_plugin'  );

add_action('wp_enqueue_scripts', 'wc_digitalbanks_load_js');

function wc_digitalbanks_load_plugin(){
  require __DIR__ . '/includes/admin/woocommerce-digitalbanks.php';
  require __DIR__ . '/includes/admin/class-wc-digitalbanks-boleto-gateway.php';
  require __DIR__ . '/includes/checkout/wc-digitalbanks-frontend.php';
  require __DIR__ . '/includes/rest-api/wc-digitalbanks-boleto.php';

  add_filter( 'woocommerce_payment_gateways', 'wc_digitalbanks_boleto_add_to_gateways' );
  //add_filter( 'woocommerce_checkout_fields' , 'wc_digitalbanks_override_checkout_fields' );

  $api = new WC_DigitalBanks\Boleto_API();
	add_action( 'rest_api_init', [$api, 'init'] );
}

function wc_digitalbanks_load_js(){
  //wp_enqueue_script('forms', plugin_dir_url( __FILE__ ) . 'assets/js/forms.js');
}
