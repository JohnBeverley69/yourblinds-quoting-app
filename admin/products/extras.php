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

$f = ['name' => '', 'is_required' => 1, 'parent_choice_id' => 0, 'system_ids' => []];
$error = null;

// Systems available on this product, for the new system-scope checkboxes
// (also used to render the existing rows below with a "X + Y only" pill).
$sysStmt = db()->prepare(
    'SELECT id, name FROM product_systems
      WHERE product_id = ? AND client_id = ?
   ORDER BY sort_order, name'
);
$sysStmt->execute([$productId, $clientId]);
$systems = $sysStmt->fetchAll();

// "+ Follow-up option" deep link from extra.php pre-fills the parent dropdown
// via ?parent_choice=N. We accept the GET only on initial render — POST
// always wins so a re-render after a validation error keeps the typed value.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['parent_choice'])) {
    $candidate = (int) $_GET['parent_choice'];
    if ($candidate > 0) {
        $f['parent_choice_id'] = $candidate;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'create') {
    csrf_check();

    $f['name']             = trim((string) ($_POST['name'] ?? ''));
    $f['is_required']      = !empty($_POST['is_required']) ? 1 : 0;
    $f['parent_choice_id'] = (int) ($_POST['parent_choice_id'] ?? 0);
    $f['system_ids']       = array_values(array_unique(array_filter(array_map(
        'intval',
        is_array($_POST['system_ids'] ?? null) ? $_POST['system_ids'] : []
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

            $stmt = $pdo->prepare(
                'INSERT INTO product_extras
                   (client_id, product_id, parent_choice_id, name, is_required, sort_order, active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                $clientId,
                $productId,
                $f['parent_choice_id'] > 0 ? $f['parent_choice_id'] : null,
                $f['name'],
                $f['is_required'],
                $nextSort,
            ]);
            $newId = (int) $pdo->lastInsertId();

            // Tie the option to its selected systems via the junction table.
            // Empty selection = available on every system.
            if ($f['system_ids']) {
                $ph = implode(',', array_fill(0, count($f['system_ids']), '?'));
                $vsSt = $pdo->prepare(
                    "SELECT id FROM product_systems
                      WHERE id IN ($ph) AND product_id = ? AND client_id = ?"
                );
                $vsSt->execute([...$f['system_ids'], $productId, $clientId]);
                $validSystemIds = array_map('intval', $vsSt->fetchAll(PDO::FETCH_COLUMN));

                // Auto-collapse: if every available system is ticked, treat
                // it as "no scope" (zero rows). Same runtime effect, but
                // cleaner data and future-proof against new systems being
                // added later (which would otherwise NOT inherit this row).
                $totalSt = $pdo->prepare(
                    'SELECT COUNT(*) FROM product_systems
                      WHERE product_id = ? AND client_id = ?'
                );
                $totalSt->execute([$productId, $clientId]);
                $totalSystems = (int) $totalSt->fetchColumn();
                if ($totalSystems > 0 && count($validSystemIds) === $totalSystems) {
                    $validSystemIds = [];
                }

                if ($validSystemIds) {
                    $jIns = $pdo->prepare(
                        'INSERT INTO product_extra_systems
                           (product_extra_id, product_system_id) VALUES (?, ?)'
                    );
                    foreach ($validSystemIds as $sid) {
                        $jIns->execute([$newId, $sid]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Option "' . $f['name'] . '" added.';
            header('Location: /admin/products/extra.php?id=' . $newId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (str_contains($e->getMessage(), 'uniq_extra_per_product')) {
                $error = 'An option with that name already exists for this product.';
            } else {
                $error = 'Could not add: ' . $e->getMessage();
            }
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
            pc.label AS parent_choice_label,
            pe.name  AS parent_extra_name,
            (SELECT COUNT(*) FROM product_extra_choices c WHERE c.product_extra_id = e.id) AS choice_count
       FROM product_extras e
       LEFT JOIN product_extra_choices pc ON pc.id = e.parent_choice_id
       LEFT JOIN product_extras        pe ON pe.id = pc.product_extra_id
      WHERE e.product_id = ? AND e.client_id = ?
   ORDER BY e.sort_order, e.name'
);
$rows->execute([$productId, $clientId]);
$extras = $rows->fetchAll();

// Pull each extra's system scope (junction) in one query, fold by extra id
// so the rows below can show "Vogue + Slim Line only" instead of nothing
// for system-scoped Options.
$scopeByExtra = [];
if ($extras) {
    $extraIds = array_map(static fn ($e) => (int) $e['id'], $extras);
    $ph = implode(',', array_fill(0, count($extraIds), '?'));
    $scopeSt = db()->prepare(
        "SELECT pes.product_extra_id, ps.name
           FROM product_extra_systems pes
           JOIN product_systems ps ON ps.id = pes.product_system_id
          WHERE pes.product_extra_id IN ($ph)
          ORDER BY ps.sort_order, ps.name"
    );
    $scopeSt->execute($extraIds);
    foreach ($scopeSt->fetchAll() as $r) {
        $scopeByExtra[(int) $r['product_extra_id']][] = (string) $r['name'];
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
            font-size: 0.9375rem; color: #111827; cursor: pointer;
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
        .extra-name { font-weight: 600; color: #111827; }
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
            font-weight: 600; color: #6b7280; background: #f3f4f6;
            border-radius: 999px; margin-left: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .conditional-row td:first-child { padding-left: 2rem; position: relative; }
        .conditional-row td:first-child::before {
            content: '↳'; position: absolute; left: 0.625rem; color: #9ca3af;
            font-size: 1.125rem; line-height: 1;
        }
        .parent-cond {
            display: block; color: #6b7280; font-size: 0.8125rem;
            margin-top: 0.125rem; font-weight: 400;
        }
        .parent-cond strong { color: #4b5563; font-weight: 600; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <?= e((string) $product['name']) ?> &mdash; Options
                </h1>
                <p class="page-subtitle">
                    <a href="/admin/products/index.php">&larr; All products</a>
                    &middot;
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

        <?php
            // Resolve the pre-filled parent choice, if any, so we can show
            // a friendly banner. We look it up in $availableChoices rather
            // than firing another query.
            $parentChoiceLabel = '';
            if ((int) $f['parent_choice_id'] > 0) {
                foreach ($availableChoices as $c) {
                    if ((int) $c['id'] === (int) $f['parent_choice_id']) {
                        $parentChoiceLabel = (string) $c['extra_name']
                                           . ' = ' . (string) $c['label'];
                        break;
                    }
                }
            }
        ?>
        <section class="section" id="add-option">
            <div class="section-header">
                <h2 class="section-title">Add option</h2>
            </div>
            <?php if ($parentChoiceLabel !== ''): ?>
                <div class="alert alert-info" style="margin-bottom:1rem">
                    Adding a <strong>follow-up option</strong> that appears when
                    <strong><?= e($parentChoiceLabel) ?></strong> is selected.
                    Adjust the "Appears when" dropdown below if you want a different parent.
                </div>
            <?php else: ?>
                <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 1rem">
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
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="parent_choice_id">Appears when</label>
                        <select id="parent_choice_id" name="parent_choice_id">
                            <option value="0">— Always visible —</option>
                            <?php foreach ($availableChoices as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"
                                    <?= ((int) $f['parent_choice_id']) === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $c['extra_name']) ?> = <?= e((string) $c['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#6b7280;font-size:0.8125rem">
                            Optional — pick a choice to make this option only show when that choice is selected.
                        </small>
                    </div>
                </div>

                <?php if ($systems): ?>
                <div class="form-row full">
                    <div class="form-group">
                        <label>System scope (optional)</label>
                        <div style="display:flex;flex-wrap:wrap;gap:0.75rem 1.25rem;padding:0.5rem 0">
                            <?php foreach ($systems as $s): ?>
                                <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:400;cursor:pointer">
                                    <input type="checkbox" name="system_ids[]"
                                           value="<?= (int) $s['id'] ?>"
                                           <?= in_array((int) $s['id'], $f['system_ids'], true) ? 'checked' : '' ?>>
                                    <?= e((string) $s['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small style="color:#6b7280;font-size:0.8125rem">
                            Tick one or more to limit this whole option to specific systems.
                            Leave all unticked to make it available on every system.
                        </small>
                    </div>
                </div>
                <?php endif; ?>

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
                <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 0.5rem">
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
                                        <?php $extraScope = $scopeByExtra[(int) $x['id']] ?? []; ?>
                                        <?php if ($extraScope): ?>
                                            <span class="req-pill" style="background:#1f3b5b">
                                                <?= e(implode(' + ', $extraScope)) ?> only
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($x['parent_choice_id'])): ?>
                                            <span class="parent-cond">
                                                Appears when
                                                <strong><?= e((string) ($x['parent_extra_name'] ?? '')) ?>
                                                = <?= e((string) ($x['parent_choice_label'] ?? '')) ?></strong>
                                                is selected
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num">
                                        <a href="/admin/products/extra.php?id=<?= (int) $x['id'] ?>">
                                            <?= (int) $x['choice_count'] ?>
                                        </a>
                                    </td>
                                    <td style="font-size:0.8125rem;color:#6b7280;white-space:nowrap">
                                        <?= e((string) $x['updated_at']) ?>
                                    </td>
                                    <td class="row-actions">
                                        <a href="/admin/products/extra-edit.php?id=<?= (int) $x['id'] ?>">Edit</a>
                                        <form method="post" action="/admin/products/extra-delete.php"
                                              onsubmit="return confirm('Delete option <?= e(addslashes((string) $x['name'])) ?>? Removes its <?= (int) $x['choice_count'] ?> choices too.');">
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
</body>
</html>
