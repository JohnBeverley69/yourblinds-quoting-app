<?php
declare(strict_types=1);

/*
 * "Customer view" for cost figures. Quotes are often built on a tablet or
 * phone in the customer's home, with the customer looking at the screen —
 * so the buying price, markup, discounts and internal WT are hidden by
 * default on every page load, for everyone (users without View costs never
 * get them at all). A small unlabelled eye icon reveals them; the next tap
 * hides them again. Deliberately NOT remembered: each page starts hidden.
 *
 * Usage: mark cost-only markup with class="cost-only", call
 * cost_reveal_assets() once on the page, and drop cost_reveal_button()
 * where the toggle should sit (JS-built panels can use window.YB_COST_EYE).
 * Toggling fires a 'yb-costs-toggle' event on document for anything that
 * needs to re-render.
 */

if (!function_exists('cost_reveal_button')) {
    function cost_reveal_button(): string
    {
        // No title/tooltip text: a hover label saying "Show costs" would give
        // the game away to the customer.
        return '<button type="button" class="yb-cost-eye" aria-label="Toggle details" aria-pressed="false">'
             . '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" fill="none" stroke="currentColor"'
             . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
             . '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>'
             . '</button>';
    }

    function cost_reveal_assets(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        ?>
<style>
html:not(.yb-costs-shown) .cost-only { display: none !important; }
.yb-cost-eye { background: none; border: 0; padding: 2px 4px; margin: 0 0 0 4px; cursor: pointer;
               color: inherit; opacity: .3; line-height: 1; vertical-align: middle; }
.yb-cost-eye:hover, .yb-cost-eye:focus-visible { opacity: .75; }
html.yb-costs-shown .yb-cost-eye { opacity: .7; }
</style>
<script>
(function () {
    'use strict';
    window.YB_COST_EYE = <?= json_encode(cost_reveal_button()) ?>;
    window.ybCostsShown = function () {
        return document.documentElement.classList.contains('yb-costs-shown');
    };
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest && ev.target.closest('.yb-cost-eye');
        if (!btn) return;
        ev.preventDefault();
        var on = document.documentElement.classList.toggle('yb-costs-shown');
        document.querySelectorAll('.yb-cost-eye').forEach(function (b) {
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        document.dispatchEvent(new Event('yb-costs-toggle'));
    });
})();
</script>
        <?php
    }
}
