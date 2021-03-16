<?php
/*
Plugin Name: WooCommerce BeGateway Payment Gateway
Plugin URI: https://github.com/begateway/woocommerce-payment-module
Description: Extends WooCommerce with BeGateway payment gateway.
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

class WC_BeGateway
{
  function __construct()
  {
    $this->id = 'begateway';

    add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
    add_action( 'woocommerce_loaded', array( $this, 'woocommerce_loaded' ), 40 );

    // Add statuses for payment complete
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array(
			$this,
			'add_valid_order_statuses'
		), 10, 2 );

    // Add Admin Backend Actions
    add_action( 'wp_ajax_begateway_capture', array(
      $this,
      'ajax_begateway_capture'
    ) );

    add_action( 'wp_ajax_begateway_cancel', array(
      $this,
      'ajax_begateway_cancel'
    ) );

    add_action( 'wp_ajax_begateway_refund', array(
      $this,
      'ajax_begateway_refund'
    ) );

    add_action( 'wp_ajax_begateway_capture_partly', array(
      $this,
      'ajax_begateway_capture_partly'
    ) );

    add_action( 'wp_ajax_begateway_refund_partly', array(
      $this,
      'ajax_begateway_refund'
    ) );

		// add meta boxes
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );

    // Add scripts and styles for admin
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
  } // end __construct

  /**
	 * Init localisations and files
	 * @return void
	 */
	public function init() {
		// Localization
    load_plugin_textdomain('woocommerce-begateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
  }

  /**
	* WooCommerce Loaded: load classes
	* @return void
	*/
  public function woocommerce_loaded() {
    require_once( dirname( __FILE__ ) . '/begateway-api-php/lib/BeGateway.php' );
    include_once( dirname( __FILE__ ) . '/includes/class-wc-begateway-payment-token.php' );
    include_once( dirname( __FILE__ ) . '/includes/class-wc-begateway-payment-tokens.php' );
    include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-begateway.php' );

    if ($this->is_woocommerce_subscription_support_enabled()) {
      // register gateway with subscription support
      include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-begateway-subscriptions.php' );
      WC_BeGateway::register_gateway('WC_Gateway_BeGateway_Subscriptions');
    } else {
      WC_BeGateway::register_gateway('WC_Gateway_BeGateway');
    }
  }

  /**
  * Register payment gateway
  *
  * @param string $class_name
  */
  public static function register_gateway($class_name) {
    global $gateways;

		if ( ! $gateways ) {
			$gateways = array();
		}

		if ( ! isset( $gateways[ $class_name ] ) ) {
			// Initialize instance
			if ( $gateway = new $class_name ) {
				$gateways[] = $class_name;

				// Register gateway instance
				add_filter( 'woocommerce_payment_gateways', function ( $methods ) use ( $gateway ) {
					$methods[] = $gateway;

					return $methods;
				} );
			}
		}
  }

  /**
 * Allow processing/completed statuses for capture
 *
 * @param array    $statuses
 * @param WC_Order $order
 *
 * @return array
 */
	public function add_valid_order_statuses( $statuses, $order ) {
		if ( $this->id == $order->get_payment_method() ) {
			$statuses = array_merge( $statuses, array(
				'processing',
				'completed'
			) );
		}

		return $statuses;
	}

  /**
 * Add meta boxes in admin
 * @return void
 */
	public function add_meta_boxes( $post_type, $post ) {
    if ( ! isset( $post->ID ) ) {       // Exclude links.
      return;
    }

		$screen     = get_current_screen();
		$post_types = [ 'shop_order', 'shop_subscription' ];

		if ( in_array( $screen->id, $post_types, true ) && in_array( $post_type, $post_types, true ) ) {
			if ( $order = wc_get_order( $post->ID ) ) {
				$payment_method = $order->get_payment_method();
				if ( $this->id == $payment_method ) {
					add_meta_box( 'begateway-payment-actions', __( 'Transactions', 'woocommerce-begateway' ), [
						&$this,
						'meta_box_payment',
					], $post_type, 'side', 'high', [
             '__block_editor_compatible_meta_box' => true
          ]
         );
				}
			}
		}
	}

  /**
	 * Inserts the content of the API actions into the meta box
	 */
	public function meta_box_payment($post) {
    if ( ! isset( $post->ID ) ) {       // Exclude links.
      return;
    }

		if ( $order = wc_get_order( $post->ID ) ) {

      $payment_method = $order->get_payment_method();

			if ( $this->id == $payment_method ) {

				do_action( 'woocommerce_begateway_meta_box_payment_before_content', $order );

				#global $post_id;
				#$order = wc_get_order( $post_id );

				// Get Payment Gateway
				$gateways = WC()->payment_gateways()->get_available_payment_gateways();

				/** @var WC_Gateway_BeGateway $gateway */
				$gateway = 	$gateways[ $payment_method ];

				try {
					wc_get_template(
						'admin/metabox-order.php',
						array(
							'gateway'    => $gateway,
							'order'      => $order,
							'order_id'   => $order->get_id(),
							'order_data' => $gateway->get_invoice_data( $order )
						),
						'',
						dirname( __FILE__ ) . '/templates/'
					);
				} catch ( Exception $e ) {
				}
			}
		}
	}

	public function meta_box_subscription() {
	  $this->meta_box_payment();
	}

	/**
	 * MetaBox for Payment Actions
	 * @return void
	 */
	public static function order_meta_box_payment_actions() {
		global $post_id;
		$order = wc_get_order( $post_id );

		// Get Payment Gateway
		$payment_method = $order->get_payment_method();
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		/** @var WC_Gateway_BeGateway $gateway */
		$gateway = 	$gateways[ $payment_method ];

		wc_get_template(
			'admin/payment-actions.php',
			array(
				'gateway'    => $gateway,
				'order'      => $order,
				'order_id'   => $post_id,
			),
			'',
			dirname( __FILE__ ) . '/templates/'
		);
	}

  /**
	 * Enqueue Scripts in admin
	 *
	 * @param $hook
	 *
	 * @return void
 */
	public function admin_enqueue_scripts( $hook ) {
		if ( $hook === 'post.php' ) {
			// Scripts
			wp_register_script(
                'begateway-js-input-mask',
                plugin_dir_url( __FILE__ ) . 'js/jquery.inputmask.js',
                array( 'jquery'),
                '5.0.3'
            );
			wp_register_script(
                'begateway-admin-js',
                plugin_dir_url( __FILE__ ) . 'js/admin.js',
                array(
                  'jquery',
	                'begateway-js-input-mask'
                )
            );
			wp_enqueue_style( 'wc-gateway-begateway', plugins_url( '/css/style.css', __FILE__ ), array(), FALSE, 'all' );

			// Localize the script
			$translation_array = array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'text_wait' => __( 'Please wait...', 'woocommerce-begateway' ),
			);
			wp_localize_script( 'begateway-admin-js', 'BeGateway_Admin', $translation_array );

			// Enqueued script with localized data
			wp_enqueue_script( 'begateway-admin-js' );
		}
	}

  /**
	 * Action for Capture
	 */
	public function ajax_begateway_capture() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'begateway' ) ) {
			exit( 'Invalid nonce' );
		}

		$order_id = (int) $_REQUEST['order_id'];
		$order = wc_get_order( $order_id );

		// Get Payment Gateway
		$payment_method = $order->get_payment_method();
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		/** @var WC_Gateway_BeGateway $gateway */
		$gateway = 	$gateways[ $payment_method ];
		$result = $gateway->capture_payment( $order_id, $order->get_total() );

    if (!is_wp_error($result)) {
			wp_send_json_success( __( 'Capture success.', 'woocommerce-begateway' ) );
    } else {
			wp_send_json_error( $result->get_error_message() );
    }
	}

	/**
	 * Action for Cancel
	 */
	public function ajax_begateway_cancel() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'begateway' ) ) {
			exit( 'Invalid nonce' );
		}

		$order_id = (int) $_REQUEST['order_id'];
		$order = wc_get_order( $order_id );

		//
		// Check if the order is already cancelled
		// ensure no more actions are made
		//
		if ( $order->get_meta( '_begateway_transaction_voided', true ) === "yes" ) {
			wp_send_json_success( __( 'Order already cancelled.', 'woocommerce-begateway' ) );
			return;
		}

		// Get Payment Gateway
		$payment_method = $order->get_payment_method();
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		/** @var WC_Gateway_BeGateway $gateway */
		$gateway = 	$gateways[ $payment_method ];

		$result = $gateway->cancel_payment( $order_id, $order->get_total() );

    if (!is_wp_error($result)) {
			wp_send_json_success( __( 'Cancel success.', 'woocommerce-begateway' ) );
    } else {
			wp_send_json_error( $result->get_error_message() );
    }
	}

	/**
	 * Action for Cancel
	 */
	public function ajax_begateway_refund() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'begateway' ) ) {
			exit( 'Invalid nonce' );
		}

		$amount = $_REQUEST['amount'];
		$order_id = (int) $_REQUEST['order_id'];
		$order = wc_get_order( $order_id );

    $amount = str_replace(",", ".", $amount);
    $amount = floatval($amount);

		$payment_method = $order->get_payment_method();
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		/** @var WC_Gateway_BeGateway $gateway */
		$gateway = 	$gateways[ $payment_method ];
		$result = $gateway->refund_payment( $order_id, $amount );

    if (!is_wp_error($result)) {
			wp_send_json_success( __( 'Refund success.', 'woocommerce-begateway' ) );
    } else {
			wp_send_json_error( $result->get_error_message() );
    }
	}

	/**
	 * Action for partial capture
	 */
	public function ajax_begateway_capture_partly() {

		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'begateway' ) ) {
			exit( 'Invalid nonce' );
		}

		$amount = $_REQUEST['amount'];
		$order_id = (int) $_REQUEST['order_id'];
		$order = wc_get_order( $order_id );

    $amount = str_replace(",", ".", $amount);
    $amount = floatval($amount);

		// Get Payment Gateway
		$payment_method = $order->get_payment_method();
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		/** @var WC_Gateway_BeGateway $gateway */
		$gateway = 	$gateways[ $payment_method ];
		$result = $gateway->capture_payment( $order_id, $amount );

    if (!is_wp_error($result)) {
			wp_send_json_success( __( 'Capture partly success.', 'woocommerce-begateway' ) );
    } else {
			wp_send_json_error( $result->get_error_message() );
    }
	}

  protected function is_woocommerce_subscription_support_enabled() {
    return class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' );
  }
} //end of class

new WC_BeGateway();
