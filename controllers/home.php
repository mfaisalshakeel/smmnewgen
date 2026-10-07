<?php
/**
 * Front page. Phase 4 builds the real storefront on top of this; right now it
 * confirms that routing, the database and the layout all work.
 */

$platform = $GLOBALS['__platform'] ?? one(
    'SELECT * FROM platforms WHERE slug = ? AND is_active = 1',
    [setting('default_platform', 'instagram')]
);

if (!$platform) {
    $platform = one('SELECT * FROM platforms WHERE is_active = 1 ORDER BY sort_order, id LIMIT 1');
}

$category = $GLOBALS['__category'] ?? null;

$counts = [
    'platforms' => (int) col('SELECT COUNT(*) FROM platforms WHERE is_active = 1', [], 0),
    'services'  => (int) col('SELECT COUNT(*) FROM services WHERE is_active = 1', [], 0),
    'orders'    => (int) col('SELECT COUNT(*) FROM orders', [], 0),
];

$name  = $platform['name'] ?? 'Social media';
$title = $category
    ? sprintf('Buy %s %s', $name, $category['name'])
    : sprintf('Buy %s Followers With Safe & Instant Delivery', $name);

view('home', [
    'title'            => $title,
    'meta_description' => setting('site_tagline', 'Social media growth services with safe, instant delivery.'),
    'platform'         => $platform,
    'category'         => $category,
    'counts'           => $counts,
]);
