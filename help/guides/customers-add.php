<?php
declare(strict_types=1);

/**
 * Guide: customers-add
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers the whole Customers area — the list (/customer-manager/index.php),
 * Add (/customer-manager/new.php) with its soft same-name check, the record
 * page (/customer-manager/edit.php) with Recent quotes and the Danger zone,
 * Find duplicates (/customer-manager/dedupe.php), and the two screens that
 * silently write into the address book (a New quote, and a calendar booking).
 * Also draws the retail/trade line: Customers is the RETAIL address book;
 * trade businesses are accounts under Trade -> Trade accounts.
 */

return [
        'aud'     => 'admin',
        'section' => 'Customers',
        'title'   => 'Adding & managing customers',
        'eyebrow' => 'Customers',
        'blurb'   => 'Your retail address book, end to end: every box on the form, the same-name check, the record page and its danger zone, merging duplicates, and why trade accounts are not in here.',
        'lede'    => '<b>Customers</b> is your <b>retail address book</b> &mdash; the end-customers your company quotes and fits. It sits under
                      <b>Retail</b> in the sidebar, and the page says so at the top: &ldquo;End-customers belonging to <i>your company</i>.&rdquo;
                      Trade businesses you supply are <b>not</b> in here &mdash; they are accounts under <b>Trade &rarr; Trade accounts</b>.
                      Only the <b>Name</b> is required; everything else is you saving yourself typing later. Here is the whole area, box by box.',
        'open'    => '/customer-manager/index.php',
        'css'     => '
          /* ---------- sidebar drawn in the real "two worlds" shape ---------- */
          .gd .navh{ font-size:.56rem; letter-spacing:.12em; text-transform:uppercase; color:#6a7d8c; font-weight:700; margin:.6rem 0 .12rem; padding:0 .5rem; }
          .gd .app:has(.stage[data-step="1"]) .navretail,
          .gd .app:has(.stage[data-step="1"]) .side a.on{ box-shadow:0 0 0 2px #5b9bff; border-radius:7px; }
          .gd .app:has(.stage[data-step="8"]) .navtrade{ box-shadow:0 0 0 2px #5b9bff; border-radius:7px; }

          /* ---------- shared page chrome ---------- */
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scList, .gd .stage[data-step="1"] .scList{ display:block; }
          .gd .stage[data-step="2"] .scForm, .gd .stage[data-step="3"] .scForm,
          .gd .stage[data-step="4"] .scForm, .gd .stage[data-step="5"] .scForm{ display:block; }
          .gd .stage[data-step="6"] .scRecord{ display:block; }
          .gd .stage[data-step="7"] .scDedupe{ display:block; }
          .gd .stage[data-step="8"] .scNew{ display:block; }

          .gd .pghead{ display:flex; align-items:flex-start; gap:.6rem; margin-bottom:.55rem; }
          .gd .pgh{ font-size:.98rem; font-weight:800; color:var(--ink); line-height:1.2; }
          .gd .pgs{ font-size:.66rem; color:var(--faint); margin:.12rem 0 0; }
          .gd .pgs a{ color:var(--accent); }
          .gd .hdbtns{ margin-left:auto; display:flex; gap:.35rem; align-items:center; flex-wrap:wrap; }
          .gd .btnp{ display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.3rem .65rem; font-size:.72rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; background:var(--surface); color:var(--soft); border:1px solid var(--border-strong,#c7ccd4); border-radius:8px; padding:.29rem .62rem; font-size:.72rem; font-weight:600; white-space:nowrap; }
          .gd .btnd{ display:inline-flex; background:#b91c1c; color:#fff; border-radius:8px; padding:.3rem .65rem; font-size:.72rem; font-weight:700; }
          .gd .stage[data-step="1"] .ringadd{ box-shadow:0 0 0 3px var(--accent-wash); }

          /* ---------- list scene ---------- */
          .gd .srch{ display:flex; align-items:center; gap:.35rem; margin:.15rem 0 .6rem; }
          .gd .srchbox{ flex:1; max-width:20rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; padding:.3rem .5rem; font-size:.7rem; color:var(--faint); background:var(--surface); }
          .gd .ctbl{ width:100%; border-collapse:collapse; font-size:.68rem; }
          .gd .ctbl th{ text-align:left; font-size:.57rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.26rem .4rem; }
          .gd .ctbl td{ padding:.32rem .4rem; border-bottom:1px solid var(--line); color:var(--ink); }
          .gd .ctbl th.num, .gd .ctbl td.num{ text-align:right; }
          .gd .ctbl a{ color:var(--accent); font-weight:600; }
          .gd .foundline{ font-size:.66rem; color:var(--faint); margin:0 0 .5rem; }
          .gd .foundline b{ color:var(--ink); }

          /* ---------- the form (Add / record) ---------- */
          .gd .colsepm{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:.55rem .7rem; margin-top:.5rem; align-items:start; }
          .gd .cols3{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:.55rem .7rem; margin-top:.5rem; }
          .gd .fld.full{ margin-top:.5rem; }
          .gd .lsoft{ color:var(--faint); font-weight:400; text-transform:none; letter-spacing:0; }
          .gd .waline{ display:inline-flex; align-items:center; gap:.38rem; font-size:.66rem; color:var(--soft); margin-top:.35rem; }
          .gd .stage[data-step="2"] .t-wa, .gd .stage[data-step="3"] .t-wa,
          .gd .stage[data-step="4"] .t-wa, .gd .stage[data-step="5"] .t-wa,
          .gd .stage[data-step="6"] .t-wa{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .facts{ margin-top:.75rem; display:flex; gap:.45rem; align-items:center; }

          /* the duplicate banner + its list of matches (step 5 only) */
          .gd .dupwrap{ display:none; margin:.1rem 0 .55rem; }
          .gd .stage[data-step="5"] .dupwrap{ display:block; }
          .gd .dupwarn{ border:1px solid #fde047; background:#fef3c7; border-radius:9px; padding:.45rem .6rem; font-size:.68rem; color:#78350f; line-height:1.45; }
          .gd .dupwarn b{ color:#78350f; }
          .gd .dupl{ margin:.4rem 0 0; padding-left:1rem; font-size:.66rem; color:#92400e; line-height:1.6; }
          .gd .dupl a{ color:#78350f; font-weight:700; text-decoration:underline; }
          .gd .savelbl2{ display:none; }
          .gd .stage[data-step="5"] .savelbl{ display:none; }
          .gd .stage[data-step="5"] .savelbl2{ display:inline; }
          .gd .stage[data-step="5"] .btnsave{ background:#b45309; }

          /* ---------- record scene ---------- */
          .gd .boxv{ height:26px; border:1px solid var(--line); border-radius:7px; background:var(--panel); display:flex; align-items:center; padding:0 .5rem; font-size:.72rem; color:var(--ink); overflow:hidden; }
          .gd .tav{ min-height:30px; border:1px solid var(--line); border-radius:7px; background:var(--panel); padding:.3rem .5rem; font-size:.7rem; color:var(--ink); line-height:1.35; }
          .gd .rqh{ font-size:.66rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; margin:.85rem 0 .35rem; }
          .gd .bdg{ display:inline-block; font-size:.56rem; font-weight:700; border-radius:20px; padding:.06rem .48rem; text-transform:capitalize; }
          .gd .bdg.draft{ background:#f3f4f6; color:#4b5563; }
          .gd .bdg.sent{ background:#eff6ff; color:#1e40af; }
          .gd .bdg.accepted{ background:#f0fdf4; color:#166534; }
          .gd .bdg.ordered{ background:#fefce8; color:#854d0e; }
          .gd .dzh{ font-size:.75rem; font-weight:800; color:#b91c1c; margin:.85rem 0 .25rem; }
          .gd .dzp{ font-size:.64rem; color:var(--faint); margin:0 0 .4rem; line-height:1.45; max-width:34rem; }
          .gd .cfmock{ position:absolute; right:.9rem; bottom:2.9rem; width:15.5rem; background:var(--surface); border:1px solid var(--line); border-radius:11px; box-shadow:0 14px 34px -12px rgba(20,30,45,.45); padding:.6rem .7rem; }
          .gd .cfmsg{ font-size:.72rem; color:var(--ink); line-height:1.4; margin-bottom:.55rem; }
          .gd .cfbtns{ display:flex; gap:.35rem; justify-content:flex-end; }

          /* ---------- dedupe scene ---------- */
          .gd .bluep{ background:#f0f9ff; border:1px solid #bae6fd; border-radius:9px; padding:.5rem .65rem; font-size:.66rem; color:#0c4a6e; line-height:1.5; margin-bottom:.6rem; }
          .gd .bluep b{ color:#0c4a6e; }
          .gd .gcard{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; }
          .gd .gcardh{ font-size:.75rem; font-weight:700; color:var(--ink); margin-bottom:.35rem; }
          .gd .gcardh span{ color:var(--faint); font-weight:600; font-size:.66rem; }
          .gd .ctbl tr.keeprow td{ background:#ecfdf5; }
          .gd .kpill{ display:inline-block; padding:.02rem .42rem; background:#16a34a; color:#fff; border-radius:999px; font-size:.54rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; margin-left:.25rem; }
          .gd .gacts{ margin-top:.5rem; text-align:right; }

          /* ---------- new-launcher / new-quote scene ---------- */
          .gd .twocol{ display:grid; grid-template-columns:1fr 1fr; gap:.9rem; align-items:start; }
          .gd .halfh{ font-size:.66rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; margin:0 0 .35rem; }
          .gd .seg{ display:inline-flex; border:1px solid var(--border-strong,#c7ccd4); border-radius:9px; overflow:hidden; }
          .gd .seg span{ padding:.3rem .85rem; font-size:.73rem; font-weight:600; background:var(--panel); color:var(--soft); }
          .gd .seg span + span{ border-left:1px solid var(--border-strong,#c7ccd4); }
          .gd .seg span.on{ background:#2563eb; color:#fff; }
          .gd .segnote{ font-size:.65rem; color:var(--faint); margin:.45rem 0 0; line-height:1.5; }
          .gd .sugg{ border:1px solid var(--line); border-top:none; border-radius:0 0 7px 7px; background:var(--surface); font-size:.68rem; color:var(--ink); padding:.26rem .5rem; }
          .gd .sugghint{ font-size:.62rem; color:var(--faint); margin-top:.25rem; }
          @media(max-width:620px){ .gd .colsepm, .gd .cols3, .gd .twocol{ grid-template-columns:1fr; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / customer-manager</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh navretail">Retail</div>
                <a class="on">Customers</a><a>Quotes</a><a>Orders</a><a>Payments</a>
                <div class="navh navtrade">Trade</div>
                <a>Trade accounts</a>
                <div class="navh">Setup</div>
                <a>Products</a><a>Users</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- ============ Scene 1: the list ============ -->
                <div class="osc scList">
                  <div class="pghead">
                    <div>
                      <div class="pgh">Customers</div>
                      <p class="pgs">End-customers belonging to Bev Blinds Ltd.</p>
                    </div>
                    <div class="hdbtns">
                      <span class="btns">Find duplicates</span>
                      <span class="btnp ringadd">+ Add customer</span>
                    </div>
                  </div>
                  <div class="srch">
                    <span class="srchbox">Search by name, email, phone, town or postcode&hellip;</span>
                    <span class="btns">Search</span>
                  </div>
                  <table class="ctbl">
                    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Town</th><th>Postcode</th><th class="num">Quotes</th><th></th></tr></thead>
                    <tbody>
                      <tr><td><b>Angela Reed</b></td><td>angela.reed@gmail.com</td><td>01926 555112</td><td>Kenilworth</td><td>CV8 1AA</td><td class="num">3</td><td><a>Edit</a></td></tr>
                      <tr><td><b>David Cole</b></td><td>d.cole@outlook.com</td><td>024 7655 0431</td><td>Coventry</td><td>CV5 6RT</td><td class="num">1</td><td><a>Edit</a></td></tr>
                      <tr><td><b>Emma Fletcher</b></td><td>emma.f@icloud.com</td><td>&mdash;</td><td>Kenilworth</td><td>CV8 1AA</td><td class="num">0</td><td><a>Edit</a></td></tr>
                    </tbody>
                  </table>
                </div>

                <!-- ============ Scenes 2&ndash;5: Add customer ============ -->
                <div class="osc scForm">
                  <div class="pghead">
                    <div>
                      <div class="pgh">Add customer</div>
                      <p class="pgs"><a>&larr; Back to customers</a></p>
                    </div>
                  </div>

                  <div class="dupwrap">
                    <div class="dupwarn">
                      <b>A customer with this name already exists.</b><br>
                      Pick the existing customer below, or scroll down and click <b>Save anyway</b> if this really is a different person with the same name.
                      <ul class="dupl">
                        <li><a>Emma Fletcher</a> &mdash; Kenilworth &middot; CV8 1AA &middot; emma.f@icloud.com &middot; 01926 555771</li>
                      </ul>
                    </div>
                  </div>

                  <div class="fld"><label>Name <span class="req">*</span></label>
                    <div class="box f2"><span class="ph">&nbsp;</span><span class="val">Emma Fletcher</span></div></div>

                  <div class="colsepm">
                    <div class="fld"><label>Email</label>
                      <div class="box f2"><span class="ph">&nbsp;</span><span class="val">emma.fletcher@gmail.com</span></div></div>
                    <div class="fld"><label>Phone <span class="lsoft">(landline)</span></label>
                      <div class="box f2"><span class="ph">&nbsp;</span><span class="val">01926 555104</span></div></div>
                    <div class="fld"><label>Mobile</label>
                      <div class="box f2"><span class="ph">&nbsp;</span><span class="val">07700 900318</span></div>
                      <span class="waline"><span class="tick t-wa">&check;</span> Mobile is on WhatsApp</span></div>
                  </div>

                  <div class="fld full"><label>Address line 1</label>
                    <div class="box f3"><span class="ph">&nbsp;</span><span class="val">14 Willow Drive</span></div></div>
                  <div class="fld full"><label>Address line 2</label>
                    <div class="box f3"><span class="ph">&nbsp;</span><span class="val">Cubbington</span></div></div>

                  <div class="cols3">
                    <div class="fld"><label>Town</label><div class="box f3"><span class="ph">&nbsp;</span><span class="val">Leamington Spa</span></div></div>
                    <div class="fld"><label>County</label><div class="box f3"><span class="ph">&nbsp;</span><span class="val">Warwickshire</span></div></div>
                    <div class="fld"><label>Postcode</label><div class="box f3"><span class="ph">&nbsp;</span><span class="val">CV32 5PJ</span></div></div>
                  </div>

                  <div class="fld full"><label>Notes</label>
                    <div class="ta f4" style="min-height:52px"><span class="ph">&nbsp;</span><span class="val">Prefers afternoon fittings. Dog in the back garden &mdash; ring first.</span></div></div>

                  <div class="facts">
                    <span class="btnp btnsave"><span class="savelbl">Save customer</span><span class="savelbl2">Save anyway (it really is a different person)</span></span>
                    <span class="btns">Cancel</span>
                  </div>
                </div>

                <!-- ============ Scene 6: the record ============ -->
                <div class="osc scRecord">
                  <div class="okbanner" style="margin-bottom:.5rem"><b>&check;</b> Customer added.</div>
                  <div class="pghead">
                    <div>
                      <div class="pgh">Emma Fletcher</div>
                      <p class="pgs"><a>&larr; Back to customers</a></p>
                    </div>
                  </div>
                  <div class="fld"><label>Name <span class="req">*</span></label><div class="boxv">Emma Fletcher</div></div>
                  <div class="colsepm">
                    <div class="fld"><label>Email</label><div class="boxv">emma.fletcher@gmail.com</div></div>
                    <div class="fld"><label>Phone <span class="lsoft">(landline)</span></label><div class="boxv">01926 555104</div></div>
                    <div class="fld"><label>Mobile</label><div class="boxv">07700 900318</div>
                      <span class="waline"><span class="tick t-wa">&check;</span> Mobile is on WhatsApp</span></div>
                  </div>
                  <div class="fld full"><label>Address line 1</label><div class="boxv">14 Willow Drive</div></div>
                  <div class="fld full"><label>Address line 2</label><div class="boxv">Cubbington</div></div>
                  <div class="cols3">
                    <div class="fld"><label>Town</label><div class="boxv">Leamington Spa</div></div>
                    <div class="fld"><label>County</label><div class="boxv">Warwickshire</div></div>
                    <div class="fld"><label>Postcode</label><div class="boxv">CV32 5PJ</div></div>
                  </div>
                  <div class="fld full"><label>Notes</label>
                    <div class="tav" style="min-height:52px">Prefers afternoon fittings. Dog in the back garden &mdash; ring first.</div></div>
                  <div class="facts"><span class="btnp">Save changes</span><span class="btns">Cancel</span></div>

                  <div class="rqh">Recent quotes</div>
                  <table class="ctbl">
                    <thead><tr><th>Quote #</th><th>Status</th><th class="num">Total</th><th>Created</th><th></th></tr></thead>
                    <tbody>
                      <tr><td><b>BEV-2026-0042</b></td><td><span class="bdg accepted">accepted</span></td><td class="num">&pound;1,486.00</td><td>2 Aug 2026</td><td><a>Open</a></td></tr>
                      <tr><td><b>BEV-2026-0039</b></td><td><span class="bdg sent">sent</span></td><td class="num">&pound;302.50</td><td>28 Jul 2026</td><td><a>Open</a></td></tr>
                      <tr><td><b>BEV-2026-0031</b></td><td><span class="bdg ordered">ordered</span></td><td class="num">&pound;915.00</td><td>19 Jul 2026</td><td><a>Open</a></td></tr>
                      <tr><td><b>BEV-2026-0028</b></td><td><span class="bdg draft">draft</span></td><td class="num">&pound;0.00</td><td>11 Jul 2026</td><td><a>Open</a></td></tr>
                    </tbody>
                  </table>

                  <div class="dzh">Danger zone</div>
                  <p class="dzp">Deleting this customer is permanent. Existing quotes will be kept but will no longer be linked to a customer record.</p>
                  <span class="btnd">Delete customer</span>

                  <div class="cfmock">
                    <div class="cfmsg">Delete Emma Fletcher? This cannot be undone.</div>
                    <div class="cfbtns"><span class="btns">Cancel</span><span class="btnd">Yes, continue</span></div>
                  </div>
                </div>

                <!-- ============ Scene 7: Find duplicates ============ -->
                <div class="osc scDedupe">
                  <div class="pghead">
                    <div>
                      <div class="pgh">Find duplicates</div>
                      <p class="pgs"><a>&larr; All customers</a> &middot; merge customers with the same name</p>
                    </div>
                    <div class="hdbtns"><span class="btnp">Merge all duplicates</span></div>
                  </div>
                  <div class="bluep"><b>How merging works:</b> the customer with the lowest id (the oldest record) is kept. All quotes,
                    appointments and payments linked to the duplicate rows are re-pointed to the keeper, then the duplicate rows are deleted.
                    <b>Cannot be undone</b> &mdash; if you&rsquo;re not sure, eyeball each group first.</div>
                  <p class="foundline">Found <b>1</b> duplicate name group with <b>1</b> redundant row in total.</p>
                  <div class="gcard">
                    <div class="gcardh">Emma Fletcher <span>(2 rows)</span></div>
                    <table class="ctbl">
                      <thead><tr><th>ID</th><th>Email</th><th>Phone</th><th>Town</th><th>Postcode</th><th class="num">Quotes</th><th class="num">Appts</th><th>Created</th></tr></thead>
                      <tbody>
                        <tr class="keeprow"><td>#312 <span class="kpill">Keeper</span></td><td>emma.f@icloud.com</td><td>01926 555771</td><td>Kenilworth</td><td>CV8 1AA</td><td class="num">0</td><td class="num">1</td><td>4 Jun 2026</td></tr>
                        <tr><td>#487</td><td>emma.fletcher@gmail.com</td><td>01926 555104</td><td>Leamington Spa</td><td>CV32 5PJ</td><td class="num">4</td><td class="num">2</td><td>2 Aug 2026</td></tr>
                      </tbody>
                    </table>
                    <div class="gacts"><span class="btns">Merge this group</span></div>
                  </div>
                </div>

                <!-- ============ Scene 8: where customers come from ============ -->
                <div class="osc scNew">
                  <div class="twocol">
                    <div>
                      <p class="halfh">New</p>
                      <div class="seg"><span>Trade</span><span class="on">Retail</span></div>
                      <p class="segnote"><b>Retail sale</b><br>A direct retail customer at your standard pricing. You&rsquo;ll add their
                         details in the quote.</p>
                      <div style="margin-top:.5rem"><span class="btnp">Start retail quote &rarr;</span></div>
                    </div>
                    <div>
                      <p class="halfh">New quote</p>
                      <div class="fld"><label>Existing customer</label>
                        <div class="boxv" style="border-radius:7px 7px 0 0"><span style="color:var(--faint)">Type to search by name, town, or postcode&hellip;</span></div>
                        <div class="sugg">Emma Fletcher &mdash; Leamington Spa &mdash; CV32 5PJ</div>
                        <div class="sugghint">Type to filter &mdash; leave blank for a new customer.</div></div>
                      <div class="fld" style="margin-top:.45rem"><label>Customer name <span class="req">*</span></label><div class="boxv">Emma Fletcher</div></div>
                      <div class="cols3">
                        <div class="fld"><label>Email</label><div class="boxv">emma.fletcher@gmail.com</div></div>
                        <div class="fld"><label>Phone <span class="lsoft">(landline)</span></label><div class="boxv">01926 555104</div></div>
                        <div class="fld"><label>Mobile</label><div class="boxv">07700 900318</div>
                          <span class="waline"><span class="tick on">&check;</span> Mobile is on WhatsApp</span></div>
                      </div>
                      <div class="fld full"><label>Address line 1</label><div class="boxv">14 Willow Drive</div></div>
                      <div class="fld full"><label>Address line 2</label><div class="boxv">Cubbington</div></div>
                      <div class="cols3">
                        <div class="fld"><label>Town</label><div class="boxv">Leamington Spa</div></div>
                        <div class="fld"><label>County</label><div class="boxv">Warwickshire</div></div>
                        <div class="fld"><label>Postcode</label><div class="boxv">CV32 5PJ</div></div>
                      </div>
                      <div class="fld full"><label>Quote notes</label><div class="tav">&nbsp;</div></div>
                    </div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> The list &mdash; Search, the Quotes count, + Add customer.</b>
                  <b class="c2"><span class="n">2</span> Name is the only must; email, landline, mobile, WhatsApp.</b>
                  <b class="c3"><span class="n">3</span> Two address lines, then town, county, postcode.</b>
                  <b class="c4"><span class="n">4</span> Notes for the fitter &mdash; then Save customer.</b>
                  <b class="c5 err"><span class="n">5</span> Same name? It shows you who, and offers Save anyway.</b>
                  <b class="c6 good"><span class="n">6</span> Customer added &mdash; their record, quotes and danger zone.</b>
                  <b class="c7"><span class="n">7</span> Find duplicates &mdash; the oldest row is the keeper.</b>
                  <b class="c8 good"><span class="n">8</span> Retail quotes pull from here; trade never does.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Customers</b> lives under <b>Retail</b> in the left-hand menu, and the subtitle on the page spells out what it holds:
             &ldquo;<b>End-customers belonging to &lt;your company&gt;</b>.&rdquo; These are the households you quote, measure and fit for.
             It appears on the menu for anybody carrying <em>any one</em> of three permissions &mdash; <b>Create quotes</b>,
             <b>Create orders</b> or <b>View all customer jobs</b>. One thing to know before anyone rings you about it: a staff member
             <b>without</b> the <b>&ldquo;View all customer jobs&rdquo;</b> tick (set per person in <b>Setup &rarr; Users</b> &mdash; note
             <b>Setup</b>, not Settings; Users and Settings are two separate entries sitting side by side in that group) only sees
             customers who have a job <b>assigned to them</b>. Two people can open the same page and see lists of different lengths, and
             neither of them is broken. Admins see the lot.</p>
          <ul class="steps">
            <li><b>Find somebody.</b> The search box matches <b>name, email, phone, town or postcode</b>, so any scrap of what you remember
                will find them &mdash; half a surname, the first part of a postcode, the last four digits of a number. Type it and press
                <b>Search</b>; a small <b>Clear</b> button then appears to take you back to everyone. The table shows Name, Email, Phone,
                Town, Postcode, a <b>Quotes</b> count, and <b>Edit</b> to open the record. Important: <b>the list stops at 100 names</b>,
                sorted alphabetically. Once you have more customers than that, the ones late in the alphabet simply are not on screen &mdash;
                they have not vanished, you just have to <b>search</b> for them rather than scroll. Empty-handed you will see either
                <em>&ldquo;No customers yet. Add your first customer &rarr;&rdquo;</em> or
                <em>&ldquo;No customers match &lsquo;&hellip;&rsquo;. View all &rarr;&rdquo;</em>.</li>
            <li><b>Name, and the three ways to reach them.</b> Click <b>+ Add customer</b>. Only <b>Name</b> carries the red
                <span class="req">*</span>, so the name is the only thing you <em>must</em> give (up to 150 characters). Under it sit three
                boxes side by side: <b>Email</b> (150 characters, and it must be a proper address), <b>Phone <em>(landline)</em></b>
                (50 characters) and <b>Mobile</b> (40). Note the screen says <em>landline</em> on the middle one &mdash; the mobile has its own
                box. Directly <b>under the Mobile box</b> is a tick labelled <b>&ldquo;Mobile is on WhatsApp&rdquo;</b>. Tick it when that
                number takes WhatsApp; it is what lets you message them about the fitting later.</li>
            <li><b>Where the blinds are going.</b> <b>Address line 1</b> and <b>Address line 2</b> are two separate full-width boxes (150
                characters each), then <b>Town</b> (100), <b>County</b> (100) and <b>Postcode</b> (20) share a row. There is <b>no postcode
                finder on this form</b> &mdash; you may have met one on the New quote and calendar screens, but here you type the address by
                hand. There is one address per customer, and it does duty as both the place you visit and the address on the paperwork.</li>
            <li><b>Notes, then save.</b> <b>Notes</b> is a four-line box for your own scribble &mdash; where to park, which door, the dog in the
                back garden, &ldquo;afternoons only&rdquo;. Nobody outside the business sees it. Then <b>Save customer</b>. <b>Cancel</b>
                abandons everything and returns you to the list. Everything except the name is optional, and a box you leave empty stores
                <b>nothing at all</b> &mdash; no blanks, no placeholders. You can always come back and fill it in.</li>
            <li><b>The &ldquo;same name&rdquo; check.</b> If that name is already in the book, the app does <b>not</b> save. An amber banner
                appears saying <b>&ldquo;A customer with this name already exists.&rdquo;</b> &mdash; <em>&ldquo;Pick the existing customer
                below, or scroll down and click Save anyway if this really is a different person with the same name.&rdquo;</em> Underneath it
                lists up to five matches as <b>clickable links</b>, each with their <b>town, postcode, email and phone</b>, which is exactly
                what you need to tell one Emma Fletcher from another. Nine times out of ten it is the same person: click their name and carry
                on with the record you already have. If it genuinely is a second person, the button has changed to
                <b>&ldquo;Save anyway (it really is a different person)&rdquo;</b> &mdash; click that and it saves. The check looks at the
                <b>name only</b>; two people really can share a phone number or an email. And it only runs when you <b>add</b> &mdash; renaming
                somebody on their record page to a name that already exists is accepted without a murmur.</li>
            <li><b>Their record.</b> Saving flashes <b>&ldquo;Customer added.&rdquo;</b> in green and drops you on their page, headed with their
                name. It is the <b>same form again</b>, filled in &mdash; so correcting a postcode is a matter of typing over it and pressing
                <b>Save changes</b> (you get <b>&ldquo;Customer updated.&rdquo;</b>). Below the form is <b>Recent quotes</b>: their last five,
                newest first, with Quote #, a colour-coded <b>Status</b> badge, the <b>Total</b>, when it was <b>Created</b>, and <b>Open</b> to
                jump into the quote. The quote numbers are the app&rsquo;s own &mdash; your prefix, the year, then a four-digit count, like
                <b>BEV-2026-0042</b>. If they have never been quoted, the <b>whole Recent quotes section is simply not there</b>; it appears the
                moment they have one, so do not go hunting for an empty table on a brand-new record. The badge colours are the app&rsquo;s own
                fixed set &mdash; <b>grey</b> draft, <b>blue</b> sent,
                <b>green</b> accepted, <b>yellow</b> ordered, <b>red</b> rejected &mdash; so you can read where every job stands at a glance.
                (These are not the palette on <em>Settings &rarr; Status colours</em>; that one paints your calendar cards and the orders list.)
                At the very bottom is the <b>Danger zone</b>.</li>
            <li><b>Find duplicates</b> (admins only &mdash; the secondary button beside <b>+ Add customer</b>, titled <em>&ldquo;Find and merge
                customers with the same name&rdquo;</em>). Under the blue &ldquo;How merging works&rdquo; panel it counts up what it found &mdash;
                <em>&ldquo;Found <b>1</b> duplicate name group with <b>1</b> redundant row in total.&rdquo;</em> &mdash; then shows each group as
                a card headed <b>&ldquo;Emma Fletcher (2 rows)&rdquo;</b>, listing ID, Email, Phone, Town, Postcode, <b>Quotes</b>, <b>Appts</b>
                and Created. The <b>oldest row is tinted green and badged &ldquo;Keeper&rdquo;</b>. Press <b>Merge this group</b> (the quiet grey
                button under the card &mdash; only <b>Merge all duplicates</b> up in the header is the blue one) and you are asked to confirm:
                <em>&ldquo;Merge this group? 1 duplicate row will be removed. Quote / appointment links are re-pointed to the keeper. This
                can&rsquo;t be undone.&rdquo;</em> Say yes and, as the blue panel says, every quote, appointment and payment on the other rows
                is re-pointed onto the keeper and the spares are deleted. You get a count back, e.g.
                <em>&ldquo;Merged 1 group; removed 2 duplicates.&rdquo;</em> Nothing to do? It says
                <em>&ldquo;No duplicates found &#127881;&rdquo;</em> &mdash; &ldquo;Every customer in this tenant has a unique name. Nothing to
                merge.&rdquo; Check the Quotes and Appts counts before you merge: this one <b>cannot be undone</b>.</li>
            <li><b>Where customers come from.</b> This page is only one of three doors. Booking a <b>measure appointment</b> on the calendar
                creates a customer (the installation address becomes their address), and starting a <b>New quote</b> for a name that is not on
                the list creates one too. <b>Neither of those asks about duplicates</b> &mdash; that is precisely how the same person ends up in
                the book twice, and why Find duplicates exists. On a <b>New quote</b>, the <b>Existing customer</b> box is a plain text box with
                a type-ahead: <em>&ldquo;Type to search by name, town, or postcode&hellip;&rdquo;</em>, hint <em>&ldquo;Type to filter &mdash;
                leave blank for a new customer.&rdquo;</em> Type two or three letters, pick them, and their name, email, landline, mobile,
                WhatsApp tick and full address all come across. Everything below that box is <b>the same set of boxes you have just filled in
                here</b> &mdash; <b>Customer name</b> <span class="req">*</span>, <b>Email</b>, <b>Phone <em>(landline)</em></b>, <b>Mobile</b>
                with its <b>Mobile is on WhatsApp</b> tick, <b>Address line 1</b>, <b>Address line 2</b>, <b>Town</b>, <b>County</b>,
                <b>Postcode</b> &mdash; and one extra at the bottom, <b>Quote notes</b>, which belongs to that quote rather than to the person
                and never lands on their customer record. That is the payoff for filling this form in properly.</li>
          </ul>
          <div class="oops"><b>The messages you will meet:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li>No name &rarr; <code>Name is required.</code></li>
               <li>A wonky email &rarr; <code>Please enter a valid email address.</code> (leave it blank if you do not have one)</li>
               <li>Name already in the book &rarr; <code>A customer with this name already exists.</code></li>
             </ul></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Deleting is permanent.</b> The red <b>Delete customer</b> button in the
             Danger zone asks you twice &mdash; a box appears reading <em>&ldquo;Delete &lt;name&gt;? This cannot be undone.&rdquo;</em> with
             <b>Cancel</b> and <b>Yes, continue</b>. Go through with it and you are told:
             <em>&ldquo;Customer deleted. Existing quotes have been kept but are no longer linked.&rdquo;</em> In other words the work survives
             but is orphaned &mdash; it belongs to nobody. Only ever delete a record you created by mistake; for somebody with history, edit them
             or merge them instead.</div></div>
          <p><b>Retail and trade are two different books.</b> Customers is the <b>retail</b> one &mdash; your own end-customers. Businesses you
             <em>supply</em> are <b>trade accounts</b>, and they live under <b>Trade &rarr; Trade accounts</b>, each with its own discount,
             portal login and invoices. The <b>+ New</b> button pinned at the top of the menu knows which of the two you are: on an ordinary
             account it goes <b>straight to the retail New quote form</b>, and on the factory&rsquo;s own super-admin account it opens a
             launcher first, headed <b>New</b>, with a two-part <b>Trade | Retail</b> switch. (If you have never seen that switch, this is why
             &mdash; you are not missing a setting.) On it, <b>Retail sale</b> is &ldquo;a direct retail customer at your standard pricing.
             You&rsquo;ll add their details in the quote&rdquo;, and that is the side that uses this address book. A <b>Trade sale</b> takes
             its customer details straight off the account and <b>never writes a record here</b>, which is why your trade customers are not in
             this list and never will be. Money taken from the people in this book shows up under <b>Retail &rarr; Payments</b>.</p>
          <p><b>One last thing:</b> there is <b>no bulk customer import</b>. Every name in here arrived one of three ways &mdash; typed on this
             form, created by a calendar booking, or created by a New quote. If you are moving over from another system, work through them as
             the jobs come in rather than trying to load them all at once.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'The Customers list, buttons and search.',
             'Customers is your address book for the people you quote — your own retail customers. Search matches their name, email, phone, town or postcode, so any scrap of it will find them. The list shows the hundred nearest the top of the alphabet, so if someone seems missing, search rather than scroll. Quotes is how many quotes they have. Edit opens their record. Find duplicates is for later. Add customer starts a new one.', 1],
            ['0:22', 'Name, email, landline, mobile, WhatsApp tick.',
             'Only the name has a red star, so the name is the only thing you must give. Then the three ways of reaching them side by side — email, the landline, and the mobile. If that mobile takes WhatsApp, tick Mobile is on WhatsApp; that is what lets you message them about the fitting later. If you type an email it has to be a real one.', 2],
            ['0:40', 'Two address lines, then town, county, postcode.',
             'Now where the blinds are going. Two address lines, then town, county and postcode on one row. There is no postcode finder on this form — you saw one on the quote screen, but here you type it. One address per customer: it becomes both where you visit and where the paperwork goes.', 3],
            ['0:56', 'Notes typed in; Save customer and Cancel.',
             'Notes are your own scribble — parking, the dog, which door, anything the fitter would thank you for. Nobody outside sees it. Then Save customer. Cancel throws the lot away and goes back to the list.', 4],
            ['1:08', 'Amber same-name banner with the match listed.',
             'If that name is already in the book, the app stops and shows you who it found, with their town, postcode and number so you can tell. Nine times in ten it is the same person and you click their name to open the one you already have. If it truly is a second Emma Fletcher, the button now reads Save anyway, and it saves. It only checks the name — two people really can share a phone or an email.', 5],
            ['1:28', 'Customer added; record, quotes, danger zone.',
             'Saved — and this is their record. It is the same form, so correcting a postcode is just typing over it and Save changes. Underneath, their last five quotes, numbered your prefix, the year, then a count — Bev, two thousand and twenty six, forty two — with the status colour: grey draft, blue sent, green accepted, yellow ordered, and Open to jump straight into one. If they have never been quoted, that whole section simply is not there. At the bottom, the danger zone. Deleting is permanent and it asks you twice; their quotes stay but stop being joined to anybody, so only delete something you created by mistake, never someone with history.', 6],
            ['1:52', 'Find duplicates; green keeper row; merge.',
             'Here is why the same name turns up twice. Booking a measure on the calendar creates a customer, and starting a New quote for a name that is not on the list creates one too — neither of those asks. Find duplicates rounds them up and tells you what it found — one duplicate name group, one redundant row. The oldest record is the keeper, marked in green; every quote, appointment and payment on the others is moved onto it and the spares are deleted. Look at the quote and appointment counts first, because this one cannot be undone.', 7],
            ['2:14', 'Trade or Retail; the new-quote type-ahead.',
             'And that is the payoff. If your account is the factory\'s own, New opens a launcher with a Trade or Retail switch; on an ordinary account it takes you straight to the retail form, which is the same thing without the choice. Either way, Retail is the side that uses this address book — trade sales run off a trade account instead and never land in here. Start a retail quote, type two or three letters of her name in the customer box, pick her, and the rest of the form fills itself: email, landline, mobile, the WhatsApp tick, both address lines, town, county and postcode. The only box left is Quote notes, and that belongs to the job, not to her. Leave the customer box empty and just type a new name, and the app quietly adds them to the book for you — handy, but that is how the duplicates you just merged got there.', 8],
        ],
];
