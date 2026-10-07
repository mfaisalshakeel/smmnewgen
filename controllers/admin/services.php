<?php
/**
 * Services: what customers actually buy.
 *
 * Bigger than the shared CRUD can express, because of the bulk actions and
 * because a service may be manual (no provider) as well as imported.
 */
require_once APP_PATH . '/helpers/crud.php';
require_once APP_PATH . '/helpers/orders.php';

$action = $params[0] ?? 'index';

// ---------------------------------------------------------------- bulk ----
if ($action === 'bulk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids  = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    $what = (string) ($_POST['bulk_action'] ?? '');

    if (!$ids) {
        flash('error', 'Select at least one service first.');
        redirect('admin/services');
    }

    $in     = implode(',', array_fill(0, count($ids), '?'));
    $count  = count($ids);
    $noun   = $count === 1 ? 'service' : 'services';

    switch ($what) {
        case 'activate':
            q("UPDATE services SET is_active = 1 WHERE id IN ($in)", $ids);
            flash('success', "Activated {$count} {$noun}.");
            break;

        case 'deactivate':
            q("UPDATE services SET is_active = 0 WHERE id IN ($in)", $ids);
            flash('success', "Deactivated {$count} {$noun}.");
            break;

        case 'feature':
            q("UPDATE services SET is_featured = 1 - is_featured WHERE id IN ($in)", $ids);
            flash('success', "Toggled 'most popular' on {$count} {$noun}.");
            break;

        case 'markup':
            $markup = (float) ($_POST['bulk_markup'] ?? 0);
            if ($markup < 0) {
                flash('error', 'Markup cannot be negative.');
                break;
            }
            q("UPDATE services SET price_per_1000 = ROUND(cost_per_1000 * (1 + ? / 100), 4),
                      updated_at = ? WHERE id IN ($in)",
                array_merge([$markup, date('Y-m-d H:i:s')], $ids));
            flash('success', "Applied a {$markup}% markup to {$count} {$noun}.");
            break;

        case 'sync':
            [$synced, $failed] = sync_service_prices($ids);
            $failed === 0
                ? flash('success', "Refreshed prices for {$synced} {$noun} from their providers.")
                : flash('warning', "Refreshed {$synced}, could not reach the provider for {$failed}.");
            break;

        case 'delete':
            $used = (int) col("SELECT COUNT(*) FROM orders WHERE service_id IN ($in)", $ids, 0);
            if ($used > 0) {
                q("UPDATE services SET is_active = 0 WHERE id IN ($in)", $ids);
                flash('warning', "{$used} order(s) point at these services, so they were deactivated "
                    . 'rather than deleted. Order history stays intact.');
            } else {
                q("DELETE FROM services WHERE id IN ($in)", $ids);
                flash('success', "Deleted {$count} {$noun}.");
            }
            break;

        default:
            flash('error', 'Pick an action from the list.');
    }

    redirect('admin/services' . build_query($_POST['return'] ?? ''));
}

