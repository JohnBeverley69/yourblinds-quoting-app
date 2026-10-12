<?php
declare(strict_types=1);

/**
 * Factory · order the BOUGHT-IN blinds on an incoming order from their third-
 * party suppliers (e.g. a PF Venetian from Hunter Douglas). The factory side of
 * quote-builder/order_suppliers.php:
 *   - acts across tenants (loads the order by id, authorises on a factory-owned
 *     line — not the tenant's client_id, which would 404);
 *   - a line is bought-in per its MASTER product's supplier (the push doesn't
 *     copy supplier onto tenant copies), so it groups by the master supplier;
 *   - emails/delivery/from-identity come from the FACTORY's own records;
 *   - logs to supplier_orders with ordered_by_factory_id and stamps
 *     quotes.supplier_ordered_at.
 *
 *   GET  ?id=N   → preview: which supplier gets what, any blockers, already-sent.
 *   POST (send[]) → send the ticked suppliers.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../quote-builder/_helpers.php';
require __DIR__ . '/../_partials/bought_in.php';
require __DIR__ . '/../_partials/supplier_send.php';
require __DIR__ . '/../_partials/factory_boughtin.php';

requireFactoryOffice();

$user      = current_user();
$factoryId = current_factory_id();
$pdo       = db();

$quoteId = (int) ($_GET['id'] ?? $_POST['quote_id'] ?? 0);
$qs = $pdo->prepare('SELECT * FROM quotes WHERE id = ? LIMIT 1');
$qs->execute([$quoteId]);
$quote = $qs->fetch(PDO::FETCH_ASSOC);
if (!$quote) { http_response_code(404); exit('Order not found.'); }

// Back to the Incoming orders tab this came from (whitelisted lanes only).
require_once __DIR__ . '/../_partials/order_tracks.php';
$backLane    = (string) ($_GET['back'] ?? $_POST['back'] ?? '');
$backToOrder = ot_back_url($backLane);

// Authorise: this factory owns a line on the order.
$own = $pdo->prepare(
    'SELECT 1 FROM quote_items qi JOIN products p ON p.id = qi.product_id
      WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ? LIMIT 1'
);
$own->execute([$quoteId, $factoryId]);
if (!$own->fetchColumn()) { http_response_code(403); exit('This order isn\'t yours to make.'); }

// Only on a placed order.
if (!in_array((string) $quote['status'], ['ordered', 'fitted', 'invoiced', 'paid'], true)) {
    qb_flash_redirect($backToOrder, 'error', 'Only placed orders can be ordered from suppliers.');
}

// Factory buyer identity, delivery, from and supplier emails — the FACTORY's own
// records, shared with Process order (factory_po_sender).
$sender          = factory_po_sender($pdo, $factoryId);
$deliveryAddress = (string) $sender['delivery'];
$emailByName     = $sender['emails'];
$accountByName   = $sender['accounts'];

// Bought-in lines only, grouped by the MASTER product's supplier.
$lines = [];
try {
    $iStmt = $pdo->prepare(
        'SELECT qi.id, qi.line_no, qi.product_id,
                qi.product_name_snapshot, qi.system_name_snapshot,
                qi.fabric_name_snapshot, qi.fabric_colour_snapshot, qi.fabric_code_snapshot,
                qi.fabric_band_snapshot, qi.width_mm, qi.drop_mm, qi.quantity, qi.room_name, qi.notes,
                mp.supplier_name AS supplier
           FROM quote_items qi
           JOIN products p ON p.id = qi.product_id
           ' . bought_in_master_join('p', 'mp') . '
          WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
            AND ' . bought_in_predicate('mp') . '
       ORDER BY qi.line_no, qi.id'
    );
    $iStmt->execute([$quoteId, $factoryId]);
    $lines = $iStmt->fetchAll();
} catch (Throwable $e) {
    qb_flash_redirect($backToOrder, 'error', 'Could not read the bought-in lines: ' . $e->getMessage());
}

// Extras per line, formatted as on every PO.
$extrasByItem = factory_po_extras($pdo, array_map(static fn ($l) => (int) $l['id'], $lines));

// Group by supplier.
$groups = [];
foreach ($lines as $ln) {
    $sup = trim((string) ($ln['supplier'] ?? ''));
    if ($sup === '') continue;   // shouldn't happen — predicate guarantees a supplier
    $ln['options'] = $extrasByItem[(int) $ln['id']] ?? [];
    $groups[$sup]['items'][] = $ln;
    $groups[$sup]['qty'] = ($groups[$sup]['qty'] ?? 0) + (int) ($ln['quantity'] ?? 1);
}
ksort($groups);

// Already sent (factory sends only) per supplier.
$lastSent = [];
try {
    $lsStmt = $pdo->prepare(
        "SELECT supplier_name, MAX(sent_at) AS last_sent
           FROM supplier_orders WHERE quote_id = ? AND ordered_by_factory_id = ?
       GROUP BY supplier_name"
    );
    $lsStmt->execute([$quoteId, $factoryId]);
    foreach ($lsStmt->fetchAll() as $r) { $lastSent[trim((string) $r['supplier_name'])] = (string) $r['last_sent']; }
} catch (Throwable $e) {}

$isSendable = static fn (string $name) => $name !== '' && factory_po_email($sender, $name) !== '';

// ── Send ────────────────────────────────────────────────────────────────────
// The backstop: Process order sends the POs normally. This page re-sends a
// supplier's WHOLE order (ticked on purpose) when something has gone wrong.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $chosen = array_map('strval', (array) ($_POST['send'] ?? []));
    $sentTo = []; $failed = [];

    foreach ($chosen as $name) {
        $name = trim($name);
        if (!isset($groups[$name]) || !$isSendable($name)) continue;
        $r = factory_po_send($pdo, $sender, $quoteId, (string) $quote['quote_number'], $name, $groups[$name]['items'], (int) $user['user_id']);
        if ($r['ok']) $sentTo[] = $name; else $failed[] = $name;
    }

    // Re-derive the "ordered / received" rollups from the log (only flips to
    // "ordered" once EVERY bought-in supplier on the order has been sent).
    if ($sentTo) {
        factory_boughtin_restamp($pdo, $quoteId, $factoryId);
        // An order out to a supplier moves the stage on (Received → In production
        // for an all-bought-in order). It never recomputed here, so the order kept
        // reading "Received" after its purchase orders had gone.
        require_once __DIR__ . '/../_partials/order_stage.php';
        try { recompute_order_stage($pdo, $quoteId); } catch (Throwable $e) { /* stage is display-only here */ }
    }
    if ($sentTo && !$failed)     qb_flash_redirect($backToOrder, 'success', 'Ordered from ' . implode(', ', $sentTo) . '.');
    elseif ($sentTo && $failed)   qb_flash_redirect($backToOrder, 'error', 'Ordered from ' . implode(', ', $sentTo) . ' — FAILED for ' . implode(', ', $failed) . '.');
    elseif ($failed)              qb_flash_redirect($backToOrder, 'error', 'Nothing was sent (failed for ' . implode(', ', $failed) . ').');
    else                          qb_flash_redirect($backToOrder, 'error', 'Nothing was sent.');
}

