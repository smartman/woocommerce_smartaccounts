<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

include_once('SmartAccountsClient.php');
include_once('SmartAccountsSalesInvoice.php');
include_once('SmartAccountsApi.php');
include_once('SmartAccountsPayment.php');
include_once('SmartAccountsArticle.php');

class SmartAccountsClass
{
    public static function orderOfferStatusProcessing($order_id)
    {
        error_log("Order $order_id changed status. Checking if sending OFFER to SmartAccounts");
        //try catch makes sure your store will operate even if there are errors
        try {
            $order = wc_get_order($order_id);
            if (strlen(get_post_meta($order_id, 'smartaccounts_invoice_id', true)) > 0
                || strlen(get_post_meta($order_id, 'smartaccounts_offer_id', true)) > 0) {
                error_log("SmartAccounts order $order_id already sent as offer or invoice, not sending OFFER again, SA id="
                    . get_post_meta($order_id, 'smartaccounts_invoice_id', true) . " offer_id="
                    . get_post_meta($order_id, 'smartaccounts_offer_id', true));

                return; //Smartaccounts offer is already created
            }

            $saClient       = new SmartAccountsClient($order);
            $client         = $saClient->getClient();
            $saSalesInvoice = new SmartAccountsSalesInvoice($order, $client);

            $offer = $saSalesInvoice->saveOffer();

            update_post_meta($order_id, 'smartaccounts_offer_id', $offer['offer']['offerId']);
            update_post_meta($order_id, 'smartaccounts_offer_number', $offer['offer']['offerNumber']);
            error_log("Offer data: " . json_encode($offer));
            error_log("SmartAccounts sales offer created for order $order_id=" . $offer['offer']['offerId']);
            $order->add_order_note("Offer sent to SmartAccounts: " . $offer['offer']['offerNumber']);

            $offerIdsString = get_option('sa_failed_offers');
            $offerIds       = json_decode($offerIdsString);
            if (is_array($offerIds) && array_key_exists($order_id, $offerIds)) {
                unset($offerIds[$order_id]);
                update_option('sa_failed_offers', json_encode($offerIds));
                error_log("Removed $order_id from failed offers array");
            }
        } catch (Exception $exception) {
            error_log("SmartAccounts error: " . $exception->getMessage() . " " . $exception->getTraceAsString());

            $offerIdsString = get_option('sa_failed_offers');
            $offerIds       = json_decode($offerIdsString);
            if (is_array($offerIds)) {
                error_log("Adding $order_id to failed offers array $offerIdsString to be retried later");
                $offerIds[$order_id] = $order_id;
                update_option('sa_failed_offers', json_encode($offerIds));
            } else {
                error_log("Adding $order_id to new failed offers array. previously $offerIdsString");
                $offerIds = [$order_id => $order_id];
                update_option('sa_failed_offers', json_encode($offerIds));
            }

            wp_schedule_single_event(time() + 129600, 'sa_retry_failed_job');
            $order->add_order_note("Offer sending to SmartAccounts failed: " . $exception->getMessage());
        }
    }

