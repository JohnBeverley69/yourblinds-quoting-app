<?php
declare(strict_types=1);

/**
 * Choices editor — spreadsheet-style inline grid.
 *
 * Every cell is editable in place: type-and-blur or type-and-tab/enter
 * to save. Adding a new choice = type a label in the bottom row, hit
 * Enter. No separate form, no page reloads, no detour through an edit
 * page for routine tweaks. The detour only kicks in for width tables
 * + thumbnail uploads (the "…" link on each row), since those need a
 * proper file picker / textarea.
 *
 * All writes go through /admin/products/choice-api.php which validates
 * tenant scope, the field whitelist, and the "one default per (extra,
 * system)" invariant.
 *
 * The page itself only renders the initial HTML and seeds the JS with
 * the systems list + extra id. Once the JS is up, the page never reloads.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$extraId = (int) ($_GET['id'] ?? 0);
if ($extraId <= 0) {
    header('Location: /admin/products/index.php');
    exit;
}

// Tenant-scoped lookup of the extra + its parent product.
$loadStmt = db()->prepare(
    'SELECT e.id, e.product_id, e.name, e.is_required, e.active,
            p.name AS product_name
       FROM product_extras e
       JOIN products p ON p.id = e.product_id
      WHERE e.id = ? AND e.client_id = ?'
);
$loadStmt->execute([$extraId, $clientId]);
$extra = $loadStmt->fetch();

if (!$extra) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Option not found</h1>';
    exit;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Sort by sort_order alone — drag-and-drop owns position. system_id
// is joined to its product_systems row so we can label the dropdown's
// selected option without a second query per row.
$rows = db()->prepare(
    'SELECT c.id, c.label, c.system_id,
            c.price_delta, c.price_percent, c.price_per_metre,
            c.is_default, c.sort_order, c.active, c.image_path,
            (SELECT COUNT(*) FROM extra_choice_price_rows r
              WHERE r.product_extra_choice_id = c.id) AS width_table_size
       FROM product_extra_choices c
      WHERE c.product_extra_id = ?
   ORDER BY c.sort_order, c.label'
);
$rows->execute([$extraId]);
$choices = $rows->fetchAll();

// Systems available on this product, for every system dropdown the
// page renders (existing rows + the bottom blank row).
$sysStmt = db()->prepare(
    'SELECT id, name FROM product_systems
      WHERE product_id = ? AND client_id = ?
   ORDER BY sort_order, name'
);
$sysStmt->execute([(int) $extra['product_id'], $clientId]);
$systems = $sysStmt->fetchAll();

// Helper closure used both for existing rows and the bottom blank row.
// Encapsulates the dropdown HTML so the markup below stays compact.
$renderSystemSelect = static function (?int $selected, string $cls = '') use ($systems): string {
    $opts = '<option value="0"' . ($selected === null ? ' selected' : '') . '>All systems</option>';
    foreach ($systems as $s) {
        $sid = (int) $s['id'];
        $sel = ($selected === $sid) ? ' selected' : '';
        $opts .= '<option value="' . $sid . '"' . $sel . '>'
               . e((string) $s['name']) . '</option>';
    }
    return '<select class="cell-select' . ($cls !== '' ? ' ' . $cls : '') . '">' . $opts . '</select>';
};

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e((string) $extra['name']) ?> &middot; Choices &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        /* Spreadsheet-style choices grid. Each cell looks plain until
           focused, then shows a clear edit affordance. Aim is to make
           the page feel like a tight data grid, not a form-and-list. */
        .grid-table { width: 100%; border-collapse: collapse; }
        .grid-table thead th {
            text-align: left; font-size: 0.75rem; font-weight: 700;
            color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;
            padding: 0.5rem 0.5rem; border-bottom: 2px solid #e5e7eb;
            background: #f9fafb;
        }
        .grid-table tbody td {
            padding: 0.25rem 0.25rem; border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        .grid-table tbody tr:hover td { background: #fafbfd; }
        .grid-table tbody tr.is-saving td { background: #fefce8; }
        .grid-table tbody tr.just-saved td {
            background: #ecfdf5; transition: background 600ms ease-out;
        }
        .grid-table tbody tr.is-error td { background: #fef2f2; }
        .grid-table tbody tr.is-inactive td .cell-input,
        .grid-table tbody tr.is-inactive td .cell-select {
            opacity: 0.55; text-decoration: line-through;
        }

        /* Editable cells — invisible until interaction. */
        .cell-input, .cell-select {
            font: inherit; width: 100%; box-sizing: border-box;
            padding: 0.4375rem 0.5rem; background: transparent;
            border: 1px solid transparent; border-radius: 6px;
            color: #111827;
        }
        .cell-input.num { text-align: right; font-variant-numeric: tabular-nums; }
        /* Suppress the browser's number-input spinners — the right-aligned
           digits read better without them, and the user can edit freely. */
        .cell-input.num::-webkit-outer-spin-button,
        .cell-input.num::-webkit-inner-spin-button {
            -webkit-appearance: none; margin: 0;
        }
        .cell-input.num { -moz-appearance: textfield; }
        .cell-input:hover, .cell-select:hover {
            border-color: #d1d5db; background: #fff;
        }
        .cell-input:focus, .cell-select:focus {
            outline: none; border-color: #1f3b5b; background: #fff;
            box-shadow: 0 0 0 3px rgba(31, 59, 91, 0.12);
        }

        .grid-table th.col-drag,    .grid-table td.col-drag    { width: 28px; padding-left: 0.25rem; padding-right: 0; color: #9ca3af; cursor: grab; text-align: center; }
        .grid-table th.col-label,   .grid-table td.col-label   { min-width: 180px; }
        .grid-table th.col-system,  .grid-table td.col-system  { width: 200px; }
        .grid-table th.col-price,   .grid-table td.col-price   { width: 96px; }
        .grid-table th.col-toggle,  .grid-table td.col-toggle  { width: 72px; text-align: center; }
        .grid-table th.col-actions, .grid-table td.col-actions { width: 130px; text-align: right; white-space: nowrap; }

        .col-toggle input[type="checkbox"] {
            width: 18px; height: 18px; cursor: pointer; margin: 0;
        }

        .row-actions a, .row-actions button {
            font-size: 0.8125rem; padding: 0.25rem 0.5rem; margin: 0 0 0 0.125rem;
            border: 0; background: transparent; cursor: pointer; border-radius: 6px;
            color: #1f3b5b; text-decoration: none;
        }
        .row-actions a:hover, .row-actions button:hover {
            background: #eef2f7;
        }
        .row-actions .btn-more { color: #4b5563; }
        .row-actions .btn-delete { color: #b91c1c; }
        .row-actions .btn-delete:hover { background: #fee2e2; }

        /* Bottom blank row gets a softer background so it reads as a
           "type to add" affordance rather than a real row. */
        .grid-table tr.new-row td { background: #f9fafb; }
        .grid-table tr.new-row td:first-child { color: #d1d5db; }
        .grid-table tr.new-row .cell-input::placeholder { color: #9ca3af; font-style: italic; }

        .row-error {
            color: #b91c1c; font-size: 0.8125rem; padding: 0.25rem 0.5rem 0;
        }

        .save-indicator {
            display: inline-block; font-size: 0.8125rem; color: #6b7280;
            margin-left: 0.5rem; opacity: 0; transition: opacity 200ms;
        }
        .save-indicator.is-visible { opacity: 1; }
        .save-indicator.is-error { color: #b91c1c; font-weight: 600; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <?= e((string) $extra['product_name']) ?> / <?= e((string) $extra['name']) ?>
                </h1>
                <p class="page-subtitle">
                    <a href="/admin/products/extras.php?product_id=<?= (int) $extra['product_id'] ?>">
                        &larr; All options for <?= e((string) $extra['product_name']) ?>
                    </a>
                    &middot;
                    <a href="/admin/products/extra-edit.php?id=<?= (int) $extraId ?>">Edit option</a>
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">
                    Choices <span id="choices-count">(<?= count($choices) ?>)</span>
                    <span id="save-indicator" class="save-indicator">Saving…</span>
                </h2>
            </div>
            <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 0.75rem">
                Click any cell to edit. Tab or Enter saves; Escape cancels.
                Type a label in the last row to add a new choice.
                Drag <strong>⋮⋮</strong> to reorder.
                <strong>…</strong> opens the row's full edit page (width-table pricing, thumbnail upload).
            </p>

            <div class="table-wrap">
                <table class="grid-table sortable-list" data-reorder-type="choices" id="choices-grid">
                    <thead>
                        <tr>
                            <th class="col-drag"></th>
                            <th class="col-label">Label</th>
                            <th class="col-system">Available on</th>
                            <th class="col-price">Flat £</th>
                            <th class="col-price">%</th>
                            <th class="col-price">£/m</th>
                            <th class="col-toggle" title="Default = pre-selected for the customer">Default</th>
                            <th class="col-toggle" title="Inactive = hidden from quote builder">Active</th>
                            <th class="col-actions"></th>
                        </tr>
                    </thead>
                    <tbody id="choices-body">
                        <?php foreach ($choices as $c): ?>
                            <?php
                                $cid       = (int) $c['id'];
                                $sysId     = $c['system_id'] !== null ? (int) $c['system_id'] : null;
                                $isActive  = (int) $c['active']     === 1;
                                $isDefault = (int) $c['is_default'] === 1;
                                $widthN    = (int) $c['width_table_size'];
                            ?>
                            <tr data-id="<?= $cid ?>" class="<?= $isActive ? '' : 'is-inactive' ?>">
                                <td class="col-drag drag-col" title="Drag to reorder">⋮⋮</td>
                                <td class="col-label">
                                    <input class="cell-input" data-field="label"
                                           value="<?= e((string) $c['label']) ?>"
                                           maxlength="150">
                                </td>
                                <td class="col-system">
                                    <?php
                                        // Manually emit the dropdown so we can attach data-field.
                                        echo str_replace(
                                            '<select class="cell-select"',
                                            '<select class="cell-select" data-field="system_id"',
                                            $renderSystemSelect($sysId)
                                        );
                                    ?>
                                </td>
                                <td class="col-price">
                                    <input class="cell-input num" data-field="price_delta"
                                           type="number" step="0.01"
                                           value="<?= number_format((float) $c['price_delta'], 2, '.', '') ?>">
                                </td>
                                <td class="col-price">
                                    <input class="cell-input num" data-field="price_percent"
                                           type="number" step="0.01"
                                           value="<?= number_format((float) $c['price_percent'], 2, '.', '') ?>">
                                </td>
                                <td class="col-price">
                                    <input class="cell-input num" data-field="price_per_metre"
                                           type="number" step="0.01"
                                           value="<?= number_format((float) $c['price_per_metre'], 2, '.', '') ?>">
                                </td>
                                <td class="col-toggle">
                                    <input type="checkbox" data-field="is_default"
                                           <?= $isDefault ? 'checked' : '' ?>>
                                </td>
                                <td class="col-toggle">
                                    <input type="checkbox" data-field="active"
                                           <?= $isActive ? 'checked' : '' ?>>
                                </td>
                                <td class="col-actions row-actions">
                                    <a href="/admin/products/extra-choice-edit.php?id=<?= $cid ?>"
                                       class="btn-more"
                                       title="Open full edit page — width-table pricing, thumbnail upload">…</a>
                                    <button type="button" class="btn-duplicate"
                                            title="Clone this choice (handy when the same label applies to another system)">
                                        Dup
                                    </button>
                                    <button type="button" class="btn-delete"
                                            title="Delete this choice">×</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Bottom "type to add" row. Always present, never has a server id. -->
                        <tr class="new-row" id="new-row">
                            <td class="col-drag">+</td>
                            <td class="col-label">
                                <input class="cell-input" id="new-label"
                                       placeholder="Type new label and press Enter…"
                                       maxlength="150">
                            </td>
                            <td class="col-system">
                                <?php
                                    echo str_replace(
                                        '<select class="cell-select"',
                                        '<select class="cell-select" id="new-system"',
                                        $renderSystemSelect(null)
                                    );
                                ?>
                            </td>
                            <td colspan="6" style="color:#9ca3af;font-size:0.8125rem">
                                Prices, default + active toggles become editable once the row is saved.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p style="color:#6b7280;font-size:0.875rem;margin-top:0.75rem">
                Need width-based pricing or a thumbnail image?
                Click <strong>…</strong> on any row.
            </p>
        </section>
    </main>
</div>

<?php require __DIR__ . '/../../_partials/sortable_init.php'; ?>

<script>
(function () {
    'use strict';

    var endpoint = '/admin/products/choice-api.php';
    var extraId  = <?= (int) $extraId ?>;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    var grid       = document.getElementById('choices-grid');
    var body       = document.getElementById('choices-body');
    var indicator  = document.getElementById('save-indicator');
    var countLabel = document.getElementById('choices-count');
    var newRow     = document.getElementById('new-row');
    var newLabel   = document.getElementById('new-label');
    var newSystem  = document.getElementById('new-system');

    var hideTimer  = null;
    function flashIndicator(message, isError) {
        clearTimeout(hideTimer);
        indicator.textContent = message;
        indicator.classList.toggle('is-error', !!isError);
        indicator.classList.add('is-visible');
        hideTimer = setTimeout(function () {
            indicator.classList.remove('is-visible');
        }, isError ? 4000 : 1100);
    }

    // Promise-based POST. Returns the parsed JSON; rejects on transport
    // errors or {ok:false} responses (with the server's error message).
    function api(action, params) {
        var fd = new FormData();
        fd.append('action',   action);
        fd.append('extra_id', String(extraId));
        Object.keys(params || {}).forEach(function (k) {
            // null/undefined are sent as empty strings so PHP sees them.
            fd.append(k, params[k] == null ? '' : String(params[k]));
        });
        return fetch(endpoint, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () {
                throw new Error('Server returned a non-JSON response.');
            }).then(function (data) {
                if (!data.ok) throw new Error(data.error || 'Unknown error.');
                return data;
            });
        });
    }

    // Visual save lifecycle on a row. Yellowish during, green flash on
    // success, red sticky on error.
    function withSavingState(row, promise) {
        if (!row) return promise;
        row.classList.remove('just-saved', 'is-error');
        row.classList.add('is-saving');
        return promise.then(function (data) {
            row.classList.remove('is-saving');
            row.classList.add('just-saved');
            setTimeout(function () { row.classList.remove('just-saved'); }, 700);
            flashIndicator('Saved');
            return data;
        }).catch(function (err) {
            row.classList.remove('is-saving');
            row.classList.add('is-error');
            flashIndicator(err.message || 'Save failed', true);
            throw err;
        });
    }

    // Pull the field value out of an input/select/checkbox in a way that
    // matches what the server expects to receive ("0"/"1" for checkboxes,
    // raw strings for everything else).
    function valueFromCell(el) {
        if (el.type === 'checkbox') return el.checked ? '1' : '0';
        return el.value;
    }

    // Track each editable cell's last-saved value so blur events that
    // didn't actually change anything skip the request.
    function captureLast(el) {
        el._lastSaved = valueFromCell(el);
    }

    function saveCell(el) {
        var row = el.closest('tr');
        if (!row || !row.dataset.id) return Promise.resolve();
        var field = el.dataset.field;
        if (!field) return Promise.resolve();
        var current = valueFromCell(el);
        if (el._lastSaved === current) return Promise.resolve();

        return withSavingState(row, api('update', {
            choice_id: row.dataset.id,
            field:     field,
            value:     current
        })).then(function () {
            el._lastSaved = current;

            // is_default is exclusive within a (extra, system) bucket on
            // the server. Mirror that on the client by un-checking
            // sibling defaults in the same bucket so the UI stays in
            // sync without a refetch.
            if (field === 'is_default' && el.checked) {
                var sysSel = row.querySelector('select[data-field="system_id"]');
                var rowSys = sysSel ? sysSel.value : '0';
                body.querySelectorAll('tr[data-id]').forEach(function (other) {
                    if (other === row) return;
                    var otherSysSel = other.querySelector('select[data-field="system_id"]');
                    var otherSys    = otherSysSel ? otherSysSel.value : '0';
                    if (otherSys === rowSys) {
                        var d = other.querySelector('input[data-field="is_default"]');
                        if (d && d.checked) {
                            d.checked = false;
                            d._lastSaved = '0';
                        }
                    }
                });
            }
            // active toggle changes whether the row's text reads as
            // strikethrough. Reflect immediately.
            if (field === 'active') {
                row.classList.toggle('is-inactive', !el.checked);
            }
        }).catch(function () {
            // On error, snap the value back so the user can retry.
            if (el._lastSaved !== undefined) {
                if (el.type === 'checkbox') el.checked = el._lastSaved === '1';
                else                        el.value   = el._lastSaved;
            }
        });
    }

    // Build a fresh row's DOM from a server-returned choice object.
    function buildRow(choice) {
        var tr = document.createElement('tr');
        tr.dataset.id = String(choice.id);
        if (!choice.active) tr.classList.add('is-inactive');

        function cellInput(field, value, opts) {
            var td = document.createElement('td');
            td.className = 'col-' + (opts.col || 'price');
            var inp = document.createElement('input');
            inp.className = 'cell-input' + (opts.num ? ' num' : '');
            inp.dataset.field = field;
            if (opts.type)      inp.type      = opts.type;
            if (opts.step)      inp.step      = opts.step;
            if (opts.maxlength) inp.maxLength = opts.maxlength;
            inp.value = value;
            captureLast(inp);
            td.appendChild(inp);
            return td;
        }
        function cellToggle(field, checked) {
            var td = document.createElement('td');
            td.className = 'col-toggle';
            var inp = document.createElement('input');
            inp.type = 'checkbox';
            inp.dataset.field = field;
            inp.checked = !!checked;
            captureLast(inp);
            td.appendChild(inp);
            return td;
        }

        // Drag handle. Both classes: col-drag for column width,
        // drag-col so the SortableJS init in sortable_init.php picks
        // it up as a draggable handle.
        var tdDrag = document.createElement('td');
        tdDrag.className = 'col-drag drag-col';
        tdDrag.title = 'Drag to reorder';
        tdDrag.textContent = '⋮⋮';
        tr.appendChild(tdDrag);

        // Label.
        tr.appendChild(cellInput('label', choice.label, { col: 'label', maxlength: 150 }));

        // System dropdown — clone the new-row's select so options match.
        var tdSys = document.createElement('td');
        tdSys.className = 'col-system';
        var sel = newSystem.cloneNode(true);
        sel.removeAttribute('id');
        sel.dataset.field = 'system_id';
        sel.value = choice.system_id == null ? '0' : String(choice.system_id);
        captureLast(sel);
        tdSys.appendChild(sel);
        tr.appendChild(tdSys);

        // Prices.
        tr.appendChild(cellInput('price_delta',     choice.price_delta,     { type: 'number', step: '0.01', num: true }));
        tr.appendChild(cellInput('price_percent',   choice.price_percent,   { type: 'number', step: '0.01', num: true }));
        tr.appendChild(cellInput('price_per_metre', choice.price_per_metre, { type: 'number', step: '0.01', num: true }));

        // Toggles.
        tr.appendChild(cellToggle('is_default', choice.is_default));
        tr.appendChild(cellToggle('active',     choice.active));

        // Actions.
        var tdActions = document.createElement('td');
        tdActions.className = 'col-actions row-actions';
        tdActions.innerHTML =
            '<a href="/admin/products/extra-choice-edit.php?id=' + choice.id +
                '" class="btn-more" title="Open full edit page">…</a>' +
            '<button type="button" class="btn-duplicate" title="Clone this choice">Dup</button>' +
            '<button type="button" class="btn-delete" title="Delete">&times;</button>';
        tr.appendChild(tdActions);

        return tr;
    }

    function updateCount() {
        var n = body.querySelectorAll('tr[data-id]').length;
        countLabel.textContent = '(' + n + ')';
    }

    // ---- Event wiring ----------------------------------------------------

    // Capture last-saved values on focus so blurs without changes are no-ops.
    body.addEventListener('focusin', function (e) {
        var el = e.target;
        if (el.matches('.cell-input, .cell-select, input[type="checkbox"]')) {
            // Only set if not already set this lifetime — first focus wins.
            if (el._lastSaved === undefined) captureLast(el);
        }
    });

    // Save existing-row cells on blur (text/number) or change (selects/checkboxes).
    body.addEventListener('change', function (e) {
        var el = e.target;
        if (el === newLabel || el === newSystem) return;
        if (el.matches('select.cell-select, input[type="checkbox"]')) {
            saveCell(el);
        }
    });
    body.addEventListener('blur', function (e) {
        var el = e.target;
        if (el === newLabel || el === newSystem) return;
        if (el.matches('input.cell-input')) saveCell(el);
    }, true); // capture-phase since blur doesn't bubble

    // Keyboard niceties on existing-row inputs: Enter/Tab → save + move,
    // Escape → revert to last saved value.
    body.addEventListener('keydown', function (e) {
        var el = e.target;
        if (!el.matches('input.cell-input')) return;
        if (el === newLabel) return;
        if (e.key === 'Enter') {
            e.preventDefault();
            el.blur(); // triggers save
        } else if (e.key === 'Escape') {
            e.preventDefault();
            if (el._lastSaved !== undefined) el.value = el._lastSaved;
            el.blur();
        }
    });

    // Duplicate + delete.
    body.addEventListener('click', function (e) {
        var btn = e.target;
        if (btn.classList.contains('btn-duplicate')) {
            var row = btn.closest('tr');
            if (!row || !row.dataset.id) return;
            withSavingState(row, api('duplicate', { choice_id: row.dataset.id }))
                .then(function (data) {
                    var newRowEl = buildRow(data.choice);
                    row.parentNode.insertBefore(newRowEl, row.nextSibling);
                    updateCount();
                    var firstInput = newRowEl.querySelector('select[data-field="system_id"]');
                    if (firstInput) firstInput.focus();
                }).catch(function () { /* indicator already shows */ });
        } else if (btn.classList.contains('btn-delete')) {
            var row = btn.closest('tr');
            if (!row || !row.dataset.id) return;
            var label = row.querySelector('input[data-field="label"]');
            var name  = label ? label.value : 'this choice';
            if (!confirm('Delete "' + name + '"?')) return;
            withSavingState(row, api('delete', { choice_id: row.dataset.id }))
                .then(function () {
                    row.parentNode.removeChild(row);
                    updateCount();
                }).catch(function () { /* indicator already shows */ });
        }
    });

    // ---- New-row creation ------------------------------------------------

    function commitNewRow(focusNext) {
        var label = newLabel.value.trim();
        if (label === '') return Promise.resolve();
        var sysId = newSystem.value || '0';

        // Briefly highlight the new row while the request is in flight.
        newRow.classList.add('is-saving');
        return api('create', { label: label, system_id: sysId })
            .then(function (data) {
                newRow.classList.remove('is-saving');
                newRow.classList.add('just-saved');
                setTimeout(function () { newRow.classList.remove('just-saved'); }, 700);
                flashIndicator('Saved');

                // Insert the real row immediately above the new-row.
                var realRow = buildRow(data.choice);
                body.insertBefore(realRow, newRow);
                updateCount();

                // Reset the new-row inputs ready for another entry.
                newLabel.value = '';
                newSystem.value = '0';

                if (focusNext) newLabel.focus();
            })
            .catch(function (err) {
                newRow.classList.remove('is-saving');
                newRow.classList.add('is-error');
                flashIndicator(err.message || 'Could not add', true);
                setTimeout(function () { newRow.classList.remove('is-error'); }, 2000);
            });
    }

    newLabel.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            commitNewRow(true); // focus stays in label for rapid entry
        } else if (e.key === 'Escape') {
            newLabel.value = '';
            newSystem.value = '0';
        }
    });
    // Tab from label → system dropdown (default behaviour). Tab from
    // system dropdown → trigger save and move on, otherwise the user's
    // typed label would silently sit unsaved.
    newSystem.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            commitNewRow(true);
        }
    });
    // Blurring the new-row entirely (focus moves outside both inputs)
    // should also commit — small delay so Tab from label → system
    // doesn't trip it. Same handler on both inputs so leaving via
    // either one saves the typed label.
    function maybeCommitNewRow() {
        setTimeout(function () {
            if (document.activeElement !== newSystem
             && document.activeElement !== newLabel
             && newLabel.value.trim() !== '') {
                commitNewRow(false);
            }
        }, 120);
    }
    newLabel.addEventListener('blur',  maybeCommitNewRow);
    newSystem.addEventListener('blur', maybeCommitNewRow);
})();
</script>
</body>
</html>
