<?php
declare(strict_types=1);

/**
 * Grant (or revoke) super-admin — full parity with the owner.
 *
 * The user form lets you tick Admin + Factory, which covers all of Beverley's
 * operations, the accounts area (when built) and the factory pages. It does NOT
 * expose the top-level super-admin flag on purpose — that's the trade-platform
 * master (managing the 60+ trade customers, catalogue push, subscriptions), and
 * it shouldn't be a stray checkbox anyone could tick.
 *
 * So this is the deliberate button for it. It can only be run by an existing
 * super-admin (you), and it targets one account by email or username. The person
 * MUST already have a login — create it on Admin → Users first (I can't set
 * passwords); this only raises an account that exists.
 *
 *   /grant_super_admin.php?email=her@example.com&confirm=1
 *   /grant_super_admin.php?username=boss&confirm=1
 *   add &revoke=1 to take it back.
 *
 * Without confirm=1 it only SHOWS who it would change — a look before you leap.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$email    = trim((string) ($_GET['email'] ?? ''));
$username = trim((string) ($_GET['username'] ?? ''));
$revoke   = !empty($_GET['revoke']);
$confirm  = !empty($_GET['confirm']);

if ($email === '' && $username === '') {
    echo "Say who: ?email=her\@example.com   or   ?username=boss\n";
    echo "Add &confirm=1 to actually do it. &revoke=1 to take it back.\n";
    exit;
}

$where = $email !== '' ? 'email = ?' : 'username = ?';
$arg   = $email !== '' ? $email : $username;

$find = $pdo->prepare("SELECT id, full_name, email, username, role, is_super_admin FROM client_users WHERE $where LIMIT 1");
$find->execute([$arg]);
$u = $find->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    echo "No account with that " . ($email !== '' ? "email" : "username") . ": {$arg}\n";
    echo "Create the login first on Admin → Users, then run this.\n";
    exit;
}

$who = trim((string) ($u['full_name'] ?: $u['username'] ?: $u['email'] ?: ('user ' . $u['id'])));
$now = (int) $u['is_super_admin'] === 1;
$want = !$revoke;

echo "  Account : {$who}  (id {$u['id']}, role {$u['role']})\n";
echo "  Now     : " . ($now ? 'super-admin' : 'not super-admin') . "\n";
echo "  Change  : " . ($want ? 'GRANT super-admin' : 'REVOKE super-admin') . "\n\n";

if ($now === $want) {
    echo "Already " . ($want ? 'a super-admin' : 'not a super-admin') . " — nothing to do.\n";
    exit;
}

if (!$confirm) {
    echo "This is a look only — nobody changed. Add &confirm=1 to apply.\n";
    exit;
}

$pdo->prepare('UPDATE client_users SET is_super_admin = ? WHERE id = ?')->execute([$want ? 1 : 0, (int) $u['id']]);
echo ($want ? "Granted." : "Revoked.") . " {$who} is now " . ($want ? 'a full super-admin — equal to you.' : 'no longer a super-admin.') . "\n";
echo "They'll need to log out and back in for it to take effect.\n";
