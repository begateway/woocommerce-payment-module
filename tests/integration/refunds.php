<?php
/**
 * Offline regression tests using real WordPress/WooCommerce order storage.
 * Run only against a disposable local installation:
 * BEGATEWAY_RUN_REFUND_TESTS=1 wp eval-file /path/to/repo/tests/integration/refunds.php
 * No gateway requests are sent. Test orders are removed after each case.
 */
if (getenv('BEGATEWAY_RUN_REFUND_TESTS') !== '1') {
    throw new RuntimeException('Set BEGATEWAY_RUN_REFUND_TESTS=1 on a disposable local installation.');
}
if (!defined('ABSPATH') || !class_exists('WC_Gateway_BeGateway')) {
    throw new RuntimeException('Load WordPress, WooCommerce and BeGateway first.');
}

class BeGateway_Refund_Test_Gateway extends WC_Gateway_BeGateway
{
    public $requests = array();
    public $fail = false;

    public function child_transaction($type, $uid, $order_id, $amount, $reason = '')
    {
        $this->requests[] = compact('type', 'uid', 'order_id', 'amount', 'reason');
        return new \BeGateway\Response(wp_json_encode(array('transaction' => array(
            'uid' => 'refund-test-' . count($this->requests),
            'status' => $this->fail ? 'failed' : 'successful',
            'message' => $this->fail ? 'Refund declined by test gateway' : 'Successfully processed',
        ))));
    }
}

function begateway_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function begateway_test_order()
{
    $order = wc_create_order();
    $order->set_payment_method('begateway');
    $order->set_currency('USD');
    $order->set_total(10);
    $order->set_status('processing');
    $order->set_transaction_id('payment-test');
    $order->update_meta_data('_begateway_transaction_id', 'payment-test');
    $order->update_meta_data('_begateway_transaction_captured', 'yes');
    $order->update_meta_data('_begateway_transaction_captured_amount', 10);
    $order->update_meta_data('_begateway_transaction_payment_method', 'credit_card');
    $order->save();
    return $order;
}

function begateway_test_refund($order, $amount, $reason = '')
{
    return wc_create_refund(array(
        'order_id' => $order->get_id(), 'amount' => $amount,
        'reason' => $reason, 'refund_payment' => true, 'restock_items' => false,
    ));
}

