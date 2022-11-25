<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = array(
  'enabled' => array(
    'title' => __('Enable/Disable', 'wc-begateway-payment'),
    'type' => 'checkbox',
    'label' => __( 'Enable BeGateway', 'wc-begateway-payment' ),
    'default' => 'yes'
  ),
  'title' => array(
    'title' => __( 'Title', 'wc-begateway-payment' ),
    'type' => 'text',
    'description' => __( 'This is the title displayed to the user during checkout', 'wc-begateway-payment' ),
    'default' => __( 'Credit or debit card', 'wc-begateway-payment' ),
    'desc_tip'    => true
  ),
  'description' => array(
    'title' => __( 'Description', 'wc-begateway-payment' ),
    'type' => 'textarea',
    'description' => __( 'This is the description which the user sees during checkout', 'wc-begateway-payment' ),
    'default' => __("Visa, Mastercard", 'wc-begateway-payment'),
    'desc_tip'    => true
  ),
  'shop-id' => array(
    'title' => __( 'Shop ID', 'wc-begateway-payment' ),
    'type' => 'text',
    'description' => __( 'Please enter your shop Id', 'wc-begateway-payment' ),
    'default' => '361',
    'desc_tip'    => true
  ),
  'secret-key' => array(
    'title' => __( 'Secret key', 'wc-begateway-payment' ),
    'type' => 'text',
    'description' => __( 'Please enter your shop secret key', 'wc-begateway-payment' ),
    'default' => 'b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d',
    'desc_tip'    => true
  ),
  'public-key' => array(
    'title' => __( 'Public key', 'wc-begateway-payment' ),
    'type' => 'text',
    'description' => __( 'Please enter your shop public key', 'wc-begateway-payment' ),
    'default' => 'b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d',
    'desc_tip'    => true
  ),
  'domain-gateway' => array(
    'title' => __( 'Payment gateway domain', 'wc-begateway-payment' ),
    'type' => 'text',
    'description' => __( 'Please enter payment gateway domain of your payment processor', 'wc-begateway-payment' ),
    'default' => 'demo-gateway.begateway.com',
    'desc_tip'    => true
  ),
  'domain-checkout' => array(
    'title' => __( 'Payment page domain', 'wc-begateway-payment' ),
    'type' => 'text',
    'description' => __( 'Please enter payment page domain of your payment processor', 'wc-begateway-payment' ),
    'default' => 'checkout.begateway.com',
    'desc_tip'    => true
  ),
  'transaction_type'      => array(
    'title' => __('Transaction type', 'wc-begateway-payment'),
    'type' => 'select',
    'options' => array(
      'payment' => __('Payment', 'wc-begateway-payment'),
      'authorization' => __('Authorization', 'wc-begateway-payment')
    ),
    'description' => __( 'Select Payment (Authorization & Capture) or Authorization', 'wc-begateway-payment' ),
    'desc_tip'    => true
  ),
	'payment_methods' => array(
		'title'    => __( 'Payment methods', 'wc-begateway-payment' ),
		'type'     => 'multiselect',
		'class'    => 'chosen_select',
		'css'      => 'width: 350px;',
		'desc_tip' => __( 'Select the payment method types to accept them explicity', 'wc-begateway-payment' ),
		'options'  => array(
			'bankcard'   => __('Bankcard', 'wc-begateway-payment' ),
			'halva'      => __('Halva', 'wc-begateway-payment' ),
			'erip'       => __('ERIP', 'wc-begateway-payment' )
		),
		'default'  => array( 'bankcard'),
	),
  'erip_service_no' => array(
    'title' => __( 'ERIP service code', 'wc-begateway-payment' ),
    'type' => 'text',
    'description' => __( 'Enter ERIP service code provided you by your payment service provider', 'wc-begateway-payment' ),
    'default' => '99999999',
    'desc_tip'    => true
  ),
  'payment_valid' => array(
    'title' => __( 'Payment valid (minutes)', 'wc-begateway-payment' ),
    'type' => 'text',
    'description' => __( 'The value sets a period of time within which an order must be paid', 'wc-begateway-payment' ),
    'default' => '60',
    'desc_tip'    => true
  ),
  'mode'      => array(
    'title' => __('Payment mode', 'wc-begateway-payment'),
    'type' => 'select',
    'options' => array(
      'test' => __('Test', 'wc-begateway-payment'),
      'live' => __('Live', 'wc-begateway-payment')
    ),
    'description' => __( 'Select module payment mode', 'wc-begateway-payment' ),
    'default' => 'test',
    'desc_tip'    => true
  ),
  'debug' => array(
    'title' => __( 'Debug Log', 'wc-begateway-payment' ),
    'type' => 'checkbox',
    'label' => __( 'Enable logging', 'wc-begateway-payment' ),
    'default' => 'no',
    'description' =>  __( 'Log events', 'wc-begateway-payment' ),
    'desc_tip'    => true
  )
);

return apply_filters('wc_begateway_settings', $settings);
