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
    .fe-fabric .fab-row { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
    .fe-fabric .fab-current { font-size: 0.92rem; font-weight: 600; }
    .fe-fabric .fab-change { font: inherit; font-size: 0.8rem; cursor: pointer; border: 1px solid var(--border-strong, #cbd5e1); background: var(--bg-subtle, #f1f5f9); color: #334155; border-radius: 8px; padding: 0.3rem 0.75rem; }
    .fe-fabric .fab-change:hover { background: #e2e8f0; }
    .fe-fabric .fab-panel { margin-top: 0.5rem; border: 1px solid var(--border, #e5e7eb); border-radius: 10px; padding: 0.6rem; background: var(--bg-subtle, #f8fafc); }
    .fe-fabric .fab-controls { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; }
    .fe-fabric .fab-band { flex: 0 0 9rem; }
    .fe-fabric .fab-q { flex: 1; }
    .fe-fabric .fab-band, .fe-fabric .fab-q { font: inherit; padding: 0.4rem 0.55rem; border: 1px solid var(--border-strong, #cbd5e1); border-radius: 8px; background: var(--bg-input, #fff); color: inherit; box-sizing: border-box; }
    .fe-fabric .fab-results { max-height: 260px; overflow-y: auto; background: var(--bg-card, #fff); border: 1px solid var(--border, #e5e7eb); border-radius: 8px; }
    .fe-fabric .fab-opt { padding: 0.4rem 0.6rem; cursor: pointer; border-bottom: 1px solid var(--border, #f1f5f9); font-size: 0.9rem; }
    .fe-fabric .fab-opt:hover { background: #eef2ff; }
    .fe-fabric .fab-band-pill { display: inline-block; background: #1f3b5b; color: #fff; font-size: 0.62rem; font-weight: 700; padding: 0.05rem 0.4rem; border-radius: 999px; margin-right: 0.35rem; vertical-align: middle; }
    .fe-fabric .fab-sup { color: var(--text-faint, #94a3b8); font-size: 0.8rem; }
    .fe-fabric .fab-loading, .fe-fabric .fab-empty { padding: 0.6rem; color: var(--text-faint, #94a3b8); font-size: 0.85rem; }
    .fe-fabric.changed .fab-current { color: #166534; }
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
            <?php if (array_key_exists('due_date', (array) $order)): ?>
                <div class="fld">
                    <label>Due date</label>
                    <input type="date" name="due_date" value="<?= e((string) ($order['due_date'] ?? '')) ?>">
                    <small style="color:var(--text-muted,#667)">Stamped when the order was placed. Change it to pull a rush job forward; clear it to remove.</small>
                </div>
            <?php endif; ?>
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
                    <?php
                        $fabDisp = trim(
                            ((string) ($it['fabric_band_snapshot'] ?? '') !== '' ? 'Band ' . $it['fabric_band_snapshot'] . ' — ' : '')
                            . (string) ($it['fabric_name_snapshot'] ?? '')
                            . ((string) ($it['fabric_colour_snapshot'] ?? '') !== '' ? ' / ' . $it['fabric_colour_snapshot'] : '')
                        );
                    ?>
                    <div style="grid-column:1/-1">
                        <label>Fabric</label>
                        <div class="fe-fabric" data-pid="<?= (int) $it['product_id'] ?>" data-client="<?= (int) $clientId ?>" data-sys="<?= (int) ($it['system_id'] ?? 0) ?>">
                            <div class="fab-row">
                                <span class="fab-current"><?= e($fabDisp !== '' ? $fabDisp : '(no fabric set)') ?></span>
                                <button type="button" class="fab-change">Change fabric</button>
                            </div>
                            <input type="hidden" name="opt_fabric[<?= $iid ?>]" value="<?= (int) ($it['option_id'] ?? 0) ?>" class="fab-optid">
                            <div class="fab-panel" hidden>
                                <div class="fab-controls">
                                    <select class="fab-band"><option value="">All bands</option></select>
                                    <input type="text" class="fab-q" placeholder="Search name, colour, code&hellip;" autocomplete="off">
                                </div>
                                <div class="fab-results"></div>
                            </div>
                        </div>
                    </div>
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

    // Fabric picker per blind: search the order's own catalogue by band + text.
    function feEsc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    document.querySelectorAll('.fe-fabric').forEach(function (fab) {
        var optid = fab.querySelector('.fab-optid'), panel = fab.querySelector('.fab-panel');
        var bandSel = fab.querySelector('.fab-band'), qInp = fab.querySelector('.fab-q');
        var results = fab.querySelector('.fab-results'), current = fab.querySelector('.fab-current');
        var pid = fab.dataset.pid, client = fab.dataset.client, loaded = false, t;
        function sysId() {
            var blind = fab.closest('.fe-blind');
            var s = blind && blind.querySelector('select[name^="sys["]');
            return (s && s.value) || fab.dataset.sys || '';
        }
        function url(extra) {
            var p = new URLSearchParams({ product_id: pid, client_id: client });
            var sid = sysId(); if (sid && sid !== '0') p.set('system_id', sid);
            Object.keys(extra || {}).forEach(function (k) { p.set(k, extra[k]); });
            return '/factory/fabrics-search.php?' + p.toString();
        }
        function loadBands() {
            fetch(url({ bands: '1' })).then(function (r) { return r.json(); }).then(function (d) {
                (d.bands || []).forEach(function (b) { var o = document.createElement('option'); o.value = b; o.textContent = 'Band ' + b; bandSel.appendChild(o); });
            }).catch(function () {});
        }
        function search() {
            clearTimeout(t);
            t = setTimeout(function () {
                results.innerHTML = '<div class="fab-loading">Searching&hellip;</div>';
                fetch(url({ q: qInp.value.trim(), band: bandSel.value, limit: '100' })).then(function (r) { return r.json(); }).then(function (d) {
                    var fs = d.fabrics || [];
                    if (!fs.length) { results.innerHTML = '<div class="fab-empty">No fabrics match.</div>'; return; }
                    results.innerHTML = fs.map(function (f) {
                        var lbl = f.name + (f.colour ? ' / ' + f.colour : '');
                        return '<div class="fab-opt" data-id="' + f.id + '" data-band="' + feEsc(f.band) + '" data-label="' + feEsc(lbl) + '">'
                             + '<span class="fab-band-pill">' + feEsc(f.band) + '</span> <strong>' + feEsc(f.name) + '</strong>'
                             + (f.colour ? ' &mdash; ' + feEsc(f.colour) : '')
                             + (f.supplier ? ' <span class="fab-sup">' + feEsc(f.supplier) + '</span>' : '') + '</div>';
                    }).join('');
                }).catch(function () { results.innerHTML = '<div class="fab-empty">Search failed.</div>'; });
            }, 200);
        }
        fab.querySelector('.fab-change').addEventListener('click', function () {
            panel.hidden = !panel.hidden;
            if (!panel.hidden && !loaded) { loaded = true; loadBands(); search(); qInp.focus(); }
        });
        bandSel.addEventListener('change', search);
        qInp.addEventListener('input', search);
        results.addEventListener('click', function (e) {
            var opt = e.target.closest('.fab-opt'); if (!opt) return;
            optid.value = opt.dataset.id;
            current.textContent = 'Band ' + opt.dataset.band + ' — ' + opt.dataset.label + '  (unsaved)';
            fab.classList.add('changed');
            panel.hidden = true;
        });
    });
    </script>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