// ---------------------------------------------------------------- save ----
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int) ($_POST['id'] ?? 0);
    $errors = [];

    $name  = trim((string) ($_POST['name'] ?? ''));
    $min   = max(1, (int) ($_POST['min_qty'] ?? 1));
    $max   = max(1, (int) ($_POST['max_qty'] ?? 1));
    $price = (float) ($_POST['price_per_1000'] ?? 0);
    $cost  = (float) ($_POST['cost_per_1000'] ?? 0);

    if ($name === '')        { $errors['name'] = 'A name is required.'; }
    if ($max < $min)         { $errors['max_qty'] = 'Maximum must be at least the minimum.'; }
    if ($price <= 0)         { $errors['price_per_1000'] = 'Set a selling price above zero.'; }

    $providerId = (int) ($_POST['provider_id'] ?? 0) ?: null;
    $providerServiceId = trim((string) ($_POST['provider_service_id'] ?? ''));

    if ($providerId && $providerServiceId === '') {
        $errors['provider_service_id'] = 'A provider service id is needed when a provider is set.';
    }
    if ($providerId && $providerServiceId !== '') {
        $clash = $id
            ? col('SELECT 1 FROM services WHERE provider_id = ? AND provider_service_id = ? AND id <> ?',
                  [$providerId, $providerServiceId, $id])
            : col('SELECT 1 FROM services WHERE provider_id = ? AND provider_service_id = ?',
                  [$providerId, $providerServiceId]);
        if ($clash) {
            $errors['provider_service_id'] = 'That provider service is already in your catalogue.';
        }
    }

    if ($errors) {
        keep_old($_POST);
        $_SESSION['_errors'] = $errors;
        flash('error', 'Please fix the highlighted fields.');
        redirect('admin/services/' . ($id ? 'edit/' . $id : 'new'));
    }

    $data = [
        'provider_id'         => $providerId,
        'provider_service_id' => $providerId ? $providerServiceId : '',
        'platform_id'         => (int) ($_POST['platform_id'] ?? 0) ?: null,
        'category_id'         => (int) ($_POST['category_id'] ?? 0) ?: null,
        'name'                => $name,
        'description'         => trim((string) ($_POST['description'] ?? '')),
        'badge'               => trim((string) ($_POST['badge'] ?? '')),
        'features'            => trim((string) ($_POST['features'] ?? '')),
        'cost_per_1000'       => $cost,
        'price_per_1000'      => $price,
        'min_qty'             => $min,
        'max_qty'             => $max,
        'delivery_time'       => trim((string) ($_POST['delivery_time'] ?? '')),
        'supports_refill'     => isset($_POST['supports_refill']) ? 1 : 0,
        'supports_cancel'     => isset($_POST['supports_cancel']) ? 1 : 0,
        'is_featured'         => isset($_POST['is_featured']) ? 1 : 0,
        'is_active'           => isset($_POST['is_active']) ? 1 : 0,
        'sort_order'          => (int) ($_POST['sort_order'] ?? 0),
        'updated_at'          => date('Y-m-d H:i:s'),
    ];

    if ($id > 0) {
        update_row('services', $data, 'id = ?', [$id]);
        flash('success', 'Service updated.');
    } else {
        $data['created_at'] = date('Y-m-d H:i:s');
        insert_row('services', $data);
        flash('success', 'Service created.');
    }
    redirect('admin/services');
}

// -------------------------------------------------------------- delete ----
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int) ($_POST['id'] ?? 0);
    $used = (int) col('SELECT COUNT(*) FROM orders WHERE service_id = ?', [$id], 0);
    if ($used > 0) {
        update_row('services', ['is_active' => 0], 'id = ?', [$id]);
        flash('warning', "That service has {$used} order(s) against it, so it was deactivated "
            . 'rather than deleted.');
    } else {
        delete_row('services', 'id = ?', [$id]);
        flash('success', 'Service deleted.');
    }
    redirect('admin/services');
}

