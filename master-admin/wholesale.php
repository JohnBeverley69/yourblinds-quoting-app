<?php
declare(strict_types=1);

/**
 * Master Admin · Wholesale (Layer B A/R hub).
 *
 * Beverley billing its trade accounts: placed orders that contain Beverley-owned
 * lines, and the documents raised against them. Phase 2B adds delivery notes;
 * invoices / credit notes / payments / statements follow.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';

requireSuperAdmin();

$user    = current_user();
$pdo     = db();
$factory = ar_factory_id();

$dnReady  = ar_table_ready($pdo, 'factory_ar_delivery_notes');
$invReady = ar_table_ready($pdo, 'factory_ar_invoices');
$cnReady  = ar_table_ready($pdo, 'factory_ar_credit_notes');

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    if ($action === 'dn_raise') {
        $qid = (int) ($_POST['quote_id'] ?? 0);
        try {
            if (!$dnReady) throw new RuntimeException('Run /migrate_ar_delivery_notes.php first.');

            $q = $pdo->prepare(
                "SELECT id, quote_number, client_id FROM quotes
                  WHERE id = ? AND status IN ('ordered','fitted','invoiced','paid') AND client_id <> ? LIMIT 1"
            );
            $q->execute([$qid, $factory]);
            $order = $q->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Order not found, not placed, or not a trade-account order.');

            $lines = ar_order_lines_for_doc($pdo, $factory, $qid);
            if (!$lines) throw new RuntimeException('This order has no Beverley-owned lines to deliver.');

            $ac = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
            $ac->execute([(int) $order['client_id']]);
            $acc  = $ac->fetch(PDO::FETCH_ASSOC) ?: [];
            $addr = ar_account_address_block($acc);

            $pdo->beginTransaction();

            // Insert header with a gap-free number; retry on the unique collision.
            $dnId = 0; $num = '';
            for ($try = 1; $try <= 3; $try++) {
                $num = ar_next_number($pdo, $factory, 'DN', 'factory_ar_delivery_notes', 'dn_number');
                try {
                    $ins = $pdo->prepare(
                        "INSERT INTO factory_ar_delivery_notes
                           (factory_client_id, account_client_id, dn_number, source_quote_id, status, delivery_address, created_by)
                         VALUES (?, ?, ?, ?, 'draft', ?, ?)"
                    );
                    $ins->execute([$factory, (int) $order['client_id'], $num, $qid, $addr !== '' ? $addr : null, (int) ($user['user_id'] ?? 0) ?: null]);
                    $dnId = (int) $pdo->lastInsertId();
                    break;
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000' && $try < 3) continue;   // number taken — retry
                    throw $e;
                }
            }

            $insL = $pdo->prepare(
                "INSERT INTO factory_ar_delivery_note_lines
                   (delivery_note_id, source_quote_item_id, product_name, system_name, fabric,
                    band_code, width_mm, drop_mm, quantity, room, options_snapshot, line_notes, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $so = 0;
            foreach ($lines as $ln) {
                $fabric = trim(implode(' / ', array_filter([
                    (string) $ln['fabric_name_snapshot'],
                    (string) $ln['fabric_colour_snapshot'],
                    (string) $ln['fabric_code_snapshot'],
                ], static fn ($s) => trim($s) !== '')));
                $opts = implode("\n", $ln['options'] ?? []);
                $insL->execute([
                    $dnId, (int) $ln['id'],
                    $ln['product_name_snapshot'] ?: null, $ln['system_name_snapshot'] ?: null,
                    $fabric !== '' ? $fabric : null, $ln['fabric_band_snapshot'] ?: null,
                    $ln['width_mm'], $ln['drop_mm'], (int) $ln['quantity'],
                    $ln['room_name'] ?: null, $opts !== '' ? $opts : null, $ln['notes'] ?: null, $so++,
                ]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Delivery note ' . $num . ' created (draft). View/print it below, then mark it dispatched.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not raise delivery note: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'dn_dispatch' || $action === 'dn_cancel') {
        $dnId = (int) ($_POST['dn_id'] ?? 0);
        try {
            if ($action === 'dn_dispatch') {
                $pdo->prepare("UPDATE factory_ar_delivery_notes SET status = 'dispatched', dispatched_at = NOW() WHERE id = ? AND factory_client_id = ? AND status = 'draft'")
                    ->execute([$dnId, $factory]);
                $_SESSION['flash_success'] = 'Delivery note marked dispatched.';
            } else {
                $pdo->prepare("UPDATE factory_ar_delivery_notes SET status = 'cancelled' WHERE id = ? AND factory_client_id = ? AND status <> 'cancelled'")
                    ->execute([$dnId, $factory]);
                $_SESSION['flash_success'] = 'Delivery note cancelled.';
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not update delivery note: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'inv_raise') {
        $qid = (int) ($_POST['quote_id'] ?? 0);
        try {
            if (!$invReady) throw new RuntimeException('Run /migrate_ar_invoices.php first.');

            $q = $pdo->prepare(
                "SELECT id, quote_number, client_id FROM quotes
                  WHERE id = ? AND status IN ('ordered','fitted','invoiced','paid') AND client_id <> ? LIMIT 1"
            );
            $q->execute([$qid, $factory]);
            $order = $q->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Order not found, not placed, or not a trade-account order.');

            // Guard: don't double-invoice an order (a non-void invoice already covers it).
            $dup = $pdo->prepare(
                "SELECT i.inv_number FROM factory_ar_invoice_orders io
                   JOIN factory_ar_invoices i ON i.id = io.invoice_id
                  WHERE io.quote_id = ? AND i.status <> 'void' LIMIT 1"
            );
            $dup->execute([$qid]);
            if ($existingInv = $dup->fetchColumn()) {
                throw new RuntimeException('Order already invoiced on ' . $existingInv . ' (void it first to re-invoice).');
            }

            $built = ar_invoice_lines_from_order($pdo, $factory, $qid);
            if (!$built['lines']) throw new RuntimeException('This order has no Beverley-owned lines to invoice.');
            if ($built['uncaptured']) throw new RuntimeException('This order predates wholesale-price capture — re-save its lines in the quote before invoicing (a priced option has no wholesale price).');

            // Account bill-to snapshot + VAT / terms.
            $ac = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
            $ac->execute([(int) $order['client_id']]);
            $acc  = $ac->fetch(PDO::FETCH_ASSOC) ?: [];
            $billTo = ar_account_address_block($acc);
            if (($acc['vat_number'] ?? '') !== '') $billTo .= "\nVAT No. " . $acc['vat_number'];
            $vatPct = 20.00;

            $subtotal = 0.0;
            foreach ($built['lines'] as $l) $subtotal += (float) $l['line_net'];
            $subtotal = round($subtotal, 2);
            $vat      = round($subtotal * $vatPct / 100, 2);
            $total    = round($subtotal + $vat, 2);

            $pdo->beginTransaction();

            $invId = 0; $num = '';
            for ($try = 1; $try <= 3; $try++) {
                $num = ar_next_number($pdo, $factory, 'INV', 'factory_ar_invoices', 'inv_number');
                try {
                    $ins = $pdo->prepare(
                        "INSERT INTO factory_ar_invoices
                           (factory_client_id, account_client_id, inv_number, status, issue_date, due_date,
                            vat_percent, subtotal, vat, total, bill_to_snapshot, created_by)
                         VALUES (?, ?, ?, 'raised', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?, ?, ?, ?, ?)"
                    );
                    $ins->execute([$factory, (int) $order['client_id'], $num, $vatPct, $subtotal, $vat, $total, $billTo !== '' ? $billTo : null, (int) ($user['user_id'] ?? 0) ?: null]);
                    $invId = (int) $pdo->lastInsertId();
                    break;
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000' && $try < 3) continue;
                    throw $e;
                }
            }

            $insL = $pdo->prepare(
                "INSERT INTO factory_ar_invoice_lines
                   (invoice_id, source_quote_id, source_quote_item_id, line_type, description,
                    width_mm, drop_mm, quantity, unit_net, line_net, list_trade_unit, discount_percent, discount_amount, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($built['lines'] as $l) {
                $insL->execute([
                    $invId, $l['source_quote_id'], $l['source_quote_item_id'], $l['line_type'], $l['description'],
                    $l['width_mm'], $l['drop_mm'], $l['quantity'], $l['unit_net'], $l['line_net'],
                    $l['list_trade_unit'], $l['discount_percent'], $l['discount_amount'], $l['sort_order'],
                ]);
            }
            $pdo->prepare('INSERT INTO factory_ar_invoice_orders (invoice_id, quote_id) VALUES (?, ?)')->execute([$invId, $qid]);

            $pdo->commit();
            $_SESSION['flash_success'] = 'Invoice ' . $num . ' raised (£' . number_format($total, 2) . '). View/send it below.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not raise invoice: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'inv_send' || $action === 'inv_void') {
        $invId = (int) ($_POST['inv_id'] ?? 0);
        try {
            if ($action === 'inv_send') {
                $pdo->prepare("UPDATE factory_ar_invoices SET status = 'sent', sent_at = NOW() WHERE id = ? AND factory_client_id = ? AND status IN ('raised')")
                    ->execute([$invId, $factory]);
                $_SESSION['flash_success'] = 'Invoice marked sent.';
            } else {
                $reason = trim((string) ($_POST['void_reason'] ?? '')) ?: 'Voided';
                // Never mutate a sent invoice's figures — void keeps its number (gap-free).
                $pdo->prepare("UPDATE factory_ar_invoices SET status = 'void', voided_at = NOW(), void_reason = ? WHERE id = ? AND factory_client_id = ? AND status <> 'void'")
                    ->execute([$reason, $invId, $factory]);
                $_SESSION['flash_success'] = 'Invoice voided (its number is kept). Raise a fresh one if needed.';
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not update invoice: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'cn_raise') {
        $invId = (int) ($_POST['inv_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? '')) ?: null;
        try {
            if (!$cnReady) throw new RuntimeException('Run /migrate_ar_credit_notes.php first.');

            $iv = $pdo->prepare('SELECT * FROM factory_ar_invoices WHERE id = ? AND factory_client_id = ? LIMIT 1');
            $iv->execute([$invId, $factory]);
            $inv = $iv->fetch(PDO::FETCH_ASSOC);
            if (!$inv) throw new RuntimeException('Invoice not found.');
            if ($inv['status'] === 'void') throw new RuntimeException('That invoice is void — nothing to credit.');

            $il = $pdo->prepare('SELECT * FROM factory_ar_invoice_lines WHERE invoice_id = ? ORDER BY sort_order, id');
            $il->execute([$invId]);
            $invLines = $il->fetchAll(PDO::FETCH_ASSOC);
            if (!$invLines) throw new RuntimeException('That invoice has no lines to credit.');

            $pdo->beginTransaction();

            $cnId = 0; $num = '';
            for ($try = 1; $try <= 3; $try++) {
                $num = ar_next_number($pdo, $factory, 'CN', 'factory_ar_credit_notes', 'cn_number');
                try {
                    $ins = $pdo->prepare(
                        "INSERT INTO factory_ar_credit_notes
                           (factory_client_id, account_client_id, cn_number, against_invoice_id, status, issue_date,
                            reason, vat_percent, subtotal, vat, total, settle_mode, bill_to_snapshot, created_by)
                         VALUES (?, ?, ?, ?, 'issued', CURDATE(), ?, ?, ?, ?, ?, 'credit', ?, ?)"
                    );
                    $ins->execute([
                        $factory, (int) $inv['account_client_id'], $num, $invId, $reason,
                        (float) $inv['vat_percent'], (float) $inv['subtotal'], (float) $inv['vat'], (float) $inv['total'],
                        $inv['bill_to_snapshot'], (int) ($user['user_id'] ?? 0) ?: null,
                    ]);
                    $cnId = (int) $pdo->lastInsertId();
                    break;
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000' && $try < 3) continue;
                    throw $e;
                }
            }

            $insL = $pdo->prepare(
                "INSERT INTO factory_ar_credit_note_lines
                   (credit_note_id, source_invoice_line_id, description, width_mm, drop_mm, quantity, unit_net, line_net, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $so = 0;
            foreach ($invLines as $l) {
                $insL->execute([
                    $cnId, (int) $l['id'], $l['description'], $l['width_mm'], $l['drop_mm'],
                    (int) $l['quantity'], (float) $l['unit_net'], (float) $l['line_net'], $so++,
                ]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Credit note ' . $num . ' raised against ' . $inv['inv_number'] . ' (£' . number_format((float) $inv['total'], 2) . ').';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not raise credit note: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'cn_void') {
        $cnId = (int) ($_POST['cn_id'] ?? 0);
        try {
            $pdo->prepare("UPDATE factory_ar_credit_notes SET status = 'void', voided_at = NOW() WHERE id = ? AND factory_client_id = ? AND status <> 'void'")
                ->execute([$cnId, $factory]);
            $_SESSION['flash_success'] = 'Credit note voided.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not void credit note: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    header('Location: /master-admin/wholesale.php'); exit;
}

// ── Load ─────────────────────────────────────────────────────────────────────
$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$fAccount = (int) ($_GET['account'] ?? 0) ?: null;
$fFrom    = trim((string) ($_GET['from'] ?? ''));
$fTo      = trim((string) ($_GET['to'] ?? ''));

$accounts = ar_account_options($pdo, $factory);
$orders   = ar_placed_orders($pdo, $factory, $fAccount, $fFrom !== '' ? $fFrom : null, $fTo !== '' ? $fTo : null);

$notes = [];
if ($dnReady) {
    try {
        $ns = $pdo->prepare(
            "SELECT dn.*, c.company_name AS account_name, q.quote_number AS order_number
               FROM factory_ar_delivery_notes dn
               JOIN clients c  ON c.id = dn.account_client_id
          LEFT JOIN quotes q   ON q.id = dn.source_quote_id
              WHERE dn.factory_client_id = ?
           ORDER BY dn.id DESC LIMIT 100"
        );
        $ns->execute([$factory]);
        $notes = $ns->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* leave empty */ }
}

