<?php
declare(strict_types=1);

/**
 * Migration: online booking, Phase 1. See _partials/online_booking.php.
 *
 * Customers book a measure visit themselves on a public page
 * (/book/?c=<company link name>), choosing one of the company's time slots.
 *
 *  - online_booking_settings: one row per company — on/off, its link name,
 *    instant or request mode, how far ahead, which days, closed days, the
 *    area covered (radius from a base postcode and/or a postcode list).
 *  - online_bookings: one row per booking made on the public page — the
 *    log the spam limits count, what the customer typed, and the
 *    request → confirmed / declined decision.
 *  - postcode_geo: postcode → latitude/longitude cache (postcodes.io), so
 *    the radius check asks once per postcode.
 *  - appointments.online_request: 1 while an online booking is waiting for
 *    the company to confirm it. The slot is held (status stays 'booked') but
 *    no confirmation text or reminder goes out until it's confirmed.
 *  - client_settings.feature_online_booking: paid-plan flag (Silver/Gold),
 *    synced from billing like feature_sms. Set here from each plan.
 *
 * Run via web: /setup/migrations/migrate_online_booking.php (super-admin). Idempotent.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once dirname(__DIR__, 2) . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
require_once dirname(__DIR__, 2) . '/_partials/billing_helpers.php';
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS online_booking_settings (
        client_id        INT UNSIGNED NOT NULL PRIMARY KEY,
        enabled          TINYINT(1)   NOT NULL DEFAULT 0,
        slug             VARCHAR(60)  NULL,
        mode             VARCHAR(10)  NOT NULL DEFAULT 'request',
        min_notice_days  TINYINT UNSIGNED  NOT NULL DEFAULT 1,
        max_days_ahead   SMALLINT UNSIGNED NOT NULL DEFAULT 28,
        weekdays         VARCHAR(20)  NOT NULL DEFAULT '1,2,3,4,5',
        closed_dates     TEXT NULL,
        base_postcode    VARCHAR(10)  NULL,
        radius_miles     SMALLINT UNSIGNED NULL,
        districts        TEXT NULL,
        intro_text       TEXT NULL,
        notify_email     VARCHAR(190) NULL,
        updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_obs_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "online_booking_settings table ready.\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS online_bookings (
        id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        client_id        INT UNSIGNED NOT NULL,
        appointment_id   INT UNSIGNED NULL,
        customer_id      INT UNSIGNED NULL,
        matched_existing TINYINT(1)   NOT NULL DEFAULT 0,
        status           VARCHAR(12)  NOT NULL DEFAULT 'requested',
        area_check       VARCHAR(12)  NOT NULL DEFAULT 'none',
        distance_miles   DECIMAL(6,1) NULL,
        ip_address       VARCHAR(45)  NOT NULL DEFAULT '',
        user_agent       VARCHAR(255) NULL,
        submitted        TEXT NULL,
        decided_by       INT UNSIGNED NULL,
        decided_at       DATETIME NULL,
        created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ob_client_time (client_id, created_at),
        KEY idx_ob_ip_time (ip_address, created_at),
        KEY idx_ob_appt (appointment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "online_bookings table ready.\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS postcode_geo (
        postcode    VARCHAR(8)   NOT NULL PRIMARY KEY,
        found       TINYINT(1)   NOT NULL DEFAULT 1,
        lat         DECIMAL(9,6) NULL,
        lng         DECIMAL(9,6) NULL,
        fetched_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "postcode_geo table ready.\n";

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetchColumn() !== false;
};

if ($colExists('appointments', 'online_request')) {
    echo "appointments.online_request already there.\n";
} else {
    $pdo->exec('ALTER TABLE appointments ADD COLUMN online_request TINYINT(1) NOT NULL DEFAULT 0');
    echo "Added appointments.online_request.\n";
}

if ($colExists('client_settings', 'feature_online_booking')) {
    echo "client_settings.feature_online_booking already there.\n";
} else {
    $pdo->exec('ALTER TABLE client_settings ADD COLUMN feature_online_booking TINYINT(1) NOT NULL DEFAULT 0');
    echo "Added client_settings.feature_online_booking.\n";
}

// Switch the flag on for every company whose plan includes it. Only this one
// column is touched — the other feature flags are left exactly as they are.
$n = 0;
$upd = $pdo->prepare('UPDATE client_settings SET feature_online_booking = ? WHERE client_id = ?');
foreach ($pdo->query('SELECT id FROM clients')->fetchAll(PDO::FETCH_COLUMN) as $cid) {
    $upd->execute([billing_feature_active_for((int) $cid, 'feature_online_booking') ? 1 : 0, (int) $cid]);
    $n++;
}
$on = (int) $pdo->query('SELECT COUNT(*) FROM client_settings WHERE feature_online_booking = 1')->fetchColumn();
echo "Checked $n companies; online booking is available to $on (Silver/Gold).\n";
echo "Done.\n";
