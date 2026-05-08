<?php
/**
 * Postcode lookup widget — populates the installation address fields
 * (installation_address1/2, installation_town, installation_county,
 * installation_postcode) from a getAddress.io result.
 *
 * Two-step flow:
 *   1. POST term to /api/postcode.php?action=autocomplete
 *      -> renders a dropdown of matching addresses
 *   2. On selection, fetch /api/postcode.php?action=get&id=<id>
 *      -> populate the form fields with the full address
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
               autocomplete="off" maxlength="20">
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
        var term = (input.value || '').trim();
        if (!term) { setError('Enter a postcode first.'); return; }

        setError('');
        btn.disabled = true;
        var origLabel = btn.textContent;
        btn.textContent = 'Finding…';

        fetch('/api/postcode.php?action=autocomplete&term=' + encodeURIComponent(term), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        }).then(function (r) {
            if (!r.ok) {
                setError((r.data && r.data.error) || 'Lookup failed.');
                return;
            }
            var suggestions = (r.data && r.data.suggestions) || [];
            sel.innerHTML = '';
            if (!suggestions.length) {
                setError('No addresses found for that postcode.');
                return;
            }
            // First option is a placeholder so the change event fires reliably
            // even when there's only one real suggestion.
            var ph = document.createElement('option');
            ph.value = '';
            ph.textContent = '— Pick one —';
            ph.disabled = true;
            ph.selected = true;
            sel.appendChild(ph);
            suggestions.forEach(function (s) {
                var opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.address;
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

    function fetchDetails(id) {
        if (!id) return;
        sel.disabled = true;

        fetch('/api/postcode.php?action=get&id=' + encodeURIComponent(id), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        }).then(function (r) {
            if (!r.ok) {
                setError((r.data && r.data.error) || 'Could not fetch address details.');
                return;
            }
            var a = r.data || {};
            setField('installation_address1', a.line1);
            setField('installation_address2', a.line2);
            setField('installation_town',     a.town);
            setField('installation_county',   a.county);
            setField('installation_postcode', a.postcode);
            setError('');
        }).catch(function () {
            setError('Could not fetch address details. Please try again.');
        }).finally(function () {
            sel.disabled = false;
        });
    }

    btn.addEventListener('click', lookup);

    // Pressing Enter in the postcode field triggers Find.
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            lookup();
        }
    });

    // Selecting an address triggers the second API call.
    sel.addEventListener('change', function () {
        fetchDetails(sel.value);
    });
})();
</script>
