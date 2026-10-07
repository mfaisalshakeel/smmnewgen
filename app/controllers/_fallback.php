<?php
/**
 * The first URL segment was not a controller. It can still be:
 *
 *   /instagram              a platform
 *   /instagram/followers    a platform and one of its categories
 *   /refund-policy          a content page
 *
 * Anything else is a 404.
 */

$slug = $params[0] ?? '';
$sub  = $params[1] ?? null;

// A platform, optionally with a category.
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

    if (isset($params[2])) {          // nothing lives deeper than two segments
        require CONTROLLER_PATH . '/_404.php';
        return;
    }

    $GLOBALS['__platform'] = $platform;
    $GLOBALS['__category'] = $category;
    require CONTROLLER_PATH . '/home.php';
    return;
}

// A content page.
if ($sub === null) {
    $page = one('SELECT * FROM pages WHERE slug = ? AND is_active = 1', [$slug]);
    if ($page) {
        view('page', [
            'title'            => $page['meta_title'] !== '' ? $page['meta_title'] : $page['title'],
            'meta_description' => $page['meta_description'],
            'canonical'        => url($page['slug']),
            'page'             => $page,
        ]);
    }
}

require CONTROLLER_PATH . '/_404.php';
