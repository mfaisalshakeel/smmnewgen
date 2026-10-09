<?php
/**
 * Order lifecycle: creating the code, sending to the provider, and pulling
 * statuses back. Used by the admin screens and by cron.
 */

require_once APP_PATH . '/helpers/SmmApi.php';
require_once APP_PATH . '/helpers/currency.php';
require_once APP_PATH . '/helpers/notify.php';
require_once APP_PATH . '/helpers/guard.php';
require_once APP_PATH . '/helpers/audit.php';
require_once APP_PATH . '/helpers/margin.php';

/** Unique order code, e.g. GK-8F42KD. */
function new_order_code(): string
{
    $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper(setting('order_prefix', 'GK'))) ?: 'GK';

    // Not checked against the table first: two requests can both find the
    // same code free and both go on to use it. `orders.code` is unique, so
    // the insert is where a collision is really settled - this only picks a
    // candidate, and insert_order_code() below is what survives losing.
    return $prefix . '-' . random_code(6);
}

/**
 * Insert an order, retrying the code if another request took it first.
 *
 * The retry is what makes the code generator safe to race: the loser of a
 * collision gets a unique-key violation rather than a duplicate row, picks
 * another code and tries again.
 *
 * @return array{0:int, 1:string}  [order id, the code it ended up with]
 */
function insert_order_code(array $fields): array
{
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $fields['code'] = new_order_code();
        try {
            return [insert_row('orders', $fields), $fields['code']];
        } catch (PDOException $e) {
            if (!is_duplicate_key($e)) {
                throw $e;
            }
        }
    }

    // Eight collisions in a row is not chance; widen the code rather than
    // failing the customer's order.
    $fields['code'] = preg_replace('/-.*$/', '', $fields['code'])
        . '-' . strtoupper(bin2hex(random_bytes(5)));

    return [insert_row('orders', $fields), $fields['code']];
}

