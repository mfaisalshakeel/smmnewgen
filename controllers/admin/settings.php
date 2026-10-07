<?php
/** Grouped settings, plus the logo and favicon uploads. */
require_once APP_PATH . '/helpers/crud.php';

/** group => [key => definition] */
$GROUPS = [
    'Site' => [
        'site_name'        => ['label' => 'Site name', 'required' => true],
        'site_tagline'     => ['label' => 'Tagline'],
        'currency_symbol'  => ['label' => 'Currency symbol', 'hint' => 'Shown before every price.'],
        'default_platform' => ['label' => 'Default platform', 'type' => 'select',
                               'options' => fn() => array_column(all('SELECT slug FROM platforms WHERE is_active = 1'), 'slug', 'slug')],
        'theme_color'      => ['label' => 'Theme colour', 'type' => 'color'],
        'order_prefix'     => ['label' => 'Order code prefix', 'hint' => 'Letters only, e.g. GK gives GK-8F42KD.'],
    ],
    'Contact' => [
        'whatsapp_number'  => ['label' => 'WhatsApp number', 'hint' => 'With country code, e.g. 923001234567.'],
        'support_email'    => ['label' => 'Support email', 'type' => 'email'],
    ],
    'Orders and automation' => [
        'auto_send_orders'      => ['label' => 'Send orders to the provider as soon as they are marked paid',
                                    'type' => 'checkbox'],
        'auto_sync_statuses'    => ['label' => 'Let cron refresh order statuses', 'type' => 'checkbox'],
        'require_trx_id'        => ['label' => 'Require a transaction id before a customer can submit payment',
                                    'type' => 'checkbox'],
        'allow_manual_services' => ['label' => 'Allow services with no provider (delivered by hand)',
                                    'type' => 'checkbox'],
        'default_markup'        => ['label' => 'Default markup %', 'type' => 'number',
                                    'hint' => 'Pre-filled on the import screen.'],
    ],
    'Advanced' => [
        'head_code' => ['label' => 'Head code', 'type' => 'textarea', 'rows' => 6,
                        'hint' => 'Pasted into <head> on the front site. Analytics and pixels go here. '
                                . 'It is output exactly as written, so only paste code you trust.'],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($GROUPS as $fields) {
        foreach ($fields as $key => $field) {
            if (($field['type'] ?? 'text') === 'checkbox') {
                set_setting($key, isset($_POST[$key]) ? '1' : '0');
                continue;
            }
            if (array_key_exists($key, $_POST)) {
                set_setting($key, trim((string) $_POST[$key]));
            }
        }
    }

    foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $input => $settingKey) {
        if (!empty($_FILES[$input]['name'])) {
            $stored = store_branding_file($input);
            if (is_string($stored)) {
                set_setting($settingKey, $stored);
            } else {
                flash('error', $stored === null ? 'Upload failed.' : (string) $stored);
            }
        }
        if (isset($_POST['remove_' . $input])) {
            set_setting($settingKey, '');
        }
    }

    flash('success', 'Settings saved.');
    redirect('admin/settings');
}

view('admin/settings', [
    'title'    => 'Settings',
    'subtitle' => 'Site, orders and automation',
    'groups'   => $GROUPS,
    'cronKey'  => (string) cfg('cron_key', ''),
], 'layouts/admin');


/**
 * Save an uploaded logo or favicon.
 *
 * Only real images are accepted, decided by what GD can actually read rather
 * than by the name or the browser-supplied type, and the file is written with
 * an extension we chose.
 *
 * @return string|null  stored path, or an error message
 */
function store_branding_file(string $input): ?string
{
    $file = $_FILES[$input] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return 'The file did not upload (error ' . ($file['error'] ?? '?') . ').';
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return 'Keep images under 2 MB.';
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return 'That upload could not be verified.';
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return 'That file is not an image.';
    }

    $allowed = [
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_ICO  => 'ico',
    ];
    $extension = $allowed[$info[2]] ?? null;
    if ($extension === null) {
        return 'Use a PNG, JPG, GIF, WebP or ICO image.';
    }

    $name   = $input . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $target = UPLOAD_PATH . '/branding/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return 'Could not write to uploads/branding/. Check the folder is writable.';
    }
    @chmod($target, 0644);

    // Drop the previous file so the folder does not fill up.
    $old = setting($input === 'logo' ? 'logo_path' : 'favicon_path', '');
    if ($old && str_starts_with($old, 'uploads/branding/') && is_file(BASE_PATH . '/' . $old)) {
        @unlink(BASE_PATH . '/' . $old);
    }

    return 'uploads/branding/' . $name;
}
