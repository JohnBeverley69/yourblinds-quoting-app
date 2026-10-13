<?php
declare(strict_types=1);

/**
 * Migration: prepaid TEXT BUNDLES (John, 2026-10-13).
 *
 * Texts cost us money, so the monthly number is an ALLOWANCE, and anything
 * over it comes from bundles a company buys up front through PayPal. Unused
 * bundle texts carry over month to month.
 *
 *   sms_bundles           — one row per bundle bought (pending until PayPal
 *                           confirms payment, then paid). The texts in it are
 *                           the company's to use whenever.
 *   messages.from_bundle  — 1 on a text sent after the month's allowance ran
 *                           out, so it's paid for out of the bundle balance
 *                           (balance = paid bundle texts − texts sent from_bundle).
 *
 * Packs and prices are set by the super-admin on Master Admin → Texts
 * (platform_config SMS_BUNDLES); none are offered until they are.
 *
 * Run via web: /setup/migrations/migrate_sms_bundles.php (super-admin). Idempotent.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once dirname(__DIR__, 2) . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->query('SELECT 1 FROM sms_bundles LIMIT 0');
    echo "  sms_bundles already exists — skipped.\n";
} catch (Throwable $e) {
    $pdo->exec(
        "CREATE TABLE sms_bundles (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            client_id         INT NOT NULL,
            texts             INT NOT NULL,
            price             DECIMAL(10,2) NOT NULL,
            currency          CHAR(3) NOT NULL DEFAULT 'GBP',
            status            VARCHAR(12) NOT NULL DEFAULT 'pending',   -- pending | paid | cancelled
            paypal_order_id   VARCHAR(64) NULL,
            paypal_capture_id VARCHAR(64) NULL,
            bought_by_user_id INT NULL,
            note              VARCHAR(255) NULL,                        -- e.g. 'added by super-admin'
            created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at           DATETIME NULL,
            UNIQUE KEY uq_sms_bundle_order (paypal_order_id),
            KEY idx_sms_bundle_client (client_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  Created sms_bundles.\n";
}

try {
    $pdo->query('SELECT from_bundle FROM messages LIMIT 0');
    echo "  messages.from_bundle already exists — skipped.\n";
} catch (Throwable $e) {
    $pdo->exec('ALTER TABLE messages ADD COLUMN from_bundle TINYINT(1) NOT NULL DEFAULT 0 AFTER segments');
    echo "  Added messages.from_bundle.\n";
}

echo "\nDone. Set the packs and prices on Master Admin -> Texts; companies buy them on Messages -> Buy more texts.\n";
