<?php
declare(strict_types=1);

/**
 * Client (tenant) email list — super-admin. A simple roster of every tenant
 * business with its contact email + login email(s), a copy-all box for pasting
 * into a mailout, and a CSV download. Read-only.
 *
 * "Client" here = a tenant business on the platform (clients table), not an
 * end customer.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$pdo = db();
$rows = [];
try {
    $st = $pdo->query(
        "SELECT c.id, c.company_name, c.email AS business_email, c.active,
                GROUP_CONCAT(DISTINCT NULLIF(TRIM(u.email), '')
                             ORDER BY u.is_super_admin DESC, u.id SEPARATOR ', ') AS user_emails
           FROM clients c
      LEFT JOIN client_users u ON u.client_id = c.id
       GROUP BY c.id, c.company_name, c.email, c.active
       ORDER BY c.company_name"
    );
    $rows = $st->fetchAll();
} catch (Throwable $e) {
    error_log('client-emails: ' . $e->getMessage());
}

// Unique, lower-cased set of every email (business + logins) for the copy box.
$allEmails = [];
foreach ($rows as $r) {
    foreach (array_merge(
        [(string) ($r['business_email'] ?? '')],
        preg_split('/\s*,\s*/', (string) ($r['user_emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
    ) as $em) {
        $em = trim($em);
        if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $allEmails[strtolower($em)] = $em;   // dedupe case-insensitively, keep first casing
        }
    }
}
$allEmails = array_values($allEmails);
sort($allEmails);

// CSV export — emit before any HTML.
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="client-emails.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Company', 'Active', 'Business email', 'Login email(s)']);
    foreach ($rows as $r) {
        fputcsv($out, [
            (string) $r['company_name'],
            ((int) ($r['active'] ?? 1)) === 1 ? 'yes' : 'no',
            (string) ($r['business_email'] ?? ''),
            (string) ($r['user_emails'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

$user      = current_user();
$activeNav = 'client-emails';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client emails &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .ce-box { width:100%; min-height:8rem; font:inherit; padding:0.625rem 0.75rem;
                  border:1px solid var(--border-strong); border-radius:8px;
                  background:var(--bg-input); color:var(--text-body); resize:vertical; }
        .ce-table td, .ce-table th { vertical-align:top; }
        .ce-muted { color:var(--text-faint); }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Client emails</h1>
                <p class="page-subtitle">
                    Every tenant business on the platform &mdash; <?= count($rows) ?> client<?= count($rows) === 1 ? '' : 's' ?>,
                    <?= count($allEmails) ?> unique email<?= count($allEmails) === 1 ? '' : 's' ?>.
                </p>
            </div>
            <div class="actions-bar">
                <a href="/master-admin/client-emails.php?csv=1" class="btn btn-secondary">Download CSV</a>
            </div>
        </div>

        <section class="section">
            <div class="section-header"><h2 class="section-title">Copy all (for a mailout)</h2></div>
            <p class="ce-muted" style="font-size:0.875rem;margin:0 0 0.5rem">
                Unique addresses, comma-separated &mdash; click <strong>Copy</strong> and paste into your email tool's
                Bcc field. (Please send marketing as Bcc.)
            </p>
            <textarea id="ce-all" class="ce-box" readonly><?= e(implode(', ', $allEmails)) ?></textarea>
            <div style="margin-top:0.5rem">
                <button type="button" class="btn btn-primary" id="ce-copy">Copy</button>
                <span id="ce-copied" class="ce-muted" style="margin-left:0.5rem"></span>
            </div>
        </section>

        <section class="section">
            <div class="section-header"><h2 class="section-title">By client</h2></div>
            <div class="table-wrap">
                <table class="table ce-table">
                    <thead>
                        <tr><th>Company</th><th>Active</th><th>Business email</th><th>Login email(s)</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="4" class="ce-muted">No clients.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr>
                                <td><strong><?= e((string) $r['company_name']) ?></strong></td>
                                <td><?= ((int) ($r['active'] ?? 1)) === 1 ? 'Yes' : '<span class="ce-muted">No</span>' ?></td>
                                <td><?= ($r['business_email'] ?? '') !== '' ? e((string) $r['business_email']) : '<span class="ce-muted">—</span>' ?></td>
                                <td><?= ($r['user_emails'] ?? '') !== '' ? e((string) $r['user_emails']) : '<span class="ce-muted">—</span>' ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script>
(function () {
    var btn = document.getElementById('ce-copy');
    var box = document.getElementById('ce-all');
    var msg = document.getElementById('ce-copied');
    if (!btn || !box) return;
    btn.addEventListener('click', function () {
        box.select();
        box.setSelectionRange(0, box.value.length);
        var done = function () { if (msg) { msg.textContent = 'Copied ✓'; setTimeout(function () { msg.textContent = ''; }, 2000); } };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(box.value).then(done, function () { try { document.execCommand('copy'); done(); } catch (e) {} });
        } else {
            try { document.execCommand('copy'); done(); } catch (e) {}
        }
    });
})();
</script>
</body>
</html>
