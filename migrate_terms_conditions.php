<?php
declare(strict_types=1);

/**
 * Migration: per-client Terms & Conditions + Privacy Policy.
 *
 * Adds two free-text columns to client_settings so each tenant can store
 * their own T&Cs and Privacy Policy. They render (personalised via tokens
 * like {{company_name}} / {{customer_name}}) on the quote PDF and the
 * customer-facing quote page. NULL = not configured (nothing renders);
 * a saved value (even empty) = configured.
 *
 * Mirrors the existing quote_footer free-text column. Idempotent.
 *
 * Run via web: /migrate_terms_conditions.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    echo "Steps completed before failure:\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$colExists = static function (string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_settings' AND COLUMN_NAME = ?"
    );
    $st->execute([$col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: client Terms & Conditions + Privacy Policy…\n\n";

foreach (['terms_conditions', 'privacy_policy', 'accept_email_body'] as $col) {
    if ($colExists($col)) {
        $ops[] = "client_settings.$col already exists — skipped.";
    } else {
        $pdo->exec("ALTER TABLE client_settings ADD COLUMN $col LONGTEXT NULL");
        $ops[] = "Added client_settings.$col (LONGTEXT NULL).";
    }
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nEach tenant can now set their Terms & Conditions and Privacy Policy on\n";
echo "the Settings page. They render personalised on the quote PDF + the\n";
echo "customer-facing quote (next step). Until a tenant saves them, nothing\n";
echo "extra appears on quotes.\n";
