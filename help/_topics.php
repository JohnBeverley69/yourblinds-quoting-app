<?php
declare(strict_types=1);

/**
 * Help topics — the searchable written answers on help/index.php, separate from
 * the animated walkthroughs in help/guides/.
 *
 * Each row: [aud, cat, title, keys, body]
 *   aud   all | admin | super  — who may see it
 *   cat   the section it groups under
 *   title the question, as the user would ask it
 *   keys  extra search words (the box on the page matches title + keys + body)
 *   body  HTML answer
 *
 * Lifted out of help/index.php, which had grown to 570 lines with 200 of them
 * being this array.
 */

return [
    // ---- Getting started ----
    ['all', 'Getting started', 'How the app flows', 'overview start begin pipeline funnel',
     '<p>The journey of a job runs: <strong>Quote → Order → Invoice → Payment</strong>.</p>
      <ul><li>Build a <strong>quote</strong> for a customer (or use <strong>InstaPrice</strong> for a quick figure).</li>
      <li>When the customer accepts, it becomes an <strong>order</strong>.</li>
      <li>Book the fit on the <strong>Calendar</strong>.</li>
      <li>Record <strong>payments</strong> (and the deposit) on the Payments page.</li></ul>'],

    // ---- Quoting ----
    ['all', 'Quoting', 'Building a quote', 'quote builder add blind product system band fabric width drop',
     '<p>In the quote builder you add one blind at a time: pick the <strong>product</strong> →
      <strong>system</strong> (variant) → <strong>band</strong> (price tier) → <strong>fabric / slat</strong> →
      enter <strong>width × drop</strong> and <strong>quantity</strong> → add any <strong>options</strong>.
      The price updates live. Add as many blinds as the job needs, then send or print.</p>'],
    ['all', 'Quoting', 'InstaPrice (quick price)', 'instaprice quick price no customer estimate',
     '<p><strong>InstaPrice</strong> (sidebar) gives a price with no customer attached — handy on the phone or
      a site visit. Same engine as the quote builder. Hit <em>“Turn into full quote”</em> to keep it.</p>'],
    ['admin', 'Quoting', 'Hide prices on the customer quote', 'hide price per blind line total settings',
     '<p>In <strong>Settings → Quoting</strong>, the <em>“Show the price of each blind”</em> tick controls whether
      the customer’s PDF / online quote shows a price per blind. Untick it to show only the overall total.</p>'],
    ['admin', 'Quoting', 'Measurement units', 'mm cm metres inches measurement unit',
     '<p>Set your company default in <strong>Settings → Quoting</strong> (mm / cm / m / in). Sizes are always stored
      in millimetres; the unit only changes how you type and read them. You can override the unit per quote.</p>'],
    ['admin', 'Quoting', 'Paid-in-full receipt', 'receipt paid full balance zero thank you settled automatic email customer',
     '<p>On by default (Settings → Quoting → “Paid-in-full receipt”). When a payment settles an order’s
      <strong>balance to zero</strong>, the customer is automatically emailed a <strong>thank-you receipt</strong> —
      the order PDF headed “Receipt”, showing it’s paid in full.</p>
      <p>It’s sent <strong>once</strong> per order (it can never go twice), and only when the customer has a valid email
      on their record. Recording the final payment on the order’s Payments panel is what triggers it. Untick the setting
      to turn it off.</p>'],
    ['admin', 'Quoting', 'WT charge (internal surcharge)', 'wt charge surcharge wally tax internal hidden markup awkward customer fee',
     '<p>Turn it on in <strong>Settings → Quoting → “WT charge”</strong>. It adds a small <strong>WT</strong> box to the
      quote builder so you can add a discretionary amount to a quote &mdash; entirely <strong>internal</strong>.</p>
      <ul><li>The customer <strong>never</strong> sees the letters “WT”, or a separate line, anywhere on their quote or invoice.</li>
      <li>It’s added <strong>before VAT</strong> (so a £30 WT adds £36 to a 20%-VAT total).</li>
      <li>If <em>“Show the price of each blind”</em> is on, the WT is <strong>spread across the blind prices</strong>
      (proportionally) so the figures still add up; if it’s off, it just lifts the total.</li>
      <li>On the builder your totals show a WT line so you know it’s there &mdash; that line is stripped from anything the customer sees.</li></ul>'],
    ['admin', 'Quoting', 'Bank details on quotes (how customers pay)', 'bank transfer sort code account number payment details pay invoice',
     '<p>In <strong>Settings → Quoting → “Bank details for customer payments”</strong>, enter your account name,
      sort code and account number (plus an optional note). They then print a <strong>“How to pay — bank transfer”</strong>
      block on the customer’s quote / invoice (PDF + online), with the quote number as the suggested reference.
      Leave the fields blank to hide the block.</p>'],

    // ---- Calendar / customers ----
    ['all', 'Calendar & customers', 'Booking jobs on the calendar', 'calendar appointment booking fit day week maps waze',
     '<p>The <strong>Calendar</strong> shows your fits by month / week / day. Book or open an appointment, link it to an
      order, and use the <strong>Maps / Waze</strong> buttons for directions. The day/week view stretches only where
      bookings genuinely overlap.</p>'],
    ['all', 'Calendar & customers', 'Customers & postcode lookup', 'customer manager address postcode lookup',
     '<p>Manage customers in <strong>Customers</strong>. On the address fields, the <strong>postcode lookup</strong>
      finds the address for you (where enabled on your plan).</p>'],

    // ---- Products & pricing ----
    ['admin', 'Products & pricing', 'Reordering, grouping and bulk-deleting products', 'reorder drag drop sort order products list multi select bulk delete move group folder checkbox tick all at once',
     '<p>On the <strong>Products</strong> page, drag a row’s <strong>⋮⋮</strong> handle to <strong>reorder</strong> products —
      the new order sticks and is the order they appear in quotes.</p>
      <p><strong>To move many products into a group at once</strong> (instead of dragging them one by one): <strong>tick</strong>
      the ones you want — tick one then <strong>Shift-click</strong> another to grab everything in between, or use the
      checkbox in a table’s header to select them all — then choose the group from the
      <strong>“Move selected to…”</strong> dropdown in the bar at the top. Pick “Ungrouped” there to pull them back out.</p>
      <p>The same selection works for <strong>Delete selected</strong> (it confirms and shows how many will go; deleting a
      product also removes its options, extras and price tables).</p>'],
    ['admin', 'Products & pricing', 'How pricing is structured', 'product system band price table grid model',
     '<p>The model is <strong>Product → System → Band → Price table</strong>. A <strong>system</strong> is a variant
      (e.g. a slat size or motorised). A <strong>band</strong> is a price tier. Each <strong>fabric/slat</strong> carries
      a band, and each <strong>price table</strong> is a width × drop grid for one (system, band). The band code is the
      link between a fabric and its prices.</p>'],
    ['admin', 'Products & pricing', 'Combine products into one (systems)', 'combine merge master product systems sizes slat 15mm 25mm fold together',
     '<p>If you imported a family as separate products (e.g. <em>15/25/35/50mm Venetian</em>) and want them under
      one product, tick them in the <strong>Products</strong> list and press <strong>“Combine into product…”</strong>.
      Name the master (e.g. <em>Metal Venetian</em>) and each one’s <strong>system</strong> name (15mm, 25mm…).</p>
      <p>The first ticked product becomes the master; the rest fold in as systems and are then
      <strong>deactivated</strong> (their fabrics, price tables and settings move across — nothing is lost).
      Each size’s slat colours are scoped to its own system, so the quote builder shows only the colours that size
      offers. Delete the empty leftovers once you’ve checked the result.</p>
      <p><strong>Adding more later:</strong> tick the existing master <em>first</em>, then the new single-size
      product(s), and Combine again — the new ones are appended as extra systems (the master keeps everything it has).</p>'],
    ['admin', 'Products & pricing', 'Setting up a product (wizard)', 'wizard new product setup steps systems fabrics price tables',
     '<p>The <strong>setup wizard</strong> walks you through Name → Systems → Fabrics → Price tables.</p>
      <p><strong>Tip — pricing first:</strong> on the Fabrics step there’s a <em>“Price tables first →”</em> button.
      Use it to import your price grids first; the bands you import then <strong>auto-suggest</strong> in the fabric’s
      Band box, so you don’t type band names twice.</p>
      <p>On the <strong>Fabrics</strong> step you don’t have to type them all in — alongside the paste box there are
      <strong>Import from Fabric Library</strong> and <strong>Import from spreadsheet</strong> buttons. Use either and
      you’re brought straight back to the wizard to carry on.</p>'],
    ['admin', 'Products & pricing', 'Pricing modes', 'width only per slat per square metre sqm shutter venetian headrail',
     '<p>A product prices one of four ways: the normal <strong>width × drop grid</strong>; <strong>width only</strong>
      (headrails / tracks); <strong>per slat</strong> (vertical fabric replacement); or <strong>per m²</strong>
      (shutters). Set it on the product’s edit page or wizard step 1.</p>'],
    ['admin', 'Products & pricing', 'Band codes can be descriptive', 'band code length name tape herringbone bamboo 60 characters',
     '<p>Band codes aren’t limited to A/B/C — they can be full names up to <strong>60 characters</strong>
      (e.g. “50mm Bamboo &amp; Gloss Herringbone Tape”). The same band must appear on the fabric/slat and on its
      price table for pricing to resolve.</p>'],
    ['admin', 'Products & pricing', 'Bulk-importing price tables', 'bulk import excel multi band worksheet sheet picker spreadsheet',
     '<p>On a system’s Price tables page, <strong>Bulk import (multiple bands)</strong> reads a multi-band Excel file —
      each band block starts with a <code>Band X</code> row. If the file has <strong>several worksheets</strong> with
      bands (e.g. one per slat size), you’ll be asked <strong>which worksheet</strong> to import into this system.
      Re-importing replaces that band’s prices.</p>'],
    ['admin', 'Products & pricing', 'Bulk-importing fabrics across products', 'bulk import fabrics colours one file per product sheet match supplier workbook distribute all at once',
     '<p>Got a supplier workbook with <strong>one sheet per product</strong> (columns <code>Name</code>, <code>Colour</code>,
      <code>Band</code>)? Use <strong>Bulk import fabrics</strong> (button on the Products page) instead of importing each
      product one by one. Upload it once and every worksheet is <strong>matched to a product by name</strong> — you check
      the suggested matches, then import them all in one click.</p>
      <p>Each sheet can go to <strong>more than one product</strong> — Ctrl/Cmd-click in its list to pick several. That’s how
      you handle a <strong>shared fabric range</strong>: one softshade range feeds all your softshade blinds, one roller range
      feeds the roller products, etc. — tick them all and they import together (this is what fixes products still showing
      “Needs fabric”). Leave a sheet with nothing selected to skip it. Duplicate rows are skipped automatically. For a single
      product, the per-product <em>Import</em> on its Fabrics page is still the simpler route.</p>'],
    ['admin', 'Products & pricing', 'Selecting many fabrics at once', 'shift click range select set band on selected bulk fabrics',
     '<p>On a product’s <strong>Fabrics</strong> page you can tick rows and use <em>Set band on selected</em> /
      <em>Delete selected</em>. To grab a run quickly: tick one row, then <strong>Shift-click</strong> another — every
      row between is selected. The search box filters the list first.</p>'],
    ['admin', 'Products & pricing', 'Order supplier vs Library supplier', 'supplier order purchase po library catalogue prefix confusion',
     '<p>Two different “suppliers”:</p>
      <ul><li><strong>Order supplier</strong> (on the product, and in <strong>Settings → Suppliers</strong>) = who you
      <em>order stock from</em>, for purchase orders.</li>
      <li><strong>Library supplier</strong> (master catalogue) = the catalogue grouping, matched by the product’s
      <strong>name prefix</strong>, not by the Order supplier field.</li></ul>
      <p>Settings → Suppliers is the single source for the Order-supplier dropdown — delete a stray there and it’s gone.</p>'],
    ['admin', 'Products & pricing', 'Sending the order to your suppliers', 'send order suppliers purchase order po email materials ordered pipeline status moved colour',
     '<p>Once a quote is <strong>accepted</strong>, open it and use <strong>“Send to suppliers”</strong>. It splits the
      job by each product’s <strong>Order supplier</strong>, emails every supplier only their own lines with a spec PDF
      (sizes, no customer prices), shipped to your delivery address from <strong>Settings → Suppliers</strong>.</p>
      <p>Sending also moves the job on to <strong>“Ordered”</strong>, so it steps along in the <strong>Pipeline</strong>
      and changes colour on the <strong>Calendar</strong> automatically. It only ever steps Accepted&nbsp;→&nbsp;Ordered —
      a job already further along (fitted / invoiced / paid) is never pulled back.</p>'],
    ['admin', 'Products & pricing', 'Invoicing the customer', 'invoice send invoice email balance due pipeline invoiced bill customer',
     '<p>Once a job is an order (Ordered onward), open it and use <strong>“Send invoice”</strong> in the quote actions.
      It emails the customer their <strong>invoice</strong> (the order PDF, headed “Invoice”, showing the total, anything
      already paid, the <strong>balance due</strong>, and your bank details), with the balance also stated in the email.</p>
      <p>Sending also moves the job on to <strong>“Invoiced”</strong> — so it advances in the Pipeline and recolours on the
      Calendar. It only steps Ordered/Fitted&nbsp;→&nbsp;Invoiced, never backwards. The customer needs a valid email on
      their record. Order-side action: admins and users who can create orders.</p>'],
    ['admin', 'Products & pricing', 'Markup vs margin (how you enter your profit)', 'markup margin profit basis pricing settings convert default',
     '<p>You can enter your profit as <strong>markup</strong> or <strong>margin</strong> — set which on
      <strong>Settings → Quoting → Default margins</strong> (“Enter your margins as”).</p>
      <ul><li><strong>Markup</strong> is added on top of your cost — cost&nbsp;+&nbsp;50% = sell.</li>
      <li><strong>Margin</strong> is the profit slice of the <em>sell</em> price — a 50% margin means cost is half the sell.</li></ul>
      <p>The customer price is identical either way; this only changes which number you type. Whatever you pick is used
      everywhere you set a rate — the default margins, per-product overrides, InstaPrice, and the per-blind override
      in the quote builder. As you type, a small “≈ … markup” line shows the equivalent so you can sense-check it.
      You can switch basis any time <strong>without re-pricing anything</strong> — nothing already saved changes.</p>'],
    ['admin', 'Quoting', 'Adjust the price of one blind on a quote', 'override markup margin discount per line quote builder tune adjust this blind',
     '<p>In the quote builder, under a blind’s details, open <strong>“Adjust price for this blind”</strong> to set a
      one-off <strong>markup/margin %</strong> or <strong>discount %</strong> just for that line — handy for matching a
      price or giving a deal. Leave the boxes blank to use the product’s normal rate. The live price updates as you type,
      and the figure is shown in your chosen basis (markup or margin). Only people who can see costs get these boxes.</p>'],

    // ---- Fabric Library (super) ----
    ['super', 'Fabric Library', 'Fabric Library structure', 'fabric library supplier range fabrics group manufacturer',
     '<p>The Fabric Library holds the cloth, structured as <strong>Supplier → Range → Fabrics</strong>.
      Create a <strong>supplier group</strong> (e.g. Decora) and drag each range’s ⠿ grip into it. Inside a range you can
      also group fabrics under headings. Everything is non-destructive — nothing merges.</p>'],
    ['super', 'Fabric Library', 'Importing fabrics & groups carrying through', 'import fabrics excel pull into product carry group multi sheet workbook review type skip',
     '<p><strong>Import fabrics</strong> loads a manufacturer’s range from a spreadsheet. It reads <strong>every worksheet</strong>
      in one go, so a multi-sheet supplier workbook (one sheet per blind type) imports in a single pass. On <strong>Preview</strong>
      you get a <strong>review-by-sheet</strong> table: each sheet imports under a <strong>type</strong> (taken from the sheet name —
      edit it to tidy up, e.g. “Decora Roller” → “Roller”), and you can <strong>untick</strong> any sheet to skip it. Duplicates
      (name + colour) are skipped automatically.</p>
      <p>When you pull library fabrics into a product, their <strong>group rides along</strong> and shows as a Group column on
      the product’s Fabrics page.</p>'],

    // ---- Master catalogue (super) ----
    ['super', 'Master catalogue', 'Catalogue & pushing to clients', 'master catalogue push prefix library suppliers tenants',
     '<p>The <strong>Master Catalogue</strong> is the source every client is built from. Products are filed under a
      supplier by their <strong>name prefix</strong> (e.g. “Bev …”). <strong>Push updates</strong> copies a supplier’s
      products into the tenants that subscribe to it.</p>'],

    // ---- Accounts ----
    ['admin', 'Accounts', 'Recording payments & deposits', 'payment deposit record ledger received outstanding',
     '<p>On <strong>Payments</strong>, log each payment against an order (method, reference, date). The deposit is a
      payment flagged as a deposit. The page shows received vs outstanding per order.</p>'],
    ['admin', 'Accounts', 'Export to Xero / QuickBooks (CSV)', 'accounts csv export xero quickbooks sage invoices payments accounting',
     '<p>On <strong>Payments</strong>, admins get <strong>Export invoices (CSV)</strong> and
      <strong>Export payments (CSV)</strong>. Invoices export one row per order line (net of VAT) in Xero’s import shape;
      they default to account code <strong>200 (Sales)</strong> and <strong>20% VAT</strong> — remap on import if your
      chart of accounts differs. Use the <strong>This month / Last month</strong> buttons to scope the period in one
      click.</p>'],

    // ---- Settings & admin ----
    ['admin', 'Settings', 'Quote defaults & suppliers', 'settings vat deposit prefix supplier email terms legal',
     '<p><strong>Settings</strong> holds your quote prefix, VAT %, default deposit, measurement unit, the
      <em>Order suppliers</em> list (with their order emails) and your Terms / Privacy text.</p>'],
    ['admin', 'Settings', 'Plans & billing', 'billing tier bronze silver gold plan subscription add-on cancel',
     '<p><strong>Billing</strong> shows your plan: Bronze (core) → Silver (+Maps/Postcode) → Gold (+Accounts).
      Each tier includes everything below it.</p>
      <p>If you <strong>cancel</strong>, billing stops but you keep that plan\'s features until the end of the
      period you\'ve already paid for — they switch off on that date, not immediately.</p>'],

    // ---- Safety (super) ----
    ['super', 'Backups & safety', 'Backups and recovery', 'backup restore cloudways recover data loss snapshot',
     '<p>The host takes an automatic <strong>daily database backup</strong> — the lifeline if data is lost.
      <strong>Master Admin → Backup</strong> also downloads a manual SQL copy (and per-tenant exports). Recovery from a
      host backup is a full restore, so do a manual backup first.</p>'],
    ['super', 'Backups & safety', 'Go-live checklist', 'launch go live readiness paypal sandbox test data checklist',
     '<p><strong>Master Admin → Go-live checklist</strong> shows pre-launch readiness: it auto-checks PayPal mode
      (sandbox vs live), app environment, API keys, terms, VAT and backup age; scans this account for left-over
      <em>test/demo</em> products, suppliers and customers; and lists the manual steps (incl. switching PayPal to live).</p>'],
    ['super', 'Backups & safety', 'Wipe products (handle with care)', 'wipe products delete bulk master protect',
     '<p><strong>Wipe products</strong> bulk-deletes matching products across tenants. The <strong>master catalogue</strong>
      is protected — it’s never pre-ticked and is excluded unless you explicitly tick “include the master catalogue”.</p>'],
];
