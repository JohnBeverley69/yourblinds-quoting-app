<?php
declare(strict_types=1);

/**
 * Guide: settings-suppliers
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers /admin/settings.php tab 5 (Suppliers): the delivery address, the
 * four-column supplier table (Supplier / Order email / Account no. / Remove),
 * and the thing that catches everybody out — the supplier NAME is a plain text
 * match against products.supplier_name, not a link, so renaming here does not
 * follow through to your products.
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Suppliers',
        'eyebrow' => 'Settings · Suppliers',
        'blurb'   => 'Your address book of the firms you buy stock from — the delivery address and each supplier\'s order email, and why the name matters more than it looks.',
        'lede'    => 'This tab is your <b>address book of the firms you buy stock from</b>, plus the one address they all ship to.
                      It is <em>not</em> the fabric library. Two things have to be right: the <b>delivery address</b>, because it prints
                      on every purchase order, and each supplier&rsquo;s <b>order email</b>, because that is literally where the order
                      is emailed. Get those two right and &ldquo;Send to suppliers&rdquo; does the rest for you.',
        'open'    => '/admin/settings.php',
        'css'     => '
          /* ---- tab strip (mirrors the real .settings-tabs row) ---- */
          .gd .tabstrip{ display:flex; flex-wrap:wrap; gap:.25rem; border-bottom:1px solid var(--line); margin-bottom:.85rem; }
          .gd .stab{ font-size:.7rem; font-weight:600; color:var(--faint); padding:.3rem .55rem; border-radius:7px 7px 0 0; border:1px solid transparent; border-bottom:none; white-space:nowrap; }
          .gd .stab.on{ color:var(--accent-ink,var(--accent)); background:var(--accent-wash); border-color:var(--line); }
          .gd .stage[data-step="1"] .stab.on{ box-shadow:0 0 0 3px var(--accent-wash); }

          /* ---- labels + the grey page hints ---- */
          .gd .slbl{ display:block; font-size:.72rem; font-weight:600; color:var(--ink); margin-bottom:.3rem; }
          .gd .muted{ color:var(--faint); font-weight:400; }
          .gd .taddr{ min-height:76px; }
          .gd .pghint{ font-size:.66rem; color:var(--faint); margin:.15rem 0 .7rem; line-height:1.5; }

          /* ---- the four-column supplier table ---- */
          .gd .suptable2{ margin-top:.85rem; border:1px solid var(--line); border-radius:8px; overflow:hidden; font-size:.78rem; }
          .gd .sup-head, .gd .sup-row{ display:grid; grid-template-columns:1.05fr 1.7fr .9fr 3.4rem; gap:.5rem; align-items:center; padding:.35rem .55rem; }
          .gd .sup-head{ background:var(--panel); font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .sup-head .rm, .gd .sup-row .rm{ text-align:center; justify-self:center; }
          .gd .sup-row{ border-top:1px solid var(--line); }
          .gd .sup-row .box{ height:26px; border:1px solid var(--line); border-radius:6px; background:var(--panel); display:flex; align-items:center; padding:0 .45rem; font-size:.74rem; color:var(--ink); overflow:hidden; }
          .gd .sup-row .box .val{ font-size:.74rem; padding:0 .45rem; }
          .gd .sup-row .box .ph{ font-size:.72rem; }

          /* values that must STAY on screen past step 5 (the shared f1..f5 rules stop there) */
          .gd .stage[data-step="6"] .keep .ph, .gd .stage[data-step="7"] .keep .ph, .gd .stage[data-step="8"] .keep .ph{ opacity:0; }
          .gd .stage[data-step="6"] .keep .val, .gd .stage[data-step="7"] .keep .val, .gd .stage[data-step="8"] .keep .val{ opacity:1; }

          /* Decora order email — typed with a typo, rejected, then corrected */
          .gd .stage[data-step="4"] .emfix .ph, .gd .stage[data-step="5"] .emfix .ph, .gd .stage[data-step="6"] .emfix .ph,
          .gd .stage[data-step="7"] .emfix .ph, .gd .stage[data-step="8"] .emfix .ph{ opacity:0; }
          .gd .stage[data-step="4"] .emfix .v-bad, .gd .stage[data-step="5"] .emfix .v-bad,
          .gd .stage[data-step="6"] .emfix .v-bad{ opacity:1; }
          .gd .stage[data-step="4"] .emfix .v-bad{ animation: gdRoll .8s ease-out both; }
          .gd .stage[data-step="7"] .emfix .v-fix, .gd .stage[data-step="8"] .emfix .v-fix{ opacity:1; }
          .gd .stage[data-step="7"] .emfix .v-fix{ animation: gdRoll .8s ease-out both; }
          .gd .stage[data-step="6"] .emfix{ border-color:var(--err) !important; box-shadow:0 0 0 3px var(--err-wash); }

          /* the stray row ticked for removal at step 7 */
          .gd .stage[data-step="7"] .tkS, .gd .stage[data-step="8"] .tkS{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="7"] .strayrow, .gd .stage[data-step="8"] .strayrow{ opacity:.55; }

          /* flashes — the real page prints alert alert-error / alert alert-success at the top */
          .gd .flash{ display:none; margin-top:.8rem; }
          .gd .stage[data-step="6"] .flash-err{ display:block; }
          .gd .stage[data-step="7"] .flash-ok, .gd .stage[data-step="8"] .flash-ok{ display:block; }

          /* the purchase order payoff */
          .gd .popanel{ display:none; margin-top:.9rem; border:1px solid var(--line); border-radius:9px; overflow:hidden; }
          .gd .stage[data-step="8"] .popanel{ display:block; }
          .gd .pohd{ background:var(--panel); border-bottom:1px solid var(--line); padding:.4rem .65rem; display:flex; align-items:baseline; gap:.6rem; }
          .gd .pohd .pot{ font-size:.72rem; font-weight:800; letter-spacing:.08em; color:var(--ink); }
          .gd .pohd .por{ font-size:.66rem; color:var(--faint); }
          .gd .pogrid{ display:grid; grid-template-columns:1fr 1fr; gap:.5rem; padding:.55rem .65rem; }
          .gd .pobox{ border:1px solid var(--line); border-radius:7px; padding:.35rem .5rem; font-size:.7rem; color:var(--soft); line-height:1.45; }
          .gd .pobox .pol{ display:block; font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin-bottom:.15rem; }
          .gd .pofoot{ border-top:1px solid var(--line); padding:.35rem .65rem; font-size:.66rem; color:var(--faint); }
          @media(max-width:620px){ .gd .pogrid{ grid-template-columns:1fr; } .gd .sup-head, .gd .sup-row{ grid-template-columns:1fr 1.3fr .8fr 2.6rem; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="tabstrip">
                  <span class="stab">Company</span><span class="stab">Quoting</span><span class="stab">Legal</span>
                  <span class="stab">Status colours</span><span class="stab on">Suppliers</span>
                  <span class="stab">Accounting</span><span class="stab">Back up data</span>
                </div>

                <div class="card-t">Suppliers</div>
                <p class="pghint">Who you <b>order stock from</b> &mdash; these fill a product&rsquo;s <em>Order supplier</em> field and go on purchase orders.</p>

                <label class="slbl">Delivery address <span class="muted">(where suppliers ship to)</span></label>
                <div class="ta taddr f2 keep"><span class="ph">Your business / warehouse address &mdash; this goes on every supplier order</span><span class="val">Demo Blinds Ltd<br>Unit 4, Sample Way<br>Leeds<br>LS1 1AA</span></div>

                <p class="pghint" style="margin-top:.8rem">Add the order email for each supplier. You can rename a supplier, tick <b>Remove</b> to delete it, or add one in the bottom row &mdash; then Save.</p>

                <div class="suptable2">
                  <div class="sup-head"><span>Supplier</span><span>Order email</span><span>Account no.</span><span class="rm">Remove</span></div>

                  <div class="sup-row">
                    <span class="box"><span class="val" style="opacity:1">In House</span></span>
                    <span class="box"><span class="ph">orders@supplier.com</span></span>
                    <span class="box"><span class="ph">Your account no.</span></span>
                    <span class="rm"><span class="tick">&check;</span></span>
                  </div>

                  <div class="sup-row">
                    <span class="box f3 keep"><span class="ph">Supplier name</span><span class="val">Louvolite</span></span>
                    <span class="box f3 keep"><span class="ph">orders@supplier.com</span><span class="val">orders@louvolite.example</span></span>
                    <span class="box f3 keep"><span class="ph">Your account no.</span><span class="val">LV-4471</span></span>
                    <span class="rm"><span class="tick">&check;</span></span>
                  </div>

                  <div class="sup-row">
                    <span class="box f4 keep"><span class="ph">Supplier name</span><span class="val">Decora</span></span>
                    <span class="box emfix"><span class="ph">orders@supplier.com</span><span class="val v-bad">trade@decora</span><span class="val v-fix">trade@decora.example</span></span>
                    <span class="box f4 keep"><span class="ph">Your account no.</span><span class="val">DEC-208</span></span>
                    <span class="rm"><span class="tick">&check;</span></span>
                  </div>

                  <div class="sup-row strayrow">
                    <span class="box"><span class="val" style="opacity:1">Louvolight</span></span>
                    <span class="box"><span class="ph">orders@supplier.com</span></span>
                    <span class="box"><span class="ph">Your account no.</span></span>
                    <span class="rm"><span class="tick tkS">&check;</span></span>
                  </div>

                  <div class="sup-row">
                    <span class="box f5 keep"><span class="ph">+ Add a supplier</span><span class="val">Blindspace UK</span></span>
                    <span class="box f5 keep"><span class="ph">orders@supplier.com</span><span class="val">orders@blindspace.example</span></span>
                    <span class="box f5 keep"><span class="ph">Your account no.</span><span class="val">BS-9902</span></span>
                    <span class="rm"></span>
                  </div>
                </div>

                <div class="save">Save suppliers</div>

                <div class="flash flash-err"><div class="errbanner"><span>&#9888;</span><div>That doesn&rsquo;t look like a valid email for &ldquo;Decora&rdquo;.</div></div></div>
                <div class="flash flash-ok"><div class="okbanner"><span>&check;</span><div>Suppliers saved.</div></div></div>

                <div class="popanel">
                  <div class="pohd"><span class="pot">PURCHASE ORDER</span><span class="por">Ref: Q-1042</span></div>
                  <div class="pogrid">
                    <div class="pobox"><span class="pol">Supplier</span>Louvolite<br>Account no: LV-4471</div>
                    <div class="pobox"><span class="pol">Deliver to</span>Demo Blinds Ltd<br>Unit 4, Sample Way<br>Leeds LS1 1AA</div>
                  </div>
                  <div class="pofoot">Sizes, fabric, band, room, notes and every option &mdash; and no customer prices.</div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Settings &rarr; the <b>Suppliers</b> tab, fifth along.</b>
                  <b class="c2"><span class="n">2</span> Your delivery address &mdash; it prints on every order.</b>
                  <b class="c3"><span class="n">3</span> Louvolite: name, order email, account number.</b>
                  <b class="c4"><span class="n">4</span> Decora next &mdash; and <b>In House</b> sits on top.</b>
                  <b class="c5"><span class="n">5</span> The bottom row adds a new supplier.</b>
                  <b class="c6 err"><span class="n">6</span> Bad email &mdash; the whole save is thrown out.</b>
                  <b class="c7 good"><span class="n">7</span> Fixed, stray ticked for Remove, saved.</b>
                  <b class="c8 good"><span class="n">8</span> That is what your supplier receives.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Open <b>Settings</b> from the sidebar and click the <b>Suppliers</b> tab &mdash; it&rsquo;s the fifth of seven
             (Company, Quoting, Legal, Status colours, <b>Suppliers</b>, Accounting, Back up data). This tab is who you
             <b>order stock from</b>. It is not your fabric list and it is nothing to do with the old price-list library.
             What you put here fills each product&rsquo;s <b>Order supplier</b> box and goes out on every purchase order.</p>
          <ul class="steps">
            <li><b>Delivery address</b> &mdash; the one address <em>all</em> your suppliers ship to. It prints on every
                purchase order in the <b>Deliver to</b> box, so put the full thing in, exactly as a courier would need it.
                Leave it empty and the send screen stops you with
                &ldquo;<b>No delivery address set &mdash; suppliers won&rsquo;t know where to ship.</b> Add one under
                Settings &rsaquo; Suppliers first.&rdquo;</li>
            <li><b>One row per supplier</b>, four columns, and <em>every</em> cell is a live box you can type in:
                <b>Supplier</b> (the name, up to 150 characters &mdash; typing over it <em>is</em> how you rename one),
                <b>Order email</b> (where the purchase order is sent, shown as <code>orders@supplier.com</code> until you fill it),
                <b>Account no.</b> (optional &mdash; your trade account number with them) and <b>Remove</b> (one tick box per row).
                The <b>Account no.</b> column only appears once that upgrade has been run on your site; if you can&rsquo;t see it,
                nothing is broken.</li>
            <li><b>The bottom row adds one.</b> It&rsquo;s the row showing <code>+ Add a supplier</code>. Type a name, an email,
                an account number if you have one, then <b>Save suppliers</b>.</li>
          </ul>
          <p><b>The list mostly fills itself.</b> Every time you save a product with an <em>Order supplier</em> typed on it,
             that name is added here automatically &mdash; so usually your only job on this tab is adding the email. It works the
             other way round too: the drop-down list on a product&rsquo;s <b>Order supplier</b> box is fed from <b>this table and
             nowhere else</b>, plus <em>In House</em>, which is always offered.</p>
          <p><b>&ldquo;In House&rdquo;</b> is pinned to the top of the list on purpose. It means <em>you make it yourself</em>: no
             email is ever sent for it, no shipping address is asked for, and it never produces a purchase order. Putting a real
             email on In House achieves nothing. Products you buy from a master catalogue don&rsquo;t need a supplier either &mdash;
             on the product they read &ldquo;<b>Made by &lt;factory&gt; &mdash; orders go straight to their manufacturing</b>&rdquo;
             and they route themselves into their own group when you place the order.</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>The supplier name here is just text &mdash; your products don&rsquo;t follow it.</b>
             There is no link between the two: your products hold the supplier&rsquo;s name as plain words. Rename <b>Louvolite</b> to
             <b>Louvolite Ltd</b> on this tab and every product still says <em>Louvolite</em>, so the send screen can no longer find an
             email and tells you &ldquo;<b>No order email for Louvolite</b> &mdash; fix it under Settings &rsaquo; Suppliers to send this.&rdquo;
             If a name really must change, <b>change it on the products first</b>, then tidy this list to match. Deleting behaves the same
             way: the name simply comes back the next time you save a product that uses it. And renaming one row onto a name that&rsquo;s
             already in the list is quietly skipped &mdash; you&rsquo;ll get &ldquo;<b>Suppliers saved &mdash; a rename was skipped because
             that name is already in your list.</b>&rdquo; One more catch: <b>clearing a name out of the box does not delete the row</b>.
             That row is just skipped. Only the <b>Remove</b> tick deletes.</div></div>
          <div class="oops"><b>If you mistype an email.</b> The page won&rsquo;t stop you typing it &mdash; the <em>save</em> does. Press
             <b>Save suppliers</b> with <code>trade@decora</code> in a row and it comes straight back with
             &ldquo;<b>That doesn&rsquo;t look like a valid email for &ldquo;Decora&rdquo;.</b>&rdquo; and <b>nothing at all is saved</b> &mdash;
             not even the delivery address you typed in the same go. Correct the address and press <b>Save suppliers</b> again for
             &ldquo;<b>Suppliers saved.</b>&rdquo; (If instead you see &ldquo;Could not save suppliers: &hellip; &mdash; have you run
             migrate_suppliers.php?&rdquo;, that&rsquo;s a setup job &mdash; ask whoever looks after the site.)</div>
          <p><b>What it&rsquo;s all for.</b> Once a quote is accepted, <b>&#128230; Send to suppliers</b> splits the job up by each
             product&rsquo;s <em>Order supplier</em> and emails each firm <b>only their own lines</b> with a spec PDF attached. A
             supplier with no email shows &ldquo;No order email &hellip; fix it under Settings &rsaquo; Suppliers to send this&rdquo;;
             a product with nothing set at all shows &ldquo;<b>&#9888; No supplier set</b>&rdquo; and tells you to assign one on the
             product. Every send is logged, so coming back later you&rsquo;ll see &ldquo;<b>&#9888; Already sent</b>&rdquo; and that
             row is left <em>unticked</em> on purpose &mdash; &ldquo;left unticked so you don&rsquo;t double-order.&rdquo; Sending also
             moves the job on from <b>Accepted</b> to <b>Ordered</b>. Factory accounts use this very same list and the very same
             delivery address from <b>&#128230; Order bought-in</b> on their incoming orders.</p>
          <p><b>Before you switch on auto-ordering.</b> Over on <b>Settings &rsaquo; Quoting</b> there&rsquo;s
             &ldquo;Automatically order bought-in blinds from their supplier when an order is placed&rdquo;. It is <b>off by default</b>
             and it sends <em>real</em> purchase orders with nobody checking first, so only turn it on once every email on this tab has
             been confirmed. A supplier with no email is simply left for you to order by hand &mdash; it won&rsquo;t fail silently.</p>
          <p><b>Three different things called &ldquo;supplier&rdquo;</b>, so you don&rsquo;t get them muddled:
             <b>Order supplier</b> is this tab &mdash; who you buy from and where the order is emailed.
             The <b>Supplier</b> box on a fabric row (set on a product&rsquo;s Fabrics page) is who <em>makes the fabric</em> &mdash;
             unrelated to this list. And <b>catalogue price updates</b> match products by their <b>name prefix</b>, not by anything on
             this tab &mdash; so changing a supplier name here will never change what a price update touches. The old tenant
             Supplier Price-List Library has been retired.</p>
          <p class="prose"><b>In Compact mode</b>, the two grey explanation lines on this tab disappear &mdash; they&rsquo;re hints.
             The table, the button and everything they do are exactly the same.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Settings opens; Suppliers tab highlighted.',      'Settings, then the Suppliers tab — the fifth one along. This is the list of firms you buy your stock from, and it is what your purchase orders are sent to.', 1],
            ['0:10', 'Delivery address types in.',                      'Start with the delivery address — where your suppliers send the goods. It prints on every single purchase order, so put the full thing in, exactly as a courier would need it. Leave it blank and the send screen stops you with, no delivery address set — suppliers won\'t know where to ship.', 2],
            ['0:25', 'Louvolite: name, order email, account fill.',     'Now each supplier. The name, then the order email — this is the important one, because the order is emailed to exactly this address and nowhere else. Then your account number with them, so they can see whose order it is.', 3],
            ['0:38', 'Decora row fills; In House sits on top.',         'The same again for the next one. And notice In House sitting at the top — that\'s the app\'s own marker for anything you make yourself. It never needs an email, and it never gets a purchase order.', 4],
            ['0:50', 'The bottom row fills — a new supplier.',          'The empty row at the bottom is how you add one. Most of the time you won\'t need it: whenever you save a product and type a supplier on it, that name appears here on its own. All that\'s missing is the email, and that\'s what you\'re here for.', 5],
            ['1:03', 'Decora\'s email is wrong; the save is rejected.', 'Get an address wrong and the page won\'t quietly take it. It comes back with, that doesn\'t look like a valid email for Decora — and nothing at all is saved, not even the delivery address you just typed. Fix the address and save again.', 6],
            ['1:17', 'Stray row ticked for Remove; saved.',             'To get rid of a stray, tick Remove on its row and save. Clearing the name out of the box does nothing at all — the row just gets skipped. And be careful renaming one: your products still hold the old name, so the order can\'t find an email for it any more. Safer to leave the name alone and change it on the products first.', 7],
            ['1:35', 'The purchase order it all produces.',             'When you place the order, each supplier gets an email of their own lines with a purchase order attached — the sizes, the fabric and the options, your account number and your delivery address, and no customer prices at all. Which is why these two boxes are worth five minutes now.', 8],
        ],
];