    public static function orderStatusProcessing($order_id)
    {
        error_log("Order $order_id changed status. Checking if sending to SmartAccounts");
        //try catch makes sure your store will operate even if there are errors
        try {
            $order = wc_get_order($order_id);
            if (strlen(get_post_meta($order_id, 'smartaccounts_invoice_id', true)) > 0) {
                error_log("SmartAccounts order $order_id already sent, not sending again, SA id="
                    . get_post_meta($order_id, 'smartaccounts_invoice_id', true));

                return; //Smartaccounts order is already created
            }

            $saClient       = new SmartAccountsClient($order);
            $client         = $saClient->getClient();
            $saSalesInvoice = new SmartAccountsSalesInvoice($order, $client);

            $invoice   = $saSalesInvoice->saveInvoice();
            $saPayment = new SmartAccountsPayment($order, $invoice);
            $saPayment->createPayment();
            update_post_meta($order_id, 'smartaccounts_invoice_id', $invoice['invoice']['invoiceNumber']);
            error_log("SmartAccounts sales invoice created for order $order_id - " . $invoice['invoice']['invoiceNumber']);
            $order->add_order_note("Invoice sent to SmartAccounts: " . $invoice['invoice']['invoiceNumber']);

            $invoiceIdsString = get_option('sa_failed_orders');
            $invoiceIds       = json_decode($invoiceIdsString);
            if (is_array($invoiceIds) && array_key_exists($order_id, $invoiceIds)) {
                unset($invoiceIds[$order_id]);
                update_option('sa_failed_orders', json_encode($invoiceIds));
                error_log("Removed $order_id from failed orders array");
            }
        } catch (Exception $exception) {
            error_log("SmartAccounts error: " . $exception->getMessage() . " " . $exception->getTraceAsString());

            $invoiceIdsString = get_option('sa_failed_orders');
            $invoiceIds       = json_decode($invoiceIdsString);
            if (is_array($invoiceIds)) {
                error_log("Adding $order_id to failed orders array $invoiceIdsString to be retried later");
                $invoiceIds[$order_id] = $order_id;
                update_option('sa_failed_orders', json_encode($invoiceIds));
            } else {
                error_log("Adding $order_id to new failed orders array. previously $invoiceIdsString");
                $invoiceIds = [$order_id => $order_id];
                update_option('sa_failed_orders', json_encode($invoiceIds));
            }

            wp_schedule_single_event(time() + 129600, 'sa_retry_failed_job');
            $order->add_order_note("Invoice sending to SmartAccounts failed: " . $exception->getMessage());
        }
    }

    public static function retryFailedOrders()
    {
        $offerIdsString = get_option('sa_failed_offers');
        error_log("Retrying offers $offerIdsString");

        $retryCount = json_decode(get_option('sa_failed_offer_retries'));
        if (!is_array($retryCount)) {
            $retryCount = [];
        }

        $offerIds = json_decode($offerIdsString);

        if (is_array($offerIds)) {
            update_option('sa_failed_offers', json_encode([]));
            foreach ($offerIds as $id) {
                if (array_key_exists($id, $retryCount)) {
                    if ($retryCount[$id] > 3) {
                        error_log("Order $id offer sync has been retried over 3 times, dropping");
                    } else {
                        $retryCount[$id]++;
                    }
                } else {
                    $retryCount[$id] = 1;
                }
                error_log("Retrying sending offer $id");
                SmartAccountsClass::orderOfferStatusProcessing($id);
            }
            update_option('sa_failed_offers_retries', json_encode($retryCount));
        } else {
            error_log("Unable to parse failed offers: $offerIdsString");
        }

        $invoiceIdsString = get_option('sa_failed_orders');
        error_log("Retrying orders $invoiceIdsString");

        $retryCount = json_decode(get_option('sa_failed_orders_retries'));
        if (!is_array($retryCount)) {
            $retryCount = [];
        }

        $invoiceIds = json_decode($invoiceIdsString);

        if (is_array($invoiceIds)) {
            update_option('sa_failed_orders', json_encode([]));
            foreach ($invoiceIds as $id) {
                if (array_key_exists($id, $retryCount)) {
                    if ($retryCount[$id] > 3) {
                        error_log("Order $id has sync been retried over 3 times, dropping");
                    } else {
                        $retryCount[$id]++;
                    }
                } else {
                    $retryCount[$id] = 1;
                }
                error_log("Retrying sending order $id");
                SmartAccountsClass::orderStatusProcessing($id);
            }
            update_option('sa_failed_orders_retries', json_encode($retryCount));
        } else {
            error_log("Unable to parse failed orders: $invoiceIdsString");
        }
    }

