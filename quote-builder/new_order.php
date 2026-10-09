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
$soldFor = '';
$error = null;
// Default "inc VAT" tick: on for a VAT-registered business.
$soldIncVat = false;
try {
    $cv = db()->prepare('SELECT vat_number FROM clients WHERE id = ? LIMIT 1');
    $cv->execute([$clientId]);
    $soldIncVat = trim((string) ($cv->fetchColumn() ?: '')) !== '';
} catch (Throwable $e) {}
$dups  = [];   // earlier jobs with the same reference (warn, don't block)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ref   = mb_substr(trim((string) ($_POST['customer_reference'] ?? '')), 0, 100);
    $label = mb_substr(trim((string) ($_POST['end_customer_name'] ?? '')), 0, 150);
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $soldFor    = trim((string) ($_POST['sold_for'] ?? ''));
    $soldIncVat = !empty($_POST['sold_for_inc_vat']);

    if ($ref === '') {
        $error = 'Please enter your order reference.';
    }
    // Same order keyed in twice (office AND boss, or portal AND phone)? Warn
    // once with what's already there; "Start order anyway" carries on — a
    // genuine second order can share a reference.
    // confirm_dup carries the reference they confirmed — change the reference
    // and it's checked again.
    if ($error === null && (string) ($_POST['confirm_dup'] ?? '') !== $ref) {
        try {
            $d = db()->prepare(
                "SELECT quote_number, status, created_at FROM quotes
                  WHERE client_id = ? AND status <> 'declined'
                    AND LOWER(TRIM(customer_reference)) = LOWER(?)
                  ORDER BY created_at DESC LIMIT 5"
            );
            $d->execute([$clientId, $ref]);
            $dups = $d->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $dups = []; }
    }
    if ($error === null && $dups) {
        // fall through to the form with the warning
    } elseif ($error === null) {
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
            $res = qb_create_quote_from_fields($pdo, $clientId, $f, 0, (int) $user['user_id']);
            $pdo->prepare(
                'UPDATE quotes SET direct_order = 1, vat_percent = 0, end_customer_name = ?
                  WHERE id = ? AND client_id = ?'
            )->execute([$label, (int) $res['id'], $clientId]);
            if ($soldFor !== '') {
                qb_save_sold_for($pdo, (int) $res['id'], $clientId, $soldFor, $soldIncVat);
            }
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
        <?php if ($dups): ?>
            <div class="alert alert-error" role="alert">
                <strong>You already have <?= count($dups) === 1 ? 'an order' : count($dups) . ' orders' ?> with the reference
                &ldquo;<?= e($ref) ?>&rdquo;:</strong>
                <ul style="margin:0.4rem 0 0.4rem 1.1rem;padding:0">
                    <?php foreach ($dups as $dp): ?>
                        <li><?= e((string) $dp['quote_number']) ?> &mdash; <?= e((string) $dp['status']) ?>,
                            started <?= e(date('j M Y', (int) strtotime((string) $dp['created_at']))) ?></li>
                    <?php endforeach; ?>
                </ul>
                Is this a new, separate order? If so, press <strong>Start order anyway</strong>. If not, cancel so it isn&rsquo;t made twice.
            </div>
        <?php endif; ?>

        <section class="section">
            <p class="ui-hint" style="color:#6b7280;font-size:0.9375rem;margin:0 0 1rem">
                Place an order straight with us. Enter your reference, then add the blinds on the next
                screen &mdash; prices shown are your buying prices.
            </p>
            <?php /* Plain p, not .ui-hint — compact mode hides hints and this one matters. */ ?>
            <p style="color:#92400e;background:#fef3c7;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.875rem;margin:0 0 1rem;max-width:44rem">
                An order only knows what you pay us. Fill in <strong>Sold for</strong> (now or later) and it counts in your
                Dashboard&rsquo;s sales and profit; leave it blank and the order is kept out of those figures.
            </p>
            <form method="post" action="/quote-builder/new_order.php" class="form form-box-labels" novalidate>
                <?= csrf_field() ?>
                <?php if ($dups): ?><input type="hidden" name="confirm_dup" value="<?= e($ref) ?>"><?php endif; ?>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="customer_reference">Order reference <span class="required">*</span></label>
                        <input id="customer_reference" name="customer_reference" type="text" maxlength="100" required
                               placeholder="Order reference *" autofocus
                               value="<?= e($ref) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_customer_name">Customer name <span style="color:var(--text-faint);font-weight:400">(optional &mdash; also on the labels)</span></label>
                        <input id="end_customer_name" name="end_customer_name" type="text" maxlength="150"
                               placeholder="Customer name (optional)"
                               value="<?= e($label) ?>">
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="sold_for">Sold for <span style="color:var(--text-faint);font-weight:400">(optional &mdash; what your customer is paying)</span></label>
                        <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap">
                            <span>£</span>
                            <input id="sold_for" name="sold_for" type="number" step="0.01" min="0" inputmode="decimal"
                                   style="width:9rem" placeholder="Sold for (optional)" value="<?= e($soldFor) ?>">
                            <label style="display:inline-flex;align-items:center;gap:0.35rem;font-weight:400;margin:0">
                                <input type="checkbox" name="sold_for_inc_vat" value="1" <?= $soldIncVat ? 'checked' : '' ?>> inc VAT
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="notes">Order notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Order notes"><?= e($notes) ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $dups ? 'Start order anyway' : 'Start order' ?></button>
                    <a href="/orders/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
