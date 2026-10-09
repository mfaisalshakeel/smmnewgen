<?php
/**
 * The complete helper set. Controllers and views use these and nothing else -
 * if something new is needed it is added here, not invented inline.
 *
 *   config / db     cfg, db, db_driver, sqlite_path, q, one, all, col,
 *                   insert_row, update_row, delete_row
 *   settings        setting, set_setting, settings_all
 *   output          e, money, qty_fmt, excerpt, when
 *   urls            app_base_path, base_url, url, asset, redirect, current_path,
 *                   is_https
 *   views           view, render, partial (theme-aware, see helpers/theme.php)
 *   forms           csrf_token, csrf_field, csrf_verify, old, flash, flashes
 *   auth            admin_user, is_admin, require_admin
 *   misc            slugify, random_code, client_ip, rate_limit, log_line
 */

// ===========================================================================
// Configuration and database
// ===========================================================================

/** Read a value from config/config.php. */
function cfg(string $key, $default = null)
{
    return $GLOBALS['__config'][$key] ?? $default;
}

/** 'mysql' or 'sqlite'. MySQL stays the default for sites installed before
 *  SQLite was an option and so have no db_driver line in their config. */
function db_driver(): string
{
    return cfg('db_driver', 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
}

/**
 * Absolute path to the SQLite file. A relative db_name is taken as relative to
 * the project, so the configured value stays short and the file cannot be
 * pinned to one machine's layout.
 */
function sqlite_path(): string
{
    $path = (string) cfg('db_name', 'storage/database.sqlite');
    return str_starts_with($path, '/') || preg_match('~^[A-Za-z]:~', $path)
        ? $path
        : BASE_PATH . '/' . ltrim($path, '/');
}

/** Shared PDO connection, opened on first use. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (db_driver() === 'sqlite') {
        $pdo = new PDO('sqlite:' . sqlite_path(), null, null, $options);
        // SQLite ignores foreign keys unless asked, and the schema relies on them.
        $pdo->exec('PRAGMA foreign_keys = ON');
        // Shared hosting means concurrent requests; WAL keeps readers out of
        // the writer's way and the timeout stops a lock becoming an error.
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        cfg('db_host', 'localhost'),
        cfg('db_name', ''),
        cfg('db_charset', 'utf8mb4')
    );

    return $pdo = new PDO($dsn, cfg('db_user', ''), cfg('db_pass', ''), $options);
}

/**
 * Run a prepared statement. Every query in the application goes through here,
 * so values are always bound and never concatenated into SQL.
 */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** First matching row, or null. */
function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** All matching rows. */
function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** First column of the first row (counts, sums, single values). */
function col(string $sql, array $params = [], $default = null)
{
    $value = q($sql, $params)->fetchColumn();
    return $value === false ? $default : $value;
}

/** Insert an associative array and return the new id. */
function insert_row(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql  = sprintf(
        'INSERT INTO `%s` (`%s`) VALUES (%s)',
        $table,
        implode('`, `', $cols),
        implode(', ', array_fill(0, count($cols), '?'))
    );
    q($sql, array_values($data));
    return (int) db()->lastInsertId();
}

/** Update rows matched by `$where` and return how many changed. */
function update_row(string $table, array $data, string $where, array $whereParams = []): int
{
    $sets = [];
    foreach (array_keys($data) as $c) {
        $sets[] = "`$c` = ?";
    }
    $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);
    return q($sql, array_merge(array_values($data), $whereParams))->rowCount();
}

/** Delete rows matched by `$where` and return how many went. */
function delete_row(string $table, string $where, array $params = []): int
{
    return q(sprintf('DELETE FROM `%s` WHERE %s', $table, $where), $params)->rowCount();
}

// ===========================================================================
// Settings (key/value rows, loaded once per request)
// ===========================================================================

function settings_all(bool $fresh = false): array
{
    static $cache = null;
    if ($cache === null || $fresh) {
        $cache = [];
        foreach (all('SELECT `k`, `v` FROM settings') as $row) {
            $cache[$row['k']] = $row['v'];
        }
    }
    return $cache;
}

function setting(string $key, $default = null)
{
    $all = settings_all();
    return array_key_exists($key, $all) && $all[$key] !== '' ? $all[$key] : $default;
}

/**
 * Upsert, written the long way because MySQL and SQLite spell their upserts
 * differently and this runs on both.
 */
function set_setting(string $key, $value): void
{
    $value = (string) $value;

    if (!col('SELECT 1 FROM settings WHERE `k` = ?', [$key])) {
        insert_row('settings', ['k' => $key, 'v' => $value]);
    } else {
        update_row('settings', ['v' => $value], '`k` = ?', [$key]);
    }

    settings_all(true);
}

