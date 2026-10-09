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
require_once APP_PATH . '/helpers/payments.php';
require_once APP_PATH . '/helpers/notify.php';
require_once APP_PATH . '/helpers/guard.php';
require_once APP_PATH . '/helpers/audit.php';
require_once APP_PATH . '/helpers/margin.php';

$code = $params[0] ?? null;

// ============================================================== create ====
if ($code === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

    // $field names the input the message is about, where one owns it. The
    // browser then writes it under that input instead of in the banner at the
    // top, which is where the person is already looking.
    $fail = static function (string $message, ?string $field = null) use ($wantsJson) {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array_filter([
                'ok'    => false,
                'error' => $message,
                'field' => $field,
            ], static fn ($value) => $value !== null));
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

    // An order nobody can pay for is not an order. Taking one would leave a
    // customer holding a code and no way to finish, and the shop with a row
    // it can only cancel.
    if (!col('SELECT 1 FROM payment_methods WHERE is_active = 1', [], null)) {
        $fail('Ordering is closed at the moment - no payment method is switched on. '
            . 'Please try again shortly.');
    }

    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $packageId = (int) ($_POST['package_id'] ?? 0);
    $quantity  = (int) ($_POST['quantity'] ?? 0);
    $link      = trim((string) ($_POST['link'] ?? ''));
    $whatsapp  = trim((string) ($_POST['whatsapp'] ?? ''));
    $email     = trim((string) ($_POST['email'] ?? ''));

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

    // A package fixes both numbers, and both are read back from its own row -
    // the quantity the browser sent is not even looked at. Its price is a real
    // price, not a rate, which is how a bigger package can cost less per
    // thousand.
    $package = null;
    if ($packageId > 0) {
        $package = one(
            'SELECT * FROM service_packages WHERE id = ? AND service_id = ? AND is_active = 1',
            [$packageId, (int) $service['id']]
        );
        if (!$package) {
            $fail('That package is no longer available.');
        }
        $quantity = (int) $package['quantity'] + (int) $package['bonus_quantity'];
    }

    $min = (int) $service['min_qty'];
    $max = (int) $service['max_qty'];

    // The limits are the provider's, so a package still has to sit inside
    // them - an admin can type a quantity the provider will refuse.
    if ($quantity < $min) {
        $fail($package
            ? 'That package is below what this service accepts.'
            : 'The minimum for this service is ' . qty_fmt($min) . '.');
    }
    if ($quantity > $max) {
        $fail($package
            ? 'That package is above what this service accepts.'
            : 'The maximum for this service is ' . qty_fmt($max) . '.');
    }

    if ($link === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
        $fail('Please paste a full link, starting with https://', 'link');
    }
    if (!preg_match('~^https?://~i', $link)) {
        $fail('The link must start with http:// or https://', 'link');
    }
    if (mb_strlen($link) > 500) {
        $fail('That link is too long.', 'link');
    }

    // When the platform declares a host, the link has to be on it - this stops
    // an Instagram order being placed with a TikTok link.
    $prefix = trim((string) ($service['url_prefix'] ?? ''));
    if ($prefix !== '') {
        if (!link_is_on_host($link, $prefix)) {
            // The same words the browser uses, so the answer does not change
            // wording depending on whether the script ran.
            $fail('That link is not on ' . $prefix . '.', 'link');
        }
    }

    $digits = preg_replace('/\D/', '', $whatsapp);
    if (strlen($digits) < 10 || strlen($digits) > 15) {
        $fail('Please enter a valid WhatsApp number with the country code.', 'whatsapp');
    }

    // Optional: left blank it is simply not there, and the receipt is not sent.
    // Typed wrong it is a mistake worth pointing at rather than swallowing.
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fail('That email address does not look right. Leave it blank if you '
            . 'would rather not give one.', 'email');
    }

    // What the customer was shown, which is not what the provider calls it.
    // Stored rather than worked out later, so the order still reads the same
    // after the service is renamed or deleted.
    $label = trim((string) ($service['platform_name'] ?? '') . ' '
        . (string) col('SELECT name FROM categories WHERE id = ?', [$service['category_id']], ''));

    // The one number that matters, worked out here and nowhere else.
    $price = $package !== null
        ? round((float) $package['price'], 2)
        : round(((float) $service['price_per_1000'] / 1000) * $quantity, 2);

    // Cost is always the rate: we buy the delivered quantity either way.
    $cost = round(((float) $service['cost_per_1000'] / 1000) * $quantity, 2);

    // Before the order exists, not after. A package carries a price an admin
    // typed once and no sync ever revisits, so a provider raising its rate
    // means every package sells below cost until somebody notices - this is
    // where it gets noticed.
    $margin = margin_allows($price, $cost, [
        'service'    => $label !== '' ? $label : $service['name'],
        'service_id' => (int) $service['id'],
    ]);
    if (!$margin['ok']) {
        // The customer is not told the shop's cost. They are told the truth:
        // this one cannot be sold right now.
        $fail('That package is not available at the moment. Please pick another '
            . 'size, or try again shortly.');
    }

    [$orderId, $orderCode] = insert_order_code([
        'service_id'   => (int) $service['id'],
        'platform_id'  => $service['platform_id'],
        'service_name' => $service['name'],
        'service_label'=> $label !== '' ? $label : $service['name'],
        'quantity'     => $quantity,
        'link'         => $link,
        'whatsapp'     => '+' . $digits,
        'email'        => $email,
        'price'        => $price,
        'cost'         => $cost,
        'status'       => 'pending',
        'ip'           => client_ip(),
        'created_at'   => date('Y-m-d H:i:s'),
    ]);

    order_log($orderId, 'Order placed by the customer.');
    audit('order.placed', [
        'entity' => 'orders', 'entity_id' => $orderId,
        'summary' => $orderCode . ': ' . qty_fmt($quantity) . ' x '
                   . ($label !== '' ? $label : $service['name'])
                   . ' at ' . money($price) . ', cost ' . money($cost)
                   . ' (' . $margin['margin']['percent'] . '%)',
        'after'  => ['price' => $price, 'cost' => $cost, 'quantity' => $quantity],
    ]);

    $tokens = [
        'order_code' => $orderCode,
        'service'    => $label !== '' ? $label : $service['name'],
        'quantity'   => qty_fmt($quantity),
        'amount'     => money($price),
        'link'       => $link,
        'status'     => 'Awaiting payment',
        'order_url'  => url('order/' . $orderCode),
        'admin_url'  => url('admin/orders/view/' . $orderId),
        'admin_link' => 'admin/orders/view/' . $orderId,
        'email'      => $email,
    ];
    notify('admin_new_order', $tokens);
    if ($email !== '') {
        notify('order_placed', $tokens);
    }

    if ($wantsJson) {
        // The customer stays where they are and watches the order appear,
        // then goes straight to the gateway when there is one to go to.
        // Everything the box shows comes from here, not from what the
        // browser thought it was ordering.
        $sole = sole_redirect_method();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'       => true,
            'code'     => $orderCode,
            'amount'   => money($price),
            'quantity' => qty_fmt($quantity),
            'status'   => 'Awaiting payment',
            'payment'  => $sole
                ? ['mode' => 'redirect', 'name' => $sole['name'],
                   'start' => url('order/' . $orderCode . '/start'),
                   'method' => (int) $sole['id'], 'token' => csrf_token()]
                : ['mode' => 'page'],
            'redirect' => url('order/' . $orderCode),
        ]);
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

    // The gateway decides what counts as a valid submission. The manual one
    // just wants a transaction id; a hosted one may check for real.
    $check = payment_verify($method, $order, ['trx_id' => $trx, 'paid_amount' => $amount]);

    if (!$check['ok']) {
        flash('error', $check['message'] !== '' ? $check['message'] : 'That payment could not be accepted.');
        redirect('order/' . $order['code']);
    }

    $reference = mb_substr((string) $check['reference'], 0, 120);

    $fields = [
        'payment_method_id' => (int) $method['id'],
        'trx_id'            => $reference,
        'paid_amount'       => $amount > 0 ? $amount : null,
        'updated_at'        => date('Y-m-d H:i:s'),
    ];

    // A gateway that really verified the money can mark the order paid itself.
    if (!empty($check['confirmed'])) {
        $fields['status']  = 'paid';
        $fields['paid_at'] = date('Y-m-d H:i:s');
    }

    // Only while it is still waiting. A customer who submits the form twice -
    // or leaves it open and comes back after an admin has confirmed - must not
    // reopen a paid order or overwrite the reference that was accepted.
    $recorded = claim('orders', $fields,
        "id = ? AND status IN ('pending', 'api_error')", [$order['id']]);

    if (!$recorded) {
        flash('success', 'We already have your payment for this order.');
        redirect('order/' . $order['code']);
    }

    order_log((int) $order['id'], 'Customer submitted payment via ' . $method['name']
        . ($reference !== '' ? ' - reference ' . $reference : '') . '.');
    audit('order.payment_submitted', [
        'entity' => 'orders', 'entity_id' => (int) $order['id'], 'severity' => 'warn',
        'summary' => $order['code'] . ': ' . money($amount > 0 ? $amount : (float) $order['price'])
                   . ' via ' . $method['name']
                   . ($reference !== '' ? ', reference ' . $reference : ''),
        'after'   => ['status' => $fields['status'] ?? $order['status'], 'trx_id' => $reference],
    ]);

    notify('admin_payment_submitted', [
        'order_code' => $order['code'],
        'amount'     => money($amount > 0 ? $amount : (float) $order['price']),
        'method'     => $method['name'],
        'reference'  => $reference !== '' ? $reference : 'none given',
        'admin_url'  => url('admin/orders/view/' . $order['id']),
        'admin_link' => 'admin/orders/view/' . $order['id'],
    ]);

    if (!empty($check['confirmed'])) {
        order_log((int) $order['id'], 'Payment confirmed by ' . $method['name'] . '.');
        if (setting('auto_send_orders', '1') === '1') {
            send_order_to_provider((int) $order['id']);
        }
        flash('success', 'Payment confirmed. Your order is on its way.');
    } else {
        flash('success', 'Thank you. We are confirming your payment now - this usually takes a few minutes.');
    }

    redirect('order/' . $order['code']);
}

