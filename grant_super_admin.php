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
 *   /grant_super_admin.php?email=her@example.com
 *   /grant_super_admin.php?username=boss
 *   add &revoke=1 to take it back.
 *
 * Looking is a GET; APPLYING is a POST carrying a CSRF token, because this is
 * the single biggest privilege jump in the system. It used to apply on a bare
 * GET with &confirm=1, which meant one link clicked by a signed-in super-admin
 * (the session cookie is SameSite=Lax, so it rides a top-level navigation)
 * could silently hand platform-wide super-admin to any account named in the URL.
 *
 * NOTE the lookup is deliberately NOT scoped to a tenant — this raises someone
 * to the platform master role, which sits above every tenant. The tenant the
 * account belongs to is shown below so you can see exactly who you are raising.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$isPost   = $_SERVER['REQUEST_METHOD'] === 'POST';
$src      = $isPost ? $_POST : $_GET;
$email    = trim((string) ($src['email'] ?? ''));
$username = trim((string) ($src['username'] ?? ''));
$revoke   = !empty($src['revoke']);

?><!doctype html>
<meta charset="utf-8">
<title>Grant super-admin</title>
<style>
  body { font: 15px/1.55 ui-monospace, Menlo, Consolas, monospace; max-width: 44rem;
         margin: 2rem auto; padding: 0 1rem; color: #1f2933; background: #fff; }
  .row { margin: .15rem 0; }
  .warn { background: #fff6e5; border: 1px solid #e3b341; padding: .75rem 1rem; border-radius: 6px; margin: 1rem 0; }
  button { font: inherit; padding: .5rem 1rem; border-radius: 6px; border: 1px solid #b42318;
           background: #d92d20; color: #fff; cursor: pointer; }
  button.revoke { background: #475467; border-color: #344054; }
  a { color: #175cd3; }
</style>
<?php

$say = static function (string $s): void { echo '<div class="row">' . e($s) . "</div>\n"; };

if ($email === '' && $username === '') {
    $say('Say who: ?email=her@example.com   or   ?username=boss');
    $say('Add &revoke=1 to take it back. Nothing changes until you press the button.');
    exit;
}

$where = $email !== '' ? 'u.email = ?' : 'u.username = ?';
$arg   = $email !== '' ? $email : $username;

$find = $pdo->prepare(
    "SELECT u.id, u.full_name, u.email, u.username, u.role, u.is_super_admin,
            u.client_id, c.company_name
       FROM client_users u
       LEFT JOIN clients c ON c.id = u.client_id
      WHERE $where LIMIT 1"
);
$find->execute([$arg]);
$u = $find->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    $say('No account with that ' . ($email !== '' ? 'email' : 'username') . ": {$arg}");
    $say('Create the login first on Admin → Users, then run this.');
    exit;
}

$who  = trim((string) ($u['full_name'] ?: $u['username'] ?: $u['email'] ?: ('user ' . $u['id'])));
$now  = (int) $u['is_super_admin'] === 1;
$want = !$revoke;

$say("Account : {$who}  (id {$u['id']}, role {$u['role']})");
$say('Tenant  : ' . (string) ($u['company_name'] ?? '—') . ' (client ' . (int) $u['client_id'] . ')');
$say('Now     : ' . ($now ? 'super-admin' : 'not super-admin'));
$say('Change  : ' . ($want ? 'GRANT super-admin' : 'REVOKE super-admin'));
echo "<br>\n";

if ($now === $want) {
    $say('Already ' . ($want ? 'a super-admin' : 'not a super-admin') . ' — nothing to do.');
    exit;
}

if (!$isPost) {
    echo '<div class="warn">';
    echo $want
        ? '<strong>This makes ' . e($who) . ' equal to you</strong> — every tenant, the master '
          . 'catalogue, subscriptions, the database backup/restore page and this very screen.'
        : '<strong>This removes platform access from ' . e($who) . '.</strong> If that is your own '
          . 'account you may not be able to undo it yourself.';
    echo '</div>', "\n";
    echo '<form method="post">', "\n";
    echo csrf_field(), "\n";
    echo '<input type="hidden" name="email" value="',    e($email),    '">', "\n";
    echo '<input type="hidden" name="username" value="', e($username), '">', "\n";
    if ($revoke) echo '<input type="hidden" name="revoke" value="1">', "\n";
    echo '<button type="submit" class="', ($want ? '' : 'revoke'), '">',
         ($want ? 'Grant super-admin' : 'Revoke super-admin'), '</button>', "\n";
    echo '</form>', "\n";
    exit;
}

csrf_check();

$pdo->prepare('UPDATE client_users SET is_super_admin = ? WHERE id = ?')
    ->execute([$want ? 1 : 0, (int) $u['id']]);

$say(($want ? 'Granted.' : 'Revoked.') . " {$who} is now "
     . ($want ? 'a full super-admin — equal to you.' : 'no longer a super-admin.'));
$say("They'll need to log out and back in for it to take effect.");
