<?php
declare(strict_types=1);

/**
 * FRESH INSTALL — the first company and super-admin login on an EMPTY database.
 *
 * Use it to stand up a new environment from git alone (a staging copy, a
 * disaster-recovery rebuild when no backup can be restored, a new white-label
 * host). For a real recovery, RESTORE A BACKUP instead — see README "Recovery";
 * this only gives you an empty, working system.
 *
 * Steps (README "Fresh install" has the full list):
 *   1. mysql -u <user> -p <db> < tests/schema.sql      # every table, no data
 *   2. php setup/tools/install_fresh.php --company="Beverley Blinds Trade" \
 *          --name="John Beverley" --email=you@example.com
 *      (asks for the password; it is never taken on the command line)
 *   3. run the migrations (all idempotent — a no-op on a fresh schema)
 *
 * It creates the factory company at FACTORY_CLIENT_ID (from .env, default 3,
 * so factory_client_id() matches), marks it a factory, and makes the login an
 * admin + super-admin with a verified email. It REFUSES if any company exists,
 * so it can never touch a live database. CLI only.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__, 2) . '/bootstrap.php';

$opt = getopt('', ['company:', 'name:', 'email:', 'password-stdin']);
$company = trim((string) ($opt['company'] ?? ''));
$name    = trim((string) ($opt['name'] ?? ''));
$email   = strtolower(trim((string) ($opt['email'] ?? '')));
if ($company === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php setup/tools/install_fresh.php --company=\"Name\" --name=\"Your name\" --email=you@example.com\n");
    exit(2);
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $n = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
} catch (Throwable $e) {
    fwrite(STDERR, "No 'clients' table — load tests/schema.sql into this database first.\n");
    exit(1);
}
if ($n > 0) {
    fwrite(STDERR, "REFUSED: this database already has $n compan" . ($n === 1 ? 'y' : 'ies') . ". install_fresh only runs on an empty database.\n");
    exit(1);
}

// Password: from stdin (piped, for scripted runs with --password-stdin) or typed at the prompt.
if (isset($opt['password-stdin'])) {
    $password = rtrim((string) fgets(STDIN), "\r\n");
} else {
    fwrite(STDOUT, 'Password for ' . $email . ' (min 8 characters): ');
    if (DIRECTORY_SEPARATOR === '/') @shell_exec('stty -echo');
    $password = rtrim((string) fgets(STDIN), "\r\n");
    if (DIRECTORY_SEPARATOR === '/') { @shell_exec('stty echo'); fwrite(STDOUT, "\n"); }
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(2);
}

$factoryId = (int) (function_exists('env') ? (env('FACTORY_CLIENT_ID', '3') ?? 3) : 3);

$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO clients (id, company_name, active) VALUES (?, ?, 1)')->execute([$factoryId, $company]);
    try { $pdo->prepare('UPDATE clients SET is_factory = 1 WHERE id = ?')->execute([$factoryId]); } catch (Throwable $e) { /* older schema */ }
    $pdo->prepare('INSERT INTO client_settings (client_id) VALUES (?)')->execute([$factoryId]);
    $pdo->prepare(
        'INSERT INTO client_users (client_id, email, full_name, password_hash, role, active, is_super_admin,
                                   can_create_quotes, can_create_orders, can_view_all_customer_jobs, can_view_costs,
                                   can_factory_office, dash_view_revenue, email_verified_at)
         VALUES (?, ?, ?, ?, "admin", 1, 1, 1, 1, 1, 1, 1, 1, NOW())'
    )->execute([$factoryId, $email, $name, password_hash($password, PASSWORD_DEFAULT)]);
    $uid = (int) $pdo->lastInsertId();
    foreach (['admin', 'factory'] as $role) {
        try { $pdo->prepare('INSERT INTO client_user_roles (user_id, role) VALUES (?, ?)')->execute([$uid, $role]); }
        catch (Throwable $e) { /* roles table absent on an old schema */ }
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAILED — nothing was created: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Created company #$factoryId \"$company\" (the factory) and super-admin $email.\n";
echo "Next: run the migrations (idempotent), set .env, then sign in at /auth/login.php.\n";
