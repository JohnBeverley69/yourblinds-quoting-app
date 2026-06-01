<?php
declare(strict_types=1);

/**
 * Guided "set up your first product" wizard.
 *
 * Brand-new tenants land on /admin/products/index.php with zero
 * products and a vague "click + Add product, then figure out the
 * rest" empty state. This wizard replaces the figuring-out with a
 * forced sequence:
 *
 *   1. Name        — create the product row
 *   2. Systems     — add at least one operating system (Standard /
 *                    Motorised / etc.). Required because price tables
 *                    hang off systems.
 *   3. Fabrics     — add at least one fabric (or whatever the
 *                    product's option_label is). Required for the
 *                    product to be quotable.
 *   4. Done        — celebration screen explaining what's left
 *                    (price tables) and dropping them onto the edit
 *                    page where they can add those inline.
 *
 * Each step uses Post/Redirect/Get — refreshing the page never
 * re-submits a form. The current step is inferred from the product's
 * state if no ?step= is provided, so a user who closes the tab can
 * resume exactly where they left off by hitting the wizard URL with
 * just the product id.
 *
 * "Skip wizard" link in the header drops out to the standard edit
 * page at any time. The wizard never blocks; it just suggests an
 * order.
 *
 * Sibling pages (/new.php, /systems.php, /options.php) still work
 * — the wizard wraps them, it doesn't replace them.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();

$productId = (int) ($_GET['id'] ?? 0);
$forcedStep = isset($_GET['step']) ? (int) $_GET['step'] : 0;

// ── Load product if id supplied ────────────────────────────────────────
$product = null;
if ($productId > 0) {
    $st = $pdo->prepare(
        'SELECT id, name, option_label
           FROM products
          WHERE id = ? AND client_id = ?'
    );
    $st->execute([$productId, $clientId]);
    $product = $st->fetch() ?: null;
    if (!$product) {
        // Bad id — restart the wizard from scratch.
        header('Location: /admin/products/wizard.php');
        exit;
    }
}

// ── State counts (drives both step inference and the "you're done with
//    step X" indicators in the UI) ─────────────────────────────────────
$systemCount = 0;
$fabricCount = 0;
if ($product) {
    $cnt = static function (string $sql, array $args) use ($pdo): int {
        $st = $pdo->prepare($sql);
        $st->execute($args);
        return (int) $st->fetchColumn();
    };
    $systemCount = $cnt(
        'SELECT COUNT(*) FROM product_systems
          WHERE product_id = ? AND client_id = ? AND active = 1',
        [$productId, $clientId]
    );
    $fabricCount = $cnt(
        'SELECT COUNT(*) FROM product_options
          WHERE product_id = ? AND client_id = ? AND active = 1',
        [$productId, $clientId]
    );
}

// ── Determine current step ────────────────────────────────────────────
//
// If the user explicitly asked for a step via ?step=N, honour that —
// they may be using the back link. Otherwise infer from state: no
// product = step 1, no system = step 2, no fabric = step 3, else
// step 4 (done).
if ($forcedStep >= 1 && $forcedStep <= 4) {
    $step = $forcedStep;
} elseif (!$product) {
    $step = 1;
} elseif ($systemCount === 0) {
    $step = 2;
} elseif ($fabricCount === 0) {
    $step = 3;
} else {
    $step = 4;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$error = null;

// ── POST handlers ─────────────────────────────────────────────────────
//
// Always Post/Redirect/Get. The redirect target is normally the next
// step, except for "add another" which loops back to the same step
// so the user can pile on more rows.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $postStep = (int) ($_POST['_step'] ?? 0);
    $action   = (string) ($_POST['_action'] ?? '');

    try {
        // ── Step 1: create the product ─────────────────────────────────
        if ($postStep === 1) {
            $name        = trim((string) ($_POST['name']         ?? ''));
            $optionLabel = trim((string) ($_POST['option_label'] ?? '')) ?: 'Fabric';

            if ($name === '')              throw new RuntimeException('Product name is required.');
            if (strlen($name) > 150)       throw new RuntimeException('Product name too long (max 150).');
            if (strlen($optionLabel) > 40) throw new RuntimeException('Option label too long (max 40).');

            $sortStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM products WHERE client_id = ?'
            );
            $sortStmt->execute([$clientId]);
            $nextSort = (int) $sortStmt->fetchColumn();

            $ins = $pdo->prepare(
                'INSERT INTO products (client_id, name, option_label, sort_order, active)
                  VALUES (?, ?, ?, ?, 1)'
            );
            $ins->execute([$clientId, $name, $optionLabel, $nextSort]);
            $newId = (int) $pdo->lastInsertId();

            header('Location: /admin/products/wizard.php?id=' . $newId . '&step=2');
            exit;
        }

        // ── Step 2: add a system ───────────────────────────────────────
        if ($postStep === 2 && $product) {
            if ($action === 'add') {
                $sysName = trim((string) ($_POST['system_name'] ?? ''));
                if ($sysName === '')         throw new RuntimeException('System name is required.');
                if (strlen($sysName) > 150)  throw new RuntimeException('System name too long (max 150).');

                $sortStmt = $pdo->prepare(
                    'SELECT COALESCE(MAX(sort_order), -1) + 1
                       FROM product_systems
                      WHERE product_id = ? AND client_id = ?'
                );
                $sortStmt->execute([$productId, $clientId]);
                $nextSort = (int) $sortStmt->fetchColumn();

                $ins = $pdo->prepare(
                    'INSERT INTO product_systems
                      (client_id, product_id, name, sort_order, active)
                      VALUES (?, ?, ?, ?, 1)'
                );
                $ins->execute([$clientId, $productId, $sysName, $nextSort]);

                $_SESSION['flash_success'] = 'Added system "' . $sysName . '".';
                header('Location: /admin/products/wizard.php?id=' . $productId . '&step=2');
                exit;
            }
            if ($action === 'continue') {
                // Server-side guard — the button is also disabled in HTML
                // when count = 0, but defence-in-depth.
                if ($systemCount === 0) {
                    throw new RuntimeException('Add at least one system before continuing.');
                }
                header('Location: /admin/products/wizard.php?id=' . $productId . '&step=3');
                exit;
            }
        }

        // ── Step 3: add a fabric ───────────────────────────────────────
        //
        // Simpler fields than the full /options.php form — wizard
        // collects just band + name. Supplier / colour / code can be
        // filled in later. Keeps the cognitive load low at the worst
        // moment (brand-new tenant doesn't yet know which fields
        // actually matter to them).
        if ($postStep === 3 && $product) {
            if ($action === 'add') {
                $band = trim((string) ($_POST['band_code'] ?? ''));
                $fab  = trim((string) ($_POST['fabric_name'] ?? ''));
                $band = (string) preg_replace('/^band\s+/i', '', $band);

                if ($band === '')          throw new RuntimeException('Band code is required (e.g. A, B, C).');
                if (strlen($band) > 20)    throw new RuntimeException('Band code too long (max 20).');
                if ($fab === '')           throw new RuntimeException('Fabric name is required.');
                if (strlen($fab) > 150)    throw new RuntimeException('Fabric name too long (max 150).');

                $ins = $pdo->prepare(
                    'INSERT INTO product_options
                      (client_id, product_id, band_code, name, sort_order, active)
                      VALUES (?, ?, ?, ?, 0, 1)'
                );
                $ins->execute([$clientId, $productId, strtoupper($band), $fab]);

                $_SESSION['flash_success'] = 'Added "' . $fab . '" (Band ' . strtoupper($band) . ').';
                header('Location: /admin/products/wizard.php?id=' . $productId . '&step=3');
                exit;
            }
            if ($action === 'continue') {
                if ($fabricCount === 0) {
                    throw new RuntimeException('Add at least one fabric before continuing.');
                }
                header('Location: /admin/products/wizard.php?id=' . $productId . '&step=4');
                exit;
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// ── Load lists for display on steps 2 + 3 ─────────────────────────────
$systems = [];
$fabrics = [];
if ($product && $step >= 2) {
    $sysSt = $pdo->prepare(
        'SELECT id, name FROM product_systems
          WHERE product_id = ? AND client_id = ? AND active = 1
          ORDER BY sort_order, name'
    );
    $sysSt->execute([$productId, $clientId]);
    $systems = $sysSt->fetchAll();
}
if ($product && $step >= 3) {
    $fabSt = $pdo->prepare(
        'SELECT id, band_code, name, colour
           FROM product_options
          WHERE product_id = ? AND client_id = ? AND active = 1
          ORDER BY band_code, name'
    );
    $fabSt->execute([$productId, $clientId]);
    $fabrics = $fabSt->fetchAll();
}

// Steps metadata for the stepper UI.
$STEPS = [
    1 => ['Name',     'What kind of blind is this?'],
    2 => ['Systems',  'Standard / Motorised / etc.'],
    3 => ['Fabrics',  'The materials you actually sell'],
    4 => ['Done',     'Time to add price tables'],
];

// Highlight the "Setup wizard" sidebar entry while in the wizard
// flow, not "Products" — the user is doing focused setup work and
// the breadcrumb should reflect that. Once they hit "Open product
// edit page →" at the end, they're back on the Products nav.
$activeNav = 'wizard';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup wizard &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .wiz-wrap { max-width: 44rem; margin: 0 auto; }

        /* Progress stepper at the top — clickable for completed
           steps so users can go back to fix a previous entry. */
        .wiz-stepper {
            display: flex; align-items: stretch; gap: 0;
            margin: 0 0 1.25rem; padding: 0; list-style: none;
        }
        .wiz-stepper li {
            flex: 1; text-align: center; position: relative;
            padding: 0.5rem 0.25rem 0.625rem;
        }
        .wiz-stepper li::after {
            content: ''; position: absolute;
            top: 1.5rem; right: -50%;
            width: 100%; height: 2px;
            background: var(--border); z-index: 0;
        }
        .wiz-stepper li:last-child::after { display: none; }
        .wiz-stepper li.done::after    { background: #10b981; }
        .wiz-stepper li.current::after { background: var(--border); }
        .wiz-stepper .num {
            position: relative; z-index: 1;
            display: inline-flex; align-items: center; justify-content: center;
            width: 2rem; height: 2rem; border-radius: 999px;
            background: var(--border); color: var(--text-faint);
            font-weight: 700; font-size: 0.875rem;
        }
        .wiz-stepper li.done    .num { background: #10b981; color: #fff; }
        .wiz-stepper li.current .num { background: #1f3b5b; color: #fff;
                                       box-shadow: 0 0 0 4px #dbeafe; }
        .wiz-stepper .lbl {
            display: block; margin-top: 0.375rem;
            font-size: 0.75rem; color: var(--text-faint);
            text-transform: uppercase; letter-spacing: 0.04em;
            font-weight: 600;
        }
        .wiz-stepper li.current .lbl { color: #1f3b5b; }
        .wiz-stepper li.done    .lbl { color: #065f46; }
        .wiz-stepper a { color: inherit; text-decoration: none; }

        .wiz-card {
            background: #fff; border: 1px solid var(--border);
            border-radius: 12px; padding: 1.5rem 1.625rem;
        }
        .wiz-card h2 {
            margin: 0 0 0.25rem; font-size: 1.25rem; color: #1f3b5b;
        }
        .wiz-card .lede {
            color: var(--text-muted); font-size: 0.9375rem;
            margin: 0 0 1.25rem; line-height: 1.55;
        }
        .wiz-card .helper {
            background: #eff6ff; border: 1px solid #bfdbfe;
            border-radius: 8px; padding: 0.625rem 0.875rem;
            font-size: 0.8125rem; color: #1e40af;
            margin-bottom: 1rem; line-height: 1.5;
        }
        .wiz-list {
            background: var(--bg-subtle); border: 1px solid var(--border);
            border-radius: 8px; padding: 0.5rem 0.75rem;
            margin-bottom: 0.875rem;
        }
        .wiz-list-item {
            padding: 0.3125rem 0;
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--bg-subtle-2);
        }
        .wiz-list-item:last-child { border-bottom: 0; }
        .wiz-list-item .check {
            color: #10b981; font-weight: 700; font-size: 1rem;
        }
        .wiz-list-empty {
            color: var(--text-faint); font-style: italic; font-size: 0.875rem;
            padding: 0.5rem 0;
        }
        .wiz-form .row {
            display: grid; gap: 0.5rem 0.75rem; align-items: end;
            margin-bottom: 0.625rem;
        }
        .wiz-form .row.cols-2 { grid-template-columns: 8rem 1fr auto; }
        .wiz-form label {
            font-size: 0.6875rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-faint); font-weight: 600;
        }
        .wiz-form input {
            padding: 0.5rem 0.625rem; border: 1px solid var(--border-strong);
            border-radius: 6px; font: inherit;
        }
        .wiz-actions {
            display: flex; gap: 0.5rem; align-items: center;
            justify-content: space-between;
            margin-top: 1rem; padding-top: 1rem;
            border-top: 1px solid var(--bg-subtle-2);
        }
        .wiz-actions .left { display: flex; gap: 0.5rem; align-items: center; }
        .wiz-skip {
            color: var(--text-faint); font-size: 0.8125rem; text-decoration: underline;
        }
        .wiz-done-tile {
            background: linear-gradient(135deg, #d1fae5 0%, #ecfdf5 100%);
            border: 1px solid #a7f3d0; border-radius: 12px;
            padding: 1.5rem 1.625rem; margin-bottom: 1rem;
            text-align: center;
        }
        .wiz-done-tile .icon { font-size: 3rem; line-height: 1; }
        .wiz-done-tile h2 { color: #065f46; margin: 0.5rem 0 0.375rem; }
        .wiz-done-tile p  { color: #047857; margin: 0; line-height: 1.55; }
        .wiz-next-step {
            background: #fef3c7; border: 1px solid #fcd34d;
            border-radius: 8px; padding: 0.875rem 1rem;
            font-size: 0.875rem; color: #78350f;
            margin-top: 0.875rem; line-height: 1.5;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="wiz-wrap">
            <div class="page-header" style="margin-bottom:0.875rem">
                <div>
                    <h1 class="page-title" style="margin:0">Set up a product</h1>
                    <p class="page-subtitle" style="margin:0.25rem 0 0">
                        Guided 4-step setup.
                        <?php if ($product): ?>
                            <a href="/admin/products/edit.php?id=<?= (int) $productId ?>" class="wiz-skip">
                                Skip wizard &rarr; jump to the edit page
                            </a>
                        <?php else: ?>
                            <a href="/admin/products/new.php" class="wiz-skip">
                                Skip wizard &rarr; standard "add product" form
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Stepper -->
            <ol class="wiz-stepper">
                <?php foreach ($STEPS as $n => [$lbl, $sub]):
                    $isDone    = $n < $step;
                    $isCurrent = $n === $step;
                    $cls = $isDone ? 'done' : ($isCurrent ? 'current' : '');
                    // Allow back-navigation only to already-completed
                    // steps (don't let users skip forward by clicking).
                    $href = ($isDone && $product)
                        ? '/admin/products/wizard.php?id=' . $productId . '&step=' . $n
                        : null;
                ?>
                    <li class="<?= e($cls) ?>">
                        <?php if ($href): ?><a href="<?= e($href) ?>"><?php endif; ?>
                            <span class="num"><?= $isDone ? '&check;' : $n ?></span>
                            <span class="lbl"><?= e($lbl) ?></span>
                        <?php if ($href): ?></a><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if ($flashMsg): ?>
                <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
            <?php endif; ?>
            <?php if ($flashErr): ?>
                <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <!-- ─── STEP 1: Name ──────────────────────────────────────── -->
            <?php if ($step === 1): ?>
                <div class="wiz-card">
                    <h2>What kind of blind are we adding?</h2>
                    <p class="lede">
                        A <strong>product</strong> is one type of blind &mdash; e.g.
                        <em>Roller Blind</em>, <em>Vertical</em>, <em>Roman</em>,
                        <em>Metal Venetian</em>. Each product gets its own systems,
                        fabrics, options and price tables.
                    </p>

                    <form method="post" class="wiz-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_step" value="1">

                        <div class="row" style="grid-template-columns:1fr">
                            <div>
                                <label for="name">Product name *</label>
                                <input id="name" name="name" type="text"
                                       required maxlength="150" autofocus
                                       placeholder="e.g. Roller Blind"
                                       value="<?= e((string) ($_POST['name'] ?? '')) ?>">
                            </div>
                        </div>

                        <div class="row" style="grid-template-columns:1fr">
                            <div>
                                <label for="option_label">What do you call the material this product is made of?</label>
                                <input id="option_label" name="option_label" type="text"
                                       maxlength="40"
                                       placeholder="Fabric"
                                       value="<?= e((string) ($_POST['option_label'] ?? 'Fabric')) ?>">
                            </div>
                        </div>

                        <div class="helper">
                            For rollers / romans use <strong>Fabric</strong>.
                            For metal venetians, try <strong>Colour</strong>.
                            For wood venetians, <strong>Finish</strong>.
                            (You can change this later.)
                        </div>

                        <div class="wiz-actions">
                            <div class="left"></div>
                            <button type="submit" class="btn btn-primary">
                                Create product &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ─── STEP 2: Systems ───────────────────────────────────── -->
            <?php if ($step === 2 && $product): ?>
                <div class="wiz-card">
                    <h2>What systems does <em><?= e((string) $product['name']) ?></em> come in?</h2>
                    <p class="lede">
                        A <strong>system</strong> is the operating mechanism &mdash;
                        e.g. <em>Standard</em>, <em>Motorised</em>, <em>Battery</em>,
                        <em>Pelmet</em>. Each system can have its own price table.
                        Most products need just one or two.
                    </p>

                    <?php if ($systems): ?>
                        <div class="wiz-list">
                            <?php foreach ($systems as $s): ?>
                                <div class="wiz-list-item">
                                    <span class="check">&check;</span>
                                    <strong><?= e((string) $s['name']) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="wiz-list">
                            <div class="wiz-list-empty">No systems yet — add at least one below.</div>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="wiz-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_step" value="2">
                        <input type="hidden" name="_action" value="add">

                        <div class="row" style="grid-template-columns:1fr auto">
                            <div>
                                <label for="system_name">Add a system</label>
                                <input id="system_name" name="system_name" type="text"
                                       required maxlength="150" autofocus
                                       placeholder="Standard"
                                       value="">
                            </div>
                            <div style="align-self:end">
                                <button type="submit" class="btn btn-secondary">+ Add</button>
                            </div>
                        </div>
                    </form>

                    <div class="helper">
                        <strong>Tip:</strong> Adding multiple? Add the first now, then
                        click <em>Add</em>. The form re-opens ready for the next one.
                    </div>

                    <form method="post" class="wiz-actions" style="margin:0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_step" value="2">
                        <input type="hidden" name="_action" value="continue">
                        <a href="/admin/products/wizard.php?id=<?= (int) $productId ?>&step=1"
                           class="wiz-skip" style="text-decoration:none;color:var(--text-faint)">
                            &larr; Back
                        </a>
                        <button type="submit" class="btn btn-primary"
                                <?= $systemCount === 0 ? 'disabled style="opacity:0.5;cursor:not-allowed"' : '' ?>>
                            Continue &rarr;
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ─── STEP 3: Fabrics ──────────────────────────────────── -->
            <?php if ($step === 3 && $product):
                $label = (string) ($product['option_label'] ?? 'Fabric');
                if ($label === '') $label = 'Fabric';
                $labelL = strtolower($label);
            ?>
                <div class="wiz-card">
                    <h2>What <?= e($labelL) ?>s do you sell?</h2>
                    <p class="lede">
                        Add at least one <?= e($labelL) ?> with a band code (the
                        price-band letter from your supplier — e.g. A, B, C).
                        You can add more later from the product edit page;
                        for now, one's enough to keep moving.
                    </p>

                    <?php if ($fabrics): ?>
                        <div class="wiz-list">
                            <?php foreach ($fabrics as $f): ?>
                                <div class="wiz-list-item">
                                    <span class="check">&check;</span>
                                    <strong><?= e((string) $f['name']) ?></strong>
                                    <span style="color:var(--text-faint);font-size:0.8125rem">
                                        — Band <?= e((string) $f['band_code']) ?>
                                        <?php if (!empty($f['colour'])): ?>
                                            &middot; <?= e((string) $f['colour']) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="wiz-list">
                            <div class="wiz-list-empty">No <?= e($labelL) ?>s yet — add at least one below.</div>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="wiz-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_step" value="3">
                        <input type="hidden" name="_action" value="add">

                        <div class="row cols-2">
                            <div>
                                <label for="band_code">Band *</label>
                                <input id="band_code" name="band_code" type="text"
                                       required maxlength="20"
                                       placeholder="A"
                                       style="text-transform:uppercase"
                                       value="">
                            </div>
                            <div>
                                <label for="fabric_name"><?= e(ucfirst($labelL)) ?> name *</label>
                                <input id="fabric_name" name="fabric_name" type="text"
                                       required maxlength="150" autofocus
                                       placeholder="e.g. Plain White"
                                       value="">
                            </div>
                            <div style="align-self:end">
                                <button type="submit" class="btn btn-secondary">+ Add</button>
                            </div>
                        </div>
                    </form>

                    <div class="helper">
                        <strong>Tip:</strong> Once you're set up, the standard
                        <?= e($labelL) ?> editor on the edit page lets you bulk-
                        import from an XLSX and add supplier / colour / code
                        details — useful for big ranges.
                    </div>

                    <form method="post" class="wiz-actions" style="margin:0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_step" value="3">
                        <input type="hidden" name="_action" value="continue">
                        <a href="/admin/products/wizard.php?id=<?= (int) $productId ?>&step=2"
                           class="wiz-skip" style="text-decoration:none;color:var(--text-faint)">
                            &larr; Back
                        </a>
                        <button type="submit" class="btn btn-primary"
                                <?= $fabricCount === 0 ? 'disabled style="opacity:0.5;cursor:not-allowed"' : '' ?>>
                            Continue &rarr;
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ─── STEP 4: Done ─────────────────────────────────────── -->
            <?php if ($step === 4 && $product): ?>
                <div class="wiz-done-tile">
                    <div class="icon">🎉</div>
                    <h2>Nice work — <?= e((string) $product['name']) ?> is taking shape</h2>
                    <p>
                        Product created, <?= (int) $systemCount ?>
                        system<?= $systemCount === 1 ? '' : 's' ?>,
                        <?= (int) $fabricCount ?>
                        <?= strtolower((string) ($product['option_label'] ?? 'fabric')) ?><?= $fabricCount === 1 ? '' : 's' ?>.
                        One thing left:
                    </p>
                </div>

                <div class="wiz-card">
                    <h2>Add a price table</h2>
                    <p class="lede">
                        Price tables turn a width × drop into a quotable price.
                        Without one, the salesperson can pick the fabric and
                        system but can't actually generate a number. They live
                        per-system, so each system you added gets its own.
                    </p>

                    <div class="wiz-next-step">
                        <strong>Why not in the wizard?</strong>
                        Price tables are usually built by importing an XLSX from your
                        supplier, or by pasting in a grid of widths × drops. That's
                        easier on the dedicated page than in a wizard step.
                    </div>

                    <div class="wiz-actions" style="border-top:0;margin-top:1.25rem;padding-top:0">
                        <a href="/admin/products/wizard.php?id=<?= (int) $productId ?>&step=3"
                           class="wiz-skip" style="text-decoration:none;color:var(--text-faint)">
                            &larr; Back
                        </a>
                        <a href="/admin/products/edit.php?id=<?= (int) $productId ?>"
                           class="btn btn-primary">
                            Open product edit page &rarr;
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