    public static function saveSettings()
    {
        $unSanitized               = json_decode(file_get_contents('php://input'));
        $settings                  = new stdClass();
        $settings->apiKey          = sanitize_text_field($unSanitized->apiKey);
        $settings->apiSecret       = sanitize_text_field($unSanitized->apiSecret);
        $settings->defaultShipping = sanitize_text_field($unSanitized->defaultShipping);
        $settings->defaultPayment  = sanitize_text_field($unSanitized->defaultPayment);
        $settings->vat_number_meta = sanitize_text_field($unSanitized->vat_number_meta);
        $settings->warehouseId     = sanitize_text_field($unSanitized->warehouseId);
        $settings->importServices  = isset($unSanitized->importServices) && $unSanitized->importServices === true;
        $settings->importProducts  = isset($unSanitized->importProducts) && $unSanitized->importProducts === true;
        $settings->importInventory = isset($unSanitized->importInventory) && $unSanitized->importInventory === true;
        $settings->inventoryFilter = sanitize_text_field($unSanitized->inventoryFilter);
        $objectId                  = sanitize_text_field($unSanitized->objectId);

        if (preg_match("/^[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}$/", $objectId)) {
            $settings->objectId = $objectId;
        } else {
            $settings->objectId = null;
        }
        if (!$settings->defaultShipping) {
            $settings->defaultShipping = "shipping";
        }

        $settings->showAdvanced = $unSanitized->showAdvanced == true;

        $settings->paymentMethodsPaid = new stdClass();
        foreach ($unSanitized->paymentMethodsPaid as $key => $method) {
            if (in_array($key, self::getAvailablePaymentMethods())) {
                $settings->paymentMethodsPaid->$key = $unSanitized->paymentMethodsPaid->$key == true;
            }
        }

        $settings->currencyBanks = [];
        foreach ($unSanitized->currencyBanks as $currencyBank) {
            $newCurrencyBank                 = new stdClass();
            $newCurrencyBank->payment_method = sanitize_text_field($currencyBank->payment_method);
            $newCurrencyBank->currency_code  = sanitize_text_field($currencyBank->currency_code);
            $newCurrencyBank->currency_bank  = sanitize_text_field($currencyBank->currency_bank);
            if (!$newCurrencyBank->currency_code || !$newCurrencyBank->currency_bank) {
                continue;
            }
            array_push($settings->currencyBanks, $newCurrencyBank);
        }

        $allowedStatuses = [
            'pending',
            'processing',
            'completed',
            'on-hold'
        ];

        $settings->statuses = [];
        foreach ($unSanitized->statuses as $status) {
            if (in_array($status, $allowedStatuses)) {
                $settings->statuses[] = $status;
            }
        }

        $settings->offer_statuses = [];
        foreach ($unSanitized->offer_statuses as $status) {
            if (in_array($status, $allowedStatuses) && !in_array($status, $settings->statuses)) {
                $settings->offer_statuses[] = $status;
            }
        }

        if (count($settings->statuses) == 0 && count($settings->offer_statuses) == 0) {
            $settings->statuses = ['completed', 'processing'];
        }

        update_option('sa_settings', json_encode($settings));

        wp_send_json(['status' => "OK", 'settings' => $settings]);
    }

    public static function getSettings()
    {
        self::convertOldSettings();

        $currentSettings = json_decode(get_option('sa_settings') ? get_option('sa_settings') : "");

        if (!$currentSettings) {
            $currentSettings = new stdClass();
        }
        if (!isset($currentSettings->vat_number_meta)) {
            $currentSettings->vat_number_meta = "vat_number";
        }
        if (!isset($currentSettings->warehouseId)) {
            $currentSettings->warehouseId = null;
        }
        if (!isset($currentSettings->importServices)) {
            $currentSettings->warehouseId = false;
        }
        if (!isset($currentSettings->importProducts)) {
            $currentSettings->importProducts = true;
        }
        if (!isset($currentSettings->importInventory)) {
            $currentSettings->importInventory = true;
        }
        if (!isset($currentSettings->inventoryFilter)) {
            $currentSettings->inventoryFilter = "";
        }
        if (!isset($currentSettings->paymentMethods) || !is_object($currentSettings->paymentMethods)) {
            $currentSettings->paymentMethods = new stdClass();
        }
        if (!isset($currentSettings->paymentMethodsPaid) || !is_object($currentSettings->paymentMethodsPaid)) {
            $currentSettings->paymentMethodsPaid = new stdClass();
        }
        if (!isset($currentSettings->countryObjects) || !is_array($currentSettings->countryObjects)) {
            $currentSettings->countryObjects = [];
        }
        if (!isset($currentSettings->currencyBanks) || !is_array($currentSettings->currencyBanks)) {
            $currentSettings->currencyBanks = [];
        }
        if (!isset($currentSettings->statuses) || !is_array($currentSettings->statuses)) {
            $currentSettings->statuses = [
                'processing',
                'completed'
            ];
        }
        if (!isset($currentSettings->offer_statuses) || !is_array($currentSettings->offer_statuses)) {
            $currentSettings->offer_statuses = [];
        }

        return $currentSettings;
    }

