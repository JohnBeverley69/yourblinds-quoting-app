<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$productId = (int) ($_GET['product_id'] ?? 0);
if ($productId <= 0) {
    header('Location: /admin/products/index.php');
    exit;
}

$pStmt = db()->prepare(
    'SELECT id, name FROM products WHERE id = ? AND client_id = ?'
);
$pStmt->execute([$productId, $clientId]);
$product = $pStmt->fetch();

if (!$product) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Product not found</h1>';
    exit;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// parent_choice_ids — a list now, not a single id. A follow-up option
// can be gated to multiple parent choices (e.g. one Colour option that
// shows when EITHER Chained OR Chainless is selected).
$f = [
    'name'               => '',
    'is_required'        => 1,
    'parent_choice_ids'  => [],
    // Optional: salesperson types a value alongside the choice. Same
    // tickbox+label pattern as on extra-edit.php. Pre-saving here
    // saves the user a round-trip into the edit page after creation.
    'length_input_label' => '',
    // 0 = single-pick dropdown (default), 1 = multi-pick tick-boxes.
    'allow_multi'        => 0,
];
$error = null;

// "+ Sub" deep link from extra.php pre-fills the parent picker
// via ?parent_choice=N. We accept the GET only on initial render — POST
// always wins so a re-render after a validation error keeps the user's
// existing ticks.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['parent_choice'])) {
    $candidate = (int) $_GET['parent_choice'];
    if ($candidate > 0) {
        $f['parent_choice_ids'] = [$candidate];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'create') {
    csrf_check();

    $f['name']              = trim((string) ($_POST['name'] ?? ''));
    $f['is_required']       = !empty($_POST['is_required']) ? 1 : 0;
    $f['length_input_label'] = trim((string) ($_POST['length_input_label'] ?? ''));
    $f['allow_multi']        = !empty($_POST['allow_multi']) ? 1 : 0;
    $f['parent_choice_ids'] = array_values(array_unique(array_filter(array_map(
        'intval',
        is_array($_POST['parent_choice_ids'] ?? null) ? $_POST['parent_choice_ids'] : []
    ))));

    if ($f['name'] === '') {
        $error = 'Name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = 'Name is too long (max 150 chars).';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // sort_order = MAX+1 so new extras append to the end of the
            // list (drag-and-drop owns ordering after that).
            $sortStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), -1) + 1
                   FROM product_extras
                  WHERE product_id = ? AND client_id = ?'
            );
            $sortStmt->execute([$productId, $clientId]);
            $nextSort = (int) $sortStmt->fetchColumn();

            // parent_choice_id (legacy single column) gets the FIRST
            // ticked id so any old code that still reads it stays
            // sensible. The junction is the source of truth.
            $legacyParent = $f['parent_choice_ids'][0] ?? null;

            // Both new columns (length_input_label, allow_multi) are
            // optional. Cascade through three INSERT shapes so the page
            // works against the historic schema, the schema after the
            // length-input migration, and the full schema.
            $lengthLabel = $f['length_input_label'] !== '' ? $f['length_input_label'] : null;
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO product_extras
                       (client_id, product_id, parent_choice_id, name,
                        is_required, length_input_label, allow_multi,
                        sort_order, active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
                );
                $stmt->execute([
                    $clientId, $productId, $legacyParent,
                    $f['name'], $f['is_required'], $lengthLabel, $f['allow_multi'],
                    $nextSort,
                ]);
            } catch (Throwable $eA) {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO product_extras
                           (client_id, product_id, parent_choice_id, name,
                            is_required, length_input_label, sort_order, active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
                    );
                    $stmt->execute([
                        $clientId, $productId, $legacyParent,
                        $f['name'], $f['is_required'], $lengthLabel, $nextSort,
                    ]);
                } catch (Throwable $eB) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO product_extras
                           (client_id, product_id, parent_choice_id, name,
                            is_required, sort_order, active)
                         VALUES (?, ?, ?, ?, ?, ?, 1)'
                    );
                    $stmt->execute([
                        $clientId, $productId, $legacyParent,
                        $f['name'], $f['is_required'], $nextSort,
                    ]);
                }
            }
            $newId = (int) $pdo->lastInsertId();

            // Junction rows for every ticked parent choice. Validate
            // each id belongs to this product before inserting.
            if ($f['parent_choice_ids']) {
                $ph = implode(',', array_fill(0, count($f['parent_choice_ids']), '?'));
                $vps = $pdo->prepare(
                    "SELECT c.id FROM product_extra_choices c
                       JOIN product_extras e ON e.id = c.product_extra_id
                      WHERE c.id IN ($ph) AND e.product_id = ? AND e.client_id = ?"
                );
                $vps->execute([...$f['parent_choice_ids'], $productId, $clientId]);
                $validParents = array_map('intval', $vps->fetchAll(PDO::FETCH_COLUMN));
                if ($validParents) {
                    $jIns = $pdo->prepare(
                        'INSERT INTO product_extra_parent_choices
                           (product_extra_id, product_extra_choice_id)
                         VALUES (?, ?)'
                    );
                    foreach ($validParents as $cid) {
                        $jIns->execute([$newId, $cid]);
                    }
                }
            }

            $pdo->commit();

            // Audit
            require_once __DIR__ . '/../../_partials/catalogue_audit.php';
            catalogue_audit_log(
                'extra', $newId, 'create',
                $f['name'],
                null,
                [
                    'name'         => $f['name'],
                    'is_required'  => (int) $f['is_required'],
                    'allow_multi'  => (int) $f['allow_multi'],
                    'parent_count' => count($f['parent_choice_ids'] ?? []),
                ],
                $productId
            );

            $_SESSION['flash_success'] = 'Option "' . $f['name'] . '" added.';
            // Honour return_to from the inline quick-add on the
            // product edit page so the user stays in their flow
            // (instead of jumping to the new option's choices
            // editor, which is the default for the dedicated form).
            $returnTo = trim((string) ($_POST['return_to'] ?? ''));
            if ($returnTo !== ''
                && $returnTo[0] === '/'
                && !str_starts_with($returnTo, '//')
                && !preg_match('#^/?\w+://#', $returnTo)) {
                header('Location: ' . $returnTo);
            } else {
                header('Location: /admin/products/extra.php?id=' . $newId);
            }
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Could not add: ' . $e->getMessage();
        }
    }
}

