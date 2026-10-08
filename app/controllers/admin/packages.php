<?php
/**
 * Preset quantity packages: "500 followers for 165", rather than a price per
 * thousand the customer works out for themselves.
 *
 * A package carries its own price, so a bigger one can genuinely cost less
 * per thousand - which is the whole reason shops sell them. Nothing is
 * derived from price_per_1000 here; that stays the rate for a custom
 * quantity.
 */
require_once APP_PATH . '/helpers/crud.php';

$services = [];
foreach (all('SELECT s.id, s.name, p.name AS platform
                FROM services s
                LEFT JOIN platforms p ON p.id = s.platform_id
               ORDER BY p.sort_order, s.sort_order, s.id') as $row) {
    $services[$row['id']] = ($row['platform'] ? $row['platform'] . ' - ' : '') . $row['name'];
}

// Arriving from a service's Packages button, the list is already narrowed to
// it - so a new package should start on that service too.
$forService = (int) ($_GET['service_id'] ?? 0);

crud_handle([
    'table'    => 'service_packages',
    'base'     => 'admin/packages',
    'title'    => 'Packages',
    'single'   => 'Package',
    'subtitle' => 'Fixed quantities at a fixed price, shown as cards on the front site',
    'order'    => 'service_id, sort_order, quantity',
    'toggle'   => 'is_active',
    'empty'    => 'No packages yet. A service with none is sold by quantity, which still works - '
                . 'packages just give people something to click.',
    'filters'  => ['service_id' => ['column' => 'service_id', 'all' => 'All services',
                                    'options' => $services]],
    'form_note'=> 'The price is what the customer pays for this package, not a rate. Bonus quantity '
                . 'is delivered on top and shown as "+500 EXTRA".',
    'columns'  => [
        ['label' => 'Service', 'render' => function ($r) use ($services) {
            return '<b>' . e($services[$r['service_id']] ?? 'service #' . $r['service_id']) . '</b>'
                 . '<small class="sub">' . e(qty_fmt($r['quantity']))
                 . ((int) $r['bonus_quantity'] > 0
                     ? ' + ' . e(qty_fmt($r['bonus_quantity'])) . ' free' : '')
                 . '</small>';
        }],
        ['label' => 'Price', 'render' => function ($r) {
            $delivered = (int) $r['quantity'] + (int) $r['bonus_quantity'];
            $per       = $delivered > 0 ? ((float) $r['price'] / $delivered) * 1000 : 0;
            return '<b>' . e(money($r['price'])) . '</b>'
                 . '<small class="sub">' . e(money($per)) . ' per 1,000</small>';
        }],
        ['label' => 'Badge', 'class' => 'hide-sm', 'render' => fn($r) => $r['badge'] !== ''
            ? '<span class="chipx">' . e($r['badge']) . '</span>'
            : '<span class="muted">-</span>'],
        ['label' => 'State', 'render' => fn($r) =>
            '<span class="st st-' . ($r['is_active'] ? 'completed' : 'cancelled') . '">'
            . ($r['is_active'] ? 'active' : 'off') . '</span>'],
    ],
    'fields' => [
        'service_id' => ['label' => 'Service', 'type' => 'select', 'options' => $services,
                         'required' => true, 'default' => $forService ?: null],
        'quantity'   => ['label' => 'Quantity', 'type' => 'number', 'required' => true,
                         'hint' => 'The headline number on the card.'],
        'bonus_quantity' => ['label' => 'Bonus quantity', 'type' => 'number', 'default' => 0,
                         'hint' => 'Delivered free on top. Leave 0 for no bonus.'],
        'price'      => ['label' => 'Price', 'type' => 'number', 'step' => '0.01', 'required' => true,
                         'hint' => 'What the customer pays for the whole package.'],
        'badge'      => ['label' => 'Badge', 'placeholder' => 'Best Value',
                         'hint' => 'Shown in the corner of the card. Leave empty for none.'],
        'sort_order' => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
        'is_active'  => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
], $params);
