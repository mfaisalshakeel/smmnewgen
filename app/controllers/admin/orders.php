<?php
/** Orders: the list, one order's detail, and every action an admin can take. */
require_once APP_PATH . '/helpers/orders.php';
require_once APP_PATH . '/helpers/crud.php';   // options_from()
require_once APP_PATH . '/helpers/guard.php';
require_once APP_PATH . '/helpers/audit.php';

$action = $params[0] ?? 'index';

// --------------------------------------------------------------- actions --
if ($action === 'action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int) ($_POST['id'] ?? 0);
    $what  = (string) ($_POST['do'] ?? '');
    $order = one('SELECT * FROM orders WHERE id = ?', [$id]);

    if (!$order) {
        flash('error', 'That order no longer exists.');
        redirect('admin/orders');
    }

    switch ($what) {
        case 'mark_paid':
            // Conditional on the status it is moving away from, so two admins
            // on the same order cannot both mark it paid and both set off a
            // send. The loser is told, not silently ignored.
            $tookIt = claim('orders', [
                'status'      => 'paid',
                'paid_at'     => $order['paid_at'] ?: date('Y-m-d H:i:s'),
                'paid_amount' => $order['paid_amount'] ?? $order['price'],
                'updated_at'  => date('Y-m-d H:i:s'),
            ], "id = ? AND status <> 'paid'", [$id]);

            if (!$tookIt) {
                flash('error', 'That order is already paid - somebody got there first.');
                redirect('admin/orders/view/' . $id);
            }

            order_log($id, 'Payment confirmed by admin.');
            audit_row('order.paid', 'orders', $id, $order,
                ['summary' => $order['code'] . ' marked paid by hand', 'severity' => 'warn']);
            flash('success', 'Marked as paid.');

            if (($order['email'] ?? '') !== '') {
                notify('order_paid', [
                    'order_code' => $order['code'],
                    'service'    => $order['service_label'] ?: $order['service_name'],
                    'quantity'   => qty_fmt((int) $order['quantity']),
                    'amount'     => money((float) $order['price']),
                    'order_url'  => url('order/' . $order['code']),
                    'email'      => $order['email'],
                ]);
            }

            if (setting('auto_send_orders', '1') === '1') {
                [$ok, $message] = send_order_to_provider($id);
                flash($ok ? 'success' : 'error', $message);
            }
            break;

        case 'send':
            [$ok, $message] = send_order_to_provider($id);
            flash($ok ? 'success' : 'error', $message);
            break;

        case 'sync':
            if ($order['provider_order_id'] === '') {
                flash('error', 'This order has not been sent to a provider yet.');
                break;
            }
            $summary = sync_order_statuses(1000);
            flash('success', 'Checked ' . $summary['checked'] . ' open order(s), '
                . $summary['updated'] . ' changed status.');
            break;

        case 'refill':
            if ($order['provider_order_id'] === '' || !$order['provider_id']) {
                flash('error', 'Nothing to refill - this order was never sent to a provider.');
                break;
            }
            $provider = one('SELECT * FROM providers WHERE id = ?', [$order['provider_id']]);
            $result   = (new SmmApi($provider['api_url'], $provider['api_key']))
                            ->refill($order['provider_order_id']);
            if (isset($result['error'])) {
                flash('error', 'Refill refused: ' . $result['error']);
                order_log($id, 'Refill refused: ' . $result['error']);
            } else {
                flash('success', 'Refill requested.');
                order_log($id, 'Refill requested at the provider.');
            }
            break;

        case 'cancel':
            if ($order['provider_order_id'] !== '' && $order['provider_id']) {
                $provider = one('SELECT * FROM providers WHERE id = ?', [$order['provider_id']]);
                $result   = (new SmmApi($provider['api_url'], $provider['api_key']))
                                ->cancel([$order['provider_order_id']]);
                if (isset($result['error'])) {
                    flash('warning', 'Provider could not cancel it: ' . $result['error']
                        . ' The order was still marked cancelled here.');
                    order_log($id, 'Provider cancel failed: ' . $result['error']);
                } else {
                    order_log($id, 'Cancelled at the provider.');
                }
            }
            update_row('orders', ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?', [$id]);
            order_log($id, 'Order cancelled by admin.');
            flash('success', 'Order cancelled.');
            break;

        case 'complete':
            update_row('orders', [
                'status'     => 'completed',
                'remains'    => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
            order_log($id, 'Marked completed by admin.');
            flash('success', 'Marked as completed.');
            break;

        case 'refund':
            update_row('orders', ['status' => 'refunded', 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?', [$id]);
            order_log($id, 'Marked refunded by admin.');
            flash('success', 'Marked as refunded. Send the money back yourself.');
            break;

        case 'note':
            $note = trim((string) ($_POST['note'] ?? ''));
            if ($note === '') {
                flash('error', 'The note was empty.');
                break;
            }
            order_log($id, 'Note: ' . $note);
            flash('success', 'Note added.');
            break;

        default:
            flash('error', 'Unknown action.');
    }

    redirect('admin/orders/' . $id);
}

// ---------------------------------------------------------------- detail --
if (ctype_digit((string) $action)) {
    $id    = (int) $action;
    $order = one(
        'SELECT o.*, p.name AS platform_name, pr.name AS provider_name, pm.name AS payment_name
           FROM orders o
           LEFT JOIN platforms p        ON p.id  = o.platform_id
           LEFT JOIN providers pr       ON pr.id = o.provider_id
           LEFT JOIN payment_methods pm ON pm.id = o.payment_method_id
          WHERE o.id = ?',
        [$id]
    );

    if (!$order) {
        flash('error', 'That order no longer exists.');
        redirect('admin/orders');
    }

    view('admin/order', [
        'title'    => 'Order ' . $order['code'],
        'subtitle' => 'Orders',
        'order'    => $order,
        'service'  => $order['service_id'] ? one('SELECT * FROM services WHERE id = ?', [$order['service_id']]) : null,
        'logs'     => all('SELECT * FROM order_logs WHERE order_id = ? ORDER BY created_at DESC, id DESC', [$id]),
    ], 'layouts/admin');
}

// ------------------------------------------------------------------ list --
$filters = [
    'q'           => trim((string) ($_GET['q'] ?? '')),
    'status'      => (string) ($_GET['status'] ?? ''),
    'platform_id' => (string) ($_GET['platform_id'] ?? ''),
    'date'        => (string) ($_GET['date'] ?? ''),
];

$where = [];
$binds = [];

if ($filters['q'] !== '') {
    $where[] = '(o.code LIKE ? OR o.link LIKE ? OR o.whatsapp LIKE ? OR o.trx_id LIKE ?)';
    array_push($binds, '%' . $filters['q'] . '%', '%' . $filters['q'] . '%',
                       '%' . $filters['q'] . '%', '%' . $filters['q'] . '%');
}
if ($filters['status'] !== '') {
    $where[] = 'o.status = ?';
    $binds[] = $filters['status'];
}
if ($filters['platform_id'] !== '') {
    $where[] = 'o.platform_id = ?';
    $binds[] = $filters['platform_id'];
}
if ($filters['date'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date'])) {
    $where[] = 'DATE(o.created_at) = ?';
    $binds[] = $filters['date'];
}

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total    = (int) col("SELECT COUNT(*) FROM orders o{$whereSql}", $binds, 0);

$orders = all(
    "SELECT o.*, p.name AS platform_name
       FROM orders o
       LEFT JOIN platforms p ON p.id = o.platform_id
       {$whereSql}
   ORDER BY o.created_at DESC, o.id DESC
      LIMIT {$perPage} OFFSET {$offset}",
    $binds
);

view('admin/orders', [
    'title'     => 'Orders',
    'subtitle'  => 'All customer orders',
    'orders'    => $orders,
    'filters'   => $filters,
    'platforms' => options_from('platforms'),
    'total'     => $total,
    'page'      => $page,
    'pages'     => max(1, (int) ceil($total / $perPage)),
], 'layouts/admin');
