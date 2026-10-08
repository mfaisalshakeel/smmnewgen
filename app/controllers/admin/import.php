<?php
/**
 * Pull a provider's catalogue in.
 *
 * The provider list is fetched once and cached on disk, because it is often
 * thousands of rows and refetching it on every filter keystroke is rude to
 * the provider and slow for us. "Refresh from API" clears the cache.
 */
require_once APP_PATH . '/helpers/crud.php';
require_once APP_PATH . '/helpers/detect.php';
require_once APP_PATH . '/helpers/currency.php';
require_once APP_PATH . '/helpers/orders.php';

const IMPORT_CACHE_TTL = 1800;   // 30 minutes

$providers = all('SELECT * FROM providers WHERE is_active = 1 ORDER BY sort_order, id');

if (!$providers) {
    flash('warning', 'Add an active provider first, then come back to import its services.');
    redirect('admin/providers');
}

// Nothing is asked of a provider until the admin names one. Defaulting to
// the first and fetching its catalogue meant simply opening this page spent
// a request on an API we were not asked about - and sat on a spinner while
// it answered.
$asked = isset($_POST['provider_id']) || isset($_GET['provider_id']);

$providerId = (int) ($_POST['provider_id'] ?? $_GET['provider_id'] ?? $providers[0]['id']);
$provider   = null;
foreach ($providers as $candidate) {
    if ((int) $candidate['id'] === $providerId) {
        $provider = $candidate;
    }
}
$provider ??= $providers[0];
$providerId = (int) $provider['id'];

