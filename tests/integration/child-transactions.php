<?php
/**
 * SDK transport regression tests using disposable WordPress/WooCommerce orders.
 * Run: BEGATEWAY_RUN_REFUND_TESTS=1 wp eval-file /path/to/repo/tests/integration/child-transactions.php
 * Starts its own loopback HTTP fixture and removes it and test orders afterward.
 * No external requests or persistent gateway/store configuration changes occur.
 */
if (getenv('BEGATEWAY_RUN_REFUND_TESTS') !== '1') {
    throw new RuntimeException('Set BEGATEWAY_RUN_REFUND_TESTS=1 on a disposable local installation.');
}
if (!defined('ABSPATH') || !class_exists('WC_Gateway_BeGateway')) {
    throw new RuntimeException('Load WordPress, WooCommerce and BeGateway first.');
}

class BeGateway_Child_Transaction_Test_Gateway extends WC_Gateway_BeGateway
{
    public $loopback_url;

    protected function _init()
    {
        // Retain the real child_transaction() and SDK HTTP transport. Replace
        // only configuration so this test cannot reach an external gateway.
        \BeGateway\Settings::$gatewayBase = $this->loopback_url;
        \BeGateway\Settings::$shopId = 'loopback-test';
        \BeGateway\Settings::$shopKey = 'not-a-real-secret';
    }
}

$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$listener) {
    throw new RuntimeException('Cannot reserve loopback port: ' . $error);
}
$address = stream_socket_get_name($listener, false);
fclose($listener);

$log = tempnam(sys_get_temp_dir(), 'begateway-transport-');
$server = null;
$orders = array();
$old_base = \BeGateway\Settings::$gatewayBase;
$old_shop = \BeGateway\Settings::$shopId;
$old_key = \BeGateway\Settings::$shopKey;
$store_currency = function () { return 'USD'; };

try {
    $server = proc_open(
        escapeshellarg(PHP_BINARY) . ' -n -S ' . escapeshellarg($address) . ' ' .
        escapeshellarg(dirname(__DIR__) . '/fixtures/gateway.php'),
        array(0 => array('pipe', 'r'), 1 => array('file', $log, 'a'), 2 => array('file', $log, 'a')),
        $pipes
    );
    if (!is_resource($server)) {
        throw new RuntimeException('Cannot start loopback gateway fixture.');
    }
    fclose($pipes[0]);

    $ready = false;
    for ($attempt = 0; $attempt < 50; ++$attempt) {
        $socket = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($socket) {
            fclose($socket);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) {
        throw new RuntimeException('Loopback fixture did not start: ' . file_get_contents($log));
    }

    add_filter('woocommerce_currency', $store_currency);
    $gateway = new BeGateway_Child_Transaction_Test_Gateway();
    $gateway->loopback_url = 'http://' . $address;

    foreach (array(array('JPY', 10, 10), array('KWD', '1.234', 1234)) as $case) {
        list($currency, $amount, $expected_minor_units) = $case;
        $order = wc_create_order();
        $orders[] = $order;
        $order->set_payment_method('begateway');
        $order->set_currency($currency);
        $order->set_total($amount);
        $order->save();

        $response = $gateway->child_transaction('refund', 'original-payment-uid', $order->get_id(), $amount, 'Customer request');
        if (!$response->isSuccess()) {
            throw new RuntimeException('SDK transport failed: ' . $response->getMessage());
        }
        $received = $response->getResponseArray()['transaction']['received_request']['request'];
        if ($received['amount'] !== $expected_minor_units ||
            $received['parent_uid'] !== 'original-payment-uid' ||
            $received['reason'] !== 'Customer request') {
            throw new RuntimeException('Incorrect SDK refund payload for ' . $currency . ': ' . wp_json_encode($received));
        }
        if (get_woocommerce_currency() !== 'USD') {
            throw new RuntimeException('Test requires a different store currency (USD).');
        }
        echo 'PASS: ' . $currency . ' ' . $amount . ' -> ' . $expected_minor_units . " minor units; parent and reason preserved\n";
    }
} finally {
    remove_filter('woocommerce_currency', $store_currency);
    \BeGateway\Settings::$gatewayBase = $old_base;
    \BeGateway\Settings::$shopId = $old_shop;
    \BeGateway\Settings::$shopKey = $old_key;
    foreach ($orders as $order) {
        $order->delete(true);
    }
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    unlink($log);
}
echo "2 SDK child-transaction transport tests passed.\n";