// ---------------------------------------------------------------- form ----
if ($action === 'new' || $action === 'edit') {
    $id  = (int) ($params[1] ?? 0);
    $row = [];

    if ($id > 0) {
        $row = one('SELECT * FROM services WHERE id = ?', [$id]);
        if (!$row) {
            flash('error', 'That service no longer exists.');
            redirect('admin/services');
        }
    } else {
        $row = [
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => 1,
            'delivery_time' => '2-15 min', 'cost_per_1000' => 0, 'price_per_1000' => 0,
        ];
    }

    view('admin/service-form', [
        'title'      => $id ? 'Edit service' : 'New service',
        'subtitle'   => 'Services',
        'row'        => $row,
        'id'         => $id,
        'providers'  => options_from('providers'),
        'platforms'  => options_from('platforms'),
        'categories' => all('SELECT c.id, c.name, c.platform_id, p.name AS platform
                               FROM categories c JOIN platforms p ON p.id = c.platform_id
                           ORDER BY p.sort_order, c.sort_order'),
        'errors'     => crud_errors(),
    ], 'layouts/admin');
}

// ---------------------------------------------------------------- list ----
$filters = [
    'q'           => trim((string) ($_GET['q'] ?? '')),
    'platform_id' => (string) ($_GET['platform_id'] ?? ''),
    'category_id' => (string) ($_GET['category_id'] ?? ''),
    'provider_id' => (string) ($_GET['provider_id'] ?? ''),
    'state'       => (string) ($_GET['state'] ?? ''),
];

$where  = [];
$binds  = [];

if ($filters['q'] !== '') {
    $where[] = '(s.name LIKE ? OR s.provider_service_id = ?)';
    $binds[] = '%' . $filters['q'] . '%';
    $binds[] = $filters['q'];
}
foreach (['platform_id', 'category_id', 'provider_id'] as $key) {
    if ($filters[$key] !== '') {
        $where[] = "s.$key = ?";
        $binds[] = $filters[$key];
    }
}
if ($filters['state'] === 'active')   { $where[] = 's.is_active = 1'; }
if ($filters['state'] === 'inactive') { $where[] = 's.is_active = 0'; }
if ($filters['state'] === 'manual')   { $where[] = 's.provider_id IS NULL'; }

$sql = 'SELECT s.*, p.name AS platform_name, c.name AS category_name, pr.name AS provider_name
          FROM services s
          LEFT JOIN platforms p  ON p.id  = s.platform_id
          LEFT JOIN categories c ON c.id  = s.category_id
          LEFT JOIN providers pr ON pr.id = s.provider_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.sort_order, c.sort_order, s.sort_order, s.id';

view('admin/services', [
    'title'      => 'Services',
    'subtitle'   => 'What customers can buy',
    'services'   => all($sql, $binds),
    'filters'    => $filters,
    'platforms'  => options_from('platforms'),
    'categories' => options_from('categories'),
    'providers'  => options_from('providers'),
], 'layouts/admin');


// ===========================================================================

/** Keep the current filters when bouncing back to the list. */
function build_query(string $raw): string
{
    $raw = ltrim($raw, '?');
    return $raw === '' ? '' : '?' . $raw;
}

/**
 * Re-read the provider catalogue and update cost (and price, keeping each
 * service's own markup) for the given service ids.
 *
 * @return array{0:int,1:int} [synced, failed]
 */
function sync_service_prices(array $ids): array
{
    if (!$ids) {
        return [0, 0];
    }

    $in       = implode(',', array_fill(0, count($ids), '?'));
    $services = all("SELECT * FROM services WHERE id IN ($in) AND provider_id IS NOT NULL", $ids);

    $byProvider = [];
    foreach ($services as $service) {
        $byProvider[(int) $service['provider_id']][] = $service;
    }

    $synced = 0;
    $failed = 0;

    foreach ($byProvider as $providerId => $group) {
        $provider = one('SELECT * FROM providers WHERE id = ?', [$providerId]);
        if (!$provider) {
            $failed += count($group);
            continue;
        }

        $api      = new SmmApi($provider['api_url'], $provider['api_key']);
        $response = $api->services();

        if (isset($response['error']) || !is_array($response)) {
            $failed += count($group);
            log_line('Price sync failed for ' . $provider['name'] . ': '
                . ($response['error'] ?? 'unreadable reply'));
            continue;
        }

        $remote = [];
        foreach ($response as $item) {
            if (is_array($item) && isset($item['service'])) {
                $remote[(string) $item['service']] = $item;
            }
        }

        foreach ($group as $service) {
            $match = $remote[(string) $service['provider_service_id']] ?? null;
            if (!$match) {
                $failed++;
                continue;
            }

            $newCost = (float) ($match['rate'] ?? $service['cost_per_1000']);
            $oldCost = (float) $service['cost_per_1000'];

            // Keep whatever markup this service already carried.
            $markup  = $oldCost > 0
                ? ((float) $service['price_per_1000'] / $oldCost)
                : (1 + (float) setting('default_markup', 35) / 100);

            update_row('services', [
                'cost_per_1000'  => $newCost,
                'price_per_1000' => round($newCost * $markup, 4),
                'min_qty'        => max(1, (int) ($match['min'] ?? $service['min_qty'])),
                'max_qty'        => max(1, (int) ($match['max'] ?? $service['max_qty'])),
                'updated_at'     => date('Y-m-d H:i:s'),
            ], 'id = ?', [$service['id']]);

            $synced++;
        }
    }

    return [$synced, $failed];
}
