<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../_partials/units.php';
require __DIR__ . '/../_partials/pricing_basis.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];
// Markup vs margin — only relabels the admin internal-cost hint below.
$pricingBasis = pricing_basis_for(db(), $clientId);
$isAdmin  = ($user['role'] ?? '') === 'admin';
$_perms   = current_user_permissions();

$id    = (int) ($_GET['id'] ?? 0);
$quote = qb_load_quote_or_404($id, $clientId);

// Measurement unit for THIS quote: the quote's own override if set, else
// the tenant default, else mm. Sizes are stored in mm; this only drives
// entry + display. (measurement_unit column may be absent pre-migration.)
$measureUnit = effective_unit($quote['measurement_unit'] ?? null, db(), $clientId);
$unitSuffix  = unit_suffix($measureUnit);

// Access gate: admin / view-all / quote-creator-equivalents see any
// quote in their tenant. Restricted users (typical fitter) can view
// ONLY quotes where they have at least one appointment assigned —
// these are the orders they're installing and need to verify blind
// details + take balance payments against. 404 (not 403) on mismatch
// so we don't leak the existence of other tenants' or other fitters'
// quotes to a guessing attacker.
$canSeeAllQuotes = $isAdmin
    || $_perms['can_view_all_customer_jobs']
    || $_perms['can_create_quotes'];
if (!$canSeeAllQuotes) {
    $assignedSt = db()->prepare(
        'SELECT 1 FROM appointments
          WHERE quote_id = ? AND client_user_id = ? AND client_id = ?
          LIMIT 1'
    );
    $assignedSt->execute([$id, (int) $user['user_id'], $clientId]);
    if (!$assignedSt->fetchColumn()) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
           . '<h1>Quote not found</h1>'
           . '<p><a href="/calendar/index.php">Back to Calendar</a></p>';
        exit;
    }
}
$editable = qb_is_editable($quote);

// Hoisted up from the Payments-panel block so the sticky header bar
// can also use it (to show the "Take payment" shortcut when relevant).
// Tenant-level paid add-on; defensive against the column not existing.
$accountsEnabled = false;
try {
    $accStmt = db()->prepare(
        'SELECT COALESCE(feature_accounts, 0)
           FROM client_settings WHERE client_id = ? LIMIT 1'
    );
    $accStmt->execute([$clientId]);
    $accountsEnabled = ((int) $accStmt->fetchColumn()) === 1;
} catch (Throwable $e) {
    // feature_accounts column missing — treat as disabled.
}
// Quote is in a state that could carry an outstanding balance.
$quoteIsOrder = in_array(
    (string) $quote['status'],
    ['accepted', 'ordered', 'fitted', 'invoiced', 'paid'],
    true
);

// WT charge (internal surcharge) — tenant opt-in. Defensive: column may be
// absent pre-migration. wt_amount lives on the quote (q.* in qb_load_*).
$wtEnabled = false;
try {
    $wtStmt = db()->prepare('SELECT COALESCE(feature_wt, 0) FROM client_settings WHERE client_id = ? LIMIT 1');
    $wtStmt->execute([$clientId]);
    $wtEnabled = ((int) $wtStmt->fetchColumn()) === 1;
} catch (Throwable $e) { /* not migrated → disabled */ }
$wtAmount = round((float) ($quote['wt_amount'] ?? 0), 2);

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
        // user_value is optional (added by migrate_extra_length_input).
        // Try-fallback so editing still works pre-migration.
        try {
            $exSt = db()->prepare(
                'SELECT product_extra_id, product_extra_choice_id, user_value
                   FROM quote_item_extras WHERE quote_item_id = ? ORDER BY id'
            );
            $exSt->execute([$editingItemId]);
            foreach ($exSt->fetchAll() as $r) {
                $editingExtras[] = [
                    'extra_id'   => (int) $r['product_extra_id'],
                    'choice_id'  => (int) $r['product_extra_choice_id'],
                    'user_value' => $r['user_value'] !== null ? (float) $r['user_value'] : null,
                ];
            }
        } catch (Throwable $e) {
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
    // user_value is added by migrate_extra_length_input.php — fall back
    // to a column-less SELECT if the migration hasn't run.
    try {
        $st = db()->prepare(
            "SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot,
                    mode, amount_applied, user_value
               FROM quote_item_extras
              WHERE quote_item_id IN ($ph)
              ORDER BY id"
        );
        $st->execute($itemIds);
        $rows = $st->fetchAll();
    } catch (Throwable $e) {
        $st = db()->prepare(
            "SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot,
                    mode, amount_applied
               FROM quote_item_extras
              WHERE quote_item_id IN ($ph)
              ORDER BY id"
        );
        $st->execute($itemIds);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) $r['user_value'] = null;
        unset($r);
    }
    foreach ($rows as $r) {
        $extrasByItem[(int) $r['quote_item_id']][] = $r;
    }
}

// Active products for the line-item form (cascading dropdowns load the rest
// via /quote-builder/api/product-data.php). Grouped by category via the shared
// product-picker helper.
require_once __DIR__ . '/../_partials/product_picker.php';
$products = product_picker_products($clientId);

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

