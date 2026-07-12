<?php
declare(strict_types=1);

/**
 * Factory · Edit order.
 *
 * Full admin control over an incoming order's Beverley lines, from the factory
 * side (cross-tenant): edit every blind's dimensions, system, fabric/colour,
 * every option + fit-height/wand-length, room and notes; add a blind (cloned
 * from the last); delete a blind; delete the whole order. Loads each blind's
 * OWN tenant catalogue for the option dropdowns (the quote-builder is locked to
 * the logged-in tenant, so it can't be reused here).
 *
 * Read + form only; all writes go through /factory/save-order.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireFactory();

$pdo    = db();
$MASTER = factory_client_id();
$qid    = (int) ($_GET['order'] ?? 0);

// Length-bearing options carry a typed number (user_value), not just a choice.
$LENGTH_EXTRAS = ['fit height', 'wand options'];

$order = null;
try {
    $s = $pdo->prepare('SELECT q.*, c.company_name FROM quotes q JOIN clients c ON c.id = q.client_id WHERE q.id = ? LIMIT 1');
    $s->execute([$qid]);
    $order = $s->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { /* handled below */ }

$clientId = (int) ($order['client_id'] ?? 0);

// Beverley lines only — the factory edits what it makes.
$items = [];
if ($order) {
    $s = $pdo->prepare(
        'SELECT qi.* FROM quote_items qi JOIN products p ON p.id = qi.product_id
          WHERE qi.quote_id = ? AND p.source_client_id = ? ORDER BY qi.line_no, qi.id'
    );
    $s->execute([$qid, $MASTER]);
    $items = $s->fetchAll(PDO::FETCH_ASSOC);
}

// Extras per item.
$extrasByItem = [];
if ($items) {
    $ids = array_map(static fn ($i) => (int) $i['id'], $items);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $s   = $pdo->prepare("SELECT * FROM quote_item_extras WHERE quote_item_id IN ($ph) ORDER BY id");
    $s->execute($ids);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $extrasByItem[(int) $r['quote_item_id']][] = $r;
}

