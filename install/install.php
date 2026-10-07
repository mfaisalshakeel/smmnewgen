<?php
/**
 * One-time installer.
 *
 * Step 1  requirements check
 * Step 2  database credentials -> writes config/config.php and runs schema.sql
 * Step 3  administrator account -> writes install/install.lock
 *
 * Once install.lock exists this script refuses to do anything, so leaving it
 * on the server cannot be used to reinstall over a live site.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('LOCK_FILE', __DIR__ . '/install.lock');
define('CONFIG_FILE', BASE_PATH . '/config/config.php');

session_name('smminstall');
session_start();

// ---------------------------------------------------------------------------
// Refuse to run twice
// ---------------------------------------------------------------------------
$justFinished = ((int) ($_GET['step'] ?? 1)) === 4 && !empty($_SESSION['install_done']);

if (is_file(LOCK_FILE) && !$justFinished) {
    http_response_code(403);
    render_page('Already installed', '
        <div class="box">
          <h2>This site is already installed</h2>
          <p>The installer is locked by <code>install/install.lock</code>.</p>
          <p>If you really need to install again, delete <code>install/install.lock</code>
             and <code>config/config.php</code> first &mdash; that wipes the link to your
             current database, so take a backup.</p>
          <p class="mt"><a class="btn" href="../admin/login">Go to the admin login</a></p>
        </div>');
    exit;
}

$step   = (int) ($_GET['step'] ?? 1);
$errors = [];

// ---------------------------------------------------------------------------
// Step 2 - database
// ---------------------------------------------------------------------------
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = [
        'host' => trim((string) ($_POST['db_host'] ?? 'localhost')),
        'name' => trim((string) ($_POST['db_name'] ?? '')),
        'user' => trim((string) ($_POST['db_user'] ?? '')),
        'pass' => (string) ($_POST['db_pass'] ?? ''),
    ];
    $baseUrl = rtrim(trim((string) ($_POST['base_url'] ?? '')), '/');

    if ($db['name'] === '') { $errors[] = 'Database name is required.'; }
    if ($db['user'] === '') { $errors[] = 'Database user is required.'; }

    if (!$errors) {
        try {
            $pdo = new PDO(
                "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
                $db['user'],
                $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            run_schema($pdo, __DIR__ . '/schema.sql');

            $config = [
                'db_host'    => $db['host'],
                'db_name'    => $db['name'],
                'db_user'    => $db['user'],
                'db_pass'    => $db['pass'],
                'db_charset' => 'utf8mb4',
                'app_key'    => bin2hex(random_bytes(32)),
                'cron_key'   => bin2hex(random_bytes(16)),
                'base_url'   => $baseUrl,
                'debug'      => false,
            ];

            if (!write_config(CONFIG_FILE, $config)) {
                $errors[] = 'Could not write config/config.php. Make the config/ folder writable (755) and try again.';
            } else {
                $_SESSION['install_db'] = true;
                header('Location: install.php?step=3');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        } catch (Throwable $e) {
            $errors[] = 'Setup failed: ' . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------------------
// Step 3 - administrator
// ---------------------------------------------------------------------------
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['install_db']) || !is_file(CONFIG_FILE)) {
        header('Location: install.php?step=2');
        exit;
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $pass     = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');
    $site     = trim((string) ($_POST['site_name'] ?? 'GrowKit'));

    if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $username)) {
        $errors[] = 'Username must be 3-60 characters: letters, numbers, dot, dash or underscore.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($pass) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($pass !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        try {
            $config = require CONFIG_FILE;
            $pdo = new PDO(
                "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
                $config['db_user'],
                $config['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $exists = $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            if ((int) $exists > 0) {
                $errors[] = 'An administrator already exists in this database.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO admins (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())'
                );
                $stmt->execute([$username, $email, password_hash($pass, PASSWORD_DEFAULT)]);

                if ($site !== '') {
                    $pdo->prepare('INSERT INTO settings (`k`,`v`) VALUES (?,?) ON DUPLICATE KEY UPDATE `v` = VALUES(`v`)')
                        ->execute(['site_name', $site]);
                }

                file_put_contents(LOCK_FILE, 'Installed ' . date('c') . PHP_EOL);
                @chmod(CONFIG_FILE, 0640);
                unset($_SESSION['install_db']);

                $_SESSION['install_done'] = ['cron_key' => $config['cron_key']];
                header('Location: install.php?step=4');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = 'Could not create the administrator: ' . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------
if ($step === 4) {
    $cronKey = $_SESSION['install_done']['cron_key'] ?? '';
    unset($_SESSION['install_done']);
    $cronLine = $cronKey
        ? '<p>Add this to cron in cPanel, every 5 minutes:</p><pre>php ' . h(BASE_PATH) . '/cron.php</pre>'
          . '<p>Or call the URL version:</p><pre>' . h(guess_base_url()) . '/cron/' . h($cronKey) . '</pre>'
        : '';

    render_page('Installed', '
      <div class="box">
        <div class="done">&#10003;</div>
        <h2>All set</h2>
        <p>Your panel is installed and the installer is now locked.</p>
        ' . $cronLine . '
        <p class="warn"><b>One last step:</b> delete the <code>install/</code> folder from your
           server. Everything still works without it.</p>
        <p class="mt"><a class="btn" href="../admin/login">Log in to the admin panel</a></p>
      </div>');
    exit;
}

if ($step === 3) {
    if (empty($_SESSION['install_db']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: install.php?step=2');
        exit;
    }
    render_page('Create your administrator', '
      <form method="post" action="install.php?step=3" class="box">
        <h2>Step 3 &mdash; administrator account</h2>
        <p class="sub">This is the account you will use to log in at <code>/admin</code>.</p>
        ' . errors_html($errors) . '
        <label>Site name
          <input name="site_name" value="' . h($_POST['site_name'] ?? 'GrowKit') . '" required></label>
        <label>Username
          <input name="username" value="' . h($_POST['username'] ?? '') . '" required
                 pattern="[A-Za-z0-9_.-]{3,60}" autocomplete="username"></label>
        <label>Email
          <input type="email" name="email" value="' . h($_POST['email'] ?? '') . '" required
                 autocomplete="email"></label>
        <label>Password
          <input type="password" name="password" required minlength="8"
                 autocomplete="new-password"></label>
        <label>Repeat password
          <input type="password" name="password_confirm" required minlength="8"
                 autocomplete="new-password"></label>
        <button class="btn" type="submit">Finish installation</button>
      </form>');
    exit;
}

if ($step === 2) {
    render_page('Database', '
      <form method="post" action="install.php?step=2" class="box">
        <h2>Step 2 &mdash; database</h2>
        <p class="sub">Create an empty MySQL database and user in cPanel first, then paste the
           details here. The tables are created for you.</p>
        ' . errors_html($errors) . '
        <label>Database host
          <input name="db_host" value="' . h($_POST['db_host'] ?? 'localhost') . '" required></label>
        <label>Database name
          <input name="db_name" value="' . h($_POST['db_name'] ?? '') . '" required></label>
        <label>Database user
          <input name="db_user" value="' . h($_POST['db_user'] ?? '') . '" required></label>
        <label>Database password
          <input type="password" name="db_pass" value=""></label>
        <label>Site URL <span class="hint">leave empty to detect automatically</span>
          <input name="base_url" value="' . h($_POST['base_url'] ?? '') . '"
                 placeholder="' . h(guess_base_url()) . '"></label>
        <button class="btn" type="submit">Connect and create tables</button>
      </form>');
    exit;
}

// --- step 1: requirements ---------------------------------------------------
$checks  = requirement_checks();
$blocked = false;
$rows    = '';
foreach ($checks as $check) {
    if (!$check['ok'] && $check['required']) {
        $blocked = true;
    }
    $icon  = $check['ok'] ? '<span class="ok">&#10003;</span>' : ($check['required']
        ? '<span class="bad">&#10007;</span>' : '<span class="meh">!</span>');
    $rows .= '<tr><td>' . $icon . '</td><td>' . h($check['label']) . '</td><td class="r">'
           . h($check['value']) . '</td></tr>';
}

render_page('Requirements', '
  <div class="box">
    <h2>Step 1 &mdash; server check</h2>
    <p class="sub">Everything below must pass before the panel can be installed.</p>
    <table>' . $rows . '</table>
    ' . ($blocked
        ? '<p class="warn">Fix the items marked &#10007; and reload this page.</p>'
        : '<a class="btn" href="install.php?step=2">Continue</a>') . '
  </div>');


// ===========================================================================
// Helpers (installer only - the application helpers are not loaded yet)
// ===========================================================================

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function guess_base_url(): string
{
    $https  = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
           || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    return $scheme . '://' . $host . $dir;
}

function requirement_checks(): array
{
    $writable = static fn(string $p): bool => is_dir($p) && is_writable($p);

    return [
        ['label' => 'PHP 8.0 or newer', 'ok' => PHP_VERSION_ID >= 80000,
         'value' => PHP_VERSION, 'required' => true],
        ['label' => 'PDO MySQL extension', 'ok' => extension_loaded('pdo_mysql'),
         'value' => extension_loaded('pdo_mysql') ? 'enabled' : 'missing', 'required' => true],
        ['label' => 'mbstring extension', 'ok' => extension_loaded('mbstring'),
         'value' => extension_loaded('mbstring') ? 'enabled' : 'missing', 'required' => true],
        ['label' => 'JSON extension', 'ok' => extension_loaded('json'),
         'value' => extension_loaded('json') ? 'enabled' : 'missing', 'required' => true],
        ['label' => 'cURL extension (provider API)', 'ok' => extension_loaded('curl'),
         'value' => extension_loaded('curl') ? 'enabled' : 'missing', 'required' => true],
        ['label' => 'config/ is writable', 'ok' => $writable(BASE_PATH . '/config'),
         'value' => $writable(BASE_PATH . '/config') ? 'writable' : 'not writable', 'required' => true],
        ['label' => 'storage/logs/ is writable', 'ok' => $writable(BASE_PATH . '/storage/logs'),
         'value' => $writable(BASE_PATH . '/storage/logs') ? 'writable' : 'not writable', 'required' => true],
        ['label' => 'install/ is writable (for the lock file)', 'ok' => $writable(__DIR__),
         'value' => $writable(__DIR__) ? 'writable' : 'not writable', 'required' => true],
        ['label' => 'uploads/branding/ is writable (logo upload)',
         'ok' => $writable(BASE_PATH . '/uploads/branding'),
         'value' => $writable(BASE_PATH . '/uploads/branding') ? 'writable' : 'not writable',
         'required' => false],
        ['label' => 'GD extension (image resizing)', 'ok' => extension_loaded('gd'),
         'value' => extension_loaded('gd') ? 'enabled' : 'missing', 'required' => false],
    ];
}

/**
 * Execute schema.sql. Statements are split on semicolons at end of line, which
 * is enough for this file: it has no stored routines or embedded semicolons.
 */
