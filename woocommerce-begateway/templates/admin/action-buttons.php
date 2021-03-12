<?php
/** @var WC_Gateway_BeGateway $gateway */
/** @var WC_Order $order */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

?>

<?php if ( $gateway->can_capture( $order ) ): ?>
	<button id="begateway_capture"
			type="button" class="button button-primary"
			data-nonce="<?php echo wp_create_nonce( 'begateway' ); ?>"
			data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
		<?php _e( 'Capture Payment', 'woocommerce-begateway' ) ?>
	</button>
<?php endif; ?>

<?php if ( $gateway->can_cancel( $order ) ): ?>
	<button id="begateway_cancel"
			type="button" class="button button-primary"
			data-nonce="<?php echo wp_create_nonce( 'begateway' ); ?>"
			data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
		<?php _e( 'Cancel Payment', 'woocommerce-begateway' ) ?>
	</button>
<?php endif; ?>
