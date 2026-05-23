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

// For each tenant, find all fabric mismatches against the master.
//
// Match key: same product name + same band + same fabric name +
// same colour (null-safe). The thing we're checking equality on:
// supplier_name (null-safe).
//
// Returns one row per mismatched fabric with both supplier_names
// side by side so the operator can eyeball "which one's right".
$mismatchSql = <<<'SQL'
    SELECT
        mp.name                AS product_name,
        mo.band_code           AS band_code,
        mo.name                AS fabric_name,
        mo.colour              AS colour,
        mo.supplier_name       AS master_supplier,
        co.id                  AS client_fabric_id,
        co.supplier_name       AS client_supplier
      FROM products mp
      JOIN product_options mo
        ON mo.product_id = mp.id AND mo.client_id = mp.client_id
      JOIN products cp
        ON cp.name = mp.name AND cp.client_id = ?
      JOIN product_options co
        ON co.product_id = cp.id
       AND co.client_id  = cp.client_id
       AND co.band_code  = mo.band_code
       AND co.name       = mo.name
       AND (co.colour <=> mo.colour)
     WHERE mp.client_id = ?
       AND mp.name LIKE ?
       AND NOT (co.supplier_name <=> mo.supplier_name)
  ORDER BY mp.name, mo.band_code, mo.name, mo.colour
SQL;

$mismatchSt = $pdo->prepare($mismatchSql);

$tenantResults = [];
foreach ($tenantRows as $t) {
    $mismatchSt->execute([(int) $t['id'], $masterClientId, $prefix . '%']);
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
    <link rel="stylesheet" href="/app.css">
    <style>
        table.audit { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        table.audit th, table.audit td {
            padding: 0.4375rem 0.625rem; text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        table.audit th {
            background: #f9fafb; font-size: 0.6875rem;
            text-transform: uppercase; letter-spacing: 0.05em;
            color: #6b7280; font-weight: 600;
        }
        table.audit td.master { color: #065f46; font-weight: 600; }
        table.audit td.client { color: #991b1b; font-weight: 600; }
        table.audit td.null   { color: #9ca3af; font-style: italic; }
        .tenant-card {
            background: #fff; border: 1px solid #e5e7eb;
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
                <strong>What this checks:</strong> for every Beverley-prefixed product,
                for every fabric on the master with a (band, name, colour) match on a
                client, is the <code>supplier_name</code> the same?
                <br>
                <strong>What to do with mismatches:</strong> eyeball the rows. If a
                client's supplier looks wrong (almost certainly a victim of the bug),
                re-push from
                <a href="/master-admin/push-updates.php" style="color:#0c4a6e;text-decoration:underline">Push updates</a>
                — the fix in commit <code>1cc2084</code> now matches on the full
                unique key, so the next push will correctly identify and update the
                affected rows.
                <br>
                <strong>What this does NOT do:</strong> change anything. Pure read-only.
                Run again after a re-push to confirm the count's dropped to zero.
            </p>
        </section>

        <section class="section">
            <p style="font-size:1rem;color:#374151">
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
                                    <th>Master supplier</th>
                                    <th>Client supplier</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tr['mismatches'] as $m): ?>
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
                                        <td class="<?= $m['client_supplier'] === null ? 'null' : 'client' ?>">
                                            <?= $m['client_supplier'] === null
                                                ? '(none)'
                                                : e((string) $m['client_supplier']) ?>
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
