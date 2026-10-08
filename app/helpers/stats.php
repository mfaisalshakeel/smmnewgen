<?php
/**
 * The numbers behind the dashboard.
 *
 * Kept out of the controller because every one of them is a decision about
 * what counts - and they have to agree with each other. The rule throughout:
 * an order only counts as money once it is paid, so `pending`, `cancelled`
 * and `refunded` are left out of every revenue, cost and profit figure. They
 * are counted as orders, because they happened.
 *
 * Dates are worked out in PHP and bound, never asked of the database: MySQL
 * and SQLite disagree about INTERVAL.
 */

/** The statuses that represent money actually taken. */
function earning_statuses(): array
{
    return ['paid', 'processing', 'completed', 'partial', 'api_error'];
}

/** `status IN (?, ?, ...)` plus the values to bind. */
function earning_clause(string $column = 'status'): array
{
    $statuses = earning_statuses();
    return [
        $column . ' IN (' . implode(', ', array_fill(0, count($statuses), '?')) . ')',
        $statuses,
    ];
}

/** Midnight, n days ago, as the database stores it. */
function days_ago(int $days): string
{
    return date('Y-m-d 00:00:00', strtotime('-' . $days . ' days'));
}

/**
 * One row per day for the last $days, with no gaps.
 *
 * A missing day is a real zero, and a chart that skips it draws a straight
 * line through a quiet week as if it never happened.
 *
 * @return array<int, array{date:string, label:string, orders:int, revenue:float, cost:float}>
 */
function stats_daily(int $days = 30): array
{
    $since = days_ago($days - 1);

    // Two queries rather than one with CASE WHEN, because the status list
    // would then appear twice in the same statement and every placeholder
    // after the first set would be bound to the wrong value.
    $counted = [];
    foreach (all(
        'SELECT DATE(created_at) AS d, COUNT(*) AS orders
           FROM orders WHERE created_at >= ? GROUP BY DATE(created_at)',
        [$since]
    ) as $row) {
        $counted[$row['d']] = (int) $row['orders'];
    }

    [$earning, $values] = earning_clause();
    $earned = [];
    foreach (all(
        "SELECT DATE(created_at) AS d,
                COALESCE(SUM(price), 0) AS revenue,
                COALESCE(SUM(cost), 0)  AS cost
           FROM orders
          WHERE created_at >= ? AND {$earning}
       GROUP BY DATE(created_at)",
        array_merge([$since], $values)
    ) as $row) {
        $earned[$row['d']] = $row;
    }

    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime('-' . $i . ' days'));
        $series[] = [
            'date'    => $date,
            'label'   => date('j M', strtotime($date)),
            'orders'  => $counted[$date] ?? 0,
            'revenue' => (float) ($earned[$date]['revenue'] ?? 0),
            'cost'    => (float) ($earned[$date]['cost'] ?? 0),
        ];
    }

    return $series;
}

/** Revenue, cost, profit and order count over a window ending now. */
function stats_window(int $days): array
{
    $since = days_ago($days - 1);

    $orders = (int) col('SELECT COUNT(*) FROM orders WHERE created_at >= ?', [$since], 0);

    [$earning, $values] = earning_clause();
    $row = one(
        "SELECT COALESCE(SUM(price), 0) AS revenue, COALESCE(SUM(cost), 0) AS cost
           FROM orders WHERE created_at >= ? AND {$earning}",
        array_merge([$since], $values)
    ) ?: [];

    $revenue = (float) ($row['revenue'] ?? 0);
    $cost    = (float) ($row['cost'] ?? 0);

    return [
        'orders'  => $orders,
        'revenue' => $revenue,
        'cost'    => $cost,
        'profit'  => $revenue - $cost,
        'margin'  => $revenue > 0 ? ($revenue - $cost) / $revenue * 100 : 0.0,
    ];
}

/** The same window, ending where the current one began - for "vs" lines. */
function stats_previous_window(int $days): array
{
    [$earning, $values] = earning_clause();

    $row = one(
        "SELECT COUNT(*) AS orders, COALESCE(SUM(price), 0) AS revenue
           FROM orders
          WHERE created_at >= ? AND created_at < ? AND {$earning}",
        array_merge([days_ago(($days * 2) - 1), days_ago($days - 1)], $values)
    ) ?: [];

    return [
        'orders'  => (int) ($row['orders'] ?? 0),
        'revenue' => (float) ($row['revenue'] ?? 0),
    ];
}

/** How many orders sit in each status, newest-relevant first. */
function stats_by_status(): array
{
    $counts = [];
    foreach (all('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $row) {
        $counts[$row['status']] = (int) $row['n'];
    }

    // A fixed order, so the list does not reshuffle as the numbers move.
    $order = ['pending', 'paid', 'processing', 'completed', 'partial',
              'api_error', 'cancelled', 'refunded'];

    $out = [];
    foreach ($order as $status) {
        $out[] = ['status' => $status, 'count' => $counts[$status] ?? 0];
    }
    return $out;
}

/** Best-selling services by revenue over a window. */
function stats_top_services(int $days = 30, int $limit = 6): array
{
    [$earning, $values] = earning_clause();

    return all(
        "SELECT service_name AS label, COUNT(*) AS orders, COALESCE(SUM(price), 0) AS revenue
           FROM orders
          WHERE created_at >= ? AND {$earning}
       GROUP BY service_name
       ORDER BY revenue DESC
          LIMIT " . max(1, min(20, $limit)),
        array_merge([days_ago($days - 1)], $values)
    );
}

/** Revenue per platform over a window. */
function stats_by_platform(int $days = 30): array
{
    [$earning, $values] = earning_clause('o.status');

    return all(
        "SELECT COALESCE(p.name, 'Unknown') AS label,
                COUNT(*) AS orders,
                COALESCE(SUM(o.price), 0) AS revenue
           FROM orders o
      LEFT JOIN platforms p ON p.id = o.platform_id
          WHERE o.created_at >= ? AND {$earning}
       GROUP BY COALESCE(p.name, 'Unknown')
       ORDER BY revenue DESC",
        array_merge([days_ago($days - 1)], $values)
    );
}

/** What each provider has cost us over a window. */
function stats_provider_spend(int $days = 30): array
{
    [$earning, $values] = earning_clause('o.status');

    return all(
        "SELECT COALESCE(pr.name, 'Manual delivery') AS label,
                COUNT(*) AS orders,
                COALESCE(SUM(o.cost), 0) AS cost
           FROM orders o
      LEFT JOIN providers pr ON pr.id = o.provider_id
          WHERE o.created_at >= ? AND {$earning}
       GROUP BY COALESCE(pr.name, 'Manual delivery')
       ORDER BY cost DESC",
        array_merge([days_ago($days - 1)], $values)
    );
}

/** Share of finished orders that actually completed. */
function stats_completion_rate(int $days = 30): array
{
    $done = (int) col(
        "SELECT COUNT(*) FROM orders WHERE created_at >= ? AND status IN ('completed','partial')",
        [days_ago($days - 1)], 0
    );
    $failed = (int) col(
        "SELECT COUNT(*) FROM orders WHERE created_at >= ? AND status IN ('cancelled','refunded','api_error')",
        [days_ago($days - 1)], 0
    );
    $total = $done + $failed;

    return [
        'completed' => $done,
        'failed'    => $failed,
        'rate'      => $total > 0 ? $done / $total * 100 : 0.0,
    ];
}
