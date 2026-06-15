<?php
declare(strict_types=1);

/**
 * Migration: DB-backed registry of library suppliers.
 *
 * Replaces the hardcoded library_suppliers() config with a table so suppliers
 * (Decora, Galaxy, …) can be added on a screen instead of in code. Seeds the
 * existing "Beverley Blinds Trade" row so nothing changes for current clients.
 *
 *   library_suppliers
 *     supplier_key  — stable slug, the join key to client_library_suppliers
 *     name          — display name
 *     prefix        — product-name prefix the push engine copies (e.g. 'Bev')
 *     is_free       — free to every account (1) or gated behind the add-on (0)
 *     blurb         — short description shown on the client subscribe page
 *     active        — show in the library (1) or retired (0)
 *     sort_order    — display order
 *
 * Created WITHOUT an explicit charset so it takes the same default as
 * client_library_suppliers — the two are joined on supplier_key, so matching
 * collation avoids an "illegal mix of collations" error.
 *
 * Idempotent. Run via /migrate_library_registry.php (super-admin) then delete.
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

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS library_suppliers (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        supplier_key VARCHAR(64)  NOT NULL,
        name         VARCHAR(120) NOT NULL,
        prefix       VARCHAR(40)  NOT NULL,
        is_free      TINYINT(1)   NOT NULL DEFAULT 0,
        blurb        TEXT         NULL,
        active       TINYINT(1)   NOT NULL DEFAULT 1,
        sort_order   INT          NOT NULL DEFAULT 0,
        created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_supplier_key (supplier_key)
    )'
);
echo "library_suppliers table: ensured\n";

// Seed the one v1 supplier. INSERT IGNORE so re-running never duplicates it
// and never clobbers edits the admin has since made.
$seed = $pdo->prepare(
    'INSERT IGNORE INTO library_suppliers
        (supplier_key, name, prefix, is_free, blurb, active, sort_order)
     VALUES (?, ?, ?, ?, ?, 1, 0)'
);
$seed->execute([
    'beverley',
    'Beverley Blinds Trade',
    'Bev',
    1,
    'Our own trade range — free to every account. The quickest way to a full, priced catalogue.',
]);
echo $seed->rowCount() > 0
    ? "Beverley Blinds Trade: seeded\n"
    : "Beverley Blinds Trade: already present (skipped)\n";

echo "\n";
echo "Done. Manage suppliers under Master admin -> Library suppliers.\n";
echo "Delete this file from the server once you're happy.\n";
