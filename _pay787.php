<?php
declare(strict_types=1);
/** TEMP read-only: inspect a quote's paid state + payments. Delete after use. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

$qnum = trim((string) ($_GET['q'] ?? 'ABC-2026-0001'));
$q = $pdo->prepare('SELECT id, client_id, quote_number, status, total, deposit_amount, deposit_paid_at, accepted_at
                      FROM quotes WHERE quote_number = ? ORDER BY id DESC LIMIT 1');
$q->execute([$qnum]);
$quote = $q->fetch(PDO::FETCH_ASSOC);
if (!$quote) { echo "No quote '$qnum'.\n"; exit; }
echo "Quote {$quote['quote_number']} (id {$quote['id']}, client {$quote['client_id']})\n";
echo "  status         = {$quote['status']}\n";
echo "  total          = " . var_export($quote['total'], true) . "\n";
echo "  deposit_amount = " . var_export($quote['deposit_amount'], true) . "\n";
echo "  deposit_paid_at= " . var_export($quote['deposit_paid_at'], true) . "\n";
echo "  accepted_at    = " . var_export($quote['accepted_at'], true) . "\n";

echo "\nPayments (payments table) for this quote:\n";
try {
    $p = $pdo->prepare('SELECT * FROM payments WHERE quote_id = ? ORDER BY id');
    $p->execute([(int) $quote['id']]);
    $rows = $p->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) echo "  (none)\n";
    foreach ($rows as $r) {
        echo "  id {$r['id']}: amount=" . ($r['amount'] ?? '?')
           . " date=" . ($r['paid_on'] ?? $r['payment_date'] ?? $r['created_at'] ?? '?')
           . " method=" . ($r['method'] ?? '?') . "\n";
    }
} catch (Throwable $e) { echo "  payments table: " . $e->getMessage() . "\n"; }
