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

        $settings->paymentMethods = new stdClass();

        foreach ($unSanitized->paymentMethods as $key => $method) {
            $settings->paymentMethods->$key     = sanitize_text_field($method);
            $settings->paymentMethodsPaid->$key = $unSanitized->paymentMethodsPaid->$key == true;
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

            <h2>General settings</h2>
            <table class="form-table">
                <tr valign="top">
                    <th>SmartAccounts public key</th>
                    <td>
                        <input size="50" v-model="settings.apiKey"/>
                    </td>
                </tr>

                <tr valign="top">
                    <th>SmartAccounts private key</th>
                    <td>
                        <input size="50" v-model="settings.apiSecret"/>
                    </td>
                </tr>

                <tr valign="top">
                    <th>SmartAccounts shipping article name</th>
                    <td>
                        <input size="50" v-model="settings.defaultShipping"/>
                    </td>
                </tr>
                <tr valign="top">
                    <th>SmartAccounts payments default bank account name</th>
                    <td>
                        <input size="50" v-model="settings.defaultPayment"/>
                    </td>
                </tr>
                <tr valign="top">
                    <th>Show advanced settings</th>
                    <td>
                        <input type="checkbox" v-model="settings.showAdvanced"/>
                    </td>
                </tr>
            </table>

            <div v-show="settings.showAdvanced">
                <h2>Payment methods</h2>
                <small>Which payment methods in WooCommerce match which in SmartAccounts. If mapping missing then
                    default is used. Last checkbox tells if payment should be created for orders with this payment
                    method.
                </small>
                <table class="form-table">
                    <tr valign="top" v-for="method in paymentMethods">
                        <th>Method: {{method}}</th>
                        <td>
                            <input size="50" v-model="settings.paymentMethods[method]"/>
                            <label>Mark paid? </label><input type="checkbox"
                                                             v-model="settings.paymentMethodsPaid[method]">
                        </td>
                    </tr>

                </table>
            </div>
            <button @click="saveSettings" class="button-primary woocommerce-save-button" :disabled="!formValid">Save
                settings
            </button>
            <small v-if="!formValid">All general settings are required</small>

        </div>
        <?php
    }


    public static function enqueueScripts()
    {
        wp_register_script('sa_vue_js', plugins_url('js/sa-vue.js', __FILE__));
        wp_register_script('sa_axios_js', plugins_url('js/sa-axios.min.js', __FILE__));
        wp_register_script('sa_app_js', plugins_url('js/sa-app.js', __FILE__));

        wp_enqueue_script('sa_vue_js');
        wp_enqueue_script('sa_axios_js');
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
        $gateways         = WC()->payment_gateways->get_available_payment_gateways();
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