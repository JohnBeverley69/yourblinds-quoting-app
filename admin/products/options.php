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

// Tenant-scoped product lookup. The label drives whether we say
// "Fabric" or "Slat type" throughout the page; show_colour_field
// drives whether the dedicated Colour sub-field renders next to
// the name. Defensive fallback for schemas where the
// migrate_show_colour_field.php hasn't run yet.
try {
    $pStmt = db()->prepare(
        'SELECT id, name, option_label, show_colour_field FROM products WHERE id = ? AND client_id = ?'
    );
    $pStmt->execute([$productId, $clientId]);
    $product = $pStmt->fetch();
} catch (Throwable $e) {
    $pStmt = db()->prepare(
        'SELECT id, name, option_label FROM products WHERE id = ? AND client_id = ?'
    );
    $pStmt->execute([$productId, $clientId]);
    $product = $pStmt->fetch();
}

if (!$product) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Product not found</h1>'
       . '<p><a href="/admin/products/index.php">Back to products</a></p>';
    exit;
}

// Per-product option label — admin sets it on the product Edit page
// ("Fabric" for rollers/romans, "Colour" for metal venetians,
// "Finish" for wood venetians, etc.). Falls back to "Fabric" for any
// product that hasn't had a label set.
$label  = (string) ($product['option_label'] ?? 'Fabric');
if ($label === '') $label = 'Fabric';
$labelL = strtolower($label);

// Per-product show_colour_field toggle drives whether the dedicated
// `colour` sub-field appears next to the name on this page. When 0,
// hide form input + table column; the name field IS the colour for
// those products. Defaults to 1 (show) on schemas where the
// migrate_show_colour_field.php migration hasn't been run yet.
$showColourField = !array_key_exists('show_colour_field', $product)
    || (int) $product['show_colour_field'] === 1;
$labelIsColour   = !$showColourField;

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Sticky form values — last submitted band/supplier carry over so a user
// adding 30 fabrics in band A doesn't have to retype "A" each time.
$lastBand     = (string) ($_SESSION['_options_last_band']     ?? '');
$lastSupplier = (string) ($_SESSION['_options_last_supplier'] ?? '');

// Normalize the sticky band against canonical bands defined on this
// product. The old behaviour uppercased band codes on save (so the
// session held "URBAN") while the price-tables rename / bulk-add path
// kept user-typed case ("Urban"). Result was the sticky pre-fill
// not matching any chip exactly. Look up the typed value
// case-insensitively against the union of product_options + price_tables
// and adopt the canonical case if there's a match.
if ($lastBand !== '') {
    $normStmt = db()->prepare(
        "SELECT band_code FROM (
            SELECT band_code FROM product_options
             WHERE product_id = ? AND client_id = ?
            UNION
            SELECT band_code FROM price_tables
             WHERE product_id = ? AND client_id = ?
         ) x
         WHERE LOWER(band_code) = LOWER(?)
         LIMIT 1"
    );
    $normStmt->execute([$productId, $clientId, $productId, $clientId, $lastBand]);
    $canon = $normStmt->fetchColumn();
    if ($canon !== false) $lastBand = (string) $canon;
}

$f = [
    'band_code'     => $lastBand,
    'supplier_name' => $lastSupplier,
    'name'          => '',
    'colour'        => '',
    'code'          => '',
    'sort_order'    => 0,
    'active'        => 1,
];
$error = null;

