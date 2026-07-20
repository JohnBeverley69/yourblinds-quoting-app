<?php
declare(strict_types=1);

/**
 * Master Admin: push catalogue updates from the master tenant to
 * selected client tenants.
 *
 * Only products whose name starts with the configured prefix
 * (default "Beverley") are pushed. The rest of every tenant's
 * catalogue is left alone. Markups/discounts are never touched.
 * See _partials/catalogue_push.php for the full rules.
 *
 * Page flow:
 *   GET  → lists Beverley-prefixed products in the master + a list
 *          of other tenants with tickboxes. Big red "Push to ticked"
 *          button at the bottom.
 *   POST → runs push_catalogue_to_client() per ticked tenant, shows
 *          a per-tenant results summary on the redirected GET.
 *
 * Super-admin only.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/catalogue_push.php';
require_once __DIR__ . '/../_partials/library.php';

requireSuperAdmin();

// Catalogue push REVIVED 2026-07-10. Beverley needs the master-admin push to
// send its own price-list updates to client copies — and the engine now also
// stamps the manufacturing source-marker (products.source_client_id) on each
// pushed product, so a push doubles as provisioning/backfill for order routing.
// NOTE: only THIS master-admin push is revived; the tenant-facing Price-List
// Library (library/index.php) stays retired.

$user           = current_user();
$masterClientId = (int) $user['client_id'];

// Product-name prefix used to decide which master-tenant products
// are eligible to push out. Default = "Bev" (was "Beverley" originally;
// renamed during the testing phase). Overridable per-request via
// ?prefix=X so a future rename doesn't require a code edit. The page
// has a small input that GETs back with the new prefix.
$DEFAULT_PREFIX = 'Bev';
$prefix         = trim((string) ($_GET['prefix'] ?? $DEFAULT_PREFIX));
if ($prefix === '') $prefix = $DEFAULT_PREFIX;

// Library suppliers drive the picker — a checkbox list so several can be pushed
// at once. Falls back to the built-in default if not migrated.
$librarySuppliers = library_suppliers();
$validPrefixes    = [];
foreach ($librarySuppliers as $sup) {
    $pfx = trim((string) ($sup['prefix'] ?? ''));
    if ($pfx !== '') $validPrefixes[] = $pfx;
}
$validPrefixes = array_values(array_unique($validPrefixes));

// Prefix → supplier display name, so pushed products land in a category
// named after the supplier on the client (matching how the Master
// Catalogue groups them). Falls back to the prefix if a name is missing.
$prefixToName = [];
foreach ($librarySuppliers as $sup) {
    $pfx = trim((string) ($sup['prefix'] ?? ''));
    if ($pfx !== '') $prefixToName[$pfx] = (string) ($sup['name'] ?? $pfx);
}

/** Blank push-summary skeleton (same keys push_catalogue_to_client returns). */
function pu_blank_summary(): array {
    return [
        'products_added' => 0, 'products_updated' => 0, 'systems_added' => 0,
        'fabrics_added' => 0, 'fabrics_updated' => 0, 'extras_added' => 0,
        'extras_updated' => 0, 'choices_added' => 0, 'choices_updated' => 0,
        'choices_removed' => 0, 'systems_removed' => 0, 'fabrics_removed' => 0,
        'extras_removed' => 0, 'price_tables_removed' => 0,
        'price_tables_added' => 0, 'price_table_cells' => 0, 'width_table_cells' => 0,
        'errors' => [],
    ];
}
/** Add summary $b into $a (sum counts, concat errors) so a tenant's multi-supplier push reads as one. */
function pu_merge_summary(array $a, array $b): array {
    foreach ($a as $k => $v) {
        if ($k === 'errors') {
            $a['errors'] = array_merge($a['errors'], is_array($b['errors'] ?? null) ? $b['errors'] : []);
        } else {
            $a[$k] = (int) $v + (int) ($b[$k] ?? 0);
        }
    }
    return $a;
}

