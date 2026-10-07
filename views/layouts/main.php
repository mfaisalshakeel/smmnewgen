<?php
/** Front-site layout. Phase 4 builds the real one on top of this. */
$siteName = setting('site_name', 'SMM Panel');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? $siteName) ?></title>
<?php if (!empty($meta_description)): ?>
<meta name="description" content="<?= e($meta_description) ?>">
<?php endif; ?>
<?php if (!empty($meta_robots)): ?>
<meta name="robots" content="<?= e($meta_robots) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<?php if ($icon = setting('favicon_path')): ?>
<link rel="icon" href="<?= e(url($icon)) ?>">
<?php endif; ?>
<style>:root{--brand:<?= e(setting('theme_color', '#4f46e5')) ?>}</style>
<?= setting('head_code', '') /* trusted: pasted by an admin in Settings */ ?>
</head>
<body>
<?= $content ?>
</body>
</html>