function order_log(int $orderId, string $message): void
{
    insert_row('order_logs', [
        'order_id'   => $orderId,
        'message'    => mb_substr($message, 0, 500),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

/**
 * Translate whatever a provider calls a status into one of ours.
 * Unknown values stay 'processing' rather than silently completing an order.
 */
function map_provider_status(string $providerStatus): string
{
    $status = strtolower(trim($providerStatus));

    return match (true) {
        in_array($status, ['completed', 'complete', 'success', 'done'], true)      => 'completed',
        in_array($status, ['partial'], true)                                        => 'partial',
        in_array($status, ['canceled', 'cancelled', 'cancel'], true)                => 'cancelled',
        in_array($status, ['refunded', 'refund'], true)                             => 'refunded',
        in_array($status, ['in progress', 'inprogress', 'in_progress', 'processing',
                           'pending', 'active', 'queue', 'queued', 'started'], true) => 'processing',
        default                                                                      => 'processing',
    };
}

/**
 * Send one order to its provider.
 *
 * Returns [ok, message]. On failure the order is parked in `api_error` with
 * the provider's own words kept, so an admin can see what went wrong and
 * retry once it is fixed. A manual service (no provider) just moves to
 * processing - a human delivers it.
 */
function send_order_to_provider(int $orderId): array
{
    $now   = date('Y-m-d H:i:s');
    // A send that started this long ago never finished - the request was cut
    // off mid-flight. Long enough that a slow provider is not lapped.
    $stale = date('Y-m-d H:i:s', time() - 600);

    // Claim it before reading it. Checking first and updating afterwards is
    // what let two requests - the admin pressing Send while cron ran, or one
    // double-click - both reach $api->add() and both be charged for the same
    // order. Exactly one caller can match this UPDATE; everyone else is told
    // to go away.
    $mine = claim('orders',
        ['sending_at' => $now],
        "id = ? AND provider_order_id = '' AND status IN ('paid', 'api_error')
           AND (sending_at IS NULL OR sending_at < ?)",
        [$orderId, $stale]
    );

    if (!$mine) {
        $order = one('SELECT status, provider_order_id, sending_at FROM orders WHERE id = ?', [$orderId]);
        if (!$order) {
            return [false, 'Order not found.'];
        }
        if ($order['provider_order_id'] !== '') {
            return [false, 'This order was already sent to the provider.'];
        }
        if (!in_array($order['status'], ['paid', 'api_error'], true)) {
            return [false, 'Only paid orders can be sent. This one is ' . $order['status'] . '.'];
        }
        return [false, 'This order is being sent right now. Give it a moment.'];
    }

    // From here on the order is ours, and every path out has to let go of it.
    $release = static function (int $id): void {
        q('UPDATE orders SET sending_at = NULL WHERE id = ?', [$id]);
    };

    $order = one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$order) {
        $release($orderId);
        return [false, 'Order not found.'];
    }

    $service = $order['service_id']
        ? one('SELECT * FROM services WHERE id = ?', [$order['service_id']])
        : null;

    // Manual service: nothing to call, a person fulfils it.
    if (!$service || empty($service['provider_id'])) {
        update_row('orders', [
            'status'     => 'processing',
            'api_error'  => '',
            'sending_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$orderId]);
        order_log($orderId, 'Marked processing (manual service, no provider).');
        audit('order.manual', ['entity' => 'orders', 'entity_id' => $orderId,
            'summary' => $order['code'] . ' has no provider - left for manual delivery.']);
        return [true, 'Manual service - marked as processing for manual delivery.'];
    }

    $provider = one('SELECT * FROM providers WHERE id = ?', [$service['provider_id']]);
    if (!$provider) {
        $release($orderId);
        return [false, 'The provider for this service no longer exists.'];
    }

    // What it costs now, not what it cost when the order was placed. Between
    // those two moments a provider can raise its rate, and buying anyway is
    // paying more for the order than the customer paid us.
    $costNow = round(((float) $service['cost_per_1000'] / 1000) * (int) $order['quantity'], 2);
    $check   = margin_allows((float) $order['price'], $costNow, [
        'service'    => $order['service_label'] ?: $order['service_name'],
        'service_id' => (int) $service['id'],
    ]);

    if (!$check['ok']) {
        $message = 'Held back: this order now ' . $check['reason']
            . '. Reprice the service, then send it by hand.';

        update_row('orders', [
            'status'      => 'api_error',
            'api_error'   => mb_substr($message, 0, 500),
            'provider_id' => (int) $provider['id'],
            'cost'        => $costNow,
            'sending_at'  => null,
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$orderId]);

        order_log($orderId, $message);
        audit('order.held_margin', [
            'entity' => 'orders', 'entity_id' => $orderId, 'severity' => 'alert',
            'summary' => $order['code'] . ' ' . $check['reason'],
            'after'   => ['price' => $order['price'], 'cost' => $costNow],
        ]);

        return [false, $message];
    }

    $api = new SmmApi($provider['api_url'], $provider['api_key']);

    // SmmApi reports its troubles rather than throwing, so this only catches
    // the unexpected - but an order left claimed by a fatal would sit unsent
    // until the ten-minute stale window let it go, and that is ten minutes of
    // a customer waiting for nothing.
    try {
        $result = $api->add($service['provider_service_id'], $order['link'], (int) $order['quantity']);
    } catch (Throwable $e) {
        $release($orderId);
        $message = 'The provider call failed: ' . $e->getMessage();
        order_log($orderId, $message);
        audit('order.send_failed', [
            'entity' => 'orders', 'entity_id' => $orderId, 'severity' => 'warn',
            'summary' => $order['code'] . ' - ' . $message,
        ]);
        return [false, $message];
    }

    $failure = $result['error'] ?? null;
    $newId   = $result['order'] ?? null;

    if ($failure !== null || $newId === null || $newId === '') {
        $message = is_string($failure) && $failure !== ''
            ? $failure
            : 'Provider did not return an order id.';

        update_row('orders', [
            'status'      => 'api_error',
            'api_error'   => mb_substr($message, 0, 500),
            'provider_id' => (int) $provider['id'],
            'sending_at'  => null,
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$orderId]);
        order_log($orderId, 'Provider rejected the order: ' . $message);
        log_line('Order ' . $order['code'] . ' api_error: ' . $message);
        audit('order.api_error', [
            'entity' => 'orders', 'entity_id' => $orderId, 'severity' => 'warn',
            'summary' => $order['code'] . ' refused by ' . $provider['name'] . ': ' . $message,
        ]);

        notify('admin_api_error', [
            'order_code' => $order['code'],
            'service'    => $order['service_label'] ?: $order['service_name'],
            'provider'   => $provider['name'],
            'error'      => $message,
            'admin_url'  => url('admin/orders/view/' . $orderId),
            'admin_link' => 'admin/orders/view/' . $orderId,
        ]);

        return [false, $message];
    }

    update_row('orders', [
        'status'            => 'processing',
        'provider_id'       => (int) $provider['id'],
        'provider_order_id' => (string) $newId,
        'cost'              => $costNow,
        'api_error'         => '',
        'sending_at'        => null,
        'updated_at'        => date('Y-m-d H:i:s'),
    ], 'id = ?', [$orderId]);

    order_log($orderId, 'Sent to ' . $provider['name'] . ' - provider order ' . $newId . '.');
    audit('order.sent', [
        'entity' => 'orders', 'entity_id' => $orderId,
        'summary' => $order['code'] . ' -> ' . $provider['name'] . ' as ' . $newId
                   . ', cost ' . money($costNow) . ', price ' . money((float) $order['price']),
        'after'   => ['provider_order_id' => (string) $newId, 'cost' => $costNow],
    ]);

    return [true, 'Sent to ' . $provider['name'] . ' (provider order ' . $newId . ').'];
}

/**
 * Ask a provider whether it answers a multi-order status request.
 *
 * Some do, some do not, and the only honest way to find out is to ask. What
 * gives the answer away is the shape of the reply, not its contents: a
 * provider that understands `orders` answers with an object keyed by order
 * id, one that does not answers with a flat status object (it read `order`
 * and ignored the rest) or with a single error.
 *
 * That means the probe does not need real orders. Where none have been sent
 * yet it asks about two ids that will not exist, and a reply still keyed by
 * those ids proves the batch form was understood. One case stays genuinely
 * unanswerable: a provider that batches but collapses a request of entirely
 * unknown ids into one error looks exactly like a provider that does not
 * batch. So a real order id is mixed in whenever one exists, and when none
 * does and the reply is a flat error the answer is "cannot tell yet" rather
 * than a guess recorded as fact.
 *
 * @param string[] $sampleOrderIds provider order ids already known to be real
 * @return array{supported: ?bool, detail: string}  supported null = no answer
 */
function multi_status_probe(array $provider, array $sampleOrderIds = []): array
{
    $real = array_values(array_filter(array_map('strval', $sampleOrderIds), 'strlen'));

    if (!$real) {
        $real = array_column(all(
            "SELECT provider_order_id FROM orders
              WHERE provider_id = ? AND provider_order_id <> ''
           ORDER BY id DESC LIMIT 2", [$provider['id']]
        ), 'provider_order_id');
        $real = array_map('strval', $real);
    }

    $real  = array_slice(array_unique($real), 0, 2);
    $probe = $real;

    // A status call changes nothing, so an id that belongs to nobody is a
    // safe second subject; 12 digits is well past any live order number.
    while (count($probe) < 2) {
        $probe[] = (string) random_int(100000000000, 999999999999);
    }

    $response = (new SmmApi($provider['api_url'], $provider['api_key']))->multiStatus($probe);

    if (!is_array($response)) {
        return ['supported' => null, 'detail' => 'The provider sent back something unreadable.'];
    }

    // Keyed by the ids we asked about - the batch form was understood, even
    // if every entry is "no such order".
    foreach ($probe as $id) {
        if (isset($response[$id])) {
            return ['supported' => true, 'detail' => 'Statuses are fetched up to 100 at a time.'];
        }
    }

    if (isset($response['status'])) {
        return ['supported' => false,
                'detail' => 'It answered about one order only, so statuses are fetched one at a time.'];
    }

    if ($real) {
        return ['supported' => false,
                'detail' => 'It did not answer a batch of real order ids, so statuses are fetched '
                          . 'one at a time.'];
    }

    return ['supported' => null,
            'detail' => 'It rejected the whole batch: ' . (string) ($response['error'] ?? 'no reason given')
                      . '. That is also what a provider says when it does not know the ids at all, so '
                      . 'this is not an answer yet - check again once an order has been sent.'];
}

/**
 * Does this provider answer a multi-order status request?
 *
 * The answer is cached on the provider row: null means "not asked yet", 1
 * yes, 0 no. An admin can override it on the provider form, and the Providers
 * screen has a button that re-asks.
 */
function provider_supports_multi_status(array $provider, array $sampleOrderIds = []): bool
{
    if ($provider['supports_multi_status'] !== null) {
        return (bool) $provider['supports_multi_status'];
    }

    $probe = multi_status_probe($provider, $sampleOrderIds);

    if ($probe['supported'] === null) {
        // Nothing learned, so nothing recorded - one order at a time for now
        // and the question gets asked again next time.
        return false;
    }

    update_row('providers', ['supports_multi_status' => $probe['supported'] ? 1 : 0],
        'id = ?', [$provider['id']]);
    log_line(sprintf('Provider %s multi-status support: %s',
        $provider['name'], $probe['supported'] ? 'yes' : 'no'));

    return $probe['supported'];
}

/**
 * Refresh statuses for orders that are still moving.
 *
 * Orders are grouped per provider and asked for in one multi-status call, so
 * a hundred open orders is a handful of requests rather than a hundred.
 *
 * @return array{checked:int, updated:int, errors:int}
 */
function sync_order_statuses(int $limit = 100): array
{
    $open = all(
        // email, the names and the counts come along because a status that
        // turns into 'completed' sends a message that needs all of them.
        "SELECT id, code, provider_id, provider_order_id, status,
                email, service_label, service_name, quantity, start_count
           FROM orders
          WHERE status IN ('processing', 'partial')
            AND provider_order_id <> ''
            AND provider_id IS NOT NULL
       ORDER BY COALESCE(synced_at, created_at) ASC
          LIMIT " . max(1, min(500, $limit))
    );

    $summary = ['checked' => 0, 'updated' => 0, 'errors' => 0];
    if (!$open) {
        return $summary;
    }

    $byProvider = [];
    foreach ($open as $order) {
        $byProvider[(int) $order['provider_id']][] = $order;
    }

    foreach ($byProvider as $providerId => $orders) {
        $provider = one('SELECT * FROM providers WHERE id = ?', [$providerId]);
        if (!$provider || !$provider['is_active']) {
            continue;
        }

        $api   = new SmmApi($provider['api_url'], $provider['api_key']);
        $multi = provider_supports_multi_status($provider, array_column($orders, 'provider_order_id'));

        // 100 is the cap in the standard; one at a time when the provider
        // cannot do batches.
        foreach (array_chunk($orders, $multi ? 100 : 1) as $chunk) {
            $ids = array_column($chunk, 'provider_order_id');

            $response = $multi
                ? $api->multiStatus($ids)
                : $api->status((string) $ids[0]);

            if (isset($response['error'])) {
                $summary['errors'] += count($chunk);
                log_line('Status sync failed for ' . $provider['name'] . ': ' . $response['error']);
                continue;
            }

            foreach ($chunk as $order) {
                $summary['checked']++;
                $row = $response[$order['provider_order_id']] ?? null;

                // A single-order reply is the status object itself, not keyed.
                if ($row === null && count($chunk) === 1 && isset($response['status'])) {
                    $row = $response;
                }
                if (!is_array($row) || isset($row['error'])) {
                    $summary['errors']++;
                    continue;
                }

                $mapped = map_provider_status((string) ($row['status'] ?? ''));
                $fields = [
                    'provider_status' => mb_substr((string) ($row['status'] ?? ''), 0, 60),
                    'synced_at'       => date('Y-m-d H:i:s'),
                    'updated_at'      => date('Y-m-d H:i:s'),
                ];
                if (isset($row['start_count']) && $row['start_count'] !== '') {
                    $fields['start_count'] = (int) $row['start_count'];
                }
                if (isset($row['remains']) && $row['remains'] !== '') {
                    $fields['remains'] = (int) $row['remains'];
                }
                if ($mapped !== $order['status']) {
                    $fields['status'] = $mapped;
                    $summary['updated']++;
                    order_log((int) $order['id'],
                        'Status from provider: ' . ($row['status'] ?? '?') . ' -> ' . $mapped . '.');

                    if ($mapped === 'completed' && ($order['email'] ?? '') !== '') {
                        notify('order_completed', [
                            'order_code'  => $order['code'],
                            'service'     => $order['service_label'] ?: $order['service_name'],
                            'quantity'    => qty_fmt((int) $order['quantity']),
                            'start_count' => qty_fmt((int) ($fields['start_count'] ?? $order['start_count'] ?? 0)),
                            'order_url'   => url('order/' . $order['code']),
                            'email'       => $order['email'],
                        ]);
                    }
                }

                update_row('orders', $fields, 'id = ?', [$order['id']]);
            }
        }
    }

    return $summary;
}

/** Ask every active provider what our balance is and store it. */
function refresh_provider_balances(): int
{
    $updated = 0;
    foreach (all('SELECT * FROM providers WHERE is_active = 1') as $provider) {
        $api    = new SmmApi($provider['api_url'], $provider['api_key']);
        $result = $api->balance();

        if (isset($result['balance'])) {
            $fields = [
                'balance'            => (float) $result['balance'],
                'balance_currency'   => mb_substr((string) ($result['currency'] ?? ''), 0, 10),
                'balance_checked_at' => date('Y-m-d H:i:s'),
                'last_error'         => '',
            ];

            // The balance reply is where a provider tells us what it bills in.
            // Only fill it in if nobody has set it by hand.
            if (($provider['currency'] ?? '') === '' && !empty($result['currency'])) {
                $fields['currency'] = mb_substr(strtoupper((string) $result['currency']), 0, 10);
            }

            update_row('providers', $fields, 'id = ?', [$provider['id']]);
            $updated++;

            notify_if_low_balance($provider, $fields);
        } else {
            $message = (string) ($result['error'] ?? 'no balance in reply');
            update_row('providers', ['last_error' => mb_substr($message, 0, 500)], 'id = ?', [$provider['id']]);
            log_line('Balance check failed for ' . $provider['name'] . ': ' . $message);
        }
    }
    return $updated;
}

/**
 * Warn once when a provider crosses the threshold on the way down.
 *
 * The threshold is in our currency and the balance is in the provider's, so
 * the comparison has to be made in base - 50 USD is not below 500 PKR. With
 * no rate on file nothing is said, because a warning worked out from a
 * guessed rate is worse than none.
 *
 * Once, not every run: the balance check runs on a schedule, and a cron that
 * mails the same warning every ten minutes is a cron the admin turns off.
 */
function notify_if_low_balance(array $provider, array $fields): void
{
    $threshold = (float) setting('low_balance_threshold', 0);
    if ($threshold <= 0) {
        return;
    }

    $code    = strtoupper(trim((string) ($fields['currency'] ?? $provider['currency'] ?? '')));
    $now     = to_base((float) $fields['balance'], $code);
    $before  = to_base((float) $provider['balance'], $code);
    if ($now === null) {
        return;
    }

    // Only on the crossing: above-to-below. Staying below says nothing new.
    if ($now >= $threshold || ($before !== null && $before < $threshold)) {
        return;
    }

    notify('admin_low_balance', [
        'provider'   => $provider['name'],
        'balance'    => money_in((float) $fields['balance'], $code) . ' = ' . money($now),
        'threshold'  => money($threshold),
        'admin_url'  => url('admin/providers'),
        'admin_link' => 'admin/providers',
    ]);
}
