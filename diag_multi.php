<?php
declare(strict_types=1);
// READ-ONLY probe for the multiple-blinds-in-one-fascia mechanism. Delete after.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

echo "== product 5 extras matching 'multiple' (incl. inactive/hidden) ==\n";
$q = $pdo->prepare("SELECT id, name, code, active FROM product_extras WHERE product_id = 5 AND (name LIKE '%ultiple%' OR code LIKE '%multiple%')");
$q->execute();
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  #{$r['id']} active={$r['active']} code=" . ($r['code'] ?? '(null)') . " name={$r['name']}\n";
    $c = $pdo->prepare("SELECT id, label FROM product_extra_choices WHERE product_extra_id = ?");
    $c->execute([(int) $r['id']]);
    foreach ($c->fetchAll(PDO::FETCH_ASSOC) as $ch) echo "      choice #{$ch['id']} {$ch['label']}\n";
}

echo "\n== allowance tables (factory {$MASTER}) ==\n";
$hasClient = false; try { $pdo->query('SELECT client_id FROM allowance_rows LIMIT 0'); $hasClient = true; } catch (Throwable $e) {}
$scope = $hasClient ? ' WHERE client_id = ' . (int) $MASTER : '';
foreach ($pdo->query("SELECT DISTINCT table_name FROM allowance_rows{$scope} ORDER BY table_name") as $t) {
    echo "  {$t['table_name']}\n";
}
foreach (['roller_multiple', 'roller_fascia_join', 'roller_pole', 'roller_fascia'] as $tn) {
    echo "\n  [$tn] rows:\n";
    $r = $pdo->prepare('SELECT keys_display, value FROM allowance_rows WHERE table_name = ?' . ($hasClient ? ' AND client_id = ' . (int) $MASTER : '') . ' ORDER BY seq, id');
    $r->execute([$tn]);
    $rows = $r->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) echo "    (none)\n";
    foreach ($rows as $x) echo "    {$x['keys_display']} = {$x['value']}\n";
}

echo "\n== Tube_Cut / Fabric_W decision tables (product 5) ==\n";
$bv = $pdo->prepare("SELECT name, columns_json, rows_json FROM build_variables WHERE product_id = 5 AND name IN ('Tube_Cut','Fabric_W','Fascia_Cut') ORDER BY seq");
$bv->execute();
foreach ($bv->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $cols = json_decode((string) $r['columns_json'], true) ?: [];
    $rows = json_decode((string) $r['rows_json'], true) ?: [];
    $labs = array_map(static fn ($c) => (string) ($c['label'] ?? ($c['ref'] ?? '?')), $cols);
    echo "\n  {$r['name']}: cols [" . implode(' | ', $labs) . "]\n";
    foreach ($rows as $row) {
        $cells = array_map(static fn ($c) => $c === '' ? '·' : $c, (array) ($row['cells'] ?? []));
        echo "     [" . implode(' | ', $cells) . "]  ->  " . (string) ($row['result'] ?? '') . "\n";
    }
}
