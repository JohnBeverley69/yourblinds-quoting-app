<?php
declare(strict_types=1);

/**
 * Database backup + restore tool. Super-admin only.
 *
 * Three operations driven by POST _action:
 *   - download : stream a full SQL dump as a downloadable .sql file
 *   - restore  : auto-snapshot the current DB, then restore from
 *                an uploaded .sql file
 *   - delete-snapshot : prune one server-side auto-snapshot
 *
 * Storage:
 *   /_backups/ inside the web root, with an .htaccess Deny-from-all
 *   so the files aren't downloadable directly. Auto-snapshots taken
 *   before a restore live here as auto-prerestore-<ts>.sql. Manual
 *   downloads stream directly to the browser and are NOT stored
 *   server-side.
 *
 * NOTE: file uploads (logos, choice images) live on the filesystem,
 * not in the DB. This tool backs up DATA ONLY — keep a separate copy
 * of /uploads if those matter for a full disaster restore.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

// Restores and big dumps can take time on shared hosting.
@set_time_limit(0);
@ini_set('memory_limit', '512M');

$user      = current_user();
$clientId  = (int) $user['client_id'];
$activeNav = 'backup';

$backupDir = __DIR__ . '/../_backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
    // Block direct web access to the dumps. Mod_rewrite-friendly hosts
    // honour this; if .htaccess is ignored, the files still require
    // guessing the timestamped filename — but better to also leave
    // these outside the web root if your host supports it.
    @file_put_contents($backupDir . '/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($backupDir . '/index.html', '');
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// =============================================================
//  Helpers — SQL dump + statement splitter + restore
// =============================================================
// They live in a partial so the scheduled backup can use the very same dump
// this screen produces, rather than a second implementation that could drift.
require_once __DIR__ . '/../_partials/db_backup.php';


// =============================================================
//  Actions
// =============================================================

$pdo    = db();
$action = (string) ($_POST['_action'] ?? '');

if ($action === 'download') {
    csrf_check();
    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $name   = 'yourblinds-' . $dbName . '-' . date('Y-m-d-His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    $fh = fopen('php://output', 'wb');
    pe_stream_dump($pdo, $fh);
    fclose($fh);
    exit;
}

if ($action === 'download-tenant') {
    csrf_check();
    $targetClient = (int) ($_POST['client_id'] ?? 0);
    if ($targetClient <= 0) {
        $_SESSION['flash_error'] = 'Pick a tenant to export.';
        header('Location: /master-admin/backup.php');
        exit;
    }
    // Verify the client exists + grab its name for the filename and the
    // dump header comment.
    $cStmt = $pdo->prepare('SELECT id, company_name FROM clients WHERE id = ? LIMIT 1');
    $cStmt->execute([$targetClient]);
    $clientRow = $cStmt->fetch();
    if (!$clientRow) {
        $_SESSION['flash_error'] = 'Tenant not found.';
        header('Location: /master-admin/backup.php');
        exit;
    }

    $companyLabel = (string) $clientRow['company_name'];
    // Build a filesystem-friendly slug for the filename.
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $companyLabel)) ?: 'tenant';
    $slug = trim($slug, '-');
    $name = 'yourblinds-tenant-' . $slug . '-' . date('Y-m-d-His') . '.sql';

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    $fh = fopen('php://output', 'wb');
    pe_stream_tenant_dump($pdo, $fh, $targetClient, $companyLabel);
    fclose($fh);
    exit;
}

if ($action === 'restore') {
    csrf_check();

    if (empty($_POST['confirm']) || $_POST['confirm'] !== 'yes') {
        $_SESSION['flash_error'] = 'You must tick the confirmation box to restore.';
        header('Location: /master-admin/backup.php');
        exit;
    }

    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        $_SESSION['flash_error'] = 'No backup file was uploaded.';
        header('Location: /master-admin/backup.php');
        exit;
    }

    try {
        // Pre-restore auto-snapshot so a bad restore is never destructive.
        $autoPath = $backupDir . '/auto-prerestore-' . date('Y-m-d-His') . '.sql';
        $autoFh   = fopen($autoPath, 'wb');
        if ($autoFh === false) {
            throw new RuntimeException('Could not open auto-snapshot file for writing.');
        }
        pe_stream_dump($pdo, $autoFh);
        fclose($autoFh);

        // Run the upload.
        $result = pe_restore_file($pdo, $_FILES['file']['tmp_name']);
        $_SESSION['flash_success'] =
            'Restore complete. Ran ' . $result['ran'] . ' of ' . $result['total']
            . ' statements. Auto-snapshot saved as ' . basename($autoPath) . '.';
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Restore failed: ' . $e->getMessage()
            . ' (an auto-snapshot was taken just before — use it to roll back if needed)';
    }

    header('Location: /master-admin/backup.php');
    exit;
}

if ($action === 'delete-snapshot') {
    csrf_check();
    $name = (string) ($_POST['name'] ?? '');
    // Whitelist filename pattern so we can't be tricked into deleting
    // anything outside the backups dir.
    if ($name !== '' && preg_match('/^auto-(?:prerestore|daily)-[\d\-]+\.sql$/', $name)) {
        $full = $backupDir . '/' . $name;
        if (is_file($full)) @unlink($full);
        $_SESSION['flash_success'] = 'Snapshot ' . $name . ' deleted.';
    } else {
        $_SESSION['flash_error'] = 'Invalid snapshot name.';
    }
    header('Location: /master-admin/backup.php');
    exit;
}

// =============================================================
//  GET — render the UI
// =============================================================

// List of server-side auto-snapshots (most recent first).
$snapshots = [];
// Both kinds: the snapshot taken just before a restore, and the scheduled daily
// dump written by cron_backup.php.
foreach (array_merge(
    glob($backupDir . '/auto-prerestore-*.sql') ?: [],
    glob($backupDir . '/auto-daily-*.sql') ?: []
) as $path) {
    $snapshots[] = [
        'name'  => basename($path),
        'size'  => (int) @filesize($path),
        'mtime' => (int) @filemtime($path),
    ];
}
usort($snapshots, static fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

// Lightweight stats so the page tells you what'd be in a backup.
$tableCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
)->fetchColumn();
$rowApprox = (int) $pdo->query(
    "SELECT IFNULL(SUM(TABLE_ROWS), 0)
       FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
)->fetchColumn();

// All tenants, for the per-tenant export dropdown.
$tenants = $pdo->query(
    'SELECT id, company_name, active FROM clients ORDER BY company_name'
)->fetchAll();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Backup &amp; Restore &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .danger-zone {
            border: 1px solid #fecaca; background: #fef2f2;
            border-radius: 10px; padding: 1rem 1.125rem;
        }
        .danger-zone legend {
            font-size: 0.8125rem; font-weight: 700; color: #991b1b;
            text-transform: uppercase; letter-spacing: 0.05em;
            padding: 0 0.5rem;
        }
        .check-row {
            display: flex; align-items: center; gap: 0.5rem;
            margin: 0.75rem 0;
        }
        .check-row input { width: 18px; height: 18px; }
        .snapshot-list {
            width: 100%; border-collapse: collapse; font-size: 0.9375rem;
        }
        .snapshot-list th, .snapshot-list td {
            text-align: left; padding: 0.5rem 0.625rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .snapshot-list th {
            font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.04em; color: var(--text-faint); font-weight: 600;
        }
        .snapshot-list code { font-size: 0.8125rem; color: #1f3b5b; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Backup &amp; Restore</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                </p>
            </div>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success" role="status"><?= e($flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-error" role="alert"><?= e($flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Download backup</h2>
            </div>
            <p style="color:var(--text-faint);font-size:0.9375rem;margin:0 0 0.75rem">
                Streams a full SQL dump of the database
                (<strong><?= $tableCount ?></strong> tables,
                ~<strong><?= number_format($rowApprox) ?></strong> rows)
                as a downloadable <code>.sql</code> file. Restore by
                re-uploading below, or by piping it into
                <code>mysql &lt;db&gt;</code> from the command line.
            </p>
            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0 0 0.75rem">
                Backs up <strong>data only</strong> — file uploads
                (logos, fabric / option images) live in
                <code>/uploads</code> and aren't included. Keep a
                separate copy of that folder if you want a true
                disaster-recovery snapshot.
            </p>
            <form method="post" action="/master-admin/backup.php">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="download">
                <button type="submit" class="btn btn-primary">Download backup now</button>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Per-tenant export</h2>
            </div>
            <p style="color:var(--text-faint);font-size:0.9375rem;margin:0 0 0.75rem">
                Exports <strong>one tenant's data only</strong> — their users,
                customers, quotes, calendar appointments, products, fabrics,
                price tables, markups. Useful for handing a tenant their own
                data takeout, migrating one tenant to a new instance, or
                investigating one tenant's records in isolation.
            </p>
            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0 0 0.75rem">
                Loads cleanly into a <strong>fresh empty database</strong>.
                Loading it into a DB that already has these tables would
                wipe other tenants — strip the DROP/CREATE block first if
                you're doing an in-place single-tenant restore (the file's
                header comment explains).
            </p>
            <form method="post" action="/master-admin/backup.php"
                  style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="download-tenant">
                <label for="tenant-select" class="visually-hidden">Tenant</label>
                <select id="tenant-select" name="client_id" required
                        style="flex:1;min-width:240px;padding:0.5rem 0.625rem;
                               border:1px solid var(--border-strong);border-radius:8px;
                               font:inherit;background:#fff">
                    <option value="">— Choose a tenant —</option>
                    <?php foreach ($tenants as $t): ?>
                        <option value="<?= (int) $t['id'] ?>">
                            <?= e((string) $t['company_name']) ?>
                            <?php if (!(int) $t['active']): ?> (inactive)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Download tenant export</button>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Restore from backup</h2>
            </div>
            <fieldset class="danger-zone">
                <legend>Danger zone</legend>
                <p style="color:#991b1b;font-size:0.9375rem;margin:0 0 0.75rem">
                    Restoring <strong>drops every table and recreates
                    them from the file</strong>. Any data added since
                    the backup will be lost.
                </p>
                <p style="color:var(--text-faint);font-size:0.875rem;margin:0 0 0.75rem">
                    For safety, an automatic snapshot is taken just
                    before the restore runs — listed under "Auto-snapshots"
                    below. If a restore goes wrong, download that
                    snapshot and restore <em>it</em> to roll back.
                </p>
                <form method="post" action="/master-admin/backup.php"
                      enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="restore">
                    <div class="form-group" style="margin:0 0 0.5rem">
                        <label for="file" style="font-weight:600;display:block;margin-bottom:0.25rem">
                            Backup file (.sql)
                        </label>
                        <input id="file" name="file" type="file" accept=".sql,application/sql,text/plain" required>
                    </div>
                    <label class="check-row">
                        <input type="checkbox" name="confirm" value="yes" required>
                        <span>I understand this overwrites the current database.</span>
                    </label>
                    <button type="submit" class="btn btn-danger">Restore database</button>
                </form>
            </fieldset>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Automatic backups</h2>
            </div>
            <p style="color:var(--text-faint);font-size:0.875rem;margin:0 0 0.5rem">
                <code>auto-daily-…</code> are the scheduled backups, written by
                <code>cron_backup.php</code>; the newest 14 are kept.
                <code>auto-prerestore-…</code> are taken automatically just before
                each restore, so a bad restore is never destructive — delete those
                once you're sure the restore worked.
                All stored in <code>/_backups/</code> on the server, blocked from
                direct download.
                <?php
                    // Say how old the newest scheduled dump is. A single stale file
                    // sitting here reads as "backups are fine" when nothing has run
                    // for a month — the page has to be honest about its own age.
                    $dailies = array_values(array_filter(
                        $snapshots,
                        static fn ($s) => strncmp($s['name'], 'auto-daily-', 11) === 0
                    ));
                    $newest  = $dailies ? (int) $dailies[0]['mtime'] : 0;   // list is newest-first
                    $ageDays = $newest ? (int) floor((time() - $newest) / 86400) : null;
                ?>
                <?php if ($ageDays === null): ?>
                    <br>This one hasn't been set up. It's optional if your host already
                    takes daily backups — to use it as well, run
                    <code>php <?= e(dirname(__DIR__)) ?>/cron_backup.php</code> on a cron.
                <?php elseif ($ageDays >= 2): ?>
                    <br><strong style="color:#b45309">The newest scheduled backup here is
                    <?= $ageDays ?> days old</strong> — so nothing is running on a
                    cron. Fine if your host backs up for you; otherwise that file is the
                    only copy this app has, and it's stale.
                <?php else: ?>
                    <br><span style="color:#166534">Newest scheduled backup:
                    <?= $ageDays === 0 ? 'today' : 'yesterday' ?>.</span>
                <?php endif; ?>
            </p>
            <?php if (!$snapshots): ?>
                <p style="color:var(--text-faint);font-style:italic">
                    No auto-snapshots yet. One will be created the
                    first time you run a restore.
                </p>
            <?php else: ?>
                <table class="snapshot-list">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th style="width:10rem">Taken</th>
                            <th style="width:6rem;text-align:right">Size</th>
                            <th style="width:6rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($snapshots as $s): ?>
                            <tr>
                                <td><code><?= e($s['name']) ?></code></td>
                                <td><?= e(date('Y-m-d H:i', $s['mtime'])) ?></td>
                                <td style="text-align:right">
                                    <?= number_format($s['size'] / 1024, 0) ?> KB
                                </td>
                                <td>
                                    <form method="post" action="/master-admin/backup.php"
                                          style="margin:0"
                                          onsubmit="return confirm('Delete this snapshot?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="delete-snapshot">
                                        <input type="hidden" name="name" value="<?= e($s['name']) ?>">
                                        <button type="submit" class="btn btn-secondary"
                                                style="padding:0.25rem 0.625rem;font-size:0.8125rem">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
