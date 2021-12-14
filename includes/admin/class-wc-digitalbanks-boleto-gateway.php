<?php
namespace WC_DigitalBanks;

use WC_Customer;
use WC_Payment_Gateway;

class Boleto_Gateway extends WC_Payment_Gateway{

  private $invoice_table_name;

  public function __construct(){

    global $wpdb;

    $this->id = 'digitalbanks_boleto';
    $this->method_title = 'Digital Banks - Boleto';
    $this->method_description = 'Integração de boleto DigitalBanks com woocommerce';
    $this->title        = $this->get_option( 'title' );
    $this->description  = $this->get_option( 'description' );
    $this->instructions = $this->get_option( 'instructions', $this->description );
    $this->token_api_invoice = $this->get_option( 'token_api_invoice' );
    $this->discountLimitDateMonth = $this->get_option('discountLimitDateMonth');
    $this->invoice_table_name = $wpdb->prefix . "wc_digitalbanks_invoices";
    $this->clientWebhook = $this->get_option( 'clientWebhook' );
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

    $page = "";
    $order_id = $order->get_id();
    global $wpdb;
    $invoice = $wpdb->get_row("SELECT * FROM {$this->invoice_table_name} WHERE wc_order = $order_id");
    if($invoice ){
      if($order->has_status('on-hold')){
        $order->update_status( 'wc-processing', 'Pedido em espera');
      }

      if($order->has_status('processing')){
        $page .= "<h3>O pedido está aguardando pagamento</h3>";
        $page .= "<p>O pedido será processado assim que o pagamento for identificado</p>";
        $page .= "<br><br>";
        $page .= "<p><strong>Chave do boleto:</strong></p>";
        $page .= "<p>{$invoice->barcode}</p>";
        $page .= "<a href='{$invoice->url}' target='_blank'><button>URL do Boleto</button></a>";
        $page .= "<br><br>";
        $page .= "<br><br>";
      }else if ( $order->is_paid() ) {
        $page .= "<h3>O pagamento já foi efetuado</h3>";
        /*
        if(!$order->is_paid()){
          $order->update_status( 'wc-completed', 'Pedido já foi pago');
        }
        */
      }

    }else{
      $page = "<h3>Não foi possível encontrar o seu boleto.</h3>";
    }


    return $page;
  }

