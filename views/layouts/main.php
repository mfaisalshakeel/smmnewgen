<?php
/**
 * Front-site layout (direction A).
 *
 * @var string  $content           rendered page
 * @var string  $title             <title> and usually the H1
 * @var ?string $meta_description
 * @var ?string $meta_robots
 * @var ?string $canonical
 */
$siteName = setting('site_name', 'SMM Panel');
$themeCss = ':root{--brand:' . preg_replace('/[^#0-9a-fA-F]/', '', setting('theme_color', '#6c4df6')) . '}';
$waNumber = preg_replace('/\D/', '', (string) setting('whatsapp_number', ''));
?><!doctype html>
<html lang="en" data-currency="<?= e(setting('currency_symbol', 'Rs ')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? $siteName) ?><?= isset($title) && $title !== $siteName ? ' | ' . e($siteName) : '' ?></title>
<?php if (!empty($meta_description)): ?>
<meta name="description" content="<?= e($meta_description) ?>">
<?php endif; ?>
<?php if (!empty($meta_robots)): ?>
<meta name="robots" content="<?= e($meta_robots) ?>">
<?php endif; ?>
<?php if (!empty($canonical)): ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= e($title ?? $siteName) ?>">
<meta property="og:type" content="website">
<?php if (!empty($meta_description)): ?>
<meta property="og:description" content="<?= e($meta_description) ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<?php if ($icon = setting('favicon_path')): ?>
<link rel="icon" href="<?= e(url($icon)) ?>">
<?php endif; ?>
<style><?= $themeCss ?></style>
<?= setting('head_code', '') /* trusted: pasted by an admin in Settings */ ?>
</head>
<body>
<?php partial('partials/icons'); ?>
<?php partial('partials/header'); ?>

<?php foreach (flashes() as $message): ?>
  <div class="wrap" style="padding-top:18px">
    <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
  </div>
<?php endforeach; ?>

<?= $content ?>

<?php partial('partials/footer'); ?>
<?php partial('partials/modals'); ?>

<?php if ($waNumber !== ''): ?>
<a class="fab" href="https://wa.me/<?= e($waNumber) ?>" target="_blank" rel="noopener"
   aria-label="Chat on WhatsApp">
  <svg class="icon"><use href="#i-whatsapp"></use></svg><span>WhatsApp</span>
</a>
<?php endif; ?>

<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
