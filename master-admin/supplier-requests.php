<?php
declare(strict_types=1);

/**
 * Master Admin: supplier requests from clients (demand-driven roadmap).
 * Shows a tally of the most-requested suppliers + every request, with any
 * attached price list, and lets you mark them handled / delete them.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$user = current_user();
$pdo  = db();

$ready = true;
try { $pdo->query('SELECT 1 FROM supplier_requests LIMIT 0'); }
catch (Throwable $e) { $ready = false; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);
    try {
        if ($action === 'handle' && $id > 0) {
            $pdo->prepare('UPDATE supplier_requests SET status = ? WHERE id = ?')->execute(['handled', $id]);
            $_SESSION['flash_success'] = 'Marked handled.';
        } elseif ($action === 'reopen' && $id > 0) {
            $pdo->prepare('UPDATE supplier_requests SET status = ? WHERE id = ?')->execute(['open', $id]);
            $_SESSION['flash_success'] = 'Reopened.';
        } elseif ($action === 'delete' && $id > 0) {
            $row = $pdo->prepare('SELECT file_path FROM supplier_requests WHERE id = ?');
            $row->execute([$id]);
            $fp = (string) ($row->fetchColumn() ?: '');
            $pdo->prepare('DELETE FROM supplier_requests WHERE id = ?')->execute([$id]);
            // Best-effort remove the attachment.
            if ($fp !== '' && strpos($fp, 'uploads/supplier-requests/') === 0) {
                @unlink(__DIR__ . '/../' . $fp);
            }
            $_SESSION['flash_success'] = 'Request removed.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not update: ' . $e->getMessage();
    }
    header('Location: /master-admin/supplier-requests.php');
    exit;
}

$requests = [];
$tally    = [];
if ($ready) {
    $requests = $pdo->query(
        'SELECT r.id, r.supplier_name, r.website, r.notes, r.file_name, r.file_path,
                r.status, r.created_at, c.company_name
           FROM supplier_requests r
      LEFT JOIN clients c ON c.id = r.client_id
       ORDER BY (r.status = "open") DESC, r.created_at DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    // Demand tally: open requests grouped by supplier name (case-insensitive).
    $tally = $pdo->query(
        'SELECT supplier_name, COUNT(*) AS n
           FROM supplier_requests WHERE status = "open"
       GROUP BY LOWER(supplier_name)
       ORDER BY n DESC, supplier_name LIMIT 12'
    )->fetchAll(PDO::FETCH_ASSOC);
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeNav = 'supplier-requests';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supplier requests &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Supplier requests</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; what clients are asking to be added to the library.
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <?php if (!$ready): ?>
            <section class="section">
                <div class="alert alert-error" role="alert">
                    Run <a href="/migrate_supplier_requests.php"><code>/migrate_supplier_requests.php</code></a> first.
                </div>
            </section>
        <?php else: ?>

        <?php if ($tally): ?>
            <section class="section">
                <h2 class="section-title" style="margin:0 0 0.5rem">Most requested (open)</h2>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                    <?php foreach ($tally as $t): ?>
                        <span style="display:inline-flex;align-items:center;gap:0.4rem;background:var(--bg-subtle-2);border-radius:999px;padding:0.2rem 0.7rem;font-size:0.875rem">
                            <strong><?= e((string) $t['supplier_name']) ?></strong>
                            <span style="background:var(--brand);color:#fff;border-radius:999px;padding:0 0.45rem;font-size:0.75rem;font-weight:700"><?= (int) $t['n'] ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="section">
            <?php if (!$requests): ?>
                <p style="color:var(--text-faint);margin:0">No supplier requests yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Supplier</th><th>From</th><th>Notes</th><th>Price list</th><th>When</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($requests as $r): $open = (string) $r['status'] === 'open'; ?>
                                <tr<?= $open ? '' : ' style="opacity:.6"' ?>>
                                    <td>
                                        <strong><?= e((string) $r['supplier_name']) ?></strong>
                                        <?php if (!empty($r['website'])): ?>
                                            <div style="font-size:.8125rem"><a href="<?= e((strpos((string) $r['website'], 'http') === 0 ? '' : 'https://') . (string) $r['website']) ?>" target="_blank" rel="noopener"><?= e((string) $r['website']) ?></a></div>
                                        <?php endif; ?>
                                        <?php if (!$open): ?><span style="font-size:.6875rem;color:var(--text-faint);text-transform:uppercase">handled</span><?php endif; ?>
                                    </td>
                                    <td><?= e((string) ($r['company_name'] ?? ('client #' . 0))) ?></td>
                                    <td style="color:var(--text-muted);font-size:.875rem;max-width:18rem"><?= e((string) ($r['notes'] ?? '')) ?></td>
                                    <td>
                                        <?php if (!empty($r['file_path'])): ?>
                                            <a href="/master-admin/supplier-request-file.php?id=<?= (int) $r['id'] ?>">&#128229; <?= e((string) ($r['file_name'] ?? 'download')) ?></a>
                                        <?php else: ?>
                                            <span style="color:var(--text-faint)">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space:nowrap;color:var(--text-faint);font-size:.8125rem"><?= e(date('j M Y', strtotime((string) $r['created_at']))) ?></td>
                                    <td class="row-actions" style="white-space:nowrap;text-align:right">
                                        <form method="post" action="/master-admin/supplier-requests.php" style="display:inline;margin:0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="<?= $open ? 'handle' : 'reopen' ?>">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <button type="submit" style="background:none;border:0;color:var(--link);cursor:pointer;font-size:.8125rem;padding:0"><?= $open ? 'Mark handled' : 'Reopen' ?></button>
                                        </form>
                                        <form method="post" action="/master-admin/supplier-requests.php" style="display:inline;margin:0 0 0 .5rem"
                                              data-confirm="Delete this request (and its attachment)?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <button type="submit" style="background:none;border:0;color:#b91c1c;cursor:pointer;font-size:.8125rem;padding:0">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
