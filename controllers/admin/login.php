<?php
/**
 * Admin login.
 *
 * Throttled per IP so the form cannot be used to guess passwords, and the
 * failure message never says whether the username or the password was wrong.
 */

if (is_admin()) {
    redirect('admin');
}

$error    = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // Look at the budget without spending it; only a wrong password counts.
    if (!rate_limit('admin_login', 8, 600, false)) {
        $error = 'Too many attempts. Wait a few minutes and try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        $admin = one(
            'SELECT id, username, password_hash FROM admins WHERE username = ? OR email = ? LIMIT 1',
            [$username, $username]
        );

        if ($admin && password_verify($password, $admin['password_hash'])) {
            // New session id on privilege change, so a fixated id is useless.
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];

            if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                update_row('admins', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)],
                    'id = ?', [$admin['id']]);
            }
            update_row('admins', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$admin['id']]);
            log_line('Admin login: ' . $admin['username'] . ' from ' . client_ip());

            $after = $_SESSION['_after_login'] ?? 'admin';
            unset($_SESSION['_after_login']);
            redirect($after !== '' ? $after : 'admin');
        }

        rate_limit_hit('admin_login');
        $error = 'Those details did not match an account.';
        log_line('Failed admin login for "' . $username . '" from ' . client_ip());
    }
}

view('admin/login', [
    'title'    => 'Admin login',
    'error'    => $error,
    'username' => $username,
], 'layouts/admin-auth');
