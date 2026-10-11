<?php
declare(strict_types=1);

/**
 * Add product_extras.collapse_multi — a per-option flag for tick-several
 * options: when 1, the quote builder / InstaPrice / product preview show the
 * option as a single box reading "None"; picking "Add …" in that box opens the
 * tick list. Keeps rarely-used add-ons (motors, accessories) from taking up
 * room on every blind. Set it per option in Products → Options → Edit.
 *
 * Also switches it on for the Home Creations add-on lists that asked for it
 * (Motor & Parts and Accessories on Bev Forest Wood / Bev Embassy Faux Wood) —
 * only where they exist in the signed-in tenant; untick any time.
 *
 * Idempotent, super-admin, web-runnable: /setup/migrations/migrate_extra_collapse_multi.php
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
$exists->execute(['product_extras', 'collapse_multi']);
if ($exists->fetchColumn()) {
    echo "product_extras.collapse_multi already exists — skipped.\n";
} else {
    $pdo->exec('ALTER TABLE product_extras ADD COLUMN collapse_multi TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_multi');
    echo "Added product_extras.collapse_multi.\n";
}

$clientId = PHP_SAPI === 'cli' ? 0 : (int) (current_user()['client_id'] ?? 0);
if ($clientId > 0) {
    $st = $pdo->prepare(
        "UPDATE product_extras e JOIN products p ON p.id = e.product_id AND p.client_id = e.client_id
            SET e.collapse_multi = 1
          WHERE e.client_id = ? AND e.allow_multi = 1
            AND p.name IN ('Bev Forest Wood', 'Bev Embassy Faux Wood')
            AND e.name IN ('Motor & Parts', 'Accessories')"
    );
    $st->execute([$clientId]);
    echo "Collapsed to 'None' on Forest Wood / Embassy Motor & Parts + Accessories: {$st->rowCount()} option(s).\n";
}
echo "\nDone. Tick 'Collapse to None until needed' on any tick-several option in Products → Options → Edit.\n";
