<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];

$id    = (int) ($_GET['id'] ?? 0);
$quote = qb_load_quote_or_404($id, $clientId);
$editable = qb_is_editable($quote);

// Items + their extras (one query each, then fold).
$itemsSt = db()->prepare(
    'SELECT * FROM quote_items WHERE quote_id = ? ORDER BY line_no, id'
);
$itemsSt->execute([$id]);
$items = $itemsSt->fetchAll();

$extrasByItem = [];
if ($items) {
    $itemIds = array_map(static fn ($r) => (int) $r['id'], $items);
    $ph = implode(',', array_fill(0, count($itemIds), '?'));
    $st = db()->prepare(
        "SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot,
                mode, amount_applied
           FROM quote_item_extras
          WHERE quote_item_id IN ($ph)
          ORDER BY id"
    );
    $st->execute($itemIds);
    foreach ($st->fetchAll() as $r) {
        $extrasByItem[(int) $r['quote_item_id']][] = $r;
    }
}

// Active products for the line-item form (cascading dropdowns load the rest
// via /quote-builder/api/product-data.php).
$prodSt = db()->prepare(
    'SELECT id, name FROM products
      WHERE client_id = ? AND active = 1
   ORDER BY sort_order, name'
);
$prodSt->execute([$clientId]);
$products = $prodSt->fetchAll();

// Postcode lookup feature flag — gates the "Find by postcode" widget.
$pcFlag = db()->prepare(
    'SELECT COALESCE(feature_postcode_lookup, 0) FROM client_settings WHERE client_id = ?'
);
$pcFlag->execute([$clientId]);
$postcodeLookupEnabled = (int) $pcFlag->fetchColumn() === 1;

// Customers dropdown for the customer-details form. We build display
// labels keyed by id so the typeahead can echo the linked customer back
// into the search box on render.
$custSt = db()->prepare(
    'SELECT id, name, town, postcode FROM customers WHERE client_id = ? ORDER BY name LIMIT 500'
);
$custSt->execute([$clientId]);
$customers = $custSt->fetchAll();

