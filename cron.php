<?php
/**
 * Scheduled work, run from the command line.
 *
 *   php /home/you/public_html/cron.php
 *
 * Every 5 minutes is a good default. Safe to run more often - each task
 * checks whether it has anything to do. Hosts without CLI cron can call
 * /cron/{cron_key} instead; both run app/helpers/cron.php.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', __DIR__);
require BASE_PATH . '/app/core/bootstrap.php';
require APP_PATH . '/helpers/cron.php';

echo run_cron_tasks();
