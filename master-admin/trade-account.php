<?php
declare(strict_types=1);

/**
 * Master Admin: Trade Account — single-account detail + edit.
 *
 * The per-client control page that didn't exist before: a super-admin can view
 * and edit an account's business/contact profile, toggle it active/inactive, and
 * see its logins. Login control (reset password / change login) is added in 1B;
 * per-account discounts in 1C — this page is the host for both.
 *
 * Super-admin only. All mutation is POST + CSRF + flash-then-redirect.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$user     = current_user();
$pdo      = db();
$myClient = (int) $user['client_id'];

$clientId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($clientId <= 0) {
    header('Location: /master-admin/trade-accounts.php');
    exit;
}

$colExists = static function (string $table, string $col) use ($pdo): bool {
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $col]);
        return $st->fetchColumn() !== false;
    } catch (Throwable $e) { return false; }
};
$hasCreated = $colExists('clients', 'created_at');

// ── POST handlers ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    // Confirm the target exists before mutating.
    $chk = $pdo->prepare('SELECT id FROM clients WHERE id = ? LIMIT 1');
    $chk->execute([$clientId]);
    if ($chk->fetchColumn() === false) {
        $_SESSION['flash_error'] = 'Account not found.';
        header('Location: /master-admin/trade-accounts.php');
        exit;
    }

    if ($action === 'profile') {
        // Same field set as the tenant-side business profile (admin/settings.php),
        // but a super-admin can edit any account's here.
        $company = trim((string) ($_POST['company_name'] ?? ''));
        if ($company === '') {
            $_SESSION['flash_error'] = 'Company name is required.';
        } else {
            try {
                $pdo->prepare(
                    'UPDATE clients
                        SET company_name = ?, contact_name = ?, email = ?, phone = ?,
                            vat_number = ?, address1 = ?, address2 = ?, town = ?,
                            county = ?, postcode = ?
                      WHERE id = ?'
                )->execute([
                    mb_substr($company, 0, 150),
                    trim((string) ($_POST['contact_name'] ?? '')) ?: null,
                    trim((string) ($_POST['email']        ?? '')) ?: null,
                    trim((string) ($_POST['phone']        ?? '')) ?: null,
                    trim((string) ($_POST['vat_number']   ?? '')) ?: null,
                    trim((string) ($_POST['address1']     ?? '')) ?: null,
                    trim((string) ($_POST['address2']     ?? '')) ?: null,
                    trim((string) ($_POST['town']         ?? '')) ?: null,
                    trim((string) ($_POST['county']       ?? '')) ?: null,
                    trim((string) ($_POST['postcode']     ?? '')) ?: null,
                    $clientId,
                ]);
                // If we just renamed our OWN client, refresh the session copy.
                if ($clientId === $myClient) $_SESSION['company_name'] = $company;
                $_SESSION['flash_success'] = 'Account details saved.';
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
            }
        }
        header('Location: /master-admin/trade-account.php?id=' . $clientId);
        exit;
    }

    if ($action === 'active') {
        $to = (int) ($_POST['to'] ?? 0) === 1 ? 1 : 0;
        // Never let a super-admin deactivate their OWN account (self-lockout).
        if ($clientId === $myClient && $to === 0) {
            $_SESSION['flash_error'] = 'You can\'t deactivate your own account.';
        } else {
            try {
                $pdo->prepare('UPDATE clients SET active = ? WHERE id = ?')->execute([$to, $clientId]);
                $_SESSION['flash_success'] = $to === 1 ? 'Account activated.' : 'Account deactivated.';
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = 'Could not update: ' . $e->getMessage();
            }
        }
        header('Location: /master-admin/trade-account.php?id=' . $clientId);
        exit;
    }

    header('Location: /master-admin/trade-account.php?id=' . $clientId);
    exit;
}

// ── Load the account ────────────────────────────────────────────────────────
$sel = 'SELECT id, company_name, contact_name, email, phone, vat_number,
               address1, address2, town, county, postcode, active, logo_path'
     . ($hasCreated ? ', created_at' : '') . '
          FROM clients WHERE id = ? LIMIT 1';
$st = $pdo->prepare($sel);
$st->execute([$clientId]);
$acc = $st->fetch(PDO::FETCH_ASSOC);

if (!$acc) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Account not found</h1><p><a href="/master-admin/trade-accounts.php">Back to Trade Accounts</a></p>';
    exit;
}

// Logins (read-only here; management arrives in 1B).
$logins = [];
try {
    $lu = $pdo->prepare(
        'SELECT id, full_name, email, username, role, active, last_login_at, email_verified_at, is_super_admin
           FROM client_users WHERE client_id = ? ORDER BY is_super_admin DESC, full_name, email'
    );
    $lu->execute([$clientId]);
    $logins = $lu->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* leave empty */ }

