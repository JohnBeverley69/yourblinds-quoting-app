<?php
declare(strict_types=1);

/**
 * Supplier catalogues — client page (sidebar: "Supplier catalogues").
 *
 * Shows the supplier ranges we hold on the master catalogue and which of them
 * are already in this account, and lets the company ask for a supplier: who
 * the supplier is, their contact, and any price lists / option sheets / EDI
 * files they have. Requests land in master-admin/supplier-requests.php and are
 * emailed to the team. Demand-driven: we build the suppliers people ask for
 * rather than every supplier we can think of.
 *
 * Ranges are NOT switched on from here — the self-serve "Add to my account"
 * of the old Price-List Library (retired 2026-07-10 with the Platinum tier)
 * stays off. A range reaches an account through Push updates; from here the
 * company asks for it.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/library.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];
require_once __DIR__ . '/../_partials/plan_gate.php';
plan_require_feature((int) $clientId, 'supplier_ranges');   // plan features (Master Admin → Plan features)
$master   = library_master_client_id();
$isMaster = ($master > 0 && $master === $clientId);
$pdo      = db();

$suppliers = library_suppliers();

// Which ranges are already in this account: products copied from the master
// (source_client_id) whose name carries the supplier's prefix.
$inAccount = [];
if ($master > 0 && !$isMaster) {
    try {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM products
              WHERE client_id = ? AND source_client_id = ? AND active = 1
                AND name LIKE ? ESCAPE '\\\\'"
        );
        foreach ($suppliers as $key => $sup) {
            $prefix = trim((string) $sup['prefix']);
            if ($prefix === '') continue;
            $st->execute([$clientId, $master, addcslashes($prefix, '%_\\') . ' %']);
            $inAccount[$key] = (int) $st->fetchColumn();
        }
    } catch (Throwable $e) { /* pre-migration — show none as in account */ }
}

// This company's own requests, newest first.
$myRequests = [];
$filesByReq = [];
try {
    $st = $pdo->prepare(
        'SELECT id, supplier_name, file_name, status, created_at
           FROM supplier_requests WHERE client_id = ?
       ORDER BY created_at DESC, id DESC LIMIT 50'
    );
    $st->execute([$clientId]);
    $myRequests = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($myRequests) {
        $ids = array_map(static fn ($r) => (int) $r['id'], $myRequests);
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $fs  = $pdo->prepare("SELECT request_id, COUNT(*) AS n FROM supplier_request_files WHERE request_id IN ($in) GROUP BY request_id");
        $fs->execute($ids);
        foreach ($fs->fetchAll(PDO::FETCH_ASSOC) as $f) $filesByReq[(int) $f['request_id']] = (int) $f['n'];
    }
} catch (Throwable $e) { /* table or files table not there yet */ }

// Prefill: "Ask for this range" passes ?supplier=Name#request.
$prefill = mb_substr(trim((string) ($_GET['supplier'] ?? '')), 0, 160);

