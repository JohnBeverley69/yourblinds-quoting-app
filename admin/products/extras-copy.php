<?php
declare(strict_types=1);

/**
 * Copy OPTIONS (product_extras) from one product to another.
 *
 * The catalogue has per-product options that are often near-identical
 * across related products (e.g. a "headrail only" line shares the
 * vertical blind's operation / control-side / bracket options). There
 * was no cross-product copy — only same-product duplicate
 * (extra-duplicate.php). This fills that gap.
 *
 * The tease: options aren't a flat row copy. Each choice can be scoped
 * to a SYSTEM, and system ids are product-specific — the target product
 * has its own product_systems rows. So every choice's system_id is
 * re-pointed to the target's same-NAMED system; a source system with no
 * name-match in the target falls back to universal (NULL) and is
 * reported. Option gating (product_extra_parent_choices) is re-linked to
 * the freshly-created choices; any gate whose parent choice wasn't part
 * of the copied set is dropped and reported.
 *
 * Two-stage UI (Post/Redirect/Get on success):
 *   1. ?product_id=TARGET            → pick a source product
 *   2. &source_id=SOURCE             → tick which options to copy
 *   3. POST action=copy              → clone + remap, redirect to extras.php
 *
 * Admin-gated, tenant-scoped via client_id.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();

$productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$sourceId  = (int) ($_GET['source_id']  ?? $_POST['source_id']  ?? 0);

// ── Target product (tenant scope) ─────────────────────────────────────
$tStmt = $pdo->prepare(
    'SELECT id, name, option_label FROM products WHERE id = ? AND client_id = ?'
);
$tStmt->execute([$productId, $clientId]);
$target = $tStmt->fetch();
if (!$target) {
    header('Location: /admin/products/index.php');
    exit;
}
$redirect = '/admin/products/extras.php?product_id=' . $productId;

// ── Other products that could be a copy source ────────────────────────
$pickStmt = $pdo->prepare(
    'SELECT p.id, p.name,
            (SELECT COUNT(*) FROM product_extras e
              WHERE e.product_id = p.id AND e.client_id = p.client_id AND e.active = 1) AS opt_count
       FROM products p
      WHERE p.client_id = ? AND p.id <> ? AND p.active = 1
      ORDER BY p.name'
);
$pickStmt->execute([$clientId, $productId]);
$sourceChoices = $pickStmt->fetchAll();

// ── Source product + its options (when chosen) ────────────────────────
$source        = null;
$sourceOptions = [];
if ($sourceId > 0) {
    $sStmt = $pdo->prepare('SELECT id, name FROM products WHERE id = ? AND client_id = ?');
    $sStmt->execute([$sourceId, $clientId]);
    $source = $sStmt->fetch() ?: null;
    if ($source) {
        $oStmt = $pdo->prepare(
            'SELECT e.id, e.name, e.is_required,
                    (SELECT COUNT(*) FROM product_extra_choices c
                      WHERE c.product_extra_id = e.id AND c.active = 1) AS choice_count
               FROM product_extras e
              WHERE e.product_id = ? AND e.client_id = ? AND e.active = 1
              ORDER BY e.sort_order, e.name'
        );
        $oStmt->execute([$sourceId, $clientId]);
        $sourceOptions = $oStmt->fetchAll();
    }
}

$flashErr = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
$error = null;

// ── Generic "copy all columns except these" helper (same as
//    extra-duplicate.php) ───────────────────────────────────────────────
$copyRow = static function (array $row, array $skip, array $override): array {
    $cols = [];
    $vals = [];
    foreach ($row as $col => $val) {
        if (isset($skip[$col])) continue;
        if (array_key_exists($col, $override)) $val = $override[$col];
        $cols[] = '`' . $col . '`';
        $vals[] = $val;
    }
    return [$cols, $vals];
};

// ── POST: do the copy ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'copy') {
    csrf_check();

    if (!$source) {
        $error = 'Pick a product to copy from.';
    } else {
        $wanted = array_map('intval', (array) ($_POST['extra_ids'] ?? []));
        $wanted = array_values(array_filter($wanted, static fn ($x) => $x > 0));
        if (!$wanted) {
            $error = 'Tick at least one option to copy.';
        }
    }

    if ($error === null) {
        // System-name → target system id (case-insensitive). NULL-safe.
        $tSys = $pdo->prepare(
            'SELECT id, name FROM product_systems
              WHERE product_id = ? AND client_id = ? AND active = 1'
        );
        $tSys->execute([$productId, $clientId]);
        $targetSysByName = [];
        foreach ($tSys->fetchAll() as $s) {
            $targetSysByName[mb_strtolower(trim((string) $s['name']))] = (int) $s['id'];
        }

        // Source system id → name (so we know what each choice was scoped to).
        $sSys = $pdo->prepare(
            'SELECT id, name FROM product_systems
              WHERE product_id = ? AND client_id = ? AND active = 1'
        );
        $sSys->execute([$sourceId, $clientId]);
        $sourceSysName = [];
        foreach ($sSys->fetchAll() as $s) {
            $sourceSysName[(int) $s['id']] = (string) $s['name'];
        }

        // Remap a source system_id to a target system_id by name.
        // Returns [newId|null, wasMadeUniversal].
        $remapSystem = static function ($srcSysId) use ($sourceSysName, $targetSysByName): array {
            if ($srcSysId === null) return [null, false];          // already universal
            $srcSysId = (int) $srcSysId;
            $name = $sourceSysName[$srcSysId] ?? null;
            if ($name === null) return [null, true];               // orphaned scope → universal
            $key = mb_strtolower(trim($name));
            if (isset($targetSysByName[$key])) return [$targetSysByName[$key], false];
            return [null, true];                                   // no same-named system → universal
        };

        // Names already present on the target (case-insensitive) — skip
        // these so a re-run is safe and can't collide.
        $exNames = $pdo->prepare(
            'SELECT name FROM product_extras
              WHERE product_id = ? AND client_id = ? AND active = 1'
        );
        $exNames->execute([$productId, $clientId]);
        $existingNames = [];
        foreach ($exNames->fetchAll(PDO::FETCH_COLUMN) as $n) {
            $existingNames[mb_strtolower(trim((string) $n))] = true;
        }

        $copiedOptions  = 0;
        $copiedChoices  = 0;
        $madeUniversal  = 0;
        $droppedGates   = 0;
        $skippedExisting = 0;

        $pdo->beginTransaction();
        try {
            // Append after the target's existing options.
            $sortSt = $pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_extras
                  WHERE product_id = ? AND client_id = ?'
            );
            $sortSt->execute([$productId, $clientId]);
            $nextSort = (int) $sortSt->fetchColumn();

            $choiceIdMap = [];   // old choice id  → new choice id  (across ALL copied options)
            $copiedExtras = [];  // old extra id   → new extra id

            foreach ($wanted as $oldExtraId) {
                // Re-load + tenant/product verify each source option.
                $exSt = $pdo->prepare(
                    'SELECT * FROM product_extras
                      WHERE id = ? AND product_id = ? AND client_id = ? AND active = 1
                      LIMIT 1'
                );
                $exSt->execute([$oldExtraId, $sourceId, $clientId]);
                $extra = $exSt->fetch(PDO::FETCH_ASSOC);
                if (!$extra) continue;

                // Skip if the target already has an option with this name.
                $nameKey = mb_strtolower(trim((string) $extra['name']));
                if (isset($existingNames[$nameKey])) {
                    $skippedExisting++;
                    continue;
                }
                $existingNames[$nameKey] = true;   // guard against in-batch dupes

                // Clone the option row into the target. parent_choice_id
                // (legacy column, if present) is NULLed — gating is re-
                // linked via the junction afterwards.
                [$eCols, $eVals] = $copyRow(
                    $extra,
                    ['id' => true, 'created_at' => true, 'updated_at' => true],
                    ['product_id' => $productId, 'sort_order' => $nextSort++,
                     'parent_choice_id' => null]
                );
                $pdo->prepare(
                    'INSERT INTO product_extras (' . implode(',', $eCols) . ') VALUES ('
                    . implode(',', array_fill(0, count($eCols), '?')) . ')'
                )->execute($eVals);
                $newExtraId = (int) $pdo->lastInsertId();
                $copiedExtras[$oldExtraId] = $newExtraId;
                $copiedOptions++;

                // Choices, with system remap.
                $chSt = $pdo->prepare(
                    'SELECT * FROM product_extra_choices
                      WHERE product_extra_id = ? ORDER BY sort_order, id'
                );
                $chSt->execute([$oldExtraId]);
                foreach ($chSt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                    [$newSysId, $wasUniversal] = $remapSystem($c['system_id'] ?? null);
                    if ($wasUniversal) $madeUniversal++;

                    [$cCols, $cVals] = $copyRow(
                        $c,
                        ['id' => true, 'created_at' => true, 'updated_at' => true],
                        ['product_extra_id' => $newExtraId, 'system_id' => $newSysId]
                    );
                    $pdo->prepare(
                        'INSERT INTO product_extra_choices (' . implode(',', $cCols) . ') VALUES ('
                        . implode(',', array_fill(0, count($cCols), '?')) . ')'
                    )->execute($cVals);
                    $newCid = (int) $pdo->lastInsertId();
                    $choiceIdMap[(int) $c['id']] = $newCid;
                    $copiedChoices++;

                    // Per-choice band scoping (band_code strings copy as-is).
                    try {
                        $bs = $pdo->prepare(
                            'SELECT band_code FROM product_extra_choice_bands WHERE choice_id = ?'
                        );
                        $bs->execute([(int) $c['id']]);
                        $bands = $bs->fetchAll(PDO::FETCH_COLUMN);
                        if ($bands) {
                            $bIns = $pdo->prepare(
                                'INSERT INTO product_extra_choice_bands (choice_id, band_code) VALUES (?, ?)'
                            );
                            foreach ($bands as $bc) $bIns->execute([$newCid, (string) $bc]);
                        }
                    } catch (Throwable $e) { /* optional table — skip */ }

                    // Per-choice width-table pricing.
                    try {
                        $ws = $pdo->prepare(
                            'SELECT width_mm, price FROM extra_choice_price_rows
                              WHERE product_extra_choice_id = ?'
                        );
                        $ws->execute([(int) $c['id']]);
                        $rows = $ws->fetchAll(PDO::FETCH_ASSOC);
                        if ($rows) {
                            $wIns = $pdo->prepare(
                                'INSERT INTO extra_choice_price_rows
                                   (product_extra_choice_id, width_mm, price) VALUES (?, ?, ?)'
                            );
                            foreach ($rows as $w) {
                                $wIns->execute([$newCid, (int) $w['width_mm'], $w['price']]);
                            }
                        }
                    } catch (Throwable $e) { /* skip */ }
                }
            }

            // Re-link option gating. A gate is kept only if its parent
            // choice was part of the copied set (otherwise it'd point at a
            // choice in the source product — meaningless here).
            $gateIns = $pdo->prepare(
                'INSERT INTO product_extra_parent_choices
                   (product_extra_id, product_extra_choice_id) VALUES (?, ?)'
            );
            foreach ($copiedExtras as $oldExtraId => $newExtraId) {
                $pg = $pdo->prepare(
                    'SELECT product_extra_choice_id FROM product_extra_parent_choices
                      WHERE product_extra_id = ?'
                );
                $pg->execute([$oldExtraId]);
                foreach ($pg->fetchAll(PDO::FETCH_COLUMN) as $oldPcid) {
                    $oldPcid = (int) $oldPcid;
                    if (isset($choiceIdMap[$oldPcid])) {
                        $gateIns->execute([$newExtraId, $choiceIdMap[$oldPcid]]);
                    } else {
                        $droppedGates++;
                    }
                }
            }

            $pdo->commit();

            // Audit — one line per copied option.
            require_once __DIR__ . '/../../_partials/catalogue_audit.php';
            foreach ($copiedExtras as $oldExtraId => $newExtraId) {
                catalogue_audit_log(
                    'extra', $newExtraId, 'create', null, null,
                    ['copied_from_product' => $sourceId, 'from_extra' => $oldExtraId],
                    $productId, ['action' => 'copy_from_product']
                );
            }

            $msg = "Copied $copiedOptions option" . ($copiedOptions === 1 ? '' : 's')
                 . " ($copiedChoices choice" . ($copiedChoices === 1 ? '' : 's') . ")"
                 . ' from "' . $source['name'] . '".';
            if ($skippedExisting > 0) {
                $msg .= " Skipped $skippedExisting already on this product (same name).";
            }
            if ($madeUniversal > 0) {
                $msg .= " $madeUniversal choice" . ($madeUniversal === 1 ? '' : 's')
                      . ' had a system this product doesn\'t have — set to "all systems".';
            }
            if ($droppedGates > 0) {
                $msg .= " $droppedGates gating link" . ($droppedGates === 1 ? '' : 's')
                      . ' dropped (parent option wasn\'t in the copy).';
            }
            $_SESSION['flash_success'] = $msg;
            header('Location: ' . $redirect);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('extras-copy failed (client ' . $clientId . ', '
                . $sourceId . '→' . $productId . '): ' . $e->getMessage());
            $error = 'Could not copy the options — please try again.';
        }
    }
}

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Copy options &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
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
                        [(string) $target['name'],    '/admin/products/edit.php?id=' . $productId],
                        ['Options',                   '/admin/products/extras.php?product_id=' . $productId],
                        ['Copy from another product', null],
                    ]);
                ?>
                <h1 class="page-title">
                    Copy options into <?= e((string) $target['name']) ?>
                </h1>
                <p class="page-subtitle">
                    <a href="<?= e($redirect) ?>">&larr; Back to options</a>
                </p>
            </div>
        </div>

        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.875rem 1.125rem;margin-bottom:1rem">
            <p style="margin:0;color:#0c4a6e;font-size:0.9375rem;line-height:1.5">
                Pick a product to copy options from, tick the ones you want, and
                they'll be cloned into <strong><?= e((string) $target['name']) ?></strong>
                &mdash; choices, band scoping and width-table pricing included.
                Each choice's <strong>system</strong> is matched to this product's
                same-named system; anything with no match becomes "all systems".
            </p>
        </section>

        <!-- Stage 1: choose source product -->
        <section class="section">
            <div class="section-header"><h2 class="section-title">1. Copy from</h2></div>
            <form method="get" style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap">
                <input type="hidden" name="product_id" value="<?= $productId ?>">
                <div class="form-group" style="margin:0;min-width:18rem">
                    <label for="source_id">Source product</label>
                    <select id="source_id" name="source_id" onchange="this.form.submit()"
                            style="padding:0.5rem 0.625rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit;background:var(--bg-input);color:var(--text-body);width:100%">
                        <option value="">— Choose a product —</option>
                        <?php foreach ($sourceChoices as $sc): ?>
                            <option value="<?= (int) $sc['id'] ?>" <?= $sourceId === (int) $sc['id'] ? 'selected' : '' ?>>
                                <?= e((string) $sc['name']) ?> (<?= (int) $sc['opt_count'] ?> option<?= (int) $sc['opt_count'] === 1 ? '' : 's' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <noscript><button type="submit" class="btn btn-secondary">Load</button></noscript>
            </form>
        </section>

        <?php if ($source): ?>
            <!-- Stage 2: pick options -->
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">2. Options in <?= e((string) $source['name']) ?></h2>
                </div>
                <?php if (!$sourceOptions): ?>
                    <div class="empty-note">That product has no options to copy.</div>
                <?php else: ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="copy">
                        <input type="hidden" name="product_id" value="<?= $productId ?>">
                        <input type="hidden" name="source_id" value="<?= $sourceId ?>">

                        <div style="margin-bottom:0.75rem">
                            <label style="cursor:pointer;font-size:0.875rem;color:var(--text-muted)">
                                <input type="checkbox" id="copy-all" checked
                                       onchange="document.querySelectorAll('.opt-cb').forEach(function(c){c.checked=this.checked}.bind(this))">
                                Select all
                            </label>
                        </div>

                        <div class="wiz-list" style="border:1px solid var(--border);border-radius:8px;padding:0.25rem 0.75rem">
                            <?php foreach ($sourceOptions as $o): ?>
                                <label style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0;
                                              border-bottom:1px solid var(--bg-subtle-2);cursor:pointer">
                                    <input type="checkbox" class="opt-cb" name="extra_ids[]"
                                           value="<?= (int) $o['id'] ?>" checked>
                                    <span style="flex:1">
                                        <strong><?= e((string) $o['name']) ?></strong>
                                        <span style="color:var(--text-faint);font-size:0.8125rem">
                                            &middot; <?= (int) $o['choice_count'] ?> choice<?= (int) $o['choice_count'] === 1 ? '' : 's' ?>
                                            <?= (int) $o['is_required'] === 1 ? ' &middot; required' : '' ?>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-actions" style="margin-top:1rem">
                            <button type="submit" class="btn btn-primary">
                                Copy selected into <?= e((string) $target['name']) ?> &rarr;
                            </button>
                            <a href="<?= e($redirect) ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
