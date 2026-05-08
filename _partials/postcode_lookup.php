<?php
/**
 * Postcode lookup widget — populates the installation address fields
 * (installation_address1/2, installation_town, installation_county,
 * installation_postcode) from a getAddress.io result.
 *
 * Caller is responsible for gating inclusion behind feature_postcode_lookup.
 * Drop the include just inside the "Installation address" fieldset.
 */
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

    btn.addEventListener('click', function () {
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
            cache.forEach(function (a, i) {
                var opt = document.createElement('option');
                opt.value = String(i);
                opt.textContent = [a.line1, a.line2, a.town, a.postcode]
                    .filter(function (p) { return p; }).join(', ');
                sel.appendChild(opt);
            });
            results.style.display = 'block';
            sel.focus();
        }).catch(function (e) {
            setError('Lookup failed. Please try again.');
        }).finally(function () {
            btn.disabled = false;
            btn.textContent = origLabel;
        });
    });

    // Apply selection to the address fields.
    sel.addEventListener('change', function () {
        var idx = parseInt(sel.value, 10);
        if (isNaN(idx) || !cache[idx]) return;
        var a = cache[idx];
        setField('installation_address1', a.line1);
        setField('installation_address2', a.line2);
        setField('installation_town',     a.town);
        setField('installation_county',   a.county);
        setField('installation_postcode', a.postcode);
    });

    // Convenience: pressing Enter in the postcode field triggers Find.
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            btn.click();
        }
    });
})();
</script>
