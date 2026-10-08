<?php
/** Categories live inside a platform: Followers, Likes, Views. */
require_once APP_PATH . '/helpers/crud.php';

$platforms = options_from('platforms');

// Which service backs a category when the catalogue is sold one service at a
// time. Listed with its platform, because two platforms often name a service
// the same thing.
$services = [];
foreach (all('SELECT s.id, s.name, p.name AS platform
                FROM services s
                LEFT JOIN platforms p ON p.id = s.platform_id
               WHERE s.is_active = 1
               ORDER BY p.sort_order, s.sort_order, s.id') as $row) {
    $services[$row['id']] = ($row['platform'] ? $row['platform'] . ' - ' : '') . $row['name'];
}

$singleMode = setting('catalogue_mode', 'services') === 'single';

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
        ['label' => 'Services', 'class' => 'hide-sm', 'render' => function ($r) use ($singleMode, $services) {
            $count = (int) col('SELECT COUNT(*) FROM services WHERE category_id = ? AND is_active = 1',
                [$r['id']], 0);
            if (!$singleMode) {
                return qty_fmt($count);
            }
            // In single mode only one of them is ever shown, so say which.
            return $r['service_id'] && isset($services[$r['service_id']])
                ? '<b>' . e(excerpt($services[$r['service_id']], 40)) . '</b>'
                  . '<small class="sub">' . qty_fmt($count) . ' available</small>'
                : '<span class="st st-pending">none chosen</span>'
                  . '<small class="sub">' . qty_fmt($count) . ' available</small>';
        }],
        ['label' => 'State', 'render' => fn($r) =>
            '<span class="st st-' . ($r['is_active'] ? 'completed' : 'cancelled') . '">'
            . ($r['is_active'] ? 'active' : 'off') . '</span>'],
    ],
    'fields' => [
        'platform_id' => ['label' => 'Platform', 'type' => 'select', 'options' => $platforms, 'required' => true],
        'name'        => ['label' => 'Name', 'required' => true],
        'slug'        => ['label' => 'URL slug', 'required' => true, 'rules' => ['slug'],
                          'hint' => 'Used in the address: /instagram/followers'],
        'service_id'  => ['label' => 'Service shown here', 'type' => 'select', 'options' => $services,
                          'search' => true,
                          'empty' => 'The first active service in this category', 'nullable' => true,
                          'hint' => 'Only used when the catalogue is set to one service per '
                                  . 'category, in Settings.'],
        'meta_title'  => ['label' => 'Meta title'],
        'meta_description' => ['label' => 'Meta description', 'type' => 'textarea', 'rows' => 2],
        'sort_order'  => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
        'is_active'   => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
], $params);
