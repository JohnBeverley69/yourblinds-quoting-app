<?php
declare(strict_types=1);

/**
 * Migration: catalogue_audit table.
 *
 * Append-only log of every catalogue mutation (create / update /
 * delete / duplicate / import). Lets a multi-admin tenant see who
 * changed what, and gives any admin a chance to spot — and undo —
 * an accidental edit before it's been live long enough to do harm.
 *
 * Schema notes:
 *   - user_name is denormalised. We carry the actor's display name
 *     forward so the audit row remains readable even after the user
 *     is deleted or renamed. The user_id link still exists for
 *     joining where it does still resolve.
 *
 *   - parent_product_id lets us scope the "recent changes on THIS
 *     product" panel without a per-entity table join.
 *
 *   - before_json / after_json carry the relevant fields snapshot.
 *     They're optional — a "create" event has only after_json; a
 *     "delete" event has only before_json. Schema is per-entity-type
 *     (we don't snapshot the entire row, just what's interesting).
 *
 *   - meta_json is a free-form extras bucket. Used for things that
 *     don't fit before/after — e.g. bulk-import has
 *     {"rows": 348, "source": "supplier.xlsx"}.
 *
 * Indexes match the queries we'll actually run: per-product feed
 * (ORDER BY id DESC LIMIT N for one product), per-tenant feed
 * (ORDER BY id DESC LIMIT N for one client), and the occasional
 * filter by entity_type or actor.
 *
 * Idempotent — re-runnable. Run via /migrate_catalogue_audit.php
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

function ca_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute([$table]);
    return $st->fetchColumn() !== false;
}

$ops = [];

if (!ca_table_exists($pdo, 'catalogue_audit')) {
    $pdo->exec(
        "CREATE TABLE catalogue_audit (
            id                 INT UNSIGNED   NOT NULL AUTO_INCREMENT,
            client_id          INT UNSIGNED   NOT NULL,
            user_id            INT UNSIGNED   NULL,
            user_name          VARCHAR(150)   NULL,
            entity_type        VARCHAR(40)    NOT NULL,
            entity_id          INT UNSIGNED   NULL,
            parent_product_id  INT UNSIGNED   NULL,
            entity_label       VARCHAR(200)   NULL,
            action             VARCHAR(20)    NOT NULL,
            before_json        JSON           NULL,
            after_json         JSON           NULL,
            meta_json          JSON           NULL,
            created_at         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ca_client_created  (client_id, created_at),
            KEY idx_ca_product_created (parent_product_id, created_at),
            KEY idx_ca_entity          (entity_type, entity_id),
            KEY idx_ca_actor           (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ops[] = 'Created table catalogue_audit';
} else {
    $ops[] = 'catalogue_audit already present';
}

echo "MIGRATION OK\n============\n\n";
foreach ($ops as $line) echo '- ' . $line . "\n";
echo "\nAll done.\n";
