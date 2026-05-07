<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];
$isAdmin  = $user['role'] === 'admin';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Quote not found.');
}

$qstmt = db()->prepare(
    'SELECT q.*, u.full_name AS author_name
       FROM quotes q
       LEFT JOIN client_users u ON u.id = q.client_user_id
      WHERE q.id = ? AND q.client_id = ?
      LIMIT 1'
);
$qstmt->execute([$id, $clientId]);
$quote = $qstmt->fetch();
if (!$quote) {
    http_response_code(404);
    exit('Quote not found.');
}

$itemsStmt = db()->prepare(
    'SELECT qi.*, p.name AS product_name
       FROM quote_items qi
       LEFT JOIN products p ON p.id = qi.product_id
      WHERE qi.quote_id = ?
      ORDER BY qi.line_no, qi.id'
);
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$money = static fn ($n) => '£' . number_format((float) $n, 2);
$fmtDate = static function (?string $dt): string {
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('j M Y', $ts) : '—';
};

$address = array_filter([
    $quote['end_customer_address1'] ?? '',
    $quote['end_customer_address2'] ?? '',
    trim(($quote['end_customer_town'] ?? '') . ' ' . ($quote['end_customer_postcode'] ?? '')),
    $quote['end_customer_county'] ?? '',
], static fn ($s) => trim((string) $s) !== '');

$dashHref = $isAdmin ? '/admin/index.php' : '/quote-builder/index.php';
$dashTag  = $isAdmin ? 'Admin Console'    : 'Trade Portal';
$activeNav = 'quote-history';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote <?= e((string) $quote['quote_number']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    Quote <?= e((string) $quote['quote_number']) ?>
                    <span class="badge badge-<?= e((string) $quote['status']) ?>" style="margin-left:.5rem; vertical-align: middle;">
                        <?= e((string) $quote['status']) ?>
                    </span>
                </h1>
                <p class="page-subtitle">
                    <a href="/quote-history/index.php">&larr; Back to history</a>
                </p>
            </div>
            <div style="display:flex; gap:.5rem;">
                <a href="/quote-builder/edit.php?id=<?= (int) $quote['id'] ?>" class="btn btn-primary">Edit</a>
            </div>
        </div>

        <section class="section">
            <div class="detail-cols">
                <div>
                    <h3>Customer</h3>
                    <p style="margin:0; font-weight:600; color:#111827;">
                        <?= e((string) $quote['end_customer_name']) ?>
                    </p>
                    <?php if (!empty($quote['end_customer_email'])): ?>
                        <p style="margin:.25rem 0 0; font-size:.9375rem;">
                            <a href="mailto:<?= e((string) $quote['end_customer_email']) ?>">
                                <?= e((string) $quote['end_customer_email']) ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($quote['end_customer_phone'])): ?>
                        <p style="margin:.25rem 0 0; font-size:.9375rem;">
                            <?= e((string) $quote['end_customer_phone']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($address): ?>
                        <p style="margin:.5rem 0 0; font-size:.9375rem; white-space:pre-line; color:#374151;"><?= e(implode("\n", $address)) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <h3>Quote details</h3>
                    <dl>
                        <dt>Quote date</dt>
                        <dd><?= e($fmtDate($quote['quote_date'] ?? $quote['created_at'])) ?></dd>

                        <dt>Valid until</dt>
                        <dd><?= e($fmtDate($quote['valid_until'] ?? null)) ?></dd>

                        <?php if (!empty($quote['accepted_at'])): ?>
                            <dt>Accepted</dt>
                            <dd><?= e($fmtDate((string) $quote['accepted_at'])) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($quote['order_date'])): ?>
                            <dt>Ordered</dt>
                            <dd><?= e($fmtDate((string) $quote['order_date'])) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($quote['author_name'])): ?>
                            <dt>Created by</dt>
                            <dd><?= e((string) $quote['author_name']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Line items</h2>
            </div>

            <?php if (empty($items)): ?>
                <div class="table-empty">No line items on this quote yet.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:3rem;">#</th>
                                <th>Room / Description</th>
                                <th>Size</th>
                                <th class="num">Qty</th>
                                <th class="num">Unit</th>
                                <th class="num">Line total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $i => $item): ?>
                                <tr>
                                    <td><?= (int) ($item['line_no'] ?? ($i + 1)) ?></td>
                                    <td>
                                        <?php if (!empty($item['room_name'])): ?>
                                            <strong><?= e((string) $item['room_name']) ?></strong><br>
                                        <?php endif; ?>
                                        <span class="pre-line" style="font-size:.875rem; color:#374151;"><?= e((string) $item['description_text']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $w = $item['width']      ?? null;
                                        $d = $item['drop_value'] ?? null;
                                        $u = $item['unit']       ?? 'm';
                                        if ($w !== null && $d !== null) {
                                            echo e(rtrim(rtrim((string) $w, '0'), '.')) . ' &times; ' . e(rtrim(rtrim((string) $d, '0'), '.')) . e($u);
                                        } else {
                                            echo '&mdash;';
                                        }
                                        ?>
                                    </td>
                                    <td class="num"><?= (int) $item['quantity'] ?></td>
                                    <td class="num"><?= e($money($item['sell_price'])) ?></td>
                                    <td class="num"><?= e($money($item['line_total'])) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="totals-row">
                                <td colspan="5" class="label">Subtotal</td>
                                <td class="num"><?= e($money($quote['subtotal'])) ?></td>
                            </tr>
                            <tr class="totals-row">
                                <td colspan="5" class="label">VAT</td>
                                <td class="num"><?= e($money($quote['vat'])) ?></td>
                            </tr>
                            <tr class="totals-row grand">
                                <td colspan="5" class="label">Total</td>
                                <td class="num"><?= e($money($quote['total'])) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($quote['notes'])): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">Notes</h2>
                </div>
                <p class="pre-line" style="margin:0; color:#374151;"><?= e((string) $quote['notes']) ?></p>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
