<?php
/** Providers: the SMM APIs we buy from. */
require_once APP_PATH . '/helpers/crud.php';
require_once APP_PATH . '/helpers/orders.php';
require_once APP_PATH . '/helpers/sync.php';
require_once APP_PATH . '/helpers/currency.php';

// "Check balances" asks every active provider and stores what comes back.
if (($params[0] ?? '') === 'balances' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated = refresh_provider_balances();
    $total   = (int) col('SELECT COUNT(*) FROM providers WHERE is_active = 1', [], 0);
    $updated === $total && $total > 0
        ? flash('success', 'Balances updated for all ' . $total . ' active providers.')
        : flash($updated ? 'warning' : 'error',
            $updated . ' of ' . $total . ' providers answered. Check storage/logs for the rest.');
    redirect('admin/providers');
}

// "Check" asks the provider everything worth knowing in one go: does the key
// work, what does it bill in, how many services does it offer, and can it
// answer a multi-order status request.
if (($params[0] ?? '') === 'check' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = one('SELECT * FROM providers WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);

    if (!$provider) {
        flash('error', 'That provider no longer exists.');
        redirect('admin/providers');
    }

    $result = provider_check($provider);

    foreach ($result['checks'] as $check) {
        flash($check['ok'] ? 'success' : 'warning', $check['label'] . ': ' . $check['detail']);
    }

    redirect('admin/providers');
}

// Ask one provider, on its own, whether it answers a batch status request.
// Part of Check as well, but worth its own button: it is the one answer that
// changes how every later status sync is made, and an admin who has just
// overridden it by hand wants to put it back without re-running everything.
if (($params[0] ?? '') === 'multistatus' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = one('SELECT * FROM providers WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);

    if (!$provider) {
        flash('error', 'That provider no longer exists.');
        redirect('admin/providers');
    }

    $probe = multi_status_probe($provider);

    if ($probe['supported'] === null) {
        flash('warning', $provider['name'] . ': ' . $probe['detail']);
    } else {
        update_row('providers', ['supports_multi_status' => $probe['supported'] ? 1 : 0],
            'id = ?', [$provider['id']]);
        flash('success', $provider['name'] . ': multiple status in one request is '
            . ($probe['supported'] ? 'supported' : 'not supported') . '. ' . $probe['detail']);
    }

    redirect('admin/providers');
}

// Sync one provider's catalogue now.
if (($params[0] ?? '') === 'sync' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = one('SELECT * FROM providers WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);

    if (!$provider) {
        flash('error', 'That provider no longer exists.');
        redirect('admin/providers');
    }

    $result = sync_provider_services($provider);

    $result['error'] === null
        ? flash('success', sprintf('%s: %d service(s) checked, %d updated, %d no longer offered.',
            $provider['name'], $result['checked'], $result['updated'], $result['missing']))
        : flash('error', $provider['name'] . ': ' . $result['error']);

    redirect('admin/providers');
}

$currencyOptions = [];
foreach (currencies() as $code => $currency) {
    $currencyOptions[$code] = $code . ' - ' . $currency['name'];
}

crud_handle([
    'table'    => 'providers',
    'base'     => 'admin/providers',
    'title'    => 'Providers',
    'single'   => 'Provider',
    'subtitle' => 'The SMM APIs your services are bought from',
    'order'    => 'sort_order, id',
    'search'   => ['name', 'api_url'],
    'search_hint' => 'Search provider name or URL',
    'toggle'   => 'is_active',
    'timestamps' => true,
    'empty'    => 'No providers yet. Add one, then use Import Services to pull in its catalogue.',
    'actions'  => [['label' => 'Check balances', 'href' => 'admin/providers/balances', 'post' => true]],
    'row_actions' => function (array $r): string {
        $form = static function (string $path, string $label, string $icon, string $tone) use ($r): string {
            return '<form method="post" action="' . e(url($path)) . '">'
                 . csrf_field()
                 . '<input type="hidden" name="id" value="' . (int) $r['id'] . '">'
                 . '<button class="iact ' . $tone . '" type="submit" title="' . e($label) . '"'
                 . ' aria-label="' . e($label) . '">'
                 . '<svg class="icon"><use href="#' . $icon . '"></use></svg></button></form>';
        };
        return $form('admin/providers/check', 'Check the connection', 'i-shield', 'iact-go')
             . $form('admin/providers/multistatus', 'Ask about multi-status', 'i-list', 'iact-go')
             . $form('admin/providers/sync', 'Sync the catalogue', 'i-refresh', 'iact-go');
    },
    'form_note'=> 'The API URL is usually the provider\'s /api/v2 endpoint. Your key is stored as given '
                . 'and only ever sent to that provider. Saving tests the connection first: a key the '
                . 'provider refuses is not saved, and one it accepts arrives with its balance and '
                . 'billing currency already filled in.',

    // Test first, save second.
    //
    // A provider whose key does not work is a row that accepts services,
    // takes orders and fails every one of them at the moment money has
    // already changed hands. So the connection is proved before the row
    // exists - and because the same call brings back the balance, the
    // currency and the batch-status answer, a provider that passes is saved
    // knowing all three rather than waiting for somebody to press Check.
    //
    // Only a refused connection stops the save. A currency with no exchange
    // rate yet, or a batch-status question that cannot be answered until an
    // order has been sent, are things to go and do - not reasons to throw
    // away a key the provider just accepted.
    'before_save' => function (array $data, int $id, array $input): array {
        $url     = (string) ($data['api_url'] ?? '');
        $typedKey = (string) ($data['api_key'] ?? '');

        // Editing with the key box left empty means "keep the saved one", so
        // that is the key to test with.
        $key = $typedKey !== '' || $id === 0
            ? $typedKey
            : (string) col('SELECT api_key FROM providers WHERE id = ?', [$id], '');

        if ($url === '' || $key === '') {
            return $data;         // crud_save already refused this as required
        }

        // Only when there is something new to test. Changing a sort order
        // should not cost the admin a round trip to the provider - and on a
        // shared host a slow provider is most of the time the request has.
        // The Check button is there for asking again on purpose.
        $sameAsSaved = $id > 0
            && $typedKey === ''
            && $url === (string) col('SELECT api_url FROM providers WHERE id = ?', [$id], '');

        if ($sameAsSaved) {
            return $data;
        }

        $probe = provider_probe($url, $key, (string) ($data['currency'] ?? ''), $id);

        if (!$probe['connected']) {
            $why = $probe['checks'][0]['detail'] ?? 'the provider did not answer';

            keep_old($input);
            $_SESSION['_errors'] = ['api_key' => 'The provider refused this: ' . $why];
            flash('error', 'Not saved - the provider could not be reached with these details: '
                . $why . ' Check the URL and the key, then try again.');
            redirect('admin/providers/' . ($id ? 'edit/' . $id : 'new'));
        }

        // Said once, after the row is written, so the admin reads "saved, and
        // here is what it told us" rather than a wall of green before it.
        $_SESSION['_provider_checks'] = $probe['checks'];

        $found = $probe['fields'];

        // The one finding the admin is allowed to overrule. "Work it out
        // automatically" is the blank choice on that select, so a blank is an
        // invitation to answer it here - and anything else is an instruction
        // not to.
        if (($data['supports_multi_status'] ?? null) !== null) {
            unset($found['supports_multi_status']);
        }

        // What the provider said fills the boxes left empty; a currency the
        // admin picked by hand is never overwritten, which is why the probe
        // was told what was in that box.
        return $found + $data;
    },

    'after_save' => function (int $id, array $data, array $input): void {
        foreach ($_SESSION['_provider_checks'] ?? [] as $check) {
            // The connection is the one that just passed; repeating it is
            // noise next to "Provider created".
            if ($check['label'] === 'Connection and API key') {
                continue;
            }
            flash($check['ok'] ? 'success' : 'warning', $check['label'] . ': ' . $check['detail']);
        }
        unset($_SESSION['_provider_checks']);
    },
    'columns'  => [
        ['label' => 'Provider', 'key' => 'name', 'render' => fn($r) =>
            '<b>' . e($r['name']) . '</b><small class="sub">' . e($r['api_url']) . '</small>'],
        ['label' => 'Balance', 'render' => fn($r) =>
            '<b>' . e(provider_balance($r)['shown']) . '</b><small class="sub">'
            . ($r['balance_checked_at'] ? e(when($r['balance_checked_at'], 'd M, H:i')) : 'never checked')
            . '</small>'],
        ['label' => 'Services', 'render' => function ($r) {
            $n = (int) col('SELECT COUNT(*) FROM services WHERE provider_id = ?', [$r['id']], 0);
            $synced = $r['last_sync_at'] ? 'synced ' . when($r['last_sync_at'], 'd M, H:i') : 'never synced';
            return qty_fmt($n) . '<small class="sub">' . e($synced) . '</small>';
        }],
        ['label' => 'API', 'render' => function ($r) {
            $bits = [];
            $bits[] = $r['currency'] !== ''
                ? '<span class="chipx">' . e($r['currency']) . '</span>'
                : '<span class="muted">currency unknown</span>';
            if ($r['supports_multi_status'] === null) {
                $bits[] = '<small class="sub">multi-status not checked</small>';
            } else {
                $bits[] = '<small class="sub">multi-status ' .
                    ($r['supports_multi_status'] ? 'supported' : 'not supported') . '</small>';
            }
            if ($r['last_error'] !== '') {
                $bits[] = '<small class="sub" style="color:var(--danger)">' . e(excerpt($r['last_error'], 46)) . '</small>';
            }
            return implode('', $bits);
        }],
        ['label' => 'State', 'render' => fn($r) =>
            '<span class="st st-' . ($r['is_active'] ? 'completed' : 'cancelled') . '">'
            . ($r['is_active'] ? 'active' : 'off') . '</span>'],
    ],
    'fields' => [
        'name'       => ['label' => 'Name', 'required' => true,
                         'hint' => 'Just for you - customers never see it.'],
        'api_url'    => ['label' => 'API URL', 'required' => true, 'rules' => ['url'],
                         'placeholder' => 'https://provider.example/api/v2'],
        'api_key'    => ['label' => 'API key', 'type' => 'password', 'skip_empty' => true,
                         'required' => true, 'hint' => 'Leave empty when editing to keep the current key.'],
        'currency'   => ['label' => 'Bills in', 'type' => 'select', 'options' => $currencyOptions,
                         'empty' => 'Detect from the balance reply',
                         'hint' => 'Its rates are converted from this into your base currency before '
                                 . 'your markup is applied.'],
        'supports_multi_status' => ['label' => 'Multiple status in one request', 'type' => 'select',
                         'options' => [1 => 'Supported', 0 => 'Not supported'],
                         'empty' => 'Work it out automatically', 'nullable' => true,
                         'hint' => 'Most providers take up to 100 order ids at once; some only one. '
                                 . 'Leave it automatic and press Multi-status on the list to ask the '
                                 . 'provider - Check asks as well.'],
        'auto_sync'  => ['label' => 'Include in the scheduled service sync', 'type' => 'checkbox', 'default' => 1],
        'sort_order' => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
        'is_active'  => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1,
                         'hint' => 'Inactive providers are skipped by import, sending and status sync.'],
    ],
    'can_delete' => function (int $id) {
        $used = (int) col('SELECT COUNT(*) FROM services WHERE provider_id = ?', [$id], 0);
        return $used > 0
            ? "This provider still has {$used} service(s). Delete or reassign them first."
            : null;
    },
], $params);
