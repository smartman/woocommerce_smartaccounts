<?php
/**
 * Created by Margus Pala.
 * Date: 17.09.18
 * Time: 18:57
 */

include_once 'wp-async-request.php';
include_once 'wp-background-process.php';

class SmartAccountsArticleAsync extends WP_Background_Process
{

    public static function syncSaProducts()
    {
        error_log('Starting to sync products from SmartAccounts');
        $api             = new SmartAccountsApi();
        $page            = 1;
        $productsBatches = [];
        $products        = [];
        $syncCount       = 0;
        $noSyncCount     = 0;

        $settings = SmartAccountsClass::getSettings();
        if (strlen($settings->inventoryFilter) === 0) {
            $filter = [];
        } else {
            $filter = explode(',', $settings->inventoryFilter);
        }

        do {
            $result = $api->sendRequest(null, "purchasesales/articles:get", "pageNumber=$page");
            if (isset($result['articles']) && is_array($result['articles'])) {
                foreach ($result['articles'] as $article) {
                    if (!$article['activeSales']) {
                        continue;
                    }

                    if (count($filter) > 0 && !in_array($article['accountWarehouse'], $filter)) {
                        error_log('Item filtered from sync ' . $article['code']);
                        $noSyncCount++;
                        continue;
                    }

                    if ($article['type'] === 'PRODUCT' && !$settings->importProducts) {
                        error_log('not importing PRODUCT ' . $article['code']);
                        $noSyncCount++;
                        continue;
                    } elseif ($article['type'] === 'SERVICE' && !$settings->importServices) {
                        error_log('not importing SERVICE ' . $article['code']);
                        $noSyncCount++;
                        continue;
                    } elseif ($article['type'] === 'WH' && !$settings->importInventory) {
                        error_log('not importing WH ' . $article['code']);
                        $noSyncCount++;
                        continue;
                    }

                    if (count($products) == 20) {
                        $productsBatches[] = $products;
                        $products          = [];
                    }

                    $products[$article['code']] = [
                        'code'        => $article['code'],
                        'price'       => $article['priceSales'],
                        'description' => $article['description'],
                        'quantity'    => 0
                    ];
                    $syncCount++;
                }
            }
            $page++;
        } while ($result['hasMoreEntries']);

        if (count($products) > 0) {
            $productsBatches[] = $products;
        }

        $background = new SmartAccountsArticleAsync();
        foreach ($productsBatches as $batch) {
            $background->push_to_queue($batch);
        }

        $background->save()->dispatch();

        wp_send_json(['message' => "SA Sync initiated. Synching $syncCount, not synching $noSyncCount. " . time()]);
    }

    protected $action = 'smartaccounts_product_import';

    function task($products)
    {
        if (!is_array($products)) {
            error_log('Not an array, something is wrong, removing from sync list: ' . print_r($products, true));

            return false;
        }

        $productCodes = [];
        foreach ($products as $product) {
            $productCodes[] = urlencode($product['code']);
        }

        $backOffTime = get_option("sa_backoff_time");
        if ($backOffTime && (time() < $backOffTime)) {
            error_log("Backing up for SmartAccounts to honor API limits $backOffTime -> " . time());
            sleep(15);

            return true;
        }
        $api = new SmartAccountsApi();

        try {
            $result = $api->sendRequest(null, "purchasesales/articles:getwarehousequantities",
                "codes=" . implode(",", $productCodes));
        } catch (Exception $exception) {
            error_log("SmartAccounts sync error: " . $exception->getMessage() . " " . $exception->getTraceAsString());
            update_option('sa_backoff_time', time() + 5400);

            return true;
        }

        sleep(2); // needed to not exceed API request limits
        if (isset($result['quantities']) && is_array($result['quantities'])) {
            error_log('Received product quantities ' . json_encode($result['quantities']));
            foreach ($result['quantities'] as $quantity) {
                $products[$quantity['code']]['quantity'] = intval($quantity['quantity']);
            }
            error_log("Quantity set: " . json_encode($products));
        }
        foreach ($products as $code => $product) {
            $productId = wc_get_product_id_by_sku($code);
            if (!$productId) {
                error_log("Inserting product $code");
                $this->insertProduct($code, $product);
            } else {
                error_log("Updating product: $code - $productId");
                $existingProduct = wc_get_product($productId);
                $this->insertProduct($code, $product, $existingProduct);
            }
        }

        return false;
    }

    protected function insertProduct($code, $data, $existing = null)
    {
        if (strlen($data['description']) == 0) {
            $title = $code;
        } else {
            $title = $data['description'];
        }
        if ($existing == null) {
            $post_id = wp_insert_post([
                'post_title'   => $title,
                'post_content' => $title,
                'post_status'  => 'publish',
                'post_type'    => "product",
            ], true);
            if (is_wp_error($post_id)) {
                error_log("Insert failed: " . $post_id->get_error_message());

                return;
            } else {
                error_log("New product ID $post_id");
            }
        } else {
            $post_id = $existing->get_id();
        }

        $regularPrice = get_post_meta($post_id, '_regular_price', true);
        $finalPrice   = get_post_meta($post_id, '_price', true);
        $salePrice    = get_post_meta($post_id, '_sale_price', true);
        if (!$regularPrice || !$finalPrice) {
            // No price set at all yet, set one now.
            update_post_meta($post_id, '_regular_price', $data['price']);
            update_post_meta($post_id, '_price', $data['price']);
        } elseif ($salePrice && ($salePrice !== $regularPrice)) {
            // Sale price is set. Update only regular price and keep actual sale price what it is.
            update_post_meta($post_id, '_regular_price', $data['price']);
        }

        if ($finalPrice && (floatval($data['price']) > floatval($regularPrice))) {
            // New final price is more than regular price so regular price will be updated also.
            update_post_meta($post_id, '_regular_price', $data['price']);
        }

        update_post_meta($post_id, '_sku', $code);
        update_post_meta($post_id, '_visibility', 'visible');
        update_post_meta($post_id, '_downloadable', 'no');
        update_post_meta($post_id, '_featured', 'no');
        update_post_meta($post_id, '_manage_stock', 'yes');
        update_post_meta($post_id, '_stock_status', intval($data['quantity']) < 1 ? 'outofstock' : 'instock');
        wc_update_product_stock($post_id, $data['quantity']);

        error_log("Update stock for $post_id - $code to " . $data['quantity']);
    }
}
