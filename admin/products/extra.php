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

// -----------------------------------------------------------------------
// Sub-option create/delete handlers — let the trade user manage follow-
// up options without leaving this page. Creating sets up the
// product_extras row + the parent_choice junction rows so the new
// sub-option is immediately gated correctly.
// -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['_action'] ?? '') === 'create_sub_option') {
    csrf_check();
    $name        = trim((string) ($_POST['name'] ?? ''));
    $isRequired  = !empty($_POST['is_required']) ? 1 : 0;
    $parentIds   = array_values(array_unique(array_filter(array_map(
        'intval',
        is_array($_POST['parent_choice_ids'] ?? null) ? $_POST['parent_choice_ids'] : []
    ))));
    $afterCreate = (string) ($_POST['after_create'] ?? 'stay');  // stay | open

    if ($name === '') {
        $_SESSION['flash_error'] = 'Sub-option name is required.';
    } elseif (!$parentIds) {
        $_SESSION['flash_error'] = 'Pick at least one parent choice to gate the sub-option.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Sort below this option so it lists near the parent.
            $sortStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), -1) + 1
                   FROM product_extras
                  WHERE product_id = ? AND client_id = ?'
            );
            $sortStmt->execute([(int) $extra['product_id'], $clientId]);
            $nextSort = (int) $sortStmt->fetchColumn();

            // Validate every ticked parent belongs to THIS option's
            // choices (POST inputs aren't trustworthy).
            $ph = implode(',', array_fill(0, count($parentIds), '?'));
            $vps = $pdo->prepare(
                "SELECT id FROM product_extra_choices
                  WHERE id IN ($ph) AND product_extra_id = ?"
            );
            $vps->execute([...$parentIds, $extraId]);
            $validParents = array_map('intval', $vps->fetchAll(PDO::FETCH_COLUMN));
            if (!$validParents) {
                throw new RuntimeException('None of the ticked parents belong to this option.');
            }

            // Insert the new extra row. Legacy parent_choice_id gets
            // the first parent for back-compat.
            $ins = $pdo->prepare(
                'INSERT INTO product_extras
                   (client_id, product_id, parent_choice_id, name, is_required, sort_order, active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            );
            $ins->execute([
                $clientId, (int) $extra['product_id'],
                $validParents[0], $name, $isRequired, $nextSort,
            ]);
            $newId = (int) $pdo->lastInsertId();

            // Junction rows for every parent.
            $jIns = $pdo->prepare(
                'INSERT INTO product_extra_parent_choices
                   (product_extra_id, product_extra_choice_id) VALUES (?, ?)'
            );
            foreach ($validParents as $pcid) {
                $jIns->execute([$newId, $pcid]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Sub-option "' . $name . '" added.';
            if ($afterCreate === 'open') {
                header('Location: /admin/products/extra.php?id=' . $newId);
            } else {
                header('Location: /admin/products/extra.php?id=' . $extraId . '#sub-options');
            }
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not add sub-option: ' . $e->getMessage();
            header('Location: /admin/products/extra.php?id=' . $extraId . '#sub-options');
            exit;
        }
    }
    header('Location: /admin/products/extra.php?id=' . $extraId . '#sub-options');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['_action'] ?? '') === 'delete_sub_option') {
    csrf_check();
    $subId = (int) ($_POST['sub_id'] ?? 0);
    if ($subId > 0) {
        $pdo = db();
        // Tenant-scope check via JOIN — only delete if the sub-option
        // belongs to this client AND is genuinely a child of one of
        // THIS extra's choices.
        $okSt = $pdo->prepare(
            'SELECT 1
               FROM product_extras se
               JOIN product_extra_parent_choices pepc
                 ON pepc.product_extra_id = se.id
               JOIN product_extra_choices pc
                 ON pc.id = pepc.product_extra_choice_id
              WHERE se.id = ? AND se.client_id = ?
                AND pc.product_extra_id = ?
              LIMIT 1'
        );
        $okSt->execute([$subId, $clientId, $extraId]);
        if ($okSt->fetchColumn()) {
            $pdo->prepare('DELETE FROM product_extras WHERE id = ? AND client_id = ?')
                ->execute([$subId, $clientId]);
            $_SESSION['flash_success'] = 'Sub-option removed.';
        } else {
            $_SESSION['flash_error'] = 'Sub-option not found.';
        }
    }
    header('Location: /admin/products/extra.php?id=' . $extraId . '#sub-options');
    exit;
}

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

