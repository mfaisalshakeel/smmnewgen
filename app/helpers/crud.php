<?php
/**
 * Shared admin CRUD.
 *
 * A controller describes its table once - the columns to list and the fields
 * to edit - and this runs the whole list / create / edit / delete / toggle
 * cycle against app/views/admin/crud/list.php and form.php.
 *
 * Field definition keys:
 *   type      text|textarea|number|select|checkbox|password|color|email|url|hidden
 *   label     shown above the input
 *   hint      small grey line under the input
 *   options   [value => label] for select
 *   required  bool
 *   default   value for a new row
 *   rules     ['slug'] | ['email'] | ['url'] | ['min:n'] | ['max:n'] | ['unique']
 *   save      callable(mixed $value, array $input): mixed  - transform before storing
 *   skip_empty bool - leave the stored value alone when submitted empty (passwords)
 */
require_once APP_PATH . '/helpers/audit.php';


function crud_handle(array $spec, array $params): void
{
    $table   = $spec['table'];
    $base    = $spec['base'];              // e.g. 'admin/providers'
    $action  = $params[0] ?? 'index';
    $id      = isset($params[1]) ? (int) $params[1] : (ctype_digit((string) $action) ? (int) $action : 0);

    if (ctype_digit((string) $action)) {
        $action = 'edit';
    }

    switch ($action) {
        case 'new':
        case 'edit':
            crud_form($spec, $id);
            return;

        case 'save':
            crud_save($spec);
            return;

        case 'delete':
            crud_delete($spec, (int) ($_POST['id'] ?? 0));
            return;

        case 'toggle':
            crud_toggle($spec, (int) ($_POST['id'] ?? 0));
            return;

        default:
            crud_list($spec);
    }
}

// ---------------------------------------------------------------------------

