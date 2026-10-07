<?php
/** Categories live inside a platform: Followers, Likes, Views. */
require_once APP_PATH . '/helpers/crud.php';

$platforms = options_from('platforms');

crud_handle([
    'table'    => 'categories',
    'base'     => 'admin/categories',
    'title'    => 'Categories',
    'single'   => 'Category',
    'subtitle' => 'The tabs under each platform',
    'order'    => 'platform_id, sort_order, id',
    'search'   => ['name', 'slug'],
    'search_hint' => 'Search category name',
    'toggle'   => 'is_active',
    'empty'    => 'No categories yet.',
    'filters'  => [
        'platform_id' => ['column' => 'platform_id', 'all' => 'All platforms', 'options' => $platforms],
    ],
    'columns'  => [
        ['label' => 'Category', 'render' => fn($r) =>
            '<b>' . e($r['name']) . '</b><small class="sub">/' . e($r['slug']) . '</small>'],
        ['label' => 'Platform', 'render' => fn($r) =>
            e(col('SELECT name FROM platforms WHERE id = ?', [$r['platform_id']], '-'))],
        ['label' => 'Services', 'class' => 'hide-sm', 'render' => fn($r) =>
            qty_fmt(col('SELECT COUNT(*) FROM services WHERE category_id = ? AND is_active = 1', [$r['id']], 0))],
        ['label' => 'State', 'render' => fn($r) =>
            '<span class="st st-' . ($r['is_active'] ? 'completed' : 'cancelled') . '">'
            . ($r['is_active'] ? 'active' : 'off') . '</span>'],
    ],
    'fields' => [
        'platform_id' => ['label' => 'Platform', 'type' => 'select', 'options' => $platforms, 'required' => true],
        'name'        => ['label' => 'Name', 'required' => true],
        'slug'        => ['label' => 'URL slug', 'required' => true, 'rules' => ['slug'],
                          'hint' => 'Used in the address: /instagram/followers'],
        'meta_title'  => ['label' => 'Meta title'],
        'meta_description' => ['label' => 'Meta description', 'type' => 'textarea', 'rows' => 2],
        'sort_order'  => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
        'is_active'   => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
], $params);
