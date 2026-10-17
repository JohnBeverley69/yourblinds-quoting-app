<?php
declare(strict_types=1);

/**
 * Migration: supplier contact details + several attachments on a supplier request.
 *
 *   supplier_requests.requester_name   VARCHAR(160) NULL  -- who at the company asked
 *   supplier_requests.supplier_contact VARCHAR(160) NULL  -- the supplier's rep / contact
 *   supplier_requests.supplier_email   VARCHAR(255) NULL
 *   supplier_requests.supplier_phone   VARCHAR(60)  NULL
 *   supplier_requests.account_number   VARCHAR(80)  NULL  -- the company's account no. with them
 *
 *   supplier_request_files (id, request_id, file_name, file_path, file_size, created_at)
 *     -- price lists, option sheets, EDI files; any number per request. The old
 *        single file_name/file_path columns stay for requests made before this.
 *
 * Idempotent. Run via web: /setup/migrations/migrate_supplier_request_contact.php (super-admin).
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
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
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$table, $col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: supplier request contact details + attachments…\n\n";

$cols = [
    'requester_name'   => 'requester_name VARCHAR(160) NULL AFTER email',
    'supplier_contact' => 'supplier_contact VARCHAR(160) NULL AFTER supplier_name',
    'supplier_email'   => 'supplier_email VARCHAR(255) NULL AFTER supplier_contact',
    'supplier_phone'   => 'supplier_phone VARCHAR(60) NULL AFTER supplier_email',
    'account_number'   => 'account_number VARCHAR(80) NULL AFTER supplier_phone',
];
foreach ($cols as $col => $def) {
    if (!$colExists('supplier_requests', $col)) {
        $pdo->exec("ALTER TABLE supplier_requests ADD COLUMN $def");
        $ops[] = "Added supplier_requests.$col.";
    } else {
        $ops[] = "supplier_requests.$col already exists — skipped.";
    }
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS supplier_request_files (
        id         INT NOT NULL AUTO_INCREMENT,
        request_id INT NOT NULL,
        file_name  VARCHAR(255) NOT NULL,
        file_path  VARCHAR(255) NOT NULL,
        file_size  INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_request (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
$ops[] = 'supplier_request_files table ready.';

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
