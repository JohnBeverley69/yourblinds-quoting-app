<?php
declare(strict_types=1);

/**
 * Migration: trial_grants — one free trial per person (_partials/signup_trial.php).
 *
 * New table only. Backfills every existing self-sign-up trial (the
 * "Auto-granted … trial on self sign-up" overrides) with the hashed email of
 * that account's users, so people who already had a trial can't get another.
 * (No IP is known for those, so only the email rule applies to them.)
 *
 * Run via web: /migrate_trial_grants.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);
require_once __DIR__ . '/_partials/signup_trial.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS trial_grants (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email_key  CHAR(64)    NOT NULL,
        ip         VARCHAR(45) NOT NULL DEFAULT '',
        client_id  INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_tg_email (email_key),
        KEY idx_tg_ip_time (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "trial_grants ready.\n";

// Backfill: accounts that already had a self-sign-up trial.
$added = 0;
try {
    $rows = $pdo->query(
        "SELECT DISTINCT o.client_id, u.email
           FROM client_plan_overrides o
           JOIN client_users u ON u.client_id = o.client_id
          WHERE o.override_type = 'trial'
            AND o.notes LIKE '%self sign-up%'
            AND u.email IS NOT NULL AND u.email <> ''"
    )->fetchAll(PDO::FETCH_ASSOC);
    $have = $pdo->prepare('SELECT 1 FROM trial_grants WHERE email_key = ? LIMIT 1');
    $ins  = $pdo->prepare('INSERT INTO trial_grants (email_key, ip, client_id) VALUES (?, \'\', ?)');
    foreach ($rows as $r) {
        $k = trial_email_key((string) $r['email']);
        $have->execute([$k]);
        if ($have->fetchColumn()) continue;
        $ins->execute([$k, (int) $r['client_id']]);
        $added++;
    }
} catch (Throwable $e) {
    echo "Backfill skipped: " . $e->getMessage() . "\n";
}
echo "Backfilled {$added} earlier trial(s).\n";
