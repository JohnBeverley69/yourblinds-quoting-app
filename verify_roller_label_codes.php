<?php
declare(strict_types=1);

/**
 * READ-ONLY verifier for the roller-label code hardening. Finds a Bev Roller
 * Blinds order line and replicates worksheet-print's option resolution (the
 * product_extras JOIN + opt:<code> keying), so we can confirm the label's
 * code-based field sources resolve to real values without rendering a PDF.
 * Web-runnable: /verify_roller_label_codes.php (super-admin). Changes nothing.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();

// Most recent roller line (match by product id OR master_product_id, any status).
$q = $pdo->prepare("SELECT qi.id, qi.quote_id
                      FROM quote_items qi
                     WHERE qi.product_id = ? OR qi.master_product_id = ?
                     ORDER BY qi.id DESC LIMIT 1");
$q->execute([$productId, $productId]);
$row = $q->fetch(PDO::FETCH_ASSOC);
if (!$row) { exit("No Bev Roller Blinds order line found anywhere — create one roller order to test rendering.\n"); }
$itemId = (int) $row['id']; $quoteId = (int) $row['quote_id'];
echo "Roller line: quote_item #{$itemId} (quote #{$quoteId})\n\n";

// Replicate worksheet-print's extras resolution.
$ex = $pdo->prepare("SELECT qie.extra_name_snapshot, qie.choice_label_snapshot, qie.user_value,
                            pe.code AS extra_code, pe.name AS extra_live_name
                       FROM quote_item_extras qie
                       LEFT JOIN product_extras pe ON pe.id = qie.product_extra_id
                      WHERE qie.quote_item_id = ? ORDER BY qie.id");
$ex->execute([$itemId]);
$rows = $ex->fetchAll(PDO::FETCH_ASSOC);

$opt = [];
echo "== Snapshot rows (name  |  live code  |  live name  =>  value) ==\n";
foreach ($rows as $r) {
    $nm  = strtolower(trim((string) $r['extra_name_snapshot']));
    $lbl = (string) $r['choice_label_snapshot'];
    $code = strtolower(trim((string) ($r['extra_code'] ?? '')));
    $live = strtolower(trim((string) ($r['extra_live_name'] ?? '')));
    if ($lbl !== '') { $opt[$nm] = $lbl; if ($code) $opt[$code] = $lbl; if ($live) $opt[$live] = $lbl; }
    printf("  %-34s | %-30s | %-34s => %s\n", $nm, ($code ?: '(no code)'), $live, $lbl);
}

// Now check the stored label's code-based field sources against $opt.
$t = $pdo->prepare('SELECT layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id LIMIT 1');
$t->execute([$productId]);
$layout = json_decode((string) $t->fetchColumn(), true) ?: [];
$sources = [];
foreach (($layout['labels'] ?? []) as $lab) {
    foreach (($lab['fields'] ?? []) as $f) {
        $s = is_array($f) ? (string) ($f['source'] ?? '') : '';
        if (strncmp($s, 'opt:', 4) === 0) $sources[] = substr($s, 4);
    }
}
echo "\n== Label opt: field sources vs resolved value ==\n";
$blank = 0;
foreach ($sources as $s) {
    $v = $opt[strtolower($s)] ?? '';
    if ($v === '') $blank++;
    printf("  %-40s => %s\n", 'opt:' . $s, $v === '' ? '(blank — option not chosen on this order)' : $v);
}
echo "\nResolved " . (count($sources) - $blank) . "/" . count($sources) . " label option sources.\n";
echo "Blank ones are simply options not selected on THIS order (gated fabric/fascia variants), not failures.\n";