// ---------------------------------------------------------------- import --
if (($params[0] ?? '') === 'run' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $picked = array_values(array_filter((array) ($_POST['service'] ?? [])));
    $markup = max(0, (float) ($_POST['markup'] ?? setting('default_markup', 35)));
    $auto   = isset($_POST['auto_detect']);
    $update = isset($_POST['update_existing']);

    if (!$picked) {
        flash('error', 'Tick at least one service to import.');
        redirect('admin/import?provider_id=' . $providerId);
    }

    $catalogue = import_catalogue($provider, false);
    if (isset($catalogue['error'])) {
        flash('error', 'Could not read the provider catalogue: ' . $catalogue['error']);
        redirect('admin/import?provider_id=' . $providerId);
    }

    $byId = [];
    foreach ($catalogue['services'] as $item) {
        $byId[(string) $item['service']] = $item;
    }

    // One conversion for the whole import.
    $providerRate = ($provider['currency'] ?? '') === '' ? 1.0 : currency_rate((string) $provider['currency']);
    if ($providerRate <= 0) {
        flash('error', 'No exchange rate for ' . $provider['currency']
            . '. Add it under Currencies, then import again.');
        redirect('admin/import?provider_id=' . $providerId);
    }

    $created = $updated = $skipped = 0;

    foreach ($picked as $serviceId) {
        $item = $byId[(string) $serviceId] ?? null;
        if (!$item) {
            $skipped++;
            continue;
        }

        $existing = one('SELECT * FROM services WHERE provider_id = ? AND provider_service_id = ?',
            [$providerId, (string) $serviceId]);

        if ($existing && !$update) {
            $skipped++;
            continue;
        }

        // The provider's rate is in its own currency; convert before marking up.
        $rawRate = (float) ($item['rate'] ?? 0);
        $cost    = round($rawRate * $providerRate, 4);
        $price   = round($cost * (1 + $markup / 100), 4);

        $fields = [
            'provider_rate'  => $rawRate,
            'markup_percent' => $markup,
            'cost_per_1000'  => $cost,
            'price_per_1000' => $price,
            'min_qty'        => max(1, (int) ($item['min'] ?? 100)),
            'max_qty'        => max(1, (int) ($item['max'] ?? 100000)),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        if ($auto) {
            $guess      = detect_service((string) ($item['name'] ?? ''), (string) ($item['category'] ?? ''));
            $platformId = platform_id_for($guess['platform']);
            $fields['platform_id'] = $platformId;
            $fields['category_id'] = category_id_for($platformId, $guess['category']);
            $fields['supports_refill'] = $guess['refill'] ? 1 : 0;
        }

        if ($existing) {
            // Keep the name and description an admin may have rewritten.
            update_row('services', $fields, 'id = ?', [$existing['id']]);
            $updated++;
        } else {
            insert_row('services', $fields + [
                'provider_id'         => $providerId,
                'provider_service_id' => (string) $serviceId,
                'name'                => mb_substr((string) ($item['name'] ?? 'Service'), 0, 190),
                'description'         => '',
                'delivery_time'       => '',
                'is_active'           => 1,
                'created_at'          => date('Y-m-d H:i:s'),
            ]);
            $created++;
        }
    }

    $parts = [];
    if ($created) { $parts[] = $created . ' added'; }
    if ($updated) { $parts[] = $updated . ' updated'; }
    if ($skipped) { $parts[] = $skipped . ' skipped'; }
    flash($created || $updated ? 'success' : 'warning',
        'Import finished: ' . ($parts ? implode(', ', $parts) : 'nothing to do') . '.');

    redirect('admin/services?provider_id=' . $providerId);
}

// ----------------------------------------------------------------- view ---
$refresh = ($params[0] ?? '') === 'refresh';

if (!$asked && !$refresh) {
    view('admin/import', [
        'title'     => 'Import services',
        'subtitle'  => 'Pull a provider catalogue in',
        'providers' => $providers,
        'provider'  => null,
        'rows'      => [],
        'categories'=> [],
        'search'    => '',
        'category'  => '',
        'markup'    => (float) setting('default_markup', 35),
        'autoDetect'=> true,
        'onlyNew'   => false,
        'total'     => 0,
        'cachedAt'  => null,
        'error'     => null,
        'billing'   => '',
        'baseCode'  => strtoupper(base_currency()['code']),
        'rate'      => 0.0,
        'currencyProblem' => null,
    ], 'layouts/admin');
    return;
}

// What the provider bills in, and whether we can turn that into our money.
// Without a rate every figure on this page would be the provider's own
// number wearing our currency symbol, which is worse than showing nothing.
$billing  = strtoupper(trim((string) ($provider['currency'] ?? '')));
$rate     = $billing === '' ? 0.0 : currency_rate($billing);
$baseCode = strtoupper(base_currency()['code']);

$currencyProblem = null;
if ($billing === '') {
    $currencyProblem = [
        'code'   => '',
        'title'  => 'We do not know what ' . $provider['name'] . ' bills in',
        'detail' => 'Run Check on the provider, or set the currency on its form. Until then '
                  . 'its rates cannot be converted into ' . $baseCode . '.',
    ];
} elseif ($rate <= 0) {
    $currencyProblem = [
        'code'   => $billing,
        'title'  => $billing . ' is not in your currencies',
        'detail' => $provider['name'] . ' prices in ' . $billing . ', and there is no '
                  . $billing . ' rate to convert that into ' . $baseCode . '.',
    ];
}

$catalogue = $currencyProblem ? ['services' => []] : import_catalogue($provider, $refresh);

if ($refresh) {
    isset($catalogue['error'])
        ? flash('error', 'Provider did not answer: ' . $catalogue['error'])
        : flash('success', 'Catalogue refreshed - ' . qty_fmt(count($catalogue['services'])) . ' services.');
    redirect('admin/import?provider_id=' . $providerId);
}

$search     = trim((string) ($_GET['q'] ?? ''));
$category   = (string) ($_GET['category'] ?? '');
$markup     = (float) ($_GET['markup'] ?? setting('default_markup', 35));
$autoDetect = !isset($_GET['auto_detect']) || $_GET['auto_detect'] === '1';
$onlyNew    = ($_GET['only_new'] ?? '') === '1';

$services   = $catalogue['services'] ?? [];
$categories = [];
foreach ($services as $item) {
    $name = (string) ($item['category'] ?? '');
    if ($name !== '') {
        $categories[$name] = $name;
    }
}
ksort($categories);

// Which of these do we already have?
$have = [];
foreach (all('SELECT provider_service_id FROM services WHERE provider_id = ?', [$providerId]) as $row) {
    $have[(string) $row['provider_service_id']] = true;
}

$rows = [];
foreach ($services as $item) {
    $name = (string) ($item['name'] ?? '');
    $cat  = (string) ($item['category'] ?? '');

    if ($search !== '' && stripos($name . ' ' . $cat, $search) === false) { continue; }
    if ($category !== '' && $cat !== $category)                            { continue; }

    $exists = isset($have[(string) $item['service']]);
    if ($onlyNew && $exists) { continue; }

    $guess = $autoDetect ? detect_service($name, $cat) : ['platform' => null, 'category' => null];

    $rows[] = [
        'id'       => (string) $item['service'],
        'name'     => $name,
        'category' => $cat,
        'cost'     => round((float) ($item['rate'] ?? 0) * $rate, 4),
        'raw'      => (float) ($item['rate'] ?? 0),
        'min'      => (int) ($item['min'] ?? 0),
        'max'      => (int) ($item['max'] ?? 0),
        'exists'   => $exists,
        'platform' => $guess['platform'],
        'kind'     => $guess['category'],
    ];
    if (count($rows) >= 400) {   // keep the page usable
        break;
    }
}

view('admin/import', [
    'title'       => 'Import Services',
    'subtitle'    => 'Pull services from a provider',
    'providers'   => $providers,
    'provider'    => $provider,
    'rows'        => $rows,
    'total'       => count($services),
    'categories'  => $categories,
    'search'      => $search,
    'category'    => $category,
    'markup'      => $markup,
    'autoDetect'  => $autoDetect,
    'onlyNew'     => $onlyNew,
    'cachedAt'    => $catalogue['cached_at'] ?? null,
    'error'       => $catalogue['error'] ?? null,
    'billing'     => $billing,
    'baseCode'    => $baseCode,
    'rate'        => $rate,
    'currencyProblem' => $currencyProblem,
], 'layouts/admin');


// ===========================================================================

/**
 * Provider catalogue, from disk when it is fresh enough.
 *
 * @return array{services: array, cached_at: ?int, error?: string}
 */
function import_catalogue(array $provider, bool $force): array
{
    $file = STORAGE_PATH . '/cache/provider-' . (int) $provider['id'] . '.json';

    if (!$force && is_file($file) && (time() - filemtime($file)) < IMPORT_CACHE_TTL) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached)) {
            return ['services' => $cached, 'cached_at' => filemtime($file)];
        }
    }

    $api      = new SmmApi($provider['api_url'], $provider['api_key']);
    $response = $api->services();

    if (isset($response['error'])) {
        // Fall back to whatever we cached before, so the screen still works.
        if (is_file($file)) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached)) {
                return ['services' => $cached, 'cached_at' => filemtime($file), 'error' => $response['error']];
            }
        }
        return ['services' => [], 'cached_at' => null, 'error' => $response['error']];
    }

    $services = [];
    foreach ($response as $item) {
        if (is_array($item) && isset($item['service'])) {
            $services[] = $item;
        }
    }

    if (!is_dir(dirname($file))) {
        @mkdir(dirname($file), 0775, true);
    }
    @file_put_contents($file, json_encode($services));

    return ['services' => $services, 'cached_at' => time()];
}
