<?php
declare(strict_types=1);

/**
 * Add products.line_charge — a flat £ charge added ONCE per quote line, after
 * the × quantity step in the pricing engine (so it is NOT multiplied by the
 * slat count on per-slat products). Pass-through (not marked up), like extras.
 *
 * First use: Arena "Vertical Louvres Only" — the louvres-only service carries
 * a £6.98 per-set surcharge (price list p63). Reusable for any per-order flat
 * charge (e.g. BlindScreen Vanoseal) later.
 *
 * Idempotent: adds the column only if missing; sets Louvres Only = 6.98.
 * DRY-RUN unless ?apply=1. Super-admin only.
 *   Preview: /migrate_line_charge.php   Apply: &apply=1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL);

$user=current_user(); $clientId=(int)$user['client_id'];
$pdo=db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$apply=(($_GET['apply']??'')==='1');

// column present?
$hasCol=false;
try { $pdo->query('SELECT line_charge FROM products LIMIT 1'); $hasCol=true; } catch(Throwable $e) {}
echo ($apply?"APPLY":"DRY RUN")." — products.line_charge\n".str_repeat('=',50)."\n";
echo "Column exists: ".($hasCol?'yes':'no')."\n";

$LOUVRES='Arena Vertical Louvres Only'; $AMOUNT=6.98;
// find the product (without referencing line_charge, which may not exist yet)
$f=$pdo->prepare('SELECT id FROM products WHERE client_id=? AND name=? LIMIT 1');
$f->execute([$clientId,$LOUVRES]); $lid=(int)($f->fetchColumn()?:0);
echo "Louvres Only: ".($lid?"#{$lid}":'NOT FOUND')."\n";

if(!$apply){ echo "\nWould: ".($hasCol?'':'ADD COLUMN line_charge DECIMAL(10,2) NOT NULL DEFAULT 0; ')."SET {$LOUVRES} = £{$AMOUNT}.\nPREVIEW ONLY — re-run with ?apply=1.\n"; exit; }

$pdo->beginTransaction();
try {
    if(!$hasCol){ $pdo->exec("ALTER TABLE products ADD COLUMN line_charge DECIMAL(10,2) NOT NULL DEFAULT 0"); echo "Added column line_charge.\n"; }
    $u=$pdo->prepare('UPDATE products SET line_charge=? WHERE client_id=? AND name=?');
    $u->execute([$AMOUNT,$clientId,$LOUVRES]);
    echo "Set {$LOUVRES} line_charge = £{$AMOUNT} (".$u->rowCount()." row).\n";
    $pdo->commit();
    echo "\nDone.\n";
} catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); echo "\nFAILED: ".$e->getMessage()."\n"; exit(1); }
