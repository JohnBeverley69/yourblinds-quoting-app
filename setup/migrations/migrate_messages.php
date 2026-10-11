<?php
declare(strict_types=1);

/**
 * Migration: customer texting (SMS), Step 1. See _partials/sms.php.
 *
 *  - messages: one row per text (and, later, per email) the platform sends or
 *    receives, with its delivery status. direction='in' is reserved for the
 *    replies add-on, so nothing here changes when replies are layered on.
 *  - client_settings: feature_sms (paid-plan flag, synced from billing),
 *    sender name, per-company monthly limit (NULL = platform default),
 *    one tick per automatic text, and wording overrides (JSON).
 *  - customers.sms_opt_out: the per-customer "Don't text" tick.
 *
 * After adding feature_sms it sets it from each tenant's plan so Silver/Gold
 * tenants get texting straight away (no other flag is touched).
 *
 * Run via web: /setup/migrations/migrate_messages.php (super-admin). Idempotent.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once dirname(__DIR__, 2) . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
require_once dirname(__DIR__, 2) . '/_partials/billing_helpers.php';
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS messages (
        id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        client_id      INT NOT NULL,
        channel        VARCHAR(10)  NOT NULL DEFAULT 'sms',
        direction      VARCHAR(3)   NOT NULL DEFAULT 'out',
        kind           VARCHAR(40)  NOT NULL DEFAULT '',
        customer_id    INT NULL,
        appointment_id INT NULL,
        user_id        INT NULL,
        to_addr        VARCHAR(190) NOT NULL DEFAULT '',
        from_addr      VARCHAR(190) NOT NULL DEFAULT '',
        body           TEXT NULL,
        segments       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        status         VARCHAR(20)  NOT NULL DEFAULT 'queued',
        status_detail  VARCHAR(255) NULL,
        provider       VARCHAR(20)  NULL,
        provider_ref   VARCHAR(80)  NULL,
        created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        sent_at        DATETIME NULL,
        delivered_at   DATETIME NULL,
        updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_msg_client_time (client_id, created_at),
        KEY idx_msg_provider_ref (provider_ref),
        KEY idx_msg_appt_kind (appointment_id, kind),
        KEY idx_msg_customer (customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "messages table ready.\n";

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetchColumn() !== false;
};

// Step 2: what a text was about (booking confirmation: date|time|window;
// reminder / on-the-way: the date), so the automatic texts never double-send.
if ($colExists('messages', 'ref_key')) {
    echo "messages.ref_key already there.\n";
} else {
    $pdo->exec('ALTER TABLE messages ADD COLUMN ref_key VARCHAR(40) NULL AFTER kind, ADD KEY idx_msg_appt_kind_ref (appointment_id, kind, ref_key)');
    echo "Added messages.ref_key.\n";
}

$settingsCols = [
    'feature_sms'         => 'TINYINT(1) NOT NULL DEFAULT 0',
    'sms_sender_name'     => 'VARCHAR(11) NULL',
    'sms_monthly_limit'   => 'INT NULL',
    'sms_booking_confirm' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'sms_day_before'      => 'TINYINT(1) NOT NULL DEFAULT 0',
    'sms_on_the_way'      => 'TINYINT(1) NOT NULL DEFAULT 0',
    'sms_templates'       => 'TEXT NULL',
];
foreach ($settingsCols as $col => $def) {
    if ($colExists('client_settings', $col)) {
        echo "client_settings.$col already there.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE client_settings ADD COLUMN $col $def");
    echo "Added client_settings.$col.\n";
}

if ($colExists('customers', 'sms_opt_out')) {
    echo "customers.sms_opt_out already there.\n";
} else {
    $pdo->exec('ALTER TABLE customers ADD COLUMN sms_opt_out TINYINT(1) NOT NULL DEFAULT 0');
    echo "Added customers.sms_opt_out.\n";
}

// Switch feature_sms on for every tenant whose plan includes it. Only this one
// column is touched — the other feature flags are left exactly as they are.
$n = 0;
$upd = $pdo->prepare('UPDATE client_settings SET feature_sms = ? WHERE client_id = ?');
foreach ($pdo->query('SELECT id FROM clients')->fetchAll(PDO::FETCH_COLUMN) as $cid) {
    $upd->execute([billing_feature_active_for((int) $cid, 'feature_sms') ? 1 : 0, (int) $cid]);
    $n++;
}
$on = (int) $pdo->query('SELECT COUNT(*) FROM client_settings WHERE feature_sms = 1')->fetchColumn();
echo "Checked $n tenants; texting is on for $on (Silver/Gold).\n";
echo "Done.\n";
