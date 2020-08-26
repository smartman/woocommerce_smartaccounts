<?php

if (!defined('ABSPATH')) {
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
            $settings = json_decode(get_option("sa_settings"));
            if (!(array_key_exists("articles", $articles) && count($articles["articles"]) == 1)) {
                $body              = new stdClass();
                $body->code        = $row->code;
                $body->description = preg_replace('/[\xF0-\xF7].../s', '_', $row->description);
                $body->type        = $row->code == $settings->defaultShipping ? "SERVICE" : "PRODUCT";
                $body->activeSales = true;
                $this->api->sendRequest($body, $addApiUrl);
            }
        }
    }

}
