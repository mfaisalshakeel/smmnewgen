<?php
/** Track an order by its code. The page works with or without JavaScript. */

$code   = strtoupper(trim((string) ($_GET['code'] ?? $_POST['code'] ?? $params[0] ?? '')));
$order  = null;
$error  = null;

if ($code !== '') {
    if (!rate_limit('track_order', 30, 600)) {
        $error = 'Too many lookups. Please wait a few minutes.';
    } else {
        $order = one(
            'SELECT o.*, p.name AS platform_name
               FROM orders o
               LEFT JOIN platforms p ON p.id = o.platform_id
              WHERE o.code = ?',
            [$code]
        );
        if (!$order) {
            $error = 'We could not find an order with that code. Check it and try again.';
        }
    }
}

view('track', [
    'title'            => 'Track your order',
    'meta_description' => 'Check the status of your order with the code we sent you.',
    'meta_robots'      => 'noindex, follow',
    'code'             => $code,
    'order'            => $order,
    'error'            => $error,
]);
