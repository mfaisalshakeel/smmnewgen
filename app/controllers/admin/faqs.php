<?php
/** FAQ entries, optionally tied to one platform. */
require_once APP_PATH . '/helpers/crud.php';

$platforms = options_from('platforms');

crud_handle([
    'table'    => 'faqs',
    'base'     => 'admin/faqs',
    'title'    => 'FAQs',
    'single'   => 'FAQ',
    'subtitle' => 'Questions shown on the front page',
    'order'    => 'sort_order, id',
    'search'   => ['question', 'answer'],
    'search_hint' => 'Search questions',
    'toggle'   => 'is_active',
    'empty'    => 'No FAQs yet.',
    'filters'  => [
        'platform_id' => ['column' => 'platform_id', 'all' => 'All platforms', 'options' => $platforms],
    ],
    'columns'  => [
        ['label' => 'Question', 'render' => fn($r) =>
            '<b>' . e($r['question']) . '</b><small class="sub">' . e(excerpt($r['answer'], 70)) . '</small>'],
        ['label' => 'Platform', 'class' => 'hide-sm', 'render' => fn($r) =>
            $r['platform_id']
                ? e(col('SELECT name FROM platforms WHERE id = ?', [$r['platform_id']], '-'))
                : '<span class="muted">all</span>'],
        ['label' => 'State', 'render' => fn($r) =>
            '<span class="st st-' . ($r['is_active'] ? 'completed' : 'cancelled') . '">'
            . ($r['is_active'] ? 'live' : 'hidden') . '</span>'],
    ],
    'fields' => [
        'question'    => ['label' => 'Question', 'required' => true],
        'answer'      => ['label' => 'Answer', 'type' => 'textarea', 'rows' => 5, 'required' => true],
        'platform_id' => ['label' => 'Only for platform', 'type' => 'select', 'options' => $platforms,
                          'empty' => 'Show on every platform', 'nullable' => true],
        'sort_order'  => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
        'is_active'   => ['label' => 'Published', 'type' => 'checkbox', 'default' => 1],
    ],
], $params);
