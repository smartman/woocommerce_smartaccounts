<?php
/**
 * Plugin Name: SmartAccounts
 * Plugin URI: http://marguspala.com
 * Description: This plugin creates sales invoices in the smartaccounts.ee Online Accounting Software after Woocommerce order creation
 * Version: 0.9
 * Author: Margus Pala
 * Author URI: http://marguspala.com
 * Requires at least: 4.8.0
 * Tested up to: 4.9
 */



if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

require_once( 'SmartAccounts.php' );

add_action( 'admin_menu', 'SmartAccounts::optionsPage' );

add_action( 'admin_init', 'SmartAccounts::registerSettings' );
add_action( 'woocommerce_order_status_processing', 'SmartAccounts::orderStatusProcessing' );
