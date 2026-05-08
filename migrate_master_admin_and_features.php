<?php
declare(strict_types=1);

/**
 * Migration: master-admin role + per-client feature flags.
 *
 * Idempotent — safe to re-run. Checks INFORMATION_SCHEMA before each ALTER
 * because MySQL 8 lacks `ADD COLUMN IF NOT EXISTS`.
 *
 * Run via CLI:   php migrate_master_admin_and_features.php
 * Run via web:   /migrate_master_admin_and_features.php  (admin login required)
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

$pdo = db();

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $sql = 'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute([$table, $column]);
    return $st->fetchColumn() !== false;
}

$ops = [];

// 1. client_users.is_super_admin
if (!column_exists($pdo, 'client_users', 'is_super_admin')) {
    $pdo->exec(
        "ALTER TABLE client_users
            ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER active"
    );
    $ops[] = 'Added client_users.is_super_admin';
} else {
    $ops[] = 'Skipped client_users.is_super_admin (already present)';
}

// 2. client_settings.feature_maps
if (!column_exists($pdo, 'client_settings', 'feature_maps')) {
    $pdo->exec(
        "ALTER TABLE client_settings
            ADD COLUMN feature_maps TINYINT(1) NOT NULL DEFAULT 0 AFTER quote_footer"
    );
    $ops[] = 'Added client_settings.feature_maps';
} else {
    $ops[] = 'Skipped client_settings.feature_maps (already present)';
}

// 3. Backfill: ensure every client has a client_settings row so the master
//    admin toggle can flip a real row rather than create-on-write.
$pdo->exec(
    'INSERT INTO client_settings (client_id)
     SELECT c.id FROM clients c
       LEFT JOIN client_settings s ON s.client_id = c.id
      WHERE s.id IS NULL'
);
$ops[] = 'Backfilled client_settings for any clients missing one';

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo "  - $op\n";
}
echo "\nNext step (one-off, via your DB tool):\n";
echo "  UPDATE client_users SET is_super_admin = 1 WHERE email = 'YOUR_EMAIL_HERE';\n";
