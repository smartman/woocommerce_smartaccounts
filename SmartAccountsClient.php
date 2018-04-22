<?php

if ( ! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class SmartAccountsClient
{

    protected $isAnonymous;
    protected $email;
    protected $name;
    protected $country;
    protected $isCompany;
    protected $api;

    protected $generalUserName = "WooCommerce User";


    /**
     * SmartAccountsClient constructor.
     *
     * @param $order WooCommerce order object
     */
    public function __construct($order)
    {
        $this->country = $order->get_billing_country();
        if ($this->country == null || strlen($this->country) == 0) {
            $this->country = $order->get_shipping_country();
        }
        $this->email       = $order->get_billing_email();
        $this->isCompany   = strlen($order->get_billing_company()) > 0;
        $firstName         = strlen($order->get_shipping_first_name()) == 0 ? $order->get_billing_first_name() : $order->get_shipping_first_name();
        $lastName          = strlen($order->get_shipping_last_name()) == 0 ? $order->get_billing_last_name() : $order->get_shipping_last_name();
        $this->isAnonymous = ( ! $firstName || ! $lastName);

        if ($this->isAnonymous) {
            $this->name = trim("$this->generalUserName $this->country");
        } else if ($this->isCompany) {
            $this->name = $order->get_billing_company();
        } else {
            $this->name = "$firstName $lastName";
        }

        $this->api = new SmartAccountsApi();
    }

    /**
     * This method will look for SmartAccounts clients and if no customers related to
     * current WooCommerce order do not exist then will create new client.
     * Comparison is done with name, country and e-mail
     *
     * @return SmartAccountsClass customer array
     */
    public function getClient()
    {
        $apiUrl  = "purchasesales/clients:get";
        $clients = $this->api->sendRequest(null, $apiUrl,
            "fetchAddresses=true&fetchContacts=true&nameOrRegCode=" . urlencode($this->name));
        if ($this->isAnonymous) {
            return $this->getAnonymousClient($clients["clients"], $this->country, $this->name);
        } else {
            return $this->getLoggedInClient($clients["clients"], $this->country, $this->name, $this->email);
        }
    }

    /**
     * Returns SmartAccounts general client for this country. Creates new if it does not exist yet.
     */
    private function getAnonymousClient($clients, $country, $name)
    {
        foreach ($clients as $client) {
            if ($this->isGeneralCountryClient($client, $country, $name)) {
                return $client;
            }
        }

        return $this->addNewSaClient(null, $name, $country);
    }

    private function isGeneralCountryClient($client, $country)
    {
        if ( ! array_key_exists("address", $client)) {
            if ($client['name'] == $this->generalUserName) {
                return true;
            } else {
                return false;
            }
        }

        foreach ($client["address"] as $key => $value) {
            if ($key == "country" && $value == $country && $this->name == $client["name"]) {
                return true;
            }
        }

        return false;
    }

    private function addNewSaClient($email, $name, $country)
    {
        $apiUrl = "purchasesales/clients:add";

        $body          = new stdClass();
        $body->name    = $name;
        $body->address = (object)[
            "country" => $country
        ];
        if ($email != null) {
            $body->contacts = [
                [
                    "type"  => "EMAIL",
                    "value" => $email
                ]
            ];
        }

        $createResponse = $this->api->sendRequest($body, $apiUrl);
        $clientId       = $createResponse["clientId"];
        $client         = $this->api->sendRequest(null, "purchasesales/clients:get", "id=$clientId");

        return $client["clients"][0];
    }

    /**
     * Returns SmartAccounts client for the logged in user in the order. Creates new if it does not exist yet.
     */
    private function getLoggedInClient($clients, $country, $name, $email)
    {
        if ( ! is_array($clients) || count($clients) == 0) {
            return $this->addNewSaClient($email, $name, $country);
        }

        foreach ($clients as $client) {
            //match client if name matches and is company or email also matches
            if (($this->isCompany || $this->hasEmail($client,
                        $email)) && strtolower($this->name) == strtolower($client["name"])) {
                return $client;
            }
        }

        return $this->addNewSaClient($email, $name, $country);
    }

    private function hasEmail($client, $email)
    {
        if ( ! array_key_exists("contacts", $client)) {
            return false;
        }
        foreach ($client["contacts"] as $contact) {
            if ($contact["type"] == "EMAIL" && $contact["value"] == $email) {
                return true;
            }
        }

        return false;
    }

}