$customerLabels = [];
foreach ($customers as $c) {
    $bits = array_filter([
        (string) $c['name'],
        (string) ($c['town'] ?? ''),
        (string) ($c['postcode'] ?? ''),
    ], static fn ($s) => $s !== '');
    $customerLabels[(int) $c['id']] = implode(' — ', $bits);
}
$selectedCustomerLabel = $customerLabels[(int) ($quote['customer_id'] ?? 0)] ?? '';

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeNav = 'new-quote';
$transitions = qb_allowed_transitions((string) $quote['status']);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote <?= e((string) $quote['quote_number']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .status-pill {
            display: inline-block; padding: 0.125rem 0.625rem; font-size: 0.75rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
            border-radius: 999px; vertical-align: middle; margin-left: 0.5rem;
        }
        .status-draft     { background: #e5e7eb; color: #374151; }
        .status-sent      { background: #dbeafe; color: #1e40af; }
        .status-accepted  { background: #d1fae5; color: #065f46; }
        .status-declined  { background: #fee2e2; color: #991b1b; }
        .status-ordered   { background: #ede9fe; color: #5b21b6; }
        .status-invoiced  { background: #fef3c7; color: #92400e; }
        .status-paid      { background: #14532d; color: #ffffff; }
        .item-desc { font-size: 0.875rem; color: #374151; line-height: 1.45; }
        .item-desc strong { color: #111827; font-weight: 600; }
        .item-extras { color: #6b7280; font-size: 0.8125rem; margin-top: 0.25rem; }
        .totals-row td { font-weight: 600; }
        .totals-row.grand td { font-size: 1.0625rem; color: #111827; }
        #item-preview {
            padding: 0.75rem 1rem; border-radius: 8px;
            font-size: 0.9375rem; margin: 0.75rem 0;
        }
        #item-preview.idle    { background: #f3f4f6; color: #4b5563; }
        #item-preview.error   { background: #fee2e2; color: #991b1b; }
        #item-preview.success { background: #d1fae5; color: #065f46; }
        .extras-grid {
            display: grid; gap: 0.75rem 1rem;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            margin: 0.5rem 0;
        }
        .extras-grid label {
            display: block; font-size: 0.8125rem; color: #4b5563;
            font-weight: 600; margin-bottom: 0.25rem;
        }
        .form-group input[type="number"], .form-group input[type="text"] {
            width: 100%; font: inherit; padding: 0.5625rem 0.75rem;
            border: 1px solid #d1d5db; border-radius: 8px; background: #fff;
            box-sizing: border-box;
        }
        .fabric-picker { position: relative; }
        .fabric-results {
            position: absolute; top: 100%; left: 0; right: 0;
            max-height: 360px; overflow-y: auto;
            background: #fff; border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            z-index: 20; margin-top: 4px;
        }
        .fabric-results .frow {
            padding: 0.5rem 0.75rem; cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
        }
        .fabric-results .frow:last-child { border-bottom: 0; }
        .fabric-results .frow:hover,
        .fabric-results .frow.active { background: #eff6ff; }
        .fabric-results .fname { font-weight: 600; color: #111827; font-size: 0.9375rem; }
        .fabric-results .fmeta { color: #6b7280; font-size: 0.8125rem; margin-top: 0.125rem; }
        .fabric-results .fband {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 700; color: #fff; background: #1f3b5b;
            border-radius: 999px; margin-right: 0.375rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .fabric-results .empty {
            padding: 1rem; text-align: center;
            color: #6b7280; font-size: 0.875rem;
        }
        .read-only-banner {
            background: #fef3c7; color: #92400e; padding: 0.75rem 1rem;
            border-radius: 8px; margin-bottom: 1rem; font-size: 0.9375rem;
        }
        .status-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem; }
        .status-actions button, .status-actions form {
            margin: 0;
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
                    Quote <?= e((string) $quote['quote_number']) ?>
                    <span class="status-pill status-<?= e((string) $quote['status']) ?>">
                        <?= e((string) $quote['status']) ?>
                    </span>
                </h1>
                <p class="page-subtitle">
                    <a href="/quote-history/index.php">&larr; Quote history</a>
                </p>
            </div>
            <div style="text-align:right">
                <strong style="font-size:1.125rem;color:#111827">
                    Total <?= e(qb_fmt_money($quote['total'])) ?>
                </strong>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <?php if (!$editable): ?>
            <div class="read-only-banner">
                This quote is in <strong><?= e((string) $quote['status']) ?></strong> state and is read-only.
                <?php if (in_array('draft', $transitions, true)): ?>
                    Use <strong>Reopen as draft</strong> below to edit it.
                <?php endif; ?>
            </div>
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
                        <label for="customer_search">Linked customer</label>
                        <input type="text" id="customer_search" list="customer-options"
                               value="<?= e($selectedCustomerLabel) ?>"
                               placeholder="Type to search by name, town, or postcode..."
                               <?= !$editable ? 'readonly' : '' ?>>
                        <input type="hidden" id="customer_id" name="customer_id"
                               value="<?= (int) ($quote['customer_id'] ?? 0) ?>">
                        <datalist id="customer-options">
                            <?php foreach ($customerLabels as $cid => $label): ?>
                                <option value="<?= e($label) ?>" data-id="<?= (int) $cid ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <?php if ($editable): ?>
                            <small style="color:#6b7280;font-size:0.8125rem">
                                Type to filter — leave blank to unlink.
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="end_customer_name">Customer name <span class="required">*</span></label>
                        <input id="end_customer_name" name="end_customer_name" type="text"
                               required maxlength="150" <?= !$editable ? 'readonly' : '' ?>
                               value="<?= e((string) $quote['end_customer_name']) ?>">
                    </div>
                </div>

                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="end_customer_email">Email</label>
                        <input id="end_customer_email" name="end_customer_email" type="email" maxlength="150"
                               <?= !$editable ? 'readonly' : '' ?>
                               value="<?= e((string) ($quote['end_customer_email'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_customer_phone">Phone</label>
                        <input id="end_customer_phone" name="end_customer_phone" type="tel" maxlength="50"
                               <?= !$editable ? 'readonly' : '' ?>
                               value="<?= e((string) ($quote['end_customer_phone'] ?? '')) ?>">
                    </div>
                </div>

                <?php if ($postcodeLookupEnabled && $editable): ?>
                    <?php
                        $pcFieldMap = [
                            'line1'    => 'end_customer_address1',
                            'line2'    => 'end_customer_address2',
                            'town'     => 'end_customer_town',
                            'county'   => 'end_customer_county',
                            'postcode' => 'end_customer_postcode',
                        ];
                        require __DIR__ . '/../_partials/postcode_lookup.php';
                    ?>
                <?php endif; ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="end_customer_address1">Address line 1</label>
                        <input id="end_customer_address1" name="end_customer_address1" type="text" maxlength="150"
                               <?= !$editable ? 'readonly' : '' ?>
                               value="<?= e((string) ($quote['end_customer_address1'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="end_customer_address2">Address line 2</label>
                        <input id="end_customer_address2" name="end_customer_address2" type="text" maxlength="150"
                               <?= !$editable ? 'readonly' : '' ?>
                               value="<?= e((string) ($quote['end_customer_address2'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="end_customer_town">Town</label>
                        <input id="end_customer_town" name="end_customer_town" type="text" maxlength="100"
                               <?= !$editable ? 'readonly' : '' ?>
                               value="<?= e((string) ($quote['end_customer_town'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_customer_county">County</label>
                        <input id="end_customer_county" name="end_customer_county" type="text" maxlength="100"
                               <?= !$editable ? 'readonly' : '' ?>
                               value="<?= e((string) ($quote['end_customer_county'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_customer_postcode">Postcode</label>
                        <input id="end_customer_postcode" name="end_customer_postcode" type="text" maxlength="20"
                               <?= !$editable ? 'readonly' : '' ?>
                               value="<?= e((string) ($quote['end_customer_postcode'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="notes">Quote notes</label>
                        <textarea id="notes" name="notes" rows="3" <?= !$editable ? 'readonly' : '' ?>><?= e((string) ($quote['notes'] ?? '')) ?></textarea>
                    </div>
                </div>

                <?php if ($editable): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save details</button>
                    </div>
                <?php endif; ?>
            </form>
        </section>

        <!-- ============== LINE ITEMS ============== -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Line items (<?= count($items) ?>)</h2>
            </div>

            <?php if (empty($items)): ?>
                <div class="placeholder">
                    <p class="placeholder-title">No line items yet</p>
                    <p class="placeholder-body">
                        <?php if ($editable): ?>
                            Add one below.
                        <?php else: ?>
                            This quote has no items.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:3rem">#</th>
                                <th>Description</th>
                                <th>Size</th>
                                <th class="num">Qty</th>
                                <th class="num">Unit</th>
                                <th class="num">Total</th>
                                <?php if ($editable): ?><th></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td><?= (int) $it['line_no'] ?></td>
                                    <td>
                                        <?php if (!empty($it['room_name'])): ?>
                                            <strong><?= e((string) $it['room_name']) ?></strong><br>
                                        <?php endif; ?>
                                        <span class="item-desc">
                                            <strong><?= e((string) $it['product_name_snapshot']) ?></strong><?php if (!empty($it['system_name_snapshot'])): ?> — <?= e((string) $it['system_name_snapshot']) ?><?php endif; ?><br>
                                            Band <?= e((string) ($it['fabric_band_snapshot'] ?? '?')) ?>
                                            <?php if (!empty($it['fabric_supplier_snapshot'])): ?> — <?= e((string) $it['fabric_supplier_snapshot']) ?><?php endif; ?>
                                            — <?= e((string) $it['fabric_name_snapshot']) ?><?php if (!empty($it['fabric_colour_snapshot'])): ?> / <?= e((string) $it['fabric_colour_snapshot']) ?><?php endif; ?>
                                        </span>
                                        <?php if (!empty($extrasByItem[(int) $it['id']])): ?>
                                            <div class="item-extras">
                                                <?php foreach ($extrasByItem[(int) $it['id']] as $ex): ?>
                                                    + <?= e((string) $ex['extra_name_snapshot']) ?>: <?= e((string) $ex['choice_label_snapshot']) ?>
                                                    <?php if ((float) $ex['amount_applied'] != 0): ?>
                                                        (<?= e(qb_fmt_money($ex['amount_applied'])) ?>)
                                                    <?php endif; ?>
                                                    <br>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($it['notes'])): ?>
                                            <div class="item-extras"><em><?= e((string) $it['notes']) ?></em></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= qb_fmt_mm((int) $it['width_mm']) ?> ×
                                        <?= qb_fmt_mm((int) $it['drop_mm']) ?>
                                        <?php if (!empty($it['width_matrix_mm'])
                                                 && ((int) $it['width_matrix_mm'] !== (int) $it['width_mm']
                                                  || (int) $it['drop_matrix_mm']  !== (int) $it['drop_mm'])): ?>
                                            <br><small style="color:#6b7280">cell: <?= qb_fmt_mm((int) $it['width_matrix_mm']) ?> × <?= qb_fmt_mm((int) $it['drop_matrix_mm']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num"><?= (int) $it['quantity'] ?></td>
                                    <td class="num"><?= e(qb_fmt_money($it['sell_price'])) ?></td>
                                    <td class="num"><?= e(qb_fmt_money($it['line_total'])) ?></td>
                                    <?php if ($editable): ?>
                                        <td>
                                            <form method="post" action="/quote-builder/delete_item.php" style="margin:0"
                                                  onsubmit="return confirm('Remove this line?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                                                <input type="hidden" name="item_id"  value="<?= (int) $it['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" aria-label="Delete line">&times;</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="totals-row">
                                <td colspan="<?= $editable ? 5 : 4 ?>" style="text-align:right">Subtotal</td>
                                <td class="num"><?= e(qb_fmt_money($quote['subtotal'])) ?></td>
                                <?php if ($editable): ?><td></td><?php endif; ?>
                            </tr>
                            <tr class="totals-row">
                                <td colspan="<?= $editable ? 5 : 4 ?>" style="text-align:right">VAT (<?= number_format((float) $quote['vat_percent'], 2) ?>%)</td>
                                <td class="num"><?= e(qb_fmt_money($quote['vat'])) ?></td>
                                <?php if ($editable): ?><td></td><?php endif; ?>
                            </tr>
                            <tr class="totals-row grand">
                                <td colspan="<?= $editable ? 5 : 4 ?>" style="text-align:right">Total</td>
                                <td class="num"><?= e(qb_fmt_money($quote['total'])) ?></td>
                                <?php if ($editable): ?><td></td><?php endif; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($editable): ?>
        <!-- ============== ADD LINE ITEM ============== -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Add line item</h2>
            </div>

            <noscript>
                <div class="alert alert-info">
                    Adding line items requires JavaScript for cascading dropdowns and live pricing.
                </div>
            </noscript>

            <form method="post" action="/quote-builder/add_item.php" class="form" id="add-item-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                <input type="hidden" name="round_up" value="1">

                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="item-product">Product <span class="required">*</span></label>
                        <select id="item-product" name="product_id" required>
                            <option value="">Choose product...</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= e((string) $p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="item-room">Room name</label>
                        <input id="item-room" name="room_name" type="text" maxlength="80"
                               placeholder="e.g. Living Room">
                    </div>
                </div>

                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="item-system">System</label>
                        <select id="item-system" name="system_id" disabled>
                            <option value="">Choose product first</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="item-fabric-search">Fabric <span class="required">*</span></label>
                        <div class="fabric-picker">
                            <input type="text" id="item-fabric-search"
                                   placeholder="Choose product first"
                                   autocomplete="off" disabled>
                            <input type="hidden" id="item-fabric" name="option_id" required>
                            <div id="item-fabric-results" class="fabric-results" hidden></div>
                        </div>
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="item-width">Width <span class="required">*</span></label>
                        <input id="item-width" name="width" type="text" required
                               placeholder="e.g. 1500, 150cm, 1.5m, 60in">
                    </div>
                    <div class="form-group">
                        <label for="item-drop">Drop <span class="required">*</span></label>
                        <input id="item-drop" name="drop" type="text" required
                               placeholder="e.g. 1800, 180cm, 1.8m, 72in">
                    </div>
                    <div class="form-group">
                        <label for="item-qty">Quantity</label>
                        <input id="item-qty" name="quantity" type="number" step="1" min="1" value="1">
                    </div>
                </div>

                <div id="item-extras-wrap" style="display:none">
                    <div class="section-header" style="margin-top:0.5rem">
                        <h3 class="section-title" style="font-size:1rem">Extras</h3>
                    </div>
                    <div id="item-extras" class="extras-grid"></div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="item-notes">Line notes</label>
                        <input id="item-notes" name="notes" type="text" maxlength="255"
                               placeholder="Optional internal note for this line">
                    </div>
                </div>

                <div id="item-preview" class="idle">
                    Pick a product, fabric and dimensions to see the price.
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="item-submit" disabled>Add line</button>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <!-- ============== STATUS + DANGER ZONE ============== -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Quote actions</h2>
            </div>
            <div class="status-actions">
                <a href="/pdf-generator/quote_pdf.php?id=<?= (int) $quote['id'] ?>"
                   class="btn btn-secondary" target="_blank" rel="noopener">
                    View PDF
                </a>
                <a href="/pdf-generator/quote_pdf.php?id=<?= (int) $quote['id'] ?>&download=1"
                   class="btn btn-secondary">
                    Download PDF
                </a>
                <?php foreach ($transitions as $t): ?>
                    <form method="post" action="/quote-builder/change_status.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                        <input type="hidden" name="target_status" value="<?= e($t) ?>">
                        <button type="submit" class="btn btn-secondary">
                            <?= $t === 'draft' ? 'Reopen as draft' : 'Mark as ' . e($t) ?>
                        </button>
                    </form>
                <?php endforeach; ?>
                <form method="post" action="/quote-builder/delete.php"
                      onsubmit="return confirm('Delete quote <?= e(addslashes((string) $quote['quote_number'])) ?>? This is permanent — all line items go too.');"
                      style="margin-left:auto">
                    <?= csrf_field() ?>
                    <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                    <button type="submit" class="btn btn-danger">Delete quote</button>
                </form>
            </div>
        </section>
    </main>
</div>

<?php if ($editable): ?>
<script>
(function () {
    'use strict';

    var form          = document.getElementById('add-item-form');
    if (!form) return;
    var productSel    = document.getElementById('item-product');
    var systemSel     = document.getElementById('item-system');
    var fabricSearch  = document.getElementById('item-fabric-search');
    var fabricId      = document.getElementById('item-fabric');
    var fabricResults = document.getElementById('item-fabric-results');
    var widthIn       = document.getElementById('item-width');
    var dropIn        = document.getElementById('item-drop');
    var qtyIn         = document.getElementById('item-qty');
    var extrasWrap    = document.getElementById('item-extras-wrap');
    var extrasBox     = document.getElementById('item-extras');
    var previewBox    = document.getElementById('item-preview');
    var submitBtn     = document.getElementById('item-submit');

    var productData    = null;  // cached response from /api/product-data
    var previewTimer   = null;
    var fabricSearchTimer = null;

    function setIdle(el, msg) {
        el.innerHTML = '<option value="">' + msg + '</option>';
        el.disabled  = true;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
        });
    }

    async function loadProductData() {
        productData = null;
        clearFabric();
        closeFabricResults();
        if (!productSel.value) {
            setIdle(systemSel, 'Choose product first');
            fabricSearch.disabled = true;
            fabricSearch.placeholder = 'Choose product first';
            extrasWrap.style.display = 'none';
            extrasBox.innerHTML = '';
            schedulePreview();
            return;
        }
        try {
            setIdle(systemSel, 'Loading...');
            fabricSearch.disabled = true;
            fabricSearch.placeholder = 'Loading...';
            extrasBox.innerHTML = '';
            extrasWrap.style.display = 'none';

            var r = await fetch('/quote-builder/api/product-data.php?product_id='
                                + encodeURIComponent(productSel.value),
                                { credentials: 'same-origin' });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            productData = await r.json();
            if (productData.error) throw new Error(productData.error);

            // Systems dropdown.
            if (productData.systems.length === 0) {
                setIdle(systemSel, '— No systems —');
                systemSel.value = '';
            } else {
                var sysOpts = '<option value="">— Choose system —</option>';
                productData.systems.forEach(function (s) {
                    sysOpts += '<option value="' + s.id + '"'
                            + (s.is_default ? ' selected' : '') + '>'
                            + escapeHtml(s.name) + '</option>';
                });
                systemSel.innerHTML = sysOpts;
                systemSel.disabled  = false;
            }

            // Fabric typeahead — enable input. Picking happens via the
            // floating results panel populated from /api/fabrics-search.
            fabricSearch.disabled    = false;
            fabricSearch.placeholder = 'Type to search fabrics (or click for recent)';

            renderExtras();
        } catch (err) {
            setIdle(systemSel, 'Failed to load');
            fabricSearch.placeholder = 'Failed to load';
            console.error(err);
        }
        schedulePreview();
    }

    // -----------------------------------------------------------------------
    // Fabric typeahead
    // -----------------------------------------------------------------------
    async function searchFabrics(query) {
        if (!productSel.value) return;
        try {
            var r = await fetch('/quote-builder/api/fabrics-search.php'
                + '?product_id=' + encodeURIComponent(productSel.value)
                + '&q='          + encodeURIComponent(query || ''),
                { credentials: 'same-origin' });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            var data = await r.json();
            renderFabricResults(data.fabrics || []);
        } catch (err) {
            fabricResults.innerHTML = '<div class="empty">Could not search fabrics.</div>';
            fabricResults.hidden = false;
            console.error(err);
        }
    }

    function renderFabricResults(items) {
        if (!items.length) {
            fabricResults.innerHTML = '<div class="empty">No matching fabrics.</div>';
            fabricResults.hidden = false;
            return;
        }
        var html = '';
        items.forEach(function (f) {
            var meta = [];
            if (f.supplier) meta.push(escapeHtml(f.supplier));
            if (f.code)     meta.push('Code ' + escapeHtml(f.code));
            html += '<div class="frow" data-id="' + f.id + '" data-label="' + escapeAttr(f.label) + '">'
                  +    '<div class="fname">'
                  +      '<span class="fband">Band ' + escapeHtml(f.band) + '</span>'
                  +      escapeHtml(f.name) + (f.colour ? ' / ' + escapeHtml(f.colour) : '')
                  +    '</div>'
                  + (meta.length ? '<div class="fmeta">' + meta.join(' · ') + '</div>' : '')
                  + '</div>';
        });
        fabricResults.innerHTML = html;
        fabricResults.hidden = false;

        // Bind click handlers on each row.
        fabricResults.querySelectorAll('.frow').forEach(function (row) {
            row.addEventListener('mousedown', function (e) {
                // Use mousedown so it fires before the input's blur event.
                e.preventDefault();
                pickFabric(row.dataset.id, row.dataset.label);
            });
        });
    }

    function pickFabric(id, label) {
        fabricId.value = String(id);
        fabricSearch.value = label;
        closeFabricResults();
        schedulePreview();
    }

    function clearFabric() {
        fabricId.value = '';
        fabricSearch.value = '';
    }

    function closeFabricResults() {
        fabricResults.hidden = true;
        fabricResults.innerHTML = '';
    }

    function scheduleFabricSearch() {
        clearTimeout(fabricSearchTimer);
        fabricSearchTimer = setTimeout(function () {
            searchFabrics(fabricSearch.value.trim());
        }, 200);
    }

    function escapeAttr(s) { return escapeHtml(s); }

    function renderExtras() {
        if (!productData || !productData.extras || productData.extras.length === 0) {
            extrasWrap.style.display = 'none';
            extrasBox.innerHTML = '';
            return;
        }
        var systemId = parseInt(systemSel.value, 10) || 0;
        var html = '';
        var anyVisible = false;

        productData.extras.forEach(function (extra, idx) {
            // Conditional extras: hidden until parent choice is selected.
            if (extra.parent_choice_id) {
                var parentSelected = false;
                productData.extras.forEach(function (other, otherIdx) {
                    if (otherIdx === idx) return;
                    var sel = document.querySelector(
                        '[data-extra-id="' + other.id + '"] select'
                    );
                    if (sel && parseInt(sel.value, 10) === extra.parent_choice_id) {
                        parentSelected = true;
                    }
                });
                if (!parentSelected) return;
            }

            // Filter choices by system_id (system-locked choices show only for that system).
            var visibleChoices = extra.choices.filter(function (c) {
                return c.system_id === null || c.system_id === systemId;
            });
            if (visibleChoices.length === 0) return;

            anyVisible = true;
            var hasDefault = visibleChoices.some(function (c) { return c.is_default; });
            html += '<div data-extra-id="' + extra.id + '">';
            html += '<label>' + escapeHtml(extra.name)
                  + (extra.is_required ? ' <span style="color:#b91c1c">*</span>' : '')
                  + '</label>';
            html += '<input type="hidden" name="extras[' + idx + '][extra_id]" value="' + extra.id + '">';
            html += '<select name="extras[' + idx + '][choice_id]"'
                  + (extra.is_required ? ' required' : '') + '>';
            if (!extra.is_required || !hasDefault) {
                html += '<option value="">— None —</option>';
            }
            visibleChoices.forEach(function (c) {
                html += '<option value="' + c.id + '"'
                      + (c.is_default ? ' selected' : '') + '>' + escapeHtml(c.label) + '</option>';
            });
            html += '</select></div>';
        });

        extrasBox.innerHTML  = html;
        extrasWrap.style.display = anyVisible ? '' : 'none';

        // Re-bind change listeners on the choice selects so conditional
        // extras can re-render when their parent's value changes.
        extrasBox.querySelectorAll('select').forEach(function (sel) {
            sel.addEventListener('change', function () {
                renderExtras();
                schedulePreview();
            });
        });
    }

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(runPreview, 250);
    }

    function collectExtras() {
        var out = [];
        var divs = extrasBox.querySelectorAll('[data-extra-id]');
        divs.forEach(function (div) {
            var sel = div.querySelector('select');
            var eid = parseInt(div.getAttribute('data-extra-id'), 10);
            var cid = parseInt(sel.value, 10);
            if (eid > 0 && cid > 0) {
                out.push({ extra_id: eid, choice_id: cid });
            }
        });
        return out;
    }

    async function runPreview() {
        var ok = productSel.value && fabricId.value
              && widthIn.value.trim() && dropIn.value.trim();
        if (!ok) {
            previewBox.className   = 'idle';
            previewBox.textContent = 'Pick a product, fabric and dimensions to see the price.';
            submitBtn.disabled = true;
            return;
        }

        var params = new URLSearchParams({
            product_id: productSel.value,
            system_id:  systemSel.value || '0',
            option_id:  fabricId.value,
            width:      widthIn.value,
            drop:       dropIn.value,
            quantity:   qtyIn.value || '1',
            round_up:   '1'
        });
        collectExtras().forEach(function (ex, i) {
            params.append('extras[' + i + '][extra_id]',  ex.extra_id);
            params.append('extras[' + i + '][choice_id]', ex.choice_id);
        });

        try {
            var r = await fetch('/quote-builder/api/preview.php?' + params,
                                { credentials: 'same-origin' });
            var data = await r.json();
            if (data.error) {
                previewBox.className   = 'error';
                previewBox.textContent = data.error;
                submitBtn.disabled = true;
                return;
            }
            var unit  = Number(data.sell_price).toFixed(2);
            var total = Number(data.line_total).toFixed(2);
            var qty   = Number(data.quantity);
            var bits = ['<strong>£' + unit + '</strong> per blind'];
            if (qty > 1) bits.push('× ' + qty + ' = <strong>£' + total + '</strong>');
            bits.push('base £' + Number(data.base_price).toFixed(2));
            if (data.extras_total > 0) bits.push('+ extras £' + Number(data.extras_total).toFixed(2));
            if (data.markup_percent > 0)   bits.push('markup ' + Number(data.markup_percent).toFixed(2) + '%');
            if (data.discount_percent > 0) bits.push('discount ' + Number(data.discount_percent).toFixed(2) + '%');
            if (data.rounded_up) bits.push('rounded up to ' + data.matrix_width_mm + ' × ' + data.matrix_drop_mm + ' mm');
            previewBox.className = 'success';
            previewBox.innerHTML = bits.join(' &middot; ');
            submitBtn.disabled = false;
        } catch (err) {
            previewBox.className   = 'error';
            previewBox.textContent = 'Could not fetch live price.';
            submitBtn.disabled = true;
            console.error(err);
        }
    }

    productSel.addEventListener('change', loadProductData);
    systemSel.addEventListener('change', function () { renderExtras(); schedulePreview(); });
    qtyIn.addEventListener('change', schedulePreview);
    [widthIn, dropIn].forEach(function (el) {
        el.addEventListener('input', schedulePreview);
    });

    // Fabric typeahead listeners.
    fabricSearch.addEventListener('focus', function () {
        // On focus, kick off a query (empty = first 50 alphabetical) so the
        // user gets something to browse before typing.
        if (productSel.value) searchFabrics(fabricSearch.value.trim());
    });
    fabricSearch.addEventListener('input', function () {
        // Typing invalidates the previous picked id — they're searching anew.
        fabricId.value = '';
        scheduleFabricSearch();
        schedulePreview();
    });
    fabricSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeFabricResults();
    });
    fabricSearch.addEventListener('blur', function () {
        // Slight delay so a click on a result row registers before close.
        setTimeout(closeFabricResults, 150);
    });
})();
</script>
<?php endif; ?>

<script>
(function () {
    // Customer typeahead — keep the hidden customer_id in sync with the
    // visible search box. Runs regardless of editable mode (readonly input
    // means there's just nothing to react to).
    var search   = document.getElementById('customer_search');
    var hidden   = document.getElementById('customer_id');
    var dataList = document.getElementById('customer-options');
    if (!search || !hidden || !dataList) return;

    function syncId() {
        var typed = search.value.trim();
        var matched = 0;
        for (var i = 0; i < dataList.options.length; i++) {
            if (dataList.options[i].value === typed) {
                matched = parseInt(dataList.options[i].dataset.id, 10) || 0;
                break;
            }
        }
        hidden.value = matched;
    }
    search.addEventListener('input',  syncId);
    search.addEventListener('change', syncId);
})();
</script>
</body>
</html>
