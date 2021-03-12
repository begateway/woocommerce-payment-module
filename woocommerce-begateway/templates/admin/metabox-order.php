<?php
/** @var WC_Gateway_BeGateway $gateway */
/** @var WC_Order $order */
/** @var int $order_id */
/** @var array $order_data */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

?>

<ul class="order_action">
	<li class="begateway-admin-section-li-header">
        <?php echo __( 'State', 'woocommerce-begateway' ); ?>: <?php echo $order_data['state']; ?>
    </li>

	<?php $order_is_cancelled = ( $order->get_meta( '_begateway_order_cancelled', true ) === '1' ); ?>
	<?php if ($order_is_cancelled && 'cancelled' != $order_data['state']): ?>
		<li class="begateway-admin-section-li-small">
            <?php echo __( 'Order is cancelled', 'woocommerce-begateway' ); ?>
        </li>
	<?php endif; ?>

	<li class="begateway-admin-section-li">
        <span class="begateway-balance__label">
            <?php echo __( 'Remaining balance', 'woocommerce-begateway' ); ?>:
        </span>
        <span class="begateway-balance__amount">
            <span class='begateway-balance__currency'>
                &nbsp;
            </span>
            <?php echo wc_price( ( $order_data['authorized_amount'] - $order_data['settled_amount'] ) / 100 ); ?>
        </span>
    </li>
	<li class="begateway-admin-section-li">
        <span class="begateway-balance__label">
            <?php echo __( 'Total authorized', 'woocommerce-begateway' ); ?>:
        </span>
        <span class="begateway-balance__amount">
            <span class='begateway-balance__currency'>
                &nbsp;
            </span>
            <?php echo wc_price( $order_data['authorized_amount'] / 100 ); ?>
        </span>
    </li>
	<li class="begateway-admin-section-li">
        <span class="begateway-balance__label">
            <?php echo __( 'Total settled', 'woocommerce-begateway' ); ?>:
        </span>
        <span class="begateway-balance__amount">
            <span class='begateway-balance__currency'>
                &nbsp;
            </span>
            <?php echo wc_price( $order_data['settled_amount'] / 100 ); ?>
        </span>
    </li>
	<li class="begateway-admin-section-li">
        <span class="begateway-balance__label">
            <?php echo __( 'Total refunded', 'woocommerce-begateway' ); ?>:
        </span>
        <span class="begateway-balance__amount">
            <span class='begateway-balance__currency'>
                &nbsp;
            </span>
            <?php echo wc_price( $order_data['refunded_amount'] / 100 ); ?>
        </span>
    </li>
	<li style='font-size: xx-small'>&nbsp;</li>
	<?php if ($order_data['settled_amount'] == 0 && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled): ?>
		<li class="begateway-full-width">
            <a class="button button-primary" data-action="begateway_capture" id="begateway_capture" data-nonce="<?php echo wp_create_nonce( 'begateway' ); ?>" data-order-id="<?php echo $order_id; ?>" data-confirm="<?php echo __( 'You are about to CAPTURE this payment', 'woocommerce-begateway' ); ?>">
                <?php echo sprintf( __( 'Capture Full Amount (%s)', 'woocommerce-begateway' ), wc_price( $order_data['authorized_amount'] / 100 ) ); ?>
            </a>
        </li>
	<?php endif; ?>

	<?php if ($order_data['settled_amount'] == 0 && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled): ?>
		<li class="begateway-full-width">
            <a class="button" data-action="begateway_cancel" id="begateway_cancel" data-confirm="<?php echo __( 'You are about to CANCEL this payment', 'woocommerce-begateway' ); ?>" data-nonce="<?php echo wp_create_nonce( 'begateway' ); ?>" data-order-id="<?php echo $order_id; ?>">
                <?php echo __( 'Cancel remaining balance', 'woocommerce-begateway' ); ?>
            </a>
        </li>
		<li style='font-size: xx-small'>&nbsp;</li>
	<?php endif; ?>

	<?php if ($order_data['authorized_amount'] > $order_data['settled_amount'] && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled): ?>
		<li class="begateway-admin-section-li-header">
            <?php echo __( 'Partly capture', 'woocommerce-begateway' ); ?>
        </li>
		<li class="begateway-balance last">
            <span class="begateway-balance__label" style="margin-right: 0;">
                <?php echo __( 'Capture amount', 'woocommerce-begateway' ); ?>:
            </span>
            <span class="begateway-partly_capture_amount">
                <input id="begateway-capture_partly_amount-field" class="begateway-capture_partly_amount-field" type="text" autocomplete="off" size="6" value="<?php echo ( $order_data['authorized_amount'] - $order_data['settled_amount'] ) / 100; ?>" />
            </span>
        </li>
		<li class="begateway-full-width">
            <a class="button" id="begateway_capture_partly" data-nonce="<?php echo wp_create_nonce( 'begateway' ); ?>" data-order-id="<?php echo $order_id; ?>">
                <?php echo __( 'Capture Specified Amount', 'woocommerce-begateway' ); ?>
            </a>
        </li>
		<li style='font-size: xx-small'>&nbsp;</li>
	<?php endif; ?>

	<?php if ( $order_data['settled_amount'] > $order_data['refunded_amount'] && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled ): ?>
		<li class="begateway-admin-section-li-header">
            <?php echo __( 'Partly refund', 'woocommerce-begateway' ); ?>
        </li>
		<li class="begateway-balance last">
            <span class="begateway-balance__label" style='margin-right: 0;'>
                <?php echo __( 'Refund amount', 'woocommerce-begateway' ); ?>:
            </span>
            <span class="begateway-partly_refund_amount">
                <input id="begateway-refund_partly_amount-field" class="begateway-refund_partly_amount-field" type="text" size="6" autocomplete="off" value="<?php echo ( $order_data['settled_amount'] - $order_data['refunded_amount'] ) / 100; ?>" />
            </span>
        </li>
		<li class="begateway-full-width">
            <a class="button" id="begateway_refund_partly" data-nonce="<?php echo wp_create_nonce( 'begateway' ); ?>" data-order-id="<?php echo $order_id; ?>">
                <?php echo __( 'Refund Specified Amount', 'woocommerce-begateway' ); ?>
            </a>
        </li>
		<li style='font-size: xx-small'>&nbsp;</li>
	<?php endif; ?>

	<li class="begateway-admin-section-li-header-small">
        <?php echo __( 'Order ID', 'woocommerce-begateway' ) ?>
    </li>
	<li class="begateway-admin-section-li-small">
        <?php echo $order_data["handle"]; ?>
    </li>
	<li class="begateway-admin-section-li-header-small">
        <?php echo __( 'Transaction ID', 'woocommerce-begateway' ) ?>
    </li>
	<li class="begateway-admin-section-li-small">
        <?php echo $order_data["id"]; ?>
    </li>
	<?php if ( isset( $order_data['transactions'][0] ) && isset( $order_data['transactions'][0]['card_transaction'] ) ): ?>
        <li class="begateway-admin-section-li-header-small">
			<?php echo __( 'Card number', 'woocommerce-begateway' ); ?>
        </li>
        <li class="begateway-admin-section-li-small">
			<?php echo WC_ReepayCheckout::formatCreditCard( $order_data['transactions'][0]['card_transaction']['masked_card'] ); ?>
        </li>
        <p>
        <center>
            <img src="<?php echo $gateway->get_logo( $order_data['transactions'][0]['card_transaction']['card_type'] ); ?>" class="begateway-admin-card-logo" />
        </center>
        </p>
	<?php endif; ?>
</ul>
