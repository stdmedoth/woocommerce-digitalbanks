<?php
namespace WC_DigitalBanks;

use WC_Payment_Gateway;

class WC_DigitalBanks_Boleto_Gateway extends WC_Payment_Gateway{

  public function __construct(){
    $this->id = 'digitalbanks_boleto';
    $this->method_title = 'Digital Banks - Boleto';
    $this->method_description = 'Integração de boleto DigitalBanks com woocommerce';
    $this->title        = $this->get_option( 'title' );
    $this->description  = $this->get_option( 'description' );
    $this->instructions = $this->get_option( 'instructions', $this->description );
    $this->token_api_invoice = $this->get_option( 'token_api_invoice' );
    $this->has_fields = true;

    $this->config_form_fields();

    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
    add_action( 'woocommerce_thankyou', array( $this, 'show_boleto_page'), 10, 1 );
    add_filter( 'woocommerce_endpoint_order-received_title', [$this,'thank_you_title'] );
    add_filter( 'woocommerce_thankyou_order_received_text', [$this,'thank_you_body'] , 20, 2 );
  }

  public function thank_you_title( $old_title ){

    return 'Quase lá!';

  }

  public function thank_you_body( $thank_you_title, $order ){

    if(wc_get_payment_gateway_by_order($order)->id != $this->id){
      return NULL;
    }

    $page = "<h3>O pedido está aguardando pagamento</h3>";
    $page .= "<p>O pedido será processado assim que o pagamento for identificado</p>";
    $page .= "<br><br><br><br>";

    return $page;
  }

  /*
  public function payment_fields(){
    class_wc_digitalbanks_boleto_form();
  }
  */

  public function config_form_fields() {

    $this->form_fields = apply_filters( 'wc_digitalbanks_boleto_form_fields', array(

      'enabled' => array(
        'title'   => __( 'Ativar/Desativar', 'wc-digitalbanks-boleto-gateway' ),
        'type'    => 'checkbox',
        'label'   => __( 'Ativar Boleto DigitalBanks', 'wc-digitalbanks-boleto-gateway' ),
        'default' => 'no'
      ),

      'title' => array(
        'title'       => __( 'Nome do Pagamento', 'wc-digitalbanks-boleto-gateway' ),
        'type'        => 'text',
        'description' => __( 'Isso controla o título da forma de pagamento que o cliente vê durante a finalização da compra.', 'wc-digitalbanks-boleto-gateway' ),
        'default'     => __( 'Boleto', 'wc-digitalbanks-boleto-gateway' ),
        'desc_tip'    => true,
      ),

      'description' => array(
        'title'       => __( 'Descrição', 'wc-digitalbanks-boleto-gateway' ),
        'type'        => 'textarea',
        'description' => __( 'Descrição da forma de pagamento que o cliente verá em sua finalização da compra.', 'wc-digitalbanks-boleto-gateway' ),
        'default'     => __( 'Efetue o pagamento Boleto.', 'wc-digitalbanks-boleto-gateway' ),
        'desc_tip'    => true,
      ),
      'token_api_invoice' => array(
        'title'       => __( 'Token da API Invoice', 'wc-digitalbanks-boleto-gateway' ),
        'type'        => 'text',
        'description' => __( 'Token API Invoice fornecido pela DigitalBanks.', 'wc-digitalbanks-boleto-gateway' ),
        'default'     => __( '', 'wc-digitalbanks-boleto-gateway' ),
        'desc_tip'    => true,
      ),
      'instructions' => array(
        'title'       => __( 'Instruções', 'wc-digitalbanks-boleto-gateway' ),
        'type'        => 'textarea',
        'description' => __( 'Instruções que serão adicionadas à página de agradecimento e aos e-mails.', 'wc-digitalbanks-boleto-gateway' ),
        'default'     => '',
        'desc_tip'    => true,
      ),
    ) );
  }

  public function process_payment( $order_id ) {
    $order = wc_get_order( $order_id );
    $order_customer = new WC_Customer( $order->get_customer_id('view') );

    $order->payment_complete();
    $order->update_status( 'on-hold', 'Pedido em espera');
    $order->reduce_order_stock();

    WC()->cart->empty_cart();
    return array(
      'result'    => 'success',
      'redirect'  => $this->get_return_url( $order )
    );

  }

  public function show_boleto_page($order_id) {

    $order = wc_get_order( $order_id );
    if(wc_get_payment_gateway_by_order($order)->id != $this->id){
      return ;
    }

  }

  public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
    if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
      echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
    }
  }
}
?>
