<?php
/**
 * Runs before every controller under controllers/admin/.
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
