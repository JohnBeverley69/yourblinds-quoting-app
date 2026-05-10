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

// Edit-blind mode: ?edit_item=N pre-populates the Add-blind form with this
// item's values and switches its submit handler to update_item.php so saving
// updates the row in place rather than creating a new one. Falls back to
// "add" mode if the item doesn't belong to the quote.
$editingItemId    = $editable ? (int) ($_GET['edit_item'] ?? 0) : 0;
$editingItem      = null;
$editingExtras    = [];
if ($editingItemId > 0) {
    $editSt = db()->prepare(
        'SELECT * FROM quote_items WHERE id = ? AND quote_id = ? LIMIT 1'
    );
    $editSt->execute([$editingItemId, $id]);
    $editingItem = $editSt->fetch();
    if (!$editingItem) {
        $editingItemId = 0;
    } else {
        $exSt = db()->prepare(
            'SELECT product_extra_id, product_extra_choice_id
               FROM quote_item_extras WHERE quote_item_id = ? ORDER BY id'
        );
        $exSt->execute([$editingItemId]);
        foreach ($exSt->fetchAll() as $r) {
            $editingExtras[] = [
                'extra_id'  => (int) $r['product_extra_id'],
                'choice_id' => (int) $r['product_extra_choice_id'],
            ];
        }
    }
}

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
// labels keyed by id + a per-customer data bundle so the typeahead can
// (a) echo the linked customer back into the search box on render, and
// (b) populate the address fields client-side when the user picks
// someone different.
$custSt = db()->prepare(
    'SELECT id, name, email, phone, has_whatsapp,
            address1, address2, town, county, postcode
       FROM customers WHERE client_id = ? ORDER BY name LIMIT 500'
);
$custSt->execute([$clientId]);
$customers = $custSt->fetchAll();

