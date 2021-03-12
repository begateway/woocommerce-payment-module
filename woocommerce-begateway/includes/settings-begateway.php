<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = array(
  'enabled' => array(
    'title' => __( 'Enable/Disable', 'woocommerce-begateway' ),
    'type' => 'checkbox',
    'label' => __( 'Enable BeGateway', 'woocommerce-begateway' ),
    'default' => 'yes'
  ),
  'title' => array(
    'title' => __( 'Title', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'This is the title displayed to the user during checkout', 'woocommerce-begateway' ),
    'default' => __( 'Credit or debit card', 'woocommerce-begateway' ),
    'desc_tip'    => true
  ),
  'description' => array(
    'title' => __( 'Description', 'woocommerce-begateway' ),
    'type' => 'textarea',
    'description' => __( 'This is the description which the user sees during checkout', 'woocommerce-begateway' ),
    'default' => __("Visa, Mastercard", 'woocommerce-begateway'),
    'desc_tip'    => true
  ),
  'shop-id' => array(
    'title' => __( 'Shop ID', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'Please enter your shop Id.', 'woocommerce-begateway' ),
    'default' => '361',
    'desc_tip'    => true
  ),
  'secret-key' => array(
    'title' => __( 'Secret key', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'Please enter your shop secret key', 'woocommerce-begateway' ),
    'default' => 'b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d',
    'desc_tip'    => true
  ),
  'public-key' => array(
    'title' => __( 'Public key', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'Please enter your shop public key', 'woocommerce-begateway' ),
    'default' => 'b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d',
    'desc_tip'    => true
  ),
  'domain-gateway' => array(
    'title' => __( 'Payment gateway domain', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'Please enter payment gateway domain of your payment processor', 'woocommerce-begateway' ),
    'default' => 'demo-gateway.begateway.com',
    'desc_tip'    => true
  ),
  'domain-checkout' => array(
    'title' => __( 'Payment page domain', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'Please enter payment page domain of your payment processor', 'woocommerce-begateway' ),
    'default' => 'checkout.begateway.com',
    'desc_tip'    => true
  ),
  'transaction_type'      => array(
    'title' => __('Transaction type', 'woocommerce-begateway'),
    'type' => 'select',
    'options' => array(
      'payment' => __('Payment', 'woocommerce-begateway'),
      'authorization' => __('Authorization', 'woocommerce-begateway')
    ),
    'description' => __( 'Select Payment (Authorization & Capture) or Authorization', 'woocommerce-begateway' ),
    'desc_tip'    => true
  ),
	'payment_methods' => array(
		'title'    => __( 'Payment methods', 'woocommerce-begateway' ),
		'type'     => 'multiselect',
		'class'    => 'chosen_select',
		'css'      => 'width: 350px;',
		'desc_tip' => __( 'Select the payment method types to accept them explicity', 'woocommerce-begateway' ),
		'options'  => array(
			'bankcard'   => __('Bankcard', 'woocommerce-begateway' ),
			'halva'      => __('Halva', 'woocommerce-begateway' ),
			'erip'       => __('ERIP', 'woocommerce-begateway' )
		),
		'default'  => array( 'bankcard'),
	),
  'erip_service_no' => array(
    'title' => __( 'ERIP service code', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'Enter ERIP service code provided you by your payment service provider', 'woocommerce-begateway' ),
    'default' => '99999999',
    'desc_tip'    => true
  ),
  'payment_valid' => array(
    'title' => __( 'Payment valid (minutes)', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'The value sets a period of time within which an order must be paid', 'woocommerce-begateway' ),
    'default' => '60',
    'desc_tip'    => true
  ),
  'mode'      => array(
    'title' => __('Payment mode', 'woocommerce-begateway'),
    'type' => 'select',
    'options' => array(
      'test' => __('Test', 'woocommerce-begateway'),
      'live' => __('Live', 'woocommerce-begateway')
    ),
    'description' => __( 'Select module payment mode', 'woocommerce-begateway' ),
    'default' => 'test',
    'desc_tip'    => true
  ),
  'debug' => array(
    'title' => __( 'Debug Log', 'woocommerce-begateway' ),
    'type' => 'checkbox',
    'label' => __( 'Enable logging', 'woocommerce-begateway' ),
    'default' => 'no',
    'description' =>  __( 'Log events', 'woocommerce-begateway' ),
    'desc_tip'    => true
  )
);

return apply_filters('wc_begateway_settings', $settings);
