<?php
declare(strict_types=1);

/**
 * Signed-in-person acceptance (John, 2026-10-09): not every customer has email
 * or WhatsApp, so the salesperson can hand over the tablet/phone and the
 * customer signs on screen. Adds to quotes:
 *   acceptance_signature_png  MEDIUMTEXT   data:image/png;base64,… of the drawn signature
 *   acceptance_method         VARCHAR(20)  'in_person' (NULL = the typed-name online accept)
 *   acceptance_by_user_id     INT NULL     whose logged-in device it was signed on
 *
 * Idempotent, super-admin, web-runnable: /setup/migrations/migrate_quote_signature.php
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$exists = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
foreach ([
    'acceptance_signature_png' => 'MEDIUMTEXT NULL',
    'acceptance_method'        => 'VARCHAR(20) NULL',
    'acceptance_by_user_id'    => 'INT NULL',
] as $col => $def) {
    $exists->execute(['quotes', $col]);
    if ($exists->fetchColumn()) {
        echo "quotes.$col already exists — skipped.\n";
    } else {
        $pdo->exec("ALTER TABLE quotes ADD COLUMN $col $def");
        echo "Added quotes.$col.\n";
    }
}
echo "\nDone. The quote screen's \"Customer signs here\" button is now live.\n";