    public static function options_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <style>
            .form-table th, .form-table td {
                padding-top: 10px;
                padding-bottom: 10px;
            }
        </style>
        <div id="sa-admin" class="wrap">
            <h1><?= esc_html(get_admin_page_title()); ?></h1>
            <hr>

            <button @click="saveSettings" class="button-primary woocommerce-save-button" :disabled="!formValid">
                Save settings
            </button>
            <div v-show="!formValid" class="notice notice-error">
                <small>Please review all settings</small>
            </div>
            <div class="notice notice-error" v-show="!settings.apiKey">
                <small>Missing SmartAccounts public key</small>
            </div>
            <div class="notice notice-error" v-show="!settings.apiSecret">
                <small>SmartAccounts private key</small>
            </div>
            <div class="notice notice-error" v-show="!settings.defaultPayment">
                <small>SmartAccounts payments default bank account name</small>
            </div>

            <div v-if="!formFieldsValidated">
                <div class="notice notice-error" v-for="err in errors.items">{{err.msg}}</div>
            </div>

            <h2>General settings</h2>
            <table class="form-table">
                <tr valign="middle">
                    <th>SmartAccounts public key</th>
                    <td>
                        <input size="50"
                               data-vv-name="apiKey"
                               v-validate="'required'"
                               v-bind:class="{'notice notice-error':errors.first('apiKey')}"
                               v-model="settings.apiKey"/>
                    </td>
                </tr>

                <tr valign="middle">
                    <th>SmartAccounts private key</th>
                    <td>
                        <input size="50"
                               data-vv-name="apiSecret"
                               v-validate="'required'"
                               v-bind:class="{'notice notice-error':errors.first('apiSecret')}"
                               v-model="settings.apiSecret"/>
                    </td>
                </tr>

                <tr valign="middle">
                    <th>SmartAccounts shipping article code</th>
                    <td>
                        <input size="50"
                               data-vv-name="defaultShipping"
                               v-bind:class="{'notice notice-error':errors.first('defaultShipping')}"
                               v-model="settings.defaultShipping"/>
                    </td>
                </tr>
                <tr valign="middle">
                    <th>SmartAccounts payments default bank account name</th>
                    <td>
                        <input size="50"
                               data-vv-name="defaultPayment"
                               v-validate="'required'"
                               v-bind:class="{'notice notice-error':errors.first('defaultPayment')}"
                               v-model="settings.defaultPayment"/>
                    </td>
                </tr>

                <tr valign="middle">
                    <th>Invoice object (optional)</th>
                    <td>
                        <input size="50"
                               data-vv-name="objectId"
                               v-validate="{regex: /^[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}$/}"
                               v-bind:class="{'notice notice-error':errors.first('objectId')}"
                               v-model="settings.objectId"
                               placeholder="00000000-0000-0000-0000-000000000000"/>
                    </td>
                </tr>

                <tr valign="middle">
                    <th></th>
                    <td>
                        <button @click="importProducts" class="button-primary woocommerce-save-button"
                                :disabled="syncInProgress">
                            Sync products from SmartAccounts
                        </button>
                    </td>
                </tr>

                <tr valign="middle">
                    <th>Show advanced settings</th>
                    <td>
                        <input type="checkbox" v-model="settings.showAdvanced"/>
                    </td>
                </tr>
            </table>


            <div v-show="settings.showAdvanced">
                <hr>

