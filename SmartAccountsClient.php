<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class SmartAccountsClient
{

    /** @var WC_Order */
    protected $order;
    protected $isAnonymous;
    protected $email;
    protected $name;
    protected $country;
    protected $isCompany;

    /** @var SmartAccountsApi */
    protected $api;
    protected $vatNumber;

    protected $generalUserName = "WooCommerce User";


    /**
     * SmartAccountsClient constructor.
     *
     * @param $order WC_Order
     */
    public function __construct($order)
    {
        $this->order   = $order;
        $this->country = $order->get_billing_country();
        if ($this->country == null || strlen($this->country) == 0) {
            $this->country = $order->get_shipping_country();
        }
        $this->email     = $order->get_billing_email();
        $this->isCompany = strlen($order->get_billing_company()) > 0;
        $firstName       = trim(strlen($order->get_shipping_first_name()) == 0 ? $order->get_billing_first_name() : $order->get_shipping_first_name());
        $lastName        = trim(strlen($order->get_shipping_last_name()) == 0 ? $order->get_billing_last_name() : $order->get_shipping_last_name());

        $this->isAnonymous = (!$firstName && !$lastName);

        if ($this->isCompany) {
            $this->name = trim($order->get_billing_company());
        } elseif ($this->isAnonymous) {
            $this->name = trim("$this->generalUserName $this->country");
        } else {
            $this->name = "$firstName $lastName";
        }

        $settings        = json_decode(get_option("sa_settings"));
        $this->vatNumber = get_post_meta($order->get_id(), isset($settings->vat_number_meta) ? $settings->vat_number_meta : 'vat_number', true);

        $this->api = new SmartAccountsApi();
    }

    /**
     * This method will look for SmartAccounts clients and if no customers related to
     * current WooCommerce order do not exist then will create new client.
     * Comparison is done with name, country and e-mail
     *
     * @return SmartAccountsClient
     */
    public function getClient()
    {
        $apiUrl = "purchasesales/clients:get";

        if ($this->order->meta_exists('_billing_regcode')) {
            $client = $this->order->get_meta('_billing_regcode', true);
        } else {
            $pres = ['oü', 'as', 'fie', 'mtü', 'kü'];
            $name = $this->name;
            foreach ($pres as $pre) {
                $name = preg_replace("/(.*)( " . $pre . "| " . mb_strtoupper($pre) . ")?$/imU", '$1', $name);
                $name = preg_replace("/^(" . $pre . " |" . mb_strtoupper($pre) . " )?(.*)/im", '$2', $name);
            }

            $client = urlencode($name);
        }

        $clients        = $this->api->sendRequest(null, $apiUrl, "fetchAddresses=true&fetchContacts=true&nameOrRegCode=" . $client);
        $hasMoreEntries = isset($clients['hasMoreEntries']) && $clients['hasMoreEntries'] ? "YES" : "NO";
        error_log("Found " . count($clients['clients']) . " SA clients. hasMoreEntries: $hasMoreEntries");

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
            if ($this->isGeneralCountryClient($client, $country)) {
                return $client;
            }
        }

        error_log("Create anonymous customer for country $country to SmartAccounts");
        return $this->addNewSaClient(null, $name, $country);
    }

    private function isGeneralCountryClient($client, $country)
    {
        if (!array_key_exists("address", $client)) {
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

        //maybe has PHP 5 and ?? operator is missing
        $city       = $this->order->get_billing_city() ? $this->order->get_billing_city() :
            ($this->order->get_shipping_city() ? $this->order->get_shipping_city() : "");
        $state      = $this->order->get_billing_state() ? $this->order->get_billing_state() :
            ($this->order->get_shipping_state() ? $this->order->get_shipping_state() : "");
        $postalCode = $this->order->get_billing_postcode() ? $this->order->get_billing_postcode() :
            ($this->order->get_shipping_postcode() ? $this->order->get_shipping_postcode() : "");
        $address1   = substr($this->order->get_billing_address_1() ? $this->order->get_billing_address_1() :
            ($this->order->get_shipping_address_1() ? $this->order->get_shipping_address_1() : ""), 0, 64);
        $address2   = substr($this->order->get_billing_address_2() ? $this->order->get_billing_address_2() :
            ($this->order->get_shipping_address_2() ? $this->order->get_shipping_address_2() : ""), 0, 64);

        $body          = new stdClass();
        $body->name    = $name;
        $body->address = (object)[
            "country"    => $country,
            "city"       => $city,
            "county"     => $state,
            "address1"   => $address1,
            "address2"   => $address2,
            "postalCode" => $postalCode
        ];

        if ($email != null) {
            $body->contacts = [
                [
                    "type"  => "EMAIL",
                    "value" => $email
                ]
            ];
        }

        $phone = $this->order->get_billing_phone();
        if ($phone) {
            if (!$body->contacts) {
                $body->contacts = [];
            }
            $body->contacts[] =
                [
                    "type"  => "PHONE",
                    "value" => $phone
                ];
        }


        if ($this->vatNumber) {
            $body->vatNumber = $this->vatNumber;
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
        if (!is_array($clients) || count($clients) == 0) {
            error_log("No SA customers found, creating new with name $name");
            return $this->addNewSaClient($email, $name, $country);
        }

        $clientNames = [];
        foreach ($clients as $client) {
            //match client if name matches and (is company or email also matches)
            if (($this->isCompany || $this->hasEmail($client, $email)) &&
                strtolower(trim($this->name)) === strtolower(trim($client["name"]))) {
                return $client;
            }
            $clientNames[] = $client['name'];
        }

        error_log("No good match found for $name, creating new company. Found names " . json_encode($clientNames));
        return $this->addNewSaClient($email, $name, $country);
    }

    private function hasEmail($client, $email)
    {
        if (!array_key_exists("contacts", $client)) {
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
