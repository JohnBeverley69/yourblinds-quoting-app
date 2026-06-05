<?php
/**
 * Shared "Bulk add choices" dialog + handler.
 *
 * Drop-in partial for any page that embeds _partials/choices_grid.php.
 * The "+ Bulk add" button rendered by the grid carries
 * data-extra-id="…"; clicking it anywhere on the page opens this
 * dialog, posts each pasted line as a new choice via the existing
 * /admin/products/choice-api.php?action=create endpoint, then reloads
 * so the new rows appear in the right grid.
 *
 * Required JS environment:
 *   - <meta name="csrf-token" content="…"> present in the page head
 *   - /admin/products/choice-api.php reachable
 *
 * Include exactly once per page (the dialog is keyed by id). Safe
 * to include even if no choices grid is rendered — the handler
 * just sits idle waiting for a matching button click.
 *
 * Previously this lived inline at the bottom of admin/products/edit.php,
 * which meant admin/products/extra.php's standalone "Colour — Choices"
 * page (and any future page using the grid) silently had a dead
 * "+ Bulk add" button. Extracted so the dialog and click handler
 * travel with the grid wherever it goes.
 */
?>
<!-- Shared bulk-add dialog for choices. One per page, populated
     dynamically when any "+ Bulk add" button is clicked. Same UX
     as the bulk-add textarea elsewhere — paste a list of labels,
     each line becomes a choice on the chosen option. -->
<dialog id="bulk-choices-dialog"
        style="border:1px solid var(--border);border-radius:12px;padding:1.25rem;background:var(--bg-card);color:var(--text-body);box-shadow:0 8px 24px rgba(0,0,0,0.18);width:min(32rem,92vw)">
    <h3 id="bulk-choices-title" style="margin:0 0 0.375rem;font-size:1.0625rem;color:var(--text-primary)">
        Add choices
    </h3>
    <p style="margin:0 0 0.625rem;font-size:0.8125rem;color:var(--text-muted);line-height:1.5">
        One label per line. Each becomes a choice on this option — e.g.
        <code>Left</code> then <code>Right</code> on a Cord option.
        New rows start with no price differences; edit prices in the
        grid afterwards if needed.
    </p>
    <textarea id="bulk-choices-input" rows="6"
              placeholder="Left&#10;Right"
              style="width:100%;border:1px solid var(--border-strong);border-radius:6px;padding:0.5rem 0.625rem;font:inherit;font-family:ui-monospace,Menlo,Consolas,monospace;background:var(--bg-input);color:var(--text-body);resize:vertical;min-height:6rem"></textarea>
    <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:0.875rem">
        <button type="button" id="bulk-choices-cancel" class="btn btn-secondary">Cancel</button>
        <button type="button" id="bulk-choices-confirm" class="btn btn-primary">Add</button>
    </div>
</dialog>
<script>
(function () {
    var dialog = document.getElementById('bulk-choices-dialog');
    var title  = document.getElementById('bulk-choices-title');
    var input  = document.getElementById('bulk-choices-input');
    var confirm = document.getElementById('bulk-choices-confirm');
    var cancel  = document.getElementById('bulk-choices-cancel');
    if (!dialog || !confirm) return;

    var currentExtraId = null;
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
    // Same endpoint the inline grid uses — see choices_grid_js.php.
    var endpoint = '/admin/products/choice-api.php';

    // Click delegation — works for any "+ Bulk add" button anywhere
    // in the document, including grids that get re-rendered.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.bulk-add-choices');
        if (!btn) return;
        e.preventDefault();
        currentExtraId = parseInt(btn.getAttribute('data-extra-id'), 10) || null;
        if (!currentExtraId) return;
        // Look up the option's name from its row in the page so the
        // dialog title can reflect what they're editing. Two host
        // shapes supported:
        //   - edit.php: each option is wrapped in <details class="option-inline">
        //               with the name in the second summary <span>
        //   - extra.php: standalone page — the option name is in
        //               <h1 class="page-title">, in the form "Name — Choices"
        var optDetails = btn.closest('details.option-inline');
        var optName    = '';
        if (optDetails) {
            var s = optDetails.querySelector('summary > span:nth-child(2)');
            if (s) optName = s.textContent || '';
        } else {
            var h1 = document.querySelector('h1.page-title');
            if (h1) {
                var t = h1.textContent || '';
                // "Slat Type — Choices" → "Slat Type"
                var idx = t.indexOf('—');
                optName = (idx > -1 ? t.slice(0, idx) : t).trim();
            }
        }
        title.textContent = optName
            ? 'Add choices to "' + optName.trim() + '"'
            : 'Add choices';
        input.value = '';
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
        setTimeout(function () { input.focus(); }, 0);
    });

    function closeDialog() {
        if (dialog.close) dialog.close(); else dialog.removeAttribute('open');
    }
    cancel.addEventListener('click', closeDialog);

    confirm.addEventListener('click', async function () {
        var lines = (input.value || '')
            .replace(/\r/g, '')
            .split('\n')
            .map(function (s) { return s.trim(); })
            .filter(function (s) { return s !== ''; });
        if (!lines.length) { closeDialog(); return; }
        if (!currentExtraId) { closeDialog(); return; }

        confirm.disabled = true;
        var added = 0;
        var failed = 0;
        for (var i = 0; i < lines.length; i++) {
            var label = lines[i].substring(0, 150);
            var fd = new FormData();
            fd.append('action', 'create');
            fd.append('extra_id', String(currentExtraId));
            fd.append('label', label);
            try {
                var r = await fetch(endpoint, {
                    method: 'POST', body: fd,
                    headers: { 'X-CSRF-Token': csrf },
                    credentials: 'same-origin'
                });
                var data = await r.json();
                if (data && data.ok) added++;
                else failed++;
            } catch (err) {
                failed++;
            }
        }
        confirm.disabled = false;
        closeDialog();
        // Reload the page so the new choices show in the right grid.
        // A full reload is simpler than per-grid surgical updates and
        // matches the way other bulk-adds work in this codebase.
        if (added > 0) {
            window.location.reload();
        } else if (failed > 0) {
            alert('Could not add the choices — ' + failed + ' attempt'
                + (failed === 1 ? '' : 's') + ' failed.');
        }
    });
})();
</script>
