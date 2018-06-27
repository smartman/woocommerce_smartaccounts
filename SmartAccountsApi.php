<?php

if ( ! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class SmartAccountsApi
{

    public function sendRequest($body, $apiUrl, $extraParams = null)
    {
        if ($body == null) {
            $body = new stdClass();
        }
        $now = new DateTime();
        $now->setTimezone(new DateTimeZone('Europe/Tallinn'));
        $pk = get_option("sa_api_pk");
        $sk = get_option("sa_api_sk");

        $bodyJson  = json_encode($body);
        $ts        = $now->format("dmYHis");
        $urlParams = "apikey=$pk&timestamp=$ts";
        $urlParams = $extraParams == null ? $urlParams : $urlParams . "&$extraParams";

        $sig = hash_hmac('sha256', $urlParams . $bodyJson, $sk);
        $url = "https://sa.smartaccounts.eu/api/$apiUrl?$urlParams&signature=$sig";

        $args = array(
            'body'    => $bodyJson,
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 60
        );

        $response        = wp_remote_post($url, $args);
        $response_code   = wp_remote_retrieve_response_code($response);
        $saResponse      = wp_remote_retrieve_body($response);
        $decodedResponse = json_decode($saResponse, true);

        if ( ! in_array($response_code, array(200, 201)) || is_wp_error($saResponse)) {
            throw new Exception("SmartAccounts call failed: $response_code" . print_r($response, true));
        }

        return $decodedResponse;
    }
}