// Push can be a long, memory-heavy operation when the master tenant
// has many products with large price grids (10k+ cells). Default PHP
// limits on shared hosting are usually 30s / 128M which is exactly
// where a 500-without-trail comes from. Bump both for this page;
// these are upper bounds, not targets — most pushes complete in a
// few seconds.
if (!headers_sent()) {
    set_time_limit(300);             // 5 minutes
    @ini_set('memory_limit', '512M');
}

// Fatal-error catcher. set_time_limit / memory exhaustion don't throw
// — they trigger PHP's shutdown sequence. If one of those (or a parse
// error in something we required) takes us down, log it to the PHP
// error log AND flash a readable message before the page dies, so
// the user sees something useful instead of a bare 500.
//
// The shutdown function only fires if the previous page render
// hadn't already completed cleanly, so it's safe to register
// unconditionally.
register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err) return;
    if (!in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR], true)) {
        return;   // non-fatal, ignore
    }
    error_log(
        'push-updates SHUTDOWN fatal: ' . $err['message']
        . ' at ' . $err['file'] . ':' . $err['line']
    );
    // Best-effort flash + redirect. If headers are already sent the
    // user gets the bare 500 anyway but at least the log has the
    // root cause.
    if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['flash_error'] = 'Push hit a fatal error — check the server log. '
            . 'Message: ' . $err['message'];
        header('Location: /master-admin/push-updates.php');
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── JSON branch ──────────────────────────────────────────────
    //
    // Sequential push driven by JS — see the inline script below.
    // The browser sends ONE tenant per request, waits for the JSON
    // response, then advances to the next. This keeps every request
    // small (one tenant's catalogue, ~5–30 sec) so the shared-host
    // resource limits never get touched.
    //
    // We return JSON for these requests and don't write to session —
    // the JS aggregates the per-tenant results client-side.
    $isJson = (string) ($_POST['_format'] ?? '') === 'json';

    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        try {
            csrf_check();
            $rawIds = is_array($_POST['target_ids'] ?? null) ? $_POST['target_ids'] : [];
            $rawIds = array_values(array_unique(array_filter(
                array_map('intval', $rawIds),
                static fn ($v) => $v > 0 && $v !== $masterClientId
            )));

            // JSON mode is one-tenant-per-call by design. If the
            // caller sends more than one, we still only process the
            // first — keeps the per-request budget predictable.
            if (!$rawIds) {
                echo json_encode(['ok' => false, 'error' => 'No tenant id supplied.']);
                exit;
            }
            $tid = $rawIds[0];

            // Which suppliers (prefixes) to push — only ones we know about.
            $prefixes = is_array($_POST['prefixes'] ?? null) ? $_POST['prefixes'] : [];
            $prefixes = array_values(array_intersect(array_map('strval', $prefixes), $validPrefixes));
            if (!$prefixes) {
                echo json_encode(['ok' => false, 'tenant_id' => $tid, 'error' => 'No suppliers selected.']);
                exit;
            }

            $pdo = db();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Resolve the tenant name upfront so the JSON payload
            // reads nicely client-side without an extra round-trip.
            $st = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
            $st->execute([$tid]);
            $name = (string) ($st->fetchColumn() ?: ('client #' . $tid));

            // Push every selected supplier into this tenant, aggregating the
            // result. A per-supplier failure is recorded but doesn't abort.
            $agg = pu_blank_summary();
            foreach ($prefixes as $pfx) {
                try {
                    $agg = pu_merge_summary($agg, push_catalogue_to_client($pdo, $masterClientId, $tid, $pfx, $prefixToName[$pfx] ?? $pfx));
                } catch (Throwable $e) {
                    error_log('push-updates JSON: tenant=' . $tid . ' prefix=' . $pfx . ' FAILED: ' . $e->getMessage());
                    $agg['errors'][] = ['product' => '(' . $pfx . ')', 'message' => $e->getMessage()];
                }
            }
            echo json_encode(['ok' => true, 'tenant_id' => $tid, 'name' => $name, 'summary' => $agg]);
            exit;
        } catch (Throwable $outer) {
            error_log(
                'push-updates JSON: top-level failure: '
                . $outer->getMessage() . ' at ' . $outer->getFile() . ':' . $outer->getLine()
            );
            // best-effort — headers may already be sent if PHP died
            // mid-output, but the log line has the full picture.
            echo json_encode(['ok' => false, 'error' => $outer->getMessage()]);
            exit;
        }
    }

    // ── Legacy HTML/redirect branch ─────────────────────────────────
    //
    // Still works for non-JS fallback or one-tenant-at-a-time manual
    // pushes. Same behaviour as before this commit.
    //
    // Outer try/catch so an unexpected fatal (memory, timeout, schema
    // mismatch on a column we forgot) flashes a readable error instead
    // of dumping a bare 500. Also logs the full exception so the next
    // diagnosis only needs the server's PHP error log.
    try {
        csrf_check();
        $targetIds = is_array($_POST['target_ids'] ?? null) ? $_POST['target_ids'] : [];
        $targetIds = array_values(array_unique(array_filter(
            array_map('intval', $targetIds),
            static fn ($v) => $v > 0 && $v !== $masterClientId
        )));

        if (!$targetIds) {
            $_SESSION['flash_error'] = 'Pick at least one tenant to push to.';
            header('Location: /master-admin/push-updates.php');
            exit;
        }

        $prefixes = is_array($_POST['prefixes'] ?? null) ? $_POST['prefixes'] : [];
        $prefixes = array_values(array_intersect(array_map('strval', $prefixes), $validPrefixes));
        if (!$prefixes) {
            $_SESSION['flash_error'] = 'Pick at least one supplier to push.';
            header('Location: /master-admin/push-updates.php');
            exit;
        }

        $pdo = db();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Resolve tenant names upfront so the summary reads nicely.
        $names = [];
        $st = $pdo->prepare('SELECT id, company_name FROM clients WHERE id = ? LIMIT 1');
        foreach ($targetIds as $tid) {
            $st->execute([$tid]);
            $row = $st->fetch();
            if ($row) $names[$tid] = (string) $row['company_name'];
        }

        $results = [];
        foreach ($targetIds as $tid) {
            try {
                $summary = pu_blank_summary();
                foreach ($prefixes as $pfx) {
                    $summary = pu_merge_summary($summary, push_catalogue_to_client($pdo, $masterClientId, $tid, $pfx, $prefixToName[$pfx] ?? $pfx));
                }
                $results[$tid] = ['name' => $names[$tid] ?? ('client #' . $tid), 'summary' => $summary, 'failed' => false];
            } catch (Throwable $e) {
                // Per-tenant failure — log AND record on the summary so
                // the UI shows it. Other tenants in the batch still get
                // processed.
                error_log(
                    'push-updates: tenant=' . $tid . ' FAILED: '
                    . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine()
                );
                $results[$tid] = [
                    'name'    => $names[$tid] ?? ('client #' . $tid),
                    'summary' => null,
                    'failed'  => true,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        $_SESSION['push_results'] = $results;
        $_SESSION['flash_success'] = 'Push complete — see the summary below.';
        header('Location: /master-admin/push-updates.php');
        exit;
    } catch (Throwable $outer) {
        // Whole-batch fatal (CSRF, DB connect, etc.). Log + flash, never 500.
        error_log(
            'push-updates: top-level failure: '
            . $outer->getMessage() . ' at ' . $outer->getFile() . ':' . $outer->getLine()
            . "\n" . $outer->getTraceAsString()
        );
        $_SESSION['flash_error'] = 'Push could not run: ' . $outer->getMessage();
        header('Location: /master-admin/push-updates.php');
        exit;
    }
}

// GET — list master's prefixed products + other tenants.
$pdo = db();

// Per-supplier product counts on the master, for the supplier checkbox list.
$supplierRows     = [];
$totalSrcProducts = 0;
$cntSt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE client_id = ? AND name LIKE ?');
foreach ($librarySuppliers as $sup) {
    $pfx = trim((string) ($sup['prefix'] ?? ''));
    if ($pfx === '') continue;
    $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $pfx) . '%';
    $cntSt->execute([$masterClientId, $like]);
    $cnt = (int) $cntSt->fetchColumn();
    $supplierRows[]    = ['name' => (string) ($sup['name'] ?? $pfx), 'prefix' => $pfx, 'count' => $cnt];
    $totalSrcProducts += $cnt;
}

// Other tenants. Exclude the master.
$tenantsSt = $pdo->prepare(
    'SELECT id, company_name, active
       FROM clients
      WHERE id != ?
   ORDER BY company_name'
);
$tenantsSt->execute([$masterClientId]);
$tenants = $tenantsSt->fetchAll();

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
$results  = $_SESSION['push_results']  ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['push_results']);

