<?php
declare(strict_types=1);

/**
 * Incoming Orders — Beverley factory queue.
 *
 * A cross-tenant, super-admin view of placed orders that contain Beverley
 * Blinds Trade lines, so the factory sees work coming in from every trade
 * customer WITHOUT anyone re-keying it (this replaces the Blind Matrix
 * "Online Submission" inbox).
 *
 * What counts as an incoming line:
 *   - the order (quote) has advanced to a placed status ('ordered' onward), and
 *   - the line's product carries the catalogue-source marker
 *     products.source_client_id = 3 (the master "Your Blinds" account).
 * Only the Beverley lines are shown — a tenant's own products never appear.
 *
 * Read-only for now: it surfaces orders. Marking as received / production
 * status / work sheets come in later phases.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$pdo    = db();
$MASTER = 3;   // Beverley Blinds Trade master catalogue = client #3

// Placed statuses = an order the retailer has committed to us.
$PLACED = ['ordered', 'fitted', 'invoiced', 'paid'];
$inPlaced = "'" . implode("','", $PLACED) . "'";

$orders   = [];
$linesBy  = [];
$loadError = null;

try {
    // 1) Orders (across all tenants) with >= 1 Beverley line.
    $oStmt = $pdo->prepare(
        "SELECT q.id, q.client_id, c.company_name AS tenant,
                q.quote_number, q.status, q.created_at,
                q.customer_reference, q.additional_reference,
                q.end_customer_name,
                COUNT(qi.id)          AS bev_lines,
                COALESCE(SUM(qi.quantity), 0) AS bev_qty
           FROM quotes q
           JOIN clients c       ON c.id = q.client_id
           JOIN quote_items qi  ON qi.quote_id = q.id
           JOIN products p      ON p.id = qi.product_id
          WHERE q.status IN ($inPlaced)
            AND p.source_client_id = ?
       GROUP BY q.id, q.client_id, c.company_name, q.quote_number, q.status,
                q.created_at, q.customer_reference, q.additional_reference,
                q.end_customer_name
       ORDER BY q.created_at DESC
          LIMIT 300"
    );
    $oStmt->execute([$MASTER]);
    $orders = $oStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2) The Beverley lines for those orders (one round-trip, grouped in PHP).
    $ids = array_map(static fn ($o) => (int) $o['id'], $orders);
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $lStmt = $pdo->prepare(
            "SELECT qi.quote_id, qi.line_no,
                    qi.product_name_snapshot, qi.system_name_snapshot,
                    qi.fabric_name_snapshot, qi.fabric_colour_snapshot,
                    qi.width_mm, qi.drop_mm, qi.quantity, qi.room_name
               FROM quote_items qi
               JOIN products p ON p.id = qi.product_id
              WHERE qi.quote_id IN ($ph)
                AND p.source_client_id = ?
           ORDER BY qi.quote_id, qi.line_no, qi.id"
        );
        $lStmt->execute([...$ids, $MASTER]);
        foreach ($lStmt->fetchAll(PDO::FETCH_ASSOC) as $ln) {
            $linesBy[(int) $ln['quote_id']][] = $ln;
        }
    }
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

/** "New" (needs making) orders lead the list; count them for the header. */
$newCount = 0;
foreach ($orders as $o) {
    if (($o['status'] ?? '') === 'ordered') $newCount++;
}

$fmtDate = static function (?string $ts): string {
    if (!$ts) return '';
    try { return (new DateTimeImmutable($ts))->format('j M Y'); }
    catch (Throwable $e) { return (string) $ts; }
};

