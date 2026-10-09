<?php
declare(strict_types=1);

/**
 * Remake orders don't carry a delivery charge (John, 2026-10-09). The rule now
 * lives in dc_recalc_delivery(); this one-off re-works the charge on every
 * existing delivery that includes a remake order, so a charge already parked on
 * a remake's delivery note moves to a paying order (or goes, if the delivery was
 * only remakes). Invoiced notes are never changed — a warning is printed instead.
 *
 * Idempotent, super-admin, web-runnable: /migrate_remake_carriage.php
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/_partials/factory_ar.php';
require_once __DIR__ . '/_partials/delivery_charges.php';
require_once __DIR__ . '/_partials/remakes.php';

$pdo = db();
if (!dc_ready($pdo) || !rm_ready($pdo)) { echo "Delivery charges or remakes not set up — nothing to do.\n"; exit; }

$st = $pdo->query(
    "SELECT DISTINCT dn.factory_client_id, dn.account_client_id, dn.delivery_date, dn.delivery_method
       FROM factory_ar_delivery_notes dn
       JOIN quotes q ON q.id = dn.source_quote_id
      WHERE dn.status <> 'cancelled' AND q.remake_of_quote_id IS NOT NULL
        AND dn.delivery_date IS NOT NULL AND dn.delivery_method IS NOT NULL"
);
$n = 0;
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $g) {
    $r = dc_recalc_delivery($pdo, (int) $g['factory_client_id'], (int) $g['account_client_id'],
                            (string) $g['delivery_date'], (string) $g['delivery_method']);
    $n++;
    echo 'Account ' . $g['account_client_id'] . ', ' . $g['delivery_date'] . ' (' . $g['delivery_method'] . '): delivery charge £'
       . number_format($r['charge'], 2) . ' on £' . number_format($r['net'], 2) . ' of paying orders'
       . ($r['warning'] !== '' ? ' — NOTE: ' . $r['warning'] : '') . "\n";
}
echo "\nDone. {$n} deliver" . ($n === 1 ? 'y' : 'ies') . " with a remake re-worked.\n";
