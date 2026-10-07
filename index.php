<?php
/**
 * SMM Panel - single entry point.
 *
 * Every request that is not a real file on disk lands here (see .htaccess),
 * so this file only has two jobs: boot the application and hand the request
 * to the resolver.
 */

define('BASE_PATH', __DIR__);

require BASE_PATH . '/app/core/bootstrap.php';
require BASE_PATH . '/app/core/resolver.php';

resolve_request();
