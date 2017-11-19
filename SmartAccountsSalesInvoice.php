<?php

class SmartAccountsSalesInvoice {

	public function __construct( $order, $client ) {
		$this->client = $client;
		$this->order  = $order;
		$this->api    = new SmartAccountsApi();
	}

	public function saveInvoice() {
		$apiUrl = "purchasesales/clientinvoices:add";

		$body           = new stdClass();
		$body->clientId = $this->client["id"];
		$body->date     = $this->order->get_date_created()->date( "d.m.Y" );
		$body->currency = $this->order->get_currency();
		$body->rows     = $this->getOrderRows();

		$saArticle = new SmartAccountsArticle();
		$saArticle->ensureAllArticlesExist( $body->rows );

		$salesInvoice = $this->api->sendRequest( $body, $apiUrl );

		return [ "invoice" => $salesInvoice, "rows" => $body->rows ];
	}


	private function getOrderRows() {
		$rows     = [];
		$totalTax = floatval( $this->order->get_total_tax() );
		$subTotal = $this->order->get_subtotal() + $this->order->get_shipping_total();
		$vatPc    = round( $totalTax * 100 / $subTotal );
		foreach ( $this->order->get_items() as $item ) {
			$row              = new stdClass();
			$row->code        = $item->get_product()->get_sku();
			$row->description = strlen( $item->get_product()->get_description() ) == 0 ? "Woocommerce product " . $item->get_product()->get_name() : $item->get_product()->get_description();
			$row->price       = $item->get_product()->get_price();
			$row->quantity    = $item->get_quantity();
			$row->vatPc       = $vatPc;
			$rows[]           = $row;
		}

		if ( $this->order->get_shipping_total() > 0 ) {
			$row              = new stdClass();
			$row->code        = "shipping";
			$row->description = "Woocommerce Shipping";
			$row->price       = $this->order->get_shipping_total();
			$row->quantity    = 1;
			$row->vatPc       = $vatPc;
			$rows[]           = $row;
		}

		return $rows;
	}


}