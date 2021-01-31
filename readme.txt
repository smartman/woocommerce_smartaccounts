=== Plugin Name ===
Tags: SmartAccounts, smartaccounts, WooCommerce
Requires at least: 4.8
Tested up to: 5.6
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
* Product final price is taken from SmartAccounts unless sale price is set. If sale price is set manually then only regular price is changed.

== Note about Woo and SmartAccounts client matching ==

If order has meta _billing_regcode then plugin looks for Client with this registration code from SmartAccounts.

Otherwise customer name is used to match. If SmartAccounts with this name does not exist then new SmartAccounts Client is created.
If user was anonymous then general client is created with the country name.

As people often type their official company name incorrectly then fuzzy matching is performed.
OÜ, AS, MTÜ, KÜ and FIE are removed from the beginning and end of the name and first match with the "main part of name" is used.

== What are the plugin limitations? ==
* Order changes and cancelling is not handled automatically
* All items have one VAT percentage
* SmartAccounts article code must be added to the Woocommerce product SKU if existing SmartAccounts article must be used
* If product is not found then "Woocommerce product NAME" Article is created to SmartAccounts
* Plugin does not handle errors which come from exceeding rate limits or unpaid SmartAccounts invoices.
* If there are errors then invoices might be missing and rest of the Woocommerce functionality keeps on working
* SmartAccounts API key, API secret and Payment account name must be configured before plugin will start working properly.
* If plugin creates offer and this offer is deleted by the time invoice is created then creating invoice will fail.
* Exact shipping method is not sent to SmartAccounts

These shortcomings can be resolved by additional development. If these are problem for you then please get in touch with margus.pala@gmail.com

== Changelog ==

= 3.2 =
Product import filters and Warehouse setting for new invoices.

= 3.1 =
VAT number order meta field name is now configurable. If configured and vat number exists then it will be used when creating new client in SmartAccounts

= 3.0.6 =
Improved pricing when sale price is set.

= 3.0.2 =
Saves SmartAccount offer human readable number to Woo order meta

= 3.0.0 =
Allows sending Woo orders to SmartAccounts as Offer.
Connects offers with invoices when creating Invoice later.
Includes phone when sending new Client to SmartAccounts
Improved detection of Client by name if OÜ, FIE etc is not written correctly to the billing info
Possible to connect Woo and SmartAccounts clients with company registration code in order meta using _billing_regcode

= 2.2.5 =
Better handling of payment methods

= 2.2.4 =
Better discount and cents fraction rounding

= 2.2.3 =
Product backorders setting overwriting removed

= 2.2.2 =
Support over 1000 currency unit invoices

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
