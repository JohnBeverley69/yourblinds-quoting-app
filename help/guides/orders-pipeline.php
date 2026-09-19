<?php
declare(strict_types=1);

/**
 * Guide: orders-pipeline
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers /orders/pipeline.php — the read-only Kanban board of the whole
 * funnel. Column colours, card flags, filters and the automatic movers are
 * all taken from the real page; the column pill inks are the ones
 * job_status_text_colour() actually returns for the default palette
 * (Quote/amber is the one that comes back dark).
 */

return [
        'aud'     => 'admin',
        'section' => 'Orders',
        'title'   => 'The Pipeline board',
        'eyebrow' => 'Orders · Pipeline',
        'blurb'   => 'One picture of every job you have on — seven columns, live money per stage, and what actually moves a job from one column to the next.',
        'lede'    => 'The Pipeline is the <b>same jobs</b> that already sit in your <b>Quotes</b> and <b>Orders</b> lists &mdash; the whole
                      funnel on one board instead of split across two tables. Seven columns, left to right: <em>Quote, Declined, Accepted,
                      Ordered, Fitted, Invoiced, Paid</em>. Under each one it tells you how many jobs are sitting there and how much money.
                      You <b>read</b> this screen; you don&rsquo;t move cards on it.
                      Moving a job happens on the job&rsquo;s own page &mdash; or happens all by itself &mdash; and this guide shows you both.',
        'open'    => '/orders/pipeline.php',
        'css'     => '
          /* ── page shell (always on screen) ─────────────────────────── */
          .gd .navh{ font-size:.54rem; letter-spacing:.12em; text-transform:uppercase; color:#6a7d8c; font-weight:700; margin:.6rem 0 .1rem; padding:0 .5rem; }
          .gd .plsub{ color:var(--faint); font-size:.72rem; margin:.1rem 0 .4rem; }
          .gd .pltabs{ display:inline-flex; background:var(--panel); border-radius:8px; padding:.12rem; margin-bottom:.7rem; }
          .gd .pltabs span{ padding:.2rem .6rem; border-radius:6px; font-size:.7rem; font-weight:700; color:var(--faint); }
          .gd .pltabs span.on{ background:var(--surface); color:var(--ink); box-shadow:0 1px 2px rgba(0,0,0,.08); }
          .gd .stage[data-step="1"] .pltabs{ box-shadow:0 0 0 3px var(--accent-wash); }

          /* ── scenes ───────────────────────────────────────────────── */
          .gd .plsc{ display:none; }
          .gd .stage[data-step="0"] .scA, .gd .stage[data-step="1"] .scA{ display:block; }
          .gd .stage[data-step="2"] .scB{ display:block; }
          .gd .stage[data-step="3"] .scC{ display:block; }
          .gd .stage[data-step="4"] .scD{ display:block; }
          .gd .stage[data-step="5"] .scE{ display:block; }
          .gd .stage[data-step="6"] .scF{ display:block; }
          .gd .stage[data-step="7"] .scG{ display:block; }
          .gd .stage[data-step="7"] .scG .ex{ display:flex; }
          .gd .stage[data-step="8"] .scH{ display:block; }

          /* ── filter bar ───────────────────────────────────────────── */
          .gd .plfilt{ display:flex; flex-wrap:wrap; align-items:center; gap:.35rem .5rem; border:1px solid var(--line);
                       border-radius:10px; padding:.45rem .55rem; background:var(--surface); margin-bottom:.6rem; }
          .gd .plfilt .box{ height:24px; flex:0 1 11rem; font-size:.68rem; }
          .gd .plfilt .box .ph, .gd .plfilt .box .val{ font-size:.68rem; }
          .gd .plfilt .selectbox{ min-width:7.4rem; font-size:.68rem; padding:.2rem .4rem; }
          .gd .plwlbl{ font-size:.68rem; color:var(--faint); }
          .gd .plfilt .pill{ font-size:.66rem; padding:.1rem .45rem; margin:0; }
          .gd .plapply{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.14rem .5rem;
                        font-size:.66rem; font-weight:600; color:var(--soft); background:var(--surface); }
          .gd .plsum{ margin-left:auto; font-size:.68rem; color:var(--soft); }
          .gd .plsum b{ color:var(--ink); }

          /* ── the board ────────────────────────────────────────────── */
          .gd .plboard{ display:grid; grid-template-columns:repeat(7,1fr); gap:.3rem; }
          .gd .plcol{ background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:.25rem; min-height:7.4rem; }
          .gd .plhead{ display:inline-block; border-radius:999px; padding:.04rem .38rem; font-size:.52rem; font-weight:700;
                       text-transform:uppercase; letter-spacing:.04em; }
          .gd .h-quote{ background:#f59e0b; color:#1f2937; }
          .gd .h-decl{ background:#dc2626; color:#ffffff; }
          .gd .h-acc{ background:#16a34a; color:#ffffff; }
          .gd .h-ord{ background:#0891b2; color:#ffffff; }
          .gd .h-fit{ background:#0d9488; color:#ffffff; }
          .gd .h-inv{ background:#ea580c; color:#ffffff; }
          .gd .h-paid{ background:#475569; color:#ffffff; }
          .gd .plmeta{ display:flex; align-items:baseline; gap:.25rem; margin:.15rem 0 .25rem; }
          .gd .plcount{ font-size:.66rem; font-weight:700; color:var(--ink); }
          .gd .plvalue{ font-size:.56rem; color:var(--faint); }
          .gd .plcard{ position:relative; background:var(--surface); border:1px solid var(--line); border-radius:6px;
                       padding:.2rem .25rem; margin-bottom:.2rem; }
          .gd .plname{ font-size:.55rem; font-weight:600; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .plnum{ font-size:.5rem; color:var(--faint); font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
          .gd .plrow{ display:flex; justify-content:space-between; gap:.25rem; margin-top:.1rem; }
          .gd .pltot{ font-size:.52rem; font-weight:700; color:#16a34a; }
          .gd .plage{ font-size:.5rem; color:var(--faint); }
          .gd .plempty{ font-size:.52rem; color:var(--faint); font-style:italic; text-align:center; padding:.5rem .1rem; }
          .gd .pltrunc{ font-size:.5rem; color:var(--faint); text-align:center; padding:.18rem 0; border-top:1px dashed var(--line); }
          .gd .plghost .plcol{ opacity:.42; }

          /* ── the card, blown up ───────────────────────────────────── */
          .gd .plzoom{ position:relative; width:15rem; background:var(--surface); border:1px solid var(--line); border-radius:8px; padding:.5rem .6rem; }
          .gd .plzoom .zname{ font-size:.86rem; font-weight:600; color:var(--ink); }
          .gd .plzoom .znum{ font-size:.72rem; color:var(--faint); font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
          .gd .plzoom .zrow{ display:flex; justify-content:space-between; align-items:baseline; margin-top:.25rem; }
          .gd .plzoom .ztot{ font-size:.82rem; font-weight:700; color:#16a34a; }
          .gd .plzoom .zage{ font-size:.66rem; color:var(--faint); }
          .gd .callouts{ margin:.55rem 0 0; }
          .gd .co{ display:flex; gap:.4rem; font-size:.7rem; color:var(--soft); padding:.1rem 0; }
          .gd .co i{ font-style:normal; color:var(--accent); font-weight:700; }
          .gd .zwrap{ display:flex; gap:.9rem; align-items:flex-start; flex-wrap:wrap; }

          /* ── card flags ───────────────────────────────────────────── */
          .gd .plnotsent{ display:inline-block; margin-top:.25rem; font-size:.56rem; font-weight:700; text-transform:uppercase;
                          letter-spacing:.03em; color:#92400e; background:#fef3c7; border:1px solid #fde68a;
                          border-radius:999px; padding:.04rem .38rem; }
          .gd .plchip{ position:absolute; top:.3rem; right:.3rem; border-radius:999px; padding:.04rem .34rem;
                       font-size:.55rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
          .gd .plchip.is-full{ background:#d1fae5; color:#065f46; }
          .gd .plchip.is-part{ background:#fef3c7; color:#92400e; }
          .gd .plout{ color:#ef4444; font-size:.6rem; font-weight:600; margin-top:.18rem; }
          .gd .flagcols{ display:flex; gap:.6rem; flex-wrap:wrap; }
          .gd .flagcol{ flex:1 1 8.6rem; }
          .gd .flagcap{ font-size:.56rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin-bottom:.22rem; }
          .gd .bigcard{ position:relative; background:var(--surface); border:1px solid var(--line); border-radius:8px; padding:.4rem .5rem; }
          .gd .bigcard .plname{ font-size:.72rem; }
          .gd .bigcard .plnum{ font-size:.62rem; }
          .gd .bigcard .pltot{ font-size:.66rem; }
          .gd .bigcard .plage{ font-size:.6rem; }
          .gd .bigcard.padchip .plname{ padding-right:3.2rem; }

          /* ── no-drag scene ────────────────────────────────────────── */
          .gd .nodrag{ position:relative; display:inline-block; }
          .gd .nodrag .nomark{ position:absolute; top:-.4rem; right:-.9rem; font-size:1.15rem; color:var(--err); font-weight:700; }
          .gd .cursorghost{ position:absolute; bottom:-.55rem; right:-.55rem; font-size:.95rem; opacity:.65; }
          .gd .qbtnrow{ display:flex; gap:.35rem; flex-wrap:wrap; margin-top:.2rem; }
          .gd .qbtn{ border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; padding:.24rem .55rem; font-size:.68rem;
                     font-weight:600; color:var(--soft); background:var(--surface); }
          .gd .qbtn.pri{ background:var(--accent); border-color:var(--accent); color:#fff; }

          /* ── automatic movers ─────────────────────────────────────── */
          .gd .scG .ex{ padding:.28rem .5rem; margin-bottom:.22rem; font-size:.72rem; }
          .gd .scG .exs{ color:var(--ink); }
          .gd .scG .exc{ color:var(--faint); }
          .gd .scG .exp{ font-size:.66rem; font-weight:700; }

          /* ── compare scene ────────────────────────────────────────── */
          .gd .cmp{ display:flex; gap:.7rem; align-items:flex-start; flex-wrap:wrap; }
          .gd .cmpcol{ flex:1 1 13rem; }
          .gd .listmock{ border:1px solid var(--line); border-radius:8px; background:var(--surface); padding:.4rem .5rem; }
          .gd .lchips{ display:flex; gap:.22rem; flex-wrap:wrap; margin-bottom:.3rem; }
          .gd .lchip{ border:1px solid var(--line); border-radius:999px; padding:.06rem .4rem; font-size:.56rem; color:var(--soft); }
          .gd .lchip.on{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .lrow{ font-size:.58rem; color:var(--soft); border-top:1px solid var(--line-2); padding:.16rem 0; }
          .gd .lchip.larch{ margin-left:auto; }
          .gd .lhd{ display:flex; align-items:center; justify-content:space-between; gap:.4rem; }
          .gd .lttl{ font-size:.74rem; font-weight:700; color:var(--ink); }
          .gd .lsub{ font-size:.56rem; color:var(--faint); margin:.05rem 0 .3rem; }
          .gd .lnew{ display:inline-block; background:var(--accent); color:#fff; border-radius:6px; padding:.1rem .45rem;
                     font-size:.58rem; font-weight:700; }
          .gd .plnote{ font-size:.68rem; color:var(--faint); margin:.4rem 0 0; }
          .gd .plnote b{ color:var(--ink); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / orders / pipeline</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a><a class="on">Pipeline</a><a>Factory</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a>Orders</a>
                <div class="navh">Trade</div>
                <a>Quotes</a><a>Orders</a>
                <div class="navh">Setup</div>
                <a>Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t" style="margin-bottom:.1rem">Pipeline</div>
                <p class="plsub">Where every job is in the funnel.</p>
                <div class="pltabs"><span>List</span><span class="on">Pipeline</span></div>

                <!-- Scene A: the empty filter bar + the board behind it (poster) -->
                <div class="plsc scA">
                  <div class="plfilt">
                    <div class="fld" style="flex:0 1 11rem"><div class="box"><span class="ph">Search customer / quote # / postcode&hellip;</span></div></div>
                    <span class="plwlbl">Window:</span><span class="selectbox">Last 90 days</span>
                    <span class="pill">All time</span>
                    <span class="pill">Mine only</span>
                    <span class="plapply">Apply</span>
                    <span class="plsum"><b>12</b> jobs &middot; <b>&pound;17,250</b> in pipeline</span>
                  </div>
                  <div class="plboard plghost">
                    <div class="plcol"><span class="plhead h-quote">Quote</span></div>
                    <div class="plcol"><span class="plhead h-decl">Declined</span></div>
                    <div class="plcol"><span class="plhead h-acc">Accepted</span></div>
                    <div class="plcol"><span class="plhead h-ord">Ordered</span></div>
                    <div class="plcol"><span class="plhead h-fit">Fitted</span></div>
                    <div class="plcol"><span class="plhead h-inv">Invoiced</span></div>
                    <div class="plcol"><span class="plhead h-paid">Paid</span></div>
                  </div>
                </div>

                <!-- Scene B: all seven columns, in full colour, with counts and money -->
                <div class="plsc scB">
                  <div class="plboard">
                    <div class="plcol">
                      <span class="plhead h-quote">Quote</span>
                      <div class="plmeta"><span class="plcount">4</span><span class="plvalue">&pound;5,120</span></div>
                      <div class="plcard"><div class="plname">Mrs J Hartley</div><div class="plnum">PRE-2026-0042</div></div>
                      <div class="plcard"><div class="plname">D Okafor</div><div class="plnum">PRE-2026-0041</div></div>
                    </div>
                    <div class="plcol">
                      <span class="plhead h-decl">Declined</span>
                      <div class="plmeta"><span class="plcount">1</span><span class="plvalue">&pound;640</span></div>
                      <div class="plcard"><div class="plname">T Whitaker</div><div class="plnum">PRE-2026-0037</div></div>
                    </div>
                    <div class="plcol">
                      <span class="plhead h-acc">Accepted</span>
                      <div class="plmeta"><span class="plcount">2</span><span class="plvalue">&pound;3,180</span></div>
                      <div class="plcard"><div class="plname">R Ellis</div><div class="plnum">PRE-2026-0039</div></div>
                    </div>
                    <div class="plcol">
                      <span class="plhead h-ord">Ordered</span>
                      <div class="plmeta"><span class="plcount">3</span><span class="plvalue">&pound;4,905</span></div>
                      <div class="plcard"><div class="plname">Kaur &amp; Sons</div><div class="plnum">PRE-2026-0033</div></div>
                    </div>
                    <div class="plcol">
                      <span class="plhead h-fit">Fitted</span>
                      <div class="plmeta"><span class="plcount">1</span><span class="plvalue">&pound;1,260</span></div>
                      <div class="plcard"><div class="plname">M Sowerby</div><div class="plnum">PRE-2026-0028</div></div>
                    </div>
                    <div class="plcol">
                      <span class="plhead h-inv">Invoiced</span>
                      <div class="plmeta"><span class="plcount">1</span><span class="plvalue">&pound;2,145</span></div>
                      <div class="plcard"><div class="plname">A Brennan</div><div class="plnum">PRE-2026-0024</div></div>
                    </div>
                    <div class="plcol">
                      <span class="plhead h-paid">Paid</span>
                      <div class="plmeta"><span class="plcount">0</span><span class="plvalue">&pound;0</span></div>
                      <div class="plempty">No jobs</div>
                    </div>
                  </div>
                  <p class="plnote"><b>Quote</b> holds a draft <em>and</em> a sent quote &mdash; a draft is only a quote that hasn&rsquo;t gone out yet.
                     The colours are <b>your</b> colours, from <b>Settings &rarr; Status colours</b> &mdash; the same ones the calendar and the orders list use.</p>
                </div>

                <!-- Scene C: one card, blown up, with callouts -->
                <div class="plsc scC">
                  <div class="zwrap">
                    <div class="plzoom">
                      <div class="zname">Mrs J Hartley</div>
                      <div class="znum">PRE-2026-0042 <span style="color:var(--faint)">&middot; LS16 5PT</span></div>
                      <div class="zrow"><span class="ztot">&pound;1,284.00</span><span class="zage" title="Last touched: 2026-09-18 14:22:07">3d ago</span></div>
                    </div>
                    <div class="callouts">
                      <div class="co"><i>&larr;</i> the customer&rsquo;s name (or <b>No name</b> if it&rsquo;s blank)</div>
                      <div class="co"><i>&larr;</i> the quote number, then the postcode</div>
                      <div class="co"><i>&larr;</i> the value of the job</div>
                      <div class="co"><i>&larr;</i> how long since anything happened &mdash; hover for <em>Last touched: 2026-09-18 14:22:07</em></div>
                    </div>
                  </div>
                  <p class="plnote">Newest-touched jobs rise to the <b>top</b> of their column, so the forgotten ones sink to the bottom.
                     The whole card is a <b>link</b> &mdash; click anywhere on it and the quote opens.</p>
                </div>

                <!-- Scene D: the three flags -->
                <div class="plsc scD">
                  <div class="flagcols">
                    <div class="flagcol">
                      <div class="flagcap">In Quote</div>
                      <div class="bigcard">
                        <div class="plname">D Okafor</div>
                        <div class="plnum">PRE-2026-0041 &middot; BD18 3HN</div>
                        <div><span class="plnotsent">Not sent</span></div>
                        <div class="plrow"><span class="pltot">&pound;820.00</span><span class="plage">6d ago</span></div>
                      </div>
                    </div>
                    <div class="flagcol">
                      <div class="flagcap">In Accepted</div>
                      <div class="bigcard padchip">
                        <span class="plchip is-full" title="Paid in full">Paid</span>
                        <div class="plname">R Ellis</div>
                        <div class="plnum">PRE-2026-0039 &middot; HX3 0PT</div>
                        <div class="plrow"><span class="pltot">&pound;1,540.00</span><span class="plage">2d ago</span></div>
                      </div>
                    </div>
                    <div class="flagcol">
                      <div class="flagcap">In Invoiced</div>
                      <div class="bigcard padchip">
                        <span class="plchip is-part" title="&pound;1,785.00 received of &pound;2,145.00">Part paid</span>
                        <div class="plname">A Brennan</div>
                        <div class="plnum">PRE-2026-0024 &middot; LS1 4AP</div>
                        <div class="plrow"><span class="pltot">&pound;2,145.00</span><span class="plage">2w ago</span></div>
                        <div class="plout">&pound;360.00 outstanding</div>
                      </div>
                    </div>
                  </div>
                  <p class="plnote"><b>Not sent</b> sits on the card body; the <b>Paid</b> / <b>Part paid</b> chip is pinned to the
                     top-right corner and shows in <em>any</em> column. <b>&pound;X outstanding</b> appears only in <b>Invoiced</b>.
                     The sums tally: &pound;1,785.00 of &pound;2,145.00 is in, so &pound;360.00 is still owed.</p>
                </div>

                <!-- Scene E: the filter bar working, plus the card cap -->
                <div class="plsc scE">
                  <div class="plfilt">
                    <div class="fld" style="flex:0 1 11rem"><div class="box f5"><span class="ph">Search customer / quote # / postcode&hellip;</span><span class="val">Hartley</span></div></div>
                    <span class="plwlbl">Window:</span><span class="selectbox">Last 90 days</span>
                    <span class="pill sel">All time &times;</span>
                    <span class="pill">Mine only</span>
                    <span class="plapply">Apply</span>
                    <span class="plsum"><b>72</b> jobs &middot; <b>&pound;86,525</b> in pipeline</span>
                  </div>
                  <div class="plboard">
                    <div class="plcol"><span class="plhead h-quote">Quote</span><div class="plmeta"><span class="plcount">4</span><span class="plvalue">&pound;5,120</span></div></div>
                    <div class="plcol"><span class="plhead h-decl">Declined</span><div class="plmeta"><span class="plcount">1</span><span class="plvalue">&pound;640</span></div></div>
                    <div class="plcol"><span class="plhead h-acc">Accepted</span><div class="plmeta"><span class="plcount">2</span><span class="plvalue">&pound;3,180</span></div></div>
                    <div class="plcol">
                      <span class="plhead h-ord">Ordered</span>
                      <div class="plmeta"><span class="plcount">63</span><span class="plvalue">&pound;74,180</span></div>
                      <div class="plcard"><div class="plname">Kaur &amp; Sons</div><div class="plnum">PRE-2026-0033</div></div>
                      <div class="pltrunc">Showing 50 of 63 &middot; refine the window or search</div>
                    </div>
                    <div class="plcol"><span class="plhead h-fit">Fitted</span><div class="plmeta"><span class="plcount">1</span><span class="plvalue">&pound;1,260</span></div></div>
                    <div class="plcol"><span class="plhead h-inv">Invoiced</span><div class="plmeta"><span class="plcount">1</span><span class="plvalue">&pound;2,145</span></div></div>
                    <div class="plcol"><span class="plhead h-paid">Paid</span><div class="plmeta"><span class="plcount">0</span><span class="plvalue">&pound;0</span></div></div>
                  </div>
                  <p class="plnote"><b>Window</b> sends the page off the moment you change it &mdash; <b>Apply</b> is for the search box.
                     <b>All time</b> and <b>Mine only</b> are links: when one is on it grows a <b>&times;</b>, and clicking it turns it off again.</p>
                </div>

                <!-- Scene F: it does not drag -->
                <div class="plsc scF">
                  <div class="zwrap">
                    <div class="nodrag">
                      <div class="plzoom" style="width:11rem">
                        <div class="zname">R Ellis</div>
                        <div class="znum">PRE-2026-0039 &middot; HX3 0PT</div>
                        <div class="zrow"><span class="ztot">&pound;1,540.00</span><span class="zage">2d ago</span></div>
                      </div>
                      <span class="nomark">&#128683;</span>
                      <span class="cursorghost">&#9757;</span>
                    </div>
                    <div style="flex:1 1 11rem">
                      <div class="flagcap">On the quote&rsquo;s own page</div>
                      <div class="qbtnrow">
                        <span class="qbtn">Mark as accepted</span>
                        <span class="qbtn">Mark as declined</span>
                      </div>
                      <div class="qbtnrow">
                        <span class="qbtn pri">&#128230; Save as order</span>
                        <span class="qbtn">Reopen as draft</span>
                      </div>
                      <p class="plnote">Moving a job <b>does things</b>: it stamps dates, books a fitting, emails your suppliers.
                         A slipped mouse must never be able to set that off.</p>
                    </div>
                  </div>
                </div>

                <!-- Scene G: the six automatic movers -->
                <div class="plsc scG">
                  <div class="ex"><span class="exs">Customer opens the link you sent</span><span class="exc">&rarr;</span><span class="exs">Quote &middot; sent</span><span class="exp">by itself</span></div>
                  <div class="ex"><span class="exs">Customer accepts online</span><span class="exc">&rarr;</span><span class="exs">Accepted</span><span class="exp">+ a pending fitting</span></div>
                  <div class="ex"><span class="exs">Every blind is one you make</span><span class="exc">&rarr;</span><span class="exs">Ordered</span><span class="exp">straight to the workshop</span></div>
                  <div class="ex"><span class="exs">Fitting appointment marked completed</span><span class="exc">&rarr;</span><span class="exs">Fitted</span><span class="exp">from the calendar</span></div>
                  <div class="ex"><span class="exs">You email the invoice</span><span class="exc">&rarr;</span><span class="exs">Invoiced</span><span class="exp">on sending</span></div>
                  <div class="ex"><span class="exs">Deposit + payments cover the total</span><span class="exc">&rarr;</span><span class="exs">Paid</span><span class="exp">never by hand</span></div>
                  <div class="okbanner" style="margin-top:.35rem"><span>&check;</span> Status: ordered. Due 3 Oct 2026. Sent straight to the workshop &mdash; all in-house, no supplier order needed.</div>
                </div>

                <!-- Scene H: board vs list -->
                <div class="plsc scH">
                  <div class="cmp">
                    <div class="cmpcol">
                      <div class="flagcap">Pipeline &mdash; the shape</div>
                      <div class="plboard">
                        <div class="plcol"><span class="plhead h-quote">Quote</span><div class="plmeta"><span class="plcount">4</span></div></div>
                        <div class="plcol"><span class="plhead h-decl">Dec</span><div class="plmeta"><span class="plcount">1</span></div></div>
                        <div class="plcol"><span class="plhead h-acc">Acc</span><div class="plmeta"><span class="plcount">2</span></div></div>
                        <div class="plcol"><span class="plhead h-ord">Ord</span><div class="plmeta"><span class="plcount">3</span></div></div>
                        <div class="plcol"><span class="plhead h-fit">Fit</span><div class="plmeta"><span class="plcount">1</span></div></div>
                        <div class="plcol"><span class="plhead h-inv">Inv</span><div class="plmeta"><span class="plcount">1</span></div></div>
                        <div class="plcol"><span class="plhead h-paid">Paid</span><div class="plmeta"><span class="plcount">0</span></div></div>
                      </div>
                      <p class="plnote">This board <b>keeps itself up to date</b>: every twenty seconds it quietly asks whether
                         anything has moved, and reloads only if something has. There is <b>no refresh badge or spinner</b> on the
                         real screen &mdash; nothing tells you it happened, the cards simply change.</p>
                    </div>
                    <div class="cmpcol">
                      <div class="flagcap">List &mdash; the work (Orders)</div>
                      <div class="listmock">
                        <div class="lhd"><span class="lttl">Orders</span><span class="lnew">+ New quote</span></div>
                        <div class="lsub">Accepted onward &mdash; orders, invoices and paid jobs.</div>
                        <div class="lchips">
                          <span class="lchip on">All (7)</span><span class="lchip">Accepted (2)</span><span class="lchip">Ordered (3)</span>
                          <span class="lchip">Fitted (1)</span><span class="lchip">Invoiced (1)</span>
                          <span class="lchip larch">&#128452; Archived (3)</span>
                        </div>
                        <div class="lrow">PRE-2026-0039 &middot; R Ellis &middot; Accepted</div>
                        <div class="lrow">PRE-2026-0033 &middot; Kaur &amp; Sons &middot; Ordered</div>
                        <div class="lrow">PRE-2026-0028 &middot; M Sowerby &middot; Fitted</div>
                      </div>
                      <p class="plnote">No <b>Paid</b> chip &mdash; the List <b>hides any chip whose count is nought</b>, and this tenant has
                         no paid jobs yet. And no <b>Quote</b> or <b>Declined</b> rows either: the <b>List</b> button lands you on
                         <b>Orders</b> (accepted onward). Drafts, sent and declined jobs live on the <b>Quotes</b> list, a separate view.</p>
                    </div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Work &rarr; Pipeline. Your Quotes and Orders, drawn as one board.</b>
                  <b class="c2"><span class="n">2</span> Quote &middot; Declined &middot; Accepted &middot; Ordered &middot; Fitted &middot; Invoiced &middot; Paid.</b>
                  <b class="c3"><span class="n">3</span> Name, quote number, postcode, value, and when it last moved.</b>
                  <b class="c4"><span class="n">4</span> Not sent &middot; Paid / Part paid &middot; &pound; outstanding.</b>
                  <b class="c5"><span class="n">5</span> Search, Window, All time, Mine only &mdash; and the fifty-card cap.</b>
                  <b class="c6 err"><span class="n">6</span> Cards don&rsquo;t drag. You move a job on the job&rsquo;s own page.</b>
                  <b class="c7"><span class="n">7</span> Six things move a card on their own.</b>
                  <b class="c8 good"><span class="n">8</span> Board to see the shape. List to work a job.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Pipeline</b> lives in the sidebar under <b>Work</b>, between <b>Calendar</b> and <b>Factory</b>. It is <b>not</b> a
             second set of records to keep up to date &mdash; it is the same jobs that already sit in your <b>Quotes</b> and <b>Orders</b>
             lists, drawn as a board instead of a table. Under the page title you&rsquo;ll find two little buttons in a grey tray,
             <b>List</b> and <b>Pipeline</b>, and they swap between the two ways of looking at that same set of records.</p>

          <p>One difference is worth knowing before you go hunting: the board shows the <b>whole funnel at once</b>, while the <b>List</b>
             shows <b>one half at a time</b>. Press <b>List</b> from here and you land on <b>Orders</b> &mdash;
             <em>&ldquo;Accepted onward &mdash; orders, invoices and paid jobs&rdquo;</em>. Your drafts, sent quotes and declined jobs
             &mdash; the board&rsquo;s <b>Quote</b> and <b>Declined</b> columns &mdash; are on the <b>Quotes</b> list instead
             (<em>&ldquo;Quotes still in the pipeline &mdash; drafts, sent, and declined&rdquo;</em>), which you reach from the sidebar.
             The board also still shows <b>archived</b> jobs, which the List tucks away behind its <b>Archived</b> chip. Rule of thumb: the
             <b>List</b> is for finding and working one job; the <b>board</b> is for seeing the shape of the whole week in one glance
             &mdash; where the jobs are stacked up, and where the money is sitting.</p>

          <p class="prose"><b>1) Reading the board.</b></p>
          <ul class="steps">
            <li><b>Seven columns, in the order a job travels:</b> <b>Quote</b>, <b>Declined</b>, <b>Accepted</b>, <b>Ordered</b>,
                <b>Fitted</b>, <b>Invoiced</b>, <b>Paid</b>. <b>Quote</b> deliberately holds <em>two</em> kinds of job &mdash; one you have
                drafted and one you have sent &mdash; because a draft is simply a quote that hasn&rsquo;t gone out yet; the card tells you
                which is which. <b>Declined</b> has a column of its own so a lost job never clutters the live ones. On a narrow screen the
                board <b>scrolls sideways</b>, and each column scrolls on its own.</li>
            <li><b>Under each column name: the count, then the money.</b> Bold number = how many jobs are sitting in that stage. The grey
                figure next to it = the total value of them, to the nearest pound. Top right of the filter bar does the same for the whole
                board: <code>12 jobs &middot; &pound;17,250 in pipeline</code> &mdash; and it really is just the columns added up, count
                and money both, so the two always tally. If your user record doesn&rsquo;t have
                <b>&ldquo;View costs&rdquo;</b>, <b>every &pound; on this page disappears</b> &mdash; the &pound; half of the summary (the
                job count stays), the column totals, the card totals, the outstanding line, <em>and</em> the amber <b>Part paid</b> chip,
                because that one only exists to tell you how much of the money is in. The plain green <b>Paid</b> chip is the one thing that
                <b>stays</b>: &ldquo;this is settled&rdquo; is a fact, not a figure. So a costs-blind user sees counts, and a green Paid or
                nothing. Nothing is broken; it&rsquo;s deliberate.</li>
            <li><b>A card is one job.</b> The customer&rsquo;s name in bold (<code>No name</code> if it was never filled in), the quote
                number underneath in typewriter letters with the postcode after it, then the value on the left and the <b>age</b> on the
                right &mdash; <em>just now</em>, <em>12m ago</em>, <em>5h ago</em>, <em>3d ago</em>, <em>2w ago</em>, and after a month a plain
                date like <em>14 Aug</em>. Hover the age and it gives you the exact moment: <code>Last touched: 2026-09-18 14:22:07</code>.
                The <b>whole card is a link</b> &mdash; click anywhere on it and that quote opens.</li>
            <li><b>Newest-touched first.</b> Each column is sorted by when the job was last changed, so anything you&rsquo;ve just been
                working on floats to the top and the jobs nobody has touched sink to the bottom. Reading down a column is reading from
                &ldquo;live&rdquo; to &ldquo;forgotten&rdquo;.</li>
            <li><b>Three flags do the real work.</b> <b>Not sent</b> &mdash; a small amber pill on the card body, only on a draft, titled
                <code>This quote hasn&rsquo;t been sent to the customer yet</code>. It means that job is still sitting on your desk.
                <b>Paid</b> (green) or <b>Part paid</b> (amber) &mdash; a separate little chip pinned to the card&rsquo;s <b>top-right
                corner</b>, and it shows on a card in <b>any</b> column, so a job that was paid up front is just as obvious sitting in
                <em>Accepted</em> as it is in <em>Paid</em>. Hover them for <code>Paid in full</code> or
                <code>&pound;1,785.00 received of &pound;2,145.00</code>. And <b>&pound;360.00 outstanding</b> in red, which appears
                <b>only</b> on cards in the <b>Invoiced</b> column &mdash; that is your chasing list, without opening a thing.</li>
          </ul>

          <p class="prose"><b>2) Narrowing it down</b> &mdash; the filter bar across the top.</p>
          <ul class="steps">
            <li><b>Window</b> is a dropdown with five choices: <em>Last 30 days</em>, <em>Last 60 days</em>, <em>Last 90 days</em>,
                <em>Last 180 days</em>, <em>Last 365 days</em>. It starts on <b>Last 90 days</b>, and it <b>sends the page off the moment
                you change it</b> &mdash; you don&rsquo;t press Apply for this one. This is the single commonest reason a first-timer thinks
                a job has vanished: it hasn&rsquo;t, it&rsquo;s just older than the window.</li>
            <li><b>All time</b> is a <b>link</b>, not a switch. Click it and the date window is ignored altogether; once it&rsquo;s on it
                reads <b>All time &times;</b> in your brand colour, and clicking it again turns it back off. Reach for it whenever you are
                hunting for something from last year.</li>
            <li><b>The search box</b> takes a customer name, a quote number <em>or</em> a postcode &mdash; the placeholder says so:
                <code>Search customer / quote # / postcode&hellip;</code>. Part of a word is enough. Type it and press <b>Apply</b> (the
                small grey button).</li>
            <li><b>Mine only</b> is the other link-chip, and it narrows the board to jobs you are the salesperson on, or have a fitting
                booked on. <b>You will only see this chip if you can see everyone&rsquo;s jobs.</b> If your record doesn&rsquo;t have
                <b>&ldquo;View all customer jobs&rdquo;</b>, you are already limited to your own, so there is nothing to narrow and the chip
                isn&rsquo;t drawn. That is why the fitter&rsquo;s screen and the manager&rsquo;s screen don&rsquo;t match.</li>
            <li><b>A very full column shows the newest fifty</b> and tells you so along the bottom:
                <code>Showing 50 of 63 &middot; refine the window or search</code>. Nothing has been lost &mdash; the <b>count and the
                &pound; total at the top of that column are still the true ones</b>; only the cards have been trimmed so the page stays
                quick. Tighten the Window or search for what you want.</li>
          </ul>

          <p class="prose"><b>3) Moving a job.</b> You <b>cannot drag a card</b> from one column to the next, and that is on purpose.
             Moving a job fires real events &mdash; it stamps the sent and accepted dates, it puts a fitting in the calendar, it emails your
             suppliers their lines, it gives the customer a promised date. Nobody should be able to set all that off with a slipped mouse.
             So you open the job (click the card) and press a button on the quote&rsquo;s own page.</p>
          <ul class="steps">
            <li><b>The buttons</b> are <b>Mark as accepted</b>, <b>Mark as declined</b>, <b>Mark as ordered</b>, <b>Mark as fitted</b>,
                <b>Mark as invoiced</b>, <b>Reopen as draft</b>, plus the blue <b>&#128230; Save as order</b> which accepts the quote and
                carries you straight on to the place-order screen. Declining asks first:
                <code>Mark this quote as declined?</code></li>
            <li><b>Only sensible moves are offered.</b> From a <b>draft</b> you can go to <em>sent</em>, <em>accepted</em> or
                <em>declined</em>. From <b>sent</b>: <em>accepted</em>, <em>declined</em>, or back to draft. From <b>accepted</b>:
                <em>ordered</em>, <em>fitted</em>, or back to draft. From <b>declined</b>: back to draft only. From <b>ordered</b>:
                <em>fitted</em>, <em>invoiced</em>, or back to draft. From <b>fitted</b>: <em>invoiced</em>, back to <em>ordered</em>, or
                back to draft. From <b>invoiced</b>: back to draft only. And a <b>paid</b> job doesn&rsquo;t move at all &mdash; try it and
                you get <code>Can&rsquo;t move from paid to draft.</code></li>
            <li><b>Two permissions govern it.</b> The sales-side moves (<em>sent</em>, <em>accepted</em>, <em>declined</em>) need
                <b>&ldquo;Create quotes&rdquo;</b> on your user record; the order-side ones (<em>ordered</em>, <em>invoiced</em>) need
                <b>&ldquo;Create orders&rdquo;</b>. Without it you get
                <code>You don&rsquo;t have permission to mark this quote as &ldquo;ordered&rdquo;.</code> Admins bypass both.</li>
          </ul>

          <p class="prose"><b>4) The moves that happen without you.</b> This is why the board changes while you are watching it &mdash; and
             the commonest &ldquo;who touched this?&rdquo; of the lot. Six things move a card on their own:</p>
          <ul class="steps">
            <li><b>The customer opens the link you sent.</b> The moment they view it, a draft flips to <b>sent</b> &mdash; so the
                <em>Not sent</em> pill disappearing is your read receipt.</li>
            <li><b>They accept it online.</b> The job lands in <b>Accepted</b>, and a placeholder fitting is dropped onto the calendar:
                <code>Installation appointment is in the calendar&rsquo;s &ldquo;Pending Fitting&rdquo; tray &mdash; drag it onto the right
                date and assign a fitter when ready.</code> (Decline it later and you&rsquo;ll be told
                <code>The pending fitting has been removed from the calendar.</code>)</li>
            <li><b>Every blind on the order is one you make yourself.</b> It skips straight past Accepted into <b>Ordered</b> and the
                workshop is told: <code>Status: ordered. Due 3 Oct 2026.</code> followed by
                <code>Sent straight to the workshop &mdash; all in-house, no supplier order needed.</code> If there is a bought-in line on
                it instead, you get <code>Order accepted &mdash; place it below (suppliers get emailed their lines).</code></li>
            <li><b>The fitting appointment is marked completed</b> on the calendar &mdash; the job moves to <b>Fitted</b>:
                <code>Status updated to completed. Linked quote PRE-2026-0042 advanced to &ldquo;fitted&rdquo;.</code></li>
            <li><b>You email the invoice</b> &mdash; it moves to <b>Invoiced</b> on sending:
                <code>Invoice emailed to jane@example.com. Marked as Invoiced.</code></li>
            <li><b>Paid is never something you press.</b> A job only reaches <b>Paid</b> when the deposit and the recorded payments actually
                cover the total. So <em>Paid</em> on this board always means the money is genuinely in.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Your colours, everywhere.</b> Those column colours aren&rsquo;t decoration
             and they aren&rsquo;t fixed &mdash; they come from <b>Settings &rarr; Status colours</b>, and the <b>calendar</b> and the
             <b>orders list</b> use the very same set. A job is the same colour wherever you meet it, so change one and you change all three.
             Worth setting once, properly, at the start.</div></div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>This is the sales funnel, not the workshop.</b> A job reads <b>Ordered</b>
             here from the minute it is placed until the day it is fitted &mdash; however far through the making it actually is. Half-cut,
             half-assembled and boxed up ready all look identical on this board. The workshop&rsquo;s own progress &mdash; which bench a job
             is on, what has been scanned, what is waiting &mdash; lives on the <b>Factory</b> screens. Don&rsquo;t go hunting for production
             detail here. And there is no retail/trade split on the board either: a trade job and a retail job sit side by side in the same
             column. That split lives on the <b>List</b>.</div></div>

          <div class="oops"><b>If something looks wrong:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><b>A column looks empty and you know there are jobs in it</b> &mdash; it&rsquo;s the <b>90-day window</b>. Press
                   <b>All time</b>, or widen <b>Window</b>.</li>
               <li><b>A job you expected isn&rsquo;t there at all</b> &mdash; someone else is the salesperson on it and your record
                   doesn&rsquo;t have <code>View all customer jobs</code>. Ask whoever manages users.</li>
               <li><b>No &pound; figures anywhere</b> &mdash; <code>View costs</code> is switched off on your user record. Same again.</li>
               <li><b>A card sitting in Quote with an amber <em>Not sent</em></b> &mdash; that one has never left your desk. Open it and
                   send it.</li>
               <li><b>The whole page is one red message</b> reading <code>The pipeline isn&rsquo;t available yet &mdash; the quotes table is
                   missing on this database. (Phase 3 schema rebuild hasn&rsquo;t shipped here.)</code> &mdash; this database hasn&rsquo;t
                   had its rebuild yet. There is nothing to fix at your end; tell support.</li>
             </ul></div>

          <p>The board <b>keeps itself up to date</b>. Every twenty seconds it quietly asks the server whether anything has moved, and only
             then does it refresh &mdash; so it never reloads under your nose while nothing is happening, and you never need to press F5.
             It only checks while the tab is actually on screen, so it costs nothing sitting in the background. Leave it open on a second
             monitor and it behaves.</p>

          <p>A good two-minute routine each morning: run your eye down the <b>Quote</b> column for anything flagged <b>Not sent</b> and get
             those out; look at the <b>Invoiced</b> column for anything in <b>red</b> and chase the money; then read the <b>ages</b> down the
             right-hand side of every column and pick out whatever nobody has touched in a fortnight. Then go to the <b>List</b> to actually
             work them &mdash; that&rsquo;s where quotes and orders are split apart, retail is separated from trade, your <b>Archived</b> jobs
             live, you can tick several at once, and there&rsquo;s a <b>+ New quote</b> button. Board to see the shape; List to do the work.</p>',
        'script'  => [
            ['0:00', 'Work → Pipeline; the List | Pipeline tray.',   'The Pipeline lives in the sidebar under Work, between Calendar and Factory. It is the same jobs that are already in your Quotes and Orders lists — nothing separate, nothing extra to keep up to date. The difference is that the board shows the whole funnel at once, while the List shows one half at a time. The two little buttons at the top, List and Pipeline, swap between them. The list is for finding and working one job; the board is for seeing the shape of the whole week at once.', 1],
            ['0:18', 'Seven columns, counts and money.',             'Seven columns, left to right, in the order a job travels. Quote holds both — a quote you have drafted and one you have sent — because a draft is only a quote that has not gone out yet. Declined has its own column so a lost job never clutters the live ones. Then Accepted, Ordered, Fitted, Invoiced and Paid. Under each name is how many jobs are in it, and how much money is sitting there.', 2],
            ['0:38', 'One card, blown up.',                          'Every job is a card. The customer name, the quote number and postcode underneath, the value of the job, and on the right how long since anything happened to it — three days ago, here. Hover that and it tells you the exact date and time. The newest-touched jobs float to the top of each column, so the stale ones sink to the bottom where you will notice them. Click anywhere on a card and it opens that quote.', 3],
            ['0:58', 'Not sent, Paid, and £ outstanding.',           'Three little flags earn their keep. Not sent, in amber, means that quote is still sitting with you — the customer has never seen it. The Paid chip in the corner shows on any card in any column, so a job that has been paid for up front is just as obvious sitting in Accepted as it is in Paid; Part paid means some of the money is in. And in the Invoiced column only, a card tells you in red exactly how much is still owed. That is your chasing list, without opening anything.', 4],
            ['1:18', 'Search, Window, All time, Mine only.',         'The board starts on the last ninety days, so an old job you cannot find probably is not missing — widen the Window, or press All time. Type a name, a quote number or a postcode in the search box and press Apply. Mine only narrows it to your own jobs. And if a column is very full, it shows the newest fifty and tells you so at the bottom — the count and the money at the top of the column are still the real ones.', 5],
            ['1:38', 'A card will not drag. Use the buttons.',       'You cannot drag a card from one column to the next, and that is deliberate. Moving a job does real things — it stamps dates, it puts a fitting in the calendar, it emails your suppliers, it gives the customer a promised date. Nobody should be able to set all that off with a slipped mouse. So you open the job and press the button: Mark as accepted, Mark as ordered, Reopen as draft.', 6],
            ['1:56', 'Six automatic movers.',                        'And some cards move by themselves, which is why the board changes while you are watching it. The moment a customer opens the link you sent, the quote flips from draft to sent. If they accept it online it lands in Accepted, and a placeholder fitting appears in the calendar Pending Fitting tray for you to drag onto the right day. If every blind on the order is one you make yourself, it goes straight past Accepted into Ordered and the workshop is told. Marking that fitting appointment completed moves the job to Fitted. Emailing the invoice moves it to Invoiced. And Paid is never something you press — a job only reaches Paid when the deposit and the payments actually cover the total. So Paid always means the money is in.', 7],
            ['2:22', 'Board for the shape, List for the work.',      'Use the board when you want the shape of things — where the money is stuck, who has not been chased, what is waiting to go out. Use the list when you want to work: it splits quotes from orders, separates retail from trade, has your archive, and lets you tick several jobs at once. Press List from here and you land on Orders, accepted onward; your drafts, sent quotes and declined jobs are on the Quotes list instead. And notice the list hides any chip whose count is nought, so a stage you have never used simply is not there. The board keeps itself up to date — it checks every twenty seconds and refreshes itself only when something has genuinely moved, so leave it open on a second screen.', 8],
        ],
];