// All choices in this product (across all extras) — these are the candidates
// for parent_choice_id. Grouped + labelled so the dropdown is readable.
$choiceStmt = db()->prepare(
    'SELECT c.id, c.label, e.name AS extra_name
       FROM product_extra_choices c
       JOIN product_extras e ON e.id = c.product_extra_id
      WHERE e.product_id = ? AND e.client_id = ?
   ORDER BY e.name, c.sort_order, c.label'
);
$choiceStmt->execute([$productId, $clientId]);
$availableChoices = $choiceStmt->fetchAll();

// Sort purely by sort_order so drag-and-drop fully controls position.
// Conditional (parent-choice-gated) rows still get their visual indent
// + ↳ marker so the relationship reads at a glance regardless of position.
$rows = db()->prepare(
    'SELECT e.id, e.name, e.is_required, e.sort_order, e.active, e.updated_at,
            e.parent_choice_id,
            (SELECT COUNT(*) FROM product_extra_choices c WHERE c.product_extra_id = e.id) AS choice_count
       FROM product_extras e
      WHERE e.product_id = ? AND e.client_id = ?
   ORDER BY e.sort_order, e.name'
);
$rows->execute([$productId, $clientId]);
$extras = $rows->fetchAll();

// Pull every junction parent for each extra in one go. Folds into a
// [extra_id => ["Operation = Wand", "Operation = Cord & Chain", ...]]
// map so the rendered "Appears when …" label can list ALL parents.
$parentsByExtra = [];
if ($extras) {
    $eIds = array_map(static fn ($r) => (int) $r['id'], $extras);
    $ph = implode(',', array_fill(0, count($eIds), '?'));
    $pSt = db()->prepare(
        "SELECT pepc.product_extra_id, c.label, pe.name AS extra_name
           FROM product_extra_parent_choices pepc
           JOIN product_extra_choices c   ON c.id  = pepc.product_extra_choice_id
           JOIN product_extras        pe  ON pe.id = c.product_extra_id
          WHERE pepc.product_extra_id IN ($ph)
       ORDER BY pe.name, c.sort_order, c.label"
    );
    $pSt->execute($eIds);
    foreach ($pSt->fetchAll() as $r) {
        $parentsByExtra[(int) $r['product_extra_id']][] = (string) $r['extra_name']
            . ' = ' . (string) $r['label'];
    }
}

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $product['name']) ?> &middot; Options &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .form-row.cols-2-narrow { grid-template-columns: 4fr 1fr; align-items: end; }
        @media (max-width: 700px) { .form-row.cols-2-narrow { grid-template-columns: 1fr; } }
        .checkbox-row {
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-size: 0.9375rem; color: var(--text-primary); cursor: pointer;
            padding: 0.5625rem 0; margin: 0;
        }
        .checkbox-row input { width: 18px; height: 18px; }
        .row-actions { white-space: nowrap; }
        .row-actions a { font-size: 0.875rem; margin-left: 0.5rem; }
        .row-actions form { display: inline; margin: 0; }
        .row-actions button {
            font-size: 0.875rem; color: #b91c1c; background: transparent;
            border: 0; cursor: pointer; padding: 0; margin-left: 0.5rem;
        }
        .row-actions button:hover { text-decoration: underline; }
        .extra-name { font-weight: 600; color: var(--text-primary); }
        a.extra-name { text-decoration: none; }
        a.extra-name:hover { color: #1f3b5b; text-decoration: underline; }
        .req-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 600; color: #fff; background: #1f3b5b;
            border-radius: 999px; margin-left: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .opt-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 600; color: var(--text-faint); background: var(--bg-subtle-2);
            border-radius: 999px; margin-left: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .conditional-row td:first-child { padding-left: 2rem; position: relative; }
        .conditional-row td:first-child::before {
            content: '↳'; position: absolute; left: 0.625rem; color: var(--text-faint);
            font-size: 1.125rem; line-height: 1;
        }
        .parent-cond {
            display: block; color: var(--text-faint); font-size: 0.8125rem;
            margin-top: 0.125rem; font-weight: 400;
        }
        .parent-cond strong { color: var(--text-muted); font-weight: 600; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <?php
                    require_once __DIR__ . '/../../_partials/breadcrumb.php';
                    echo render_breadcrumb([
                        ['Products',                  '/admin/products/index.php'],
                        [(string) $product['name'],   '/admin/products/edit.php?id=' . (int) $productId],
                        ['Options',                   null],
                    ]);
                ?>
                <h1 class="page-title">
                    <?= e((string) $product['name']) ?> &mdash; Options
                </h1>
                <p class="page-subtitle">
                    <a href="/admin/products/edit.php?id=<?= (int) $productId ?>">Edit product</a>
                    &middot;
                    <a href="/admin/products/options.php?product_id=<?= (int) $productId ?>">Fabrics</a>
                    &middot;
                    <a href="/admin/products/systems.php?product_id=<?= (int) $productId ?>">Systems</a>
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.875rem 1.125rem;margin-bottom:1rem">
            <p style="margin:0;color:#0c4a6e;font-size:0.9375rem;line-height:1.5">
                <strong>Options</strong> are the things your salesperson picks for each blind
                when building a quote &mdash; e.g. <em>Bottom Weight</em>, <em>Bracket colour</em>,
                <em>Control side</em>. Add an option below, then click into it to set up its
                <strong>choices</strong> (the values the customer can pick from).
            </p>
        </section>

        <?php
            // Resolve the pre-filled parent choices, if any, so we can show
            // a friendly banner. We look them up in $availableChoices rather
            // than firing another query.
            $parentChoiceLabels = [];
            foreach ($availableChoices as $c) {
                if (in_array((int) $c['id'], $f['parent_choice_ids'], true)) {
                    $parentChoiceLabels[] = (string) $c['extra_name']
                                          . ' = ' . (string) $c['label'];
                }
            }
            $parentChoiceLabel = implode(' OR ', $parentChoiceLabels);
        ?>
        <section class="section" id="add-option">
            <div class="section-header">
                <h2 class="section-title">Add option</h2>
            </div>
            <?php if ($parentChoiceLabel !== ''): ?>
                <div class="alert alert-info" style="margin-bottom:1rem">
                    Adding a <strong>follow-up option</strong> that appears when
                    <strong><?= e($parentChoiceLabel) ?></strong> is selected.
                    Tick extra choices under "Appears when" below to gate the option
                    to <em>multiple</em> parents (e.g. show it for both Chained AND
                    Chainless).
                </div>
            <?php else: ?>
                <p style="color:var(--text-faint);font-size:0.9375rem;margin:0 0 1rem">
                    Examples: Control side, Control type, Draw side, Lining, Motor type, Headrail colour.
                </p>
            <?php endif; ?>
            <form method="post" action="/admin/products/extras.php?product_id=<?= (int) $productId ?>"
                  class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="create">

                <div class="form-row cols-2-narrow">
                    <div class="form-group">
                        <label for="name">Name <span class="required">*</span></label>
                        <input id="name" name="name" type="text"
                               required maxlength="150" autofocus
                               value="<?= e((string) $f['name']) ?>"
                               placeholder="e.g. Control side">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-row" for="is_required">
                            <input type="checkbox" id="is_required" name="is_required" value="1"
                                   <?= $f['is_required'] === 1 ? 'checked' : '' ?>>
                            Required
                        </label>
                        <label class="checkbox-row" for="allow_multi" style="margin-top:0.375rem">
                            <input type="checkbox" id="allow_multi" name="allow_multi" value="1"
                                   <?= $f['allow_multi'] === 1 ? 'checked' : '' ?>>
                            Allow multiple choices
                            <small style="color:var(--text-faint);font-size:0.8125rem">
                                renders as tick-boxes &mdash; salesperson can pick any combination
                            </small>
                        </label>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label>Appears when (optional)</label>
                        <?php if (!$availableChoices): ?>
                            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0">
                                No other choices on this product yet. Add some options + choices first
                                if you want this option to be gated.
                            </p>
                        <?php else: ?>
                            <div style="display:flex;flex-direction:column;gap:0.375rem;padding:0.5rem 0;max-height:240px;overflow-y:auto">
                                <?php foreach ($availableChoices as $c): ?>
                                    <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:400;cursor:pointer">
                                        <input type="checkbox" name="parent_choice_ids[]"
                                               value="<?= (int) $c['id'] ?>"
                                               <?= in_array((int) $c['id'], $f['parent_choice_ids'], true) ? 'checked' : '' ?>>
                                        <?= e((string) $c['extra_name']) ?> = <?= e((string) $c['label']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <small style="color:var(--text-faint);font-size:0.8125rem">
                                Tick one or more choices and this option will show in the quote builder
                                when <strong>any</strong> of them is selected. Tick none to make the
                                option always visible.
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <!--
                    Same tickbox+label pattern as on extra-edit.php —
                    keeps the two forms consistent so users who learn
                    one immediately recognise the other. Saving here
                    skips the round-trip "create → edit → save" dance.
                -->
                <?php $hasLen = $f['length_input_label'] !== ''; ?>
                <div class="form-row full">
                    <div class="form-group">
                        <label style="display:inline-flex;align-items:flex-start;gap:0.5rem;font-weight:500;cursor:pointer;margin:0">
                            <input type="checkbox" id="add_show_length"
                                   <?= $hasLen ? 'checked' : '' ?>
                                   style="margin-top:0.25rem">
                            <span>
                                Also show a number input next to this option
                                <small style="display:block;color:var(--text-faint);font-weight:400;font-size:0.8125rem;margin-top:0.125rem">
                                    For things like wand length, cable length, etc. &mdash;
                                    the salesperson types a value alongside picking a choice.
                                    Recorded on the quote line for supplier docs.
                                </small>
                            </span>
                        </label>
                        <div id="add_length_label_wrap"
                             style="<?= $hasLen ? '' : 'display:none' ?>;margin:0.375rem 0 0 1.625rem">
                            <label for="add_length_input_label" style="font-size:0.8125rem;font-weight:600;color:var(--text-secondary)">
                                What to call this field
                            </label>
                            <input id="add_length_input_label" name="length_input_label" type="text"
                                   maxlength="60"
                                   value="<?= e((string) $f['length_input_label']) ?>"
                                   placeholder="e.g. Wand length (mm)"
                                   style="width:100%;font:inherit;padding:0.5rem 0.625rem;border:1px solid var(--border-strong);border-radius:8px;background:#fff;box-sizing:border-box;margin-top:0.25rem">
                        </div>
                    </div>
                </div>

                <script>
                (function () {
                    var tick = document.getElementById('add_show_length');
                    var wrap = document.getElementById('add_length_label_wrap');
                    var lbl  = document.getElementById('add_length_input_label');
                    if (!tick || !wrap || !lbl) return;
                    tick.addEventListener('change', function () {
                        if (tick.checked) {
                            wrap.style.display = '';
                            if (lbl.value.trim() === '') lbl.value = 'Length (mm)';
                            setTimeout(function () { lbl.focus(); lbl.select(); }, 0);
                        } else {
                            wrap.style.display = 'none';
                            lbl.value = '';
                        }
                    });
                })();
                </script>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add option</button>
                </div>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Options (<?= count($extras) ?>)</h2>
            </div>

            <?php if (!$extras): ?>
                <div class="placeholder">
                    <p class="placeholder-title">No options yet</p>
                    <p class="placeholder-body">
                        Add an option (e.g. "Control side"), then open it to add the choices customers can pick from.
                    </p>
                </div>
            <?php else: ?>
                <p style="color:var(--text-faint);font-size:0.9375rem;margin:0 0 0.5rem">
                    Drag the <strong>⋮⋮</strong> handle to reorder.
                    <span class="reorder-status">Saving…</span>
                </p>
                <div class="table-wrap">
                    <table class="table sortable-list" data-reorder-type="extras">
                        <thead>
                            <tr>
                                <th class="drag-col"></th>
                                <th>Name</th>
                                <th class="num">Choices</th>
                                <th>Updated</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($extras as $x): ?>
                                <tr data-id="<?= (int) $x['id'] ?>"<?= !empty($x['parent_choice_id']) ? ' class="conditional-row"' : '' ?>>
                                    <td class="drag-col" title="Drag to reorder">⋮⋮</td>
                                    <td>
                                        <a href="/admin/products/extra.php?id=<?= (int) $x['id'] ?>"
                                           class="extra-name">
                                            <?= e((string) $x['name']) ?>
                                        </a>
                                        <?php if ((int) $x['is_required'] === 1): ?>
                                            <span class="req-pill">Required</span>
                                        <?php else: ?>
                                            <span class="opt-pill">Optional</span>
                                        <?php endif; ?>
                                        <?php $parentLabels = $parentsByExtra[(int) $x['id']] ?? []; ?>
                                        <?php if ($parentLabels): ?>
                                            <span class="parent-cond">
                                                Appears when
                                                <strong><?= e(implode(' OR ', $parentLabels)) ?></strong>
                                                is selected
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num">
                                        <a href="/admin/products/extra.php?id=<?= (int) $x['id'] ?>">
                                            <?= (int) $x['choice_count'] ?>
                                        </a>
                                    </td>
                                    <td style="font-size:0.8125rem;color:var(--text-faint);white-space:nowrap">
                                        <?= e((string) $x['updated_at']) ?>
                                    </td>
                                    <td class="row-actions">
                                        <a href="/admin/products/extra-edit.php?id=<?= (int) $x['id'] ?>">Edit</a>
                                        <form method="post" action="/admin/products/extra-delete.php"
                                              data-confirm="Delete option <?= e((string) $x['name']) ?>? Removes its <?= (int) $x['choice_count'] ?> choices too.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
                                            <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                                            <button type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php if ($extras): require __DIR__ . '/../../_partials/sortable_init.php'; endif; ?>
<?php require __DIR__ . '/../../_partials/confirm_modal.php'; ?>
</body>
</html>
