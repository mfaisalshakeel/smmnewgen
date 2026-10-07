<?php
/**
 * The scheduled work itself, so the CLI script (cron.php) and the URL
 * endpoint (controllers/cron.php) run exactly the same thing.
 */

require_once APP_PATH . '/helpers/orders.php';

function run_cron_tasks(): string
{
    $lines = ['[' . date('c') . '] cron start'];

    // 1. Send paid orders that have not reached their provider yet.
    $waiting = all(
        "SELECT id, code FROM orders
          WHERE status = 'paid' AND provider_order_id = ''
       ORDER BY created_at ASC LIMIT 25"
    );
    $sent = $failed = 0;
    foreach ($waiting as $order) {
        [$ok, $message] = send_order_to_provider((int) $order['id']);
        $ok ? $sent++ : $failed++;
        $lines[] = '  ' . $order['code'] . ': ' . $message;
    }
    $lines[] = 'sent ' . $sent . ', failed ' . $failed;

    // 2. Refresh statuses of orders still in flight.
    if (setting('auto_sync_statuses', '1') === '1') {
        $summary = sync_order_statuses(200);
        $lines[] = 'status sync: checked ' . $summary['checked']
                 . ', updated ' . $summary['updated']
                 . ', errors ' . $summary['errors'];
    } else {
        $lines[] = 'status sync: off in settings';
    }

    // 3. Provider balances, about once an hour.
    if (time() - (int) setting('_last_balance_check', 0) > 3600) {
        $updated = refresh_provider_balances();
        set_setting('_last_balance_check', (string) time());
        $lines[] = 'balances refreshed for ' . $updated . ' provider(s)';
    }

    // 4. Housekeeping.
    q('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');

    $lines[] = '[' . date('c') . '] cron done';
    log_line('cron: sent ' . $sent . ', failed ' . $failed);

    return implode(PHP_EOL, $lines) . PHP_EOL;
}
