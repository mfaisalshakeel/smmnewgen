<?php
/**
 * Runs before every controller under app/controllers/admin/.
 *
 * Login and logout are reachable without a session; everything else demands
 * one. State-changing requests must also carry a valid CSRF token, which is
 * checked here once rather than in each controller.
 */

$adminAction = $GLOBALS['__admin_action'] ?? 'index';

// The admin area is never indexed.
header('X-Robots-Tag: noindex, nofollow', true);

// Pages that must stay reachable while logged out.
$publicActions = ['login', 'logout'];

if (!in_array($adminAction, $publicActions, true)) {
    require_admin();
}

// Any POST/PUT/DELETE inside the admin area must prove it came from our form.
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    csrf_verify();
}

/** Shared chrome data for the admin layout. */
$GLOBALS['__admin_nav_active'] = $adminAction;

// The layout prints a converted provider balance on every screen, so the
// conversion has to be loaded for every screen - not only for the handful of
// controllers that happen to need currencies for their own work.
require_once APP_PATH . '/helpers/currency.php';
// The bell is drawn on every screen, so its count has to be reachable
// from every screen too.
require_once APP_PATH . '/helpers/notify.php';

// Whether a newer release has been uploaded. Worked out here so the layout
// can show the nudge on every screen, and only once logged in - there is
// nothing to tell a stranger about our version.
$GLOBALS['__update_available'] = false;
if (!in_array($adminAction, $publicActions, true)) {
    require_once APP_PATH . '/helpers/update.php';
    $GLOBALS['__update_available'] = update_available();
}
