<?php
declare(strict_types=1);

/**
 * Migration: trade delivery charges (see _partials/delivery_charges.php).
 *
 *   factory_ar_account_delivery     — per trade account: delivery method
 *                                     (carrier|van|collect) + "no delivery charge"
 *   factory_ar_delivery_notes       + delivery_method, delivery_date (which delivery
 *                                     the note went out on), carriage_net (the charge
 *                                     parked on this note), carriage_override (a
 *                                     waive / custom amount for the whole delivery)
 *
 * The charge rules themselves live in app_settings.delivery_charge_rules (defaults
 * in code: carrier £16 under £260, van £10 under £100). Idempotent.
 * Run as super-admin: /migrate_delivery_charges.php
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

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableExists = static function (string $t) use ($pdo): bool {
    $s = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    $s->execute([$t]);
    return $s->fetchColumn() !== false;
};
$colExists = static function (string $t, string $c) use ($pdo): bool {
    $s = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $s->execute([$t, $c]);
    return $s->fetchColumn() !== false;
};

$ops = [];

if (!$tableExists('factory_ar_account_delivery')) {
    $pdo->exec(
        "CREATE TABLE factory_ar_account_delivery (
            account_client_id  INT         NOT NULL PRIMARY KEY,   -- clients.id of the trade account
            delivery_method    VARCHAR(12) NOT NULL DEFAULT 'carrier',  -- carrier|van|collect
            no_delivery_charge TINYINT(1)  NOT NULL DEFAULT 0,
            updated_at         DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table factory_ar_account_delivery.';
} else {
    $ops[] = 'Table factory_ar_account_delivery already exists — skipped.';
}

if (!$tableExists('factory_ar_delivery_notes')) {
    echo "factory_ar_delivery_notes is missing — run /migrate_ar_delivery_notes.php first.\n";
    exit;
}

$cols = [
    'delivery_method'   => "ADD COLUMN delivery_method VARCHAR(12) NULL AFTER delivery_address",
    'delivery_date'     => "ADD COLUMN delivery_date DATE NULL AFTER delivery_method",
    'carriage_net'      => "ADD COLUMN carriage_net DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER delivery_date",
    'carriage_override' => "ADD COLUMN carriage_override DECIMAL(10,2) NULL AFTER carriage_net",
];
foreach ($cols as $c => $ddl) {
    if (!$colExists('factory_ar_delivery_notes', $c)) {
        $pdo->exec("ALTER TABLE factory_ar_delivery_notes $ddl");
        $ops[] = "Added factory_ar_delivery_notes.$c.";
    } else {
        $ops[] = "factory_ar_delivery_notes.$c already exists — skipped.";
    }
}

$hasIdx = $pdo->query("SHOW INDEX FROM factory_ar_delivery_notes WHERE Key_name = 'idx_dn_delivery'")->fetch();
if (!$hasIdx) {
    $pdo->exec('ALTER TABLE factory_ar_delivery_notes ADD KEY idx_dn_delivery (account_client_id, delivery_date, delivery_method)');
    $ops[] = 'Added index idx_dn_delivery.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nSet each account's delivery method on Trade -> account details; rules on Wholesale.\n";
