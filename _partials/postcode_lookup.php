<?php
/**
 * Postcode lookup widget — populates address fields from a Postcoder
 * lookup result.
 *
 * Single-call flow: postcode -> list of full addresses -> user picks one.
 *
 * Caller responsibilities:
 *   1. Gate inclusion behind feature_postcode_lookup.
 *   2. Optionally pass $pcFieldMap before requiring this file to control
 *      which form-field IDs get populated. If not set, defaults to the
 *      calendar's installation_* fields.
 *
 *      $pcFieldMap = [
 *          'line1'    => 'end_customer_address1',
 *          'line2'    => 'end_customer_address2',
 *          'town'     => 'end_customer_town',
 *          'county'   => 'end_customer_county',
 *          'postcode' => 'end_customer_postcode',
 *      ];
 *      require __DIR__ . '/../_partials/postcode_lookup.php';
 *
 *   3. Drop the include just inside the address fieldset so the
 *      "Find by postcode" row appears above the address fields.
 *
 * Multiple instances on one page would collide on the widget IDs — at
 * the moment there's no caller that needs that, so we keep it simple.
 */

$pcFieldMap = $pcFieldMap ?? [
    'line1'    => 'installation_address1',
    'line2'    => 'installation_address2',
    'town'     => 'installation_town',
    'county'   => 'installation_county',
    'postcode' => 'installation_postcode',
];
?>
<div class="form-row" style="grid-template-columns: 1fr auto; align-items: end; margin-bottom: 0.5rem;">
    <div class="form-group" style="margin-bottom: 0;">
        <label for="postcode_lookup_input">Find by postcode</label>
        <input type="text" id="postcode_lookup_input"
               placeholder="e.g. BS1 4ST"
               autocomplete="off" maxlength="8">
    </div>
    <div class="form-group" style="margin-bottom: 0;">
        <button type="button" id="postcode_lookup_btn" class="btn btn-secondary">Find address</button>
    </div>
</div>
<div id="postcode_lookup_error"
     style="display:none;color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;
            border-radius:8px;padding:0.5rem 0.75rem;font-size:0.875rem;
            margin-bottom:0.75rem;"></div>
<div id="postcode_lookup_results" class="form-row full" style="display:none;">
    <div class="form-group">
        <label for="postcode_lookup_select">Pick an address</label>
        <select id="postcode_lookup_select" size="6"
                style="width:100%;padding:0.5rem;border:1px solid #d1d5db;
                       border-radius:8px;background:#fff;font-family:inherit;
                       font-size:0.9375rem;">
        </select>
    </div>
</div>

<script>
(function () {
    var fieldMap = <?= json_encode($pcFieldMap, JSON_THROW_ON_ERROR) ?>;
    var input   = document.getElementById('postcode_lookup_input');
    var btn     = document.getElementById('postcode_lookup_btn');
    var results = document.getElementById('postcode_lookup_results');
    var sel     = document.getElementById('postcode_lookup_select');
    var errEl   = document.getElementById('postcode_lookup_error');
    if (!input || !btn || !results || !sel || !errEl) return;

    var cache = [];

    function setError(msg) {
        if (msg) {
            errEl.textContent = msg;
            errEl.style.display = 'block';
            results.style.display = 'none';
        } else {
            errEl.textContent = '';
            errEl.style.display = 'none';
        }
    }

    function setField(id, value) {
        var el = document.getElementById(id);
        if (el) { el.value = value || ''; }
    }

    function lookup() {
        var pc = (input.value || '').trim();
        if (!pc) { setError('Enter a postcode first.'); return; }

        setError('');
        btn.disabled = true;
        var origLabel = btn.textContent;
        btn.textContent = 'Finding…';

        fetch('/api/postcode.php?postcode=' + encodeURIComponent(pc), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        }).then(function (r) {
            if (!r.ok) {
                setError((r.data && r.data.error) || 'Lookup failed.');
                return;
            }
            cache = (r.data && r.data.addresses) || [];
            sel.innerHTML = '';
            if (!cache.length) {
                setError('No addresses found for that postcode.');
                return;
            }
            // Placeholder so the change event fires reliably even when there's
            // only one real suggestion.
            var ph = document.createElement('option');
            ph.value = '';
            ph.textContent = '— Pick one —';
            ph.disabled = true;
            ph.selected = true;
            sel.appendChild(ph);
            cache.forEach(function (a, i) {
                var opt = document.createElement('option');
                opt.value = String(i);
                opt.textContent = [a.line1, a.line2, a.town, a.postcode]
                    .filter(function (p) { return p; }).join(', ');
                sel.appendChild(opt);
            });
            results.style.display = 'block';
            sel.focus();
        }).catch(function () {
            setError('Lookup failed. Please try again.');
        }).finally(function () {
            btn.disabled = false;
            btn.textContent = origLabel;
        });
    }

    btn.addEventListener('click', lookup);

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            lookup();
        }
    });

    // Apply selection to the address fields configured by the caller, then
    // collapse the dropdown so the form doesn't stay cluttered after picking.
    sel.addEventListener('change', function () {
        var idx = parseInt(sel.value, 10);
        if (isNaN(idx) || !cache[idx]) return;
        var a = cache[idx];
        Object.keys(fieldMap).forEach(function (key) {
            setField(fieldMap[key], a[key]);
        });
        results.style.display = 'none';
    });
})();
</script>