// ===========================================================================
// Output
// ===========================================================================

/** Escape for HTML. Every value printed in a view goes through this. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format an amount in the base currency.
 *
 * Every stored price is already in the base currency, so this only has to put
 * the right symbol in front of it.
 */
function money($amount): string
{
    $symbol = function_exists('base_currency')
        ? (base_currency()['symbol'] ?: setting('currency_symbol', 'Rs '))
        : setting('currency_symbol', 'Rs ');
    $amount = (float) $amount;
    $text   = number_format($amount, 2, '.', ',');
    $text   = preg_replace('/\.00$/', '', $text);

    // "Rs165" is a typo waiting to happen: a symbol that ends in a letter
    // needs air before the figure, a glyph like $ or ₹ does not. The space is
    // non-breaking so a price never wraps between the two.
    $gap = preg_match('/\p{L}$/u', $symbol) ? "\u{00A0}" : '';

    return $symbol . $gap . $text;
}

/** Thousands separator for quantities. */
function qty_fmt($n): string
{
    return number_format((int) $n, 0, '.', ',');
}

function excerpt(?string $text, int $length = 120): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '...' : $text;
}

/** Human date for the admin tables. */
function when(?string $datetime, string $format = 'd M Y, H:i'): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : '-';
}

// ===========================================================================
// URLs
// ===========================================================================

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/**
 * The URL path the application is mounted at: '' at a domain root,
 * '/shop' in a subfolder.
 *
 * This deliberately does not trust SCRIPT_NAME first. Servers disagree about
 * it - PHP's built-in server reports the directory index it resolved
 * (/install/index.php) where Apache reports the rewritten front controller
 * (/index.php) - and taking its dirname then eats a real URL segment.
 */
function app_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    // 1. The site URL the installer stored. Its path is the answer.
    $configured = trim((string) cfg('base_url', ''));
    if ($configured !== '') {
        return $base = rtrim((string) (parse_url($configured, PHP_URL_PATH) ?? ''), '/');
    }

    // 2. Where this project sits inside the document root.
    $docRoot = str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $docRoot = rtrim((string) (realpath($docRoot) ?: $docRoot), '/');
    $appRoot = str_replace('\\', '/', rtrim((string) (realpath(BASE_PATH) ?: BASE_PATH), '/'));

    if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
        return $base = rtrim(substr($appRoot, strlen($docRoot)), '/');
    }

    // 3. Nothing else to go on.
    return $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
}

/** Site root with no trailing slash. */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $configured = trim((string) cfg('base_url', ''));
    if ($configured !== '') {
        return $base = rtrim($configured, '/');
    }

    $scheme = is_https() ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $base = $scheme . '://' . $host . app_base_path();
}

/** Build an absolute URL for an application path. */
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/** Asset URL with a cache-busting stamp taken from the file's mtime. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/' . $path;
    $stamp = is_file($file) ? '?v=' . filemtime($file) : '';
    return url($path) . $stamp;
}

function redirect(string $path, int $status = 302): never
{
    $target = preg_match('~^https?://~i', $path) ? $path : url($path);
    header('Location: ' . $target, true, $status);
    exit;
}

/** Request path with no query string and no leading or trailing slash. */
function current_path(): string
{
    $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $uri  = rawurldecode($uri);
    $root = app_base_path();

    if ($root !== '' && str_starts_with($uri, $root)) {
        $uri = substr($uri, strlen($root));
    }

    return trim($uri, '/');
}

// ===========================================================================
// Views
// ===========================================================================

/** Render a view file to a string. Views only ever see what they are given. */
function render(string $name, array $data = []): string
{
    // View names are written by us, never taken from a request - this is here
    // so that stays true even if someone later wires one up to input.
    if (!preg_match('~^[A-Za-z0-9_/-]+$~', $name) || str_contains($name, '..')) {
        throw new RuntimeException("Bad view name: {$name}");
    }

    // The active theme gets first refusal, then the default theme, then the
    // application's own views. A theme only has to ship what it changes.
    $file = null;
    foreach (function_exists('theme_view_paths') ? theme_view_paths() : [VIEW_PATH] as $dir) {
        $candidate = $dir . '/' . ltrim($name, '/') . '.php';
        if (is_file($candidate)) {
            $file = $candidate;
            break;
        }
    }

    if ($file === null) {
        throw new RuntimeException("View not found: {$name}");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    return (string) ob_get_clean();
}

/** Render a view inside a layout and send it. */
function view(string $name, array $data = [], string $layout = 'layouts/main'): never
{
    $data['content'] = render($name, $data);
    echo render($layout, $data);
    exit;
}

/** Render a small view inline (used from inside other views). */
function partial(string $name, array $data = []): void
{
    echo render($name, $data);
}

// ===========================================================================
// Forms: CSRF, old input, flash messages
// ===========================================================================

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * Check the token on any state-changing request. Fails closed: a missing or
 * wrong token ends the request rather than falling through to the action.
 */
function csrf_verify(): void
{
    $sent = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Go back, reload the page and try again.');
    }
}