// ── Bulk-add: same pattern as the wizard's step 3. Pick a band code
//    (and optional system scope) once, paste a list of names, one per
//    line. Each line becomes a fabric in that band. Duplicates against
//    the uniq_option_per_product constraint are counted as skips so
//    one bad row doesn't fail the whole batch.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'add_bulk') {
    csrf_check();

    $band = trim((string) ($_POST['bulk_band'] ?? ''));
    $band = (string) preg_replace('/^band\s+/i', '', $band);
    $namesRaw = (string) ($_POST['bulk_names'] ?? '');
    $sysIdRaw = (string) ($_POST['bulk_system_id'] ?? '');
    $sysId    = ($sysIdRaw === '' || $sysIdRaw === '0') ? null : (int) $sysIdRaw;

    // Schema-aware: skip system_id write if the column doesn't exist
    // (tenant hasn't run migrate_option_system_scope.php yet).
    $hasSystemIdCol = false;
    try {
        $hasSystemIdCol = (bool) db()->query(
            "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'product_options'
                AND COLUMN_NAME  = 'system_id'"
        )->fetchColumn();
    } catch (Throwable $e) { /* keep false */ }

    if ($band === '') {
        $error = 'Band code is required.';
    } elseif (strlen($band) > 20) {
        $error = 'Band code too long (max 20).';
    } else {
        if ($sysId !== null && $hasSystemIdCol) {
            $check = db()->prepare(
                'SELECT 1 FROM product_systems
                  WHERE id = ? AND product_id = ? AND client_id = ?'
            );
            $check->execute([$sysId, $productId, $clientId]);
            if (!$check->fetchColumn()) {
                $error = 'Chosen system is not on this product.';
            }
        }

        if ($error === null) {
            $lines = preg_split('/\r\n|\r|\n/', $namesRaw) ?: [];
            $names = [];
            foreach ($lines as $line) {
                $name = trim($line);
                if ($name === '' || strlen($name) > 150) continue;
                $names[] = $name;
            }
            if (!$names) {
                $error = 'No names — paste at least one name into the box.';
            } else {
                $ins = $hasSystemIdCol
                    ? db()->prepare(
                        'INSERT INTO product_options
                          (client_id, product_id, system_id, band_code, name, sort_order, active)
                          VALUES (?, ?, ?, ?, ?, 0, 1)'
                    )
                    : db()->prepare(
                        'INSERT INTO product_options
                          (client_id, product_id, band_code, name, sort_order, active)
                          VALUES (?, ?, ?, ?, 0, 1)'
                    );
                $added   = 0;
                $skipped = 0;
                foreach ($names as $name) {
                    try {
                        if ($hasSystemIdCol) {
                            $ins->execute([$clientId, $productId, $sysId, $band, $name]);
                        } else {
                            $ins->execute([$clientId, $productId, $band, $name]);
                        }
                        $added++;
                    } catch (Throwable $e) {
                        $skipped++;
                    }
                }
                $msg = "Added $added to Band $band"
                     . ($sysId !== null ? ' (one system only)' : '')
                     . '.';
                if ($skipped > 0) $msg .= " Skipped $skipped (likely duplicates).";
                $_SESSION['flash_success'] = $msg;

                $returnTo = trim((string) ($_POST['return_to'] ?? ''));
                if ($returnTo !== ''
                    && $returnTo[0] === '/'
                    && !str_starts_with($returnTo, '//')
                    && !preg_match('#^/?\w+://#', $returnTo)) {
                    header('Location: ' . $returnTo);
                } else {
                    header('Location: /admin/products/options.php?product_id=' . $productId);
                }
                exit;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'create') {
    csrf_check();

    foreach (['band_code','supplier_name','name','colour','code'] as $k) {
        $f[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $f['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
    $f['active']     = !empty($_POST['active']) ? 1 : 0;

    // Strip "Band " prefix if the user typed it — store just "A" / "AAA" etc.
    $f['band_code'] = preg_replace('/^band\s+/i', '', $f['band_code']);

    if ($f['band_code'] === '') {
        $error = 'Band code is required (e.g. A, B, C).';
    } elseif (strlen($f['band_code']) > 20) {
        $error = 'Band code is too long (max 20 chars).';
    } elseif ($f['name'] === '') {
        $error = ucfirst($labelL) . ' name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = ucfirst($labelL) . ' name is too long (max 150 chars).';
    } else {
        try {
            $stmt = db()->prepare(
                'INSERT INTO product_options
                   (client_id, product_id, band_code, supplier_name,
                    name, colour, code, sort_order, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            // No strtoupper — let the typed case stick. MySQL's
            // case-insensitive collation handles lookups, and the
            // price-tables UI keeps user case too, so this avoids
            // the "URBAN vs Urban" mismatch in the band chips.
            $stmt->execute([
                $clientId,
                $productId,
                $f['band_code'],
                $f['supplier_name'] !== '' ? $f['supplier_name'] : null,
                $f['name'],
                $f['colour'] !== '' ? $f['colour'] : null,
                $f['code']   !== '' ? $f['code']   : null,
                $f['sort_order'],
                $f['active'],
            ]);
            $newFabricId = (int) db()->lastInsertId();
            $_SESSION['_options_last_band']     = $f['band_code'];
            $_SESSION['_options_last_supplier'] = $f['supplier_name'];

            // Audit
            require_once __DIR__ . '/../../_partials/catalogue_audit.php';
            catalogue_audit_log(
                'fabric', $newFabricId, 'create',
                $f['name'],
                null,
                [
                    'band_code'     => $f['band_code'],
                    'supplier_name' => $f['supplier_name'],
                    'name'          => $f['name'],
                    'colour'        => $f['colour'],
                    'code'          => $f['code'],
                ],
                $productId
            );

            $_SESSION['flash_success'] = ucfirst($labelL) . ' "' . $f['name'] . '" added.';
            // Same return_to handling as systems.php — lets the inline
            // quick-add on the product edit page bounce straight back.
            $returnTo = trim((string) ($_POST['return_to'] ?? ''));
            if ($returnTo !== ''
                && $returnTo[0] === '/'
                && !str_starts_with($returnTo, '//')
                && !preg_match('#^/?\w+://#', $returnTo)) {
                header('Location: ' . $returnTo);
            } else {
                header('Location: /admin/products/options.php?product_id=' . $productId);
            }
            exit;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'uniq_option_per_product')) {
                $error = 'A ' . $labelL . ' with that name + colour already exists for this product.';
            } else {
                $error = 'Could not add: ' . $e->getMessage();
            }
        }
    }
}

// product_options.system_id is optional (migrate_option_system_scope.php).
// When present, each fabric can be pinned to a system — surfaced as a
// "System" column so the admin can see at a glance which fabrics are
// universal vs scoped.
$hasSystemIdCol = false;
try {
    $hasSystemIdCol = (bool) db()->query(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'product_options'
            AND COLUMN_NAME  = 'system_id'"
    )->fetchColumn();
} catch (Throwable $e) { /* keep false */ }

// List existing options. Custom band sort: AAA → AA → A → B → C → ...
// (premium "A" tiers in descending length, then alphabetical for the rest).
$rows = db()->prepare(
    "SELECT id, band_code, supplier_name, name, colour, code, sort_order, active"
    . ($hasSystemIdCol ? ', system_id' : '') . "
       FROM product_options
      WHERE product_id = ? AND client_id = ?
   ORDER BY
        CASE
            WHEN band_code = 'AAA' THEN 1
            WHEN band_code = 'AA'  THEN 2
            WHEN band_code = 'A'   THEN 3
            ELSE 100
        END,
        band_code,
        sort_order, name, colour"
);
$rows->execute([$productId, $clientId]);
$options = $rows->fetchAll();

// Distinct band codes already in use on this product (across both
// fabrics and price tables) — used to populate the <datalist>
// autocomplete on the band input so the user can pick rather than
// retype. Typing a new value still works for the first band on a
// fresh product.
// No active filter — deactivated tables / fabrics still represent
// a band the tenant has defined; gating them out of autocomplete
// would silently swallow bands the user expects to see.
$knownBandsStmt = db()->prepare(
    "SELECT DISTINCT band_code FROM (
        SELECT band_code FROM product_options
         WHERE product_id = ? AND client_id = ?
        UNION
        SELECT band_code FROM price_tables
         WHERE product_id = ? AND client_id = ?
     ) x
     WHERE band_code IS NOT NULL AND band_code != ''
     ORDER BY band_code"
);
$knownBandsStmt->execute([$productId, $clientId, $productId, $clientId]);
$knownBands = array_map(
    static fn ($v) => (string) $v,
    $knownBandsStmt->fetchAll(PDO::FETCH_COLUMN)
);

// Systems on this product — used by the bulk-add form so the admin
// can optionally scope a paste to one system (e.g. all 12 Polaris
// colours go on rollers only, not on verticals). Returns [] when
// the product has no systems defined yet, in which case the form
// hides the system dropdown.
$systemsStmt = db()->prepare(
    'SELECT id, name FROM product_systems
      WHERE product_id = ? AND client_id = ?
      ORDER BY sort_order, name'
);
try {
    $systemsStmt->execute([$productId, $clientId]);
    $systems = $systemsStmt->fetchAll();
} catch (Throwable $e) {
    // sort_order column may not exist on older schemas; fall back.
    $systemsStmt = db()->prepare(
        'SELECT id, name FROM product_systems
          WHERE product_id = ? AND client_id = ? ORDER BY name'
    );
    $systemsStmt->execute([$productId, $clientId]);
    $systems = $systemsStmt->fetchAll();
}

// Map system_id → name for the list's System column, plus a flag for
// whether to show the column at all (only when scoping exists and the
// product actually has systems).
$systemNameById = [];
foreach ($systems as $s) {
    $systemNameById[(int) $s['id']] = (string) $s['name'];
}
$showSystemCol = $hasSystemIdCol && !empty($systems);

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $product['name']) ?> &middot; <?= e($label) ?>s &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .form-row.cols-5 { grid-template-columns: 1fr 1fr 2fr 1fr 0.75fr; }
        @media (max-width: 800px) {
            .form-row.cols-5 { grid-template-columns: 1fr; }
        }
        .form-group input[type="number"] {
            width: 100%; font: inherit; padding: 0.5625rem 0.75rem;
            border: 1px solid var(--border-strong); border-radius: 8px; background: #fff;
        }
        .checkbox-row {
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-size: 0.9375rem; color: var(--text-primary); cursor: pointer; margin-right: 0.75rem;
        }
        .checkbox-row input { width: 18px; height: 18px; }
        .band-pill {
            display: inline-block; text-align: center;
            padding: 0.125rem 0.625rem; font-weight: 700; font-size: 0.8125rem;
            color: #fff; background: #1f3b5b; border-radius: 6px;
            white-space: nowrap;
        }
        .band-chips {
            display: flex; flex-wrap: wrap; align-items: center;
            gap: 0.375rem; margin-bottom: 0.875rem;
            padding: 0.5rem 0.75rem; background: var(--bg-subtle, #f6f8fb);
            border: 1px dashed var(--border-strong, #cbd5e1); border-radius: 8px;
        }
        .band-chips__label {
            font-size: 0.8125rem; font-weight: 600; color: var(--text-faint, #475569);
            margin-right: 0.25rem;
        }
        .band-chip {
            font: inherit; font-size: 0.8125rem; font-weight: 600;
            padding: 0.1875rem 0.625rem;
            color: #1f3b5b; background: #fff;
            border: 1px solid #cbd5e1; border-radius: 999px;
            cursor: pointer; transition: background 0.1s, border-color 0.1s;
        }
        .band-chip:hover { background: #1f3b5b; color: #fff; border-color: #1f3b5b; }
        .band-chip.is-active { background: #1f3b5b; color: #fff; border-color: #1f3b5b; }
        .row-actions { white-space: nowrap; }
        .row-actions a { font-size: 0.875rem; margin-left: 0.5rem; }
        .row-actions form { display: inline; margin: 0; }
        .row-actions button {
            font-size: 0.875rem; color: #b91c1c; background: transparent;
            border: 0; cursor: pointer; padding: 0; margin-left: 0.5rem;
        }
        .row-actions button:hover { text-decoration: underline; }
        .inactive-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 600; color: var(--text-faint); background: var(--bg-subtle-2);
            border-radius: 999px; margin-left: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .bulk-bar {
            display: flex; align-items: center; gap: 0.75rem;
            margin-bottom: 0.75rem; flex-wrap: wrap;
        }
        .bulk-bar .selected-count { font-size: 0.875rem; color: var(--text-faint); }
        .row-check { width: 1%; text-align: center; }
        .row-check input { width: 18px; height: 18px; cursor: pointer; }
        /* Filter search above the table — finds fabrics by any field
           (name, colour, supplier, band, code). Multi-word ANDs:
           "polaris cream" returns only Polaris fabrics in Cream. */
        .fabric-search-bar {
            display: flex; gap: 0.625rem; align-items: center;
            margin-bottom: 0.75rem; flex-wrap: wrap;
        }
        .fabric-search-bar input {
            flex: 1 1 18rem; max-width: 28rem;
            padding: 0.4375rem 0.6875rem;
            border: 1px solid var(--border-strong); border-radius: 8px;
            font: inherit; font-size: 0.9375rem;
        }
        .fabric-search-bar .clear-btn {
            background: transparent; border: 0; cursor: pointer;
            color: var(--text-faint); font-size: 0.8125rem; text-decoration: underline;
        }
        .fabric-search-bar .clear-btn:hover { color: #1f3b5b; }
        .fabric-count { font-size: 0.875rem; color: var(--text-faint); }
        tr.is-hidden { display: none; }
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
                        [$label . 's',                null],
                    ]);
                ?>
                <h1 class="page-title">
                    <?= e((string) $product['name']) ?> &mdash; <?= e($label) ?>s
                </h1>
                <p class="page-subtitle">
                    <a href="/admin/products/edit.php?id=<?= (int) $productId ?>">Edit product</a>
                </p>
            </div>
            <a href="/admin/products/options-import.php?product_id=<?= (int) $productId ?>"
               class="btn btn-secondary">Import from Excel</a>
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
            <p style="margin:0 0 0.625rem;color:#0c4a6e;font-size:0.9375rem;line-height:1.5">
                <strong><?= e($label) ?>s</strong> are what the customer picks for the
                blind &mdash; the actual material / colour / slat type they want.
                Each one belongs to a <strong>band</strong> &mdash; a letter code
                (A, B, C&hellip;) you assign by supplier price tier.
            </p>
            <p style="margin:0;color:#0c4a6e;font-size:0.875rem;line-height:1.5">
                <strong>About bands:</strong> a band groups <?= e($labelL) ?>s
                that all cost the same per blind size, so they share <em>one</em>
                price table instead of needing one each.
                E.g. give all your basic plain fabrics band <em>A</em>, all premium
                textured ones band <em>B</em>, etc. The price tables (next page)
                are keyed by band.
            </p>
        </section>

        <!-- Bulk-add — paste a list of names, all go in under one band.
             Same pattern as the wizard step 3. Open by default so the
             admin sees it without having to discover a collapsed
             control. The single-add form below stays available for
             one-off entries. -->
        <section class="section">
            <details open>
                <summary style="cursor:pointer;font-weight:600;font-size:1rem;color:var(--text-primary);padding:0.25rem 0;">
                    Bulk add <?= e($labelL) ?>s &mdash; paste a list
                </summary>
                <p style="margin:0.5rem 0 0.875rem;font-size:0.875rem;color:var(--text-faint)">
                    One name per line. They all go in under the same band
                    <?= $systems ? '(and optionally one system)' : '' ?>.
                    Duplicates are skipped silently.
                </p>
                <form method="post" action="/admin/products/options.php?product_id=<?= (int) $productId ?>"
                      class="form" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="add_bulk">

                    <?php if ($knownBands): ?>
                        <div class="band-chips" aria-label="Known bands">
                            <span class="band-chips__label">Bands:</span>
                            <?php foreach ($knownBands as $b): ?>
                                <button type="button" class="band-chip" data-fill-bulk="<?= e($b) ?>"><?= e($b) ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-row <?= $systems ? 'cols-2' : '' ?>">
                        <div class="form-group">
                            <label for="bulk_band">Band <span class="required">*</span></label>
                            <input id="bulk_band" name="bulk_band" type="text"
                                   required maxlength="20"
                                   list="known-bands"
                                   value="<?= e((string) $lastBand) ?>" placeholder="A">
                        </div>
                        <?php if ($systems): ?>
                            <div class="form-group">
                                <label for="bulk_system_id">System (optional)</label>
                                <select id="bulk_system_id" name="bulk_system_id">
                                    <option value="">All systems on this product</option>
                                    <?php foreach ($systems as $s): ?>
                                        <option value="<?= (int) $s['id'] ?>"><?= e((string) $s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="bulk_names"><?= e(ucfirst($labelL)) ?> names &mdash; one per line <span class="required">*</span></label>
                        <textarea id="bulk_names" name="bulk_names" rows="10"
                                  style="width:100%;font:inherit;padding:0.5625rem 0.75rem;border:1px solid var(--border-strong);border-radius:8px;background:#fff;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:0.9375rem"
                                  placeholder="<?= e($labelL === 'slat' ? "Cream\nWalnut\nOak\nWhite Gloss" : "Cream\nStone\nBlack\nPolaris White") ?>"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Add all</button>
                    </div>
                </form>
            </details>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Add <?= e($labelL) ?> (one at a time)</h2>
            </div>
            <form method="post" action="/admin/products/options.php?product_id=<?= (int) $productId ?>"
                  class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="create">

                <?php if ($knownBands): ?>
                    <!-- Autocomplete options for the Band input below.
                         Picked from bands already in use on this
                         product (fabrics + price tables). Typing a
                         new value still works for the first band on
                         a fresh product. -->
                    <datalist id="known-bands">
                        <?php foreach ($knownBands as $b): ?>
                            <option value="<?= e($b) ?>">
                        <?php endforeach; ?>
                    </datalist>

                    <!-- Visible chip row, click-to-fill. The datalist
                         above filters its options by what's already
                         typed in the input, which means a sticky
                         value (e.g. last band used) hides the rest.
                         This row stays visible regardless and gives
                         the admin a clear at-a-glance view of every
                         band defined for this product. -->
                    <div class="band-chips" aria-label="Known bands for this product">
                        <span class="band-chips__label">Bands:</span>
                        <?php foreach ($knownBands as $b): ?>
                            <button type="button" class="band-chip"
                                    data-fill="<?= e($b) ?>"><?= e($b) ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="form-row <?= $labelIsColour ? 'cols-4' : 'cols-5' ?>">
                    <div class="form-group">
                        <label for="band_code">Band <span class="required">*</span></label>
                        <input id="band_code" name="band_code" type="text"
                               required maxlength="20" autofocus
                               list="known-bands"
                               value="<?= e((string) $f['band_code']) ?>" placeholder="A">
                    </div>
                    <div class="form-group">
                        <label for="name"><?= e($label) ?> name <span class="required">*</span></label>
                        <input id="name" name="name" type="text" required maxlength="150"
                               value="<?= e((string) $f['name']) ?>"
                               placeholder="<?= $label === 'Slat type' ? 'e.g. 25mm Faux Wood' : 'e.g. Cream Slats' ?>">
                    </div>
                    <?php if (!$labelIsColour): ?>
                        <div class="form-group">
                            <label for="colour">Colour</label>
                            <input id="colour" name="colour" type="text" maxlength="150"
                                   value="<?= e((string) $f['colour']) ?>">
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="supplier_name">Supplier</label>
                        <input id="supplier_name" name="supplier_name" type="text" maxlength="150"
                               value="<?= e((string) $f['supplier_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="code">Code</label>
                        <input id="code" name="code" type="text" maxlength="50"
                               value="<?= e((string) $f['code']) ?>">
                    </div>
                </div>

                <label class="checkbox-row" for="active">
                    <input type="checkbox" id="active" name="active" value="1" checked>
                    Active
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add <?= e($labelL) ?></button>
                </div>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title"><?= e($label) ?>s (<?= count($options) ?>)</h2>
            </div>

            <?php if (!$options): ?>
                <div class="placeholder">
                    <p class="placeholder-title">No <?= e($labelL) ?>s yet</p>
                    <p class="placeholder-body">
                        Use the form above to add your first <?= e($labelL) ?> for this product.
                    </p>
                </div>
            <?php else: ?>
                <!--
                    Filter search — client-side because all rows are
                    already on the page. Whitespace-separated terms ALL
                    have to appear in the row's combined text (name +
                    colour + supplier + band + code), so "polaris cream"
                    narrows to just Polaris in Cream. Survives sort
                    order and lives on across keystrokes without a
                    server round-trip.
                -->
                <div class="fabric-search-bar">
                    <input type="search" id="fabric-search"
                           placeholder="Filter (e.g. polaris cream)…"
                           autocomplete="off">
                    <span class="fabric-count" id="fabric-count">
                        <?= count($options) ?>
                        <?= count($options) === 1 ? $labelL : $labelL . 's' ?>
                    </span>
                    <button type="button" id="fabric-search-clear"
                            class="clear-btn" hidden>Clear</button>
                </div>

                <div class="bulk-bar">
                    <button type="button" id="bulk-delete-btn"
                            class="btn btn-secondary btn-sm" disabled>
                        Delete selected
                    </button>
                    <span class="selected-count" id="bulk-count">No rows selected</span>
                </div>
                <form id="bulk-form" method="post" action="/admin/products/option-delete.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="row-check">
                                        <input type="checkbox" id="check-all"
                                               aria-label="Select all">
                                    </th>
                                    <th>Band</th>
                                    <?php if ($showSystemCol): ?>
                                        <th>System</th>
                                    <?php endif; ?>
                                    <th><?= e($label) ?></th>
                                    <?php if (!$labelIsColour): ?>
                                        <th>Colour</th>
                                    <?php endif; ?>
                                    <th>Supplier</th>
                                    <th>Code</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($options as $o):
                                    // Pre-built lower-case search blob so the JS
                                    // filter doesn't have to walk td.textContent
                                    // every keystroke. Includes every field the
                                    // user might type to find a row.
                                    // Resolve this fabric's system scope for the
                                    // System column + the search blob (so typing a
                                    // system name filters too). NULL = all systems.
                                    $rowSysId   = ($showSystemCol && isset($o['system_id']) && $o['system_id'] !== null)
                                                ? (int) $o['system_id'] : null;
                                    $rowSysName = $rowSysId === null
                                                ? 'All systems'
                                                : ($systemNameById[$rowSysId] ?? ('#' . $rowSysId));
                                    $searchBlob = strtolower(implode(' ', array_filter([
                                        (string) ($o['band_code']     ?? ''),
                                        (string) ($o['name']          ?? ''),
                                        (string) ($o['colour']        ?? ''),
                                        (string) ($o['supplier_name'] ?? ''),
                                        (string) ($o['code']          ?? ''),
                                        $showSystemCol ? $rowSysName : '',
                                    ])));
                                ?>
                                    <tr data-search="<?= e($searchBlob) ?>">
                                        <td class="row-check">
                                            <input type="checkbox" class="row-checkbox"
                                                   name="ids[]" value="<?= (int) $o['id'] ?>"
                                                   aria-label="Select <?= e((string) $o['name']) ?>">
                                        </td>
                                        <td><span class="band-pill">Band <?= e((string) $o['band_code']) ?></span></td>
                                        <?php if ($showSystemCol): ?>
                                            <td>
                                                <?php if ($rowSysId === null): ?>
                                                    <span style="color:#6b7280;font-size:0.8125rem">All systems</span>
                                                <?php else: ?>
                                                    <?= e($rowSysName) ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <?= e((string) $o['name']) ?>
                                            <?php if ((int) $o['active'] !== 1): ?>
                                                <span class="inactive-pill">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if (!$labelIsColour): ?>
                                            <td><?= e((string) ($o['colour'] ?? '')) ?></td>
                                        <?php endif; ?>
                                        <td><?= e((string) ($o['supplier_name'] ?? '')) ?></td>
                                        <td><?= e((string) ($o['code'] ?? '')) ?></td>
                                        <td class="row-actions">
                                            <a href="/admin/products/option-edit.php?id=<?= (int) $o['id'] ?>">Edit</a>
                                            <button type="button" class="row-delete"
                                                    data-id="<?= (int) $o['id'] ?>"
                                                    data-name="<?= e((string) $o['name']) ?>"
                                                    style="font-size:0.875rem;color:#b91c1c;background:transparent;border:0;cursor:pointer;padding:0;margin-left:0.5rem;">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
(function () {
    var form = document.getElementById('bulk-form');
    if (!form) return;
    var checkAll = document.getElementById('check-all');
    var rowBoxes = form.querySelectorAll('.row-checkbox');
    var allRows  = form.querySelectorAll('tbody tr[data-search]');
    var btn      = document.getElementById('bulk-delete-btn');
    var counter  = document.getElementById('bulk-count');

    // ── Filter search ───────────────────────────────────────────
    //
    // Client-side, runs on every keystroke. Whitespace-separated
    // words ALL have to appear in the row's data-search blob, so
    // "polaris cream" returns only Polaris fabrics in cream
    // (mirrors the preview drawer's typeahead semantic).
    //
    // Hidden rows still exist in the DOM — bulk-delete / select-all
    // simply ignore them, so an operator filtering to "polaris bo"
    // and clicking "Select all" only selects what's visible.
    var searchInput = document.getElementById('fabric-search');
    var clearBtn    = document.getElementById('fabric-search-clear');
    var countEl     = document.getElementById('fabric-count');
    var origCountText = countEl ? countEl.textContent.trim() : '';

    function visibleRows() {
        var out = [];
        allRows.forEach(function (tr) {
            if (!tr.classList.contains('is-hidden')) out.push(tr);
        });
        return out;
    }
    function visibleRowBoxes() {
        return visibleRows()
            .map(function (tr) { return tr.querySelector('.row-checkbox'); })
            .filter(Boolean);
    }

    function applyFilter() {
        if (!searchInput) return;
        var q = (searchInput.value || '').trim().toLowerCase();
        var words = q ? q.split(/\s+/) : [];
        var visible = 0;
        allRows.forEach(function (tr) {
            var hay = tr.dataset.search || '';
            var matches = words.every(function (w) { return hay.indexOf(w) !== -1; });
            tr.classList.toggle('is-hidden', !matches);
            // Uncheck rows that just got hidden so they don't sneak
            // into a bulk-delete.
            if (!matches) {
                var cb = tr.querySelector('.row-checkbox');
                if (cb) cb.checked = false;
            }
            if (matches) visible++;
        });
        if (countEl) {
            countEl.textContent = q
                ? 'Showing ' + visible + ' of ' + allRows.length
                : origCountText;
        }
        if (clearBtn) clearBtn.hidden = (q === '');
        refresh();
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilter);
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            applyFilter();
            searchInput.focus();
        });
    }

    // ── Bulk delete (filter-aware) ──────────────────────────────
    function checkedIds() {
        var ids = [];
        visibleRowBoxes().forEach(function (cb) {
            if (cb.checked) ids.push(cb.value);
        });
        return ids;
    }

    function refresh() {
        var ids = checkedIds();
        var n   = ids.length;
        var visBoxes = visibleRowBoxes();
        btn.disabled = n === 0;
        if (n === 0) {
            counter.textContent = 'No rows selected';
        } else {
            counter.textContent = n + ' row' + (n === 1 ? '' : 's') + ' selected';
        }
        if (checkAll) {
            checkAll.checked       = (n > 0 && n === visBoxes.length);
            checkAll.indeterminate = (n > 0 && n < visBoxes.length);
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            visibleRowBoxes().forEach(function (cb) { cb.checked = checkAll.checked; });
            refresh();
        });
    }
    rowBoxes.forEach(function (cb) { cb.addEventListener('change', refresh); });

    btn.addEventListener('click', function () {
        var n = checkedIds().length;
        if (n === 0) return;
        if (confirm('Delete ' + n + ' selected row' + (n === 1 ? '' : 's') + '? This cannot be undone.')) {
            form.submit();
        }
    });

    // Per-row Delete buttons reuse the same bulk form: clear all checkboxes,
    // tick just the target, confirm, submit.
    document.querySelectorAll('.row-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id   = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name');
            if (!confirm('Delete ' + name + '?')) return;
            rowBoxes.forEach(function (cb) { cb.checked = (cb.value === id); });
            form.submit();
        });
    });

    refresh();
})();

