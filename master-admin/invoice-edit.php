<?php
declare(strict_types=1);

/**
 * Wholesale A/R — edit a trade invoice's extra lines. ?id=<invoiceId>
 *
 * While an invoice is still a draft / raised and has NOT been sent (nothing paid,
 * no credit note against it), the factory can add a CARRIAGE line or an
 * ADJUSTMENT line (net, ex VAT; an adjustment may be negative) and remove those
 * manual lines again. Subtotal / VAT / total are recomputed at the invoice's own
 * VAT rate. Blind lines and the automatic "agreed price" line are never editable
 * here — once sent, correct an invoice with a credit note or void + reissue.
 * Super-admin (factory) only, like the rest of Wholesale.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';

requireSuperAdmin();

$pdo     = db();
$factory = ar_factory_id();
$invId   = (int) ($_GET['id'] ?? $_POST['inv_id'] ?? 0);
$back    = '/master-admin/invoice-edit.php?id=' . $invId;

$load = static function () use ($pdo, $factory, $invId): ?array {
    try {
        $st = $pdo->prepare(
            'SELECT i.*, c.company_name AS account_name FROM factory_ar_invoices i
               JOIN clients c ON c.id = i.account_client_id
              WHERE i.id = ? AND i.factory_client_id = ? LIMIT 1'
        );
        $st->execute([$invId, $factory]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
};
$inv = $load();
if (!$inv) {
    $_SESSION['flash_error'] = 'Invoice not found.';
    header('Location: /master-admin/wholesale.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');
    try {
        if ($action === 'add_line') {
            $type = (string) ($_POST['line_type'] ?? 'carriage');
            $amt  = (float) str_replace([',', '£'], '', (string) ($_POST['amount'] ?? '0'));
            ar_invoice_add_line($pdo, $factory, $invId, $type, (string) ($_POST['description'] ?? ''), $amt);
            $_SESSION['flash_success'] = ($type === 'carriage' ? 'Carriage' : 'Adjustment') . ' line added — totals updated.';
        } elseif ($action === 'remove_line') {
            ar_invoice_remove_line($pdo, $factory, $invId, (int) ($_POST['line_id'] ?? 0));
            $_SESSION['flash_success'] = 'Line removed — totals updated.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = 'Could not update the invoice: ' . $e->getMessage();
    }
    header('Location: ' . $back);
    exit;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$lines = [];
try {
    $ls = $pdo->prepare('SELECT * FROM factory_ar_invoice_lines WHERE invoice_id = ? ORDER BY sort_order, id');
    $ls->execute([$invId]);
    $lines = $ls->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* none */ }

$blockReason = ar_invoice_edit_block_reason($pdo, $inv);
$editable    = $blockReason === '';
$money  = static fn ($n) => '&pound;' . number_format((float) $n, 2);
$vatPct = rtrim(rtrim(number_format((float) $inv['vat_percent'], 2), '0'), '.');
$typeLabel = ['blind' => 'Blind', 'option' => 'Option', 'adjust' => 'Adjustment', 'carriage' => 'Carriage'];

$activeNav = 'wholesale';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit invoice <?= e((string) $inv['inv_number']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .ie-num { font-variant-numeric:tabular-nums; text-align:right; white-space:nowrap }
        .ie-tot td { font-weight:700 }
        .ie-add { display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end; margin:0.5rem 0 0 }
        .ie-add label { display:flex; flex-direction:column; gap:0.2rem; font-size:0.85rem }
        .ie-add input, .ie-add select { padding:0.4rem 0.5rem; border:1px solid var(--border-strong); border-radius:6px; font:inherit; background:var(--bg-input) }
        .ie-muted { color:var(--text-faint); font-size:0.85rem }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <p style="margin:0 0 .3rem"><a href="/master-admin/wholesale.php">&larr; Wholesale</a></p>
                <h1 class="page-title">Invoice <?= e((string) $inv['inv_number']) ?></h1>
                <p class="page-subtitle"><?= e((string) $inv['account_name']) ?> &middot; add carriage or an adjustment before it is sent.</p>
            </div>
            <div>
                <a href="/master-admin/invoice-pdf.php?id=<?= (int) $invId ?>" class="btn btn-secondary" target="_blank">View PDF</a>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>
        <?php if (!$editable): ?><div class="alert alert-error" role="alert"><?= e($blockReason) ?> Its lines can no longer be changed here.</div><?php endif; ?>

        <section class="section">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Type</th><th>Description</th><th class="ie-num">Qty</th><th class="ie-num">Unit (net)</th><th class="ie-num">Net</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($lines as $l):
                            // Manually added = carriage/adjust with no order link (see ar_invoice_add_line).
                            $manual = in_array((string) $l['line_type'], ['carriage', 'adjust'], true)
                                   && $l['source_quote_item_id'] === null && $l['source_quote_id'] === null; ?>
                            <tr>
                                <td><?= e($typeLabel[(string) $l['line_type']] ?? ucfirst((string) $l['line_type'])) ?></td>
                                <td><?= e((string) $l['description']) ?></td>
                                <td class="ie-num"><?= (int) $l['quantity'] ?></td>
                                <td class="ie-num"><?= $money($l['unit_net']) ?></td>
                                <td class="ie-num"><?= $money($l['line_net']) ?></td>
                                <td style="text-align:right">
                                    <?php if ($editable && $manual): ?>
                                        <form method="post" action="/master-admin/invoice-edit.php" style="display:inline;margin:0" data-confirm="Remove this line?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="remove_line">
                                            <input type="hidden" name="inv_id" value="<?= (int) $invId ?>">
                                            <input type="hidden" name="line_id" value="<?= (int) $l['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="ie-tot"><td colspan="4" style="text-align:right">Subtotal (net)</td><td class="ie-num"><?= $money($inv['subtotal']) ?></td><td></td></tr>
                        <tr class="ie-tot"><td colspan="4" style="text-align:right">VAT @ <?= e($vatPct) ?>%</td><td class="ie-num"><?= $money($inv['vat']) ?></td><td></td></tr>
                        <tr class="ie-tot"><td colspan="4" style="text-align:right">Total</td><td class="ie-num"><?= $money($inv['total']) ?></td><td></td></tr>
                    </tbody>
                </table>
            </div>

            <?php if ($editable): ?>
                <h2 class="section-title" style="margin:1.25rem 0 0.25rem">Add a line</h2>
                <p class="ie-muted" style="margin:0">Amounts are <strong>net (ex VAT)</strong>; VAT is added at <?= e($vatPct) ?>%. Use a negative adjustment for a discount.</p>
                <form method="post" action="/master-admin/invoice-edit.php" class="ie-add">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="add_line">
                    <input type="hidden" name="inv_id" value="<?= (int) $invId ?>">
                    <label>Type
                        <select name="line_type">
                            <option value="carriage">Carriage</option>
                            <option value="adjust">Adjustment</option>
                        </select>
                    </label>
                    <label style="flex:1;min-width:14rem">Description
                        <input type="text" name="description" maxlength="255" placeholder="e.g. Carriage — next-day courier">
                    </label>
                    <label>Amount (net) &pound;
                        <input type="number" name="amount" step="0.01" required style="width:8rem;text-align:right">
                    </label>
                    <button type="submit" class="btn btn-primary">Add line</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
