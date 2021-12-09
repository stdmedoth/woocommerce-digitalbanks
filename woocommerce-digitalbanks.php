<?php
/*
* Plugin Name: WC Gateway Digital Banks
* Description: Uma integração do WooCommerce com API Digital Banks
* Version: 1.0.0
* Author: João Calisto
*/

defined( 'ABSPATH' ) || exit;

class DigitalBanksPlugin
{

  public function create_tables(){

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $invoice_table_name = $wpdb->prefix . "wc_digitalbanks_invoices";
    $sql = "CREATE TABLE IF NOT EXISTS $invoice_table_name (
      id INT NOT NULL AUTO_INCREMENT,
      invoice_id VARCHAR(40),
      barcode VARCHAR(44),
      document_number INT,
      url VARCHAR(255),
      wc_order INT NOT NULL,
      status INT NOT NULL,
      response TEXT NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

  }

  public function load_js(){
    //wp_enqueue_script('forms', plugin_dir_url( __FILE__ ) . 'assets/js/forms.js');
  }
  public function load(){
    require __DIR__ . '/includes/admin/woocommerce-digitalbanks.php';
    require __DIR__ . '/includes/admin/class-wc-digitalbanks-boleto-gateway.php';
    require __DIR__ . '/includes/checkout/wc-digitalbanks-frontend.php';
    require __DIR__ . '/includes/rest-api/wc-digitalbanks-boleto.php';

    add_action('wp_enqueue_scripts', [$this, 'load_js']);
    add_filter( 'woocommerce_payment_gateways', 'wc_digitalbanks_boleto_add_to_gateways' );
    //add_filter( 'woocommerce_checkout_fields' , 'wc_digitalbanks_override_checkout_fields' );

    $api = new WC_DigitalBanks\Boleto_API();
    add_action( 'rest_api_init', [$api, 'init'] );
  }

}


$d_b_p = new DigitalBanksPlugin();
add_action( 'woocommerce_init', [$d_b_p, 'load']  );
register_activation_hook( __FILE__, [$d_b_p, 'create_tables']  );
