<?php
declare(strict_types=1);

/**
 * Guide: quote-build
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Scope: the NEW QUOTE form (/quote-builder/new.php) and the BUILDER
 * (/quote-builder/edit.php) — up to the point the quote is built. Sending,
 * accepting, ordering, invoicing and payments each have their own guide.
 *
 * Fill classes: the engine's f1..f5 only hold a value up to data-step="5"
 * (f1 alone persists to step 8). Scenes here are kept inside their window,
 * and the two later scenes use the guide-local g6 / g8 fill classes declared
 * in the css below.
 */

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Building a quote',
        'eyebrow' => 'Quotes',
        'blurb'   => 'Start a quote, then build it blind by blind — product, system, band, fabric, room, size, options — watching the live price and reading the totals.',
        'lede'    => 'This is the one you will use every day. It covers <b>both screens</b>: the short <b>New quote</b> form that
                      creates the job, and the <b>quote builder</b> where you add the blinds. You pick the <b>product</b>, let the
                      <b>system</b>, <b>band</b> and <b>fabric</b> cascade off it, name the <b>room</b>, type the <b>width</b> and
                      <b>drop</b>, and watch the <b>live price</b> go green before you can save. We go slowly, we show every field,
                      and we walk straight into the mistake everybody makes once &mdash; so that when it happens to you, you already
                      know what it means. <b>Sending it, accepting it, ordering and invoicing all have guides of their own.</b>',
        'open'    => '/quote-builder/new.php',
        'css'     => '
          /* ── scene switching ─────────────────────────────────────────────── */
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scNew, .gd .stage[data-step="1"] .scNew, .gd .stage[data-step="2"] .scNew{ display:block; }
          .gd .stage[data-step="3"] .scLand{ display:block; }
          .gd .stage[data-step="4"] .scCasc, .gd .stage[data-step="5"] .scCasc{ display:block; }
          .gd .stage[data-step="6"] .scSize{ display:block; }
          .gd .stage[data-step="7"] .scErr{ display:block; }
          .gd .stage[data-step="8"] .scOpts{ display:block; }

          /* ── guide-local persistent fills (the engine stops at f5) ────────── */
          .gd .stage[data-step="6"] .g6 .ph, .gd .stage[data-step="8"] .g8 .ph{ opacity:0; }
          .gd .stage[data-step="6"] .g6 .val, .gd .stage[data-step="8"] .g8 .val{ opacity:1; animation:gdRoll .8s ease-out both; }

          /* ── small shared scaffolding this guide needs ────────────────────── */
          .gd .ldesc2{ color:var(--faint); font-size:.68rem; margin:.18rem 0 .5rem; }
          .gd .ldesc2 b{ color:var(--soft); }
          .gd .c3{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:.6rem .7rem; margin-top:.5rem; }
          .gd .c4{ display:grid; grid-template-columns:1fr 1fr .8fr 1.3fr; gap:.6rem .7rem; margin-top:.5rem; }
          .gd .boxv{ height:30px; border:1px solid var(--line); border-radius:7px; background:var(--panel);
                     display:flex; align-items:center; padding:0 .5rem; font-size:.8rem; color:var(--ink); overflow:hidden; }
          /* .numbox is not global — same ph/val scaffolding as a .box, with spinners */
          .gd .numbox{ position:relative; display:flex; align-items:center; height:30px; width:5.4rem;
                       border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; padding:0 .5rem;
                       background:var(--surface); font-size:.8rem; color:var(--ink); overflow:hidden; }
          .gd .numbox .ph{ color:var(--faint); transition:opacity .15s; }
          .gd .numbox .val{ position:absolute; inset:0; display:flex; align-items:center; padding:0 .5rem;
                            color:var(--ink); opacity:0; white-space:nowrap; overflow:hidden; }
          .gd .numbox .spin{ position:absolute; right:.35rem; top:50%; transform:translateY(-50%); z-index:2;
                             display:flex; flex-direction:column; line-height:.72; font-size:.46rem; color:var(--faint); }
          .gd .chkline{ display:inline-flex; align-items:center; gap:.4rem; font-size:.72rem; color:var(--ink); margin-top:.3rem; }
          /* A .selectbox that fills in. The Product control on the real form is a
             <select> (edit.php:1162), so it must be drawn as one — but it also has
             to roll its value in at step 4, which the engine only scaffolds for
             .box. This gives a .selectbox the same .ph / .val pair, clear of the
             chevron the ::after draws on the right. */
          .gd .selectbox.fillv{ position:relative; }
          .gd .selectbox.fillv .ph{ color:var(--faint); transition:opacity .15s; }
          .gd .selectbox.fillv .val{ position:absolute; left:.6rem; right:1.7rem; top:0; bottom:0;
                                     display:flex; align-items:center; color:var(--ink); opacity:0;
                                     white-space:nowrap; overflow:hidden; }
          .gd .qbtn{ display:inline-flex; align-items:center; gap:.3rem; border-radius:8px; padding:.4rem .8rem;
                     font-size:.76rem; font-weight:600; }
          .gd .qbtn.pri{ background:var(--accent); color:#fff; }
          .gd .qbtn.ghost{ background:var(--surface); border:1px solid var(--line); color:var(--soft); }
          .gd .qbtn.off{ background:var(--line); color:var(--faint); }
          .gd .savebar{ margin-top:.7rem; display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
          .gd .offnote{ font-size:.64rem; color:var(--faint); font-style:italic; }

          /* ── the "New quote" screen ───────────────────────────────────────── */
          .gd .custres{ display:none; border:1px solid var(--border-strong,#c7ccd4); border-radius:8px;
                        background:var(--surface); margin-top:.25rem; padding:.2rem; max-width:24rem;
                        box-shadow:0 8px 20px rgba(0,0,0,.08); }
          .gd .stage[data-step="1"] .custres{ display:block; }
          .gd .custres .cr{ padding:.28rem .45rem; border-radius:6px; font-size:.74rem; color:var(--ink); }
          .gd .custres .cr.on{ background:var(--accent-wash); }
          .gd .stage[data-step="2"] .wa{ background:var(--accent); color:#fff; }
          .gd .pcbox{ border:1px dashed var(--line); border-radius:8px; padding:.35rem .55rem; margin-top:.5rem;
                      font-size:.68rem; color:var(--faint); background:var(--panel); }

          /* ── sticky quote bar (builder only: steps 3+) ────────────────────── */
          .gd .qbar{ display:none; align-items:center; gap:.4rem; flex-wrap:wrap; background:var(--nav); color:#fff;
                     border-radius:8px; padding:.42rem .65rem; font-size:.72rem; margin-bottom:.7rem; }
          .gd .stage[data-step="3"] .qbar, .gd .stage[data-step="4"] .qbar, .gd .stage[data-step="5"] .qbar,
          .gd .stage[data-step="6"] .qbar, .gd .stage[data-step="7"] .qbar, .gd .stage[data-step="8"] .qbar{ display:flex; }
          .gd .qbar .qn{ font-weight:700; }
          /* The real .status-pill is text-transform:uppercase (edit.php:268), so a
             pill on a draft quote reads DRAFT, not Draft. */
          .gd .qpill{ font-size:.58rem; font-weight:700; border-radius:20px; padding:.06rem .5rem;
                      background:rgba(255,255,255,.22); text-transform:uppercase; letter-spacing:.05em; }
          .gd .qbar .mini{ font-size:.6rem; border:1px solid rgba(255,255,255,.35); border-radius:6px; padding:.08rem .38rem; }
          .gd .qbar .mini.acc{ background:#16a34a; border-color:#16a34a; }
          .gd .qbar .qtot{ margin-left:auto; font-weight:700; }

          /* ── landing scene ────────────────────────────────────────────────── */
          .gd .actrow{ display:flex; gap:.35rem; flex-wrap:wrap; margin-bottom:.6rem; }
          .gd .summ{ border:1px solid var(--line); border-radius:8px; background:var(--panel);
                     padding:.42rem .6rem; font-size:.76rem; color:var(--ink); }
          .gd .summ .tri{ color:var(--faint); margin-right:.3rem; }
          .gd .summ .cs-hint{ color:var(--faint); font-size:.68rem; }
          .gd .twocol{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem; margin-top:.6rem; }
          .gd .colbox{ border:1px dashed var(--line); border-radius:9px; padding:.45rem .6rem; font-size:.7rem; color:var(--faint); }
          .gd .colbox b{ display:block; color:var(--soft); font-size:.62rem; text-transform:uppercase;
                         letter-spacing:.04em; margin-bottom:.2rem; }

          /* ── cascade scene ────────────────────────────────────────────────── */
          .gd .asleep{ border-style:dashed; color:var(--faint); background:var(--panel); }
          .gd .beforestrip{ border:1px solid var(--line-2); border-radius:9px; background:var(--panel);
                            padding:.4rem .55rem; margin-top:.6rem; }
          .gd .beforestrip .bs-t{ font-size:.6rem; text-transform:uppercase; letter-spacing:.05em;
                                  color:var(--faint); font-weight:700; margin-bottom:.3rem; }
          .gd .bs-row{ display:flex; gap:.4rem; flex-wrap:wrap; align-items:center; }
          .gd .bs-row .selectbox{ min-width:0; font-size:.68rem; padding:.22rem .45rem; }
          /* Fabric is a text input, not a dropdown — so the asleep strip draws it
             as a box with no chevron (edit.php:1195). */
          .gd .bs-row .boxv{ height:auto; min-width:0; font-size:.68rem; padding:.22rem .45rem; }
          .gd .fabres{ display:none; border:1px solid var(--border-strong,#c7ccd4); border-radius:8px;
                       background:var(--surface); margin-top:.25rem; padding:.2rem; max-width:19rem;
                       box-shadow:0 8px 20px rgba(0,0,0,.08); }
          .gd .stage[data-step="5"] .fabres, .gd .stage[data-step="5"] .roompop{ display:block; }
          .gd .fabres .fr{ padding:.26rem .45rem; border-radius:6px; }
          .gd .fabres .fr.on{ background:var(--accent-wash); }
          .gd .fabres .fname{ font-size:.74rem; color:var(--ink); }
          .gd .fabres .fmeta{ font-size:.62rem; color:var(--faint); }
          .gd .fabres .empty{ padding:.26rem .45rem; font-size:.68rem; color:var(--faint); font-style:italic; }
          .gd .roomwrap{ position:relative; }
          .gd .roomwrap .chev{ position:absolute; right:.45rem; top:.42rem; font-size:.66rem; color:var(--faint); }
          .gd .roompop{ display:none; border:1px solid var(--border-strong,#c7ccd4); border-radius:8px;
                        background:var(--surface); margin-top:.25rem; padding:.2rem; max-width:13rem;
                        box-shadow:0 8px 20px rgba(0,0,0,.08); font-size:.72rem; }
          .gd .roompop div{ padding:.18rem .4rem; border-radius:5px; color:var(--ink); }
          .gd .roompop div.on{ background:var(--accent-wash); }
          .gd .roompop .more{ color:var(--faint); font-style:italic; font-size:.64rem; }

          /* ── unit dropdown (size scene) ───────────────────────────────────── */
          .gd .unitpop{ border:1px solid var(--border-strong,#c7ccd4); border-radius:8px; background:var(--surface);
                        margin-top:.25rem; padding:.2rem; max-width:11rem; font-size:.72rem;
                        box-shadow:0 8px 20px rgba(0,0,0,.08); }
          .gd .unitpop div{ padding:.16rem .4rem; border-radius:5px; color:var(--ink); }
          .gd .unitpop div.on{ background:var(--accent-wash); font-weight:600; }

          /* ── live price box ───────────────────────────────────────────────── */
          .gd .prev{ margin-top:.7rem; border-radius:8px; padding:.5rem .65rem; font-size:.73rem; max-width:26rem; }
          .gd .prev.idle{ background:var(--panel); color:var(--faint); font-style:italic; }
          .gd .prev.err{ background:#fee2e2; color:#991b1b; }
          .gd .prev.ok{ background:#d1fae5; color:#065f46; }
          .gd .prev.ok b{ color:#065f46; }
          .gd .prev2{ margin-top:.35rem; }

          /* ── options grid (final scene) ───────────────────────────────────── */
          .gd .optgroup{ border:1px solid var(--line); border-radius:9px; padding:.45rem .6rem; margin-top:.5rem; }
          .gd .optgroup.before{ border-style:dashed; background:var(--panel); }
          .gd .opthd{ font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint);
                      font-weight:700; margin-bottom:.32rem; }
          .gd .optrow{ display:flex; align-items:center; gap:.5rem; font-size:.73rem; margin:.24rem 0; flex-wrap:wrap; }
          .gd .optrow > label{ min-width:6.4rem; color:var(--faint); font-size:.62rem; text-transform:uppercase; letter-spacing:.03em; }
          .gd .optrow .selectbox{ min-width:8.5rem; font-size:.74rem; padding:.26rem .5rem; }
          .gd .multi{ display:flex; gap:.9rem; flex-wrap:wrap; }
          .gd .child{ margin-left:.7rem; padding-left:.6rem; border-left:2px solid var(--line); }
          .gd .capn{ font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint);
                     font-weight:700; margin:.3rem 0 .15rem; }
          .gd .ovr{ border:1px solid var(--line); border-radius:8px; padding:.4rem .6rem; margin-top:.5rem; }
          .gd .ovr .sum{ font-size:.73rem; color:var(--soft); }
          .gd .ovr .sum .tri{ color:var(--faint); margin-right:.3rem; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / quote-builder</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a class="on">Quotes</a>
                <div class="navh">Setup</div>
                <a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- sticky bar — builder screen only (steps 3+) -->
                <div class="qbar">
                  <span class="qn">Quote PRE-2026-0042</span>
                  <span class="qpill">Draft</span>
                  <span class="mini acc">&check; Customer accepted</span>
                  <span class="mini">&#10005; Customer declined</span>
                  <span class="qtot">Total &pound;0.00</span>
                </div>

                <!-- ════════ SCENE 1 — the New quote form (steps 0-2) ════════ -->
                <div class="osc scNew">
                  <div class="card-t">New quote</div>
                  <p class="ldesc2">Pick an existing customer (their details auto-fill below), or type a new customer&rsquo;s name. You can flesh out the rest later from the editor.</p>

                  <div class="fld">
                    <label>Existing customer</label>
                    <div class="box f1"><span class="ph">Type to search by name, town, or postcode&hellip;</span><span class="val">Flet</span></div>
                    <div class="custres">
                      <div class="cr on">Emma Fletcher &mdash; Leamington Spa &mdash; CV32 5PJ</div>
                      <div class="cr">Gordon Fletcher &mdash; Kenilworth &mdash; CV8 1AB</div>
                    </div>
                    <p class="ldesc2">Type to filter &mdash; leave blank for a new customer.</p>
                  </div>

                  <div class="fld" style="margin-top:.4rem">
                    <label>Customer name <span class="req">*</span></label>
                    <div class="box f2"><span class="ph">&nbsp;</span><span class="val">Emma Fletcher</span></div>
                  </div>

                  <div class="c3">
                    <div class="fld"><label>Email</label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">emma.f@example.co.uk</span></div></div>
                    <div class="fld"><label>Phone (landline)</label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">01926 555041</span></div></div>
                    <div class="fld">
                      <label>Mobile</label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">07700 900118</span></div>
                      <span class="chkline"><span class="tick wa">&check;</span> Mobile is on WhatsApp</span>
                    </div>
                  </div>

                  <div class="pcbox">Find by postcode &mdash; an optional extra: a postcode box and a <b>Find address</b> button, then a <b>Pick an address</b> list you choose the right one from. It only shows if it has been switched on for your company.</div>

                  <div class="fld" style="margin-top:.5rem"><label>Address line 1</label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">14 Warwick Place</span></div></div>
                  <div class="fld" style="margin-top:.4rem"><label>Address line 2</label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">Lillington</span></div></div>

                  <div class="c3">
                    <div class="fld"><label>Town</label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">Leamington Spa</span></div></div>
                    <div class="fld"><label>County</label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">Warwickshire</span></div></div>
                    <div class="fld"><label>Postcode</label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">CV32 5PJ</span></div></div>
                  </div>

                  <div class="fld" style="margin-top:.5rem"><label>Quote notes</label><div class="ta"><span class="ph">&nbsp;</span><span class="val"></span></div></div>

                  <div class="savebar">
                    <span class="qbtn pri">Create quote</span>
                    <span class="qbtn ghost">Cancel</span>
                  </div>
                </div>

                <!-- ════════ SCENE 2 — landing in the builder (step 3) ════════ -->
                <div class="osc scLand">
                  <div class="card-t">Quote actions</div>
                  <div class="actrow">
                    <span class="qbtn ghost">View PDF</span>
                    <span class="qbtn ghost">Download PDF</span>
                    <span class="qbtn ghost">Mark as sent</span>
                    <span class="qbtn ghost">Mark as accepted</span>
                    <span class="qbtn ghost">Mark as declined</span>
                  </div>
                  <p class="ldesc2">No <b>&#128230; Save as order</b> yet &mdash; that button only joins the row once the quote has at least one blind on it.</p>

                  <div class="summ"><span class="tri">&#9654;</span>Customer: Emma Fletcher &mdash; Leamington Spa &mdash; CV32 5PJ <span class="cs-hint">(click to edit)</span></div>
                  <p class="ldesc2">Folded away to keep the screen short. Click the line to open the full name / email / phone / address form.</p>

                  <div class="fld" style="margin-top:.4rem"><label>Quote notes</label><div class="ta"><span class="ph">&nbsp;</span><span class="val"></span></div></div>
                  <div class="savebar"><span class="qbtn pri">Save details</span></div>

                  <div class="twocol">
                    <div class="colbox"><b>Left column</b>Customer details, then <b>Add blind</b> &mdash; the form you will live in.</div>
                    <div class="colbox"><b>Right column</b>Blinds (0) &mdash; <em>No blinds yet</em> &mdash; then totals, deposit and payments.</div>
                  </div>
                </div>

                <!-- ════════ SCENE 3 — the cascade (steps 4-5) ════════ -->
                <div class="osc scCasc">
                  <div class="card-t">Add blind</div>
                  <div class="frow">
                    <div class="fld"><label>Product <span class="req">*</span></label><div class="selectbox fillv f4"><span class="ph">Choose product&hellip;</span><span class="val">Roller Blind</span></div></div>
                    <div class="fld"><label>System</label><div class="selectbox">Standard</div></div>
                  </div>
                  <div class="frow" style="margin-top:.5rem">
                    <div class="fld"><label>Band</label><div class="selectbox">A</div></div>
                    <div class="fld"></div>
                  </div>
                  <div class="frow" style="margin-top:.5rem">
                    <div class="fld">
                      <label>Fabric <span class="req">*</span></label>
                      <div class="box f5"><span class="ph">Type to search fabrics (or click for recent)</span><span class="val">Sunset White / Ivory</span></div>
                      <div class="fabres">
                        <div class="fr on"><div class="fname">Sunset White / Ivory</div><div class="fmeta">Louvolite &middot; Code SW-104</div></div>
                        <div class="fr"><div class="fname">Sunset White / Linen</div><div class="fmeta">Louvolite &middot; Code SW-106</div></div>
                        <div class="empty">&hellip; and when nothing matches: No matching fabrics.</div>
                      </div>
                    </div>
                    <div class="fld">
                      <label>Room name</label>
                      <div class="roomwrap">
                        <div class="box f5"><span class="ph">Type or pick &mdash; e.g. Living Room</span><span class="val">Living Room</span></div>
                        <span class="chev">&#9662;</span>
                      </div>
                      <div class="roompop">
                        <div>Kitchen / Diner</div><div>Landing</div><div class="on">Living Room</div><div>Lounge</div>
                        <div class="more">21 rooms in the list &mdash; or type your own</div>
                      </div>
                    </div>
                  </div>

                  <div class="beforestrip">
                    <div class="bs-t">Before you pick a product, these three are asleep</div>
                    <div class="bs-row">
                      <span class="selectbox asleep">Choose product first</span>
                      <span class="selectbox asleep">All bands</span>
                      <span class="boxv asleep">Choose product first</span>
                    </div>
                    <p class="ldesc2">System and Band are <b>dropdowns</b> sitting on those words; Fabric is a <b>text box</b> you cannot type in yet.</p>
                  </div>
                </div>

                <!-- ════════ SCENE 4 — unit, size, quantity, notes (step 6) ════════ -->
                <div class="osc scSize">
                  <div class="card-t">Add blind &mdash; the sizes</div>
                  <div class="fld" style="max-width:13rem">
                    <label>Measurement unit (this quote)</label>
                    <div class="selectbox">Millimetres (mm)</div>
                    <div class="unitpop">
                      <div class="on">Millimetres (mm)</div><div>Centimetres (cm)</div><div>Metres (m)</div><div>Inches (in)</div>
                    </div>
                    <p class="ldesc2">Re-displays this quote&rsquo;s sizes in the chosen unit. Sizes are stored the same way regardless.</p>
                  </div>

                  <div class="c4">
                    <div class="fld"><label>Width (mm) <span class="req">*</span></label><div class="box g6"><span class="ph">Width in mm</span><span class="val">1200</span></div></div>
                    <div class="fld"><label>Drop (mm) <span class="req">*</span></label><div class="box g6"><span class="ph">Drop in mm</span><span class="val">1500</span></div></div>
                    <div class="fld"><label>Quantity</label><div class="numbox g6"><span class="ph">1</span><span class="val">2</span><span class="spin">&#9650;<br>&#9660;</span></div></div>
                    <div class="fld"><label>Notes</label><div class="box g6"><span class="ph">Optional internal note</span><span class="val">Bay &mdash; left of three</span></div></div>
                  </div>
                  <p class="ldesc2">On a <b>per-slat</b> product the third label changes itself from <b>Quantity</b> to <b>Number of slats</b>.</p>

                  <div class="prev idle">Still need: width, drop.</div>
                  <div class="savebar">
                    <span class="qbtn off">Save</span>
                    <span class="qbtn off">Save and add another blind</span>
                    <span class="offnote">both greyed out until the price is green</span>
                  </div>
                </div>

                <!-- ════════ SCENE 5 — the slip and the fix (step 7) ════════ -->
                <div class="osc scErr">
                  <div class="card-t">Add blind &mdash; the price says no</div>
                  <div class="frow">
                    <div class="fld"><label>Product <span class="req">*</span></label><div class="selectbox">Roller Blind</div></div>
                    <div class="fld"><label>System</label><div class="selectbox" style="border-color:#ef4444;box-shadow:0 0 0 2px rgba(239,68,68,.25)">Grip Fit</div></div>
                  </div>
                  <div class="frow" style="margin-top:.5rem">
                    <div class="fld"><label>Band</label><div class="selectbox">A</div></div>
                    <div class="fld"><label>Fabric <span class="req">*</span></label><div class="boxv">Sunset White / Ivory</div></div>
                  </div>

                  <div class="prev err">No price table for Roller Blind band A on system &lsquo;Grip Fit&rsquo;.</div>
                  <div class="prev err prev2">Size 2400 &times; 3000 mm exceeds the largest cell in this price table.</div>

                  <div class="savebar">
                    <span class="qbtn off">Save</span>
                    <span class="qbtn off">Save and add another blind</span>
                    <span class="offnote">still disabled &mdash; a broken line cannot be saved</span>
                  </div>
                </div>

                <!-- ════════ SCENE 6 — options and save (step 8) ════════ -->
                <div class="osc scOpts">
                  <div class="card-t">Add blind &mdash; the options</div>

                  <div class="optgroup before">
                    <div class="optrow"><label>Fascia Options</label><span class="selectbox">Senses</span></div>
                    <div class="child">
                      <div class="optrow"><label>Fascia Sizing</label><span class="selectbox">Standard fascia</span></div>
                    </div>
                  </div>
                  <p class="ldesc2">The dashed box is <b>us</b>, not the screen. Options flagged <em>Show above the size fields</em> are simply drawn here, between <b>Measurement unit</b> and <b>Width / Drop</b> &mdash; with <b>no heading of any kind</b> over them.</p>

                  <div class="optgroup">
                    <div class="opthd">Options</div>
                    <div class="optrow"><label>Bottom Weight</label><span class="selectbox">Chained</span></div>
                    <div class="child">
                      <div class="optrow"><label>Colour</label><span class="selectbox">White</span></div>
                    </div>
                    <div class="optrow"><label>Optional Extras</label>
                      <span class="multi">
                        <span class="chkline"><span class="tick on">&check;</span> <em>first choice on your list</em></span>
                        <span class="chkline"><span class="tick">&check;</span> <em>second choice</em></span>
                        <span class="chkline"><span class="tick">&check;</span> <em>third choice</em></span>
                      </span>
                    </div>
                    <p class="ldesc2">Ticks, not a dropdown, because that option has <b>Allow multiple choices</b> set. The captions are whatever <em>your</em> product&rsquo;s list holds &mdash; we are not going to put words in your screen&rsquo;s mouth.</p>
                    <div class="optrow"><label>Fit height</label></div>
                    <p class="capn">Fit height</p>
                    <div class="numbox g8"><span class="ph">&nbsp;</span><span class="val">2100</span><span class="spin">&#9650;<br>&#9660;</span></div>
                    <p class="ldesc2">One choice plus a measurement &rarr; the pointless dropdown is hidden and the number box <b>is</b> the control. You get the option&rsquo;s name, then the measurement&rsquo;s own small caption over the box &mdash; on this one they happen to be the same words twice.</p>
                  </div>
                  <p class="ldesc2"><b>No prices anywhere in this grid.</b> Picking an option never puts a little &ldquo;+ &pound;5.00&rdquo; beside it. The money shows up in the green price line below (<em>+ extras &pound;5.00</em>) and, once saved, on the blind&rsquo;s own line in the table.</p>

                  <div class="ovr">
                    <div class="sum"><span class="tri">&#9662;</span>Adjust price for this blind</div>
                    <div class="frow" style="margin-top:.4rem">
                      <div class="fld"><label>Discount % (this blind)</label><div class="numbox" style="width:100%"><span class="ph">product default</span><span class="val"></span><span class="spin">&#9650;<br>&#9660;</span></div></div>
                      <div class="fld"><label>Markup % (this blind)</label><div class="numbox" style="width:100%"><span class="ph">product default</span><span class="val"></span><span class="spin">&#9650;<br>&#9660;</span></div></div>
                    </div>
                    <p class="ldesc2">Leave blank to use the product&rsquo;s set markup / discount. This only changes <b>this blind</b> on this quote. On a company set to <b>margin</b> rather than markup, that second label reads <b>Margin % (this blind)</b> and the sentence says <em>margin</em> instead.</p>
                  </div>

                  <div class="prev ok"><b>&pound;50.00</b> per blind &middot; base &pound;45.00 &middot; + extras &pound;5.00</div>
                  <div class="savebar">
                    <span class="qbtn pri">Save</span>
                    <span class="qbtn ghost">Save and add another blind</span>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> New quote &rarr; type a few letters of the name, town or postcode.</b>
                  <b class="c2"><span class="n">2</span> Pick them and their details drop in. Then Create quote.</b>
                  <b class="c3"><span class="n">3</span> You land on a draft. Every panel saves itself.</b>
                  <b class="c4"><span class="n">4</span> Product first &mdash; System, Band and Fabric wake up.</b>
                  <b class="c5"><span class="n">5</span> Search the fabric, then name the room.</b>
                  <b class="c6"><span class="n">6</span> Unit, width, drop, quantity, internal note.</b>
                  <b class="c7 err"><span class="n">7</span> Red price = no price for that band on that system.</b>
                  <b class="c8 good"><span class="n">8</span> Options, green price, Save.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>There are <b>two screens</b>. The short <b>New quote</b> form creates the job and the customer behind it. The
             <b>builder</b> is where you spend your time &mdash; one blind at a time, with the price working itself out as you go.</p>

          <ul class="steps">
            <li><b>Start it.</b> From Quotes, click <b>+ New quote</b>. The first box is <b>Existing customer</b> &mdash; a plain
                text box you type into (<em>&ldquo;Type to search by name, town, or postcode&hellip;&rdquo;</em>). Two or three letters
                is plenty; the list underneath shows <em>Name &mdash; Town &mdash; Postcode</em> so you can tell two Fletchers apart.
                Click the right one and the browser <b>copies their details down the form for you</b> &mdash; name, email, landline,
                mobile, the <b>Mobile is on WhatsApp</b> tick, address, town, county and postcode. That is the one place on this screen
                where boxes fill themselves; everywhere else you type. For a brand-new customer just <b>leave the search box empty</b>
                and type their name into <b>Customer name</b> instead &mdash; that is the only <b>required</b> field, and leaving it
                out gets you <em>&ldquo;Customer name is required.&rdquo;</em></li>
            <li><b>Fill in what you know.</b> Email, Phone (landline), Mobile, the WhatsApp tick, Address line 1 and 2, Town, County,
                Postcode and <b>Quote notes</b>. If <b>Find by postcode</b> has been switched on for your company you get a postcode
                box that fills the address for you. None of it is compulsory &mdash; you can finish it later from the builder &mdash;
                but the email and mobile are what the quote is later sent with, so put them in now if you have them. Then
                <b>Create quote</b>. You get <em>&ldquo;Quote PRE-2026-0042 created.&rdquo;</em> and land in the builder.</li>
            <li><b>Coming from the calendar instead.</b> If you raise the quote from a measure appointment, the blue <b>Start quote</b>
                button on that appointment carries everything across &mdash; and it prefers the appointment&rsquo;s <b>installation
                address</b> over the address on the customer record, which is usually what you want. When the appointment already has
                a customer and a name, this screen is skipped entirely and you drop straight into the builder with the blind form open.
                If something is missing you see <em>&ldquo;Could not start the quote automatically &mdash; please check the details
                below and click Create quote.&rdquo;</em> and you finish it by hand.</li>
          </ul>

          <p><b>In the builder.</b> A dark bar sits at the top with the quote number, a status pill, the one-tap
             <b>&check; Customer accepted</b> / <b>&#10005; Customer declined</b> buttons and the running <b>Total</b>. Below that is a
             <b>Quote actions</b> row &mdash; View PDF, Download PDF, the status buttons (<b>Mark as sent</b>, <b>Mark as accepted</b>,
             <b>Mark as declined</b> on a draft) and, once there is at least one blind on the quote, <b>&#128230; Save as order</b>. The
             customer block is <b>folded shut</b>: a single line reading <em>&ldquo;Customer: Emma Fletcher &mdash; Leamington Spa
             &mdash; CV32 5PJ (click to edit)&rdquo;</em>. Click it to open the whole address form, which has its own <b>Save details</b>
             button. <b>Quote notes</b> deliberately sits outside the fold so it stays in view.</p>

          <p><b>There is no big &ldquo;Save quote&rdquo; button, because every panel saves itself.</b> Save details saves the customer.
             <b>Set</b> saves the WT and the override. <b>Save deposit</b> saves the deposit. <b>Save</b> saves the blind. Nothing is
             waiting on a final button &mdash; if you clicked the button next to it, it is saved.</p>

          <ul class="steps">
            <li><b>Product first &mdash; always.</b> Until you choose one, <b>System</b> is greyed out reading <em>Choose product
                first</em>, <b>Band</b> is greyed out on <em>All bands</em>, and the <b>Fabric</b> box will not let you type. Pick the
                product and all three wake up: System fills with that product&rsquo;s systems and pre-picks its default, Band narrows
                to just that system&rsquo;s bands (and if there is only one, it picks it for you), and Fabric turns into a live search.
                <b>These labels rename themselves per product</b> &mdash; &ldquo;Fabric&rdquo; may read <b>Slat</b> or <b>Colour</b>,
                &ldquo;Band&rdquo; may read <b>Tape / String</b>. Same boxes, same order, different words.</li>
            <li><b>Find the fabric.</b> Click the box (<em>&ldquo;Type to search fabrics (or click for recent)&rdquo;</em>) and type a
                name, colour or code. A little panel drops down; each row shows the <b>name and colour</b> on top and a grey
                <b>supplier and code</b> underneath &mdash; <em>Louvolite &middot; Code SW-104</em>. Click the row. Nothing matching
                gives you <em>&ldquo;No matching fabrics.&rdquo;</em>; a dropped connection gives
                <em>&ldquo;Could not search fabrics.&rdquo;</em> &mdash; try it again in a moment.</li>
            <li><b>Name the room.</b> Click the box or the little <b>&#9662;</b> beside it and a list of twenty-one rooms opens &mdash;
                Living Room, Master Bedroom, Kitchen / Diner, En-suite and so on &mdash; filtering as you type. Or ignore the list and
                type your own. The room is only a label, but it is the label the fitter and the workshop read on the ticket, so
                <b>always fill it in</b>. &ldquo;Blind 3&rdquo; helps nobody on a landing with four windows.</li>
            <li><b>Sizes.</b> <b>Measurement unit (this quote)</b> offers Millimetres (mm), Centimetres (cm), Metres (m) and Inches
                (in). It is set <b>per quote, not per blind</b>: change it and every size already on the quote is re-displayed in the
                new unit &mdash; the stored measurements never change. You can also just type <code>150cm</code>, <code>1.5m</code> or
                <code>60in</code> straight into Width and it is read for you; something it cannot read comes back as
                <em>&ldquo;Could not read width &quot;abc&quot;.&rdquo;</em> &mdash; straight double quotes round whatever you
                typed, which is how the app prints it back at you. <b>Quantity</b> is how many identical blinds &mdash; on
                a per-slat product that label changes itself to <b>Number of slats</b>. <b>Notes</b> is <b>internal</b>: it prints on
                your paperwork, not on the customer&rsquo;s quote.</li>
          </ul>

          <div class="oops"><b>&ldquo;No price table for Roller Blind band A on system &lsquo;Grip Fit&rsquo;.&rdquo;</b> The slip
             everybody makes once. The <b>Band</b> dropdown only <b>filters the fabric list</b> &mdash; it does not promise a price.
             The price lives on the <b>combination</b> of product + system + band. <b>Fix:</b> change the <b>System</b> to one that is
             priced for that band (the Band list re-scopes as you do), or pick a band that has a price list, then reselect the fabric.
             Its cousin <em>&ldquo;No price table set up for Roller Blind for system &lsquo;Grip Fit&rsquo;.&rdquo;</em> means that
             system has no prices at all yet.</div>

          <div class="oops"><b>&ldquo;Size 2400 &times; 3000 mm exceeds the largest cell in this price table.&rdquo;</b> The size is
             past the end of the grid. <b>Before you blame the price list, check the unit</b> &mdash; 150 typed while the quote is in
             millimetres is a 15&nbsp;cm blind; 1500 typed while it is in centimetres is fifteen metres. Related wordings you may see:
             <em>&ldquo;Width 2400 mm exceeds the largest entry in this price list.&rdquo;</em> and
             <em>&ldquo;No &pound;/m&sup2; rate set for Roller Blind in this price list.&rdquo;</em> What you will <b>never</b> see
             here is a complaint that your exact size is not in the grid: the builder always <b>rounds up to the next cell</b>, so an
             odd size between two rows simply prices at the larger one. Only a size past the <em>end</em> of the table stops it.</div>

          <p><b>The live price box does the checking for you.</b> Grey and italic means it is still waiting &mdash;
             <em>&ldquo;Still need: product, fabric, width, drop.&rdquo;</em>. Red means it tried and could not price it. Green means
             you are good: <em>&ldquo;&pound;50.00 per blind &middot; base &pound;45.00 &middot; + extras &pound;5.00&rdquo;</em>, or
             for more than one, <em>&ldquo;&pound;90.00 for 2 blinds &middot; &pound;45.00 each&rdquo;</em>. If you are allowed to see
             costs it also tacks on the markup or margin, the discount, and <em>trade discount 15%</em> on a trade line. <b>Both save
             buttons start disabled and stay disabled until that box is green</b> &mdash; they are greyed and will not click. That is
             deliberate: a blind with no price cannot go onto a quote.</p>

          <p><b>Options.</b> Once the product loads, a section headed simply <b>Options</b> appears, and it will not look the same on
             two products, because options are set up per product. Some are a plain <b>dropdown</b> (with a &ldquo;&mdash; Select
             &mdash;&rdquo; first line only when nothing is set as the default). Some are a <b>list of tick-boxes</b> where you can
             have more than one &mdash; that is an option with <b>Allow multiple choices</b> ticked in its setup. Some are just a
             <b>number to type</b>: when an option has a measurement box and only one thing to pick, the pointless dropdown is hidden
             and the number box is the whole control, under a small capitalised caption &mdash; <em>Fascia width (mm)</em>,
             <em>Fit height</em>. A single <b>choice</b> can also carry a number box of its own &mdash; hanging off that one choice
             and nothing else, under whatever caption whoever set the option up gave it, so it appears the moment you pick that choice
             and vanishes again if you pick another. Choices can show a little picture, and some options only appear <b>after</b> you
             have picked the fabric or the system &mdash; that is by design, not a glitch. Before a fabric is picked you may see the stand-in line <em>&ldquo;Pick a
             fabric above to see its options.&rdquo;</em> instead of the grid.</p>

          <p><b>What the Options grid never shows you is a price.</b> There is no little green &ldquo;+ &pound;5.00&rdquo; beside the
             choice you just made &mdash; each option is a label and a control, full stop. The money appears in two places only: the
             live price line below the form (<em>+ extras &pound;5.00</em>) and, once the blind is saved, its own line in the blinds
             table (<em>+ Bottom Weight: Chained (&pound;5.00)</em>). If you are hunting the grid for a price, stop hunting.</p>

          <p><b>Options that sit above the size.</b> An option can be flagged <em>Show above the size fields</em> in its setup, and it
             is then drawn between <b>Measurement unit (this quote)</b> and <b>Width / Drop</b> &mdash; because you need to answer it
             before the size means anything. On the roller that is <b>Fascia Options</b> (Senses, the LL cassettes, or <b>No Fascia</b>),
             and nested under it once a real fascia is chosen, <b>Fascia Sizing</b> &mdash; <em>Standard fascia</em>, <em>Over size for
             single blind</em>, <em>Multi blind</em>. The last two open a <b>Fascia width</b> box. There is <b>no heading</b> over that
             area on the real screen: the options just appear there, above Width, with nothing announcing them.</p>

          <p><b>Adjust price for this blind</b> is a folded-away panel &mdash; only there if you are allowed to see costs &mdash;
             holding <b>Discount % (this blind)</b> and <b>Markup % (this blind)</b> for this line only, both showing
             <em>product default</em> until you type in them. If your company is set to work in <b>margin</b> rather than markup, that
             second label reads <b>Margin % (this blind)</b> instead, and the little note underneath says <em>&ldquo;Leave blank to use
             the product&rsquo;s set margin / discount.&rdquo;</em> Same box, your word for it. Then <b>Save</b>, or <b>Save and add
             another blind</b> to keep the form open and carry on to the next window.</p>

          <p><b>The blinds list.</b> Each saved blind lands in the table on the right &mdash; columns <b>#</b>, <b>Description</b>,
             <b>Size</b>, <b>Qty</b>, <b>Unit</b>, <b>Total</b>. The description stacks up in the order you built it: the <b>Room</b> in
             bold, then <em>Roller Blind &mdash; Standard</em>, then <em>Band A &mdash; Louvolite &mdash; Sunset White / Ivory</em>,
             then a line per option (<em>+ Bottom Weight: Chained (&pound;5.00)</em>), then your internal note in italics. Three
             buttons follow: <b>Edit</b>, <b>Dup</b> (<em>&ldquo;Duplicate this blind &mdash; copies fabric, system, options. New row
             opens in edit mode for you to tweak the size.&rdquo;</em> &mdash; the fastest way to do four windows in one room) and
             <b>&times;</b>, which asks <em>&ldquo;Remove this blind?&rdquo;</em> first. Before you add anything it simply says
             <em>No blinds yet</em>.</p>

          <p><b>The totals, line by line</b>, underneath that same table: the purple <b>WT</b> row, <b>Discount (to agreed price)</b>
             when an override is in force, <b>Subtotal</b>, <b>VAT (20.00%)</b>, <b>Total</b>, the <b>Override price</b> row, and a
             faint <b>Deposit due on acceptance</b> until the customer says yes.</p>

          <p><b>Override price.</b> The row reads <em>&ldquo;Override price (agreed price ex VAT &mdash; VAT added on top; blank to
             clear)&rdquo;</em> and has a little <b>&pound;</b> box and a <b>Set</b> button. You shook hands on &pound;950 on the
             doorstep &mdash; type <code>950</code>, click <b>Set</b>, and the quote totals &pound;950 plus VAT. The gap between the
             blinds and the agreed figure appears above as a <b>Discount (to agreed price)</b> line, so the arithmetic still adds up
             and nobody has to guess later why it does not. Agree a figure <em>higher</em> than the blinds add up to and the same row
             calls itself <b>Price adjustment (to agreed price)</b> and shows a plus instead. Empty the box and Set again to clear it.</p>

          <p><b>The Wally tax (WT charge).</b> The purple row labelled <em>&ldquo;WT (internal &mdash; never shown to the
             customer)&rdquo;</em> is your hassle money &mdash; the awkward job, the scaffold tower, the two-hour round trip. Type the
             amount in the <b>&pound;</b> box and click <b>Set</b>. It folds into the price the customer sees; there is <b>never</b> a
             separate line on their quote, their PDF or their invoice. If you cannot see this row at all, the WT charge simply has not
             been switched on in Settings for your company.</p>

          <p><b>Deposit.</b> The panel has two faces. <b>Before</b> the customer accepts it reads <em>&ldquo;The deposit due when the
             customer accepts&hellip;&rdquo;</em> with <b>Deposit due on acceptance &pound;</b> and <b>Save deposit</b> &mdash; plus a
             handy <em>Suggested 50%: &pound;33.00</em> link you can click to fill the box. <b>After</b> they accept it flips to
             <em>&ldquo;Enter the deposit the customer has paid.&rdquo;</em> with <b>Deposit paid &pound;</b> and <b>Record deposit
             paid</b>; once recorded you get <em>&ldquo;&check; Deposit paid &pound;33.00 on 19 Sep 2026&rdquo;</em> and, underneath
             it, the two ways to undo a fat finger: an <b>Amend &pound;</b> label with a number box and a <b>Save</b> button beside
             it (retype the figure, click Save), and a separate <b>Mark unpaid</b> button that reverses the whole thing. There is no
             button actually labelled &ldquo;Amend&rdquo; &mdash; Amend is the caption on the box, and the button says Save.</p>

          <p><b>Rollers only &mdash; several blinds under one fascia.</b> Set <b>Fascia Sizing</b> to <b>Multi blind</b> and the Width
             box locks itself to the words <em>multi blind</em>, greyed and italic, because the individual widths now drive the cut. A
             <b>Number of Blinds?</b> dropdown appears (2 to 5), and a <b>Blind 1 Width</b> &hellip; <b>Blind 5 Width</b> box for each
             one you asked for. A panel appears under the
             price headed <b>Blinds in this fascia</b>: a row per blind with its own width, an optional drop override (leave it
             <em>same</em> to share the drop) and its price, with a running total. It fit-checks as you type &mdash;
             <em>&ldquo;&check; Fits: 2850 mm in 2900 mm fascia&rdquo;</em>, <em>&ldquo;&#9888; Won&rsquo;t fit: blinds total 3000 mm
             vs fascia 2900 mm&rdquo;</em>, or <em>&ldquo;Tip: enter a Fascia width to fit-check.&rdquo;</em> The price reads
             <em>&ldquo;3 blinds under one fascia &mdash; &pound;135.00 total&rdquo;</em>. Saving fans it out into <b>one line per
             blind</b>, each tagged with a purple <b>Fascia A</b> pill in the size column so the workshop knows they belong together.</p>

          <div class="oops"><b>&ldquo;Quote is locked (status: sent). Reopen it to add blinds.&rdquo;</b> You have tried to change a
             quote that has already left the building. The banner at the top says the same thing in longer words:
             <em>&ldquo;This quote is in <b>sent</b> state and is read-only. Use <b>Reopen as draft</b> above to edit it.&rdquo;</em>
             Click <b>Reopen as draft</b> in Quote actions, make your change, then send it again.</div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Five things worth knowing before you go.</b>
             <b>(1)</b> Only a <b>draft</b> is editable &mdash; everything else is read-only until you reopen it.
             <b>(2)</b> <b>Paid is never a button.</b> The quote flips itself to paid once the deposit and payments cover the total.
             <b>(3)</b> Marking it accepted also creates the install appointment for you &mdash; titled <em>Install: PRE-2026-0042
             &mdash; Emma Fletcher</em>, 9am, an hour long, carrying the address over. It is deliberately given <b>no date</b>, so it
             lands in the calendar&rsquo;s <b>Pending Fitting</b> tray for you to drag onto the real day. Where there is exactly one
             fitter on the books it is assigned to them; with several it is left unassigned for you to pick. Decline the quote later
             and that pending fitting is removed again.
             <b>(4)</b> <b>&#128230; Save as order</b> accepts the quote and takes you straight to Place order in one go.
             <b>(5)</b> On a job raised for a <b>trade account</b>, the customer block is replaced by a <b>Trade account</b> card
             showing the company&rsquo;s contact, phone, mobile, email and address, with the note <em>&ldquo;These details come from
             the trade account.&rdquo;</em> (A super-admin &mdash; and only a super-admin &mdash; sees that sentence run on into
             <em>&ldquo;&mdash; edit them there, not here&rdquo;</em>, plus a <b>Manage account &rarr;</b> link in the corner. If you
             are not one, do not go hunting for either.) You get two extra boxes instead &mdash; <b>Customer reference (their order /
             PO)</b> and <b>Additional reference (optional)</b> &mdash; and the live price shows their <em>trade discount</em> as
             well.</div></div>

          <p>One last thing: if a screen looks like it is missing its little grey helper lines, you are probably in <b>compact mode</b>,
             which hides them to fit more on screen. Turn it off and the hints come back.</p>

          <p><b>What happens next.</b> Getting it in front of the customer and getting a yes is <b>Sending &amp; accepting</b>. Turning
             the yes into a factory order and a bill is <b>Ordering &amp; invoicing</b>. Taking and recording the money is
             <b>Payments &amp; accounts</b>.</p>',
        'script'  => [
            ['0:00', 'New quote — searching the customer.',  'A quote starts from the Quotes screen: click plus New quote. The first box is a search box. Type two or three letters of their name, their town, or their postcode, and the matches drop down underneath. If this is a brand new customer, leave it blank and just type their name in the box below.', 1],
            ['0:14', 'Picked — their details copy down.',    'Click the right one and it copies their details down the form for you: name, email, landline, mobile, the WhatsApp tick, and the whole address. Fill in anything it does not know, add a note if you want one, and click Create quote. If you forget the name it tells you: customer name is required. And if you started from a measure appointment in the calendar, it carries the installation address across and usually skips this screen altogether.', 2],
            ['0:32', 'Landing on the draft.',                'You land on a fresh draft, quote P R E, two thousand and twenty six, forty two. The blind form is down the left, the running quote is down the right. The customer block is folded away to keep it short: click that summary line to open it, and it has its own Save details button. There is no big save quote button anywhere, because every panel saves itself.', 3],
            ['0:50', 'Product wakes the other three up.',    'Now build it a blind at a time. Choose the Product first, always. Until you do, System says choose product first, Band says all bands, and the Fabric box will not let you type. Pick the product and all three wake up. System fills in and pre picks the default. Band narrows to that system. And watch the words: on some products Fabric reads Slat or Colour, and Band reads tape and string. Same boxes, different names.', 4],
            ['1:10', 'Fabric search panel; room name.',      'Click the fabric box and type. A little panel drops down, with the name and colour on top and the supplier and code underneath. Click the row you want. If nothing matches it simply says, no matching fabrics. Then the room name: click the box or the little arrow and pick from the list, or type your own. The room is only a label, but it is the label the fitter reads, so always fill it in.', 5],
            ['1:28', 'Unit, width, drop, quantity, notes.',  'Measurement unit is set for the whole quote, not for one blind: change it and every size is re-displayed in the new unit, but the actual measurements never change. You can also type a hundred and fifty centimetres, or sixty inches, straight into the width and it is read for you. Quantity is how many identical blinds, and on a slatted product that label changes itself to number of slats. Notes are internal only: they print on your paperwork, never on the customer\'s quote.', 6],
            ['1:50', 'Red price; both Save buttons blocked.', 'Here is the slip everybody makes once. The price box goes red: no price table for Roller Blind band A on system Grip Fit. The band dropdown only filters the fabric list, it never promises a price. The price lives on the combination of product, system and band. So change the system to one that is priced, or pick a band that has a price list. You will also meet, size exceeds the largest cell in this price table, and nine times out of ten that is the unit, not the price list. Notice both save buttons are greyed out and will not click. You cannot save a broken line.', 7],
            ['2:14', 'Options; green price; Save.',          'Last comes options, and they will look different on every product, because they are set up per product. Some are a dropdown. Some are a list of tick boxes where you can have more than one. Some are just a number to type. Some only appear once you have picked the fabric or the system, and that is on purpose. Adjust price for this blind nudges this one line, and nothing else. The price goes green: fifty pounds a blind. Now Save, or Save and add another blind to carry straight on to the next window.', 8],
        ],
];
