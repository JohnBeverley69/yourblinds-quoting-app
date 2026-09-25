<?php
declare(strict_types=1);

/**
 * Seed: make the roller Fascia_Cut honour a manual fascia width.
 *
 * The "Fascia Options" option now carries a number box (its length_input_label):
 * blank/0 = fit blind, a value = a manual fascia width (e.g. cut full width past
 * a skirting board while the blind is pulled in). worksheet-print.php exposes that
 * typed value to the build engine as the numeric variable `Fascia_Width`
 * (defaulting to the ordered Width when the box is blank).
 *
 * This rewrites the Fascia_Cut build variable's formulas from `Width + LOOKUP(...)`
 * to `Fascia_Width + LOOKUP(...)`, so the fascia extrusion is cut to the manual
 * width when given and to the blind width otherwise. Only Fascia_Cut is touched;
 * the roller_fascia allowances (the editable numbers) are unchanged.
 *
 * Idempotent (upsert). Run via web: /seed_roller_fascia_width.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Could not find product 'Bev Roller Blinds' for client {$MASTER}.\n"); }

$extraId = static function (string $name) use ($pdo, $productId, $MASTER): int {
    $q = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND name = ? LIMIT 1");
    $q->execute([$productId, $MASTER, $name]);
    return (int) $q->fetchColumn();
};
$fasciaId = $extraId('Fascia Options');
$fitId    = $extraId('Exact or Recess');
foreach (['Fascia Options' => $fasciaId, 'Exact or Recess' => $fitId] as $n => $id) {
    if ($id === 0) { exit("Missing option group '{$n}' on product {$productId}.\n"); }
}

$colFascia = ['ref' => 'extra:' . $fasciaId, 'label' => 'Fascia Options'];
$colFit    = ['ref' => 'extra:' . $fitId,    'label' => 'Exact or Recess'];
$LL_FASCIAS = ['LL 70mm Cassette', 'LL 40mm Cassette', 'Grip Fix Cassette'];

// Fascia_Cut off Fascia_Width (= typed manual width, else ordered Width).
$fasciaRows = [];
$fasciaRows[] = ['cells' => ['Senses', 'Recess'],     'result' => 'Fascia_Width + LOOKUP("roller_fascia", "senses", "recess")'];
$fasciaRows[] = ['cells' => ['Senses', 'Exact'],      'result' => 'Fascia_Width + LOOKUP("roller_fascia", "senses", "exact")'];
$fasciaRows[] = ['cells' => ['Senses', 'Cloth Size'], 'result' => 'Fascia_Width + LOOKUP("roller_fascia", "senses", "cloth")'];
foreach ($LL_FASCIAS as $f) {
    $fasciaRows[] = ['cells' => [$f, 'Recess'],     'result' => 'Fascia_Width + LOOKUP("roller_fascia", "ll", "recess")'];
    $fasciaRows[] = ['cells' => [$f, 'Exact'],      'result' => 'Fascia_Width + LOOKUP("roller_fascia", "ll", "exact")'];
    $fasciaRows[] = ['cells' => [$f, 'Cloth Size'], 'result' => 'Fascia_Width + LOOKUP("roller_fascia", "ll", "cloth")'];
}
$fasciaRows[] = ['cells' => ['', ''], 'result' => '""'];   // None / anything else → no fascia piece

$upVar = $pdo->prepare(
    "INSERT INTO build_variables (product_id, name, seq, columns_json, rows_json)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE seq = VALUES(seq), columns_json = VALUES(columns_json), rows_json = VALUES(rows_json)"
);
$upVar->execute([$productId, 'Fascia_Cut', 30,
    json_encode([$colFascia, $colFit], JSON_UNESCAPED_UNICODE),
    json_encode($fasciaRows, JSON_UNESCAPED_UNICODE)]);
echo "  build var Fascia_Cut (" . count($fasciaRows) . " rows) now off Fascia_Width\n";

echo "\nDone — Fascia_Cut on product {$productId} now honours a manual fascia width.\n";
echo "Fascia width box blank → Fascia_Width = ordered Width (unchanged). Typed → fascia cut + priced at that width.\n";
