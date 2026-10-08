<?php
/**
 * Which service backs each category.
 *
 * Only meaningful while the catalogue is sold one service per category: the
 * customer is choosing a quantity, so exactly one service has to be the one
 * they are buying. It is editable on each category's own form too, but a
 * shop with thirty categories should not have to open thirty forms.
 */

$single = setting('catalogue_mode', 'services') === 'single';

if (($params[0] ?? '') === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $picked  = (array) ($_POST['service'] ?? []);
    $changed = 0;

    foreach ($picked as $categoryId => $serviceId) {
        $categoryId = (int) $categoryId;
        $serviceId  = (int) $serviceId;

        $category = one('SELECT id, service_id FROM categories WHERE id = ?', [$categoryId]);
        if (!$category) {
            continue;
        }

        // A service can only back the category it belongs to - otherwise the
        // tab would show something from a different platform entirely.
        if ($serviceId > 0 && !col('SELECT 1 FROM services WHERE id = ? AND category_id = ?',
                [$serviceId, $categoryId])) {
            continue;
        }

        if ((int) $category['service_id'] !== $serviceId) {
            update_row('categories', ['service_id' => $serviceId ?: null], 'id = ?', [$categoryId]);
            $changed++;
        }
    }

    flash($changed ? 'success' : 'info',
        $changed ? $changed . ' categor' . ($changed === 1 ? 'y' : 'ies') . ' updated.'
                 : 'Nothing changed.');
    redirect('admin/mapping');
}

$rows = all(
    'SELECT c.id, c.name, c.slug, c.service_id, c.is_active,
            p.name AS platform, p.id AS platform_id
       FROM categories c
       JOIN platforms p ON p.id = c.platform_id
   ORDER BY p.sort_order, p.id, c.sort_order, c.id'
);

// Each category's own services, so a dropdown can never offer a wrong one.
$choices = [];
foreach (all('SELECT id, name, category_id FROM services WHERE is_active = 1
               ORDER BY is_featured DESC, sort_order, id') as $service) {
    $choices[(int) $service['category_id']][] = $service;
}

view('admin/mapping', [
    'title'    => 'Service mapping',
    'subtitle' => 'Which service each category sells',
    'rows'     => $rows,
    'choices'  => $choices,
    'single'   => $single,
], 'layouts/admin');
