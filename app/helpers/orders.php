<?php
/**
 * Order lifecycle: creating the code, sending to the provider, and pulling
 * statuses back. Used by the admin screens and by cron.
 */

require_once APP_PATH . '/helpers/SmmApi.php';
require_once APP_PATH . '/helpers/currency.php';

/** Unique order code, e.g. GK-8F42KD. */
function new_order_code(): string
{
    $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper(setting('order_prefix', 'GK'))) ?: 'GK';
    for ($attempt = 0; $attempt < 12; $attempt++) {
        $code = $prefix . '-' . random_code(6);
        if (!col('SELECT 1 FROM orders WHERE code = ?', [$code])) {
            return $code;
        }
    }
    // Astronomically unlikely; fall back to something that cannot collide.
    return $prefix . '-' . strtoupper(bin2hex(random_bytes(5)));
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
    $order = one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$order) {
        return [false, 'Order not found.'];
    }
    if ($order['provider_order_id'] !== '') {
        return [false, 'This order was already sent to the provider.'];
    }
    if (!in_array($order['status'], ['paid', 'api_error'], true)) {
        return [false, 'Only paid orders can be sent. This one is ' . $order['status'] . '.'];
    }

    $service = $order['service_id']
        ? one('SELECT * FROM services WHERE id = ?', [$order['service_id']])
        : null;

    // Manual service: nothing to call, a person fulfils it.
    if (!$service || empty($service['provider_id'])) {
        update_row('orders', [
            'status'     => 'processing',
            'api_error'  => '',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$orderId]);
        order_log($orderId, 'Marked processing (manual service, no provider).');
        return [true, 'Manual service - marked as processing for manual delivery.'];
    }

    $provider = one('SELECT * FROM providers WHERE id = ?', [$service['provider_id']]);
    if (!$provider) {
        return [false, 'The provider for this service no longer exists.'];
    }

    $api    = new SmmApi($provider['api_url'], $provider['api_key']);
    $result = $api->add($service['provider_service_id'], $order['link'], (int) $order['quantity']);

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
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$orderId]);
        order_log($orderId, 'Provider rejected the order: ' . $message);
        log_line('Order ' . $order['code'] . ' api_error: ' . $message);

        return [false, $message];
    }

    update_row('orders', [
        'status'            => 'processing',
        'provider_id'       => (int) $provider['id'],
        'provider_order_id' => (string) $newId,
        'cost'              => round(((float) $service['cost_per_1000'] / 1000) * (int) $order['quantity'], 2),
        'api_error'         => '',
        'updated_at'        => date('Y-m-d H:i:s'),
    ], 'id = ?', [$orderId]);

    order_log($orderId, 'Sent to ' . $provider['name'] . ' - provider order ' . $newId . '.');

    return [true, 'Sent to ' . $provider['name'] . ' (provider order ' . $newId . ').'];
}

/**
 * Does this provider answer a multi-order status request?
 *
 * Some do, some do not, and the only honest way to find out is to ask. The
 * answer is cached on the provider row: null means "not asked yet", 1 yes,
 * 0 no. An admin can override it on the provider form.
 *
 * The test sends two real order ids and looks at the shape of the reply: a
 * provider that supports it returns an object keyed by order id, one that
 * does not returns an error or a single flat status object.
 */
function provider_supports_multi_status(array $provider, array $sampleOrderIds): bool
{
    if ($provider['supports_multi_status'] !== null) {
        return (bool) $provider['supports_multi_status'];
    }
    if (count($sampleOrderIds) < 2) {
        // Nothing to test with - assume not, and ask again another time.
        return false;
    }

    $api      = new SmmApi($provider['api_url'], $provider['api_key']);
    $sample   = array_slice($sampleOrderIds, 0, 2);
    $response = $api->multiStatus($sample);

    $supported = !isset($response['error'])
              && !isset($response['status'])            // a flat reply means it ignored `orders`
              && isset($response[(string) $sample[0]]); // keyed by order id is the real thing

    update_row('providers', ['supports_multi_status' => $supported ? 1 : 0], 'id = ?', [$provider['id']]);
    log_line(sprintf('Provider %s multi-status support: %s', $provider['name'], $supported ? 'yes' : 'no'));

    return $supported;
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
        "SELECT id, code, provider_id, provider_order_id, status
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
        } else {
            $message = (string) ($result['error'] ?? 'no balance in reply');
            update_row('providers', ['last_error' => mb_substr($message, 0, 500)], 'id = ?', [$provider['id']]);
            log_line('Balance check failed for ' . $provider['name'] . ': ' . $message);
        }
    }
    return $updated;
}
