<?php
/**
 * Notifications and the mail they send.
 *
 * One event list drives three things that used to drift apart: what the admin
 * sees in the bell, what mail goes out, and which switches the Notifications
 * screen offers. An event that is not in `notification_events()` does not
 * exist anywhere, so adding one is a single edit.
 */
require_once APP_PATH . '/helpers/mail.php';

/**
 * Every event the system can raise.
 *
 * `to` says who the message is for - `admin` goes to the notification feed
 * and the admin's address, `customer` goes to the address on the order.
 * `tokens` is what the template may use; it is shown on the edit form, so a
 * token missing from here is a token nobody knows about.
 */
function notification_events(): array
{
    return [
        'order_placed' => [
            'label'  => 'Order placed',
            'to'     => 'customer',
            'when'   => 'As soon as a customer finishes the order form.',
            'tokens' => ['order_code', 'service', 'quantity', 'amount', 'link',
                         'status', 'order_url', 'site_name'],
        ],
        'order_paid' => [
            'label'  => 'Payment confirmed',
            'to'     => 'customer',
            'when'   => 'When you mark the order paid.',
            'tokens' => ['order_code', 'service', 'quantity', 'amount',
                         'order_url', 'site_name'],
        ],
        'order_completed' => [
            'label'  => 'Order completed',
            'to'     => 'customer',
            'when'   => 'When the provider reports the order finished.',
            'tokens' => ['order_code', 'service', 'quantity', 'start_count',
                         'order_url', 'site_name'],
        ],
        'admin_new_order' => [
            'label'  => 'New order',
            'to'     => 'admin',
            'when'   => 'Every time an order is placed.',
            'tokens' => ['order_code', 'service', 'quantity', 'amount',
                         'link', 'admin_url', 'site_name'],
        ],
        'admin_payment_submitted' => [
            'label'  => 'Payment submitted',
            'to'     => 'admin',
            'when'   => 'A customer says they have paid and gives a reference.',
            'tokens' => ['order_code', 'amount', 'method', 'reference',
                         'admin_url', 'site_name'],
        ],
        'admin_api_error' => [
            'label'  => 'Provider refused an order',
            'to'     => 'admin',
            'when'   => 'A provider rejects an order and it parks in api_error.',
            'tokens' => ['order_code', 'service', 'provider', 'error',
                         'admin_url', 'site_name'],
        ],
        'admin_low_balance' => [
            'label'  => 'Provider balance low',
            'to'     => 'admin',
            'when'   => 'A provider balance falls under the threshold on the '
                      . 'Notifications screen.',
            'tokens' => ['provider', 'balance', 'threshold', 'admin_url', 'site_name'],
        ],
        'admin_new_message' => [
            'label'  => 'Contact form message',
            'to'     => 'admin',
            'when'   => 'Someone writes in through the contact form.',
            'tokens' => ['name', 'email', 'subject', 'message', 'admin_url', 'site_name'],
        ],
        'admin_login_code' => [
            'label'  => 'Sign-in code',
            'to'     => 'admin',
            'when'   => 'Two-factor is set to email and you sign in.',
            'tokens' => ['code', 'minutes', 'ip', 'site_name'],
            'always' => true,   // a login code is not a thing to switch off
        ],
    ];
}

/** Does this event write a row into the bell? Customer mail never does. */
function notification_in_panel(string $event): bool
{
    $events = notification_events();
    if (($events[$event]['to'] ?? '') !== 'admin') {
        return false;
    }
    return !empty($events[$event]['always'])
        || setting('notify_' . $event . '_panel', '1') === '1';
}

/** Does this event send mail? */
function notification_by_email(string $event): bool
{
    $events = notification_events();
    if (!isset($events[$event]) || !mail_ready()) {
        return false;
    }
    if (!empty($events[$event]['always'])) {
        return true;
    }
    return setting('notify_' . $event . '_mail', '0') === '1';
}

/** Where admin mail goes: the address set for it, else the support address. */
function notification_admin_email(): string
{
    foreach ([setting('notify_email', ''), setting('support_email', '')] as $candidate) {
        if (filter_var((string) $candidate, FILTER_VALIDATE_EMAIL)) {
            return (string) $candidate;
        }
    }
    return (string) (col('SELECT email FROM admins ORDER BY id LIMIT 1', [], '') ?: '');
}

/**
 * Raise an event.
 *
 * Nothing here is allowed to break the thing that raised it: an order must
 * still be placed when the mail server is down, so every failure is caught
 * and written to the log instead of thrown.
 *
 * @param array $data  token => value, used by the template and the panel row
 */
