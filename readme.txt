=== Plugin Name ===
Tags: SmartAccounts, smartaccounts, WooCommerce
Requires at least: 4.8
Tested up to: 4.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin creates sales invoices in the smartaccounts.ee Online Accounting Software after Woocommerce order creation

== Description ==

After woocommerce order goes to status processing then this plugin:
* Creates customer to SmartAccounts if no existing customer with same name and e-mail found
* Creates Articles in Smartaccounts of Woocommerce product on the order if existing products are not found.
 Woocommerce product SKU is compared with SmartAccounts article code.
* Creates sales in invoice and connects it with the right customer and adds all the Articles on the invoice.
* Marks the sales invoice as paid.

== Installation ==

Configuration is needed to make this plugin work. After actvating the plugin find its configuration page under Woocommerce menu item and:

* Make sure you have SmartAccounts package with API access
* Copy SmartAccounts API key and secret from SmartAccounts interface.
* Add bank account name you want to be used when making the invoices paid

== Frequently Asked Questions ==

= What are the plugin limitations? =
This plugin creates sales invoice to SmartAccounts platform every time when order is changed into "Processing" status.
Duplicate invoices could be created if order is changed to Processing status many times.
Invoice is always marked paid regardless if the payment is actually made or not yet. For example if Cash on Delivery payment method is used.
Order changes and cancelling is not handled automatically
Shipping item has code "shipping"
All items have one VAT percentage
SmartAccounts article code must be added to the Woocommerce product SKU if existing SmartAccounts article must be used
If product is not found then "Woocommerce product NAME" Article is created to SmartAccounts
Plugin does not handle errors which come from exceeding rate limits or unpaid SmartAccounts invoices.
If there are errors then invoices might be missing and rest of the Woocommerce functionality keeps on working
SmartAccounts API key, API secret and Payment account name must be configured before plugin will start working properly.


== Screenshots ==


== Changelog ==

= 0.9 =
* Plugin is ready for production testing