<?php

if ( ! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class SmartAccountsSalesInvoice
{

    public function __construct($order, $client)
    {
        $this->client = $client;
        $this->order  = $order;
        $this->api    = new SmartAccountsApi();
    }

    public function saveInvoice()
    {
        $apiUrl = "purchasesales/clientinvoices:add";

        $body              = new stdClass();
        $body->clientId    = $this->client["id"];
        $body->date        = $this->order->get_date_created()->date("d.m.Y");
        $body->currency    = $this->order->get_currency();
        $body->rows        = $this->getOrderRows();
        $body->roundAmount = $this->getRoundingAmount($body->rows);
        $body->amount      = number_format($this->getOrderTotal(), 2);
        $body->invoiceNote = "WooCommerce order #" . $this->order->get_id();

        $saArticle = new SmartAccountsArticle();
        $saArticle->ensureAllArticlesExist($body->rows);

        $salesInvoice = $this->api->sendRequest($body, $apiUrl);

        return ["invoice" => $salesInvoice, "rows" => $body->rows];
    }

    public function getRoundingAmount($rows)
    {
        $rowsTotal = 0;
        foreach ($rows as $row) {
            $rowsTotal += $row->totalCents;
            $rowsTotal += $row->taxCents;
        }

        $roundingAmount = number_format(($this->getOrderTotal() * 100 + $this->getTotalTax() * 100 - $rowsTotal) / 100,
            2);

        return $roundingAmount;
    }

    public function getTotalTax()
    {
        return floatval($this->order->get_total_tax());
    }

    public function getOrderTotal()
    {
        return $this->order->get_subtotal() + $this->order->get_shipping_total() - $this->order->get_discount_total();
    }

    private function getOrderRows()
    {
        $rows     = [];
        $totalTax = $this->getTotalTax();
        $subTotal = $this->getOrderTotal();
        $vatPc    = round($totalTax * 100 / $subTotal);
        foreach ($this->order->get_items() as $item) {
            $product = $item->get_product();
            $row     = new stdClass();
            if ($product == null) {
                error_log("SA Product not found for order item " . $item->get_id());
                $row->description = $item->get_name();
                $code             = "wc_missing_product_" . $item->get_id();
            } else {
                $code = $product->get_sku();
                if ($code == null || strlen($code) == 0) {
                    $code = "wc_product_" . $product->get_id();
                }
	            $row->description = strlen( $product->get_name() ) == 0 ? $product->get_description() : $product->get_name();
            }

            if (strlen($row->description) == 0) {
                $row->description = $code;
            }

            $row->code     = $code;
            $row->quantity = $item->get_quantity();

            $rowPrice = $item->get_total() / $item->get_quantity();

            $row->price      = number_format($rowPrice, 2);
            $row->vatPc      = $vatPc;
            $row->totalCents = intval(round(floatval($row->price) * $row->quantity * 100));
            $row->taxCents   = intval(round($row->totalCents * $vatPc / 100));
            $rows[]          = $row;
        }

        if ($this->order->get_shipping_total() > 0) {
            $row              = new stdClass();
            $row->code        = get_option('sa_api_shipping_code');
            $row->description = "Woocommerce Shipping";
            $row->price       = $this->order->get_shipping_total();
            $row->quantity    = 1;
            $row->vatPc       = $vatPc;
            $row->totalCents  = intval(round(floatval($row->price) * $row->quantity * 100));
            $row->taxCents    = intval(round($row->totalCents * $vatPc / 100));
            $rows[]           = $row;
        }

        return $rows;
    }


}
