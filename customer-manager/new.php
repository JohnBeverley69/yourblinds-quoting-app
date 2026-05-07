<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];
$isAdmin  = $user['role'] === 'admin';

$customer = [
    'name' => '', 'email' => '', 'phone' => '',
    'address1' => '', 'address2' => '',
    'town' => '', 'county' => '', 'postcode' => '',
    'notes' => '',
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach (array_keys($customer) as $k) {
        $customer[$k] = trim((string) ($_POST[$k] ?? ''));
    }

    if ($customer['name'] === '') {
        $error = 'Name is required.';
    } elseif ($customer['email'] !== '' && !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = db()->prepare(
            'INSERT INTO customers
              (client_id, name, email, phone, address1, address2, town, county, postcode, notes)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $clientId,
            $customer['name'],
            $customer['email']    !== '' ? $customer['email']    : null,
            $customer['phone']    !== '' ? $customer['phone']    : null,
            $customer['address1'] !== '' ? $customer['address1'] : null,
            $customer['address2'] !== '' ? $customer['address2'] : null,
            $customer['town']     !== '' ? $customer['town']     : null,
            $customer['county']   !== '' ? $customer['county']   : null,
            $customer['postcode'] !== '' ? $customer['postcode'] : null,
            $customer['notes']    !== '' ? $customer['notes']    : null,
        ]);
        $newId = (int) db()->lastInsertId();
        $_SESSION['flash_success'] = 'Customer added.';
        header('Location: /customer-manager/edit.php?id=' . $newId);
        exit;
    }
}

$dashHref = $isAdmin ? '/admin/index.php' : '/quote-builder/index.php';
$dashTag  = $isAdmin ? 'Admin Console'    : 'Trade Portal';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add customer &middot; YourBlinds</title>
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
                <h1 class="page-title">Add customer</h1>
                <p class="page-subtitle">
                    <a href="/customer-manager/index.php">&larr; Back to customers</a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/customer-manager/new.php" class="form" novalidate>
                <?= csrf_field() ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="name">Name <span class="required">*</span></label>
                        <input id="name" name="name" type="text" required maxlength="150" autofocus
                               value="<?= e($customer['name']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" maxlength="150" autocomplete="email"
                               value="<?= e($customer['email']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input id="phone" name="phone" type="tel" maxlength="50" autocomplete="tel"
                               value="<?= e($customer['phone']) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="address1">Address line 1</label>
                        <input id="address1" name="address1" type="text" maxlength="150"
                               value="<?= e($customer['address1']) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="address2">Address line 2</label>
                        <input id="address2" name="address2" type="text" maxlength="150"
                               value="<?= e($customer['address2']) ?>">
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="town">Town</label>
                        <input id="town" name="town" type="text" maxlength="100"
                               value="<?= e($customer['town']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="county">County</label>
                        <input id="county" name="county" type="text" maxlength="100"
                               value="<?= e($customer['county']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="postcode">Postcode</label>
                        <input id="postcode" name="postcode" type="text" maxlength="20" autocomplete="postal-code"
                               value="<?= e($customer['postcode']) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="4"><?= e($customer['notes']) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save customer</button>
                    <a href="/customer-manager/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