// ── Known-band chips: click to fill the matching band input ─────
// Two chip rows on this page: one above the single-add form
// (data-fill → #band_code) and one above the bulk-add form
// (data-fill-bulk → #bulk_band). Same datalist filter quirk
// applies to both — sticky pre-fills can hide options — so each
// row is independently click-to-fill with active-chip mirroring.
(function () {
    function wire(chipAttr, inputId, nextId) {
        var input = document.getElementById(inputId);
        var chips = document.querySelectorAll('[' + chipAttr + ']');
        if (!input || !chips.length) return;

        function syncActive() {
            var v = (input.value || '').trim().toLowerCase();
            chips.forEach(function (c) {
                var match = (c.getAttribute(chipAttr) || '').toLowerCase() === v;
                c.classList.toggle('is-active', match);
            });
        }

        chips.forEach(function (c) {
            c.addEventListener('click', function () {
                input.value = c.getAttribute(chipAttr) || '';
                syncActive();
                var next = nextId ? document.getElementById(nextId) : null;
                if (next) next.focus();
            });
        });

        input.addEventListener('input', syncActive);
        syncActive();
    }

    wire('data-fill',      'band_code', 'name');
    wire('data-fill-bulk', 'bulk_band', 'bulk_names');
})();
</script>
</body>
</html>
