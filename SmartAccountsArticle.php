<?php

if ( ! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class SmartAccountsArticle
{

    public function __construct()
    {
        $this->api = new SmartAccountsApi();
    }

    public function ensureAllArticlesExist($rows)
    {
        $getApiUrl = "purchasesales/articles:get";
        $addApiUrl = "purchasesales/articles:add";
        foreach ($rows as $row) {
            $body     = new stdClass();
            $articles = $this->api->sendRequest($body, $getApiUrl, "code=" . urlencode($row->code));
            if ( ! (array_key_exists("articles", $articles) && count($articles["articles"]) == 1)) {
                $body              = new stdClass();
                $body->code        = $row->code;
                $body->description = $row->description;
                $body->type        = $row->code == get_option('sa_api_shipping_code') ? "SERVICE" : "PRODUCT";
                $body->activeSales = true;
                $this->api->sendRequest($body, $addApiUrl);
            }
        }
    }

}
