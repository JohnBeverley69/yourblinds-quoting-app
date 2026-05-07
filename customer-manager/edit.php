<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];
$isAdmin  = $user['role'] === 'admin';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Customer not found.');
}

$stmt = db()->prepare(
    'SELECT * FROM customers WHERE id = ? AND client_id = ? LIMIT 1'
);
$stmt->execute([$id, $clientId]);
$customer = $stmt->fetch();
if (!$customer) {
    http_response_code(404);
    exit('Customer not found.');
}

$error    = null;
$flashMsg = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $fields = ['name','email','phone','address1','address2','town','county','postcode','notes'];
    $input  = [];
    foreach ($fields as $f) {
        $input[$f] = trim((string) ($_POST[$f] ?? ''));
    }

    if ($input['name'] === '') {
        $error = 'Name is required.';
    } elseif ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $upd = db()->prepare(
            'UPDATE customers
                SET name = ?, email = ?, phone = ?, address1 = ?, address2 = ?,
                    town = ?, county = ?, postcode = ?, notes = ?
              WHERE id = ? AND client_id = ?'
        );
        $upd->execute([
            $input['name'],
            $input['email']    !== '' ? $input['email']    : null,
            $input['phone']    !== '' ? $input['phone']    : null,
            $input['address1'] !== '' ? $input['address1'] : null,
            $input['address2'] !== '' ? $input['address2'] : null,
            $input['town']     !== '' ? $input['town']     : null,
            $input['county']   !== '' ? $input['county']   : null,
            $input['postcode'] !== '' ? $input['postcode'] : null,
            $input['notes']    !== '' ? $input['notes']    : null,
            $id, $clientId,
        ]);
        $_SESSION['flash_success'] = 'Customer updated.';
        header('Location: /customer-manager/edit.php?id=' . $id);
        exit;
    }

    // Re-render the form with the user's input (so they don't lose edits on validation error).
    foreach ($fields as $f) {
        $customer[$f] = $input[$f];
    }
}

// Recent quotes for this customer (read-only, last 5)
$qstmt = db()->prepare(
    'SELECT id, quote_number, status, total, created_at
       FROM quotes
      WHERE customer_id = ? AND client_id = ?
      ORDER BY created_at DESC
      LIMIT 5'
);
$qstmt->execute([$id, $clientId]);
$recentQuotes = $qstmt->fetchAll();

$money = static fn ($n) => '£' . number_format((float) $n, 2);

$dashHref = $isAdmin ? '/admin/index.php' : '/quote-builder/index.php';
$dashTag  = $isAdmin ? 'Admin Console'    : 'Trade Portal';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit customer &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<div class="app-shell">
    <input type="checkbox" id="navToggle" class="nav-toggle-input">
    <label class="nav-fab" for="navToggle" aria-label="Open menu" tabindex="0">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    </label>
    <label class="nav-close" for="navToggle" aria-label="Close menu" tabindex="0">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </label>
    <label class="nav-backdrop" for="navToggle" aria-hidden="true"></label>
    <aside class="app-sidebar" aria-label="Primary navigation">
        <div class="app-sidebar-brand">
            <a href="<?= e($dashHref) ?>" class="app-brand-mark">Your<span class="accent">Blinds</span></a>
            <span class="app-brand-tag"><?= e($dashTag) ?></span>
        </div>
        <div class="app-sidebar-user">
            <div class="app-sidebar-user-name"><?= e($user['full_name']) ?></div>
            <div class="app-sidebar-user-meta">
                <?= e($user['company_name']) ?> &middot; <?= e($user['role']) ?>
            </div>
        </div>
        <nav class="app-sidebar-nav">
            <a href="<?= e($dashHref) ?>">Dashboard</a>
            <a href="/quote-builder/new.php">New Quote</a>
            <a href="/quote-history/index.php">Quote History</a>
            <a href="/customer-manager/index.php" class="active">Customers</a>
            <?php if ($isAdmin): ?>
                <a href="/admin/pricing.php">Price Lists</a>
                <a href="/admin/settings.php">Settings</a>
            <?php endif; ?>
        </nav>
        <div class="app-sidebar-foot">
            <a href="/auth/logout.php">Sign out &rarr;</a>
        </div>
    </aside>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title"><?= e((string) $customer['name']) ?></h1>
                <p class="page-subtitle">
                    <a href="/customer-manager/index.php">&larr; Back to customers</a>
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/customer-manager/edit.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $customer['id'] ?>">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="name">Name <span class="required">*</span></label>
                        <input id="name" name="name" type="text" required maxlength="150"
                               value="<?= e((string) $customer['name']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" maxlength="150" autocomplete="email"
                               value="<?= e((string) ($customer['email'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input id="phone" name="phone" type="tel" maxlength="50" autocomplete="tel"
                               value="<?= e((string) ($customer['phone'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="address1">Address line 1</label>
                        <input id="address1" name="address1" type="text" maxlength="150"
                               value="<?= e((string) ($customer['address1'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="address2">Address line 2</label>
                        <input id="address2" name="address2" type="text" maxlength="150"
                               value="<?= e((string) ($customer['address2'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="town">Town</label>
                        <input id="town" name="town" type="text" maxlength="100"
                               value="<?= e((string) ($customer['town'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="county">County</label>
                        <input id="county" name="county" type="text" maxlength="100"
                               value="<?= e((string) ($customer['county'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="postcode">Postcode</label>
                        <input id="postcode" name="postcode" type="text" maxlength="20" autocomplete="postal-code"
                               value="<?= e((string) ($customer['postcode'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="4"><?= e((string) ($customer['notes'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="/customer-manager/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>

        <?php if (!empty($recentQuotes)): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">Recent quotes</h2>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Quote #</th>
                                <th>Status</th>
                                <th class="num">Total</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentQuotes as $q): ?>
                                <tr>
                                    <td><strong><?= e((string) $q['quote_number']) ?></strong></td>
                                    <td><span class="badge badge-<?= e((string) $q['status']) ?>"><?= e((string) $q['status']) ?></span></td>
                                    <td class="num"><?= e($money($q['total'])) ?></td>
                                    <td><?= e(date('j M Y', strtotime((string) $q['created_at']))) ?></td>
                                    <td><a href="/quote-builder/edit.php?id=<?= (int) $q['id'] ?>">Open</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title" style="color:#b91c1c;">Danger zone</h2>
            </div>
            <p style="color:#6b7280; margin: 0 0 1rem;">
                Deleting this customer is permanent. Existing quotes will be kept but will no longer be linked to a customer record.
            </p>
            <form method="post" action="/customer-manager/delete.php" style="margin:0;"
                  onsubmit="return confirm('Delete <?= e(addslashes((string) $customer['name'])) ?>? This cannot be undone.');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $customer['id'] ?>">
                <button type="submit" class="btn btn-danger">Delete customer</button>
            </form>
        </section>
    </main>
</div>
</body>
</html>
