<?php
declare(strict_types=1);
/* TEMP one-time: put every IMPORTED trade account (account_ref set) on the Gold
 * tier via a comp override (free forever) + feature-flag sync. Existing tenants
 * and dummy accounts (no account_ref) are untouched. Dry-run by default; ?go=1
 * writes. git rm after. */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/_partials/billing_helpers.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dry = (($_GET['go'] ?? '') !== '1');
if ($dry) echo "DRY RUN — add ?go=1 to write.\n\n";

$rows = $pdo->query("SELECT id, company_name FROM clients WHERE account_ref IS NOT NULL ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
$done = 0; $fail = [];
foreach ($rows as $r) {
    $cid = (int) $r['id'];
    if ($dry) { echo "  would set Gold: {$r['company_name']} (#{$cid})\n"; $done++; continue; }
    try {
        $pdo->prepare(
            "INSERT INTO client_plan_overrides (client_id, plan_code, override_type, expires_at, notes, active)
             VALUES (?, 'gold', 'comp', NULL, ?, 1)
             ON DUPLICATE KEY UPDATE override_type='comp', expires_at=NULL, notes=VALUES(notes), active=1"
        )->execute([$cid, 'Trade account migration — Gold comp (' . date('Y-m-d') . ')']);
        billing_sync_feature_flags_force($cid);
        $done++;
        echo "  + Gold: {$r['company_name']} (#{$cid})\n";
    } catch (Throwable $e) {
        $fail[] = $r['company_name'] . ': ' . $e->getMessage();
    }
}
echo "\n" . ($dry ? 'WOULD set' : 'Set') . " Gold on {$done} account(s).\n";
if ($fail) { echo "FAILED: " . count($fail) . "\n"; foreach ($fail as $f) echo "  ! {$f}\n"; }
