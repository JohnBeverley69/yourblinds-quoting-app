<?php
declare(strict_types=1);
/** TEMP read-only diagnostic: dump an order's line data + computed build vars. Delete after use. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/_partials/build_eval.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$qnum = trim((string) ($_GET['q'] ?? 'ABC-2026-0001'));
$pdo  = db();

$q = $pdo->prepare('SELECT id, client_id, quote_number, status FROM quotes WHERE quote_number = ? ORDER BY id DESC LIMIT 1');
$q->execute([$qnum]);
$quote = $q->fetch(PDO::FETCH_ASSOC);
if (!$quote) { echo "No quote '$qnum'.\n"; exit; }
echo "Quote {$quote['quote_number']} id={$quote['id']} client={$quote['client_id']} status={$quote['status']}\n\n";

$li = $pdo->prepare(
    "SELECT qi.id, qi.line_no, qi.quantity, qi.product_id, qi.system_id, qi.width_mm, qi.drop_mm,
            qi.product_name_snapshot, qi.system_name_snapshot, qi.fabric_name_snapshot, qi.fabric_colour_snapshot,
            qi.room_name, qi.notes, p.name AS pname, COALESCE(p.source_product_id, p.id) AS master_pid,
            COALESCE(NULLIF(p.source_client_id,0), p.client_id) AS owner
       FROM quote_items qi JOIN products p ON p.id = qi.product_id
      WHERE qi.quote_id = ? ORDER BY qi.line_no, qi.id"
);
$li->execute([(int) $quote['id']]);
foreach ($li->fetchAll(PDO::FETCH_ASSOC) as $ln) {
    echo "LINE {$ln['line_no']}: product='{$ln['pname']}' (id {$ln['product_id']} master {$ln['master_pid']} owner {$ln['owner']})\n";
    echo "  width_mm=" . var_export($ln['width_mm'], true) . " drop_mm=" . var_export($ln['drop_mm'], true)
       . " qty=" . var_export($ln['quantity'], true) . "\n";
    echo "  snapshots: product='{$ln['product_name_snapshot']}' system='{$ln['system_name_snapshot']}'"
       . " fabric='{$ln['fabric_name_snapshot']}' colour='{$ln['fabric_colour_snapshot']}'\n";

    // Extras for this line.
    $ex = $pdo->prepare("SELECT extra_name_snapshot, choice_label_snapshot, user_value FROM quote_item_extras WHERE quote_item_id = ? ORDER BY id");
    $ex->execute([(int) $ln['id']]);
    $optSel = ['system' => (string) $ln['system_name_snapshot']];
    foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $r) {
        echo "  extra: {$r['extra_name_snapshot']} = '{$r['choice_label_snapshot']}'" . ($r['user_value'] !== null ? " (uv={$r['user_value']})" : '') . "\n";
        $optSel[strtolower(trim((string) $r['extra_name_snapshot']))] = (string) $r['choice_label_snapshot'];
    }

    $numVars = ['Width' => (float) $ln['width_mm'], 'Drop' => (float) $ln['drop_mm'], 'Quantity' => (float) $ln['quantity']];
    $eval = build_evaluate($pdo, (int) $ln['master_pid'], $numVars, $optSel);
    echo "  build_evaluate vars: " . json_encode($eval['vars'] ?? []) . "\n\n";

    // Worksheet template field sources for this product's master.
    $t = $pdo->prepare('SELECT layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id LIMIT 1');
    $t->execute([(int) $ln['master_pid']]);
    $tpl = json_decode((string) ($t->fetchColumn() ?: ''), true);
    echo "  TEMPLATE one_per_line=" . var_export($tpl['one_per_line'] ?? null, true) . "\n";
    echo "  HEADER fields: " . json_encode(array_map(static fn($f)=>[$f['source']??'',$f['caption']??''], $tpl['header'] ?? [])) . "\n";
    foreach (($tpl['labels'] ?? []) as $li => $lab) {
        echo "  LABEL[$li] '" . ($lab['title'] ?? '') . "': "
           . json_encode(array_map(static fn($f)=>[$f['source']??'',$f['caption']??''], $lab['fields'] ?? [])) . "\n";
    }
}
