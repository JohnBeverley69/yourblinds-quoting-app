<?php
declare(strict_types=1);

/**
 * Diagnostic: quote visibility across users / companies.
 *
 * Answers "why can't the company admin see a rep's quote?" by laying out the
 * raw client_id wiring:
 *   - companies (clients) matching the search
 *   - users matching the search, with the company they belong to
 *   - quotes matching the search, with the company they're filed under AND
 *     the company of whoever created them
 *
 * The tell-tale: a quote whose client_id differs from its creator's client_id,
 * or a "rep" whose client_id isn't the company admin's — either means the
 * admin (scoped to their own client_id) will never see that quote.
 *
 * Usage: /master-admin/diag-quote-visibility.php?q=Annette  (or a company name
 *        or a quote number). Read-only. Super-admin gated.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$term = trim((string) ($_GET['q'] ?? ''));
$pdo  = db();
$like = '%' . $term . '%';

$clients = $users = $quotes = [];
if ($term !== '') {
    $st = $pdo->prepare('SELECT id, company_name FROM clients WHERE company_name LIKE ? ORDER BY company_name LIMIT 50');
    $st->execute([$like]);
    $clients = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare(
        "SELECT u.id, u.full_name, u.role, u.active, u.client_id, c.company_name
           FROM client_users u
      LEFT JOIN clients c ON c.id = u.client_id
          WHERE u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?
       ORDER BY u.full_name LIMIT 50"
    );
    $st->execute([$like, $like, $like]);
    $users = $st->fetchAll(PDO::FETCH_ASSOC);

    // Quotes matching by number, customer, OR created by a user matching the term.
    $st = $pdo->prepare(
        "SELECT q.id, q.quote_number, q.status, q.client_id,
                qc.company_name AS quote_company,
                q.created_by_user_id,
                u.full_name AS creator_name, u.client_id AS creator_client_id,
                uc.company_name AS creator_company
           FROM quotes q
      LEFT JOIN clients      qc ON qc.id = q.client_id
      LEFT JOIN client_users u  ON u.id  = q.created_by_user_id
      LEFT JOIN clients      uc ON uc.id = u.client_id
          WHERE q.quote_number LIKE ?
             OR q.end_customer_name LIKE ?
             OR u.full_name LIKE ?
       ORDER BY q.id DESC LIMIT 100"
    );
    $st->execute([$like, $like, $like]);
    $quotes = $st->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: text/html; charset=utf-8');
$h = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Diag: quote visibility</title>
    <style>
        body { font: 14px/1.5 system-ui, sans-serif; max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }
        h1 { margin-top: 0; } h2 { margin-top: 1.75rem; font-size: 1rem; color: #1f3b5b; }
        form { margin-bottom: 1.5rem; padding: 0.75rem; background: #f4f6fa; border-radius: 6px; }
        table { border-collapse: collapse; width: 100%; margin: 0.5rem 0 1.25rem; }
        th, td { border: 1px solid #cbd5e1; padding: 0.375rem 0.625rem; text-align: left; }
        th { background: #f8fafc; }
        code { background: #f4f6fa; padding: 1px 4px; border-radius: 3px; }
        .bad { color: #b91c1c; font-weight: 700; }
        .ok { color: #166534; font-weight: 600; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <h1>Quote visibility diagnostic</h1>
    <form method="get">
        <label>Search (rep name, company, or quote #):
            <input type="text" name="q" value="<?= $h($term) ?>" autofocus></label>
        <button type="submit">Look up</button>
    </form>

    <?php if ($term === ''): ?>
        <p class="muted">Enter a rep's name (e.g. <code>Annette</code>), a company, or a quote number.</p>
    <?php else: ?>
        <h2>Companies matching &ldquo;<?= $h($term) ?>&rdquo;</h2>
        <?php if (!$clients): ?><p class="muted">None.</p><?php else: ?>
            <table><tr><th>client_id</th><th>Company</th></tr>
                <?php foreach ($clients as $c): ?>
                    <tr><td><code><?= (int) $c['id'] ?></code></td><td><?= $h($c['company_name']) ?></td></tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <h2>Users matching &ldquo;<?= $h($term) ?>&rdquo;</h2>
        <?php if (!$users): ?><p class="muted">None.</p><?php else: ?>
            <table><tr><th>user id</th><th>Name</th><th>Role</th><th>Active</th><th>client_id</th><th>Company</th></tr>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><code><?= (int) $u['id'] ?></code></td>
                        <td><?= $h($u['full_name']) ?></td>
                        <td><?= $h($u['role']) ?></td>
                        <td><?= ((int) $u['active'] === 1) ? 'yes' : '<span class="bad">no</span>' ?></td>
                        <td><code><?= (int) $u['client_id'] ?></code></td>
                        <td><?= $h($u['company_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <h2>Quotes matching &ldquo;<?= $h($term) ?>&rdquo;</h2>
        <?php if (!$quotes): ?><p class="muted">None.</p><?php else: ?>
            <table>
                <tr><th>Quote</th><th>Status</th><th>Filed under (client_id / company)</th>
                    <th>Created by</th><th>Creator's company</th><th>Mismatch?</th></tr>
                <?php foreach ($quotes as $qr):
                    $mismatch = $qr['creator_client_id'] !== null
                             && (int) $qr['creator_client_id'] !== (int) $qr['client_id'];
                ?>
                    <tr>
                        <td><?= $h($qr['quote_number'] ?: ('#' . (int) $qr['id'])) ?></td>
                        <td><?= $h($qr['status']) ?></td>
                        <td><code><?= (int) $qr['client_id'] ?></code> / <?= $h($qr['quote_company'] ?? '—') ?></td>
                        <td><?= $h($qr['creator_name'] ?? '—') ?>
                            <?php if ($qr['creator_client_id'] !== null): ?>
                                <span class="muted">(client <?= (int) $qr['creator_client_id'] ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $h($qr['creator_company'] ?? '—') ?></td>
                        <td><?= $mismatch ? '<span class="bad">YES — creator in a different company</span>' : '<span class="ok">no</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
