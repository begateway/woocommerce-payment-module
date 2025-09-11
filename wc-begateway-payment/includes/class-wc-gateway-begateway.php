<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Gateway_BeGateway extends WC_Payment_Gateway
{
    const NO_REFUND = ['erip'];
    const NO_CAPTURE = ['erip'];
    const NO_CANCEL = ['erip'];

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

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        //callback URL - hooks into the WP/WooCommerce API and initiates the payment class for the bank server so it can access all functions
        $this->notify_url = WC()->api_request_url('WC_Gateway_BeGateway', is_ssl());
        $this->notify_url = str_replace('0.0.0.0', 'webhook.begateway.com:8443', $this->notify_url);

        add_action('woocommerce_receipt_begateway', array($this, 'receipt_page'));
        add_action('woocommerce_api_wc_gateway_begateway', array($this, 'validate_ipn_request'));
        add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    } // end __construct

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties()
    {
        $this->id = 'begateway';
        $this->icon = apply_filters('woocommerce_begateway_icon', '');
        $this->method_title = __('BeGateway', 'wc-begateway-payment');
        $this->method_description = __('BeGateway payment gateway solution', 'wc-begateway-payment');
        $this->has_fields = false;
    }

    public function init_form_fields()
    {
        $this->form_fields = include __DIR__ . '/settings-begateway.php';
    }

    function generate_begateway_form($order_id)
    {
        //creates a self-submitting form to pass the user through to the beGateway server
        $order = new WC_order($order_id);
        $this->log('Generating payment form for order ' . $order->get_order_number());

        // Order number & Cart Contents for description field - may change
        $item_loop = 0;
        //grab the language
        $lang = $this->getLanguage();

        $token = new \BeGateway\GetPaymentToken;
        $this->_init();

        if ($this->get_option('transaction_type') == 'authorization') {
            $token->setAuthorizationTransactionType();
        }

        $this->set_payment_token_params($token, $order);
        $token->setTrackingId($order->get_id());
        $token->customer->setFirstName($order->get_billing_first_name());
        $token->customer->setLastName($order->get_billing_last_name());
        $token->customer->setCountry($order->get_billing_country());
        $token->customer->setCity($order->get_billing_city());
        $token->customer->setPhone($order->get_billing_phone());
        $token->customer->setZip($order->get_billing_postcode());
        $token->customer->setAddress($order->get_billing_address_1() . $order->get_billing_address_2());
        $token->customer->setEmail($order->get_billing_email());

        if (in_array($order->get_billing_country(), array('US', 'CA'))) {
            $token->customer->setState($order->get_billing_state());
        }

        $token->setSuccessUrl(esc_url_raw($this->get_return_url($order)));
        $token->setDeclineUrl(esc_url_raw($order->get_cancel_order_url_raw()));
        $token->setFailUrl(esc_url_raw($order->get_cancel_order_url_raw()));
        $token->setCancelUrl(esc_url_raw($order->get_cancel_order_url_raw()));
        $token->setNotificationUrl($this->notify_url);

        $token->setExpiryDate(date("c", intval($this->settings['payment_valid']) * 60 + time() + 1));

        $token->setLanguage($lang);
        $this->save_locale($lang, $order);

        $payment_methods = $this->get_option('payment_methods');
        $payment_methods = (is_array($payment_methods)) ? $payment_methods : [];

        if (in_array('bankcard', $payment_methods)) {
            $cc = new \BeGateway\PaymentMethod\CreditCard;
            $token->addPaymentMethod($cc);
        }

        if (in_array('halva', $payment_methods)) {
            $halva = new \BeGateway\PaymentMethod\CreditCardHalva;
            $token->addPaymentMethod($halva);
        }

        if (in_array('erip', $payment_methods)) {
            $erip = new \BeGateway\PaymentMethod\Erip(
                array(
                    'order_id' => $order_id,
                    'account_number' => ltrim($order->get_order_number()),
                    'service_no' => $this->get_option('erip_service_no', null)
                )
            );
            $token->addPaymentMethod($erip);
        }

        if ($this->get_option('mode') == 'test') {
            $token->setTestMode(true);
        }

        $this->log('Requesting token for order ' . $order->get_order_number());
        $token->additional_data->setContract($this->get_contract_data());

        $response = $token->submit();

        if (!$response->isSuccess()) {

            $this->log('Unable to get payment token on order: ' . $order_id . 'Reason: ' . $response->getMessage());

            wc_add_notice(__('Error to get a payment token', 'wc-begateway-payment'), 'error');
            wc_add_notice($response->getMessage(), 'error');
        } else {
            //now look to the result array for the token
            $payment_url = $response->getRedirectUrlScriptName();
            $order->update_meta_data(ltrim($order->get_order_number(), '#'), '_Token', $token);
            $order->save();

            $this->log('Token received, forwarding customer to: ' . $payment_url);

            $this->enqueueWidgetScripts(
                array(
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
            <a class="button checkout-button" href="' . esc_url($response->getRedirectUrl()) . '" onClick="return woocommerce_start_begateway_payment(event);">' . __('Make payment', 'wc-begateway-payment') . '"</a>
            <a class="cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order', 'wc-begateway-payment') . '</a>
        ';
        }
    }

    function set_payment_token_params(&$token, $order)
    {
        $token->money->setCurrency($order->get_currency());
        $token->money->setAmount($order->get_total());
        $token->setDescription(__('Order', 'woocommerce') . ' # ' . $order->get_order_number());
    }

    function process_payment($order_id)
    {
        $order = new WC_Order($order_id);

        // Return payment page
        return array(
            'result' => 'success',
            'redirect' => add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true))
        );
    }
    // end process_payment

    function receipt_page($order)
    {

        echo $this->generate_begateway_form($order);

    }

    function thankyou_page()
    {
        if ($this->description)
            wpautop(wptexturize(esc_html($this->description)));
    } // end thankyou_page

    private function plugin_url()
    {
        return $this->plugin;
    } // end plugin_url

    /**
     *this function is called via the wp-api when the begateway server sends
     *callback data
     */
    function validate_ipn_request()
    {
        $webhook = new \BeGateway\Webhook;
        $this->_init();

        $this->log('Received webhook json: ' . file_get_contents('php://input'));

        if (!$this->validate_ipn_amount($webhook)) {
            $this->log(
                '----------- Invalid amount webhook --------------' . PHP_EOL .
                "Order No: " . $webhook->getTrackingId() . PHP_EOL .
                "UID: " . $webhook->getUid() . PHP_EOL .
                '--------------------------------------------'
            );

            wp_die("beGateway Notify Amount Failure");
        }

        if ($webhook->isAuthorized()) {
            $this->log(
                '-------------------------------------------' . PHP_EOL .
                "Order No: " . $webhook->getTrackingId() . PHP_EOL .
                "UID: " . $webhook->getUid() . PHP_EOL .
                '--------------------------------------------'
            );

            if (!$this->process_ipn_request($webhook)) {
                wp_die("beGateway Process Notify Failure");
            }

        } else {
            $this->log(
                '----------- Unauthorized webhook --------------' . PHP_EOL .
                "Order No: " . $webhook->getTrackingId() . PHP_EOL .
                "UID: " . $webhook->getUid() . PHP_EOL .
                '--------------------------------------------'
            );

            wp_die("beGateway Notify Failure");
        }
    }
    //end of check_ipn_response

    protected function validate_ipn_amount($webhook)
    {
        $order_id = $webhook->getTrackingId();
        $order = new WC_Order($order_id);

        if (!$order) {
            return false;
        }

        $money = new \BeGateway\Money;
        $money->setCurrency($order->get_currency());
        $money->setAmount($order->get_total());
        $money->setCurrency($webhook->getResponse()->transaction->currency);
        $money->setCents($webhook->getResponse()->transaction->amount);

        $transaction = $webhook->getResponse()->transaction;

        return $transaction->currency == $money->getCurrency() &&
            $transaction->amount == $money->getCents();
    }

    function process_ipn_request($webhook)
    {
        $order_id = $webhook->getTrackingId();
        $order = new WC_Order($order_id);
        $type = $webhook->getResponse()->transaction->type;
        if (in_array($type, array('payment', 'authorization'))) {
            $status = $webhook->getStatus();

            $this->log(
                'Transaction type: ' . $type . PHP_EOL .
                'Payment status ' . $status . PHP_EOL .
                'UID: ' . $webhook->getUid() . PHP_EOL .
                'Message: ' . $webhook->getMessage()
            );

            if ($webhook->isSuccess()) {
                if (!$order->payment_complete($webhook->getUid())) {
                    $this->log(
                        sprintf('Error to change order #%d status from %s to paid', $order_id, $order->get_status())
                    );
                    return false;
                }

                if ('authorization' == $type) {
                    $order->update_meta_data('_begateway_transaction_captured', 'no');
                    $order->update_meta_data('_begateway_transaction_captured_amount', 0);
                } else {
                    $order->update_meta_data('_begateway_transaction_captured', 'yes');
                    $order->update_meta_data('_begateway_transaction_captured_amount', $order->get_total());
                }
                $order->update_meta_data('_begateway_transaction_refunded_amount', 0);
                $order->update_meta_data('_begateway_transaction_payment_method', $webhook->getPaymentMethod());
                $order->save();

                if ($webhook->hasTransactionSection() && isset($webhook->getResponse()->transaction->language)) {
                    $lang = $webhook->getResponse()->transaction->language;
                    $this->save_locale($lang, $order);
                }

                $pm = $webhook->getPaymentMethod();

                if ($pm && isset($webhook->getResponse()->transaction->$pm->token)) {
                    $this->save_card_id($webhook->getResponse()->transaction->$pm, $order);
                }

                $this->save_transaction_id($webhook, $order);

            } elseif ($webhook->isFailed()) {
                if (!$order->has_status(array('processing', 'completed'))) {
                    if (!$order->update_status('failed', $webhook->getMessage())) {
                        $this->log(
                            sprintf('Error to change order #%d status from %s to failed', $order_id, $order->get_status())
                        );
                        return false;
                    }
                }
            }
        }
        return true;
    } //end function

    /**
     * Capture payment when the order is changed from on-hold to complete or processing
     *
     * @param $order_id int
     */
    public function capture_payment($order_id, $amount)
    {
        $order = wc_get_order($order_id);
        if ($this->id != $order->get_payment_method()) {
            return new WP_Error('begateway_error', __('Invalid payment method', 'wc-begateway-payment'));
        }
        $transaction_uid = $this->get_transaction_id($order);
        $captured = $order->get_meta('_begateway_transaction_captured', true);

        if (!$transaction_uid) {
            return new WP_Error('begateway_error', __('No transaction reference UID to capture', 'wc-begateway-payment'));
        }

        if ('yes' == $captured) {
            return new WP_Error('begateway_error', __('Transaction is already captured', 'wc-begateway-payment'));
        }

        $this->log("Info: Starting to capture {$transaction_uid} of {$order_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__);

        $response = $this->child_transaction('capture', $transaction_uid, $order_id, $amount);

        if ($response->isSuccess()) {
            $note = __('Capture completed', 'wc-begateway-payment') . PHP_EOL .
                __('Transaction UID: ', 'wc-begateway-payment') . $response->getUid();

            $order->add_order_note($note);

            $this->log('Info: Capture was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__);
            $this->save_transaction_id($response, $order);

            $order->update_meta_data('_begateway_transaction_captured', 'yes');
            $order->update_meta_data('_begateway_transaction_captured_amount', $amount);
            $order->save();
            $order->payment_complete($response->getUid());

            return true;
        } else {
            $order->add_order_note(
                __('Error to capture transaction', 'wc-begateway-payment') . PHP_EOL .
                __('Error: ', 'wc-begateway-payment') . $response->getMessage()

            );
            $this->log('Issue: Capture has failed there has been an issue with the transaction.' . $response->getMessage() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__);
            return new WP_Error('begateway_error', __('Error to capture transaction', 'wc-begateway-payment'));
        }
    }

    /**
     * Cancel pre-auth on refund/cancellation
     *
     * @param int $order_id
     */
    public function cancel_payment($order_id, $amount)
    {
        $order = wc_get_order($order_id);
        if ($this->id != $order->get_payment_method()) {
            return new WP_Error('begateway_error', __('Invalid payment method', 'wc-begateway-payment'));
        }
        $transaction_uid = $this->get_transaction_id($order);

        $captured = $order->get_meta('_begateway_transaction_captured', true);
        if (!$transaction_uid) {
            return new WP_Error('begateway_error', __('No transaction reference UID to cancel', 'wc-begateway-payment'));
        }

        $this->log("Info: Starting to void {$transaction_uid} of {$order_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__);

        $response = $this->child_transaction('void', $transaction_uid, $order_id, $amount);

        if ($response->isSuccess()) {
            $note = __('Void complete', 'wc-begateway-payment') . PHP_EOL .
                __('Transaction UID: ', 'wc-begateway-payment') . $response->getUid();

            $order->add_order_note($note);

            $this->log('Info: Void was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__);

            $order->update_meta_data('_begateway_transaction_voided', 'yes');
            $order->save();

            return true;
        } else {
            $order->add_order_note(
                __('Error to void transaction', 'wc-begateway-payment') . PHP_EOL .
                __('Error: ', 'wc-begateway-payment') . $response->getMessage()
            );
            $this->log("Issue: Void has failed there has been an issue with the transaction." . $response->getMessage() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__);

            return new WP_Error('begateway_error', __('Error to void transaction', 'wc-begateway-payment'));
        }
    }

    /**
     * Refund payment
     *
     * @param int $order_id
     */
    public function refund_payment($order_id, $amount)
    {
        $order = wc_get_order($order_id);
        if ($this->id != $order->get_payment_method()) {
            return new WP_Error('begateway_error', __('Invalid payment method', 'wc-begateway-payment'));
        }
        $transaction_uid = $this->get_transaction_id($order);

        $captured = $order->get_meta('_begateway_transaction_captured', true);
        if (!$transaction_uid) {
            return new WP_Error('begateway_error', __('No transaction reference UID to refund', 'wc-begateway-payment'));
        }

        $this->log("Info: Starting to refund {$transaction_uid} of {$order_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__);

        $response = $this->child_transaction('refund', $transaction_uid, $order_id, $amount, __('Refunded from Woocommerce', 'wc-begateway-payment'));

        if ($response->isSuccess()) {
            $note = __('Refund completed', 'wc-begateway-payment') . PHP_EOL .
                __('Transaction UID: ', 'wc-begateway-payment') . $response->getUid();

            $order->add_order_note($note);

            $this->log('Info: Refund was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__);

            $refund_amount = $order->get_meta('_begateway_transaction_refunded_amount', true) ?: 0;
            $refund_amount += $amount;
            $order->update_meta_data('_begateway_transaction_refunded_amount', $refund_amount);

            if ($refund_amount >= $order->get_total()) {
                $order->update_meta_data('_begateway_transaction_refunded', 'yes');
            }

            $order->save();
            return true;

        } else {
            $order->add_order_note(
                __('Error to refund transaction', 'wc-begateway-payment') . PHP_EOL .
                __('Error: ', 'wc-begateway-payment') . $response->getMessage()
            );
            $this->log("Issue: Refund has failed there has been an issue with the transaction." . $response->getMessage() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__);

            return new WP_Error('begateway_error', __('Error to refund transaction', 'wc-begateway-payment'));
        }
    }

    /**
     * Store the transaction id.
     *
     * @param array    $transaction The transaction returned by the api wrapper.
     * @param WC_Order $order The order object related to the transaction.
     */
    protected function save_transaction_id($transaction, $order)
    {
        $order->update_meta_data('_transaction_id', $transaction->getUid());
        $order->update_meta_data('_begateway_transaction_id', $transaction->getUid());

        if (method_exists($transaction, 'getPaymentMethod')) {
            $order->update_meta_data('_begateway_transaction_payment_method', $transaction->getPaymentMethod());
        }
        $order->save();
    }

    /**
     * Retrieve the order transaction id.
     *
     * @param WC_Order $order The order object related to the transaction.
     * @return string uid of a transaction
     * 
     */
    public function get_transaction_id($order)
    {
        return $order->get_meta('_begateway_transaction_id', true);
    }

    function child_transaction($type, $uid, $order_id, $amount, $reason = '')
    {
        $order = new WC_order($order_id);

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
    public function create_new_transaction($token, $order, $amount)
    {
        $merchant_id = $this->settings['shop-id'];
        // create a new transaction by card or transaction.

        if ($this->settings['transaction_type'] == 'authorization') {
            $transaction = new \BeGateway\AuthorizationOperation;
        } else {
            $transaction = new \BeGateway\PaymentOperation;
        }
        $this->_init();

        $transaction->money->setAmount($amount);
        $transaction->money->setCurrency($this->get_order_currency($order));
        $transaction->setDescription(__('Order', 'woocommerce') . ' # ' . $order->get_order_number());
        $transaction->setTrackingId($order->get_id());

        $transaction->setLanguage($this->get_locale($order));

        $transaction->card->setCardToken($token);

        $transaction->customer->setFirstName($order->get_billing_first_name());
        $transaction->customer->setLastName($order->get_billing_last_name());
        $transaction->customer->setCountry($order->get_billing_country());
        $transaction->customer->setAddress($order->get_billing_address_1() . $order->get_billing_address_2());
        $transaction->customer->setCity($order->get_billing_city());
        $transaction->customer->setZip($order->get_billing_postcode());
        $transaction->customer->setEmail($order->get_billing_email());

        if (in_array($order->get_billing_country(), array('US', 'CA'))) {
            $transaction->customer->setState($order->get_billing_state());
        }

        $transaction->setNotificationUrl($this->notify_url);

        if ($this->settings['mode'] == 'test') {
            $transaction->setTestMode(true);
        }

        $this->log("Info: Starting to create a transaction {$amount} in {$transaction->money->getCurrency()} for {$merchant_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__);

        $response = $transaction->submit();

        if ($response->isError() || $response->isFailed()) {
            $order->add_order_note(
                __('Issue: Creating the transaction failed!') . PHP_EOL . $response->getMessage()
            );
            return new WP_Error('begateway_error', __('There was a problem creating the transaction!', 'wc-begateway-payment'));
        }

        return $response;
    }


    protected function _init()
    {
        \BeGateway\Settings::$gatewayBase = 'https://' . $this->settings['domain-gateway'];
        \BeGateway\Settings::$checkoutBase = 'https://' . $this->settings['domain-checkout'];
        \BeGateway\Settings::$shopId = $this->settings['shop-id'];
        \BeGateway\Settings::$shopKey = $this->settings['secret-key'];
        \BeGateway\Settings::$shopPubKey = $this->settings['public-key'];
    }

    public function get_order_currency($order)
    {
        if (method_exists($order, 'get_currency')) {
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
    public function get_order_amount($order)
    {
        return $order->get_total() - $order->get_total_refunded();
    }

    function enqueueWidgetScripts($data)
    {
        $url = explode('.', $this->settings['domain-checkout']);
        $url[0] = 'js';
        $url = 'https://' . implode('.', $url) . '/widget/be_gateway.js';

        wp_register_script('begateway_wc_widget', $url, null, null);
        wp_register_script(
            'begateway_wc_widget_start',
            untrailingslashit(plugins_url('/', __FILE__)) . '/../js/script.js',
            array('begateway_wc_widget'),
            null
        );

        wp_localize_script(
            'begateway_wc_widget_start',
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
    public function get_invoice_data($order)
    {
        if (is_int($order)) {
            $order = wc_get_order($order);
        }

        if ($order->get_payment_method() !== $this->id) {
            throw new Exception('Unable to get invoice data.');
        }

        return array(
            'authorized_amount' => $order->get_total(),
            'settled_amount' => $order->get_meta('_begateway_transaction_captured_amount', true) ?: 0,
            'refunded_amount' => $order->get_meta('_begateway_transaction_refunded_amount', true) ?: 0,
            'state' => $order->get_status()
        );
    }

    /**
     * Log function
     */
    public function log($message)
    {
        if (empty($this->log)) {
            $this->log = new WC_Logger();
        }
        if ('yes' == $this->get_option('debug', 'no')) {
            $this->log->debug($message, array('source' => 'woocommerce-gateway-begateway'));
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log($message);
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
    protected function save_card_id($card, $order)
    {
        $order->update_meta_data('_begateway_card_id', $card->token);
        $order->update_meta_data('_begateway_card_last_4', $card->last_4);
        $order->update_meta_data('_begateway_card_brand', $card->brand != 'master' ? $card->brand : 'mastercard');
        $order->save();
    }

    /**
     * @param $order
     *
     * @return mixed
     */
    protected function get_card_id($order)
    {
        $card_id = $order->get_meta('_begateway_card_id', true);
        if ($card_id) {
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
    public function can_payment_method_refund($order)
    {
        $pm = $this->getPaymentMethod($order);
        return !in_array($pm, self::NO_REFUND);
    }

    /**
     * Check if a payment method supports cancel
     *
     * @param WC_Order $order The order object related to the transaction.
     * @return boolean
     */
    public function can_payment_method_cancel($order)
    {
        $pm = $this->getPaymentMethod($order);
        return !in_array($pm, self::NO_CANCEL);
    }

    /**
     * Check if a payment method supports capture
     *
     * @param WC_Order $order The order object related to the transaction.
     * @return boolean
     */
    public function can_payment_method_capture($order)
    {
        $pm = $this->getPaymentMethod($order);
        return !in_array($pm, self::NO_CAPTURE);
    }

    /**
     * Return order payment method
     *
     * @param WC_Order $order The order object related to the transaction.
     * @return string
     */
    public function getPaymentMethod($order)
    {
        return $order->get_meta('_begateway_transaction_payment_method');
    }

    /**
     * Save user locale to use it in subscription charges
     * @param WC_Order $order The order object related to the transaction.
     * @param string $lang Locale code
     */
    protected function save_locale($locale, $order)
    {
        $lang = $order->get_meta('_begateway_transaction_language', true) ?: $locale;
        $order->get_meta('_begateway_transaction_language', $lang);
    }

    /**
     * Get saved user locale to use it in subscription charges
     * @param WC_Order $order The order object related to the transaction.
     * @return string
     */
    protected function get_locale($order)
    {
        return $order->get_meta('_begateway_transaction_language', true) ?: 'en';
    }

    /**
     * get values for additional_data.contract param
     * @param null
     * @return array
     */
    protected function get_contract_data()
    {
        return [];
    }

    /**
     * Mapping wordpress languages to begateway locales
     * @return string
     */
    function getLanguage()
    {
        // Old version
        // $lang = explode('_', get_locale());
        // return $lang[0];

        switch (get_locale()) {
            case 'az':
            case 'azb':
                return 'az';
            case 'bel':
                return 'be';
            case 'bn_BD':
            case 'bn_IN':
                return 'bn';
            case 'da_DK':
                return 'da';
            case 'de_AT':
            case 'de_CH':
            case 'de_CH_informal':
            case 'de_DE':
            case 'de_DE_formal':
                return 'de';
            case 'en_AU':
            case 'en_CA':
            case 'en_GB':
            case 'en_NZ':
            case 'en_US':
            case 'en_ZA':
                return 'en';
            case 'ca':
            case 'es_AR':
            case 'es_CL':
            case 'es_CO':
            case 'es_CR':
            case 'es_ES':
            case 'es_GT':
            case 'es_MX':
            case 'es_PE':
            case 'es_UY':
            case 'es_VE':
                return 'es';
            case 'et':
                return 'et';
            case 'fi':
                return 'fi';
            case 'fr_BE':
            case 'fr_CA':
            case 'fr_FR':
                return 'fr';
            case 'it_IT':
                return 'it';
            case 'ja':
                return 'ja';
            case 'ka_GE':
                return 'ka';
            case 'kk':
                return 'kk';
            case 'lv':
                return 'lv';
            case 'nn_NO':
                return 'no';
            case 'pl_PL':
                return 'pl';
            case 'pt_AO':
            case 'pt_BR':
                return 'pt';
            case 'pt_PT':
            case 'pt_PT_ao90':
                return 'pt-PT';
            case 'ro_RO':
                return 'ro';
            case 'ru_RU':
                return 'ru';
            case 'sr_RS':
                return 'sr';
            case 'sv_SE':
                return 'sv';
            case 'tr_TR':
                return 'tr';
            case 'uk':
                return 'uk';
            case 'zh_CN':
            case 'zh_HK':
            case 'zh_TW':
                return 'zh';
            default:
                return 'en';
        }
    }
}
