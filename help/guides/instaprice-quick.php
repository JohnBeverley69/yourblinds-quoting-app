<?php
declare(strict_types=1);

/**
 * Guide: instaprice-quick
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers /instaprice/index.php (the quick-price screen) and
 * /instaprice/to-quote.php (turning that price into a real draft quote).
 */

return [
        'aud'     => 'all',
        'section' => 'Quotes',
        'title'   => 'InstaPrice &mdash; a price in thirty seconds',
        'eyebrow' => 'InstaPrice',
        'blurb'   => 'The whole quick-price flow: product, fabric, options, size — then the breakdown, the two rates you can override, and turning it into a real quote.',
        'lede'    => 'InstaPrice is the tool for <b>&ldquo;how much is that, roughly?&rdquo;</b> &mdash; on the phone, or standing in
                      somebody&rsquo;s front room. <b>No customer, no saving</b>: pick a product, size it, read the price. It uses the
                      <b>same pricing engine as the quote builder</b>, so the figure you read out is the real figure. And if they say yes,
                      <b>one button</b> turns it into a proper quote.',
        'open'    => '/instaprice/index.php',
        'css'     => '
          /* ---------- shell ---------- */
          .gd .side a.ipcta{ background:#f2a33c; color:#3a2400; font-weight:700; margin-bottom:.4rem; }
          .gd .mt{ margin-top:.7rem; }
          .gd .ipnote{ font-size:.62rem; color:var(--faint); margin:.2rem 0 0; }
          .gd .sysnote{ display:none; }
          .gd .stage[data-step="1"] .sysnote{ display:block; }
          .gd .stage[data-step="8"] .ipform{ display:none; }
          .gd .qbscene{ display:none; }
          .gd .stage[data-step="8"] .qbscene{ display:block; }
          .gd .fld{ position:relative; }

          /* ---------- a real <select>: label + chevron, value still rolls in ---------- */
          .gd .selb{ border-color:var(--border-strong,#c7ccd4); background:var(--surface); padding-right:1.35rem; }
          .gd .selb::after{ content:"\25BE"; position:absolute; right:.5rem; top:50%; transform:translateY(-50%);
                            color:var(--faint); font-size:.7rem; z-index:3; }
          .gd .selb .val{ padding-right:1.35rem; }
          .gd .selb.dis{ background:var(--panel); color:var(--faint); }
          .gd .stage:not([data-step="0"]) .selb.dis{ background:var(--surface); }
          .gd .valv{ color:var(--ink); }

          @keyframes gdRollIn{ from{ opacity:0; clip-path:inset(0 100% 0 0);} to{ opacity:1; clip-path:inset(0 0 0 0);} }
          @keyframes gdFadeIn{ from{ opacity:0 } to{ opacity:1 } }
          @keyframes gdShrink{ from{ opacity:1; max-height:2.4rem } to{ opacity:0; max-height:0 } }
          @keyframes gdPhOut{ from{ opacity:1 } to{ opacity:0 } }
          @keyframes gdLive{ from{ background:var(--accent); color:#fff } to{ background:var(--accent); color:#fff } }
          @media (prefers-reduced-motion:reduce){
            .gd .stage .val, .gd .stage .pbrk, .gd .stage .pstill, .gd .stage .echo{ animation:none !important; }
          }

          /* ---------- product dropdown, open (step 1) ---------- */
          .gd .ddlist{ display:none; position:absolute; top:100%; left:0; right:0; margin-top:4px; z-index:40;
                       background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); border-radius:8px;
                       box-shadow:0 10px 26px -14px rgba(20,30,45,.45); padding:.25rem 0; }
          .gd .stage[data-step="1"] .ddlist{ display:block; }
          .gd .oglbl{ font-size:.62rem; font-weight:700; font-style:italic; color:var(--faint); padding:.2rem .55rem 0; }
          .gd .ddopt{ font-size:.76rem; color:var(--ink); padding:.16rem .55rem .16rem 1.1rem; }
          .gd .ddopt.on{ background:var(--accent-wash); color:var(--accent-ink); font-weight:700; }

          /* ---------- fabric typeahead results (step 2) ---------- */
          .gd .fabres{ display:none; position:absolute; top:100%; left:0; right:0; margin-top:4px; z-index:40;
                       background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); border-radius:8px;
                       box-shadow:0 10px 26px -14px rgba(20,30,45,.45); overflow:hidden; }
          .gd .stage[data-step="2"] .fabres{ display:block; }
          .gd .frw{ padding:.3rem .55rem; border-bottom:1px solid var(--line-2); }
          .gd .frw:last-child{ border-bottom:none; }
          .gd .frw.on{ background:var(--panel); }
          .gd .fname2{ font-size:.78rem; color:var(--ink); }
          .gd .fmeta2{ font-size:.64rem; color:var(--faint); margin-top:.05rem; }

          /* ---------- options block ---------- */
          .gd .optblk{ display:none; margin-top:.7rem; padding:.15rem; }
          .gd .stage[data-step="3"] .optblk, .gd .stage[data-step="4"] .optblk, .gd .stage[data-step="5"] .optblk,
          .gd .stage[data-step="6"] .optblk, .gd .stage[data-step="7"] .optblk{ display:block; }
          .gd .stage[data-step="3"] .optblk{ box-shadow:0 0 0 3px var(--accent-wash); border-radius:9px; }
          .gd .opt{ margin-bottom:.45rem; max-width:17rem; }
          .gd .opt > label{ display:block; font-size:.64rem; color:var(--faint); font-weight:600;
                            text-transform:uppercase; letter-spacing:.04em; margin-bottom:.2rem; }
          .gd .optchild{ margin-left:.7rem; padding-left:.6rem; border-left:2px solid var(--line); margin-top:.45rem; }
          .gd .multibox{ display:flex; flex-direction:column; gap:.3rem; border:1px solid var(--border-strong,#c7ccd4);
                         border-radius:8px; background:var(--surface); padding:.4rem .5rem; }
          .gd .mrow{ display:inline-flex; align-items:center; gap:.45rem; font-size:.78rem; color:var(--ink); }
          .gd .numin{ background:var(--surface); border-color:var(--border-strong,#c7ccd4); max-width:12rem; }

          /* ---------- dimensions + echo ---------- */
          .gd .dims{ display:grid; grid-template-columns:1fr 1fr 4.2rem; gap:.5rem; }
          .gd .dcap{ font-size:.62rem; color:var(--faint); font-weight:600; margin-bottom:.15rem; }
          .gd .dimbox{ background:var(--surface); border-color:var(--border-strong,#c7ccd4); }
          .gd .echo{ display:none; margin-top:.4rem; font-size:.7rem; color:var(--faint); }
          .gd .e2{ display:none; }
          .gd .stage[data-step="5"] .echo, .gd .stage[data-step="6"] .echo, .gd .stage[data-step="7"] .echo{ display:block; }
          .gd .stage[data-step="4"] .echo{ display:block; animation:gdFadeIn .3s ease 6.4s both; }
          .gd .stage[data-step="7"] .e1{ display:none; }
          .gd .stage[data-step="7"] .e2{ display:inline; }

          /* ---------- price panel ---------- */
          .gd .ipp{ margin-top:.8rem; border:1px solid var(--line); border-radius:11px; background:var(--surface); padding:.6rem .75rem; }
          .gd .stage[data-step="7"] .ipp{ border-color:var(--err); }
          .gd .pidle, .gd .pstill{ display:none; font-style:italic; color:var(--faint); font-size:.78rem; overflow:hidden; }
          .gd .perrbox{ display:none; color:var(--err); font-size:.78rem; font-weight:600; }
          .gd .pbrk{ display:none; }
          .gd .stage[data-step="0"] .pidle, .gd .stage[data-step="1"] .pidle,
          .gd .stage[data-step="2"] .pidle, .gd .stage[data-step="3"] .pidle{ display:block; }
          .gd .stage[data-step="4"] .pstill{ display:block; animation:gdShrink .35s ease 5.9s forwards; }
          .gd .stage[data-step="4"] .pbrk{ display:block; animation:gdFadeIn .35s ease 6.4s both; }
          .gd .stage[data-step="5"] .pbrk, .gd .stage[data-step="6"] .pbrk{ display:block; }
          .gd .stage[data-step="7"] .perrbox{ display:block; }
          .gd .iprow{ display:flex; align-items:center; justify-content:space-between; gap:.7rem; padding:.28rem 0; font-size:.78rem; }
          .gd .iprow + .iprow{ border-top:1px solid var(--line-2); }
          .gd .iprow .lbl{ color:var(--soft); }
          .gd .iprow .amt{ font-variant-numeric:tabular-nums; font-weight:600; color:var(--ink); }
          .gd .iprow.edit .lbl{ color:#b45309; font-weight:700; }
          .gd .traderow .lbl, .gd .traderow .amt{ color:var(--good); }
          .gd .numbox{ display:inline-flex; justify-content:flex-end; min-width:4.6rem; border:1px solid var(--border-strong,#c7ccd4);
                       border-radius:6px; background:var(--surface); padding:.16rem .4rem; font-size:.76rem;
                       font-variant-numeric:tabular-nums; color:var(--ink); }
          .gd .stage[data-step="6"] .numbox{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .ipsell{ margin-top:.3rem; padding-top:.4rem; border-top:2px solid var(--border-strong,#c7ccd4) !important; }
          .gd .ipsell .lbl{ font-weight:700; color:var(--ink); font-size:.86rem; }
          .gd .ipsell .amt{ font-weight:800; font-size:1.05rem; color:var(--accent-ink); }
          .gd .iptot{ text-align:right; font-size:.68rem; color:var(--faint); margin-top:.15rem; min-height:.8rem; }
          .gd .s6{ display:none; }
          .gd .stage[data-step="6"] .s5{ display:none; }
          .gd .stage[data-step="6"] .s6{ display:inline; }

          /* ---------- buttons ---------- */
          .gd .btnrow{ display:flex; gap:.5rem; margin-top:.7rem; }
          .gd .pbtn{ display:inline-flex; background:var(--line); color:var(--faint); border-radius:8px;
                     padding:.4rem .8rem; font-size:.78rem; font-weight:700; }
          .gd .sbtn{ display:inline-flex; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:8px; padding:.4rem .8rem; font-size:.78rem; font-weight:600; }
          .gd .stage[data-step="5"] .pbtn, .gd .stage[data-step="6"] .pbtn{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="4"] .pbtn{ animation:gdLive .01s linear 6.4s forwards; }

          /* ---------- step 8: the quote builder ---------- */
          .gd .qline{ margin-top:.6rem; border:1px solid var(--line); border-radius:8px; background:var(--panel);
                      padding:.4rem .55rem; font-size:.74rem; color:var(--soft); }
          .gd .qbcust{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); background:var(--surface); }

          /* ---------- value reveals (explicit: f1..f5 stop at step 5) ---------- */
          .gd .stage[data-step="1"] .rP .val, .gd .stage[data-step="2"] .rP .val, .gd .stage[data-step="3"] .rP .val,
          .gd .stage[data-step="4"] .rP .val, .gd .stage[data-step="5"] .rP .val, .gd .stage[data-step="6"] .rP .val,
          .gd .stage[data-step="7"] .rP .val,
          .gd .stage[data-step="1"] .rS .val, .gd .stage[data-step="2"] .rS .val, .gd .stage[data-step="3"] .rS .val,
          .gd .stage[data-step="4"] .rS .val, .gd .stage[data-step="5"] .rS .val, .gd .stage[data-step="6"] .rS .val,
          .gd .stage[data-step="7"] .rS .val,
          .gd .stage[data-step="2"] .rB .val, .gd .stage[data-step="3"] .rB .val, .gd .stage[data-step="4"] .rB .val,
          .gd .stage[data-step="5"] .rB .val, .gd .stage[data-step="6"] .rB .val, .gd .stage[data-step="7"] .rB .val,
          .gd .stage[data-step="3"] .rO .val, .gd .stage[data-step="4"] .rO .val, .gd .stage[data-step="5"] .rO .val,
          .gd .stage[data-step="6"] .rO .val, .gd .stage[data-step="7"] .rO .val,
          .gd .stage[data-step="4"] .rW .wa, .gd .stage[data-step="5"] .rW .wa, .gd .stage[data-step="6"] .rW .wa,
          .gd .stage[data-step="7"] .rW .wb,
          .gd .stage[data-step="5"] .rD .val, .gd .stage[data-step="6"] .rD .val, .gd .stage[data-step="7"] .rD .val{ opacity:1; }

          .gd .stage[data-step="1"] .rP .ph, .gd .stage[data-step="2"] .rP .ph, .gd .stage[data-step="3"] .rP .ph,
          .gd .stage[data-step="4"] .rP .ph, .gd .stage[data-step="5"] .rP .ph, .gd .stage[data-step="6"] .rP .ph,
          .gd .stage[data-step="7"] .rP .ph,
          .gd .stage[data-step="1"] .rS .ph, .gd .stage[data-step="2"] .rS .ph, .gd .stage[data-step="3"] .rS .ph,
          .gd .stage[data-step="4"] .rS .ph, .gd .stage[data-step="5"] .rS .ph, .gd .stage[data-step="6"] .rS .ph,
          .gd .stage[data-step="7"] .rS .ph,
          .gd .stage[data-step="2"] .rB .ph, .gd .stage[data-step="3"] .rB .ph, .gd .stage[data-step="4"] .rB .ph,
          .gd .stage[data-step="5"] .rB .ph, .gd .stage[data-step="6"] .rB .ph, .gd .stage[data-step="7"] .rB .ph,
          .gd .stage[data-step="3"] .rO .ph, .gd .stage[data-step="4"] .rO .ph, .gd .stage[data-step="5"] .rO .ph,
          .gd .stage[data-step="6"] .rO .ph, .gd .stage[data-step="7"] .rO .ph,
          .gd .stage[data-step="4"] .rW .ph, .gd .stage[data-step="5"] .rW .ph, .gd .stage[data-step="6"] .rW .ph,
          .gd .stage[data-step="7"] .rW .ph,
          .gd .stage[data-step="5"] .rD .ph, .gd .stage[data-step="6"] .rD .ph, .gd .stage[data-step="7"] .rD .ph{ opacity:0; }

          /* the roll happens on the step the field is being filled, and the ring with it */
          .gd .stage[data-step="1"] .rP .val, .gd .stage[data-step="1"] .rS .val,
          .gd .stage[data-step="2"] .rB .val, .gd .stage[data-step="3"] .rO .val,
          .gd .stage[data-step="4"] .rW .wa, .gd .stage[data-step="7"] .rW .wb{ animation:gdRollIn .8s ease-out both; }
          .gd .stage[data-step="1"] .rP, .gd .stage[data-step="1"] .rS,
          .gd .stage[data-step="2"] .rB, .gd .stage[data-step="2"] .fabbox,
          .gd .stage[data-step="4"] .rW, .gd .stage[data-step="4"] .rD,
          .gd .stage[data-step="7"] .rW{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="6"] .qbox{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }

          /* drop lands part-way through step 4, once the width has been talked about */
          .gd .stage[data-step="4"] .rD .val{ animation:gdRollIn .8s ease-out 6s both; }
          .gd .stage[data-step="4"] .rD .ph{ animation:gdPhOut .01s linear 6s forwards; }

          /* fabric: two placeholders and two values in one box */
          .gd .fabbox{ background:var(--surface); border-color:var(--border-strong,#c7ccd4); }
          .gd .fabbox .p1{ display:none; }
          .gd .stage[data-step="1"] .fabbox .p1{ display:inline; }
          .gd .stage:not([data-step="0"]) .fabbox .p0{ display:none; }
          .gd .stage[data-step="2"] .fabbox .vft{ opacity:1; animation:gdRollIn .8s ease-out both; }
          .gd .stage[data-step="3"] .fabbox .vfp, .gd .stage[data-step="4"] .fabbox .vfp,
          .gd .stage[data-step="5"] .fabbox .vfp, .gd .stage[data-step="6"] .fabbox .vfp,
          .gd .stage[data-step="7"] .fabbox .vfp{ opacity:1; }

          /* quantity is a real number box, pre-set to 1 */
          .gd .q2{ display:none; }
          .gd .stage[data-step="6"] .q1, .gd .stage[data-step="7"] .q1{ display:none; }
          .gd .stage[data-step="6"] .q2, .gd .stage[data-step="7"] .q2{ display:inline; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / instaprice</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a class="ipcta on">&#9889; InstaPrice</a>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <div class="ipform">
                  <div class="card-t">InstaPrice &mdash; Quick price, no customer details needed.</div>

                  <!-- Product + System -->
                  <div class="frow">
                    <div class="fld">
                      <label>Product</label>
                      <div class="box selb rP"><span class="ph">Choose product&hellip;</span><span class="val">25mm Venetian</span></div>
                      <div class="ddlist">
                        <div class="oglbl">Venetian Blinds</div>
                        <div class="ddopt on">25mm Venetian</div>
                        <div class="ddopt">50mm Wood Venetian</div>
                        <div class="oglbl">Roller Blinds</div>
                        <div class="ddopt">Bev Roller Blinds</div>
                      </div>
                    </div>
                    <div class="fld">
                      <label>System</label>
                      <div class="box selb dis rS"><span class="ph">Choose product first</span><span class="val">Bev 25mm</span></div>
                      <p class="ipnote sysnote">landed on this product&rsquo;s default system</p>
                    </div>
                  </div>

                  <!-- Band + Fabric (whole row disappears on a no-fabric product) -->
                  <div class="frow mt">
                    <div class="fld">
                      <label>Band</label>
                      <div class="box selb rB"><span class="ph">All bands</span><span class="val">Band A</span></div>
                    </div>
                    <div class="fld">
                      <label>Fabric</label>
                      <div class="box fabbox">
                        <span class="ph p0">Choose product first</span>
                        <span class="ph p1">Type to search fabrics&hellip;</span>
                        <span class="val vft">aspen</span>
                        <span class="val vfp">Aspen / Auburn</span>
                      </div>
                      <div class="fabres">
                        <div class="frw on"><div class="fname2">Aspen / Auburn</div><div class="fmeta2">Louvolite &middot; Code 1234</div></div>
                        <div class="frw"><div class="fname2">Aspen / Ivory</div><div class="fmeta2">Louvolite &middot; Code 1237</div></div>
                      </div>
                    </div>
                  </div>

                  <!-- Options, built from this product / system / fabric -->
                  <div class="optblk">
                    <div class="opt">
                      <label>Control side</label>
                      <div class="box selb rO"><span class="ph">&mdash; Select &mdash;</span><span class="val">Left</span></div>
                    </div>
                    <div class="opt">
                      <label>Headrail <span class="req">*</span></label>
                      <div class="box selb rO"><span class="ph">&mdash; Select &mdash;</span><span class="val">Vogue</span></div>
                      <div class="optchild">
                        <div class="opt">
                          <label>Chain type</label>
                          <div class="box selb rO"><span class="ph">&mdash; Select &mdash;</span><span class="val">Metal</span></div>
                        </div>
                      </div>
                    </div>
                    <div class="opt">
                      <label>Trims</label>
                      <div class="multibox">
                        <span class="mrow"><span class="tick on">&check;</span> Braid</span>
                        <span class="mrow"><span class="tick">&check;</span> Eyelets</span>
                      </div>
                    </div>
                    <div class="opt">
                      <label>Cable length</label>
                      <div class="box numin"><span class="ph">Cable length (mm)</span></div>
                    </div>
                  </div>

                  <!-- Measurement unit (second cell is empty on the real screen too) -->
                  <div class="frow mt">
                    <div class="fld">
                      <label>Measurement unit</label>
                      <div class="box selb"><span class="valv">Millimetres (mm)</span></div>
                    </div>
                    <div class="fld"></div>
                  </div>

                  <!-- Dimensions + quantity + the grey echo line -->
                  <div class="fld mt">
                    <label>Dimensions (mm) &amp; quantity</label>
                    <div class="dims">
                      <div>
                        <div class="dcap">Width</div>
                        <div class="box dimbox rW"><span class="ph">Width</span><span class="val wa">1200</span><span class="val wb">3200</span></div>
                      </div>
                      <div>
                        <div class="dcap">Drop</div>
                        <div class="box dimbox rD"><span class="ph">Drop</span><span class="val">1400</span></div>
                      </div>
                      <div>
                        <div class="dcap">Qty</div>
                        <div class="box dimbox qbox"><span class="valv q1">1</span><span class="valv q2">2</span></div>
                      </div>
                    </div>
                    <div class="echo"><span class="e1">Using 1200 &times; 1400 mm</span><span class="e2">Using 3200 &times; 1400 mm</span></div>
                  </div>

                  <!-- The price panel -->
                  <div class="ipp">
                    <div class="pidle">Choose a product and enter a size to see the price.</div>
                    <div class="pstill">Still need: drop.</div>
                    <div class="perrbox">Size 3200 &times; 1400 mm exceeds the largest cell in this price table.</div>
                    <div class="pbrk">
                      <div class="iprow traderow"><span class="lbl">Trade discount</span>
                        <span class="amt"><span class="s5">12.50% (&minus;&pound;7.71)</span><span class="s6">12.50% (&minus;&pound;15.42)</span></span></div>
                      <div class="iprow"><span class="lbl">Price</span><span class="amt">&pound;54.00</span></div>
                      <div class="iprow edit"><span class="lbl">Discount %</span>
                        <span class="numbox"><span class="s5">10.00</span><span class="s6">15.00</span></span></div>
                      <div class="iprow"><span class="lbl">Discounted price</span>
                        <span class="amt"><span class="s5">&pound;48.60</span><span class="s6">&pound;45.90</span></span></div>
                      <div class="iprow edit"><span class="lbl">Mark up %</span>
                        <span class="numbox"><span class="s5">100.00</span><span class="s6">120.00</span></span></div>
                      <div class="iprow ipsell"><span class="lbl">Sell price</span>
                        <span class="amt"><span class="s5">&pound;97.20</span><span class="s6">&pound;201.96</span></span></div>
                      <div class="iptot"><span class="s6">2 &times; &pound;100.98 each</span></div>
                    </div>
                  </div>

                  <div class="btnrow">
                    <span class="pbtn">Turn into full quote &rarr;</span>
                    <span class="sbtn">Reset</span>
                  </div>
                </div>

                <!-- Step 8: it is a real quote now -->
                <div class="qbscene">
                  <div class="okbanner"><span>&check;</span> Quote Q-1042 started from InstaPrice &mdash; add the customer details (and any more blinds) below.</div>
                  <div class="card-t mt">Quote Q-1042 &mdash; draft</div>
                  <div class="frow">
                    <div class="fld"><label>Search customers</label><div class="box"><span class="ph">Type a name&hellip;</span></div></div>
                    <div class="fld"><label>Customer name <span class="req">*</span></label>
                      <div class="box qbcust"><span class="valv">Quick price (add customer)</span></div>
                      <p class="ipnote">rename this now &mdash; it is how the quote shows in your list</p>
                    </div>
                  </div>
                  <div class="qline">Line 1 &mdash; 25mm Venetian &middot; Bev 25mm &middot; Aspen / Auburn &middot; 1200 &times; 1400 mm &middot; Qty 2 &middot; &pound;201.96</div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Pick the product &mdash; the System fills itself in.</b>
                  <b class="c2"><span class="n">2</span> Type a few letters, pick the fabric from the list.</b>
                  <b class="c3"><span class="n">3</span> Only the options this blind can actually have.</b>
                  <b class="c4"><span class="n">4</span> Size and quantity &mdash; and check the grey line underneath.</b>
                  <b class="c5"><span class="n">5</span> The price, worked out line by line.</b>
                  <b class="c6"><span class="n">6</span> Change either rate and watch the price move.</b>
                  <b class="c7 err"><span class="n">7</span> Off the end of the price table &mdash; fix the size.</b>
                  <b class="c8 good"><span class="n">8</span> One click &mdash; it is a real quote, waiting for a name.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>InstaPrice</b> is for the moment somebody asks <em>&ldquo;go on then, roughly what would that cost?&rdquo;</em> You open it from
             the coloured <b>&#9889; InstaPrice</b> box in the left-hand sidebar, just under <b>+ New</b> &mdash; everybody sees it. Pick a product,
             put a size in, read the price out. It runs the <b>same pricing engine as the quote builder</b>, so it is not a guess.</p>
          <p><b>What it deliberately hasn&rsquo;t got.</b> No customer, no rooms, no notes, no Wally tax (WT charge) box, no VAT line, and
             <b>no saving</b> &mdash; nothing you type here is recorded anywhere, and leaving the page loses it. The big <b>Sell price</b> is the
             <b>ex-VAT</b> selling figure; VAT only joins in once it is a real quote. <b>Reset</b> empties the lot and starts again.</p>

          <ul class="steps">
            <li><b>Product, then System.</b> The <b>Product</b> dropdown groups your live products under their <b>category</b> headings, so scroll
                to the right heading (anything without a category sits under <b>Other</b>, at the bottom). The <b>System</b> box next to it starts
                greyed out reading <b>&ldquo;Choose product first&rdquo;</b>; pick a product and it wakes up on that product&rsquo;s <b>default
                system</b>. Change it only if this job is the other one &mdash; the price, the bands and the options can all differ per system.</li>
            <li><b>Band, then Fabric.</b> <b>Band</b> is a price tier: leave it on <b>&ldquo;All bands&rdquo;</b> and search the lot, or pick a band
                to narrow the search down. <b>Fabric</b> is a <b>search box, not a dropdown</b> &mdash; type two or three letters and the matches
                drop down underneath with the supplier and code, then click one. Nothing matching? It says <b>&ldquo;No matching fabrics.&rdquo;</b>
                Once you have picked, the box holds that fabric&rsquo;s <b>name</b>, so clicking back into it shows you the <b>whole list</b> again
                to switch colour. Changing the <b>System</b> clears the fabric, so pick the system first. These two labels follow the product &mdash;
                on a vertical the second one may say <b>Slat</b>, on a shutter <b>Colour</b>.</li>
            <li><b>Options.</b> Whatever this product offers appears next, and only what <em>this</em> product, <em>this</em> system and
                <em>this</em> fabric can actually have. Sensible ones are <b>already chosen</b> for you; where there is no sensible default the
                dropdown starts on <b>&ldquo;&mdash; Select &mdash;&rdquo;</b>. A red <b>*</b> means you must answer it. Some options are
                <b>tick-boxes</b> (pick any combination), some are just a <b>number box</b> with its own caption in it, and some open a second
                option <b>indented underneath</b> once you have picked the parent. If an option you expected isn&rsquo;t there, nine times out of
                ten it is the <b>fabric</b> &mdash; that choice is set up for other colours, or other bands. Pure measurement boxes are left out
                here on purpose: they don&rsquo;t move the price.</li>
            <li><b>Unit, size and quantity.</b> <b>Measurement unit</b> starts on whatever your company works in (Settings &rarr; Measurements) and
                offers <b>Millimetres (mm)</b>, <b>Centimetres (cm)</b>, <b>Metres (m)</b> and <b>Inches (in)</b>. A <b>bare number is read in that
                unit</b> &mdash; but if you <b>type the unit on the end</b> it wins, so <code>60&quot;</code>, <code>1.5m</code> and
                <code>150cm</code> all work whatever the box says. Underneath, the little grey line shows what the system is really using &mdash;
                <b>&ldquo;Using 1200 &times; 1400 mm&rdquo;</b> &mdash; which is your last chance to spot a stray nought or the wrong unit.
                <b>Qty</b> starts at 1 and simply multiplies the Sell price.</li>
            <li><b>Read the breakdown.</b> Until you have filled everything in, the panel just tells you what is outstanding &mdash;
                <b>&ldquo;Still need: product, fabric, width, drop.&rdquo;</b>, shrinking as you go. Then it becomes a sum you can follow, top to
                bottom: <b>Price</b> (this blind at this size with its options) &rarr; <b>Discount %</b> &rarr; <b>Discounted price</b> &rarr;
                <b>Mark up %</b> &rarr; <b>Sell price</b>, big, at the bottom. With more than one blind a small grey line reads
                <b>&ldquo;2 &times; &pound;100.98 each&rdquo;</b> under the total.</li>
            <li><b>Turn it into a quote.</b> Happy with it and they have said yes? <b>Turn into full quote &rarr;</b> lifts the whole spec into the
                quote builder so you can add the customer and the rest of the blinds.</li>
          </ul>

          <p><b>How the form changes shape</b> &mdash; nothing is broken, it is following the product:</p>
          <ul class="steps">
            <li><b>No-fabric products</b> (a headrail, a track, spares) hide the <b>Band and Fabric row entirely</b> &mdash; they price on system and
                size alone.</li>
            <li><b>Width-only products</b> have <b>no Drop box</b>; the echo reads <b>&ldquo;Using 1524 mm wide&rdquo;</b>.</li>
            <li><b>Per-slat products</b> have <b>no Width box</b> &mdash; they are priced by drop, one slat at a time, and the echo reads
                <b>&ldquo;Using 900 mm drop (per slat)&rdquo;</b>.</li>
            <li><b>Per square metre products</b> (shutters) show the area instead: <b>&ldquo;Area: 2.40 m&sup2;&rdquo;</b>, and
                <b>&ldquo;Area: 2.40 m&sup2; (billed at min 3.00 m&sup2;)&rdquo;</b> when the job is under the minimum charge.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>The green &ldquo;Trade discount&rdquo; line is not the customer&rsquo;s
             discount.</b> When it appears at the top of the panel &mdash; e.g. <b>12.50% (&minus;&pound;7.71)</b> &mdash; that is <b>your</b>
             standing buying discount from your supplier on that product, system and band. It is <b>already inside the Price underneath</b>; it is
             there for your information only, and only people allowed to see cost figures ever see it at all. The customer&rsquo;s discount is the
             amber <b>Discount %</b> box below it.</div></div>

          <p><b>The two amber boxes are yours to play with.</b> <b>Discount %</b> and <b>Mark up %</b> arrive already filled in from that
             <b>product and system&rsquo;s own saved rates</b> (which themselves fall back to <b>Settings &rarr; Default margins</b>). Type straight
             over either one and the figures below <b>move as you type</b> &mdash; no waiting, no saving &mdash; which is exactly what you want when
             somebody is haggling and you need to know what another five percent really costs you. Three things to know: it is a <b>one-off</b> for
             this figure, it is <b>never written back to the product</b>, and it <b>springs back to the usual rates</b> the moment you change the
             product or the system. But whatever those boxes read when you press <b>Turn into full quote</b> <b>is</b> what the quote line gets
             created with. If your company works in <b>margin</b> rather than markup, the second box is labelled <b>Margin %</b> instead &mdash; same
             money, different way of typing it.</p>

          <p><b>Roller blinds sharing one fascia.</b> Pick the multi-blind choice on a roller and the screen changes: the <b>Width</b> box locks
             itself to a grey italic <b>&ldquo;multi blind&rdquo;</b> (each blind carries its own width instead), and a small table headed
             <b>&ldquo;Blinds in this fascia&rdquo;</b> appears under the price &mdash; <b>Blind &middot; Width &middot; Drop &middot; Price</b>, one
             row per blind, each with its own drop box you can leave blank for <b>&ldquo;same&rdquo;</b>, and a <b>Total</b>. Underneath it fit-checks
             for you: <b>&ldquo;&#10003; Fits: 2400 mm in 2500 mm&rdquo;</b>, or <b>&ldquo;&#9888; Won&rsquo;t fit: 2600 mm vs fascia 2500 mm&rdquo;</b>,
             or <b>&ldquo;Tip: enter a Fascia width to fit-check.&rdquo;</b> if you haven&rsquo;t given it one. The main panel then just reads
             <b>&ldquo;3 blinds &middot; &pound;312.00 total&rdquo;</b>. You cannot convert while it does not fit, and if you somehow get past that on
             a stale figure the server says the same thing again: <b>&ldquo;Those blinds won&rsquo;t fit: they total 2600 mm but the fascia is only
             2500 mm.&rdquo;</b> On conversion each blind becomes <b>its own line, grouped together</b>, with the fascia charged <b>once</b>.</p>

          <div class="oops"><b>What the panel says, and what to do about it:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><code>Still need: fabric, drop.</code> &mdash; nothing wrong; just keep filling in. (On a multi-blind fascia it reads
                   <code>Still need: product, fabric, a shared drop, at least 2 blind widths.</code>)</li>
               <li><code>Size 3200 &times; 1400 mm exceeds the largest cell in this price table.</code> &mdash; that band&rsquo;s grid stops short of
                   the size you asked for. Either the size is wrong, or somebody needs to extend the table under <b>Products</b>. (Width-only and
                   per-slat products say <code>Width 3200 mm exceeds the largest entry in this price list.</code> and
                   <code>Drop 2600 mm exceeds the largest entry in this per-slat rate list.</code>)</li>
               <li><code>No price table for 25mm Venetian band A on system &lsquo;Bev 25mm&rsquo;.</code> &mdash; that band has <b>no grid at all</b>
                   on that system yet. Same for <code>No price table set up for Headrail for system &lsquo;Vogue&rsquo;.</code> and
                   <code>No &pound;/m&sup2; rate set for Shutters in this price list.</code></li>
               <li><code>Could not get a price &mdash; try again.</code> &mdash; the connection blinked. Change something and it will re-price.</li>
               <li><code>Fabric is required.</code> / <code>Width must be greater than zero.</code> / <code>Drop must be greater than zero.</code>
                   &mdash; something the engine needs is missing or zero.</li>
             </ul>
             While the panel is red, <b>Turn into full quote</b> goes grey. Fix the figure and the price comes straight back.</div>

          <p><b>What &ldquo;Turn into full quote&rdquo; really does &mdash; read this bit.</b> It does not open a scratch pad. It <b>creates a
             genuine draft quote</b>: it takes the next <b>quote number</b>, snapshots your current <b>VAT rate</b>, copies the line and all its
             options across with the rates you set, stamps the <b>measurement unit</b> you were working in so the builder opens the same way, and
             drops you on the quote builder with a green message naming the quote. The customer is a placeholder reading
             <b>&ldquo;Quick price (add customer)&rdquo;</b> &mdash; and that is exactly how it will sit in your quotes list until you change it, so
             <b>rename it straight away</b>. Press that button to <b>keep</b> a price, not to see what happens; if you clicked it by mistake, delete
             the draft from your quotes list rather than leaving litter behind. If the server cannot manage it you will come back to InstaPrice with a
             red bar reading <code>Could not price that: &hellip;</code>, <code>Could not read the size &mdash; go back and try again.</code> or
             <code>Could not start the quote &mdash; please try again.</code> &mdash; nothing will have been created.</p>

          <p><b>No &ldquo;Turn into full quote&rdquo; button at all?</b> That is not a fault either. The button is only drawn for admins and for
             users with the <b>Create quotes</b> permission &mdash; everybody else can price all day but cannot keep it. An admin can switch that on
             under <b>Setup &rarr; Users</b>, on your user, with the tick-box marked <b>Create quotes</b>. (Post without it and you simply get
             <code>You don&rsquo;t have permission to create quotes.</code>)</p>',
        'script'  => [
            ['0:00', 'Product picked; System fills itself.',
                'Everything starts with the Product box. Your products are grouped under their categories, so scroll to the right heading and pick one. The moment you do, the System box next to it wakes up and lands on that product\'s usual system — change it only if this job is the other one, and remember the price can be different for each.', 1],
            ['0:16', 'Band narrowed; fabric searched and picked.',
                'Next the fabric. Band is a price tier — leave it on All bands and search everything, or pick a band to narrow it down. Then just type two or three letters into the Fabric box; matches drop down underneath with the supplier and the code, and you click the one you want. If nothing matches it says so, in plain words: no matching fabrics. And these two labels change with the product — on a vertical it might say Slat, on a shutter it might say Colour. On a headrail or a track there is no fabric at all, so the whole row disappears.', 2],
            ['0:40', 'Options appear, most already answered.',
                'Now the options. These are not a fixed list — the screen only offers what this product, this system and this fabric can actually have, and sensible ones are already chosen for you. A red star means you must answer it. Some options open a second one underneath, indented, once you have picked the parent. And if an option you expected is not there, nine times out of ten it is the fabric — that choice is set up for other colours.', 3],
            ['1:00', 'Unit, width, drop, quantity, echo line.',
                'Sizes. The unit box starts on whatever your company works in, and plain numbers are read in that unit — so with millimetres chosen, twelve hundred means twelve hundred millimetres. You can also type sixty inches, or one point five metres, and it will take the hint. Until you have filled everything in, the panel below just tells you what is still missing: still need, drop. And once it is all in, that little grey line underneath tells you what the system is really using — using twelve hundred by fourteen hundred millimetres — so a stray nought or the wrong unit shows itself before it becomes a wrong price.', 4],
            ['1:24', 'The breakdown, line by line.',
                'And there is your price, shown as a sum you can follow. Price is what this blind costs at that size with its options. Discount percent and Mark up percent arrive already filled in from that product\'s own settings. Discounted price is the middle step, and Sell price at the bottom, in big letters, is what you would charge. If you see a green Trade discount line at the top, that is your own buying discount from your supplier — it is already inside the Price above, it is shown for your information only, and only people allowed to see cost figures ever see it. And if your company works in margin rather than markup, that second box is labelled Margin percent instead — same money either way.', 5],
            ['1:56', 'Rates typed over; quantity raised to two.',
                'Those two amber boxes are yours to play with. Type over them and the price changes as you type — handy when someone is haggling and you want to know what a bit more discount really costs you. It is a one-off, just for this figure: nothing is saved back to the product, and it springs back to the usual rates the moment you change product or system. Put the quantity up and the big number becomes the total for all of them, with the each-price underneath. Nothing on this screen is stored anywhere — Reset wipes it and you start again.', 6],
            ['2:20', 'Size off the end of the table; panel goes red.',
                'If a size falls outside the grid you have loaded, InstaPrice will not guess. The panel goes red and tells you exactly why — size three thousand two hundred by fourteen hundred millimetres exceeds the largest cell in this price table — and the quote button goes grey until it is fixed. That is not a fault on this screen: it means the price table for that band stops short of that size, and somebody needs to add it under Products. Bring the size back inside the table and the price comes straight back.', 7],
            ['2:44', 'Converted: a real draft quote, no customer yet.',
                'They have said yes. Click Turn into full quote and the whole thing lifts straight into the quote builder — same product, same fabric, same options, same size, and the rates you set. It opens in the unit you were working in. The green message names the new quote number, and the customer box is sitting there saying quick price, add customer — that is your job, right now, before you forget. Then carry on as normal: add the rest of the blinds, and send it. One word of warning — that button makes a real quote the moment you press it, number and all. Press it to keep a price, not to see what happens; if you did not mean to, delete the draft from your quotes list.', 8],
        ],
];
