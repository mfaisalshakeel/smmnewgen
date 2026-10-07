<?php
/** Platforms: Instagram, TikTok and friends. */
require_once APP_PATH . '/helpers/crud.php';

crud_handle([
    'table'    => 'platforms',
    'base'     => 'admin/platforms',
    'title'    => 'Platforms',
    'single'   => 'Platform',
    'subtitle' => 'What customers can buy for',
    'order'    => 'sort_order, id',
    'search'   => ['name', 'slug'],
    'search_hint' => 'Search platform name',
    'toggle'   => 'is_active',
    'empty'    => 'No platforms yet.',
    'columns'  => [
        ['label' => 'Platform', 'render' => fn($r) =>
            '<b>' . e($r['name']) . '</b><small class="sub">/' . e($r['slug']) . '</small>'],
        ['label' => 'Categories', 'class' => 'hide-sm', 'render' => fn($r) =>
            qty_fmt(col('SELECT COUNT(*) FROM categories WHERE platform_id = ?', [$r['id']], 0))],
        ['label' => 'Services', 'class' => 'hide-sm', 'render' => fn($r) =>
            qty_fmt(col('SELECT COUNT(*) FROM services WHERE platform_id = ? AND is_active = 1', [$r['id']], 0))],
        ['label' => 'State', 'render' => fn($r) =>
            '<span class="st st-' . ($r['is_active'] ? 'completed' : 'cancelled') . '">'
            . ($r['is_active'] ? 'active' : 'off') . '</span>'],
    ],
    'fields' => [
        'name'       => ['label' => 'Name', 'required' => true],
        'slug'       => ['label' => 'URL slug', 'required' => true, 'rules' => ['slug', 'unique'],
                         'hint' => 'Used in the address: /instagram'],
        'icon'       => ['label' => 'Icon id', 'default' => 'i-instagram',
                         'hint' => 'One of the ids in the icon sprite, e.g. i-instagram.'],
        'color'      => ['label' => 'Accent colour', 'type' => 'color', 'default' => '#e1306c'],
        'url_prefix' => ['label' => 'Expected link host', 'placeholder' => 'instagram.com',
                         'hint' => 'Orders whose link does not contain this are rejected. Leave empty to allow any link.'],
        'meta_title' => ['label' => 'Meta title', 'hint' => 'Falls back to the page heading when empty.'],
        'meta_description' => ['label' => 'Meta description', 'type' => 'textarea', 'rows' => 2],
        'sort_order' => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
        'is_active'  => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
    'can_delete' => function (int $id) {
        $used = (int) col('SELECT COUNT(*) FROM orders WHERE platform_id = ?', [$id], 0);
        return $used > 0
            ? "This platform has {$used} order(s) against it. Disable it instead of deleting."
            : null;
    },
], $params);