$activeNav = 'push-updates';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Push catalogue updates &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .info-panel {
            background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px;
            padding:0.875rem 1.125rem; margin-bottom:1rem;
            color:#0c4a6e; font-size:0.9375rem; line-height:1.5;
        }
        .info-panel strong { color:#0c4a6e; }
        .info-panel code {
            background:#fff; padding:0.0625rem 0.375rem; border-radius:4px;
            border:1px solid #bae6fd; font-size:0.8125rem;
        }
        .src-grid {
            display:grid; gap:0.5rem;
            grid-template-columns: 1fr;
        }
        .src-row {
            background:#fff; border:1px solid var(--border); border-radius:10px;
            padding:0.625rem 0.875rem;
            display:flex; align-items:center; gap:0.875rem; flex-wrap:wrap;
        }
        .src-row .src-name { font-weight:600; color:var(--text-primary); flex:0 0 auto; min-width:14rem; }
        .src-row .src-bits { color:var(--text-faint); font-size:0.875rem; }
        .tenant-row {
            background:#fff; border:1px solid var(--border); border-radius:10px;
            padding:0.5rem 0.875rem; margin-bottom:0.375rem;
            display:flex; align-items:center; gap:0.625rem;
        }
        .tenant-row label {
            display:inline-flex; align-items:center; gap:0.5rem;
            cursor:pointer; flex:1 1 auto; font-weight:500;
        }
        .tenant-row input[type="checkbox"] { width:18px; height:18px; }
        .tenant-row .inactive {
            font-size:0.6875rem; color:var(--text-faint); background:var(--bg-subtle-2);
            padding:0.0625rem 0.4375rem; border-radius:999px;
            text-transform:uppercase; letter-spacing:0.05em;
        }
        .summary-block {
            background:#fff; border:1px solid var(--border); border-radius:10px;
            padding:0.75rem 1rem; margin-bottom:0.625rem;
        }
        .summary-block .sb-name { font-weight:700; color:#1f3b5b; font-size:1.0625rem; }
        .summary-block .sb-stats {
            display:flex; flex-wrap:wrap; gap:0.5rem 1rem;
            margin-top:0.5rem; font-size:0.875rem; color:var(--text-secondary);
        }
        .summary-block .sb-stats span strong { color:#065f46; }
        .summary-block.failed { border-color:#fecaca; background:#fef2f2; }
        .summary-block.failed .sb-name { color:#991b1b; }
        .summary-block .sb-errors {
            margin-top:0.5rem; font-size:0.8125rem; color:#991b1b;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Push catalogue updates</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                </p>
            </div>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="info-panel">
            <p style="margin:0 0 0.5rem">
                Pushes the products of the <strong>suppliers you tick below</strong> into the
                <strong>tenants you tick</strong>. Each tenant's own non-prefixed products are
                <em>not touched</em>.
            </p>
            <p style="margin:0">
                <strong>What gets synced:</strong> products, systems, fabrics, options (extras),
                choices, and price tables. New products are filed into a group named after the
                supplier (matching the Master Catalogue) &mdash; a tenant's own grouping is never changed.
                <strong>What does NOT get changed:</strong> any tenant's markup or discount %,
                or any non-prefixed product they've set up themselves.
                <br>
                <strong>How updates work:</strong> matched items (by name) are updated in place.
                Missing items are added. Items the tenant has added themselves are kept.
                Prices in matched grids are overwritten cell-by-cell; cells the tenant has at
                sizes you don't cover are kept.
            </p>
        </section>

        <?php if ($results): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">Latest push results</h2>
                </div>
                <?php foreach ($results as $tid => $res):
                    $s = $res['summary'];
                ?>
                    <div class="summary-block <?= $res['failed'] ? 'failed' : '' ?>">
                        <div class="sb-name">
                            <?= e((string) $res['name']) ?>
                            <?php if ($res['failed']): ?>
                                &mdash; <span style="color:#991b1b">failed</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($res['failed']): ?>
                            <div class="sb-errors">
                                <?= e((string) ($res['error'] ?? 'Unknown error')) ?>
                            </div>
                        <?php else: ?>
                            <div class="sb-stats">
                                <span><strong><?= (int) $s['products_added']     ?></strong> products added</span>
                                <span><strong><?= (int) $s['products_updated']   ?></strong> products refreshed</span>
                                <span><strong><?= (int) $s['systems_added']      ?></strong> systems added</span>
                                <span><strong><?= (int) $s['fabrics_added']      ?></strong> fabrics added</span>
                                <span><strong><?= (int) $s['fabrics_updated']    ?></strong> fabrics updated</span>
                                <span><strong><?= (int) $s['extras_added']       ?></strong> options added</span>
                                <span><strong><?= (int) $s['extras_updated']     ?></strong> options updated</span>
                                <span><strong><?= (int) $s['choices_added']      ?></strong> choices added</span>
                                <span><strong><?= (int) $s['choices_updated']    ?></strong> choices updated</span>
                                <span><strong><?= (int) ($s['choices_removed'] ?? 0) ?></strong> choices removed</span>
                                <span><strong><?= (int) ($s['systems_removed'] ?? 0) ?></strong> systems removed</span>
                                <span><strong><?= (int) ($s['fabrics_removed'] ?? 0) ?></strong> fabrics removed</span>
                                <span><strong><?= (int) ($s['extras_removed'] ?? 0) ?></strong> options removed</span>
                                <span><strong><?= (int) ($s['price_tables_removed'] ?? 0) ?></strong> price tables removed</span>
                                <span><strong><?= (int) $s['price_tables_added'] ?></strong> price tables added</span>
                                <span><strong><?= (int) $s['price_table_cells']  ?></strong> price cells synced</span>
                                <span><strong><?= (int) $s['width_table_cells']  ?></strong> width-table cells synced</span>
                            </div>
                            <?php if (!empty($s['errors'])): ?>
                                <div class="sb-errors">
                                    <strong><?= count($s['errors']) ?> product(s) failed:</strong>
                                    <?php foreach ($s['errors'] as $err): ?>
                                        <div>&middot; <?= e((string) ($err['product'] ?? '?')) ?>: <?= e((string) ($err['message'] ?? '')) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($totalSrcProducts > 0 && $tenants): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">Push updates</h2>
                </div>
                <!--
                    No data-confirm on the form — the confirm-modal
                    partial calls form.submit() on OK which skips event
                    listeners, so our JS submit handler would never
                    fire. The confirmation is done in JS below using a
                    native confirm() dialog instead.
                -->
                <form method="post" action="/master-admin/push-updates.php"
                      id="push-form">
                    <?= csrf_field() ?>

                    <h3 style="font-size:0.8125rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-faint);font-weight:700;margin:0 0 0.5rem">1. Suppliers to push</h3>
                    <div style="margin-bottom:0.5rem">
                        <label style="display:inline-flex;align-items:center;gap:0.5rem;cursor:pointer;color:#1f3b5b;font-weight:600">
                            <input type="checkbox" id="select-all-suppliers" style="width:18px;height:18px">
                            Select all suppliers
                        </label>
                    </div>
                    <?php foreach ($supplierRows as $sr): $has = (int) $sr['count'] > 0; ?>
                        <div class="tenant-row"<?= $has ? '' : ' style="opacity:.6"' ?>>
                            <label>
                                <input type="checkbox" name="prefixes[]" value="<?= e((string) $sr['prefix']) ?>"
                                       class="supplier-cb" <?= $has ? '' : 'disabled' ?>>
                                <strong><?= e((string) $sr['name']) ?></strong>
                                <span style="color:var(--text-faint);font-size:0.875rem">
                                    (<?= e((string) $sr['prefix']) ?>) · <?= (int) $sr['count'] ?> product<?= (int) $sr['count'] === 1 ? '' : 's' ?>
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>

                    <h3 style="font-size:0.8125rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-faint);font-weight:700;margin:1rem 0 0.5rem">2. Tenants to push to</h3>
                    <div style="margin-bottom:0.625rem">
                        <label style="display:inline-flex;align-items:center;gap:0.5rem;cursor:pointer;color:#1f3b5b;font-weight:600">
                            <input type="checkbox" id="select-all-tenants" style="width:18px;height:18px">
                            Select all
                        </label>
                    </div>
                    <?php foreach ($tenants as $t): ?>
                        <div class="tenant-row">
                            <label>
                                <input type="checkbox" name="target_ids[]" value="<?= (int) $t['id'] ?>"
                                       class="tenant-cb" data-tenant-name="<?= e((string) $t['company_name']) ?>">
                                <?= e((string) $t['company_name']) ?>
                            </label>
                            <?php if ((int) $t['active'] !== 1): ?>
                                <span class="inactive">Inactive tenant</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-actions" style="margin-top:1rem">
                        <button type="submit" class="btn btn-primary" id="push-submit"
                                style="background:#b91c1c;border-color:#b91c1c">
                            Push to selected tenants &raquo;
                        </button>
                    </div>
                </form>

                <!--
                    Live progress + per-tenant results. Hidden until the
                    JS push begins. Each tenant card flips green/red as
                    its fetch() resolves.
                -->
                <div id="push-progress" style="display:none;margin-top:1rem">
                    <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:0.875rem 1.125rem">
                        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.625rem">
                            <strong style="color:#1f3b5b;font-size:1rem">
                                Pushing… <span id="pp-current">0</span> of <span id="pp-total">0</span>
                            </strong>
                            <span id="pp-current-name" style="color:var(--text-faint);font-size:0.875rem;flex:1"></span>
                        </div>
                        <div style="background:var(--bg-subtle-2);border-radius:999px;height:0.5rem;overflow:hidden">
                            <div id="pp-bar" style="background:#1f3b5b;height:100%;width:0%;transition:width 200ms"></div>
                        </div>
                        <div id="pp-results" style="margin-top:0.875rem"></div>
                    </div>
                </div>

                <script>
                (function () {
                    var all = document.getElementById('select-all-tenants');
                    if (all) {
                        all.addEventListener('change', function () {
                            document.querySelectorAll('.tenant-cb').forEach(function (cb) {
                                cb.checked = all.checked;
                            });
                        });
                    }
                    var allSup = document.getElementById('select-all-suppliers');
                    if (allSup) {
                        allSup.addEventListener('change', function () {
                            document.querySelectorAll('.supplier-cb:not(:disabled)').forEach(function (cb) {
                                cb.checked = allSup.checked;
                            });
                        });
                    }
                    function selectedPrefixes() {
                        return Array.from(document.querySelectorAll('.supplier-cb:checked')).map(function (cb) { return cb.value; });
                    }

                    // ── Sequential push controller ────────────────────
                    //
                    // Replaces the form's default submit with a JS
                    // controller that POSTs each ticked tenant in turn,
                    // waiting for the previous response before sending
                    // the next. Each request is JSON (_format=json) and
                    // only carries one tenant — that's what keeps every
                    // request under the shared-host resource limits.
                    //
                    // The "data-confirm" attribute on the form is
                    // handled by the global confirm_modal partial; once
                    // it resolves the form fires a real "submit" event,
                    // which we intercept here.
                    var form     = document.getElementById('push-form');
                    var btn      = document.getElementById('push-submit');
                    var progEl   = document.getElementById('push-progress');
                    var curEl    = document.getElementById('pp-current');
                    var totEl    = document.getElementById('pp-total');
                    var nameEl   = document.getElementById('pp-current-name');
                    var barEl    = document.getElementById('pp-bar');
                    var resEl    = document.getElementById('pp-results');
                    if (!form || !btn) return;

                    var csrfInput = form.querySelector('input[name="csrf_token"], input[name="_csrf"], input[name="csrf"]');

                    function esc(s) {
                        return String(s).replace(/[&<>"']/g, function (c) {
                            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
                        });
                    }

                    function renderResultRow(r) {
                        // r is the JSON the server returned (ok / failed
                        // / summary). Builds one card per tenant in the
                        // same visual style as the post-redirect summary.
                        var failed = !r.ok;
                        var bg = failed ? '#fef2f2' : '#fff';
                        var border = failed ? '#fecaca' : 'var(--border)';
                        var nameClr = failed ? '#991b1b' : '#1f3b5b';

                        var stats = '';
                        if (!failed && r.summary) {
                            var s = r.summary;
                            var bits = [
                                [s.products_added,     'products added'],
                                [s.products_updated,   'products refreshed'],
                                [s.systems_added,      'systems added'],
                                [s.fabrics_added,      'fabrics added'],
                                [s.fabrics_updated,    'fabrics updated'],
                                [s.extras_added,       'options added'],
                                [s.extras_updated,     'options updated'],
                                [s.choices_added,      'choices added'],
                                [s.choices_updated,    'choices updated'],
                                [s.choices_removed,    'choices removed'],
                                [s.systems_removed,    'systems removed'],
                                [s.fabrics_removed,    'fabrics removed'],
                                [s.extras_removed,     'options removed'],
                                [s.price_tables_removed, 'price tables removed'],
                                [s.price_tables_added, 'price tables added'],
                                [s.price_table_cells,  'price cells synced'],
                                [s.width_table_cells,  'width-table cells synced']
                            ];
                            stats = '<div style="display:flex;flex-wrap:wrap;gap:0.5rem 1rem;margin-top:0.5rem;font-size:0.875rem;color:var(--text-secondary)">';
                            bits.forEach(function (b) {
                                stats += '<span><strong style="color:#065f46">' + (b[0] | 0) + '</strong> ' + b[1] + '</span>';
                            });
                            stats += '</div>';

                            if (s.errors && s.errors.length) {
                                stats += '<div style="margin-top:0.5rem;font-size:0.8125rem;color:#991b1b">';
                                stats += '<strong>' + s.errors.length + ' product(s) failed:</strong>';
                                s.errors.forEach(function (err) {
                                    stats += '<div>&middot; ' + esc(err.product || '?') + ': ' + esc(err.message || '') + '</div>';
                                });
                                stats += '</div>';
                            }
                        } else {
                            stats = '<div style="margin-top:0.5rem;font-size:0.8125rem;color:#991b1b">'
                                  + esc(r.error || 'Unknown error') + '</div>';
                        }

                        return '<div style="background:' + bg + ';border:1px solid ' + border + ';border-radius:8px;padding:0.5rem 0.75rem;margin-bottom:0.375rem">'
                             +   '<div style="font-weight:700;color:' + nameClr + '">'
                             +     esc(r.name || ('client #' + r.tenant_id))
                             +     (failed ? ' &mdash; <span style="color:#991b1b">failed</span>' : '')
                             +   '</div>'
                             +   stats
                             + '</div>';
                    }

                    async function pushOne(tenantId, prefixes) {
                        var fd = new FormData();
                        fd.append('_format', 'json');
                        fd.append('target_ids[]', String(tenantId));
                        (prefixes || []).forEach(function (p) { fd.append('prefixes[]', p); });
                        if (csrfInput) fd.append(csrfInput.name, csrfInput.value);
                        try {
                            var resp = await fetch('/master-admin/push-updates.php', {
                                method:      'POST',
                                body:        fd,
                                credentials: 'same-origin',
                                cache:       'no-store'
                            });
                            // Server returns JSON for both success + failure.
                            // A truly fatal PHP error might return a 500 with
                            // HTML — handle that gracefully.
                            if (!resp.ok && resp.status >= 500) {
                                return { ok: false, tenant_id: tenantId, error: 'Server error (' + resp.status + '). Check error log.' };
                            }
                            var text = await resp.text();
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                return { ok: false, tenant_id: tenantId, error: 'Bad response: ' + text.substring(0, 200) };
                            }
                        } catch (e) {
                            return { ok: false, tenant_id: tenantId, error: 'Network error: ' + e.message };
                        }
                    }

                    form.addEventListener('submit', function (ev) {
                        // Collect ticked tenants + suppliers.
                        var ticked   = Array.from(form.querySelectorAll('.tenant-cb:checked'));
                        var prefixes = selectedPrefixes();
                        if (!ticked.length && !prefixes.length) return;   // let the server give the error

                        // Intercept and drive the push from JS.
                        ev.preventDefault();
                        ev.stopImmediatePropagation();

                        if (!prefixes.length) { alert('Tick at least one supplier to push.'); return; }
                        if (!ticked.length)   { alert('Tick at least one tenant to push to.'); return; }

                        var queue = ticked.map(function (cb) {
                            return { id: parseInt(cb.value, 10), name: cb.dataset.tenantName || ('client #' + cb.value) };
                        });

                        // Confirmation step — replaces the data-confirm
                        // modal which can't be used here (see HTML
                        // comment above the form for why).
                        var names = queue.map(function (q) { return q.name; }).join(', ');
                        var msg = 'Push ' + prefixes.length + ' supplier' + (prefixes.length === 1 ? '' : 's')
                                + ' to ' + queue.length + ' tenant' + (queue.length === 1 ? '' : 's') + '?'
                                + '\n\n' + names + '\n\n'
                                + 'Matched items will be updated, missing ones added. '
                                + 'Tenant markups, discounts, and non-prefixed products are not touched.';
                        if (!confirm(msg)) return;

                        progEl.style.display = '';
                        totEl.textContent = String(queue.length);
                        curEl.textContent = '0';
                        nameEl.textContent = '';
                        barEl.style.width = '0%';
                        resEl.innerHTML = '';

                        btn.disabled = true;
                        btn.textContent = 'Pushing…';

                        (async function () {
                            for (var i = 0; i < queue.length; i++) {
                                var t = queue[i];
                                curEl.textContent = String(i + 1);
                                nameEl.textContent = '— ' + t.name;
                                barEl.style.width = (((i) / queue.length) * 100).toFixed(1) + '%';

                                var r = await pushOne(t.id, prefixes);
                                // Ensure name is populated even on
                                // top-level failures where the server
                                // didn't get a chance to look it up.
                                if (!r.name) r.name = t.name;

                                resEl.insertAdjacentHTML('beforeend', renderResultRow(r));
                                barEl.style.width = (((i + 1) / queue.length) * 100).toFixed(1) + '%';
                            }
                            nameEl.textContent = '— all done';
                            btn.disabled = false;
                            btn.textContent = 'Push to selected tenants »';
                        })();
                    });
                })();
                </script>
            </section>
        <?php endif; ?>

        <?php if ($totalSrcProducts > 0 && !$tenants): ?>
            <!-- Source products exist but there's nowhere to send them. Without
                 this note the whole "Push to which tenants?" section just
                 vanished, which reads as broken rather than "no targets". -->
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">Push to which tenants?</h2>
                </div>
                <p style="color:var(--text-secondary);font-size:0.9375rem;line-height:1.5;max-width:46rem">
                    There are <strong>no other tenants to push to.</strong> The catalogue is
                    only ever pushed to <em>other</em> client accounts &mdash; never the one
                    you're signed into. Add or restore a client tenant via
                    <a href="/master-admin/new-client.php" style="color:var(--brand)">Master Admin &rarr; New client</a>,
                    then come back here.
                </p>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
