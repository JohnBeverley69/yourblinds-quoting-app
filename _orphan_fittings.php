<?php
declare(strict_types=1);
/** TEMP one-time: delete ABC's (client 19) orphaned fittings (quote_id NULL = order deleted). Delete after use. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

$sel = "client_id = 19 AND appt_kind = 'fitting' AND quote_id IS NULL AND status <> 'completed'";
$n = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE $sel")->fetchColumn();
echo "ABC orphaned fittings (quote_id NULL, not completed): {$n}\n";
if ($n > 0) {
    $del = $pdo->prepare("DELETE FROM appointments WHERE $sel");
    $del->execute();
    echo "Deleted {$del->rowCount()}.\n";
} else {
    echo "Nothing to delete.\n";
}
