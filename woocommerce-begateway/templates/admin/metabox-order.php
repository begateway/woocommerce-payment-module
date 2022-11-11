<?php
/** @var WC_Gateway_BeGateway $gateway */
/** @var WC_Order $order */
/** @var int $order_id */
/** @var array $order_data */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
if ($gateway->get_transaction_id($order)):
?>
    <ul class="order_action">
        <?php if ($order->get_type() == 'shop_order'): ?>
            <?php $order_is_cancelled = ( 
                $order->get_meta( '_begateway_transaction_refunded', true ) === 'yes' ) || 
                $order->get_meta( '_begateway_transaction_voided', true ) === 'yes'; 
            ?>
            <?php if ($order_is_cancelled && 'cancelled' != $order_data['state']): ?>
                <li class="begateway-admin-section-li-small">
                    <?php _e( 'Order is cancelled', 'woocommerce-begateway' ); ?>
                </li>
            <?php endif; ?>

            <li class="begateway-admin-section-li">
                <span class="begateway-balance__label">
                    <?php _e( 'Remaining balance', 'woocommerce-begateway' ); ?>:
                </span>
                <span class="begateway-balance__amount">
                    <span class='begateway-balance__currency'>
                        &nbsp;
                    </span>
                    <?php echo(wc_price( $order_data['authorized_amount'] - $order_data['settled_amount'] ) ); ?>
                </span>
            </li>
            <li class="begateway-admin-section-li">
                <span class="begateway-balance__label">
                    <?php _e( 'Total authorized', 'woocommerce-begateway' ); ?>:
                </span>
                <span class="begateway-balance__amount">
                    <span class='begateway-balance__currency'>
                        &nbsp;
                    </span>
                    <?php echo( wc_price( $order_data['authorized_amount'] ) ); ?>
                </span>
            </li>
            <li class="begateway-admin-section-li">
                <span class="begateway-balance__label">
                    <?php _e( 'Total captured', 'woocommerce-begateway' ); ?>:
                </span>
                <span class="begateway-balance__amount">
                    <span class='begateway-balance__currency'>
                        &nbsp;
                    </span>
                    <?php echo( wc_price( $order_data['settled_amount'] ) ); ?>
                </span>
            </li>
            <li class="begateway-admin-section-li">
                <span class="begateway-balance__label">
                    <?php _e( 'Total refunded', 'woocommerce-begateway' ); ?>:
                </span>
                <span class="begateway-balance__amount">
                    <span class='begateway-balance__currency'>
                        &nbsp;
                    </span>
                    <?php echo( wc_price( $order_data['refunded_amount'] ) ); ?>
                </span>
            </li>
            <li style='font-size: xx-small'>&nbsp;</li>
        <?php $can_capture = $gateway->can_payment_method_capture( $order ); ?>
            <?php if ($order_data['settled_amount'] == 0 &&
                    ! in_array( $order_data['state'], array( 'cancelled', 'created') ) &&
                    ! $order_is_cancelled && $can_capture): ?>
                <li class="begateway-full-width">
                    <a class="button button-primary" data-action="begateway_capture" id="begateway_capture" 
                       data-nonce="<?php echo wp_create_nonce( 'begateway' ); ?>" 
                       data-order-id="<?php esc_attr_e( $order_id ); ?>" 
                       data-confirm="<?php _e( 'You are about to CAPTURE this payment', 'woocommerce-begateway' ); ?>">
                       <?php esc_html( sprintf( __( 'Capture full amount (%s)', 'woocommerce-begateway' ), wc_price( $order_data['authorized_amount'] ) ) ); ?>
                    </a>
                </li>
            <?php endif; ?>

        <?php $can_cancel = $gateway->can_payment_method_cancel( $order ); ?>
            <?php if ($order_data['settled_amount'] == 0 && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled && $can_cancel): ?>
                <li class="begateway-full-width">
                    <a class="button" data-action="begateway_cancel" 
                       id="begateway_cancel" 
                       data-confirm="<?php _e( 'You are about to CANCEL this payment', 'woocommerce-begateway' ); ?>" 
                       data-nonce="<?php echo wp_create_nonce( 'begateway' ); ?>" 
                       data-order-id="<?php esc_attr_e( $order_id ); ?>">
                       <?php _e( 'Cancel transaction', 'woocommerce-begateway' ); ?>
                    </a>
                </li>
                <li style='font-size: xx-small'>&nbsp;</li>
            <?php endif; ?>

        <?php $can_capture = $gateway->can_payment_method_capture( $order ); ?>
        <?php $order_is_captured = $order->get_meta( '_begateway_transaction_captured', true) == 'yes'; ?>
            <?php if ($order_data['authorized_amount'] > $order_data['settled_amount'] && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled && !$order_is_captured && $can_capture): ?>
                <li class="begateway-admin-section-li-header">
                    <?php _e( 'Partly capture', 'woocommerce-begateway' ); ?>
                </li>
                <li class="begateway-balance last">
                    <span class="begateway-balance__label" style="margin-right: 0;">
                        <?php _e( 'Capture amount', 'woocommerce-begateway' ); ?>:
                    </span>
                    <span class="begateway-partly_capture_amount">
                        <input id="begateway-capture_partly_amount-field" class="begateway-capture_partly_amount-field" type="text" autocomplete="off" size="6" value="<?php esc_attr_e( $order_data['authorized_amount'] - $order_data['settled_amount'] ); ?>" />
                    </span>
                </li>
                <li class="begateway-full-width">
                    <a class="button" id="begateway_capture_partly" 
                       data-nonce="<?php echo wp_create_nonce( 'begateway' ); ?>" 
                       data-order-id="<?php esc_attr_e( $order_id ); ?>">
                       <?php _e( 'Capture specified amount', 'woocommerce-begateway' ); ?>
                    </a>
                </li>
                <li style='font-size: xx-small'>&nbsp;</li>
            <?php endif; ?>

        <?php $can_refund = $gateway->can_payment_method_capture( $order ); ?>
            <?php if ( $order_data['settled_amount'] > $order_data['refunded_amount'] && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled && $can_refund): ?>
                <li class="begateway-admin-section-li-header">
                    <?php _e( 'Partly refund', 'woocommerce-begateway' ); ?>
                </li>
                <li class="begateway-balance last">
                    <span class="begateway-balance__label" style='margin-right: 0;'>
                        <?php _e( 'Refund amount', 'woocommerce-begateway' ); ?>:
                    </span>
                    <span class="begateway-partly_refund_amount">
                        <input id="begateway-refund_partly_amount-field" 
                               class="begateway-refund_partly_amount-field" type="text" 
                               size="6" autocomplete="off" 
                               value="<?php esc_attr_e( $order_data['settled_amount'] - $order_data['refunded_amount'] ) ?>" />
                    </span>
                </li>
                <li class="begateway-full-width">
                    <a class="button" id="begateway_refund_partly" 
                       data-nonce="<?php echo wp_create_nonce( 'begateway' ); ?>" 
                       data-order-id="<?php esc_attr_e( $order_id ); ?>">
                       <?php _e( 'Refund specified amount', 'woocommerce-begateway' ); ?>
                    </a>
                </li>
                <li style='font-size: xx-small'>&nbsp;</li>
            <?php endif; ?>
        <?php endif; ?>    

        <li class="begateway-admin-section-li-header-small">
            <?php _e( 'Payment method', 'woocommerce-begateway' ) ?>
        </li>
        <li class="begateway-admin-section-li-small">
            <?php esc_html_e( ucfirst( $order->get_meta( '_begateway_transaction_payment_method', true ) ) ); ?>
        </li>
        <li class="begateway-admin-section-li-header-small">
            <?php _e( 'Transaction UID', 'woocommerce-begateway' ) ?>
        </li>
        <li class="begateway-admin-section-li-small">
            <?php esc_html_e( $order->get_meta( '_begateway_transaction_id', true ) ); ?>
        </li>
        <?php if ( null != $order->get_meta( '_begateway_card_last_4', true ) ): ?>
            <li class="begateway-admin-section-li-header-small">
                <?php _e( 'Card number', 'woocommerce-begateway' ); ?>
            </li>
            <li class="begateway-admin-section-li-small">
                <?php esc_html_e( 'xxxx ' . $order->get_meta( '_begateway_card_last_4', true ) ); ?>
            </li>
            <li class="begateway-admin-section-li-header-small">
                <?php _e( 'Card brand', 'woocommerce-begateway' ); ?>
            </li>
            <li class="begateway-admin-section-li-small">
                <?php esc_html_e( ucfirst( $order->get_meta( '_begateway_card_brand', true ) ) ); ?>
            </li>
        <?php endif ?>
    </ul>
<?php endif ?>
