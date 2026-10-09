<?php
/**
 * The notification feed, and the switches that decide what reaches the admin.
 *
 *   /admin/notifications          the feed
 *   /admin/notifications/rules    which events notify, and how
 */
require_once APP_PATH . '/helpers/notify.php';

$action = $params[0] ?? '';

if ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        update_row('notifications', ['is_read' => 1], 'id = ?', [$id]);
    } else {
        q('UPDATE notifications SET is_read = 1 WHERE is_read = 0');
        flash('success', 'All marked as read.');
    }
    redirect('admin/notifications');
}

if ($action === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only what has been read: clearing an unread one loses something nobody
    // has seen yet.
    $removed = q('DELETE FROM notifications WHERE is_read = 1')->rowCount();
    flash('success', $removed ? 'Cleared ' . qty_fmt($removed) . ' read notification(s).'
                              : 'Nothing read to clear.');
    redirect('admin/notifications');
}

if ($action === 'rules') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (notification_events() as $event => $definition) {
            if (!empty($definition['always'])) {
                continue;               // a sign-in code is not switchable
            }
            if ($definition['to'] === 'admin') {
                set_setting('notify_' . $event . '_panel',
                    isset($_POST['panel'][$event]) ? '1' : '0');
            }
            set_setting('notify_' . $event . '_mail',
                isset($_POST['mail'][$event]) ? '1' : '0');
        }

        set_setting('notify_email', trim((string) ($_POST['notify_email'] ?? '')));
        set_setting('low_balance_threshold',
            (string) max(0, (float) ($_POST['low_balance_threshold'] ?? 0)));

        flash('success', 'Notification settings saved.');
        redirect('admin/notifications/rules');
    }

    view('admin/notification-rules', [
        'title'     => 'Notifications',
        'subtitle'  => 'What reaches you, and how',
        'events'    => notification_events(),
        'mailReady' => mail_ready(),
        'adminMail' => notification_admin_email(),
    ], 'layouts/admin');
    return;
}

view('admin/notifications', [
    'title'    => 'Notifications',
    'subtitle' => 'What has happened lately',
    'rows'     => all('SELECT * FROM notifications ORDER BY id DESC LIMIT 100'),
    'unread'   => notifications_unread(),
], 'layouts/admin');
