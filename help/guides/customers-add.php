<?php
declare(strict_types=1);

/**
 * Guide: customers-add
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Customers',
        'title'   => 'Adding & managing customers',
        'eyebrow' => 'Customers',
        'blurb'   => 'Your address book: add a customer, handle the "same name" check, and read their record — recent quotes, colour-coded by status.',
        'lede'    => 'The <b>Customers</b> page is your address book &mdash; everyone you quote lives here. Add one (only the <b>name</b> is
                      required), and the same page becomes their <b>record</b>: their recent quotes, colour-coded by status. Pick them on a
                      <b>New quote</b> and their address fills itself in.',
        'open'    => '/customer-manager/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.78rem; margin:0 0 .6rem; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scList, .gd .stage[data-step="1"] .scList{ display:block; }
          .gd .stage[data-step="2"] .scForm, .gd .stage[data-step="3"] .scForm, .gd .stage[data-step="4"] .scForm{ display:block; }
          .gd .stage[data-step="5"] .scDetail{ display:block; }
          .gd .stage[data-step="6"] .scQuote{ display:block; }

          /* list */
          .gd .chead{ display:flex; align-items:center; gap:.6rem; margin-bottom:.55rem; }
          .gd .chead .h{ font-weight:700; color:var(--ink); font-size:.92rem; }
          .gd .addcust{ margin-left:auto; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.34rem .7rem; font-size:.76rem; font-weight:700; }
          .gd .stage[data-step="1"] .addcust{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .csearch{ display:flex; align-items:center; border:1px solid var(--line); border-radius:8px; padding:.34rem .55rem; font-size:.74rem; color:var(--faint); background:var(--surface); max-width:24rem; margin-bottom:.6rem; }
          .gd .csearch::before{ content:"\1F50D"; margin-right:.4rem; font-size:.72rem; }
          .gd .ctbl{ width:100%; border-collapse:collapse; font-size:.72rem; }
          .gd .ctbl th{ text-align:left; font-size:.6rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.3rem .45rem; }
          .gd .ctbl td{ padding:.38rem .45rem; border-bottom:1px solid var(--line); color:var(--ink); }
          .gd .ctbl td b{ color:var(--ink); }
          .gd .ctbl a{ color:var(--accent); font-weight:600; }

          /* form */
          .gd .cols3{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:.7rem .8rem; }
          .gd .waline{ display:inline-flex; align-items:center; gap:.4rem; font-size:.72rem; color:var(--soft); margin-top:.35rem; }
          .gd .savec{ margin-top:.85rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.42rem .85rem; font-size:.8rem; font-weight:700; transition:transform .1s, filter .1s; }
          .gd .stage[data-step="2"] .savec, .gd .stage[data-step="3"] .savec{ } /* Save customer */
          .gd .savelbl2{ display:none; }
          .gd .stage[data-step="4"] .savelbl{ display:none; }
          .gd .stage[data-step="4"] .savelbl2{ display:inline; }
          .gd .stage[data-step="4"] .savec{ background:#b45309; }
          .gd .dupwarn{ display:none; margin:.2rem 0 .6rem; border:1px solid #e6b64c; background:rgba(230,182,76,.14); border-radius:9px; padding:.5rem .65rem; font-size:.72rem; color:var(--soft); }
          .gd .stage[data-step="4"] .dupwarn{ display:block; }
          .gd .dupwarn b{ color:var(--ink); }

          /* detail / recent quotes */
          .gd .rqh{ font-size:.72rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.03em; margin:.2rem 0 .45rem; }
          .gd .bdg{ display:inline-block; font-size:.58rem; font-weight:700; border-radius:20px; padding:.08rem .5rem; text-transform:capitalize; }
          .gd .bdg.draft{ background:var(--line); color:var(--soft); }
          .gd .bdg.sent{ background:#dbeafe; color:#1e40af; }
          .gd .bdg.accepted{ background:#dcfce7; color:#166534; }
          .gd .bdg.ordered{ background:#fef3c7; color:#92400e; }

          .gd .t-wa{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .boxv{ border:1px solid var(--line); border-radius:7px; background:var(--panel); color:var(--ink); }

          /* quote picker */
          .gd .qnote{ font-size:.7rem; color:var(--faint); margin-top:.5rem; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / customers</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a class="on">Customers</a><a>Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- Scene: list -->
                <div class="osc scList">
                  <div class="chead"><span class="h">Customers</span><span class="addcust">+ Add customer</span></div>
                  <div class="csearch">Search by name, email, phone, town or postcode&hellip;</div>
                  <table class="ctbl">
                    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Town</th><th>Postcode</th><th>Quotes</th><th></th></tr></thead>
                    <tbody>
                      <tr><td><b>Angela Reed</b></td><td>angela@&hellip;</td><td>07700 900112</td><td>Kenilworth</td><td>CV8 1AA</td><td>3</td><td><a>Edit</a></td></tr>
                      <tr><td><b>David Cole</b></td><td>d.cole@&hellip;</td><td>07700 900431</td><td>Coventry</td><td>CV5 6RT</td><td>1</td><td><a>Edit</a></td></tr>
                    </tbody>
                  </table>
                </div>

                <!-- Scene: add form -->
                <div class="osc scForm">
                  <div class="card-t">Add customer</div>
                  <div class="fld"><label>Name <span style="color:#b91c1c">*</span></label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">Emma Fletcher</span></div></div>
                  <div class="frow" style="margin-top:.5rem">
                    <div class="fld"><label>Email</label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">emma.fletcher@gmail.com</span></div></div>
                    <div class="fld"><label>Phone</label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">07700 900318</span></div>
                      <span class="waline"><span class="tick t-wa">&check;</span> Customer has WhatsApp on this number</span></div>
                  </div>
                  <div class="dupwarn"><b>A customer with this name already exists.</b> Pick the existing customer below, or &mdash; if this really is a different person with the same name &mdash; click <b>Save anyway</b>.</div>
                  <div class="fld" style="margin-top:.5rem"><label>Address line 1</label><div class="box f3"><span class="ph">&nbsp;</span><span class="val">14 Willow Drive</span></div></div>
                  <div class="cols3" style="margin-top:.5rem">
                    <div class="fld"><label>Town</label><div class="box f3"><span class="ph">&nbsp;</span><span class="val">Leamington Spa</span></div></div>
                    <div class="fld"><label>County</label><div class="box f3"><span class="ph">&nbsp;</span><span class="val">Warwickshire</span></div></div>
                    <div class="fld"><label>Postcode</label><div class="box f3"><span class="ph">&nbsp;</span><span class="val">CV32 5PJ</span></div></div>
                  </div>
                  <div class="fld" style="margin-top:.5rem"><label>Notes</label><div class="ta f3"><span class="ph">&nbsp;</span><span class="val">Prefers afternoon fittings. Dog in the garden.</span></div></div>
                  <div class="savec"><span class="savelbl">Save customer</span><span class="savelbl2">Save anyway (it really is a different person)</span></div>
                </div>

                <!-- Scene: detail / recent quotes -->
                <div class="osc scDetail">
                  <div class="card-t">Emma Fletcher</div>
                  <p class="ldesc2">&larr; Back to customers &nbsp;&middot;&nbsp; this page is also her record.</p>
                  <div class="rqh">Recent quotes</div>
                  <table class="ctbl">
                    <thead><tr><th>Quote #</th><th>Status</th><th>Total</th><th>Created</th><th></th></tr></thead>
                    <tbody>
                      <tr><td><b>Q-1042</b></td><td><span class="bdg accepted">accepted</span></td><td>&pound;486.00</td><td>2 Aug 2026</td><td><a>Open</a></td></tr>
                      <tr><td><b>Q-1039</b></td><td><span class="bdg sent">sent</span></td><td>&pound;302.50</td><td>28 Jul 2026</td><td><a>Open</a></td></tr>
                      <tr><td><b>Q-1031</b></td><td><span class="bdg ordered">ordered</span></td><td>&pound;915.00</td><td>19 Jul 2026</td><td><a>Open</a></td></tr>
                    </tbody>
                  </table>
                </div>

                <!-- Scene: new quote picker -->
                <div class="osc scQuote">
                  <div class="card-t">New quote</div>
                  <div class="fld"><label>Existing customer</label><div class="selectbox">Emma Fletcher &mdash; Leamington Spa &middot; CV32 5PJ</div></div>
                  <div class="fld" style="margin-top:.5rem"><label>Address (auto-filled)</label><div class="boxv" style="height:auto;padding:.4rem .5rem;line-height:1.4">14 Willow Drive, Leamington Spa, Warwickshire, CV32 5PJ</div></div>
                  <p class="qnote">Picking a customer fills their address in for you &mdash; no re-typing.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Customers &mdash; your address book. + Add customer.</b>
                  <b class="c2"><span class="n">2</span> Name is the only must &mdash; add email, phone, WhatsApp.</b>
                  <b class="c3"><span class="n">3</span> Address for the fitting + any notes (optional).</b>
                  <b class="c4"><span class="n">4</span> Same name exists? Pick them, or Save anyway.</b>
                  <b class="c5"><span class="n">5</span> Their record: recent quotes, colour-coded.</b>
                  <b class="c6 good"><span class="n">6</span> New quote &rarr; pick them &rarr; address auto-fills.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Customers</b> page (in the sidebar) is your address book &mdash; every end-customer you quote. It opens as a searchable
             <b>list</b>; up top are <b>+ Add customer</b> and (for admins) <b>Find duplicates</b>.</p>
          <ul class="steps">
            <li><b>Find someone fast.</b> The search box matches <b>name, email, phone, town or postcode</b> &mdash; type any part and hit
                Search. The list shows Name, Email, Phone, Town, Postcode and their <b>Quotes</b> count; <b>Edit</b> opens the record.</li>
            <li><b>Add a customer.</b> Only <b>Name</b> is required (the red *). Add <b>Email</b> and <b>Phone</b> &mdash; tick
                <b>&ldquo;Customer has WhatsApp on this number&rdquo;</b> if it does &mdash; then the <b>address</b> (line 1 &amp; 2, Town,
                County, Postcode) and any <b>Notes</b>. There&rsquo;s <b>one address per customer</b> (no separate billing/site address here),
                and everything except the name is optional &mdash; but the more you add now, the less you re-type on every quote. Click
                <b>Save customer</b>.</li>
            <li><b>The &ldquo;same name&rdquo; check.</b> If a customer with that name already exists you&rsquo;ll see
                <b>&ldquo;A customer with this name already exists&rdquo;</b> with the matches listed &mdash; so you don&rsquo;t create a
                duplicate by accident. Either <b>pick the existing person</b>, or, if it genuinely is a different customer who happens to share
                the name, the button becomes <b>&ldquo;Save anyway (it really is a different person)&rdquo;</b>. (The check is by <em>name</em>
                only &mdash; two people can share a phone or email.)</li>
            <li><b>The record.</b> Saving opens that customer&rsquo;s page &mdash; the <b>same screen you edit on</b>. Below the details is
                <b>Recent quotes</b> (their last five): Quote #, <b>Status</b>, Total, Created and <b>Open</b>. The status is a
                <b>colour-coded badge</b> &mdash; the very colours you set in <em>Settings &rarr; Status colours</em> (draft, sent, accepted,
                ordered&hellip;) &mdash; so you can see at a glance where each job stands.</li>
          </ul>
          <p><b>Starting a quote for them:</b> you don&rsquo;t do it from here &mdash; open <b>New quote</b> and, in the <b>Existing customer</b>
             box, start typing their name (or town/postcode). Pick them and <b>their address fills in automatically</b>. Brand-new customer?
             Just type the name straight into that box and flesh out the rest later.</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Deleting a customer is permanent</b> &mdash; you&rsquo;ll be asked
             &ldquo;Delete &lt;name&gt;? This cannot be undone.&rdquo; Their <b>existing quotes are kept</b> but are <b>no longer linked</b> to a
             customer record, so only delete a genuine mistake, not someone with history. Two records for the same person? Admins can use
             <b>Find duplicates</b> to merge them &mdash; the oldest is kept and all quotes/appointments re-point to it.</div></div>
          <p><b>Heads-up on emails:</b> if you type an email it must be valid &mdash; a malformed one gives
             <em>&ldquo;Please enter a valid email address.&rdquo;</em> Leave it blank if you don&rsquo;t have one. (There&rsquo;s no bulk
             customer import &mdash; customers are added here or created inline on a quote.)</p>',
        'script'  => [
            ['0:00', 'Customers list; + Add customer.',   'Customers is your address book — everyone you quote lives here. Hit Add customer to start a new one, or search to find an existing one.', 1],
            ['0:08', 'Name, email, phone, WhatsApp.',      'Only the name is required. Add their email and phone — tick WhatsApp if that number takes it — so you can reach them later.', 2],
            ['0:16', 'Address and notes filled in.',       'Then their address for the fitting, and any notes. None of it is compulsory, but the more you put in now, the less you type on every quote.', 3],
            ['0:25', 'Same-name warning; Save anyway.',    'Save. If the name already exists it stops you — pick the existing person, or, if it really is a different customer with the same name, click Save anyway.', 4],
            ['0:35', 'Their record; recent quotes.',       'Saved. This same page is their record — their recent quotes, colour-coded by status, so you can see at a glance what is drafted, sent, accepted or ordered.', 5],
            ['0:45', 'New quote auto-fills address.',      'And that is the payoff: when you start a New quote, pick them from the customer box and their address fills itself in.', 6],
        ],
];
