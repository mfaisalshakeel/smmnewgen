<?php
/**
 * Keeping the catalogue in step with the providers.
 *
 * Two jobs:
 *   - test a provider (does the key work, what does it bill in, can it answer
 *     a multi-order status request)
 *   - re-read every provider's catalogue and bring prices, limits and
 *     availability up to date
 *
 * Prices are rebuilt rather than nudged: the provider's own rate is converted
 * from its currency into the base currency, then the service's own markup is
 * applied. A provider raising its price, or the exchange rate moving, both
 * come through without touching the margin.
 */

require_once APP_PATH . '/helpers/SmmApi.php';
require_once APP_PATH . '/helpers/currency.php';

/**
 * Put a provider through its paces and record what we learn.
 *
 * @return array{ok: bool, checks: array<int, array{label: string, ok: bool, detail: string}>}
 */
function provider_check(array $provider): array
{
    $api    = new SmmApi($provider['api_url'], $provider['api_key']);
    $checks = [];
    $fields = [];

    // --- 1. the key works, and what it bills in --------------------------
    $balance = $api->balance();
    if (isset($balance['error']) || !isset($balance['balance'])) {
        $message = (string) ($balance['error'] ?? 'no balance in the reply');
        $checks[] = ['label' => 'Connection and API key', 'ok' => false, 'detail' => $message];
        update_row('providers', ['last_error' => mb_substr($message, 0, 500)], 'id = ?', [$provider['id']]);

        return ['ok' => false, 'checks' => $checks];
    }

    $currency = strtoupper(trim((string) ($balance['currency'] ?? '')));
    $fields['balance']            = (float) $balance['balance'];
    $fields['balance_currency']   = mb_substr($currency, 0, 10);
    $fields['balance_checked_at'] = date('Y-m-d H:i:s');
    $fields['last_error']         = '';
    if (($provider['currency'] ?? '') === '' && $currency !== '') {
        $fields['currency'] = mb_substr($currency, 0, 10);
    }

    $checks[] = [
        'label'  => 'Connection and API key',
        'ok'     => true,
        'detail' => 'Balance ' . rtrim(rtrim(number_format((float) $balance['balance'], 2), '0'), '.')
                  . ($currency !== '' ? ' ' . $currency : ''),
    ];

    // --- 2. does the currency it bills in have a rate? -------------------
    $billing = $fields['currency'] ?? $provider['currency'] ?? '';
    if ($billing === '') {
        $checks[] = ['label' => 'Billing currency', 'ok' => false,
                     'detail' => 'The provider did not say. Set it on this form so prices convert correctly.'];
    } elseif (currency_rate($billing) <= 0) {
        $checks[] = ['label' => 'Billing currency', 'ok' => false,
                     'detail' => $billing . ' has no exchange rate yet. Add it under Currencies.'];
    } else {
        $checks[] = ['label' => 'Billing currency', 'ok' => true,
                     'detail' => $billing . ' at ' . rtrim(rtrim(number_format(currency_rate($billing), 4), '0'), '.')
                               . ' ' . base_currency()['code'] . ' per ' . $billing];
    }

    // --- 3. the catalogue ------------------------------------------------
    $services = $api->services();
    if (isset($services['error']) || !is_array($services)) {
        $checks[] = ['label' => 'Service list', 'ok' => false,
                     'detail' => (string) ($services['error'] ?? 'unreadable reply')];
    } else {
        $count = 0;
        foreach ($services as $item) {
            if (is_array($item) && isset($item['service'])) {
                $count++;
            }
        }
        $checks[] = ['label' => 'Service list', 'ok' => $count > 0,
                     'detail' => $count > 0 ? qty_fmt($count) . ' services offered' : 'the list came back empty'];
    }

    // --- 4. multi-order status -------------------------------------------
    $sample = array_column(all(
        "SELECT provider_order_id FROM orders
          WHERE provider_id = ? AND provider_order_id <> ''
       ORDER BY id DESC LIMIT 2", [$provider['id']]
    ), 'provider_order_id');

    if (count($sample) < 2) {
        $checks[] = ['label' => 'Multiple status in one request', 'ok' => true,
                     'detail' => 'Not testable yet - it needs two orders already sent to this provider. '
                               . 'Until then statuses are fetched one at a time.'];
    } else {
        $response  = $api->multiStatus($sample);
        $supported = !isset($response['error'])
                  && !isset($response['status'])
                  && isset($response[(string) $sample[0]]);

        $fields['supports_multi_status'] = $supported ? 1 : 0;
        $checks[] = [
            'label'  => 'Multiple status in one request',
            'ok'     => true,
            'detail' => $supported
                ? 'Supported - statuses are fetched up to 100 at a time.'
                : 'Not supported - statuses are fetched one order at a time.',
        ];
    }

    update_row('providers', $fields, 'id = ?', [$provider['id']]);

    $ok = true;
    foreach ($checks as $check) {
        $ok = $ok && $check['ok'];
    }

    return ['ok' => $ok, 'checks' => $checks];
}

