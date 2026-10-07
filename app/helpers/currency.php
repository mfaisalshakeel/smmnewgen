<?php
/**
 * Currencies.
 *
 * One currency is the base: every price in the database is stored in it, and
 * every rate says how many of that currency one unit of the other is worth.
 * So with PKR as base and USD at 280, `rate_to_base` for USD is 280.
 *
 * Providers bill in their own currency. When a service is imported or synced,
 * the provider's rate is converted to base first, and the markup is applied
 * after - so a dollar moving does not quietly eat the margin.
 */

/** Every currency, keyed by code. Read once per request. */
function currencies(bool $fresh = false): array
{
    static $cache = null;
    if ($cache === null || $fresh) {
        $cache = [];
        foreach (all('SELECT * FROM currencies ORDER BY sort_order, code') as $row) {
            $cache[strtoupper($row['code'])] = $row;
        }
    }
    return $cache;
}

/** The base currency row, or a sensible stand-in if none is marked. */
function base_currency(): array
{
    foreach (currencies() as $currency) {
        if ($currency['is_base']) {
            return $currency;
        }
    }

    $code = strtoupper((string) setting('base_currency', 'PKR'));
    return currencies()[$code] ?? [
        'code'         => $code,
        'name'         => $code,
        'symbol'       => (string) setting('currency_symbol', ''),
        'rate_to_base' => 1,
        'is_base'      => 1,
    ];
}

/** What one unit of $code is worth in the base currency. */
function currency_rate(string $code): float
{
    $code = strtoupper(trim($code));
    if ($code === '' || $code === strtoupper(base_currency()['code'])) {
        return 1.0;
    }

    $rate = (float) (currencies()[$code]['rate_to_base'] ?? 0);
    return $rate > 0 ? $rate : 0.0;
}

/**
 * Convert an amount into the base currency.
 *
 * Returns null when the currency is unknown, because guessing a rate would
 * quietly mis-price everything. Callers decide what to do about it.
 */
function to_base(float $amount, string $fromCode): ?float
{
    $rate = currency_rate($fromCode);
    return $rate > 0 ? $amount * $rate : null;
}

/**
 * Pull fresh rates from a public source.
 *
 * open.er-api.com needs no key and answers with rates *from* the base, so
 * each is inverted to get "what is one unit worth in base".
 *
 * @return array{ok: bool, updated: int, message: string}
 */
function refresh_currency_rates(): array
{
    $base = strtoupper(base_currency()['code']);
    $url  = 'https://open.er-api.com/v6/latest/' . rawurlencode($base);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'SMMPanel/1.0',
    ]);
    $body  = curl_exec($ch);
    $error = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error !== '') {
        return ['ok' => false, 'updated' => 0, 'message' => 'Could not reach the rate service: ' . $error];
    }
    if ($code >= 400) {
        return ['ok' => false, 'updated' => 0, 'message' => 'Rate service answered HTTP ' . $code];
    }

    $data = json_decode((string) $body, true);
    if (!is_array($data) || empty($data['rates']) || !is_array($data['rates'])) {
        return ['ok' => false, 'updated' => 0, 'message' => 'Rate service sent something we could not read.'];
    }

    $now     = date('Y-m-d H:i:s');
    $updated = 0;

    foreach (currencies(true) as $code => $currency) {
        if ($currency['is_base']) {
            update_row('currencies', ['rate_to_base' => 1, 'updated_at' => $now], 'id = ?', [$currency['id']]);
            continue;
        }

        // The feed gives base -> code; we store code -> base.
        $perBase = (float) ($data['rates'][$code] ?? 0);
        if ($perBase <= 0) {
            continue;
        }

        update_row('currencies', [
            'rate_to_base' => round(1 / $perBase, 8),
            'updated_at'   => $now,
        ], 'id = ?', [$currency['id']]);
        $updated++;
    }

    set_setting('currency_rates_updated_at', $now);
    currencies(true);

    return [
        'ok'      => true,
        'updated' => $updated,
        'message' => $updated
            ? 'Updated ' . $updated . ' rate' . ($updated === 1 ? '' : 's') . ' against ' . $base . '.'
            : 'The rate service had nothing for the currencies you have added.',
    ];
}
