<?php
declare(strict_types=1);

/**
 * Migration: online booking, Phase 2. See _partials/online_booking.php.
 *
 *  - online_booking_settings.display_name: the name customers see on the
 *    booking page and in its emails (blank = the company name);
 *  - online_booking_settings.auto_assign: give each online booking to a
 *    salesperson automatically (1, the default) or leave it unassigned;
 *  - online_booking_settings.group_miles: "group visits by area" — on a day a
 *    salesperson already has a visit, only offer them to customers within this
 *    many miles of it (NULL = off);
 *  - online_booking_staff: per salesperson — do they take online bookings, and
 *    their own area (a radius from their home postcode and/or a postcode list).
 *    No rows = every salesperson takes them, anywhere in the company's area;
 *  - online_bookings.manage_token: the secret in the customer's "change or
 *    cancel" link; reschedules counts how often they've moved it;
 *    cancelled_at is set when the customer cancels it themselves.
 *
 * Run via web: /setup/migrations/migrate_online_booking_phase2.php (super-admin). Idempotent.
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
$add = static function (string $table, string $col, string $def) use ($pdo, $colExists): void {
    if ($colExists($table, $col)) {
        echo "$table.$col already there.\n";
        return;
    }
    $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
    echo "Added $table.$col.\n";
};

$add('online_booking_settings', 'display_name', 'VARCHAR(150) NULL');
$add('online_booking_settings', 'auto_assign', 'TINYINT(1) NOT NULL DEFAULT 1');
$add('online_booking_settings', 'group_miles', 'SMALLINT UNSIGNED NULL');

$add('online_bookings', 'manage_token', 'CHAR(64) NULL');
$add('online_bookings', 'reschedules', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
$add('online_bookings', 'cancelled_at', 'DATETIME NULL');
$hasIdx = $pdo->query("SHOW INDEX FROM online_bookings WHERE Key_name = 'uq_ob_manage_token'")->fetch();
if (!$hasIdx) {
    $pdo->exec('ALTER TABLE online_bookings ADD UNIQUE KEY uq_ob_manage_token (manage_token)');
    echo "Added index online_bookings.uq_ob_manage_token.\n";
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS online_booking_staff (
        client_id     INT UNSIGNED NOT NULL,
        user_id       INT UNSIGNED NOT NULL,
        takes_online  TINYINT(1)   NOT NULL DEFAULT 1,
        radius_miles  SMALLINT UNSIGNED NULL,
        districts     TEXT NULL,
        updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (client_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "online_booking_staff table ready.\n";
echo "Done.\n";