function run_schema(PDO $pdo, string $file): void
{
    if (!is_file($file)) {
        throw new RuntimeException('install/schema.sql is missing.');
    }

    $sql = file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', (string) $sql);

    foreach (preg_split('/;\s*[\r\n]+/', (string) $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

function write_config(string $path, array $config): bool
{
    $lines = ["<?php", "/** Written by install/install.php - edit by hand only when moving servers. */", "return ["];
    foreach ($config as $key => $value) {
        $lines[] = sprintf("    %-12s => %s,", var_export($key, true),
            is_bool($value) ? ($value ? 'true' : 'false') : var_export($value, true));
    }
    $lines[] = "];";

    return (bool) @file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);
}

function errors_html(array $errors): string
{
    if (!$errors) {
        return '';
    }
    $items = '';
    foreach ($errors as $error) {
        $items .= '<li>' . h($error) . '</li>';
    }
    return '<ul class="errors">' . $items . '</ul>';
}

function render_page(string $title, string $body): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex, nofollow">'
       . '<title>' . h($title) . ' &mdash; Installer</title><style>'
       . <<<'CSS'
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font:15px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif;background:#f5f6fa;color:#141726;
  padding:28px 16px}
.wrap{max-width:560px;margin:0 auto}
.head{text-align:center;margin-bottom:22px}
.head b{display:block;font-size:21px;letter-spacing:-.02em}
.head span{color:#838aa0;font-size:13.5px}
.box{background:#fff;border:1px solid #e8eaf1;border-radius:14px;padding:26px}
h2{margin:0 0 6px;font-size:19px;letter-spacing:-.02em}
.sub{color:#838aa0;font-size:14px;margin:0 0 20px}
label{display:block;margin-bottom:15px;font-size:13px;font-weight:600;color:#4a5068}
.hint{font-weight:400;color:#9aa0b4}
input{display:block;width:100%;margin-top:7px;padding:11px 13px;font-size:15px;font-weight:400;
  border:1px solid #e8eaf1;border-radius:9px;background:#f9fafc;outline:none;color:#141726}
input:focus{border-color:#4f46e5;background:#fff}
.btn{display:inline-block;margin-top:6px;background:#4f46e5;color:#fff;border:0;border-radius:9px;
  padding:12px 22px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none}
.btn:hover{background:#4338ca}
table{width:100%;border-collapse:collapse;margin-bottom:20px;font-size:14px}
td{padding:9px 6px;border-bottom:1px solid #f0f1f6}
td.r{text-align:right;color:#838aa0;font-size:13px}
.ok{color:#16a34a;font-weight:700}
.bad{color:#dc2626;font-weight:700}
.meh{color:#d97706;font-weight:700}
.errors{background:#fdeaea;border:1px solid #f6caca;color:#b91c1c;border-radius:9px;
  padding:12px 14px 12px 30px;margin:0 0 18px;font-size:13.5px}
.warn{background:#fff7e6;border:1px solid #f6e2b8;color:#8a5d2a;border-radius:9px;
  padding:12px 14px;font-size:13.5px}
pre{background:#f5f6fa;border:1px solid #e8eaf1;border-radius:9px;padding:11px 13px;font-size:12.5px;
  overflow-x:auto;margin:8px 0 16px}
code{background:#f5f6fa;border-radius:5px;padding:1px 5px;font-size:13px}
.done{width:46px;height:46px;border-radius:50%;background:#e7f8ee;color:#16a34a;display:grid;
  place-items:center;font-size:24px;margin-bottom:14px}
.mt{margin-top:18px}
CSS
       . '</style></head><body><div class="wrap">'
       . '<div class="head"><b>SMM Panel</b><span>Installation</span></div>'
       . $body . '</div></body></html>';
}
