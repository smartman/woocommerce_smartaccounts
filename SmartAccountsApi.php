<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class SmartAccountsApi
{
    public function sendRequest($body, $apiUrl, $extraParams = null)
    {
        error_log("Sending SA call to $apiUrl");
        if ($body == null) {
            $body = new stdClass();
        }
        $now = new DateTime();
        $now->setTimezone(new DateTimeZone('Europe/Tallinn'));
        $settings = SmartAccountsClass::getSettings();
        $pk       = $settings->apiKey;
        $sk       = $settings->apiSecret;

        $bodyJson  = json_encode($body);
        $ts        = $now->format("dmYHis");
        $urlParams = "apikey=$pk&timestamp=$ts";
        $urlParams = $extraParams == null ? $urlParams : $urlParams . "&$extraParams";

        $sig = hash_hmac('sha256', $urlParams . $bodyJson, $sk);
        $url = "https://sa.smartaccounts.eu/api/$apiUrl?$urlParams&signature=$sig";

        $args = [
            'body'    => $bodyJson,
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 60
        ];

        $response        = wp_remote_post($url, $args);
        $response_code   = wp_remote_retrieve_response_code($response);
        $saResponse      = wp_remote_retrieve_body($response);
        $decodedResponse = json_decode($saResponse, true);


        if (!in_array($response_code, [200, 201]) || is_wp_error($saResponse)) {
            error_log("SmartAccounts call failed url=$url: $response_code" . print_r($response, true));
            throw new Exception("SmartAccounts call failed url=$url: $response_code" . print_r($response, true));
        }

        if (!$decodedResponse) {
            return $saResponse; // Needed for getting raw PDF bytes
        }

        return $decodedResponse;
    }
}