// -----------------------------------------------------------------------
// Sub-options — any product_extras row gated by one or more of THIS
// option's choices. Loaded WITH their full choice lists so each one
// gets its own inline grid below — no round-trip to a separate page
// to manage them.
// -----------------------------------------------------------------------
$subOptions = [];
$parentLabelsBySub = [];
$choicesBySub      = [];
if ($choices) {
    $myChoiceIds = array_map(static fn ($c) => (int) $c['id'], $choices);
    $ph = implode(',', array_fill(0, count($myChoiceIds), '?'));
    $subSt = db()->prepare(
        "SELECT DISTINCT se.id, se.name, se.is_required, se.active, se.sort_order
           FROM product_extras se
           JOIN product_extra_parent_choices pepc ON pepc.product_extra_id = se.id
          WHERE pepc.product_extra_choice_id IN ($ph)
            AND se.product_id = ? AND se.client_id = ?
            AND se.id != ?
       ORDER BY se.sort_order, se.name"
    );
    $subSt->execute([...$myChoiceIds, (int) $extra['product_id'], $clientId, $extraId]);
    $subOptions = $subSt->fetchAll();

    if ($subOptions) {
        $subIds = array_map(static fn ($s) => (int) $s['id'], $subOptions);
        $sph    = implode(',', array_fill(0, count($subIds), '?'));

        // Parent badges: which of this option's choices gate each sub.
        $plSt = db()->prepare(
            "SELECT pepc.product_extra_id, c.label
               FROM product_extra_parent_choices pepc
               JOIN product_extra_choices c ON c.id = pepc.product_extra_choice_id
              WHERE pepc.product_extra_id IN ($sph)
                AND c.product_extra_id = ?
           ORDER BY c.sort_order, c.label"
        );
        $plSt->execute([...$subIds, $extraId]);
        foreach ($plSt->fetchAll() as $r) {
            $parentLabelsBySub[(int) $r['product_extra_id']][] = (string) $r['label'];
        }

        // Full choice rows per sub — drives the inline grids.
        $scSt = db()->prepare(
            "SELECT c.id, c.product_extra_id, c.label, c.system_id,
                    c.price_delta, c.price_percent, c.price_per_metre,
                    c.is_default, c.sort_order, c.active,
                    (SELECT COUNT(*) FROM extra_choice_price_rows r
                      WHERE r.product_extra_choice_id = c.id) AS width_table_size
               FROM product_extra_choices c
              WHERE c.product_extra_id IN ($sph)
           ORDER BY c.product_extra_id, c.sort_order, c.label"
        );
        $scSt->execute($subIds);
        foreach ($scSt->fetchAll() as $r) {
            $choicesBySub[(int) $r['product_extra_id']][] = $r;
        }
    }
}

// Systems available on this product, for every system dropdown the
// page renders (existing rows + the bottom blank row).
$sysStmt = db()->prepare(
    'SELECT id, name FROM product_systems
      WHERE product_id = ? AND client_id = ?
   ORDER BY sort_order, name'
);
$sysStmt->execute([(int) $extra['product_id'], $clientId]);
$systems = $sysStmt->fetchAll();

