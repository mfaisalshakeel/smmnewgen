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
 * Format an amount in a currency that is not necessarily ours.
 *
 * money() always prints the base symbol, which is right for a price and
 * wrong for a provider balance: a provider holding 0.72 USD was being shown
 * as "Rs 0.72", the provider's own number wearing our symbol.
 */
function money_in(float $amount, string $code): string
{
    $code     = strtoupper(trim($code));
    $currency = currencies()[$code] ?? null;
    $symbol   = $currency['symbol'] ?? $code;
    $text     = number_format($amount, 2);
    // A letter symbol needs air before the digits; a glyph like $ does not.
    $gap = preg_match('/\p{L}$/u', (string) $symbol) ? "\u{00A0}" : '';

    return $symbol . $gap . $text;
}

/**
 * A provider balance, said in full: its own currency and ours.
 *
 * When there is no rate the base figure is left out rather than invented,
 * and the caller can see that from `converted` being null.
 *
 * @return array{own: string, base: ?float, shown: string, code: string}
 */
function provider_balance(array $provider): array
{
    $amount = (float) ($provider['balance'] ?? 0);
    $code   = strtoupper(trim((string) ($provider['currency'] ?? '')));
    $base   = strtoupper(base_currency()['code']);

    $own  = $code === '' ? number_format($amount, 2) : money_in($amount, $code);
    $converted = $code === '' ? null : to_base($amount, $code);

    return [
        'code'      => $code,
        'own'       => $own,
        'base'      => $converted,
        // Nothing to convert when the provider already bills in our money.
        'shown'     => $code === '' || $code === $base || $converted === null
                        ? $own
                        : $own . ' = ' . money($converted),
    ];
}

/**
 * Every active provider balance added up, in base.
 *
 * Adding balances held in different currencies without converting them is
 * meaningless before it is even mislabelled, so anything with no rate is
 * counted separately and named.
 *
 * @return array{total: float, missing: string[]}
 */
function provider_balance_total(): array
{
    $total   = 0.0;
    $missing = [];

    foreach (all('SELECT balance, currency FROM providers WHERE is_active = 1') as $provider) {
        $code = strtoupper(trim((string) $provider['currency']));

        // A provider that never said what it bills in is left out, not counted
        // as ours. currency_rate() answers 1 for an empty code, which is the
        // right default when pricing a service and the wrong one here: it
        // would fold an unknown number into the total as if it were rupees.
        $converted = $code === '' ? null : to_base((float) $provider['balance'], $code);
        if ($converted === null) {
            $missing[$code === '' ? 'unknown' : $code] = true;
            continue;
        }
        $total += $converted;
    }

    return ['total' => $total, 'missing' => array_keys($missing)];
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

/**
 * The currencies a provider is likely to bill in.
 *
 * Only here so an added currency arrives with a name and a symbol rather than
 * three bare letters. Anything not listed still works - it is named after its
 * own code until somebody edits it.
 */
const KNOWN_CURRENCIES = [
    'USD' => ['US Dollar', '$'],          'EUR' => ['Euro', '\u{20AC}'],
    'GBP' => ['British Pound', '\u{A3}'],   'PKR' => ['Pakistani Rupee', 'Rs '],
    'INR' => ['Indian Rupee', '\u{20B9}'],  'BDT' => ['Bangladeshi Taka', '\u{9F3}'],
    'NGN' => ['Nigerian Naira', '\u{20A6}'],'TRY' => ['Turkish Lira', '\u{20BA}'],
    'BRL' => ['Brazilian Real', 'R$'],    'IDR' => ['Indonesian Rupiah', 'Rp'],
    'PHP' => ['Philippine Peso', '\u{20B1}'],'EGP' => ['Egyptian Pound', 'E\u{A3}'],
    'SAR' => ['Saudi Riyal', 'SR'],       'AED' => ['UAE Dirham', 'AED '],
    'RUB' => ['Russian Ruble', '\u{20BD}'], 'CNY' => ['Chinese Yuan', '\u{A5}'],
    'CAD' => ['Canadian Dollar', 'C$'],   'AUD' => ['Australian Dollar', 'A$'],
];

/**
 * Add a currency and go and get its rate.
 *
 * Used where a provider turns out to bill in something we have never heard
 * of: there is nothing useful to show until the rate exists, so the panel
 * offers to fetch it rather than quietly pricing everything wrong.
 *
 * @return array{ok: bool, message: string, rate: float}
 */
function currency_add(string $code): array
{
    $code = strtoupper(trim($code));

    if (!preg_match('/^[A-Z]{3}$/', $code)) {
        return ['ok' => false, 'message' => 'A currency code is three letters, like USD.', 'rate' => 0.0];
    }

    if (isset(currencies(true)[$code])) {
        return ['ok' => true, 'message' => $code . ' is already in your currencies.',
                'rate' => currency_rate($code)];
    }

    [$name, $symbol] = KNOWN_CURRENCIES[$code] ?? [$code, $code . ' '];

    insert_row('currencies', [
        'code'         => $code,
        'name'         => $name,
        'symbol'       => $symbol,
        'rate_to_base' => 0,
        'is_base'      => 0,
        'is_active'    => 1,
        'sort_order'   => (int) col('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM currencies', [], 1),
        'updated_at'   => date('Y-m-d H:i:s'),
    ]);
    currencies(true);

    $refresh = refresh_currency_rates();
    $rate    = currency_rate($code);

    if ($rate <= 0) {
        return [
            'ok'      => false,
            'rate'    => 0.0,
            'message' => $code . ' was added, but no rate came back'
                       . ($refresh['ok'] ? '' : ': ' . $refresh['message'])
                       . '. Set it by hand under Currencies.',
        ];
    }

    return [
        'ok'      => true,
        'rate'    => $rate,
        'message' => $code . ' added at ' . rtrim(rtrim(number_format($rate, 4), '0'), '.')
                   . ' ' . base_currency()['code'] . ' per ' . $code . '.',
    ];
}
