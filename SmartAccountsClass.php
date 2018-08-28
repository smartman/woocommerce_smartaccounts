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
        $settings->showAdvanced    = $unSanitized->showAdvanced == true;

        $settings->paymentMethodsPaid = new stdClass();
        foreach ($unSanitized->paymentMethodsPaid as $key => $method) {
            $settings->paymentMethodsPaid->$key = $unSanitized->paymentMethodsPaid->$key == true;
        }

        $settings->countryObjects = [];
        foreach ($unSanitized->countryObjects as $countryObject) {
            $newCountryObject            = new stdClass();
            $newCountryObject->country   = sanitize_text_field($countryObject->country);
            $newCountryObject->object_id = sanitize_text_field($countryObject->object_id);
            $newCountryObject->non_eu    = $countryObject->non_eu == true;
            if ( ! $newCountryObject->country || ! $newCountryObject->object_id) {
                continue;
            }
            array_push($settings->countryObjects, $newCountryObject);
        }

        $settings->currencyBanks = [];
        foreach ($unSanitized->currencyBanks as $currencyBank) {
            $newCurrencyBank                = new stdClass();
            $newCurrencyBank->currency_code = sanitize_text_field($currencyBank->currency_code);
            $newCurrencyBank->currency_bank = sanitize_text_field($currencyBank->currency_bank);
            if ( ! $newCurrencyBank->currency_code || ! $newCurrencyBank->currency_bank) {
                continue;
            }
            array_push($settings->currencyBanks, $newCurrencyBank);
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

            <button @click="saveSettings" class="button-primary woocommerce-save-button" :disabled="!formValid">Save
                settings
            </button>
            <div v-if="!formValid" class="notice notice-error">
                <small>All general settings are required and filled fields correct</small>
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
                    <th>SmartAccounts shipping article name</th>
                    <td>
                        <input size="50"
                               data-vv-name="defaultShipping"
                               v-validate="'required'"
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
                    <th>Show advanced settings</th>
                    <td>
                        <input type="checkbox" v-model="settings.showAdvanced"/>
                    </td>
                </tr>
            </table>

            <hr>
            <div v-show="settings.showAdvanced">
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
                <hr>
                <h2>Country objects</h2>
                <small>If customer country mapping exists then following object ID is set when creating sales invoice to
                    SmartAccounts
                </small>
                <table class="form-table">
                    <thead>
                    <tr>
                        <th>2 letter ISO ountry code</th>
                        <th>Object ID</th>
                        <th>Non EU</th>
                    </tr>
                    </thead>
                    <tr valign="middle" v-for="(item, index) in settings.countryObjects">
                        <th>
                            <a @click="removeCountryObject(index)">X</a>
                            <input :data-vv-name="'co_'+index"
                                   v-validate="{regex: /^[A-Z]{2}$/}"
                                   v-bind:class="{'notice notice-error':errors.first('co_'+index)}"
                                   v-model="settings.countryObjects[index].country"
                                   placeholder="EE"/>
                        </th>
                        <td>
                            <input size="30"
                                   :data-vv-name="'co_id_'+index"
                                   v-validate="{regex: /^[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}$/}"
                                   v-bind:class="{'notice notice-error':errors.first('co_id_'+index)}"
                                   v-model="settings.countryObjects[index].object_id"
                                   placeholder="7828f2d7-968f-442d-8d88-da18eee72434"/>
                        </td>
                        <td>
                            <input type="checkbox" v-model="settings.countryObjects[index].non_eu">
                        </td>
                    </tr>
                </table>
                <button @click="newCountryObject" class="button-primary woocommerce-save-button">New country objects
                    mapping
                </button>

                <br>
                <br>
                <hr>
                <h2>Currency bank accounts</h2>
                <small>If currency bank account mapping is set then given bank account will be used for bank payment
                    entry
                </small>
                <table class="form-table">
                    <thead>
                    <tr>
                        <th>Currency code</th>
                        <th>SmartAccounts bank account name</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tr valign="middle" v-for="(item, index) in settings.currencyBanks">
                        <th>
                            <a @click="removeCurrency(index)">X</a>
                            <input :data-vv-name="'currency_code_'+index"
                                   v-validate="{regex: /^[A-Z]{3}$/}"
                                   v-bind:class="{'notice notice-error':errors.first('currency_code_'+index)}"
                                   v-model="settings.currencyBanks[index].currency_code"
                                   placeholder="EUR"/>
                        </th>
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
                <button @click="newCurrency" class="button-primary woocommerce-save-button">New currency
                </button>

            </div>
            <br>
            <hr>
            <button @click="saveSettings" class="button-primary woocommerce-save-button" :disabled="!formValid">Save
                settings
            </button>
            <div v-if="!formValid" class="notice notice-error">
                <small>All general settings are required and filled fields correct</small>
            </div>


        </div>
        <?php
    }


    public static function enqueueScripts()
    {
        wp_register_script('sa_vue_js', plugins_url('js/sa-vue.js', __FILE__));
        wp_register_script('sa_axios_js', plugins_url('js/sa-axios.min.js', __FILE__));
        wp_register_script('sa_app_js', plugins_url('js/sa-app.js', __FILE__));
        wp_register_script('sa_vee_validate', plugins_url('js/sa-vee-validate.js', __FILE__));

        wp_enqueue_script('sa_vue_js');
        wp_enqueue_script('sa_axios_js');
        wp_enqueue_script('sa_vee_validate', false, ['sa_vue_js'], null, true);
        wp_enqueue_script('sa_app_js', false, ['sa_vue_js', 'sa_axios_js'], null, true);

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
}