// Helper closure for an existing row's "Available on" multi-select.
//
// Each existing row gets a checkbox dropdown identical-looking to the
// new-row one. The current row's own system is pre-ticked AND disabled
// (use × to delete the row instead). Every OTHER specific-system
// checkbox is clickable, including on "All systems" rows — ticking one
// spawns a sibling row for that system. The user can then × the
// original "All systems" row to finish converting to specific scopes.
//
// "All systems" itself is always disabled — converting a specific row
// into an All-systems row from the dropdown would either silently
// overwrite the row's system_id (destructive) or create a redundant
// row, and the × + re-add path is clearer.
$renderSystemMultiSelect = static function (?int $currentSystemId) use ($systems): string {
    $isAll = $currentSystemId === null;

    // Summary text: current system name (or "All systems").
    $summaryText = 'All systems';
    if (!$isAll) {
        foreach ($systems as $s) {
            if ((int) $s['id'] === $currentSystemId) {
                $summaryText = (string) $s['name'];
                break;
            }
        }
    }

    $html  = '<details class="multi-select row-multi">';
    $html .= '<summary>' . e($summaryText) . '</summary>';
    $html .= '<div class="multi-opts">';

    // "All systems" — ticked + disabled if this is the current state.
    // Always disabled to keep the conversion path explicit (× + re-add).
    $html .= '<label>'
           . '<input type="checkbox" class="row-system-tick" data-system="0"'
           . ($isAll ? ' checked' : '') . ' disabled>'
           . ' <strong>All systems</strong>'
           . '</label>';

    if ($systems) {
        $html .= '<hr>';
        foreach ($systems as $s) {
            $sid       = (int) $s['id'];
            $isCurrent = ($sid === $currentSystemId);
            // Only the current row's own system is locked. Others are
            // always clickable (even on All-systems rows) so the user
            // can spawn siblings to convert into specific scopes.
            $html .= '<label>'
                   . '<input type="checkbox" class="row-system-tick" data-system="' . $sid . '"'
                   . ($isCurrent ? ' checked' : '')
                   . ($isCurrent ? ' disabled' : '')
                   . '> ' . e((string) $s['name'])
                   . '</label>';
        }
    }
    $html .= '</div></details>';
    return $html;
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
        .row-actions .btn-sub  { color: #15803d; }
        .row-actions .btn-sub:hover { background: #dcfce7; }
        .row-actions .btn-delete { color: #b91c1c; }
        .row-actions .btn-delete:hover { background: #fee2e2; }

        /* Bottom blank row gets a softer background so it reads as a
           "type to add" affordance rather than a real row. */
        .grid-table tr.new-row td { background: #f9fafb; }
        .grid-table tr.new-row td:first-child { color: #d1d5db; }
        .grid-table tr.new-row .cell-input::placeholder { color: #9ca3af; font-style: italic; }

        /* Multi-system selector on the new-row. Built on <details> so
           we get show/hide for free; the styling makes the closed
           summary look like a normal cell-select, and the open panel
           floats above the table with a checkbox per system. */
        .multi-select { position: relative; }
        .multi-select > summary {
            list-style: none; cursor: pointer;
            font: inherit; padding: 0.4375rem 1.75rem 0.4375rem 0.5rem;
            border: 1px solid transparent; border-radius: 6px;
            background: transparent; color: #111827;
            position: relative;
        }
        .multi-select > summary::-webkit-details-marker { display: none; }
        .multi-select > summary::after {
            content: '▾'; position: absolute; right: 0.5rem; top: 50%;
            transform: translateY(-50%); color: #6b7280; font-size: 0.75rem;
        }
        .multi-select > summary:hover {
            border-color: #d1d5db; background: #fff;
        }
        .multi-select[open] > summary {
            border-color: #1f3b5b; background: #fff;
            box-shadow: 0 0 0 3px rgba(31, 59, 91, 0.12);
        }
        .multi-opts {
            position: absolute; top: 100%; left: 0; right: 0;
            margin-top: 4px; padding: 0.375rem;
            background: #fff; border: 1px solid #d1d5db;
            border-radius: 8px; box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            z-index: 30; min-width: 200px;
        }
        .multi-opts label {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.375rem 0.5rem; cursor: pointer; border-radius: 6px;
            font-size: 0.9375rem; color: #111827;
        }
        .multi-opts label:hover { background: #eef2f7; }
        .multi-opts input[type="checkbox"] {
            width: 16px; height: 16px; margin: 0;
        }
        .multi-opts hr {
            margin: 0.25rem 0; border: 0; border-top: 1px solid #f3f4f6;
        }

        .row-error {
            color: #b91c1c; font-size: 0.8125rem; padding: 0.25rem 0.5rem 0;
        }

        .save-indicator {
            display: inline-block; font-size: 0.8125rem; color: #6b7280;
            margin-left: 0.5rem; opacity: 0; transition: opacity 200ms;
        }
        .save-indicator.is-visible { opacity: 1; }
        .save-indicator.is-error { color: #b91c1c; font-weight: 600; }

        /* ===========================================================
           Sub-options section — cards for each follow-up option
           gated by THIS option's choices, plus a collapsible
           "+ Add sub-option" form below. Lets the trade user manage
           the whole tree without round-tripping through the Add
           Option page.
           =========================================================== */
        .sub-card {
            background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px;
            padding: 0.75rem 0.875rem;
            margin-bottom: 0.875rem;
        }
        .sub-card.is-inactive { opacity: 0.7; }
        .sub-card-head {
            display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
            margin-bottom: 0.625rem;
        }
        .sub-card-name { font-size: 1rem; color: #111827; }
        .sub-card-gates {
            font-size: 0.8125rem; color: #6b7280;
            display: inline-flex; align-items: center; gap: 0.3125rem; flex-wrap: wrap;
        }
        .gate-pill {
            display: inline-block; padding: 0.0625rem 0.5rem;
            background: #eef2f7; color: #1f3b5b;
            border-radius: 999px; font-size: 0.75rem; font-weight: 600;
        }
        .sub-card-actions {
            display: inline-flex; flex-wrap: wrap; gap: 0.25rem 0.625rem;
            font-size: 0.8125rem;
            margin-left: auto;
        }
        .sub-card-actions a, .sub-card-actions button {
            background: transparent; border: 0; padding: 0; cursor: pointer;
            font: inherit; text-decoration: none;
        }
        .sub-card-actions .btn-primary-link   { color: #1f3b5b; font-weight: 600; }
        .sub-card-actions .btn-secondary-link { color: #4b5563; }
        .sub-card-actions .btn-danger-link    { color: #b91c1c; }
        .sub-card-actions a:hover, .sub-card-actions button:hover { text-decoration: underline; }
        /* Sub-option's inline grid keeps the same look but a subtle
           background so it's visually grouped with its card header. */
        .sub-card > .choices-grid-wrap {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
        }

        details.add-sub-form > summary {
            cursor: pointer; padding: 0.5rem 0.75rem;
            background: #eef2f7; border: 1px dashed #93c5fd;
            border-radius: 8px; color: #1f3b5b; font-weight: 600;
            font-size: 0.9375rem; list-style: none;
            width: max-content;
        }
        details.add-sub-form > summary::-webkit-details-marker { display: none; }
        details.add-sub-form > summary:hover { background: #dbeafe; }
        details.add-sub-form[open] > summary { background: #dbeafe; }
        details.add-sub-form > .form {
            margin-top: 0.75rem;
            background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
            padding: 1rem;
        }
        .req-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 700; color: #fff; background: #1f3b5b;
            border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em;
        }
        .opt-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 700; color: #6b7280; background: #f3f4f6;
            border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em;
        }
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
                    Choices (<?= count($choices) ?>)
                    <span id="save-indicator" class="save-indicator">Saving…</span>
                </h2>
            </div>
            <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 0.75rem">
                Click any cell to edit. Tab or Enter saves; Escape cancels.
                Type a label in the last row to add a new choice.
                Drag <strong>⋮⋮</strong> to reorder.
                <strong>Edit</strong> on a row opens its full edit page (width-table pricing, thumbnail image).
            </p>

            <?php
                $gridExtraId = $extraId;
                $gridChoices = $choices;
                $productId   = (int) $extra['product_id'];
                require __DIR__ . '/../../_partials/choices_grid.php';
            ?>
        </section>

        <!-- ============== SUB-OPTIONS (follow-ups gated by this option's choices) ============== -->
        <section class="section" id="sub-options">
            <div class="section-header">
                <h2 class="section-title">
                    Sub-options <span style="color:#6b7280;font-weight:400">(<?= count($subOptions) ?>)</span>
                </h2>
            </div>
            <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 0.75rem">
                Follow-up options that only show in the quote builder when specific choices above
                are selected — e.g. <em>Colour</em> appears only when <em>Bottom Weight = Chained
                OR Chainless</em>. One sub-option can be gated by multiple parent choices, so you
                don't have to duplicate the choice list.
            </p>

            <?php if (!$subOptions): ?>
                <p style="color:#6b7280;font-size:0.875rem;margin:0 0 1rem;font-style:italic">
                    None yet. Tick parent choices in the form below to add one.
                </p>
            <?php else: ?>
                <?php foreach ($subOptions as $sub):
                    $sid       = (int) $sub['id'];
                    $gates     = $parentLabelsBySub[$sid] ?? [];
                    $subChoices = $choicesBySub[$sid] ?? [];
                ?>
                    <div class="sub-card<?= (int) $sub['active'] === 0 ? ' is-inactive' : '' ?>">
                        <div class="sub-card-head">
                            <strong class="sub-card-name"><?= e((string) $sub['name']) ?></strong>
                            <?php if ((int) $sub['is_required'] === 1): ?>
                                <span class="req-pill">Required</span>
                            <?php endif; ?>
                            <?php if ((int) $sub['active'] === 0): ?>
                                <span class="opt-pill">Inactive</span>
                            <?php endif; ?>
                            <span class="sub-card-gates">
                                Appears when
                                <?php foreach ($gates as $g): ?>
                                    <span class="gate-pill"><?= e((string) $g) ?></span>
                                <?php endforeach; ?>
                            </span>
                            <span class="sub-card-actions">
                                <a href="/admin/products/extra-edit.php?id=<?= $sid ?>" class="btn-secondary-link">
                                    Edit gates
                                </a>
                                <form method="post" action="/admin/products/extra.php?id=<?= (int) $extraId ?>"
                                      style="display:inline;margin:0"
                                      data-confirm="Delete sub-option <?= e((string) $sub['name']) ?>? Removes its choices too. Cannot be undone.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="delete_sub_option">
                                    <input type="hidden" name="sub_id" value="<?= $sid ?>">
                                    <button type="submit" class="btn-danger-link">Delete</button>
                                </form>
                            </span>
                        </div>
                        <?php
                            // Render an inline choices grid for this sub-option.
                            // Same partial as the main grid → same UX, same keyboard
                            // flow, same single JS handler picks it up.
                            $gridExtraId = $sid;
                            $gridChoices = $subChoices;
                            $productId   = (int) $extra['product_id'];
                            require __DIR__ . '/../../_partials/choices_grid.php';
                        ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($choices)): ?>
                <details class="add-sub-form">
                    <summary>+ Add sub-option</summary>
                    <form method="post" action="/admin/products/extra.php?id=<?= (int) $extraId ?>"
                          class="form" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="create_sub_option">

                        <div class="form-row full">
                            <div class="form-group">
                                <label for="sub_name">Sub-option name <span class="required">*</span></label>
                                <input id="sub_name" name="name" type="text"
                                       required maxlength="150"
                                       placeholder="e.g. Colour, Length, Bracket type">
                            </div>
                        </div>

                        <div class="form-row full">
                            <div class="form-group">
                                <label>Appears when <span class="required">*</span></label>
                                <div style="display:flex;flex-direction:column;gap:0.375rem;padding:0.5rem 0;max-height:200px;overflow-y:auto">
                                    <?php foreach ($choices as $c): ?>
                                        <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:400;cursor:pointer">
                                            <input type="checkbox" name="parent_choice_ids[]"
                                                   value="<?= (int) $c['id'] ?>">
                                            <?= e((string) $extra['name']) ?> = <?= e((string) $c['label']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <small style="color:#6b7280;font-size:0.8125rem">
                                    Tick one or more. The sub-option shows in the quote builder when <strong>any</strong> ticked choice is selected.
                                </small>
                            </div>
                        </div>

                        <div class="form-row full">
                            <div class="form-group">
                                <label class="checkbox-row">
                                    <input type="checkbox" name="is_required" value="1">
                                    Required — customer must pick a choice from this sub-option
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="after_create" value="open" class="btn btn-primary">
                                Save &amp; open choices
                            </button>
                            <button type="submit" name="after_create" value="stay" class="btn btn-secondary">
                                Save &amp; stay here
                            </button>
                        </div>
                    </form>
                </details>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require __DIR__ . '/../../_partials/confirm_modal.php'; ?>
<?php require __DIR__ . '/../../_partials/sortable_init.php'; ?>

<script>
(function () {
    'use strict';

    // ============================================================
    // Shared, page-level — multiple grids on this page share these.
    // ============================================================
    var endpoint  = '/admin/products/choice-api.php';
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var indicator = document.getElementById('save-indicator');

    // Systems list, mirrored from PHP. All grids on this page operate
    // on the same product, so the same systems apply to all of them.
    var systemsList = <?= json_encode(
        array_map(static fn ($s) => [
            'id'   => (int) $s['id'],
            'name' => (string) $s['name'],
        ], $systems),
        JSON_THROW_ON_ERROR
    ) ?>;

    var hideTimer = null;
    function flashIndicator(message, isError) {
        clearTimeout(hideTimer);
        if (!indicator) return;
        indicator.textContent = message;
        indicator.classList.toggle('is-error', !!isError);
        indicator.classList.add('is-visible');
        hideTimer = setTimeout(function () {
            indicator.classList.remove('is-visible');
        }, isError ? 4000 : 1100);
    }

    // Outside-click closes any open multi-select on any grid. One
    // global listener handles every grid on the page.
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.multi-select[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.open = false;
        });
    });

    // ============================================================
    // Per-grid — every .choices-grid-wrap on the page gets one
    // call. All state (extraId, body, newRow, etc.) is closure-local.
    // ============================================================
    function initGrid(rootEl) {
        var extraId = parseInt(rootEl.dataset.extraId, 10);
        if (!extraId) return;

        var body          = rootEl.querySelector('.grid-body');
        var newRow        = rootEl.querySelector('.new-row');
        if (!body || !newRow) return;
        var newLabel      = rootEl.querySelector('.new-label-input');
        var newSystem     = rootEl.querySelector('.new-system-details');
        var newSystemSummary = newSystem && newSystem.querySelector('.new-system-summary');
        var newSystemAll  = newSystem && newSystem.querySelector('.new-system-all-cb');
        var newSystemOnes = newSystem ? newSystem.querySelectorAll('.new-system-one') : [];

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
            // Tell password managers (Dashlane / 1Password / LastPass /
            // Bitwarden) to ignore these. They aren't credential inputs,
            // and PM keystroke-interception breaks our keydown handler.
            inp.autocomplete = 'off';
            inp.dataset.formType = 'other';
            inp.dataset.lpignore = 'true';
            inp.dataset['1pIgnore'] = 'true';
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

        // System multi-select — same widget as the new-row, but with the
        // current row's system pre-ticked + disabled (use × to delete).
        // Other ticks spawn sibling rows. See the PHP renderSystemMulti
        // Select() helper for the canonical structure.
        var tdSys = document.createElement('td');
        tdSys.className = 'col-system';
        tdSys.appendChild(buildSystemMultiSelect(choice.system_id == null ? null : choice.system_id));
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
                '" class="btn-more" title="Full edit page — width table, thumbnail">Edit</a>' +
            '<a href="/admin/products/extras.php?product_id=<?= (int) $extra['product_id'] ?>&parent_choice=' + choice.id +
                '#add-option" class="btn-sub" title="Add a follow-up option that appears only when this choice is selected">+ Sub</a>' +
            '<button type="button" class="btn-duplicate" title="Clone this choice">Dup</button>' +
            '<button type="button" class="btn-delete" title="Delete">&times;</button>';
        tr.appendChild(tdActions);

        return tr;
    }

    // No-op stub — the per-grid count label used to live in the
    // section header but it's now just a static count in the PHP.
    // Keeping the function so existing call sites don't need editing.
    function updateCount() {}

    // Build the existing-row multi-select widget — DOM mirror of the
    // PHP renderSystemMultiSelect() helper. Used by buildRow when a new
    // row is freshly inserted (after spawn or duplicate).
    function buildSystemMultiSelect(currentSystemId) {
        var isAll = currentSystemId == null;

        var details = document.createElement('details');
        details.className = 'multi-select row-multi';

        var summary = document.createElement('summary');
        summary.textContent = 'All systems';
        if (!isAll) {
            for (var i = 0; i < systemsList.length; i++) {
                if (systemsList[i].id === currentSystemId) {
                    summary.textContent = systemsList[i].name;
                    break;
                }
            }
        }
        details.appendChild(summary);

        var opts = document.createElement('div');
        opts.className = 'multi-opts';

        // "All systems" — ticked + disabled if current; else disabled.
        var allLabel = document.createElement('label');
        var allCb    = document.createElement('input');
        allCb.type    = 'checkbox';
        allCb.className = 'row-system-tick';
        allCb.dataset.system = '0';
        allCb.disabled = true;
        if (isAll) allCb.checked = true;
        var allStrong = document.createElement('strong');
        allStrong.textContent = 'All systems';
        allLabel.appendChild(allCb);
        allLabel.appendChild(document.createTextNode(' '));
        allLabel.appendChild(allStrong);
        opts.appendChild(allLabel);

        if (systemsList.length) {
            var hr = document.createElement('hr');
            opts.appendChild(hr);

            systemsList.forEach(function (s) {
                var lbl = document.createElement('label');
                var cb  = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'row-system-tick';
                cb.dataset.system = String(s.id);
                var isCurrent = (s.id === currentSystemId);
                if (isCurrent) cb.checked = true;
                // Only this row's own system is locked. Others are
                // always clickable so the user can spawn siblings,
                // even on "All systems" rows.
                if (isCurrent) cb.disabled = true;
                lbl.appendChild(cb);
                lbl.appendChild(document.createTextNode(' ' + s.name));
                opts.appendChild(lbl);
            });
        }

        details.appendChild(opts);
        return details;
    }

    // React to a specific-system tick on an existing row's dropdown.
    //
    // Two paths depending on what the source row currently is:
    //
    // 1. Source row is "All systems" (its All checkbox is ticked).
    //    The first specific tick CONVERTS the row in place — its
    //    system_id changes from NULL to the ticked system. No sibling
    //    is created; no orphan All-systems row left behind. Subsequent
    //    ticks (now that the source is a specific row) fall through
    //    to path 2.
    //
    // 2. Source row is already a specific system. Ticking another
    //    system spawns a SIBLING row (clone) for that system, and the
    //    just-ticked checkbox locks ticked so the user sees a running
    //    record of what they've added. The original row is untouched.
    //
    // Both paths leave the dropdown OPEN so the user can rapid-tick
    // more systems if they want.
    function spawnSibling(sourceRow, systemId, checkbox) {
        var allCb = sourceRow.querySelector('.row-system-tick[data-system="0"]');
        var isAllRow = allCb && allCb.checked;

        if (isAllRow) {
            // Path 1: convert in place. Update system_id on this row.
            return withSavingState(sourceRow, api('update', {
                choice_id: sourceRow.dataset.id,
                field:     'system_id',
                value:     systemId
            })).then(function () {
                // Reflect the new state in the dropdown without a
                // full rebuild: untick All, lock the new current,
                // update the summary text. The other checkboxes were
                // already enabled by buildSystemMultiSelect's "only
                // the current is disabled" rule.
                allCb.checked  = false;
                checkbox.disabled = true;

                var sysName = '?';
                for (var i = 0; i < systemsList.length; i++) {
                    if (String(systemsList[i].id) === String(systemId)) {
                        sysName = systemsList[i].name;
                        break;
                    }
                }
                var summary = sourceRow.querySelector('.multi-select > summary');
                if (summary) summary.textContent = sysName;
            }).catch(function () {
                checkbox.checked = false;
            });
        }

        // Path 2: standard spawn. Source row stays; new sibling created.
        return withSavingState(sourceRow, api('duplicate', {
            choice_id: sourceRow.dataset.id,
            system_id: systemId
        })).then(function (data) {
            var newRowEl = buildRow(data.choice);
            sourceRow.parentNode.insertBefore(newRowEl, sourceRow.nextSibling);
            updateCount();
            checkbox.disabled = true;
        }).catch(function () {
            checkbox.checked = false;
        });
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

        // Existing-row multi-select tick → spawn a sibling row for that
        // system. Don't fall through to saveCell — it's a row create,
        // not a field update on this row.
        if (el.classList.contains('row-system-tick')) {
            // The new-row's multi-select uses different classes, so we
            // don't accidentally double-handle here.
            if (!el.checked) return;
            if (el.dataset.system === '0') return;   // disabled in HTML; defensive
            var sourceRow = el.closest('tr');
            if (!sourceRow || !sourceRow.dataset.id) return;
            spawnSibling(sourceRow, el.dataset.system, el);
            return;
        }

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

    // ---- New-row creation (multi-system) --------------------------------

    // "All systems" and the per-system checkboxes are mutually exclusive.
    // Ticking "All" clears specifics; ticking any specific clears "All".
    // If everything ends up unticked, snap back to "All" so we never have
    // an indeterminate selection.
    function syncMultiSelect(changed) {
        if (changed === newSystemAll) {
            if (newSystemAll.checked) {
                newSystemOnes.forEach(function (cb) { cb.checked = false; });
            }
        } else if (changed && changed.classList.contains('new-system-one')) {
            if (changed.checked) newSystemAll.checked = false;
        }
        var anyOne = false;
        newSystemOnes.forEach(function (cb) { if (cb.checked) anyOne = true; });
        if (!anyOne && !newSystemAll.checked) newSystemAll.checked = true;

        // Update the summary text. "All systems" / single name / "Vogue +
        // Nova" / "3 systems" depending on count.
        var picked = [];
        newSystemOnes.forEach(function (cb) {
            if (cb.checked) {
                var span = cb.parentNode.querySelector('span');
                picked.push(span ? span.textContent : cb.value);
            }
        });
        if (newSystemAll.checked || picked.length === 0) {
            newSystemSummary.textContent = 'All systems';
        } else if (picked.length === 1) {
            newSystemSummary.textContent = picked[0];
        } else if (picked.length === 2) {
            newSystemSummary.textContent = picked.join(' + ');
        } else {
            newSystemSummary.textContent = picked.length + ' systems';
        }
    }

    function resetMultiSelect() {
        newSystemAll.checked = true;
        newSystemOnes.forEach(function (cb) { cb.checked = false; });
        syncMultiSelect();
    }

    // Wire the new-row's multi-select checkboxes — only if there is one
    // (a fresh sub-option grid might not have it depending on layout).
    if (newSystem) {
        newSystem.addEventListener('change', function (e) {
            if (e.target.matches('input[type="checkbox"]')) {
                syncMultiSelect(e.target);
            }
        });
    }

    function commitNewRow(focusNext) {
        var label = newLabel.value.trim();
        if (label === '') return Promise.resolve();

        // Build the system_ids[] payload. "All systems" = no entries
        // (server treats missing as "all").
        var fd = new FormData();
        fd.append('label', label);
        if (!newSystemAll.checked) {
            newSystemOnes.forEach(function (cb) {
                if (cb.checked) fd.append('system_ids[]', cb.value);
            });
        }

        newRow.classList.add('is-saving');

        // Use api() but with our pre-built FormData. Bypass the helper
        // since system_ids is an array, which the helper's flat-key loop
        // would mangle.
        fd.append('action',   'create');
        fd.append('extra_id', String(extraId));

        return fetch(endpoint, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!data.ok) throw new Error(data.error || 'Unknown error.');
                return data;
            });
        }).then(function (data) {
            newRow.classList.remove('is-saving');
            newRow.classList.add('just-saved');
            setTimeout(function () { newRow.classList.remove('just-saved'); }, 700);

            // Insert each created row above the new-row, in order.
            var rows = data.choices || (data.choice ? [data.choice] : []);
            rows.forEach(function (c) {
                body.insertBefore(buildRow(c), newRow);
            });
            updateCount();
            flashIndicator(rows.length === 1 ? 'Saved' : 'Saved (' + rows.length + ' rows)');

            // Reset the new-row ready for another entry.
            newLabel.value = '';
            resetMultiSelect();
            newSystem.open = false;

            if (focusNext) newLabel.focus();
        }).catch(function (err) {
            newRow.classList.remove('is-saving');
            newRow.classList.add('is-error');
            flashIndicator(err.message || 'Could not add', true);
            setTimeout(function () { newRow.classList.remove('is-error'); }, 2000);
        });
    }

    if (newLabel) {
        newLabel.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                commitNewRow(true); // focus stays in label for rapid entry
            } else if (e.key === 'Escape') {
                newLabel.value = '';
                resetMultiSelect();
                if (newSystem) newSystem.open = false;
            }
        });
    }

    // Blurring the new-row entirely (focus moves outside the label input
    // AND the multi-select dropdown) should also commit — small delay
    // so navigating between them doesn't trip it.
    function maybeCommitNewRow() {
        setTimeout(function () {
            var active = document.activeElement;
            var insideMs = newSystem && newSystem.contains(active);
            if (active !== newLabel
             && !insideMs
             && newLabel && newLabel.value.trim() !== '') {
                commitNewRow(false);
            }
        }, 150);
    }
    if (newLabel)  newLabel.addEventListener('blur', maybeCommitNewRow);
    if (newSystem) newSystem.addEventListener('focusout', maybeCommitNewRow);

    }  // end initGrid

    // Initialise every choices grid on the page (main option + any
    // sub-options rendered inline below their cards).
    document.querySelectorAll('.choices-grid-wrap').forEach(initGrid);
})();
</script>
</body>
</html>
