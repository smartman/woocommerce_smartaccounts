<?php

class SmartAccountsPayment
{

    public function __construct($order, $invoice)
    {
        $this->api     = new SmartAccountsApi();
        $this->order   = $order;
        $this->invoice = $invoice["invoice"];
    }

    public function createPayment()
    {
        $settings                = json_decode(get_option("sa_settings"));
        $orderPaymentMethod      = $this->order->get_payment_method();
        $orderPaymentMethodTitle = $this->order->get_payment_method_title();

        //use method ID and title for fallback
        if (isset($settings->paymentMethodsPaid) && ! ($settings->paymentMethodsPaid->$orderPaymentMethod || $settings->paymentMethodsPaid->$orderPaymentMethodTitle)) {
            error_log("Payment method $orderPaymentMethod is not allowed to be marked paid");

            return;
        }

        $orderCurrencyCode = $this->order->get_currency();
        $paymentMethod     = null;
        if (is_array($settings->currencyBanks)) {
            foreach ($settings->currencyBanks as $bank) {
                if ($bank->currency_code == $orderCurrencyCode && $bank->payment_method == $orderPaymentMethod) {
                    $paymentMethod = $bank->currency_bank;
                    break;
                }
            }
        }

        if ($paymentMethod == null) {
            $paymentMethod = $settings->defaultPayment;
        }

        $apiUrl            = "purchasesales/payments:add";
        $body              = new stdClass();
        $body->date        = $this->order->get_date_created()->date("d.m.Y");
        $body->partnerType = "CLIENT";
        $body->clientId    = $this->invoice["clientId"];
        $body->accountType = "BANK";
        $body->accountName = $paymentMethod;
        $body->currency    = $this->order->get_currency();
        $body->amount      = $this->invoice["totalAmount"];
        $body->rows        = [
            [
                "type"   => "CLIENT_INVOICE",
                "id"     => $this->invoice["invoiceId"],
                "amount" => $this->invoice["totalAmount"]
            ]
        ];
        $this->api->sendRequest($body, $apiUrl);
    }
}