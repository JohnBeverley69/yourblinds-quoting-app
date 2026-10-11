<?php
/**
 * Settings → Accounting: "Get your sales into your accounts package".
 * Included by admin/settings.php at the top of the Accounting tab. Download
 * buttons for QuickBooks / Xero / Sage (accounts/export.php) with short import
 * steps for each — the route every company has today. The live QuickBooks
 * link sits below it (for now shown to super-admins only).
 *
 * Package steps checked against the vendors' help, Sep–Oct 2026; the full
 * walkthrough is the help guide g=accounts-to-package.
 */
require_once __DIR__ . '/../accounts/_helpers.php';

$expOn   = acct_feature_enabled((int) $clientId);
$expFrom = date('Y-m-01', strtotime('first day of last month'));
$expTo   = date('Y-m-t', strtotime('last day of last month'));
$step    = 'margin:0 0 .35rem;padding-left:1.25rem;color:var(--text-secondary);font-size:.875rem;line-height:1.5';
$card    = 'border:1px solid var(--border-strong);border-radius:10px;padding:.875rem 1.125rem;margin-bottom:.75rem';
?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Get your sales into your accounts package</h2>
    </div>
    <p class="ui-hint" style="color:var(--text-secondary);margin:0 0 1rem;max-width:44rem">
        Download your sales for a period and import them into QuickBooks, Xero or Sage &mdash;
        no typing them in again. Pick the dates, then the button for your package.
        <a href="/help/guide.php?g=accounts-to-package">Watch the step-by-step guide</a>.
    </p>

    <?php if (!$expOn): ?>
        <div class="alert alert-info" role="status" style="max-width:44rem">
            The export files come with the <strong>Accounts</strong> add-on (Gold plan), which also tracks
            payments and balances. <a href="/billing/index.php">See plans</a>
        </div>
    <?php else: ?>
    <form method="get" action="/accounts/export.php" style="max-width:44rem">
        <div class="form-row" style="align-items:flex-end;gap:.75rem;flex-wrap:wrap">
            <div class="form-group">
                <label for="exp_from">From</label>
                <input type="date" id="exp_from" name="from" value="<?= e($expFrom) ?>">
            </div>
            <div class="form-group">
                <label for="exp_to">To</label>
                <input type="date" id="exp_to" name="to" value="<?= e($expTo) ?>">
            </div>
            <div class="form-group" style="display:flex;gap:.4rem;flex-wrap:wrap">
                <button type="button" class="btn btn-secondary btn-sm" data-exp-range="last">Last month</button>
                <button type="button" class="btn btn-secondary btn-sm" data-exp-range="this">This month</button>
            </div>
        </div>
        <p class="ui-hint" style="color:var(--text-faint);font-size:.8125rem;margin:.25rem 0 1rem">
            Orders are included by the date they were accepted. Prices go across before VAT; the package adds the VAT.
        </p>

        <div style="<?= $card ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
                <strong style="font-size:1.0625rem">QuickBooks Online</strong>
                <button type="submit" name="type" value="quickbooks" class="btn btn-primary">Download for QuickBooks</button>
            </div>
            <details style="margin-top:.5rem">
                <summary style="cursor:pointer;color:var(--text-secondary);font-size:.875rem">How to import it</summary>
                <ol style="<?= $step ?>;margin-top:.5rem">
                    <li>In QuickBooks, click the <strong>&#9881; gear</strong> &rarr; <strong>Import data</strong> &rarr; <strong>Invoices</strong>.</li>
                    <li>Choose the file you downloaded. If it came as a <strong>.zip</strong> (over 100 invoices), unzip it and import the parts one after another.</li>
                    <li>The columns match up on their own. The first time, tick <strong>add new products/services</strong> so QuickBooks creates the item &ldquo;Blinds&rdquo;.</li>
                    <li>Check the customers it found, then <strong>Import</strong>.</li>
                </ol>
                <p style="<?= $step ?>;padding-left:0">VAT comes across as QuickBooks' own codes (20.0% S, 5.0% R or No VAT). Any agreed-price discount is already folded into the lines.</p>
            </details>
        </div>

        <div style="<?= $card ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
                <strong style="font-size:1.0625rem">Xero</strong>
                <button type="submit" name="type" value="invoices" class="btn btn-primary">Download for Xero</button>
            </div>
            <details style="margin-top:.5rem">
                <summary style="cursor:pointer;color:var(--text-secondary);font-size:.875rem">How to import it</summary>
                <ol style="<?= $step ?>;margin-top:.5rem">
                    <li>In Xero, go to <strong>Business</strong> &rarr; <strong>Invoices</strong> &rarr; <strong>Import</strong>.</li>
                    <li>Choose the file and pick <strong>Tax exclusive</strong> (prices are before VAT).</li>
                    <li>The invoices arrive as <strong>Drafts</strong> &mdash; check them and <strong>Approve</strong>.</li>
                </ol>
                <p style="<?= $step ?>;padding-left:0">Sales go to account <strong>200 (Sales)</strong> at 20% VAT; Xero lets you change these on import. An invoice number Xero already has is skipped, so importing a month twice does no harm.</p>
            </details>
        </div>

        <div style="<?= $card ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
                <strong style="font-size:1.0625rem">Sage Accounting</strong>
                <button type="submit" name="type" value="sage" class="btn btn-primary">Download for Sage</button>
            </div>
            <details style="margin-top:.5rem">
                <summary style="cursor:pointer;color:var(--text-secondary);font-size:.875rem">How to import it</summary>
                <p style="<?= $step ?>;padding-left:0;margin-top:.5rem">You get a .zip with two files. Do them in this order:</p>
                <ol style="<?= $step ?>">
                    <li><strong>Customers first:</strong> Contacts &rarr; Import &rarr; Customers, and choose <strong>1-customers.csv</strong>. (Sage won't take an invoice for a customer it doesn't know.)</li>
                    <li><strong>Then the invoices:</strong> Sales &rarr; Quick entries &rarr; the arrow beside <strong>New Quick Entry</strong> &rarr; <strong>Import Quick Entries</strong>, and choose <strong>2-invoices.csv</strong>.</li>
                </ol>
                <p style="<?= $step ?>;padding-left:0">Each order comes in as one line to sales ledger 4000. Sage changes its import columns now and then &mdash; if it says one doesn't match, download its own template from the same Import screen and copy the columns across.</p>
            </details>
        </div>

        <p class="ui-hint" style="color:var(--text-faint);font-size:.8125rem;margin:.5rem 0 0;max-width:44rem">
            Payments don't import into any of these &mdash; your package matches them from its bank feed.
            For your own records you can still
            <button type="submit" name="type" value="payments" class="btn-link" style="background:none;border:0;padding:0;color:var(--link);cursor:pointer;font:inherit;text-decoration:underline">download the payments list</button>.
        </p>
    </form>
    <script>
    (function () {
        var f = document.getElementById('exp_from'), t = document.getElementById('exp_to');
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var ymd = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
        document.querySelectorAll('[data-exp-range]').forEach(function (b) {
            b.addEventListener('click', function () {
                var now = new Date(), y = now.getFullYear(), m = now.getMonth();
                if (b.getAttribute('data-exp-range') === 'last') m -= 1;
                f.value = ymd(new Date(y, m, 1));
                t.value = ymd(new Date(y, m + 1, 0));
            });
        });
    })();
    </script>
    <?php endif; ?>
</section>
