<?php
declare(strict_types=1);

/**
 * Remakes (Factory Console stage 3, John 2026-10-09).
 *
 *   factory_remake_reasons  — the factory's own editable list of reasons
 *   factory_remakes         — one remake: which order, why, who raised it, the
 *                             approval, who pays (free / charge / supplier claim),
 *                             what it cost us (trade price of the blinds) and the
 *                             remake order it became
 *   factory_remake_items    — which blinds (and how many) are being remade
 *   quotes.remake_of_quote_id — set on the remake ORDER, pointing at the original
 *
 * Idempotent, super-admin, web-runnable: /migrate_remakes.php
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableExists = static function (string $t) use ($pdo): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $s->execute([$t]);
    return (bool) $s->fetchColumn();
};
$colExists = static function (string $t, string $c) use ($pdo): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
    $s->execute([$t, $c]);
    return (bool) $s->fetchColumn();
};

if ($tableExists('factory_remake_reasons')) {
    echo "factory_remake_reasons already exists — skipped.\n";
} else {
    $pdo->exec("CREATE TABLE factory_remake_reasons (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        factory_client_id INT          NOT NULL,
        label             VARCHAR(120) NOT NULL,
        sort_order        INT          NOT NULL DEFAULT 0,
        active            TINYINT(1)   NOT NULL DEFAULT 1,
        created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_rr_factory (factory_client_id, active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created factory_remake_reasons.\n";
}

if ($tableExists('factory_remakes')) {
    echo "factory_remakes already exists — skipped.\n";
} else {
    $pdo->exec("CREATE TABLE factory_remakes (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        factory_client_id  INT           NOT NULL,
        account_client_id  INT           NOT NULL,
        source_quote_id    INT           NOT NULL,          -- the original order
        remake_quote_id    INT           NULL,              -- the remake order, once approved
        status             VARCHAR(20)   NOT NULL DEFAULT 'requested',  -- requested|approved|declined
        reason_id          INT           NULL,
        reason_label       VARCHAR(120)  NOT NULL DEFAULT '',
        note               TEXT          NULL,
        photo_path         VARCHAR(255)  NULL,
        raised_by          VARCHAR(10)   NOT NULL DEFAULT 'factory',     -- factory|account
        raised_by_user_id  INT           NULL,
        decided_by_user_id INT           NULL,
        decided_at         DATETIME      NULL,
        decline_reason     VARCHAR(255)  NULL,
        charge_mode        VARCHAR(10)   NULL,              -- free|charge|supplier
        charge_amount      DECIMAL(10,2) NOT NULL DEFAULT 0, -- net, what the account is billed
        supplier_name      VARCHAR(120)  NULL,              -- supplier claim
        cost               DECIMAL(10,2) NOT NULL DEFAULT 0, -- trade price of the blinds remade
        due_date           DATE          NULL,
        created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_rm_factory (factory_client_id, status, created_at),
        KEY idx_rm_source (source_quote_id),
        KEY idx_rm_remake (remake_quote_id),
        KEY idx_rm_account (account_client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created factory_remakes.\n";
}

if ($tableExists('factory_remake_items')) {
    echo "factory_remake_items already exists — skipped.\n";
} else {
    $pdo->exec("CREATE TABLE factory_remake_items (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        remake_id      INT NOT NULL,
        source_item_id INT NOT NULL,
        quantity       INT NOT NULL DEFAULT 1,
        KEY idx_rmi_remake (remake_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created factory_remake_items.\n";
}

if ($colExists('quotes', 'remake_of_quote_id')) {
    echo "quotes.remake_of_quote_id already exists — skipped.\n";
} else {
    $pdo->exec('ALTER TABLE quotes ADD COLUMN remake_of_quote_id INT NULL, ADD KEY idx_quotes_remake_of (remake_of_quote_id)');
    echo "Added quotes.remake_of_quote_id.\n";
}

echo "\nDone. Remakes are ready: Factory Console → Remakes.\n";