  public function config_form_fields() {

    $protocol = stripos($_SERVER['SERVER_PROTOCOL'],'https') === 0 ? 'https://' : 'http://';
    $server_host = $protocol . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'];

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

      'clientWebhook' => array(
        'title' => _('clientWebhook URL', 'wc-digitalbanks-boleto-gateway'),
        'type'        => 'text',
        'description' => __( 'URL para retorno da API de pagamento (Processamento).', 'wc-digitalbanks-boleto-gateway' ),
        'default'     => __( $server_host . '/wp-json/wc-digitalbanks-boleto/v1/clientWebhook', 'wc-digitalbanks-boleto-gateway' ),
      ),

      'discountLimitDateMonth' => array(
        'title' => _('Pagamento Limite em Meses', 'wc-digitalbanks-boleto-gateway'),
        'type'        => 'number',
        'description' => __( 'Quantidade de meses para pagamento do boleto.', 'wc-digitalbanks-boleto-gateway' ),
        'default'     => 1,
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
    global $wpdb;

    $order = wc_get_order( $order_id );
    if(wc_get_payment_gateway_by_order($order)->id != $this->id){
      return NULL;
    }

    $params = [];
    $date_time = strtotime(date("Y-m-d"));
    $discountLimitDateMonth = date("Y-m-d", strtotime("+".$this->discountLimitDateMonth." month", $date_time));;

    $params['value'] = $order->get_total();
    $customer_id = $order->get_customer_id();
    if($customer_id){
      $order_customer = new WC_Customer( $customer_id );

      if($order_customer->get_meta('billing_cpf') && strlen($order_customer->get_meta('billing_cpf'))){
        $params['payerTaxId'] = $order_customer->get_meta('billing_cpf');
      }else if($order_customer->get_meta('billing_cnpj') && strlen($order_customer->get_meta('billing_cnpj'))){
        $params['payerTaxId'] = $order_customer->get_meta('billing_cnpj');
      }
      $params['payerPostalCode'] = $order_customer->get_billing_postcode();
      $params['payerLocationNumber'] = $order_customer->get_meta( 'billing_number' );
      $params['customIssuerName'] = $order_customer->get_first_name() . ' ' . $order_customer->get_last_name();;
      $params['payerNeighborhood'] = $order_customer->get_meta( 'billing_neighborhood' );
      $params['payerStreet'] = $order_customer->get_billing_address_1();;
      $params['payerCity'] = $order_customer->get_billing_city();
      $params['payerState'] = $order_customer->get_billing_state();
    }else{
      if($order->get_meta('_billing_cpf') && strlen($order->get_meta('_billing_cpf'))){
        $params['payerTaxId'] = $order->get_meta('_billing_cpf');
      }else if($order->get_meta('_billing_cnpj') && strlen($order->get_meta('_billing_cnpj'))){
        $params['payerTaxId'] = $order->get_meta('_billing_cnpj');
      }

      $params['payerPostalCode'] = $order->get_billing_postcode();
      $params['payerLocationNumber'] = $order->get_meta( '_billing_number' );
      $params['customIssuerName'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();;
      $params['payerNeighborhood'] = $order->get_meta( '_billing_neighborhood' );
      $params['payerStreet'] = $order->get_billing_address_1();;
      $params['payerCity'] = $order->get_billing_city();
      $params['payerState'] = $order->get_billing_state();
    }

    $params['payerComplement'] = 'Teste';
    $params['clientWebhook'] = $this->clientWebhook;

    $this->dueDate = 1;
    $dueDate = date("Y-m-d", strtotime("+".$this->dueDate." month", $date_time));
    $params['dueDate'] = $dueDate;

    $params['customBodyText'] = 'Referente ao pedido WC : ' . $order_id;

    //$params['discountType'] = 3;

    //$params['discountLimitDate'] = $discountLimitDateMonth;
    //$params['discountPercentAmount'] = 0;
    //$params['discountAmount'] = $order->get_discount_total();
    //$params['discountFixedAmount'] = $order->get_discount_total();
    /*
    $params['interestType'] = 1;
    $params['interestPercent'] = 0;
    $params['interestAmount'] = 2;
    $params['fineType'] = 1;
    $params['finePercent'] = 0;
    $params['fineAmount'] = 0;
    $params['fineDate'] = '2021-08-09';
    */

    $post_arr = [];
    foreach ($params as $key => $value) {
      $post_arr[] = "$key=$value";
    }
    $post = urlencode(implode('&', $post_arr));
    //$post = implode('&', $post_arr);
    //echo $post;
    //die();

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.digitalbanks.com.br/api/public/v2/invoice',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POST =>  1,
      CURLOPT_POSTFIELDS => $post,
      CURLOPT_HTTPHEADER => array(
        'Authorization: '.$this->token_api_invoice,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept-Language: pt-BR'
      ),
    ));

    $response = curl_exec($curl);
    if(!$response){
      wc_add_notice( 'Algo deu errado. Tente novamente mais tarde', 'error');
      return NULL;
    }
    $response_obj = json_decode($response);
    if(!$response_obj){
      wc_add_notice( 'Algo deu errado. Tente novamente mais tarde', 'error');
      return NULL;
    }
    if($response_obj->has_errors != false){
      wc_add_notice( $response_obj->message, 'error' );
      //var_dump($response_obj);
      //die();
      foreach ($response_obj->errors as $key => $value) {
        wc_add_notice( $value->description, 'error' );
      }
      return NULL;
    }

    $result = $response_obj->result;

    $wpdb->insert($this->invoice_table_name, [
      'invoice_id' => $result->id,
      'document_number' => $result->details->document_number,
      'barcode' => $result->details->barcode,
      'url' => $result->details->url,
      'wc_order' => $order_id,
      'status' => $result->status,
      'response' => $response
    ]);

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
