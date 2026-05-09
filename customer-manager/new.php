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
$customer['has_whatsapp'] = 0;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach (['name','email','phone','address1','address2','town','county','postcode','notes'] as $k) {
        $customer[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $customer['has_whatsapp'] = !empty($_POST['has_whatsapp']) ? 1 : 0;

    if ($customer['name'] === '') {
        $error = 'Name is required.';
    } elseif ($customer['email'] !== '' && !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = db()->prepare(
            'INSERT INTO customers
              (client_id, name, email, phone, has_whatsapp,
               address1, address2, town, county, postcode, notes)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $clientId,
            $customer['name'],
            $customer['email']    !== '' ? $customer['email']    : null,
            $customer['phone']    !== '' ? $customer['phone']    : null,
            (int) $customer['has_whatsapp'],
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

$dashTag = $isAdmin ? 'Admin Console' : 'Trade Portal';
$activeNav = 'customers';
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
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

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
                        <label style="display:inline-flex;align-items:center;gap:0.4rem;margin-top:0.5rem;font-weight:400;font-size:0.875rem;color:#4b5563;cursor:pointer">
                            <input type="checkbox" name="has_whatsapp" value="1"
                                   <?= !empty($customer['has_whatsapp']) ? 'checked' : '' ?>>
                            Customer has WhatsApp on this number
                        </label>
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
