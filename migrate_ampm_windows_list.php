<?php
declare(strict_types=1);

/**
 * Migration — booking windows become a per-tenant LIST (add / name / remove).
 *
 * Adds to client_settings:
 *   ampm_windows_json  TEXT NULL  -- JSON list of {k,label,start,end,cap,off}
 *
 * NULL means "not saved yet": the window list is built from the existing
 * ampm_am_* / ampm_pm_* columns (see _partials/slot_window.php), so nothing
 * changes for any tenant until they save Settings → Calendar. Nothing is
 * backfilled. Super-admin only; safe to run more than once.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';

requireLogin();
if (!function_exists('is_super_admin') || !is_super_admin()) {
    http_response_code(403);
    exit('Super-admin only.');
}

require_run_confirmation();

$pdo = db();
header('Content-Type: text/plain; charset=utf-8');

try {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $st->execute(['client_settings', 'ampm_windows_json']);
    if ((int) $st->fetchColumn() > 0) {
        echo "client_settings.ampm_windows_json already exists — skipped.\n";
    } else {
        $pdo->exec('ALTER TABLE client_settings ADD COLUMN ampm_windows_json TEXT NULL');
        echo "Added client_settings.ampm_windows_json.\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Migration FAILED: ' . $e->getMessage() . "\n";
    exit;
}

echo "\nBooking-window list migration complete. Tenants can now add windows in Settings → Calendar.\n";
