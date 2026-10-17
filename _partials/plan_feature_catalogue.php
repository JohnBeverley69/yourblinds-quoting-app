<?php
declare(strict_types=1);

/**
 * Every feature of YourBlinds, for DESCRIBING the plans (John, 2026-10-17):
 * "a full list of all of the features of the system… little boxes that I can
 * drag into bronze, silver, gold or platinum".
 *
 * These are LISTED features: dragging one to a tier changes what the Billing and
 * welcome pages say that tier includes. It does NOT switch anything on or off —
 * the features that really switch with the plan are the flags in
 * feature_flags.php (Maps, Postcode lookup, Texts, Online booking, Accounts),
 * shown on the same board. John chose "describe now, switch later"; a listed
 * feature becomes a real switch only when he asks for it.
 *
 * key => [label, one-line description, standard tier code]. The keys are stored
 * in app_settings[plan_feature_catalogue] (Master Admin → Plan features), so
 * never rename one. Order here = order on the board and in the plan lists.
 */

return [
    // ── Quoting and selling ─────────────────────────────────────────────
    'instaprice'     => ['InstaPrice',               'Price any blind in seconds, from your own and your suppliers\' price lists', 'free'],
    'quotes'         => ['Quotes',                   'Build, price and email professional quotes with a PDF',                     'free'],
    'online_accept'  => ['Online quote acceptance',  'Customers view, sign and accept their quote online',                        'free'],
    'sign_in_person' => ['Sign on your device',      'The customer signs on your tablet or phone there and then',                 'silver'],
    'quote_followup' => ['Quote follow-ups',         'An automatic reminder for quotes that haven\'t been answered',              'silver'],
    'pipeline'       => ['Sales pipeline',           'Every enquiry, quote and job, from first visit to paid',                    'gold'],
    'customers'      => ['Customers',                'Customer records with every quote, order and visit',                        'free'],
    // ── Ordering ────────────────────────────────────────────────────────
    'orders'         => ['Orders',                   'Turn an accepted quote into an order in one click',                        'free'],
    'supplier_orders'=> ['Supplier ordering',        'Purchase orders emailed to each supplier with a full spec PDF',            'free'],
    'trade_catalogue'=> ['Trade catalogue',          'Your manufacturer\'s products and prices, kept up to date for you',        'free'],
    'supplier_ranges'=> ['Supplier ranges',          'See the supplier ranges available and ask for new ones',                   'free'],
    // ── Calendar and fitting ────────────────────────────────────────────
    'calendar'       => ['Calendar',                 'Measures and fittings, with your own booking slots',                       'free'],
    'fitters'        => ['Fitters',                  'Fitter logins with their own day of jobs',                                 'gold'],
    // ── Money ───────────────────────────────────────────────────────────
    'payments'       => ['Payments and deposits',    'Take deposits, record payments and send receipts',                         'silver'],
    'invoices'       => ['Invoices',                 'Invoice the customer straight from the job',                               'silver'],
    'dashboard'      => ['Dashboard',                'Your sales, quotes won and money owed at a glance',                        'gold'],
    // ── Your products ───────────────────────────────────────────────────
    'products'       => ['Your own products',        'Add your own products, options and price tables',                          'silver'],
    'price_import'   => ['Price list import',        'Import price tables from a spreadsheet, with undo',                        'platinum'],
    // ── Running the business ────────────────────────────────────────────
    'team'           => ['Team and permissions',     'Add staff, choose who sees costs and who can order',                       'platinum'],
    'messages_log'   => ['Messages log',             'Every text and email sent to a customer, in one place',                    'gold'],
    'work_offline'   => ['Work offline',             'Quote on a tablet with no signal; it sends when you\'re back online',      'platinum'],
    'help'           => ['Help guides and assistant','Animated step-by-step guides and a support assistant',                     'free'],
    // ── The factory side (manufacturers) ────────────────────────────────
    'factory_pipeline' => ['Factory Pipeline',       'Every order from new to paid, with one button for the next step',          'factory'],
    'process_order'    => ['Process order',          'One click puts the blinds on the floor and orders the bought-in items',   'factory'],
    'production_floor' => ['Production floor',       'Every blind on the floor by area, so you can see how far each one has got', 'factory'],
    'scanning'         => ['Barcode scanning',       'Scan each blind in at every stage with a Wi-Fi scanner; progress updates itself', 'factory'],
    'worksheets'       => ['Worksheets and labels',  'Your own work tickets and roll labels, with cut sizes worked out',         'factory'],
    'build_rules'      => ['Build rules',            'Cut sizes and parts calculated for every blind you make',                 'factory'],
    'production_areas' => ['Production areas',       'Split the work across benches and routes',                                'factory'],
    'remakes'          => ['Remakes',                'Raise, approve and track remakes, and who pays for them',                 'factory'],
    // NB (2026-10-17): the two below run on the MAIN factory's accounts pages
    // (requireFactoryAccounts); a white-label factory doesn't have them yet —
    // opening them up is part of the parked white-label work.
    'dispatch'         => ['Dispatch and delivery notes', 'Tick the van run, print delivery notes and mark orders dispatched', 'factory'],
    'auto_invoice'     => ['Auto-invoice on dispatch', 'Invoices raised and emailed the moment an order is dispatched',          'factory'],
];
