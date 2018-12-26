<?php

include_once('SmartAccountsClient.php');
include_once('SmartAccountsSalesInvoice.php');
include_once('SmartAccountsApi.php');
include_once('SmartAccountsPayment.php');
include_once('SmartAccountsArticle.php');

class SmartAccountsClass
{
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
        } catch (Exception $exception) {
            error_log("SmartAccounts error: " . $exception->getMessage() . " " . $exception->getTraceAsString());

            $invoiceIdsString = get_option('sa_failed_orders');
            $invoiceIds       = json_decode($invoiceIdsString);
            if (is_array($invoiceIds)) {
                error_log("Adding $order_id to failed orders array $invoiceIdsString to be retried later");
                $invoiceIds[] = $order_id;
                update_option('sa_failed_orders', json_encode($invoiceIds));
            } else {
                error_log("Adding $order_id to new failed orders array. previously $invoiceIdsString");
                $invoiceIds = [$order_id];
                update_option('sa_failed_orders', json_encode($invoiceIds));
            }

            wp_schedule_single_event(time() + 129600, 'sa_retry_failed_job');
        }
    }

    public function retryFailedOrders()
    {
        $invoiceIdsString = get_option('sa_failed_orders');
        error_log("Retrying orders $invoiceIdsString");

        $retryCount = json_decode(get_option('sa_failed_orders_retries'));
        if ( ! is_array($retryCount)) {
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
        $objectId                  = sanitize_text_field($unSanitized->objectId);
        if (preg_match("/^[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}$/", $objectId)) {
            $settings->objectId = $objectId;
        } else {
            $settings->objectId = null;
        }
        if ( ! $settings->defaultShipping) {
            $settings->defaultShipping = "shipping";
        }

        $settings->backorders = $unSanitized->backorders == true;

        $settings->showAdvanced = $unSanitized->showAdvanced == true;

        $settings->paymentMethodsPaid = new stdClass();
        foreach ($unSanitized->paymentMethodsPaid as $key => $method) {
            $settings->paymentMethodsPaid->$key = $unSanitized->paymentMethodsPaid->$key == true;
        }

        $settings->currencyBanks = [];
        foreach ($unSanitized->currencyBanks as $currencyBank) {
            $newCurrencyBank                 = new stdClass();
            $newCurrencyBank->payment_method = sanitize_text_field($currencyBank->payment_method);
            $newCurrencyBank->currency_code  = sanitize_text_field($currencyBank->currency_code);
            $newCurrencyBank->currency_bank  = sanitize_text_field($currencyBank->currency_bank);
            if ( ! $newCurrencyBank->currency_code || ! $newCurrencyBank->currency_bank) {
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
        if (count($settings->statuses) == 0) {
            $settings->statuses = ['completed', 'processing'];
        }

        update_option('sa_settings', json_encode($settings));

        wp_send_json(['status' => "OK", 'settings' => $settings]);
    }

    public static function getSettings()
    {
        if (get_option('sa_api_pk')) {
            self::convertOldSettings();
        }

        $currentSettings = json_decode(get_option('sa_settings') ? get_option('sa_settings') : "");

        if ( ! $currentSettings) {
            $currentSettings = new stdClass();
        }
        if ( ! isset($currentSettings->backorders)) {
            $currentSettings->backorders = false;
        }
        if ( ! is_object($currentSettings->paymentMethods)) {
            $currentSettings->paymentMethods = new stdClass();
        }
        if ( ! is_object($currentSettings->paymentMethodsPaid)) {
            $currentSettings->paymentMethodsPaid = new stdClass();
        }
        if ( ! is_array($currentSettings->countryObjects)) {
            $currentSettings->countryObjects = [];
        }
        if ( ! is_array($currentSettings->currencyBanks)) {
            $currentSettings->currencyBanks = [];
        }
        if ( ! is_array($currentSettings->statuses)) {
            $currentSettings->statuses = [
                'processing',
                'completed'
            ];
        }

        return $currentSettings;
    }

    public static function options_page_html()
    {
        if ( ! current_user_can('manage_options')) {
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
                                :disabled="syncInProgress">Sync products
                            from SmartAccounts
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
                <h2>Order statuses to send to SmartAccounts</h2>
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

                <hr>
                <h2>Payment methods</h2>
                <small>Configure which payment methods are paid immediately and invoices can be created with payments
                </small>
                <table class="form-table">
                    <tr valign="top" v-for="method in paymentMethods">
                        <th>Method: {{method}}</th>
                        <td>
                            <label>Mark paid? </label>
                            <input type="checkbox" v-model="settings.paymentMethodsPaid[method]">
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
                                <option v-for="method in paymentMethods">{{method}}</option>
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
                <br>
                <hr>
                <h2>Product import settings</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th>Allow backorders</th>
                        <td>
                            <label>Products on sale with negative stock </label>
                            <input type="checkbox" v-model="settings.backorders">
                        </td>
                    </tr>
                </table>
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
                    $enabled_gateways[] = $gateway->title;
                }
            }
        }

        return $enabled_gateways;
    }

    public static function convertOldSettings()
    {
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

    public static function loadAsyncClass()
    {
        new SmartAccountsArticleAsync();
    }
}
