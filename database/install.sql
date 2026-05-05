SET FOREIGN_KEY_CHECKS=0;
-- =============================================================================
-- YourBlinds — install / re-install script.
-- Idempotent: safe to run on a live database that already contains an older
-- copy of these tables (or legacy customer_* tables from the previous schema).
-- =============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Drops, child-first. With FK checks disabled the order is belt-and-braces;
-- with FK checks enabled this exact order would still succeed.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS quote_items;
DROP TABLE IF EXISTS quotes;
DROP TABLE IF EXISTS client_markups;
DROP TABLE IF EXISTS client_discounts;
DROP TABLE IF EXISTS client_settings;
DROP TABLE IF EXISTS customer_discounts;
DROP TABLE IF EXISTS price_table_rows;
DROP TABLE IF EXISTS price_tables;
DROP TABLE IF EXISTS vertical_fabrics;
DROP TABLE IF EXISTS client_users;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS product_groups;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS clients;

-- Other legacy tables that may still be present from the previous schema.
DROP TABLE IF EXISTS customer_markups;
DROP TABLE IF EXISTS customer_settings;
DROP TABLE IF EXISTS customer_users;

-- ===========================================================================
-- CLIENTS  (trade businesses that use YourBlinds)
-- ===========================================================================

CREATE TABLE clients (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_name         VARCHAR(150) NOT NULL,
    account_code         VARCHAR(50)      NULL,
    contact_name         VARCHAR(150)     NULL,
    email                VARCHAR(150)     NULL,
    phone                VARCHAR(50)      NULL,
    address1             VARCHAR(150)     NULL,
    address2             VARCHAR(150)     NULL,
    town                 VARCHAR(100)     NULL,
    county               VARCHAR(100)     NULL,
    postcode             VARCHAR(20)      NULL,
    logo_path            VARCHAR(255)     NULL,
    order_destination    ENUM('beverley_blinds','customer_office','both') NOT NULL DEFAULT 'beverley_blinds',
    quote_destination    ENUM('customer_office','both','none')            NOT NULL DEFAULT 'customer_office',
    office_order_email   VARCHAR(150)     NULL,
    office_quote_email   VARCHAR(150)     NULL,
    notes                TEXT             NULL,
    active               TINYINT(1)   NOT NULL DEFAULT 1,
    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_clients_active (active),
    KEY idx_clients_company (company_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE client_users (
    id                          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id                   INT UNSIGNED NOT NULL,
    username                    VARCHAR(60)      NULL,
    full_name                   VARCHAR(150) NOT NULL,
    first_name                  VARCHAR(80)      NULL,
    last_name                   VARCHAR(80)      NULL,
    email                       VARCHAR(150) NOT NULL,
    password_hash               VARCHAR(255) NOT NULL,
    role                        ENUM('admin','owner','office','sales','agent','readonly')
                                              NOT NULL DEFAULT 'sales',
    can_create_quotes           TINYINT(1)   NOT NULL DEFAULT 1,
    can_create_orders           TINYINT(1)   NOT NULL DEFAULT 0,
    can_view_all_customer_jobs  TINYINT(1)   NOT NULL DEFAULT 0,
    can_view_costs              TINYINT(1)   NOT NULL DEFAULT 0,
    active                      TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at               DATETIME         NULL,
    created_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_client_user_email (email),
    UNIQUE KEY uniq_client_user_username (username),
    KEY idx_client_users_client (client_id),
    KEY idx_client_users_role (role),
    CONSTRAINT fk_client_users_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE client_settings (
    id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id                INT UNSIGNED NOT NULL,
    quote_prefix             VARCHAR(20)      NULL,
    default_markup_percent   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    vat_percent              DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    email_from_name          VARCHAR(150)     NULL,
    reply_to_email           VARCHAR(150)     NULL,
    quote_footer             TEXT             NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_client_settings_client (client_id),
    CONSTRAINT fk_client_settings_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- AUTH OPERATIONAL TABLES
-- ===========================================================================

CREATE TABLE login_attempts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address  VARCHAR(45)     NOT NULL,
    identifier  VARCHAR(190)        NULL,
    successful  TINYINT(1)      NOT NULL DEFAULT 0,
    user_agent  VARCHAR(255)        NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_ip_time (ip_address, created_at),
    KEY idx_login_attempts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME         NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_password_resets_token (token_hash),
    KEY idx_password_resets_user (user_id),
    KEY idx_password_resets_expires (expires_at),
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES client_users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- PER-CLIENT PRODUCT CATALOGUE
-- ===========================================================================

CREATE TABLE product_groups (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id   INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_product_group_client_name (client_id, name),
    KEY idx_product_groups_client (client_id),
    CONSTRAINT fk_product_groups_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id         INT UNSIGNED NOT NULL,
    product_group_id  INT UNSIGNED NOT NULL,
    name              VARCHAR(150) NOT NULL,
    code              VARCHAR(50)      NULL,
    active            TINYINT(1)   NOT NULL DEFAULT 1,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_product_client_group_name (client_id, product_group_id, name),
    KEY idx_products_client (client_id),
    KEY idx_products_group (product_group_id),
    CONSTRAINT fk_products_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_products_group
        FOREIGN KEY (product_group_id) REFERENCES product_groups(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE price_tables (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id    INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NOT NULL,
    table_name   VARCHAR(150) NOT NULL,
    band_code    VARCHAR(20)      NULL,
    notes        VARCHAR(255)     NULL,
    active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_price_table_client_product_band (client_id, product_id, band_code),
    KEY idx_price_tables_client (client_id),
    KEY idx_price_tables_product (product_id),
    CONSTRAINT fk_price_tables_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_price_tables_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE price_table_rows (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    price_table_id    INT UNSIGNED  NOT NULL,
    width_value       DECIMAL(10,3)     NULL,
    drop_value_exact  DECIMAL(10,3)     NULL,
    width_min         DECIMAL(10,2)     NULL,
    width_max         DECIMAL(10,2)     NULL,
    drop_min          DECIMAL(10,2)     NULL,
    drop_max          DECIMAL(10,2)     NULL,
    base_price        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_matrix_cell (price_table_id, width_value, drop_value_exact),
    KEY idx_ptr_lookup (price_table_id, width_value, drop_value_exact),
    CONSTRAINT fk_price_rows_table
        FOREIGN KEY (price_table_id) REFERENCES price_tables(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vertical_fabrics (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id     INT UNSIGNED NOT NULL,
    supplier_name VARCHAR(150) NOT NULL,
    band_code     VARCHAR(20)  NOT NULL,
    fabric_name   VARCHAR(150) NOT NULL,
    colour_name   VARCHAR(150) NOT NULL,
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_vf_client_supplier_band_fabric_colour
        (client_id, supplier_name, band_code, fabric_name, colour_name),
    KEY idx_vf_client (client_id),
    KEY idx_vf_client_supplier (client_id, supplier_name),
    KEY idx_vf_client_supplier_fabric (client_id, supplier_name, fabric_name),
    KEY idx_vf_band (band_code),
    CONSTRAINT fk_vertical_fabrics_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- PER-(CLIENT, PRODUCT) ADJUSTMENTS
-- ===========================================================================

CREATE TABLE client_discounts (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id         INT UNSIGNED NOT NULL,
    product_id        INT UNSIGNED NOT NULL,
    discount_percent  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_client_product_discount (client_id, product_id),
    KEY fk_client_discounts_product (product_id),
    CONSTRAINT fk_client_discounts_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_client_discounts_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE client_markups (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id       INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    markup_percent  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_client_product_markup (client_id, product_id),
    KEY fk_client_markups_product (product_id),
    CONSTRAINT fk_client_markups_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_client_markups_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- END-CUSTOMERS  (the trade business's customers)
-- ===========================================================================

CREATE TABLE customers (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id     INT UNSIGNED NOT NULL,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(150)     NULL,
    phone         VARCHAR(50)      NULL,
    address1      VARCHAR(150)     NULL,
    address2      VARCHAR(150)     NULL,
    town          VARCHAR(100)     NULL,
    county        VARCHAR(100)     NULL,
    postcode      VARCHAR(20)      NULL,
    notes         TEXT             NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customers_client (client_id),
    KEY idx_customers_client_name (client_id, name),
    KEY idx_customers_client_email (client_id, email),
    CONSTRAINT fk_customers_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- QUOTES
-- ===========================================================================

CREATE TABLE quotes (
    id                      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    client_id               INT UNSIGNED  NOT NULL,
    client_user_id          INT UNSIGNED      NULL,
    customer_id             INT UNSIGNED      NULL,
    quote_number            VARCHAR(50)   NOT NULL,
    end_customer_name       VARCHAR(150)  NOT NULL,
    end_customer_email      VARCHAR(150)      NULL,
    end_customer_phone      VARCHAR(50)       NULL,
    end_customer_address1   VARCHAR(150)      NULL,
    end_customer_address2   VARCHAR(150)      NULL,
    end_customer_town       VARCHAR(100)      NULL,
    end_customer_county     VARCHAR(100)      NULL,
    end_customer_postcode   VARCHAR(20)       NULL,
    status                  ENUM('draft','sent','accepted','rejected','ordered','expired','archived')
                                          NOT NULL DEFAULT 'draft',
    subtotal                DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    vat                     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total                   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes                   TEXT              NULL,
    public_token            CHAR(64)          NULL,
    accepted_at             DATETIME          NULL,
    order_date              DATETIME          NULL,
    valid_until             DATE              NULL,
    quote_date              DATETIME          NULL,
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_quote_number (quote_number),
    UNIQUE KEY uniq_quote_public_token (public_token),
    KEY idx_quotes_client_status (client_id, status),
    KEY idx_quotes_client_user (client_user_id),
    KEY idx_quotes_customer (customer_id),
    KEY idx_quotes_created (client_id, created_at),
    CONSTRAINT fk_quotes_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quotes_client_user
        FOREIGN KEY (client_user_id) REFERENCES client_users(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quotes_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_items (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id            INT UNSIGNED    NOT NULL,
    product_id          INT UNSIGNED    NOT NULL,
    line_no             SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    room_name           VARCHAR(80)         NULL,
    description_text    TEXT            NOT NULL,
    width               DECIMAL(10,3)       NULL,
    drop_value          DECIMAL(10,3)       NULL,
    unit                ENUM('mm','cm','m','in') NOT NULL DEFAULT 'm',
    operation_type      VARCHAR(60)         NULL,
    fitting_type        VARCHAR(60)         NULL,
    quantity            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    base_cost           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    discount_percent    DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    markup_percent      DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    sell_price          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    line_total          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    price_table_id      INT UNSIGNED        NULL,
    price_table_row_id  INT UNSIGNED        NULL,
    vertical_fabric_id  INT UNSIGNED        NULL,
    notes               VARCHAR(400)        NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_qi_quote (quote_id, line_no),
    KEY idx_qi_product (product_id),
    KEY idx_qi_price_table (price_table_id),
    KEY idx_qi_price_table_row (price_table_row_id),
    KEY idx_qi_vertical_fabric (vertical_fabric_id),
    CONSTRAINT fk_quote_items_quote
        FOREIGN KEY (quote_id) REFERENCES quotes(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quote_items_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_quote_items_price_table
        FOREIGN KEY (price_table_id) REFERENCES price_tables(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quote_items_price_table_row
        FOREIGN KEY (price_table_row_id) REFERENCES price_table_rows(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quote_items_vertical_fabric
        FOREIGN KEY (vertical_fabric_id) REFERENCES vertical_fabrics(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
