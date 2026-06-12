<?php
declare(strict_types=1);

/**
 * Send order to suppliers — split an accepted order by each product's supplier
 * and email each supplier ONLY their lines, with a spec PDF attached.
 *
 *   GET  ?id=N   → preview: who gets what, which email, any blockers.
 *   POST (send[]) → send the ticked suppliers, log each to supplier_orders.
 *
 * Grouping key is the PRODUCT's supplier (quote_items.product_id ->
 * products.supplier_name). A line whose product has no supplier, or a supplier
 * with no email in Settings, is flagged and can't be sent.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../pdf-generator/pdf.php';
require __DIR__ . '/../mailer.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$isAdmin  = ($user['role'] ?? '') === 'admin';
$_perms   = current_user_permissions();

// Ordering materials is an order-side action.
if (!$isAdmin && empty($_perms['can_create_orders'])) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>403</title><h1>403 Forbidden</h1>'
       . '<p>You don\'t have permission to order materials.</p>'
       . '<p><a href="/orders/index.php">Back to orders</a></p>';
    exit;
}

$quoteId = (int) ($_GET['id'] ?? $_POST['quote_id'] ?? 0);
$quote   = qb_load_quote_or_404($quoteId, $clientId);

$backToQuote = '/quote-builder/edit.php?id=' . $quoteId;

// Only on a real order (accepted onward).
if (!in_array((string) $quote['status'], ['accepted', 'ordered', 'fitted', 'invoiced', 'paid'], true)) {
    qb_flash_redirect($backToQuote, 'error', 'Accept the quote before ordering from suppliers.');
}

// Trade company (buyer) details for the PO header.
$cStmt = db()->prepare('SELECT company_name, email, phone FROM clients WHERE id = ? LIMIT 1');
$cStmt->execute([$clientId]);
$client = $cStmt->fetch() ?: [];

// Delivery address (where suppliers ship to).
$deliveryAddress = '';
try {
    $dStmt = db()->prepare('SELECT supplier_delivery_address FROM client_settings WHERE client_id = ? LIMIT 1');
    $dStmt->execute([$clientId]);
    $deliveryAddress = (string) ($dStmt->fetchColumn() ?: '');
} catch (Throwable $e) { /* column may be absent pre-migration */ }

// Send the order "from" the trade client's own settings — their email-from
// name + reply-to address — falling back to the company name/email. So a
// supplier sees the order coming from the client, and replies reach them.
$fromName  = (string) ($client['company_name'] ?? '');
$fromEmail = (string) ($client['email'] ?? '');
try {
    $fsStmt = db()->prepare('SELECT email_from_name, reply_to_email FROM client_settings WHERE client_id = ? LIMIT 1');
    $fsStmt->execute([$clientId]);
    if ($fsRow = $fsStmt->fetch()) {
        if (trim((string) ($fsRow['email_from_name'] ?? '')) !== '') $fromName  = trim((string) $fsRow['email_from_name']);
        if (trim((string) ($fsRow['reply_to_email']  ?? '')) !== '') $fromEmail = trim((string) $fsRow['reply_to_email']);
    }
} catch (Throwable $e) { /* columns may be absent — fall back to company details */ }

$mailOpts = [];
if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    $mailOpts['from_email']     = $fromEmail;
    $mailOpts['reply_to_email'] = $fromEmail;
}
if ($fromName !== '') {
    $mailOpts['from_name']     = $fromName;
    $mailOpts['reply_to_name'] = $fromName;
}

// Supplier emails (name -> email). Looked up in PHP (collation-safe).
$emailByName = [];
try {
    $eStmt = db()->prepare('SELECT name, email FROM suppliers WHERE client_id = ?');
    $eStmt->execute([$clientId]);
    foreach ($eStmt->fetchAll() as $r) {
        $emailByName[trim((string) $r['name'])] = trim((string) ($r['email'] ?? ''));
    }
} catch (Throwable $e) { /* suppliers table may be absent */ }

