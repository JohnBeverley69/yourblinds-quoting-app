<?php
declare(strict_types=1);

/**
 * Migration: Phase 3.1 — quote schema + per-product markup/discount.
 *
 * Creates (if not present):
 *   quotes
 *   quote_items
 *   quote_item_extras
 *   client_markups
 *   client_discounts
 *
 * Idempotent — safe to re-run. Each CREATE TABLE uses IF NOT EXISTS, so
 * already-created tables are skipped silently.
 *
 * Run via CLI:   php migrate_phase3_quotes.php
 * Run via web:   /migrate_phase3_quotes.php   (super-admin login required)
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

// Surface SQL / PDO errors directly into the browser instead of 500-ing,
// so it's obvious which CREATE TABLE failed and why. This is a one-shot
// migration tool — fine to be loud here.
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

set_exception_handler(function (Throwable $e) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "MIGRATION FAILED\n";
    echo "================\n\n";
    echo 'Error:   ' . $e->getMessage()                . "\n";
    echo 'In:      ' . $e->getFile() . ':' . $e->getLine() . "\n";
    if ($e->getPrevious()) {
        echo 'Caused by: ' . $e->getPrevious()->getMessage() . "\n";
    }
    echo "\nThe migration is idempotent — it's safe to fix the issue and re-run.\n";
    exit(1);
});

function table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute([$table]);
    return $st->fetchColumn() !== false;
}

$ops = [];

// ---------------------------------------------------------------------------
// 1. quotes
// ---------------------------------------------------------------------------
if (!table_exists($pdo, 'quotes')) {
    $pdo->exec("
        CREATE TABLE quotes (
            id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id             INT UNSIGNED NOT NULL,
            quote_number          VARCHAR(40)  NOT NULL,
            customer_id           INT UNSIGNED     NULL,
            end_customer_name     VARCHAR(150) NOT NULL,
            end_customer_email    VARCHAR(150)     NULL,
            end_customer_phone    VARCHAR(50)      NULL,
            end_customer_address1 VARCHAR(150)     NULL,
            end_customer_address2 VARCHAR(150)     NULL,
            end_customer_town     VARCHAR(100)     NULL,
            end_customer_county   VARCHAR(100)     NULL,
            end_customer_postcode VARCHAR(20)      NULL,
            status                ENUM('draft','sent','accepted','declined','ordered','invoiced','paid')
                                                NOT NULL DEFAULT 'draft',
            subtotal              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            vat                   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total                 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            vat_percent           DECIMAL(5,2)  NOT NULL DEFAULT 20.00,
            notes                 TEXT             NULL,
            public_token          VARCHAR(64)  NOT NULL,
            sent_at               DATETIME         NULL,
            accepted_at           DATETIME         NULL,
            created_by_user_id    INT UNSIGNED     NULL,
            created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_quote_number_per_client (client_id, quote_number),
            UNIQUE KEY uniq_quote_public_token      (public_token),
            KEY idx_quotes_client_status_created    (client_id, status, created_at),
            KEY idx_quotes_client_customer          (client_id, customer_id),
            CONSTRAINT fk_quotes_client
                FOREIGN KEY (client_id)          REFERENCES clients(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_quotes_customer
                FOREIGN KEY (customer_id)        REFERENCES customers(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_quotes_user
                FOREIGN KEY (created_by_user_id) REFERENCES client_users(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ops[] = 'Created table: quotes';
} else {
    $ops[] = 'Skipped quotes (already present)';
}

// ---------------------------------------------------------------------------
// 2. quote_items
// ---------------------------------------------------------------------------
if (!table_exists($pdo, 'quote_items')) {
    $pdo->exec("
        CREATE TABLE quote_items (
            id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_id                 INT UNSIGNED NOT NULL,
            line_no                  INT UNSIGNED NOT NULL DEFAULT 1,
            product_id               INT UNSIGNED     NULL,
            product_name_snapshot    VARCHAR(150) NOT NULL,
            system_id                INT UNSIGNED     NULL,
            system_name_snapshot     VARCHAR(150)     NULL,
            option_id                INT UNSIGNED     NULL,
            fabric_band_snapshot     VARCHAR(20)      NULL,
            fabric_supplier_snapshot VARCHAR(150)     NULL,
            fabric_name_snapshot     VARCHAR(150)     NULL,
            fabric_colour_snapshot   VARCHAR(150)     NULL,
            fabric_code_snapshot     VARCHAR(50)      NULL,
            room_name                VARCHAR(80)      NULL,
            width_mm                 INT UNSIGNED NOT NULL,
            drop_mm                  INT UNSIGNED NOT NULL,
            width_matrix_mm          INT UNSIGNED     NULL,
            drop_matrix_mm           INT UNSIGNED     NULL,
            quantity                 INT UNSIGNED NOT NULL DEFAULT 1,
            price_table_id           INT UNSIGNED     NULL,
            price_table_row_id       INT UNSIGNED     NULL,
            base_price               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            extras_total             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            subtotal_per_blind       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            markup_percent           DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
            discount_percent         DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
            sell_price               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            line_total               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            notes                    TEXT             NULL,
            created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                  ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_quote_items_quote_line (quote_id, line_no),
            CONSTRAINT fk_quote_items_quote
                FOREIGN KEY (quote_id)           REFERENCES quotes(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_quote_items_product
                FOREIGN KEY (product_id)         REFERENCES products(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_quote_items_system
                FOREIGN KEY (system_id)          REFERENCES product_systems(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_quote_items_option
                FOREIGN KEY (option_id)          REFERENCES product_options(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_quote_items_price_table
                FOREIGN KEY (price_table_id)     REFERENCES price_tables(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_quote_items_price_row
                FOREIGN KEY (price_table_row_id) REFERENCES price_table_rows(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ops[] = 'Created table: quote_items';
} else {
    $ops[] = 'Skipped quote_items (already present)';
}

// ---------------------------------------------------------------------------
// 3. quote_item_extras
// ---------------------------------------------------------------------------
if (!table_exists($pdo, 'quote_item_extras')) {
    $pdo->exec("
        CREATE TABLE quote_item_extras (
            id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_item_id           INT UNSIGNED NOT NULL,
            product_extra_id        INT UNSIGNED     NULL,
            extra_name_snapshot     VARCHAR(150) NOT NULL,
            product_extra_choice_id INT UNSIGNED     NULL,
            choice_label_snapshot   VARCHAR(150)     NULL,
            mode                    ENUM('flat','percent','per_metre','width_table') NOT NULL,
            amount_applied          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_quote_item_extras_item (quote_item_id),
            CONSTRAINT fk_qie_item
                FOREIGN KEY (quote_item_id)           REFERENCES quote_items(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_qie_extra
                FOREIGN KEY (product_extra_id)        REFERENCES product_extras(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_qie_choice
                FOREIGN KEY (product_extra_choice_id) REFERENCES product_extra_choices(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ops[] = 'Created table: quote_item_extras';
} else {
    $ops[] = 'Skipped quote_item_extras (already present)';
}

// ---------------------------------------------------------------------------
// 4. client_markups
// ---------------------------------------------------------------------------
if (!table_exists($pdo, 'client_markups')) {
    $pdo->exec("
        CREATE TABLE client_markups (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id       INT UNSIGNED NOT NULL,
            product_id      INT UNSIGNED NOT NULL,
            markup_percent  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_markup_client_product (client_id, product_id),
            CONSTRAINT fk_markups_client
                FOREIGN KEY (client_id)  REFERENCES clients(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_markups_product
                FOREIGN KEY (product_id) REFERENCES products(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ops[] = 'Created table: client_markups';
} else {
    $ops[] = 'Skipped client_markups (already present)';
}

// ---------------------------------------------------------------------------
// 5. client_discounts
// ---------------------------------------------------------------------------
if (!table_exists($pdo, 'client_discounts')) {
    $pdo->exec("
        CREATE TABLE client_discounts (
            id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id         INT UNSIGNED NOT NULL,
            product_id        INT UNSIGNED NOT NULL,
            discount_percent  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_discount_client_product (client_id, product_id),
            CONSTRAINT fk_discounts_client
                FOREIGN KEY (client_id)  REFERENCES clients(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_discounts_product
                FOREIGN KEY (product_id) REFERENCES products(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ops[] = 'Created table: client_discounts';
} else {
    $ops[] = 'Skipped client_discounts (already present)';
}

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\nNext step: rerun safe — IF NOT EXISTS guards every CREATE.\n";
echo "When you're done, you can delete this file from the server.\n";
