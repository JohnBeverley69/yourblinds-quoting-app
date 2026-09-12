<?php
declare(strict_types=1);

/**
 * Migration: statement-email send log (Statement run, Stage 2).
 *
 * One row per statement emailed to a trade account for a given run ("as at" date).
 * Serves two jobs: an AUDIT of who was sent what and when, and the DOUBLE-SEND
 * guard — before emailing an account for a period_to we check for an existing
 * 'sent' row and skip it unless a deliberate resend.
 *
 *   factory_ar_statement_emails(id, factory_client_id, account_client_id,
 *     period_to DATE, to_email, closing DECIMAL(10,2), status, sent_by, sent_at)
 *   status: sent | failed | skipped
 *
 * Run via web: /migrate_ar_statement_emails.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableExists = static function (string $t) use ($pdo): bool {
    try { $pdo->query("SELECT 1 FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$tableExists('factory_ar_statement_emails')) {
    $pdo->exec(
        "CREATE TABLE factory_ar_statement_emails (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            factory_client_id INT NOT NULL,
            account_client_id INT NOT NULL,
            period_to         DATE NOT NULL,
            to_email          VARCHAR(255) NULL,
            closing           DECIMAL(10,2) NULL,
            status            VARCHAR(16) NOT NULL,     -- sent | failed | skipped
            sent_by           INT NULL,
            sent_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_run  (factory_client_id, period_to),
            KEY idx_acct (factory_client_id, account_client_id, period_to)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  Created factory_ar_statement_emails.\n";
} else {
    echo "  factory_ar_statement_emails already exists — skipped.\n";
}

echo "\nDone. The statement run can now bulk-email statements and record each send.\n";
