<?php
/** Installed front-site themes, and which one is live. */

$action = $params[0] ?? 'index';

if ($action === 'activate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug      = (string) ($_POST['theme'] ?? '');
    $available = themes_available(true);

    if (!isset($available[$slug])) {
        flash('error', 'That theme is not installed.');
        redirect('admin/themes');
    }

    set_setting('active_theme', $slug);
    flash('success', $available[$slug]['name'] . ' is now live on the front site.');
    redirect('admin/themes');
}

view('admin/themes', [
    'title'    => 'Themes',
    'subtitle' => 'How the front site looks',
    'themes'   => themes_available(true),
    'active'   => active_theme(),
], 'layouts/admin');
