<?php
/**
 * A fake SMM provider, for testing without spending real money.
 *
 *   php -S 127.0.0.1:8001 tools/mock-provider.php
 *
 * Then add a provider in the admin with
 *   API URL  http://127.0.0.1:8001/
 *   API key  anything
 *
 * It speaks the usual API v2 dialect: services, add, status, multi-status,
 * balance, refill, cancel. Orders are kept in a JSON file next to this script
 * and each status call nudges them further along, so an order placed here
 * really does walk pending -> in progress -> completed.
 *
 * Never deploy this. It is excluded from the release zip.
 */

declare(strict_types=1);

const STORE = __DIR__ . '/mock-orders.json';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'services': echo json_encode(mock_services(), JSON_PRETTY_PRINT); break;
    case 'balance':  echo json_encode(['balance' => '4821.37', 'currency' => 'USD']); break;
    case 'add':      echo json_encode(mock_add()); break;
    case 'status':   echo json_encode(mock_status()); break;
    case 'refill':   echo json_encode(['refill' => random_int(100000, 999999)]); break;
    case 'cancel':   echo json_encode(mock_cancel()); break;
    default:         echo json_encode(['error' => 'Unknown action']);
}

// ---------------------------------------------------------------------------

function load(): array
{
    return is_file(STORE) ? (json_decode((string) file_get_contents(STORE), true) ?: []) : [];
}

function save(array $orders): void
{
    file_put_contents(STORE, json_encode($orders, JSON_PRETTY_PRINT), LOCK_EX);
}

/** ~40 services across several platforms, with realistic category names. */
function mock_services(): array
{
    $catalogue = [
        ['Instagram Followers', [
            ['Instagram Followers | Non Drop | Max 100K | Instant', 0.41, 100, 100000],
            ['Instagram Followers | Premium Quality | Real Looking', 0.64, 100, 50000],
            ['Instagram Followers | Indian | HQ', 0.76, 100, 20000],
            ['Instagram Followers | Real & Active | Slow Delivery', 1.21, 50, 20000],
            ['Instagram Followers | Cheapest | No Refill', 0.28, 100, 500000],
        ]],
        ['Instagram Likes', [
            ['Instagram Likes | Instant Start | Non Drop', 0.10, 50, 50000],
            ['Instagram Likes | Real Profiles', 0.22, 50, 20000],
            ['Instagram Story Views | Fast', 0.06, 100, 100000],
            ['Instagram Reels Views | High Retention', 0.03, 500, 1000000],
            ['Instagram Comments | Custom Text', 2.80, 10, 5000],
        ]],
        ['TikTok Followers', [
            ['TikTok Followers | Non Drop | Max 100K', 0.48, 100, 100000],
            ['TikTok Followers | Real & Active | Slow', 0.94, 50, 20000],
            ['TikTok Followers | Cheap | Mixed', 0.31, 100, 200000],
            ['TikTok Likes | Instant | Non Drop', 0.21, 50, 50000],
            ['TikTok Video Views | Super Fast', 0.02, 1000, 10000000],
            ['TikTok Shares | Worldwide', 0.18, 100, 50000],
        ]],
        ['YouTube Views', [
            ['YouTube Views | High Retention | Non Drop', 0.68, 500, 1000000],
            ['YouTube Views | Cheap | Fast Start', 0.44, 1000, 500000],
            ['YouTube Likes | Worldwide', 0.24, 50, 50000],
            ['YouTube Subscribers | Non Drop | 30 Day Refill', 1.90, 100, 20000],
            ['YouTube Watch Hours | 4000 Hours Package', 48.00, 1000, 10000],
            ['YouTube Shorts Views | Instant', 0.09, 1000, 1000000],
        ]],
        ['Facebook Services', [
            ['Facebook Page Followers | Non Drop', 0.45, 100, 50000],
            ['Facebook Post Likes | Fast', 0.20, 50, 30000],
            ['Facebook Video Views | 3 Second', 0.05, 500, 500000],
            ['Facebook Group Members | Worldwide', 0.88, 100, 20000],
            ['Facebook Page Likes | Real Looking', 0.52, 100, 50000],
        ]],
        ['Telegram Members', [
            ['Telegram Members | Non Drop | Cheap', 0.34, 100, 100000],
            ['Telegram Members | Real & Active', 1.05, 50, 30000],
            ['Telegram Reactions | Mixed Positive', 0.17, 20, 50000],
            ['Telegram Post Views | Last 5 Posts', 0.01, 500, 500000],
            ['Telegram Channel Subscribers | Instant', 0.39, 100, 80000],
        ]],
        ['Twitter / X', [
            ['Twitter Followers | Non Drop | HQ', 0.62, 100, 25000],
            ['Twitter Likes | Instant Start', 0.29, 50, 20000],
            ['Twitter Retweets | Worldwide', 0.33, 50, 20000],
            ['Twitter Post Views | Fast', 0.07, 500, 500000],
        ]],
        ['Other Services', [
            ['Spotify Plays | Premium Accounts', 0.09, 1000, 500000],
            ['Spotify Followers | Real', 0.56, 100, 50000],
            ['Discord Members | Online', 1.40, 50, 10000],
            ['Website Traffic | Google Referral', 0.04, 1000, 1000000],
        ]],
    ];

    $services = [];
    $id = 1001;
    foreach ($catalogue as [$category, $items]) {
        foreach ($items as [$name, $rate, $min, $max]) {
            $services[] = [
                'service'  => (string) $id++,
                'name'     => $name,
                'type'     => 'Default',
                'category' => $category,
                'rate'     => number_format($rate * 100, 2, '.', ''),  // per 1000, in cents-ish
                'min'      => (string) $min,
                'max'      => (string) $max,
                'refill'   => str_contains(strtolower($name), 'refill')
                           || str_contains(strtolower($name), 'non drop'),
                'cancel'   => true,
            ];
        }
    }
    return $services;
}

