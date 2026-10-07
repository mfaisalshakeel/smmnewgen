<?php
/** Payment methods shown on the order page. */
require_once APP_PATH . '/helpers/crud.php';

crud_handle([
    'table'    => 'payment_methods',
    'base'     => 'admin/payment-methods',
    'title'    => 'Payment Methods',
    'single'   => 'Payment method',
    'subtitle' => 'What customers can pay with',
    'order'    => 'sort_order, id',
    'search'   => ['name', 'account_number'],
    'search_hint' => 'Search method or account',
    'toggle'   => 'is_active',
    'empty'    => 'No payment methods yet.',
    'form_note'=> 'Only active methods with an account number are shown to customers.',
    'columns'  => [
        ['label' => 'Method', 'render' => fn($r) =>
            '<b>' . e($r['name']) . '</b><small class="sub">' . e($r['account_title']) . '</small>'],
        ['label' => 'Account', 'class' => 'hide-sm', 'render' => fn($r) =>
            $r['account_number'] !== ''
                ? '<span class="mono">' . e($r['account_number']) . '</span>'
                : '<span class="muted">not set</span>'],
        ['label' => 'State', 'render' => fn($r) =>
            '<span class="st st-' . ($r['is_active'] ? 'completed' : 'cancelled') . '">'
            . ($r['is_active'] ? 'active' : 'off') . '</span>'],
    ],
    'fields' => [
        'name'           => ['label' => 'Name', 'required' => true, 'placeholder' => 'JazzCash'],
        'short_name'     => ['label' => 'Short code', 'placeholder' => 'JC',
                             'hint' => 'Two letters shown in the little badge.'],
        'account_title'  => ['label' => 'Account title', 'required' => true],
        'account_number' => ['label' => 'Account number / IBAN', 'required' => true],
        'extra_label'    => ['label' => 'Extra field label', 'placeholder' => 'Branch'],
        'extra_value'    => ['label' => 'Extra field value', 'placeholder' => 'Gulberg III, Lahore'],
        'instructions'   => ['label' => 'Instructions', 'type' => 'textarea', 'rows' => 3,
                             'hint' => 'Shown under the account details on the payment page.'],
        'sort_order'     => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
        'is_active'      => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
], $params);
