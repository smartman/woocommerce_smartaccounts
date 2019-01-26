=== Plugin Name ===
Tags: SmartAccounts, smartaccounts, WooCommerce
Requires at least: 4.8
Tested up to: 5.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Send sales invoices to smartaccounts.ee Online Accounting Software and sync products with warehouse quantities.

== Description ==

This plugin:
* Creates Customer to SmartAccounts if no existing customer with same name and e-mail found
* Creates Articles in Smartaccounts of Woocommerce product on the order if existing products are not found.
Woocommerce product SKU is compared with SmartAccounts article code.
* Creates sales in invoice and connects it with the right customer and adds all the Articles on the invoice.
* Marks the sales invoice as paid if needed.
* Imports Articles from SmartAccounts to Woocommerce

== Installation ==

Configuration is needed to make this plugin work. After activating the plugin find its configuration page under Woocommerce menu item and:

* Make sure you have SmartAccounts package with API access
* Copy SmartAccounts API key and secret from SmartAccounts interface. If you don't have SmartAccounts API key (please check Settings - Connected Services) you can contact SmartAccounts support to enable API service for your account. Additional conditions and charges may apply to API service.
* Add bank account name you want to be used when making the invoices paid
* You can also change SmartAccounts code for shipping
* For periodical price and stock updates configure cron to call GET domain/wp-admin/admin-ajax.php?action=sa_sync_products
* "Payment methods" section allows configuring which payment methods are marked paid in SmartAccounts immediately
* "Bank accounts" section allows configuring which payment method and currency corresponds to which SmartAccounts bank account name. If mapping is missing then default is used

== Importing products from SmartAccounts ==
* Products must be active sales items and of type Warehouse Item or Product. Services are not imported.
* Product final sale price is price taken from SmartAccounts. If discount prices is needed then Regular price needs to be changed and then Regular price is not changed.

== Frequently Asked Questions ==

= What are the plugin limitations? =
* Order changes and cancelling is not handled automatically
* All items have one VAT percentage
* SmartAccounts article code must be added to the Woocommerce product SKU if existing SmartAccounts article must be used
* If product is not found then "Woocommerce product NAME" Article is created to SmartAccounts
* Plugin does not handle errors which come from exceeding rate limits or unpaid SmartAccounts invoices.
* If there are errors then invoices might be missing and rest of the Woocommerce functionality keeps on working
* SmartAccounts API key, API secret and Payment account name must be configured before plugin will start working properly.

== Changelog ==

= 2.2.1 =
If address is longer than 64 characters then it is truncated when sending to SmartAccounts as longer addresses are not supported

= 2.2 =
Import products from SmartAccounts available

= 2.0 =
Many advanced configuration settings available

=1.4=
Can handle SKU with spaces
Product name instead of long description in SmartAccounts row description

=1.3=
Cart based discounts are handled better.