function crud_list(array $spec): void
{
    $table  = $spec['table'];
    $order  = $spec['order'] ?? 'id DESC';
    $search = trim((string) ($_GET['q'] ?? ''));

    $where  = [];
    $params = [];

    if ($search !== '' && !empty($spec['search'])) {
        $parts = [];
        foreach ($spec['search'] as $column) {
            $parts[]  = "`$column` LIKE ?";
            $params[] = '%' . $search . '%';
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    foreach ($spec['filters'] ?? [] as $key => $filter) {
        $value = $_GET[$key] ?? '';
        if ($value !== '' && $value !== null) {
            $where[]  = '`' . ($filter['column'] ?? $key) . '` = ?';
            $params[] = $value;
        }
    }

    $sql  = "SELECT * FROM `$table`";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $order;

    view('admin/crud/list', [
        'title'    => $spec['title'],
        'subtitle' => $spec['subtitle'] ?? '',
        'spec'     => $spec,
        'rows'     => all($sql, $params),
        'search'   => $search,
    ], 'layouts/admin');
}

function crud_form(array $spec, int $id): void
{
    $row = [];
    if ($id > 0) {
        $row = one("SELECT * FROM `{$spec['table']}` WHERE id = ?", [$id]);
        if (!$row) {
            flash('error', 'That record no longer exists.');
            redirect($spec['base']);
        }
    } else {
        foreach ($spec['fields'] as $name => $field) {
            $row[$name] = $field['default'] ?? '';
        }
    }

    view('admin/crud/form', [
        'title'    => ($id ? 'Edit ' : 'New ') . strtolower($spec['single'] ?? 'record'),
        'subtitle' => $spec['title'],
        'spec'     => $spec,
        'row'      => $row,
        'id'       => $id,
    ], 'layouts/admin');
}

function crud_save(array $spec): void
{
    $id     = (int) ($_POST['id'] ?? 0);
    $data   = [];
    $errors = [];

    foreach ($spec['fields'] as $name => $field) {
        $type  = $field['type'] ?? 'text';
        $value = $_POST[$name] ?? null;

        if ($type === 'checkbox') {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }

        $value = is_string($value) ? trim($value) : $value;

        // Passwords and similar: an empty box means "leave it as it was".
        if (!empty($field['skip_empty']) && ($value === '' || $value === null)) {
            continue;
        }

        if (!empty($field['required']) && ($value === '' || $value === null)) {
            $errors[$name] = ($field['label'] ?? $name) . ' is required.';
            continue;
        }

        foreach ($field['rules'] ?? [] as $rule) {
            [$rule, $arg] = array_pad(explode(':', $rule, 2), 2, null);

            if ($rule === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = 'Enter a valid email address.';
            }
            if ($rule === 'url' && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[$name] = 'Enter a full URL including https://';
            }
            if ($rule === 'slug' && $value !== '' && !preg_match('/^[a-z0-9-]+$/', $value)) {
                $errors[$name] = 'Use lowercase letters, numbers and dashes only.';
            }
            if ($rule === 'min' && $value !== '' && (float) $value < (float) $arg) {
                $errors[$name] = 'Must be at least ' . $arg . '.';
            }
            if ($rule === 'max' && $value !== '' && (float) $value > (float) $arg) {
                $errors[$name] = 'Must be at most ' . $arg . '.';
            }
            if ($rule === 'unique' && $value !== '') {
                $clash = $id
                    ? col("SELECT 1 FROM `{$spec['table']}` WHERE `$name` = ? AND id <> ?", [$value, $id])
                    : col("SELECT 1 FROM `{$spec['table']}` WHERE `$name` = ?", [$value]);
                if ($clash) {
                    $errors[$name] = 'That value is already used.';
                }
            }
        }

        // A field the request did not carry at all arrives as null, which a
        // NOT NULL column rejects. Only a field that says `nullable` is meant
        // to store NULL; everything else falls back to its default.
        //
        // `empty` is the label on a select's blank choice and says nothing
        // about the column - "Detect from the balance reply" is an invitation
        // to leave it blank, not a request to write NULL into a NOT NULL
        // column.
        if ($value === null || $value === '') {
            $value = !empty($field['nullable']) ? null : ($field['default'] ?? '');
        }

        if (isset($field['save']) && is_callable($field['save'])) {
            $value = $field['save']($value, $_POST);
        }

        $data[$name] = $value;
    }

    if ($errors) {
        keep_old($_POST);
        $_SESSION['_errors'] = $errors;
        flash('error', 'Please fix the highlighted fields.');
        redirect($spec['base'] . ($id ? '/edit/' . $id : '/new'));
    }

    if (isset($spec['before_save']) && is_callable($spec['before_save'])) {
        $data = $spec['before_save']($data, $id, $_POST);
    }

    // Hooked once here rather than in each screen: providers, platforms,
    // categories, payment methods, pages and FAQs all save through this, and
    // an API key or a payment account changing is exactly what the record is
    // for. audit_mask() keeps the secrets out of it.
    if ($id > 0) {
        if (!empty($spec['timestamps'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        $before = one('SELECT * FROM `' . $spec['table'] . '` WHERE id = ?', [$id]);
        update_row($spec['table'], $data, 'id = ?', [$id]);
        audit_row($spec['table'] . '.updated', $spec['table'], $id, $before,
            ['summary' => ($spec['single'] ?? 'Record') . ' #' . $id . ' updated',
             'severity' => 'warn']);
        flash('success', ($spec['single'] ?? 'Record') . ' updated.');
    } else {
        if (!empty($spec['timestamps'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        $id = insert_row($spec['table'], $data);
        audit_row($spec['table'] . '.created', $spec['table'], $id, [],
            ['summary' => ($spec['single'] ?? 'Record') . ' #' . $id . ' created',
             'severity' => 'warn']);
        flash('success', ($spec['single'] ?? 'Record') . ' created.');
    }

    if (isset($spec['after_save']) && is_callable($spec['after_save'])) {
        $spec['after_save']($id, $data, $_POST);
    }

    redirect($spec['base']);
}

function crud_delete(array $spec, int $id): void
{
    if ($id <= 0) {
        redirect($spec['base']);
    }

    if (isset($spec['can_delete']) && is_callable($spec['can_delete'])) {
        $reason = $spec['can_delete']($id);
        if (is_string($reason)) {
            flash('error', $reason);
            redirect($spec['base']);
        }
    }

    // Read it first: once it is gone there is nothing left to say what it was,
    // and "who deleted that provider" is the question people actually ask.
    $before = one('SELECT * FROM `' . $spec['table'] . '` WHERE id = ?', [$id]);
    delete_row($spec['table'], 'id = ?', [$id]);
    audit($spec['table'] . '.deleted', [
        'entity' => $spec['table'], 'entity_id' => $id, 'severity' => 'alert',
        'summary' => ($spec['single'] ?? 'Record') . ' #' . $id . ' deleted'
                   . (isset($before['name']) ? ' (' . $before['name'] . ')' : ''),
        'before'  => $before ?: [],
        'after'   => [],
    ]);
    flash('success', ($spec['single'] ?? 'Record') . ' deleted.');
    redirect($spec['base']);
}

function crud_toggle(array $spec, int $id): void
{
    $column = $spec['toggle'] ?? 'is_active';
    if ($id > 0) {
        q("UPDATE `{$spec['table']}` SET `$column` = 1 - `$column` WHERE id = ?", [$id]);
        $now = col("SELECT `$column` FROM `{$spec['table']}` WHERE id = ?", [$id], 0);
        audit($spec['table'] . '.toggled', [
            'entity' => $spec['table'], 'entity_id' => $id, 'severity' => 'warn',
            'summary' => ($spec['single'] ?? 'Record') . ' #' . $id . ' switched '
                       . ($now ? 'on' : 'off'),
            'before'  => [$column => $now ? 0 : 1],
            'after'   => [$column => $now ? 1 : 0],
        ]);
    }
    redirect($spec['base']);
}

/** Validation errors kept for one redirect. */
function crud_errors(): array
{
    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);
    return $errors;
}

/** [id => label] helper for building select options from a table. */
function options_from(string $table, string $labelColumn = 'name', string $where = '1'): array
{
    $options = [];
    foreach (all("SELECT id, `$labelColumn` AS label FROM `$table` WHERE $where ORDER BY `$labelColumn`") as $row) {
        $options[$row['id']] = $row['label'];
    }
    return $options;
}
