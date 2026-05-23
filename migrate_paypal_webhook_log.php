<?php
declare(strict_types=1);

/**
 * Migration: paypal_webhook_log table.
 *
 * Captures every webhook event PayPal sends us — verified or not, even
 * if there's no matching local subscription. Powers the "PayPal health"
 * dashboard at /master-admin/paypal-health.php (last-seen timestamp,
 * 24h count, recent events table) and provides an audit trail if a
 * billing dispute ever comes up ("you say you cancelled, here's the
 * event PayPal sent us — or the absence of one").
 *
 * Append-only by design. Rows are never updated; old rows are
 * harmless and can be trimmed by a future cron if the table gets
 * large.
 *
 * Idempotent — re-runnable.
 *
 * Run via /migrate_paypal_webhook_log.php (super-admin login).
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

set_exception_handler(function (Throwable $e) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "MIGRATION FAILED\n================\n\n";
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'In:    ' . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
});

function pwl_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute([$table]);
    return $st->fetchColumn() !== false;
}

$ops = [];

if (!pwl_table_exists($pdo, 'paypal_webhook_log')) {
    $pdo->exec(
        "CREATE TABLE paypal_webhook_log (
            id                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
            event_type        VARCHAR(80)    NOT NULL,
            event_id          VARCHAR(80)    NULL,
            subscription_id   VARCHAR(80)    NULL,
            client_id         INT UNSIGNED   NULL,
            plan_code         VARCHAR(32)    NULL,
            verified          TINYINT(1)     NOT NULL DEFAULT 0,
            processed         TINYINT(1)     NOT NULL DEFAULT 0,
            outcome           VARCHAR(40)    NULL,
            payload_excerpt   TEXT           NULL,
            received_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pwl_received (received_at),
            KEY idx_pwl_event    (event_type, received_at),
            KEY idx_pwl_client   (client_id, received_at),
            KEY idx_pwl_event_id (event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ops[] = 'Created table paypal_webhook_log';
} else {
    $ops[] = 'paypal_webhook_log already present';
}

echo "MIGRATION OK\n============\n\n";
foreach ($ops as $line) echo '- ' . $line . "\n";
echo "\nAll done.\n";
