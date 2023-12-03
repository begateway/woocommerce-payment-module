<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * BeGateway payment method integration
 *
 * @since 2.2.0
 */
final class WC_BeGateway_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Name of the payment method.
	 *
	 * @var string
	 */
	protected $name = 'begateway';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_begateway_settings', [] );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		$payment_gateways_class   = WC()->payment_gateways();
		$payment_gateways         = $payment_gateways_class->payment_gateways();

		return $payment_gateways['begateway']->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path       = '/assets/js/frontend/blocks.js';
		$script_asset_path = WC_BeGateway::plugin_abspath() . 'assets/js/frontend/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => WC_BeGateway::plugin_version()
			);
		$script_url        = WC_BeGateway::plugin_url() . $script_path;

		wp_register_script(
			'wc-begateway-payments-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-begateway-payments-blocks', 'wc-begateway-payment', WC_BeGateway::plugin_abspath() . 'languages/' );
		}

		return [ 'wc-begateway-payments-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
			//'logo_url'    => WC_BeGateway::plugin_url() . '/assets/images/icon.png',
		];
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		return $payment_gateways['begateway']->supports;
	}
}