// Line items joined to their product's supplier (product_id is an int join —
// no collation pitfall). Snapshots give the spec frozen at quote time.
$lines = [];
try {
    $iStmt = db()->prepare(
        'SELECT qi.id, qi.line_no, qi.product_id,
                qi.product_name_snapshot, qi.system_name_snapshot,
                qi.fabric_name_snapshot, qi.fabric_colour_snapshot, qi.fabric_code_snapshot,
                qi.fabric_band_snapshot, qi.width_mm, qi.drop_mm, qi.quantity,
                qi.room_name, qi.notes,
                p.supplier_name AS product_supplier
           FROM quote_items qi
      LEFT JOIN products p ON p.id = qi.product_id
          WHERE qi.quote_id = ?
       ORDER BY qi.line_no, qi.id'
    );
    $iStmt->execute([$quoteId]);
    $lines = $iStmt->fetchAll();
} catch (Throwable $e) {
    qb_flash_redirect($backToQuote, 'error', 'Could not read the order lines: ' . $e->getMessage());
}

// Group by the product's supplier. Empty supplier = "unassigned" (a blocker).
$groups = [];   // name => ['items'=>[], 'qty'=>int]
foreach ($lines as $ln) {
    $sup = trim((string) ($ln['product_supplier'] ?? ''));
    $groups[$sup]['items'][]  = $ln;
    $groups[$sup]['qty']       = ($groups[$sup]['qty'] ?? 0) + (int) ($ln['quantity'] ?? 1);
}
// In House first, then named suppliers, then unassigned ('') last.
uksort($groups, static function ($a, $b) {
    if (($a === '') !== ($b === '')) return $a === '' ? 1 : -1;   // unassigned last
    if (($a === 'In House') !== ($b === 'In House')) return $a === 'In House' ? -1 : 1;
    return strcasecmp($a, $b);
});

// When was each supplier last sent for this quote?
$lastSent = [];
try {
    $lsStmt = db()->prepare(
        'SELECT supplier_name, MAX(sent_at) AS last_sent
           FROM supplier_orders WHERE client_id = ? AND quote_id = ?
       GROUP BY supplier_name'
    );
    $lsStmt->execute([$clientId, $quoteId]);
    foreach ($lsStmt->fetchAll() as $r) {
        $lastSent[trim((string) $r['supplier_name'])] = (string) $r['last_sent'];
    }
} catch (Throwable $e) { /* supplier_orders may be absent pre-migration */ }

// Helper: is a supplier group sendable? (named + has an email)
$emailFor   = static fn (string $name) => $name !== '' ? ($emailByName[$name] ?? '') : '';
$isSendable = static fn (string $name) => $name !== '' && ($emailByName[$name] ?? '') !== '';

