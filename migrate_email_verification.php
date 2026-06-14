<?php
declare(strict_types=1);

/**
 * Migration: email verification (for public self sign-up).
 *
 *   1. email_verifications        — one-time confirmation tokens (mirrors
 *                                   password_resets: token_hash + expiry + used_at).
 *   2. client_users.email_verified_at DATETIME NULL — when the user confirmed
 *                                   their email. NULL = not yet confirmed.
 *
 * Backfill (runs ONCE, only when the column is first added): every EXISTING
 * user is marked verified — they were created by an admin / master admin and
 * are trusted, so the new login gate must never lock them out. Only future
 * self-signups start life as NULL (unconfirmed). Re-running won't re-verify
 * pending signups because the backfill only fires on the fresh column add.
 *
 * Idempotent. Run via /migrate_email_verification.php (super-admin), then delete.
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

$columnExists = static function (PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetchColumn() !== false;
};

// 1) email_verifications table -------------------------------------------------
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS email_verifications (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id    INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at    DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ev_token (token_hash),
        KEY idx_ev_user  (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "email_verifications table: ensured\n";

// 2) client_users.email_verified_at + ONE-TIME backfill ------------------------
if (!$columnExists($pdo, 'client_users', 'email_verified_at')) {
    $pdo->exec('ALTER TABLE client_users ADD COLUMN email_verified_at DATETIME NULL');
    echo "client_users.email_verified_at: added\n";
    // Trust every pre-existing user so the login gate can't lock them out.
    $n = $pdo->exec('UPDATE client_users SET email_verified_at = NOW() WHERE email_verified_at IS NULL');
    echo "existing users marked verified: " . (int) $n . "\n";
} else {
    echo "client_users.email_verified_at: already present (backfill skipped)\n";
}

echo "\nDone. Delete this file from the server once you're happy.\n";
