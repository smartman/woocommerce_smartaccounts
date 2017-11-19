<?php

class SmartAccountsPayment {

	public function __construct( $order, $invoice ) {
		$this->api     = new SmartAccountsApi();
		$this->order   = $order;
		$this->invoice = $invoice["invoice"];
	}

	public function createPayment() {
		$apiUrl            = "purchasesales/payments:add";
		$body              = new stdClass();
		$body->date        = $this->order->get_date_created()->date( "d.m.Y" );
		$body->partnerType = "CLIENT";
		$body->clientId    = $this->invoice["clientId"];
		$body->accountType = "BANK";
		$body->accountName = get_option( "sa_api_payment_account" );
		$body->currency    = $this->order->get_currency();
		$body->amount      = $this->invoice["totalAmount"];
		$body->rows        = [
			[
				"type"   => "CLIENT_INVOICE",
				"id"     => $this->invoice["invoiceId"],
				"amount" => $this->invoice["totalAmount"]
			]
		];
		$this->api->sendRequest( $body, $apiUrl );
	}
}