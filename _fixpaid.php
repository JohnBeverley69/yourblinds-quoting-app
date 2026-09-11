<?php
declare(strict_types=1);
/** TEMP one-time: revert this account's not-money-backed 'paid' quotes to 'invoiced'. Delete after use. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireLogin();
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$clientId = (int) (current_user()['client_id'] ?? 0);
echo "Client: {$clientId}\n\n";

$q = $pdo->prepare("SELECT id, quote_number, total, deposit_amount, deposit_paid_at
                      FROM quotes WHERE client_id = ? AND status = 'paid'");
$q->execute([$clientId]);
$fixed = 0;
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $qid = (int) $r['id'];
    $depPaid = !empty($r['deposit_paid_at']) ? (float) $r['deposit_amount'] : 0.0;
    $pay = 0.0;
    try {
        $p = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE quote_id = ?');
        $p->execute([$qid]);
        $pay = (float) $p->fetchColumn();
    } catch (Throwable $e) { $pay = 0.0; }
    $received = round($depPaid + $pay, 2);
    $total = (float) $r['total'];
    if ($received < $total - 0.004) {
        // Not actually paid — step back to invoiced; clear a £0 deposit-paid flag.
        $pdo->prepare("UPDATE quotes SET status = 'invoiced' WHERE id = ? AND client_id = ? AND status = 'paid'")
            ->execute([$qid, $clientId]);
        if ((float) $r['deposit_amount'] <= 0.004) {
            $pdo->prepare('UPDATE quotes SET deposit_paid_at = NULL WHERE id = ? AND client_id = ?')
                ->execute([$qid, $clientId]);
        }
        echo "Reverted {$r['quote_number']} (id {$qid}): received £" . number_format($received, 2)
           . " < total £" . number_format($total, 2) . " -> invoiced" . ((float) $r['deposit_amount'] <= 0.004 ? ", £0 deposit-paid flag cleared" : "") . "\n";
        $fixed++;
    } else {
        echo "Kept {$r['quote_number']} (id {$qid}): received £" . number_format($received, 2) . " covers total — genuinely paid.\n";
    }
}
echo "\nReverted {$fixed} quote(s).\n";
