<?php
declare(strict_types=1);

/**
 * New ORDER — for trade clients who don't quote through the system and just
 * want to send us an order, the way they do on the Blind Matrix portal.
 * Asks only for the order reference (required), an optional name for the
 * labels and notes; then the usual blind screen, priced at their buying price
 * (no markup / retail discount, no VAT-to-customer), and one Place order step.
 * Needs Create orders (or admin). See qb_is_direct_order().
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$isAdmin  = ($user['role'] ?? '') === 'admin';
$_perms   = current_user_permissions();
if (!$isAdmin && empty($_perms['can_create_orders'])) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>403 Forbidden</title>'
       . '<h1>403 Forbidden</h1>'
       . '<p>You don\'t have permission to place orders. Speak to your admin '
       . 'if you think this is wrong.</p>'
       . '<p><a href="/calendar/index.php">Back to Calendar</a></p>';
    exit;
}

$ref   = '';
$label = '';
$notes = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ref   = mb_substr(trim((string) ($_POST['customer_reference'] ?? '')), 0, 100);
    $label = mb_substr(trim((string) ($_POST['end_customer_name'] ?? '')), 0, 150);
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($ref === '') {
        $error = 'Please enter your order reference.';
    } else {
        $f = [
            'customer_id' => 0, 'end_customer_name' => '', 'end_customer_email' => '',
            'end_customer_phone' => '', 'end_customer_mobile' => '', 'has_whatsapp' => 0,
            'end_customer_address1' => '', 'end_customer_address2' => '', 'end_customer_town' => '',
            'end_customer_county' => '', 'end_customer_postcode' => '',
            'notes' => $notes, 'customer_reference' => $ref,
        ];
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Name left blank here so no customer record is spawned — the label
            // name is just text for the worksheets/labels, set below.
            $res = qb_create_quote_from_fields($pdo, $clientId, $f, 0, (int) $user['user_id'], 0, 'trade');
            $pdo->prepare(
                'UPDATE quotes SET direct_order = 1, vat_percent = 0, end_customer_name = ?
                  WHERE id = ? AND client_id = ?'
            )->execute([$label, (int) $res['id'], $clientId]);
            $pdo->commit();
            $_SESSION['flash_success'] = 'Order ' . $res['number'] . ' started — add the blinds, then Place order.';
            header('Location: /quote-builder/edit.php?id=' . (int) $res['id'] . '#add-line');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = str_contains($e->getMessage(), 'direct_order')
                ? 'Orders aren\'t switched on yet — run migrate_direct_orders.php.'
                : 'Could not start the order: ' . $e->getMessage();
        }
    }
}

$activeNav = 'order-history';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content">
    <title>New order &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">New order</h1>
                <p class="page-subtitle">
                    <a href="/orders/index.php">&larr; Order history</a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <p class="ui-hint" style="color:#6b7280;font-size:0.9375rem;margin:0 0 1rem">
                Place an order straight with us. Enter your reference, then add the blinds on the next
                screen &mdash; prices shown are your buying prices.
            </p>
            <form method="post" action="/quote-builder/new_order.php" class="form form-box-labels" novalidate>
                <?= csrf_field() ?>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="customer_reference">Order reference <span class="required">*</span></label>
                        <input id="customer_reference" name="customer_reference" type="text" maxlength="100" required
                               placeholder="Order reference *" autofocus
                               value="<?= e($ref) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_customer_name">Name for the labels <span style="color:var(--text-faint);font-weight:400">(optional)</span></label>
                        <input id="end_customer_name" name="end_customer_name" type="text" maxlength="150"
                               placeholder="Name for the labels (optional)"
                               value="<?= e($label) ?>">
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="notes">Order notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Order notes"><?= e($notes) ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Start order</button>
                    <a href="/orders/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
