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

// Closure for an existing row's "Available on" multi-select widget,
// shared with admin/products/edit.php (Phase 2B inline grids). See
// the partial for the full behaviour notes.
require_once __DIR__ . '/../../_partials/choices_grid_helpers.php';
$renderSystemMultiSelect = make_render_system_multi_select($systems);

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e((string) $extra['name']) ?> &middot; Choices &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <?php require __DIR__ . '/../../_partials/choices_grid_css.php'; ?>
    <style>
        /* (page-specific styles, none for now — all moved to the
           shared partial above so edit.php can re-use them) */
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
                        ['Products',                       '/admin/products/index.php'],
                        [(string) $extra['product_name'],  '/admin/products/edit.php?id='   . (int) $extra['product_id']],
                        ['Options',                        '/admin/products/extras.php?product_id=' . (int) $extra['product_id']],
                        [(string) $extra['name'],          '/admin/products/extra-edit.php?id=' . (int) $extraId],
                        ['Choices',                        null],
                    ]);
                ?>
                <h1 class="page-title">
                    <?= e((string) $extra['name']) ?> &mdash; Choices
                </h1>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.875rem 1.125rem;margin-bottom:1rem">
            <p style="margin:0;color:#0c4a6e;font-size:0.9375rem;line-height:1.5">
                <strong>What's a choice?</strong>
                The values the customer can pick from inside this option &mdash; e.g. for
                <em>Bottom Weight</em> the choices might be <em>Chained</em> and <em>Chainless</em>.
                Each choice can carry its own price modifier and (if needed) a width-based
                price table for size-dependent surcharges.
            </p>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">
                    Choices (<?= count($choices) ?>)
                    <!--
                        Persistent save status. Reassures the user every
                        edit is persisted as soon as they Tab/Enter out
                        of a cell — without a Save button, this pill is
                        the only place that says "yes, you're safe".
                    -->
                    <span id="save-indicator" class="save-indicator">All changes saved</span>
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

        <!--
            "Done" button is the psychological Save affordance for users
            who expect one at the bottom of an editing page. Changes are
            already auto-saved as they're made; this just navigates back
            to the parent options list. The reassurance text underneath
            spells out what "Done" actually does so it doesn't feel
            misleading.
        -->
        <section class="section" style="margin-top:1rem">
            <div class="form-actions" style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
                <a href="/admin/products/extras.php?product_id=<?= (int) $extra['product_id'] ?>"
                   class="btn btn-primary">
                    Done &mdash; back to options
                </a>
                <span style="color:#6b7280;font-size:0.875rem">
                    Every change is saved automatically as you make it &mdash; you don't have to click anything to save.
                    The badge at the top of the page tells you when something's still in flight.
                </span>
            </div>
        </section>
    </main>
</div>

<?php require __DIR__ . '/../../_partials/confirm_modal.php'; ?>
<?php require __DIR__ . '/../../_partials/sortable_init.php'; ?>
<?php
    // Inline-edit JS for every .choices-grid-wrap on this page.
    // $systems is loaded earlier; $gridProductId is passed in scope
    // so the partial can build the "+ Sub-option" link.
    $gridProductId = (int) $extra['product_id'];
    require __DIR__ . '/../../_partials/choices_grid_js.php';
?>

</body>
</html>
