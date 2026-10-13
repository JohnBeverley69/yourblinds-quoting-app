<?php
declare(strict_types=1);

/**
 * Migration: a separate "Factory office" tick (client_users.can_factory_office).
 *
 * Until now "Create orders" did two jobs on a factory account: it let someone
 * place shop orders AND, with the Factory role, made them factory office
 * (Pipeline, Process order, production set-up). So ticking it for a
 * salesperson who also had the Factory role quietly made them office too
 * (step 3 of Tyler's critique, John 2026-10-13). Factory office is now its own
 * tick; "Create orders" means shop orders only.
 *
 * Nobody loses anything: everyone who is factory office today — a Factory-role
 * user on a factory account with "Create orders" ticked — gets the new tick.
 * The back-fill runs only when the column is first added, so a tick an admin
 * later takes off stays off.
 *
 * Run via web: /setup/migrations/migrate_factory_office_perm.php (super-admin). Idempotent.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once dirname(__DIR__, 2) . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$hasCol = true;
try { $pdo->query('SELECT can_factory_office FROM client_users LIMIT 0'); } catch (Throwable $e) { $hasCol = false; }

if ($hasCol) {
    echo "  client_users.can_factory_office already exists — skipped (back-fill not re-run).\n";
} else {
    $pdo->exec('ALTER TABLE client_users ADD COLUMN can_factory_office TINYINT(1) NOT NULL DEFAULT 0 AFTER can_create_orders');
    echo "  Added client_users.can_factory_office.\n";
    $n = $pdo->exec(
        "UPDATE client_users u
           JOIN clients c ON c.id = u.client_id AND c.is_factory = 1
            SET u.can_factory_office = 1
          WHERE COALESCE(u.can_create_orders, 0) = 1
            AND EXISTS (SELECT 1 FROM client_user_roles r WHERE r.user_id = u.id AND r.role = 'factory')"
    );
    echo "  Ticked Factory office for $n existing factory office user(s).\n";
}

echo "\nDone. Factory office is now its own tick on Admin -> Users (factory accounts only).\n";
