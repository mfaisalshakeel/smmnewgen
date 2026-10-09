<?php
/** Providers: the SMM APIs we buy from. */
require_once APP_PATH . '/helpers/crud.php';
require_once APP_PATH . '/helpers/orders.php';
require_once APP_PATH . '/helpers/sync.php';
require_once APP_PATH . '/helpers/currency.php';

// "Check balances" asks every active provider and stores what comes back.
if (($params[0] ?? '') === 'balances' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated = refresh_provider_balances();
    $total   = (int) col('SELECT COUNT(*) FROM providers WHERE is_active = 1', [], 0);
    $updated === $total && $total > 0
        ? flash('success', 'Balances updated for all ' . $total . ' active providers.')
        : flash($updated ? 'warning' : 'error',
            $updated . ' of ' . $total . ' providers answered. Check storage/logs for the rest.');
    redirect('admin/providers');
}

// "Check" asks the provider everything worth knowing in one go: does the key
// work, what does it bill in, how many services does it offer, and can it
// answer a multi-order status request.
if (($params[0] ?? '') === 'check' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = one('SELECT * FROM providers WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);

    if (!$provider) {
        flash('error', 'That provider no longer exists.');
        redirect('admin/providers');
    }

    $result = provider_check($provider);

    foreach ($result['checks'] as $check) {
        flash($check['ok'] ? 'success' : 'warning', $check['label'] . ': ' . $check['detail']);
    }

    redirect('admin/providers');
}

// Ask one provider, on its own, whether it answers a batch status request.
// Part of Check as well, but worth its own button: it is the one answer that
// changes how every later status sync is made, and an admin who has just
// overridden it by hand wants to put it back without re-running everything.
if (($params[0] ?? '') === 'multistatus' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = one('SELECT * FROM providers WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);

    if (!$provider) {
        flash('error', 'That provider no longer exists.');
        redirect('admin/providers');
    }

    $probe = multi_status_probe($provider);

    if ($probe['supported'] === null) {
        flash('warning', $provider['name'] . ': ' . $probe['detail']);
    } else {
        update_row('providers', ['supports_multi_status' => $probe['supported'] ? 1 : 0],
            'id = ?', [$provider['id']]);
        flash('success', $provider['name'] . ': multiple status in one request is '
            . ($probe['supported'] ? 'supported' : 'not supported') . '. ' . $probe['detail']);
    }

    redirect('admin/providers');
}

// Sync one provider's catalogue now.
if (($params[0] ?? '') === 'sync' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = one('SELECT * FROM providers WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);

    if (!$provider) {
        flash('error', 'That provider no longer exists.');
        redirect('admin/providers');
    }

    $result = sync_provider_services($provider);

    $result['error'] === null
        ? flash('success', sprintf('%s: %d service(s) checked, %d updated, %d no longer offered.',
            $provider['name'], $result['checked'], $result['updated'], $result['missing']))
        : flash('error', $provider['name'] . ': ' . $result['error']);

    redirect('admin/providers');
}

$currencyOptions = [];
foreach (currencies() as $code => $currency) {
    $currencyOptions[$code] = $code . ' - ' . $currency['name'];
}

crud_handle([
    'table'    => 'providers',
    'base'     => 'admin/providers',
    'title'    => 'Providers',
    'single'   => 'Provider',
    'subtitle' => 'The SMM APIs your services are bought from',
    'order'    => 'sort_order, id',
    'search'   => ['name', 'api_url'],
    'search_hint' => 'Search provider name or URL',
    'toggle'   => 'is_active',
    'timestamps' => true,
    'empty'    => 'No providers yet. Add one, then use Import Services to pull in its catalogue.',
    'actions'  => [['label' => 'Check balances', 'href' => 'admin/providers/balances', 'post' => true]],
    'row_actions' => function (array $r): string {
        $form = static function (string $path, string $label, string $icon, string $tone) use ($r): string {
            return '<form method="post" action="' . e(url($path)) . '">'
                 . csrf_field()
                 . '<input type="hidden" name="id" value="' . (int) $r['id'] . '">'
                 . '<button class="iact ' . $tone . '" type="submit" title="' . e($label) . '"'
                 . ' aria-label="' . e($label) . '">'
                 . '<svg class="icon"><use href="#' . $icon . '"></use></svg></button></form>';
        };
        return $form('admin/providers/check', 'Check the connection', 'i-shield', 'iact-go')
             . $form('admin/providers/multistatus', 'Ask about multi-status', 'i-list', 'iact-go')
             . $form('admin/providers/sync', 'Sync the catalogue', 'i-refresh', 'iact-go');
    },
    'form_note'=> 'The API URL is usually the provider\'s /api/v2 endpoint. Your key is stored as given '
                . 'and only ever sent to that provider.',
    'columns'  => [
        ['label' => 'Provider', 'key' => 'name', 'render' => fn($r) =>
            '<b>' . e($r['name']) . '</b><small class="sub">' . e($r['api_url']) . '</small>'],
        ['label' => 'Balance', 'class' => 'hide-sm', 'render' => fn($r) =>
            '<b>' . e(money($r['balance'])) . '</b><small class="sub">'
            . ($r['balance_checked_at'] ? e(when($r['balance_checked_at'], 'd M, H:i')) : 'never checked')
            . '</small>'],
        ['label' => 'Services', 'class' => 'hide-sm', 'render' => function ($r) {
            $n = (int) col('SELECT COUNT(*) FROM services WHERE provider_id = ?', [$r['id']], 0);
            $synced = $r['last_sync_at'] ? 'synced ' . when($r['last_sync_at'], 'd M, H:i') : 'never synced';
            return qty_fmt($n) . '<small class="sub">' . e($synced) . '</small>';
        }],
        ['label' => 'API', 'class' => 'hide-sm', 'render' => function ($r) {
            $bits = [];
            $bits[] = $r['currency'] !== ''
                ? '<span class="chipx">' . e($r['currency']) . '</span>'
                : '<span class="muted">currency unknown</span>';
            if ($r['supports_multi_status'] === null) {
                $bits[] = '<small class="sub">multi-status not checked</small>';
            } else {
                $bits[] = '<small class="sub">multi-status ' .
                    ($r['supports_multi_status'] ? 'supported' : 'not supported') . '</small>';
            }
            if ($r['last_error'] !== '') {
                $bits[] = '<small class="sub" style="color:var(--danger)">' . e(excerpt($r['last_error'], 46)) . '</small>';
            }
            return implode('', $bits);
        }],
        ['label' => 'State', 'render' => fn($r) =>
            '<span class="st st-' . ($r['is_active'] ? 'completed' : 'cancelled') . '">'
            . ($r['is_active'] ? 'active' : 'off') . '</span>'],
    ],
    'fields' => [
        'name'       => ['label' => 'Name', 'required' => true,
                         'hint' => 'Just for you - customers never see it.'],
        'api_url'    => ['label' => 'API URL', 'required' => true, 'rules' => ['url'],
                         'placeholder' => 'https://provider.example/api/v2'],
        'api_key'    => ['label' => 'API key', 'type' => 'password', 'skip_empty' => true,
                         'required' => true, 'hint' => 'Leave empty when editing to keep the current key.'],
        'currency'   => ['label' => 'Bills in', 'type' => 'select', 'options' => $currencyOptions,
                         'empty' => 'Detect from the balance reply',
                         'hint' => 'Its rates are converted from this into your base currency before '
                                 . 'your markup is applied.'],
        'supports_multi_status' => ['label' => 'Multiple status in one request', 'type' => 'select',
                         'options' => [1 => 'Supported', 0 => 'Not supported'],
                         'empty' => 'Work it out automatically', 'nullable' => true,
                         'hint' => 'Most providers take up to 100 order ids at once; some only one. '
                                 . 'Leave it automatic and press Multi-status on the list to ask the '
                                 . 'provider - Check asks as well.'],
        'auto_sync'  => ['label' => 'Include in the scheduled service sync', 'type' => 'checkbox', 'default' => 1],
        'sort_order' => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
        'is_active'  => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1,
                         'hint' => 'Inactive providers are skipped by import, sending and status sync.'],
    ],
    'can_delete' => function (int $id) {
        $used = (int) col('SELECT COUNT(*) FROM services WHERE provider_id = ?', [$id], 0);
        return $used > 0
            ? "This provider still has {$used} service(s). Delete or reassign them first."
            : null;
    },
], $params);
