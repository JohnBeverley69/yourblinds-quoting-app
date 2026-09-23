<?php
declare(strict_types=1);

/**
 * Diagnostic: why can't the live site read which git commit it's running?
 *
 * support_app_version() (_partials/support.php) reads .git/HEAD → the branch
 * ref → packed-refs under APP_ROOT, and support tickets show "unknown" on the
 * live site. This reports, read-only, what PHP can actually see there: the
 * paths, existence/readability, the PHP user vs file owners, open_basedir,
 * and the first bytes of HEAD. No secrets are printed (git refs are just
 * commit hashes). Super-admin gated.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$root = APP_ROOT;
$git  = $root . '/.git';
$owner = static function (string $p): string {
    $uid = @fileowner($p);
    if ($uid === false) return '?';
    $name = function_exists('posix_getpwuid') ? (posix_getpwuid($uid)['name'] ?? '') : '';
    return $uid . ($name !== '' ? " ($name)" : '');
};
$line = static function (string $label, string $p) use ($owner): void {
    printf("%-26s exists=%s dir=%s readable=%s owner=%s perms=%s\n  %s\n",
        $label, file_exists($p) ? 'y' : 'n', is_dir($p) ? 'y' : 'n', is_readable($p) ? 'y' : 'n',
        $owner($p), file_exists($p) ? substr(sprintf('%o', fileperms($p)), -4) : '-', $p);
};

echo "APP_ROOT:        {$root}\n";
echo "realpath:        " . (realpath($root) ?: '?') . "\n";
echo "__DIR__/..:      " . (realpath(__DIR__ . '/..') ?: '?') . "\n";
$eu = function_exists('posix_geteuid') ? posix_geteuid() : null;
echo "PHP runs as:     " . ($eu !== null ? $eu . ' (' . (posix_getpwuid($eu)['name'] ?? '') . ')' : get_current_user() . ' (script owner; posix missing)') . "\n";
echo "open_basedir:    " . (ini_get('open_basedir') ?: '(none)') . "\n\n";

$line('.git', $git);
$line('.git/HEAD', $git . '/HEAD');
$head = @file_get_contents($git . '/HEAD');
echo "HEAD contents:   " . ($head === false ? '(read failed: ' . (error_get_last()['message'] ?? '') . ')' : trim($head)) . "\n\n";
if (is_string($head) && strpos(trim($head), 'ref: ') === 0) {
    $ref = substr(trim($head), 5);
    $line('ref file', $git . '/' . $ref);
    $line('packed-refs', $git . '/packed-refs');
}
$line('.git/FETCH_HEAD', $git . '/FETCH_HEAD');
$line('.git/ORIG_HEAD', $git . '/ORIG_HEAD');

echo "\nParent folders (a .git one level up would mean the repo root isn't APP_ROOT):\n";
foreach ([dirname($root), dirname($root, 2)] as $d) $line('  ' . basename($d) . '/.git', $d . '/.git');

echo "\nsupport_app_version() now returns: ";
require_once __DIR__ . '/../_partials/support.php';
var_dump(support_app_version());
