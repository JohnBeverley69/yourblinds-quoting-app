<?php
declare(strict_types=1);

/**
 * Process order — the one button that takes a new factory order on (John,
 * 2026-10-12). In one go it:
 *   1. takes the order in (factory_jobs, as Accept did),
 *   2. puts the blinds made here on the floor (bj_release_order),
 *   3. emails each bought-in supplier its purchase order — one PO per order per
 *      supplier (factory_send_outstanding, the same send as Order bought-in),
 * leaving the order In production / Awaiting supplier.
 *
 * Before it fires, a summary says what will happen and who gets emailed
 * (po_modal); a factory can skip that with its "Process orders without the
 * summary" setting (client_settings.process_skip_summary, per factory, off by
 * default). A supplier PO that can't go is never silent: the order shows
 * "Couldn't order from …" with the reason and a Retry (the same button, for
 * that one supplier). A bought-in item added later shows "New item to order
 * from …" and sends only the new lines.
 *
 * Used by factory/incoming-orders.php, factory/pipeline.php and the action
 * itself, factory/process-order.php.
 */

/** The factory's "Process orders without the summary" setting (default off). */
function po_skip_summary(PDO $pdo, int $factoryId): bool
{
    try {
        $s = $pdo->prepare('SELECT process_skip_summary FROM client_settings WHERE client_id = ? LIMIT 1');
        $s->execute([$factoryId]);
        return (int) $s->fetchColumn() === 1;
    } catch (Throwable $e) { return false; }   // not migrated — always show the summary
}

/** Units a supplier's next PO would carry: everything if never ordered, else the new lines. */
function po_supplier_units(array $b): int
{
    return ($b['sent_at'] ?? null) === null ? (int) $b['units'] : (int) $b['new_units'];
}

/**
 * What pressing the button will do, for the summary: ['title' => …, 'rows' =>
 * [['text' => …, 'warn' => bool]], 'go' => button label]. $supplier set = the
 * one-supplier retry / new-item send.
 */
function po_plan(string $ref, array $t, ?string $supplier = null): array
{
    $n = static fn (int $k, string $one) => $k . ' ' . $one . ($k === 1 ? '' : 's');
    $rows = [];
    $emailRow = static function (string $sup, array $b, bool $single) use ($n): array {
        $k = po_supplier_units($b);
        if (!$b['has_email']) {
            return ['text' => $sup . ': ' . ($b['email'] === '' ? 'no order email is set' : 'the order email isn\'t valid')
                . ', so it can\'t be ordered. The order will show "Couldn\'t order from ' . $sup . '" until it\'s fixed under Settings › Suppliers.', 'warn' => true];
        }
        $what = ($b['sent_at'] ?? null) !== null
            ? 'a purchase order for the ' . $n($k, 'new item') . ' (marked as additional — the first PO isn\'t sent again)'
            : 'its purchase order (' . $n($k, 'item') . ')';
        return ['text' => 'Email ' . $sup . ' ' . $what . ' → ' . $b['email'], 'warn' => false];
    };

    if ($supplier !== null) {
        $b = $t['buy'][$supplier] ?? null;
        if ($b) $rows[] = $emailRow($supplier, $b, true);
        return ['title' => 'Order from ' . $supplier . ' for ' . $ref, 'rows' => $rows, 'go' => 'Send to ' . $supplier];
    }

    $mk = $t['make'];
    if ($mk['units'] > 0) {
        $parts = [];
        foreach ($mk['products'] ?? [] as $p => $q) $parts[] = $p . ' ×' . $q;
        $rows[] = ['text' => 'Put ' . $n((int) $mk['units'], 'blind') . ' on the floor: ' . implode(', ', $parts), 'warn' => false];
    }
    foreach ($t['buy'] as $sup => $b) {
        if ($b['state'] === 'to_order') { $rows[] = $emailRow((string) $sup, $b, false); continue; }
        $rows[] = ['text' => $sup . ': already ordered ' . date('j M', strtotime((string) $b['sent_at'])) . ', not sent again.', 'warn' => false];
    }
    if (!$rows) $rows[] = ['text' => 'Take the order in.', 'warn' => false];
    return ['title' => 'Process ' . $ref, 'rows' => $rows, 'go' => 'Process order'];
}

/**
 * The button (a POST form to factory/process-order.php). $supplier set = a
 * Retry / New-item send for that supplier only.
 */
