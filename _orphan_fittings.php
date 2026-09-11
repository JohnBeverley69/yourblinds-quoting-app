<?php
declare(strict_types=1);
/** TEMP one-time: delete appointments whose quote no longer exists (phantom fittings). Delete after use. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

$where = "a.quote_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM quotes q WHERE q.id = a.quote_id)";

$n = (int) $pdo->query("SELECT COUNT(*) FROM appointments a WHERE $where")->fetchColumn();
echo "Orphaned appointments (order deleted): {$n}\n";

if ($n > 0) {
    // Show a breakdown before removing.
    $bk = $pdo->query("SELECT a.client_id, COUNT(*) c FROM appointments a WHERE $where GROUP BY a.client_id");
    foreach ($bk->fetchAll(PDO::FETCH_ASSOC) as $r) echo "  client {$r['client_id']}: {$r['c']}\n";

    $del = $pdo->prepare("DELETE a FROM appointments a WHERE $where");
    $del->execute();
    echo "\nDeleted {$del->rowCount()} phantom appointment(s).\n";
} else {
    echo "Nothing to clean.\n";
}
