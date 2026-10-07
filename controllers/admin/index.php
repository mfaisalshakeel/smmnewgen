<?php
/** Admin dashboard: today's numbers, provider balances and recent orders. */

$today = date('Y-m-d');

$stats = [
    'orders_today'  => (int) col('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?', [$today], 0),
    'orders_yday'   => (int) col('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?',
                        [date('Y-m-d', strtotime('-1 day'))], 0),
    'revenue_today' => (float) col(
        "SELECT COALESCE(SUM(price),0) FROM orders WHERE DATE(created_at) = ? AND status NOT IN ('pending','cancelled','refunded')",
        [$today], 0),
    'revenue_yday'  => (float) col(
        "SELECT COALESCE(SUM(price),0) FROM orders WHERE DATE(created_at) = ? AND status NOT IN ('pending','cancelled','refunded')",
        [date('Y-m-d', strtotime('-1 day'))], 0),
    'pending'       => (int) col("SELECT COUNT(*) FROM orders WHERE status = 'pending'", [], 0),
    'api_errors'    => (int) col("SELECT COUNT(*) FROM orders WHERE status = 'api_error'", [], 0),
    'services'      => (int) col('SELECT COUNT(*) FROM services WHERE is_active = 1', [], 0),
    'platforms'     => (int) col('SELECT COUNT(*) FROM platforms WHERE is_active = 1', [], 0),
    'unread'        => (int) col('SELECT COUNT(*) FROM messages WHERE is_read = 0', [], 0),
];

$recent = all(
    'SELECT o.*, p.name AS platform_name
       FROM orders o
       LEFT JOIN platforms p ON p.id = o.platform_id
   ORDER BY o.created_at DESC
      LIMIT 8'
);

$providers = all('SELECT * FROM providers ORDER BY sort_order, id');

view('admin/index', [
    'title'     => 'Dashboard',
    'subtitle'  => 'Overview of today',
    'stats'     => $stats,
    'recent'    => $recent,
    'providers' => $providers,
], 'layouts/admin');