$invoices = [];
if ($invReady) {
    try {
        $is = $pdo->prepare(
            "SELECT i.*, c.company_name AS account_name
               FROM factory_ar_invoices i
               JOIN clients c ON c.id = i.account_client_id
              WHERE i.factory_client_id = ?
           ORDER BY i.id DESC LIMIT 100"
        );
        $is->execute([$factory]);
        $invoices = $is->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* leave empty */ }
}

$creditNotes = [];
if ($cnReady) {
    try {
        $cs = $pdo->prepare(
            "SELECT cn.*, c.company_name AS account_name, i.inv_number AS against_number
               FROM factory_ar_credit_notes cn
               JOIN clients c ON c.id = cn.account_client_id
          LEFT JOIN factory_ar_invoices i ON i.id = cn.against_invoice_id
              WHERE cn.factory_client_id = ?
           ORDER BY cn.id DESC LIMIT 100"
        );
        $cs->execute([$factory]);
        $creditNotes = $cs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* leave empty */ }
}

$money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
$fmtD  = static function (?string $dt): string {
    if (!$dt) return '&mdash;';
    $ts = strtotime($dt);
    return $ts ? date('j M Y', $ts) : '&mdash;';
};
$dnPill = static function (string $s): array {
    return $s === 'dispatched' ? ['Dispatched', '#065f46', '#d1fae5']
        : ($s === 'cancelled'  ? ['Cancelled', '#6b7280', '#e5e7eb']
        : ['Draft', '#92400e', '#fef3c7']);
};
$invPill = static function (string $s): array {
    return $s === 'paid'      ? ['Paid', '#065f46', '#d1fae5']
        : ($s === 'part_paid' ? ['Part paid', '#1e40af', '#dbeafe']
        : ($s === 'void'      ? ['Void', '#6b7280', '#e5e7eb']
        : ($s === 'sent'      ? ['Sent', '#1e40af', '#dbeafe']
        : ['Raised', '#92400e', '#fef3c7'])));
};

