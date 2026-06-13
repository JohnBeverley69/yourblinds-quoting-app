<?php
declare(strict_types=1);

/**
 * Customer dedupe tool.
 *
 * Finds groups of customers in the current tenant that share a
 * case-insensitive trimmed name, then merges each group: the OLDEST
 * row (lowest id) is kept; quotes / appointments / payments /
 * customer_markups / customer_discounts pointing at the others are
 * re-aimed at the keeper; the duplicate rows are deleted.
 *
 * Why "oldest"? It's the most likely to have been the original real
 * customer record — later duplicates tend to be test entries or
 * accidental re-adds. The admin can review the list of merges
 * before committing.
 *
 * Admin-only. Tenant-scoped via current_user()['client_id'].
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pdo = db();

// ---- Find duplicate groups ------------------------------------------
//
// Group by LOWER(TRIM(name)) and only keep groups with 2+ entries.
// Skip rows where the name is blank — those need manual attention.
$groupsStmt = $pdo->prepare(
    'SELECT LOWER(TRIM(name)) AS key_name, COUNT(*) AS cnt
       FROM customers
      WHERE client_id = ? AND name IS NOT NULL AND TRIM(name) != ""
   GROUP BY key_name
     HAVING cnt > 1
   ORDER BY cnt DESC, key_name'
);
$groupsStmt->execute([$clientId]);
$groupRows = $groupsStmt->fetchAll();

// For each group, pull all the customer rows + their reference counts.
$groups = [];
$totalDupes = 0;
foreach ($groupRows as $g) {
    $keyName = (string) $g['key_name'];
    $rowsSt = $pdo->prepare(
        'SELECT c.id, c.name, c.email, c.phone, c.town, c.postcode,
                c.created_at,
                (SELECT COUNT(*) FROM quotes       q WHERE q.customer_id = c.id) AS quote_count,
                (SELECT COUNT(*) FROM appointments a WHERE a.customer_id = c.id) AS appt_count
           FROM customers c
          WHERE c.client_id = ?
            AND LOWER(TRIM(c.name)) = ?
       ORDER BY c.id'
    );
    $rowsSt->execute([$clientId, $keyName]);
    $rows = $rowsSt->fetchAll();
    if (count($rows) <= 1) continue;   // race / weirdness
    $groups[] = [
        'key'   => $keyName,
        'name'  => (string) $rows[0]['name'],
        'rows'  => $rows,
    ];
    $totalDupes += count($rows) - 1;   // dupes = all but the keeper
}

// ---- POST: merge groups ---------------------------------------------
//
// Two actions:
//   merge_one  — merge a single group (?group=keyname)
//   merge_all  — merge every group in one go (safer to spot-check first
//                via merge_one, but merge_all is convenient when the
//                user has eyeballed the page).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');
    $targetKey = (string) ($_POST['group_key'] ?? '');

    $toMerge = [];
    if ($action === 'merge_all') {
        $toMerge = $groups;
    } elseif ($action === 'merge_one' && $targetKey !== '') {
        foreach ($groups as $g) {
            if ($g['key'] === $targetKey) { $toMerge = [$g]; break; }
        }
    }

    if (!$toMerge) {
        $_SESSION['flash_error'] = 'Nothing to merge.';
        header('Location: /customer-manager/dedupe.php');
        exit;
    }

    $totalMerged = 0;
    $totalDeleted = 0;
    try {
        $pdo->beginTransaction();
        foreach ($toMerge as $g) {
            $rows = $g['rows'];
            // Keeper = lowest id (oldest). Everyone else gets re-pointed
            // + deleted.
            $keeper   = (int) $rows[0]['id'];
            $loserIds = array_slice(array_map(static fn ($r) => (int) $r['id'], $rows), 1);
            if (!$loserIds) continue;

            $ph = implode(',', array_fill(0, count($loserIds), '?'));

            // Re-point every table that holds a customer_id FK.
            // payments, customer_markups, customer_discounts may not
            // exist on every install — try each, swallow "table missing"
            // errors so the merge completes for the tables that ARE there.
            $fkTables = ['quotes', 'appointments', 'payments',
                         'customer_markups', 'customer_discounts'];
            foreach ($fkTables as $tbl) {
                try {
                    $upd = $pdo->prepare(
                        "UPDATE $tbl SET customer_id = ?
                          WHERE customer_id IN ($ph)
                            AND client_id = ?"
                    );
                    $upd->execute(array_merge([$keeper], $loserIds, [$clientId]));
                } catch (Throwable $e) {
                    // Skip silently — table doesn't exist on this install.
                }
            }

            // Now delete the losers. clients table cascade isn't
            // involved (we're not touching the tenant), so this is a
            // direct DELETE — but we filter by client_id defensively.
            $del = $pdo->prepare(
                "DELETE FROM customers
                  WHERE id IN ($ph) AND client_id = ?"
            );
            $del->execute(array_merge($loserIds, [$clientId]));

            $totalMerged++;
            $totalDeleted += count($loserIds);
        }
        $pdo->commit();
        $_SESSION['flash_success'] = $totalMerged === 1
            ? 'Merged 1 group; removed ' . $totalDeleted . ' duplicate'
              . ($totalDeleted === 1 ? '' : 's') . '.'
            : 'Merged ' . $totalMerged . ' groups; removed '
              . $totalDeleted . ' duplicates in total.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = 'Merge failed: ' . $e->getMessage();
    }
    header('Location: /customer-manager/dedupe.php');
    exit;
}

$activeNav = 'customers';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Find duplicates &middot; Customers &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .group-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px;
            padding: 0.875rem 1rem; margin-bottom: 0.875rem;
        }
        .group-card h3 {
            margin: 0 0 0.5rem; font-size: 1rem; color: var(--text-body);
        }
        .group-card table {
            width: 100%; border-collapse: collapse; font-size: 0.875rem;
        }
        .group-card th, .group-card td {
            text-align: left; padding: 0.375rem 0.5rem;
            border-bottom: 1px solid var(--bg-subtle-2);
        }
        .group-card th {
            font-size: 0.6875rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-faint);
        }
        .group-card tr.is-keeper td { background: #ecfdf5; }
        .group-card .keeper-pill {
            display: inline-block; padding: 0.0625rem 0.5rem;
            background: #16a34a; color: #fff; border-radius: 999px;
            font-size: 0.6875rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .group-card .actions { margin-top: 0.625rem; text-align: right; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Find duplicates</h1>
                <p class="page-subtitle">
                    <a href="/customer-manager/index.php">&larr; All customers</a>
                    &middot; merge customers with the same name
                </p>
            </div>
            <?php if ($groups): ?>
                <form method="post" action="/customer-manager/dedupe.php"
                      data-confirm="Merge ALL <?= count($groups) ?> duplicate group<?= count($groups) === 1 ? '' : 's' ?>? <?= (int) $totalDupes ?> duplicate row<?= $totalDupes === 1 ? '' : 's' ?> will be removed. The oldest row in each group is kept and all quote / appointment links are re-pointed to it. This can't be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="merge_all">
                    <button type="submit" class="btn btn-primary">
                        Merge all duplicates
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.875rem 1.125rem;margin-bottom:1rem">
            <p style="margin:0;color:#0c4a6e;font-size:0.9375rem;line-height:1.5">
                <strong>How merging works:</strong> the customer with the lowest id
                (the oldest record) is kept. All quotes, appointments and payments
                linked to the duplicate rows are re-pointed to the keeper, then
                the duplicate rows are deleted. <strong>Cannot be undone</strong> —
                if you're not sure, eyeball each group first.
            </p>
        </section>

        <?php if (!$groups): ?>
            <section class="section">
                <div class="placeholder">
                    <p class="placeholder-title">No duplicates found 🎉</p>
                    <p class="placeholder-body">
                        Every customer in this tenant has a unique name.
                        Nothing to merge.
                    </p>
                </div>
            </section>
        <?php else: ?>
            <p style="color:var(--text-secondary);font-size:0.9375rem;margin:0 0 0.75rem">
                Found <strong><?= count($groups) ?></strong> duplicate name group<?= count($groups) === 1 ? '' : 's' ?>
                with <strong><?= (int) $totalDupes ?></strong> redundant row<?= $totalDupes === 1 ? '' : 's' ?> in total.
            </p>

            <?php foreach ($groups as $g): ?>
                <div class="group-card">
                    <h3><?= e((string) $g['name']) ?>
                        <span style="color:var(--text-faint);font-weight:400;font-size:0.875rem">
                            (<?= count($g['rows']) ?> rows)
                        </span>
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Town</th>
                                <th>Postcode</th>
                                <th>Quotes</th>
                                <th>Appts</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($g['rows'] as $i => $r):
                                $isKeeper = $i === 0;
                            ?>
                                <tr class="<?= $isKeeper ? 'is-keeper' : '' ?>">
                                    <td>
                                        #<?= (int) $r['id'] ?>
                                        <?php if ($isKeeper): ?>
                                            <span class="keeper-pill">Keeper</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string) ($r['email'] ?? '')) ?></td>
                                    <td><?= e((string) ($r['phone'] ?? '')) ?></td>
                                    <td><?= e((string) ($r['town'] ?? '')) ?></td>
                                    <td><?= e((string) ($r['postcode'] ?? '')) ?></td>
                                    <td><?= (int) $r['quote_count'] ?></td>
                                    <td><?= (int) $r['appt_count'] ?></td>
                                    <td style="color:var(--text-faint);font-size:0.8125rem">
                                        <?= e((string) ($r['created_at'] ?? '')) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="actions">
                        <form method="post" action="/customer-manager/dedupe.php"
                              style="display:inline"
                              data-confirm="Merge this group? <?= count($g['rows']) - 1 ?> duplicate row<?= count($g['rows']) - 1 === 1 ? '' : 's' ?> will be removed. Quote / appointment links are re-pointed to the keeper. This can't be undone.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="merge_one">
                            <input type="hidden" name="group_key" value="<?= e((string) $g['key']) ?>">
                            <button type="submit" class="btn btn-secondary"
                                    style="padding:0.375rem 0.75rem;font-size:0.875rem">
                                Merge this group
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