// Quick counts.
$countOf = static function (string $sql, int $id) use ($pdo): int {
    try { $s = $pdo->prepare($sql); $s->execute([$id]); return (int) $s->fetchColumn(); }
    catch (Throwable $e) { return 0; }
};
$productCount  = $countOf('SELECT COUNT(*) FROM products WHERE client_id = ?', $clientId);
$discountCount = $countOf('SELECT COUNT(*) FROM client_discounts WHERE client_id = ?', $clientId);

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$isActive  = (int) $acc['active'] === 1;
$hasPortal = count($logins) > 0;

$activeNav = 'trade-accounts';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $acc['company_name']) ?> &middot; Trade Account</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .ta-badge { display:inline-block; margin-left:0.4rem; padding:0.0625rem 0.5rem; font-size:0.6875rem; font-weight:700; border-radius:999px; text-transform:uppercase; letter-spacing:0.04em; }
        .ta-badge.inactive { background:var(--bg-subtle-2); color:var(--text-faint); }
        .ta-badge.noportal { background:#fef3c7; color:#92400e; }
        .ta-badge.portal { background:#d1fae5; color:#065f46; }
        .ta-badge.you { background:#1f3b5b; color:#fff; }
        .ta-form-grid { display:grid; gap:0.75rem 1rem; grid-template-columns:1fr 1fr; }
        @media (max-width:720px){ .ta-form-grid { grid-template-columns:1fr; } }
        .ta-form-grid .full { grid-column:1 / -1; }
        .ta-form-grid label { display:block; font-size:0.75rem; font-weight:600; color:var(--text-faint); text-transform:uppercase; letter-spacing:0.03em; margin-bottom:0.25rem; }
        .ta-form-grid input { width:100%; box-sizing:border-box; padding:0.5rem 0.7rem; border:1px solid var(--border-strong); border-radius:8px; font:inherit; background:var(--bg-input); }
        .ta-counts { display:flex; gap:1.25rem; flex-wrap:wrap; color:var(--text-muted); font-size:0.9375rem; margin:0 0 0.25rem; }
        .ta-counts b { color:var(--text-primary); }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <?= e((string) $acc['company_name']) ?>
                    <?php if ($clientId === $myClient): ?><span class="ta-badge you">You</span><?php endif; ?>
                    <?php if (!$isActive): ?><span class="ta-badge inactive">Inactive</span><?php endif; ?>
                    <?php if ($hasPortal): ?><span class="ta-badge portal">Portal</span><?php else: ?><span class="ta-badge noportal">No portal</span><?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    <a href="/master-admin/trade-accounts.php">&larr; Trade Accounts</a>
                    <?php if ($hasCreated && !empty($acc['created_at'])): ?>
                        &middot; account since <?= e(date('j M Y', strtotime((string) $acc['created_at']))) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>" style="margin:0"
                      <?php if ($isActive): ?>data-confirm="Deactivate <?= e((string) $acc['company_name']) ?>? Their logins won't be able to sign in until you re-activate."<?php endif; ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="active">
                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                    <input type="hidden" name="to" value="<?= $isActive ? 0 : 1 ?>">
                    <?php if ($clientId === $myClient && $isActive): ?>
                        <button type="button" class="btn btn-secondary" disabled title="You can't deactivate your own account">Deactivate</button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-<?= $isActive ? 'secondary' : 'primary' ?>"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <div class="ta-counts">
            <span><b><?= count($logins) ?></b> login<?= count($logins) === 1 ? '' : 's' ?></span>
            <span><b><?= number_format($productCount) ?></b> products</span>
            <span><b><?= number_format($discountCount) ?></b> discount<?= $discountCount === 1 ? '' : 's' ?> set</span>
        </div>

        <!-- Business / contact profile -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.75rem">Account details</h2>
            <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="profile">
                <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                <div class="ta-form-grid">
                    <div class="full">
                        <label for="company_name">Company name *</label>
                        <input id="company_name" name="company_name" type="text" required maxlength="150" value="<?= e((string) $acc['company_name']) ?>">
                    </div>
                    <div>
                        <label for="contact_name">Contact name</label>
                        <input id="contact_name" name="contact_name" type="text" maxlength="150" value="<?= e((string) ($acc['contact_name'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" maxlength="190" value="<?= e((string) ($acc['email'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="phone">Phone</label>
                        <input id="phone" name="phone" type="text" maxlength="60" value="<?= e((string) ($acc['phone'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="vat_number">VAT number</label>
                        <input id="vat_number" name="vat_number" type="text" maxlength="40" value="<?= e((string) ($acc['vat_number'] ?? '')) ?>">
                    </div>
                    <div class="full">
                        <label for="address1">Address line 1</label>
                        <input id="address1" name="address1" type="text" maxlength="150" value="<?= e((string) ($acc['address1'] ?? '')) ?>">
                    </div>
                    <div class="full">
                        <label for="address2">Address line 2</label>
                        <input id="address2" name="address2" type="text" maxlength="150" value="<?= e((string) ($acc['address2'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="town">Town / city</label>
                        <input id="town" name="town" type="text" maxlength="100" value="<?= e((string) ($acc['town'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="county">County</label>
                        <input id="county" name="county" type="text" maxlength="100" value="<?= e((string) ($acc['county'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="postcode">Postcode</label>
                        <input id="postcode" name="postcode" type="text" maxlength="20" value="<?= e((string) ($acc['postcode'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-actions" style="margin-top:1rem">
                    <button type="submit" class="btn btn-primary">Save details</button>
                </div>
            </form>
        </section>

        <!-- Logins (read-only; management arrives next) -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.5rem">Logins</h2>
            <?php if (!$logins): ?>
                <p style="color:var(--text-faint);margin:0">
                    No portal login &mdash; this is a <strong>no-portal</strong> account (a one-off you invoice/ship to, with no one signing in).
                </p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Name</th><th>Login</th><th>Role</th><th>Status</th><th>Last login</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logins as $u): ?>
                                <tr>
                                    <td>
                                        <strong><?= e((string) ($u['full_name'] ?? '')) ?></strong>
                                        <?php if ((int) $u['is_super_admin'] === 1): ?><span class="ta-badge you">Super</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?= e((string) ($u['email'] ?: $u['username'] ?? '')) ?>
                                        <?php if (($u['email'] ?? '') !== '' && empty($u['email_verified_at'])): ?>
                                            <span class="ta-badge noportal">Unverified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-transform:capitalize"><?= e((string) ($u['role'] ?? '')) ?></td>
                                    <td><?= (int) $u['active'] === 1 ? 'Active' : '<span style="color:var(--text-faint)">Inactive</span>' ?></td>
                                    <td><?= !empty($u['last_login_at']) ? e(date('j M Y H:i', strtotime((string) $u['last_login_at']))) : '<span style="color:var(--text-faint)">never</span>' ?></td>
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