                <h2>Order statuses to send to SmartAccounts as Offer (Pakkumine)</h2>
                <small>These statuses are saved in SmartAccounts as Offer. Make sure API account has permission!</small>
                <br><br>
                <select v-model="settings.offer_statuses" multiple>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="on-hold">On hold</option>
                    <option value="completed">Completed</option>
                </select>

                <h2>Order statuses to send to SmartAccounts as Invoice (Müügiarve)</h2>
                <small>If none selected then default Processing and Completed are used. Use CTRL+click to choose
                    multiple values
                </small>
                <br><br>
                <select v-model="settings.statuses" multiple>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="on-hold">On hold</option>
                    <option value="completed">Completed</option>
                </select>

                <h2>Vat number meta field</h2>
                <small>Order meta field that contains company VAT number if one exists. Default vat_number. If meta
                    field does not exists then client VAT number will not be sent to the SmartAccounts
                    (Optional)</small>
                <br>
                <input size="20" v-model="settings.vat_number_meta"/>
                <br><br>

                <hr>
                <h2>Payment methods</h2>
                <small>Configure which payment methods are paid immediately and invoices can be created with payments
                </small>
                <table class="form-table">
                    <tr valign="top" v-for="(method, title) in paymentMethods">
                        <th>Method: {{title}}</th>
                        <td>
                            <label>Mark paid? </label>
                            <input type="checkbox" v-model="settings.paymentMethodsPaid[method]">
                        </td>
                    </tr>
                </table>

                <h2>Warehouse and import config</h2>
                <small>What type of articles to sync from SmartAccounts and what warehouses to use
                </small>
                <table class="form-table">
                    <tr valign="top">
                        <th>Import Services</th>
                        <td>
                            <input type="checkbox" v-model="settings.importServices">
                        </td>
                    </tr>
                    <tr valign="top">
                        <th>Import Products</th>
                        <td>
                            <input type="checkbox" v-model="settings.importProducts">
                        </td>
                    </tr>
                    <tr valign="top">
                        <th>Import Warehouse Inventory</th>
                        <td>
                            <input type="checkbox" v-model="settings.importInventory">
                        </td>
                    </tr>
                    <tr valign="top">
                        <th>Warehouse filter (Overrides others)</th>
                        <td>
                            <input type="text" v-model="settings.inventoryFilter"><br>
                            <small>Comma separate list of Inventory account (Laokonto) to use when synching product stock quantities from eg 10710,10741. If not empty then overrides filters above.</small>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th>What warehouse to use when sending sales invoice.</th>
                        <td>
                            <input type="text" v-model="settings.warehouseId"><br>
                            <small>Leave empty if not not relevant</small>
                        </td>
                    </tr>
                </table>

                <br>
                <br>
                <hr>
                <h2>Bank accounts</h2>
                <small>If currency and bank account mapping is set then given bank account name will be used for bank
                    payment
                    entry
                </small>
                <table class="form-table">
                    <thead>
                    <tr>
                        <th>Payment method</th>
                        <th>Currency code</th>
                        <th>SmartAccounts bank account name</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tr valign="middle" v-for="(item, index) in settings.currencyBanks">
                        <th>
                            <select v-model="settings.currencyBanks[index].payment_method">
                                <option v-for="(method, title) in paymentMethods" :value="method">{{title}}</option>
                            </select>
                        </th>
                        <td>
                            <a @click="removeCurrency(index)">X</a>
                            <input :data-vv-name="'currency_code_'+index"
                                   v-validate="{regex: /^[A-Z]{3}$/}"
                                   v-bind:class="{'notice notice-error':errors.first('currency_code_'+index)}"
                                   v-model="settings.currencyBanks[index].currency_code"
                                   placeholder="EUR"/>
                        </td>
                        <td>
                            <input size="30"
                                   :data-vv-name="'currency_bank_'+index"
                                   v-validate="{min: 2}"
                                   v-bind:class="{'notice notice-error':errors.first('currency_bank_'+index)}"
                                   v-model="settings.currencyBanks[index].currency_bank"
                                   placeholder="LHV EUR"/>
                        </td>
                        <td></td>
                    </tr>
                </table>
                <button @click="newCurrency" class="button-primary woocommerce-save-button">New mapping
                </button>
            </div>

