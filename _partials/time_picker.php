<?php
/**
 * Custom time-picker combobox — used on calendar/new.php and
 * calendar/edit.php.
 *
 * Why not native:
 *   - <input type="time"> renders inconsistently across browsers
 *     (some are non-typable wheel-pickers).
 *   - <input type="text" list="..."> + <datalist> hides options
 *     that don't match the current value, so a pre-filled "09:00"
 *     made the dropdown only ever show "09:00" — defeating the
 *     point of having quick-pick slots.
 *
 * This partial emits a tiny custom combobox: text input you can
 * type into AND a clickable dropdown that always shows the full
 * list of half-hour slots (08:00 → 18:00). Filters as you type.
 *
 * Required vars in the calling scope:
 *   $value (string)  the current time value (HH:MM or empty)
 *   $name  (string)  optional, defaults to "appointment_time"
 *   $id    (string)  optional, defaults to "appointment_time"
 *
 * Idempotent CSS / JS — only emits once per page render even if
 * the partial is required twice. Page must include /app.css for
 * the surrounding form styles.
 */
$tpName  = $name  ?? 'appointment_time';
$tpId    = $id    ?? 'appointment_time';
$tpValue = (string) ($value ?? '');

// Half-hour slots covering a typical fitting window.
$tpSlots = [
    '08:00','08:30','09:00','09:30','10:00','10:30',
    '11:00','11:30','12:00','12:30','13:00','13:30',
    '14:00','14:30','15:00','15:30','16:00','16:30',
    '17:00','17:30','18:00',
];

$tpFirst = !defined('TP_PARTIAL_INCLUDED');
if ($tpFirst) define('TP_PARTIAL_INCLUDED', true);
?>
<?php if ($tpFirst): ?>
<style>
    .tp-wrap { position: relative; }
    .tp-options {
        position: absolute; top: 100%; left: 0; right: 0;
        margin-top: 4px;
        background: var(--bg-card);
        border: 1px solid var(--border-strong); border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        z-index: 30;
        max-height: 240px; overflow-y: auto;
        display: none;
    }
    .tp-wrap.is-open .tp-options { display: block; }
    .tp-opt {
        padding: 0.4375rem 0.75rem; cursor: pointer;
        font-size: 0.9375rem; color: var(--text-primary);
        font-variant-numeric: tabular-nums;
    }
    .tp-opt:hover, .tp-opt.is-active { background: var(--bg-subtle-2); }
    .tp-opt.is-hidden { display: none; }
    .tp-empty {
        padding: 0.5rem 0.75rem; color: var(--text-faint);
        font-size: 0.8125rem; font-style: italic;
    }
</style>
<script>
(function () {
    'use strict';
    // One delegated listener handles every .tp-wrap on the page —
    // adds-handlers-on-input / blur / outside-click pattern that
    // works for any number of pickers.

    function init(wrap) {
        if (wrap._tpReady) return;
        wrap._tpReady = true;
        var input    = wrap.querySelector('input.tp-input');
        var optsBox  = wrap.querySelector('.tp-options');
        var opts     = optsBox.querySelectorAll('.tp-opt');

        function open() {
            wrap.classList.add('is-open');
            // Reset filter (show all) when opening with the existing
            // value present — that's the whole point of this widget.
            filter('');
        }
        function close() {
            wrap.classList.remove('is-open');
            opts.forEach(function (o) { o.classList.remove('is-active'); });
        }
        function filter(q) {
            var any = false;
            q = q.trim().toLowerCase();
            opts.forEach(function (o) {
                var match = q === '' || o.textContent.toLowerCase().indexOf(q) !== -1;
                o.classList.toggle('is-hidden', !match);
                if (match) any = true;
            });
            var emptyMsg = optsBox.querySelector('.tp-empty');
            if (!any) {
                if (!emptyMsg) {
                    emptyMsg = document.createElement('div');
                    emptyMsg.className = 'tp-empty';
                    emptyMsg.textContent = 'No common slot — type any HH:MM you like.';
                    optsBox.appendChild(emptyMsg);
                }
                emptyMsg.style.display = '';
            } else if (emptyMsg) {
                emptyMsg.style.display = 'none';
            }
        }

        input.addEventListener('focus', open);
        input.addEventListener('input', function () { filter(input.value); });
        input.addEventListener('blur',  function () {
            // Slight delay so a click on a tp-opt registers before close.
            setTimeout(close, 150);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { close(); input.blur(); }
        });

        opts.forEach(function (opt) {
            // mousedown not click — fires before the input's blur,
            // so we don't need to fight the close timer.
            opt.addEventListener('mousedown', function (e) {
                e.preventDefault();
                input.value = opt.dataset.value;
                close();
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.tp-wrap').forEach(init);
    });
    // If the partial is included after DOMContentLoaded for some
    // reason, init runs immediately too.
    if (document.readyState !== 'loading') {
        document.querySelectorAll('.tp-wrap').forEach(init);
    }
})();
</script>
<?php endif; ?>
<div class="tp-wrap">
    <input class="tp-input" id="<?= e($tpId) ?>" name="<?= e($tpName) ?>"
           type="text" required
           pattern="[0-9]{1,2}:[0-9]{2}"
           placeholder="HH:MM (e.g. 09:30)"
           autocomplete="off"
           value="<?= e($tpValue) ?>">
    <div class="tp-options" role="listbox">
        <?php foreach ($tpSlots as $slot): ?>
            <div class="tp-opt" data-value="<?= e($slot) ?>" role="option"><?= e($slot) ?></div>
        <?php endforeach; ?>
    </div>
</div>
