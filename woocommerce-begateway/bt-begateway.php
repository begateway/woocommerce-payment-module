<?php
/*
Plugin Name: WooCommerce BeGateway Payment Gateway
Plugin URI: https://github.com/begateway/woocommerce-payment-module
Description: Extends WooCommerce with BeGateway payment gateway.
Version: 1.4.2
Author: BeGateway development team

Text Domain: woocommerce-begateway
Domain Path: /languages/

WC requires at least: 3.2.0
WC tested up to: 5.0.0
*/

if ( ! defined( 'ABSPATH' ) )
{
    exit; // Exit if accessed directly
}

require_once dirname(  __FILE__  ) . '/begateway-api-php/lib/BeGateway.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

load_plugin_textdomain('woocommerce-begateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
add_action('plugins_loaded', 'woocommerce_begateway_init', 0);

//Launch plugin
function woocommerce_begateway_init()
{
  if (!class_exists('WC_Payment_Gateway')) {
    return;
  }

  class WC_Gateway_BeGateway extends WC_Payment_Gateway
  {
    public $id = 'begateway';
    public $icon;//not used
    public $has_fields = true;
    public $method_title;
    public $title;
    public $settings;
    protected $log;

    /**
     * constructor
     *
     */
    function __construct()
    {
      global $woocommerce;
      $this->supports = array(
        'products',
        'refunds',
        'tokenization',
        'subscriptions',
        'subscription_cancellation',
        'subscription_suspension',
        'subscription_reactivation',
        'subscription_amount_changes',
        'subscription_date_changes',
        'subscription_payment_method_change', // Subs 1.n compatibility.
        'subscription_payment_method_change_customer',
        'subscription_payment_method_change_admin',
        'multiple_subscriptions',
      );

      // load form fields
      $this->init_form_fields();
      // initialise settings
      $this->init_settings();
      // variables
      $this->title   = $this->settings['title'];
      //admin title
      if ( current_user_can( 'manage_options' ) ){
        $this->title = $this->settings['admin_title'];
      }

      //callback URL - hooks into the WP/WooCommerce API and initiates the payment class for the bank server so it can access all functions
      $this->notify_url = WC()->api_request_url('WC_Gateway_BeGateway', is_ssl());
      $this->notify_url = str_replace('0.0.0.0','webhook.begateway.com:8443', $this->notify_url);

      $this->method_title             = $this->title;
      $this->description              = $this->settings['description'];

      add_action('woocommerce_receipt_begateway', array( $this, 'receipt_page'));
      add_action('woocommerce_api_bt_begateway', array( $this, 'check_ipn_response' ) );
      add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
			if ( ! $this->get_compatibility_mode() ) {
				add_action( 'woocommerce_order_status_processing_to_completed', array( $this, 'capture_payment' ) );
			} else {
				add_action( 'woocommerce_order_status_processing_to_completed', array(
					$this,
					'maybe_capture_warning'
				) );
			}
			add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );

    } // end __construct

    public function admin_options()
    {
      echo '<h3>' . __('BeGateway', 'woocommerce-begateway') . '</h3>';
      echo '<table class="form-table">';
      // generate the settings form.
      $this->generate_settings_html();
      echo '</table><!--/.form-table-->';
    } // end admin_options()


    public function init_form_fields() {
      $this->form_fields = include(plugin_basename(__FILE__) . '/includes/settings-begateway.php');
    }

    function generate_begateway_form( $order_id ) {
      //creates a self-submitting form to pass the user through to the beGateway server
      global $woocommerce;
      $order = new WC_order( $order_id );
      $this->log('Generating payment form for order ' . $order->get_order_number());

      // Order number & Cart Contents for description field - may change
      $item_loop = 0;
      //grab the langauge

      $lang = explode('_', get_locale());
      $lang = $lang[0];

      $token = new \BeGateway\GetPaymentToken;
      $this->_init();

      if ($this->settings['transaction_type'] == 'authorization') {
        $token->setAuthorizationTransactionType();
      }

      $token->money->setCurrency(get_woocommerce_currency());
      $token->money->setAmount($order->get_total());
      $token->setDescription(__('Order', 'woocommerce') . ' # ' .$order->get_order_number());
      $token->setTrackingId($order->get_id());
      $token->customer->setFirstName($order->get_billing_first_name());
      $token->customer->setLastName($order->get_billing_last_name());
      $token->customer->setCountry($order->get_billing_country());
      $token->customer->setCity($order->get_billing_city());
      $token->customer->setPhone($order->get_billing_phone());
      $token->customer->setZip($order->get_billing_postcode());
      $token->customer->setAddress($order->get_billing_address_1() . $order->get_billing_address_2());
      $token->customer->setEmail($order->get_billing_email());

      if (in_array($order->get_billing_country(), array('US','CA'))) {
        $token->customer->setState($order->get_billing_state());
      }

      $token->setSuccessUrl(esc_url_raw( $this->get_return_url($order) ) );
      $token->setDeclineUrl( esc_url_raw( $order->get_cancel_order_url_raw() ) );
      $token->setFailUrl( esc_url_raw( $order->get_cancel_order_url_raw() ) );
      $token->setCancelUrl( esc_url_raw( $order->get_cancel_order_url_raw() ) );
      $token->setNotificationUrl($this->notify_url);

      $token->setExpiryDate(date("c", intval($this->settings['payment_valid']) * 60 + time() + 1));

      $token->setLanguage($lang);

      if ($this->settings['enable_bankcard'] == 'yes') {
        $cc = new \BeGateway\PaymentMethod\CreditCard;
        $token->addPaymentMethod($cc);
      }

      if ($this->settings['enable_bankcard_halva'] == 'yes') {
        $halva = new \BeGateway\PaymentMethod\CreditCardHalva;
        $token->addPaymentMethod($halva);
      }

      if ($this->settings['enable_erip'] == 'yes') {
        $erip = new \BeGateway\PaymentMethod\Erip(array(
          'order_id' => $order_id,
          'account_number' => ltrim($order->get_order_number()),
          'service_no' => $this->settings['erip_service_no']
        ));
        $token->addPaymentMethod($erip);
      }

      if ($this->settings['mode'] == 'test') {
        $token->setTestMode(true);
      }

      $this->log('Requesting token for order ' . $order->get_order_number());
      $token->additional_data->setContract(['recurring', 'card_on_file']);

      $response = $token->submit();

      if(!$response->isSuccess()) {

        $this->log('Unable to get payment token on order: ' . $order_id . 'Reason: ' . $response->getMessage());

        wc_add_notice(__('Error to get a payment token.', 'woocommerce-begateway'), 'error');
        wc_add_notice($response->getMessage(), 'error');
      } else {
      //now look to the result array for the token
        $payment_url=$response->getRedirectUrlScriptName();
        update_post_meta(  ltrim( $order->get_order_number(), '#' ), '_Token', $token );

        $this->log('Token received, forwarding customer to: '.$payment_url);

        $this->enqueueWidgetScripts(array(
            'checkout_url' => \BeGateway\Settings::$checkoutBase,
            'token' => $response->getToken(),
            'cancel_url' => $order->get_cancel_order_url()
          )
        );

        return '
          <script>
            function woocommerce_start_begateway_payment(e) {
              // check if BeGateway library is loaded well
              if (typeof woocommerce_start_begateway_payment_widget === "function" ) {
                e.preventDefault();
                woocommerce_start_begateway_payment_widget();
                return false;
              } else {
                return true;
              }
            }
          </script>
          <form action="'.$payment_url.'" method="post" id="begateway_payment_form" onSubmit="return woocommerce_start_begateway_payment(event);">
            <input type="hidden" name="token" value="' . $response->getToken() . '">
            <input type="submit" class="button alt" id="submit_begateway_payment_form" value="'.__('Make payment', 'woocommerce-begateway').'" />
            <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order', 'woocommerce-begateway').'</a>
          </form>
        ';
      }
    }

    function process_payment( $order_id ) {
      global $woocommerce;

      $order = new WC_Order( $order_id );

      // Return payment page
      return array(
        'result'    => 'success',
        'redirect'	=> add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url( true ))
      );
    }
    // end process_payment

    function receipt_page( $order ) {

      echo $this->generate_begateway_form( $order );

    }

    function thankyou_page()
    {
      if ($this->description) echo wpautop(wptexturize($this->description));
    } // end thankyou_page

    private function plugin_url()
    {
      return $this->plugin;
    }// end plugin_url

    /**
     *this function is called via the wp-api when the begateway server sends
     *callback data
    */
    function check_ipn_response() {
      global $woocommerce;

      $webhook = new \BeGateway\Webhook;
      $this->_init();

      $this->log(print_r($_SERVER, true));

      if ($webhook->isAuthorized()) {
        //log
        if ( "yes" == $this->settings['debug'] ){
          $display="\n-------------------------------------------\n";
          $display.= "Order No: ".$webhook->getTrackingId();
          $display.= "\nUID: ".$webhook->getUid();
          $display.="\n--------------------------------------------\n";
          $this->log($display);
        }

        $this->process_order($webhook);

      } else {
        if ( "yes" == $this->settings['debug'] ){
          $display="\n----------- Unable to proceed --------------\n";
          $display.= "Order No: ".$webhook->getTrackingId();
          $display.="\n--------------------------------------------\n";
          $this->log($display);
        }
        wp_die( "beGateway Notify Failure" );
      }
    }
    //end of check_ipn_response

    function process_order($webhook) {
      global $woocommerce;
      $order_id = $webhook->getTrackingId();
      $order = new WC_Order( $order_id );
      $type = $webhook->getResponse()->transaction->type;
      if (in_array($type, array('payment','authorization'))) {
        $status = $webhook->getStatus();

        $this->save_transaction_id($webhook, $order);

        $messages = array(
          'payment' => array(
            'success' => __('Payment success.', 'woocommerce-begateway'),
            'failed' => __('Payment failed.', 'woocommerce-begateway'),
            'incomplete' => __('Payment incomplete, order status not updated.', 'woocommerce-begateway'),
            'error' => __('Payment error, order status not updated.', 'woocommerce-begateway'),
          ),
          'authorization' => array(
            'success' => __('Payment authorised. No money captured yet.', 'woocommerce-begateway'),
            'failed' => __('Authorization failed.', 'woocommerce-begateway'),
            'incomplete' => __('Authorisation incomplete, order status not updated.', 'woocommerce-begateway'),
            'error' => __('Authorisation error, order status not updated', 'woocommerce-begateway'),
          )

        );
        $messages['callback_error'] = __('Callback error, order status not updated', 'woocommerce-begateway');

        $this->log('Transaction type: ' . $type . '. Payment status '.$status.'. UID: '.$webhook->getUid());

        $notice = array(
          ' ',
          'UID: ' . $webhook->getUid(),
          'Payment method: ' . $webhook->getPaymentMethod()
        );

        $notice = implode('<br>', $notice);

        if ($webhook->isSuccess()) {
          if ($type == 'payment' && $order->get_status() != 'processing') {
            $order->add_order_note($messages[$type]['success'] . $notice);
            $order->payment_complete($webhook->getResponse()->transaction->uid);
            update_post_meta(  $order_id, '_begateway_transaction_captured', 'yes');
          } elseif ($order->get_status() != 'on-hold') {
            $order->update_status('on-hold', $messages[$type]['success'] . $notice);
          }

          if (isset($webhook->getPaymentMethod()->token)) {
            $this->save_card_id($webhook->getPaymentMethod()->token, $order);
          }
        } elseif ($webhook->isFailed()) {
            $order->update_status('failed', $messages[$type]['failed'] . $notice);
        } elseif ($webhook->isIncomplete() || $webhook->isPending()) {
            $order->add_order_note($messages[$type]['incomplete'] . $notice);
        } elseif ($webhook->isError()) {
            $order->add_order_note($messages[$type]['error'] . $notice);
        } else {
            $order->add_order_note($messages['callback_error'] . $notice);
        }
      }
    }//end function

		/**
		 * Capture payment when the order is changed from on-hold to complete or processing
		 *
		 * @param $order_id int
		 */
		public function capture_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( 'begateway' != $order->get_payment_method() ) {
				return false;
			}
			$transaction_uid = get_post_meta( $order_id, '_begateway_transaction_id', true );
			$captured = get_post_meta( $order_id, '_begatewat_transaction_captured', true );
			if ( ! ( $transaction_uid && 'no' === $captured ) ) {
				return false;
			}

			$this->log( "Info: Starting to capture {$transaction_uid} of {$order_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

      $response = $this->child_transaction('capture', $transaction_uid, $order_id, $this->get_order_amount($order));

      if($response->isSuccess()){
        $note = __( 'BeGateway capture complete.', 'woocommerce-begateway' ) . PHP_EOL .
          __( 'Transaction UID: ', 'woocommerce-begateway' ) . $response->getUid();

  			$order->add_order_note($note);

  			$this->log('Info: Capture was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
  			$this->save_transaction_id( $result, $order );
  			update_post_meta( get_woo_id( $order ), '_begateway_transaction_captured', 'yes' );
        $order->payment_complete($response->getUid());
      } else {
  			$order->add_order_note(
  				__( 'Unable to capture transaction!', 'woocommerce-begateway' ) . PHP_EOL .
  				__( 'Error: ', 'woocommerce-begateway' ) . $response->getMessage();
  			);
  			$this->log( 'Issue: Capure has failed there has been an issue with the transaction.' . $response->getMessage(); . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
      }
		}

		/**
		 * Cancel pre-auth on refund/cancellation
		 *
		 * @param int $order_id
		 */
		public function cancel_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( 'begateway' != $order->get_payment_method() ) {
				return false;
			}
			$transaction_uid = get_post_meta( $order_id, '_begateway_transaction_id', true );
			$captured = get_post_meta( $order_id, '_begateway_transaction_captured', true );
			if ( ! $transaction_uid ) {
				return false;
			}

      $type = $captured == 'yes' ? 'refund' : 'void';

      $this->log( "Info: Starting to {$type} {$transaction_uid} of {$order_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

      if ('refund' == $type) {
        $this->child_transaction('refund', $transaction_uid, $order_id, $this->get_order_amount($order), __('Refunded from Woocommerce', 'woocommerce-begateway'));
      } else {
        $this->child_transaction('void', $transaction_uid, $order_id, $this->get_order_amount($order));
      }

      if($response->isSuccess()){
        if ('refund' == $type) {
          $note = __( 'BeGateway refund complete.', 'woocommerce-begateway' ) . PHP_EOL .
            __( 'Transaction UID: ', 'woocommerce-begateway' ) . $response->getUid();

    			$order->add_order_note($note);

    			$this->log('Info: Refund was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
    			$this->save_transaction_id( $result, $order );
    			update_post_meta( get_woo_id( $order ), '_begateway_transaction_refunded', 'yes' );

        } else {
          $note = __( 'BeGateway void complete.', 'woocommerce-begateway' ) . PHP_EOL .
            __( 'Transaction UID: ', 'woocommerce-begateway' ) . $response->getUid();

    			$order->add_order_note($note);

    			$this->log('Info: Void was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
    			$this->save_transaction_id( $result, $order );
    			update_post_meta( get_woo_id( $order ), '_begateway_transaction_voided', 'yes' );
        }
      } else {
  			$order->add_order_note(
  				sprintf(__('Unable to %s transaction!', 'woocommerce-begateway'), $type) . PHP_EOL .
  				__( 'Error: ', 'woocommerce-begateway' ) . $response->getMessage();
  			);
  			$this->log("Issue: {$type} has failed there has been an issue with the transaction." . $response->getMessage(); . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
      }
		}

  	/**
  	 * Store the transaction id.
  	 *
  	 * @param array    $transaction The transaction returned by the api wrapper.
  	 * @param WC_Order $order The order object related to the transaction.
  	 */
  	protected function save_transaction_id( $transaction, $order ) {
  		update_post_meta( get_woo_id( $order ), '_transaction_id', $transaction->getUid());
  		update_post_meta( get_woo_id( $order ), '_begateway_transaction_id', $transaction->getUid());
  	}

    function child_transaction($type, $uid, $order_id, $amount, $reason = ''){
      global $woocommerce;
      $order = new WC_order( $order_id );

      $this->_init();
      $klass = '\\BeGateway\\' . ucfirst($type) . 'Operation';
      $transaction = new $klass();
      $transaction->setParentUid($uid);
      $transaction->money->setCurrency(get_woocommerce_currency());
      $transaction->money->setAmount($amount);
      $transaction->setReason($reason);

      $response = $transaction->submit();

      return $response;
    }

    /**
     * Creates a new transaction by card token
     *
     * @param int      $entity_id The reference id.
     * @param WC_Order $order The order that is used for billing details and amount.
     * @param int      $amount The amount for which the transaction is created.
     * @param string   $type The type for which the transaction needs to be created.
     *
     * @return int|mixed|null
     */
    public function create_new_transaction($token, $order, $amount) {
      $merchant_id = $this->settings['shop-id'];
      // create a new transaction by card or transaction.

      if ($this->settings['transaction_type'] == 'authorization') {
        $transaction = new \BeGateway\AuthorizationOperation;
      } else {
        $transaction = new \BeGateway\PaymentOperation;
      }
      $this->_init();

      $transaction->money->setAmount($amount);
      $transaction->money->setCurrency($this->_get_order_currency($order));
      $transaction->setDescription(__('Order', 'woocommerce') . ' # ' .$order->get_order_number());
      $transaction->setTrackingId($order->get_id());

      $transaction->setTestMode(true);

      $transaction->card->setCardToken($token);

      $transaction->customer->setFirstName($order->get_billing_first_name());
      $transaction->customer->setLastName($order->get_billing_last_name());
      $transaction->customer->setCountry($order->get_billing_country());
      $transaction->customer->setAddress($order->get_billing_address_1() . $order->get_billing_address_2());
      $transaction->customer->setCity($order->get_billing_city());
      $transaction->customer->setZip($order->get_billing_postcode());
      $transaction->customer->setEmail($order->get_billing_email());

      if (in_array($order->get_billing_country(), array('US','CA'))) {
        $transaction->customer->setState($order->get_billing_state());
      }

      $transaction->setNotificationUrl($this->notify_url);

      if ($this->settings['mode'] == 'test') {
        $transaction->setTestMode(true);
      }

      $this->log("Info: Starting to create a transaction {$amount} in {$transaction->money->getCurrency()} for {$merchant_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

      $response = $transaction->submit();

      if ($response->isError() || $response->isFailed()) {
        $order->add_order_note(
          __('Issue: Creating the transaction failed!'). PHP_EOL . $response->getMessage()
        );
        return new WP_Error( 'begateway_error', __( 'There was a problem creating the transaction!.', 'woocommerce-begateway' ) );
      }

      return $response;
    }


    protected function _init() {
      \BeGateway\Settings::$gatewayBase = 'https://' . $this->settings['domain-gateway'];
      \BeGateway\Settings::$checkoutBase = 'https://' . $this->settings['domain-checkout'];
      \BeGateway\Settings::$shopId = $this->settings['shop-id'];
      \BeGateway\Settings::$shopKey = $this->settings['secret-key'];
    }

  	function _get_order_currency( $order ) {
  		if ( method_exists( $order, 'get_currency' ) ) {
  			return $order->get_currency();
  		} else {
  			return $order->get_order_currency();
  		}
  	}

    /**
     * Return order that can be captured, check for partial void or refund
     *
     * @param WC_Order $order
     *
     * @return mixed
     */
    protected function get_order_amount( $order ) {
      return $order->get_total() - $order->get_total_refunded();
    }

    function enqueueWidgetScripts($data) {
      $url = explode('.', $this->settings['domain-checkout']);
      $url[0] = 'js';
      $url = 'https://' . implode('.', $url) . '/widget/be_gateway.js';

      wp_register_script('begateway_wc_widget', $url, null, null);
      wp_register_script('begateway_wc_widget_start',
        plugin_dir_url(__FILE__) . '/js/script.js',
        array('begateway_wc_widget'), null
      );

      wp_localize_script('begateway_wc_widget_start',
            'begateway_wc_checkout_vars',
            $data
        );

      wp_enqueue_script('begateway_wc_widget_start');
    }

    /**
     * Check if we are in incompatibility mode or not
     */
    public function get_compatibility_mode() {
      $options = get_option( 'woocommerce_paylike_settings' );
      if ( isset( $options['compatibility_mode'] ) ) {
        $this->compatibility_mode = ( 'yes' === $options['compatibility_mode'] ? $options['compatibility_mode'] : 0 );
      } else {
        $this->compatibility_mode = 0;
      }

      return $this->compatibility_mode;
    }

    /**
     * Log function
     */
    public function log( $message ) {
      if ( empty( self::$log ) ) {
        self::$log = new WC_Logger();
      }
      if ('yes' == $this->settings['debug']) {
        self::$log->debug( $message, array( 'source' => 'woocommerce-gateway-begateway' ) );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
          error_log( $message );
        }
      }
    }

  	/**
  	 * Saves the card id
  	 * used for trials, and changing payment option
  	 *
  	 * @param int      $card_id The card reference.
  	 * @param WC_Order $order The order object related to the transaction.
  	 */
  	protected function save_card_id( $card_id, $order ) {
  		update_post_meta( get_woo_id( $order ), '_begateway_card_id', $card_id );
  	}


  } //end of class

  function is_woocommerce_subscription_support_enabled() {
    return class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' );
  }
  //add to gateways
  function woocommerce_add_begateway_gateway( $methods )
  {
      if (is_woocommerce_subscription_support_enabled()) {
        include_once( plugin_basename( 'includes/class-wc-begateway-payment-tokens.php' ) );
        include_once( plugin_basename( 'includes/class-wc-begateway-payment-token.php' ) );
        $methods[] = 'WC_Gateway_BeGateway_Addons'
      }
      $methods[] = 'WC_Gateway_BeGateway';
      return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'woocommerce_add_begateway_gateway' );
}
