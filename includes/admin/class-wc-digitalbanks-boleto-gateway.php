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
    $this->invoice_table_name = $wpdb->prefix . "wc_digitalbanks_invoices";
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

    $order_customer = new WC_Customer( $order->get_customer_id('view') );

    $curl = curl_init();
    $params = [];
    $params['value'] = $order->get_total();
    $params['payerTaxId'] = '512.146.788-58';
    $params['payerPostalCode'] = '13067-450';
    $params['payerLocationNumber'] = '18';
    $params['payerComplement'] = '';
    $params['clientWebhook'] = 'http://localhost:8081/wp-json/get_boleto';
    $params['dueDate'] = '2022-01-01';

    $params['customIssuerName'] = 'PADARIA%20DO%20JOAO';
    $params['customBodyText'] = 'Referente ao pedido ABC0000001234';
    $params['payerNeighborhood'] = 'Boa Vista';
    $params['payerStreet'] = 'Marcos Samartine';
    $params['payerCity'] = 'Campinas';
    $params['payerState'] = 'SP';

    $params['discountType'] = 3;
    $params['discountLimitDate'] = '2022-01-01';
    $params['discountPercentAmount'] = '0';
    $params['discountAmount'] = 0.10;
    $params['discountFixedAmount'] = 0.25;
    $params['interestType'] = 1;
    $params['interestPercent'] = 0;
    $params['interestAmount'] = 2;
    $params['fineType'] = 2;
    $params['finePercent'] = 3.10;
    $params['fineAmount'] = 0;
    $params['fineDate'] = '2021-08-09';

    $post_arr = [];
    foreach ($params as $key => $value) {
      $post_arr[] = "$key=$value";
    }
    $post = implode('&', $post_arr);

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.digitalbanks.com.br/api/public/invoice',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $post,
      CURLOPT_HTTPHEADER => array(
        'Authorization: '.$this->token_api_invoice,
        'Content-Type: application/x-www-form-urlencoded'
      ),
    ));
    //string(790) "{"id":"ba5d7e2e-1ef1-44a9-a90d-76137ef59da0","type":1,"recurrent":false,"value":10.0,"details":{"id":"793a2ced-872b-4e6d-ab6d-933191355db1","document_number":"003791082","barcode":"34192885200000010001090037910828848273473000","typing_line":"34191090083791082884982734730003288520000001000","url":"https://api.digitalbankstecnologia.com.br/api/public/print/boleto?boleto=ba5d7e2e-1ef1-44a9-a90d-76137ef59da0"},"status":1,"statusReadable":"PROCESSED","boleto":{"id":null,"customIssuerInvoiceIdentifier":null,"paidAt":null},"dueDate":"2022-01-01","clientWebhook":"http://localhost:8081/wp-json/get_boleto","createdAt":"2021-12-09T21:52:42.107268Z","updatedAt":"2021-12-09T21:52:42.112602Z","clientServerResponseStatus":null,"clientServerResponses":null,"clientWebhookNotificationsAttempts":0}"

    $response = curl_exec($curl);
    $response_obj = json_decode($response);

    $wpdb->insert($this->invoice_table_name, [
      'invoice_id' => $response_obj->id,
      'document_number' => $response_obj->details->document_number,
      'barcode' => $response_obj->details->barcode,
      'url' => $response_obj->details->url,
      'wc_order' => $order_id,
      'status' => $response_obj->status,
      'response' => $response
    ]);

    if($response_obj->status != 1){
      wc_add_notice( 'Erro no pagamento: ' . implode('<br>', $response_obj->errors), 'error' );
      return NULL;
    }

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
