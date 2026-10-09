<?php
/**
 * Admin shell: sidebar, top bar and the page body.
 *
 * @var string $content   rendered page
 * @var string $title     page title
 * @var string $subtitle  line under the title
 */
$active   = $GLOBALS['__admin_nav_active'] ?? 'index';
$user     = admin_user();
$siteName = setting('site_name', 'SMM Panel');
$unread   = (int) col('SELECT COUNT(*) FROM messages WHERE is_read = 0', [], 0);
$pending  = (int) col("SELECT COUNT(*) FROM orders WHERE status = 'pending'", [], 0);
// Converted, not summed raw: balances are held in each provider's own
// currency, so adding them is meaningless before it is mislabelled.
$balance  = provider_balance_total();
$alerts   = notifications_unread();

$nav = [
    ['group' => 'Main'],
    ['key' => 'index',     'label' => 'Dashboard',       'icon' => 'i-grid', 'href' => 'admin'],
    ['key' => 'orders',    'label' => 'Orders',          'icon' => 'i-bag', 'href' => 'admin/orders',
     'badge' => $pending ?: null],
    ['key' => 'messages',  'label' => 'Messages',        'icon' => 'i-chat', 'href' => 'admin/messages',
     'badge' => $unread ?: null],
    ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'i-bell',
     'href' => 'admin/notifications', 'badge' => $alerts ?: null],
    ['group' => 'Catalogue'],
    ['key' => 'services',  'label' => 'Services',        'icon' => 'i-layers', 'href' => 'admin/services'],
    ['key' => 'packages',  'label' => 'Packages',        'icon' => 'i-box', 'href' => 'admin/packages'],
    // Only worth a nav slot while the catalogue is actually sold that way.
    ['key' => 'mapping',   'label' => 'Service mapping', 'icon' => 'i-link', 'href' => 'admin/mapping',
     'when' => setting('catalogue_mode', 'services') === 'single'],
    ['key' => 'import',    'label' => 'Import Services', 'icon' => 'i-down', 'href' => 'admin/import'],
    ['key' => 'providers', 'label' => 'Providers',       'icon' => 'i-plug','href' => 'admin/providers'],
    ['key' => 'platforms', 'label' => 'Platforms',       'icon' => 'i-globe','href' => 'admin/platforms'],
    ['key' => 'categories','label' => 'Categories',      'icon' => 'i-tag', 'href' => 'admin/categories'],
    ['group' => 'Content'],
    ['key' => 'pages',     'label' => 'Pages',           'icon' => 'i-doc', 'href' => 'admin/pages'],
    ['key' => 'faqs',      'label' => 'FAQs',            'icon' => 'i-help', 'href' => 'admin/faqs'],
    ['group' => 'Appearance'],
    ['key' => 'themes',    'label' => 'Themes',          'icon' => 'i-palette', 'href' => 'admin/themes'],
    ['group' => 'System'],
    ['key' => 'payment-methods', 'label' => 'Payment Methods', 'icon' => 'i-card',
     'href' => 'admin/payment-methods'],
    ['key' => 'currencies', 'label' => 'Currencies',     'icon' => 'i-coin', 'href' => 'admin/currencies'],
    ['key' => 'cron',       'label' => 'Cron',           'icon' => 'i-clock','href' => 'admin/cron'],
    ['key' => 'update',    'label' => 'Update',          'icon' => 'i-refresh','href' => 'admin/update',
     'dot' => !empty($GLOBALS['__update_available'])],
    ['key' => 'emails',    'label' => 'Email templates', 'icon' => 'i-mail', 'href' => 'admin/emails'],
    ['key' => 'settings',  'label' => 'Settings',        'icon' => 'i-gear', 'href' => 'admin/settings'],
    ['key' => 'security',  'label' => 'Two-factor',      'icon' => 'i-lock', 'href' => 'admin/security'],
    ['key' => 'password',  'label' => 'Change Password', 'icon' => 'i-key','href' => 'admin/password'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Admin') ?> &mdash; <?= e($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
<?php if ($icon = setting('favicon_path')): ?>
<link rel="icon" href="<?= e(url($icon)) ?>">
<?php endif; ?>
</head>
<body>
<?php partial('partials/icons'); ?>

<div class="shell">
  <aside class="side">
    <div class="brand">
      <span class="logo-mark"><svg class="icon"><use href="#i-rocket"></use></svg></span>
      <span><?= e($siteName) ?><small>Admin Panel</small></span>
      <button class="sideclose" type="button" data-side-close>&times;</button>
    </div>

    <nav class="snav">
      <?php foreach ($nav as $item): ?>
        <?php if (array_key_exists('when', $item) && !$item['when']): continue; endif; ?>
        <?php if (isset($item['group'])): ?>
          <span class="sgroup"><?= e($item['group']) ?></span>
        <?php else: ?>
          <a href="<?= e(url($item['href'])) ?>"<?= $active === $item['key'] ? ' class="on"' : '' ?>>
            <svg class="icon"><use href="#<?= e($item['icon']) ?>"></use></svg>
            <?= e($item['label']) ?>
            <?php if (!empty($item['badge'])): ?><span class="pill"><?= (int) $item['badge'] ?></span><?php endif; ?>
            <?php if (!empty($item['dot'])): ?><span class="updot" title="Update available"></span><?php endif; ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>

      <form method="post" action="<?= e(url('admin/logout')) ?>" style="margin-top:4px">
        <?= csrf_field() ?>
        <button type="submit" class="snav-btn">
          <svg class="icon"><use href="#i-out"></use></svg> Log out
        </button>
      </form>
    </nav>

    <div class="sfoot">
      <div class="bal-mini">
        <small>Provider balance</small>
        <b><?= e(money($balance['total'])) ?></b>
        <?php if ($balance['missing']): ?>
          <small class="bal-warn" title="No exchange rate on file, so these are left out of the total">
            <?= e(implode(', ', $balance['missing'])) ?> not counted
          </small>
        <?php endif; ?>
      </div>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="burger" type="button" data-side-open>
        <svg class="icon"><use href="#i-menu"></use></svg>
      </button>
      <div class="crumb">
        <b><?= e($title ?? 'Admin') ?></b>
        <small><?= e($subtitle ?? '') ?></small>
      </div>
      <div class="tb-right">
        <a class="iconbtn" href="<?= e(url('')) ?>" target="_blank" rel="noopener" title="View site">
          <svg class="icon"><use href="#i-rocket"></use></svg>
        </a>
        <div class="me">
          <span class="avatar"><?= e(strtoupper(mb_substr($user['username'] ?? 'A', 0, 2))) ?></span>
          <span class="me-txt"><b><?= e($user['username'] ?? '') ?></b><small>Administrator</small></span>
        </div>
      </div>
    </header>

    <div class="content">
      <?php if (!empty($GLOBALS['__update_available']) && $active !== 'update'): ?>
        <div class="alert alert-info">
          A newer version of the panel is on the server.
          <a href="<?= e(url('admin/update')) ?>">Run the database update</a> to finish it.
        </div>
      <?php endif; ?>
      <?php foreach (flashes() as $message): ?>
        <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </div>
  </div>

  <div class="scrim" data-side-close></div>
</div>

<script src="<?= e(asset('assets/js/admin.js')) ?>"></script>
</body>
</html>
