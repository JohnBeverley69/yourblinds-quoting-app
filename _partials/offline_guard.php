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

$ybOfflineUserId = (int) ($user['id'] ?? (current_user()['id'] ?? 0));
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
        var err = doc.querySelector('.alert-error, .alert-danger');
        if (err) return { kind: 'rejected', message: err.textContent.trim(), url: r.url };
        return { kind: 'saved', url: r.url };
    }

    var flushTimer = null;
    function flushSoon() { clearTimeout(flushTimer); flushTimer = setTimeout(flush, 400); }

    async function flush() {
        if (sending) return;
        var queue = mine().filter(function (i) { return i.status === 'waiting'; });
        if (!queue.length) return;
        sending = true; changed();
        var sent = [];
        for (var k = 0; k < queue.length; k++) {
            var item = queue[k];
            var res = await send(item.action, item.pairs);
            if (res.kind === 'saved') {
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
        } else if (rejected.length) {
            cls = 'is-warn';
            var q = rejected[0].scope;
            txt = rejected.length + (rejected.length === 1 ? ' saved change needs' : ' saved changes need')
                + ' checking — <a href="/quote-builder/edit.php?id=' + encodeURIComponent(q) + '#add-line">open the quote</a>.';
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
            if (res.kind === 'saved') { draft.clear(key); location.href = res.url; return; }
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

    // bfcache "Back": the sidebar guard reloads to avoid stale pages, but with
    // no signal a reload would swap the page for the browser's offline error.
    window.ybOffline = {
        get online() { return !netDown && navigator.onLine; },
        draft: draft, outbox: outbox, send: send, protectForm: protectForm,
        restoreBar: restoreBar, when: when,
        onChange: function (fn) { listeners.push(fn); }
    };

    renderBar();
    if (mine().some(function (i) { return i.status === 'waiting'; })) flushSoon();
    setInterval(function () {
        if (!netDown && mine().some(function (i) { return i.status === 'waiting'; })) flush();
    }, 20000);
})();
</script>
