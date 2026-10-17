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

// The schema must be the current one (tests/schema.sql). Checked up front, so a
// genuine database error below is never mistaken for an "older schema".
$has = static function (string $table, ?string $col = null) use ($pdo): bool {
    $sql = $col === null
        ? 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        : 'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?';
    $st = $pdo->prepare($sql);
    $st->execute($col === null ? [$table] : [$table, $col]);
    return (int) $st->fetchColumn() > 0;
};
$needs = ['clients.is_factory' => $has('clients', 'is_factory'), 'client_settings' => $has('client_settings'),
          'client_user_roles' => $has('client_user_roles'), 'client_users.is_super_admin' => $has('client_users', 'is_super_admin')];
if (in_array(false, $needs, true)) {
    fwrite(STDERR, 'REFUSED: this database is not the current schema (missing: '
        . implode(', ', array_keys(array_filter($needs, static fn($ok) => !$ok)))
        . "). Load tests/schema.sql from this checkout first.\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO clients (id, company_name, active, is_factory) VALUES (?, ?, 1, 1)')->execute([$factoryId, $company]);
    $pdo->prepare('INSERT INTO client_settings (client_id) VALUES (?)')->execute([$factoryId]);
    $pdo->prepare(
        'INSERT INTO client_users (client_id, email, full_name, password_hash, role, active, is_super_admin,
                                   can_create_quotes, can_create_orders, can_view_all_customer_jobs, can_view_costs,
                                   can_factory_office, dash_view_revenue, email_verified_at)
         VALUES (?, ?, ?, ?, "admin", 1, 1, 1, 1, 1, 1, 1, 1, NOW())'
    )->execute([$factoryId, $email, $name, password_hash($password, PASSWORD_DEFAULT)]);
    $uid = (int) $pdo->lastInsertId();
    foreach (['admin', 'factory'] as $role) {
        $pdo->prepare('INSERT INTO client_user_roles (user_id, role) VALUES (?, ?)')->execute([$uid, $role]);
    }
    // Verify before committing: the company is a factory and the login has both roles.
    $chk = $pdo->prepare('SELECT (SELECT is_factory FROM clients WHERE id = ?) AS f,
                                 (SELECT COUNT(*) FROM client_user_roles WHERE user_id = ?) AS r');
    $chk->execute([$factoryId, $uid]);
    $v = $chk->fetch(PDO::FETCH_ASSOC);
    if ((int) $v['f'] !== 1 || (int) $v['r'] !== 2) {
        throw new RuntimeException('check failed after insert (is_factory=' . $v['f'] . ', roles=' . $v['r'] . ')');
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAILED — nothing was created: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Created company #$factoryId \"$company\" (the factory) and super-admin $email.\n";
echo "Next: run the migrations (idempotent), set .env, then sign in at /auth/login.php.\n";
