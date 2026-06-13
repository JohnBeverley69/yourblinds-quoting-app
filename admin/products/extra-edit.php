<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /admin/products/index.php');
    exit;
}

// Try-fallback for the optional columns (length_input_label from
// migrate_extra_length_input.php, allow_multi from
// migrate_extra_allow_multi.php). Pre-migration installs still load.
try {
    $loadStmt = db()->prepare(
        'SELECT e.id, e.product_id, e.parent_choice_id, e.name, e.is_required,
                e.length_input_label, e.allow_multi,
                e.sort_order, e.active,
                p.name AS product_name
           FROM product_extras e
           JOIN products p ON p.id = e.product_id
          WHERE e.id = ? AND e.client_id = ?'
    );
    $loadStmt->execute([$id, $clientId]);
    $extra = $loadStmt->fetch();
    $hasLengthInputColumn = true;
    $hasAllowMultiColumn  = true;
} catch (Throwable $e) {
    // One or both new columns missing — fall back to a select that
    // works on the historical schema.
    try {
        $loadStmt = db()->prepare(
            'SELECT e.id, e.product_id, e.parent_choice_id, e.name, e.is_required,
                    e.length_input_label,
                    e.sort_order, e.active,
                    p.name AS product_name
               FROM product_extras e
               JOIN products p ON p.id = e.product_id
              WHERE e.id = ? AND e.client_id = ?'
        );
        $loadStmt->execute([$id, $clientId]);
        $extra = $loadStmt->fetch();
        $hasLengthInputColumn = true;
        $hasAllowMultiColumn  = false;
    } catch (Throwable $e2) {
        $loadStmt = db()->prepare(
            'SELECT e.id, e.product_id, e.parent_choice_id, e.name, e.is_required,
                    e.sort_order, e.active,
                    p.name AS product_name
               FROM product_extras e
               JOIN products p ON p.id = e.product_id
              WHERE e.id = ? AND e.client_id = ?'
        );
        $loadStmt->execute([$id, $clientId]);
        $extra = $loadStmt->fetch();
        $hasLengthInputColumn = false;
        $hasAllowMultiColumn  = false;
    }
}

if (!$extra) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Option not found</h1>';
    exit;
}

// Load existing parent choices from the junction table.
$pcSt = db()->prepare(
    'SELECT product_extra_choice_id
       FROM product_extra_parent_choices
      WHERE product_extra_id = ?'
);
$pcSt->execute([$id]);
$existingParents = array_map('intval', $pcSt->fetchAll(PDO::FETCH_COLUMN));

