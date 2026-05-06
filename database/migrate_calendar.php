<?php
declare(strict_types=1);

/**
 * Online migration for the calendar module.
 *
 * Idempotent: safe to run multiple times. Creates the appointments table if
 * missing, and adds the home_* columns to client_users if missing. No DROPs,
 * no data loss.
 *
 * Run via CLI:   php database/migrate_calendar.php
 * Run via web:   /database/migrate_calendar.php  (requires admin login)
 *
 * NOTE: MySQL 8.0 does not support `ADD COLUMN IF NOT EXISTS`, so this script
 * checks INFORMATION_SCHEMA before each ALTER. It works on MySQL 5.7+ and 8.x.
 */

require __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require __DIR__ . '/../auth/middleware.php';
    requireAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

$log = static function (string $msg): void {
    echo $msg . "\n";
    @ob_flush();
    @flush();
};

$pdo = db();

$log('Calendar migration starting...');
$log('Database: ' . (string) $pdo->query('SELECT DATABASE()')->fetchColumn());
$log('');

// ---------------------------------------------------------------------------
// 1) appointments table
// ---------------------------------------------------------------------------
$apptCheck = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
);
$apptCheck->execute(['appointments']);
$apptExists = (int) $apptCheck->fetchColumn() > 0;

if ($apptExists) {
    $log('SKIP  appointments table already exists');
} else {
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS appointments (
                id                          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                client_id                   INT UNSIGNED  NOT NULL,
                client_user_id              INT UNSIGNED      NULL,
                customer_id                 INT UNSIGNED      NULL,
                title                       VARCHAR(150)  NOT NULL,
                appointment_date            DATE          NOT NULL,
                appointment_time            TIME          NOT NULL,
                duration_minutes            SMALLINT UNSIGNED NOT NULL DEFAULT 60,
                installation_address1       VARCHAR(150)      NULL,
                installation_address2       VARCHAR(150)      NULL,
                installation_town           VARCHAR(100)      NULL,
                installation_county         VARCHAR(100)      NULL,
                installation_postcode       VARCHAR(20)       NULL,
                different_billing_address   TINYINT(1)    NOT NULL DEFAULT 0,
                billing_address1            VARCHAR(150)      NULL,
                billing_address2            VARCHAR(150)      NULL,
                billing_town                VARCHAR(100)      NULL,
                billing_county              VARCHAR(100)      NULL,
                billing_postcode            VARCHAR(20)       NULL,
                notes                       TEXT              NULL,
                status                      ENUM('booked','completed','cancelled','no_show')
                                                          NOT NULL DEFAULT 'booked',
                created_at                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_appointments_client_date (client_id, appointment_date),
                KEY idx_appointments_client_status (client_id, status),
                KEY idx_appointments_client_user (client_user_id),
                KEY idx_appointments_customer (customer_id),
                CONSTRAINT fk_appointments_client
                    FOREIGN KEY (client_id) REFERENCES clients(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_appointments_client_user
                    FOREIGN KEY (client_user_id) REFERENCES client_users(id)
                    ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_appointments_customer
                    FOREIGN KEY (customer_id) REFERENCES customers(id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log('OK    created appointments table');
    } catch (Throwable $e) {
        $log('FAIL  could not create appointments table: ' . $e->getMessage());
        exit(1);
    }
}

$log('');

// ---------------------------------------------------------------------------
// 2) client_users home_* columns. Each is added AFTER the previous one so the
// physical column order matches schema.sql, but functionally they work in any
// position.
// ---------------------------------------------------------------------------
$columns = [
    ['name' => 'home_address1', 'def' => 'VARCHAR(150) NULL', 'after' => 'last_login_at'],
    ['name' => 'home_address2', 'def' => 'VARCHAR(150) NULL', 'after' => 'home_address1'],
    ['name' => 'home_town',     'def' => 'VARCHAR(100) NULL', 'after' => 'home_address2'],
    ['name' => 'home_county',   'def' => 'VARCHAR(100) NULL', 'after' => 'home_town'],
    ['name' => 'home_postcode', 'def' => 'VARCHAR(20)  NULL', 'after' => 'home_county'],
];

$colCheck = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?'
);

foreach ($columns as $c) {
    $colCheck->execute(['client_users', $c['name']]);
    if ((int) $colCheck->fetchColumn() > 0) {
        $log("SKIP  client_users.{$c['name']} already exists");
        continue;
    }

    // If the AFTER target hasn't been added yet (e.g. partial prior run),
    // fall back to appending at the end.
    $colCheck->execute(['client_users', $c['after']]);
    $afterExists = (int) $colCheck->fetchColumn() > 0;
    $afterClause = $afterExists ? " AFTER `{$c['after']}`" : '';

    $sql = "ALTER TABLE client_users ADD COLUMN `{$c['name']}` {$c['def']}{$afterClause}";
    try {
        $pdo->exec($sql);
        $log("OK    added client_users.{$c['name']}");
    } catch (Throwable $e) {
        $log("FAIL  could not add client_users.{$c['name']}: " . $e->getMessage());
        exit(1);
    }
}

$log('');
$log('Migration complete.');
