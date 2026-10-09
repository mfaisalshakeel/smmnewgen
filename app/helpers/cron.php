<?php
/**
 * Scheduled work.
 *
 * Every job is a task in one registry, so the admin can list them, see when
 * each last ran and what it did, and run any of them by hand. The CLI script
 * (cron.php) and the URL endpoint (app/controllers/cron.php) both come through
 * here, and both can run everything or one named task.
 *
 * Each run is recorded in cron_runs, which is what the Cron screen shows.
 */

require_once APP_PATH . '/helpers/orders.php';
require_once APP_PATH . '/helpers/sync.php';
require_once APP_PATH . '/helpers/currency.php';
require_once APP_PATH . '/helpers/guard.php';
require_once APP_PATH . '/helpers/audit.php';

/**
 * The jobs, in the order a full run does them.
 *
 * Each handler returns a one-line summary. Throwing is fine - the runner
 * records the failure and carries on with the next task.
 */
function cron_tasks(): array
{
    return [
        'send_orders' => [
            'label'    => 'Send paid orders to providers',
            'detail'   => 'Any order marked paid that has not reached its provider yet.',
            'every'    => 'Every 5 minutes',
            'handler'  => 'cron_send_orders',
        ],
        'sync_statuses' => [
            'label'    => 'Refresh order statuses',
            'detail'   => 'Asks each provider about orders still in flight and brings back '
                        . 'start count and remains. Batched where the provider supports it.',
            'every'    => 'Every 5 minutes',
            'handler'  => 'cron_sync_statuses',
            'enabled'  => 'auto_sync_statuses',
        ],
        'sync_services' => [
            'label'    => 'Sync services and prices',
            'detail'   => 'Re-reads each provider catalogue, converts its rate into your base '
                        . 'currency and reapplies each service markup. Flags anything the '
                        . 'provider has dropped.',
            'every'    => 'Every 6 hours',
            'handler'  => 'cron_sync_services',
            'enabled'  => 'sync_services_on_cron',
            'throttle' => 21600,
        ],
        'currency_rates' => [
            'label'    => 'Update currency rates',
            'detail'   => 'Pulls fresh exchange rates so provider costs convert correctly.',
            'every'    => 'Daily',
            'handler'  => 'cron_currency_rates',
            'enabled'  => 'currency_auto_update',
            'throttle' => 43200,
        ],
        'balances' => [
            'label'    => 'Check provider balances',
            'detail'   => 'Keeps the figure on the dashboard honest.',
            'every'    => 'Hourly',
            'handler'  => 'cron_balances',
            'throttle' => 3600,
        ],
        'housekeeping' => [
            'label'    => 'Housekeeping',
            'detail'   => 'Clears expired throttle rows, old cron history, abandoned '
                        . 'locks and routine audit entries past their keep-days. '
                        . 'Warnings and alerts in the audit log are never pruned.',
            'every'    => 'Daily',
            'handler'  => 'cron_housekeeping',
            'throttle' => 43200,
        ],
    ];
}

// ===========================================================================
// The tasks
// ===========================================================================