$activeNav = 'incoming-orders';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Incoming Orders &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .io-sub { color: var(--text-muted); margin: 0.15rem 0 0; }
        .io-order {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 12px; margin: 0 0 1rem; overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }
        .io-head {
            display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.4rem 1.1rem;
            padding: 0.8rem 1rem; border-bottom: 1px solid var(--border);
            background: var(--bg-subtle);
        }
        .io-head .ref { font-weight: 700; font-variant-numeric: tabular-nums; }
        .io-head .tenant { font-weight: 600; color: var(--text-primary); }
        .io-head .meta { color: var(--text-muted); font-size: 0.875rem; }
        .io-status {
            font-size: 0.6875rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.04em; padding: 0.15rem 0.55rem; border-radius: 999px;
            background: #e0e7ff; color: #3730a3;
        }
        .io-status.ordered { background: #dcfce7; color: #166534; }
        .io-lines { width: 100%; border-collapse: collapse; }
        .io-lines th {
            text-align: left; font-size: 0.7rem; letter-spacing: 0.05em;
            text-transform: uppercase; color: var(--text-faint); font-weight: 600;
            padding: 0.5rem 1rem; border-bottom: 1px solid var(--border);
        }
        .io-lines td {
            padding: 0.5rem 1rem; border-bottom: 1px solid var(--border);
            font-size: 0.9375rem; vertical-align: top;
        }
        .io-lines tr:last-child td { border-bottom: none; }
        .io-lines .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .io-lines .prod { font-weight: 600; }
        .io-empty {
            background: var(--bg-subtle); border: 1px dashed var(--border);
            border-radius: 12px; padding: 1.5rem; color: var(--text-faint);
            text-align: center;
        }
        .io-badge {
            display: inline-block; background: #dcfce7; color: #166534;
            font-size: 0.8125rem; font-weight: 600; padding: 0.1rem 0.6rem;
            border-radius: 999px; margin-left: 0.5rem;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    Incoming Orders
                    <?php if ($newCount > 0): ?><span class="io-badge"><?= (int) $newCount ?> new</span><?php endif; ?>
                </h1>
                <p class="io-sub">Placed orders from every trade customer that contain Beverley Blinds Trade lines. Only your lines are shown.</p>
            </div>
        </div>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-error" role="alert">
                Couldn't load orders: <?= e($loadError) ?>
            </div>
        <?php elseif (!$orders): ?>
            <div class="io-empty">
                No incoming orders yet. Placed orders containing Beverley lines will appear here —
                nothing to re-key from Blind Matrix.
            </div>
        <?php else: ?>
            <?php foreach ($orders as $o):
                $qid   = (int) $o['id'];
                $lines = $linesBy[$qid] ?? [];
                $status = (string) ($o['status'] ?? '');
                $custRef = trim((string) ($o['customer_reference'] ?? ''));
                $addRef  = trim((string) ($o['additional_reference'] ?? ''));
                $endCust = trim((string) ($o['end_customer_name'] ?? ''));
            ?>
                <section class="io-order">
                    <div class="io-head">
                        <span class="ref"><?= e((string) ($o['quote_number'] ?? ('#' . $qid))) ?></span>
                        <span class="tenant"><?= e((string) ($o['tenant'] ?? 'Unknown account')) ?></span>
                        <span class="io-status <?= $status === 'ordered' ? 'ordered' : '' ?>"><?= e($status) ?></span>
                        <span class="meta">
                            <?= (int) $o['bev_lines'] ?> line<?= (int) $o['bev_lines'] === 1 ? '' : 's' ?>
                            &middot; <?= (int) $o['bev_qty'] ?> unit<?= (int) $o['bev_qty'] === 1 ? '' : 's' ?>
                            &middot; placed <?= e($fmtDate($o['created_at'] ?? null)) ?>
                        </span>
                        <span class="meta" style="margin-left:auto">
                            <?php if ($custRef !== ''): ?>Ref: <strong><?= e($custRef) ?></strong><?php endif; ?>
                            <?php if ($addRef !== ''): ?> &middot; <?= e($addRef) ?><?php endif; ?>
                            <?php if ($endCust !== ''): ?> &middot; <?= e($endCust) ?><?php endif; ?>
                        </span>
                    </div>
                    <?php if ($lines): ?>
                    <table class="io-lines">
                        <thead>
                            <tr>
                                <th>Product</th><th>Fabric / Colour</th>
                                <th class="num">W&times;D (mm)</th><th class="num">Qty</th><th>Room</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lines as $ln):
                                $fab = trim((string) ($ln['fabric_name_snapshot'] ?? ''));
                                $col = trim((string) ($ln['fabric_colour_snapshot'] ?? ''));
                                $sys = trim((string) ($ln['system_name_snapshot'] ?? ''));
                            ?>
                                <tr>
                                    <td class="prod">
                                        <?= e((string) ($ln['product_name_snapshot'] ?? '')) ?>
                                        <?php if ($sys !== ''): ?><br><span class="meta" style="font-weight:400;font-size:0.8125rem"><?= e($sys) ?></span><?php endif; ?>
                                    </td>
                                    <td><?= e(trim($fab . ($col !== '' ? ' / ' . $col : ''))) ?></td>
                                    <td class="num"><?= (int) $ln['width_mm'] ?> &times; <?= (int) $ln['drop_mm'] ?></td>
                                    <td class="num"><?= (int) $ln['quantity'] ?></td>
                                    <td><?= e((string) ($ln['room_name'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
