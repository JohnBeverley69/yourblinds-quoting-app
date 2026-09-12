<?php
declare(strict_types=1);
/* TEMP fix: BM "Perfect Fit" discount was mapped onto ALL four PF products, but it
 * only belongs on Perfect Fit PLEATED. Remove the migrated base discount from
 * Bev PF Roller / PF Venetian / PF Shutter across all accounts; keep PF Pleated.
 * Dry-run default; ?go=1 writes. git rm after. */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;
$dry = (($_GET['go'] ?? '') !== '1');
if ($dry) echo "DRY RUN — add ?go=1 to write.\n\n";

$clear = ['Bev PF Roller', 'Bev PF Venetian', 'Bev PF Shutter'];   // NOT Bev PF Pleated
$mids = [];
foreach ($clear as $n) {
    $s = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = ? LIMIT 1");
    $s->execute([$MASTER, $n]);
    $id = (int) ($s->fetchColumn() ?: 0);
    if ($id) $mids[$n] = $id; else echo "  ! master product not found: {$n}\n";
}
if (!$mids) { echo "No PF master products found — nothing to do.\n"; exit; }
$in = implode(',', array_map('intval', array_values($mids)));

// The account (mirrored) products for those masters.
$prod = $pdo->query("SELECT id FROM products WHERE source_product_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
if (!$prod) { echo "No mirrored PF products found.\n"; exit; }
$pin = implode(',', array_map('intval', $prod));

$sql = "FROM trade_discounts
         WHERE notes = 'Migrated 2026 trade discount'
           AND system_id IS NULL AND band_code IS NULL AND extra_id IS NULL AND choice_id IS NULL
           AND product_id IN ($pin)";
$n = (int) $pdo->query("SELECT COUNT(*) $sql")->fetchColumn();

if ($dry) {
    echo "PF products to clear (master ids): " . implode(', ', array_map(fn($k,$v)=>"$k#$v", array_keys($mids), $mids)) . "\n";
    echo "WOULD delete {$n} migrated discount row(s) on PF Roller/Venetian/Shutter.\n";
    $kept = (int) $pdo->query("SELECT COUNT(*) FROM trade_discounts td JOIN products p ON p.id = td.product_id JOIN products m ON m.id = p.source_product_id WHERE m.name = 'Bev PF Pleated' AND td.notes = 'Migrated 2026 trade discount'")->fetchColumn();
    echo "PF Pleated discount rows kept: {$kept}.\n";
    exit;
}
$del = $pdo->prepare("DELETE $sql");
$del->execute();
echo "Deleted " . $del->rowCount() . " discount row(s) from PF Roller/Venetian/Shutter. PF Pleated untouched.\n";
