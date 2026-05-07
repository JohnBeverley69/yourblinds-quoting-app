<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];
$isAdmin  = $user['role'] === 'admin';

$id    = (int) ($_GET['id'] ?? 0);
$quote = qb_load_quote_or_404($id, $clientId);

$itemsStmt = db()->prepare(
    'SELECT qi.*, p.name AS product_name
       FROM quote_items qi
       LEFT JOIN products p ON p.id = qi.product_id
      WHERE qi.quote_id = ?
      ORDER BY qi.line_no, qi.id'
);
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$prodStmt = db()->prepare(
    'SELECT p.id, p.name, pg.name AS group_name
       FROM products p
       JOIN product_groups pg ON pg.id = p.product_group_id
      WHERE p.client_id = ? AND p.active = 1
      ORDER BY pg.sort_order, pg.name, p.name'
);
$prodStmt->execute([$clientId]);
$products = $prodStmt->fetchAll();

$custStmt = db()->prepare(
    'SELECT id, name, town, postcode FROM customers WHERE client_id = ? ORDER BY name LIMIT 500'
);
$custStmt->execute([$clientId]);
$customers = $custStmt->fetchAll();

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$money    = static fn ($n) => '£' . number_format((float) $n, 2);
$dashHref = $isAdmin ? '/admin/index.php' : '/quote-builder/index.php';
$dashTag  = $isAdmin ? 'Admin Console'    : 'Trade Portal';
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
    <input type="checkbox" id="navToggle" class="nav-toggle-input">
    <label class="nav-fab" for="navToggle" aria-label="Open menu" tabindex="0">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    </label>
    <label class="nav-close" for="navToggle" aria-label="Close menu" tabindex="0">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </label>
    <label class="nav-backdrop" for="navToggle" aria-hidden="true"></label>
    <aside class="app-sidebar" aria-label="Primary navigation">
        <div class="app-sidebar-brand">
            <a href="<?= e($dashHref) ?>" class="app-brand-mark">Your<span class="accent">Blinds</span></a>
            <span class="app-brand-tag"><?= e($dashTag) ?></span>
        </div>
        <div class="app-sidebar-user">
            <div class="app-sidebar-user-name"><?= e($user['full_name']) ?></div>
            <div class="app-sidebar-user-meta">
                <?= e($user['company_name']) ?> &middot; <?= e($user['role']) ?>
            </div>
        </div>
        <nav class="app-sidebar-nav">
            <a href="<?= e($dashHref) ?>">Dashboard</a>
            <a href="/quote-builder/new.php">New Quote</a>
            <a href="/quote-history/index.php">Quote History</a>
            <a href="/customer-manager/index.php">Customers</a>
            <?php if ($isAdmin): ?>
                <a href="/admin/pricing.php">Price Lists</a>
                <a href="/admin/settings.php">Settings</a>
            <?php endif; ?>
        </nav>
        <div class="app-sidebar-foot">
            <a href="/auth/logout.php">Sign out &rarr;</a>
        </div>
    </aside>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    Quote <?= e((string) $quote['quote_number']) ?>
                    <span class="badge badge-<?= e((string) $quote['status']) ?>" style="margin-left:.5rem; vertical-align:middle;">
                        <?= e((string) $quote['status']) ?>
                    </span>
                </h1>
                <p class="page-subtitle">
                    <a href="/quote-history/index.php">&larr; Back to history</a>
                    &nbsp;&middot;&nbsp;
                    <a href="/quote-history/view.php?id=<?= (int) $quote['id'] ?>">Read-only view</a>
                </p>
            </div>
            <div style="display:flex; gap:.5rem; align-items:center;">
                <strong style="font-size:1.125rem; color:#111827;">Total <?= e($money($quote['total'])) ?></strong>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <!-- ============== CUSTOMER DETAILS ============== -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Customer details</h2>
            </div>
            <form method="post" action="/quote-builder/save_details.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="customer_id">Linked customer</label>
                        <select id="customer_id" name="customer_id">
                            <option value="">— None —</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"
                                    <?= (int) ($quote['customer_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $c['name']) ?>
                                    <?php if (!empty($c['town']) || !empty($c['postcode'])): ?>
                                        — <?= e(trim((string) $c['town']) . ' ' . (string) $c['postcode']) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="end_customer_name">Customer name <span class="required">*</span></label>
                        <input id="end_customer_name" name="end_customer_name" type="text" required maxlength="150"
                               value="<?= e((string) $quote['end_customer_name']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="end_customer_email">Email</label>
                        <input id="end_customer_email" name="end_customer_email" type="email" maxlength="150"
                               value="<?= e((string) ($quote['end_customer_email'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_customer_phone">Phone</label>
                        <input id="end_customer_phone" name="end_customer_phone" type="tel" maxlength="50"
                               value="<?= e((string) ($quote['end_customer_phone'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="end_customer_address1">Address line 1</label>
                        <input id="end_customer_address1" name="end_customer_address1" type="text" maxlength="150"
                               value="<?= e((string) ($quote['end_customer_address1'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="end_customer_address2">Address line 2</label>
                        <input id="end_customer_address2" name="end_customer_address2" type="text" maxlength="150"
                               value="<?= e((string) ($quote['end_customer_address2'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="end_customer_town">Town</label>
                        <input id="end_customer_town" name="end_customer_town" type="text" maxlength="100"
                               value="<?= e((string) ($quote['end_customer_town'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_customer_county">County</label>
                        <input id="end_customer_county" name="end_customer_county" type="text" maxlength="100"
                               value="<?= e((string) ($quote['end_customer_county'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_customer_postcode">Postcode</label>
                        <input id="end_customer_postcode" name="end_customer_postcode" type="text" maxlength="20"
                               value="<?= e((string) ($quote['end_customer_postcode'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="notes">Quote notes</label>
                        <textarea id="notes" name="notes" rows="3"><?= e((string) ($quote['notes'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save details</button>
                </div>
            </form>
        </section>

        <!-- ============== LINE ITEMS ============== -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Line items</h2>
            </div>

            <?php if (empty($items)): ?>
                <div class="table-empty">No line items yet. Add one below.</div>
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
                                <th class="num">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= (int) ($item['line_no'] ?? 0) ?></td>
                                    <td>
                                        <?php if (!empty($item['room_name'])): ?>
                                            <strong><?= e((string) $item['room_name']) ?></strong><br>
                                        <?php endif; ?>
                                        <span class="pre-line" style="font-size:.875rem; color:#374151;"><?= e((string) $item['description_text']) ?></span>
                                    </td>
                                    <td>
                                        <?= e(qb_fmt_size((float) $item['width'])) ?> &times;
                                        <?= e(qb_fmt_size((float) $item['drop_value'])) ?> m
                                    </td>
                                    <td class="num"><?= (int) $item['quantity'] ?></td>
                                    <td class="num"><?= e($money($item['sell_price'])) ?></td>
                                    <td class="num"><?= e($money($item['line_total'])) ?></td>
                                    <td>
                                        <form method="post" action="/quote-builder/delete_item.php" style="margin:0;"
                                              onsubmit="return confirm('Remove this line item?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                                            <input type="hidden" name="item_id"  value="<?= (int) $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" aria-label="Delete line">&times;</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="totals-row">
                                <td colspan="5" class="label">Subtotal</td>
                                <td class="num"><?= e($money($quote['subtotal'])) ?></td>
                                <td></td>
                            </tr>
                            <tr class="totals-row">
                                <td colspan="5" class="label">VAT</td>
                                <td class="num"><?= e($money($quote['vat'])) ?></td>
                                <td></td>
                            </tr>
                            <tr class="totals-row grand">
                                <td colspan="5" class="label">Total</td>
                                <td class="num"><?= e($money($quote['total'])) ?></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- ============== ADD LINE ITEM ============== -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Add line item</h2>
            </div>

            <noscript>
                <div class="alert alert-info">
                    Adding line items requires JavaScript for live pricing and the supplier / fabric / colour dropdowns.
                </div>
            </noscript>

            <form method="post" action="/quote-builder/add_item.php" class="form" id="add-item-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                <input type="hidden" name="round_up" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label for="item-room">Room name</label>
                        <input id="item-room" name="room_name" type="text" maxlength="80"
                               placeholder="e.g. Living Room">
                    </div>
                    <div class="form-group">
                        <label for="item-product">Product <span class="required">*</span></label>
                        <select id="item-product" name="product_id" required>
                            <option value="">Choose product...</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int) $p['id'] ?>">
                                    <?= e((string) $p['name']) ?> (<?= e((string) $p['group_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="item-supplier">Supplier <span class="required">*</span></label>
                        <select id="item-supplier" name="supplier" required>
                            <option value="">Loading...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="item-fabric">Fabric <span class="required">*</span></label>
                        <select id="item-fabric" name="fabric" required disabled>
                            <option value="">Choose supplier first</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="item-colour">Colour <span class="required">*</span></label>
                        <select id="item-colour" name="colour" required disabled>
                            <option value="">Choose fabric first</option>
                        </select>
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="item-width">Width (m) <span class="required">*</span></label>
                        <input id="item-width" name="width" type="number" step="0.001" min="0.001" required>
                    </div>
                    <div class="form-group">
                        <label for="item-drop">Drop (m) <span class="required">*</span></label>
                        <input id="item-drop" name="drop" type="number" step="0.001" min="0.001" required>
                    </div>
                    <div class="form-group">
                        <label for="item-qty">Quantity</label>
                        <input id="item-qty" name="quantity" type="number" step="1" min="1" value="1">
                    </div>
                </div>

                <div id="item-preview" class="alert alert-info" role="status" aria-live="polite">
                    Fill in product, supplier, fabric, colour and size to see the price.
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="item-submit" disabled>Add to quote</button>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
(function () {
    'use strict';

    const $ = id => document.getElementById(id);
    const supplierSel = $('item-supplier');
    const fabricSel   = $('item-fabric');
    const colourSel   = $('item-colour');
    const productSel  = $('item-product');
    const widthIn     = $('item-width');
    const dropIn      = $('item-drop');
    const qtyIn       = $('item-qty');
    const previewBox  = $('item-preview');
    const submitBtn   = $('item-submit');

    const setIdle = (el, msg) => {
        el.innerHTML = '<option value="">' + msg + '</option>';
        el.disabled = true;
    };

    async function loadJson(url) {
        const r = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
        if (!r.ok) {
            const body = await r.text();
            throw new Error('HTTP ' + r.status + ': ' + body.slice(0, 200));
        }
        return r.json();
    }

    async function loadSuppliers() {
        try {
            setIdle(supplierSel, 'Loading...');
            supplierSel.disabled = true;
            const data = await loadJson('/pricing-engine/api/suppliers.php');
            supplierSel.innerHTML = '<option value="">Choose supplier...</option>'
                + data.suppliers.map(s =>
                    '<option value="' + escapeAttr(s.name) + '">' + escapeHtml(s.name) + ' (' + s.count + ')</option>'
                ).join('');
            supplierSel.disabled = false;
        } catch (err) {
            setIdle(supplierSel, 'Failed to load suppliers');
            console.error(err);
        }
    }

    async function loadFabrics() {
        if (!supplierSel.value) {
            setIdle(fabricSel, 'Choose supplier first');
            setIdle(colourSel, 'Choose fabric first');
            schedulePreview();
            return;
        }
        try {
            setIdle(fabricSel, 'Loading...');
            const data = await loadJson('/pricing-engine/api/fabrics.php?supplier='
                + encodeURIComponent(supplierSel.value));
            fabricSel.innerHTML = '<option value="">Choose fabric...</option>'
                + data.fabrics.map(f =>
                    '<option value="' + escapeAttr(f.name) + '">' + escapeHtml(f.name) + ' (' + f.count + ')</option>'
                ).join('');
            fabricSel.disabled = false;
            setIdle(colourSel, 'Choose fabric first');
        } catch (err) {
            setIdle(fabricSel, 'Failed to load fabrics');
            console.error(err);
        }
        schedulePreview();
    }

    async function loadColours() {
        if (!supplierSel.value || !fabricSel.value) {
            setIdle(colourSel, 'Choose fabric first');
            schedulePreview();
            return;
        }
        try {
            setIdle(colourSel, 'Loading...');
            const data = await loadJson('/pricing-engine/api/colours.php'
                + '?supplier=' + encodeURIComponent(supplierSel.value)
                + '&fabric='   + encodeURIComponent(fabricSel.value));
            colourSel.innerHTML = '<option value="">Choose colour...</option>'
                + data.colours.map(c =>
                    '<option value="' + escapeAttr(c.colour) + '" data-band="' + escapeAttr(c.band) + '">'
                    + escapeHtml(c.colour) + ' (Band ' + escapeHtml(c.band) + ')</option>'
                ).join('');
            colourSel.disabled = false;
        } catch (err) {
            setIdle(colourSel, 'Failed to load colours');
            console.error(err);
        }
        schedulePreview();
    }

    let previewTimer = null;
    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(runPreview, 300);
    }

    async function runPreview() {
        const haveAll = productSel.value && supplierSel.value && fabricSel.value && colourSel.value
            && parseFloat(widthIn.value) > 0 && parseFloat(dropIn.value) > 0;
        if (!haveAll) {
            previewBox.className = 'alert alert-info';
            previewBox.textContent = 'Fill in product, supplier, fabric, colour and size to see the price.';
            submitBtn.disabled = true;
            return;
        }

        const params = new URLSearchParams({
            product_id: productSel.value,
            supplier:   supplierSel.value,
            fabric:     fabricSel.value,
            colour:     colourSel.value,
            width:      widthIn.value,
            drop:       dropIn.value,
            quantity:   qtyIn.value || '1',
            round_up:   '1'
        });
        try {
            const r = await fetch('/pricing-engine/api/preview.php?' + params, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const data = await r.json();
            if (!r.ok || data.error) {
                previewBox.className = 'alert alert-error';
                previewBox.textContent = data.error || ('HTTP ' + r.status);
                submitBtn.disabled = true;
                return;
            }
            const unit  = Number(data.sell_price).toFixed(2);
            const total = Number(data.line_total).toFixed(2);
            const qty   = Number(data.quantity);
            const rounded = data.rounded_up
                ? ' &middot; rounded up to ' + Number(data.matrix_width).toFixed(1)
                  + 'm × ' + Number(data.matrix_drop).toFixed(1) + 'm cell'
                : '';
            previewBox.className = 'alert alert-success';
            previewBox.innerHTML =
                '<strong>£' + unit + '</strong> per blind'
                + (qty > 1 ? ' × ' + qty + ' = <strong>£' + total + '</strong>' : '')
                + ' &middot; markup ' + Number(data.markup_percent).toFixed(1) + '%'
                + (data.discount_percent > 0 ? ', discount ' + Number(data.discount_percent).toFixed(1) + '%' : '')
                + rounded;
            submitBtn.disabled = false;
        } catch (err) {
            previewBox.className = 'alert alert-error';
            previewBox.textContent = 'Could not fetch live price.';
            submitBtn.disabled = true;
            console.error(err);
        }
    }

    function escapeAttr(s)  { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function escapeHtml(s)  { return escapeAttr(s); }

    supplierSel.addEventListener('change', () => { loadFabrics(); });
    fabricSel.addEventListener('change',   () => { loadColours(); });
    [colourSel, productSel].forEach(el => el.addEventListener('change', schedulePreview));
    [widthIn, dropIn, qtyIn].forEach(el => el.addEventListener('input', schedulePreview));

    loadSuppliers();
})();
</script>
</body>
</html>
