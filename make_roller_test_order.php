<?php
declare(strict_types=1);

/**
 * DISPOSABLE test data: insert one Bev Roller Blinds order with a handful of
 * chosen options (proper product_extra_id + choice snapshots) so we can render
 * its worksheet label and prove the code-based resolution + rename-proofness.
 * Pricing is irrelevant to the label, so it is left at 0.
 *
 *   /make_roller_test_order.php            -> create (prints quote id + number)
 *   /make_roller_test_order.php?cleanup=1  -> delete the test order(s)
 *
 * Web-runnable (super-admin). Test-only; the app is pre-launch.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;
$TAG = 'ZZTEST-ROLLER';

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Bev Roller Blinds not found.\n"); }

if (isset($_GET['cleanup'])) {
    $ids = $pdo->prepare("SELECT id FROM quotes WHERE client_id = ? AND quote_number LIKE ?");
    $ids->execute([$MASTER, $TAG . '%']);
    $qids = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));
    if (!$qids) { exit("No test orders to clean up.\n"); }
    $pdo->beginTransaction();
    try {
        foreach ($qids as $qid) {
            $items = $pdo->prepare("SELECT id FROM quote_items WHERE quote_id = ?");
            $items->execute([$qid]);
            foreach (array_map('intval', $items->fetchAll(PDO::FETCH_COLUMN)) as $iid) {
                $pdo->prepare("DELETE FROM quote_item_extras WHERE quote_item_id = ?")->execute([$iid]);
            }
            $pdo->prepare("DELETE FROM quote_items WHERE quote_id = ?")->execute([$qid]);
            $pdo->prepare("DELETE FROM quotes WHERE id = ?")->execute([$qid]);
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); exit('Cleanup FAILED: ' . $e->getMessage() . "\n"); }
    exit('Deleted ' . count($qids) . " test order(s): #" . implode(', #', $qids) . "\n");
}

// A system for roller (Standard Roller if present).
$sys = $pdo->prepare("SELECT id, name FROM systems WHERE product_id = ? ORDER BY (name LIKE '%Standard%') DESC, id LIMIT 1");
$sys->execute([$productId]);
$system = $sys->fetch(PDO::FETCH_ASSOC) ?: ['id' => 0, 'name' => ''];

// A handful of label-relevant options, each with its first active choice.
$wantNames = ['Exact or Recess', 'Fabric Roll', 'Control Options', 'Control Side', 'Chain Type',
              'Mech Colour', 'Bottom Bar Options', 'Senses Bottom Bar Colour', 'Fascia Options',
              'Senses Profile Colour', 'Optional Extras'];
$optRows = [];
$peSel = $pdo->prepare("SELECT id, name, code FROM product_extras WHERE product_id = ? AND client_id = ? AND name = ? AND active = 1 ORDER BY id LIMIT 1");
$chSel = $pdo->prepare("SELECT id, label FROM product_extra_choices WHERE product_extra_id = ? AND active = 1 ORDER BY sort_order, id LIMIT 1");
foreach ($wantNames as $nm) {
    $peSel->execute([$productId, $MASTER, $nm]);
    $pe = $peSel->fetch(PDO::FETCH_ASSOC);
    if (!$pe) continue;
    $chSel->execute([(int) $pe['id']]);
    $ch = $chSel->fetch(PDO::FETCH_ASSOC);
    if (!$ch) continue;
    $optRows[] = ['extra_id' => (int) $pe['id'], 'extra_name' => (string) $pe['name'], 'code' => (string) ($pe['code'] ?? ''),
                  'choice_id' => (int) $ch['id'], 'choice_label' => (string) $ch['label']];
}

$pdo->beginTransaction();
try {
    $quoteNumber = $TAG . '-' . date('HUs');
    $token = bin2hex(random_bytes(16));
    $pdo->prepare(
        'INSERT INTO quotes (client_id, quote_number, end_customer_name, end_customer_town,
                             status, vat_percent, notes, public_token, created_by_user_id)
         VALUES (?, ?, ?, ?, "ordered", 20, ?, ?, ?)'
    )->execute([$MASTER, $quoteNumber, 'Roller Label Test', 'Beverley', 'Disposable roller label test order', $token, null]);
    $quoteId = (int) $pdo->lastInsertId();

    // Discover the quote_items columns present, so we only set ones that exist.
    $cols = [];
    foreach ($pdo->query("SHOW COLUMNS FROM quote_items")->fetchAll(PDO::FETCH_ASSOC) as $c) $cols[$c['Field']] = true;
    $item = [
        'quote_id' => $quoteId, 'line_no' => 1,
        'product_id' => $productId, 'product_name_snapshot' => 'Bev Roller Blinds',
        'system_id' => (int) $system['id'] ?: null, 'system_name_snapshot' => (string) $system['name'],
        'fabric_name_snapshot' => 'Test Fabric', 'fabric_colour_snapshot' => 'Charcoal',
        'room_name' => 'Lounge', 'width_mm' => 1200, 'drop_mm' => 1500, 'quantity' => 1,
        'base_price' => 0, 'extras_total' => 0, 'subtotal_per_blind' => 0,
        'markup_percent' => 0, 'discount_percent' => 0, 'sell_price' => 0, 'line_total' => 0,
        'notes' => 'label render test',
    ];
    $item = array_filter($item, static fn ($k) => isset($cols[$k]), ARRAY_FILTER_USE_KEY);
    $fields = array_keys($item);
    $ph = implode(',', array_fill(0, count($fields), '?'));
    $pdo->prepare('INSERT INTO quote_items (' . implode(',', $fields) . ') VALUES (' . $ph . ')')
        ->execute(array_values($item));
    $itemId = (int) $pdo->lastInsertId();

    // Extras with proper product_extra_id so worksheet-print can JOIN the code.
    $eCols = [];
    foreach ($pdo->query("SHOW COLUMNS FROM quote_item_extras")->fetchAll(PDO::FETCH_ASSOC) as $c) $eCols[$c['Field']] = true;
    $insE = $pdo->prepare(
        'INSERT INTO quote_item_extras (quote_item_id, product_extra_id, extra_name_snapshot,
                                        product_extra_choice_id, choice_label_snapshot' .
        (isset($eCols['mode']) ? ', mode' : '') . (isset($eCols['amount_applied']) ? ', amount_applied' : '') . ')
         VALUES (?, ?, ?, ?, ?' . (isset($eCols['mode']) ? ', ?' : '') . (isset($eCols['amount_applied']) ? ', ?' : '') . ')'
    );
    foreach ($optRows as $o) {
        $vals = [$itemId, $o['extra_id'], $o['extra_name'], $o['choice_id'], $o['choice_label']];
        if (isset($eCols['mode'])) $vals[] = 'fixed';
        if (isset($eCols['amount_applied'])) $vals[] = 0;
        $insE->execute($vals);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    exit("FAILED: " . $e->getMessage() . "\n");
}

echo "Created roller test order: quote #{$quoteId} ({$quoteNumber}), item #{$itemId}\n";
echo "Extras attached (" . count($optRows) . "):\n";
foreach ($optRows as $o) echo "  {$o['extra_name']} [{$o['code']}] = {$o['choice_label']}\n";
echo "\nRender: /factory/worksheet-print.php?quote_id={$quoteId}\n";
echo "Clean up when done: /make_roller_test_order.php?cleanup=1\n";