function mock_add(): array
{
    $service  = (string) ($_POST['service'] ?? '');
    $link     = (string) ($_POST['link'] ?? '');
    $quantity = (int) ($_POST['quantity'] ?? 0);

    if ($service === '' || $link === '') {
        return ['error' => 'Incorrect service ID or link'];
    }
    if ($quantity <= 0) {
        return ['error' => 'Incorrect quantity'];
    }
    // A way to exercise the error path on purpose.
    if (str_contains($link, 'fail-me')) {
        return ['error' => 'Not enough funds on your balance'];
    }

    $orders = load();
    $id     = (string) random_int(10000000, 99999999);

    $orders[$id] = [
        'service'     => $service,
        'link'        => $link,
        'quantity'    => $quantity,
        'start_count' => random_int(500, 50000),
        'delivered'   => 0,
        'checks'      => 0,
        'status'      => 'Pending',
        'charge'      => number_format($quantity * 0.0004, 4, '.', ''),
    ];
    save($orders);

    return ['order' => $id];
}

/** Each status call moves the order on, so a few calls complete it. */
function mock_status(): array
{
    $orders = load();

    $ids = isset($_POST['orders'])
        ? array_filter(array_map('trim', explode(',', (string) $_POST['orders'])))
        : [trim((string) ($_POST['order'] ?? ''))];

    $result = [];
    foreach ($ids as $id) {
        if (!isset($orders[$id])) {
            $result[$id] = ['error' => 'Incorrect order ID'];
            continue;
        }

        $order = &$orders[$id];
        $order['checks']++;

        if ($order['status'] !== 'Canceled' && $order['status'] !== 'Completed') {
            $order['status']    = $order['checks'] === 1 ? 'In progress' : $order['status'];
            $order['delivered'] = min($order['quantity'],
                (int) round($order['quantity'] * min(1, $order['checks'] / 3)));
            if ($order['delivered'] >= $order['quantity']) {
                $order['status'] = 'Completed';
            } elseif ($order['checks'] > 1) {
                $order['status'] = 'In progress';
            }
        }

        $result[$id] = [
            'charge'      => $order['charge'],
            'start_count' => (string) $order['start_count'],
            'status'      => $order['status'],
            'remains'     => (string) max(0, $order['quantity'] - $order['delivered']),
            'currency'    => 'USD',
        ];
        unset($order);
    }
    save($orders);

    // A single-id request gets the flat shape most providers use.
    return count($ids) === 1 ? reset($result) : $result;
}

function mock_cancel(): array
{
    $orders = load();
    $ids    = array_filter(array_map('trim', explode(',', (string) ($_POST['orders'] ?? ''))));

    $out = [];
    foreach ($ids as $id) {
        if (isset($orders[$id])) {
            $orders[$id]['status'] = 'Canceled';
            $out[] = ['order' => $id, 'cancel' => ['status' => 'Success']];
        } else {
            $out[] = ['order' => $id, 'cancel' => ['error' => 'Incorrect order ID']];
        }
    }
    save($orders);
    return $out;
}
