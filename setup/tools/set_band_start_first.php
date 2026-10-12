<?php
declare(strict_types=1);

/**
 * Turn on "Start on the first band instead of All bands" for every product
 * of ONE company, and make it the default for products created from now on.
 *
 * John, 2026-10-12: nearly every product wants the band box to open on its
 * first band (the top of the Price tables order) rather than "All bands", and
 * ticking it product by product is a chore. The tick stays per product, so it
 * can still be switched off on any product afterwards.
 *
 *   1. products.band_start_first now DEFAULTS to 1, so a new product starts
 *      ticked. Duplicate, the catalogue push and seeding copy the source's own
 *      value as before.
 *   2. The chosen company's products that are unticked get ticked.
 *
 * Per company, with no crossover: only ?client=N is touched (default: the main
 * factory). A company with linked copies (e.g. ABC) picks up the master's
 * tick on its next catalogue push, or run this tool again with its id.
 *
 * Super-admin, web-runnable: /setup/tools/set_band_start_first.php[?client=N]
 *   (add &apply=1 to actually change — without it, it only reports)
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$args   = PHP_SAPI === 'cli' ? ($argv ?? []) : [];
$apply  = PHP_SAPI === 'cli' ? in_array('--apply', $args, true) : !empty($_GET['apply']);
$client = PHP_SAPI === 'cli'
    ? (int) (preg_replace('/^--client=/', '', (string) (current(preg_grep('/^--client=\d+$/', $args)) ?: '')) ?: 0)
    : (int) ($_GET['client'] ?? 0);
if ($client <= 0) $client = function_exists('factory_client_id') ? (int) factory_client_id() : 3;

$exists = $pdo->prepare('SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
$exists->execute(['products', 'band_start_first']);
$default = $exists->fetchColumn();
if ($default === false) {
    echo "products.band_start_first is missing — run setup/migrations/migrate_band_start_first.php first.\n";
    exit;
}

$name = $pdo->prepare('SELECT company_name FROM clients WHERE id = ?');
$name->execute([$client]);
echo 'Company: ' . ($name->fetchColumn() ?: '?') . " (client $client)\n";
echo $apply ? "APPLYING\n\n" : "Report only — add &apply=1 to change.\n\n";

// 1. New products start ticked.
if (trim((string) $default, "'") === '1') {
    echo "New products already start ticked.\n";
} elseif ($apply) {
    $pdo->exec('ALTER TABLE products ALTER COLUMN band_start_first SET DEFAULT 1');
    echo "New products now start ticked.\n";
} else {
    echo "Would make new products start ticked.\n";
}

// 2. This company's unticked products.
$st = $pdo->prepare('SELECT id, name, active FROM products WHERE client_id = ? AND COALESCE(band_start_first, 0) = 0 ORDER BY name');
$st->execute([$client]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
echo "\n" . count($rows) . " product(s) not ticked:\n";
foreach ($rows as $r) echo '  #' . $r['id'] . '  ' . $r['name'] . ((int) $r['active'] ? '' : '  (inactive)') . "\n";

if ($apply && $rows) {
    $up = $pdo->prepare('UPDATE products SET band_start_first = 1 WHERE client_id = ? AND COALESCE(band_start_first, 0) = 0');
    $up->execute([$client]);
    echo "\nTicked " . $up->rowCount() . " product(s).\n";
}
echo "\nDone. To switch one off again: Products → edit product → untick it under Band label.\n";
