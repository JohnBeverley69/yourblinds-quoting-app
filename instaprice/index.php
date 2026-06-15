<?php
declare(strict_types=1);

/**
 * InstaPrice — fast standalone price calculator.
 *
 * "Client wants a quick price, no customer details." Pick a product,
 * size it, choose options, get the sell price in a snap. Reuses the
 * same engine as the quote builder:
 *   - /quote-builder/api/product-data.php  (systems, bands, options)
 *   - /quote-builder/api/fabrics-search.php (slat typeahead)
 *   - /quote-builder/api/preview.php        (pe_calculate_item)
 *
 * Discount % and Mark-up % default to the product's saved rates (which
 * the engine resolves from the Settings defaults / per-product
 * overrides) and can be overridden live. Nothing is saved.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/units.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];
// Company default measurement unit — InstaPrice has no quote of its own,
// so it starts on the company default with a local switcher.
$defaultUnit = client_default_unit(db(), $clientId);
$isAdmin  = ($user['role'] ?? '') === 'admin';
$_perms   = current_user_permissions();
$canCreateQuotes = $isAdmin || !empty($_perms['can_create_quotes']);

// Active products for the picker, grouped by category via the shared helper.
require_once __DIR__ . '/../_partials/product_picker.php';
$products = product_picker_products($clientId);

$activeNav = 'instaprice';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>InstaPrice &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .ip-wrap { max-width: 880px; }
        .ip-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 720px) { .ip-grid { grid-template-columns: 1fr; } }
        .ip-field label { display:block; font-size:0.8125rem; font-weight:600;
            color: var(--text-secondary); margin-bottom: 0.25rem; }
        .ip-field select, .ip-field input[type="text"], .ip-field input[type="number"] {
            width: 100%; padding: 0.5rem 0.625rem; border: 1px solid var(--border-strong);
            border-radius: 8px; background: var(--bg-input); color: var(--text-body); font: inherit;
        }
        .ip-field select:disabled, .ip-field input:disabled { background: var(--bg-subtle-2); }
        .ip-dims { display:grid; grid-template-columns: 1fr 1fr 5rem; gap: 0.625rem; }
        @media (max-width: 520px) { .ip-dims { grid-template-columns: 1fr; } }

        /* Slat typeahead */
        .ip-fab { position: relative; }
        .ip-fab-results {
            position:absolute; top:100%; left:0; right:0; margin-top:4px; z-index:30;
            background: var(--bg-card); border:1px solid var(--border-strong); border-radius:8px;
            box-shadow: var(--shadow-sm); max-height: 300px; overflow-y:auto;
        }
        .ip-fab-results .frow { padding:0.4375rem 0.625rem; cursor:pointer; border-bottom:1px solid var(--border-faint); }
        .ip-fab-results .frow:hover, .ip-fab-results .frow.active { background: var(--bg-subtle); }
        .ip-fab-results .fname { font-size:0.9375rem; color: var(--text-primary); }
        .ip-fab-results .fmeta { font-size:0.75rem; color: var(--text-faint); margin-top:0.125rem; }
        .ip-fab-results .empty { padding:0.625rem; color: var(--text-faint); font-size:0.875rem; }

        /* Options */
        #ip-extras > div[data-extra-id] { margin-bottom: 0.75rem; }
        #ip-extras .extra-child { margin-left: 1rem; padding-left: 0.75rem; border-left: 2px solid var(--border); }
        #ip-extras label { display:block; font-size:0.8125rem; font-weight:600; color:var(--text-secondary); margin-bottom:0.25rem; }
        #ip-extras select,
        #ip-extras input[type="number"] { width:100%; padding:0.5rem 0.625rem; border:1px solid var(--border-strong);
            border-radius:8px; background:var(--bg-input); color:var(--text-body); font:inherit; }

        /* Price panel */
        .ip-price { background: var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1.125rem 1.25rem; }
        .ip-price.is-idle { color: var(--text-faint); font-style: italic; }
        .ip-price.is-error { border-color: var(--danger-border); color: var(--danger-text); }
        .ip-row { display:flex; align-items:center; justify-content:space-between; gap:0.75rem; padding:0.375rem 0; }
        .ip-row + .ip-row { border-top: 1px solid var(--border-faint); }
        .ip-row .lbl { color: var(--text-secondary); }
        .ip-row .val { font-variant-numeric: tabular-nums; font-weight:600; color: var(--text-primary); }
        .ip-row.editable .lbl { color: #b45309; font-weight:600; }
        .ip-row input.pct { width: 6.5rem; text-align:right; padding:0.375rem 0.5rem;
            border:1px solid var(--border-strong); border-radius:6px; background:var(--bg-input); color:var(--text-body); font:inherit; }
        .ip-sell { margin-top:0.5rem; padding-top:0.625rem; border-top:2px solid var(--border-strong); }
        .ip-sell .lbl { font-weight:700; color: var(--text-primary); font-size:1.0625rem; }
        .ip-sell .val { font-weight:800; font-size:1.375rem; color: var(--brand, #1f3b5b); }
        [data-theme="dark"] .ip-sell .val { color: var(--brand-accent); }
        .ip-total { font-size:0.8125rem; color: var(--text-faint); text-align:right; margin-top:0.25rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">InstaPrice</h1>
                <p class="page-subtitle">Quick price — no customer details needed.</p>
            </div>
        </div>

        <?php
            $ipFlashErr = $_SESSION['flash_error'] ?? null;
            unset($_SESSION['flash_error']);
        ?>
        <?php if ($ipFlashErr !== null): ?>
            <div class="alert alert-error" role="alert" style="max-width:880px"><?= e((string) $ipFlashErr) ?></div>
        <?php endif; ?>

        <section class="section ip-wrap">
            <form id="ip-form" class="form" novalidate onsubmit="return false">
                <div class="ip-grid">
                    <div class="ip-field">
                        <label for="ip-product">Product</label>
                        <select id="ip-product">
                            <option value="">Choose product…</option>
                            <?= product_picker_options_html($products, 0) ?>
                        </select>
                    </div>
                    <div class="ip-field">
                        <label for="ip-system">System</label>
                        <select id="ip-system" disabled><option value="">Choose product first</option></select>
                    </div>
                </div>

                <div class="ip-grid" style="margin-top:1rem" id="ip-fabric-grid">
                    <div class="ip-field" id="ip-band-field">
                        <label for="ip-band"><span id="ip-band-label">Band</span></label>
                        <select id="ip-band" disabled><option value="">All bands</option></select>
                    </div>
                    <div class="ip-field ip-fab" id="ip-fabric-field">
                        <label for="ip-fabric-search"><span id="ip-fabric-label">Fabric</span></label>
                        <input type="text" id="ip-fabric-search" autocomplete="off" disabled
                               placeholder="Choose product first">
                        <input type="hidden" id="ip-fabric">
                        <div id="ip-fabric-results" class="ip-fab-results" hidden></div>
                    </div>
                </div>

                <div id="ip-extras" style="margin-top:1rem"></div>

                <div class="ip-grid" style="margin-top:1rem">
                    <div class="ip-field">
                        <label for="ip-unit">Measurement unit</label>
                        <select id="ip-unit">
                            <?php foreach (['mm' => 'Millimetres (mm)', 'cm' => 'Centimetres (cm)',
                                            'm' => 'Metres (m)', 'in' => 'Inches (in)'] as $uVal => $uLabel): ?>
                                <option value="<?= e($uVal) ?>" <?= $defaultUnit === $uVal ? 'selected' : '' ?>>
                                    <?= e($uLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ip-field"></div>
                </div>

                <div class="ip-field" style="margin-top:1rem">
                    <label><span id="ip-dim-label">Dimensions</span> &amp; quantity</label>
                    <?php /* QA #007: persistent per-field labels, not placeholder-only,
                             so the field's meaning stays visible while typing (and for
                             screen readers). */ ?>
                    <div class="ip-dims">
                        <div style="flex:1 1 0;min-width:0">
                            <label for="ip-width" class="ip-dim-cap">Width</label>
                            <input id="ip-width" type="text" inputmode="numeric" style="width:100%;box-sizing:border-box"
                                   autocomplete="off" autocorrect="off" autocapitalize="off"
                                   data-lpignore="true" data-1p-ignore="true" placeholder="Width">
                        </div>
                        <div style="flex:1 1 0;min-width:0">
                            <label for="ip-drop" class="ip-dim-cap">Drop</label>
                            <input id="ip-drop"  type="text" inputmode="numeric" style="width:100%;box-sizing:border-box"
                                   autocomplete="off" autocorrect="off" autocapitalize="off"
                                   data-lpignore="true" data-1p-ignore="true" placeholder="Drop">
                        </div>
                        <div style="flex:0 0 5rem">
                            <label for="ip-qty" class="ip-dim-cap">Qty</label>
                            <input id="ip-qty"   type="number" min="1" value="1" autocomplete="off" style="width:100%;box-sizing:border-box">
                        </div>
                    </div>
                    <div id="ip-dim-echo" hidden
                         style="margin-top:0.4rem;font-size:0.8125rem;color:var(--text-faint)"></div>
                </div>

                <div style="margin-top:1.25rem">
                    <div id="ip-price" class="ip-price is-idle">Choose a product and enter a size to see the price.</div>
                </div>

                <div class="form-actions" style="margin-top:1rem">
                    <?php if ($canCreateQuotes): ?>
                        <button type="button" id="ip-to-quote" class="btn btn-primary" disabled>
                            Turn into full quote &rarr;
                        </button>
                    <?php endif; ?>
                    <button type="button" id="ip-reset" class="btn btn-secondary">Reset</button>
                </div>

            </form>

            <?php if ($canCreateQuotes): ?>
                <!-- Convert form — kept OUTSIDE #ip-form. Nested forms are
                     invalid HTML; the browser drops the inner one, so
                     .submit() silently did nothing. The button's JS fills
                     these hidden fields and submits this form. -->
                <form id="ip-quote-form" method="post" action="/instaprice/to-quote.php" style="display:none">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id"  id="q-product">
                    <input type="hidden" name="system_id"   id="q-system">
                    <input type="hidden" name="option_id"   id="q-option">
                    <input type="hidden" name="width"       id="q-width">
                    <input type="hidden" name="drop"        id="q-drop">
                    <input type="hidden" name="quantity"    id="q-qty">
                    <input type="hidden" name="unit"        id="q-unit">
                    <input type="hidden" name="extras_json" id="q-extras">
                </form>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
(function () {
    'use strict';

    var productSel = document.getElementById('ip-product');
    var systemSel  = document.getElementById('ip-system');
    var bandSel    = document.getElementById('ip-band');
    var fabricGrid = document.getElementById('ip-fabric-grid');
    var bandLabelEl   = document.getElementById('ip-band-label');
    var fabricLabelEl = document.getElementById('ip-fabric-label');
    var fabricSearch  = document.getElementById('ip-fabric-search');
    var fabricId      = document.getElementById('ip-fabric');
    var fabricResults = document.getElementById('ip-fabric-results');
    var extrasBox     = document.getElementById('ip-extras');
    var widthIn  = document.getElementById('ip-width');
    var dropIn   = document.getElementById('ip-drop');
    var qtyIn    = document.getElementById('ip-qty');
    var priceBox = document.getElementById('ip-price');
    var toQuoteBtn = document.getElementById('ip-to-quote');
    var resetBtn   = document.getElementById('ip-reset');

    var productData = null;
    var previewTimer = null, fabricSearchTimer = null;
    var currentFabricBand = '';
    var requiresOption = true;   // false for no-fabric products (headrail/track/spares)
    var widthOnly = false;       // true for width-only products (headrail/track) — no drop
    var perSlat = false;         // true for per-slat products (vertical fabric only) — no width
    var perSqm = false;          // true for per-m² products (shutters) — width × height area
    var minAreaM2 = 0;           // optional minimum billable area for per-m² products

    // Active measurement unit (starts on the company default; the switcher
    // changes it). Bare numbers are read in this unit; explicit suffixes
    // (60in, 1.5m) still win.
    var unitSel  = document.getElementById('ip-unit');
    var dimLabel = document.getElementById('ip-dim-label');
    var activeUnit = (unitSel && unitSel.value) ? unitSel.value : 'mm';
    var UNIT_FACTOR = { mm: 1, cm: 10, m: 1000, in: 25.4 };
    var UNIT_SUFFIX = { mm: 'mm', cm: 'cm', m: 'm', in: '"' };
    function unitSfx() { return UNIT_SUFFIX[activeUnit] || 'mm'; }
    function refreshDimLabel() {
        if (dimLabel) dimLabel.textContent = 'Dimensions (' + unitSfx() + ')';
    }
    var lastBase = null, lastExtras = 0;
    var ratesDirty = true;            // reset disc/markup to the system's defaults on next price
    var curDisc = null, curMarkup = null;   // remember overrides across panel rebuilds
    var previewSeq = 0;               // guards against out-of-order preview responses

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
        });
    }
    function escapeAttr(s) { return escapeHtml(s); }
    function round2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }
    function money(n) { return '£' + Number(n).toFixed(2); }
    function setIdle(el, msg) { el.innerHTML = '<option value="">' + msg + '</option>'; el.disabled = true; }

    // Show / hide the Band + Fabric grid (no-fabric) and the Drop input
    // (width-only).
    function applyFabricVisibility() {
        if (fabricGrid) fabricGrid.style.display = requiresOption ? '' : 'none';
        if (!requiresOption) { clearFabric(); closeFabricResults(); }
        if (dropIn) {
            dropIn.style.display = widthOnly ? 'none' : '';
            if (widthOnly) dropIn.value = '';
        }
        if (widthIn) {
            widthIn.style.display = perSlat ? 'none' : '';
            if (perSlat) widthIn.value = '';   // priced per slat by drop
        }
    }

    // ----- Product load -------------------------------------------------
    async function loadProductData() {
        productData = null;
        clearFabric(); closeFabricResults(); resetBand();
        ratesDirty = true; curDisc = null; curMarkup = null;
        extrasBox.innerHTML = '';
        if (!productSel.value) {
            setIdle(systemSel, 'Choose product first');
            fabricSearch.disabled = true; fabricSearch.placeholder = 'Choose product first';
            if (fabricLabelEl) fabricLabelEl.textContent = 'Fabric';
            if (bandLabelEl) bandLabelEl.textContent = 'Band';
            requiresOption = true; widthOnly = false; perSlat = false; applyFabricVisibility();
            schedulePreview();
            return;
        }
        try {
            setIdle(systemSel, 'Loading…');
            fabricSearch.disabled = true; fabricSearch.placeholder = 'Loading…';
            var r = await fetch('/quote-builder/api/product-data.php?product_id=' + encodeURIComponent(productSel.value)
                                + '&_=' + Date.now(),   // cache-buster: defeat any edge page-cache
                                { credentials: 'same-origin' });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            productData = await r.json();
            if (productData.error) throw new Error(productData.error);

            if (!productData.systems.length) {
                setIdle(systemSel, '— No systems —'); systemSel.value = '';
            } else {
                var opts = '<option value="">— Choose system —</option>';
                productData.systems.forEach(function (s) {
                    opts += '<option value="' + s.id + '"' + (s.is_default ? ' selected' : '') + '>'
                          + escapeHtml(s.name) + '</option>';
                });
                systemSel.innerHTML = opts; systemSel.disabled = false;
            }

            var optLabel = (productData.product && productData.product.option_label) || 'Fabric';
            if (fabricLabelEl) fabricLabelEl.textContent = optLabel;
            var bandLabel = (productData.product && productData.product.band_label) || 'Band';
            if (bandLabelEl) bandLabelEl.textContent = bandLabel;

            // No-fabric product? Hide the Band + Fabric grid; price on
            // system × size alone.
            requiresOption = !productData.product
                          || productData.product.requires_option !== false;
            widthOnly = !!(productData.product && productData.product.width_only === true);
            perSlat   = !!(productData.product && productData.product.price_per_slat === true);
            perSqm    = !!(productData.product && productData.product.price_per_sqm === true);
            minAreaM2 = (productData.product && Number(productData.product.min_area_m2)) || 0;
            applyFabricVisibility();

            populateBands(bandsForCurrentSystem());
            fabricSearch.disabled = false;
            fabricSearch.placeholder = 'Type to search ' + optLabel.toLowerCase() + 's…';
            renderExtras();
        } catch (err) {
            setIdle(systemSel, 'Failed to load');
            fabricSearch.placeholder = 'Failed to load';
        }
        schedulePreview();
    }

    // ----- Band ---------------------------------------------------------
    function bandsForCurrentSystem() {
        var sid = (systemSel && systemSel.value) ? String(systemSel.value) : '';
        var bbs = productData && productData.bandsBySystem;
        if (sid && bbs && bbs[sid]) return bbs[sid];
        return (productData && productData.bands) || [];
    }
    function resetBand() {
        bandSel.innerHTML = '<option value="">All bands</option>'; bandSel.disabled = true;
    }
    function populateBands(bands) {
        var prev = bandSel.value;
        var opts = '<option value="">All bands</option>';
        bands.forEach(function (b) { opts += '<option value="' + escapeAttr(b) + '">' + escapeHtml(b) + '</option>'; });
        bandSel.innerHTML = opts;
        bandSel.disabled = bands.length === 0;
        if (prev && bands.indexOf(prev) !== -1) bandSel.value = prev;
        else if (bands.length === 1) bandSel.value = bands[0];
        else bandSel.value = '';
    }

    // ----- Fabric typeahead --------------------------------------------
    async function searchFabrics(query) {
        if (!productSel.value) return;
        try {
            var sysQ = systemSel.value ? '&system_id=' + encodeURIComponent(systemSel.value) : '';
            var bandQ = bandSel.value ? '&band=' + encodeURIComponent(bandSel.value) : '';
            var r = await fetch('/quote-builder/api/fabrics-search.php?product_id=' + encodeURIComponent(productSel.value)
                + '&q=' + encodeURIComponent(query || '') + sysQ + bandQ + '&_=' + Date.now(), { credentials: 'same-origin' });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            var data = await r.json();
            renderFabricResults(data.fabrics || []);
        } catch (e) {
            fabricResults.innerHTML = '<div class="empty">Could not search.</div>';
            fabricResults.hidden = false;
        }
    }
    function renderFabricResults(items) {
        if (!items.length) {
            fabricResults.innerHTML = '<div class="empty">No matching fabrics.</div>';
            fabricResults.hidden = false; return;
        }
        var html = '';
        items.forEach(function (f) {
            var meta = [];
            if (f.supplier) meta.push(escapeHtml(f.supplier));
            if (f.code)     meta.push('Code ' + escapeHtml(f.code));
            var pickLabel = f.name + (f.colour ? ' / ' + f.colour : '');
            html += '<div class="frow" data-id="' + f.id + '" data-label="' + escapeAttr(pickLabel) + '"'
                  + ' data-band="' + escapeAttr(f.band || '') + '">'
                  + '<div class="fname">' + escapeHtml(f.name) + (f.colour ? ' / ' + escapeHtml(f.colour) : '') + '</div>'
                  + (meta.length ? '<div class="fmeta">' + meta.join(' · ') + '</div>' : '')
                  + '</div>';
        });
        fabricResults.innerHTML = html;
        fabricResults.hidden = false;
        fabricResults.querySelectorAll('.frow').forEach(function (row) {
            row.addEventListener('mousedown', function (e) {
                e.preventDefault();
                pickFabric(row.dataset.id, row.dataset.label, row.dataset.band || '');
            });
        });
    }
    function pickFabric(id, label, band) {
        fabricId.value = String(id);
        fabricSearch.value = label;
        currentFabricBand = (band || '').toLowerCase();
        closeFabricResults();
        renderExtras();
        schedulePreview();
    }
    function clearFabric() { fabricId.value = ''; fabricSearch.value = ''; currentFabricBand = ''; }
    function closeFabricResults() { fabricResults.hidden = true; fabricResults.innerHTML = ''; }
    function scheduleFabricSearch() {
        clearTimeout(fabricSearchTimer);
        fabricSearchTimer = setTimeout(function () { searchFabrics(fabricSearch.value.trim()); }, 200);
    }

    // ----- Extras (ported from the quote builder) ----------------------
    function renderExtras() {
        if (!productData || !productData.extras || !productData.extras.length) {
            extrasBox.innerHTML = ''; return;
        }
        var preset = {};
        extrasBox.querySelectorAll('[data-extra-id]').forEach(function (div) {
            var eid = parseInt(div.getAttribute('data-extra-id'), 10);
            var sel = div.querySelector('select');
            if (sel) preset[eid] = sel.value;
            var multi = div.querySelectorAll('input[data-multi-choice]');
            if (multi.length) {
                preset[eid + '__multi'] = Array.from(multi).filter(function (cb) { return cb.checked; })
                    .map(function (cb) { return parseInt(cb.dataset.multiChoice, 10); });
            }
            var uvIn = div.querySelector('input[data-uv-for="' + eid + '"]');
            if (uvIn) preset[eid + '__uv'] = uvIn.value;
            // Per-choice number inputs — keyed by choice so a re-render keeps
            // each value (mirrors the full quote builder).
            div.querySelectorAll('input[data-cuv-for]').forEach(function (cin) {
                preset[eid + '__cuv__' + cin.getAttribute('data-cuv-for')] = cin.value;
            });
        });
        var systemId = parseInt(systemSel.value, 10) || 0;

        var choiceToExtra = {};
        productData.extras.forEach(function (e) { e.choices.forEach(function (c) { choiceToExtra[c.id] = e.id; }); });
        var childrenOf = {};
        productData.extras.forEach(function (e) {
            var parents = e.parent_choice_ids || [];
            if (!parents.length) return;
            // Single owner — nest a child under its FIRST matching parent only,
            // matching the real quote builder. Mapping it under every parent
            // would render the child once per parent (duplicated in the tree).
            var owner;
            for (var i = 0; i < parents.length; i++) {
                if (choiceToExtra[parents[i]] !== undefined) { owner = choiceToExtra[parents[i]]; break; }
            }
            if (owner === undefined) return;
            if (!childrenOf[owner]) childrenOf[owner] = [];
            if (childrenOf[owner].indexOf(e.id) === -1) childrenOf[owner].push(e.id);
        });

        function choiceAvailable(c) {
            if (c.system_id !== null && c.system_id !== undefined && c.system_id !== systemId) return false;
            var bands = c.bands || [];
            if (bands.length === 0) return true;
            if (!currentFabricBand) return false;
            for (var i = 0; i < bands.length; i++) {
                if (String(bands[i]).toLowerCase() === currentFabricBand) return true;
            }
            return false;
        }
        // Measurement-only option: a length/spec input with a single
        // carrier choice (e.g. "Fit height" in mm). Pure spec — no price
        // impact — so it's irrelevant on a quick-price screen. Skipped.
        function isMeasurementOnly(extra) {
            return !!extra.length_input_label
                && extra.choices.filter(choiceAvailable).length === 1;
        }
        function effectiveChoiceIds(extra) {
            if (extra.allow_multi) {
                var m = preset[extra.id + '__multi'];
                if (m !== undefined) return m.slice();
                return extra.choices.filter(function (c) { return choiceAvailable(c) && c.is_default; }).map(function (c) { return c.id; });
            }
            if (preset[extra.id] !== undefined) { var v = preset[extra.id]; return v ? [parseInt(v, 10)] : []; }
            var def = extra.choices.find(function (c) { return choiceAvailable(c) && c.is_default; });
            return def ? [def.id] : [];
        }
        function isVisible(extra) {
            var parents = extra.parent_choice_ids || [];
            if (parents.length) {
                var ok = false;
                productData.extras.forEach(function (other) {
                    if (other.id === extra.id) return;
                    var ids = effectiveChoiceIds(other);
                    for (var i = 0; i < ids.length; i++) { if (parents.indexOf(ids[i]) !== -1) { ok = true; break; } }
                });
                if (!ok) return false;
            }
            // Number-only option (a measurement input, no choices) is always
            // visible once any parent gate passes — nothing for choiceAvailable
            // to match.
            if ((extra.choices || []).length === 0 && extra.length_input_label) return true;
            return extra.choices.some(choiceAvailable);
        }
        function renderOne(extra, isChild) {
            var idx = productData.extras.indexOf(extra);
            var visible = extra.choices.filter(choiceAvailable);
            // Number-only option: no choices, just a measurement input.
            var numberOnly = !!extra.length_input_label && (extra.choices || []).length === 0;
            // Per-choice number box (choice.length_input_label) — e.g. each
            // offset side or a mid-rail height. Value sticky via preset.
            function choiceNumberInput(c) {
                if (!c.length_input_label) return '';
                var pv = preset[extra.id + '__cuv__' + c.id];
                var val = (pv !== undefined && pv !== null) ? String(pv) : '';
                return '<div style="margin-top:0.25rem">'
                     + '<input type="number" min="0" step="1" data-cuv-for="' + c.id + '"'
                     + ' value="' + escapeAttr(val) + '"'
                     + ' placeholder="' + escapeAttr(c.length_input_label) + '"'
                     + ' style="max-width:12rem">'
                     + '</div>';
            }
            var out = '<div data-extra-id="' + extra.id + '"' + (isChild ? ' class="extra-child"' : '') + '>';
            out += '<label>' + escapeHtml(extra.name) + (extra.is_required ? ' <span style="color:#b91c1c">*</span>' : '') + '</label>';
            out += '<input type="hidden" name="extras[' + idx + '][extra_id]" value="' + extra.id + '">';
            if (numberOnly) {
                var uvP   = preset[extra.id + '__uv'];
                var uvVal = (uvP !== undefined && uvP !== null) ? uvP : '';
                out += '<input type="number" min="0" step="1" data-uv-for="' + extra.id + '"'
                     + (extra.is_required ? ' required' : '')
                     + ' value="' + escapeAttr(uvVal) + '"'
                     + ' placeholder="' + escapeAttr(extra.length_input_label) + '">';
            } else if (extra.allow_multi) {
                var pm = preset[extra.id + '__multi'];
                var pre = Array.isArray(pm) ? pm.slice() : visible.filter(function (c) { return c.is_default; }).map(function (c) { return c.id; });
                out += '<div style="display:flex;flex-direction:column;gap:0.3125rem;padding:0.4375rem 0.5rem;background:var(--bg-input);border:1px solid var(--border-strong);border-radius:8px">';
                visible.forEach(function (c) {
                    var t = pre.indexOf(c.id) !== -1;
                    out += '<div>'
                         + '<label style="display:inline-flex;align-items:center;gap:0.5rem;cursor:pointer;font-weight:400">'
                         + '<input type="checkbox" data-multi-choice="' + c.id + '"' + (t ? ' checked' : '') + '> ' + escapeHtml(c.label) + '</label>'
                         + (t ? choiceNumberInput(c) : '')
                         + '</div>';
                });
                out += '</div>';
            } else {
                var pv = preset[extra.id];
                var hasDef = visible.some(function (c) { return c.is_default; });
                out += '<select>';
                if (!hasDef) out += '<option value=""' + (pv === '' ? ' selected' : '') + '>— Select —</option>';
                var selChoice = null;
                visible.forEach(function (c) {
                    var sel;
                    if (pv !== undefined && pv !== '') sel = String(c.id) === pv;
                    else if (pv === '') sel = false;
                    else sel = c.is_default;
                    if (sel) selChoice = c;
                    out += '<option value="' + c.id + '"' + (sel ? ' selected' : '') + '>' + escapeHtml(c.label) + '</option>';
                });
                out += '</select>';
                if (selChoice) out += choiceNumberInput(selChoice);
            }
            // Group-level measurement input for an extra that has BOTH a label
            // and choices (numberOnly rendered its own above). Mirrors the
            // quote builder so the measurement isn't silently dropped here.
            if (extra.length_input_label && !numberOnly) {
                var guvP   = preset[extra.id + '__uv'];
                var guvVal = (guvP !== undefined && guvP !== null) ? guvP : '';
                out += '<input type="number" min="0" step="1" data-uv-for="' + extra.id + '"'
                     + ' value="' + escapeAttr(guvVal) + '"'
                     + ' placeholder="' + escapeAttr(extra.length_input_label) + '">';
            }
            out += '</div>';
            return out;
        }
        function renderTree(extra, depth) {
            if (depth > 4) return '';
            var out = renderOne(extra, depth > 0);
            (childrenOf[extra.id] || []).forEach(function (cid) {
                var child = productData.extras.find(function (e) { return e.id === cid; });
                if (!child || !isVisible(child) || isMeasurementOnly(child)) return;
                out += renderTree(child, depth + 1);
            });
            return out;
        }
        var html = '';
        productData.extras.forEach(function (extra) {
            if ((extra.parent_choice_ids || []).length) return;
            if (isMeasurementOnly(extra)) return;   // spec-only — not relevant to a quick price
            if (!isVisible(extra)) return;
            html += '<div>' + renderTree(extra, 0) + '</div>';
        });
        extrasBox.innerHTML = html;
        extrasBox.querySelectorAll('select, input[data-multi-choice]').forEach(function (el) {
            el.addEventListener('change', function () { renderExtras(); schedulePreview(); });
        });
        // Number-only inputs don't affect price, but capturing keystrokes keeps
        // the value sticky across re-renders and ready for "Convert to quote".
        extrasBox.querySelectorAll('input[data-uv-for], input[data-cuv-for]').forEach(function (el) {
            el.addEventListener('input', schedulePreview);
        });
    }
    function collectExtras() {
        var out = [];
        extrasBox.querySelectorAll('[data-extra-id]').forEach(function (div) {
            var eid = parseInt(div.getAttribute('data-extra-id'), 10);
            if (eid <= 0) return;
            // This choice's own value (per-choice box), else the group-level
            // measurement, else null — matches the quote builder's precedence.
            function valueFor(cid) {
                var cin = div.querySelector('input[data-cuv-for="' + cid + '"]');
                if (cin && cin.value !== '') { var v = parseFloat(cin.value); if (v > 0) return v; }
                var uvIn = div.querySelector('input[data-uv-for="' + eid + '"]');
                if (uvIn && uvIn.value !== '') { var gv = parseFloat(uvIn.value); if (gv > 0) return gv; }
                return null;
            }
            var multi = div.querySelectorAll('input[data-multi-choice]');
            if (multi.length) {
                multi.forEach(function (cb) {
                    if (!cb.checked) return;
                    var cid = parseInt(cb.dataset.multiChoice, 10);
                    if (cid > 0) {
                        var rec = { extra_id: eid, choice_id: cid };
                        var v = valueFor(cid);
                        if (v !== null) rec.user_value = v;
                        out.push(rec);
                    }
                });
                return;
            }
            var sel = div.querySelector('select');
            if (!sel) {
                // Number-only option — no picker, just the typed measurement.
                var uvIn = div.querySelector('input[data-uv-for="' + eid + '"]');
                if (uvIn) {
                    var rec = { extra_id: eid };
                    var uv = parseFloat(uvIn.value);
                    if (uvIn.value !== '' && uv > 0) rec.user_value = uv;
                    out.push(rec);
                }
                return;
            }
            var cid = parseInt(sel.value, 10);
            if (cid > 0) {
                var rec = { extra_id: eid, choice_id: cid };
                var v = valueFor(cid);
                if (v !== null) rec.user_value = v;
                out.push(rec);
            }
        });
        return out;
    }

    // ----- Price --------------------------------------------------------
    function schedulePreview() { clearTimeout(previewTimer); previewTimer = setTimeout(runPreview, 250); }

    function setPriceIdle(msg, isError) {
        lastBase = null;
        priceBox.className = 'ip-price ' + (isError ? 'is-error' : 'is-idle');
        priceBox.textContent = msg;
        if (toQuoteBtn) toQuoteBtn.disabled = true;
    }

    async function runPreview() {
        var missing = [];
        if (!productSel.value)                  missing.push('product');
        if (requiresOption && !fabricId.value)  missing.push('fabric');
        if (!perSlat && !widthIn.value.trim())  missing.push('width');
        if (!widthOnly && !dropIn.value.trim()) missing.push('drop');
        if (missing.length) { setPriceIdle('Still need: ' + missing.join(', ') + '.', false); return; }

        var params = new URLSearchParams({
            product_id: productSel.value,
            system_id:  systemSel.value || '0',
            option_id:  fabricId.value,
            width:      widthIn.value,
            drop:       dropIn.value,
            quantity:   '1',
            round_up:   '1',
            unit:       activeUnit
        });
        collectExtras().forEach(function (ex, i) {
            params.append('extras[' + i + '][extra_id]', ex.extra_id);
            // Omit choice_id for number-only options so the server can tell
            // them apart from an unpicked dropdown.
            if (ex.choice_id !== undefined) {
                params.append('extras[' + i + '][choice_id]', ex.choice_id);
            }
            if (ex.user_value !== undefined) {
                params.append('extras[' + i + '][user_value]', ex.user_value);
            }
        });
        var myseq = ++previewSeq;   // only the latest request may update the panel
        try {
            params.append('_', Date.now());   // cache-buster
            var r = await fetch('/quote-builder/api/preview.php?' + params, { credentials: 'same-origin' });
            var data = await r.json();
            if (myseq !== previewSeq) return;   // a newer size change superseded this one
            if (data.error) { setPriceIdle(data.error, true); return; }
            lastBase   = Number(data.base_price);
            lastExtras = Number(data.extras_total || 0);
            var engineDisc   = Number(data.discount_percent || 0);
            var engineMarkup = Number(data.markup_percent || 0);
            var panelExists  = !!document.getElementById('ip-disc');
            if (ratesDirty) {
                // First price for this product/system → use its default rates.
                ratesDirty = false;
                renderPricePanel(engineDisc, engineMarkup);
            } else if (!panelExists) {
                // A previous error tore the panel down — rebuild it, keeping any
                // discount/markup the user had overridden.
                renderPricePanel(curDisc != null ? curDisc : engineDisc,
                                 curMarkup != null ? curMarkup : engineMarkup);
            } else {
                recompute();
            }
            if (toQuoteBtn) toQuoteBtn.disabled = false;
        } catch (e) {
            if (myseq !== previewSeq) return;
            setPriceIdle('Could not get a price — try again.', true);
        }
    }

    // Build the breakdown panel with editable discount/markup inputs.
    function renderPricePanel(discDefault, markupDefault) {
        priceBox.className = 'ip-price';
        priceBox.innerHTML =
            '<div class="ip-row"><span class="lbl">Price</span><span class="val" id="ip-base">—</span></div>'
          + '<div class="ip-row editable"><span class="lbl">Discount %</span>'
          +   '<input type="number" step="0.01" class="pct" id="ip-disc" value="' + discDefault.toFixed(2) + '"></div>'
          + '<div class="ip-row"><span class="lbl">Discounted price</span><span class="val" id="ip-disc-price">—</span></div>'
          + '<div class="ip-row editable"><span class="lbl">Mark up %</span>'
          +   '<input type="number" step="0.01" class="pct" id="ip-markup" value="' + markupDefault.toFixed(2) + '"></div>'
          + '<div class="ip-row ip-sell"><span class="lbl">Sell price</span><span class="val" id="ip-sell">—</span></div>'
          + '<div class="ip-total" id="ip-total"></div>';
        curDisc = discDefault; curMarkup = markupDefault;
        document.getElementById('ip-disc').addEventListener('input', function () {
            var v = parseFloat(this.value); curDisc = isNaN(v) ? 0 : v; recompute();
        });
        document.getElementById('ip-markup').addEventListener('input', function () {
            var v = parseFloat(this.value); curMarkup = isNaN(v) ? 0 : v; recompute();
        });
        recompute();
    }

    function recompute() {
        if (lastBase === null) return;
        var discEl = document.getElementById('ip-disc');
        var markEl = document.getElementById('ip-markup');
        if (!discEl || !markEl) return;
        var disc = parseFloat(discEl.value); if (isNaN(disc)) disc = 0;
        var markup = parseFloat(markEl.value); if (isNaN(markup)) markup = 0;
        var qty = Math.max(1, parseInt(qtyIn.value, 10) || 1);

        var discountedBase = lastBase * (1 - disc / 100);
        var pricePer = round2(lastBase + lastExtras);
        var discountedPer = round2(discountedBase + lastExtras);
        var sellPer = round2(discountedBase * (1 + markup / 100) + lastExtras);
        var total = round2(sellPer * qty);

        document.getElementById('ip-base').textContent = money(pricePer);
        document.getElementById('ip-disc-price').textContent = money(discountedPer);
        // Headline the TOTAL (qty × unit) in bold; show the single price as
        // the small grey supporting line. For qty 1 the total IS the single
        // price, so the grey line is omitted.
        document.getElementById('ip-sell').textContent = money(total);
        document.getElementById('ip-total').textContent = qty > 1
            ? (qty + ' × ' + money(sellPer) + ' each') : '';
    }

    // ----- Events -------------------------------------------------------
    productSel.addEventListener('change', loadProductData);
    systemSel.addEventListener('change', function () {
        ratesDirty = true; curDisc = null; curMarkup = null;   // rates are per system
        populateBands(bandsForCurrentSystem());
        renderExtras();
        if (fabricId.value) { clearFabric(); closeFabricResults(); }
        schedulePreview();
    });
    bandSel.addEventListener('change', function () {
        if (fabricId.value) clearFabric();
        renderExtras();
        if (productSel.value) { fabricSearch.focus(); searchFabrics(''); }
    });
    fabricSearch.addEventListener('focus', function () { if (productSel.value) searchFabrics(fabricSearch.value.trim()); });
    fabricSearch.addEventListener('click', function () { if (productSel.value) searchFabrics(fabricSearch.value.trim()); });
    fabricSearch.addEventListener('input', function () { fabricId.value = ''; scheduleFabricSearch(); schedulePreview(); });
    fabricSearch.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeFabricResults(); });
    fabricSearch.addEventListener('blur', function () { setTimeout(closeFabricResults, 150); });
    // Mirror of ptp_parse_dimension (server) so we can show the user the
    // exact mm the engine will use — makes a stray digit / wrong unit
    // obvious before they even read an error.
    function parseDim(raw) {
        var s = String(raw == null ? '' : raw).trim();
        if (s === '') return null;
        var c = s.replace(/[^\d.\-]/g, '');
        if (c === '' || isNaN(parseFloat(c))) return null;
        var n = parseFloat(c);
        if (n <= 0) return null;
        var low = s.toLowerCase();
        if (low.indexOf('mm') !== -1) return Math.round(n);
        if (low.indexOf('cm') !== -1) return Math.round(n * 10);
        if (/\d\s*m\b/i.test(s))      return Math.round(n * 1000);
        if (/['"]|\d\s*in(?:s|ch|ches)?\b/i.test(s)) return Math.round(n * 25.4);
        // Bare number — interpret in the active unit (matches the server).
        return Math.round(n * (UNIT_FACTOR[activeUnit] || 1));
    }
    function updateDimEcho() {
        var el = document.getElementById('ip-dim-echo');
        if (!el) return;
        var w = parseDim(widthIn.value), d = parseDim(dropIn.value);
        // Echo back in mm so the operator can sanity-check the conversion
        // (e.g. 60" really is 1524 mm).
        if (perSlat) {
            if (d) { el.textContent = 'Using ' + d + ' mm drop (per slat)'; el.hidden = false; }
            else   { el.hidden = true; }
        } else if (widthOnly) {
            if (w) { el.textContent = 'Using ' + w + ' mm wide'; el.hidden = false; }
            else   { el.hidden = true; }
        } else if (perSqm) {
            if (w && d) {
                var area = (w / 1000) * (d / 1000);
                var billed = Math.max(area, minAreaM2 || 0);
                var txt = 'Area: ' + area.toFixed(2) + ' m²';
                if (billed > area + 0.0001) txt += ' (billed at min ' + billed.toFixed(2) + ' m²)';
                el.textContent = txt; el.hidden = false;
            } else { el.hidden = true; }
        } else if (w && d) {
            el.textContent = 'Using ' + w + ' × ' + d + ' mm'; el.hidden = false;
        } else {
            el.hidden = true;
        }
    }

    [widthIn, dropIn].forEach(function (el) {
        el.addEventListener('input', function () { updateDimEcho(); schedulePreview(); });
    });
    qtyIn.addEventListener('input', recompute);

    // Unit switcher — change how typed numbers are read + relabel + recalc.
    if (unitSel) {
        unitSel.addEventListener('change', function () {
            activeUnit = unitSel.value || 'mm';
            refreshDimLabel();
            updateDimEcho();
            schedulePreview();
        });
    }
    refreshDimLabel();

    if (resetBtn) resetBtn.addEventListener('click', function () {
        productSel.value = ''; widthIn.value = ''; dropIn.value = ''; qtyIn.value = '1';
        updateDimEcho();
        loadProductData();
        setPriceIdle('Choose a product and enter a size to see the price.', false);
    });

    if (toQuoteBtn) toQuoteBtn.addEventListener('click', function () {
        if (toQuoteBtn.disabled) return;
        document.getElementById('q-product').value = productSel.value;
        document.getElementById('q-system').value  = systemSel.value || '0';
        document.getElementById('q-option').value  = fabricId.value;
        document.getElementById('q-width').value   = widthIn.value;
        document.getElementById('q-drop').value    = dropIn.value;
        document.getElementById('q-qty').value     = qtyIn.value || '1';
        document.getElementById('q-unit').value    = activeUnit;
        document.getElementById('q-extras').value  = JSON.stringify(collectExtras());
        document.getElementById('ip-quote-form').submit();
    });
})();
</script>
</body>
</html>
