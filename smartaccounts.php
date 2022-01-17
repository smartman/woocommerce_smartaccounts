<?php
/**
 * Plugin Name: SmartAccounts
 * Plugin URI: https://github.com/smartman/woocommerce_smartaccounts
 * Description: This plugin creates sales invoices in the smartaccounts.ee Online Accounting Software after Woocommerce order creation
 * Version: 3.8.1
 * Author: Margus Pala
 * Author URI: https://marguspala.com
 * License: GPLv2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 4.8.0
 * Tested up to: 5.8.3
 */

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

function smartaccounts_missing_wc_admin_notice()
{
    ?>
    <div class="notice notice-error">
        <p>SmartAccounts is WooCommerce plugin and requires WooCommerce plugin to be installed</p>
    </div>
    <?php
}

if (is_plugin_active('woocommerce/woocommerce.php')) {
    require_once('SmartAccountsClass.php');
    require_once('SmartAccountsArticleAsync.php');

    add_action('admin_enqueue_scripts', 'SmartAccountsClass::enqueueScripts');
    add_action('admin_menu', 'SmartAccountsClass::optionsPage');

    //if no configured invoice nor offer sending statuses configured then use default
    $settings      = json_decode(get_option('sa_settings'));
    $countStatuses = 0;
    if (isset($settings->statuses) && is_array($settings->statuses)) {
        foreach ($settings->statuses as $status) {
            $countStatuses++;
            add_action("woocommerce_order_status_$status", 'SmartAccountsClass::orderStatusProcessing');
        }
    }
    if (isset($settings->offer_statuses) && is_array($settings->offer_statuses)) {
        foreach ($settings->offer_statuses as $status) {
            $countStatuses++;
            add_action("woocommerce_order_status_$status", 'SmartAccountsClass::orderOfferStatusProcessing');
        }
    }
    if ($countStatuses === 0) {
        add_action('woocommerce_order_status_processing', 'SmartAccountsClass::orderStatusProcessing');
        add_action('woocommerce_order_status_completed', 'SmartAccountsClass::orderStatusProcessing');
    }

    add_filter('woocommerce_email_attachments', 'SmartAccountsClass::attachPdf', 10, 4);

    add_action('sa_retry_failed_job', 'SmartAccountsClass::retryFailedOrders');

    add_action("wp_ajax_sa_save_settings", "SmartAccountsClass::saveSettings");
    add_action("wp_ajax_sa_sync_products", "SmartAccountsArticleAsync::syncSaProducts");
    add_action("wp_ajax_nopriv_sa_sync_products", "SmartAccountsArticleAsync::syncSaProducts");
    add_action('init', 'SmartAccountsClass::loadAsyncClass');
} else {
    add_action('admin_notices', 'smartaccounts_missing_wc_admin_notice');
}
