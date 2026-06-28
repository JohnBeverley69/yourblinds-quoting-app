<?php
declare(strict_types=1);

/**
 * One-off cleanup: remove the stale half-built "Arena …" products created
 * during the first manual rebuild attempt, so the catalogue is rebuilt
 * cleanly via the seeder scripts (seed_arena_*.php) instead.
 *
 * Scope is locked down three ways so this can NEVER touch the live "Bev …"
 * catalogue or the new seeder-built product:
 *   1. An explicit allow-list of the 28 stale product ids.
 *   2. A name guard — only rows whose name starts with "Arena ".
 *   3. id <> 5754 (the new clean "Arena Vertical" stays).
 *   4. client_id scoping (current tenant only), like delete.php.
 *
 * Children (systems / options / extras / price_tables + rows) are removed
 * automatically by ON DELETE CASCADE. Idempotent: re-running finds nothing
 * left and deletes 0. Run via web (super-admin): /cleanup_arena_stale.php
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$user     = current_user();
$clientId = (int) ($user['client_id'] ?? 0);
if ($clientId <= 0) {
    echo "Could not determine your client_id — are you logged in?\n";
    exit(1);
}

// The stale "Arena …" batch (created ~5 hrs before this cleanup). Explicit
// ids — NOT a range — so nothing unexpected is caught.
$STALE_IDS = [
    5653, 5657, 5659, 5660, 5661, 5662, 5663, 5664, 5665, 5666,
    5667, 5668, 5669, 5670, 5671, 5672, 5673, 5674, 5675, 5676,
    5677, 5678, 5679, 5680, 5681, 5682, 5683, 5684,
];
$KEEP_ID = 5754;   // the new clean "Arena Vertical" — never delete

echo "Cleanup: stale Arena products in client_id {$clientId}\n";
echo str_repeat('=', 60) . "\n\n";

// Resolve exactly what WILL be deleted, applying every guard.
$ph = implode(',', array_fill(0, count($STALE_IDS), '?'));
$sel = $pdo->prepare(
    "SELECT id, name FROM products
      WHERE id IN ($ph)
        AND client_id = ?
        AND id <> ?
        AND name LIKE 'Arena %'
   ORDER BY id"
);
$sel->execute(array_merge($STALE_IDS, [$clientId, $KEEP_ID]));
$targets = $sel->fetchAll(PDO::FETCH_ASSOC);

if (!$targets) {
    echo "Nothing to delete — already clean.\n";
    exit(0);
}

echo "Will delete " . count($targets) . " product(s):\n";
foreach ($targets as $t) {
    echo sprintf("  #%-5d %s\n", (int) $t['id'], (string) $t['name']);
}
echo "\n";

$delIds = array_map(static fn ($t) => (int) $t['id'], $targets);
$dph    = implode(',', array_fill(0, count($delIds), '?'));

$pdo->beginTransaction();
try {
    // Same guards on the DELETE itself — belt and braces.
    $del = $pdo->prepare(
        "DELETE FROM products
          WHERE id IN ($dph)
            AND client_id = ?
            AND id <> ?
            AND name LIKE 'Arena %'"
    );
    $del->execute(array_merge($delIds, [$clientId, $KEEP_ID]));
    $n = $del->rowCount();
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "FAILED (rolled back): " . $e->getMessage() . "\n";
    exit(1);
}

echo str_repeat('=', 60) . "\n";
echo "Deleted {$n} stale Arena product(s). Children removed via ON DELETE CASCADE.\n";
echo "Kept: your live 'Bev …' catalogue and 'Arena Vertical' (#{$KEEP_ID}).\n";
