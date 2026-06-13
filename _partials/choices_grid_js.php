<?php
/**
 * Shared inline-edit JS for the choices grid.
 *
 * Used by:
 *   - admin/products/extra.php   — the dedicated choices editor
 *   - admin/products/edit.php    — inline grids per option (Phase 2B)
 *
 * Required vars in the calling scope:
 *   $systems         (array) — the product's systems list, mirrored to
 *                              JS for the multi-select widgets
 *   $gridProductId   (int)   — the current product id, used in the
 *                              "+ Sub-option" deep-link template
 *
 * The JS is structured as a single IIFE that initialises EVERY
 * .choices-grid-wrap it finds on the page — so this partial can be
 * included once per page even when there are multiple grids. Each
 * grid's state (extraId, body, newRow, etc.) is closure-local in
 * initGrid().
 *
 * The save-status pill (#save-indicator) is page-level — only one
 * grid is edited at a time, so a single indicator covers them all.
 */
?>
<script>
(function () {
    'use strict';

    // ============================================================
    // Shared, page-level — multiple grids on this page share these.
    // ============================================================
    var endpoint  = '/admin/products/choice-api.php';
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var indicator = document.getElementById('save-indicator');

    // Systems list, mirrored from PHP. All grids on this page operate
    // on the same product, so the same systems apply to all of them.
    var systemsList = <?= json_encode(
        array_map(static fn ($s) => [
            'id'   => (int) $s['id'],
            'name' => (string) $s['name'],
        ], $systems),
        JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;

    // Band-scoping, mirrored from PHP. bandsColShown gates whether
    // JS-built rows (new-row commit, duplicate, sibling-system spawn)
    // get a "Bands" cell — it must match the server-rendered grid,
    // which only shows the column when $renderBandMultiSelect is set
    // (i.e. the migrate_choice_band_scoping.php migration has run).
    // Without this, freshly-added rows were missing the Bands widget
    // entirely (and every column to its right shifted left by one),
    // so you couldn't scope a new option's bands without reloading or
    // going through the Edit link.
    var bandsColShown  = <?= (isset($renderBandMultiSelect) && $renderBandMultiSelect !== null) ? 'true' : 'false' ?>;
    var knownBandsList = <?= json_encode(array_values($knownBands ?? []), JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    // Save-status pill is PERSISTENT — it always shows one of:
    //   "All changes saved" (green, default at rest)
    //   "Saving…"           (amber, during a fetch)
    //   "Save failed"       (red, after a server reject; auto-clears
    //                       back to "saved" after 4s so the user can
    //                       try again with a clean slate)
    // The legacy `flashIndicator(msg, isError)` API is preserved so
    // existing callers don't need to change.
    var hideTimer = null;
    function setIndicatorState(state, customMessage) {
        if (!indicator) return;
        clearTimeout(hideTimer);
        indicator.classList.remove('is-saving', 'is-error');
        if (state === 'saving') {
            indicator.classList.add('is-saving');
            indicator.textContent = customMessage || 'Saving…';
        } else if (state === 'error') {
            indicator.classList.add('is-error');
            indicator.textContent = customMessage || 'Save failed';
            hideTimer = setTimeout(function () {
                setIndicatorState('saved');
            }, 4000);
        } else {
            indicator.textContent = customMessage || 'All changes saved';
        }
    }
    function flashIndicator(message, isError) {
        setIndicatorState(isError ? 'error' : 'saved', message);
    }

    // Outside-click closes any open multi-select on any grid. One
    // global listener handles every grid on the page.
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.multi-select[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.open = false;
        });
    });

    // ============================================================
    // Per-grid — every .choices-grid-wrap on the page gets one
    // call. All state (extraId, body, newRow, etc.) is closure-local.
    // ============================================================
    function initGrid(rootEl) {
        var extraId = parseInt(rootEl.dataset.extraId, 10);
        if (!extraId) return;

        var body          = rootEl.querySelector('.grid-body');
        var newRow        = rootEl.querySelector('.new-row');
        if (!body || !newRow) return;
        var newLabel      = rootEl.querySelector('.new-label-input');
        var newSystem     = rootEl.querySelector('.new-system-details');
        var newSystemSummary = newSystem && newSystem.querySelector('.new-system-summary');
        var newSystemAll  = newSystem && newSystem.querySelector('.new-system-all-cb');
        var newSystemOnes = newSystem ? newSystem.querySelectorAll('.new-system-one') : [];

    function api(action, params) {
        var fd = new FormData();
        fd.append('action',   action);
        fd.append('extra_id', String(extraId));
        Object.keys(params || {}).forEach(function (k) {
            fd.append(k, params[k] == null ? '' : String(params[k]));
        });
        return fetch(endpoint, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () {
                throw new Error('Server returned a non-JSON response.');
            }).then(function (data) {
                if (!data.ok) throw new Error(data.error || 'Unknown error.');
                return data;
            });
        });
    }

    function withSavingState(row, promise) {
        if (!row) return promise;
        row.classList.remove('just-saved', 'is-error');
        row.classList.add('is-saving');
        setIndicatorState('saving');
        return promise.then(function (data) {
            row.classList.remove('is-saving');
            row.classList.add('just-saved');
            setTimeout(function () { row.classList.remove('just-saved'); }, 700);
            setIndicatorState('saved');
            return data;
        }).catch(function (err) {
            row.classList.remove('is-saving');
            row.classList.add('is-error');
            setIndicatorState('error', err.message || 'Save failed');
            throw err;
        });
    }

    function valueFromCell(el) {
        if (el.type === 'checkbox') return el.checked ? '1' : '0';
        return el.value;
    }
    function captureLast(el) { el._lastSaved = valueFromCell(el); }

    function saveCell(el) {
        var row = el.closest('tr');
        if (!row || !row.dataset.id) return Promise.resolve();
        var field = el.dataset.field;
        if (!field) return Promise.resolve();
        var current = valueFromCell(el);
        if (el._lastSaved === current) return Promise.resolve();

        return withSavingState(row, api('update', {
            choice_id: row.dataset.id,
            field:     field,
            value:     current
        })).then(function () {
            el._lastSaved = current;
            if (field === 'is_default' && el.checked) {
                var sysSel = row.querySelector('select[data-field="system_id"]');
                var rowSys = sysSel ? sysSel.value : '0';
                body.querySelectorAll('tr[data-id]').forEach(function (other) {
                    if (other === row) return;
                    var otherSysSel = other.querySelector('select[data-field="system_id"]');
                    var otherSys    = otherSysSel ? otherSysSel.value : '0';
                    if (otherSys === rowSys) {
                        var d = other.querySelector('input[data-field="is_default"]');
                        if (d && d.checked) {
                            d.checked = false;
                            d._lastSaved = '0';
                        }
                    }
                });
            }
            if (field === 'active') {
                row.classList.toggle('is-inactive', !el.checked);
            }
        }).catch(function () {
            if (el._lastSaved !== undefined) {
                if (el.type === 'checkbox') el.checked = el._lastSaved === '1';
                else                        el.value   = el._lastSaved;
            }
        });
    }

    function buildRow(choice) {
        var tr = document.createElement('tr');
        tr.dataset.id = String(choice.id);
        if (!choice.active) tr.classList.add('is-inactive');

        function cellInput(field, value, opts) {
            var td = document.createElement('td');
            td.className = 'col-' + (opts.col || 'price');
            var inp = document.createElement('input');
            inp.className = 'cell-input' + (opts.num ? ' num' : '');
            inp.dataset.field = field;
            if (opts.type)      inp.type      = opts.type;
            if (opts.step)      inp.step      = opts.step;
            if (opts.maxlength) inp.maxLength = opts.maxlength;
            inp.autocomplete = 'off';
            inp.dataset.formType = 'other';
            inp.dataset.lpignore = 'true';
            inp.dataset['1pIgnore'] = 'true';
            inp.value = value;
            captureLast(inp);
            td.appendChild(inp);
            return td;
        }
        function cellToggle(field, checked) {
            var td = document.createElement('td');
            td.className = 'col-toggle';
            var inp = document.createElement('input');
            inp.type = 'checkbox';
            inp.dataset.field = field;
            inp.checked = !!checked;
            captureLast(inp);
            td.appendChild(inp);
            return td;
        }

        var tdDrag = document.createElement('td');
        tdDrag.className = 'col-drag drag-col';
        tdDrag.title = 'Drag to reorder';
        tdDrag.textContent = '⋮⋮';
        tr.appendChild(tdDrag);

        tr.appendChild(cellInput('label', choice.label, { col: 'label', maxlength: 150 }));

        var tdSys = document.createElement('td');
        tdSys.className = 'col-system';
        tdSys.appendChild(buildSystemMultiSelect(choice.system_id == null ? null : choice.system_id));
        tr.appendChild(tdSys);

        // Bands cell — only when the server-rendered grid shows the
        // column. New/duplicated choices carry no band scope, so the
        // widget opens on "All bands"; choice.bands is honoured if a
        // future API response ever returns a pre-scoped list.
        if (bandsColShown) {
            var tdBands = document.createElement('td');
            tdBands.className = 'col-bands';
            tdBands.appendChild(buildBandMultiSelect(choice.bands || []));
            tr.appendChild(tdBands);
        }

        tr.appendChild(cellInput('price_delta',     choice.price_delta,     { type: 'number', step: '0.01', num: true }));
        tr.appendChild(cellInput('price_percent',   choice.price_percent,   { type: 'number', step: '0.01', num: true }));
        tr.appendChild(cellInput('price_per_metre', choice.price_per_metre, { type: 'number', step: '0.01', num: true }));

        tr.appendChild(cellToggle('is_default', choice.is_default));
        tr.appendChild(cellToggle('active',     choice.active));

        var tdActions = document.createElement('td');
        tdActions.className = 'col-actions row-actions';
        tdActions.innerHTML =
            '<a href="/admin/products/extra-choice-edit.php?id=' + choice.id +
                '" class="btn-more" title="Full edit page — width table, thumbnail">Edit</a>' +
            '<a href="/admin/products/extras.php?product_id=<?= (int) $gridProductId ?>&parent_choice=' + choice.id +
                '#add-option" class="btn-sub" title="Add a follow-up option that appears only when this choice is selected">+ Sub-option</a>' +
            '<button type="button" class="btn-duplicate" title="Clone this choice (e.g. same label on a different system)">Duplicate</button>' +
            '<button type="button" class="btn-delete" title="Delete">&times;</button>';
        tr.appendChild(tdActions);

        return tr;
    }

    function updateCount() {}

    // Mirrors make_render_band_multi_select() in choices_grid_helpers.php
    // so a JS-built row's Bands widget is byte-for-byte equivalent to a
    // server-rendered one: same classes (.row-band-tick / data-band),
    // same "All bands" empty-string checkbox, same summary semantics.
    // The per-row change handler (delegated on tbody) then drives saves
    // for these rows with no extra wiring.
    function buildBandMultiSelect(pickedBands) {
        var picked = pickedBands || [];

        var details = document.createElement('details');
        details.className = 'multi-select row-multi row-bands';

        var summary = document.createElement('summary');
        if (picked.length === 0)      summary.textContent = 'All bands';
        else if (picked.length === 1) summary.textContent = picked[0];
        else                          summary.textContent = picked.length + ' bands';
        details.appendChild(summary);

        var opts = document.createElement('div');
        opts.className = 'multi-opts';

        var allLabel = document.createElement('label');
        var allCb    = document.createElement('input');
        allCb.type      = 'checkbox';
        allCb.className  = 'row-band-tick';
        allCb.dataset.band = '';
        if (picked.length === 0) allCb.checked = true;
        var allStrong = document.createElement('strong');
        allStrong.textContent = 'All bands';
        allLabel.appendChild(allCb);
        allLabel.appendChild(document.createTextNode(' '));
        allLabel.appendChild(allStrong);
        opts.appendChild(allLabel);

        if (knownBandsList.length) {
            opts.appendChild(document.createElement('hr'));
            knownBandsList.forEach(function (b) {
                var lbl = document.createElement('label');
                var cb  = document.createElement('input');
                cb.type     = 'checkbox';
                cb.className = 'row-band-tick';
                cb.dataset.band = String(b);
                if (picked.indexOf(b) !== -1) cb.checked = true;
                lbl.appendChild(cb);
                lbl.appendChild(document.createTextNode(' ' + b));
                opts.appendChild(lbl);
            });
        }

        details.appendChild(opts);
        return details;
    }

    function buildSystemMultiSelect(currentSystemId) {
        var isAll = currentSystemId == null;

        var details = document.createElement('details');
        details.className = 'multi-select row-multi';

        var summary = document.createElement('summary');
        summary.textContent = 'All systems';
        if (!isAll) {
            for (var i = 0; i < systemsList.length; i++) {
                if (systemsList[i].id === currentSystemId) {
                    summary.textContent = systemsList[i].name;
                    break;
                }
            }
        }
        details.appendChild(summary);

        var opts = document.createElement('div');
        opts.className = 'multi-opts';

        var allLabel = document.createElement('label');
        var allCb    = document.createElement('input');
        allCb.type    = 'checkbox';
        allCb.className = 'row-system-tick';
        allCb.dataset.system = '0';
        if (isAll) { allCb.checked = true; allCb.disabled = true; }
        var allStrong = document.createElement('strong');
        allStrong.textContent = 'All systems';
        allLabel.appendChild(allCb);
        allLabel.appendChild(document.createTextNode(' '));
        allLabel.appendChild(allStrong);
        opts.appendChild(allLabel);

        if (systemsList.length) {
            var hr = document.createElement('hr');
            opts.appendChild(hr);

            systemsList.forEach(function (s) {
                var lbl = document.createElement('label');
                var cb  = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'row-system-tick';
                cb.dataset.system = String(s.id);
                var isCurrent = (s.id === currentSystemId);
                if (isCurrent) cb.checked = true;
                if (isCurrent) cb.disabled = true;
                lbl.appendChild(cb);
                lbl.appendChild(document.createTextNode(' ' + s.name));
                opts.appendChild(lbl);
            });
        }

        details.appendChild(opts);
        return details;
    }

    function spawnSibling(sourceRow, systemId, checkbox) {
        var allCb = sourceRow.querySelector('.row-system-tick[data-system="0"]');
        var isAllRow = allCb && allCb.checked;

        if (isAllRow) {
            return withSavingState(sourceRow, api('update', {
                choice_id: sourceRow.dataset.id,
                field:     'system_id',
                value:     systemId
            })).then(function () {
                allCb.checked  = false;
                checkbox.disabled = true;

                var sysName = '?';
                for (var i = 0; i < systemsList.length; i++) {
                    if (String(systemsList[i].id) === String(systemId)) {
                        sysName = systemsList[i].name;
                        break;
                    }
                }
                var summary = sourceRow.querySelector('.multi-select > summary');
                if (summary) summary.textContent = sysName;
            }).catch(function () {
                checkbox.checked = false;
            });
        }

        return withSavingState(sourceRow, api('duplicate', {
            choice_id: sourceRow.dataset.id,
            system_id: systemId
        })).then(function (data) {
            var newRowEl = buildRow(data.choice);
            sourceRow.parentNode.insertBefore(newRowEl, sourceRow.nextSibling);
            updateCount();
            checkbox.disabled = true;
        }).catch(function () {
            checkbox.checked = false;
        });
    }

    function convertToAllSystems(sourceRow, allCb) {
        return withSavingState(sourceRow, api('update', {
            choice_id: sourceRow.dataset.id,
            field:     'system_id',
            value:     '0'
        })).then(function () {
            allCb.disabled = true;

            var details = sourceRow.querySelector('details.multi-select');
            if (details) {
                var locked = details.querySelector(
                    '.row-system-tick[data-system]:not([data-system="0"])[disabled]'
                );
                if (locked) {
                    locked.disabled = false;
                    locked.checked  = false;
                }
                var summary = details.querySelector('summary');
                if (summary) summary.textContent = 'All systems';
            }
        }).catch(function () {
            allCb.checked = false;
        });
    }

    body.addEventListener('focusin', function (e) {
        var el = e.target;
        if (el.matches('.cell-input, .cell-select, input[type="checkbox"]')) {
            if (el._lastSaved === undefined) captureLast(el);
        }
    });

    body.addEventListener('change', function (e) {
        var el = e.target;
        if (el.classList.contains('row-system-tick')) {
            if (!el.checked) return;
            var sourceRow = el.closest('tr');
            if (!sourceRow || !sourceRow.dataset.id) return;
            if (el.dataset.system === '0') {
                convertToAllSystems(sourceRow, el);
            } else {
                spawnSibling(sourceRow, el.dataset.system, el);
            }
            return;
        }
        if (el === newLabel || el === newSystem) return;
        if (el.matches('select.cell-select, input[type="checkbox"]')) {
            saveCell(el);
        }
    });
    body.addEventListener('blur', function (e) {
        var el = e.target;
        if (el === newLabel || el === newSystem) return;
        if (el.matches('input.cell-input')) saveCell(el);
    }, true);

    body.addEventListener('keydown', function (e) {
        var el = e.target;
        if (!el.matches('input.cell-input')) return;
        if (el === newLabel) return;
        if (e.key === 'Enter') {
            e.preventDefault();
            el.blur();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            if (el._lastSaved !== undefined) el.value = el._lastSaved;
            el.blur();
        }
    });

    body.addEventListener('click', function (e) {
        var btn = e.target;
        if (btn.classList.contains('btn-duplicate')) {
            var row = btn.closest('tr');
            if (!row || !row.dataset.id) return;
            withSavingState(row, api('duplicate', { choice_id: row.dataset.id }))
                .then(function (data) {
                    var newRowEl = buildRow(data.choice);
                    row.parentNode.insertBefore(newRowEl, row.nextSibling);
                    updateCount();
                    var firstInput = newRowEl.querySelector('select[data-field="system_id"]');
                    if (firstInput) firstInput.focus();
                }).catch(function () { });
        } else if (btn.classList.contains('btn-delete')) {
            var row = btn.closest('tr');
            if (!row || !row.dataset.id) return;
            var label = row.querySelector('input[data-field="label"]');
            var name  = label ? label.value : 'this choice';
            if (!confirm('Delete "' + name + '"?')) return;
            withSavingState(row, api('delete', { choice_id: row.dataset.id }))
                .then(function () {
                    row.parentNode.removeChild(row);
                    updateCount();
                }).catch(function () { });
        }
    });

    function syncMultiSelect(changed) {
        if (changed === newSystemAll) {
            if (newSystemAll.checked) {
                newSystemOnes.forEach(function (cb) { cb.checked = false; });
            }
        } else if (changed && changed.classList.contains('new-system-one')) {
            if (changed.checked) newSystemAll.checked = false;
        }
        var anyOne = false;
        newSystemOnes.forEach(function (cb) { if (cb.checked) anyOne = true; });
        if (!anyOne && !newSystemAll.checked) newSystemAll.checked = true;

        var picked = [];
        newSystemOnes.forEach(function (cb) {
            if (cb.checked) {
                var span = cb.parentNode.querySelector('span');
                picked.push(span ? span.textContent : cb.value);
            }
        });
        if (newSystemAll.checked || picked.length === 0) {
            newSystemSummary.textContent = 'All systems';
        } else if (picked.length === 1) {
            newSystemSummary.textContent = picked[0];
        } else if (picked.length === 2) {
            newSystemSummary.textContent = picked.join(' + ');
        } else {
            newSystemSummary.textContent = picked.length + ' systems';
        }
    }

    function resetMultiSelect() {
        newSystemAll.checked = true;
        newSystemOnes.forEach(function (cb) { cb.checked = false; });
        syncMultiSelect();
    }

    if (newSystem) {
        newSystem.addEventListener('change', function (e) {
            if (e.target.matches('input[type="checkbox"]')) {
                syncMultiSelect(e.target);
            }
        });
    }

    function commitNewRow(focusNext) {
        var label = newLabel.value.trim();
        if (label === '') return Promise.resolve();

        var fd = new FormData();
        fd.append('label', label);
        if (!newSystemAll.checked) {
            newSystemOnes.forEach(function (cb) {
                if (cb.checked) fd.append('system_ids[]', cb.value);
            });
        }

        newRow.classList.add('is-saving');

        fd.append('action',   'create');
        fd.append('extra_id', String(extraId));

        return fetch(endpoint, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!data.ok) throw new Error(data.error || 'Unknown error.');
                return data;
            });
        }).then(function (data) {
            newRow.classList.remove('is-saving');
            newRow.classList.add('just-saved');
            setTimeout(function () { newRow.classList.remove('just-saved'); }, 700);

            var rows = data.choices || (data.choice ? [data.choice] : []);
            rows.forEach(function (c) {
                body.insertBefore(buildRow(c), newRow);
            });
            updateCount();
            flashIndicator(rows.length === 1 ? 'Saved' : 'Saved (' + rows.length + ' rows)');

            newLabel.value = '';
            resetMultiSelect();
            newSystem.open = false;

            if (focusNext) newLabel.focus();
        }).catch(function (err) {
            newRow.classList.remove('is-saving');
            newRow.classList.add('is-error');
            flashIndicator(err.message || 'Could not add', true);
            setTimeout(function () { newRow.classList.remove('is-error'); }, 2000);
        });
    }

    if (newLabel) {
        newLabel.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                commitNewRow(true);
            } else if (e.key === 'Escape') {
                newLabel.value = '';
                resetMultiSelect();
                if (newSystem) newSystem.open = false;
            }
        });
    }

    function maybeCommitNewRow() {
        setTimeout(function () {
            var active = document.activeElement;
            var insideMs = newSystem && newSystem.contains(active);
            if (active !== newLabel
             && !insideMs
             && newLabel && newLabel.value.trim() !== '') {
                commitNewRow(false);
            }
        }, 150);
    }
    if (newLabel)  newLabel.addEventListener('blur', maybeCommitNewRow);
    if (newSystem) newSystem.addEventListener('focusout', maybeCommitNewRow);

    // ============================================================
    // Per-row band-scoping checkboxes (.row-band-tick)
    //
    // Each tick / untick replaces the per-choice band scope in one
    // POST. Delegated on tbody so it works for rows the grid
    // spawned dynamically (sibling-system row, post-commit new row).
    //
    // Behaviour:
    //   - "All bands" checkbox carries data-band="" (the empty
    //     string). When ticked, untick every specific band — that's
    //     the "applies to every band" semantic backend-side.
    //   - Ticking a specific band unticks "All bands". If after a
    //     change no specifics are ticked, "All bands" auto-rechecks
    //     so the user can't be in a "selected nothing" state.
    //   - Summary text updates live to "All bands" / "<one band>" /
    //     "N bands" so the closed widget tells the truth at a glance.
    // ============================================================
    body.addEventListener('change', function (e) {
        var t = e.target;
        if (!t.classList.contains('row-band-tick')) return;

        var tr = t.closest('tr[data-id]');
        if (!tr) return;
        var choiceId = parseInt(tr.dataset.id, 10);
        if (!choiceId) return;

        var details   = t.closest('details');
        if (!details) return;
        var allBox    = details.querySelector('.row-band-tick[data-band=""]');
        var specBoxes = details.querySelectorAll('.row-band-tick[data-band]:not([data-band=""])');

        // Snapshot the widget state BEFORE the optimistic mutation so a
        // rejected save can roll the UI back (mirrors saveCell()). Without
        // this, a failed save left the new ticks/summary showing while the
        // status pill auto-reverted to "All changes saved" — the admin
        // would think a band scope persisted when it hadn't.
        var allTicks   = details.querySelectorAll('.row-band-tick');
        var priorChecked = [];
        allTicks.forEach(function (cb) { priorChecked.push(cb.checked); });
        var summaryForRestore = details.querySelector('summary');
        var priorSummaryText  = summaryForRestore ? summaryForRestore.textContent : '';
        function restoreBandWidget() {
            allTicks.forEach(function (cb, i) { cb.checked = priorChecked[i]; });
            if (summaryForRestore) summaryForRestore.textContent = priorSummaryText;
        }

        if (t === allBox) {
            if (t.checked) {
                specBoxes.forEach(function (cb) { cb.checked = false; });
            } else {
                // Don't let the user untick "All bands" without
                // ticking at least one specific — re-check and bail.
                t.checked = true;
                return;
            }
        } else {
            allBox.checked = false;
            var anyChecked = false;
            specBoxes.forEach(function (cb) { if (cb.checked) anyChecked = true; });
            if (!anyChecked) allBox.checked = true;
        }

        var picked = [];
        specBoxes.forEach(function (cb) {
            if (cb.checked) picked.push(cb.dataset.band);
        });

        var summary = details.querySelector('summary');
        if (summary) {
            if (picked.length === 0)        summary.textContent = 'All bands';
            else if (picked.length === 1)   summary.textContent = picked[0];
            else                            summary.textContent = picked.length + ' bands';
        }

        setIndicatorState('saving');
        var fd = new FormData();
        fd.append('action',    'set_bands');
        fd.append('extra_id',  String(extraId));
        fd.append('choice_id', String(choiceId));
        picked.forEach(function (b) { fd.append('bands[]', b); });

        fetch(endpoint, {
            method: 'POST', body: fd,
            headers: { 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () {
                throw new Error('Server returned a non-JSON response.');
            });
        }).then(function (data) {
            if (data.ok) setIndicatorState('saved');
            else throw new Error(data.error || 'Save failed');
        }).catch(function (err) {
            restoreBandWidget();
            setIndicatorState('error', err.message || 'Save failed');
        });
    });

    // ============================================================
    // "Set all" — header control that applies one band scope to
    // every choice in this grid in a single request (set_bands_all),
    // so the admin doesn't have to change rows one by one. Lives in
    // the Bands <th>; only present when the band column is shown and
    // the product has known bands.
    // ============================================================
    var setAll = rootEl.querySelector('.bands-set-all');
    if (setAll) {
        var setAllAll   = setAll.querySelector('.setall-band-tick[data-band=""]');
        var setAllSpecs = setAll.querySelectorAll('.setall-band-tick[data-band]:not([data-band=""])');
        var setAllApply = setAll.querySelector('.setall-apply');

        // Mutually-exclusive logic mirrors the per-row widget:
        // ticking "All bands" clears specifics; ticking a specific
        // clears "All bands"; un-ticking the last specific re-checks
        // "All bands" so the picker is never in an empty state.
        setAll.addEventListener('change', function (e) {
            var t = e.target;
            if (!t.classList.contains('setall-band-tick')) return;
            if (t === setAllAll) {
                if (t.checked) {
                    setAllSpecs.forEach(function (cb) { cb.checked = false; });
                } else {
                    t.checked = true;   // can't untick "All" directly
                }
            } else {
                setAllAll.checked = false;
                var any = false;
                setAllSpecs.forEach(function (cb) { if (cb.checked) any = true; });
                if (!any) setAllAll.checked = true;
            }
        });

        if (setAllApply) {
            setAllApply.addEventListener('click', function () {
                var picked = [];
                setAllSpecs.forEach(function (cb) { if (cb.checked) picked.push(cb.dataset.band); });

                var rowCount = body.querySelectorAll('tr[data-id]').length;
                if (rowCount === 0) { setAll.open = false; return; }

                var scopeText = picked.length === 0
                    ? '“All bands”'
                    : (picked.length === 1 ? picked[0] : picked.length + ' bands');
                if (!confirm('Set all ' + rowCount + ' choice' + (rowCount === 1 ? '' : 's')
                           + ' to ' + scopeText + '? This replaces their current band scope.')) {
                    return;
                }

                setAll.open = false;
                setIndicatorState('saving');

                var fd = new FormData();
                fd.append('action',   'set_bands_all');
                fd.append('extra_id', String(extraId));
                picked.forEach(function (b) { fd.append('bands[]', b); });

                fetch(endpoint, {
                    method: 'POST', body: fd,
                    headers: { 'X-CSRF-Token': csrfToken },
                    credentials: 'same-origin'
                }).then(function (r) {
                    return r.json().catch(function () {
                        throw new Error('Server returned a non-JSON response.');
                    });
                }).then(function (data) {
                    if (!data.ok) throw new Error(data.error || 'Save failed');
                    setIndicatorState('saved');
                    // Reload so every row's widget reflects the new scope —
                    // simpler than surgically re-rendering each one, and
                    // matches how bulk-add refreshes the grid.
                    window.location.reload();
                }).catch(function (err) {
                    setIndicatorState('error', err.message || 'Save failed');
                });
            });
        }
    }

    // ============================================================
    // "Set all" — header control that sets the "Available on" system
    // for every choice in this grid at once (set_system_all). Single
    // target (All systems, or one system), unlike the multi-band one.
    // ============================================================
    var sysSetAll = rootEl.querySelector('.system-set-all');
    if (sysSetAll) {
        var sysSetAllApply = sysSetAll.querySelector('.setall-system-apply');
        if (sysSetAllApply) {
            sysSetAllApply.addEventListener('click', function () {
                var picked = sysSetAll.querySelector('.setall-system-pick:checked');
                var sysVal = picked ? picked.value : '';
                var sysLabel = picked
                    ? (picked.parentNode.textContent || '').trim()
                    : 'All systems';

                var rowCount = body.querySelectorAll('tr[data-id]').length;
                if (rowCount === 0) { sysSetAll.open = false; return; }

                if (!confirm('Set "Available on" for all ' + rowCount + ' choice'
                           + (rowCount === 1 ? '' : 's') + ' to ' + sysLabel
                           + '? This replaces their current system.')) {
                    return;
                }

                sysSetAll.open = false;
                setIndicatorState('saving');

                var fd = new FormData();
                fd.append('action',    'set_system_all');
                fd.append('extra_id',  String(extraId));
                fd.append('system_id', sysVal);

                fetch(endpoint, {
                    method: 'POST', body: fd,
                    headers: { 'X-CSRF-Token': csrfToken },
                    credentials: 'same-origin'
                }).then(function (r) {
                    return r.json().catch(function () {
                        throw new Error('Server returned a non-JSON response.');
                    });
                }).then(function (data) {
                    if (!data.ok) throw new Error(data.error || 'Save failed');
                    setIndicatorState('saved');
                    // Reload so each row's "Available on" widget reflects
                    // the new system (matches the bands "Set all").
                    window.location.reload();
                }).catch(function (err) {
                    setIndicatorState('error', err.message || 'Save failed');
                });
            });
        }
    }

    // ============================================================
    // "Set all" — header controls on the price columns (Flat £ / % /
    // £ per metre). Type a value, apply it to every choice at once
    // (set_price_all). One control per price column, keyed by data-field.
    // ============================================================
    rootEl.querySelectorAll('.price-set-all').forEach(function (wrap) {
        var field = wrap.dataset.field;
        var input = wrap.querySelector('.setall-price-input');
        var apply = wrap.querySelector('.setall-price-apply');
        if (!field || !input || !apply) return;
        apply.addEventListener('click', function () {
            var raw = (input.value || '').trim();
            if (raw === '' || isNaN(parseFloat(raw))) {
                input.focus();
                return;
            }
            var num = parseFloat(raw);
            var rowCount = body.querySelectorAll('tr[data-id]').length;
            if (rowCount === 0) { wrap.open = false; return; }

            var colName = field === 'price_percent' ? '%'
                        : (field === 'price_per_metre' ? '£/m' : 'flat £');
            if (!confirm('Set ' + colName + ' to ' + num.toFixed(2) + ' for all '
                       + rowCount + ' choice' + (rowCount === 1 ? '' : 's') + '?')) {
                return;
            }

            wrap.open = false;
            setIndicatorState('saving');

            var fd = new FormData();
            fd.append('action',   'set_price_all');
            fd.append('extra_id', String(extraId));
            fd.append('field',    field);
            fd.append('value',    String(num));

            fetch(endpoint, {
                method: 'POST', body: fd,
                headers: { 'X-CSRF-Token': csrfToken },
                credentials: 'same-origin'
            }).then(function (r) {
                return r.json().catch(function () {
                    throw new Error('Server returned a non-JSON response.');
                });
            }).then(function (data) {
                if (!data.ok) throw new Error(data.error || 'Save failed');
                setIndicatorState('saved');
                // Reload so every row's cell shows the new value.
                window.location.reload();
            }).catch(function (err) {
                setIndicatorState('error', err.message || 'Save failed');
            });
        });
    });

    }  // end initGrid

    document.querySelectorAll('.choices-grid-wrap').forEach(initGrid);
})();
</script>