$activeNav = 'wholesale';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wholesale &middot; Master admin</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .wh-filter { display:flex; gap:0.625rem; align-items:flex-end; flex-wrap:wrap; margin:0 0 0.75rem; }
        .wh-filter label { font-size:0.75rem; color:var(--text-faint); display:block; margin-bottom:0.15rem; }
        .wh-filter select, .wh-filter input { padding:0.4rem 0.55rem; border:1px solid var(--border-strong); border-radius:8px; font:inherit; background:var(--bg-input); }
        .wh-pill { display:inline-block; padding:0.05rem 0.5rem; font-size:0.7rem; font-weight:700; border-radius:999px; }
        .wh-money { font-variant-numeric:tabular-nums; text-align:right; }
        .wh-muted { color:var(--text-faint); font-size:0.8125rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Wholesale</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; billing your trade accounts for the orders they place &mdash; delivery notes, invoices and statements.
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>
        <?php if (!$dnReady): ?>
            <div class="alert alert-error" role="alert">
                The wholesale tables aren't set up yet — run
                <a href="/migrate_ar_delivery_notes.php"><code>/migrate_ar_delivery_notes.php</code></a> (super-admin), then reload.
            </div>
        <?php endif; ?>

        <!-- Placed orders → raise a delivery note -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.4rem">Placed orders</h2>
            <p class="wh-muted" style="margin:0 0 0.75rem;max-width:74ch">
                Orders your trade accounts have placed that contain your products. Raise a delivery note to send with the
                goods (specs only, no prices); invoicing follows.
            </p>

            <form method="get" action="/master-admin/wholesale.php" class="wh-filter">
                <div>
                    <label>Account</label>
                    <select name="account">
                        <option value="">All accounts</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= $fAccount === (int) $a['id'] ? 'selected' : '' ?>><?= e((string) $a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>From</label><input type="date" name="from" value="<?= e($fFrom) ?>"></div>
                <div><label>To</label><input type="date" name="to" value="<?= e($fTo) ?>"></div>
                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                <?php if ($fAccount || $fFrom !== '' || $fTo !== ''): ?>
                    <a href="/master-admin/wholesale.php" class="wh-muted" style="margin-left:0.25rem">clear</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Order</th><th>Account</th><th>Placed</th><th class="wh-money">Lines</th><th class="wh-money">Wholesale</th><th>Delivery notes</th><th></th></tr></thead>
                    <tbody>
                        <?php if (!$orders): ?>
                            <tr><td colspan="7" class="table-empty">No placed trade-account orders<?= $fAccount || $fFrom !== '' || $fTo !== '' ? ' for this filter' : '' ?>.</td></tr>
                        <?php else: foreach ($orders as $o): ?>
                            <tr>
                                <td><strong><?= e((string) ($o['quote_number'] ?: ('#' . (int) $o['id']))) ?></strong><br><span class="wh-muted"><?= e(ucfirst((string) $o['status'])) ?></span></td>
                                <td><?= e((string) $o['account_name']) ?></td>
                                <td style="white-space:nowrap"><?= $fmtD($o['created_at']) ?></td>
                                <td class="wh-money"><?= (int) $o['bev_lines'] ?> <span class="wh-muted">/ <?= (int) $o['bev_qty'] ?> blinds</span></td>
                                <td class="wh-money"><?= $money($o['wholesale_total']) ?></td>
                                <td><?= (int) $o['dn_count'] > 0 ? (int) $o['dn_count'] . ' raised' : '<span class="wh-muted">—</span>' ?></td>
                                <td style="text-align:right;white-space:nowrap">
                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0 0 0 0.4rem"
                                          <?= (int) $o['dn_count'] > 0 ? 'data-confirm="A delivery note already exists for this order. Raise another?"' : '' ?>>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="dn_raise">
                                        <input type="hidden" name="quote_id" value="<?= (int) $o['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" <?= $dnReady ? '' : 'disabled' ?>>Delivery note</button>
                                    </form>
                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0 0 0 0.4rem">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="inv_raise">
                                        <input type="hidden" name="quote_id" value="<?= (int) $o['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm" <?= $invReady ? '' : 'disabled' ?>>Raise invoice</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Delivery notes raised -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.6rem">Delivery notes</h2>
            <?php if (!$notes): ?>
                <p class="wh-muted" style="margin:0">No delivery notes raised yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Number</th><th>Account</th><th>Order</th><th>Status</th><th>Date</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($notes as $dn): [$lbl, $fg, $bg] = $dnPill((string) $dn['status']); ?>
                                <tr>
                                    <td><strong><?= e((string) $dn['dn_number']) ?></strong></td>
                                    <td><?= e((string) $dn['account_name']) ?></td>
                                    <td><?= e((string) ($dn['order_number'] ?: '—')) ?></td>
                                    <td><span class="wh-pill" style="background:<?= $bg ?>;color:<?= $fg ?>"><?= e($lbl) ?></span></td>
                                    <td style="white-space:nowrap"><?= $dn['status'] === 'dispatched' ? $fmtD($dn['dispatched_at']) : $fmtD($dn['created_at']) ?></td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <a href="/master-admin/delivery-note-pdf.php?id=<?= (int) $dn['id'] ?>" target="_blank" style="color:var(--link);font-size:0.8125rem;text-decoration:underline">View PDF</a>
                                        <?php if ($dn['status'] === 'draft'): ?>
                                            <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0 0 0 0.6rem">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="dn_dispatch">
                                                <input type="hidden" name="dn_id" value="<?= (int) $dn['id'] ?>">
                                                <button type="submit" style="background:none;border:0;color:var(--link);cursor:pointer;font-size:0.8125rem;text-decoration:underline;padding:0">Mark dispatched</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($dn['status'] !== 'cancelled'): ?>
                                            <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0 0 0 0.6rem"
                                                  data-confirm="Cancel delivery note <?= e((string) $dn['dn_number']) ?>?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="dn_cancel">
                                                <input type="hidden" name="dn_id" value="<?= (int) $dn['id'] ?>">
                                                <button type="submit" style="background:none;border:0;color:#b91c1c;cursor:pointer;font-size:0.8125rem;text-decoration:underline;padding:0">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Invoices raised -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.6rem">Invoices</h2>
            <?php if (!$invReady): ?>
                <div class="alert alert-error" role="alert">
                    Invoicing isn't set up yet — run
                    <a href="/migrate_ar_invoices.php"><code>/migrate_ar_invoices.php</code></a> (super-admin), then reload.
                </div>
            <?php elseif (!$invoices): ?>
                <p class="wh-muted" style="margin:0">No invoices raised yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Number</th><th>Account</th><th>Issued</th><th>Due</th><th class="wh-money">Total</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): [$lbl, $fg, $bg] = $invPill((string) $inv['status']); ?>
                                <tr>
                                    <td><strong><?= e((string) $inv['inv_number']) ?></strong></td>
                                    <td><?= e((string) $inv['account_name']) ?></td>
                                    <td style="white-space:nowrap"><?= $fmtD($inv['issue_date']) ?></td>
                                    <td style="white-space:nowrap"><?= $fmtD($inv['due_date']) ?></td>
                                    <td class="wh-money"><?= $money($inv['total']) ?></td>
                                    <td><span class="wh-pill" style="background:<?= $bg ?>;color:<?= $fg ?>"><?= e($lbl) ?></span></td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <a href="/master-admin/invoice-pdf.php?id=<?= (int) $inv['id'] ?>" target="_blank" style="color:var(--link);font-size:0.8125rem;text-decoration:underline">View PDF</a>
                                        <?php if ($inv['status'] === 'raised'): ?>
                                            <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0 0 0 0.6rem">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="inv_send">
                                                <input type="hidden" name="inv_id" value="<?= (int) $inv['id'] ?>">
                                                <button type="submit" style="background:none;border:0;color:var(--link);cursor:pointer;font-size:0.8125rem;text-decoration:underline;padding:0">Mark sent</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($inv['status'] !== 'void' && $inv['status'] !== 'paid'): ?>
                                            <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0 0 0 0.6rem"
                                                  data-confirm="Void invoice <?= e((string) $inv['inv_number']) ?>? Its number is kept; raise a fresh invoice to replace it.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="inv_void">
                                                <input type="hidden" name="inv_id" value="<?= (int) $inv['id'] ?>">
                                                <button type="submit" style="background:none;border:0;color:#b91c1c;cursor:pointer;font-size:0.8125rem;text-decoration:underline;padding:0">Void</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($cnReady && $inv['status'] !== 'void'): ?>
                                            <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0 0 0 0.6rem"
                                                  data-confirm="Raise a full credit note for <?= e((string) $inv['inv_number']) ?> (£<?= e(number_format((float) $inv['total'], 2)) ?>)?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="cn_raise">
                                                <input type="hidden" name="inv_id" value="<?= (int) $inv['id'] ?>">
                                                <button type="submit" style="background:none;border:0;color:var(--link);cursor:pointer;font-size:0.8125rem;text-decoration:underline;padding:0">Credit note</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Credit notes -->
        <?php if ($cnReady && $creditNotes): ?>
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.6rem">Credit notes</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Number</th><th>Account</th><th>Against</th><th>Issued</th><th class="wh-money">Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($creditNotes as $cn): $void = $cn['status'] === 'void'; ?>
                            <tr>
                                <td><strong><?= e((string) $cn['cn_number']) ?></strong></td>
                                <td><?= e((string) $cn['account_name']) ?></td>
                                <td><?= e((string) ($cn['against_number'] ?: '—')) ?></td>
                                <td style="white-space:nowrap"><?= $fmtD($cn['issue_date']) ?></td>
                                <td class="wh-money">&minus;<?= $money($cn['total']) ?></td>
                                <td><span class="wh-pill" style="background:<?= $void ? '#e5e7eb' : '#d1fae5' ?>;color:<?= $void ? '#6b7280' : '#065f46' ?>"><?= $void ? 'Void' : 'Issued' ?></span></td>
                                <td style="text-align:right;white-space:nowrap">
                                    <a href="/master-admin/credit-note-pdf.php?id=<?= (int) $cn['id'] ?>" target="_blank" style="color:var(--link);font-size:0.8125rem;text-decoration:underline">View PDF</a>
                                    <?php if (!$void): ?>
                                        <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0 0 0 0.6rem"
                                              data-confirm="Void credit note <?= e((string) $cn['cn_number']) ?>?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="cn_void">
                                            <input type="hidden" name="cn_id" value="<?= (int) $cn['id'] ?>">
                                            <button type="submit" style="background:none;border:0;color:#b91c1c;cursor:pointer;font-size:0.8125rem;text-decoration:underline;padding:0">Void</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
