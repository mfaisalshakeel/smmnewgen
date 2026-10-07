<?php
/**
 * Reached when the first URL segment is not a controller. It can still be a
 * platform (/instagram), a platform and category (/instagram/followers), or a
 * content page (/refund-policy). Anything else is a 404.
 *
 * Phase 4 fills in the platform and category pages; for now a known platform
 * renders the home controller so the URL already works.
 */

$slug = $params[0] ?? '';
$sub  = $params[1] ?? null;

// Content page?
$page = one('SELECT * FROM pages WHERE slug = ? AND is_active = 1', [$slug]);
if ($page) {
    view('page', [
        'title'            => $page['meta_title'] ?: $page['title'],
        'meta_description' => $page['meta_description'],
        'page'             => $page,
    ]);
}

// Platform, optionally with a category?
$platform = one('SELECT * FROM platforms WHERE slug = ? AND is_active = 1', [$slug]);
if ($platform) {
    $category = null;
    if ($sub !== null) {
        $category = one(
            'SELECT * FROM categories WHERE slug = ? AND platform_id = ? AND is_active = 1',
            [$sub, $platform['id']]
        );
        if (!$category) {
            require CONTROLLER_PATH . '/_404.php';
            return;
        }
    }

    $GLOBALS['__platform'] = $platform;
    $GLOBALS['__category'] = $category;
    require CONTROLLER_PATH . '/home.php';
    return;
}

require CONTROLLER_PATH . '/_404.php';
