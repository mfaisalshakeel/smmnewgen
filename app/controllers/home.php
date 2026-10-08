<?php
/**
 * The storefront.
 *
 * Reached three ways, all rendering the same page:
 *   /                         the default platform, first category
 *   /instagram                that platform, first category
 *   /instagram/followers      that platform and category
 *
 * The last two arrive through _fallback.php, which has already looked the rows
 * up and left them in $GLOBALS.
 */

$platform = $GLOBALS['__platform'] ?? null;

if (!$platform) {
    $platform = one('SELECT * FROM platforms WHERE slug = ? AND is_active = 1',
        [setting('default_platform', 'instagram')]);
}
if (!$platform) {
    $platform = one('SELECT * FROM platforms WHERE is_active = 1 ORDER BY sort_order, id LIMIT 1');
}
if (!$platform) {
    // Nothing is set up yet - say so plainly rather than rendering an empty shop.
    view('_empty', [
        'title'       => setting('site_name', 'SMM Panel'),
        'meta_robots' => 'noindex, follow',
    ]);
}

// Only categories that actually have something to sell.
$categories = all(
    'SELECT c.* FROM categories c
      WHERE c.platform_id = ? AND c.is_active = 1
        AND EXISTS (SELECT 1 FROM services s WHERE s.category_id = c.id AND s.is_active = 1)
   ORDER BY c.sort_order, c.id',
    [$platform['id']]
);

$category = $GLOBALS['__category'] ?? null;
if (!$category && $categories) {
    $category = $categories[0];
}

$services = $category
    ? all('SELECT * FROM services
            WHERE category_id = ? AND is_active = 1
         ORDER BY is_featured DESC, sort_order, id', [$category['id']])
    : [];

// Sold one service per category: the customer is choosing a quantity, not a
// service, so only the one the category names is shown. Nothing chosen falls
// back to the first active service rather than showing an empty tab.
if ($category && setting('catalogue_mode', 'services') === 'single' && $services) {
    $chosen = null;
    foreach ($services as $service) {
        if ((int) $service['id'] === (int) ($category['service_id'] ?? 0)) {
            $chosen = $service;
            break;
        }
    }
    $services = [$chosen ?? $services[0]];
}

$platformRows = all('SELECT * FROM platforms WHERE is_active = 1 ORDER BY sort_order, id');

$faqs = all(
    'SELECT * FROM faqs
      WHERE is_active = 1 AND (platform_id IS NULL OR platform_id = ?)
   ORDER BY sort_order, id',
    [$platform['id']]
);

// Per-platform SEO, falling back to something sensible.
$noun  = $category['name'] ?? 'Followers';
$title = $category['meta_title'] ?? '';
if ($title === '') {
    $title = $platform['meta_title'] ?? '';
}
if ($title === '') {
    $title = sprintf('Buy %s %s With Safe & Instant Delivery', $platform['name'], $noun);
}

$description = $category['meta_description'] ?? '';
if ($description === '') {
    $description = $platform['meta_description'] ?? '';
}
if ($description === '') {
    $cheapest = $services
        ? min(array_map(static fn($s) => (float) $s['price_per_1000'] / 1000 * (int) $s['min_qty'], $services))
        : 0;
    $description = sprintf(
        'Buy %s %s with instant delivery and no password required.%s Secure payment and 24/7 support.',
        $platform['name'],
        strtolower($noun),
        $cheapest > 0 ? ' Starting from ' . money($cheapest) . '.' : ''
    );
}

$canonical = $category
    ? url($platform['slug'] . '/' . $category['slug'])
    : url($platform['slug']);

// The platform strip and the tabs are real links. When the script follows one
// it asks for just the parts that change, so switching never reloads the page.
if (($_SERVER['HTTP_X_FRAGMENT'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'title'     => $title,
        'heroTitle' => $platform['name'] . ' ' . $noun,
        'ctaLabel'  => 'Buy ' . $platform['name'] . ' ' . $noun . ' \u2192',
        'url'       => $canonical,
        'platforms' => render('partials/platforms', ['platforms' => $platformRows, 'platform' => $platform]),
        'tabs'      => render('partials/tabs', ['categories' => $categories, 'category' => $category, 'platform' => $platform]),
        'cards'     => render('partials/cards', ['services' => $services, 'platform' => $platform, 'category' => $category]),
    ]);
    exit;
}

view('home', [
    'title'            => $title,
    'meta_description' => $description,
    'canonical'        => $canonical,
    'platform'         => $platform,
    'category'         => $category,
    'platforms'        => $platformRows,
    'categories'       => $categories,
    'services'         => $services,
    'faqs'             => $faqs,
]);