/**
 * Re-read a provider's catalogue and bring its services up to date.
 *
 * Only services with auto_sync on are touched. A service the provider no
 * longer lists is flagged rather than deleted, because orders point at it.
 *
 * @return array{checked: int, updated: int, missing: int, error: ?string}
 */
function sync_provider_services(array $provider): array
{
    $result = ['checked' => 0, 'updated' => 0, 'missing' => 0, 'error' => null];

    $api      = new SmmApi($provider['api_url'], $provider['api_key']);
    $response = $api->services();

    if (isset($response['error']) || !is_array($response)) {
        $result['error'] = (string) ($response['error'] ?? 'unreadable reply');
        update_row('providers', ['last_error' => mb_substr($result['error'], 0, 500)], 'id = ?', [$provider['id']]);
        return $result;
    }

    $remote = [];
    foreach ($response as $item) {
        if (is_array($item) && isset($item['service'])) {
            $remote[(string) $item['service']] = $item;
        }
    }

    $billing = (string) ($provider['currency'] ?? '');
    $rate    = $billing === '' ? 1.0 : currency_rate($billing);

    if ($rate <= 0) {
        $result['error'] = 'No exchange rate for ' . $billing . ', so prices were left alone.';
        update_row('providers', ['last_error' => $result['error']], 'id = ?', [$provider['id']]);
        return $result;
    }

    $now      = date('Y-m-d H:i:s');
    $services = all('SELECT * FROM services WHERE provider_id = ? AND auto_sync = 1', [$provider['id']]);

    foreach ($services as $service) {
        $result['checked']++;
        $match = $remote[(string) $service['provider_service_id']] ?? null;

        if (!$match) {
            // Gone from the provider. Flag it and take it off the shop, but
            // keep the row so existing orders still read correctly.
            if (!$service['missing_at_provider']) {
                update_row('services', [
                    'missing_at_provider' => 1,
                    'is_active'           => 0,
                    'updated_at'          => $now,
                ], 'id = ?', [$service['id']]);
                log_line('Service ' . $service['id'] . ' (' . $service['name'] . ') is gone from '
                       . $provider['name'] . ' - deactivated.');
            }
            $result['missing']++;
            continue;
        }

        $providerRate = (float) ($match['rate'] ?? $service['provider_rate']);
        $baseCost     = $providerRate * $rate;

        // Fall back to whatever margin the service already carried, then to
        // the site default, so a service never loses its markup in a sync.
        $markup = (float) $service['markup_percent'];
        if ($markup <= 0) {
            $oldCost = (float) $service['cost_per_1000'];
            $markup  = $oldCost > 0
                ? ((float) $service['price_per_1000'] / $oldCost - 1) * 100
                : (float) setting('default_markup', 35);
        }

        $fields = [
            'provider_rate'       => $providerRate,
            'cost_per_1000'       => round($baseCost, 4),
            'price_per_1000'      => round($baseCost * (1 + $markup / 100), 4),
            'markup_percent'      => round($markup, 2),
            'min_qty'             => max(1, (int) ($match['min'] ?? $service['min_qty'])),
            'max_qty'             => max(1, (int) ($match['max'] ?? $service['max_qty'])),
            'supports_refill'     => !empty($match['refill']) ? 1 : 0,
            'supports_cancel'     => !empty($match['cancel']) ? 1 : 0,
            'supports_dripfeed'   => !empty($match['dripfeed']) ? 1 : 0,
            'missing_at_provider' => 0,
            'last_synced_at'      => $now,
            'updated_at'          => $now,
        ];

        update_row('services', $fields, 'id = ?', [$service['id']]);
        $result['updated']++;
    }

    update_row('providers', ['last_sync_at' => $now, 'last_error' => ''], 'id = ?', [$provider['id']]);

    return $result;
}

/**
 * Sync every provider that has auto_sync on.
 *
 * @return array{providers: int, updated: int, missing: int, errors: string[]}
 */
function sync_all_services(): array
{
    $totals = ['providers' => 0, 'updated' => 0, 'missing' => 0, 'errors' => []];

    foreach (all('SELECT * FROM providers WHERE is_active = 1 AND auto_sync = 1 ORDER BY sort_order, id') as $provider) {
        $totals['providers']++;
        $one = sync_provider_services($provider);

        $totals['updated'] += $one['updated'];
        $totals['missing'] += $one['missing'];
        if ($one['error'] !== null) {
            $totals['errors'][] = $provider['name'] . ': ' . $one['error'];
        }
    }

    return $totals;
}
