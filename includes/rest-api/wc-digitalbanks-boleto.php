<?php

namespace WC_DigitalBanks;
use WP_REST_Server;
use WC_Order;

class Boleto_API{

	public function init(){
		$this->register_routes();
	}

	public function api_permission_called(){

		return true;
	}

	public function register_routes() {
	    // register_rest_route() handles more arguments but we are going to stick to the basics for now.
	    register_rest_route( 'wc-digitalbanks-boleto/v1', 'clientWebhook/', array(
	        // By using this constant we ensure that when the WP_REST_Server changes our readable endpoints will work as intended.
	        'methods'  => WP_REST_Server::CREATABLE,
	        // Here we register our callback. The callback is fired when this endpoint is matched by the WP_REST_Server class.
	        'callback' => [$this, 'clientWebhook'],
	        // Here we register our permissions callback. The callback is fired before the main callback to check if the current user can access the endpoint.
	        'permission_callback' => [$this, 'api_permission_called'],
	    ) );


	}


	public function clientWebhook( $request ){
		$params = (Object) $request->get_params();

		/*
			1 PROCESSED - Processed by the system, issued at Bank
			2 LIQUIDATED - Client payed
			3 EXPIRED_LIQUIDATED - Client payed after due date
			4 LIQUIDATED_AFTER_CANCEL - Invoice was cancelled by assignor/owner but client payed it anyway
			5 EXPIRED / DATE_EXPIRED - Invoice expired and not paid yet
			6 ASSIGNOR_CANCELLED - Invoice was cancelled by assignor/owner
			7 BANK_CANCELLED - Invoice was cancelled by bank
		*/
		global $wpdb;
		$invoice_table_name = $wpdb->prefix . "wc_digitalbanks_invoices";
		switch($params->status){
			case 1:
				$invoice = $wpdb->get_row("SELECT * FROM {$invoice_table_name} WHERE invoice_id = '{$params->id}'");
				if(!$invoice){
					return [
						'status' => 'error',
						'message' => "Não foi possível encontrar o pedido para o invoice " . $invoice->id
					];
				}

				$order = new WC_Order($invoice->wc_order);
				if($order){
					$order->update_status( 'wc-processing', 'Pedido em processamento identificado pelo webhook DigitalBanks');
				}
				return [
					'status' => 'ok',
					'message' => "Informação recebida"
				];
				break;
			case 2:
			case 3:
				$invoice = $wpdb->get_row("SELECT * FROM {$invoice_table_name} WHERE invoice_id = '{$params->id}'");
				if(!$invoice){
					return [
						'status' => 'error',
						'message' => "Não foi possível encontrar o pedido para o invoice " . $invoice->id
					];
				}

				$order = new WC_Order($invoice->wc_order);
				if($order){
					$order->update_status( 'wc-completed', 'Pagamento identificado pelo webhook DigitalBanks');
				}
				return [
					'status' => 'ok',
					'message' => "Informação recebida"
				];
				break;
			case 4:
			case 5:
			case 6:
			case 7:
				$invoice = $wpdb->get_row("SELECT * FROM {$invoice_table_name} WHERE invoice_id = '{$params->id}'");
				if(!$invoice){
					return [
						'status' => 'error',
						'message' => "Não foi possível encontrar o pedido para o invoice " . $invoice->id
					];
				}

				$order = new WC_Order($invoice->wc_order);
				if($order){
					$order->update_status( 'wc-cancelled', 'Cancelamento identificado pelo webhook DigitalBanks');
				}
				return [
					'status' => 'ok',
					'message' => "Informação recebida"
				];
				break;
		}

		return [];
  }

}
