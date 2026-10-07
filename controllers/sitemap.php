<?php
/** XML sitemap: the home page, every platform and category, and every page. */

header('Content-Type: application/xml; charset=utf-8');

$urls = [['loc' => url(''), 'priority' => '1.0', 'freq' => 'daily']];

foreach (all('SELECT slug FROM platforms WHERE is_active = 1 ORDER BY sort_order, id') as $platform) {
    $urls[] = ['loc' => url($platform['slug']), 'priority' => '0.9', 'freq' => 'daily'];
}

foreach (all(
    'SELECT p.slug AS pslug, c.slug AS cslug
       FROM categories c
       JOIN platforms p ON p.id = c.platform_id
      WHERE c.is_active = 1 AND p.is_active = 1
        AND EXISTS (SELECT 1 FROM services s WHERE s.category_id = c.id AND s.is_active = 1)
   ORDER BY p.sort_order, c.sort_order'
) as $row) {
    $urls[] = ['loc' => url($row['pslug'] . '/' . $row['cslug']), 'priority' => '0.8', 'freq' => 'daily'];
}

foreach (all('SELECT slug FROM pages WHERE is_active = 1 ORDER BY sort_order, id') as $page) {
    $urls[] = ['loc' => url($page['slug']), 'priority' => '0.4', 'freq' => 'monthly'];
}

$urls[] = ['loc' => url('faq'),     'priority' => '0.5', 'freq' => 'monthly'];
$urls[] = ['loc' => url('contact'), 'priority' => '0.4', 'freq' => 'monthly'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $entry) {
    printf(
        "  <url><loc>%s</loc><changefreq>%s</changefreq><priority>%s</priority></url>\n",
        e($entry['loc']), $entry['freq'], $entry['priority']
    );
}
echo '</urlset>' . "\n";
