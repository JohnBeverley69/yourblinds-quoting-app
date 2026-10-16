<?php
declare(strict_types=1);

/**
 * Migration: online booking by EXACT TIME (as well as by time slot).
 * See _partials/online_booking.php.
 *
 *  - online_booking_settings.booking_style: 'slots' (the company's Booking time
 *    slots, as before) or 'times' (start times worked out from the hours below);
 *  - visit_minutes / gap_minutes: how long a visit takes and the gap left after
 *    it for travel, so start times run every visit + gap (60 + 30 → 9:00, 10:30…);
 *  - hours_json: opening hours per weekday, {"1":{"from":"09:00","to":"17:00"},…}.
 *
 * Existing companies keep 'slots', so nothing changes until they choose.
 * Run via web: /setup/migrations/migrate_online_booking_times.php (super-admin). Idempotent.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once dirname(__DIR__, 2) . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetchColumn() !== false;
};

$cols = [
    'booking_style' => "VARCHAR(10) NOT NULL DEFAULT 'slots'",
    'visit_minutes' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 60',
    'gap_minutes'   => 'SMALLINT UNSIGNED NOT NULL DEFAULT 30',
    'hours_json'    => 'TEXT NULL',
];
foreach ($cols as $col => $def) {
    if ($colExists('online_booking_settings', $col)) {
        echo "online_booking_settings.$col already there.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE online_booking_settings ADD COLUMN $col $def");
    echo "Added online_booking_settings.$col.\n";
}
echo "Done.\n";