// ── Send ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $chosen = array_map('strval', (array) ($_POST['send'] ?? []));
    $sentTo = [];
    $failed = [];

    foreach ($chosen as $name) {
        $name = trim($name);
        if (!isset($groups[$name]) || !$isSendable($name)) { continue; }
        $email = $emailFor($name);
        $items = $groups[$name]['items'];

        // Map snapshots → the PDF's spec rows.
        $pdfItems = array_map(static fn ($it) => [
            'product'  => $it['product_name_snapshot'],
            'system'   => $it['system_name_snapshot'],
            'fabric'   => $it['fabric_name_snapshot'],
            'colour'   => $it['fabric_colour_snapshot'],
            'code'     => $it['fabric_code_snapshot'],
            'band'     => $it['fabric_band_snapshot'],
            'width_mm' => $it['width_mm'],
            'drop_mm'  => $it['drop_mm'],
            'quantity' => $it['quantity'],
            'room'     => $it['room_name'],
            'notes'    => $it['notes'],
        ], $items);

        $ctx = [
            'company_name'     => (string) ($client['company_name'] ?? ''),
            'company_email'    => (string) ($client['email'] ?? ''),
            'company_phone'    => (string) ($client['phone'] ?? ''),
            'supplier_name'    => $name,
            'delivery_address' => $deliveryAddress,
            'quote_number'     => (string) $quote['quote_number'],
            'po_ref'           => (string) $quote['quote_number'],
            'date'             => date('j M Y'),
        ];

        $pdf = pdf_render_supplier_order($ctx, $pdfItems);

        // Plain-text body (the PDF carries the full spec).
        $bodyLines = [
            'Hello,',
            '',
            'Please supply the following order from ' . ($client['company_name'] ?? 'us')
                . ' (ref ' . $quote['quote_number'] . '). Full specification is attached as a PDF.',
            '',
        ];
        foreach ($pdfItems as $pi) {
            $fab = implode(' / ', array_filter([
                (string) $pi['fabric'], (string) $pi['colour'], (string) $pi['code'],
            ], static fn ($s) => trim((string) $s) !== ''));
            $sz = ((int) ($pi['width_mm'] ?? 0)) . ' x ' . ((int) ($pi['drop_mm'] ?? 0)) . ' mm';
            $bodyLines[] = '- ' . (int) ($pi['quantity'] ?? 1) . ' x ' . $pi['product']
                . ($fab !== '' ? ' — ' . $fab : '') . ' — ' . $sz
                . ((string) ($pi['room'] ?? '') !== '' ? ' (' . $pi['room'] . ')' : '');
        }
        $bodyLines[] = '';
        if (trim($deliveryAddress) !== '') {
            $bodyLines[] = 'Deliver to:';
            $bodyLines[] = $deliveryAddress;
            $bodyLines[] = '';
        }
        $bodyLines[] = 'Any queries, please reply to '
            . ($client['email'] ?: 'us')
            . ((string) ($client['phone'] ?? '') !== '' ? ' / ' . $client['phone'] : '') . '.';
        $bodyLines[] = '';
        $bodyLines[] = (string) ($client['company_name'] ?? '');

        $safeName = preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?: 'supplier';
        $attachment = ($pdf !== null) ? [
            'content'  => $pdf,
            'filename' => 'PO-' . $quote['quote_number'] . '-' . $safeName . '.pdf',
            'mime'     => 'application/pdf',
        ] : null;

        $ok = mailer_send(
            $email,
            ($client['company_name'] ?: 'Order') . ' — Purchase Order ' . $quote['quote_number'],
            implode("\n", $bodyLines),
            $attachment,
            null,
            $mailOpts
        );

        if ($ok) {
            $sentTo[] = $name;
            try {
                db()->prepare(
                    'INSERT INTO supplier_orders
                        (client_id, quote_id, supplier_name, email, item_count, sent_by_user_id)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$clientId, $quoteId, $name, $email, count($items), (int) $user['user_id']]);
            } catch (Throwable $e) { error_log('supplier_orders log failed: ' . $e->getMessage()); }
        } else {
            $failed[] = $name;
        }
    }

    if ($sentTo && !$failed) {
        qb_flash_redirect($backToQuote, 'success',
            'Order sent to ' . implode(', ', $sentTo) . '.');
    } elseif ($sentTo && $failed) {
        qb_flash_redirect($backToQuote, 'error',
            'Sent to ' . implode(', ', $sentTo) . '; FAILED for ' . implode(', ', $failed) . ' — try again.');
    } else {
        qb_flash_redirect($backToQuote, 'error',
            'Nothing was sent' . ($failed ? ' (failed for ' . implode(', ', $failed) . ')' : '') . '.');
    }
}

