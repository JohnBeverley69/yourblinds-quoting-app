<?php
declare(strict_types=1);

/**
 * One-off audit: list fabrics where a client tenant's supplier_name
 * disagrees with the master's, for the SAME band + name + colour
 * within the same Beverley-prefixed product.
 *
 * Read-only. Renders one section per tenant with a table of
 * mismatches. No fix actions — eyeball the rows, decide if any need
 * manual cleanup, then re-push from /master-admin/push-updates.php
 * to write the master's values back in (the fixed push handler now
 * matches on the full unique key so it won't loop in on itself).
 *
 * Built specifically to flag the fallout from commit 410ae46 → 1cc2084,
 * where pp_sync_options() was matching on 5 of the unique key's 6
 * fields. The bug silently overwrote supplier_name on some client
 * rows before the next-iteration INSERT tripped a duplicate-key
 * violation. This audit shows which rows look wrong as a result.
 *
 * Tenant scoping: master tenant = the logged-in super-admin's
 * client_id. Beverley-prefix is hardcoded to match push-updates.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$user           = current_user();
$masterClientId = (int) $user['client_id'];
$prefix         = 'Beverley';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Active client tenants except the master itself. Inactive ones can
// be reactivated later — for now exclude them so the audit doesn't
// drown the operator in dead data.
$tenants = $pdo->prepare(
    'SELECT id, company_name FROM clients
      WHERE id != ? AND active = 1
   ORDER BY company_name'
);
$tenants->execute([$masterClientId]);
$tenantRows = $tenants->fetchAll();

// For each tenant, find every MASTER fabric where the client doesn't
// have an exact 6-field match (band + supplier + name + colour).
//
// Earlier the query joined on the 4-field "human key" (product, band,
// name, colour) and reported any supplier_name mismatch. That hugely
// over-counted when both sides legitimately stocked the same fabric
// under two supplier brands (e.g. Bamboo from both Eclipse and Market
// Place) — every cross-pairing was reported as a mismatch even though
// both sides actually had matching pairs.
//
// New approach:
//   1. List master's Beverley fabrics
//   2. For each, NOT EXISTS check: client has an exact 6-field match?
//   3. If not, this is a genuine "missing" / "different supplier"
//      situation. Include it, with a side-column listing what the
//      client DOES have for the same (band, name, colour) — usually
//      a different supplier or nothing.
//
// Sub-select fetches client's supplier names for the same fabric so
// the operator can see "master says Market Place, client only stocks
// it under Eclipse" at a glance. NULL → '(none)' rendered downstream.
$mismatchSql = <<<'SQL'
    SELECT
        mp.name           AS product_name,
        mo.band_code      AS band_code,
        mo.name           AS fabric_name,
        mo.colour         AS colour,
        mo.supplier_name  AS master_supplier,
        (SELECT GROUP_CONCAT(co2.supplier_name ORDER BY co2.supplier_name SEPARATOR ', ')
           FROM products cp2
           JOIN product_options co2
             ON co2.product_id = cp2.id AND co2.client_id = cp2.client_id
          WHERE cp2.client_id  = ?
            AND cp2.name       = mp.name
            AND co2.band_code  = mo.band_code
            AND co2.name       = mo.name
            AND (co2.colour <=> mo.colour)
        ) AS client_suppliers
      FROM products mp
      JOIN product_options mo
        ON mo.product_id = mp.id AND mo.client_id = mp.client_id
     WHERE mp.client_id = ?
       AND mp.name LIKE ?
       AND NOT EXISTS (
            SELECT 1
              FROM products cp
              JOIN product_options co
                ON co.product_id = cp.id AND co.client_id = cp.client_id
             WHERE cp.client_id   = ?
               AND cp.name        = mp.name
               AND co.band_code   = mo.band_code
               AND co.name        = mo.name
               AND (co.colour <=> mo.colour)
               AND (co.supplier_name <=> mo.supplier_name)
       )
  ORDER BY mp.name, mo.band_code, mo.name, mo.colour, mo.supplier_name
SQL;

$mismatchSt = $pdo->prepare($mismatchSql);

$tenantResults = [];
foreach ($tenantRows as $t) {
    // Params in order:
    //   1. client_id for the GROUP_CONCAT sub-select
    //   2. master_id (mp.client_id =)
    //   3. prefix (mp.name LIKE)
    //   4. client_id for the NOT EXISTS check
    $mismatchSt->execute([
        (int) $t['id'],
        $masterClientId,
        $prefix . '%',
        (int) $t['id'],
    ]);
    $tenantResults[] = [
        'id'         => (int) $t['id'],
        'name'       => (string) $t['company_name'],
        'mismatches' => $mismatchSt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

$totalMismatches = array_sum(array_map(static fn ($r) => count($r['mismatches']), $tenantResults));

$activeNav = 'push-updates';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit: fabric suppliers &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        table.audit { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        table.audit th, table.audit td {
            padding: 0.4375rem 0.625rem; text-align: left;
            border-bottom: 1px solid var(--bg-subtle-2);
        }
        table.audit th {
            background: var(--bg-subtle); font-size: 0.6875rem;
            text-transform: uppercase; letter-spacing: 0.05em;
            color: var(--text-faint); font-weight: 600;
        }
        table.audit td.master { color: #065f46; font-weight: 600; }
        table.audit td.client { color: #991b1b; font-weight: 600; }
        table.audit td.null   { color: var(--text-faint); font-style: italic; }
        .tenant-card {
            background: #fff; border: 1px solid var(--border);
            border-radius: 10px; padding: 0.875rem 1.125rem;
            margin-bottom: 0.875rem;
        }
        .tenant-card h2 { margin: 0; font-size: 1rem; color: #1f3b5b; }
        .tenant-card.clean { border-color: #a7f3d0; background: #ecfdf5; }
        .tenant-card.clean .clean-msg {
            color: #065f46; font-size: 0.875rem; margin-top: 0.25rem;
        }
        .summary-pill {
            display: inline-block; background: #fee2e2; color: #991b1b;
            padding: 0.125rem 0.625rem; border-radius: 999px;
            font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em;
            margin-left: 0.5rem;
        }
        .summary-pill.zero {
            background: #d1fae5; color: #065f46;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Audit: fabric supplier mismatches</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/push-updates.php">&larr; Push updates</a>
                    &middot; read-only audit of fallout from the 5-vs-6 field push bug
                </p>
            </div>
        </div>

        <section class="section" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.75rem 1rem">
            <p style="margin:0;color:#0c4a6e;font-size:0.875rem;line-height:1.55">
                <strong>What this lists:</strong> for every Beverley-prefixed fabric
                on the master, the tenant rows where the client does <em>not</em>
                have an exact match on
                <code>(band, supplier, name, colour)</code>. The right-hand column
                shows what suppliers the client has for the same fabric — either a
                different brand (e.g. Eclipse vs. Market Place — same fabric, two
                supplier collections; totally legitimate) or <code>(not stocked)</code>
                if they don't carry it at all.
                <br>
                <strong>Earlier version was misleading.</strong> The previous query
                cross-joined and reported every supplier-pairing as a mismatch even
                when both sides legitimately stocked both supplier versions. The
                rewrite uses a <code>NOT EXISTS</code> check on the full unique key
                so a tenant who has both Eclipse and Market Place versions of a
                fabric now shows <em>zero</em> mismatches for it.
                <br>
                <strong>What this does NOT do:</strong> change anything. Pure
                read-only. After a re-push from
                <a href="/master-admin/push-updates.php" style="color:#0c4a6e;text-decoration:underline">Push updates</a>,
                this is the catalogue-divergence dashboard — useful long-term to
                see who's missing what.
            </p>
        </section>

        <section class="section">
            <p style="font-size:1rem;color:var(--text-secondary)">
                <strong>Total mismatches across <?= count($tenantResults) ?> active tenant<?= count($tenantResults) === 1 ? '' : 's' ?>:</strong>
                <span class="summary-pill <?= $totalMismatches === 0 ? 'zero' : '' ?>">
                    <?= (int) $totalMismatches ?>
                </span>
            </p>
        </section>

        <?php foreach ($tenantResults as $tr):
            $isClean = empty($tr['mismatches']);
        ?>
            <div class="tenant-card <?= $isClean ? 'clean' : '' ?>">
                <h2>
                    <?= e($tr['name']) ?>
                    <span class="summary-pill <?= $isClean ? 'zero' : '' ?>">
                        <?= count($tr['mismatches']) ?>
                        mismatch<?= count($tr['mismatches']) === 1 ? '' : 'es' ?>
                    </span>
                </h2>

                <?php if ($isClean): ?>
                    <div class="clean-msg">
                        &check; All matched fabrics have the same supplier as the master.
                    </div>
                <?php else: ?>
                    <div style="margin-top:0.625rem;overflow-x:auto">
                        <table class="audit">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Band</th>
                                    <th>Fabric</th>
                                    <th>Colour</th>
                                    <th>Master supplier (missing on client)</th>
                                    <th>Client's other suppliers for this fabric</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tr['mismatches'] as $m):
                                    $clientSuppliers = (string) ($m['client_suppliers'] ?? '');
                                ?>
                                    <tr>
                                        <td><?= e((string) $m['product_name']) ?></td>
                                        <td><?= e((string) $m['band_code']) ?></td>
                                        <td><?= e((string) $m['fabric_name']) ?></td>
                                        <td>
                                            <?php if ($m['colour'] === null || $m['colour'] === ''): ?>
                                                <span class="null">(none)</span>
                                            <?php else: ?>
                                                <?= e((string) $m['colour']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?= $m['master_supplier'] === null ? 'null' : 'master' ?>">
                                            <?= $m['master_supplier'] === null
                                                ? '(none)'
                                                : e((string) $m['master_supplier']) ?>
                                        </td>
                                        <td class="<?= $clientSuppliers === '' ? 'null' : 'client' ?>">
                                            <?= $clientSuppliers === ''
                                                ? '(not stocked)'
                                                : e($clientSuppliers) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </main>
</div>
</body>
</html>
