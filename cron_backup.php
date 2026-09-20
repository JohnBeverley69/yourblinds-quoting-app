<?php
declare(strict_types=1);

/**
 * Scheduled database backup.
 *
 * Writes the same SQL dump the Backup & Restore screen produces into
 * _backups/auto-daily-<date>.sql, then prunes to the newest $KEEP.
 *
 * WHY THIS EXISTS: the only automatic dumps were the ones taken immediately
 * before a restore, so if nothing had ever been restored there was nothing to
 * go back to. On 2026-09-20 an order was deleted and there was no copy of it
 * anywhere in the app. A daily dump is the difference between "restore that
 * row" and "type it all in again".
 *
 * RUN IT — one of:
 *   CLI (what the cron should use, no auth needed, nothing served):
 *       php /path/to/public_html/cron_backup.php
 *   Web, super-admin only, for a manual run or to check it works:
 *       /cron_backup.php
 *   Web, unattended (Cloudways cron via URL), with the token from
 *   Settings → app setting `backup_cron_token`:
 *       /cron_backup.php?token=<token>
 *
 * The dumps land in _backups/, which is closed to the web by its own .htaccess.
 * Download them from the Backup & Restore screen, or off the server directly.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_partials/db_backup.php';

$isCli = PHP_SAPI === 'cli';

/** How many daily dumps to keep. Older ones are deleted after a successful run. */
$KEEP = 14;

// ---- Who may run this ------------------------------------------------------
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');

    // A token lets an unattended cron call this over HTTP without a session.
    // Compared in constant time so the endpoint can't be used as an oracle.
    $token    = (string) ($_GET['token'] ?? '');
    $expected = '';
    try {
        require_once __DIR__ . '/_partials/app_settings.php';
        $expected = (string) app_setting_get('backup_cron_token', '');
    } catch (Throwable $e) { /* no settings table — fall through to the login check */ }

    $tokenOk = $expected !== '' && $token !== '' && hash_equals($expected, $token);

    if (!$tokenOk) {
        // No token (or a wrong one): fall back to an actual signed-in super-admin.
        require_once __DIR__ . '/auth/middleware.php';
        requireSuperAdmin();
    }
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$backupDir = __DIR__ . '/_backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
    @file_put_contents($backupDir . '/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($backupDir . '/index.html', '');
}
if (!is_writable($backupDir)) {
    fwrite($isCli ? STDERR : STDOUT, "Backup directory is not writable: {$backupDir}\n");
    exit(1);
}

// ---- Dump ------------------------------------------------------------------
// Written to a .part file and renamed only once the dump has finished, so a run
// that dies half way through can never leave behind a truncated file that looks
// like a good backup.
$stamp = date('Y-m-d-His');
$final = $backupDir . '/auto-daily-' . $stamp . '.sql';
$part  = $final . '.part';

$started = microtime(true);
$fh = @fopen($part, 'wb');
if (!$fh) {
    fwrite($isCli ? STDERR : STDOUT, "Could not open {$part} for writing.\n");
    exit(1);
}

try {
    pe_stream_dump(db(), $fh);
    fclose($fh);
    if (!@rename($part, $final)) {
        @unlink($part);
        throw new RuntimeException('Could not move the finished dump into place.');
    }
} catch (Throwable $e) {
    if (is_resource($fh)) fclose($fh);
    @unlink($part);
    fwrite($isCli ? STDERR : STDOUT, 'Backup FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}

$size = (int) @filesize($final);
$secs = round(microtime(true) - $started, 1);
echo 'Wrote ' . basename($final) . ' — ' . number_format($size / 1048576, 2) . " MB in {$secs}s\n";

// A dump far smaller than the last one means something went wrong quietly —
// say so rather than silently keeping a near-empty "backup".
$existing = glob($backupDir . '/auto-daily-*.sql') ?: [];
sort($existing);
if (count($existing) > 1) {
    $prev = (int) @filesize($existing[count($existing) - 2]);
    if ($prev > 0 && $size < $prev * 0.5) {
        echo "WARNING: this dump is less than half the size of the previous one ({$prev} bytes). Check it.\n";
    }
}

// ---- Prune -----------------------------------------------------------------
// Oldest first; keep the newest $KEEP. Only ever touches auto-daily-* files, so
// a dump someone saved by hand is never cleared up from under them.
if (count($existing) > $KEEP) {
    foreach (array_slice($existing, 0, count($existing) - $KEEP) as $old) {
        if (@unlink($old)) echo 'Pruned ' . basename($old) . "\n";
    }
}

echo 'Kept ' . min(count($existing), $KEEP) . " daily backup(s).\n";