// --- hand off to a hosted gateway -------------------------------------------
if (($params[1] ?? '') === 'start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $asJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    $answer = static function (array $payload, string $flash = '') use ($asJson, $order): never {
        if ($asJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload);
            exit;
        }
        if ($flash !== '') {
            flash('error', $flash);
        }
        redirect(!empty($payload['redirect']) && empty($payload['ok'])
            ? 'order/' . $order['code']
            : (string) ($payload['redirect'] ?? 'order/' . $order['code']));
    };

    $method = one('SELECT * FROM payment_methods WHERE id = ? AND is_active = 1',
        [(int) ($_POST['payment_method_id'] ?? 0)]);

    if (!$method) {
        $answer(['ok' => false, 'error' => 'Please choose a payment method.'],
            'Please choose a payment method.');
    }

    $start = payment_start($method, $order);

    if (!empty($start['error'])) {
        $answer(['ok' => false, 'error' => (string) $start['error']], (string) $start['error']);
    }

    if (!empty($start['redirect'])) {
        update_row('orders', ['payment_method_id' => (int) $method['id']], 'id = ?', [$order['id']]);
        order_log((int) $order['id'], 'Sent to ' . $method['name'] . ' to pay.');
        $answer(['ok' => true, 'redirect' => (string) $start['redirect']]);
    }

    // Nothing to redirect to: the order page takes a reference as usual.
    $answer(['ok' => true, 'redirect' => url('order/' . $order['code'])]);
}

// ---------------------------------------------------------------- view ----
view('order', [
    'title'       => 'Order ' . $order['code'],
    'meta_robots' => 'noindex, nofollow',
    'order'       => $order,
    // A hosted gateway has no account number to show, so the old
    // "must have an account number" filter would have hidden it.
    'methods'     => array_values(array_filter(
        all('SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id'),
        static function (array $m): bool {
            $gateway = payment_gateway((string) $m['driver']);
            return $gateway !== null
                && ($gateway['kind'] !== 'manual' || trim((string) $m['account_number']) !== '');
        }
    )),
    'gateways'    => payment_gateways(),
]);