// The signed-in person's own name + email, as the default "your details".
$myName  = (string) ($user['full_name'] ?? '');
$myEmail = '';
try {
    $es = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $es->execute([(int) ($user['user_id'] ?? 0)]);
    $myEmail = (string) ($es->fetchColumn() ?: '');
} catch (Throwable $e) { /* leave blank */ }

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeNav = 'supplier-catalogues';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supplier catalogues &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Supplier catalogues</h1>
                <p class="page-subtitle">
                    Supplier price lists, ready priced, with the supplier's own options.
                    Don't see one you buy from? <a href="#request">Request a supplier</a>.
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <?php if ($isMaster): ?>
            <section class="section">
                <p style="color:var(--text-secondary);margin:0">
                    This account holds the <strong>master catalogue</strong>. Supplier ranges are managed in
                    <a href="/master-admin/master-catalogue.php">Master Catalogue</a>, and what companies ask for is in
                    <a href="/master-admin/supplier-requests.php">Supplier requests</a>.
                </p>
            </section>
        <?php endif; ?>

        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.75rem">Supplier ranges</h2>
            <?php if (!$suppliers): ?>
                <p style="color:var(--text-faint);margin:0">No supplier ranges yet &mdash; request the ones you use below.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Supplier</th><th>In your account</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($suppliers as $key => $sup): $n = $inAccount[$key] ?? 0; ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) $sup['name']) ?></strong>
                                    <?php if ((string) $sup['blurb'] !== ''): ?>
                                        <div style="font-size:.8125rem;color:var(--text-muted)"><?= e((string) $sup['blurb']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isMaster): ?>
                                        <span style="color:var(--text-faint)">&mdash;</span>
                                    <?php elseif ($n > 0): ?>
                                        &check; <?= $n ?> product<?= $n === 1 ? '' : 's' ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-faint)">Not yet</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;white-space:nowrap">
                                    <?php if (!$isMaster && $n === 0): ?>
                                        <a href="/library/index.php?supplier=<?= e(rawurlencode((string) $sup['name'])) ?>#request">Ask for this range</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="section" id="request" style="background:var(--bg-subtle)">
            <h2 class="section-title" style="margin:0 0 0.25rem">Request a supplier</h2>
            <p style="color:var(--text-secondary);font-size:0.9375rem;margin:0 0 0.875rem;max-width:46rem">
                Tell us who the supplier is and we'll look at adding their range. If you have their
                price list, option sheets or EDI files, attach them &mdash; it gets them on much faster.
            </p>
            <form method="post" action="/library/request-supplier.php" enctype="multipart/form-data" class="form" style="max-width:46rem">
                <?= csrf_field() ?>
                <h3 style="font-size:.9375rem;margin:.25rem 0 .5rem">The supplier</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Supplier company <span class="required">*</span></label>
                        <input type="text" name="supplier_name" class="form-control" maxlength="160" required
                               value="<?= e($prefill) ?>" placeholder="e.g. Louvolite">
                    </div>
                    <div class="form-group">
                        <label>Website</label>
                        <input type="text" name="website" class="form-control" maxlength="255" placeholder="e.g. louvolite.com">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Contact name</label>
                        <input type="text" name="supplier_contact" class="form-control" maxlength="160" placeholder="Your rep or account manager">
                    </div>
                    <div class="form-group">
                        <label>Contact email</label>
                        <input type="email" name="supplier_email" class="form-control" maxlength="255">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Contact phone</label>
                        <input type="text" name="supplier_phone" class="form-control" maxlength="60">
                    </div>
                    <div class="form-group">
                        <label>Your account number with them</label>
                        <input type="text" name="account_number" class="form-control" maxlength="80">
                    </div>
                </div>

                <h3 style="font-size:.9375rem;margin:.75rem 0 .5rem">Your details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Your name <span class="required">*</span></label>
                        <input type="text" name="requester_name" class="form-control" maxlength="160" required value="<?= e($myName) ?>">
                    </div>
                    <div class="form-group">
                        <label>Your email <span class="required">*</span></label>
                        <input type="email" name="requester_email" class="form-control" maxlength="255" required value="<?= e($myEmail) ?>">
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label>Company</label>
                        <input type="text" class="form-control" value="<?= e((string) ($user['company_name'] ?? '')) ?>" disabled>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3" maxlength="2000"
                                  placeholder="Which products or ranges you buy from them, anything useful"></textarea>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label>Files <span style="color:var(--text-faint);font-weight:400">(optional &mdash; price lists, option sheets, EDI files: Excel, CSV, PDF or ZIP, up to 10 files, 20 MB each)</span></label>
                        <input type="file" name="files[]" multiple class="form-control"
                               accept=".xlsx,.xlsm,.xls,.csv,.ods,.pdf,.txt,.zip" style="max-width:28rem">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Send request</button>
                </div>
            </form>
        </section>

        <?php if ($myRequests): ?>
            <section class="section">
                <h2 class="section-title" style="margin:0 0 0.75rem">Your requests</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Supplier</th><th>Files</th><th>Sent</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($myRequests as $r):
                            $nf = ($filesByReq[(int) $r['id']] ?? 0) + (!empty($r['file_name']) ? 1 : 0); ?>
                            <tr>
                                <td><?= e((string) $r['supplier_name']) ?></td>
                                <td><?= $nf > 0 ? $nf : '&mdash;' ?></td>
                                <td style="white-space:nowrap"><?= e(date('j M Y', strtotime((string) $r['created_at']))) ?></td>
                                <td><?= (string) $r['status'] === 'open' ? 'With us' : 'Done' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