function notify(string $event, array $data = []): void
{
    $events = notification_events();
    if (!isset($events[$event])) {
        log_line('notify(): unknown event "' . $event . '"');
        return;
    }
    $definition = $events[$event];
    $data += ['site_name' => (string) setting('site_name', 'SMM Panel')];

    try {
        if (notification_in_panel($event)) {
            insert_row('notifications', [
                'event'      => $event,
                'title'      => mb_substr(notification_title($event, $data), 0, 190),
                'body'       => mb_substr(notification_body($event, $data), 0, 500),
                'link'       => mb_substr((string) ($data['admin_link'] ?? ''), 0, 190),
                'is_read'    => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (notification_by_email($event)) {
            $to = $definition['to'] === 'admin'
                ? notification_admin_email()
                : (string) ($data['email'] ?? '');

            if ($to !== '') {
                $rendered = render_email_template($event, $data);
                if ($rendered !== null) {
                    send_mail($to, $rendered['subject'], $rendered['body'], ['event' => $event]);
                }
            }
        }
    } catch (Throwable $e) {
        // The caller is in the middle of placing an order. Say so in the log
        // and let it carry on.
        log_line('notify(' . $event . ') failed: ' . $e->getMessage());
    }
}

/** The one-line headline for the bell. */
function notification_title(string $event, array $data): string
{
    $events = notification_events();
    $label  = $events[$event]['label'] ?? $event;

    return match ($event) {
        'admin_new_order'          => 'New order ' . ($data['order_code'] ?? ''),
        'admin_payment_submitted'  => 'Payment submitted for ' . ($data['order_code'] ?? ''),
        'admin_api_error'          => 'Provider refused ' . ($data['order_code'] ?? ''),
        'admin_low_balance'        => ($data['provider'] ?? 'A provider') . ' is low on balance',
        'admin_new_message'        => 'Message from ' . ($data['name'] ?? 'a visitor'),
        default                    => $label,
    };
}

/** The supporting line under it. */
function notification_body(string $event, array $data): string
{
    return match ($event) {
        'admin_new_order' => trim(($data['quantity'] ?? '') . ' x ' . ($data['service'] ?? '')
            . ' - ' . ($data['amount'] ?? '')),
        'admin_payment_submitted' => trim(($data['amount'] ?? '') . ' by ' . ($data['method'] ?? '')
            . (($data['reference'] ?? '') !== '' ? ' - ref ' . $data['reference'] : '')),
        'admin_api_error'   => (string) ($data['error'] ?? ''),
        'admin_low_balance' => ($data['balance'] ?? '') . ' left, below ' . ($data['threshold'] ?? ''),
        'admin_new_message' => (string) ($data['subject'] ?? $data['message'] ?? ''),
        default             => '',
    };
}

/** Unread count for the bell. Cheap enough to ask for on every admin request. */
function notifications_unread(): int
{
    return (int) col('SELECT COUNT(*) FROM notifications WHERE is_read = 0', [], 0);
}

// ============================================================ templates ====

/**
 * Fill a template in.
 *
 * Returns null when the template row is missing or switched off, which is how
 * an admin turns one message off without turning the whole event off.
 */
function render_email_template(string $event, array $data): ?array
{
    $template = one('SELECT subject, body, is_active FROM email_templates WHERE event = ?', [$event]);
    if (!$template || !$template['is_active']) {
        return null;
    }

    return [
        'subject' => fill_tokens($template['subject'], $data, false),
        'body'    => email_wrap(fill_tokens($template['body'], $data, true)),
    ];
}

/**
 * Replace {token} with its value.
 *
 * Values are escaped when they land in HTML, because a service name or a
 * customer's message is not ours to trust - the same rule the views follow.
 * A token with no value is removed rather than left showing its own braces.
 */
function fill_tokens(string $text, array $data, bool $html): string
{
    return preg_replace_callback('/\{([a-z_]+)\}/', static function ($match) use ($data, $html) {
        $value = (string) ($data[$match[1]] ?? '');
        return $html ? e($value) : $value;
    }, $text);
}

/**
 * Put a body inside the shell every message shares.
 *
 * Inline styles and a table, because mail clients are a decade behind
 * browsers: a stylesheet in the head is stripped by several of them and
 * flexbox is not reliable in any.
 */
function email_wrap(string $html): string
{
    $brand = (string) setting('theme_color', '#6c4df6');
    $brand = preg_match('/^#[0-9a-f]{3,8}$/i', $brand) ? $brand : '#6c4df6';
    $site  = e((string) setting('site_name', 'SMM Panel'));
    $year  = date('Y');

    return '<!doctype html><html><body style="margin:0;padding:0;background:#f2f2f9;">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
      . 'style="background:#f2f2f9;padding:24px 12px;"><tr><td align="center">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
      . 'style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;'
      . 'font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
      . '<tr><td style="background:' . e($brand) . ';padding:18px 24px;color:#ffffff;'
      . 'font-size:17px;font-weight:700;">' . $site . '</td></tr>'
      . '<tr><td style="padding:24px;color:#15152a;font-size:15px;line-height:1.6;">'
      . $html
      . '</td></tr>'
      . '<tr><td style="padding:16px 24px;background:#f8f8fc;color:#767690;font-size:12px;">'
      . '&copy; ' . $year . ' ' . $site . '</td></tr>'
      . '</table></td></tr></table></body></html>';
}

// ===================================================== template defaults ====

/**
 * What each message says out of the box.
 *
 * Kept in PHP rather than in the two schema files, because a seed written
 * twice is a seed that drifts: these are long strings full of quotes and
 * newlines, which is exactly the shape that ends up subtly different between
 * the MySQL and the SQLite copy.
 */
function email_template_defaults(): array
{
    return [
        'order_placed' => [
            'subject' => 'Your order {order_code} is in',
            'body'    => "<p>Thanks for your order.</p>\n"
                . "<p><b>{order_code}</b> &mdash; {quantity} x {service}, {amount}.</p>\n"
                . "<p>Keep that code: it is how you track the order.</p>\n"
                . '<p><a href="{order_url}">Track your order</a></p>',
        ],
        'order_paid' => [
            'subject' => 'Payment confirmed for {order_code}',
            'body'    => "<p>We have your payment for <b>{order_code}</b>.</p>\n"
                . "<p>{quantity} x {service} is on its way. Delivery usually starts within "
                . "a few minutes.</p>\n"
                . '<p><a href="{order_url}">Track your order</a></p>',
        ],
        'order_completed' => [
            'subject' => 'Order {order_code} is complete',
            'body'    => "<p><b>{order_code}</b> is done: {quantity} x {service} delivered.</p>\n"
                . "<p>Thanks for ordering with {site_name}.</p>\n"
                . '<p><a href="{order_url}">See the order</a></p>',
        ],
        'admin_new_order' => [
            'subject' => 'New order {order_code} - {amount}',
            'body'    => "<p><b>{order_code}</b></p>\n"
                . "<p>{quantity} x {service}<br>{amount}<br>{link}</p>\n"
                . '<p><a href="{admin_url}">Open it in the panel</a></p>',
        ],
        'admin_payment_submitted' => [
            'subject' => 'Payment submitted for {order_code}',
            'body'    => "<p><b>{order_code}</b> has a payment waiting to be checked.</p>\n"
                . "<p>{amount} by {method}<br>Reference: {reference}</p>\n"
                . '<p><a href="{admin_url}">Check it</a></p>',
        ],
        'admin_api_error' => [
            'subject' => 'Provider refused order {order_code}',
            'body'    => "<p><b>{order_code}</b> ({service}) was refused by {provider}.</p>\n"
                . "<p>The provider said: {error}</p>\n"
                . '<p><a href="{admin_url}">Retry it</a></p>',
        ],
        'admin_low_balance' => [
            'subject' => '{provider} is low on balance',
            'body'    => "<p>{provider} is down to {balance}, below the {threshold} you set.</p>\n"
                . "<p>Orders to this provider will start failing once it runs out.</p>\n"
                . '<p><a href="{admin_url}">Providers</a></p>',
        ],
        'admin_new_message' => [
            'subject' => 'Message from {name}',
            'body'    => "<p><b>{name}</b> &lt;{email}&gt;</p>\n"
                . "<p>{message}</p>\n"
                . '<p><a href="{admin_url}">Reply from the panel</a></p>',
        ],
        'admin_login_code' => [
            'subject' => 'Your {site_name} sign-in code',
            'body'    => "<p>Your sign-in code is <b style=\"font-size:22px;letter-spacing:3px\">{code}</b></p>\n"
                . "<p>It works for {minutes} minutes. The request came from {ip}.</p>\n"
                . "<p>If that was not you, change your password.</p>",
        ],
    ];
}

/**
 * Make sure every event has a row.
 *
 * Called when the templates screen loads rather than seeded by the installer,
 * so adding an event to `notification_events()` is still a single edit - the
 * row appears the next time anyone looks.
 *
 * @return int  how many were created
 */
function email_templates_ensure(): int
{
    $existing = array_column(all('SELECT event FROM email_templates'), 'event', 'event');
    $created  = 0;

    foreach (email_template_defaults() as $event => $default) {
        if (isset($existing[$event]) || !isset(notification_events()[$event])) {
            continue;
        }
        insert_row('email_templates', [
            'event'      => $event,
            'subject'    => $default['subject'],
            'body'       => $default['body'],
            'is_active'  => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $created++;
    }
    return $created;
}
