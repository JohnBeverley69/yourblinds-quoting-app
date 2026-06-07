<?php
declare(strict_types=1);

/**
 * Copy FABRICS (product_options) from one product to another.
 *
 * Sibling products often sell the exact same materials — e.g. a
 * "vertical blind fabric only" line uses the vertical blind's whole
 * fabric range. There was no cross-product copy; this adds one.
 *
 * Like the options copy, fabric system scope is product-specific:
 * a fabric scoped to a system is re-pointed to the target's same-NAMED
 * system, falling back to universal (NULL) with a count when there's no
 * match. (Most fabrics are universal already, so this is usually a no-op.)
 *
 * Existing matches in the target — same band + name + colour — are
 * skipped, so re-running the copy is safe and won't pile up duplicates.
 *
 * Copy is scoped by band: tick which bands to bring across (all by
 * default). Designed for big ranges (the vertical set is ~1,300) — the
 * insert is chunked into multi-row statements.
 *
 * Two-stage UI:
 *   1. ?product_id=TARGET   → pick a source product
 *   2. &source_id=SOURCE    → tick bands, copy
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

// ── Target product ────────────────────────────────────────────────────
$tStmt = $pdo->prepare(
    'SELECT id, name, option_label FROM products WHERE id = ? AND client_id = ?'
);
$tStmt->execute([$productId, $clientId]);
$target = $tStmt->fetch();
if (!$target) {
    header('Location: /admin/products/index.php');
    exit;
}
$label  = trim((string) ($target['option_label'] ?? '')) ?: 'Fabric';
$labelL = strtolower($label);
$redirect = '/admin/products/options.php?product_id=' . $productId;

// ── Candidate source products (with fabric counts) ────────────────────
$pickStmt = $pdo->prepare(
    'SELECT p.id, p.name,
            (SELECT COUNT(*) FROM product_options o
              WHERE o.product_id = p.id AND o.client_id = p.client_id AND o.active = 1) AS fab_count
       FROM products p
      WHERE p.client_id = ? AND p.id <> ? AND p.active = 1
      ORDER BY p.name'
);
$pickStmt->execute([$clientId, $productId]);
$sourceChoices = $pickStmt->fetchAll();

// ── Source product + its bands (when chosen) ──────────────────────────
$source      = null;
$sourceBands = [];
if ($sourceId > 0) {
    $sStmt = $pdo->prepare('SELECT id, name FROM products WHERE id = ? AND client_id = ?');
    $sStmt->execute([$sourceId, $clientId]);
    $source = $sStmt->fetch() ?: null;
    if ($source) {
        // Premium-first band sort (AAA → AA → A → rest), matching the
        // Fabrics list ordering elsewhere.
        $bStmt = $pdo->prepare(
            "SELECT band_code, COUNT(*) AS n
               FROM product_options
              WHERE product_id = ? AND client_id = ? AND active = 1
              GROUP BY band_code
              ORDER BY CASE band_code WHEN 'AAA' THEN 1 WHEN 'AA' THEN 2
                       WHEN 'A' THEN 3 ELSE 100 END, band_code"
        );
        $bStmt->execute([$sourceId, $clientId]);
        $sourceBands = $bStmt->fetchAll();
    }
}

$flashErr = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
$error = null;

// ── POST: do the copy ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'copy') {
    csrf_check();

    if (!$source) {
        $error = 'Pick a product to copy from.';
    }

    // Which bands to copy. '' (the empty band) is a legitimate value, so
    // we compare against the posted list as strings.
    $wantBands = array_map('strval', (array) ($_POST['bands'] ?? []));
    if ($error === null && !$wantBands) {
        $error = 'Tick at least one band to copy.';
    }

    if ($error === null) {
        // System remap (by name), same as the options copy.
        $tSys = $pdo->prepare(
            'SELECT id, name FROM product_systems
              WHERE product_id = ? AND client_id = ? AND active = 1'
        );
        $tSys->execute([$productId, $clientId]);
        $targetSysByName = [];
        foreach ($tSys->fetchAll() as $s) {
            $targetSysByName[mb_strtolower(trim((string) $s['name']))] = (int) $s['id'];
        }
        $sSys = $pdo->prepare(
            'SELECT id, name FROM product_systems
              WHERE product_id = ? AND client_id = ? AND active = 1'
        );
        $sSys->execute([$sourceId, $clientId]);
        $sourceSysName = [];
        foreach ($sSys->fetchAll() as $s) {
            $sourceSysName[(int) $s['id']] = (string) $s['name'];
        }
        $remapSystem = static function ($srcSysId) use ($sourceSysName, $targetSysByName): array {
            if ($srcSysId === null) return [null, false];
            $name = $sourceSysName[(int) $srcSysId] ?? null;
            if ($name === null) return [null, true];
            $key = mb_strtolower(trim($name));
            if (isset($targetSysByName[$key])) return [$targetSysByName[$key], false];
            return [null, true];
        };

        // Existing target keys (band|name|colour, case-insensitive) so we
        // can skip duplicates.
        $dupKey = static fn ($band, $name, $colour): string =>
            mb_strtolower(trim((string) $band)) . '|'
            . mb_strtolower(trim((string) $name)) . '|'
            . mb_strtolower(trim((string) $colour));
        $exStmt = $pdo->prepare(
            'SELECT band_code, name, colour FROM product_options
              WHERE product_id = ? AND client_id = ? AND active = 1'
        );
        $exStmt->execute([$productId, $clientId]);
        $existing = [];
        foreach ($exStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $existing[$dupKey($r['band_code'], $r['name'], $r['colour'])] = true;
        }

        // Pull the source fabrics for the wanted bands.
        $bandPh = implode(',', array_fill(0, count($wantBands), '?'));
        $srcStmt = $pdo->prepare(
            "SELECT * FROM product_options
              WHERE product_id = ? AND client_id = ? AND active = 1
                AND band_code IN ($bandPh)
              ORDER BY band_code, sort_order, name"
        );
        $srcStmt->execute(array_merge([$sourceId, $clientId], $wantBands));
        $srcRows = $srcStmt->fetchAll(PDO::FETCH_ASSOC);

        $copied = 0; $skipped = 0; $madeUniversal = 0;

        $pdo->beginTransaction();
        try {
            $sortSt = $pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_options
                  WHERE product_id = ? AND client_id = ?'
            );
            $sortSt->execute([$productId, $clientId]);
            $nextSort = (int) $sortSt->fetchColumn();

            // Build the insert column list once, from a sample row minus
            // the columns we never copy. product_id / system_id / sort_order
            // are overridden per row.
            $skip = ['id' => true, 'created_at' => true, 'updated_at' => true];
            $batch = [];   // each entry = ordered value array
            $insertCols = null;

            foreach ($srcRows as $r) {
                $key = $dupKey($r['band_code'], $r['name'], $r['colour']);
                if (isset($existing[$key])) { $skipped++; continue; }
                $existing[$key] = true;   // guard against in-batch dupes too

                [$newSysId, $wasUniversal] = $remapSystem($r['system_id'] ?? null);
                if ($wasUniversal) $madeUniversal++;

                $override = [
                    'product_id' => $productId,
                    'system_id'  => $newSysId,
                    'sort_order' => $nextSort++,
                ];

                $rowVals = [];
                if ($insertCols === null) {
                    $insertCols = [];
                    foreach ($r as $col => $_v) {
                        if (isset($skip[$col])) continue;
                        $insertCols[] = $col;
                    }
                }
                foreach ($insertCols as $col) {
                    $rowVals[] = array_key_exists($col, $override) ? $override[$col] : $r[$col];
                }
                $batch[] = $rowVals;
                $copied++;
            }

            if ($batch && $insertCols !== null) {
                $colSql   = '`' . implode('`,`', $insertCols) . '`';
                $rowPh    = '(' . implode(',', array_fill(0, count($insertCols), '?')) . ')';
                $chunkSz  = 100;
                for ($i = 0; $i < count($batch); $i += $chunkSz) {
                    $chunk  = array_slice($batch, $i, $chunkSz);
                    $params = [];
                    foreach ($chunk as $vals) {
                        foreach ($vals as $v) $params[] = $v;
                    }
                    $sql = "INSERT INTO product_options ($colSql) VALUES "
                         . implode(',', array_fill(0, count($chunk), $rowPh));
                    $pdo->prepare($sql)->execute($params);
                }
            }

            $pdo->commit();

            require_once __DIR__ . '/../../_partials/catalogue_audit.php';
            catalogue_audit_log(
                'option', null, 'import', null, null,
                ['copied' => $copied, 'skipped' => $skipped, 'from_product' => $sourceId],
                $productId, ['action' => 'copy_fabrics_from_product']
            );

            $msg = "Copied $copied $labelL" . ($copied === 1 ? '' : 's')
                 . ' from "' . $source['name'] . '".';
            if ($skipped > 0) {
                $msg .= " Skipped $skipped already present (same band + name + colour).";
            }
            if ($madeUniversal > 0) {
                $msg .= " $madeUniversal had a system this product doesn't have — set to \"all systems\".";
            }
            $_SESSION['flash_success'] = $msg;
            header('Location: ' . $redirect);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('options-copy failed (client ' . $clientId . ', '
                . $sourceId . '→' . $productId . '): ' . $e->getMessage());
            $error = 'Could not copy the ' . $labelL . 's — please try again.';
        }
    }
}

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Copy <?= e($labelL) ?>s &middot; YourBlinds</title>
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
                        [$label . 's',                $redirect],
                        ['Copy from another product', null],
                    ]);
                ?>
                <h1 class="page-title">
                    Copy <?= e($labelL) ?>s into <?= e((string) $target['name']) ?>
                </h1>
                <p class="page-subtitle">
                    <a href="<?= e($redirect) ?>">&larr; Back to <?= e($labelL) ?>s</a>
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
                Pick a product to copy <?= e($labelL) ?>s from and choose which bands to
                bring across. They're cloned into <strong><?= e((string) $target['name']) ?></strong>,
                skipping any that already exist (same band + name + colour). System-scoped
                <?= e($labelL) ?>s are matched to this product's same-named system.
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
                                <?= e((string) $sc['name']) ?> (<?= (int) $sc['fab_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <noscript><button type="submit" class="btn btn-secondary">Load</button></noscript>
            </form>
        </section>

        <?php if ($source): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">2. Bands in <?= e((string) $source['name']) ?></h2>
                </div>
                <?php if (!$sourceBands): ?>
                    <div class="empty-note">That product has no <?= e($labelL) ?>s to copy.</div>
                <?php else: ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="copy">
                        <input type="hidden" name="product_id" value="<?= $productId ?>">
                        <input type="hidden" name="source_id" value="<?= $sourceId ?>">

                        <div style="margin-bottom:0.75rem">
                            <label style="cursor:pointer;font-size:0.875rem;color:var(--text-muted)">
                                <input type="checkbox" id="copy-all" checked
                                       onchange="document.querySelectorAll('.band-cb').forEach(function(c){c.checked=this.checked}.bind(this))">
                                Select all bands
                            </label>
                        </div>

                        <div class="wiz-list" style="border:1px solid var(--border);border-radius:8px;padding:0.25rem 0.75rem">
                            <?php foreach ($sourceBands as $b):
                                $code = (string) $b['band_code'];
                            ?>
                                <label style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0;
                                              border-bottom:1px solid var(--bg-subtle-2);cursor:pointer">
                                    <input type="checkbox" class="band-cb" name="bands[]"
                                           value="<?= e($code) ?>" checked>
                                    <span style="flex:1">
                                        <strong><?= $code === '' ? '<em>(no band)</em>' : 'Band ' . e($code) ?></strong>
                                        <span style="color:var(--text-faint);font-size:0.8125rem">
                                            &middot; <?= (int) $b['n'] ?> <?= e($labelL) ?><?= (int) $b['n'] === 1 ? '' : 's' ?>
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
                        <p style="margin-top:0.625rem;color:var(--text-faint);font-size:0.8125rem">
                            Existing <?= e($labelL) ?>s (same band + name + colour) are skipped, so
                            it's safe to run this more than once.
                        </p>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
