<?php
/**
 * Placing an order and paying for it.
 *
 *   POST /order              create an order (JSON for fetch, redirect without JS)
 *   GET  /order/{CODE}       summary, payment details and live status
 *   POST /order/{CODE}/pay   submit the transaction id
 *
 * Nothing the browser sends about money is trusted: the price is recalculated
 * from the service row every time.
 */
require_once APP_PATH . '/helpers/orders.php';

$code = $params[0] ?? null;

// ============================================================== create ====
if ($code === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

    $fail = static function (string $message) use ($wantsJson) {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $message]);
            exit;
        }
        flash('error', $message);
        redirect('');
    };

    // A bot filling the hidden field looks exactly like success to the bot.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        $fail('Something went wrong. Please try again.');
    }

    if (!rate_limit('place_order', 10, 600)) {
        $fail('That is a lot of orders in a short time. Please wait a few minutes.');
    }

    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $quantity  = (int) ($_POST['quantity'] ?? 0);
    $link      = trim((string) ($_POST['link'] ?? ''));
    $whatsapp  = trim((string) ($_POST['whatsapp'] ?? ''));

    $service = one(
        'SELECT s.*, p.name AS platform_name, p.url_prefix, p.is_active AS platform_active
           FROM services s
           LEFT JOIN platforms p ON p.id = s.platform_id
          WHERE s.id = ? AND s.is_active = 1',
        [$serviceId]
    );

    if (!$service) {
        $fail('That service is no longer available.');
    }
    if ($service['platform_id'] !== null && !$service['platform_active']) {
        $fail('That service is no longer available.');
    }

    $min = (int) $service['min_qty'];
    $max = (int) $service['max_qty'];

    if ($quantity < $min) {
        $fail('The minimum for this service is ' . qty_fmt($min) . '.');
    }
    if ($quantity > $max) {
        $fail('The maximum for this service is ' . qty_fmt($max) . '.');
    }

    if ($link === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
        $fail('Please paste a full link, starting with https://');
    }
    if (!preg_match('~^https?://~i', $link)) {
        $fail('The link must start with http:// or https://');
    }
    if (mb_strlen($link) > 500) {
        $fail('That link is too long.');
    }

    // When the platform declares a host, the link has to be on it - this stops
    // an Instagram order being placed with a TikTok link.
    $prefix = trim((string) ($service['url_prefix'] ?? ''));
    if ($prefix !== '') {
        $host = strtolower((string) parse_url($link, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        if ($host === '' || !str_contains($host, strtolower($prefix))) {
            $fail('That does not look like a link to ' . $service['platform_name']
                . '. It should be on ' . $prefix . '.');
        }
    }

    $digits = preg_replace('/\D/', '', $whatsapp);
    if (strlen($digits) < 10 || strlen($digits) > 15) {
        $fail('Please enter a valid WhatsApp number with the country code.');
    }

    // The one number that matters, worked out here and nowhere else.
    $price = round(((float) $service['price_per_1000'] / 1000) * $quantity, 2);
    $cost  = round(((float) $service['cost_per_1000'] / 1000) * $quantity, 2);

    $orderCode = new_order_code();
    $orderId   = insert_row('orders', [
        'code'         => $orderCode,
        'service_id'   => (int) $service['id'],
        'platform_id'  => $service['platform_id'],
        'service_name' => $service['name'],
        'quantity'     => $quantity,
        'link'         => $link,
        'whatsapp'     => '+' . $digits,
        'price'        => $price,
        'cost'         => $cost,
        'status'       => 'pending',
        'ip'           => client_ip(),
        'created_at'   => date('Y-m-d H:i:s'),
    ]);

    order_log($orderId, 'Order placed by the customer.');

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'code' => $orderCode, 'redirect' => url('order/' . $orderCode)]);
        exit;
    }
    redirect('order/' . $orderCode);
}

if ($code === null) {
    require CONTROLLER_PATH . '/_404.php';
    return;
}

// ============================================================ one order ====
// The code is the only thing guarding this page, and the page shows the
// customer's link and WhatsApp number. A real customer reloads a handful of
// times; this stops anyone walking the code space looking for them.
if (!rate_limit('view_order', 60, 600)) {
    http_response_code(429);
    view('_404', ['title' => 'Too many requests', 'meta_robots' => 'noindex, nofollow']);
}

$order = one(
    'SELECT o.*, p.name AS platform_name, pm.name AS payment_name
       FROM orders o
       LEFT JOIN platforms p        ON p.id  = o.platform_id
       LEFT JOIN payment_methods pm ON pm.id = o.payment_method_id
      WHERE o.code = ?',
    [strtoupper($code)]
);

if (!$order) {
    require CONTROLLER_PATH . '/_404.php';
    return;
}

// ------------------------------------------------------------- payment ----
if (($params[1] ?? '') === 'pay' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (!rate_limit('submit_payment', 12, 600)) {
        flash('error', 'Too many attempts. Please wait a few minutes.');
        redirect('order/' . $order['code']);
    }

    if (!in_array($order['status'], ['pending', 'api_error'], true)) {
        flash('warning', 'This order is already being processed.');
        redirect('order/' . $order['code']);
    }

    $methodId = (int) ($_POST['payment_method_id'] ?? 0);
    $trx      = trim((string) ($_POST['trx_id'] ?? ''));
    $amount   = (float) str_replace(',', '', (string) ($_POST['paid_amount'] ?? 0));

    $method = one('SELECT * FROM payment_methods WHERE id = ? AND is_active = 1', [$methodId]);
    if (!$method) {
        flash('error', 'Please choose a payment method.');
        redirect('order/' . $order['code']);
    }

    if (setting('require_trx_id', '1') === '1' && strlen($trx) < 4) {
        flash('error', 'Please enter the transaction id from your payment receipt.');
        redirect('order/' . $order['code']);
    }
    if (mb_strlen($trx) > 120) {
        flash('error', 'That transaction id is too long.');
        redirect('order/' . $order['code']);
    }

    update_row('orders', [
        'payment_method_id' => (int) $method['id'],
        'trx_id'            => $trx,
        'paid_amount'       => $amount > 0 ? $amount : null,
        'updated_at'        => date('Y-m-d H:i:s'),
    ], 'id = ?', [$order['id']]);

    order_log((int) $order['id'],
        'Customer submitted payment via ' . $method['name'] . ($trx !== '' ? ' - TRX ' . $trx : '') . '.');

    flash('success', 'Thank you. We are confirming your payment now - this usually takes a few minutes.');
    redirect('order/' . $order['code']);
}

// ---------------------------------------------------------------- view ----
view('order', [
    'title'       => 'Order ' . $order['code'],
    'meta_robots' => 'noindex, nofollow',
    'order'       => $order,
    'methods'     => all('SELECT * FROM payment_methods
                           WHERE is_active = 1 AND account_number <> ""
                        ORDER BY sort_order, id'),
]);