// Catalogue (systems + extras + choices) per product, scoped to the order's tenant.
$catalogue = [];   // product_id => ['systems'=>[[id,name]], 'extras'=>[extra_id=>[name, choices[]]]]
foreach (array_unique(array_map(static fn ($i) => (int) $i['product_id'], $items)) as $pid) {
    $sys = $pdo->prepare('SELECT id, name FROM product_systems WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, name');
    $sys->execute([$pid, $clientId]);
    $systems = $sys->fetchAll(PDO::FETCH_ASSOC);

    $exs = $pdo->prepare('SELECT id, name FROM product_extras WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, name');
    $exs->execute([$pid, $clientId]);
    $extras = [];
    foreach ($exs->fetchAll(PDO::FETCH_ASSOC) as $er) {
        $eid = (int) $er['id'];
        $cs  = $pdo->prepare('SELECT DISTINCT label FROM product_extra_choices WHERE product_extra_id = ? AND active = 1 ORDER BY sort_order, label');
        $cs->execute([$eid]);
        $extras[$eid] = ['name' => (string) $er['name'], 'choices' => $cs->fetchAll(PDO::FETCH_COLUMN)];
    }
    $catalogue[$pid] = ['systems' => $systems, 'extras' => $extras];
}

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$factoryTitle = 'Edit order';
$factoryNav   = 'incoming';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
    .fe-bar { display: flex; align-items: baseline; gap: 0.75rem; flex-wrap: wrap; margin: 0 0 0.4rem; }
    .fe-h1 { font-size: 1.5rem; font-weight: 700; margin: 0; }
    .fe-sub { color: var(--text-muted, #667); margin: 0 0 1.1rem; }
    .fe-flash { padding: 0.7rem 1rem; border-radius: 10px; margin: 0 0 1.1rem; font-size: 0.9375rem; }
    .fe-flash.ok { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .fe-flash.err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .fe-blind { background: var(--bg-card, #fff); border: 1px solid var(--border, #e5e7eb); border-radius: 12px; padding: 1rem 1.1rem; margin: 0 0 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
    .fe-blind-head { display: flex; align-items: center; gap: 0.75rem; margin: 0 0 0.8rem; }
    .fe-blind-head h3 { font-size: 1.05rem; margin: 0; font-weight: 700; }
    .fe-blind-head .prod { color: var(--text-muted, #667); font-weight: 500; font-size: 0.9rem; }
    .fe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.7rem 0.9rem; }
    .fe-grid label, .fe-opts label { display: block; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em; color: var(--text-faint, #94a3b8); font-weight: 600; margin: 0 0 0.2rem; }
    .fe-grid input, .fe-grid select, .fe-opts input, .fe-opts select { width: 100%; font: inherit; padding: 0.4rem 0.55rem; border: 1px solid var(--border-strong, #cbd5e1); border-radius: 8px; background: var(--bg-input, #fff); box-sizing: border-box; color: inherit; }
    .fe-opts { margin-top: 0.9rem; border-top: 1px dashed var(--border, #e5e7eb); padding-top: 0.7rem; }
    .fe-opts-title { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-faint, #94a3b8); font-weight: 700; margin: 0 0 0.5rem; }
    .fe-opts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.6rem 0.9rem; }
    .fe-opt .with-num { display: flex; gap: 0.4rem; }
    .fe-opt .with-num select, .fe-opt .with-num input[type=text] { flex: 1; }
    .fe-opt .with-num input[type=number] { width: 5rem; flex: 0 0 auto; }
    .fe-btn { font: inherit; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; border-radius: 8px; padding: 0.5rem 1rem; }
    .fe-btn.primary { background: #166534; color: #fff; }
    .fe-btn.primary:hover { background: #14532d; }
    .fe-btn.ghost { background: var(--bg-subtle, #f1f5f9); color: #334155; }
    .fe-btn.ghost:hover { background: #e2e8f0; }
    .fe-btn.del { background: none; color: #b91c1c; padding: 0.3rem 0.5rem; font-size: 0.8rem; }
    .fe-btn.del:hover { text-decoration: underline; }
    .fe-blind-head .fe-btn.del { margin-left: auto; }
    .fe-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; margin: 1.2rem 0; }
    .fe-hdr-fields { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; margin: 0 0 1.2rem; }
    .fe-hdr-fields .fld label { display:block; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.03em; color:var(--text-faint,#94a3b8); font-weight:600; margin:0 0 0.2rem; }
    .fe-hdr-fields .fld input { font: inherit; padding: 0.4rem 0.55rem; border: 1px solid var(--border-strong, #cbd5e1); border-radius: 8px; background: var(--bg-input,#fff); color: inherit; }
    .fe-danger { border: 1px solid #fca5a5; background: #fef2f2; border-radius: 12px; padding: 0.9rem 1.1rem; margin: 1.5rem 0 0; }
    .fe-danger h3 { margin: 0 0 0.4rem; font-size: 0.95rem; color: #991b1b; }
    .fe-danger .fe-btn { background: #dc2626; color: #fff; }
    .fe-empty { background: var(--bg-subtle, #f8fafc); border: 1px dashed var(--border, #e5e7eb); border-radius: 12px; padding: 1.75rem; color: var(--text-faint, #94a3b8); text-align: center; }
</style>

<div class="fe-bar">
    <h1 class="fe-h1">Edit order</h1>
    <a href="/factory/incoming-orders.php" style="font-size:0.9rem">&larr; back to incoming</a>
</div>

<?php if ($flashOk !== ''): ?><div class="fe-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="fe-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$order): ?>
    <div class="fe-empty">Order not found.</div>
<?php elseif (!$items): ?>
    <div class="fe-empty">This order has no Beverley lines to edit.</div>
<?php else: ?>
    <p class="fe-sub">
        <strong><?= e((string) ($order['quote_number'] ?? ('#' . $qid))) ?></strong>
        &middot; <?= e((string) ($order['company_name'] ?? '')) ?>
        &middot; <?= count($items) ?> blind<?= count($items) === 1 ? '' : 's' ?>
    </p>

    <form method="post" action="/factory/save-order.php" id="fe-form">
        <?= csrf_field() ?>
        <input type="hidden" name="quote_id" value="<?= $qid ?>">

        <div class="fe-hdr-fields">
            <div class="fld">
                <label>Customer reference</label>
                <input type="text" name="customer_reference" value="<?= e((string) ($order['customer_reference'] ?? '')) ?>" maxlength="120">
            </div>
            <div class="fld">
                <label>Additional reference</label>
                <input type="text" name="additional_reference" value="<?= e((string) ($order['additional_reference'] ?? '')) ?>" maxlength="120">
            </div>
        </div>

        <?php foreach ($items as $it):
            $iid    = (int) $it['id'];
            $pid    = (int) $it['product_id'];
            $cat    = $catalogue[$pid] ?? ['systems' => [], 'extras' => []];
            $exList = $extrasByItem[$iid] ?? [];
        ?>
            <div class="fe-blind">
                <div class="fe-blind-head">
                    <h3>Blind <?= (int) $it['line_no'] ?></h3>
                    <span class="prod"><?= e((string) ($it['product_name_snapshot'] ?? '')) ?></span>
                    <button type="submit" name="del_item" value="<?= $iid ?>" class="fe-btn del" formnovalidate
                            data-confirm="Delete blind <?= (int) $it['line_no'] ?> from this order?">&times; Delete blind</button>
                </div>

                <div class="fe-grid">
                    <div>
                        <label>System</label>
                        <?php if ($cat['systems']): ?>
                            <select name="sys[<?= $iid ?>]">
                                <?php foreach ($cat['systems'] as $sy): ?>
                                    <option value="<?= (int) $sy['id'] ?>" <?= (int) $sy['id'] === (int) $it['system_id'] ? 'selected' : '' ?>><?= e((string) $sy['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="sysname[<?= $iid ?>]" value="<?= e((string) ($it['system_name_snapshot'] ?? '')) ?>">
                        <?php endif; ?>
                    </div>
                    <div><label>Width (mm)</label><input type="number" name="w[<?= $iid ?>]" value="<?= (int) $it['width_mm'] ?>" step="1" min="1"></div>
                    <div><label>Drop (mm)</label><input type="number" name="d[<?= $iid ?>]" value="<?= (int) $it['drop_mm'] ?>" step="1" min="1"></div>
                    <div><label>Qty</label><input type="number" name="qty[<?= $iid ?>]" value="<?= (int) $it['quantity'] ?>" step="1" min="1"></div>
                    <div><label>Room</label><input type="text" name="room[<?= $iid ?>]" value="<?= e((string) ($it['room_name'] ?? '')) ?>" maxlength="80"></div>
                    <div><label>Fabric</label><input type="text" name="fabname[<?= $iid ?>]" value="<?= e((string) ($it['fabric_name_snapshot'] ?? '')) ?>" maxlength="150"></div>
                    <div><label>Colour</label><input type="text" name="fabcol[<?= $iid ?>]" value="<?= e((string) ($it['fabric_colour_snapshot'] ?? '')) ?>" maxlength="150"></div>
                </div>

                <?php if ($exList): ?>
                <div class="fe-opts">
                    <p class="fe-opts-title">Options</p>
                    <div class="fe-opts-grid">
                        <?php foreach ($exList as $ex):
                            $exId    = (int) $ex['id'];
                            $exName  = (string) ($ex['extra_name_snapshot'] ?? '');
                            $curLbl  = (string) ($ex['choice_label_snapshot'] ?? '');
                            $catEx   = $cat['extras'][(int) ($ex['product_extra_id'] ?? 0)] ?? null;
                            $choices = $catEx['choices'] ?? [];
                            $isLen   = in_array(strtolower(trim($exName)), $LENGTH_EXTRAS, true);
                            $hasVal  = ($ex['user_value'] ?? null) !== null || $isLen;
                        ?>
                            <div class="fe-opt">
                                <label><?= e($exName) ?></label>
                                <div class="with-num">
                                    <?php if ($choices): ?>
                                        <select name="opt[<?= $exId ?>]">
                                            <?php $has = in_array($curLbl, $choices, true); ?>
                                            <?php if (!$has && $curLbl !== ''): ?><option value="<?= e($curLbl) ?>" selected><?= e($curLbl) ?></option><?php endif; ?>
                                            <?php foreach ($choices as $ch): ?>
                                                <option value="<?= e((string) $ch) ?>" <?= $ch === $curLbl ? 'selected' : '' ?>><?= e((string) $ch) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" name="opt[<?= $exId ?>]" value="<?= e($curLbl) ?>">
                                    <?php endif; ?>
                                    <?php if ($hasVal): ?>
                                        <input type="number" name="uval[<?= $exId ?>]" value="<?= $ex['user_value'] !== null ? e((string) (0 + $ex['user_value'])) : '' ?>" step="any" placeholder="mm" title="length (mm)">
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="fe-opts">
                    <label>Notes</label>
                    <input type="text" name="notes[<?= $iid ?>]" value="<?= e((string) ($it['notes'] ?? '')) ?>" maxlength="255" style="width:100%;font:inherit;padding:0.4rem 0.55rem;border:1px solid var(--border-strong,#cbd5e1);border-radius:8px;background:var(--bg-input,#fff);box-sizing:border-box;color:inherit;">
                </div>
            </div>
        <?php endforeach; ?>

        <div class="fe-actions">
            <button type="submit" name="save" value="1" class="fe-btn primary">Save changes</button>
            <button type="submit" name="add_item" value="1" class="fe-btn ghost" formnovalidate>+ Add blind (copy of last)</button>
            <a href="/factory/worksheet-print.php?order=<?= $qid ?>" target="_blank" rel="noopener" class="fe-btn ghost" style="text-decoration:none">Worksheet &#8599;</a>
        </div>

        <div class="fe-danger">
            <h3>Danger zone</h3>
            <button type="submit" name="del_order" value="1" class="fe-btn" formnovalidate
                    data-confirm="Delete this ENTIRE order (<?= (int) count($items) ?> blind<?= count($items) === 1 ? '' : 's' ?>)? This cannot be undone.">Delete whole order</button>
        </div>
    </form>

    <script>
    document.getElementById('fe-form').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-confirm]');
        if (btn && !confirm(btn.getAttribute('data-confirm'))) e.preventDefault();
    });
    </script>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
