<?php
declare(strict_types=1);

/**
 * Diagnostic: dump raw band_code data for a product.
 *
 * Usage: /master-admin/diag-product-bands.php?product_id=52
 *
 * Shows the exact contents of products.band_code across both
 * tables that feed the Fabric form's "known bands" autocomplete,
 * so we can see whether the values are NULL, empty, mis-scoped
 * (different product_id than expected), or just genuinely sparse.
 *
 * One-off testing tool — safe to leave in master-admin/ but not
 * referenced from any nav. Super-admin gated.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$productId = (int) ($_GET['product_id'] ?? 0);

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Diag: product bands</title>
    <style>
        body { font: 14px/1.5 system-ui, sans-serif; max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }
        h1 { margin-top: 0; }
        form { margin-bottom: 1.5rem; padding: 0.75rem; background: #f4f6fa; border-radius: 6px; }
        table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
        th, td { border: 1px solid #cbd5e1; padding: 0.5rem 0.75rem; text-align: left; vertical-align: top; }
        th { background: #f8fafc; }
        code { background: #f4f6fa; padding: 1px 4px; border-radius: 3px; }
        .null { color: #b91c1c; font-style: italic; }
        .empty { color: #b91c1c; font-style: italic; }
        h2 { margin-top: 2rem; font-size: 1rem; color: #1f3b5b; border-bottom: 2px solid #cbd5e1; padding-bottom: 0.25rem; }
        .summary { background: #ecfdf5; border: 1px solid #6ee7b7; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; }
        .summary.bad { background: #fef2f2; border-color: #fca5a5; }
    </style>
</head>
<body>

<h1>Diagnostic: product bands</h1>
<form method="get">
    <label>Product ID: <input type="number" name="product_id" value="<?= (int) $productId ?>" autofocus></label>
    <button type="submit">Inspect</button>
</form>

<?php if ($productId <= 0): ?>
    <p>Enter a product ID above.</p>
</body></html><?php exit;
endif;

$pdo = db();
$user = current_user();
$clientId = $user['client_id'];

// 1. Confirm the product exists & belongs to this tenant.
$pStmt = $pdo->prepare('SELECT id, client_id, name, option_label FROM products WHERE id = ?');
$pStmt->execute([$productId]);
$product = $pStmt->fetch();

if (!$product) {
    echo '<p style="color:#b91c1c">Product ' . (int) $productId . ' not found.</p></body></html>';
    exit;
}
?>

<h2>Product</h2>
<table>
    <tr><th>id</th><td><?= (int) $product['id'] ?></td></tr>
    <tr><th>client_id</th><td><?= (int) $product['client_id'] ?></td></tr>
    <tr><th>name</th><td><?= e((string) $product['name']) ?></td></tr>
    <tr><th>option_label</th><td><?= e((string) $product['option_label']) ?></td></tr>
</table>

<?php
$prodClientId = (int) $product['client_id'];

// 2. Raw product_options for this product (fabrics).
$optStmt = $pdo->prepare(
    'SELECT id, client_id, product_id, band_code, name, colour, active
       FROM product_options WHERE product_id = ? ORDER BY id'
);
$optStmt->execute([$productId]);
$options = $optStmt->fetchAll();
?>

<h2>product_options (fabrics) — <?= count($options) ?> row<?= count($options) === 1 ? '' : 's' ?></h2>
<?php if (!$options): ?>
    <p><em>None.</em></p>
<?php else: ?>
    <table>
        <tr><th>id</th><th>client_id</th><th>product_id</th><th>band_code</th><th>name</th><th>colour</th><th>active</th></tr>
        <?php foreach ($options as $o): ?>
            <tr>
                <td><?= (int) $o['id'] ?></td>
                <td><?= (int) $o['client_id'] ?>
                    <?= (int) $o['client_id'] !== $prodClientId ? ' <span class="null">(MISMATCH)</span>' : '' ?></td>
                <td><?= (int) $o['product_id'] ?></td>
                <td>
                    <?php if ($o['band_code'] === null): ?><span class="null">NULL</span>
                    <?php elseif ($o['band_code'] === ''): ?><span class="empty">(empty)</span>
                    <?php else: ?><code><?= e((string) $o['band_code']) ?></code> (<?= strlen((string) $o['band_code']) ?> chars)
                    <?php endif; ?>
                </td>
                <td><?= e((string) $o['name']) ?></td>
                <td><?= e((string) ($o['colour'] ?? '')) ?></td>
                <td><?= (int) $o['active'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php
// 3. Systems for this product.
$sysStmt = $pdo->prepare(
    'SELECT id, client_id, product_id, name FROM product_systems WHERE product_id = ? ORDER BY id'
);
$sysStmt->execute([$productId]);
$systems = $sysStmt->fetchAll();
?>

<h2>product_systems — <?= count($systems) ?> row<?= count($systems) === 1 ? '' : 's' ?></h2>
<?php if (!$systems): ?>
    <p><em>None.</em></p>
<?php else: ?>
    <table>
        <tr><th>id</th><th>client_id</th><th>product_id</th><th>name</th></tr>
        <?php foreach ($systems as $s): ?>
            <tr>
                <td><?= (int) $s['id'] ?></td>
                <td><?= (int) $s['client_id'] ?>
                    <?= (int) $s['client_id'] !== $prodClientId ? ' <span class="null">(MISMATCH)</span>' : '' ?></td>
                <td><?= (int) $s['product_id'] ?></td>
                <td><?= e((string) $s['name']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php
// 4. Raw price_tables for this product.
$ptStmt = $pdo->prepare(
    'SELECT id, client_id, product_id, system_id, band_code, name, active
       FROM price_tables WHERE product_id = ? ORDER BY system_id, id'
);
$ptStmt->execute([$productId]);
$tables = $ptStmt->fetchAll();
?>

<h2>price_tables (scoped by product_id = <?= (int) $productId ?>) — <?= count($tables) ?> row<?= count($tables) === 1 ? '' : 's' ?></h2>
<?php if (!$tables): ?>
    <p><em>None match. <strong>That's the bug if the systems page shows price tables here.</strong></em></p>
<?php else: ?>
    <table>
        <tr><th>id</th><th>client_id</th><th>product_id</th><th>system_id</th><th>band_code</th><th>name</th><th>active</th></tr>
        <?php foreach ($tables as $t): ?>
            <tr>
                <td><?= (int) $t['id'] ?></td>
                <td><?= (int) $t['client_id'] ?>
                    <?= (int) $t['client_id'] !== $prodClientId ? ' <span class="null">(MISMATCH)</span>' : '' ?></td>
                <td><?= (int) $t['product_id'] ?></td>
                <td><?= (int) $t['system_id'] ?></td>
                <td>
                    <?php if ($t['band_code'] === null): ?><span class="null">NULL</span>
                    <?php elseif ($t['band_code'] === ''): ?><span class="empty">(empty)</span>
                    <?php else: ?><code><?= e((string) $t['band_code']) ?></code> (<?= strlen((string) $t['band_code']) ?> chars)
                    <?php endif; ?>
                </td>
                <td><?= e((string) ($t['name'] ?? '')) ?></td>
                <td><?= (int) $t['active'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php
// 5. Cross-check: price_tables linked via system_id (in case product_id
// is wrong but the system_id is correct). This is the "what the
// systems page sees" view.
if ($systems) {
    $sysIds = array_map(static fn ($s) => (int) $s['id'], $systems);
    $ph     = implode(',', array_fill(0, count($sysIds), '?'));
    $ptByS  = $pdo->prepare(
        "SELECT id, client_id, product_id, system_id, band_code, name, active
           FROM price_tables WHERE system_id IN ($ph) ORDER BY system_id, id"
    );
    $ptByS->execute($sysIds);
    $tablesBySys = $ptByS->fetchAll();
?>

<h2>price_tables (scoped by system_id IN [<?= e(implode(', ', $sysIds)) ?>]) — <?= count($tablesBySys) ?> row<?= count($tablesBySys) === 1 ? '' : 's' ?></h2>
<p style="font-size:0.875rem;color:#475569">
    This is what the Systems &rarr; Price tables UI shows you. If the count here is bigger than the
    count under the product_id query above, then some price tables have the <code>product_id</code>
    column out of sync with their parent system &mdash; that's the bug.
</p>
    <?php if (!$tablesBySys): ?>
        <p><em>None.</em></p>
    <?php else: ?>
        <table>
            <tr><th>id</th><th>client_id</th><th>product_id</th><th>system_id</th><th>band_code</th><th>name</th><th>active</th></tr>
            <?php foreach ($tablesBySys as $t): ?>
                <?php $mismatch = (int) $t['product_id'] !== $productId; ?>
                <tr<?= $mismatch ? ' style="background:#fef2f2"' : '' ?>>
                    <td><?= (int) $t['id'] ?></td>
                    <td><?= (int) $t['client_id'] ?></td>
                    <td><?= (int) $t['product_id'] ?>
                        <?= $mismatch ? ' <span class="null">(should be ' . (int) $productId . ')</span>' : '' ?></td>
                    <td><?= (int) $t['system_id'] ?></td>
                    <td>
                        <?php if ($t['band_code'] === null): ?><span class="null">NULL</span>
                        <?php elseif ($t['band_code'] === ''): ?><span class="empty">(empty)</span>
                        <?php else: ?><code><?= e((string) $t['band_code']) ?></code> (<?= strlen((string) $t['band_code']) ?> chars)
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) ($t['name'] ?? '')) ?></td>
                    <td><?= (int) $t['active'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php } ?>

<?php
// 6. What the options.php query actually returns.
$kStmt = $pdo->prepare(
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
$kStmt->execute([$productId, $prodClientId, $productId, $prodClientId]);
$known = $kStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<h2>options.php "known bands" query — <?= count($known) ?> row<?= count($known) === 1 ? '' : 's' ?></h2>
<p style="font-size:0.875rem;color:#475569">
    This is the literal SQL that powers the Band dropdown / chip row on the Fabrics page.
    Scoped by <code>product_id = <?= (int) $productId ?></code> AND
    <code>client_id = <?= $prodClientId ?></code>.
</p>
<?php if (!$known): ?>
    <p><em>Nothing.</em></p>
<?php else: ?>
    <ol>
        <?php foreach ($known as $b): ?><li><code><?= e((string) $b) ?></code></li><?php endforeach; ?>
    </ol>
<?php endif; ?>

</body></html>