// ── Preview (GET) ───────────────────────────────────────────────────────────
$activeNav = 'order-history';
$sendableCount = 0;
foreach ($groups as $name => $g) { if ($isSendable((string) $name)) $sendableCount++; }
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Send to suppliers &middot; <?= e((string) $quote['quote_number']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .sup-group { border: 1px solid var(--border); border-radius: 10px; margin: 0 0 1rem; overflow: hidden; }
        .sup-head { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem;
                    background: var(--bg-subtle); border-bottom: 1px solid var(--border); flex-wrap: wrap; }
        .sup-head .sup-name { font-weight: 700; font-size: 1rem; }
        .sup-head .sup-email { color: var(--text-secondary); font-size: 0.875rem; }
        .sup-head .sup-pick { margin-left: auto; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.875rem; }
        .sup-warn { color: #9a3412; background: #fff7ed; font-size: 0.8125rem; padding: 0.5rem 1rem; }
        .sup-items { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .sup-items th { text-align: left; padding: 0.4rem 1rem; color: var(--text-faint); font-size: 0.75rem; text-transform: uppercase; }
        .sup-items td { padding: 0.4rem 1rem; border-top: 1px solid var(--border-faint); vertical-align: top; }
        .sup-meta { color: var(--text-faint); font-size: 0.8125rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Send order to suppliers</h1>
                <p class="page-subtitle">
                    <a href="<?= e($backToQuote) ?>">&larr; Back to order <?= e((string) $quote['quote_number']) ?></a>
                </p>
            </div>
        </div>

        <?php if (trim($deliveryAddress) === ''): ?>
            <div class="alert alert-error" role="alert">
                No delivery address set — suppliers won't know where to ship.
                Add one under <strong>Settings &rsaquo; Suppliers</strong> first.
            </div>
        <?php endif; ?>

        <form method="post" action="/quote-builder/order_suppliers.php">
            <?= csrf_field() ?>
            <input type="hidden" name="quote_id" value="<?= (int) $quoteId ?>">

            <section class="section">
                <p style="color:var(--text-secondary);margin:0 0 1rem">
                    Each supplier below gets an email with <strong>only their lines</strong> and a
                    spec PDF. Tick the ones to send, then <strong>Send selected orders</strong>.
                </p>

                <?php if (!$groups): ?>
                    <p class="sup-meta">This order has no line items.</p>
                <?php endif; ?>

                <?php foreach ($groups as $name => $g):
                    $name      = (string) $name;
                    $items     = $g['items'];
                    $sendable  = $isSendable($name);
                    $email     = $emailFor($name);
                ?>
                    <div class="sup-group">
                        <div class="sup-head">
                            <span class="sup-name"><?= $name !== '' ? e($name) : '⚠️ No supplier set' ?></span>
                            <?php if ($name !== ''): ?>
                                <span class="sup-email"><?= $email !== '' ? e($email) : '— no email —' ?></span>
                            <?php endif; ?>
                            <?php if (!empty($lastSent[$name])): ?>
                                <span class="sup-meta">· last sent <?= e(date('j M Y, H:i', strtotime((string) $lastSent[$name]))) ?></span>
                            <?php endif; ?>
                            <?php if ($sendable): ?>
                                <label class="sup-pick">
                                    <input type="checkbox" name="send[]" value="<?= e($name) ?>" checked
                                           style="width:18px;height:18px">
                                    Send <?= count($items) ?> line<?= count($items) === 1 ? '' : 's' ?>
                                </label>
                            <?php endif; ?>
                        </div>

                        <?php if ($name === ''): ?>
                            <div class="sup-warn">
                                These lines' products have no supplier set — assign one on the product
                                (Products &rsaquo; edit) to order them.
                            </div>
                        <?php elseif ($email === ''): ?>
                            <div class="sup-warn">
                                No order email for <strong><?= e($name) ?></strong> — add one under
                                Settings &rsaquo; Suppliers to send this.
                            </div>
                        <?php endif; ?>

                        <table class="sup-items">
                            <thead><tr>
                                <th>Product</th><th>Fabric / colour</th><th>Size</th><th>Qty</th><th>Room</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($items as $it):
                                    $fab = implode(' / ', array_filter([
                                        (string) $it['fabric_name_snapshot'],
                                        (string) $it['fabric_colour_snapshot'],
                                        (string) $it['fabric_code_snapshot'],
                                    ], static fn ($s) => trim((string) $s) !== ''));
                                ?>
                                    <tr>
                                        <td><strong><?= e((string) $it['product_name_snapshot']) ?></strong>
                                            <?= (string) $it['system_name_snapshot'] !== '' ? '<br><span class="sup-meta">' . e((string) $it['system_name_snapshot']) . '</span>' : '' ?>
                                        </td>
                                        <td><?= $fab !== '' ? e($fab) : '—' ?></td>
                                        <td><?= (int) ($it['width_mm'] ?? 0) ?> &times; <?= (int) ($it['drop_mm'] ?? 0) ?> mm</td>
                                        <td><?= (int) ($it['quantity'] ?? 1) ?></td>
                                        <td><?= e((string) ($it['room_name'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" <?= $sendableCount === 0 ? 'disabled' : '' ?>>
                        📦 Send selected orders
                    </button>
                    <a href="<?= e($backToQuote) ?>" class="btn btn-secondary">Cancel</a>
                </div>
                <?php if ($sendableCount === 0): ?>
                    <p class="sup-meta" style="margin-top:0.5rem">
                        Nothing's ready to send yet — set suppliers on your products and their emails in Settings.
                    </p>
                <?php endif; ?>
            </section>
        </form>
    </main>
</div>
</body>
</html>
