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
    $input['has_whatsapp'] = !empty($_POST['has_whatsapp']) ? 1 : 0;

    if ($input['name'] === '') {
        $error = 'Name is required.';
    } elseif ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $upd = db()->prepare(
            'UPDATE customers
                SET name = ?, email = ?, phone = ?, has_whatsapp = ?,
                    address1 = ?, address2 = ?,
                    town = ?, county = ?, postcode = ?, notes = ?
              WHERE id = ? AND client_id = ?'
        );
        $upd->execute([
            $input['name'],
            $input['email']    !== '' ? $input['email']    : null,
            $input['phone']    !== '' ? $input['phone']    : null,
            (int) $input['has_whatsapp'],
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
    $customer['has_whatsapp'] = $input['has_whatsapp'];
}

// Recent quotes for this customer (read-only, last 5). The `quotes` table
// is dropped during the Phase 2 schema rebuild; until Phase 3 brings it
// back, skip the lookup so the page still renders.
$recentQuotes = [];
if (db()->query("SHOW TABLES LIKE 'quotes'")->fetchColumn()) {
    $qstmt = db()->prepare(
        'SELECT id, quote_number, status, total, created_at
           FROM quotes
          WHERE customer_id = ? AND client_id = ?
          ORDER BY created_at DESC
          LIMIT 5'
    );
    $qstmt->execute([$id, $clientId]);
    $recentQuotes = $qstmt->fetchAll();
}

$money = static fn ($n) => '£' . number_format((float) $n, 2);

$dashTag = $isAdmin ? 'Admin Console' : 'Trade Portal';
$activeNav = 'customers';
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
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

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
