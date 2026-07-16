<?php
declare(strict_types=1);

/**
 * Migration: production times + order due dates.
 *
 *   product_lead_times  — how many WORKING days each Beverley product takes to
 *                         make. Turned up when the workshop is busy, down when
 *                         it's quiet. Editable on the factory Routes screen.
 *   quotes.due_date     — the promised date, STAMPED ONCE when the order is
 *                         placed, from the lead times in force at that moment.
 *
 * The stamp is the whole point: changing a production time must never move the
 * date on an order that's already placed — only orders placed after the edit.
 * So due_date is written once (dd_stamp_order only fires when it's NULL) and
 * never recomputed. Same principle as the price/name snapshots on order lines.
 *
 * Run via web: /migrate_factory_due_dates.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};
$tableExists = static function (string $t) use ($pdo): bool {
    try { $pdo->query("SELECT 1 FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$colExists('quotes', 'due_date')) {
    $pdo->exec("ALTER TABLE quotes ADD COLUMN due_date DATE NULL");
    echo "  Added quotes.due_date.\n";
} else {
    echo "  quotes.due_date already exists — skipped.\n";
}

if (!$tableExists('product_lead_times')) {
    $pdo->exec(
        "CREATE TABLE product_lead_times (
            product_id INT NOT NULL PRIMARY KEY,   -- Beverley's MASTER product
            lead_days  INT NOT NULL DEFAULT 10,    -- WORKING days (Mon-Fri)
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  Created product_lead_times.\n";
} else {
    echo "  product_lead_times already exists — skipped.\n";
}

echo "\nDone. Set each product's production time on /factory/routes.php.\n";
echo "Orders placed from now on get a due date stamped at placement; existing\n";
echo "orders have none (run /seed_factory_due_dates.php to backfill them).\n";
