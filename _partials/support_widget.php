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
.ybs-log{display:flex;flex-direction:column;gap:.5rem;max-height:min(52vh,420px);overflow-y:auto;margin:0 0 .6rem;padding:.1rem}
.ybs-bub{padding:.5rem .7rem;border-radius:10px;max-width:88%;word-wrap:break-word;font-size:.88rem}
.ybs-bub p{margin:0 0 .4rem}.ybs-bub p:last-child{margin:0}.ybs-bub ul{margin:.2rem 0 .4rem;padding-left:1.1rem}
.ybs-bub.is-user{align-self:flex-end;background:var(--brand,#1f3b5b);color:var(--text-on-brand,#fff);white-space:pre-wrap}
.ybs-bub.is-bot{align-self:flex-start;background:var(--bg-subtle,#f9fafb);border:1px solid var(--border,#e5e7eb)}
.ybs-bub.is-note{align-self:center;background:none;color:var(--text-faint,#6b7280);font-size:.8rem;text-align:center}
.ybs-bub.is-wait{color:var(--text-faint,#6b7280);font-style:italic}
.ybs-chatform{display:flex;gap:.4rem;align-items:flex-end}
.ybs-chatform textarea{min-height:2.6rem;flex:1}
.ybs-mic{flex:none;width:2.6rem;height:2.6rem;border-radius:8px;border:1px solid var(--border-strong,#d1d5db);
    background:var(--bg-input,#fff);cursor:pointer;font-size:1.1rem;line-height:1}
.ybs-mic[aria-pressed="true"]{background:#b91c1c;border-color:#b91c1c;animation:ybsPulse 1.2s ease-in-out infinite}
@keyframes ybsPulse{50%{box-shadow:0 0 0 5px rgba(185,28,28,.25)}}
@media (prefers-reduced-motion: reduce){.ybs-mic[aria-pressed="true"]{animation:none}}
#ybsSpeak{font-size:1rem}
.ybs-chatfoot{margin-top:.5rem;font-size:.78rem;color:var(--text-faint,#6b7280)}
.ybs-link{border:0;background:none;padding:0;color:var(--link,#2563eb);cursor:pointer;font:inherit;text-decoration:underline}
.ybs-notice{margin:0 0 .6rem;padding:.5rem .6rem;border-radius:8px;background:var(--bg-subtle,#f9fafb);font-size:.82rem;color:var(--text-secondary,#374151)}
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
        <span>
            <button type="button" class="ybs-x" id="ybsSpeak" aria-label="Read replies aloud" aria-pressed="false" title="Read replies aloud" hidden>🔇</button>
            <button type="button" class="ybs-x" id="ybsClose" aria-label="Close">&times;</button>
        </span>
    </div>
    <div id="ybsChat" hidden>
        <div class="ybs-log" id="ybsLog" aria-live="polite"></div>
        <form id="ybsChatForm" class="ybs-chatform" novalidate>
            <label for="ybsChatInput" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Your message</label>
            <textarea id="ybsChatInput" rows="2" maxlength="4000" placeholder="Ask a question or describe a problem…"></textarea>
            <button type="button" class="ybs-mic" id="ybsMic" aria-label="Speak your message" aria-pressed="false" title="Speak instead of typing" hidden>🎤</button>
            <button type="submit" class="btn btn-primary" id="ybsChatSend">Send</button>
        </form>
        <div class="ybs-chatfoot">
            <button type="button" class="ybs-link" id="ybsNew">New chat</button>
            &middot; <button type="button" class="ybs-link" id="ybsToForm">Send to the team instead</button>
            &middot; <a href="/help/index.php">Help &amp; guide</a>
        </div>
    </div>
    <form id="ybsForm" novalidate>
        <p class="ybs-notice" id="ybsNotice" hidden></p>
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
        if (!f || (f.closest && f.closest('#ybsPanel'))) return;
        push(KEY_BC, { t: stamp(), act: 'submit', what: clip(f.getAttribute('action') || where(), 120), page: where() }, MAX_BC);
    }, true);


    // ── Panel ───────────────────────────────────────────────────────────
    // Two modes: the AI chat (when the assistant is on for this user) and the
    // plain report form (always available — the fallback for everything).
    function ready(fn) { document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn(); }
    ready(function () {
        var fab = document.getElementById('ybsFab'), panel = document.getElementById('ybsPanel');
        var form = document.getElementById('ybsForm'), msg = document.getElementById('ybsMsg');
        var send = document.getElementById('ybsSend'), done = document.getElementById('ybsDone');
        var text = document.getElementById('ybsMessage'), title = document.getElementById('ybsTitle');
        var chat = document.getElementById('ybsChat'), log = document.getElementById('ybsLog');
        var chatForm = document.getElementById('ybsChatForm'), chatInput = document.getElementById('ybsChatInput');
        var chatSend = document.getElementById('ybsChatSend'), notice = document.getElementById('ybsNotice');
        if (!fab || !panel || !form) return;
        var csrf = form.querySelector('input[name=_csrf]').value;

        function addContext(fd) {
            fd.append('page_url', location.href);
            fd.append('page_title', document.title);
            fd.append('viewport', window.innerWidth + '×' + window.innerHeight);
            fd.append('js_errors', JSON.stringify(load(KEY_ERR)));
            fd.append('breadcrumbs', JSON.stringify(load(KEY_BC)));
            return fd;
        }
        function post(url, fd) {
            fd.append('_csrf', csrf);
            return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json().catch(function () { return { ok: false }; }); });
        }
        function fields(obj) { var fd = new FormData(); for (var k in obj) fd.append(k, obj[k]); return fd; }

        // ── Mode switching ──
        var status = null;   // fetched once per page load
        function showForm(note) {
            chat.hidden = true; form.hidden = false; done.hidden = true;
            document.getElementById('ybsSpeak').hidden = true;
            if (window.speechSynthesis) window.speechSynthesis.cancel();
            title.textContent = 'Report a problem';
            notice.hidden = !note; notice.textContent = note || '';
            setTimeout(function () { text.focus(); }, 0);
        }
        function showChat() {
            chat.hidden = false; form.hidden = true; done.hidden = true;
            title.textContent = 'Help';
            document.getElementById('ybsSpeak').hidden = !window.speechSynthesis;
            setTimeout(function () { chatInput.focus(); log.scrollTop = log.scrollHeight; }, 0);
        }

        // ── Chat rendering: escape first, then a tiny safe markdown ──
        function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
        function md(s) {
            var html = '', inList = false;
            esc(s).split(/\n/).forEach(function (line) {
                line = line.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                var li = line.match(/^\s*(?:[-*•]|\d+\.)\s+(.*)$/);
                if (li) { if (!inList) { html += '<ul>'; inList = true; } html += '<li>' + li[1] + '</li>'; return; }
                if (inList) { html += '</ul>'; inList = false; }
                if (line.trim() !== '') html += '<p>' + line + '</p>';
            });
            return html + (inList ? '</ul>' : '');
        }
        function bubble(role, content) {
            var d = document.createElement('div');
            d.className = 'ybs-bub is-' + role;
            if (role === 'bot') d.innerHTML = md(content); else d.textContent = content;
            log.appendChild(d); log.scrollTop = log.scrollHeight;
            return d;
        }
        // ── Voice ──
        // Speak-to-type via the browser's own speech recognition (Chrome, Edge,
        // Safari; the mic hides where it's missing, e.g. Firefox), and optional
        // read-aloud of replies in the Help guide's voice ("Google UK English
        // Female", else the best British voice). Both free, both in-browser.
        var mic = document.getElementById('ybsMic'), speakBtn = document.getElementById('ybsSpeak');
        var Rec = window.SpeechRecognition || window.webkitSpeechRecognition, rec = null, listening = false;
        if (Rec) {
            mic.hidden = false;
            mic.addEventListener('click', function () {
                if (listening) { rec && rec.stop(); return; }
                stopSpeaking();
                var base = chatInput.value.replace(/\s+$/, '');
                rec = new Rec();
                rec.lang = 'en-GB'; rec.interimResults = true; rec.continuous = false;
                rec.onresult = function (e) {
                    var said = '';
                    for (var i = 0; i < e.results.length; i++) said += e.results[i][0].transcript;
                    chatInput.value = (base ? base + ' ' : '') + said;
                };
                rec.onerror = function (e) {
                    if (e.error === 'not-allowed' || e.error === 'service-not-allowed') {
                        bubble('note', 'Microphone blocked — allow it in your browser\'s address bar to speak your message.');
                    }
                };
                rec.onend = function () { listening = false; mic.setAttribute('aria-pressed', 'false'); chatInput.focus(); };
                try { rec.start(); listening = true; mic.setAttribute('aria-pressed', 'true'); } catch (err) { listening = false; }
            });
        }
        function stopListening() { if (listening && rec) rec.stop(); }

        var synth = window.speechSynthesis, voice = null, speakOn = false;
        try { speakOn = localStorage.getItem('ybs_speak') === '1'; } catch (e) {}
        function pickVoice() {
            var vs = (synth && synth.getVoices()) || [];
            function score(v) { var s = 0; if (/google uk english female/i.test(v.name)) s += 10; if (/google uk english/i.test(v.name)) s += 4; if (/en[-_]GB/i.test(v.lang)) s += 2; if (/natural|online|neural|premium|enhanced/i.test(v.name)) s += 3; if (/^en/i.test(v.lang)) s += 1; return s; }
            voice = vs.slice().sort(function (a, b) { return score(b) - score(a); })[0] || null;
        }
        function paintSpeak() {
            speakBtn.textContent = speakOn ? '🔊' : '🔇';
            speakBtn.setAttribute('aria-pressed', speakOn ? 'true' : 'false');
            speakBtn.title = speakOn ? 'Reading replies aloud — click to stop' : 'Read replies aloud';
        }
        function stopSpeaking() { if (synth) synth.cancel(); }
        function speak(textToSay) {
            if (!synth || !speakOn) return;
            stopSpeaking();
            if (!voice) pickVoice();
            var plain = String(textToSay).replace(/\*\*/g, '').replace(/^\s*(?:[-*•]|\d+\.)\s+/gm, '').replace(/→/g, ', then ');
            // Short pieces: Chrome drops long utterances part-way (see help/guide.php).
            (plain.match(/[^.!?\n]+[.!?]*\s*/g) || [plain]).forEach(function (part) {
                if (!part.trim()) return;
                var u = new SpeechSynthesisUtterance(part.trim());
                if (voice) { u.voice = voice; u.lang = voice.lang; } else { u.lang = 'en-GB'; }
                synth.speak(u);
            });
        }
        if (synth) {
            pickVoice();
            if (synth.addEventListener) synth.addEventListener('voiceschanged', pickVoice);
            paintSpeak();
            speakBtn.addEventListener('click', function () {
                speakOn = !speakOn;
                try { localStorage.setItem('ybs_speak', speakOn ? '1' : '0'); } catch (e) {}
                paintSpeak();
                if (!speakOn) stopSpeaking();
            });
        }

        function greet() {
            bubble('bot', 'Hi! I\'m the YourBlinds assistant. Ask me how to do something, or tell me what\'s gone wrong and I\'ll get it to the team.');
        }

        function open() {
            panel.hidden = false; fab.setAttribute('aria-expanded', 'true');
            if (status) { status.available ? showChat() : showForm(status.notice); return; }
            showForm();
            post('/support/chat.php', fields({ action: 'status' })).then(function (res) {
                status = res && res.ok ? res : { available: false };
                if (!status.available) { showForm(status.notice); return; }
                log.innerHTML = '';
                if (status.history && status.history.length) {
                    status.history.forEach(function (m) { bubble(m.role === 'user' ? 'user' : 'bot', m.text); });
                } else { greet(); }
                showChat();
            }).catch(function () { status = { available: false }; });
        }
        function close() {
            stopListening(); stopSpeaking();
            panel.hidden = true; fab.setAttribute('aria-expanded', 'false'); fab.focus();
        }
        fab.addEventListener('click', function () { panel.hidden ? open() : close(); });
        document.getElementById('ybsClose').addEventListener('click', close);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) close(); });
        document.getElementById('ybsAnother').addEventListener('click', function () {
            text.value = ''; msg.textContent = '';
            status && status.available ? showChat() : showForm();
        });
        document.getElementById('ybsToForm').addEventListener('click', function () { showForm(); });
        document.getElementById('ybsNew').addEventListener('click', function () {
            post('/support/chat.php', fields({ action: 'reset' })).then(function () { log.innerHTML = ''; greet(); chatInput.focus(); });
        });

        // ── Chat send ──
        chatInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); chatSend.click(); }
        });
        chatForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var t = chatInput.value.trim();
            if (!t || chatSend.disabled) return;
            stopListening(); stopSpeaking();
            bubble('user', t);
            chatInput.value = ''; chatSend.disabled = true;
            var wait = bubble('bot', 'Thinking…'); wait.classList.add('is-wait');
            var fd = addContext(fields({ action: 'send', message: t }));
            post('/support/chat.php', fd).then(function (res) {
                wait.remove();
                if (res && res.ok) {
                    bubble('bot', res.reply || '…');
                    speak(res.reply || '');
                    if (res.tickets && res.tickets.length) { try { sessionStorage.removeItem(KEY_ERR); } catch (e2) {} }
                } else if (res && res.fallback) {
                    status = { available: false };
                    text.value = t;
                    showForm(res.error);
                } else {
                    bubble('note', (res && res.error) || 'Sorry — that didn\'t send. Please try again.');
                }
            }).catch(function () {
                wait.remove();
                bubble('note', 'Couldn\'t reach the server — check your connection and try again.');
            }).then(function () { chatSend.disabled = false; chatInput.focus(); });
        });

        // ── Plain report form ──
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!text.value.trim()) { msg.className = 'ybs-msg is-err'; msg.textContent = 'Please describe the problem first.'; text.focus(); return; }
            var fd = addContext(new FormData(form));
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
