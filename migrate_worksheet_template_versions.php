<?php
declare(strict_types=1);

/**
 * Migration: worksheet-template version history.
 *
 * The Worksheets editor overwrites worksheet_templates.layout_json in place, so
 * a bad save (e.g. one that serialised an empty field list) silently destroyed
 * the previous layout with no way back in-app. This table keeps a snapshot of
 * the PREVIOUS layout_json before every overwrite, so any save can be rolled
 * back from the editor — no database backup needed.
 *
 * Paired with a server-side guard in factory/worksheets.php that refuses to
 * replace a populated template with an empty one unless explicitly confirmed.
 *
 * Additive + idempotent. Web-runnable: /migrate_worksheet_template_versions.php
 * (super-admin).
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

$exists = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'worksheet_template_versions'"
)->fetchColumn();

if ((int) $exists === 0) {
    $pdo->exec(
        "CREATE TABLE worksheet_template_versions (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            template_id       INT NOT NULL,
            product_id        INT NOT NULL,
            name              VARCHAR(120) NULL,
            layout_json       MEDIUMTEXT NOT NULL,
            field_count       INT NOT NULL DEFAULT 0,
            saved_by_user_id  INT NULL,
            created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wtv_template (template_id, created_at),
            INDEX idx_wtv_product  (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "Created worksheet_template_versions.\n";
} else {
    echo "worksheet_template_versions already present — skipped.\n";
}

echo "\nDone. Saves now snapshot the previous layout here before overwriting;\n";
echo "restore any snapshot from the Worksheets editor's \"Previous versions\" panel.\n";
