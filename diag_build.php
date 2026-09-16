<?php
declare(strict_types=1);
// READ-ONLY diagnostic: for each Beverley line on an order, dump the option
// selections build_eval sees + the computed build-variable results (ok/value/
// error per var). /diag_build.php?order=N (super-admin). Delete after.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
require_once __DIR__ . '/_partials/build_eval.php';
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL);

$pdo = db();
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;
$qid = (int) ($_GET['order'] ?? 0);

$li = $pdo->prepare(
    "SELECT qi.id, qi.line_no, qi.width_mm, qi.drop_mm, qi.quantity, qi.fascia_group,
            COALESCE(p.source_product_id, p.id) AS master_product_id
       FROM quote_items qi JOIN products p ON p.id = qi.product_id
      WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
   ORDER BY qi.line_no, qi.id"
);
$li->execute([$qid, $MASTER]);
$lines = $li->fetchAll(PDO::FETCH_ASSOC);
echo "Order {$qid}: " . count($lines) . " Beverley line(s)\n\n";

$ex = $pdo->prepare("SELECT extra_name_snapshot, choice_label_snapshot, user_value FROM quote_item_extras WHERE quote_item_id = ? ORDER BY id");

// Dump the product's build variable column labels once.
$bv = $pdo->prepare('SELECT name, columns_json FROM build_variables WHERE product_id = ? ORDER BY seq, id');

foreach ($lines as $ln) {
    echo "── Line {$ln['line_no']} (item {$ln['id']}, {$ln['width_mm']}×{$ln['drop_mm']}, group " . ($ln['fascia_group'] ?: '-') . ")\n";
    $ex->execute([(int) $ln['id']]);
    $byName = []; $userVal = [];
    foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $nm = strtolower(trim((string) $r['extra_name_snapshot']));
        $byName[$nm] = (string) $r['choice_label_snapshot'];
        if (is_numeric($r['user_value'] ?? null)) $userVal[$nm] = (float) $r['user_value'];
        echo "    opt: {$r['extra_name_snapshot']} = {$r['choice_label_snapshot']}" .
             (is_numeric($r['user_value'] ?? null) ? " (uv={$r['user_value']})" : '') . "\n";
    }
    $fasciaWidth = (($userVal['fascia width'] ?? 0) > 0) ? (float) $userVal['fascia width'] : (float) $ln['width_mm'];
    $optSel = array_merge(['system' => ''], $byName);
    $numVars = ['Width' => (float) $ln['width_mm'], 'Drop' => (float) $ln['drop_mm'],
                'Fit_height' => 0.0, 'Quantity' => (float) $ln['quantity'], 'Fascia_Width' => $fasciaWidth];
    $mpid = (int) $ln['master_product_id'];
    $eval = build_evaluate($pdo, $mpid, $numVars, $optSel);
    foreach ($eval['results'] as $res) {
        printf("      %-14s %s %s\n", $res['name'], $res['ok'] ? '=' : 'X', (string) $res['value']);
    }
    echo "\n";
}

echo "== build_variables columns (product 5 master) ==\n";
$bv->execute([5]);
foreach ($bv->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $cols = json_decode((string) $r['columns_json'], true) ?: [];
    $labs = array_map(static fn ($c) => (string) ($c['label'] ?? ($c['ref'] ?? '?')), $cols);
    echo "  {$r['name']}: [" . implode(' | ', $labs) . "]\n";
}
