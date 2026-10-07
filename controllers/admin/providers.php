<?php
/** Providers: the SMM APIs we buy from. */
require_once APP_PATH . '/helpers/crud.php';
require_once APP_PATH . '/helpers/orders.php';

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
    'form_note'=> 'The API URL is usually the provider\'s /api/v2 endpoint. Your key is stored as given '
                . 'and only ever sent to that provider.',
    'columns'  => [
        ['label' => 'Provider', 'key' => 'name', 'render' => fn($r) =>
            '<b>' . e($r['name']) . '</b><small class="sub">' . e($r['api_url']) . '</small>'],
        ['label' => 'Balance', 'class' => 'hide-sm', 'render' => fn($r) =>
            '<b>' . e(money($r['balance'])) . '</b><small class="sub">'
            . ($r['balance_checked_at'] ? e(when($r['balance_checked_at'], 'd M, H:i')) : 'never checked')
            . '</small>'],
        ['label' => 'Services', 'class' => 'hide-sm', 'render' => fn($r) =>
            qty_fmt(col('SELECT COUNT(*) FROM services WHERE provider_id = ?', [$r['id']], 0))],
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
