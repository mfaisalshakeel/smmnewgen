<?php
/**
 * Small JSON endpoints.
 *
 *   GET /api/track?code=GK-8F42KD
 *
 * Returns only what the customer already knows from their own order page, so
 * there is nothing here that a code holder cannot already see.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$action = $params[0] ?? '';

if ($action !== 'track') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Unknown endpoint']);
    exit;
}

$code = strtoupper(trim((string) ($_GET['code'] ?? $_POST['code'] ?? '')));

if ($code === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No order code given']);
    exit;
}

if (!rate_limit('api_track', 60, 600)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many lookups, please slow down']);
    exit;
}

$order = one('SELECT code, service_name, quantity, status, start_count, remains, created_at
                FROM orders WHERE code = ?', [$code]);

if (!$order) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Order not found']);
    exit;
}

$delivered = $order['remains'] !== null
    ? max(0, (int) $order['quantity'] - (int) $order['remains'])
    : ($order['status'] === 'completed' ? (int) $order['quantity'] : 0);

echo json_encode([
    'ok' => true,
    'order' => [
        'code'        => $order['code'],
        'service'     => $order['service_name'],
        'quantity'    => (int) $order['quantity'],
        'status'      => $order['status'],
        'start_count' => $order['start_count'] === null ? null : (int) $order['start_count'],
        'delivered'   => $delivered,
        'remains'     => $order['remains'] === null ? (int) $order['quantity'] : (int) $order['remains'],
        'placed_at'   => $order['created_at'],
    ],
]);
