<?php
declare(strict_types=1);

/**
 * Floating "Help / Report a problem" widget — on every logged-in page (included
 * from _partials/sidebar.php and the factory shell, _partials/factory_head.php).
 *
 * The user types what went wrong; the widget quietly attaches the context that
 * makes it fixable: page URL + title, viewport, recent JavaScript errors and the
 * last few clicks (carried across page loads in sessionStorage). Server-side
 * /support/report.php adds tenant, user, browser and the deployed git version,
 * saves a support_tickets row and emails the owner. What the user types INTO
 * forms is never captured — breadcrumbs are button/link labels only.
 *
 * Requires auth/middleware.php (csrf_token, e) already loaded.
 */

if (!empty($GLOBALS['_ybSupportWidgetShown'])) {
    return;
}
$GLOBALS['_ybSupportWidgetShown'] = true;
require_once __DIR__ . '/support.php';
?>
<style>
.ybs-fab{position:fixed;right:1rem;bottom:1rem;z-index:900;display:inline-flex;align-items:center;gap:.4rem;
    padding:.55rem .9rem;border:0;border-radius:999px;background:var(--brand,#1f3b5b);color:var(--text-on-brand,#fff);
    font:600 .85rem/1 system-ui,sans-serif;box-shadow:0 4px 14px rgba(0,0,0,.25);cursor:pointer}
.ybs-fab:hover{background:var(--brand-hover,#15294a)}
.ybs-fab:focus-visible{outline:3px solid var(--brand-accent,#93c5fd);outline-offset:2px}
.ybs-fab-q{display:inline-grid;place-items:center;width:1.15rem;height:1.15rem;border-radius:50%;
    background:rgba(255,255,255,.2);font-size:.8rem}
.ybs-panel{position:fixed;right:1rem;bottom:4.25rem;z-index:2000;width:min(380px,calc(100vw - 2rem));
    max-height:calc(100vh - 6rem);overflow:auto;background:var(--bg-card,#fff);color:var(--text-body,#1f2937);
    border:1px solid var(--border,#e5e7eb);border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.28);padding:1rem;
    font:400 .9rem/1.45 system-ui,sans-serif}
.ybs-panel[hidden]{display:none}
.ybs-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem}
.ybs-head h2{margin:0;font-size:1rem;color:var(--text-primary,#111827)}
.ybs-x{border:0;background:none;font-size:1.3rem;line-height:1;cursor:pointer;color:var(--text-faint,#6b7280);padding:.2rem .4rem}
.ybs-cats{display:flex;flex-wrap:wrap;gap:.35rem;margin:.25rem 0 .6rem;border:0;padding:0}
.ybs-cats label{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .6rem;border:1px solid var(--border-strong,#d1d5db);
    border-radius:999px;cursor:pointer;font-size:.8rem;color:var(--text-secondary,#374151)}
.ybs-cats input{margin:0}
.ybs-cats label:has(input:checked){border-color:var(--brand,#1f3b5b);background:var(--bg-subtle,#f9fafb);color:var(--text-primary,#111827)}
.ybs-panel textarea{width:100%;box-sizing:border-box;min-height:7rem;resize:vertical;padding:.55rem;border-radius:8px;
    border:1px solid var(--border-strong,#d1d5db);background:var(--bg-input,#fff);color:var(--text-body,#1f2937);font:inherit}
.ybs-note{margin:.45rem 0 .7rem;font-size:.78rem;color:var(--text-faint,#6b7280)}
.ybs-actions{display:flex;justify-content:space-between;align-items:center;gap:.5rem}
.ybs-actions a{font-size:.82rem}
.ybs-msg{margin-top:.6rem;font-size:.85rem}
.ybs-msg.is-err{color:#b91c1c}
.ybs-done{text-align:center;padding:.75rem .25rem}
.ybs-done strong{display:block;font-size:1rem;margin-bottom:.3rem;color:var(--text-primary,#111827)}
/* Keep bottom-pinned action bars (quote builder Save) clear of the button. */
.form-actions.sticky-save{padding-right:6.5rem!important}
@media print{.ybs-fab,.ybs-panel{display:none!important}}
</style>
<button type="button" class="ybs-fab" id="ybsFab" aria-haspopup="dialog" aria-controls="ybsPanel" aria-expanded="false">
    <span class="ybs-fab-q" aria-hidden="true">?</span> Help
</button>
<div class="ybs-panel" id="ybsPanel" role="dialog" aria-modal="false" aria-labelledby="ybsTitle" hidden>
    <div class="ybs-head">
        <h2 id="ybsTitle">Report a problem</h2>
        <button type="button" class="ybs-x" id="ybsClose" aria-label="Close">&times;</button>
    </div>
    <form id="ybsForm" novalidate>
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <fieldset class="ybs-cats">
            <legend class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">What is it?</legend>
            <?php $first = true; foreach (support_categories() as $ck => $cl): ?>
                <label><input type="radio" name="category" value="<?= e($ck) ?>"<?= $first ? ' checked' : '' ?>> <?= e($cl) ?></label>
            <?php $first = false; endforeach; ?>
        </fieldset>
        <label for="ybsMessage" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Describe it</label>
        <textarea id="ybsMessage" name="message" maxlength="5000" required
                  placeholder="What were you doing, and what happened? e.g. &ldquo;Clicked Save on the quote and nothing happened.&rdquo;"></textarea>
        <p class="ybs-note">We automatically attach the page you're on, your browser and any error details
            &mdash; never anything you've typed into forms.</p>
        <div class="ybs-actions">
            <a href="/help/index.php">Help &amp; guide &rarr;</a>
            <button type="submit" class="btn btn-primary" id="ybsSend">Send report</button>
        </div>
        <div class="ybs-msg" id="ybsMsg" role="status" aria-live="polite"></div>
    </form>
    <div class="ybs-done" id="ybsDone" hidden>
        <strong>Thanks &mdash; we've got it.</strong>
        <span id="ybsDoneText"></span>
        <p style="margin:.75rem 0 0"><button type="button" class="btn btn-secondary" id="ybsAnother">Report something else</button></p>
    </div>
</div>
<script>
(function () {
    // ── Context capture ─────────────────────────────────────────────────
    // Ring buffers kept in sessionStorage so the trail survives the page
    // load that usually follows the problem ("I clicked Save, it went to a
    // blank page"). Every storage touch is guarded — private windows throw.
    var KEY_ERR = 'ybs_err', KEY_BC = 'ybs_bc', MAX_ERR = 10, MAX_BC = 20;
    function load(k) { try { return JSON.parse(sessionStorage.getItem(k) || '[]') || []; } catch (e) { return []; } }
    function push(k, item, max) {
        var list = load(k); list.push(item);
        if (list.length > max) list = list.slice(-max);
        try { sessionStorage.setItem(k, JSON.stringify(list)); } catch (e) {}
    }
    function stamp() { return new Date().toTimeString().slice(0, 8); }
    function where() { return location.pathname + location.search; }
    function clip(s, n) { s = String(s == null ? '' : s).replace(/\s+/g, ' ').trim(); return s.length > n ? s.slice(0, n) + '…' : s; }

    function recordError(msg, src) {
        push(KEY_ERR, { t: stamp(), page: where(), msg: clip(msg, 500), src: clip(src || '', 200) }, MAX_ERR);
    }
    window.addEventListener('error', function (e) {
        if (e && e.target && e.target !== window && (e.target.src || e.target.href)) {
            recordError('Failed to load ' + (e.target.tagName || 'resource'), e.target.src || e.target.href);
        } else {
            recordError(e.message || 'Script error', (e.filename || '') + (e.lineno ? ':' + e.lineno : ''));
        }
    }, true);
    window.addEventListener('unhandledrejection', function (e) {
        var r = e && e.reason;
        recordError('Unhandled promise: ' + (r && r.message ? r.message : r), '');
    });
    if (window.console && console.error) {
        var origErr = console.error;
        console.error = function () {
            try { recordError('console.error: ' + Array.prototype.map.call(arguments, function (a) {
                return a && a.message ? a.message : (typeof a === 'object' ? JSON.stringify(a) : String(a));
            }).join(' '), ''); } catch (e) {}
            return origErr.apply(console, arguments);
        };
    }

    push(KEY_BC, { t: stamp(), act: 'open', what: clip(document.title, 80), page: where() }, MAX_BC);
    document.addEventListener('click', function (e) {
        var el = e.target && e.target.closest
            ? e.target.closest('a,button,[role=button],input[type=submit],input[type=button],summary,label') : null;
        if (!el || el.closest('#ybsPanel') || el.id === 'ybsFab') return;
        var label = el.getAttribute('aria-label') || el.innerText || el.value || el.title || el.getAttribute('href') || el.tagName;
        push(KEY_BC, { t: stamp(), act: 'click', what: clip(label, 80), page: where() }, MAX_BC);
    }, true);
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || f.id === 'ybsForm') return;
        push(KEY_BC, { t: stamp(), act: 'submit', what: clip(f.getAttribute('action') || where(), 120), page: where() }, MAX_BC);
    }, true);

    // ── Panel ───────────────────────────────────────────────────────────
    function ready(fn) { document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn(); }
    ready(function () {
        var fab = document.getElementById('ybsFab'), panel = document.getElementById('ybsPanel');
        var form = document.getElementById('ybsForm'), msg = document.getElementById('ybsMsg');
        var send = document.getElementById('ybsSend'), done = document.getElementById('ybsDone');
        var text = document.getElementById('ybsMessage');
        if (!fab || !panel || !form) return;

        function open() {
            panel.hidden = false; fab.setAttribute('aria-expanded', 'true');
            setTimeout(function () { (done.hidden ? text : document.getElementById('ybsAnother')).focus(); }, 0);
        }
        function close() { panel.hidden = true; fab.setAttribute('aria-expanded', 'false'); fab.focus(); }
        fab.addEventListener('click', function () { panel.hidden ? open() : close(); });
        document.getElementById('ybsClose').addEventListener('click', close);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) close(); });
        document.getElementById('ybsAnother').addEventListener('click', function () {
            done.hidden = true; form.hidden = false; text.value = ''; msg.textContent = ''; text.focus();
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!text.value.trim()) { msg.className = 'ybs-msg is-err'; msg.textContent = 'Please describe the problem first.'; text.focus(); return; }
            var fd = new FormData(form);
            fd.append('page_url', location.href);
            fd.append('page_title', document.title);
            fd.append('viewport', window.innerWidth + '×' + window.innerHeight);
            fd.append('js_errors', JSON.stringify(load(KEY_ERR)));
            fd.append('breadcrumbs', JSON.stringify(load(KEY_BC)));
            send.disabled = true; msg.className = 'ybs-msg'; msg.textContent = 'Sending…';
            fetch('/support/report.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
                .then(function (res) {
                    if (res && res.ok) {
                        try { sessionStorage.removeItem(KEY_ERR); } catch (e2) {}
                        form.hidden = true; done.hidden = false; msg.textContent = '';
                        document.getElementById('ybsDoneText').textContent =
                            'Report #' + res.id + ' is with the YourBlinds team. We\'ll be in touch by email.';
                        document.getElementById('ybsAnother').focus();
                    } else {
                        msg.className = 'ybs-msg is-err';
                        msg.textContent = (res && res.error) || 'Sorry — that didn\'t send. Please try again.';
                    }
                })
                .catch(function () {
                    msg.className = 'ybs-msg is-err';
                    msg.textContent = 'Couldn\'t reach the server — check your connection and try again.';
                })
                .then(function () { send.disabled = false; });
        });
    });
})();
</script>