function po_button(int $qid, string $ref, array $t, string $back, string $cls, string $label, ?string $supplier = null, string $title = ''): string
{
    $plan = po_plan($ref, $t, $supplier);
    return '<form method="post" action="/factory/process-order.php" class="po-form" data-po-plan="' . e((string) json_encode($plan)) . '">'
         . csrf_field()
         . '<input type="hidden" name="quote_id" value="' . $qid . '">'
         . '<input type="hidden" name="back" value="' . e($back) . '">'
         . ($supplier !== null ? '<input type="hidden" name="supplier" value="' . e($supplier) . '">' : '')
         . '<button type="submit" class="' . e($cls) . '"' . ($title !== '' ? ' title="' . e($title) . '"' : '') . '>' . e($label) . '</button></form>';
}

/**
 * The summary panel + its script, once per page. With $skip (the factory's
 * setting) the buttons submit straight away; either way a button can't be
 * pressed twice (a double click must not email a supplier twice).
 */
function po_modal(bool $skip): string
{
    ob_start(); ?>
<style>
    .po-back { position: fixed; inset: 0; z-index: 1000; background: rgba(15,23,42,.45); display: flex; align-items: center; justify-content: center; padding: 16px; }
    .po-back[hidden] { display: none; }
    .po-box { background: var(--bg-card, #fff); color: var(--text-body, #1f2937); border-radius: 14px; max-width: 34rem; width: 100%;
              box-shadow: 0 20px 50px rgba(0,0,0,.25); padding: 1.2rem 1.3rem 1rem; }
    .po-box h2 { margin: 0 0 .7rem; font-size: 1.15rem; color: var(--text-primary, #111827); }
    .po-box ul { margin: 0 0 .9rem; padding: 0; list-style: none; display: flex; flex-direction: column; gap: .45rem; }
    .po-box li { padding: .5rem .7rem; border-radius: 8px; background: var(--bg-subtle, #f1f5f9); font-size: .9375rem; line-height: 1.4; overflow-wrap: anywhere; }
    .po-box li.warn { background: #fee2e2; color: #991b1b; font-weight: 600; }
    .po-box p { margin: 0 0 1rem; font-size: .8125rem; color: var(--text-muted, #64748b); }
    .po-acts { display: flex; gap: .6rem; justify-content: flex-end; flex-wrap: wrap; }
    .po-acts button { font: inherit; font-weight: 700; cursor: pointer; border-radius: 8px; padding: .5rem 1.1rem; border: 1px solid var(--border, #d7dee7); }
    .po-acts .go { background: #1f2a37; color: #fff; border-color: #1f2a37; }
    .po-acts .no { background: var(--bg-card, #fff); color: var(--text-muted, #4b5563); }
</style>
<div class="po-back" id="po-back" hidden>
    <div class="po-box" role="dialog" aria-modal="true" aria-labelledby="po-title">
        <h2 id="po-title"></h2>
        <ul id="po-rows"></ul>
        <p>Sizes and options are sent exactly as the customer ordered them.</p>
        <div class="po-acts">
            <button type="button" class="no" id="po-no">Cancel</button>
            <button type="button" class="go" id="po-go">Process order</button>
        </div>
    </div>
</div>
<script>
(function () {
    var skip = <?= $skip ? 'true' : 'false' ?>;
    var back = document.getElementById('po-back');
    var pending = null;
    function send(form) {
        var b = form.querySelector('button[type=submit]');
        if (b) { b.disabled = true; b.textContent = 'Working…'; }
        form.dataset.poGo = '1';
        form.submit();
    }
    function close() { back.hidden = true; pending = null; }
    document.addEventListener('submit', function (e) {
        var form = e.target.closest && e.target.closest('form.po-form');
        if (!form) return;
        e.preventDefault();
        if (form.dataset.poGo === '1') return;   // already on its way
        if (skip) { send(form); return; }
        var plan = {};
        try { plan = JSON.parse(form.getAttribute('data-po-plan') || '{}'); } catch (x) {}
        document.getElementById('po-title').textContent = plan.title || 'Process order';
        var ul = document.getElementById('po-rows');
        ul.textContent = '';
        (plan.rows || []).forEach(function (r) {
            var li = document.createElement('li');
            li.textContent = r.text;
            if (r.warn) li.className = 'warn';
            ul.appendChild(li);
        });
        document.getElementById('po-go').textContent = plan.go || 'Process order';
        pending = form;
        back.hidden = false;
        document.getElementById('po-go').focus();
    }, true);
    document.getElementById('po-go').addEventListener('click', function () {
        var f = pending; close();
        if (f) send(f);
    });
    document.getElementById('po-no').addEventListener('click', close);
    back.addEventListener('click', function (e) { if (e.target === back) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !back.hidden) close(); });
})();
</script>
<?php
    return (string) ob_get_clean();
}
