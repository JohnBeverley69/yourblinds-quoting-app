<?php
declare(strict_types=1);
/* TEMP one-time: reconcile Blind Corner (#54) to BM. Dry-run default; ?go=1
   writes. git rm after. */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL);
$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;
$CID = 54;
$R = array (
  'account_ref' => 'BLIN001',
  'contact' => 'Richard Tomalin',
  'email' => 'mail@blindcorner.co.uk',
  'phone' => '01604671189',
  'postcode' => 'NN3 6AQ',
  'username' => 'BLIN001',
  'hash' => '$2y$10$coPua0TMoT9ijefLAvbObOINm/yJ/s2IUSpsknJCAAdxtXP1toz7y',
  'discounts' => 
  array (
    0 => 
    array (
      0 => 'Bev Vertical Blinds',
      1 => 15.0,
    ),
    1 => 
    array (
      0 => 'Bev Vertical Fabrics Only',
      1 => 15.0,
    ),
    2 => 
    array (
      0 => 'Bev Vertical Blind Head Rail Only',
      1 => 15.0,
    ),
    3 => 
    array (
      0 => 'Bev Roller Blinds',
      1 => 9.0,
    ),
  ),
);
$dry = (($_GET['go'] ?? '') !== '1');
if ($dry) echo "DRY RUN — add ?go=1 to write.\n\n";

// Confirm #54 is Blind Corner.
$c = $pdo->prepare("SELECT id, company_name, account_ref FROM clients WHERE id = ?");
$c->execute([$CID]); $row = $c->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo "Client #{$CID} not found.\n"; exit; }
if (stripos((string) $row['company_name'], 'blind corner') === false) { echo "! #{$CID} is '{$row['company_name']}', not Blind Corner — aborting.\n"; exit; }
echo "Client #{$CID}: {$row['company_name']} (current account_ref: " . ($row['account_ref'] ?? '(none)') . ")\n";

// login row(s)
$us = $pdo->prepare("SELECT id, email, username FROM client_users WHERE client_id = ? ORDER BY id");
$us->execute([$CID]); $users = $us->fetchAll(PDO::FETCH_ASSOC);
echo "logins: " . count($users) . (count($users) ? " (updating #{$users[0]['id']}: username -> {$R['username']}, password -> BM, verified)" : " — will CREATE one") . "\n";

// discounts: existing base rows
$de = $pdo->prepare("SELECT COUNT(*) FROM trade_discounts WHERE client_id = ? AND system_id IS NULL AND band_code IS NULL AND extra_id IS NULL AND choice_id IS NULL");
$de->execute([$CID]); $existDisc = (int) $de->fetchColumn();
echo "existing base discounts: {$existDisc} (will be replaced by the " . count($R['discounts']) . " BM ones)\n";

// resolve BM discount products to #54's mirrored product ids
$mp = [];
foreach ($pdo->query("SELECT id, name FROM products WHERE client_id = {$MASTER}")->fetchAll(PDO::FETCH_ASSOC) as $r) $mp[(string) $r['name']] = (int) $r['id'];
$plan = [];
foreach ($R['discounts'] as [$mname, $pct]) {
    $mid = $mp[$mname] ?? 0;
    if (!$mid) { echo "  ! master '{$mname}' not found\n"; continue; }
    $ap = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND source_product_id = ? LIMIT 1");
    $ap->execute([$CID, $mid]); $pid = (int) ($ap->fetchColumn() ?: 0);
    if (!$pid) { echo "  ! #{$CID} has no mirrored product for '{$mname}'\n"; continue; }
    $plan[] = [$pid, $mname, $pct];
    echo "  discount: {$mname} #{$pid} = {$pct}%\n";
}

if ($dry) { echo "\n(dry run — nothing written)\n"; exit; }

$pdo->beginTransaction();
try {
    // 1. clients
    $pdo->prepare("UPDATE clients SET account_ref = ?, contact_name = ?, email = ?, phone = ?, postcode = ? WHERE id = ?")
        ->execute([$R['account_ref'], $R['contact'] ?: null, $R['email'] ?: null, $R['phone'] ?: null, $R['postcode'] ?: null, $CID]);
    // 2. login
    if ($users) {
        $pdo->prepare("UPDATE client_users SET username = ?, password_hash = ?, full_name = ?, email = ?, email_verified_at = NOW(), active = 1 WHERE id = ?")
            ->execute([$R['username'], $R['hash'], $R['contact'] ?: 'Blind Corner', $R['email'] ?: null, (int) $users[0]['id']]);
    } else {
        $pdo->prepare("INSERT INTO client_users (client_id, email, username, full_name, password_hash, role, active, is_super_admin, email_verified_at) VALUES (?, ?, ?, ?, ?, 'admin', 1, 0, NOW())")
            ->execute([$CID, $R['email'] ?: null, $R['username'], $R['contact'] ?: 'Blind Corner', $R['hash']]);
    }
    // 3. discounts — replace base rows with the BM set
    $pdo->prepare("DELETE FROM trade_discounts WHERE client_id = ? AND system_id IS NULL AND band_code IS NULL AND extra_id IS NULL AND choice_id IS NULL")->execute([$CID]);
    $ins = $pdo->prepare("INSERT INTO trade_discounts (client_id, product_id, discount_percent, active, notes) VALUES (?, ?, ?, 1, 'Migrated 2026 trade discount')");
    foreach ($plan as [$pid, $mname, $pct]) $ins->execute([$CID, $pid, $pct]);
    $pdo->commit();
    echo "\nReconciled Blind Corner #{$CID}: code BLIN001, login username BLIN001 + BM password, " . count($plan) . " discounts.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "FAILED: " . $e->getMessage() . "\n";
}
