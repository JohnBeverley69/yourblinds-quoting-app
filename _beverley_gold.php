<?php
declare(strict_types=1);
/* TEMP one-time: put Beverley (the owner / factory account) on complimentary Gold
 * via a comp override + feature-flag sync, so the owner isn't sitting on the base
 * Bronze tier. git rm after. */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/_partials/billing_helpers.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$cid = function_exists('factory_client_id') ? factory_client_id() : 3;

$c = $pdo->prepare("SELECT company_name FROM clients WHERE id = ?");
$c->execute([$cid]);
$name = (string) ($c->fetchColumn() ?: '');
echo "Owner account #{$cid}: {$name}\n";

$pdo->prepare(
    "INSERT INTO client_plan_overrides (client_id, plan_code, override_type, expires_at, notes, active)
     VALUES (?, 'gold', 'comp', NULL, ?, 1)
     ON DUPLICATE KEY UPDATE override_type='comp', expires_at=NULL, notes=VALUES(notes), active=1"
)->execute([$cid, 'Owner/master account — complimentary Gold (' . date('Y-m-d') . ')']);
billing_sync_feature_flags_force($cid);

echo "Set complimentary Gold on #{$cid} ({$name}).\n";