$f = [
    'name'               => (string) $extra['name'],
    'is_required'        => (int)    $extra['is_required'],
    'active'             => (int)    $extra['active'],
    'parent_choice_ids'  => $existingParents,
    // length_input_label: empty / NULL = no number input (default).
    'length_input_label' => (string) ($extra['length_input_label'] ?? ''),
    // allow_multi: 0 (default) = single-pick dropdown in the quote
    // builder. 1 = checkbox list, salesperson can tick any number.
    'allow_multi'        => (int)    ($extra['allow_multi'] ?? 0),
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['name']               = trim((string) ($_POST['name'] ?? ''));
    $f['is_required']        = !empty($_POST['is_required']) ? 1 : 0;
    $f['active']             = !empty($_POST['active']) ? 1 : 0;
    $f['length_input_label'] = trim((string) ($_POST['length_input_label'] ?? ''));
    $f['allow_multi']        = !empty($_POST['allow_multi']) ? 1 : 0;
    $f['parent_choice_ids']  = array_values(array_unique(array_filter(array_map(
        'intval',
        is_array($_POST['parent_choice_ids'] ?? null) ? $_POST['parent_choice_ids'] : []
    ))));

    if ($f['name'] === '') {
        $error = 'Name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = 'Name is too long (max 150 chars).';
    } elseif (strlen($f['length_input_label']) > 60) {
        $error = 'Length input label is too long (max 60 chars).';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Legacy single column gets the first ticked id (or NULL)
            // so any code still reading it stays sensible. The junction
            // is the source of truth.
            $legacyParent = $f['parent_choice_ids'][0] ?? null;
            // length_input_label: empty string → NULL (cleaner "not set"
            // signal than empty string).
            $lengthLabel = $f['length_input_label'] !== '' ? $f['length_input_label'] : null;

            if ($hasAllowMultiColumn && $hasLengthInputColumn) {
                // Both new columns present — write the full set.
                $u = $pdo->prepare(
                    'UPDATE product_extras
                        SET name = ?, is_required = ?, active = ?,
                            length_input_label = ?, allow_multi = ?,
                            parent_choice_id = ?
                      WHERE id = ? AND client_id = ?'
                );
                $u->execute([
                    $f['name'], $f['is_required'], $f['active'],
                    $lengthLabel, $f['allow_multi'],
                    $legacyParent,
                    $id, $clientId,
                ]);
            } elseif ($hasLengthInputColumn) {
                $u = $pdo->prepare(
                    'UPDATE product_extras
                        SET name = ?, is_required = ?, active = ?,
                            length_input_label = ?,
                            parent_choice_id = ?
                      WHERE id = ? AND client_id = ?'
                );
                $u->execute([
                    $f['name'], $f['is_required'], $f['active'],
                    $lengthLabel,
                    $legacyParent,
                    $id, $clientId,
                ]);
            } else {
                $u = $pdo->prepare(
                    'UPDATE product_extras
                        SET name = ?, is_required = ?, active = ?,
                            parent_choice_id = ?
                      WHERE id = ? AND client_id = ?'
                );
                $u->execute([
                    $f['name'], $f['is_required'], $f['active'],
                    $legacyParent,
                    $id, $clientId,
                ]);
            }

            // Replace the junction rows. Validate ids belong to this
            // product's catalogue first (POST inputs aren't trustworthy).
            $pdo->prepare(
                'DELETE FROM product_extra_parent_choices WHERE product_extra_id = ?'
            )->execute([$id]);
            if ($f['parent_choice_ids']) {
                $ph = implode(',', array_fill(0, count($f['parent_choice_ids']), '?'));
                $vps = $pdo->prepare(
                    "SELECT c.id FROM product_extra_choices c
                       JOIN product_extras e ON e.id = c.product_extra_id
                      WHERE c.id IN ($ph)
                        AND e.product_id = ? AND e.client_id = ?
                        AND e.id != ?"
                );
                $vps->execute([...$f['parent_choice_ids'], (int) $extra['product_id'], $clientId, $id]);
                $validParents = array_map('intval', $vps->fetchAll(PDO::FETCH_COLUMN));
                if ($validParents) {
                    $jIns = $pdo->prepare(
                        'INSERT INTO product_extra_parent_choices
                           (product_extra_id, product_extra_choice_id)
                         VALUES (?, ?)'
                    );
                    foreach ($validParents as $cid) {
                        $jIns->execute([$id, $cid]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Option updated.';
            header('Location: /admin/products/extras.php?product_id=' . (int) $extra['product_id']);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Could not save: ' . $e->getMessage();
        }
    }
}

// All choices in this product (excluding self's own choices, to avoid loops).
$choiceStmt = db()->prepare(
    'SELECT c.id, c.label, e.name AS extra_name
       FROM product_extra_choices c
       JOIN product_extras e ON e.id = c.product_extra_id
      WHERE e.product_id = ? AND e.client_id = ? AND e.id != ?
   ORDER BY e.name, c.sort_order, c.label'
);
$choiceStmt->execute([(int) $extra['product_id'], $clientId, $id]);
$availableChoices = $choiceStmt->fetchAll();

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit option &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .form-group input[type="number"] {
            width: 100%; font: inherit; padding: 0.5625rem 0.75rem;
            border: 1px solid var(--border-strong); border-radius: 8px; background: var(--bg-input);
        }
        .toggle-stack {
            display: flex; flex-direction: column; gap: 0.625rem;
            margin: 1.25rem 0;
        }
        .toggle-stack label {
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-size: 0.9375rem; color: var(--text-primary); cursor: pointer;
            margin: 0; padding: 0;
        }
        .toggle-stack input[type="checkbox"] { width: 18px; height: 18px; }
        .toggle-stack small {
            color: var(--text-faint); font-size: 0.8125rem; margin-left: 0.375rem;
        }
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
                        ['Products',                              '/admin/products/index.php'],
                        [(string) $extra['product_name'],         '/admin/products/edit.php?id='   . (int) $extra['product_id']],
                        ['Options',                               '/admin/products/extras.php?product_id=' . (int) $extra['product_id']],
                        [(string) $extra['name'],                 null],
                    ]);
                ?>
                <h1 class="page-title">Edit option: <?= e((string) $extra['name']) ?></h1>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <!--
            Plain-language intro so non-tech tenants understand what
            this page is for before they touch any fields. Same pattern
            applied across all of the products admin pages.
        -->
        <section class="section" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.875rem 1.125rem;margin-bottom:1rem">
            <p style="margin:0;color:#0c4a6e;font-size:0.9375rem;line-height:1.5">
                <strong>What is an Option?</strong>
                Something the salesperson picks for each blind when building a quote &mdash;
                e.g. <em>Bottom Weight</em>, <em>Bracket colour</em>, <em>Control side</em>.
                Each option has a list of <strong>choices</strong> the customer can pick from
                (you'll add those on the next page). You can also have the salesperson type
                in a measurement (e.g. wand length) alongside the choice.
            </p>
        </section>

        <section class="section">
            <form method="post" action="/admin/products/extra-edit.php?id=<?= (int) $id ?>"
                  class="form" novalidate>
                <?= csrf_field() ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="name">Name <span class="required">*</span></label>
                        <input id="name" name="name" type="text"
                               required maxlength="150"
                               value="<?= e((string) $f['name']) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label>Appears when (optional)</label>
                        <?php if (!$availableChoices): ?>
                            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0">
                                No other choices on this product yet.
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
                                when <strong>any</strong> of them is selected. Leave all unticked to
                                make it always visible.
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($hasLengthInputColumn):
                    $hasLengthInput = $f['length_input_label'] !== '';
                ?>
                <!--
                    Length input is a two-step "tickbox + label" pattern.
                    Old single-input UI was easy to misread as "type the
                    actual length here" rather than "name the field you
                    want shown to the salesperson". Now it's:
                      [✓] Also ask the salesperson for a number
                          (e.g. wand length)
                          ┌────────────────────────────────┐
                          │ Wand length (mm)               │  ← only shown when ticked
                          └────────────────────────────────┘
                          What it'll say above the number box.

                    The underlying column is the same string — empty =
                    off, non-empty = on with that label.
                -->
                <fieldset style="border:1px solid var(--border);border-radius:10px;padding:1rem 1.125rem;margin:1rem 0">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;font-weight:600;color:var(--text-body);text-transform:uppercase;letter-spacing:0.05em">
                        Capture a number from the salesperson
                    </legend>

                    <label style="display:inline-flex;align-items:flex-start;gap:0.5rem;font-weight:500;cursor:pointer;margin-bottom:0.625rem">
                        <input type="checkbox" id="show_length_input"
                               <?= $hasLengthInput ? 'checked' : '' ?>
                               style="margin-top:0.25rem">
                        <span>
                            Also show a number input next to this option
                            <small style="display:block;color:var(--text-faint);font-weight:400;font-size:0.8125rem;margin-top:0.125rem">
                                Useful for things like wand length, cable length, motor count &mdash;
                                anything where the salesperson needs to type a value alongside
                                picking from the choices. The typed value goes on the quote line
                                for supplier docs. It doesn't change the price.
                            </small>
                        </span>
                    </label>

                    <div id="length_label_wrap" style="<?= $hasLengthInput ? '' : 'display:none' ?>;margin-left:1.625rem">
                        <label for="length_input_label" style="font-size:0.8125rem;font-weight:600;color:var(--text-secondary)">
                            What to call this field
                        </label>
                        <input id="length_input_label" name="length_input_label" type="text"
                               maxlength="60"
                               value="<?= e((string) $f['length_input_label']) ?>"
                               placeholder="e.g. Wand length (mm)"
                               style="width:100%;font:inherit;padding:0.5625rem 0.75rem;border:1px solid var(--border-strong);border-radius:8px;background:var(--bg-input);box-sizing:border-box;margin-top:0.25rem">
                        <small style="color:var(--text-faint);font-size:0.8125rem;display:block;margin-top:0.25rem">
                            The label shown above the number box in the quote builder.
                            Include the unit (e.g. <em>mm</em>) so the salesperson knows what to type.
                        </small>
                    </div>
                </fieldset>

                <script>
                (function () {
                    var tick = document.getElementById('show_length_input');
                    var wrap = document.getElementById('length_label_wrap');
                    var lbl  = document.getElementById('length_input_label');
                    if (!tick || !wrap || !lbl) return;
                    tick.addEventListener('change', function () {
                        if (tick.checked) {
                            wrap.style.display = '';
                            // Pre-fill a sensible default if blank, so save
                            // works immediately. The user can rename it.
                            if (lbl.value.trim() === '') {
                                lbl.value = 'Length (mm)';
                            }
                            setTimeout(function () { lbl.focus(); lbl.select(); }, 0);
                        } else {
                            wrap.style.display = 'none';
                            lbl.value = '';   // empty = off on the server side
                        }
                    });
                })();
                </script>
                <?php endif; ?>

                <div class="toggle-stack">
                    <label for="is_required">
                        <input type="checkbox" id="is_required" name="is_required" value="1"
                               <?= (int) $f['is_required'] === 1 ? 'checked' : '' ?>>
                        Required
                        <small>customer must pick a choice</small>
                    </label>
                    <?php if ($hasAllowMultiColumn): ?>
                    <label for="allow_multi">
                        <input type="checkbox" id="allow_multi" name="allow_multi" value="1"
                               <?= (int) $f['allow_multi'] === 1 ? 'checked' : '' ?>>
                        Allow multiple choices
                        <small>renders as tick-boxes instead of a dropdown &mdash; salesperson can pick any combination, each ticked choice contributes to the price</small>
                    </label>
                    <?php endif; ?>
                    <label for="active">
                        <input type="checkbox" id="active" name="active" value="1"
                               <?= (int) $f['active'] === 1 ? 'checked' : '' ?>>
                        Active
                        <small>uncheck to hide from quote builder</small>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="/admin/products/extras.php?product_id=<?= (int) $extra['product_id'] ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
<?php
    // Floating "Fix next →" pill — same as on extra.php. Lets the
    // user chain through catalogue-health fixes without going back
    // to the product page each time.
    require_once __DIR__ . '/../../_partials/catalogue_validator.php';
    echo catalogue_render_fix_next_pill(
        (int) $extra['product_id'],
        (int) $clientId,
        (string) ($_SERVER['REQUEST_URI'] ?? ''),
        (string) ($extra['product_name'] ?? '')
    );
?>
</body>
</html>