/** Remember submitted values so a failed form can be redrawn. */
function old(string $key, $default = '')
{
    $old = $_SESSION['_old'] ?? [];
    return $old[$key] ?? $default;
}

function keep_old(array $input): void
{
    unset($input['_token'], $input['password'], $input['password_confirm']);
    $_SESSION['_old'] = $input;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** Read and clear queued flash messages. */
function flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash'], $_SESSION['_old']);
    return $messages;
}

// ===========================================================================
// Authentication
// ===========================================================================

function admin_user(): ?array
{
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $user = one('SELECT id, username, email, created_at, last_login_at FROM admins WHERE id = ?',
        [$_SESSION['admin_id']]);
    return $user;
}

function is_admin(): bool
{
    return admin_user() !== null;
}

function require_admin(): void
{
    if (!is_admin()) {
        $_SESSION['_after_login'] = current_path();
        redirect('admin/login');
    }
}

// ===========================================================================
// Misc
// ===========================================================================

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower(preg_replace('~[^-\w]+~', '', $text));
    return trim($text, '-') ?: 'item';
}

/** Short, unambiguous code for order references. */
function random_code(int $length = 6): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no I, O, 0, 1
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Per-IP throttle backed by the rate_limits table.
 *
 * Returns true while the caller is under the limit for the window. With
 * $record left true it also counts this call, which suits things like the
 * contact form where every attempt is a real attempt. Pass false to only
 * look, and call rate_limit_hit() yourself on the attempts that should
 * count - the login form does that so a correct password never eats into
 * someone's own budget.
 */
function rate_limit(string $action, int $max, int $windowSeconds, bool $record = true): bool
{
    $ip     = client_ip();
    // The cutoff is worked out here rather than in SQL, so the query says the
    // same thing to MySQL and to SQLite.
    $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);

    q('DELETE FROM rate_limits WHERE created_at < ?', [$cutoff]);

    $used = (int) col(
        'SELECT COUNT(*) FROM rate_limits WHERE action = ? AND ip = ? AND created_at >= ?',
        [$action, $ip, $cutoff], 0
    );

    if ($used >= $max) {
        return false;
    }
    if ($record) {
        rate_limit_hit($action);
    }
    return true;
}

