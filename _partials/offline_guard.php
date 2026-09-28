<?php
declare(strict_types=1);

/**
 * "Never lose work": the shared offline layer, included on every signed-in page
 * via _partials/sidebar.php.
 *
 *   • A "No signal" bar whenever the connection drops (or a request fails).
 *   • window.ybOffline.draft: autosaved form drafts on this device (localStorage).
 *   • window.ybOffline.outbox: changes saved with no signal. Each one is a form
 *     POST (action + fields) that's sent automatically when the signal returns,
 *     from whatever page is open, with a fresh CSRF token (/api/csrf.php).
 *   • window.ybOffline.protectForm(): autosave + "put it back" for a plain form.
 *
 * Items are tagged with the signed-in user id, so a shared tablet never sends
 * one person's saved work under another person's login.
 *
 * Needs $user (current_user()) in scope, as sidebar.php already does.
 */

$ybOfflineUserId = (int) (current_user()['user_id'] ?? 0);
?>
<style>
  #yb-net-bar{position:fixed;left:50%;bottom:14px;transform:translateX(-50%);z-index:9990;display:none;
    max-width:calc(100vw - 32px);padding:9px 16px;border-radius:999px;font:600 14px/1.3 system-ui,sans-serif;
    box-shadow:0 4px 18px rgba(0,0,0,.18);background:#2b2f36;color:#fff;text-align:center}
  #yb-net-bar.is-off{background:#8a4b00}
  #yb-net-bar.is-warn{background:#9b1c1c}
  #yb-net-bar a{color:#fff;text-decoration:underline}
  .yb-restore-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 12px;padding:10px 12px;
    border:1px solid #e0b252;background:#fff8e6;color:#4a3700;border-radius:8px;font-size:14px}
  .yb-restore-bar button{padding:5px 12px;border-radius:6px;border:1px solid #c99a2e;background:#fff;cursor:pointer;font:inherit}
  .yb-restore-bar button.primary{background:#c99a2e;color:#fff;border-color:#c99a2e}
  [data-theme="dark"] .yb-restore-bar{background:#3a3020;color:#f3e2b8;border-color:#7a6230}
</style>
<div id="yb-net-bar" role="status" aria-live="polite"></div>
<script>
(function () {
    'use strict';
    var USER_ID = <?= $ybOfflineUserId ?>;
    var OUTBOX_KEY = 'yb.outbox';
    var csrf = <?= json_encode(csrf_token()) ?>;
    var netDown = !navigator.onLine;
    var loginNeeded = false;
    var sending = false;
    var listeners = [];

    function lsGet(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } }
    function lsSet(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); return true; } catch (e) { return false; } }
    function lsDel(k) { try { localStorage.removeItem(k); } catch (e) {} }
    function uid() { return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10); }
    function changed() { renderBar(); listeners.forEach(function (fn) { try { fn(); } catch (e) {} }); }

    // Any failed request means "no signal" in practice (a weak signal often
    // still reports navigator.onLine = true); any success means it's back.
    var realFetch = window.fetch.bind(window);
    window.fetch = function () {
        return realFetch.apply(null, arguments).then(function (r) {
            if (netDown) { netDown = false; changed(); flushSoon(); }
            return r;
        }, function (err) {
            if (err && err.name !== 'AbortError' && !netDown) { netDown = true; changed(); }
            throw err;
        });
    };
    window.addEventListener('offline', function () { netDown = true; changed(); });
    window.addEventListener('online',  function () { netDown = false; changed(); flushSoon(); });

    // ---- drafts ------------------------------------------------------------
    var draft = {
        key:   function (k) { return 'yb.draft.' + USER_ID + '.' + k; },
        load:  function (k) { return lsGet(draft.key(k)); },
        save:  function (k, data) { data.savedAt = Date.now(); return lsSet(draft.key(k), data); },
        clear: function (k) { lsDel(draft.key(k)); }
    };

    // ---- outbox ------------------------------------------------------------
    function all()  { var a = lsGet(OUTBOX_KEY); return Array.isArray(a) ? a : []; }
    function mine() { return all().filter(function (i) { return i.userId === USER_ID; }); }
    function write(a) { lsSet(OUTBOX_KEY, a); changed(); }
    var outbox = {
        list:  function (scope) { return mine().filter(function (i) { return scope == null || i.scope === String(scope); }); },
        add:   function (item) {
            item.id = uid(); item.userId = USER_ID; item.createdAt = Date.now();
            item.status = 'waiting'; item.scope = String(item.scope || '');
            var a = all(); a.push(item);
            if (!lsSet(OUTBOX_KEY, a)) return null;   // storage full: caller must not clear the form
            changed(); return item;
        },
        remove: function (id) { write(all().filter(function (i) { return i.id !== id; })); },
        update: function (id, patch) { write(all().map(function (i) { return i.id === id ? Object.assign(i, patch) : i; })); },
        flush:  flush
    };

    // POST a saved form. Resolves to {kind: saved|rejected|offline|login, message, url}.
    //   saved    = the server accepted it (followed redirect, no error alert)
    //   rejected = the server said no (its flash error, e.g. "size too big")
    //   offline  = no signal; login = signed out
    async function send(action, pairs, retried) {
        var body = new URLSearchParams();
        pairs.forEach(function (p) { if (p[0] !== '_csrf') body.append(p[0], p[1]); });
        body.append('_csrf', csrf);
        var r;
        try {
            r = await fetch(action, { method: 'POST', body: body, credentials: 'same-origin', redirect: 'follow' });
        } catch (e) {
            return { kind: 'offline' };
        }
        if (r.status === 419 || /\/auth\/login\.php/.test(r.url)) {
            if (!retried) {
                try {
                    var t = await fetch('/api/csrf.php?_=' + Date.now(), { credentials: 'same-origin' });
                    if (t.ok) {
                        var j = await t.json();
                        if (j.token && j.user_id === USER_ID) { csrf = j.token; return send(action, pairs, true); }
                    }
                } catch (e) { return { kind: 'offline' }; }
            }
            return { kind: 'login' };
        }
        var html = '';
        try { html = await r.text(); } catch (e) { return { kind: 'offline' }; }
        if (!r.ok) return { kind: 'rejected', message: 'The server could not save this (error ' + r.status + ').' };
        var doc = new DOMParser().parseFromString(html, 'text/html');
        // The flash error is the red alert with role="alert" (edit.php). Other red
        // boxes (e.g. "this quote expired", role="status") are not a refusal.
        var err = doc.querySelector('.alert-error[role="alert"]');
        if (err) return { kind: 'rejected', message: err.textContent.trim(), url: r.url };
        // The green "Blind 3 added (£61.00)." tells us what the server charged.
        var ok = doc.querySelector('.alert-success');
        return { kind: 'saved', url: r.url, message: ok ? ok.textContent.trim() : '' };
    }

    // ---- New quotes started with no signal ("provisional") ---------------
    // Started on the tablet (the blank quote screen, edit.php?offline_template=1)
    // with a temporary reference. The outbox holds a 'create' item (a new.php
    // post of the customer details) AHEAD of the quote's blinds. When the signal
    // is back the quote is created first, and that gives it its real number.
    // Its blinds are then re-pointed at it and sent as normal.
    var PROV_KEY = 'yb.prov.' + USER_ID;
    function provAll() { var a = lsGet(PROV_KEY); return (a && typeof a === 'object') ? a : {}; }
    function detailsForCreate(pairs, pid) {
        return pairs.filter(function (p) { return p[0] !== '_csrf' && p[0] !== 'quote_id' && p[0] !== 'client_ref'; })
                    .concat([['client_ref', pid]]);
    }
    function nameIn(pairs) { var n = pairs.find(function (p) { return p[0] === 'end_customer_name'; }); return n ? n[1] : ''; }
    var prov = {
        all: provAll,
        get: function (pid) { return provAll()[pid] || null; },
        save: function (pid, patch) {
            var a = provAll();
            a[pid] = Object.assign(a[pid] || { pid: pid, createdAt: Date.now() }, patch);
            lsSet(PROV_KEY, a);
            return a[pid];
        },
        // Start one. Returns its reference, or null if the tablet is out of space.
        create: function (detailsPairs) {
            var pid = 'p' + uid();
            detailsPairs = detailsPairs || [];
            prov.save(pid, { details: detailsPairs });
            var item = outbox.add({ scope: 'p:' + pid, kind: 'create', action: '/quote-builder/new.php',
                pairs: detailsForCreate(detailsPairs, pid), summary: 'New quote for ' + (nameIn(detailsPairs) || '(no name yet)') });
            return item ? pid : null;
        },
        // Customer details changed: keep the waiting 'create' in step.
        setDetails: function (pid, detailsPairs) {
            prov.save(pid, { details: detailsPairs });
            write(all().map(function (i) {
                if (i.scope !== 'p:' + pid || i.kind !== 'create') return i;
                return Object.assign(i, { pairs: detailsForCreate(detailsPairs, pid), status: 'waiting', error: null,
                    summary: 'New quote for ' + (nameIn(detailsPairs) || '(no name yet)') });
            }));
        }
    };

    // The 'create' went through: the server's quote id is in the page it sent us to.
    function resolveProv(createItem, res) {
        var m = /edit\.php\?id=(\d+)/.exec(res.url || '');
        if (!m) return false;
        var pid = createItem.scope.slice(2), newId = m[1];
        var p = prov.get(pid) || {};
        var addRef = (p.details || []).find(function (x) { return x[0] === 'additional_reference' && x[1]; });
        var list = all().filter(function (i) { return i.id !== createItem.id; }).map(function (i) {
            if (i.scope !== createItem.scope) return i;
            return Object.assign(i, { scope: newId, pairs: i.pairs.map(function (x) { return x[0] === 'quote_id' ? ['quote_id', newId] : x; }) });
        });
        // new.php doesn't take the additional reference; save it straight after.
        if (addRef) {
            list.unshift({ id: uid(), userId: USER_ID, createdAt: Date.now(), status: 'waiting', scope: newId, kind: 'details',
                action: '/quote-builder/save_details.php', summary: 'Customer details',
                pairs: (p.details || []).filter(function (x) { return x[0] !== 'quote_id'; }).concat([['quote_id', newId]]) });
        }
        write(list);
        var num = /Quote\s+(\S+)\s+created/.exec(res.message || '');
        prov.save(pid, { serverId: newId, number: num ? num[1] : '' });
        document.dispatchEvent(new CustomEvent('yb:prov-created', { detail: { pid: pid, id: newId } }));
        return true;
    }

    // ---- Price differences (flagged, never silently changed) -------------
    var FLAGS_KEY = 'yb.priceflags.' + USER_ID;
    function flags() { var a = lsGet(FLAGS_KEY); return Array.isArray(a) ? a : []; }
    function checkPrice(item, res) {
        if (item.tabletPrice == null) return;
        var m = /£\s?([\d,]+\.\d{2})/.exec(res.message || '');
        if (!m) return;   // e.g. a multi-blind group: no single price in the reply
        var server = parseFloat(m[1].replace(/,/g, ''));
        if (Math.abs(server - item.tabletPrice) < 0.005) return;
        var a = flags();
        a.push({ id: uid(), scope: item.scope, summary: item.summary.replace(/ · £[\d.,]+ \(tablet price\)$/, ''),
                 tablet: item.tabletPrice, server: server, at: Date.now() });
        lsSet(FLAGS_KEY, a);
    }

    var flushTimer = null;
    function flushSoon() { clearTimeout(flushTimer); flushTimer = setTimeout(flush, 400); }

    async function flush() {
        if (sending) return;
        var queue = mine().filter(function (i) { return i.status === 'waiting'; });
        if (!queue.length) return;
        sending = true; changed();
        var sent = [], again = false;
        for (var k = 0; k < queue.length; k++) {
            var item = queue[k];
            // A new quote's blinds wait until the quote itself has been created.
            if (item.scope.indexOf('p:') === 0 && item.kind !== 'create') continue;
            var res = await send(item.action, item.pairs);
            if (res.kind === 'saved' && item.kind === 'create') {
                if (resolveProv(item, res)) { sent.push(item); again = true; break; }   // its blinds go next
                outbox.update(item.id, { status: 'rejected', error: 'The quote could not be created — please check the customer details.' });
            } else if (res.kind === 'saved') {
                checkPrice(item, res);
                outbox.remove(item.id); sent.push(item);
            } else if (res.kind === 'rejected') {
                outbox.update(item.id, { status: 'rejected', error: res.message });
            } else {
                if (res.kind === 'login') loginNeeded = true;
                if (res.kind === 'offline') netDown = true;
                break;
            }
            loginNeeded = false;
        }
        sending = false; changed();
        if (sent.length) {
            document.dispatchEvent(new CustomEvent('yb:outbox-sent', { detail: { items: sent } }));
        }
        if (again) return flush();
    }

    // ---- the bar ----------------------------------------------------------
    var bar = document.getElementById('yb-net-bar');
    function renderBar() {
        if (!bar) return;
        var items = mine();
        var waiting  = items.filter(function (i) { return i.status === 'waiting'; }).length;
        var rejected = items.filter(function (i) { return i.status === 'rejected'; });
        var txt = '', cls = '';
        var nWait = waiting === 1 ? '1 saved change' : waiting + ' saved changes';
        if (netDown) {
            cls = 'is-off';
            txt = 'No signal — anything you save is kept on this tablet'
                + (waiting ? ' (' + nWait + ' waiting)' : '') + '.';
        } else if (loginNeeded && waiting) {
            cls = 'is-warn';
            txt = 'Signed out — <a href="/auth/login.php" target="_blank" rel="noopener">sign in again</a> to send '
                + nWait + '.';
        } else if (sending && waiting) {
            txt = 'Signal back — sending ' + nWait + '…';
        } else if (flags().length) {
            cls = 'is-warn';
            var f0 = flags()[0];
            txt = flags().length + (flags().length === 1 ? ' blind was' : ' blinds were')
                + ' priced differently by the server than on the tablet — <a href="/quote-builder/edit.php?id='
                + encodeURIComponent(f0.scope) + '">check the quote</a>.';
        } else if (rejected.length) {
            cls = 'is-warn';
            var q = rejected[0].scope;
            var qHref = q.indexOf('p:') === 0
                ? '/quote-builder/edit.php?offline_template=1#p=' + encodeURIComponent(q.slice(2))
                : '/quote-builder/edit.php?id=' + encodeURIComponent(q) + '#add-line';
            txt = rejected.length + (rejected.length === 1 ? ' saved change needs' : ' saved changes need')
                + ' checking — <a href="' + qHref + '">open the quote</a>.';
        } else if (waiting) {
            txt = nWait + ' waiting to send.';
        }
        bar.className = cls;
        bar.innerHTML = txt;
        bar.style.display = txt ? 'block' : 'none';
    }

    // ---- plain-form autosave ----------------------------------------------
    // Autosave every named field of `form` under `key`; offer to put a saved
    // draft back on load. opts.queue: when there's no signal on submit, add the
    // form to the outbox (opts.summary() labels it) instead of losing it.
    // opts.needsSignal: message shown when it can't be queued (e.g. a new quote
    // needs the server to create it).
    function fieldPairs(form) {
        var out = [];
        new FormData(form).forEach(function (v, k) { if (typeof v === 'string') out.push([k, v]); });
        return out;
    }
    function restoreBar(beforeEl, text, onRestore, onDiscard) {
        var b = document.createElement('div');
        b.className = 'yb-restore-bar';
        b.innerHTML = '<span></span><button type="button" class="primary">Put it back</button><button type="button">Discard</button>';
        b.firstChild.textContent = text;
        b.children[1].addEventListener('click', function () { b.remove(); onRestore(); });
        b.children[2].addEventListener('click', function () { b.remove(); onDiscard(); });
        beforeEl.parentNode.insertBefore(b, beforeEl);
        return b;
    }
    function when(ts) {
        var d = new Date(ts);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            + (d.toDateString() === new Date().toDateString() ? '' : ' on ' + d.toLocaleDateString());
    }
    function protectForm(form, key, opts) {
        if (!form) return;
        opts = opts || {};
        var skip = { _csrf: 1 };
        function snapshot() { return fieldPairs(form).filter(function (p) { return !skip[p[0]]; }); }
        var initial = JSON.stringify(snapshot());
        var saved = draft.load(key);
        if (saved && saved.pairs && JSON.stringify(saved.pairs) !== initial) {
            restoreBar(form, 'You have unsaved changes here from ' + when(saved.savedAt) + '.', function () {
                var seen = {};
                saved.pairs.forEach(function (p) {
                    var els = form.querySelectorAll('[name="' + CSS.escape(p[0]) + '"]');
                    els.forEach(function (el) {
                        if (el.type === 'checkbox' || el.type === 'radio') el.checked = (el.value === p[1]);
                        else if (!seen[p[0]]) el.value = p[1];
                    });
                    seen[p[0]] = 1;
                });
                // Unticked boxes aren't in the draft: clear any the draft didn't list.
                form.querySelectorAll('input[type=checkbox][name]').forEach(function (el) {
                    if (!saved.pairs.some(function (p) { return p[0] === el.name && p[1] === el.value; })) el.checked = false;
                });
                form.dispatchEvent(new Event('input', { bubbles: true }));
            }, function () { draft.clear(key); });
        } else if (saved) {
            draft.clear(key);
        }
        var t = null;
        function autosave() {
            clearTimeout(t);
            t = setTimeout(function () {
                var s = snapshot();
                if (JSON.stringify(s) === initial) draft.clear(key); else draft.save(key, { pairs: s });
            }, 300);
        }
        form.addEventListener('input', autosave);
        form.addEventListener('change', autosave);
        form.addEventListener('submit', async function (e) {
            if (!opts.queue) {
                if (netDown || !navigator.onLine) {
                    e.preventDefault();
                    // New quote on a tablet set up for offline: start it on the tablet.
                    if (opts.offlineStart && tabletSetUp()) {
                        var details = snapshot();
                        if (!nameIn(details).trim()) { alert('Type the customer’s name first.'); return; }
                        var pid = prov.create(details);
                        if (pid) {
                            draft.clear(key);
                            location.href = '/quote-builder/edit.php?offline_template=1#p=' + pid;
                            return;
                        }
                    }
                    alert(opts.needsSignal || 'No signal right now. Everything you typed is kept on this tablet; try again when the signal is back.');
                }
                // Online: a normal submit. The draft is kept (flagged) until the
                // page it lands on says it went through, so a failed send loses nothing.
                clearTimeout(t);
                var sub = snapshot();
                if (JSON.stringify(sub) !== initial) draft.save(key, { pairs: sub, submitted: true });
                return;
            }
            e.preventDefault();
            var pairs = fieldPairs(form);
            if (e.submitter && e.submitter.name) pairs.push([e.submitter.name, e.submitter.value]);
            var btns = form.querySelectorAll('button[type=submit],input[type=submit]');
            btns.forEach(function (b) { b.disabled = true; });
            var res = await send(form.getAttribute('action'), pairs);
            btns.forEach(function (b) { b.disabled = false; });
            if (res.kind === 'saved') { draft.clear(key); go(res.url); return; }
            if (res.kind === 'rejected') { alert(res.message); return; }
            var item = outbox.add({ scope: opts.scope, action: form.getAttribute('action'), pairs: pairs,
                                    summary: opts.summary ? opts.summary() : 'Saved changes' });
            if (!item) { alert('This tablet is out of storage space, so this could not be kept. Please try again with signal.'); return; }
            draft.clear(key);
            initial = JSON.stringify(snapshot());
            if (res.kind === 'login') loginNeeded = true;
            changed();
        });
    }

    // Go to a page the server just sent us to. If it's THIS page with only a
    // different #anchor, a plain assignment would just scroll, not reload
    // (so a newly saved blind wouldn't show), so reload explicitly.
    function go(url, hash) {
        url = String(url).split('#')[0];
        if (url === location.href.split('#')[0]) {
            history.replaceState(null, '', url + (hash || ''));
            location.reload();
        } else {
            location.href = url + (hash || '');
        }
    }

    // bfcache "Back": the sidebar guard reloads to avoid stale pages, but with
    // no signal a reload would swap the page for the browser's offline error.
    window.ybOffline = {
        userId: USER_ID,
        go: go,
        get online() { return !netDown && navigator.onLine; },
        draft: draft, outbox: outbox, send: send, protectForm: protectForm,
        restoreBar: restoreBar, when: when,
        onChange: function (fn) { listeners.push(fn); }
    };

    // ---- Saved pages (the service worker, /sw.js) ---------------------------
    // Only on a tablet set up for offline (offline/engine.js). It keeps each
    // quote page opened with signal for 14 days, per user, so it opens with no
    // signal too. Turning offline off wipes them (agreed with John 2026-09-28).
    var SW = 'serviceWorker' in navigator ? navigator.serviceWorker : null;
    function tabletSetUp() { return !!lsGet('yb.engine.enabled.' + USER_ID); }
    function swTell(msg) {
        if (!SW) return Promise.resolve();
        return SW.getRegistration('/').then(function (reg) {
            var w = reg && (reg.active || reg.waiting || reg.installing);
            if (w) w.postMessage(msg);
            return reg;
        }).catch(function () {});
    }
    function swEnable(saveUrls) {
        if (!SW || !USER_ID) return Promise.resolve();
        return SW.register('/sw.js', { scope: '/' }).then(function () { return SW.ready; }).then(function (reg) {
            reg.active.postMessage({ type: 'user', uid: USER_ID });
            if (saveUrls && saveUrls.length) reg.active.postMessage({ type: 'save', urls: saveUrls });
        }).catch(function (e) { console.warn('Offline pages not set up:', e); });
    }
    function swWipe() {
        if (!SW) return Promise.resolve();
        return swTell({ type: 'wipe' }).then(function (reg) { if (reg) return reg.unregister(); });
    }
    if (SW && USER_ID) {
        if (tabletSetUp()) swEnable();
        // Not set up for THIS user (e.g. someone else signs in on a shared tablet):
        // make sure the helper never shows another person's saved pages.
        else swTell({ type: 'user', uid: USER_ID });
    }

    // This page came from the tablet's saved copy (no signal): say so.
    if (window.__ybSavedCopy && location.search.indexOf('offline_template') === -1) {
        document.addEventListener('DOMContentLoaded', function () {
            var host = document.querySelector('main') || document.body;
            var note = document.createElement('div');
            note.className = 'yb-restore-bar';
            note.textContent = 'No signal — this is the copy saved on this tablet at ' + when(window.__ybSavedCopy)
                + '. Anything you change is kept here and sent when the signal is back.';
            host.insertBefore(note, host.firstChild);
        });
    }
    window.ybOffline.swEnable = swEnable;
    window.ybOffline.swWipe = swWipe;
    window.ybOffline.prov = prov;

    // On a quote whose blinds the server priced differently from the tablet:
    // list them, with both prices. The server's price is the one on the quote.
    document.addEventListener('DOMContentLoaded', function () {
        var qm = /\/quote-builder\/edit\.php$/.test(location.pathname) && new URLSearchParams(location.search).get('id');
        if (!qm) return;
        var mineFlags = flags().filter(function (f) { return f.scope === String(qm); });
        if (!mineFlags.length) return;
        var host = document.querySelector('main') || document.body;
        var box = document.createElement('div');
        box.className = 'yb-restore-bar';
        box.style.flexDirection = 'column';
        box.style.alignItems = 'stretch';
        var h = document.createElement('strong');
        h.textContent = 'Priced differently when sent from the tablet — the quote uses the server’s price:';
        box.appendChild(h);
        mineFlags.forEach(function (f) {
            var row = document.createElement('div');
            row.textContent = f.summary + ': £' + f.tablet.toFixed(2) + ' on the tablet → £' + f.server.toFixed(2) + ' now.';
            box.appendChild(row);
        });
        var okBtn = document.createElement('button');
        okBtn.type = 'button'; okBtn.textContent = 'OK, checked';
        okBtn.style.alignSelf = 'flex-start';
        okBtn.addEventListener('click', function () {
            lsSet(FLAGS_KEY, flags().filter(function (f) { return f.scope !== String(qm); }));
            box.remove(); changed();
        });
        box.appendChild(okBtn);
        host.insertBefore(box, host.firstChild);
    });

    renderBar();
    if (mine().some(function (i) { return i.status === 'waiting'; })) flushSoon();
    setInterval(function () {
        if (netDown) {
            // Still "no signal"? A weak signal often never fires the browser's
            // 'online' event, so check quietly; a success flips the bar and
            // sends anything waiting (see the fetch wrapper above).
            fetch('/api/csrf.php?_=' + Date.now(), { credentials: 'same-origin' }).catch(function () {});
        } else if (mine().some(function (i) { return i.status === 'waiting'; })) {
            flush();
        }
    }, 20000);
})();
</script>
