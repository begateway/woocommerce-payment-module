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
    'default' => __( 'Credit or debit card', 'woocommerce-begateway' )
  ),
  'admin_title' => array(
    'title' => __( 'Admin Title', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'This is the title displayed to the admin user', 'woocommerce-begateway' ),
    'default' => __( 'BeGateway', 'woocommerce-begateway' )
  ),
  'description' => array(
    'title' => __( 'Description', 'woocommerce-begateway' ),
    'type' => 'textarea',
    'description' => __( 'This is the description which the user sees during checkout', 'woocommerce-begateway' ),
    'default' => __("VISA, Mastercard", 'woocommerce-begateway')
  ),
  'shop-id' => array(
    'title' => __( 'Shop ID', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'Please enter your Shop Id.', 'woocommerce-begateway' ),
    'default' => '361'
  ),
  'secret-key' => array(
    'title' => __( 'Secret key', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'Please enter your Shop secret key.', 'woocommerce-begateway' ),
    'default' => 'b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d'
  ),
  'domain-gateway' => array(
    'title' => __( 'Payment gateway domain', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'Please enter payment gateway domain of your payment processor', 'woocommerce-begateway' ),
    'default' => 'demo-gateway.begateway.com'
  ),
  'domain-checkout' => array(
    'title' => __( 'Payment page domain', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'Please enter payment page domain of your payment processor', 'woocommerce-begateway' ),
    'default' => 'checkout.begateway.com'
  ),
  'transaction_type'      => array(
    'title' => __('Transaction type', 'woocommerce-begateway'),
    'type' => 'select',
    'options' => array(
      'payment' => __('Payment', 'woocommerce-begateway'),
      'authorization' => __('Authorization', 'woocommerce-begateway')
    ),
    'description' => __( 'Select Payment (Authorization & Capture) or Authorization.', 'woocommerce-begateway' )
  ),
  'enable_bankcard' => array(
    'title' => __( 'Enable Bankcard Payments', 'woocommerce-begateway' ),
    'type' => 'checkbox',
    'description' => __( 'This enables VISA/Mastercard and etc card payments', 'woocommerce-begateway' ),
    'default' => 'yes'
  ),
  'enable_bankcard_halva' => array(
    'title' => __( 'Enable Halva card payments', 'woocommerce-begateway' ),
    'type' => 'checkbox',
    'description' => __( 'This enables Halva card payments', 'woocommerce-begateway' ),
    'default' => 'no'
  ),
  'enable_erip' => array(
    'title' => __( 'Enable ERIP payments', 'woocommerce-begateway' ),
    'type' => 'checkbox',
    'description' => __( 'This enables ERIP payments', 'woocommerce-begateway' ),
    'default' => 'no'
  ),
  'erip_service_no' => array(
    'title' => __( 'ERIP service code', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'Enter ERIP service code provided you by your payment service provider', 'woocommerce-begateway' ),
    'default' => '99999999'
  ),
  'payment_valid' => array(
    'title' => __( 'Payment valid (minutes)', 'woocommerce-begateway' ),
    'type' => 'text',
    'description' => __( 'The value sets a period of time within which an order must be paid', 'woocommerce-begateway' ),
    'default' => '60'
  ),
  'compatibility_mode'   => array(
    'title'       => __( 'Compatibility Mode', 'woocommerce-begateway' ),
    'label'       => __( 'Don\'t capture from processing to completed', 'woocommerce-begateway' ),
    'type'        => 'checkbox',
    'description' => __( 'When this is checked you can capture payment by moving an order to On Hold and then to complete or processing status, when its not checked you can also complete them from processing to complete as well as the other 2 options', 'woocommerce-begateway' ),
    'default'     => 'yes',
    'desc_tip'    => true,
  ),
  'mode'      => array(
    'title' => __('Payment mode', 'woocommerce-begateway'),
    'type' => 'select',
    'options' => array(
      'test' => __('Test', 'woocommerce-begateway'),
      'live' => __('Live', 'woocommerce-begateway')
    ),
    'description' => __( 'Select module payment mode', 'woocommerce-begateway' ),
    'default' => 'test'
  ),
  'debug' => array(
    'title' => __( 'Debug Log', 'woocommerce-begateway' ),
    'type' => 'checkbox',
    'label' => __( 'Enable logging', 'woocommerce-begateway' ),
    'default' => 'no',
    'description' =>  __( 'Log events', 'woocommerce-begateway' ),
  )
);

return apply_filters( 'wc_begateway_settings', $settings );
