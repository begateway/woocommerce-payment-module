<?php
/*
Plugin Name: WooCommerce BeGateway Payment Gateway
Plugin URI: https://github.com/begateway/woocommerce-payment-module
Description: Extends WooCommerce with BeGateway payment gateway
Version: 2.0.0
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

//require_once dirname(  __FILE__  ) . '/begateway-api-php/lib/BeGateway.php';
// require_once ABSPATH . 'wp-admin/includes/plugin.php';

// if (!class_exists('WC_Payment_Gateway')) {
//   exit;
// }

  class WC_Gateway_BeGateway extends WC_Payment_Gateway
  {
    const NO_REFUND  = [ 'erip' ];
    const NO_CAPTURE = [ 'erip' ];
    const NO_CANCEL  = [ 'erip' ];

    protected $log;

    /**
     * constructor
     *
     */
    function __construct()
    {
      $this->supports = array(
        'products',
        'refunds',
        'tokenization',
      );

      $this->setup_properties();
      $this->init_form_fields();
      $this->init_settings();

      $this->title              = $this->get_option('title');
      $this->description        = $this->get_option('description');

      //callback URL - hooks into the WP/WooCommerce API and initiates the payment class for the bank server so it can access all functions
      $this->notify_url = WC()->api_request_url('WC_Gateway_BeGateway', is_ssl());
      $this->notify_url = str_replace('0.0.0.0','webhook.begateway.com:8443', $this->notify_url);

      add_action('woocommerce_receipt_begateway', array( $this, 'receipt_page'));
      add_action('woocommerce_api_wc_gateway_begateway', array( $this, 'validate_ipn_request' ) );
      add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    } // end __construct

    /**
  	* Setup general properties for the gateway.
	  */
    protected function setup_properties() {
      $this->id                 = 'begateway';
  		$this->icon               = apply_filters( 'woocommerce_begateway_icon', '' );
  		$this->method_title       = __('BeGateway', 'woocommerce-begateway');
      $this->method_description = __('BeGateway payment gateway solution', 'woocommerce-begateway');
      $this->has_fields         = false;
    }

    public function init_form_fields() {
      $this->form_fields = include __DIR__ . '/settings-begateway.php';
    }

    function generate_begateway_form( $order_id ) {
      //creates a self-submitting form to pass the user through to the beGateway server
      $order = new WC_order( $order_id );
      $this->log('Generating payment form for order ' . $order->get_order_number());

      // Order number & Cart Contents for description field - may change
      $item_loop = 0;
      //grab the langauge

      $lang = explode('_', get_locale());
      $lang = $lang[0];

      $token = new \BeGateway\GetPaymentToken;
      $this->_init();

      if ($this->get_option('transaction_type') == 'authorization') {
        $token->setAuthorizationTransactionType();
      }

      $this->set_payment_token_params( $token, $order );
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

      if (in_array('bankcard', $this->get_option('payment_methods'))) {
        $cc = new \BeGateway\PaymentMethod\CreditCard;
        $token->addPaymentMethod($cc);
      }

      if (in_array('halva', $this->get_option('payment_methods'))) {
        $halva = new \BeGateway\PaymentMethod\CreditCardHalva;
        $token->addPaymentMethod($halva);
      }

      if (in_array('erip', $this->get_option('payment_methods'))) {
        $erip = new \BeGateway\PaymentMethod\Erip(array(
          'order_id' => $order_id,
          'account_number' => ltrim($order->get_order_number()),
          'service_no' => $this->get_option('erip_service_no', null)
        ));
        $token->addPaymentMethod($erip);
      }

      if ($this->get_option('mode') == 'test') {
        $token->setTestMode(true);
      }

      $this->log('Requesting token for order ' . $order->get_order_number());
      $token->additional_data->setContract(['recurring', 'card_on_file']);

      $response = $token->submit();

      if(!$response->isSuccess()) {

        $this->log('Unable to get payment token on order: ' . $order_id . 'Reason: ' . $response->getMessage());

        wc_add_notice(__('Error to get a payment token', 'woocommerce-begateway'), 'error');
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

    function set_payment_token_params( &$token, $order ) {
      $token->money->setCurrency( $order->get_currency() );
      $token->money->setAmount( $order->get_total() );
      $token->setDescription( __( 'Order', 'woocommerce' ) . ' # ' .$order->get_order_number() );
    }

    function process_payment( $order_id ) {
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
    function validate_ipn_request() {
      $webhook = new \BeGateway\Webhook;
      $this->_init();

      $this->log('Received webhook json: ' . file_get_contents('php://input'));

      if ( ! $this->validate_ipn_amount($webhook) ) {
        $this->log(
          '----------- Invalid amount webhook --------------' . PHP_EOL .
          "Order No: ".$webhook->getTrackingId() . PHP_EOL .
          "UID: ".$webhook->getUid() . PHP_EOL .
          '--------------------------------------------'
        );

        wp_die( "beGateway Notify Amount Failure" );
      }

      if ( $webhook->isAuthorized() ) {
        $this->log(
          '-------------------------------------------' . PHP_EOL .
          "Order No: ".$webhook->getTrackingId() . PHP_EOL .
          "UID: ".$webhook->getUid() . PHP_EOL .
          '--------------------------------------------'
        );

        $this->process_ipn_request($webhook);

      } else {
        $this->log(
          '----------- Unauthorized webhook --------------' . PHP_EOL .
          "Order No: ".$webhook->getTrackingId() . PHP_EOL .
          "UID: ".$webhook->getUid() . PHP_EOL .
          '--------------------------------------------'
        );

        wp_die( "beGateway Notify Failure" );
      }
    }
    //end of check_ipn_response

    protected function validate_ipn_amount( $webhook ) {
      $order_id = $webhook->getTrackingId();
      $order = new WC_Order( $order_id );

      if ( ! $order ) {
        return false;
      }

      $money = new \BeGateway\Money;
      $money->setCurrency( $order->get_currency() );
      $money->setAmount( $order->get_total() );
      $money->setCurrency( $webhook->getResponse()->transaction->currency );
      $money->setCents( $webhook->getResponse()->transaction->amount );

      $transaction = $webhook->getResponse()->transaction;

      return $transaction->currency == $money->getCurrency() &&
        $transaction->amount == $money->getCents();
    }

    function process_ipn_request($webhook) {
      $order_id = $webhook->getTrackingId();
      $order = new WC_Order( $order_id );
      $type = $webhook->getResponse()->transaction->type;
      if (in_array($type, array('payment','authorization'))) {
        $status = $webhook->getStatus();

        $this->log(
          'Transaction type: ' . $type . PHP_EOL .
          'Payment status '. $status . PHP_EOL .
          'UID: ' . $webhook->getUid() . PHP_EOL .
          'Message: ' . $webhook->getMessage()
        );

        if ($webhook->isSuccess()) {
          $order->payment_complete( $webhook->getUid() );

          if ( 'authorization' == $type ) {
            update_post_meta($order_id, '_begateway_transaction_captured', 'no' );
            update_post_meta($order_id, '_begateway_transaction_captured_amount', 0 );
          } else {
            update_post_meta($order_id, '_begateway_transaction_captured', 'yes' );
            update_post_meta($order_id, '_begateway_transaction_captured_amount', $order->get_total() );
          }
          update_post_meta($order_id, '_begateway_transaction_refunded_amount', 0 );

          update_post_meta($order_id, '_begateway_transaction_payment_method', $webhook->getPaymentMethod() );

          $pm = $webhook->getPaymentMethod();

          if ( $pm && isset( $webhook->getResponse()->transaction->$pm->token ) ) {
            $this->save_card_id( $webhook->getResponse()->transaction->$pm, $order );
          }

          $this->save_transaction_id($webhook, $order);

        } elseif ($webhook->isFailed()) {
          $order->update_status( 'failed', $webhook->getMessage() );
        }
      }
    }//end function

		/**
		 * Capture payment when the order is changed from on-hold to complete or processing
		 *
		 * @param $order_id int
		 */
		public function capture_payment( $order_id, $amount ) {
			$order = wc_get_order( $order_id );
			if ( $this->id != $order->get_payment_method() ) {
				return new WP_Error( 'begateway_error', __( 'Invalid payment method' , 'woocommerce-begateway' ) );
			}
			$transaction_uid = get_post_meta( $order_id, '_begateway_transaction_id', true );
			$captured = get_post_meta( $order_id, '_begateway_transaction_captured', true );

			if ( ! $transaction_uid ) {
				return new WP_Error( 'begateway_error', __( 'No transaction reference UID to capture' , 'woocommerce-begateway' ) );
			}

			if ( 'yes' == $captured ) {
				return new WP_Error( 'begateway_error', __( 'Transaction is already captured' , 'woocommerce-begateway' ) );
			}

			$this->log( "Info: Starting to capture {$transaction_uid} of {$order_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

      $response = $this->child_transaction( 'capture', $transaction_uid, $order_id, $amount );

      if($response->isSuccess()){
        $note = __( 'Capture completed', 'woocommerce-begateway' ) . PHP_EOL .
          __( 'Transaction UID: ', 'woocommerce-begateway' ) . $response->getUid();

  			$order->add_order_note($note);

  			$this->log('Info: Capture was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
  			$this->save_transaction_id( $response, $order );
  			update_post_meta( $order->get_id(), '_begateway_transaction_captured', 'yes' );
  			update_post_meta( $order->get_id(), '_begateway_transaction_captured_amount', $amount );
        $order->payment_complete( $response->getUid() );

        return true;
      } else {
  			$order->add_order_note(
  				__( 'Error to capture transaction', 'woocommerce-begateway' ) . PHP_EOL .
  				__( 'Error: ', 'woocommerce-begateway' ) . $response->getMessage()

  			);
  			$this->log('Issue: Capture has failed there has been an issue with the transaction.' . $response->getMessage() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
				return new WP_Error( 'begateway_error', __( 'Error to capture transaction' , 'woocommerce-begateway' ) );
      }
		}

		/**
		 * Cancel pre-auth on refund/cancellation
		 *
		 * @param int $order_id
		 */
		public function cancel_payment( $order_id, $amount ) {
			$order = wc_get_order( $order_id );
			if ( $this->id != $order->get_payment_method() ) {
				return new WP_Error( 'begateway_error', __( 'Invalid payment method' , 'woocommerce-begateway' ) );
			}
			$transaction_uid = get_post_meta( $order_id, '_begateway_transaction_id', true );
			$captured = get_post_meta( $order_id, '_begateway_transaction_captured', true );
			if ( ! $transaction_uid ) {
				return new WP_Error( 'begateway_error', __( 'No transaction reference UID to cancel' , 'woocommerce-begateway' ) );
			}

      $this->log( "Info: Starting to void {$transaction_uid} of {$order_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

      $response = $this->child_transaction('void', $transaction_uid, $order_id, $amount);

      if($response->isSuccess()){
        $note = __( 'Void complete', 'woocommerce-begateway' ) . PHP_EOL .
          __( 'Transaction UID: ', 'woocommerce-begateway' ) . $response->getUid();

  			$order->add_order_note($note);

  			$this->log('Info: Void was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
  			update_post_meta( $order->get_id(), '_begateway_transaction_voided', 'yes' );
        return true;
      } else {
  			$order->add_order_note(
  			  __( 'Error to void transaction', 'woocommerce-begateway' ) . PHP_EOL .
  				__( 'Error: ', 'woocommerce-begateway' ) . $response->getMessage()
  			);
  			$this->log("Issue: Void has failed there has been an issue with the transaction." . $response->getMessage() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

				return new WP_Error('begateway_error', __( 'Error to void transaction', 'woocommerce-begateway' ) );
      }
		}

    /**
     * Refund payment
     *
     * @param int $order_id
     */
    public function refund_payment( $order_id, $amount ) {
      $order = wc_get_order( $order_id );
      if ( $this->id != $order->get_payment_method() ) {
        return new WP_Error( 'begateway_error', __( 'Invalid payment method' , 'woocommerce-begateway' ) );
      }
      $transaction_uid = get_post_meta( $order_id, '_begateway_transaction_id', true );
      $captured = get_post_meta( $order_id, '_begateway_transaction_captured', true );
      if ( ! $transaction_uid ) {
        return new WP_Error( 'begateway_error', __( 'No transaction reference UID to refund' , 'woocommerce-begateway' ) );
      }

      $this->log( "Info: Starting to refund {$transaction_uid} of {$order_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

      $response = $this->child_transaction('refund', $transaction_uid, $order_id, $amount, __( 'Refunded from Woocommerce', 'woocommerce-begateway' ) );

      if($response->isSuccess()){
        $note = __( 'Refund completed', 'woocommerce-begateway' ) . PHP_EOL .
          __( 'Transaction UID: ', 'woocommerce-begateway' ) . $response->getUid();

        $order->add_order_note($note);

        $this->log('Info: Refund was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

        $refund_amount = $order->get_meta( '_begateway_transaction_refunded_amount', true ) ?: 0;
        $refund_amount += $amount;
        update_post_meta( $order->get_id(), '_begateway_transaction_refunded_amount', $refund_amount );

        if ( $refund_amount >= $order->get_total() ) {
          update_post_meta( $order->get_id(), '_begateway_transaction_refunded', 'yes' );
        }
        return true;

      } else {
        $order->add_order_note(
          __( 'Error to refund transaction', 'woocommerce-begateway' ) . PHP_EOL .
          __( 'Error: ', 'woocommerce-begateway' ) . $response->getMessage()
        );
        $this->log("Issue: Refund has failed there has been an issue with the transaction." . $response->getMessage() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

        return new WP_Error('begateway_error', __( 'Error to refund transaction', 'woocommerce-begateway' ) );
      }
    }

  	/**
  	 * Store the transaction id.
  	 *
  	 * @param array    $transaction The transaction returned by the api wrapper.
  	 * @param WC_Order $order The order object related to the transaction.
  	 */
  	protected function save_transaction_id( $transaction, $order ) {
  		update_post_meta( $order->get_id(), '_transaction_id', $transaction->getUid());
  		update_post_meta( $order->get_id(), '_begateway_transaction_id', $transaction->getUid());
      if ( method_exists($transaction, 'getPaymentMethod') ) {
        update_post_meta( $order->get_id(), '_begateway_transaction_payment_method', $transaction->getPaymentMethod() );
      }
  	}

    function child_transaction($type, $uid, $order_id, $amount, $reason = ''){
      $order = new WC_order( $order_id );

      $this->_init();
      $klass = '\\BeGateway\\' . ucfirst($type) . 'Operation';
      $transaction = new $klass();
      $transaction->setParentUid($uid);
      $transaction->money->setCurrency(get_woocommerce_currency());
      $transaction->money->setAmount($amount);
      if (!empty($reason)) {
        $transaction->setReason($reason);
      }

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
        return new WP_Error( 'begateway_error', __( 'There was a problem creating the transaction!', 'woocommerce-begateway' ) );
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
        untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../js/script.js',
        array('begateway_wc_widget'), null
      );

      wp_localize_script('begateway_wc_widget_start',
            'begateway_wc_checkout_vars',
            $data
        );

      wp_enqueue_script('begateway_wc_widget_start');
    }

    /**
    * Get Invoice data of Order.
    *
    * @param WC_Order $order
    *
    * @return array
    * @throws Exception
    */
    public function get_invoice_data( $order ) {
      if ( is_int( $order ) ) {
        $order = wc_get_order( $order );
      }

      if ( $order->get_payment_method() !== $this->id ) {
        throw new Exception('Unable to get invoice data.');
      }

      return array(
        'authorized_amount' => $order->get_total(),
        'settled_amount'    => $order->get_meta( '_begateway_transaction_captured_amount', true ) ?: 0,
        'refunded_amount'   => $order->get_meta( '_begateway_transaction_refunded_amount', true ) ?: 0,
        'state'             => $order->get_status()
      );
    }

    /**
     * Log function
     */
    public function log( $message ) {
      if ( empty( $this->log ) ) {
        $this->log = new WC_Logger();
      }
      if ('yes' == $this->get_option('debug', 'no')) {
        $this->log->debug( $message, array( 'source' => 'woocommerce-gateway-begateway' ) );
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
  	protected function save_card_id( $card, $order ) {
  		update_post_meta( $order->get_id(), '_begateway_card_id', $card->token );
  		update_post_meta( $order->get_id(), '_begateway_card_last_4', $card->last_4 );
  		update_post_meta( $order->get_id(), '_begateway_card_brand', $card->brand != 'master' ? $card->brand : 'mastercard' );
  	}

    /**
    * @param $order
    *
    * @return mixed
    */
    protected function get_card_id( $order ) {
      $card_id = get_post_meta( $order->get_id(), '_begateway_card_id', true );
      if ( $card_id ) {
        return $card_id;
      }
      return false;
    }

  	/**
  	 * Check if a payment method supports refund
  	 *
  	 * @param WC_Order $order The order object related to the transaction.
     * @return boolean
  	 */
    public function can_payment_method_refund( $order ) {
      return !in_array( $pm, self::NO_REFUND);
    }

  	/**
  	 * Check if a payment method supports cancel
  	 *
  	 * @param WC_Order $order The order object related to the transaction.
     * @return boolean
  	 */
    public function can_payment_method_cancel( $order ) {
      $pm = $this->getPaymentMethod( $order );
      return !in_array( $pm, self::NO_CANCEL);
    }

  	/**
  	 * Check if a payment method supports capture
  	 *
  	 * @param WC_Order $order The order object related to the transaction.
     * @return boolean
  	 */
    public function can_payment_method_capture( $order ) {
      $pm = $this->getPaymentMethod( $order );
      return !in_array( $pm, self::NO_CAPTURE);
    }

  	/**
  	 * Return order payment method
  	 *
  	 * @param WC_Order $order The order object related to the transaction.
     * @return string
  	 */
    public function getPaymentMethod( $order ) {
      return $order->get_meta( '_begateway_transaction_payment_method');
    }
  } //end of class
