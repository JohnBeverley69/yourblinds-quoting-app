<?php
declare(strict_types=1);

/**
 * Migration: support_tickets — the "Report a problem" inbox.
 *
 *   One row per report sent from the floating Help / Report-a-problem widget
 *   (_partials/support_widget.php → /support/report.php). Alongside what the
 *   user typed, it stores the context that makes a bug fixable without a
 *   back-and-forth: the page, tenant + user, the deployed app version (git
 *   commit), browser/device, recent JavaScript errors and the last few clicks.
 *
 *   Read + worked in Master Admin → Support inbox (/master-admin/support.php).
 *
 * New table only — nothing existing changes.
 *
 * Run via web: /migrate_support_tickets.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$existed = false;
try { $pdo->query('SELECT 1 FROM support_tickets LIMIT 0'); $existed = true; }
catch (Throwable $e) { $existed = false; }

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS support_tickets (
        id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        client_id     INT NULL,
        user_id       INT NULL,
        user_name     VARCHAR(190) NULL,
        company_name  VARCHAR(190) NULL,
        user_email    VARCHAR(190) NULL,
        category      VARCHAR(20)  NOT NULL DEFAULT 'problem',
        message       TEXT NOT NULL,
        page_url      VARCHAR(1000) NULL,
        page_title    VARCHAR(255) NULL,
        app_version   VARCHAR(64)  NULL,
        user_agent    VARCHAR(500) NULL,
        viewport      VARCHAR(40)  NULL,
        js_errors     MEDIUMTEXT NULL,
        breadcrumbs   MEDIUMTEXT NULL,
        status        VARCHAR(20)  NOT NULL DEFAULT 'new',
        admin_notes   TEXT NULL,
        created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_status_created (status, created_at),
        KEY idx_client (client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

echo $existed
    ? "  support_tickets already existed — no change.\n"
    : "  Created support_tickets.\n";

echo "\nDone. The Report-a-problem widget now saves to Master Admin → Support inbox.\n";
