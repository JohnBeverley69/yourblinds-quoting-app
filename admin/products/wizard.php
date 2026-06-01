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
        //
        // Two add modes: single-add (one row at a time) and bulk-add
        // (paste a list of names, all go into the chosen band). The
        // bulk path is the workhorse — a typical venetian / vertical
        // tenant has 50-200 colours per band, and one-at-a-time was
        // unusable for that scale.
        if ($postStep === 3 && $product) {
            if ($action === 'add') {
                $band = trim((string) ($_POST['band_code'] ?? ''));
                $fab  = trim((string) ($_POST['fabric_name'] ?? ''));
                $band = (string) preg_replace('/^band\s+/i', '', $band);
                // system_id: blank string or "0" → NULL (universal).
                $sysIdRaw = (string) ($_POST['system_id'] ?? '');
                $sysId    = ($sysIdRaw === '' || $sysIdRaw === '0') ? null : (int) $sysIdRaw;

                if ($band === '')          throw new RuntimeException('Band code is required (e.g. A, B, C, or Standard, Special).');
                if (strlen($band) > 20)    throw new RuntimeException('Band code too long (max 20).');
                if ($fab === '')           throw new RuntimeException('Fabric name is required.');
                if (strlen($fab) > 150)    throw new RuntimeException('Fabric name too long (max 150).');
                if ($sysId !== null) {
                    // Validate the chosen system belongs to this product / tenant.
                    $check = $pdo->prepare(
                        'SELECT 1 FROM product_systems
                          WHERE id = ? AND product_id = ? AND client_id = ?'
                    );
                    $check->execute([$sysId, $productId, $clientId]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('Chosen system is not on this product.');
                    }
                }

                $ins = $pdo->prepare(
                    'INSERT INTO product_options
                      (client_id, product_id, system_id, band_code, name, sort_order, active)
                      VALUES (?, ?, ?, ?, ?, 0, 1)'
                );
                $ins->execute([$clientId, $productId, $sysId, strtoupper($band), $fab]);

                $_SESSION['flash_success'] = 'Added "' . $fab . '" (Band ' . strtoupper($band)
                    . ($sysId !== null ? ', system #' . $sysId : '') . ').';
                header('Location: /admin/products/wizard.php?id=' . $productId . '&step=3');
                exit;
            }
            if ($action === 'add_bulk') {
                $band = trim((string) ($_POST['bulk_band'] ?? ''));
                $band = (string) preg_replace('/^band\s+/i', '', $band);
                $namesRaw = (string) ($_POST['bulk_names'] ?? '');
                $sysIdRaw = (string) ($_POST['bulk_system_id'] ?? '');
                $sysId    = ($sysIdRaw === '' || $sysIdRaw === '0') ? null : (int) $sysIdRaw;

                if ($band === '')         throw new RuntimeException('Band code is required.');
                if (strlen($band) > 20)   throw new RuntimeException('Band code too long (max 20).');
                if ($sysId !== null) {
                    $check = $pdo->prepare(
                        'SELECT 1 FROM product_systems
                          WHERE id = ? AND product_id = ? AND client_id = ?'
                    );
                    $check->execute([$sysId, $productId, $clientId]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('Chosen system is not on this product.');
                    }
                }

                // Split on any newline kind. Skip blanks and overlong
                // lines silently so a stray empty line in a paste
                // doesn't blow up the whole submission.
                $lines = preg_split('/\r\n|\r|\n/', $namesRaw) ?: [];
                $names = [];
                foreach ($lines as $line) {
                    $name = trim($line);
                    if ($name === '')          continue;
                    if (strlen($name) > 150)   continue;
                    $names[] = $name;
                }
                if (!$names) {
                    throw new RuntimeException('No names to add — paste at least one name into the box.');
                }

                $bandUp = strtoupper($band);
                $ins = $pdo->prepare(
                    'INSERT INTO product_options
                      (client_id, product_id, system_id, band_code, name, sort_order, active)
                      VALUES (?, ?, ?, ?, ?, 0, 1)'
                );
                $added   = 0;
                $skipped = 0;
                foreach ($names as $name) {
                    try {
                        $ins->execute([$clientId, $productId, $sysId, $bandUp, $name]);
                        $added++;
                    } catch (Throwable $e) {
                        // Duplicates (uniq constraint) and any other
                        // row-level errors get counted as skips so
                        // the whole batch doesn't fail.
                        $skipped++;
                    }
                }

                $msg = "Added $added to Band $bandUp"
                     . ($sysId !== null ? ' (one system only)' : ' (all systems)')
                     . '.';
                if ($skipped > 0) $msg .= " Skipped $skipped (likely duplicates).";
                $_SESSION['flash_success'] = $msg;
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

        // ── Step 4: create missing price-table stubs ───────────────────
        //
        // User explicitly opted in via the "Create missing" button.
        // Same INSERT IGNORE as the first-time auto-create — safe to
        // run again.
        if ($postStep === 4 && $product && $action === 'create_missing') {
            // Same scope-aware logic as the first-time auto-create:
            // only combos with at least one matching fabric.
            $stubStmt = $pdo->prepare(
                "INSERT IGNORE INTO price_tables
                    (client_id, product_id, system_id, band_code, active)
                 SELECT DISTINCT ?, ?, s.id, po.band_code, 1
                   FROM product_systems s
                   JOIN product_options po
                     ON po.product_id = s.product_id
                    AND po.client_id  = s.client_id
                    AND po.active     = 1
                    AND po.band_code IS NOT NULL
                    AND po.band_code != ''
                    AND (po.system_id IS NULL OR po.system_id = s.id)
                  WHERE s.product_id = ? AND s.client_id = ? AND s.active = 1"
            );
            $stubStmt->execute([
                $clientId, $productId,
                $productId, $clientId,
            ]);
            $created = $stubStmt->rowCount();
            $_SESSION['flash_success'] = $created === 1
                ? '1 price table created.'
                : $created . ' price tables created.';
            header('Location: /admin/products/wizard.php?id=' . $productId . '&step=4');
            exit;
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
    // Pull system_id + system name so we can show "Standard only" /
    // "Special only" tags next to scoped fabrics, and "All systems"
    // (i.e. unscoped) for universal ones.
    $fabSt = $pdo->prepare(
        'SELECT o.id, o.band_code, o.name, o.colour, o.system_id,
                s.name AS system_name
           FROM product_options o
           LEFT JOIN product_systems s ON s.id = o.system_id
          WHERE o.product_id = ? AND o.client_id = ? AND o.active = 1
          ORDER BY o.band_code, o.name'
    );
    $fabSt->execute([$productId, $clientId]);
    $fabrics = $fabSt->fetchAll();
}

// ── Step 4 setup: load price tables + detect missing combos ──────────
//
// Each (system × distinct band_code) combination CAN have a price
// table. The wizard shows the existing ones with fill status, plus
// a list of any (system × band) combos that have no table yet —
// the user can create them all with one click, individually via
// the edit page, or leave them alone if a combo doesn't apply.
//
// Auto-create on FIRST entry to step 4 only (when zero tables
// exist for the product). After that, the user explicitly
// chooses what to create via "Create missing" — otherwise a
// deleted stub would silently come back on next step 4 visit,
// which would be infuriating.
//
// Schema notes:
//   - price_tables has a UNIQUE (product_id, system_id, band_code)
//     constraint (per tenant via client_id). INSERT IGNORE on the
//     create-missing path handles races safely.
//   - Fabrics without band_code don't generate combos (the band
//     is the price-band key — without it, no pricing).
$priceTables   = [];
$missingCombos = [];
if ($product && $step === 4) {
    // Has the product ever had a price table? Used to decide
    // whether to auto-create on first visit.
    $hasAnyStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM price_tables
          WHERE product_id = ? AND client_id = ?'
    );
    $hasAnyStmt->execute([$productId, $clientId]);
    $hasAnyTables = (int) $hasAnyStmt->fetchColumn() > 0;

    if (!$hasAnyTables) {
        // First-time setup: auto-create stubs only for combos that
        // have at least one matching fabric (universal OR scoped to
        // this specific system). Avoids generating stubs for
        // combinations that don't physically exist — e.g. on a
        // Venetian where Special-band colours only apply to the
        // Special system, the (Standard system × Special band)
        // combo isn't created.
        $stubStmt = $pdo->prepare(
            "INSERT IGNORE INTO price_tables
                (client_id, product_id, system_id, band_code, active)
             SELECT DISTINCT ?, ?, s.id, po.band_code, 1
               FROM product_systems s
               JOIN product_options po
                 ON po.product_id = s.product_id
                AND po.client_id  = s.client_id
                AND po.active     = 1
                AND po.band_code IS NOT NULL
                AND po.band_code != ''
                AND (po.system_id IS NULL OR po.system_id = s.id)
              WHERE s.product_id = ? AND s.client_id = ? AND s.active = 1"
        );
        $stubStmt->execute([
            $clientId, $productId,
            $productId, $clientId,
        ]);
    }

    // Load every existing (system, band) combo with its fill status.
    $combosStmt = $pdo->prepare(
        "SELECT t.id, s.name AS system_name, t.band_code,
                (SELECT COUNT(*) FROM price_table_rows r
                  WHERE r.price_table_id = t.id) AS cell_count
           FROM price_tables t
           JOIN product_systems s ON s.id = t.system_id
          WHERE t.product_id = ? AND t.client_id = ? AND t.active = 1
            AND s.active = 1
          ORDER BY s.sort_order, s.name, t.band_code"
    );
    $combosStmt->execute([$productId, $clientId]);
    $priceTables = $combosStmt->fetchAll();

    // Detect (system × band) combos that don't yet have a table.
    // System scoping respected: a band only counts as "missing" for
    // a system if there's at least one fabric of that band that
    // could be used with that system (universal or scoped to it).
    $missingStmt = $pdo->prepare(
        "SELECT DISTINCT s.id AS system_id, s.name AS system_name, po.band_code
           FROM product_systems s
           JOIN product_options po
             ON po.product_id = s.product_id
            AND po.client_id  = s.client_id
            AND po.active     = 1
            AND po.band_code IS NOT NULL
            AND po.band_code != ''
            AND (po.system_id IS NULL OR po.system_id = s.id)
          WHERE s.product_id = ? AND s.client_id = ? AND s.active = 1
            AND NOT EXISTS (
              SELECT 1 FROM price_tables t
               WHERE t.product_id = s.product_id
                 AND t.system_id  = s.id
                 AND t.band_code  = po.band_code
                 AND t.client_id  = ?
                 AND t.active     = 1
            )
          ORDER BY s.sort_order, s.name, po.band_code"
    );
    $missingStmt->execute([
        $productId, $clientId,
        $clientId,
    ]);
    $missingCombos = $missingStmt->fetchAll();
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
                                    <form method="post" action="/admin/products/system-delete.php"
                                          style="margin:0 0 0 auto;display:inline"
                                          data-confirm="Remove the &quot;<?= e((string) $s['name']) ?>&quot; system? Any price tables it has go too.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                                        <input type="hidden" name="return_to"
                                               value="/admin/products/wizard.php?id=<?= (int) $productId ?>&amp;step=2">
                                        <button type="submit"
                                                style="background:transparent;border:0;color:#b91c1c;cursor:pointer;font-size:0.8125rem;text-decoration:underline">
                                            Remove
                                        </button>
                                    </form>
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
                        Add at least one <?= e($labelL) ?> with a <strong>band
                        code</strong>. The band groups <?= e($labelL) ?>s that
                        share the same price table — your "cheap range" and
                        "premium range" are usually different bands. Common
                        codes: <code>A</code>/<code>B</code>/<code>C</code>
                        from a supplier price list, or words like
                        <code>Standard</code>/<code>Special</code>.
                    </p>
                    <?php if (count($systems) >= 2): ?>
                        <p class="lede" style="margin-top:-0.5rem">
                            By default a <?= e($labelL) ?> is <strong>universal</strong>
                            — available with every system. If a colour only
                            applies to a specific system (e.g. Standard slats
                            and Special slats on a Venetian have different
                            ranges), pick that system in the dropdown.
                        </p>
                    <?php endif; ?>

                    <?php if ($fabrics): ?>
                        <div class="wiz-list" style="max-height:18rem;overflow-y:auto">
                            <?php foreach ($fabrics as $f): ?>
                                <div class="wiz-list-item">
                                    <span class="check">&check;</span>
                                    <strong><?= e((string) $f['name']) ?></strong>
                                    <span style="color:var(--text-faint);font-size:0.8125rem">
                                        — Band <?= e((string) $f['band_code']) ?>
                                        <?php if (!empty($f['colour'])): ?>
                                            &middot; <?= e((string) $f['colour']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($f['system_name'])): ?>
                                            &middot; <em style="color:#7c3aed">
                                                <?= e((string) $f['system_name']) ?> only
                                            </em>
                                        <?php elseif (count($systems) >= 2): ?>
                                            &middot; <em style="color:var(--text-faint)">all systems</em>
                                        <?php endif; ?>
                                    </span>
                                    <form method="post" action="/admin/products/option-delete.php"
                                          style="margin:0 0 0 auto;display:inline"
                                          data-confirm="Remove &quot;<?= e((string) $f['name']) ?>&quot; from your <?= e($labelL) ?>s?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                        <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                                        <input type="hidden" name="return_to"
                                               value="/admin/products/wizard.php?id=<?= (int) $productId ?>&amp;step=3">
                                        <button type="submit"
                                                style="background:transparent;border:0;color:#b91c1c;cursor:pointer;font-size:0.8125rem;text-decoration:underline">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="wiz-list">
                            <div class="wiz-list-empty">No <?= e($labelL) ?>s yet — add at least one below.</div>
                        </div>
                    <?php endif; ?>

                    <!-- Reusable system <select> snippet. Only shows when
                         the product has 2+ systems; with 0 or 1 system
                         there's nothing meaningful to scope to. -->
                    <?php
                        $renderSystemSelect = function (string $id, string $name) use ($systems) {
                            if (count($systems) < 2) {
                                return '';
                            }
                            $html = '<div><label for="' . e($id) . '">Available on</label>'
                                  . '<select id="' . e($id) . '" name="' . e($name) . '"'
                                  . ' style="padding:0.5rem 0.625rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit;background:var(--bg-input);color:var(--text-body);width:100%">'
                                  . '<option value="">All systems (universal)</option>';
                            foreach ($systems as $s) {
                                $html .= '<option value="' . (int) $s['id'] . '">'
                                       . e((string) $s['name']) . ' only</option>';
                            }
                            $html .= '</select></div>';
                            return $html;
                        };
                    ?>

                    <!-- Single-add row — fast one-off entry. -->
                    <h3 style="margin:1.25rem 0 0.5rem;font-size:0.9375rem;color:var(--text-primary)">
                        Add one
                    </h3>
                    <form method="post" class="wiz-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_step" value="3">
                        <input type="hidden" name="_action" value="add">

                        <div class="row" style="grid-template-columns:6rem 1fr<?= count($systems) >= 2 ? ' 11rem' : '' ?> auto;align-items:end;gap:0.5rem">
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
                                       required maxlength="150"
                                       placeholder="e.g. Plain White"
                                       value="">
                            </div>
                            <?= $renderSystemSelect('system_id', 'system_id') ?>
                            <div style="align-self:end">
                                <button type="submit" class="btn btn-secondary">+ Add</button>
                            </div>
                        </div>
                    </form>

                    <!-- Bulk-add — pick a band ONCE, paste a list of names.
                         This is the right tool for the 50-200 colour ranges
                         common on metal venetians, verticals, etc. -->
                    <h3 style="margin:1.5rem 0 0.5rem;font-size:0.9375rem;color:var(--text-primary)">
                        Add many at once
                    </h3>
                    <form method="post" class="wiz-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_step" value="3">
                        <input type="hidden" name="_action" value="add_bulk">

                        <div class="row" style="grid-template-columns:8rem<?= count($systems) >= 2 ? ' 12rem' : '' ?> 1fr;align-items:start;gap:0.5rem">
                            <div>
                                <label for="bulk_band">Band *</label>
                                <input id="bulk_band" name="bulk_band" type="text"
                                       required maxlength="20"
                                       placeholder="A"
                                       style="text-transform:uppercase"
                                       value="">
                            </div>
                            <?= $renderSystemSelect('bulk_system_id', 'bulk_system_id') ?>
                            <div>
                                <label for="bulk_names">Names (one per line)</label>
                                <textarea id="bulk_names" name="bulk_names"
                                          rows="8"
                                          required
                                          placeholder="Plain White&#10;Plain Cream&#10;Plain Black&#10;Silver&#10;…"
                                          style="width:100%;font:inherit;padding:0.5rem 0.625rem;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-input);color:var(--text-body)"></textarea>
                            </div>
                        </div>
                        <div style="display:flex;gap:0.5rem;align-items:center;margin-top:0.5rem">
                            <button type="submit" class="btn btn-secondary">+ Add all</button>
                            <span style="color:var(--text-faint);font-size:0.8125rem">
                                Every line becomes a <?= e($labelL) ?> in the chosen band
                                <?php if (count($systems) >= 2): ?>and scope<?php endif; ?>.
                                Paste straight from a column in Excel / a supplier list.
                            </span>
                        </div>
                    </form>

                    <div class="helper" style="margin-top:1.25rem">
                        <strong>Tip:</strong> The full
                        <?= e($labelL) ?> editor on the edit page lets you
                        bulk-import from an XLSX and add supplier / colour /
                        code details too — useful when you already have a
                        spreadsheet.
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

            <!-- ─── STEP 4: Price tables ─────────────────────────────── -->
            <?php if ($step === 4 && $product):
                $totalTables  = count($priceTables);
                $filledTables = 0;
                foreach ($priceTables as $t) {
                    if ((int) $t['cell_count'] > 0) $filledTables++;
                }
                $allFilled = $totalTables > 0 && $filledTables === $totalTables;
            ?>
                <?php if ($allFilled): ?>
                    <div class="wiz-done-tile">
                        <div class="icon">🎉</div>
                        <h2>All set — <?= e((string) $product['name']) ?> is ready to quote</h2>
                        <p>
                            Product, <?= (int) $systemCount ?>
                            system<?= $systemCount === 1 ? '' : 's' ?>,
                            <?= (int) $fabricCount ?>
                            <?= strtolower((string) ($product['option_label'] ?? 'fabric')) ?><?= $fabricCount === 1 ? '' : 's' ?>,
                            and all <?= (int) $totalTables ?>
                            price table<?= $totalTables === 1 ? '' : 's' ?> filled in.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="wiz-done-tile" style="background:linear-gradient(135deg,#fef3c7 0%,#fffbeb 100%);border-color:#fcd34d">
                        <div class="icon">📋</div>
                        <h2 style="color:#78350f">One thing left — price tables</h2>
                        <p style="color:#92400e">
                            <?php if ($totalTables === 0): ?>
                                Your fabrics don't have band codes yet — go back
                                to step 3 and add at least one band (A, B, C…).
                                Price tables are keyed by band, so we need that
                                first.
                            <?php else: ?>
                                <?= $filledTables ?> of <?= $totalTables ?> filled in.
                                Click <em>Fill in</em> on each to type or paste
                                prices straight from a supplier sheet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($missingCombos)): ?>
                    <div class="wiz-card" style="background:#fffbeb;border-color:#fcd34d">
                        <h2 style="color:#78350f">
                            <?= count($missingCombos) ?>
                            missing (system × band)
                            combo<?= count($missingCombos) === 1 ? '' : 's' ?>
                        </h2>
                        <p class="lede" style="color:#92400e">
                            These combinations have no price table yet — they
                            won't generate a price at quote time.
                        </p>
                        <div class="wiz-list" style="background:transparent;border:0;padding:0;margin-bottom:0.875rem">
                            <?php foreach ($missingCombos as $m): ?>
                                <div class="wiz-list-item" style="border-bottom-color:#fde68a">
                                    <span class="check" style="color:#b45309">○</span>
                                    <strong><?= e((string) $m['system_name']) ?></strong>
                                    <span style="color:#92400e">— Band <?= e((string) $m['band_code']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form method="post" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_step" value="4">
                            <input type="hidden" name="_action" value="create_missing">
                            <button type="submit" class="btn btn-primary">
                                Create the missing tables
                            </button>
                            <span style="color:var(--text-faint);font-size:0.8125rem;margin-left:0.625rem">
                                Or leave them out if a combo doesn't apply to your range.
                            </span>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($totalTables > 0): ?>
                    <div class="wiz-card">
                        <h2>Price tables</h2>
                        <p class="lede">
                            One per <em>(system × band)</em>. Empty tables won't
                            generate a price at quote time — fill them in now,
                            or come back via the product edit page later.
                        </p>

                        <div class="wiz-list" style="max-height:none;padding:0.25rem 0.5rem">
                            <?php foreach ($priceTables as $t):
                                $cells   = (int) $t['cell_count'];
                                $filled  = $cells > 0;
                                $href    = '/admin/products/price-table.php?id=' . (int) $t['id']
                                         . '&from=wizard&product_id=' . (int) $productId;
                            ?>
                                <div class="wiz-list-item" style="padding:0.5rem 0.25rem">
                                    <span class="check" style="<?= $filled ? '' : 'color:var(--text-faint)' ?>">
                                        <?= $filled ? '&check;' : '○' ?>
                                    </span>
                                    <div style="flex:1;display:flex;flex-wrap:wrap;gap:0.375rem;align-items:baseline">
                                        <strong><?= e((string) $t['system_name']) ?></strong>
                                        <span style="color:var(--text-faint)">— Band <?= e((string) $t['band_code']) ?></span>
                                        <span style="color:var(--text-faint);font-size:0.8125rem">
                                            <?= $filled
                                                ? '· ' . $cells . ' cell' . ($cells === 1 ? '' : 's')
                                                : '· empty' ?>
                                        </span>
                                    </div>
                                    <a href="<?= e($href) ?>"
                                       class="btn <?= $filled ? 'btn-secondary' : 'btn-primary' ?> btn-sm">
                                        <?= $filled ? 'Edit' : 'Fill in' ?>
                                    </a>
                                    <form method="post" action="/admin/products/price-table-delete.php"
                                          style="margin:0;display:inline"
                                          data-confirm="Delete the <?= e((string) $t['system_name']) ?> &mdash; Band <?= e((string) $t['band_code']) ?> price table? <?= $filled ? 'Its ' . $cells . ' price cells will be wiped too.' : '' ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <input type="hidden" name="return_to"
                                               value="/admin/products/wizard.php?id=<?= (int) $productId ?>&amp;step=4">
                                        <button type="submit"
                                                style="background:transparent;border:0;color:#b91c1c;cursor:pointer;font-size:0.8125rem;text-decoration:underline">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="wiz-actions" style="margin-top:0">
                    <a href="/admin/products/wizard.php?id=<?= (int) $productId ?>&step=3"
                       class="wiz-skip" style="text-decoration:none;color:var(--text-faint)">
                        &larr; Back to fabrics
                    </a>
                    <a href="/admin/products/edit.php?id=<?= (int) $productId ?>"
                       class="btn <?= $allFilled ? 'btn-primary' : 'btn-secondary' ?>">
                        <?= $allFilled
                            ? 'Open product edit page →'
                            : 'Finish later — open product →' ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../../_partials/confirm_modal.php'; ?>
</body>
</html>
