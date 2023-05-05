=== BeGateway Payment Gateway for WooCommerce ===
Contributors: begateway
Author URI: https://begateway.com
Requires at least: 4.7
Tested up to: 6.2
Stable tag: 2.0.6
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept payments in WooCommerce with BeGateway Payment Gateway

== Description ==

Start accepting card and non-card payments on your WooCommerce by using the BeGateway integration.

= Key benefits =

  * Accepting debit and credit card payments.
  * Accepting non-card payments.
  * Automatically updating Order statuses using BeGateway webhooks notifications.
  * Capture/Cancel/Refund payments in WooCommerce.
  * It supports [WooCommerce™ Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)

= Setup instructions =

Go to _WooCommerce → Settings → Checkout_

At the top of the page you will see a link entitled `BeGateway` – click on that to bring up the setup page.
This will bring up a page displaying all the options that you can select to administer the payment module – these are all fairly self-explanatory.

* set _Title_ e.g. _Credit or debit card_
* set _Admin Title_ e.g. _BeGateway_
* set _Description_ e.g. _Visa, Mastercard_. You are free to put all payment cards supported by your acquiring payment agreement.
* Transaction type: _Authorization_ or _Payment_
* Check _Enable admin capture etc_ if you want to allow administrators
  to issue refunds or captures from WooCommerce backend
* Check _Debug Log_ if you want to log messages between _BeGateway_
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

* __Shop ID:__ `361`
* __Shop Key:__ `b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d`
* __Payment page domain:__ `checkout.begateway.com`
* __Payment gateway domain:__ `demo-gateway.begateway.com`

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

Use [the guide](https://docs.woocommerce.com/document/testing-subscription-renewal-payments/) to test subscription renewal payments.

== Installation ==

= Using WordPress dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for __BeGateway Payment Gateway for WooCommerce__
3. Click _Install Now_
4. Activate the plugin on the Plugin dashboard

= Uploading via WordPress dashboard =

1. Download [wc-begateway-payment.zip](https://github.com/begateway/woocommerce-payment-module/raw/master/wc-begateway-payment.zip)
2. Navigate to the 'Add New' in the plugins dashboard
3. Navigate to the 'Upload' area
4. Select `wc-begateway-payment.zip` from your computer
5. Click _Install Now_
6. Activate the plugin in the Plugin dashboard

= Using FTP =

1. Download [wc-begateway-payment.zip](https://github.com/begateway/woocommerce-payment-module/raw/master/wc-begateway-payment.zip)
2. Extract `wc-begateway-payment.zip` to a directory to your computer
3. Upload the extracted directory `wc-begateway-payment` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the Plugin dashboard

== Screenshots ==

1. Setup step 1 - Select plugin settings.
2. Setup step 2 - Configure plugin.

== Changelog ==

= 2.0.8 =
* fix readme links

= 2.0.7 =
* update beGateway PHP API library use

= 2.0.6 =
* add support of WordPress 6.2

= 2.0.5 =
* code fixes

= 2.0.2 =
* official release
