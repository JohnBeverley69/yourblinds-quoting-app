<?php
/**
 * Reusable confirmation modal — replacement for native confirm().
 *
 * Why: native confirm() can be suppressed by browsers ("Prevent this
 * page from creating additional dialogs" in Firefox). After that
 * checkbox is ticked, every confirm() returns false silently and any
 * onsubmit="return confirm(...)" form silently refuses to submit.
 *
 * Usage in a page:
 *
 *   1. Include this partial once per page that has confirms:
 *        require __DIR__ . '/.../_partials/confirm_modal.php';
 *
 *   2. Tag any form that needs a confirmation with data-confirm:
 *        <form ... data-confirm="Delete this thing? Cannot be undone.">
 *        — drop the old onsubmit="return confirm(...)" attribute.
 *
 *   3. (Optional) Tag any link / button that needs a confirmation
 *      with data-confirm-click:
 *        <a href="..." data-confirm-click="Are you sure?">...</a>
 *
 * The JS intercepts the submit / click, opens this modal, and only
 * proceeds when the user clicks the red confirm button. Escape and
 * a click on the backdrop both cancel.
 *
 * Self-contained: idempotent require — the markup only emits once
 * even if a page accidentally requires the partial twice.
 */
if (defined('CFM_MODAL_INCLUDED')) return;
define('CFM_MODAL_INCLUDED', true);
?>
<style>
    .cfm-overlay {
        position: fixed; inset: 0;
        background: rgba(17, 24, 39, 0.55);
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .cfm-overlay.is-open { display: flex; }
    .cfm-box {
        /* Theme-aware: the message text uses var(--text-primary), which
           is near-white in dark mode — on a hardcoded white box that left
           the text invisible (Tyler: remove-logo + delete-payment dialogs
           unreadable). Match the box to the card background like the other
           modals so it reads in both themes. */
        background: var(--bg-card);
        max-width: 480px; width: 100%;
        padding: 1.25rem 1.25rem 1rem;
        border-radius: 12px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    }
    .cfm-msg {
        font-size: 1rem; color: var(--text-primary); line-height: 1.45;
        white-space: pre-line;
        margin: 0 0 1.125rem;
    }
    .cfm-actions {
        display: flex; gap: 0.5rem; justify-content: flex-end;
        flex-wrap: wrap;
    }
    .cfm-btn {
        padding: 0.5625rem 1rem;
        border: 0; border-radius: 8px;
        font: inherit; font-weight: 600;
        cursor: pointer;
    }
    .cfm-btn-cancel  { background: var(--border); color: var(--text-secondary); }
    .cfm-btn-cancel:hover { background: var(--border-strong); }
    .cfm-btn-confirm { background: #dc2626; color: #fff; }
    .cfm-btn-confirm:hover { background: #b91c1c; }
</style>
<div class="cfm-overlay" id="cfmOverlay" role="dialog" aria-modal="true" aria-labelledby="cfmMsg">
    <div class="cfm-box">
        <p class="cfm-msg" id="cfmMsg"></p>
        <div class="cfm-actions">
            <button type="button" class="cfm-btn cfm-btn-cancel"  id="cfmCancel">Cancel</button>
            <button type="button" class="cfm-btn cfm-btn-confirm" id="cfmOk">Yes, continue</button>
        </div>
    </div>
</div>
<script>
(function () {
    'use strict';
    var overlay = document.getElementById('cfmOverlay');
    var msgEl   = document.getElementById('cfmMsg');
    var okBtn   = document.getElementById('cfmOk');
    var cancel  = document.getElementById('cfmCancel');
    var pending = null;  // callback to run on confirm

    function open(text, onConfirm) {
        msgEl.textContent = text || 'Are you sure?';
        overlay.classList.add('is-open');
        pending = onConfirm;
        // Defer focus so the click that opened us doesn't immediately
        // re-trigger Enter-to-confirm in some browsers.
        setTimeout(function () { okBtn.focus(); }, 30);
    }
    function close() {
        overlay.classList.remove('is-open');
        pending = null;
    }

    cancel.addEventListener('click', close);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });
    document.addEventListener('keydown', function (e) {
        if (!overlay.classList.contains('is-open')) return;
        if (e.key === 'Escape') { close(); }
        else if (e.key === 'Enter') { okBtn.click(); }
    });
    okBtn.addEventListener('click', function () {
        var fn = pending;
        close();
        if (fn) fn();
    });

    // Intercept submits on any form tagged with data-confirm.
    // Capture phase so we run before any other submit handlers.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.dataset || !form.dataset.confirm) return;
        if (form.dataset.confirmed === '1') return;  // already confirmed → let it through
        e.preventDefault();
        e.stopImmediatePropagation();
        open(form.dataset.confirm, function () {
            form.dataset.confirmed = '1';
            // form.submit() doesn't fire submit events again, so the
            // "let it through" guard above isn't strictly needed — but
            // we set it anyway in case future code uses requestSubmit().
            form.submit();
        });
    }, true);

    // Intercept clicks on links / buttons tagged with data-confirm-click.
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-confirm-click]');
        if (!el) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        var text = el.getAttribute('data-confirm-click');
        open(text, function () {
            // Removed so the next click goes through unimpeded.
            el.removeAttribute('data-confirm-click');
            // Native re-dispatch — submits a button's parent form, follows
            // a link's href, etc.
            el.click();
        });
    }, true);
})();
</script>
<?php
