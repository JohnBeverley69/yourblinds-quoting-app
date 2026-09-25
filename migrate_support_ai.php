<?php
declare(strict_types=1);

/**
 * Migration: AI support assistant (phase 2 of the Help widget).
 *
 *   support_ai_usage        — one row per Claude API call (tokens + estimated
 *                             cost), for the in-app monthly £ cap, the per-user
 *                             daily message limit and the admin usage line.
 *   support_tickets.source  — 'form' (typed into the report form) or 'ai'
 *                             (filed by the chat assistant).
 *
 * Settings themselves live in platform_config (migrate_platform_config.php).
 *
 * Run via web: /migrate_support_ai.php?run=1 (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS support_ai_usage (
        id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        conversation_id    VARCHAR(40) NOT NULL,
        client_id          INT NULL,
        user_id            INT NULL,
        is_user_turn       TINYINT(1) NOT NULL DEFAULT 0,
        input_tokens       INT UNSIGNED NOT NULL DEFAULT 0,
        output_tokens      INT UNSIGNED NOT NULL DEFAULT 0,
        cache_write_tokens INT UNSIGNED NOT NULL DEFAULT 0,
        cache_read_tokens  INT UNSIGNED NOT NULL DEFAULT 0,
        cost_usd           DECIMAL(10,6) NOT NULL DEFAULT 0,
        created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_created (created_at),
        KEY idx_user_created (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "  support_ai_usage ready.\n";

if (!$colExists('support_tickets', 'source')) {
    $pdo->exec("ALTER TABLE support_tickets ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT 'form' AFTER category");
    echo "  Added support_tickets.source.\n";
} else {
    echo "  support_tickets.source already exists — skipped.\n";
}

try { $pdo->query('SELECT 1 FROM platform_config LIMIT 0'); echo "  platform_config present.\n"; }
catch (Throwable $e) { echo "  WARNING: platform_config missing — run /migrate_platform_config.php too.\n"; }

echo "\nDone. Set the key under Master Admin → Support inbox → AI assistant.\n";
