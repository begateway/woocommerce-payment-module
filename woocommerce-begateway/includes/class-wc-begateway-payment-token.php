<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Payment_Token_BeGateway extends WC_Payment_Token {
	/** @protected string Token Type String */
	protected $type = 'begateway';

	/**
	 * Stores Paylike payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'last4' => '',
		'brand' => '',
	);

	/**
	 * Returns the last four digits.
	 *
	 * @param string $context
	 *
	 * @return string Last 4 digits
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @param string $last4
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}

	/**
	 * Returns the brand.
	 *
	 * @param string $context
	 *
	 * @return string Card Brand
	 */
	public function get_brand( $context = 'view' ) {
		return $this->get_prop( 'brand', $context );
	}

	/**
	 * Set the card brand.
	 *
	 * @param string $brand
	 */
	public function set_brand( $brand ) {
		$this->set_prop( 'brand', $brand );
	}

	/**
	 * Get the source of the token (card|transaction)
	 *
	 * @return string
	 */
	public function get_token_source() {
		$token = $this->get_token();
		$token = explode( '-', $token );


		return $token[0];
	}

	/**
	 * Get token without prefix
	 *
	 * @return string|string[]
	 */
	public function get_token_id() {
		$original_token = $this->get_token();
		$token = explode( '-', $original_token );

		return str_replace( $token[0] . "-", "", $original_token );
	}

	/**
	 * Get type to display to user.
	 *
	 * @param string $deprecated Deprecated since WooCommerce 3.0
	 *
	 * @return string
	 * @since  4.0.0
	 * @version 4.0.0
	 */
	public function get_display_name( $deprecated = '' ) {

		$source = $this->get_token_source();
		$label = ucfirst( $source );

		if ( $source === 'transaction' || $source === 'card' ) {
			$label = $this->get_brand();
		}

		$display = sprintf(
			__( '%s ending in %s', 'woocommerce-begateway' ),
			$label,
			$this->get_last4()
		);

		return $display;
	}

}
