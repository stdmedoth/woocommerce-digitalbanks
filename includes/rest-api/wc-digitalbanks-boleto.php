<?php

namespace WC_DigitalBanks;
use WP_REST_Server;

class Boleto_API{

	public function init(){
		$this->register_routes();
	}

	public function api_permission_called(){

		return true;
	}

	public function register_routes() {
	    // register_rest_route() handles more arguments but we are going to stick to the basics for now.
	    register_rest_route( 'wc-digitalbanks-boleto/v1', 'get_boleto/', array(
	        // By using this constant we ensure that when the WP_REST_Server changes our readable endpoints will work as intended.
	        'methods'  => WP_REST_Server::READABLE,
	        // Here we register our callback. The callback is fired when this endpoint is matched by the WP_REST_Server class.
	        'callback' => [$this, 'get_boleto'],
	        // Here we register our permissions callback. The callback is fired before the main callback to check if the current user can access the endpoint.
	        'permission_callback' => [$this, 'api_permission_called'],
	    ) );


	}


	public function get_boleto( $request ){

		$order_id = $request->get_param('order_id');
		$curl = curl_init();

		$params = [];
		$params['value'] = $order->get_total();
		$params['payerTaxId'] = '000.000.000-00';
		$params['payerPostalCode'] = '00000-000';
		$params['payerLocationNumber'] = '0';
		$params['payerComplement'] = NULL;
		$params['clientWebhook'] = NULL;
		$params['customIssuerName'] = 'PADARIA%20DO%20JOAO';
		$params['customBodyText'] = 'Referente ao pedido ABC0000001234';
		$params['discountType'] = 3;
		$params['discountLimitDate'] = '2019-08-09';
		$params['discountPercentAmount'] = '0';
		$params['discountAmount'] = 0.10;
		$params['discountFixedAmount'] = 0.25;
		$params['interestType'] = 1;
		$params['interestPercent'] = 0;
		$params['interestAmount'] = 2;
		$params['fineType'] = 2;
		$params['finePercent'] = 3.10;
		$params['fineAmount'] = 0;
		$params['fineDate'] = '2019-08-09';

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
				'Authorization: {{token_api_invoice}}',
				'Content-Type: application/x-www-form-urlencoded'
			),
		));

		//$response = curl_exec($curl);

		curl_close($curl);
  }



}
