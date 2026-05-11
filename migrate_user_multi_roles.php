<?php
declare(strict_types=1);

/**
 * Migration: a user can have MORE THAN ONE role.
 *
 * Before:
 *   client_users.role  — single varchar per user
 *
 * After:
 *   client_user_roles  — junction (user_id, role) with one row per
 *                        role per user. UNIQUE on the pair.
 *   client_users.role  — kept as the "primary" (highest-privilege)
 *                        role for back-compat. Existing checks like
 *                        $user['role'] === 'admin' keep working
 *                        unchanged; the moment a user picks 'admin'
 *                        alongside anything else, role stays 'admin'.
 *
 * Why both? The junction is the source of truth for "what roles does
 * this user have". The legacy column is a cached pre-computed
 * primary for cheap reads (and the auth gates that currently look
 * at it). Saved at upsert time by admin/users_edit.php.
 *
 * Steps:
 *   1. Create client_user_roles with FK to client_users(id).
 *   2. Backfill: one row per existing single role.
 *
 * Idempotent — re-running detects table + row presence and skips.
 *
 * Run via web: /migrate_user_multi_roles.php   (super-admin login)
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

function table_exists_q(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute([$table]);
    return $st->fetchColumn() !== false;
}

function user_id_type(PDO $pdo): string
{
    // Match client_users.id's exact type so the FK is accepted —
    // avoids the same int / int unsigned mismatch the markup
    // migration hit.
    $st = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = "client_users"
            AND COLUMN_NAME = "id" LIMIT 1'
    );
    $st->execute();
    $t = $st->fetchColumn();
    if (!$t) {
        throw new RuntimeException('client_users.id not found');
    }
    return (string) $t;
}

$ops = [];

if (!table_exists_q($pdo, 'client_user_roles')) {
    $userIdType = user_id_type($pdo);
    $pdo->exec("
        CREATE TABLE client_user_roles (
            id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id   $userIdType  NOT NULL,
            role      VARCHAR(32)  NOT NULL,
            created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_role (user_id, role),
            KEY idx_cur_user (user_id),
            CONSTRAINT fk_cur_user FOREIGN KEY (user_id)
                REFERENCES client_users(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ops[] = "Created table client_user_roles (user_id type: $userIdType)";
} else {
    $ops[] = 'Table client_user_roles already present (skipped)';
}

// Backfill: copy existing client_users.role into the junction.
// INSERT IGNORE skips users who already have an entry (re-runs are
// no-ops).
$copied = $pdo->exec(
    'INSERT IGNORE INTO client_user_roles (user_id, role)
     SELECT id, role
       FROM client_users
      WHERE role IS NOT NULL AND role <> ""'
);
$ops[] = "Backfilled $copied role row(s) from client_users.role";

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Multi-role support is now wired. Each user can have one or more\n";
echo "roles picked from a checkbox group on the user Edit page; the\n";
echo "client_users.role column continues to hold the highest-privilege\n";
echo "role as a 'primary' so existing requireAdmin() / role===admin\n";
echo "checks keep working unchanged.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