// Sidebar lights up "Order history" — the quote-editing pages live
// under that menu entry now that the old "New Quote" / "Quote
// History" / "Orders" trio has been merged (Tyler review #3).
$activeNav = 'order-history';
$transitions = qb_allowed_transitions((string) $quote['status']);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote <?= e((string) $quote['quote_number']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .status-pill {
            display: inline-block; padding: 0.125rem 0.625rem; font-size: 0.75rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
            border-radius: 999px; vertical-align: middle; margin-left: 0.5rem;
        }
        .status-draft     { background: var(--border); color: var(--text-secondary); }
        .status-sent      { background: #dbeafe; color: #1e40af; }
        .status-accepted  { background: #d1fae5; color: #065f46; }
        .status-declined  { background: #fee2e2; color: #991b1b; }
        .status-ordered   { background: #ede9fe; color: #5b21b6; }
        .status-invoiced  { background: #fef3c7; color: #92400e; }
        .status-paid      { background: #14532d; color: #ffffff; }
        .item-desc { font-size: 0.875rem; color: var(--text-secondary); line-height: 1.45; }
        .item-desc strong { color: var(--text-primary); font-weight: 600; }
        .item-extras { color: var(--text-faint); font-size: 0.8125rem; margin-top: 0.25rem; }
        .totals-row td { font-weight: 600; }
        .totals-row.grand td { font-size: 1.0625rem; color: var(--text-primary); }
        #item-preview {
            padding: 0.75rem 1rem; border-radius: 8px;
            font-size: 0.9375rem; margin: 0.75rem 0;
        }
        #item-preview.idle    { background: var(--bg-subtle-2); color: var(--text-muted); }
        #item-preview.error   { background: #fee2e2; color: #991b1b; }
        #item-preview.success { background: #d1fae5; color: #065f46; }
        .extras-grid {
            display: grid; gap: 0.75rem 1rem;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            margin: 0.5rem 0;
        }
        .extras-grid label {
            display: block; font-size: 0.8125rem; color: var(--text-muted);
            font-weight: 600; margin-bottom: 0.25rem;
        }
        .extras-grid .choice-thumb {
            display: block; max-width: 160px; max-height: 90px;
            margin-top: 0.5rem; padding: 0.25rem;
            background: #fff; border: 1px solid var(--border); border-radius: 6px;
        }
        /* ============================================================
           Page-slimming pass. The quote edit page used to be tall enough
           to need lots of scrolling on a laptop. Each block below trims
           a chunk without hiding functionality.
           ============================================================ */
        /* Tighter gap between form rows (default ~1rem → 0.5rem). */
        #add-line .form-row { margin-bottom: 0.5rem; }
        #add-line .form-row + .form-row { margin-top: 0; }

        /* Compact "Options" subheader inside the add-blind form. */
        #add-line #item-extras-wrap .section-header { margin: 0.5rem 0 0.25rem; }
        #add-line #item-extras-wrap .section-title  {
            font-size: 0.8125rem !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-faint);
            font-weight: 600;
        }

        /* Dimensions + quantity + notes row.
           Wide screens (≥1100px): 4-up grid, one tidy line.
           Narrower: same grid but the notes input wraps to its own
           row spanning the full width — no duplicate inputs, just
           layout reflow. */
        #add-line .form-row.cols-3-plus-notes {
            display: grid;
            grid-template-columns: 1fr 1fr 0.5fr 1.5fr;
            gap: 0.5rem 0.75rem;
        }
        #add-line .form-row.cols-3-plus-notes .form-group { margin: 0; }
        @media (max-width: 1099px) {
            #add-line .form-row.cols-3-plus-notes {
                grid-template-columns: 1fr 1fr 0.75fr;
            }
            /* Notes input is the last form-group — span across all
               three columns so it gets a full-width row of its own. */
            #add-line .form-row.cols-3-plus-notes .form-group:last-child {
                grid-column: 1 / -1;
            }
        }

        /* Sticky save bar inside the add-blind form. Once you scroll down
           past the form, the Save button rides along at the bottom of the
           viewport so you never have to scroll back to commit a change.
           Only fires inside the form (no global sticky bar across the
           whole page), so the customer-details form still works normally. */
        #add-line .form-actions.sticky-save {
            position: sticky;
            bottom: 0;
            background: var(--bg-card);
            margin-top: 0.5rem;
            padding: 0.625rem 0;
            border-top: 1px solid var(--border);
            z-index: 5;
        }

        /* The add/edit-blind form is the natural landing spot after a
           Duplicate or Edit click. Give the anchor scroll some breathing
           room so it doesn't sit jammed up against the page top, and
           a brief flash so the user notices they've been moved here. */
        #add-line { scroll-margin-top: 1rem; }
        #add-line.flash-jump {
            animation: edit-flash 1.5s ease-out;
        }
        @keyframes edit-flash {
            0%   { box-shadow: 0 0 0 4px rgba(252, 211, 77, 0.55); }
            100% { box-shadow: 0 0 0 4px rgba(252, 211, 77, 0); }
        }
        /* Each top-level option is one grid cell. Follow-up options
           (e.g. Colour for the chosen Bottom Weight type) nest inside
           the parent's cell with a left rule + indent so the
           relationship is visually obvious — no orphan dropdowns
           drifting around the grid. */
        .extras-grid .extra-cell {
            display: flex; flex-direction: column; gap: 0.5rem;
            background: var(--bg-subtle);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.75rem 0.875rem;
        }
        .extras-grid .extra-cell select {
            width: 100%;
            padding: 0.4375rem 0.625rem;
            font: inherit;
            background: var(--bg-input);
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            box-sizing: border-box;
        }
        .extras-grid .extra-cell .extra-child {
            margin-left: 0.625rem;
            padding-left: 0.625rem;
            border-left: 2px solid var(--border-strong);
        }
        .extras-grid .extra-cell .extra-child label {
            font-size: 0.75rem;
            color: var(--text-faint);
        }
        .extras-grid .extra-cell .extra-child label::before {
            content: '↳ ';
            color: var(--text-faint);
        }
        .form-group input[type="number"], .form-group input[type="text"] {
            width: 100%; font: inherit; padding: 0.5625rem 0.75rem;
            border: 1px solid var(--border-strong); border-radius: 8px; background: var(--bg-input);
            box-sizing: border-box;
        }
        .fabric-picker { position: relative; }
        .fabric-results {
            position: absolute; top: 100%; left: 0; right: 0;
            max-height: 360px; overflow-y: auto;
            background: var(--bg-card); border: 1px solid var(--border-strong);
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            z-index: 20; margin-top: 4px;
        }
        .fabric-results .frow {
            padding: 0.5rem 0.75rem; cursor: pointer;
            border-bottom: 1px solid var(--bg-subtle-2);
        }
        .fabric-results .frow:last-child { border-bottom: 0; }
        .fabric-results .frow:hover,
        .fabric-results .frow.active { background: var(--bg-subtle-2); }
        .fabric-results .fname { font-weight: 600; color: var(--text-primary); font-size: 0.9375rem; }
        .fabric-results .fmeta { color: var(--text-faint); font-size: 0.8125rem; margin-top: 0.125rem; }
        .fabric-results .fband {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 700; color: #fff; background: #1f3b5b;
            border-radius: 999px; margin-right: 0.375rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .fabric-results .empty {
            padding: 1rem; text-align: center;
            color: var(--text-faint); font-size: 0.875rem;
        }
        .read-only-banner {
            background: #fef3c7; color: #92400e; padding: 0.75rem 1rem;
            border-radius: 8px; margin-bottom: 1rem; font-size: 0.9375rem;
        }
        .status-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem; }
        .status-actions button, .status-actions form {
            margin: 0;
        }
        /* Top-of-page Quote actions panel — slimmer than a standard
           section so it doesn't push the customer/items content too
           far below the fold on a tall list of transitions. */
        .qb-top-actions {
            margin-top: 0;
            padding: 0.625rem 0.875rem;
        }
        .qb-top-actions .section-header { margin-bottom: 0.25rem; }
        .qb-top-actions .section-title { font-size: 0.9375rem; margin: 0; }
        .qb-top-actions .status-actions { margin-top: 0.5rem; }

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
        .quote-sticky-bar .qsb-actions {
            display: flex; gap: 0.375rem; flex-wrap: wrap; align-items: center;
        }
        .quote-sticky-bar .qsb-actions form { margin: 0; }
        .quote-sticky-bar .qsb-actions button {
            font: inherit; font-size: 0.8125rem; font-weight: 600;
            padding: 0.3125rem 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.35);
            background: rgba(255, 255, 255, 0.12);
            color: #fff; border-radius: 999px; cursor: pointer;
        }
        .quote-sticky-bar .qsb-actions button:hover {
            background: rgba(255, 255, 255, 0.22);
        }
        .quote-sticky-bar .qsb-actions button.is-accept {
            background: #15803d; border-color: #166534;
        }
        .quote-sticky-bar .qsb-actions button.is-accept:hover { background: #166534; }
        .quote-sticky-bar .qsb-actions button.is-decline {
            background: rgba(220, 38, 38, 0.85); border-color: rgba(220, 38, 38, 1);
        }
        /* ===========================================================
           Record-a-new-payment card. The form used to just be a row
           of inputs flowing off the bottom of the Payments panel —
           users tapped "Take payment", got scrolled here, and didn't
           realise the inputs were where to type. Now wrapped in a
           highlighted card with a clear heading so it reads as
           "you're here to enter a payment."
           =========================================================== */
        .record-payment-card {
            background: var(--alert-info-bg);
            border: 2px solid var(--brand);
            border-radius: 12px;
            padding: 1rem 1.125rem;
            margin: 0.5rem 0 0;
        }
        .record-payment-card__title {
            margin: 0 0 0.25rem;
            font-size: 1.0625rem; font-weight: 700; color: var(--alert-info-text);
        }
        .record-payment-card__hint {
            margin: 0 0 0.875rem; color: var(--text-muted); font-size: 0.875rem;
        }
        .record-payment-card__form {
            display: flex; flex-wrap: wrap; gap: 0.625rem; align-items: end;
        }
        .record-payment-card .rp-field {
            display: flex; flex-direction: column; gap: 0.1875rem;
            flex: 1 1 8rem; min-width: 8rem;
        }
        .record-payment-card .rp-field--amount { flex: 0 0 8rem; }
        .record-payment-card .rp-field--ref    { flex: 2 1 12rem; }
        .record-payment-card .rp-field label {
            font-size: 0.8125rem; color: var(--alert-info-text); font-weight: 600;
        }
        .record-payment-card .rp-field input,
        .record-payment-card .rp-field select {
            padding: 0.5rem 0.625rem;
            border: 1px solid #93c5fd; border-radius: 6px;
            font: inherit; background: var(--bg-input);
        }
        .record-payment-card .rp-field input:focus,
        .record-payment-card .rp-field select:focus {
            border-color: var(--brand);
            outline: 3px solid rgba(31, 59, 91, 0.18);
        }
        /* The Amount field is the only one that almost always needs
           confirmation/edit — make it visibly bigger so the eye lands
           on it first when scrolled into view from the Take-payment
           button. */
        .record-payment-card #rp-amount {
            font-size: 1.25rem; font-weight: 700;
            padding: 0.625rem 0.75rem;
        }
        .record-payment-card .rp-submit { flex: 0 0 auto; }
        .record-payment-card__btn {
            font-size: 0.9375rem; font-weight: 700;
            padding: 0.5625rem 1.125rem;
        }

        /* "Take payment" — anchor, not a button, so the .is-accept
           button rule above didn't catch it; it was inheriting the
           default link colour (blue on dark blue, near-invisible).
           Dedicated style: white pill, dark text, slightly bigger so
           it reads as the primary action on the bar. */
        .quote-sticky-bar #qsb-take-payment {
            background: #fff;
            color: #1f3b5b;
            border: 1px solid #fff;
            font-size: 0.9375rem;
            font-weight: 700;
            padding: 0.4375rem 0.9375rem;
            border-radius: 999px;
            text-decoration: none;
        }
        .quote-sticky-bar #qsb-take-payment:hover {
            background: var(--bg-subtle-2);
        }

        /* ===========================================================
           Customer details — collapsible section. Once the customer
           is filled in, the section collapses to a one-line summary
           ("Customer: Name — Town — Postcode") so it doesn't dominate
           the top of the page on every revisit. Click the summary
           to expand and edit.
           =========================================================== */
        .customer-collapse > summary {
            list-style: none; cursor: pointer; padding: 0.25rem 0;
            font-size: 1.125rem; font-weight: 600; color: var(--text-primary);
            display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
        }
        .customer-collapse > summary::-webkit-details-marker { display: none; }
        .customer-collapse > summary::before {
            content: '▸'; display: inline-block; color: var(--text-faint);
            transition: transform 150ms; flex-shrink: 0;
        }
        .customer-collapse[open] > summary::before { transform: rotate(90deg); }
        .customer-collapse > summary .cs-meta {
            font-size: 0.875rem; font-weight: 400; color: var(--text-faint);
        }
        .customer-collapse > summary .cs-hint {
            font-size: 0.8125rem; font-weight: 400; color: var(--text-faint);
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
           Mobile / small-screen tweaks. The global app.css already
           collapses .form-row variants and shrinks .app-main padding
           under 768px; the rules below target quote-builder-specific
           bits (sticky bar, button rows, section spacing) so the
           page doesn't feel cramped or have the nav FAB sitting on
           top of the total.
           =========================================================== */
        @media (max-width: 768px) {
            /* Sticky bar margins were tuned for the 1.5rem desktop
               main padding; on mobile main-padding is 1.25rem. Also
               pad the right side so the nav FAB button (top: 0.625rem
               right: 0.625rem, 44x44px, z-index 1002) doesn't sit on
               top of the Total figure. */
            .quote-sticky-bar {
                margin: -1.25rem -1.25rem 1rem;
                padding: 0.5rem 3.5rem 0.5rem 0.75rem;
                font-size: 0.875rem;
            }
            .quote-sticky-bar .qsb-total { font-size: 1rem; }

            /* Sections themselves — tighter inner padding so each
               doesn't take a full screen height of vertical space. */
            .section { padding: 1rem; margin-bottom: 0.875rem; }

            /* Customer-collapse summary was getting cut off on the
               right ("...Coventry — CV") because the meta span sat
               on the same flex line as the name. Smaller font, allow
               the meta to drop to its own line, and let any single
               long token (postcode) break instead of overflowing. */
            .customer-collapse > summary {
                font-size: 1rem;
                line-height: 1.35;
                gap: 0.25rem;
                word-break: break-word;
            }
            .customer-collapse > summary .cs-meta,
            .customer-collapse > summary .cs-hint {
                display: block;
                width: 100%;
            }

            /* Send-to-customer button row was side-by-side; on a
               narrow screen the WhatsApp button overflows. Stack them. */
            .section [style*="display:flex"][style*="flex-wrap:wrap"] button,
            .section [style*="display:flex"][style*="flex-wrap:wrap"] a.btn-secondary {
                flex: 1 1 100%;
                text-align: center;
            }

            /* Status / quote-actions row at the bottom — same idea,
               buttons full width so they don't squeeze each other. */
            .status-actions form,
            .status-actions > a,
            .status-actions > button { flex: 1 1 100%; }
            .status-actions form button { width: 100%; }

            /* Belt-and-braces against any inline-styled or third-
               party element that ignores width:100% and pushes past
               the section right edge (the Quote-notes textarea was
               disappearing off the right). */
            .section input,
            .section textarea,
            .section select { max-width: 100%; box-sizing: border-box; }
        }
        @media (max-width: 700px) {
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
            <?php
                // The two transitions that almost always want one-click
                // access: "Mark as accepted" (customer's said yes) and
                // "Mark as declined" (customer's said no). Available
                // from draft now too, so an on-the-spot acceptance
                // doesn't require a Send hop.
                $quickActions = array_values(array_intersect(
                    ['accepted', 'declined'], $transitions
                ));
                // Filter further by the user's permission — sales-side
                // transitions (accepted / declined) need
                // can_create_quotes. A fitter shouldn't see these
                // buttons because they don't close sales.
                $quickActions = array_values(array_filter(
                    $quickActions,
                    static fn ($t) => qb_user_can_change_to($isAdmin, $_perms, $t)
                ));
            ?>
            <?php if ($editable && $quickActions): ?>
                <span class="qsb-actions">
                    <?php foreach ($quickActions as $qa): ?>
                        <form method="post" action="/quote-builder/change_status.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                            <input type="hidden" name="target_status" value="<?= e($qa) ?>">
                            <button type="submit"
                                    class="<?= $qa === 'accepted' ? 'is-accept' : 'is-decline' ?>"
                                    <?php if ($qa === 'declined'): ?>
                                        data-confirm="Mark this quote as declined?"
                                    <?php endif; ?>>
                                <?= $qa === 'accepted' ? '✓ Customer accepted' : '✕ Customer declined' ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
            <?php if ($quoteIsOrder && $accountsEnabled): ?>
                <!-- One-tap shortcut to the Payments panel — the typical
                     fitter-at-the-door action. Scrolls to the panel and
                     focuses the Amount field via the anchor + JS hook. -->
                <span class="qsb-actions">
                    <a href="#payments" id="qsb-take-payment">
                        💷 Take payment
                    </a>
                </span>
            <?php endif; ?>
            <span class="qsb-total">
                Total <?= e(qb_fmt_money($quote['total'])) ?>
            </span>
        </div>

        <!-- Quote actions panel — moved to the top of the page per
             Tyler's review. Operators who pop in to do a quick status
             change (mark ordered, mark fitted, download PDF) no longer
             have to scroll to the bottom to find the controls. Delete
             stays in its own "Danger zone" at the very bottom so it
             can't be a top-of-page misclick. -->
        <section class="section qb-top-actions">
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
                    <?php if (!qb_user_can_change_to($isAdmin, $_perms, $t)) continue; ?>
                    <form method="post" action="/quote-builder/change_status.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                        <input type="hidden" name="target_status" value="<?= e($t) ?>">
                        <button type="submit" class="btn btn-secondary">
                            <?= $t === 'draft' ? 'Reopen as draft' : 'Mark as ' . e($t) ?>
                        </button>
                    </form>
                <?php endforeach; ?>
                <?php if ($quoteIsOrder && ($isAdmin || !empty($_perms['can_create_orders']))): ?>
                    <a href="/quote-builder/order_suppliers.php?id=<?= (int) $quote['id'] ?>"
                       class="btn btn-secondary">📦 Send to suppliers</a>
                <?php endif; ?>
                <?php
                    // Invoice the customer — available once the job is an order
                    // (ordered onward). Emails the invoice + advances to Invoiced.
                    $canInvoice = ($isAdmin || !empty($_perms['can_create_orders']))
                        && in_array((string) $quote['status'], ['ordered', 'fitted', 'invoiced', 'paid'], true);
                ?>
                <?php if ($canInvoice): ?>
                    <form method="post" action="/pdf-generator/send_invoice.php" style="display:inline;margin:0"
                          onsubmit="return confirm('Email this invoice to the customer now? This also marks the job as Invoiced.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $quote['id'] ?>">
                        <button type="submit" class="btn btn-secondary">🧾 Send invoice</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <div class="page-header">
            <div>
                <p class="page-subtitle" style="margin:0">
                    <a href="/orders/index.php">&larr; Order history</a>
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
                    Use <strong>Reopen as draft</strong> above to edit it.
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
            // Customer details starts COLLAPSED by default — the summary
            // line tells the user what's in there and how to expand it
            // ("click to edit" / "click to add the customer's contact info").
            // Used to auto-open for new quotes; that ate ~250px on every
            // page load whether the user needed it or not. Click is one tap.
            $startOpen   = false;
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
                            <small style="color:var(--text-faint);font-size:0.8125rem">
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
                        <label style="display:inline-flex;align-items:center;gap:0.4rem;margin-top:0.5rem;font-weight:400;font-size:0.875rem;color:var(--text-muted);<?= !$editable ? 'cursor:default' : 'cursor:pointer' ?>">
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

                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="end_customer_address1">Address line 1</label>
                        <input id="end_customer_address1" name="end_customer_address1" type="text" maxlength="150"
                               <?= !$editable ? 'readonly' : '' ?>
                               value="<?= e((string) ($quote['end_customer_address1'] ?? '')) ?>">
                    </div>
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
                 the form so it saves alongside customer fields.
                 rows="1" keeps the page short by default — the textarea
                 is still resizable so anyone writing more than a line
                 just drags the corner. -->
            <div class="form-row full" style="margin-top:1rem">
                <div class="form-group">
                    <label for="notes">Quote notes</label>
                    <textarea id="notes" name="notes" rows="1"
                              style="resize:vertical;min-height:2.5rem"
                              <?= !$editable ? 'readonly' : '' ?>><?= e((string) ($quote['notes'] ?? '')) ?></textarea>
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

                <!--
                    Field order matches the natural decision flow the
                    salesperson walks: pick the Product first (it gates
                    Systems + Fabric), then System (gates which fabric
                    bands apply), then Fabric, then Room (purely a label).
                -->
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="item-product">Product <span class="required">*</span></label>
                        <select id="item-product" name="product_id" required>
                            <option value="">Choose product...</option>
                            <?= product_picker_options_html($products, $editingItem ? (int) $editingItem['product_id'] : 0) ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="item-system">System</label>
                        <select id="item-system" name="system_id" disabled>
                            <option value="">Choose product first</option>
                        </select>
                    </div>
                </div>

                <!--
                    Band filter — narrows the Fabric typeahead below to a
                    single price band so the salesperson isn't scrolling a
                    huge list. UI-only (no name attr): the picked fabric
                    still carries the band; this just filters the search.
                -->
                <div class="form-row cols-2" id="item-band-row">
                    <div class="form-group">
                        <label for="item-band"><span id="item-band-label">Band</span></label>
                        <select id="item-band" disabled>
                            <option value="">All bands</option>
                        </select>
                    </div>
                    <div class="form-group"></div>
                </div>

                <div class="form-row cols-2">
                    <div class="form-group" id="item-fabric-group">
                        <label for="item-fabric-search"><span id="item-fabric-label">Fabric</span> <span class="required">*</span></label>
                        <div class="fabric-picker">
                            <input type="text" id="item-fabric-search"
                                   placeholder="Choose product first"
                                   autocomplete="off" disabled>
                            <input type="hidden" id="item-fabric" name="option_id" required>
                            <div id="item-fabric-results" class="fabric-results" hidden></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="item-room">Room name</label>
                        <!--
                            Custom combobox replaces the <datalist> + text
                            input pattern. <datalist> requires a double-
                            click in most browsers to show the dropdown on
                            an empty input — confusing and slow. This
                            widget opens on the first click (input OR
                            chevron) and filters as you type.

                            Keeps the original input id/name so backend
                            POST and editing-mode prefill keep working.
                        -->
                        <div class="room-combobox" style="position:relative">
                            <input id="item-room" name="room_name" type="text" maxlength="80"
                                   autocomplete="off"
                                   value="<?= e((string) ($editingItem['room_name'] ?? '')) ?>"
                                   placeholder="Type or pick — e.g. Living Room"
                                   style="padding-right:2rem">
                            <button type="button" id="item-room-chevron"
                                    aria-label="Show room name suggestions"
                                    style="position:absolute;right:0.375rem;top:50%;transform:translateY(-50%);
                                           background:transparent;border:0;color:var(--text-faint);cursor:pointer;
                                           padding:0.25rem 0.375rem;font-size:0.75rem;line-height:1">▾</button>
                            <div id="item-room-popup" hidden
                                 style="position:absolute;top:100%;left:0;right:0;
                                        margin-top:4px;background:#fff;border:1px solid var(--border-strong);
                                        border-radius:8px;box-shadow:0 8px 20px rgba(0,0,0,0.08);
                                        z-index:20;max-height:240px;overflow-y:auto;padding:0.25rem">
                                <?php
                                $rooms = [
                                    'Bathroom', 'Bedroom', 'Bedroom 2', 'Bedroom 3',
                                    'Cloakroom', 'Conservatory', 'Dining Room', 'En-suite',
                                    'Hallway', 'Kitchen', 'Kitchen / Diner', 'Landing',
                                    'Living Room', 'Lounge', 'Master Bedroom', 'Nursery',
                                    'Office', 'Snug', 'Spare Room', 'Study', 'Utility',
                                ];
                                foreach ($rooms as $r): ?>
                                    <div class="room-opt" data-room="<?= e($r) ?>"
                                         style="padding:0.4375rem 0.5625rem;cursor:pointer;
                                                border-radius:6px;font-size:0.9375rem;color:var(--text-primary)"
                                         onmouseover="this.style.background='#eef2f7'"
                                         onmouseout="this.style.background='transparent'">
                                        <?= e($r) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <script>
                        (function () {
                            var wrap   = document.currentScript.previousElementSibling;
                            var input  = document.getElementById('item-room');
                            var chev   = document.getElementById('item-room-chevron');
                            var popup  = document.getElementById('item-room-popup');
                            if (!wrap || !input || !popup) return;
                            var opts = popup.querySelectorAll('.room-opt');

                            function open()  { popup.hidden = false; filter(input.value); }
                            function close() { popup.hidden = true;  }
                            function toggle() {
                                if (popup.hidden) { open(); input.focus(); }
                                else close();
                            }
                            function filter(q) {
                                var s = (q || '').toLowerCase().trim();
                                var anyVisible = false;
                                opts.forEach(function (el) {
                                    var hit = s === '' || el.dataset.room.toLowerCase().indexOf(s) !== -1;
                                    el.style.display = hit ? '' : 'none';
                                    if (hit) anyVisible = true;
                                });
                                popup.style.display = anyVisible ? '' : 'none';
                            }

                            input.addEventListener('focus', open);
                            input.addEventListener('click', open);
                            input.addEventListener('input', function () { filter(input.value); });
                            chev.addEventListener('mousedown', function (e) {
                                e.preventDefault();   // don't steal focus
                                toggle();
                            });
                            opts.forEach(function (el) {
                                el.addEventListener('mousedown', function (e) {
                                    e.preventDefault();
                                    input.value = el.dataset.room;
                                    close();
                                });
                            });
                            // Click outside the combobox closes the popup.
                            document.addEventListener('click', function (e) {
                                if (!wrap.contains(e.target)) close();
                            });
                            // Escape closes too.
                            input.addEventListener('keydown', function (e) {
                                if (e.key === 'Escape') close();
                            });
                        })();
                        </script>
                    </div>
                </div>

                <!-- On wide screens this row morphs into a 4-up grid (the
                     CSS turns cols-3-plus-notes into Width|Drop|Qty|Notes
                     side by side) so we don't burn two rows on four short
                     inputs. Below 1100px it falls back to cols-3 and the
                     standalone notes row below takes over. -->
                <div class="form-row full">
                    <div class="form-group" style="max-width:18rem">
                        <label for="item-unit">Measurement unit (this quote)</label>
                        <select id="item-unit">
                            <?php foreach (['mm' => 'Millimetres (mm)', 'cm' => 'Centimetres (cm)',
                                            'm' => 'Metres (m)', 'in' => 'Inches (in)'] as $uVal => $uLabel): ?>
                                <option value="<?= e($uVal) ?>" <?= $measureUnit === $uVal ? 'selected' : '' ?>>
                                    <?= e($uLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:var(--text-faint);font-size:0.75rem;display:block;margin-top:0.25rem">
                            Re-displays this quote's sizes in the chosen unit. Sizes are stored
                            the same way regardless.
                        </small>
                    </div>
                </div>

                <div class="form-row cols-3 cols-3-plus-notes">
                    <div class="form-group" id="item-width-group">
                        <label for="item-width">Width (<?= e($unitSuffix) ?>) <span class="required">*</span></label>
                        <input id="item-width" name="width" type="text" required
                               autocomplete="off" autocorrect="off" autocapitalize="off"
                               data-lpignore="true" data-1p-ignore="true"
                               value="<?= $editingItem ? e(unit_format((int) $editingItem['width_mm'], $measureUnit, false)) : '' ?>"
                               placeholder="Width in <?= e($unitSuffix) ?>">
                    </div>
                    <div class="form-group" id="item-drop-group">
                        <label for="item-drop">Drop (<?= e($unitSuffix) ?>) <span class="required">*</span></label>
                        <input id="item-drop" name="drop" type="text" required
                               autocomplete="off" autocorrect="off" autocapitalize="off"
                               data-lpignore="true" data-1p-ignore="true"
                               value="<?= $editingItem ? e(unit_format((int) $editingItem['drop_mm'], $measureUnit, false)) : '' ?>"
                               placeholder="Drop in <?= e($unitSuffix) ?>">
                    </div>
                    <div class="form-group">
                        <label for="item-qty" id="item-qty-label">Quantity</label>
                        <input id="item-qty" name="quantity" type="number" step="1" min="1"
                               value="<?= $editingItem ? (int) $editingItem['quantity'] : 1 ?>">
                    </div>
                    <div class="form-group">
                        <label for="item-notes">Notes</label>
                        <input id="item-notes" name="notes" type="text" maxlength="255"
                               value="<?= e((string) ($editingItem['notes'] ?? '')) ?>"
                               placeholder="Optional internal note">
                    </div>
                </div>

                <?php if ($isAdmin || $_perms['can_view_costs']):
                    // Per-blind pricing override. The rate field is in the
                    // tenant's basis; a hidden field carries the MARKUP the
                    // server stores. Blank = use the product's set rate.
                    $rateLabel  = pricing_basis_label($pricingBasis);
                    $basisWord  = strtolower($rateLabel);
                    $ovMarkupK  = ($editingItem && $editingItem['markup_percent'] !== null)
                        ? (float) $editingItem['markup_percent'] : null;
                    $ovMarkupShown = $ovMarkupK !== null
                        ? number_format(markup_to_display($ovMarkupK, $pricingBasis), 2, '.', '') : '';
                    $ovDiscShown = ($editingItem && $editingItem['discount_percent'] !== null)
                        ? number_format((float) $editingItem['discount_percent'], 2, '.', '') : '';
                ?>
                <details class="item-override" id="item-override" <?= $ovMarkupShown !== '' || $ovDiscShown !== '' ? 'open' : '' ?>
                         style="margin:0.25rem 0 0.5rem;border:1px solid var(--border);border-radius:8px;padding:0.5rem 0.75rem">
                    <summary style="cursor:pointer;font-size:0.8125rem;color:var(--text-secondary)">
                        Adjust price for this blind
                    </summary>
                    <div class="form-row cols-2" style="margin-top:0.5rem">
                        <div class="form-group">
                            <label for="item-disc-override">Discount % (this blind)</label>
                            <input id="item-disc-override" name="discount_override" type="number"
                                   step="0.01" min="0" autocomplete="off"
                                   value="<?= e($ovDiscShown) ?>" placeholder="product default">
                        </div>
                        <div class="form-group">
                            <label for="item-rate-override" id="item-rate-override-label"><?= e($rateLabel) ?> % (this blind)</label>
                            <input id="item-rate-override" type="number"
                                   step="0.01" min="0" autocomplete="off"
                                   value="<?= e($ovMarkupShown) ?>" placeholder="product default">
                            <input type="hidden" name="markup_override" id="item-markup-override-hidden"
                                   value="<?= $ovMarkupK !== null ? e(number_format($ovMarkupK, 4, '.', '')) : '' ?>">
                            <span id="item-rate-hint" style="font-size:0.72rem;color:#2563eb;display:block;margin-top:0.2rem"></span>
                        </div>
                    </div>
                    <small style="color:var(--text-faint);font-size:0.72rem;line-height:1.4;display:block">
                        Leave blank to use the product's set <?= e($basisWord) ?> / discount.
                        This only changes <strong>this blind</strong> on this quote.
                    </small>
                </details>
                <?php endif; ?>

                <div id="item-extras-wrap" style="display:none">
                    <div class="section-header" style="margin-top:0.5rem">
                        <h3 class="section-title" style="font-size:1rem">Options</h3>
                    </div>
                    <div id="item-extras" class="extras-grid"></div>
                </div>

                <div id="item-preview" class="idle">
                    Pick a product, fabric and dimensions to see the price.
                </div>

                <div class="form-actions sticky-save">
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
                                                    + <?= e((string) $ex['extra_name_snapshot']) ?><?php if (($ex['choice_label_snapshot'] ?? '') !== ''): ?>: <?= e((string) $ex['choice_label_snapshot']) ?><?php endif; ?>
                                                    <?php if (isset($ex['user_value']) && $ex['user_value'] !== null && (float) $ex['user_value'] > 0): ?>
                                                        — <?= e(rtrim(rtrim(number_format((float) $ex['user_value'], 2, '.', ''), '0'), '.')) ?>mm
                                                    <?php endif; ?>
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
                                        <?php
                                            // A 0 on either axis means that axis doesn't apply
                                            // (width-only → no drop; per-slat → no width).
                                            $itW = (int) $it['width_mm'];
                                            $itD = (int) $it['drop_mm'];
                                            if ($itW > 0 && $itD > 0) {
                                                echo e(unit_format($itW, $measureUnit)) . ' × '
                                                   . e(unit_format($itD, $measureUnit));
                                            } elseif ($itD > 0) {
                                                echo e(unit_format($itD, $measureUnit)) . ' drop';
                                            } elseif ($itW > 0) {
                                                echo e(unit_format($itW, $measureUnit)) . ' wide';
                                            } else {
                                                echo '—';
                                            }
                                        ?>
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
                                                  data-confirm="Remove this blind?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                                                <input type="hidden" name="item_id"  value="<?= (int) $it['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" aria-label="Remove blind">&times;</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ($wtEnabled || $wtAmount > 0.0049): ?>
                                <tr class="totals-row" style="color:#9333ea">
                                    <td colspan="<?= $editable ? 5 : 4 ?>" style="text-align:right">
                                        WT
                                        <span style="font-weight:400;font-size:0.75rem;color:var(--text-faint)">(internal — never shown to the customer)</span>
                                    </td>
                                    <td class="num">
                                        <?php if ($editable && $wtEnabled): ?>
                                            <form method="post" action="/quote-builder/save_wt.php"
                                                  style="display:inline-flex;gap:0.25rem;align-items:center;justify-content:flex-end;margin:0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                                                <span>£</span>
                                                <input type="number" name="wt_amount" step="0.01" min="0"
                                                       value="<?= e(number_format($wtAmount, 2, '.', '')) ?>"
                                                       style="width:5rem;padding:0.2rem 0.35rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit;text-align:right">
                                                <button type="submit" class="btn btn-secondary" style="padding:0.15rem 0.5rem;font-size:0.8125rem">Set</button>
                                            </form>
                                        <?php else: ?>
                                            <?= e(qb_fmt_money($wtAmount)) ?>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($editable): ?><td></td><?php endif; ?>
                                </tr>
                            <?php endif; ?>
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
                            <?php
                                // QA #002: before a quote is accepted (no deposit stored yet)
                                // show the deposit that WILL be due, so office staff aren't left
                                // guessing — mirrors the customer-facing "Deposit on acceptance".
                                // Once accepted, deposit_amount is set and the Deposit panel
                                // below takes over, so this row disappears.
                                $predDep = (empty($quote['deposit_amount'])
                                            && !in_array((string) $quote['status'], ['declined', 'paid'], true))
                                    ? qb_predicted_deposit(db(), (int) $quote['client_id'], (float) $quote['total'])
                                    : 0.0;
                            ?>
                            <?php if ($predDep > 0): ?>
                            <tr class="totals-row">
                                <td colspan="<?= $editable ? 5 : 4 ?>" style="text-align:right;color:var(--text-faint);font-size:0.875rem">Deposit due on acceptance</td>
                                <td class="num" style="color:var(--text-faint);font-size:0.875rem"><?= e(qb_fmt_money($predDep)) ?></td>
                                <?php if ($editable): ?><td></td><?php endif; ?>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php
            // Deposit panel. Taking a deposit is an accepted-ORDER activity, so
            // it shows for any order-state quote (not just draft). The trade
            // user records what the customer ACTUALLY paid here; the tenant's
            // default-deposit setting is offered as a suggestion they can accept
            // or override.
            $depositAmount = $quote['deposit_amount'] ?? null;
            $depositPaidAt = $quote['deposit_paid_at'] ?? null;

            // Suggested deposit from tenant settings — same basis change_status
            // uses to seed on accept (percent of total, or a flat figure).
            $depositSuggestion   = null;
            $depositSuggestLabel = 'Suggested deposit';
            try {
                try {
                    $dpS = db()->prepare('SELECT default_deposit_mode, default_deposit_percent, default_deposit_flat FROM client_settings WHERE client_id = ? LIMIT 1');
                    $dpS->execute([$clientId]);
                    $dp = $dpS->fetch() ?: [];
                } catch (PDOException $e) {
                    if ($e->getCode() !== '42S22') throw $e;   // not "column missing"
                    $dpS = db()->prepare('SELECT default_deposit_percent FROM client_settings WHERE client_id = ? LIMIT 1');
                    $dpS->execute([$clientId]);
                    $dp = $dpS->fetch() ?: [];
                }
                $depTotal = (float) $quote['total'];
                if ((string) ($dp['default_deposit_mode'] ?? 'percent') === 'flat') {
                    $depositSuggestion = round(min((float) ($dp['default_deposit_flat'] ?? 0), $depTotal), 2);
                } else {
                    $pct = (float) ($dp['default_deposit_percent'] ?? 50);
                    $depositSuggestion   = round($depTotal * $pct / 100, 2);
                    $depositSuggestLabel = 'Suggested ' . rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') . '%';
                }
            } catch (Throwable $e) { /* settings missing — just skip the hint */ }

            // Pre-fill the entry box with what's already recorded, else the
            // suggestion, else leave blank.
            $depositPrefill = $depositAmount !== null
                ? (float) $depositAmount
                : ($depositSuggestion !== null ? $depositSuggestion : null);
        ?>
        <?php if ($quoteIsOrder): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Deposit</h2>
            </div>

            <?php if ($depositPaidAt): ?>
                <p style="background:#d1fae5;color:#065f46;
                          padding:0.5rem 0.75rem;border-radius:8px;
                          margin:0 0 0.75rem;font-size:0.9375rem;font-weight:600">
                    ✓ Deposit paid <?= e(qb_fmt_money((float) $depositAmount)) ?>
                    on <?= e(date('j M Y', strtotime((string) $depositPaidAt))) ?>
                </p>
                <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
                    <form method="post" action="/quote-builder/deposit.php"
                          style="display:flex;gap:0.375rem;align-items:center;margin:0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="record_paid">
                        <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                        <label style="font-size:0.8125rem;color:var(--text-faint);margin:0">Amend £</label>
                        <input type="number" name="deposit_amount" step="0.01" min="0"
                               value="<?= e(number_format((float) $depositAmount, 2, '.', '')) ?>"
                               style="width:7rem;padding:0.375rem 0.5rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit">
                        <button type="submit" class="btn btn-secondary"
                                style="padding:0.3125rem 0.75rem;font-size:0.8125rem">Save</button>
                    </form>
                    <form method="post" action="/quote-builder/deposit.php" style="margin:0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="mark_paid">
                        <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                        <button type="submit" class="btn btn-secondary"
                                style="padding:0.3125rem 0.875rem;font-size:0.8125rem">Mark unpaid</button>
                    </form>
                </div>
            <?php else: ?>
                <p style="color:var(--text-secondary);font-size:0.9375rem;margin:0 0 0.625rem">
                    Enter the deposit the customer has paid.
                </p>
                <form method="post" action="/quote-builder/deposit.php"
                      style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="record_paid">
                    <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                    <label for="dep-amt" style="font-size:0.8125rem;color:var(--text-faint);margin:0">Deposit paid £</label>
                    <input id="dep-amt" type="number" name="deposit_amount" step="0.01" min="0"
                           <?= $depositPrefill !== null ? 'value="' . e(number_format($depositPrefill, 2, '.', '')) . '"' : '' ?>
                           style="width:8rem;padding:0.375rem 0.5rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit">
                    <button type="submit" class="btn btn-primary"
                            style="padding:0.3125rem 0.875rem;font-size:0.8125rem">Record deposit paid</button>
                    <?php if ($depositSuggestion !== null): ?>
                        <span style="font-size:0.8125rem;color:var(--text-faint)">
                            <?= e($depositSuggestLabel) ?>:
                            <a href="#" onclick="document.getElementById('dep-amt').value='<?= e(number_format($depositSuggestion, 2, '.', '')) ?>';return false;"
                               style="font-weight:600;color:var(--brand);text-decoration:none"><?= e(qb_fmt_money($depositSuggestion)) ?></a>
                        </span>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php
            // Payments section — recorded against this quote. Hidden
            // entirely when the tenant doesn't have the paid Accounts
            // add-on enabled. $accountsEnabled was hoisted up earlier
            // in the file so the sticky bar can use it too.
            $paymentsList    = [];
            $paymentsTotal   = 0.0;
            $paymentsLoaded  = false;
            if ($accountsEnabled) {
                try {
                    // Exclude the deposit's own payment row — the deposit has its
                    // dedicated panel/section here, so it'd otherwise show twice.
                    // The deposit is still added to totalReceived below via
                    // $depositCounted, so the figure is unchanged.
                    $depFilter = payments_has_is_deposit() ? ' AND is_deposit = 0' : '';
                    $pStmt = db()->prepare(
                        'SELECT id, amount, received_at, method, reference, notes
                           FROM payments
                          WHERE quote_id = ? AND client_id = ?' . $depFilter . '
                       ORDER BY received_at DESC, id DESC'
                    );
                    $pStmt->execute([(int) $quote['id'], $clientId]);
                    $paymentsList   = $pStmt->fetchAll();
                    $paymentsTotal  = array_sum(array_map(
                        static fn ($p) => (float) $p['amount'], $paymentsList
                    ));
                    $paymentsLoaded = true;
                } catch (Throwable $e) {
                    // payments table missing — migration not run yet.
                }
            }
            // Outstanding = total − payments − (deposit if marked paid).
            $depositCounted   = $depositPaidAt ? (float) ($depositAmount ?? 0) : 0.0;
            $totalReceived    = round($paymentsTotal + $depositCounted, 2);
            $outstandingHere  = round((float) $quote['total'] - $totalReceived, 2);

            // Local helper — keep the template clean.
            $acctMethodLabels = [
                'cash' => 'Cash', 'card' => 'Card',
                'bank_transfer' => 'Bank transfer',
                'cheque' => 'Cheque', 'paypal' => 'PayPal',
                'stripe' => 'Stripe', 'gocardless' => 'GoCardless',
                'other' => 'Other',
            ];
        ?>
        <?php if ($paymentsLoaded): ?>
        <section class="section" id="payments" style="scroll-margin-top:4rem">
            <div class="section-header">
                <h2 class="section-title">Payments</h2>
            </div>

            <?php if ($outstandingHere > 0.0049): ?>
                <p style="background:#fef3c7;color:#92400e;
                          padding:0.5rem 0.75rem;border-radius:8px;
                          margin:0 0 0.75rem;font-size:0.9375rem;font-weight:600">
                    Outstanding: <?= e(qb_fmt_money($outstandingHere)) ?>
                    <span style="font-weight:400;color:#92400e80">
                        of <?= e(qb_fmt_money((float) $quote['total'])) ?>
                    </span>
                </p>
            <?php elseif ($outstandingHere < -0.0049): ?>
                <p style="background:#dbeafe;color:#1e40af;
                          padding:0.5rem 0.75rem;border-radius:8px;
                          margin:0 0 0.75rem;font-size:0.9375rem;font-weight:600">
                    Overpaid by <?= e(qb_fmt_money(-$outstandingHere)) ?>
                </p>
            <?php else: ?>
                <p style="background:#d1fae5;color:#065f46;
                          padding:0.5rem 0.75rem;border-radius:8px;
                          margin:0 0 0.75rem;font-size:0.9375rem;font-weight:600">
                    ✓ Fully paid (<?= e(qb_fmt_money($totalReceived)) ?>)
                </p>
            <?php endif; ?>

            <?php if ($paymentsList): ?>
                <table class="table" style="font-size:0.875rem;margin:0 0 0.75rem">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th class="num">Amount</th>
                            <?php if ($quoteIsOrder): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentsList as $p): ?>
                            <tr>
                                <td><?= e(date('j M Y', strtotime((string) $p['received_at']))) ?></td>
                                <td><?= e($acctMethodLabels[$p['method']] ?? ucfirst((string) $p['method'])) ?></td>
                                <td><?= e((string) ($p['reference'] ?? '')) ?></td>
                                <td class="num"><?= e(qb_fmt_money((float) $p['amount'])) ?></td>
                                <?php if ($quoteIsOrder): ?>
                                    <td style="white-space:nowrap">
                                        <form method="post" action="/accounts/payment_delete.php"
                                              style="display:inline;margin:0"
                                              data-confirm="Delete this payment?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <input type="hidden" name="return_to"
                                                   value="/quote-builder/edit.php?id=<?= (int) $quote['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                    style="padding:0.125rem 0.4375rem;font-size:0.75rem">
                                                ×
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($quoteIsOrder && $outstandingHere > 0.0049): ?>
                <div class="record-payment-card">
                    <h3 class="record-payment-card__title">
                        💷 Record a new payment
                    </h3>
                    <p class="record-payment-card__hint">
                        Outstanding amount pre-filled. Adjust if it's a
                        part-payment, then click <strong>Record payment</strong>.
                    </p>
                    <form method="post" action="/accounts/payment_save.php"
                          class="record-payment-card__form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                        <input type="hidden" name="return_to"
                               value="/quote-builder/edit.php?id=<?= (int) $quote['id'] ?>#payments">
                        <div class="rp-field rp-field--amount">
                            <label for="rp-amount">Amount £</label>
                            <input id="rp-amount" type="number" name="amount" step="0.01" required
                                   value="<?= e(number_format($outstandingHere, 2, '.', '')) ?>">
                        </div>
                        <div class="rp-field">
                            <label for="rp-date">Date received</label>
                            <input id="rp-date" type="date" name="received_at" required
                                   value="<?= e(date('Y-m-d')) ?>">
                        </div>
                        <div class="rp-field">
                            <label for="rp-method">Method</label>
                            <select id="rp-method" name="method">
                                <?php foreach ($acctMethodLabels as $k => $lbl): ?>
                                    <option value="<?= e($k) ?>"
                                            <?= $k === 'bank_transfer' ? 'selected' : '' ?>>
                                        <?= e($lbl) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="rp-field rp-field--ref">
                            <label for="rp-ref">Reference (optional)</label>
                            <input id="rp-ref" type="text" name="reference" maxlength="200"
                                   placeholder="e.g. cheque #, Stripe id...">
                        </div>
                        <div class="rp-submit">
                            <button type="submit" class="btn btn-primary record-payment-card__btn">
                                ✓ Record payment
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        </div><!-- /col-right -->
        </div><!-- /quote-cols -->

        <!-- ============== SEND TO CUSTOMER (full-width below the cols
             so it doesn't get buried under a tall Blinds table when the
             quote has lots of line items) ============== -->
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
            <p style="color:var(--text-faint);font-size:0.9375rem;margin:0 0 1rem">
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
                        <textarea id="send-message" name="message" rows="1"
                                  style="resize:vertical;min-height:2.5rem"
                                  placeholder="Optional — anything to add above the standard text."></textarea>
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
                    <button type="button" id="copy-public-link"
                            class="btn btn-secondary"
                            data-url="<?= e($publicUrl) ?>"
                            title="Copy the public URL to your clipboard">
                        🔗 Copy public link
                    </button>
                </div>
                <small style="display:block;color:var(--text-faint);font-size:0.8125rem;margin-top:0.625rem">
                    Public link: <code style="font-size:0.8125rem"><?= e($publicUrl) ?></code>
                </small>
            </form>
        </section>

        <script>
        (function () {
            var btn = document.getElementById('copy-public-link');
            if (!btn) return;
            var original = btn.textContent;
            function flash() {
                btn.textContent = '✓ Link copied!';
                setTimeout(function () { btn.textContent = original; }, 1800);
            }
            function fallback(u) {
                try {
                    var ta = document.createElement('textarea');
                    ta.value = u; ta.style.position = 'fixed'; ta.style.left = '-9999px';
                    document.body.appendChild(ta); ta.select();
                    document.execCommand('copy'); document.body.removeChild(ta);
                } catch (e) { /* last resort: the URL is shown below the button to copy by hand */ }
            }
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-url') || '';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(flash, function () { fallback(url); flash(); });
                } else {
                    fallback(url); flash();
                }
            });
        })();
        </script>

        <!-- ============== DANGER ZONE ==============
             Status transitions / PDF buttons now live at the TOP of
             the page (see .qb-top-actions). Only the Delete button
             remains down here — deliberately separated so it can't
             be a top-of-page misclick on a quick visit. -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title" style="color:#b91c1c">Danger zone</h2>
            </div>
            <div class="status-actions">
                <form method="post" action="/quote-builder/delete.php"
                      data-confirm="Delete quote <?= e((string) $quote['quote_number']) ?>? This is permanent — all blinds go too.">
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
    var bandSel       = document.getElementById('item-band');
    var bandRow       = document.getElementById('item-band-row');
    var fabricGroup   = document.getElementById('item-fabric-group');
    var bandLabelEl   = document.getElementById('item-band-label');
    var fabricLabelEl = document.getElementById('item-fabric-label');
    var fabricSearch  = document.getElementById('item-fabric-search');
    var fabricId      = document.getElementById('item-fabric');
    var fabricResults = document.getElementById('item-fabric-results');

    // No-fabric products (headrail/track/spares) hide the Band + Fabric
    // pickers and price on system × size alone. Set per product load.
    var requiresOption = true;
    // Width-only products (headrail/track) hide the Drop field and price
    // on width alone.
    var widthOnly = false;
    // Per-slat products (vertical fabric only) hide the WIDTH field; the
    // price is per-slat (looked up by drop) × the quantity (= slat count).
    var perSlat = false;
    // Per-m² products (shutters) keep both width + height; the live preview
    // shows the computed area.
    var perSqm = false;
    var widthIn       = document.getElementById('item-width');
    var dropIn        = document.getElementById('item-drop');
    var widthGroup    = document.getElementById('item-width-group');
    var dropGroup     = document.getElementById('item-drop-group');
    var qtyIn         = document.getElementById('item-qty');
    var qtyLabel      = document.getElementById('item-qty-label');
    var unitSel       = document.getElementById('item-unit');

    // The quote's measurement unit. Sent to the preview API so a bare
    // number is read in this unit (the server parses the same way).
    var measureUnit = <?= json_encode($measureUnit) ?>;

    // Markup vs margin for the per-blind override field. The override field is
    // shown / typed in this basis; the form + preview always send MARKUP.
    // Mirror of pricing_basis.php — keep the two formulas in lockstep.
    var QB_BASIS = <?= json_encode($pricingBasis) ?>;
    function qbM2K(m){ m = Math.min(Math.max(m, 0), 99.99); return m <= 0 ? 0 : m * 100 / (100 - m); }
    function qbK2M(k){ k = Math.max(k, 0);                  return k <= 0 ? 0 : k * 100 / (100 + k); }
    var rateOverrideIn  = document.getElementById('item-rate-override');       // basis-facing
    var rateOverrideHid = document.getElementById('item-markup-override-hidden'); // markup posted
    var discOverrideIn  = document.getElementById('item-disc-override');
    var rateOverrideHint = document.getElementById('item-rate-hint');
    // Keep the hidden markup field + hint in sync with the basis-facing input.
    function syncRateOverride() {
        if (!rateOverrideIn || !rateOverrideHid) return;
        var raw = rateOverrideIn.value.trim();
        if (raw === '' || isNaN(parseFloat(raw))) {
            rateOverrideHid.value = '';
            if (rateOverrideHint) rateOverrideHint.textContent = '';
            return;
        }
        var typed = parseFloat(raw);
        var markup = QB_BASIS === 'margin' ? qbM2K(typed) : typed;
        rateOverrideHid.value = String(Math.round(markup * 100) / 100);
        if (rateOverrideHint) {
            rateOverrideHint.textContent = QB_BASIS === 'margin'
                ? '≈ ' + (Math.round(markup * 100) / 100).toFixed(2) + '% markup'
                : '≈ ' + (Math.round(qbK2M(markup) * 100) / 100).toFixed(2) + '% margin';
        }
    }

    // Changing the unit persists it to the quote and reloads so every
    // already-listed size re-renders in the new unit consistently.
    if (unitSel) {
        unitSel.addEventListener('change', function () {
            var f = document.createElement('form');
            f.method = 'post';
            f.action = '/quote-builder/set_unit.php';
            f.innerHTML =
                '<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">'
              + '<input type="hidden" name="quote_id" value="<?= (int) $id ?>">'
              + '<input type="hidden" name="unit" value="' + unitSel.value + '">';
            document.body.appendChild(f);
            f.submit();
        });
    }
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

    // Show / hide the Band + Fabric pickers based on requiresOption,
    // and the Drop field based on widthOnly.
    function applyFabricVisibility() {
        if (bandRow)     bandRow.style.display     = requiresOption ? '' : 'none';
        if (fabricGroup) fabricGroup.style.display = requiresOption ? '' : 'none';
        if (fabricId) {
            fabricId.required = requiresOption;
            if (!requiresOption) fabricId.value = '';
        }
        if (!requiresOption) clearFabric();

        if (dropGroup) dropGroup.style.display = widthOnly ? 'none' : '';
        if (dropIn) {
            dropIn.required = !widthOnly;
            if (widthOnly) dropIn.value = '';   // priced on width alone
        }

        // Per-slat: hide width; quantity is the slat count.
        if (widthGroup) widthGroup.style.display = perSlat ? 'none' : '';
        if (widthIn) {
            widthIn.required = !perSlat;
            if (perSlat) widthIn.value = '';
        }
        if (qtyLabel) qtyLabel.textContent = perSlat ? 'Number of slats' : 'Quantity';
    }

    async function loadProductData() {
        productData = null;
        clearFabric();
        closeFabricResults();
        resetBand();
        if (!productSel.value) {
            setIdle(systemSel, 'Choose product first');
            if (fabricLabelEl) fabricLabelEl.textContent = 'Fabric';
            if (bandLabelEl)   bandLabelEl.textContent = 'Band';
            fabricSearch.disabled = true;
            fabricSearch.placeholder = 'Choose product first';
            extrasWrap.style.display = 'none';
            extrasBox.innerHTML = '';
            requiresOption = true;   // restore default pickers
            widthOnly = false;       // restore drop field
            perSlat = false;         // restore width field
            applyFabricVisibility();
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
                                + encodeURIComponent(productSel.value)
                                + '&_=' + Date.now(),   // cache-buster: defeat any edge page-cache
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

            // Band filter dropdown — scoped to the (default) selected
            // system so it shows that system's bands only. "All bands"
            // keeps the unfiltered behaviour; picking a band narrows the
            // fabric typeahead via the search API's &band= param.
            populateBands(bandsForCurrentSystem());

            // Per-product wording for the "fabric" axis — "Slat" for
            // wood venetians, "Colour" for metal, etc. Falls back to
            // "Fabric". Drives the field label + the search placeholder.
            var optLabel = (productData.product && productData.product.option_label) || 'Fabric';
            if (fabricLabelEl) fabricLabelEl.textContent = optLabel;
            // Per-product label for the band step (e.g. "Tape / String").
            var bandLabel = (productData.product && productData.product.band_label) || 'Band';
            if (bandLabelEl) bandLabelEl.textContent = bandLabel;

            // No-fabric product? Hide the Band + Fabric pickers entirely
            // and drop the "required" guard on the hidden option_id, so
            // the line prices on system × size alone.
            requiresOption = !productData.product
                          || productData.product.requires_option !== false;
            widthOnly = !!(productData.product && productData.product.width_only === true);
            perSlat   = !!(productData.product && productData.product.price_per_slat === true);
            perSqm    = !!(productData.product && productData.product.price_per_sqm === true);
            applyFabricVisibility();

            // Fabric typeahead — enable input. Picking happens via the
            // floating results panel populated from /api/fabrics-search.
            fabricSearch.disabled    = false;
            fabricSearch.placeholder = 'Type to search ' + optLabel.toLowerCase() + 's (or click for recent)';

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
            // Include the currently-selected system so the API can
            // filter out fabrics scoped to a different system. The
            // typical metal-venetian case: Standard slats and Special
            // slats have separate colour ranges; picking a system
            // narrows the dropdown to just the matching range.
            // System-less fabrics always show.
            var sysQ = systemSel && systemSel.value
                ? '&system_id=' + encodeURIComponent(systemSel.value)
                : '';
            // Band filter — narrows results to one price band when the
            // salesperson has picked one from the Band dropdown.
            var bandQ = bandSel && bandSel.value
                ? '&band=' + encodeURIComponent(bandSel.value)
                : '';
            var r = await fetch('/quote-builder/api/fabrics-search.php'
                + '?product_id=' + encodeURIComponent(productSel.value)
                + '&q='          + encodeURIComponent(query || '')
                + sysQ
                + bandQ
                + '&_=' + Date.now(),
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
        // Show just the colour — the band is already chosen in the
        // Tape/String step above, so repeating "Band X" on every row is
        // noise. (data-band is still kept for downstream choice filtering.)
        var html = '';
        items.forEach(function (f) {
            var meta = [];
            if (f.supplier) meta.push(escapeHtml(f.supplier));
            if (f.code)     meta.push('Code ' + escapeHtml(f.code));
            // data-label = what lands in the input on pick (colour only).
            var pickLabel = f.name + (f.colour ? ' / ' + f.colour : '');
            html += '<div class="frow" data-id="' + f.id + '" data-label="' + escapeAttr(pickLabel) + '"'
                  +    ' data-band="' + escapeAttr(f.band || '') + '">'
                  +    '<div class="fname">'
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
                pickFabric(row.dataset.id, row.dataset.label, row.dataset.band || '');
            });
        });
    }

    // Tracked separately from the hidden id so band-scoped choice
    // filtering (e.g. tape colour available only on FW 50mm) doesn't
    // need to re-fetch the fabric every time. Lower-cased here so
    // the compare against choice.bands[] is case-insensitive.
    var currentFabricBand = '';

    function pickFabric(id, label, band) {
        fabricId.value = String(id);
        fabricSearch.value = label;
        currentFabricBand = (band || '').toLowerCase();
        closeFabricResults();
        // Re-render extras: band-scoped choices appear / disappear
        // for this fabric. Same as a system change.
        renderExtras();
        schedulePreview();
    }

    function clearFabric() {
        fabricId.value = '';
        fabricSearch.value = '';
        currentFabricBand = '';
    }

    // Band filter helpers — the dropdown is UI-only (it just constrains
    // the fabric search), so resetting/populating it never touches the
    // submitted form values.
    function resetBand() {
        if (!bandSel) return;
        bandSel.innerHTML = '<option value="">All bands</option>';
        bandSel.disabled  = true;
    }
    // Bands for the currently-selected system — falls back to the flat
    // product-wide list when no system is picked or the product has no
    // per-system breakdown. This is what makes the dropdown show, say,
    // the 50mm system's bands (incl. gloss) and not the 35mm system's.
    function bandsForCurrentSystem() {
        var sid = (systemSel && systemSel.value) ? String(systemSel.value) : '';
        var bbs = productData && productData.bandsBySystem;
        if (sid && bbs && bbs[sid]) return bbs[sid];
        return (productData && productData.bands) || [];
    }

    function populateBands(bands) {
        if (!bandSel) return;
        var prev = bandSel.value;
        var opts = '<option value="">All bands</option>';
        bands.forEach(function (b) {
            opts += '<option value="' + escapeAttr(b) + '">' + escapeHtml(b) + '</option>';
        });
        bandSel.innerHTML = opts;
        bandSel.disabled  = bands.length === 0;
        // Selection: keep the prior pick if it's still valid; else if the
        // system maps to exactly ONE band, auto-select it (a system with a
        // single price list shouldn't need a manual pick); otherwise
        // default to "All bands".
        if (prev && bands.indexOf(prev) !== -1) {
            bandSel.value = prev;
        } else if (bands.length === 1) {
            bandSel.value = bands[0];
        } else {
            bandSel.value = '';
        }
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
        // Three flavours captured under different keys:
        //   preset[<eid>]         — single-pick string value
        //   preset[<eid>__multi]  — array of choice_ids for multi-pick
        //   preset[<eid>__uv]     — typed user_value (length input)
        var preset = {};
        extrasBox.querySelectorAll('[data-extra-id]').forEach(function (div) {
            var eid = parseInt(div.getAttribute('data-extra-id'), 10);
            var sel = div.querySelector('select');
            if (sel) preset[eid] = sel.value;

            // Multi-pick: tick-boxes carry data-multi-choice="<id>".
            var multiBoxes = div.querySelectorAll('input[data-multi-choice]');
            if (multiBoxes.length) {
                var picked = [];
                multiBoxes.forEach(function (cb) {
                    if (cb.checked) picked.push(parseInt(cb.dataset.multiChoice, 10));
                });
                preset[eid + '__multi'] = picked;
            }

            var uvIn = div.querySelector('input[data-uv-for="' + eid + '"]');
            if (uvIn) preset[eid + '__uv'] = uvIn.value;

            // Per-choice number inputs (data-cuv-for="<choiceId>") — keyed by
            // choice so a re-render (e.g. ticking another box) doesn't wipe a
            // value the salesperson already typed against a different choice.
            div.querySelectorAll('input[data-cuv-for]').forEach(function (cin) {
                preset[eid + '__cuv__' + cin.getAttribute('data-cuv-for')] = cin.value;
            });
        });

        var systemId = parseInt(systemSel.value, 10) || 0;

        // ----- Relationships: which extra owns which choice id, which
        //       extras are children of which "owner" extra. Children sit
        //       inside their owner's grid cell — keeps the visual
        //       parent → child relationship obvious.
        var choiceToExtra = {};
        productData.extras.forEach(function (e) {
            e.choices.forEach(function (c) { choiceToExtra[c.id] = e.id; });
        });
        var childrenOf = {};
        productData.extras.forEach(function (e) {
            var parents = e.parent_choice_ids || [];
            if (parents.length === 0) return;
            var ownerId = null;
            for (var i = 0; i < parents.length; i++) {
                if (choiceToExtra[parents[i]] !== undefined) {
                    ownerId = choiceToExtra[parents[i]];
                    break;
                }
            }
            if (ownerId === null) return;
            if (!childrenOf[ownerId]) childrenOf[ownerId] = [];
            if (childrenOf[ownerId].indexOf(e.id) === -1) {
                childrenOf[ownerId].push(e.id);
            }
        });

        // What choices are currently selected on an extra? Returns an
        // ARRAY of choice_ids (as integers) — single-pick extras give
        // 0 or 1 entries, multi-pick extras can give any number.
        //
        // Falls back to the extra's defaults for the current system if
        // nothing's been picked yet — covers the first-paint case where
        // the DOM hasn't been written and preset is empty.
        // Single source of truth for choice visibility. Three filters:
        //   - system_id: null/undefined = "all systems", else exact match
        //   - fabrics:   []    = "every fabric",         else the picked
        //                fabric's id must be in the list. Finer than bands,
        //                for restrictions a band can't express — 38mm slat
        //                on Snow and Cool White only, where Snow shares its
        //                band with ten colours that don't offer it.
        //   - bands:     []    = "all bands",            else case-
        //                insensitive membership against the currently-
        //                picked fabric's band. With no fabric picked
        //                yet, band-scoped choices stay hidden — showing
        //                them risks the salesperson committing a pick
        //                that becomes invalid the moment they pick a
        //                fabric on the wrong band. Fabric scoping hides for
        //                the same reason.
        function choiceAvailable(c) {
            if (c.system_id !== null && c.system_id !== undefined && c.system_id !== systemId) return false;

            var fabrics = c.fabrics || [];
            if (fabrics.length > 0) {
                var picked = parseInt(fabricId && fabricId.value, 10) || 0;
                if (!picked) return false;
                var hit = false;
                for (var j = 0; j < fabrics.length; j++) {
                    if (parseInt(fabrics[j], 10) === picked) { hit = true; break; }
                }
                if (!hit) return false;
            }

            var bands = c.bands || [];
            if (bands.length === 0) return true;
            if (!currentFabricBand) return false;
            for (var i = 0; i < bands.length; i++) {
                if (String(bands[i]).toLowerCase() === currentFabricBand) return true;
            }
            return false;
        }

        // What is actually selected on an option right now.
        //
        // A preset is only honoured while the choice it names is STILL
        // available. Change the fabric and a previous pick can stop being
        // offered — swap a 63mm-capable colour for one that's 50mm only and
        // the old 63mm pick is no longer on the menu. Left in place it stays
        // silently "selected", so anything gated on that option (Tape only
        // appears at 50mm) sees the stale id and vanishes, taking the rest of
        // the form with it. Treat it as stale and fall back to the default.
        function isChoiceStillOffered(extra, id) {
            return !!extra.choices.find(function (c) {
                return c.id === id && choiceAvailable(c);
            });
        }

        function effectiveChoiceIds(extra) {
            // Multi-pick: the multi preset is an array of ids. Drop any that
            // are no longer offered; keep the rest of the customer's picks.
            if (extra.allow_multi) {
                var multi = preset[extra.id + '__multi'];
                if (multi !== undefined) {
                    var kept = multi.filter(function (id) {
                        return isChoiceStillOffered(extra, parseInt(id, 10));
                    });
                    // If a fabric change stripped every pick from a REQUIRED
                    // option, fall back to whatever defaults the new fabric does
                    // offer — mirrors the single-pick path, so a required option
                    // never ends up holding nothing.
                    if (kept.length === 0 && extra.is_required) {
                        return extra.choices.filter(function (c) {
                            return choiceAvailable(c) && c.is_default;
                        }).map(function (c) { return c.id; });
                    }
                    return kept;
                }
                // First paint — pick all available defaults. Defaults
                // tagged to a band the customer hasn't picked don't
                // auto-select.
                return extra.choices.filter(function (c) {
                    return choiceAvailable(c) && c.is_default;
                }).map(function (c) { return c.id; });
            }
            // Single-pick — single preset value (string).
            if (preset[extra.id] !== undefined) {
                var v  = preset[extra.id];
                var id = v ? parseInt(v, 10) : 0;
                if (!id) return [];                                  // deliberately cleared
                if (isChoiceStillOffered(extra, id)) return [id];
                // Stale — fall through and re-default below.
            }
            var def = extra.choices.find(function (c) {
                return choiceAvailable(c) && c.is_default;
            });
            if (def) return [def.id];
            // No default offered for this fabric. For a REQUIRED option take the
            // first that is, so it is never left silently holding nothing — the
            // salesperson can still change it. An optional one stays empty
            // rather than quietly adding a priced choice nobody asked for.
            if (extra.is_required) {
                var first = extra.choices.find(function (c) { return choiceAvailable(c); });
                if (first) return [first.id];
            }
            return [];
        }

        // Backwards-compat wrapper — some callers want a single value.
        // Returns the first selected choice id as a string, or "".
        function effectiveChoiceId(extra) {
            var ids = effectiveChoiceIds(extra);
            return ids.length ? String(ids[0]) : '';
        }

        function isVisible(extra) {
            var parents = extra.parent_choice_ids || [];
            if (parents.length > 0) {
                // Which parent choice ids are currently selected anywhere?
                var selected = {};
                productData.extras.forEach(function (other) {
                    if (other.id === extra.id) return;
                    effectiveChoiceIds(other).forEach(function (id) { selected[id] = true; });
                });
                if (extra.parent_match_all) {
                    // AND across DISTINCT parent options (OR within one):
                    // group parent ids by the option that owns them, and
                    // require every group to have a selected member. Lets a
                    // sub-option need e.g. Control = Wand AND Headrail = Vogue.
                    var groups = {};
                    parents.forEach(function (pid) {
                        var owner = choiceToExtra[pid];
                        if (owner === undefined) owner = '_orphan';
                        (groups[owner] = groups[owner] || []).push(pid);
                    });
                    var allOk = Object.keys(groups).every(function (owner) {
                        return groups[owner].some(function (pid) { return selected[pid]; });
                    });
                    if (!allOk) return false;
                } else {
                    // Default OR: any one selected parent choice is enough.
                    var anyOk = parents.some(function (pid) { return selected[pid]; });
                    if (!anyOk) return false;
                }
            }
            // Number-only option (a measurement input, no choices): there's
            // nothing for choiceAvailable to match, but it should still show
            // once any parent gate above has passed.
            if ((extra.choices || []).length === 0 && extra.length_input_label) return true;
            return extra.choices.some(choiceAvailable);
        }

        // Render a single extra's inner HTML (label + picker + optional
        // thumbnail). Doesn't include the outer .extra-cell wrapper —
        // caller decides whether this is a top-level cell or nested inside
        // its parent. Two flavours of picker:
        //   - single-pick (default): a <select>
        //   - multi-pick (allow_multi=1): a list of tick-boxes
        function renderOne(extra, isChild) {
            var idx = productData.extras.indexOf(extra);
            var visibleChoices = extra.choices.filter(choiceAvailable);
            var selectedThumb = null;

            // A number-capturing option with NO choices to pick is just a
            // number input — emit no picker at all. An empty dropdown is
            // useless, and a hidden *required* <select> with no option would
            // block the whole form from submitting. Keyed on genuinely having
            // no choices (matches the server + isVisible), not on choices
            // being filtered out by band/system.
            var numberOnly = !!extra.length_input_label && (extra.choices || []).length === 0;

            // Per-choice number input (choice.length_input_label). Rendered
            // next to a chosen choice so e.g. each offset side or a mid-rail
            // can capture its own mm value. Keyed by choice id; the value
            // survives re-renders via the preset map. Spec only — no price.
            function choiceNumberInput(c) {
                if (!c.length_input_label) return '';
                var pv  = preset[extra.id + '__cuv__' + c.id];
                var val = (pv !== undefined && pv !== null) ? String(pv) : '';
                return '<div class="choice-user-value" style="margin-top:0.25rem">'
                     + '<label style="display:block;font-size:0.7rem;font-weight:600;'
                     + 'color:var(--text-faint);text-transform:uppercase;letter-spacing:0.05em;'
                     + 'margin-bottom:0.125rem">' + escapeHtml(c.length_input_label) + '</label>'
                     + '<input type="number" min="0" step="1"'
                     + ' name="extras[' + idx + '][choice_user_values][' + c.id + ']"'
                     + ' value="' + escapeAttr(val) + '"'
                     + ' data-cuv-for="' + c.id + '"'
                     + ' style="width:100%;max-width:12rem;padding:0.3125rem 0.5rem;'
                     + 'border:1px solid var(--border-strong);border-radius:6px;font:inherit">'
                     + '</div>';
            }

            var out = '<div data-extra-id="' + extra.id + '"'
                    + (isChild ? ' class="extra-child"' : '')
                    + '>';
            out += '<label>' + escapeHtml(extra.name)
                 + (extra.is_required ? ' <span style="color:#b91c1c">*</span>' : '')
                 + '</label>';
            out += '<input type="hidden" name="extras[' + idx + '][extra_id]" value="' + extra.id + '">';

            if (numberOnly) {
                // ---------- Number-only option ----------
                // No choices to pick — the number input rendered below is the
                // entire control. Nothing to emit here.
            } else if (extra.allow_multi) {
                // ---------- Multi-pick tick-box list ----------
                //
                // Each ticked choice becomes its own quote_item_extras
                // row at save time. Submitted via
                //   extras[<idx>][choice_ids][] = <choice_id>
                // for each ticked box. add_item.php fans these out into
                // multiple pricing-engine entries that share extra_id.
                var presetMulti = preset[extra.id + '__multi'];
                var preselectIds = null;
                if (Array.isArray(presetMulti)) {
                    // Re-render: respect the user's exact ticks (incl.
                    // "all unticked" → no default backfill).
                    preselectIds = presetMulti.slice();
                } else {
                    // First paint — tick whichever choices are defaults.
                    preselectIds = visibleChoices.filter(function (c) {
                        return c.is_default;
                    }).map(function (c) { return c.id; });
                }

                out += '<div class="multi-choice-list"'
                     + ' style="display:flex;flex-direction:column;gap:0.3125rem;'
                     + 'padding:0.4375rem 0.5rem;background:#fff;'
                     + 'border:1px solid var(--border-strong);border-radius:8px">';
                visibleChoices.forEach(function (c) {
                    var ticked = preselectIds.indexOf(c.id) !== -1;
                    if (ticked && c.image_url) selectedThumb = c.image_url;
                    // Each row = the tick-box label, plus this choice's own
                    // number box underneath when it's ticked (re-render on
                    // tick reveals/hides it).
                    out += '<div class="multi-choice-row">'
                         + '<label style="display:inline-flex;align-items:center;gap:0.5rem;cursor:pointer;font-weight:400">'
                         + '<input type="checkbox"'
                         + ' name="extras[' + idx + '][choice_ids][]"'
                         + ' value="' + c.id + '"'
                         + ' data-multi-choice="' + c.id + '"'
                         + (ticked ? ' checked' : '')
                         + '>'
                         + ' ' + escapeHtml(c.label)
                         + '</label>'
                         + (ticked ? choiceNumberInput(c) : '')
                         + '</div>';
                });
                out += '</div>';
            } else {
                // ---------- Single-pick <select> (original) ----------
                var presetVal = preset[extra.id];
                var hasDefault = visibleChoices.some(function (c) { return c.is_default; });

                // Input-only option: a single (carrier) choice paired with a
                // measurement input (e.g. "Fit height" in mm) needs no visible
                // dropdown — hide the auto-selected select, show just the input.
                var hideSelect = !!extra.length_input_label && visibleChoices.length === 1;

                out += '<select name="extras[' + idx + '][choice_id]"'
                     + (extra.is_required ? ' required' : '')
                     + (hideSelect ? ' style="display:none" aria-hidden="true"' : '') + '>';
                // Placeholder "Select" option — only useful when there's no
                // default to fall back to. If a default exists, it IS the
                // natural pick and an extra escape-hatch just adds noise
                // (and lets them ship a Bottom Weight with no Colour, which
                // is silly).
                if (!hasDefault && !hideSelect) {
                    out += '<option value=""'
                         + (presetVal === '' ? ' selected' : '')
                         + '>— Select —</option>';
                }
                var selChoice = null;
                visibleChoices.forEach(function (c) {
                    var isSelected;
                    if (hideSelect) {
                        isSelected = true;   // only choice — force-select so it's applied
                    } else if (presetVal !== undefined && presetVal !== '') {
                        isSelected = String(c.id) === presetVal;
                    } else if (presetVal === '') {
                        isSelected = false;
                    } else {
                        isSelected = c.is_default;
                    }
                    if (isSelected) selChoice = c;
                    if (isSelected && !hideSelect && c.image_url) selectedThumb = c.image_url;
                    out += '<option value="' + c.id + '"'
                         + (isSelected ? ' selected' : '') + '>' + escapeHtml(c.label) + '</option>';
                });
                out += '</select>';
                // The selected choice's own number box (if it asks for one).
                if (selChoice) out += choiceNumberInput(selChoice);
            }

            if (selectedThumb) {
                out += '<img class="choice-thumb" src="' + escapeAttr(selectedThumb)
                     + '" alt="" loading="lazy">';
            }

            // length_input_label set on this extra → render a number
            // input next to the picker. The salesperson types e.g. 1230
            // (mm) for a wand length and it's submitted alongside the
            // chosen choice_id. Spec only — doesn't change price.
            if (extra.length_input_label) {
                var presetUv = preset[extra.id + '__uv'];
                var uvValue  = presetUv !== undefined && presetUv !== null && presetUv !== ''
                    ? String(presetUv) : '';
                out += '<div class="extra-user-value" style="margin-top:0.375rem">'
                     + '<label style="display:block;font-size:0.75rem;font-weight:600;'
                     + 'color:var(--text-faint);text-transform:uppercase;letter-spacing:0.05em;'
                     + 'margin-bottom:0.1875rem">'
                     + escapeHtml(extra.length_input_label) + '</label>'
                     + '<input type="number" min="0" step="1"'
                     + ' name="extras[' + idx + '][user_value]"'
                     + ' value="' + escapeAttr(uvValue) + '"'
                     + (numberOnly && extra.is_required ? ' required' : '')
                     + ' data-uv-for="' + extra.id + '"'
                     + ' style="width:100%;padding:0.375rem 0.5rem;'
                     + 'border:1px solid var(--border-strong);border-radius:6px;font:inherit">'
                     + '</div>';
            }

            out += '</div>';
            return out;
        }

        // Walk a top-level visible extra and recursively append visible
        // children inside the same grid cell. Limits depth defensively.
        function renderTreeInto(extra, depth) {
            if (depth > 4) return '';   // defensive — schema doesn't loop, but JS shouldn't either
            var out = renderOne(extra, depth > 0);
            (childrenOf[extra.id] || []).forEach(function (cid) {
                var child = productData.extras.find(function (e) { return e.id === cid; });
                if (!child || !isVisible(child)) return;
                out += renderTreeInto(child, depth + 1);
            });
            return out;
        }

        var html = '';
        var anyVisible = false;
        productData.extras.forEach(function (extra) {
            var parents = extra.parent_choice_ids || [];
            if (parents.length > 0) return;   // children handled recursively
            if (!isVisible(extra)) return;
            anyVisible = true;
            html += '<div class="extra-cell">' + renderTreeInto(extra, 0) + '</div>';
        });

        if (anyVisible) {
            extrasBox.innerHTML = html;
            extrasWrap.style.display = '';
        } else {
            // Options are band-scoped, so they only appear once a fabric
            // is picked. Rather than silently hiding the whole section
            // (which reads as "this product has no options"), show a
            // short hint when that's the reason.
            var hasBandScoped = productData.extras.some(function (e) {
                return (e.choices || []).some(function (c) { return (c.bands || []).length > 0; });
            });
            var optLbl = ((productData.product && productData.product.option_label) || 'fabric').toLowerCase();
            if (!currentFabricBand && hasBandScoped) {
                extrasBox.innerHTML = '<div class="item-extras">Pick a ' + escapeHtml(optLbl)
                                    + ' above to see its options.</div>';
                extrasWrap.style.display = '';
            } else {
                extrasBox.innerHTML = '';
                extrasWrap.style.display = 'none';
            }
        }

        // Re-bind change listeners on the choice pickers so conditional
        // extras can re-render when their parent's value changes. Both
        // <select>s (single-pick) and the multi-choice tick-boxes need
        // listeners; ticking/unticking a multi-pick parent affects
        // which children are visible.
        extrasBox.querySelectorAll('select').forEach(function (sel) {
            sel.addEventListener('change', function () {
                renderExtras();
                schedulePreview();
            });
        });
        extrasBox.querySelectorAll('input[data-multi-choice]').forEach(function (cb) {
            cb.addEventListener('change', function () {
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
            // Re-scope the band dropdown to the saved system so the
            // saved band below resolves to a real option.
            if (bandSel) populateBands(bandsForCurrentSystem());
        }
        if (initial.option_id && initial.fabric_label) {
            fabricSearch.value = initial.fabric_label;
            fabricId.value = String(initial.option_id);
            // Restore the band too — drives band-scoped choice
            // visibility on edit of an existing item. Without this,
            // re-opening a saved quote that picked a band-scoped tape
            // colour would hide the option, losing the selection.
            currentFabricBand = (initial.fabric_band || '').toLowerCase();
            // Mirror the saved band into the filter dropdown so a
            // re-search starts narrowed to it (falls back to "All
            // bands" if the code no longer matches an option).
            if (bandSel && initial.fabric_band) bandSel.value = initial.fabric_band;
        }
        // Three-pass set-then-render: covers single-level conditional nesting
        // (which is all the schema currently supports). Each pass sets values
        // for extras that exist in the DOM and re-renders to reveal newly-
        // unlocked conditional ones.
        // Apply each saved extra. Handles both:
        //   single-pick — set the <select> value to ex.choice_id
        //   multi-pick  — tick the checkbox matching ex.choice_id
        // The saved extras array may have MULTIPLE entries with the
        // same extra_id (one per ticked choice on a multi-pick option);
        // each gets applied independently.
        function applyOneExtra(ex) {
            // Multi-pick checkbox?
            var cb = document.querySelector(
                '[data-extra-id="' + ex.extra_id + '"] input[data-multi-choice="' + ex.choice_id + '"]'
            );
            if (cb) {
                cb.checked = true;
            } else {
                var sel = document.querySelector(
                    '[data-extra-id="' + ex.extra_id + '"] select'
                );
                if (sel) sel.value = String(ex.choice_id);
            }
            if (ex.user_value !== undefined && ex.user_value !== null) {
                // Group-level box (extra-scoped)...
                var uvIn = document.querySelector(
                    'input[data-uv-for="' + ex.extra_id + '"]'
                );
                if (uvIn) uvIn.value = String(ex.user_value);
                // ...and the per-choice box (choice-scoped), if this choice
                // has one. Only exists once the choice is ticked/selected and
                // re-rendered — the multi-pass apply loop fills it in.
                if (ex.choice_id) {
                    var cuvIn = document.querySelector(
                        '[data-extra-id="' + ex.extra_id + '"] input[data-cuv-for="' + ex.choice_id + '"]'
                    );
                    if (cuvIn) cuvIn.value = String(ex.user_value);
                }
            }
        }

        for (var pass = 0; pass < 3; pass++) {
            (initial.extras || []).forEach(applyOneExtra);
            renderExtras();
        }
        // Final pass after the last render — renderExtras' sticky preset
        // already applies the values, but a defensive pass costs nothing.
        (initial.extras || []).forEach(applyOneExtra);
        schedulePreview();
    }

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(runPreview, 250);
    }

    // Emit one record per (extra, choice). For multi-pick extras with
    // N ticked boxes, that's N records all sharing extra_id. The
    // pricing engine + save handler iterate the array and naturally
    // handle this without further special-casing.
    function collectExtras() {
        var out = [];
        var divs = extrasBox.querySelectorAll('[data-extra-id]');
        divs.forEach(function (div) {
            var eid = parseInt(div.getAttribute('data-extra-id'), 10);
            if (eid <= 0) return;

            // Group-level user_value — present when the EXTRA has a
            // length_input_label (one box shared across ticked choices).
            var uvIn = div.querySelector('input[data-uv-for="' + eid + '"]');
            var userValue = null;
            if (uvIn && uvIn.value !== '') {
                var uv = parseFloat(uvIn.value);
                if (uv > 0) userValue = uv;
            }

            // Per-choice value — present when a CHOICE has its own number box.
            // It wins over the group-level value for that choice. Returns the
            // record's user_value (number) or null.
            function valueFor(cid) {
                var cin = div.querySelector('input[data-cuv-for="' + cid + '"]');
                if (cin && cin.value !== '') {
                    var v = parseFloat(cin.value);
                    if (v > 0) return v;
                }
                return userValue;
            }

            // Multi-pick: one record per ticked checkbox.
            var multiBoxes = div.querySelectorAll('input[data-multi-choice]');
            if (multiBoxes.length) {
                multiBoxes.forEach(function (cb) {
                    if (!cb.checked) return;
                    var cid = parseInt(cb.dataset.multiChoice, 10);
                    if (cid > 0) {
                        var rec = { extra_id: eid, choice_id: cid };
                        var val = valueFor(cid);
                        if (val !== null) rec.user_value = val;
                        out.push(rec);
                    }
                });
                return;
            }

            // Single-pick: read the <select>'s value.
            var sel = div.querySelector('select');
            if (!sel) {
                // No picker at all = a number-only option. Emit a record
                // carrying just the typed value (no choice_id) so the engine
                // can snapshot it onto the quote line.
                if (uvIn) {
                    var nrec = { extra_id: eid };
                    if (userValue !== null) nrec.user_value = userValue;
                    out.push(nrec);
                }
                return;
            }
            var cid = parseInt(sel.value, 10);
            if (cid > 0) {
                var rec = { extra_id: eid, choice_id: cid };
                var val = valueFor(cid);
                if (val !== null) rec.user_value = val;
                out.push(rec);
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
        if (!productSel.value)                   missing.push('product');
        if (requiresOption && !fabricId.value)   missing.push('fabric');
        if (!perSlat && !widthIn.value.trim())   missing.push('width');
        if (!widthOnly && !dropIn.value.trim())  missing.push('drop');
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
            round_up:   '1',
            unit:       measureUnit
        });
        // Per-blind override (cost-viewers only — fields absent otherwise).
        // markup_override is the hidden, already-converted MARKUP value;
        // discount needs no conversion. Sent only when non-empty so a blank
        // field falls through to the product's resolved rate.
        if (rateOverrideHid && rateOverrideHid.value !== '') {
            params.append('markup_override', rateOverrideHid.value);
        }
        if (discOverrideIn && discOverrideIn.value.trim() !== '') {
            params.append('discount_override', discOverrideIn.value.trim());
        }
        collectExtras().forEach(function (ex, i) {
            params.append('extras[' + i + '][extra_id]',  ex.extra_id);
            // choice_id is omitted for number-only options (no choices) so
            // the server can tell them apart from an unpicked dropdown.
            if (ex.choice_id !== undefined) {
                params.append('extras[' + i + '][choice_id]', ex.choice_id);
            }
            // Optional user-typed length/spec value — only present when
            // the extra has a length_input_label. Server-side pricing
            // engine snapshots it onto quote_item_extras.user_value.
            if (ex.user_value !== undefined) {
                params.append('extras[' + i + '][user_value]', ex.user_value);
            }
        });

        try {
            params.append('_', Date.now());   // cache-buster
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
            // Headline the line TOTAL in bold; per-unit price as subtext.
            // Noun follows the product type — slats for per-slat products.
            var noun   = perSlat ? 'slat'  : 'blind';
            var nounPl = perSlat ? 'slats' : 'blinds';
            var bits;
            if (qty > 1) {
                bits = ['<strong>£' + total + '</strong> for ' + qty + ' ' + nounPl, '£' + unit + ' each'];
            } else {
                bits = ['<strong>£' + unit + '</strong> per ' + noun];
            }
            if (perSqm && data.width_mm && data.drop_mm) {
                bits.push(((data.width_mm / 1000) * (data.drop_mm / 1000)).toFixed(2) + ' m²');
            }
            bits.push('base £' + Number(data.base_price).toFixed(2));
            if (data.extras_total > 0) bits.push('+ extras £' + Number(data.extras_total).toFixed(2));
            <?php if ($isAdmin || $_perms['can_view_costs']): ?>
            // Internal-cost breakdown (markup % / discount %) — visible
            // only to admins and users with can_view_costs. Hidden from
            // fitters etc. who would otherwise see the trade margin.
            if (data.markup_percent > 0) {
                // Show in the tenant's basis (markup stored; margin = k·100/(100+k)).
                var _mk = Number(data.markup_percent);
                <?php if ($pricingBasis === 'margin'): ?>
                bits.push('margin ' + (_mk <= 0 ? 0 : _mk * 100 / (100 + _mk)).toFixed(2) + '%');
                <?php else: ?>
                bits.push('markup ' + _mk.toFixed(2) + '%');
                <?php endif; ?>
            }
            if (data.discount_percent > 0) bits.push('discount ' + Number(data.discount_percent).toFixed(2) + '%');
            <?php endif; ?>
            // Rounded-up cell size used to be shown here ("rounded up
            // to 1600 × 2000 mm") — trade users found it noisy / not
            // actionable, since the engine always rounds up to the
            // nearest price-table cell and that's expected behaviour.
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
    systemSel.addEventListener('change', function () {
        // Re-scope the band filter to the new system (its band set may
        // differ — e.g. 50mm has gloss tiers the 35mm system doesn't).
        if (bandSel) populateBands(bandsForCurrentSystem());
        renderExtras();
        // Picking a different system can change the available fabrics
        // (system-scoped ones may drop in/out). Clear the current pick
        // so the salesperson re-confirms a valid one for the new
        // system rather than carrying a stale invalid id.
        if (fabricId.value) {
            clearFabric();
            closeFabricResults();
        }
        schedulePreview();
    });
    if (bandSel) {
        bandSel.addEventListener('change', function () {
            // Changing the band invalidates the current fabric pick (it
            // may belong to a different band). Clear it, then reopen the
            // typeahead narrowed to the chosen band so the matching
            // fabrics show straight away.
            if (fabricId.value) clearFabric();
            renderExtras();
            schedulePreview();
            if (productSel.value) {
                fabricSearch.focus();
                searchFabrics('');
            }
        });
    }
    qtyIn.addEventListener('change', schedulePreview);
    [widthIn, dropIn].forEach(function (el) {
        el.addEventListener('input', schedulePreview);
    });
    // Per-blind pricing override (cost-viewers only). Keep the hidden markup
    // field in sync with the basis-facing input, then re-price live.
    if (rateOverrideIn) {
        rateOverrideIn.addEventListener('input', function () { syncRateOverride(); schedulePreview(); });
    }
    if (discOverrideIn) {
        discOverrideIn.addEventListener('input', schedulePreview);
    }
    syncRateOverride(); // paint hint + hidden value from any pre-filled override

    // Fabric typeahead listeners.
    // What to search when the box is merely REOPENED (focus / click) rather
    // than typed into.
    //
    // pickFabric() writes the chosen fabric's label into this same box, so
    // once something is picked the text is a label, not a search term. Using
    // it as a query returns exactly one row — the fabric already chosen — so
    // the list looks empty of alternatives and there's no way to change
    // colour without first deleting the text. Browse the full list instead;
    // typing (which clears fabricId) searches normally again.
    function fabricBrowseQuery() {
        return fabricId.value ? '' : fabricSearch.value.trim();
    }

    fabricSearch.addEventListener('focus', function () {
        // On focus, kick off a query (empty = first 50 alphabetical) so the
        // user gets something to browse before typing.
        if (!productSel.value) return;
        // Select the label of an already-picked fabric so the first keystroke
        // replaces it rather than appending to it ("Autumn GoldFl").
        if (fabricId.value) {
            setTimeout(function () { try { fabricSearch.select(); } catch (e) {} }, 0);
        }
        searchFabrics(fabricBrowseQuery());
    });
    fabricSearch.addEventListener('click', function () {
        // Clicking the input always reopens the dropdown — even when focus
        // was already inside (in which case the 'focus' event won't re-fire).
        // Without this, after picking a fabric the user has to delete the
        // text to browse again, which is exactly what Tyler reported.
        if (productSel.value) searchFabrics(fabricBrowseQuery());
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
    //
    // Then, once everything has settled, scrolls the form into view —
    // the browser's own anchor-scroll fires too early (before the JS
    // populates the cascade and shifts layout), which is why hitting
    // Duplicate or Edit used to feel like "the page shot back to the
    // top." Doing it ourselves AFTER the async work is reliable.
    function scrollToAddLine() {
        var target = document.getElementById('add-line');
        if (!target) return;
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        target.classList.add('flash-jump');
        setTimeout(function () { target.classList.remove('flash-jump'); }, 1600);
    }
    var qs = new URLSearchParams(window.location.search);
    var jumpToForm = qs.has('edit_item');

    if (productSel.value) {
        (async function () {
            await loadProductData();
            if (window.__editingBlind__) {
                await applyEditingValues(window.__editingBlind__);
            }
            if (jumpToForm) scrollToAddLine();
        })();
    } else if (jumpToForm) {
        // No product picked yet but we're in edit mode anyway — still
        // worth scrolling so the user lands on the form.
        scrollToAddLine();
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
    // Colour only (no "Band X —" prefix) — consistent with the live
    // fabric picker, which drops the band since it's chosen above.
    $editFabricLabel = implode(' / ', $editFabricBits);
?>
<script>
window.__editingBlind__ = <?= json_encode([
    'product_id'    => (int) ($editingItem['product_id']     ?? 0),
    'system_id'     => $editingItem['system_id'] !== null ? (int) $editingItem['system_id'] : null,
    'option_id'     => (int) ($editingItem['option_id']      ?? 0),
    'fabric_label'  => $editFabricLabel,
    'fabric_band'   => (string) ($editingItem['fabric_band_snapshot'] ?? ''),
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
<script>
// "Take payment" shortcut in the sticky header — when clicked, the
// browser's anchor-scroll takes the user to #payments (already
// scroll-margin-top'd so the sticky bar doesn't cover it). Once
// scrolling has likely settled, focus the Amount input on the
// inline Record-payment form so the fitter can type the figure and
// Enter to save without any more clicks. Defensive against the form
// not being present (e.g. already-fully-paid order: no input to focus).
(function () {
    var trigger = document.getElementById('qsb-take-payment');
    if (!trigger) return;
    trigger.addEventListener('click', function () {
        setTimeout(function () {
            // The Amount input inside the Record-payment card — focused
            // + selected so the user can immediately type or hit Enter
            // to record the outstanding figure (which is pre-filled).
            var amt = document.getElementById('rp-amount');
            if (amt) {
                amt.focus();
                amt.select();
            }
        }, 350);
    });
})();
</script>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
