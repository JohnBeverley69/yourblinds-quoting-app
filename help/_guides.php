<?php
declare(strict_types=1);

/**
 * Guided-walkthrough registry — shared by help/guide.php (renders one) and
 * help/index.php (lists them). Returns the $GUIDES array, keyed by slug (?g=slug).
 *
 * ONE FILE PER GUIDE, in help/guides/<slug>.php, each returning that guide's
 * array. They used to all live in this file; at 25 guides and ~3,300 lines that
 * made every edit a merge conflict and every change unreviewable in a diff.
 *
 * Each guide's fields:
 *   aud     all | admin | super  (who may open it)
 *   section the Help & guide section it belongs under
 *   title   page + link title
 *   eyebrow small label above the title — "Area · Tab"; help/index.php parses
 *           this to build its collapsible groups, so keep the convention
 *   blurb   one-line summary for the index card
 *   lede    one-paragraph intro (HTML allowed)
 *   open    href to the real screen this guide covers ("Open the screen →")
 *   css     the guide's own scenario styles, emitted in a scoped <style>
 *   demo    HTML for the animated walkthrough (uses the .gd demo styles)
 *   body    HTML for the written steps
 *   script  array of [beat, on-screen, voiceover, stepIndex] — feeds the table
 *           AND the narration (the voiceover column is read aloud, in order);
 *           stepIndex drives the demo's data-step
 *   js      optional, scoped <script> for an interactive guide (write it as a
 *           nowdoc <<<'JS' … JS, so nothing needs escaping)
 *
 * ORDER BELOW IS THE ORDER SHOWN. It follows the running order chosen for the
 * app: foundations first (Settings → Users → Products), then the workflow a
 * real job takes (Customers → Quotes → Calendar). A guide file that is not
 * listed still loads — it just lands at the end, so adding one can never make
 * it silently disappear.
 */

$dir = __DIR__ . '/guides';

$ORDER = [
    // The screen everyone lands on.
    'dashboard-tour',
    // Settings — done once, before anything else works.
    'settings-company',
    'settings-logo',
    'settings-dashboard',
    'settings-calendar',
    'settings-margins',
    'settings-measurements',
    'settings-quote-defaults',
    'settings-bank',
    'settings-legal',
    'settings-colours',
    'settings-suppliers',
    'trade-terms-page',
    'settings-backup',
    // Who can get in.
    'users-add',
    // What you sell.
    'products-wizard',
    'products-pricing-modes',
    'products-import-price-tables',
    'products-import-fabrics',
    'products-options',
    'products-combine',
    // The job itself.
    'customers-add',
    'instaprice-quick',
    'quote-build',
    'quote-send-accept',
    'quote-order-invoice',
    'orders-list',
    'orders-pipeline',
    'calendar-booking',
    'quote-payments',
    'accounts-money',
];

$GUIDES = [];
foreach ($ORDER as $slug) {
    $f = $dir . '/' . $slug . '.php';
    if (is_file($f)) $GUIDES[$slug] = require $f;
}

// Anything present but not listed above — so a new guide file works the moment
// it is dropped in, rather than vanishing until someone remembers this list.
foreach (glob($dir . '/*.php') ?: [] as $f) {
    $slug = basename($f, '.php');
    if (!isset($GUIDES[$slug])) $GUIDES[$slug] = require $f;
}

return $GUIDES;
