<?php
/** robots.txt, pointing at the sitemap. The admin and order pages stay out. */

header('Content-Type: text/plain; charset=utf-8');

echo "User-agent: *\n";
echo "Disallow: /admin\n";
echo "Disallow: /order\n";
echo "Disallow: /track\n";
echo "Disallow: /api\n";
echo "Disallow: /install\n";
echo "\n";
echo 'Sitemap: ' . url('sitemap.xml') . "\n";
