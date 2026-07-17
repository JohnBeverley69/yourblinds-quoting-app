<?php
declare(strict_types=1);

/**
 * Migration: let a login have no email address.
 *
 * "Could not add user: Column 'email' cannot be null".
 *
 * Back in #369 the CODE was changed to make email optional — workshop staff
 * don't have addresses, and a workstation ("Vertical Head Rail") isn't a person
 * at all. It stores NULL when the field is left blank. But nothing ever relaxed
 * the COLUMN, which has been NOT NULL since day one. So username-only accounts
 * have never actually been creatable; the feature was only half-built, and it
 * sat there looking finished until John tried to make a real one.
 *
 * NULL rather than '': there's a unique index on email, and a second account
 * with '' would collide with the first. Multiple NULLs are fine — SQL treats
 * them as distinct.
 *
 * This RELAXES a constraint (NOT NULL -> NULL), so it can't lose data and every
 * existing row stays valid. The column's exact type is read from the table
 * rather than guessed, so nothing else about it changes.
 *
 * Run via web: /migrate_user_email_optional.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$col = $pdo->query("SHOW COLUMNS FROM client_users LIKE 'email'")->fetch(PDO::FETCH_ASSOC);
if (!$col) { echo "No client_users.email column — nothing to do.\n"; exit; }

echo "  current: {$col['Type']} " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";

if ($col['Null'] === 'YES') {
    echo "  Already nullable — skipped.\n";
} else {
    // Keep the type exactly as it is; only the nullability changes.
    $pdo->exec("ALTER TABLE client_users MODIFY email {$col['Type']} NULL");
    echo "  client_users.email is now optional.\n";
}

// A unique index on email is what makes NULL (not '') the right empty value.
$idx = $pdo->query("SHOW INDEX FROM client_users WHERE Column_name = 'email'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($idx as $i) {
    echo "  index on email: {$i['Key_name']} " . ((int) $i['Non_unique'] === 0 ? '(unique — so blanks must be NULL, and are)' : '(non-unique)') . "\n";
}

$n = (int) $pdo->query("SELECT COUNT(*) FROM client_users WHERE email IS NULL OR email = ''")->fetchColumn();
echo "\n  logins with no email: {$n}\n";
echo "\nDone. Workshop logins can now be created with a username and no email.\n";