            <br>
            <hr>
            <button @click="saveSettings" class="button-primary woocommerce-save-button" :disabled="!formValid">
                Save settings
            </button>

        </div>
        <?php
    }

    public static function enqueueScripts()
    {
        wp_register_script('sa_vue_js', plugins_url('js/sa-vue.js', __FILE__));
        wp_register_script('sa_axios_js', plugins_url('js/sa-axios.min.js', __FILE__));
        wp_register_script('sa_app_js', plugins_url('js/sa-app.js', __FILE__));
        wp_register_script('sa_vee_validate', plugins_url('js/sa-vee-validate.js', __FILE__));
        wp_register_script('sa_mini_toastr', plugins_url('js/sa-mini-toastr.js', __FILE__));

        wp_enqueue_script('sa_mini_toastr');
        wp_enqueue_script('sa_vue_js');
        wp_enqueue_script('sa_axios_js');
        wp_enqueue_script('sa_vee_validate', false, ['sa_vue_js'], null, true);
        wp_enqueue_script('sa_app_js', false, ['sa_vue_js', 'sa_axios_js', 'sa_mini_toastr'], null, true);


        wp_localize_script("sa_app_js",
            'sa_settings',
            [
                'ajaxUrl'        => admin_url('admin-ajax.php'),
                'settings'       => self::getSettings(),
                'paymentMethods' => self::getAvailablePaymentMethods()
            ]
        );
    }

    public static function optionsPage()
    {
        add_submenu_page('woocommerce', 'SmartAccounts settings', "SmartAccounts", 'manage_woocommerce',
            'SmartAccounts', 'SmartAccountsClass::options_page_html');
    }

    public static function getAvailablePaymentMethods()
    {
        $gateways         = WC()->payment_gateways->payment_gateways();
        $enabled_gateways = [];

        if ($gateways) {
            foreach ($gateways as $gateway) {
                if ($gateway->enabled == 'yes') {
                    $enabled_gateways[$gateway->title] = $gateway->id;
                }
            }
        }

        return $enabled_gateways;
    }

    //not very expensive to run every time when getting SA settings, better safe than sorry
    public static function convertOldSettings()
    {
        if (get_option('sa_api_pk')) {
            $settings                  = new stdClass();
            $settings->apiKey          = get_option('sa_api_pk');
            $settings->apiSecret       = get_option('sa_api_sk');
            $settings->defaultShipping = get_option('sa_api_shipping_code');
            $settings->defaultPayment  = get_option('sa_api_payment_account');
            update_option('sa_settings', json_encode($settings));
            delete_option('sa_api_pk');
            delete_option('sa_api_sk');
            delete_option('sa_api_shipping_code');
            delete_option('sa_api_payment_account');
        }

        $settings = json_decode(get_option('sa_settings')) ? json_decode(get_option('sa_settings')) : new stdClass();

        $gateways = WC()->payment_gateways->payment_gateways() ? WC()->payment_gateways->payment_gateways() : [];

        foreach ($gateways as $gateway) {
            $title = $gateway->title;
            $id    = $gateway->id;
            //move paid methods over to ID-s from title
            if (property_exists($settings, 'paymentMethodsPaid')) {
                if (property_exists($settings->paymentMethodsPaid, $title)) {
                    $settings->paymentMethodsPaid->$id = $settings->paymentMethodsPaid->$title;
                    unset($settings->paymentMethodsPaid->$title);
                }
            }

            //move currency bank accounts over to ID-s from title
            if (property_exists($settings, 'currencyBanks')) {
                $newCurrencyBanks = [];
                foreach ($settings->currencyBanks as $key => $value) {
                    if ($value->payment_method === $title) {
                        $value->payment_method = $id;
                    }
                    $newCurrencyBanks[] = $value;
                }
                $settings->currencyBanks = $newCurrencyBanks;
            }
        }
        update_option('sa_settings', json_encode($settings));
    }

    public static function loadAsyncClass()
    {
        new SmartAccountsArticleAsync();
    }
}
