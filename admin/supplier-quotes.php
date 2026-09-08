<?php
declare(strict_types=1);

/**
 * "Quotes from your supplier" — the trade-account side of the factory's "New
 * order" flow. A quote raised BY a supplier (e.g. Beverley) FOR this account has
 * quotes.account_client_id = this client. It's owned by the supplier, but the
 * account sees it here in their own portal and can Accept / Decline, which syncs
 * straight back to the supplier's copy (same quote row).
 *
 * Scope is strict: only quotes whose account_client_id is THIS client are ever
 * loaded or actioned, so an account can only see and answer quotes sent to them.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$isAdmin  = ($user['role'] ?? '') === 'admin';
$_perms   = current_user_permissions();

// Answering a supplier's quote is an order-side action.
$canRespond = $isAdmin || !empty($_perms['can_create_orders']) || !empty($_perms['can_create_quotes']);

// ── Accept / decline (scoped to quotes sent to THIS account) ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $qid    = (int) ($_POST['quote_id'] ?? 0);

    if (!$canRespond) {
        $_SESSION['flash_error'] = "You don't have permission to accept or decline quotes.";
    } elseif (!in_array($action, ['accept', 'decline'], true) || $qid <= 0) {
        $_SESSION['flash_error'] = 'Nothing to do.';
    } else {
        try {
            // Load ONLY if this quote was sent to us and is still awaiting an answer.
            $q = db()->prepare(
                "SELECT id, status, quote_number FROM quotes
                  WHERE id = ? AND account_client_id = ? LIMIT 1"
            );
            $q->execute([$qid, $clientId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $_SESSION['flash_error'] = 'That quote is not available.';
            } elseif ((string) $row['status'] !== 'sent') {
                $_SESSION['flash_error'] = 'Quote ' . $row['quote_number'] . ' can no longer be answered (it is ' . $row['status'] . ').';
            } elseif ($action === 'accept') {
                db()->prepare("UPDATE quotes SET status = 'accepted', accepted_at = NOW() WHERE id = ? AND account_client_id = ?")
                    ->execute([$qid, $clientId]);
                $_SESSION['flash_success'] = 'Accepted ' . $row['quote_number'] . ' — it now shows as accepted on your supplier\'s side.';
            } else {
                db()->prepare("UPDATE quotes SET status = 'declined' WHERE id = ? AND account_client_id = ?")
                    ->execute([$qid, $clientId]);
                $_SESSION['flash_success'] = 'Declined ' . $row['quote_number'] . '.';
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not update the quote: ' . $e->getMessage();
        }
    }
    header('Location: /admin/supplier-quotes.php');
    exit;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Load this account's supplier quotes ─────────────────────────────────────
$quotes = [];
try {
    $st = db()->prepare(
        "SELECT q.id, q.quote_number, q.status, q.total, q.created_at, q.accepted_at,
                q.public_token, q.end_customer_name,
                c.company_name AS supplier_name,
                COALESCE((SELECT SUM(qi.quantity) FROM quote_items qi WHERE qi.quote_id = q.id), 0) AS blinds
           FROM quotes q
           JOIN clients c ON c.id = q.client_id
          WHERE q.account_client_id = ? AND q.status <> 'draft'
       ORDER BY q.created_at DESC, q.id DESC
          LIMIT 300"
    );
    $st->execute([$clientId]);
    $quotes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* account_client_id column absent (pre-migration) → empty */ }

$money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
$fmtD  = static function (?string $dt): string {
    if (!$dt) return '&mdash;';
    $ts = strtotime($dt);
    return $ts ? date('j M Y', $ts) : '&mdash;';
};
$pill = static function (string $s): array {
    return $s === 'accepted' ? ['Accepted', '#065f46', '#d1fae5']
        : ($s === 'declined' ? ['Declined', '#991b1b', '#fee2e2']
        : ($s === 'sent'     ? ['Awaiting your answer', '#1e40af', '#dbeafe']
        : [ucfirst($s), '#374151', '#e5e7eb']));
};

$isSuperAdmin = (bool) ($user['is_super_admin'] ?? false);
$activeNav = 'supplier-quotes';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supplier quotes &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .sq-pill { display:inline-block; padding:0.05rem 0.6rem; font-size:0.72rem; font-weight:700; border-radius:999px; white-space:nowrap; }
        .sq-money { font-variant-numeric:tabular-nums; text-align:right; white-space:nowrap; }
        .sq-num { font-weight:700; white-space:nowrap; }
        .sq-act { display:inline-flex; gap:0.4rem; align-items:center; }
        .sq-link { color:var(--link); font-size:0.85rem; text-decoration:underline; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Quotes from your supplier</h1>
                <p class="page-subtitle">Quotes your supplier has sent you &mdash; view them, and accept or decline right here.</p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <section class="section">
            <?php if (!$quotes): ?>
                <p style="color:var(--text-faint);margin:0">Nothing here yet. When your supplier sends you a quote, it appears here for you to accept or decline.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Quote</th><th>Supplier</th><th>Received</th>
                                <th class="sq-money">Blinds</th><th class="sq-money">Total</th>
                                <th>Status</th><th style="text-align:right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quotes as $q): [$pl, $fg, $bg] = $pill((string) $q['status']); $tok = (string) ($q['public_token'] ?? ''); ?>
                                <tr>
                                    <td class="sq-num"><?= e((string) ($q['quote_number'] ?: ('#' . (int) $q['id']))) ?></td>
                                    <td><?= e((string) $q['supplier_name']) ?></td>
                                    <td style="white-space:nowrap"><?= $fmtD($q['created_at']) ?></td>
                                    <td class="sq-money"><?= (int) $q['blinds'] ?></td>
                                    <td class="sq-money"><?= $money($q['total']) ?></td>
                                    <td><span class="sq-pill" style="background:<?= $bg ?>;color:<?= $fg ?>"><?= e($pl) ?></span></td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <span class="sq-act">
                                            <?php if ($tok !== ''): ?>
                                                <a class="sq-link" href="/quote-history/public.php?token=<?= e($tok) ?>" target="_blank" rel="noopener">View</a>
                                            <?php endif; ?>
                                            <?php if ((string) $q['status'] === 'sent' && $canRespond): ?>
                                                <form method="post" action="/admin/supplier-quotes.php" style="display:inline;margin:0"
                                                      data-confirm="Accept quote <?= e((string) $q['quote_number']) ?> (<?= e(number_format((float) $q['total'], 2)) ?>)?">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="accept">
                                                    <input type="hidden" name="quote_id" value="<?= (int) $q['id'] ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">Accept</button>
                                                </form>
                                                <form method="post" action="/admin/supplier-quotes.php" style="display:inline;margin:0"
                                                      data-confirm="Decline quote <?= e((string) $q['quote_number']) ?>?">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="decline">
                                                    <input type="hidden" name="quote_id" value="<?= (int) $q['id'] ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm">Decline</button>
                                                </form>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