$gateways = WC()->payment_gateways();
$original = $gateways->payment_gateways;
$failures = 0;
$disable_mail = static function () { return true; };
add_filter('pre_wp_mail', $disable_mail);
$tests = array(
    'native full refund reaches gateway and records one WC refund' => function ($gateway, $order) {
        $refund = begateway_test_refund($order, 10, 'Customer request');
        begateway_assert(!is_wp_error($refund), is_wp_error($refund) ? $refund->get_error_message() : '');
        begateway_assert(count($gateway->requests) === 1, 'Expected exactly one gateway request');
        begateway_assert($gateway->requests[0]['reason'] === 'Customer request', 'Refund reason was lost');
        $fresh = wc_get_order($order->get_id());
        begateway_assert(count($fresh->get_refunds()) === 1, 'Duplicate/missing WooCommerce refund');
        begateway_assert((float) $fresh->get_total_refunded() === 10.0, 'Incorrect WooCommerce refunded total');
        begateway_assert($fresh->get_meta('_begateway_transaction_refunded') === 'yes', 'Missing full refund flag');
        begateway_assert($fresh->get_meta('_begateway_transaction_id') === 'payment-test', 'Payment parent UID changed');
        begateway_assert(is_wp_error($gateway->process_refund($order->get_id(), 1)), 'Repeat refund accepted');
        begateway_assert(count($gateway->requests) === 1, 'Repeat refund reached API');
    },
    'native partial then remaining refund keeps original parent UID' => function ($gateway, $order) {
        foreach (array(3.25, 6.75) as $amount) {
            $refund = begateway_test_refund($order, $amount);
            begateway_assert(!is_wp_error($refund), is_wp_error($refund) ? $refund->get_error_message() : '');
        }
        begateway_assert(count($gateway->requests) === 2, 'Expected two requests');
        begateway_assert($gateway->requests[0]['reason'] !== '', 'Default refund reason missing');
        begateway_assert($gateway->requests[1]['uid'] === 'payment-test', 'Refund used wrong parent UID');
        $fresh = wc_get_order($order->get_id());
        begateway_assert((float) $fresh->get_meta('_begateway_transaction_refunded_amount') === 10.0, 'Gateway total incorrect');
        begateway_assert((float) $fresh->get_total_refunded() === 10.0, 'WC total incorrect');
    },
    'gateway failure rolls back WC refund and leaves gateway totals intact' => function ($gateway, $order) {
        $gateway->fail = true;
        $refund = begateway_test_refund($order, 4);
        begateway_assert(is_wp_error($refund), 'Expected WP_Error');
        begateway_assert(strpos($refund->get_error_message(), 'Refund declined by test gateway') !== false, 'Lost gateway error detail');
        $fresh = wc_get_order($order->get_id());
        begateway_assert((float) $fresh->get_total_refunded() === 0.0, 'Failed WC refund was retained');
        begateway_assert((float) $fresh->get_meta('_begateway_transaction_refunded_amount') === 0.0, 'Failed refund modified metadata');
    },
    'missing transaction UID returns WP_Error without API request' => function ($gateway, $order) {
        $order->delete_meta_data('_begateway_transaction_id');
        $order->save();
        begateway_assert(is_wp_error($gateway->process_refund($order->get_id(), 1)), 'Expected WP_Error');
        begateway_assert(!$gateway->requests, 'Unexpected API request');
    },
    'ERIP refund rejected without API request' => function ($gateway, $order) {
        $order->update_meta_data('_begateway_transaction_payment_method', 'erip');
        $order->save();
        begateway_assert(is_wp_error($gateway->process_refund($order->get_id(), 1)), 'Expected WP_Error');
        begateway_assert(!$gateway->requests, 'Unexpected ERIP API request');
    },
    'wrong gateway rejected without API request' => function ($gateway, $order) {
        $order->set_payment_method('cod');
        $order->save();
        begateway_assert(is_wp_error($gateway->process_refund($order->get_id(), 1)), 'Expected WP_Error');
        begateway_assert(!$gateway->requests, 'Unexpected API request');
    },
    'uncaptured authorization rejected without API request' => function ($gateway, $order) {
        $order->update_meta_data('_begateway_transaction_captured', 'no');
        $order->update_meta_data('_begateway_transaction_captured_amount', 0);
        $order->save();
        begateway_assert(is_wp_error($gateway->process_refund($order->get_id(), 1)), 'Expected WP_Error');
        begateway_assert(!$gateway->requests, 'Unexpected API request');
    },
    'voided transaction rejected without API request' => function ($gateway, $order) {
        $order->update_meta_data('_begateway_transaction_voided', 'yes');
        $order->save();
        begateway_assert(is_wp_error($gateway->process_refund($order->get_id(), 1)), 'Expected WP_Error');
        begateway_assert(!$gateway->requests, 'Unexpected API request');
    },
    'invalid order returns WP_Error' => function ($gateway, $order) {
        begateway_assert(is_wp_error($gateway->process_refund(0, 1)), 'Expected WP_Error');
        begateway_assert(!$gateway->requests, 'Unexpected API request');
    },
    'invalid amounts are rejected before API call' => function ($gateway, $order) {
        foreach (array(null, 0, -1, 11, 'invalid', INF, NAN) as $amount) {
            begateway_assert(is_wp_error($gateway->process_refund($order->get_id(), $amount)), 'Invalid amount accepted: ' . var_export($amount, true));
        }
        begateway_assert(!$gateway->requests, 'Unexpected API request');
    },
    'partial capture refund is bounded by captured amount' => function ($gateway, $order) {
        $order->update_meta_data('_begateway_transaction_captured_amount', 6);
        $order->save();
        begateway_assert(is_wp_error($gateway->process_refund($order->get_id(), 7)), 'Over-refund accepted');
        begateway_assert(!$gateway->requests, 'Unexpected API request');
        begateway_assert($gateway->process_refund($order->get_id(), 6) === true, 'Captured amount refund failed');
        begateway_assert(wc_get_order($order->get_id())->get_meta('_begateway_transaction_refunded') === 'yes', 'Partial capture not fully refunded');
    },
    'custom panel and native refund share remaining gateway balance' => function ($gateway, $order) {
        begateway_assert($gateway->refund_payment($order->get_id(), 4) === true, 'Panel refund failed');
        begateway_assert(is_wp_error($gateway->process_refund($order->get_id(), 7)), 'Mixed paths allowed over-refund');
        begateway_assert($gateway->process_refund($order->get_id(), 6) === true, 'Remainder failed');
        begateway_assert(count($gateway->requests) === 2, 'Unexpected request count');
    },
);

try {
    foreach ($tests as $name => $test) {
        $gateway = new BeGateway_Refund_Test_Gateway();
        $gateways->payment_gateways['begateway'] = $gateway;
        $order = begateway_test_order();
        try {
            $test($gateway, $order);
            echo "PASS: $name\n";
        } catch (Throwable $e) {
            ++$failures;
            echo "FAIL: $name: {$e->getMessage()}\n";
        } finally {
            $fresh = wc_get_order($order->get_id());
            foreach ($fresh->get_refunds() as $refund) {
                $refund->delete(true);
            }
            $fresh->delete(true);
        }
    }
} finally {
    $gateways->payment_gateways = $original;
    remove_filter('pre_wp_mail', $disable_mail);
}
if ($failures) {
    throw new RuntimeException("$failures refund regression test(s) failed.");
}
echo count($tests) . " refund regression tests passed.\n";
