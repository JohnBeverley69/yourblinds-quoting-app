<?php
declare(strict_types=1);

/**
 * Migration: user_calendar_tokens table.
 *
 * Each row holds a per-user secret token that lets external calendar
 * apps (Google Calendar, Apple Calendar, Outlook, Thunderbird) poll
 * a personal ICS feed at /calendar/feed.php?t=<token> — same
 * appointments the user sees inside YourBlinds, formatted as
 * standard iCalendar so they show up on their phone alongside
 * personal events.
 *
 * One token per user. Regenerating the token deletes the old one
 * and inserts a new one, which immediately invalidates any
 * subscription the user had set up — that's the "revoke access"
 * affordance.
 *
 * No PII in the token row; just the link between a random secret
 * and a user. last_used_at lets us see whether anyone's actually
 * using the feed and trim stale tokens later if we want.
 *
 * Idempotent — re-runnable. Run via /migrate_calendar_tokens.php
 * (super-admin login).
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

function uct_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute([$table]);
    return $st->fetchColumn() !== false;
}

$ops = [];

if (!uct_table_exists($pdo, 'user_calendar_tokens')) {
    $pdo->exec(
        "CREATE TABLE user_calendar_tokens (
            id            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
            user_id       INT UNSIGNED   NOT NULL,
            client_id     INT UNSIGNED   NOT NULL,
            token         VARCHAR(64)    NOT NULL,
            created_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at  TIMESTAMP      NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_token (token),
            UNIQUE KEY uniq_user  (user_id),
            KEY idx_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ops[] = 'Created table user_calendar_tokens';
} else {
    $ops[] = 'user_calendar_tokens already present';
}

echo "MIGRATION OK\n============\n\n";
foreach ($ops as $line) echo '- ' . $line . "\n";
echo "\nAll done.\n";
