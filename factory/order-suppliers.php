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

requireFactory();

$user      = current_user();
$factoryId = current_factory_id();
$pdo       = db();

$quoteId = (int) ($_GET['id'] ?? $_POST['quote_id'] ?? 0);
$qs = $pdo->prepare('SELECT * FROM quotes WHERE id = ? LIMIT 1');
$qs->execute([$quoteId]);
$quote = $qs->fetch(PDO::FETCH_ASSOC);
if (!$quote) { http_response_code(404); exit('Order not found.'); }

$backToOrder = '/factory/incoming-orders.php';

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

// Factory buyer identity + delivery + from — the FACTORY's own settings.
$cStmt = $pdo->prepare('SELECT company_name, email, phone FROM clients WHERE id = ? LIMIT 1');
$cStmt->execute([$factoryId]);
$client = $cStmt->fetch() ?: [];

$deliveryAddress = '';
try {
    $dStmt = $pdo->prepare('SELECT supplier_delivery_address FROM client_settings WHERE client_id = ? LIMIT 1');
    $dStmt->execute([$factoryId]);
    $deliveryAddress = (string) ($dStmt->fetchColumn() ?: '');
} catch (Throwable $e) {}

$fromName  = (string) ($client['company_name'] ?? '');
$fromEmail = (string) ($client['email'] ?? '');
try {
    $fsStmt = $pdo->prepare('SELECT email_from_name, reply_to_email FROM client_settings WHERE client_id = ? LIMIT 1');
    $fsStmt->execute([$factoryId]);
    if ($fsRow = $fsStmt->fetch()) {
        if (trim((string) ($fsRow['email_from_name'] ?? '')) !== '') $fromName  = trim((string) $fsRow['email_from_name']);
        if (trim((string) ($fsRow['reply_to_email']  ?? '')) !== '') $fromEmail = trim((string) $fsRow['reply_to_email']);
    }
} catch (Throwable $e) {}
$mailOpts = [];
if ($fromName !== '') { $mailOpts['from_name'] = $fromName; $mailOpts['reply_to_name'] = $fromName; }
if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) { $mailOpts['reply_to_email'] = $fromEmail; }

// Factory's supplier emails + account numbers.
$emailByName = []; $accountByName = [];
try {
    $supHasAccount = false;
    try { $supHasAccount = $pdo->query("SHOW COLUMNS FROM suppliers LIKE 'account_number'")->fetchColumn() !== false; } catch (Throwable $e) {}
    $eStmt = $pdo->prepare('SELECT name, email' . ($supHasAccount ? ', account_number' : '') . ' FROM suppliers WHERE client_id = ?');
    $eStmt->execute([$factoryId]);
    foreach ($eStmt->fetchAll() as $r) {
        $nm = trim((string) $r['name']);
        $emailByName[$nm]   = trim((string) ($r['email'] ?? ''));
        $accountByName[$nm] = $supHasAccount ? trim((string) ($r['account_number'] ?? '')) : '';
    }
} catch (Throwable $e) {}

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

// Extras per line.
$extrasByItem = [];
$lineIds = array_map(static fn ($l) => (int) $l['id'], $lines);
if ($lineIds) {
    $eph = implode(',', array_fill(0, count($lineIds), '?'));
    try {
        $exSt = $pdo->prepare("SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot, user_value FROM quote_item_extras WHERE quote_item_id IN ($eph) ORDER BY id");
        $exSt->execute($lineIds); $exRows = $exSt->fetchAll();
    } catch (Throwable $e) {
        $exSt = $pdo->prepare("SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot FROM quote_item_extras WHERE quote_item_id IN ($eph) ORDER BY id");
        $exSt->execute($lineIds); $exRows = $exSt->fetchAll();
        foreach ($exRows as &$er) { $er['user_value'] = null; } unset($er);
    }
    foreach ($exRows as $er) { $extrasByItem[(int) $er['quote_item_id']][] = $er; }
}
$fmtExtraSpec = static function (array $ex): string {
    $name = trim((string) ($ex['extra_name_snapshot'] ?? '')); $choice = trim((string) ($ex['choice_label_snapshot'] ?? ''));
    $out = $name; if ($choice !== '') $out .= ($out !== '' ? ': ' : '') . $choice;
    if (isset($ex['user_value']) && $ex['user_value'] !== null && (float) $ex['user_value'] > 0) {
        $out .= ' — ' . rtrim(rtrim(number_format((float) $ex['user_value'], 2, '.', ''), '0'), '.') . 'mm';
    }
    return $out;
};

// Group by supplier.
$groups = [];
foreach ($lines as $ln) {
    $sup = trim((string) ($ln['supplier'] ?? ''));
    if ($sup === '') continue;   // shouldn't happen — predicate guarantees a supplier
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

$isSendable = static fn (string $name) => $name !== '' && filter_var($emailByName[$name] ?? '', FILTER_VALIDATE_EMAIL) !== false;

// ── Send ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $chosen = array_map('strval', (array) ($_POST['send'] ?? []));
    $sentTo = []; $failed = [];

    foreach ($chosen as $name) {
        $name = trim($name);
        if (!isset($groups[$name]) || !$isSendable($name)) continue;
        $items = $groups[$name]['items'];
        $pdfItems = array_map(static fn ($it) => [
            'product'  => $it['product_name_snapshot'], 'system' => $it['system_name_snapshot'],
            'fabric'   => $it['fabric_name_snapshot'], 'colour' => $it['fabric_colour_snapshot'],
            'code'     => $it['fabric_code_snapshot'], 'band' => $it['fabric_band_snapshot'],
            'width_mm' => $it['width_mm'], 'drop_mm' => $it['drop_mm'], 'quantity' => $it['quantity'],
            'room'     => $it['room_name'], 'notes' => $it['notes'],
            'options'  => array_map($fmtExtraSpec, $extrasByItem[(int) $it['id']] ?? []),
        ], $items);

        $ctx = [
            'company_name' => (string) ($client['company_name'] ?? ''), 'company_email' => (string) ($client['email'] ?? ''),
            'company_phone' => (string) ($client['phone'] ?? ''), 'supplier_name' => $name,
            'account_number' => $accountByName[$name] ?? '', 'delivery_address' => $deliveryAddress,
            'quote_number' => (string) $quote['quote_number'], 'po_ref' => (string) $quote['quote_number'], 'date' => date('j M Y'),
        ];
        $ok = supplier_send_group($pdo, $ctx, $pdfItems, $emailByName[$name], $mailOpts, [
            'client_id' => $factoryId, 'quote_id' => $quoteId, 'supplier_name' => $name,
            'item_count' => count($items), 'sent_by_user_id' => (int) $user['user_id'], 'ordered_by_factory_id' => $factoryId,
        ]);
        if ($ok) $sentTo[] = $name; else $failed[] = $name;
    }

    // Re-derive the "ordered / received" rollups from the log (only flips to
    // "ordered" once EVERY bought-in supplier on the order has been sent).
    if ($sentTo) factory_boughtin_restamp($pdo, $quoteId, $factoryId);
    if ($sentTo && !$failed)      qb_flash_redirect($backToOrder, 'success', 'Ordered from ' . implode(', ', $sentTo) . '.');
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
                                        <?php foreach (($extrasByItem[(int) $it['id']] ?? []) as $ex): ?><br><span style="color:#1f3b5b;font-size:0.8125rem;font-weight:600">+ <?= e($fmtExtraSpec($ex)) ?></span><?php endforeach; ?>
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
