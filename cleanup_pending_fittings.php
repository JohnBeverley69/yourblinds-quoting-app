<?php
declare(strict_types=1);

/**
 * TEMP: remove the pending (unscheduled) FITTING appointments on the Beverley
 * tenant (client 3) — the orphaned leftovers from the load-test orders.
 *
 * "Pending fitting" = appointments.appt_kind='fitting' AND appointment_date IS NULL.
 *
 * DRY RUN by default (counts + a sample, deletes nothing).
 * Deletes only with ?confirm=1. Chunked so it can't lock the box.
 * Super-admin, web-runnable. Delete this file once done.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(300);
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const CID = 3;
$confirm = ($_GET['confirm'] ?? '') === '1';

// What we'd delete: pending fittings on client 3.
$where = "client_id = ? AND appt_kind = 'fitting' AND appointment_date IS NULL";

$total = (int) (function () use ($pdo, $where) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE $where");
    $s->execute([CID]); return $s->fetchColumn();
})();

// How many are orphaned (their quote no longer exists) — a safety cross-check
// that these really are the deleted load-test orders' leftovers.
$orphaned = (int) (function () use ($pdo) {
    $s = $pdo->prepare(
        "SELECT COUNT(*) FROM appointments a
          WHERE a.client_id = ? AND a.appt_kind = 'fitting' AND a.appointment_date IS NULL
            AND (a.quote_id IS NULL OR NOT EXISTS (SELECT 1 FROM quotes q WHERE q.id = a.quote_id))"
    );
    $s->execute([CID]); return $s->fetchColumn();
})();

// For contrast — leave these alone.
$scheduledFittings = (int) (function () use ($pdo) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE client_id = ? AND appt_kind = 'fitting' AND appointment_date IS NOT NULL");
    $s->execute([CID]); return $s->fetchColumn();
})();
$pendingMeasures = (int) (function () use ($pdo) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE client_id = ? AND (appt_kind IS NULL OR appt_kind <> 'fitting') AND appointment_date IS NULL");
    $s->execute([CID]); return $s->fetchColumn();
})();

echo "Beverley (client " . CID . ") appointment scan\n";
echo "----------------------------------------------\n";
echo "PENDING FITTINGS (would delete) : {$total}\n";
echo "  of which orphaned (quote gone): {$orphaned}\n";
echo "Scheduled fittings (KEPT)       : {$scheduledFittings}\n";
echo "Pending measures  (KEPT)        : {$pendingMeasures}\n\n";

if (!$confirm) {
    echo "DRY RUN — nothing deleted.\n";
    echo "To delete the {$total} pending fittings, re-run with ?confirm=1\n";
    exit;
}

$del = $pdo->prepare("DELETE FROM appointments WHERE $where LIMIT 500");
$deleted = 0;
do {
    $del->execute([CID]);
    $n = $del->rowCount();
    $deleted += $n;
} while ($n > 0);

echo "Deleted {$deleted} pending fitting(s) from client " . CID . ".\n";
echo "Scheduled fittings and pending measures were left untouched.\n";