function cron_send_orders(): string
{
    $waiting = all("SELECT id, code FROM orders
                     WHERE status = 'paid' AND provider_order_id = ''
                  ORDER BY created_at ASC LIMIT 25");

    $sent = $failed = 0;
    foreach ($waiting as $order) {
        [$ok] = send_order_to_provider((int) $order['id']);
        $ok ? $sent++ : $failed++;
    }

    return $waiting
        ? sprintf('%d sent, %d failed', $sent, $failed)
        : 'nothing waiting';
}

function cron_sync_statuses(): string
{
    $summary = sync_order_statuses(200);
    return sprintf('%d checked, %d changed, %d errors',
        $summary['checked'], $summary['updated'], $summary['errors']);
}

function cron_sync_services(): string
{
    $totals = sync_all_services();
    $line   = sprintf('%d provider(s), %d services updated, %d missing',
        $totals['providers'], $totals['updated'], $totals['missing']);

    return $totals['errors'] ? $line . ' - ' . implode('; ', $totals['errors']) : $line;
}

function cron_currency_rates(): string
{
    $result = refresh_currency_rates();
    if (!$result['ok']) {
        throw new RuntimeException($result['message']);
    }
    return $result['message'];
}

function cron_balances(): string
{
    $updated = refresh_provider_balances();
    return $updated . ' provider balance(s) refreshed';
}

function cron_housekeeping(): string
{
    $limits = q('DELETE FROM rate_limits WHERE created_at < ?',
        [date('Y-m-d H:i:s', time() - 86400)])->rowCount();

    $runs = q('DELETE FROM cron_runs WHERE created_at < ?',
        [date('Y-m-d H:i:s', time() - 30 * 86400)])->rowCount();

    // A lock whose holder died is already ignored once it expires; this only
    // stops the table growing. Audit pruning keeps warnings and alerts.
    require_once APP_PATH . '/helpers/audit.php';
    $locks = locks_prune();
    $audit = audit_prune();

    return sprintf('%d throttle rows, %d old cron runs, %d stale lock(s), %d audit entries cleared',
        $limits, $runs, $locks, $audit);
}

// ===========================================================================
// The runner
// ===========================================================================

/** When a task last finished, or null. */
function cron_last_run(string $task): ?array
{
    return one('SELECT * FROM cron_runs WHERE task = ? ORDER BY id DESC LIMIT 1', [$task]);
}

/**
 * Run one task and record it.
 *
 * `$force` ignores both the on/off setting and the throttle, which is what
 * the Run now button in the admin does.
 */
function cron_run_task(string $key, string $source = 'cron', bool $force = false): array
{
    $tasks = cron_tasks();
    if (!isset($tasks[$key])) {
        return ['task' => $key, 'ok' => false, 'summary' => 'Unknown task', 'skipped' => false];
    }

    $task = $tasks[$key];

    if (!$force && isset($task['enabled']) && setting($task['enabled'], '1') !== '1') {
        return ['task' => $key, 'ok' => true, 'summary' => 'turned off in settings', 'skipped' => true];
    }

    // Tasks that do not need to run on every five-minute tick say how long
    // they want between runs.
    if (!$force && isset($task['throttle'])) {
        $last = cron_last_run($key);
        if ($last && (time() - strtotime($last['created_at'])) < $task['throttle']) {
            return ['task' => $key, 'ok' => true, 'summary' => 'not due yet', 'skipped' => true];
        }
    }

    $started = microtime(true);
    $ok      = true;

    try {
        $summary = ($task['handler'])();
    } catch (Throwable $e) {
        $ok      = false;
        $summary = $e->getMessage();
        log_line('Cron task ' . $key . ' failed: ' . $summary);
    }

    $duration = (int) round((microtime(true) - $started) * 1000);

    insert_row('cron_runs', [
        'task'        => $key,
        'source'      => $source,
        'ok'          => $ok ? 1 : 0,
        'summary'     => mb_substr((string) $summary, 0, 500),
        'duration_ms' => $duration,
        'created_at'  => date('Y-m-d H:i:s'),
    ]);

    return ['task' => $key, 'ok' => $ok, 'summary' => $summary, 'skipped' => false, 'ms' => $duration];
}

/**
 * Run everything, or one named task.
 *
 * Returns a plain-text report, which is what cron emails and what the URL
 * endpoint prints.
 */
function run_cron_tasks(?string $only = null, string $source = 'cron'): string
{
    // New code on a database that has not been migrated: half the columns a
    // job needs are missing, and a job that half-runs against money is worse
    // than one that does not run. Say what is wrong and stop.
    require_once APP_PATH . '/helpers/migrate.php';
    $pending = migrations_pending();
    if ($pending) {
        return '[' . date('c') . '] cron stopped - the database is behind the code. '
            . count($pending) . ' change(s) not applied. '
            . 'Open /admin/update and run it.' . PHP_EOL;
    }

    // One run at a time. A five-minute schedule and a run that takes six
    // minutes overlap, and both would pick up the same 'paid, not yet sent'
    // orders and both buy them. The URL endpoint makes it likelier still:
    // anyone who knows the key can fire it while the CLI job is mid-flight.
    $output = with_lock('cron:' . ($only ?? 'all'), static function () use ($only, $source) {
        $lines = ['[' . date('c') . '] cron start' . ($only ? ' (' . $only . ' only)' : '')];
        $keys  = $only !== null ? [$only] : array_keys(cron_tasks());

        foreach ($keys as $key) {
            $result = cron_run_task($key, $source, $only !== null);
            $lines[] = sprintf('  %-16s %s%s',
                $key,
                $result['skipped'] ? '- ' : ($result['ok'] ? 'ok ' : 'FAILED '),
                $result['summary']
            );
        }

        $lines[] = '[' . date('c') . '] cron done';
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }, 1800);

    if ($output === null) {
        // Not an error: the work is being done, just not by us.
        return '[' . date('c') . '] cron skipped - another run is still going.' . PHP_EOL;
    }

    return $output;
}
