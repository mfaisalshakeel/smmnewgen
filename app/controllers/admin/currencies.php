<?php
/** Currencies, and the rates that convert provider prices into yours. */
require_once APP_PATH . '/helpers/crud.php';
require_once APP_PATH . '/helpers/currency.php';

// --- refresh rates from the public feed -------------------------------------
if (($params[0] ?? '') === 'refresh' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = refresh_currency_rates();
    flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('admin/currencies');
}

// --- make one the base ------------------------------------------------------
if (($params[0] ?? '') === 'base' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int) ($_POST['id'] ?? 0);
    $row = one('SELECT * FROM currencies WHERE id = ?', [$id]);

    if (!$row) {
        flash('error', 'That currency no longer exists.');
        redirect('admin/currencies');
    }

    // Everything is stored in the base currency, so changing it would
    // revalue every price in the database. Say so instead of doing it.
    $priced = (int) col('SELECT COUNT(*) FROM services WHERE price_per_1000 > 0', [], 0);
    $orders = (int) col('SELECT COUNT(*) FROM orders', [], 0);

    if ($priced > 0 || $orders > 0) {
        flash('error', 'The base currency cannot be changed once there are priced services or orders - '
            . 'every stored amount is in it. Set it up before you import services.');
        redirect('admin/currencies');
    }

    q('UPDATE currencies SET is_base = 0');
    update_row('currencies', ['is_base' => 1, 'rate_to_base' => 1, 'is_active' => 1], 'id = ?', [$id]);
    set_setting('base_currency', strtoupper($row['code']));
    set_setting('currency_symbol', $row['symbol']);
    currencies(true);

    flash('success', $row['code'] . ' is now your base currency.');
    redirect('admin/currencies');
}

$updatedAt = setting('currency_rates_updated_at', '');

crud_handle([
    'table'    => 'currencies',
    'base'     => 'admin/currencies',
    'title'    => 'Currencies',
    'single'   => 'Currency',
    'subtitle' => 'Base currency ' . base_currency()['code']
                . ($updatedAt ? ' - rates updated ' . when($updatedAt) : ' - rates never updated'),
    'order'    => 'is_base DESC, sort_order, code',
    'search'   => ['code', 'name'],
    'search_hint' => 'Search code or name',
    'toggle'   => 'is_active',
    'empty'    => 'No currencies yet.',
    'actions'  => [['label' => 'Update rates now', 'href' => 'admin/currencies/refresh', 'post' => true]],
    'form_note'=> 'The rate is how much one unit of this currency is worth in your base currency. '
                . 'With PKR as base and the dollar at 280, USD has a rate of 280. '
                . '"Update rates now" fills these in for you.',
    'columns'  => [
        ['label' => 'Currency', 'render' => fn($r) =>
            '<b>' . e($r['code']) . ($r['is_base'] ? ' <span class="chipx">base</span>' : '') . '</b>'
            . '<small class="sub">' . e($r['name']) . '</small>'],
        ['label' => 'Symbol', 'render' => fn($r) => e($r['symbol'] ?: '-')],
        ['label' => 'Rate', 'render' => fn($r) => $r['is_base']
            ? '<span class="muted">-</span>'
            : '<b>' . e(rtrim(rtrim(number_format((float) $r['rate_to_base'], 6), '0'), '.')) . '</b>'
              . '<small class="sub">' . e(base_currency()['code']) . ' per ' . e($r['code']) . '</small>'],
        ['label' => 'Used by', 'class' => 'hide-sm', 'render' => function ($r) {
            $n = (int) col('SELECT COUNT(*) FROM providers WHERE currency = ?', [$r['code']], 0);
            return $n ? qty_fmt($n) . ' provider' . ($n === 1 ? '' : 's') : '<span class="muted">-</span>';
        }],
        ['label' => 'Updated', 'class' => 'hide-sm', 'render' => fn($r) => e(when($r['updated_at'], 'd M, H:i'))],
        ['label' => 'Base', 'render' => function ($r) {
            if ($r['is_base']) {
                return '<span class="st st-completed">base</span>';
            }
            return '<form method="post" action="' . e(url('admin/currencies/base')) . '" style="display:inline"'
                 . ' data-confirm="Make ' . e($r['code']) . ' the base currency?">'
                 . csrf_field()
                 . '<input type="hidden" name="id" value="' . (int) $r['id'] . '">'
                 . '<button class="btn btn-ghost btn-sm" type="submit">Make base</button></form>';
        }],
    ],
    'fields' => [
        'code'   => ['label' => 'Code', 'required' => true, 'rules' => ['unique'],
                     'hint' => 'Three letters, as the rate feed knows it: USD, PKR, EUR.',
                     'save' => fn($v) => strtoupper(trim((string) $v))],
        'name'   => ['label' => 'Name', 'required' => true, 'placeholder' => 'US Dollar'],
        'symbol' => ['label' => 'Symbol', 'placeholder' => '$',
                     'hint' => 'Only the base currency symbol is shown to customers.'],
        'rate_to_base' => ['label' => 'Rate', 'type' => 'number', 'step' => '0.00000001', 'default' => 1,
                           'hint' => 'How much one unit is worth in ' . base_currency()['code'] . '.'],
        'sort_order' => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
        'is_active'  => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
    'after_save' => function () { currencies(true); },
    'can_delete' => function (int $id) {
        $row = one('SELECT * FROM currencies WHERE id = ?', [$id]);
        if ($row && $row['is_base']) {
            return 'The base currency cannot be deleted.';
        }
        $used = (int) col('SELECT COUNT(*) FROM providers WHERE currency = ?', [$row['code'] ?? ''], 0);
        return $used > 0
            ? "{$used} provider(s) bill in this currency. Point them elsewhere first."
            : null;
    },
], $params);
