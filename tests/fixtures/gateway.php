<?php
/** Loopback-only SDK transport fixture; started by child-transactions.php. */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_SERVER['REQUEST_URI'] !== '/transactions/refunds') {
    http_response_code(404);
    exit;
}

header('Content-Type: application/json');
echo json_encode(array('transaction' => array(
    'uid' => 'loopback-refund',
    'status' => 'successful',
    'message' => 'Loopback fixture received request',
    'received_request' => json_decode(file_get_contents('php://input'), true),
)));
