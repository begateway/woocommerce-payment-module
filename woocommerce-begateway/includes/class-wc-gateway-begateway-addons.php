<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Gateway_BeGateway_Addons
 *
 * The addons class, used for subscriptions.
 */
class WC_Gateway_BeGateway_Addons extends WC_Gateway_BeGateway {


	/**
	 * WC_Gateway_BeGateway_Addons constructor.
	 */
	public function __construct() {
		parent::__construct();
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
				$this,
				'scheduled_subscription_payment',
			), 10, 2 );
			add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );
			add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array(
				$this,
				'update_failing_payment_method',
			), 10, 2 );
			// display the credit card used for a subscription in the "My Subscriptions" table.
			add_filter( 'woocommerce_my_subscriptions_payment_method', array(
				$this,
				'maybe_render_subscription_payment_method',
			), 10, 2 );
			// allow store managers to manually set Paylike as the payment method on a subscription.
			add_filter( 'woocommerce_subscription_payment_meta', array(
				$this,
				'add_subscription_payment_meta',
			), 10, 2 );
			add_filter( 'woocommerce_subscription_validate_payment_meta', array(
				$this,
				'validate_subscription_payment_meta',
			), 10, 2 );
		}
	}

	/**
	 * Trigger scheduled subscription payment.
	 *
	 * @param float    $amount_to_charge The amount to charge.
	 * @param WC_Order $renewal_order An order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$result = $this->process_subscription_payment( $renewal_order, $amount_to_charge );
		if ( is_wp_error( $result ) ) {
			/* translators: %1$s is replaced with the error message */
			$renewal_order->update_status( 'failed', sprintf( __( 'BeGateway Transaction Failed (%s)', 'woocommerce-begateway' ), $result->get_error_message() ) );
		}
	}


	/**
	 * Process payment for subscription
	 *
	 * @param WC_Order $order the renewal order created from the initial order only containing the subscription product we are renewing.
	 * @param int      $amount The amount for the subscription.
	 *
	 * @return bool|int|mixed|null|WP_Error
	 */
	public function process_subscription_payment( $order = null, $amount = 0 ) {
		if ( 0 == $amount ) {
			$order->payment_complete();

			return true;
		}
		// get last transaction id used.
		$last_transaction_id = $this->get_transaction_id( $order );
		$last_card_id        = $this->get_card_id( $order );
		$result              = null;
		if ( !$last_card_id ) {
				return new WP_Error( 'begateway_error', __( 'Card ID was found', 'woocommerce-begateway' ) );
		}
		$order_id = get_woo_id( $order );

		$this->log( "Info: Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );
		// create a new transaction from a previous one, or a card.
		if ( $last_card_id ) {
			// card can be added after a subscription, should get checked first.
			$result = $this->create_new_transaction( $last_card_id, $order, $amount);
		}

		if ( is_wp_error( $result ) ) {
			$this->log( 'Issue: Authorize has failed, the result from the verification threw an wp error:' . $result->get_error_message() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			$order->add_order_note(
				__( 'Unable to verify transaction!', 'woocommerce-begateway' ) . PHP_EOL .
				$result->get_error_message()
			);
			return $result;
		}

		$order->add_order_note(
      __( 'BeGateway authorization complete.', 'woocommerce-begateway' ) . PHP_EOL .
			__( 'Transaction ID: ', 'woocommerce-begateway' ) . $result->getUid() . PHP_EOL
		);

		$this->save_transaction_id( $result, $order );

		$this->log( 'Info: Authorize was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
	  update_post_meta( get_woo_id( $order ), '_begateway_transaction_captured',
      $this->settings['transaction_type'] == 'authorization' ? 'no' : 'yes'
    );

    $order->payment_complete($result->getUid());
	}

	/**
	 * Gets merchant id from transaction or card
	 *
	 * @param int    $entity_id card id / transaction id.
	 * @param string $type 'card' or 'transaction'.
	 *
	 * @return bool|int|mixed|null|WP_Error
	 */
	private function get_merchant_id() {
    return $this->settings['shop-id'];
	}

	/**
	 * Update the begateway_transaction_id for a subscription after using Paylike to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order        $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 *
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		update_post_meta( get_woo_id( $subscription ), '_begateway_transaction_id', get_post_meta( $renewal_order->get_id(), '_begateway_transaction_id', true ));
		update_post_meta( get_woo_id( $subscription ), '_begateway_card_id', get_post_meta( $renewal_order->get_id(), '_begateway_card_id', true ));
	}

	/**
	 * Don't transfer Paylike transaction id meta to resubscribe orders.
	 *
	 * @param WC_Order $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription.
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		delete_post_meta( get_woo_id( $resubscribe_order ), '_begateway_transaction_id' );
		delete_post_meta( get_woo_id( $resubscribe_order ), '_begateway_card_id' );
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string          $payment_method_to_display the default payment method text to display.
	 * @param WC_Subscription $subscription the subscription details.
	 *
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		// bail for other payment methods.
		if ( $this->id !== $subscription->get_payment_method() || ! $subscription->get_user() ) {
			return $payment_method_to_display;
		}
		$transaction_id = get_post_meta( get_woo_id( $subscription ), '_begateway_transaction_id', true );
		// add more details, if we can get the card.

    $this->_init();
    $query = new \BeGateway\QueryByUid;
    $query->setUid($transaction_id);

    $response = $query->submit();

    if ($response->isError()) {
      $this->log("Fetching transaction {$transaction_id} failed");
		} elseif ($response->isSuccess() && $response->getPaymentMethod()) {
      $card = $response->getPaymentMethod();
			$payment_method_to_display = sprintf( __( 'Via %s card ending in %s (%s)', 'woocommerce-begateway' ), ucfirst($card->brand), $card->last_4, ucfirst( $this->id ) );
    }

		return $payment_method_to_display;
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param array           $payment_meta associative array of meta data required for automatic payments.
	 * @param WC_Subscription $subscription An instance of a subscription object.
	 *
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_begateway_transaction_id' => array(
					'value' => get_post_meta( get_woo_id( $subscription ), '_begateway_transaction_id', true ),
					'label' => 'A previous transaction ID',
				),
				'begateway_card_id'         => array(
					'value' => get_post_meta( get_woo_id( $subscription ), '_begateway_card_id', true ),
					'label' => 'A previous card ID',
				),
			),
		);

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param $payment_method_id The ID of the payment method to validate.
	 * @param $payment_meta Associative array of meta data required for automatic payments.
	 *
	 * @throws Exception
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {
			if ( ! isset( $payment_meta['post_meta']['_begateway_transaction_id']['value'] ) || empty( $payment_meta['post_meta']['_begateway_transaction_id']['value'] ) ) {
				if ( ! isset( $payment_meta['post_meta']['_begateway_card_id']['value'] ) || empty( $payment_meta['post_meta']['_begateway_card_id']['value'] ) ) {
					throw new Exception( 'A "_begateway_transaction_id" value is required.' );
				}
			}
		}
	}

	/**
	 * Saves the transaction id on the order and subscription.
	 *
	 * @param array    $result The result returned by the api wrapper.
	 * @param WC_Order $order The order asociated with the order.
	 */
	protected function save_transaction_id( $result, $order ) {
		parent::save_transaction_id( $result, $order );
		// Also store it on the subscriptions being purchased or paid for in the order.
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( get_woo_id( $order ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( get_woo_id( $order ) );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( get_woo_id( $order ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( get_woo_id( $order ) );
		} else {
			$subscriptions = array();
		}
		foreach ( $subscriptions as $subscription ) {
			update_post_meta( get_woo_id( $subscription ), '_begateway_transaction_id', $result['id'] );
		}
	}

	/**
	 * Saves the card id on the order and subscription.
	 *
	 * @param int      $card_id The card reference.
	 * @param WC_Order $order The order reference.
	 */
	protected function save_card_id( $card_id, $order ) {
		parent::save_card_id( $card_id, $order );
		// Also store it on the subscriptions being purchased or paid for in the order.
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( get_woo_id( $order ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( get_woo_id( $order ) );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( get_woo_id( $order ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( get_woo_id( $order ) );
		} else {
			$subscriptions = array();
		}
		foreach ( $subscriptions as $subscription ) {
			update_post_meta( get_woo_id( $subscription ), '_begateway_card_id', $card_id );
		}
	}
}