$customerLabels = [];
$customerData   = [];
foreach ($customers as $c) {
    $cid  = (int) $c['id'];
    $bits = array_filter([
        (string) $c['name'],
        (string) ($c['town'] ?? ''),
        (string) ($c['postcode'] ?? ''),
    ], static fn ($s) => $s !== '');
    $customerLabels[$cid] = implode(' — ', $bits);
    $customerData[$cid]   = [
        'name'         => (string) $c['name'],
        'email'        => (string) ($c['email']    ?? ''),
        'phone'        => (string) ($c['phone']    ?? ''),
        'has_whatsapp' => !empty($c['has_whatsapp']) ? '1' : '',
        'address1'     => (string) ($c['address1'] ?? ''),
        'address2'     => (string) ($c['address2'] ?? ''),
        'town'         => (string) ($c['town']     ?? ''),
        'county'       => (string) ($c['county']   ?? ''),
        'postcode'     => (string) ($c['postcode'] ?? ''),
    ];
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
        .extras-grid .choice-thumb {
            display: block; max-width: 160px; max-height: 90px;
            margin-top: 0.5rem; padding: 0.25rem;
            background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;
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

        /* ===========================================================
           Sticky quote bar — always-visible quote-number + status +
           total. Slim so it doesn't eat much screen real estate; the
           page-header below it carries the rest of the metadata.
           =========================================================== */
        .quote-sticky-bar {
            position: sticky; top: 0; z-index: 40;
            display: flex; justify-content: space-between; align-items: center;
            gap: 1rem; flex-wrap: wrap;
            padding: 0.625rem 1rem; margin: -1rem -1rem 1rem;
            background: #1f3b5b; color: #fff;
            font-size: 0.9375rem; font-weight: 600;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .quote-sticky-bar .qsb-left { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
        .quote-sticky-bar .qsb-total { font-size: 1.0625rem; }
        .quote-sticky-bar .status-pill { margin: 0; }

        /* ===========================================================
           Customer details — collapsible section. Once the customer
           is filled in, the section collapses to a one-line summary
           ("Customer: Name — Town — Postcode") so it doesn't dominate
           the top of the page on every revisit. Click the summary
           to expand and edit.
           =========================================================== */
        .customer-collapse > summary {
            list-style: none; cursor: pointer; padding: 0.25rem 0;
            font-size: 1.125rem; font-weight: 600; color: #111827;
            display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
        }
        .customer-collapse > summary::-webkit-details-marker { display: none; }
        .customer-collapse > summary::before {
            content: '▸'; display: inline-block; color: #6b7280;
            transition: transform 150ms; flex-shrink: 0;
        }
        .customer-collapse[open] > summary::before { transform: rotate(90deg); }
        .customer-collapse > summary .cs-meta {
            font-size: 0.875rem; font-weight: 400; color: #6b7280;
        }
        .customer-collapse > summary .cs-hint {
            font-size: 0.8125rem; font-weight: 400; color: #9ca3af;
            font-style: italic;
        }
        .customer-collapse > .form { margin-top: 0.75rem; }

        /* ===========================================================
           Two-column layout: customer details + Add Blind form on the
           LEFT (the input side — what the trade user is actively
           typing into), and the Blinds list on the RIGHT (the output
           side — what they've added so far). Left column is sticky so
           it stays in view when the right-side list grows long.
           Stacks back to one column under 1000px since the left side
           now needs more horizontal room for the Add Blind form rows.
           =========================================================== */
        .quote-cols {
            display: grid;
            grid-template-columns: minmax(360px, 42%) 1fr;
            gap: 1.25rem;
            align-items: start;
        }
        .quote-cols .col-left {
            position: sticky;
            top: 3.25rem;          /* clears the sticky quote bar */
            max-height: calc(100vh - 4.5rem);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .quote-cols .col-left > .section { margin-bottom: 0; }
        .quote-cols .col-right > .section { margin-bottom: 1rem; }

        @media (max-width: 1000px) {
            .quote-cols { grid-template-columns: 1fr; gap: 0; }
            .quote-cols .col-left {
                position: static;
                max-height: none; overflow-y: visible;
                gap: 0;
            }
            .quote-cols .col-left > .section { margin-bottom: 1rem; }
        }

        /* ===========================================================
           Mobile tweaks — tighter padding on the Add-blind form so
           it doesn't feel cramped on a phone, and the items table
           stays horizontally scrollable inside its .table-wrap.
           =========================================================== */
        @media (max-width: 700px) {
            .quote-sticky-bar { padding: 0.5rem 0.75rem; font-size: 0.875rem; }
            .quote-sticky-bar .qsb-total { font-size: 1rem; }
            .extras-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <!-- Slim sticky bar — quote #, status, total — always visible
             as the user scrolls through customer details / add-blind /
             items / send. Replaces the "Total only at top" complaint. -->
        <div class="quote-sticky-bar">
            <span class="qsb-left">
                Quote <?= e((string) $quote['quote_number']) ?>
                <span class="status-pill status-<?= e((string) $quote['status']) ?>">
                    <?= e((string) $quote['status']) ?>
                </span>
            </span>
            <span class="qsb-total">
                Total <?= e(qb_fmt_money($quote['total'])) ?>
            </span>
        </div>

        <div class="page-header">
            <div>
                <p class="page-subtitle" style="margin:0">
                    <a href="/quote-history/index.php">&larr; Quote history</a>
                </p>
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

        <div class="quote-cols">
        <div class="col-left">
        <!-- ============== CUSTOMER DETAILS (collapsible) ============== -->
        <?php
            // Build a compact summary of the customer for the collapsed
            // state. Defaults to expanded when any required bit is
            // missing (so the user sees the form on a brand-new quote).
            $csName     = trim((string) ($quote['end_customer_name'] ?? ''));
            $csTown     = trim((string) ($quote['end_customer_town'] ?? ''));
            $csPostcode = trim((string) ($quote['end_customer_postcode'] ?? ''));
            $hasCustomer = $csName !== '';
            $startOpen   = !$hasCustomer;
        ?>
        <section class="section">
            <form method="post" action="/quote-builder/save_details.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">

            <details class="customer-collapse"<?= $startOpen ? ' open' : '' ?>>
                <summary>
                    <?php if ($hasCustomer): ?>
                        Customer:
                        <?= e($csName) ?>
                        <span class="cs-meta">
                            <?php if ($csTown !== ''):     ?> — <?= e($csTown) ?><?php endif; ?>
                            <?php if ($csPostcode !== ''): ?> — <?= e($csPostcode) ?><?php endif; ?>
                        </span>
                        <span class="cs-hint">(click to edit)</span>
                    <?php else: ?>
                        Customer details
                        <span class="cs-hint">— click to add the customer's contact info</span>
                    <?php endif; ?>
                </summary>

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
                            <?php foreach ($customerLabels as $cid => $label): $d = $customerData[$cid]; ?>
                                <option value="<?= e($label) ?>"
                                        data-id="<?= (int) $cid ?>"
                                        data-name="<?= e($d['name']) ?>"
                                        data-email="<?= e($d['email']) ?>"
                                        data-phone="<?= e($d['phone']) ?>"
                                        data-has_whatsapp="<?= e($d['has_whatsapp']) ?>"
                                        data-address1="<?= e($d['address1']) ?>"
                                        data-address2="<?= e($d['address2']) ?>"
                                        data-town="<?= e($d['town']) ?>"
                                        data-county="<?= e($d['county']) ?>"
                                        data-postcode="<?= e($d['postcode']) ?>"></option>
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
                        <label style="display:inline-flex;align-items:center;gap:0.4rem;margin-top:0.5rem;font-weight:400;font-size:0.875rem;color:#4b5563;<?= !$editable ? 'cursor:default' : 'cursor:pointer' ?>">
                            <input type="checkbox" id="end_customer_has_whatsapp" name="has_whatsapp" value="1"
                                   <?= !empty($quote['has_whatsapp']) ? 'checked' : '' ?>
                                   <?= !$editable ? 'disabled' : '' ?>>
                            Customer has WhatsApp on this number
                        </label>
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
            </details>

            <!-- Quote notes lives OUTSIDE <details> so it stays visible
                 even when the customer summary is collapsed. Still inside
                 the form so it saves alongside customer fields. -->
            <div class="form-row full" style="margin-top:1rem">
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

        <?php if ($editable): ?>
        <!-- ============== ADD / EDIT BLIND (left column, directly below
             customer details — keeps input + output side by side) ============== -->
        <section class="section" id="add-line">
            <div class="section-header">
                <h2 class="section-title">
                    <?= $editingItemId > 0 ? 'Edit blind ' . (int) $editingItem['line_no'] : 'Add blind' ?>
                </h2>
                <?php if ($editingItemId > 0): ?>
                    <a href="/quote-builder/edit.php?id=<?= (int) $quote['id'] ?>"
                       style="font-size:0.875rem">Cancel edit</a>
                <?php endif; ?>
            </div>

            <noscript>
                <div class="alert alert-info">
                    Adding blinds requires JavaScript for cascading dropdowns and live pricing.
                </div>
            </noscript>

            <form method="post"
                  action="<?= $editingItemId > 0 ? '/quote-builder/update_item.php' : '/quote-builder/add_item.php' ?>"
                  class="form" id="add-item-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                <input type="hidden" name="round_up" value="1">
                <?php if ($editingItemId > 0): ?>
                    <input type="hidden" name="item_id" value="<?= (int) $editingItemId ?>">
                <?php endif; ?>

                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="item-room">Room name</label>
                        <input id="item-room" name="room_name" type="text" maxlength="80"
                               list="room-options"
                               value="<?= e((string) ($editingItem['room_name'] ?? '')) ?>"
                               placeholder="Type or pick — e.g. Living Room">
                        <datalist id="room-options">
                            <option value="Bathroom">
                            <option value="Bedroom">
                            <option value="Bedroom 2">
                            <option value="Bedroom 3">
                            <option value="Cloakroom">
                            <option value="Conservatory">
                            <option value="Dining Room">
                            <option value="En-suite">
                            <option value="Hallway">
                            <option value="Kitchen">
                            <option value="Kitchen / Diner">
                            <option value="Landing">
                            <option value="Living Room">
                            <option value="Lounge">
                            <option value="Master Bedroom">
                            <option value="Nursery">
                            <option value="Office">
                            <option value="Snug">
                            <option value="Spare Room">
                            <option value="Study">
                            <option value="Utility">
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label for="item-product">Product <span class="required">*</span></label>
                        <select id="item-product" name="product_id" required>
                            <option value="">Choose product...</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"
                                    <?= ($editingItem && (int) $editingItem['product_id'] === (int) $p['id']) ? 'selected' : '' ?>>
                                    <?= e((string) $p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                               value="<?= $editingItem ? (int) $editingItem['width_mm'] : '' ?>"
                               placeholder="e.g. 1500, 150cm, 1.5m, 60in">
                    </div>
                    <div class="form-group">
                        <label for="item-drop">Drop <span class="required">*</span></label>
                        <input id="item-drop" name="drop" type="text" required
                               value="<?= $editingItem ? (int) $editingItem['drop_mm'] : '' ?>"
                               placeholder="e.g. 1800, 180cm, 1.8m, 72in">
                    </div>
                    <div class="form-group">
                        <label for="item-qty">Quantity</label>
                        <input id="item-qty" name="quantity" type="number" step="1" min="1"
                               value="<?= $editingItem ? (int) $editingItem['quantity'] : 1 ?>">
                    </div>
                </div>

                <div id="item-extras-wrap" style="display:none">
                    <div class="section-header" style="margin-top:0.5rem">
                        <h3 class="section-title" style="font-size:1rem">Options</h3>
                    </div>
                    <div id="item-extras" class="extras-grid"></div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="item-notes">Notes</label>
                        <input id="item-notes" name="notes" type="text" maxlength="255"
                               value="<?= e((string) ($editingItem['notes'] ?? '')) ?>"
                               placeholder="Optional internal note for this blind">
                    </div>
                </div>

                <div id="item-preview" class="idle">
                    Pick a product, fabric and dimensions to see the price.
                </div>

                <div class="form-actions">
                    <?php if ($editingItemId > 0): ?>
                        <button type="submit" class="btn btn-primary item-submit" disabled>
                            Save changes
                        </button>
                        <a href="/quote-builder/edit.php?id=<?= (int) $quote['id'] ?>"
                           class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary item-submit"
                                name="next_action" value="stop" disabled>
                            Save
                        </button>
                        <button type="submit" class="btn btn-secondary item-submit"
                                name="next_action" value="more" disabled>
                            Save and add another blind
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </section>
        <?php endif; ?>
        </div><!-- /col-left -->

        <div class="col-right">
        <!-- ============== LINE ITEMS ============== -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Blinds (<?= count($items) ?>)</h2>
            </div>

            <?php if (empty($items)): ?>
                <div class="placeholder">
                    <p class="placeholder-title">No blinds yet</p>
                    <p class="placeholder-body">
                        <?php if ($editable): ?>
                            Add one below.
                        <?php else: ?>
                            This quote has no blinds.
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
                                        <td style="white-space:nowrap">
                                            <a href="/quote-builder/edit.php?id=<?= (int) $quote['id'] ?>&edit_item=<?= (int) $it['id'] ?>#add-line"
                                               class="btn btn-sm btn-secondary" style="margin-right:0.25rem">Edit</a>
                                            <form method="post" action="/quote-builder/duplicate_item.php" style="display:inline;margin:0 0.25rem 0 0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                                                <input type="hidden" name="item_id"  value="<?= (int) $it['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary"
                                                        title="Duplicate this blind — copies fabric, system, options. New row opens in edit mode for you to tweak the size.">
                                                    Dup
                                                </button>
                                            </form>
                                            <form method="post" action="/quote-builder/delete_item.php" style="display:inline;margin:0"
                                                  onsubmit="return confirm('Remove this blind?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                                                <input type="hidden" name="item_id"  value="<?= (int) $it['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" aria-label="Remove blind">&times;</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ((float) $quote['vat_percent'] > 0): ?>
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
                            <?php endif; ?>
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
        </div><!-- /col-right -->
        </div><!-- /quote-cols -->

        <!-- ============== SEND TO CUSTOMER ============== -->
        <?php
            // Build the absolute public-accept URL (used by both the email
            // body via the server-side handler AND the WhatsApp link below).
            // Same APP_URL pattern as forgot_password.php — never derive
            // from $_SERVER['HTTP_HOST'].
            $appUrl     = trim((string) (env('APP_URL', '') ?? ''));
            $publicUrl  = $appUrl !== ''
                ? rtrim($appUrl, '/') . '/quote-history/public.php?token=' . urlencode((string) $quote['public_token'])
                : '/quote-history/public.php?token=' . urlencode((string) $quote['public_token']);

            // Normalise the customer's phone to wa.me's required format:
            // international, digits only, NO leading + or 00. Defaults to
            // UK assumption (07… → 447…), since that's the typical case
            // here. Numbers that already start with a country code or are
            // explicitly +-prefixed pass through fine.
            //
            // Examples:
            //   07123 456789  → 447123456789
            //   +44 7123 ...  → 447123...
            //   0044 7123 ... → 447123...
            //   34 600 ...    → 34600... (Spanish, kept as-is)
            $rawPhone = (string) ($quote['end_customer_phone'] ?? '');
            $digits   = preg_replace('/[^0-9]/', '', $rawPhone);
            if ($digits === '') {
                $waPhone = '';
            } elseif (str_starts_with($digits, '00')) {
                $waPhone = substr($digits, 2);            // strip 00 dial-out
            } elseif (str_starts_with($digits, '0')) {
                $waPhone = '44' . substr($digits, 1);     // UK national → +44
            } else {
                $waPhone = $digits;                        // already international
            }

            // Only enables WhatsApp when the trade user has explicitly ticked
            // "has WhatsApp" on the customer; otherwise a tap of the wa.me
            // link could land on a "user not on WhatsApp" error page.
            $waEnabled = $waPhone !== '' && !empty($quote['has_whatsapp']);

            // WhatsApp message body. Plain text — wa.me will URL-encode it.
            $waMessage = "Hi " . ((string) $quote['end_customer_name'])
                       . ", here's your quote " . (string) $quote['quote_number']
                       . " from " . (string) $user['company_name'] . ":\n"
                       . $publicUrl;
        ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Send to customer</h2>
            </div>
            <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 1rem">
                Email the PDF and a link the customer can click to accept the quote online.
                <?php if ($waEnabled): ?>
                    Or share the same link via WhatsApp.
                <?php elseif ($waPhone === ''): ?>
                    Add a phone number to the customer details above to enable WhatsApp sharing.
                <?php else: ?>
                    Tick "Customer has WhatsApp on this number" above to enable WhatsApp sharing.
                <?php endif; ?>
            </p>

            <form method="post" action="/pdf-generator/email_pdf.php" class="form" novalidate
                  style="margin-bottom:1rem">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $quote['id'] ?>">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="send-to">Recipient email</label>
                        <input id="send-to" name="to" type="email" required maxlength="150"
                               value="<?= e((string) ($quote['end_customer_email'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="send-message">Message (optional)</label>
                        <textarea id="send-message" name="message" rows="3"
                                  placeholder="Optional — anything you want to add above the standard text."></textarea>
                    </div>
                </div>

                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;margin-top:0.5rem">
                    <button type="submit"
                            style="background:rgba(220,38,38,0.5);color:#000;font-size:1rem;font-weight:700;padding:0.75rem 1.5rem;border:0;border-radius:8px;cursor:pointer">
                        📧 Email PDF + accept link
                    </button>
                    <?php if ($waEnabled): ?>
                        <a href="https://wa.me/<?= e($waPhone) ?>?text=<?= e(rawurlencode($waMessage)) ?>"
                           target="_blank" rel="noopener"
                           style="display:inline-block;background:rgba(37,211,102,0.5);color:#000;font-size:1rem;font-weight:700;padding:0.75rem 1.5rem;border-radius:8px;text-decoration:none">
                            💬 Send via WhatsApp
                        </a>
                    <?php endif; ?>
                    <a href="<?= e($publicUrl) ?>"
                       class="btn btn-secondary" target="_blank" rel="noopener"
                       title="Public URL — share manually if needed">
                        🔗 Copy public link
                    </a>
                </div>
                <small style="display:block;color:#6b7280;font-size:0.8125rem;margin-top:0.625rem">
                    Public link: <code style="font-size:0.8125rem"><?= e($publicUrl) ?></code>
                </small>
            </form>
        </section>

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
                      onsubmit="return confirm('Delete quote <?= e(addslashes((string) $quote['quote_number'])) ?>? This is permanent — all blinds go too.');"
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
    var submitBtns    = document.querySelectorAll('#add-item-form .item-submit');
    function setSubmitDisabled(disabled) {
        submitBtns.forEach(function (btn) { btn.disabled = !!disabled; });
    }

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

        // Capture currently-selected values so re-renders preserve user
        // selections instead of reverting to defaults. Distinguishes
        // "user picked None" ("") from "never rendered yet" (undefined).
        var preset = {};
        extrasBox.querySelectorAll('[data-extra-id]').forEach(function (div) {
            var sel = div.querySelector('select');
            if (sel) preset[parseInt(div.getAttribute('data-extra-id'), 10)] = sel.value;
        });

        var systemId = parseInt(systemSel.value, 10) || 0;
        var html = '';
        var anyVisible = false;

        productData.extras.forEach(function (extra, idx) {
            // Conditional extras: hidden until parent choice is selected.
            // Read from `preset` (captured) rather than the live DOM since
            // we're mid-rebuild.
            if (extra.parent_choice_id) {
                var parentSelected = false;
                productData.extras.forEach(function (other, otherIdx) {
                    if (otherIdx === idx) return;
                    var presetVal = preset[other.id];
                    if (presetVal && parseInt(presetVal, 10) === extra.parent_choice_id) {
                        parentSelected = true;
                    }
                });
                if (!parentSelected) return;
            }

            // Filter choices by system. system_id === null means "all
            // systems"; otherwise it must match the chosen system. The
            // option auto-hides when no choice is available — there is
            // no separate option-level scope in this model.
            var visibleChoices = extra.choices.filter(function (c) {
                if (c.system_id === null || c.system_id === undefined) return true;
                return c.system_id === systemId;
            });
            if (visibleChoices.length === 0) return;

            anyVisible = true;
            var presetVal = preset[extra.id];
            var hasDefault = visibleChoices.some(function (c) { return c.is_default; });
            var selectedThumb = null;   // image_url of the currently-selected choice, if any
            html += '<div data-extra-id="' + extra.id + '">';
            html += '<label>' + escapeHtml(extra.name)
                  + (extra.is_required ? ' <span style="color:#b91c1c">*</span>' : '')
                  + '</label>';
            html += '<input type="hidden" name="extras[' + idx + '][extra_id]" value="' + extra.id + '">';
            html += '<select name="extras[' + idx + '][choice_id]"'
                  + (extra.is_required ? ' required' : '') + '>';
            if (!extra.is_required || !hasDefault) {
                html += '<option value=""'
                      + (presetVal === '' ? ' selected' : '')
                      + '>— None —</option>';
            }
            visibleChoices.forEach(function (c) {
                var isSelected;
                if (presetVal !== undefined && presetVal !== '') {
                    isSelected = String(c.id) === presetVal;
                } else if (presetVal === '') {
                    isSelected = false;
                } else {
                    isSelected = c.is_default;
                }
                if (isSelected && c.image_url) {
                    selectedThumb = c.image_url;
                }
                html += '<option value="' + c.id + '"'
                      + (isSelected ? ' selected' : '') + '>' + escapeHtml(c.label) + '</option>';
            });
            html += '</select>';
            // Show a small preview of whatever the customer just picked. The
            // thumbnail re-renders along with the rest of the extras box on
            // every change, so flipping choices updates the image instantly.
            if (selectedThumb) {
                html += '<img class="choice-thumb" src="' + escapeAttr(selectedThumb)
                      + '" alt="" loading="lazy">';
            }
            html += '</div>';
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

    // Apply the editing-mode pre-fill after loadProductData has populated
    // the cascade. Multi-pass so conditional extras (which only appear
    // once their parent is set) get their values too.
    async function applyEditingValues(initial) {
        if (!initial) return;
        if (initial.system_id) {
            systemSel.value = String(initial.system_id);
        }
        if (initial.option_id && initial.fabric_label) {
            fabricSearch.value = initial.fabric_label;
            fabricId.value = String(initial.option_id);
        }
        // Three-pass set-then-render: covers single-level conditional nesting
        // (which is all the schema currently supports). Each pass sets values
        // for extras that exist in the DOM and re-renders to reveal newly-
        // unlocked conditional ones.
        for (var pass = 0; pass < 3; pass++) {
            (initial.extras || []).forEach(function (ex) {
                var sel = document.querySelector(
                    '[data-extra-id="' + ex.extra_id + '"] select'
                );
                if (sel) sel.value = String(ex.choice_id);
            });
            renderExtras();
        }
        // Final pass after the last render — renderExtras' sticky preset
        // already applies the values, but a defensive pass costs nothing.
        (initial.extras || []).forEach(function (ex) {
            var sel = document.querySelector(
                '[data-extra-id="' + ex.extra_id + '"] select'
            );
            if (sel) sel.value = String(ex.choice_id);
        });
        schedulePreview();
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
        // Spell out exactly what's missing so the user can act on it
        // without having to guess which field they skipped — generic
        // "pick a product, fabric and dimensions" was too easy to miss
        // when you'd already filled three of the four.
        var missing = [];
        if (!productSel.value)     missing.push('product');
        if (!fabricId.value)       missing.push('fabric');
        if (!widthIn.value.trim()) missing.push('width');
        if (!dropIn.value.trim())  missing.push('drop');
        if (missing.length > 0) {
            previewBox.className   = 'idle';
            previewBox.textContent = 'Still need: ' + missing.join(', ') + '.';
            setSubmitDisabled(true);
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
                setSubmitDisabled(true);
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
            setSubmitDisabled(false);
        } catch (err) {
            previewBox.className   = 'error';
            previewBox.textContent = 'Could not fetch live price.';
            setSubmitDisabled(true);
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

    // Edit-mode pre-fill. When the page loads with ?edit_item=N, the form
    // already has the basic fields (room, dimensions, quantity, notes,
    // product) populated server-side; the cascading bits (system, fabric,
    // extras) need to wait for /api/product-data to come back before they
    // can be set. This kicks the cascade and applies the values.
    if (productSel.value) {
        (async function () {
            await loadProductData();
            if (window.__editingBlind__) {
                await applyEditingValues(window.__editingBlind__);
            }
        })();
    }
})();
</script>

<?php
// Editing data for the JS pre-fill, emitted only when in edit mode.
if ($editingItemId > 0 && $editingItem):
    // Build the fabric label the typeahead expects ("Band X — Supplier / Name / Colour").
    $editFabricBits = array_filter([
        (string) ($editingItem['fabric_supplier_snapshot'] ?? ''),
        (string) ($editingItem['fabric_name_snapshot']     ?? ''),
        (string) ($editingItem['fabric_colour_snapshot']   ?? ''),
    ], static fn ($s) => $s !== '');
    $editFabricLabel = 'Band ' . (string) ($editingItem['fabric_band_snapshot'] ?? '?')
                     . ' — ' . implode(' / ', $editFabricBits);
?>
<script>
window.__editingBlind__ = <?= json_encode([
    'product_id'    => (int) ($editingItem['product_id']     ?? 0),
    'system_id'     => $editingItem['system_id'] !== null ? (int) $editingItem['system_id'] : null,
    'option_id'     => (int) ($editingItem['option_id']      ?? 0),
    'fabric_label'  => $editFabricLabel,
    'extras'        => $editingExtras,
], JSON_THROW_ON_ERROR) ?>;
</script>
<?php endif; ?>
<?php endif; ?>

<script>
(function () {
    // Customer typeahead. When a match is picked:
    //   - copy data-id into the hidden customer_id field
    //   - populate the customer-detail fields from the data-* attrs so the
    //     form reflects the newly-linked customer's current details.
    // No match (free-text) → hidden id resets to 0; populated fields are
    // left as-is so the user can finish typing without losing their work.
    var search   = document.getElementById('customer_search');
    var hidden   = document.getElementById('customer_id');
    var dataList = document.getElementById('customer-options');
    if (!search || !hidden || !dataList) return;

    var FIELDS = ['name','email','phone','address1','address2','town','county','postcode'];

    function setField(suffix, value) {
        var el = document.getElementById(
            suffix === 'name' ? 'end_customer_name' : 'end_customer_' + suffix
        );
        if (el) el.value = value || '';
    }

    function syncFromMatch() {
        var typed = search.value.trim();
        var matched = null;
        for (var i = 0; i < dataList.options.length; i++) {
            if (dataList.options[i].value === typed) { matched = dataList.options[i]; break; }
        }
        if (matched) {
            hidden.value = matched.dataset.id || '0';
            FIELDS.forEach(function (f) { setField(f, matched.dataset[f]); });
            var wa = document.getElementById('end_customer_has_whatsapp');
            if (wa) wa.checked = matched.dataset.has_whatsapp === '1';
        } else {
            hidden.value = '0';
        }
    }

    search.addEventListener('input',  syncFromMatch);
    search.addEventListener('change', syncFromMatch);
})();
</script>
</body>
</html>
