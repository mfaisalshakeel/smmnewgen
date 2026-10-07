<?php
/** Every FAQ on one page. The home page shows them too, per platform. */

$faqs = all('SELECT f.*, p.name AS platform_name
               FROM faqs f
               LEFT JOIN platforms p ON p.id = f.platform_id
              WHERE f.is_active = 1
           ORDER BY f.platform_id IS NOT NULL, f.sort_order, f.id');

view('faq', [
    'title'            => 'Frequently asked questions',
    'meta_description' => 'Answers about delivery times, safety, payment and refills.',
    'canonical'        => url('faq'),
    'faqs'             => $faqs,
]);
