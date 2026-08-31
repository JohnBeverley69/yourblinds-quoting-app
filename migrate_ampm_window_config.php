<?php
declare(strict_types=1);

/**
 * Migration — configurable AM/PM window times + per-window capacity.
 *
 * Adds to client_settings:
 *   ampm_am_start   TIME  DEFAULT '09:00:00'
 *   ampm_am_end     TIME  DEFAULT '13:00:00'
 *   ampm_pm_start   TIME  DEFAULT '13:00:00'
 *   ampm_pm_end     TIME  DEFAULT '17:00:00'
 *   ampm_am_capacity INT  DEFAULT 4   -- morning bookings per day
 *   ampm_pm_capacity INT  DEFAULT 4   -- afternoon bookings per day
 *
 * Seeds the two capacities from the existing single ampm_slot_capacity so
 * nothing changes for tenants who'd already set a number. The old
 * ampm_slot_capacity column is kept (still written = morning capacity) for
 * backward compatibility. Super-admin only; safe to run more than once.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';

requireLogin();
if (!function_exists('is_super_admin') || !is_super_admin()) {
    http_response_code(403);
    exit('Super-admin only.');
}

$pdo = db();
$ops = [];

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $st->execute([$table, $col]);
    return (int) $st->fetchColumn() > 0;
};

$add = static function (string $col, string $ddl) use ($pdo, $colExists, &$ops): void {
    if (!$colExists('client_settings', $col)) {
        $pdo->exec("ALTER TABLE client_settings ADD COLUMN $ddl");
        $ops[] = "Added client_settings.$col.";
    } else {
        $ops[] = "client_settings.$col already exists — skipped.";
    }
};

try {
    $add('ampm_am_start',    "ampm_am_start TIME NOT NULL DEFAULT '09:00:00'");
    $add('ampm_am_end',      "ampm_am_end   TIME NOT NULL DEFAULT '13:00:00'");
    $add('ampm_pm_start',    "ampm_pm_start TIME NOT NULL DEFAULT '13:00:00'");
    $add('ampm_pm_end',      "ampm_pm_end   TIME NOT NULL DEFAULT '17:00:00'");
    $add('ampm_am_capacity', "ampm_am_capacity INT NOT NULL DEFAULT 4");
    $add('ampm_pm_capacity', "ampm_pm_capacity INT NOT NULL DEFAULT 4");

    // Seed the per-window capacities from the existing single value (once).
    if ($colExists('client_settings', 'ampm_slot_capacity')) {
        $n = $pdo->exec(
            'UPDATE client_settings
                SET ampm_am_capacity = COALESCE(ampm_slot_capacity, 4),
                    ampm_pm_capacity = COALESCE(ampm_slot_capacity, 4)
              WHERE ampm_slot_capacity IS NOT NULL'
        );
        $ops[] = "Seeded morning/afternoon capacity from ampm_slot_capacity ($n row(s)).";
    }
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Migration FAILED: " . $e->getMessage() . "\n\nDone so far:\n - " . implode("\n - ", $ops);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "AM/PM window-config migration complete.\n\n - " . implode("\n - ", $ops) . "\n";
