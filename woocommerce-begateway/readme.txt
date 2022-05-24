=== BeGateway Payment Gateway for WooCommerce ===
Contributors: begateway
Author URI: https://begateway.com
Requires at least: 4.7
Tested up to: 5.9
Stable tag: 2.0.2
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept payments in WooCommerce with BeGateway Payment Gateway

== Description ==

Start accepting card and non-card payments on your WooCommerce by using the BeGateway integration.

= Key benefits =

  * Accepting debit and credit card payments.
  * Accepting non-card payments.
  * Automatically updating Order statuses using beGateway webhooks notifications.
  * Capture/Cancel/Refund payments in WooCommerce.
  * It supports [WooCommerce™ Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)

= Setup instructions =

Go to _WooCommerce → Settings → Checkout_

At the top of the page you will see a link entitled `BeGateway` – click on that to bring up the setup page.
This will bring up a page displaying all the options that you can select to administer the payment module – these are all fairly self-explanatory.

* set _Title_ e.g. _Credit or debit card_
* set _Admin Title_ e.g. _beGateway_
* set _Description_ e.g. _Visa, Mastercard_. You are free to put all payment cards supported by your acquiring payment agreement.
* Transaction type: _Authorization_ or _Payment_
* Check _Enable admin capture etc_ if you want to allow administrators
  to issue refunds or captures from WooCommerce backend
* Check _Debug Log_ if you want to log messages between _beGateway_
  and WooCommerce

Enter in fields as follows:

* _Shop Id_
* _Shop Key_
* _Payment gateway domain_
* _Payment page domain_
* and etc

values received from your payment processor.

* click _Save changes_

Now the plugin is configured.

= Testing =

You can use the following information to adjust the payment method in test mode:

* __Shop ID:__ 361
* __Shop Key:__ b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d
* __Checkout page domain:__ checkout.begateway.com

Use the following test card to make successful test payment:

* Card number: `4200000000000000`
* Name on card: `JOHN DOE`
* Card expiry date: `01/30`
* CVC: `123`

Use the following test card to make failed test payment:

* Card number: `4005550000000019`
* Name on card: `JOHN DOE`
* Card expiry date: `01/30`
* CVC: `123`

Use the guide https://docs.woocommerce.com/document/testing-subscription-renewal-payments/ to test subscription renewal payments.

== Installation ==

= Using The WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for __WooCommerce BeGateway Payment Gateway__
3. Click _Install Now_
4. Activate the plugin on the Plugin dashboard

= Uploading in WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Navigate to the 'Upload' area
3. Select `woocommerce-begateway.zip` from your computer
4. Click _Install Now_
5. Activate the plugin in the Plugin dashboard

= Using FTP =

1. Download `woocommerce-begateway.zip`
2. Extract the woocommerce-begateway.zip-begateway` directory to your computer
3. Upload the `woocommerce-begateway.zip` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the Plugin dashboard

== Screenshots ==

1. Setup step 1 - Select plugin settings.
2. Setup step 2 - Configure plugin.

== Changelog ==

= 2.0.2 =
* official release
