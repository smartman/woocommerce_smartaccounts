<?php
/**
 * Plugin Name: SmartAccounts
 * Plugin URI: https://github.com/smartman/woocommerce_smartaccounts
 * Description: This plugin creates sales invoices in the smartaccounts.ee Online Accounting Software after Woocommerce order creation
 * Version: 1.2
 * Author: Margus Pala
 * Author URI: http://marguspala.com
 * License: GPLv2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 4.8.0
 * Tested up to: 4.9.5
 */


if ( ! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly


function smartaccounts_missing_wc_admin_notice()
{
    ?>
    <div class="notice notice-error">
        <p>SmartAccounts is WooCommerce plugin and requires WooCommerce plugin to be installed</p>
    </div>
    <?php
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    require_once('SmartAccountsClass.php');

    add_action('admin_menu', 'SmartAccountsClass::optionsPage');

    add_action('admin_init', 'SmartAccountsClass::registerSettings');
    add_action('woocommerce_order_status_processing', 'SmartAccountsClass::orderStatusProcessing');
    add_action('woocommerce_order_status_completed', 'SmartAccountsClass::orderStatusProcessing');
} else {
    add_action('admin_notices', 'smartaccounts_missing_wc_admin_notice');
}