/** Count one attempt against the throttle. */
function rate_limit_hit(string $action): void
{
    insert_row('rate_limits', [
        'action'     => $action,
        'ip'         => client_ip(),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function log_line(string $message): void
{
    $file = STORAGE_PATH . '/logs/app-' . date('Y-m') . '.log';
    @file_put_contents($file, '[' . date('c') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * The preset packages a service is sold in, cheapest first.
 *
 * A service with none is sold by quantity alone, which is why this returning
 * an empty array is an ordinary answer rather than a problem.
 */
function service_packages(int $serviceId): array
{
    static $cache = [];

    if (!isset($cache[$serviceId])) {
        $cache[$serviceId] = all(
            'SELECT * FROM service_packages
              WHERE service_id = ? AND is_active = 1
           ORDER BY sort_order, quantity',
            [$serviceId]
        );
    }

    return $cache[$serviceId];
}

/**
 * The round quantities a service is offered at, when nobody has said.
 *
 * A shop that sells "followers" sells them in 500s and 1,000s, not at
 * whatever number the customer types - that is the whole shape of the
 * reference. Making an admin hand-enter five rows per service before the
 * storefront looks right is work the panel can do itself, so a service with
 * no packages of its own gets this ladder, trimmed to what the provider
 * actually accepts.
 *
 * These carry no discount: each is simply the service's own rate times the
 * quantity, which is exactly what the server charges for it. Real discounts
 * mean real package rows, which is what Generate packages writes.
 */
const QUANTITY_LADDER = [100, 250, 500, 1000, 2500, 5000, 10000, 25000, 50000, 100000];

function suggested_quantities(int $min, int $max, int $wanted = 5): array
{
    $steps = array_values(array_filter(
        QUANTITY_LADDER,
        static fn(int $q): bool => $q >= $min && $q <= $max
    ));

    // A range too narrow for the ladder still deserves a first tier.
    if (!$steps) {
        return $min > 0 && $min <= $max ? [$min] : [];
    }

    if (count($steps) <= $wanted) {
        return $steps;
    }

    // Spread the picks across the range rather than taking the smallest few,
    // so the row reads as a scale from cheap to serious.
    $picked = [];
    $last   = count($steps) - 1;
    for ($i = 0; $i < $wanted; $i++) {
        $picked[] = $steps[(int) round($i * $last / ($wanted - 1))];
    }

    return array_values(array_unique($picked));
}

/**
 * What to draw as cards for a service: its own packages, or the ladder.
 *
 * A generated tier has no id. The order then posts a quantity and no package,
 * which the server prices from the rate - the same number the card showed.
 *
 * @return array<int, array<string, mixed>>
 */
function service_tiers(array $service): array
{
    $packages = service_packages((int) $service['id']);
    if ($packages) {
        return $packages;
    }

    if (setting('auto_packages', '1') !== '1') {
        return [];
    }

    $rate  = (float) $service['price_per_1000'];
    $tiers = [];

    foreach (suggested_quantities((int) $service['min_qty'], (int) $service['max_qty']) as $quantity) {
        $tiers[] = [
            'id'             => 0,
            'service_id'     => (int) $service['id'],
            'quantity'       => $quantity,
            'bonus_quantity' => 0,
            'price'          => round($rate / 1000 * $quantity, 2),
            'badge'          => '',
            'is_active'      => 1,
        ];
    }

    return $tiers;
}

/**
 * The ticked lines on a card.
 *
 * A service that has its own wins; anything else falls back to the shop-wide
 * set in Settings, so a freshly imported service arrives with ticks on it
 * rather than a blank space nobody notices until a customer does.
 *
 * @return string[]
 */
function service_feature_lines(array $service): array
{
    $own = trim((string) ($service['features'] ?? ''));
    $text = $own !== '' ? $own : (string) setting('default_features', '');

    return array_slice(array_values(array_filter(
        array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: []),
        static fn(string $line): bool => $line !== ''
    )), 0, 8);
}

/**
 * What the current catalogue settings actually mean, in words.
 *
 * The two settings between them decide whether a service appears on the
 * front site at all and what shape it takes there, and neither is obvious
 * from the screen you are standing on. Written once here so Services,
 * Import and Packages cannot drift into describing it differently.
 *
 * @return array{mode: string, title: string, lines: string[]}
 */
function catalogue_explainer(): array
{
    $single = setting('catalogue_mode', 'services') === 'single';
    $auto   = setting('auto_packages', '1') === '1';

    $lines = $single
        ? [
            'Each category shows <b>one</b> service, so the customer picks a quantity '
                . 'rather than a service.',
            'Which one is set on <b>Service mapping</b>. A category that names none falls back '
                . 'to its first active service.',
            'The other services in that category are not shown to customers at all. They stay '
                . 'here so you can map them later and so their orders keep their history.',
          ]
        : [
            'Every active service in a category gets its own card, so the customer picks a '
                . 'service first and then a quantity.',
            'Switch to one service per category in <b>Settings</b> if you would rather they '
                . 'only chose an amount.',
          ];

    $lines[] = $auto
        ? 'A service with no packages of its own is shown at round quantities within its own '
            . 'minimum and maximum, priced at its rate. Give it packages to set real prices '
            . '&mdash; that is the only way a bigger package can cost less per thousand.'
        : 'Round quantities are turned off, so a service shows a quantity box unless you give '
            . 'it packages of its own.';

    return [
        'mode'  => $single ? 'single' : 'services',
        'title' => $single
            ? 'One service per category'
            : 'Every service gets its own card',
        'lines' => $lines,
    ];
}

/**
 * Is this link on the platform's own host?
 *
 * "Contains" is not good enough: instagram.com.example.net contains
 * "instagram.com" and is not Instagram. The host has to BE the platform's
 * host, or a subdomain of it.
 */
function link_is_on_host(string $link, string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '') {
        return true;    // the platform does not claim one
    }

    $actual = strtolower((string) parse_url($link, PHP_URL_HOST));
    $actual = preg_replace('/^www\./', '', $actual);
    $host   = preg_replace('/^www\./', '', $host);

    return $actual !== '' && ($actual === $host || str_ends_with($actual, '.' . $host));
}
