<?php
declare(strict_types=1);

/**
 * Guide: settings-accounting
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Four scenes, driven by the stage's data-step:
 *   .scTab   (steps 0, 1, 4, 8) — Settings → Accounting tab, the QuickBooks Online card
 *   .scIntu  (steps 2, 3)       — QuickBooks' own sign-in / "connect" screens (Intuit's site)
 *   .scMap   (steps 5, 6, 7)    — /admin/accounting/quickbooks-mapping.php
 *
 * Sources: admin/settings.php (Accounting panel — card states, badges, buttons,
 * mapping summary, "Xero & Sage — Coming soon"), admin/accounting/connect.php,
 * callback.php (flash "Connected to QuickBooks Online (<company>).", error
 * flashes), disconnect.php (confirm + flash), quickbooks-mapping.php (four
 * fields, hints, "QuickBooks mapping saved."). The Intuit screens are Intuit's,
 * not ours, so they're drawn plainly and described in general terms.
 *
 * NOTE (keep true): the paid-sale push to QuickBooks (phase 3) is NOT built yet
 * — connecting + mapping only prepares it. Say so; don't promise it happens.
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Link your accounts package (QuickBooks Online)',
        'eyebrow' => 'Settings · Accounting',
        'blurb'   => 'Connect your QuickBooks Online company in two minutes, then tell YourBlinds which item, VAT code and bank account your paid sales belong to. Xero and Sage: use the CSV guide for now.',
        'lede'    => 'The <b>Accounting</b> tab links YourBlinds to <b>your own QuickBooks Online</b> company. You sign in on
                      <b>QuickBooks&rsquo; own website</b> &mdash; YourBlinds never sees your QuickBooks password &mdash; and then answer
                      <b>four questions</b> about where paid sales should go. It works on <b>cash accounting</b>: nothing is ever sent
                      until a job is <b>paid</b>. Use <b>Xero</b>, <b>Sage</b> or <b>FreeAgent</b> instead? Those links aren&rsquo;t
                      switched on yet &mdash; see the guide <b>&ldquo;Get your figures into Xero, QuickBooks or Sage&rdquo;</b>.',
        'open'    => '/admin/settings.php#accounting',
        'css'     => '
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scTab, .gd .stage[data-step="1"] .scTab,
          .gd .stage[data-step="4"] .scTab, .gd .stage[data-step="8"] .scTab{ display:block; }
          .gd .stage[data-step="2"] .scIntu, .gd .stage[data-step="3"] .scIntu{ display:block; }
          .gd .stage[data-step="5"] .scMap, .gd .stage[data-step="6"] .scMap, .gd .stage[data-step="7"] .scMap{ display:block; }

          /* ---- settings tab strip: Accounting is the sixth of seven ---- */
          .gd .tabstrip{ display:flex; flex-wrap:wrap; gap:.1rem .25rem; border-bottom:1px solid var(--line); margin-bottom:.6rem; }
          .gd .tabx{ font-size:.68rem; color:var(--faint); padding:.28rem .45rem; border-bottom:2px solid transparent; white-space:nowrap; }
          .gd .tabx.on{ color:var(--accent); font-weight:700; border-bottom-color:var(--accent); }
          .gd .bhint{ font-size:.68rem; color:var(--faint); line-height:1.45; margin:-.55rem 0 .75rem; max-width:30rem; }

          /* ---- the QuickBooks Online card ---- */
          .gd .qcard{ border:1px solid var(--line); border-radius:10px; padding:.65rem .8rem; background:var(--surface); max-width:30rem; }
          .gd .qtop{ display:flex; align-items:center; justify-content:space-between; gap:.6rem; flex-wrap:wrap; }
          .gd .qname{ font-weight:700; font-size:.86rem; color:var(--ink); }
          .gd .qbadge{ margin-left:.4rem; font-size:.6rem; padding:.08rem .38rem; border-radius:6px; background:var(--panel); color:var(--soft); font-weight:600; }
          .gd .qstate{ font-size:.7rem; color:var(--faint); }
          .gd .qstate.on{ color:var(--good); font-weight:700; }
          .gd .qtxt{ font-size:.74rem; color:var(--soft); margin:.5rem 0 .6rem; line-height:1.45; }
          .gd .qtxt b{ color:var(--ink); }
          .gd .qhint{ font-size:.66rem; color:var(--faint); margin:.35rem 0 .1rem; }
          .gd .qmap{ font-size:.7rem; color:var(--soft); margin:.35rem 0 .1rem; }
          .gd .qmap b{ color:var(--ink); }
          .gd .btnrow{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.45rem; }
          .gd .bp, .gd .bs{ display:inline-flex; align-items:center; border-radius:8px; padding:.36rem .7rem; font-size:.74rem; font-weight:600; transition:box-shadow .2s; }
          .gd .bp{ background:var(--nav); color:#fff; }
          .gd .bs{ border:1px solid var(--line); color:var(--ink); background:var(--surface); }
          .gd .xsoon{ border:1px dashed var(--line); border-radius:10px; padding:.45rem .8rem; max-width:30rem; margin-top:.6rem; font-size:.74rem; color:var(--soft); }
          .gd .xsoon i{ font-style:normal; color:var(--faint); margin-left:.35rem; font-size:.68rem; }

          /* which card state shows at which step */
          .gd .stNot, .gd .stOn, .gd .stDone{ display:none; }
          .gd .stage[data-step="0"] .stNot, .gd .stage[data-step="1"] .stNot{ display:block; }
          .gd .stage[data-step="4"] .stOn{ display:block; }
          .gd .stage[data-step="8"] .stDone{ display:block; }
          .gd .okb{ display:none; margin-bottom:.6rem; }
          .gd .stage[data-step="4"] .okb.k4, .gd .stage[data-step="8"] .okb.k8{ display:flex; }
          .gd .stage[data-step="1"] .connect, .gd .stage[data-step="4"] .setmap{ box-shadow:0 0 0 3px var(--accent-wash), 0 0 0 5px var(--accent); }

          /* ---- QuickBooks&rsquo; own site (drawn plainly — it is not our page) ---- */
          .gd .intu{ border:1px solid var(--line); border-radius:12px; background:var(--surface); max-width:21rem; margin:.3rem auto; padding:.9rem 1rem; text-align:center; }
          .gd .intu .who{ font-size:.62rem; text-transform:uppercase; letter-spacing:.06em; color:var(--faint); font-weight:700; }
          .gd .intu h4{ margin:.35rem 0 .6rem; font-size:.95rem; color:var(--ink); }
          .gd .intu .ibox{ border:1px solid var(--line); border-radius:7px; height:28px; margin:.35rem 0; display:flex; align-items:center; padding:0 .5rem; font-size:.72rem; color:var(--faint); text-align:left; }
          .gd .intu .ibtn{ margin-top:.55rem; border-radius:8px; padding:.4rem; font-size:.78rem; font-weight:700; background:#2ca01c; color:#fff; }
          .gd .intu .co{ border:1px solid var(--line); border-radius:8px; padding:.4rem .55rem; margin:.3rem 0; font-size:.74rem; text-align:left; color:var(--ink); display:flex; gap:.45rem; align-items:center; }
          .gd .intu .co .dot{ width:.8rem; height:.8rem; border-radius:50%; border:2px solid var(--line); flex:none; }
          .gd .intu .co.sel{ border-color:#2ca01c; }
          .gd .intu .co.sel .dot{ border-color:#2ca01c; background:#2ca01c; }
          .gd .intu .perm{ font-size:.66rem; color:var(--soft); text-align:left; margin:.5rem 0 0; line-height:1.45; }
          .gd .i2, .gd .i3{ display:none; }
          .gd .stage[data-step="2"] .i2{ display:block; }
          .gd .stage[data-step="3"] .i3{ display:block; }

          /* ---- the mapping page ---- */
          .gd .mhead{ font-weight:700; font-size:.95rem; color:var(--ink); }
          .gd .msub{ font-size:.7rem; color:var(--soft); margin:.15rem 0 .7rem; }
          .gd .mrow{ margin-bottom:.6rem; max-width:30rem; }
          .gd .mrow label{ display:block; font-size:.64rem; color:var(--faint); font-weight:600; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.2rem; }
          .gd .mrow label .req{ color:#b91c1c; }
          .gd .mrow .selectbox{ width:100%; font-size:.72rem; min-width:0; box-sizing:border-box; }
          .gd .mrow .mh{ font-size:.62rem; color:var(--faint); margin-top:.2rem; line-height:1.4; }
          .gd .sph{ color:var(--faint); } .gd .sval{ display:none; color:var(--ink); }
          .gd .stage[data-step="5"] .m1 .sph, .gd .stage[data-step="6"] .m1 .sph, .gd .stage[data-step="7"] .m1 .sph,
          .gd .stage[data-step="6"] .m2 .sph, .gd .stage[data-step="7"] .m2 .sph,
          .gd .stage[data-step="6"] .m3 .sph, .gd .stage[data-step="7"] .m3 .sph,
          .gd .stage[data-step="7"] .m4 .sph{ display:none; }
          .gd .stage[data-step="5"] .m1 .sval, .gd .stage[data-step="6"] .m1 .sval, .gd .stage[data-step="7"] .m1 .sval,
          .gd .stage[data-step="6"] .m2 .sval, .gd .stage[data-step="7"] .m2 .sval,
          .gd .stage[data-step="6"] .m3 .sval, .gd .stage[data-step="7"] .m3 .sval,
          .gd .stage[data-step="7"] .m4 .sval{ display:inline; }
          .gd .stage[data-step="5"] .m1, .gd .stage[data-step="6"] .m2, .gd .stage[data-step="6"] .m3,
          .gd .stage[data-step="7"] .m4{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .mok{ display:none; margin-bottom:.6rem; }
          .gd .stage[data-step="7"] .mok{ display:flex; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>www.yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- ============ SCENE A: Settings → Accounting ============ -->
                <div class="osc scTab">
                  <div class="okbanner okb k4"><span>&check;</span> Connected to QuickBooks Online (Beverley Blinds Ltd).</div>
                  <div class="okbanner okb k8"><span>&check;</span> QuickBooks mapping saved.</div>
                  <div class="tabstrip">
                    <span class="tabx">Company</span><span class="tabx">Quoting</span><span class="tabx">Legal</span>
                    <span class="tabx">Status colours</span><span class="tabx">Suppliers</span><span class="tabx on">Accounting</span>
                    <span class="tabx">Back up data</span>
                  </div>
                  <div class="card-t">Accounting integration</div>
                  <p class="bhint">Link your accounting package so paid sales flow straight through &mdash; no re-keying.
                     <b>Nothing is sent until an invoice is paid</b> (cash accounting).</p>

                  <div class="qcard">
                    <div class="qtop">
                      <div><span class="qname">QuickBooks Online</span><span class="qbadge">Live</span></div>
                      <span class="qstate stNot">Not connected</span>
                      <span class="qstate on stOn">&#9679; Connected</span>
                      <span class="qstate on stDone">&#9679; Connected</span>
                    </div>

                    <div class="stNot">
                      <div class="qtxt">Connect your QuickBooks company to get started. You&rsquo;ll sign in at QuickBooks and approve access.</div>
                      <span class="bp connect">Connect to QuickBooks</span>
                    </div>

                    <div class="stOn">
                      <div class="qtxt">Linked to <b>Beverley Blinds Ltd</b> &middot; since 26 Sep 2026</div>
                      <div class="qhint">Next: map your sales item, VAT code and bank account so paid sales post to the right place.</div>
                      <div class="btnrow"><span class="bp setmap">Set up mapping</span><span class="bs">Disconnect</span></div>
                    </div>

                    <div class="stDone">
                      <div class="qtxt">Linked to <b>Beverley Blinds Ltd</b> &middot; since 26 Sep 2026</div>
                      <div class="qmap">Mapping set: <b>Blinds</b> &middot; VAT <b>20.0% S</b> &middot; into <b>Business Current Account</b></div>
                      <div class="btnrow"><span class="bp">Edit mapping</span><span class="bs">Disconnect</span></div>
                    </div>
                  </div>
                  <div class="xsoon"><b>Xero &amp; Sage</b><i>Coming soon &mdash; same one-click connect.</i></div>
                </div>

                <!-- ============ SCENE B: QuickBooks&rsquo; own website ============ -->
                <div class="osc scIntu">
                  <div class="intu i2">
                    <div class="who">QuickBooks &middot; Intuit&rsquo;s own website</div>
                    <h4>Sign in</h4>
                    <div class="ibox">Email or user ID</div>
                    <div class="ibox">Password</div>
                    <div class="ibtn">Sign in</div>
                    <p class="perm">This is QuickBooks, not YourBlinds &mdash; your password goes to Intuit only.</p>
                  </div>
                  <div class="intu i3">
                    <div class="who">QuickBooks &middot; Intuit&rsquo;s own website</div>
                    <h4>Connect YourBlinds to&hellip;</h4>
                    <div class="co sel"><span class="dot"></span>Beverley Blinds Ltd</div>
                    <div class="co"><span class="dot"></span>Beverley Holdings (old company)</div>
                    <p class="perm">YourBlinds will be able to read your lists (items, VAT codes, accounts) and record sales.</p>
                    <div class="ibtn">Connect</div>
                  </div>
                </div>

                <!-- ============ SCENE C: the mapping page ============ -->
                <div class="osc scMap">
                  <div class="okbanner mok"><span>&check;</span> QuickBooks mapping saved.</div>
                  <div class="mhead">QuickBooks mapping</div>
                  <div class="msub">Tell YourBlinds where paid sales should post in <b>Beverley Blinds Ltd</b>.</div>
                  <div class="mrow"><label>How to record a paid sale</label>
                    <span class="selectbox m1"><span class="sph">Sales Receipt (income + payment in one &mdash; no open debtor)</span><span class="sval">Sales Receipt (income + payment in one &mdash; no open debtor)</span></span>
                    <div class="mh">You only put paid sales on QuickBooks (cash accounting), so a Sales Receipt is usually the cleanest.</div></div>
                  <div class="mrow"><label>Sales item <span class="req">*</span></label>
                    <span class="selectbox m2"><span class="sph">&mdash; Select an item &mdash;</span><span class="sval">Blinds</span></span>
                    <div class="mh">QuickBooks needs an item on each line &mdash; a single &ldquo;Blinds&rdquo; sales item is fine.</div></div>
                  <div class="mrow"><label>Default VAT / tax code</label>
                    <span class="selectbox m3"><span class="sph">&mdash; Select a tax code &mdash;</span><span class="sval">20.0% S</span></span></div>
                  <div class="mrow"><label>Bank account paid sales land in</label>
                    <span class="selectbox m4"><span class="sph">&mdash; Select an account &mdash;</span><span class="sval">Business Current Account</span></span>
                    <div class="mh">Usually your current account (or Undeposited Funds if payments arrive batched).</div></div>
                  <div class="save">Save mapping</div>
                </div>

                <div class="caps">
                  <b class="c0"><span class="n">0</span> Settings &rarr; Accounting &mdash; the QuickBooks Online card.</b>
                  <b class="c1"><span class="n">1</span> Press Connect to QuickBooks.</b>
                  <b class="c2"><span class="n">2</span> You&rsquo;re on QuickBooks&rsquo; site now &mdash; sign in there.</b>
                  <b class="c3"><span class="n">3</span> Pick your company, then Connect.</b>
                  <b class="c4 good"><span class="n">4</span> Back in YourBlinds: &#9679; Connected. Now Set up mapping.</b>
                  <b class="c5"><span class="n">5</span> How to record a paid sale &mdash; leave it on Sales Receipt.</b>
                  <b class="c6"><span class="n">6</span> Sales item &ldquo;Blinds&rdquo; and your standard VAT code.</b>
                  <b class="c7 good"><span class="n">7</span> The bank account &mdash; then Save mapping.</b>
                  <b class="c8 good"><span class="n">8</span> Done: the card shows your mapping.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <div class="heads"><span class="hi">&#9888;</span><div><b>Read this first &mdash; what the link does today.</b> Connecting and
             mapping <b>gets everything ready</b>. The part that actually <b>sends each paid job into QuickBooks is still being finished</b>,
             so for now <b>nothing is sent automatically</b>. Until it&rsquo;s switched on, keep getting your figures across with the
             <b>Export invoices (CSV)</b> and <b>Export payments (CSV)</b> buttons on the <b>Payments</b> page &mdash; the guide
             <b>&ldquo;Get your figures into Xero, QuickBooks or Sage&rdquo;</b> walks through it. We&rsquo;ll tell you when the automatic
             sending goes live; you won&rsquo;t need to connect again.</div></div>

          <p><b>Before you start &mdash; five minutes in QuickBooks.</b> Do these in QuickBooks Online first, so the lists YourBlinds shows
             you have the right things in them:</p>
          <ul class="steps">
            <li><b>Be a QuickBooks admin.</b> Only a user with admin rights on the QuickBooks company can approve the link. If you&rsquo;re
                not sure, ask whoever set QuickBooks up (often your bookkeeper or accountant).</li>
            <li><b>Make one &ldquo;Blinds&rdquo; sales item.</b> In QuickBooks, open <b>Products and services</b> and add a new
                <b>Service</b> called <code>Blinds</code>, pointing at your <b>sales / income</b> account. QuickBooks insists every line of a
                sale has an item; one item for everything is plenty &mdash; the detail of each blind still goes across in the description.</li>
            <li><b>Make sure VAT is switched on</b> in QuickBooks (if you&rsquo;re VAT registered), so the <code>20.0% S</code> standard-rate
                code exists. If you&rsquo;re on the <b>VAT Cash Accounting Scheme</b>, that&rsquo;s a setting in QuickBooks&rsquo; own VAT
                settings &mdash; check it with your accountant; YourBlinds follows whatever QuickBooks is set to.</li>
            <li><b>Know which bank account the money lands in</b> &mdash; normally your business current account as it&rsquo;s named in
                QuickBooks.</li>
          </ul>

          <p><b>Connecting &mdash; step by step.</b></p>
          <ul class="steps">
            <li>Go to <b>Setup &rarr; Settings</b> and click the <b>Accounting</b> tab &mdash; the <b>sixth</b> tab along, between
                <b>Suppliers</b> and <b>Back up data</b>. You&rsquo;ll see a card headed <b>QuickBooks Online</b>.</li>
            <li>Look at the little grey badge beside the name. <b>Live</b> means you can connect your real company.
                <b>Sandbox (test)</b> means the link is still being tested &mdash; your real company won&rsquo;t connect yet, so leave it
                for now. If the card says <b>Not set up</b> instead of a button, the link hasn&rsquo;t been switched on for your account
                &mdash; ask us through <b>? Help</b>.</li>
            <li>Press <b>Connect to QuickBooks</b>. You leave YourBlinds and land on <b>QuickBooks&rsquo; own sign-in page</b> (the
                address bar will show an intuit.com address). Sign in with your normal QuickBooks details. <b>YourBlinds never sees
                that password</b>.</li>
            <li>If you have more than one QuickBooks company, <b>pick the right one</b> &mdash; the business these blinds are sold by.
                QuickBooks shows what YourBlinds will be allowed to do; press <b>Connect</b> to approve.</li>
            <li>You&rsquo;re sent straight back to the Accounting tab with a green bar &mdash;
                <b>&ldquo;Connected to QuickBooks Online (your company name).&rdquo;</b> &mdash; and the card now reads
                <b>&#9679; Connected</b>, <b>Linked to</b> your company, with the date. Underneath it says
                <em>&ldquo;Next: map your sales item, VAT code and bank account&hellip;&rdquo;</em></li>
          </ul>

          <p><b>Mapping &mdash; the four questions.</b> Press <b>Set up mapping</b>. The page is headed <b>QuickBooks mapping</b> and every
             dropdown is filled <b>live from your own QuickBooks</b>, so you&rsquo;ll see your own items and accounts:</p>
          <ul class="steps">
            <li><b>How to record a paid sale.</b> Leave it on <b>Sales Receipt</b> unless your accountant says otherwise. A sales receipt
                records the sale and the money received in one go, which is what cash accounting wants &mdash; nothing sits in QuickBooks as
                &ldquo;owed&rdquo;. The other choice, <b>Invoice + Payment</b>, creates an invoice and pays it off the same day, if you like
                to see a customer&rsquo;s invoice history in QuickBooks.</li>
            <li><b>Sales item</b> (the only one with a red star &mdash; it&rsquo;s required). Pick the <b>Blinds</b> item you made.</li>
            <li><b>Default VAT / tax code.</b> Pick your standard-rate code, usually <code>20.0% S</code>. Not VAT registered? Pick your
                &ldquo;No VAT&rdquo; / exempt code.</li>
            <li><b>Bank account paid sales land in.</b> Your business current account. Only pick <b>Undeposited Funds</b> if customers&rsquo;
                payments reach your bank <em>lumped together</em> (a card machine paying out once a day, for example) &mdash; then you group
                them in QuickBooks to match the single bank line.</li>
            <li>Press <b>Save mapping</b>. The green bar <b>&ldquo;QuickBooks mapping saved.&rdquo;</b> appears at the top of the page. Press
                <b>&larr; Back to Accounting</b> and the card now shows <b>Mapping set: Blinds &middot; VAT 20.0% S &middot; into &hellip;</b>
                and the button has become <b>Edit mapping</b>.</li>
          </ul>

          <p><b>What will happen once sending is switched on.</b> When a job is <b>fully paid</b> in YourBlinds, it goes to QuickBooks as a
             sales receipt using the item, VAT code and bank account you chose &mdash; so it sits ready to match against the payment in your
             QuickBooks bank feed. Jobs that aren&rsquo;t paid are never sent. You won&rsquo;t need to do anything extra.</p>

          <p><b>If something goes wrong.</b></p>
          <ul class="steps">
            <li><b>&ldquo;Security check failed on the accounting connection (state mismatch)&rdquo;</b> &mdash; almost always because the
                connection started on one web address and finished on another. Close the QuickBooks tab, check the address bar says
                <code>www.yourblinds.uk</code>, go back to <b>Settings &rarr; Accounting</b> and press Connect again.</li>
            <li><b>&ldquo;The connection took too long and expired&rdquo;</b> &mdash; you have 15 minutes from pressing Connect. Just start again.</li>
            <li><b>&ldquo;Connection cancelled or refused by the provider&rdquo;</b> &mdash; you pressed Cancel on QuickBooks&rsquo; page, or
                you&rsquo;re not an admin on that QuickBooks company.</li>
            <li><b>&ldquo;Couldn&rsquo;t load lists from QuickBooks&rdquo;</b> on the mapping page &mdash; QuickBooks didn&rsquo;t answer.
                Try again in a minute; if it keeps happening, <b>Disconnect</b> and connect again.</li>
            <li><b>The Blinds item or VAT code isn&rsquo;t in the list</b> &mdash; it doesn&rsquo;t exist in QuickBooks yet. Make it there
                (see &ldquo;Before you start&rdquo;), then reopen the mapping page; the lists are fetched fresh each time.</li>
          </ul>

          <p><b>Disconnecting.</b> On the Accounting tab press <b>Disconnect</b> and confirm. You&rsquo;ll see
             <b>&ldquo;Disconnected from QuickBooks Online.&rdquo;</b> Nothing already in QuickBooks is touched. You can also remove the link
             from inside QuickBooks (its list of connected apps) &mdash; either way, connect again any time with the same button.</p>

          <p><b>Xero, Sage or FreeAgent?</b> The Accounting tab shows <b>Xero &amp; Sage &mdash; Coming soon</b>. They&rsquo;ll work the same
             way &mdash; sign in on their site, then map your sales account, VAT rate and bank account. Until then, use the CSV exports on the
             Payments page; the guide <b>&ldquo;Get your figures into Xero, QuickBooks or Sage&rdquo;</b> has the steps for each package.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Settings → Accounting; the QuickBooks Online card reads "Not connected".', 'Linking QuickBooks lives in Settings, on the Accounting tab — the sixth tab along. You\'ll see a card for QuickBooks Online. The little badge should say Live; if it says Sandbox, the link is still being tested, so leave it for now.', 0],
            ['0:13', 'Connect to QuickBooks highlighted.',                                        'Before you press anything, make sure you\'re an admin on your QuickBooks company, and that QuickBooks has a Blinds sales item and your VAT codes set up. Then press Connect to QuickBooks.', 1],
            ['0:25', 'Scene: QuickBooks\' own sign-in page.',                                      'You\'re now on QuickBooks\' own website, not ours. Sign in with your usual QuickBooks details. That password goes to QuickBooks only — YourBlinds never sees it.', 2],
            ['0:36', 'Pick the company; Connect.',                                                 'If you run more than one company, choose the right one, then press Connect to approve the link.', 3],
            ['0:44', 'Back in YourBlinds: green bar, "Connected". Set up mapping highlighted.',     'You\'re sent straight back, with a green bar saying you\'re connected, and the card now reads Connected, linked to your company. Next, press Set up mapping.', 4],
            ['0:55', 'Mapping page: How to record a paid sale = Sales Receipt.',                  'The mapping page asks four questions, and every list comes from your own QuickBooks. First, how to record a paid sale. Leave it on Sales Receipt — money in and the sale together, which is exactly what cash accounting wants.', 5],
            ['1:08', 'Sales item "Blinds"; VAT code "20.0% S".',                                   'Second, the sales item. Choose Blinds. Third, your VAT code — normally twenty percent standard rated. If you\'re not VAT registered, choose your no-VAT code.', 6],
            ['1:19', 'Bank account chosen; Save mapping; green bar.',                              'Last, the bank account paid sales land in — your business current account. Press Save mapping, and look for the green bar at the top.', 7],
            ['1:28', 'Accounting tab shows "Mapping set: Blinds · VAT 20.0% S · into Business Current Account".', 'That\'s it. The card shows your mapping, and the button now says Edit mapping. One thing to know: the automatic sending of paid jobs is still being switched on. Until then, keep using the CSV exports on the Payments page — there\'s a separate guide for that.', 8],
        ],
];