// ── Preview (GET) ────────────────────────────────────────────────────────────
$sendableCount = 0; foreach ($groups as $name => $g) if ($isSendable((string) $name)) $sendableCount++;
$activeNav = '';
require __DIR__ . '/../_partials/factory_head.php';
?>
    <div class="page-header">
        <div>
            <h1 class="page-title">Order bought-in items</h1>
            <p class="page-subtitle"><a href="<?= e($backToOrder) ?>">&larr; Factory orders</a>
                &middot; <?= e((string) $quote['quote_number']) ?></p>
        </div>
    </div>

    <?php if (trim($deliveryAddress) === '' && $groups): ?>
        <div class="alert alert-error" role="alert">No delivery address set — suppliers won't know where to ship. Add one under <strong>Settings &rsaquo; Suppliers</strong>.</div>
    <?php endif; ?>

    <form method="post" action="/factory/order-suppliers.php" id="facSupForm">
        <?= csrf_field() ?>
        <input type="hidden" name="quote_id" value="<?= (int) $quoteId ?>">
        <input type="hidden" name="back" value="<?= e($backLane) ?>">
        <section class="section">
            <p style="color:var(--text-secondary);margin:0 0 1rem">These lines are <strong>bought-in</strong> — the factory orders them from the supplier rather than making them. Tick a supplier and <strong>Order selected</strong>; each gets an email with only their lines + a spec PDF.</p>
            <?php if (!$groups): ?>
                <p class="sup-meta">This order has no bought-in lines — everything is made in-house.</p>
            <?php endif; ?>
            <?php foreach ($groups as $name => $g):
                $name = (string) $name; $items = $g['items']; $sendable = $isSendable($name);
                $email = $emailByName[$name] ?? ''; $alreadySent = !empty($lastSent[$name]);
            ?>
                <div style="border:1px solid var(--border);border-radius:10px;margin:0 0 1rem;overflow:hidden">
                    <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1rem;background:var(--bg-subtle);border-bottom:1px solid var(--border);flex-wrap:wrap">
                        <span style="font-weight:700"><?= e($name) ?></span>
                        <span style="color:var(--text-secondary);font-size:0.875rem"><?= $email !== '' ? e($email) : '— no order email —' ?></span>
                        <?php if (($acct = $accountByName[$name] ?? '') !== ''): ?><span class="sup-meta">· acct <?= e($acct) ?></span><?php endif; ?>
                        <?php if ($alreadySent): ?><span style="color:#9a3412;background:#ffedd5;border:1px solid #fdba74;border-radius:999px;padding:0.1rem 0.55rem;font-size:0.75rem;font-weight:700">⚠️ Ordered <?= e(date('j M Y, H:i', strtotime((string) $lastSent[$name]))) ?></span><?php endif; ?>
                        <?php if ($sendable): ?>
                            <label style="margin-left:auto;display:inline-flex;align-items:center;gap:0.4rem;font-size:0.875rem">
                                <input type="checkbox" name="send[]" value="<?= e($name) ?>" <?= $alreadySent ? 'data-resend="1"' : 'checked' ?> style="width:18px;height:18px">
                                <?= $alreadySent ? 'Re-order' : 'Order' ?> <?= count($items) ?> line<?= count($items) === 1 ? '' : 's' ?>
                            </label>
                        <?php endif; ?>
                    </div>
                    <?php if (!$sendable): ?>
                        <div style="color:#9a3412;background:#fff7ed;font-size:0.8125rem;padding:0.5rem 1rem"><?= $email === '' ? 'No order email' : 'That order email isn\'t valid' ?> for <strong><?= e($name) ?></strong> — fix it under Settings &rsaquo; Suppliers to order this.</div>
                    <?php endif; ?>
                    <table class="table" style="width:100%;font-size:0.875rem">
                        <thead><tr><th>Product</th><th>Fabric / colour</th><th>Size</th><th>Qty</th><th>Room</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $it):
                                $fab = implode(' / ', array_filter([(string) $it['fabric_name_snapshot'], (string) $it['fabric_colour_snapshot'], (string) $it['fabric_code_snapshot']], static fn ($s) => trim((string) $s) !== '')); ?>
                                <tr>
                                    <td><strong><?= e((string) $it['product_name_snapshot']) ?></strong>
                                        <?php foreach (($extrasByItem[(int) $it['id']] ?? []) as $ex): ?><br><span style="color:#1f3b5b;font-size:0.8125rem;font-weight:600">+ <?= e($ex) ?></span><?php endforeach; ?>
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
            <?php if ($groups): ?>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" <?= $sendableCount > 0 ? '' : 'disabled' ?>>📦 Order selected</button>
                    <a href="<?= e($backToOrder) ?>" class="btn btn-secondary">Cancel</a>
                </div>
            <?php endif; ?>
        </section>
    </form>
<script>
(function () {
    var form = document.getElementById('facSupForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        var resent = Array.prototype.filter.call(form.querySelectorAll('input[name="send[]"][data-resend="1"]'), function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
        if (resent.length && !window.confirm('Already ordered from:\n\n  ' + resent.join('\n  ') + '\n\nOrder again? This places a second order.')) e.preventDefault();
    });
})();
</script>
<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
