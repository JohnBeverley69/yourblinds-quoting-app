<?php
declare(strict_types=1);

/**
 * Master Admin: Factories.
 *
 * White-label manufacturing. A client with is_factory = 1 runs its own factory
 * back-office (its own queue, worksheets, build rules, scanner). This screen is
 * where the owner turns that on for a trade firm — the onboarding switch.
 *
 * Flagging a client here does two things downstream:
 *   - its admin can then grant the 'factory' role to a user (Users screen), who
 *     gets into the factory app;
 *   - order lines for products it owns route to ITS queue, not Beverley's.
 *
 * The canonical Beverley factory (factory_client_id) can't be un-flagged here —
 * that would break the primary factory.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$user      = current_user();
$pdo       = db();
$canonical = function_exists('factory_client_id') ? factory_client_id() : 3;

// Is the flag migrated yet? Everything degrades to a clear note if not.
$colMissing = false;
try { $pdo->query('SELECT is_factory FROM clients LIMIT 0'); }
catch (Throwable $e) { $colMissing = true; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $clientId = (int) ($_POST['client_id'] ?? 0);
    $want     = (int) ($_POST['to'] ?? 0) === 1 ? 1 : 0;

    if ($colMissing) {
        $_SESSION['flash_error'] = 'Run /migrate_client_is_factory.php first.';
    } elseif ($clientId <= 0) {
        $_SESSION['flash_error'] = 'No account specified.';
    } elseif ($want === 0 && $clientId === $canonical) {
        $_SESSION['flash_error'] = 'The primary Beverley factory can’t be switched off here.';
    } else {
        try {
            $st = $pdo->prepare('UPDATE clients SET is_factory = ? WHERE id = ?');
            $st->execute([$want, $clientId]);
            $name = (function () use ($pdo, $clientId): string {
                $s = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
                $s->execute([$clientId]);
                return (string) ($s->fetchColumn() ?: ('#' . $clientId));
            })();
            $_SESSION['flash_success'] = $want === 1
                ? e($name) . ' is now a factory. Next: grant a user the “factory” role in that account’s Users screen.'
                : e($name) . ' is no longer a factory.';
        } catch (Throwable $e) {
            error_log('[YourBlinds] factories toggle failed: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Could not update. See the error log.';
        }
    }
    header('Location: /master-admin/factories.php');
    exit;
}

$clients = [];
if (!$colMissing) {
    try {
        $clients = $pdo->query(
            "SELECT c.id, c.company_name, c.is_factory,
                    (SELECT COUNT(*) FROM client_users u
                      WHERE u.client_id = c.id AND u.active = 1) AS user_count
               FROM clients c
           ORDER BY c.is_factory DESC, c.company_name"
        )->fetchAll();
    } catch (Throwable $e) {
        $clients = [];
    }
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeNav = 'factories';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factories &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Factories</h1>
                <p class="page-subtitle">
                    A factory runs its own manufacturing back-office — its own order queue,
                    worksheets, build rules and scanner. Turn it on for a trade firm here,
                    then grant a user in that account the <strong>factory</strong> role so
                    they can get in.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/master-admin/index.php" class="btn btn-secondary">&larr; Master Admin</a>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= $flashMsg /* pre-escaped */ ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <?php if ($colMissing): ?>
                <p style="color:var(--text-secondary)">
                    The factory flag isn’t set up yet — run
                    <strong>/migrate_client_is_factory.php</strong> first.
                </p>
            <?php elseif (!$clients): ?>
                <p style="color:var(--text-secondary)">No accounts found.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th style="text-align:center">Users</th>
                                <th style="text-align:center">Factory</th>
                                <th style="text-align:right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $c):
                                $cid   = (int) $c['id'];
                                $isFac = (int) $c['is_factory'] === 1;
                                $isCanon = $cid === $canonical;
                            ?>
                                <tr>
                                    <td>
                                        <?= e((string) $c['company_name']) ?>
                                        <?php if ($isCanon): ?>
                                            <span class="pill" style="background:#e0e7ff;color:#3730a3;font-size:0.7rem;padding:0.1rem 0.5rem;border-radius:999px;margin-left:0.4rem">primary</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;font-variant-numeric:tabular-nums"><?= (int) $c['user_count'] ?></td>
                                    <td style="text-align:center">
                                        <?php if ($isFac): ?>
                                            <span style="color:#166534;font-weight:600">✓ Factory</span>
                                        <?php else: ?>
                                            <span style="color:var(--text-faint)">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right">
                                        <?php if ($isCanon): ?>
                                            <span style="color:var(--text-faint);font-size:0.8125rem">always on</span>
                                        <?php else: ?>
                                            <form method="post" action="/master-admin/factories.php"
                                                  style="margin:0;display:inline"
                                                  data-confirm="<?= $isFac
                                                      ? 'Turn off the factory for ' . e((string) $c['company_name']) . '?'
                                                      : 'Make ' . e((string) $c['company_name']) . ' a factory?' ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="client_id" value="<?= $cid ?>">
                                                <input type="hidden" name="to" value="<?= $isFac ? 0 : 1 ?>">
                                                <button type="submit" class="btn btn-sm <?= $isFac ? 'btn-secondary' : 'btn-primary' ?>">
                                                    <?= $isFac ? 'Turn off' : 'Make a factory' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.875rem 0 0;max-width:48rem">
                    After making an account a factory, open that account’s <strong>Users</strong> screen and
                    give a user the <strong>factory</strong> role — that’s who signs into the factory app. As
                    super-admin you can also switch between factories from inside the factory back-office.
                </p>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
