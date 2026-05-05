<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];
$isAdmin  = $user['role'] === 'admin';

$error    = null;
$customerIdSel  = (int)    ($_POST['customer_id']        ?? $_GET['customer_id'] ?? 0);
$customerName   = trim((string) ($_POST['end_customer_name'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($customerName === '') {
        $error = 'Customer name is required.';
    } else {
        $linked = null;
        if ($customerIdSel > 0) {
            $stmt = db()->prepare(
                'SELECT * FROM customers WHERE id = ? AND client_id = ? LIMIT 1'
            );
            $stmt->execute([$customerIdSel, $clientId]);
            $linked = $stmt->fetch() ?: null;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $quoteNumber = qb_generate_quote_number($clientId);
            $stmt = $pdo->prepare(
                'INSERT INTO quotes
                  (client_id, client_user_id, customer_id, quote_number,
                   end_customer_name, end_customer_email, end_customer_phone,
                   end_customer_address1, end_customer_address2,
                   end_customer_town, end_customer_county, end_customer_postcode,
                   status, quote_date, valid_until)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                         "draft", NOW(), DATE_ADD(CURDATE(), INTERVAL 30 DAY))'
            );
            $stmt->execute([
                $clientId,
                $user['user_id'],
                $linked ? (int) $linked['id'] : null,
                $quoteNumber,
                $customerName,
                $linked['email']    ?? null,
                $linked['phone']    ?? null,
                $linked['address1'] ?? null,
                $linked['address2'] ?? null,
                $linked['town']     ?? null,
                $linked['county']   ?? null,
                $linked['postcode'] ?? null,
            ]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();

            $_SESSION['flash_success'] = 'Draft quote ' . $quoteNumber . ' created.';
            header('Location: /quote-builder/edit.php?id=' . $newId);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Could not create quote: ' . $e->getMessage();
        }
    }
}

$cstmt = db()->prepare(
    'SELECT id, name, town, postcode FROM customers WHERE client_id = ? ORDER BY name LIMIT 500'
);
$cstmt->execute([$clientId]);
$customers = $cstmt->fetchAll();

$dashHref = $isAdmin ? '/admin/index.php' : '/quote-builder/index.php';
$dashTag  = $isAdmin ? 'Admin Console'    : 'Trade Portal';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Quote &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<div class="app-shell">
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
            <a href="/quote-builder/new.php" class="active">New Quote</a>
            <a href="/quote-history/index.php">Quote History</a>
            <a href="/customer-manager/index.php">Customers</a>
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
                <h1 class="page-title">New Quote</h1>
                <p class="page-subtitle">
                    <a href="/quote-history/index.php">&larr; Back to history</a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <p class="page-subtitle" style="margin-bottom:1.25rem;">
                Pick an existing customer or type the customer name below, then add
                line items on the next screen.
            </p>

            <form method="post" action="/quote-builder/new.php" class="form" novalidate>
                <?= csrf_field() ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="customer_id">Link to existing customer (optional)</label>
                        <select id="customer_id" name="customer_id">
                            <option value="">— None / type details below —</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"
                                    <?= $customerIdSel === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $c['name']) ?>
                                    <?php if (!empty($c['town']) || !empty($c['postcode'])): ?>
                                        — <?= e(trim((string) $c['town']) . ' ' . (string) $c['postcode']) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="end_customer_name">Customer name <span class="required">*</span></label>
                        <input id="end_customer_name" name="end_customer_name" type="text"
                               required maxlength="150" autofocus
                               value="<?= e($customerName) ?>"
                               placeholder="e.g. Mrs. Sarah Davies">
                        <p style="font-size:.8125rem; color:#6b7280; margin:.4rem 0 0;">
                            If you linked an existing customer above, the rest of their details will be copied automatically.
                        </p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create draft</button>
                    <a href="/quote-history/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
