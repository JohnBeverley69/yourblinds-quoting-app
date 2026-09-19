<?php
declare(strict_types=1);

/**
 * Guide: settings-margins
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Default margins',
        'eyebrow' => 'Settings · Quoting',
        'blurb'   => 'Markup vs margin — the pricing choice people get wrong, with a live calculator.',
        'lede'    => 'The choice that trips people up. You set your profit as <b>markup</b> or as <b>margin</b> —
                      and they are <b>not</b> the same number. Mix them up and every quote is priced wrong. Here\'s
                      the difference, plainly, with a calculator to play with.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .mlabel{ font-size:.78rem; color:var(--soft); font-weight:600; margin-bottom:.45rem; }
          .gd .mradios{ margin-bottom:.9rem; }
          .gd .stage[data-step="0"] .rmk, .gd .stage:not([data-step="0"]) .rmg{ color:var(--ink); font-weight:600; }
          .gd .stage[data-step="0"] .rmk .dot, .gd .stage:not([data-step="0"]) .rmg .dot{ border-color:var(--accent); }
          .gd .stage[data-step="0"] .rmk .dot::after, .gd .stage:not([data-step="0"]) .rmg .dot::after{ content:""; position:absolute; inset:3px; border-radius:50%; background:var(--accent); }
          .gd .mrates{ margin-bottom:.7rem; }
          .gd .mhint{ font-size:.66rem; color:var(--accent); margin-top:.2rem; }
          .gd .wmg, .gd .vmg, .gd .hmg{ display:none; }
          .gd .stage:not([data-step="0"]) .wmk, .gd .stage:not([data-step="0"]) .vmk, .gd .stage:not([data-step="0"]) .hmk{ display:none; }
          .gd .stage:not([data-step="0"]) .wmg, .gd .stage:not([data-step="0"]) .vmg, .gd .stage:not([data-step="0"]) .hmg{ display:inline; }
          .gd .exwrap{ min-height:52px; }
          .gd .ex{ display:none; align-items:center; gap:.5rem; font-size:1rem; padding:.55rem .8rem; border:1px solid var(--line); border-radius:10px; background:var(--panel); }
          .gd .ex .exc{ color:var(--soft); }
          .gd .ex .exs{ color:var(--ink); font-size:1.05rem; }
          .gd .ex .exp{ color:var(--good); font-size:.8rem; margin-left:auto; }
          .gd .ex.danger{ border-color:var(--err); background:var(--err-wash); }
          .gd .ex.danger .exs{ color:var(--err); }
          .gd .stage[data-step="0"] .e0, .gd .stage[data-step="1"] .e1, .gd .stage[data-step="2"] .e2, .gd .stage[data-step="3"] .e3{ display:flex; }
          /* interactive pop-out */
          .gd .calc-open{ margin-top:.7rem; display:inline-flex; align-items:center; gap:.45rem; cursor:pointer; font:inherit; font-weight:700; font-size:.92rem; border:none; border-radius:9px; padding:.55rem 1rem; background:var(--accent); color:#fff; }
          .gd .calc-open:hover{ background:var(--accent-ink); }
          .gd .calc-modal{ display:none; position:fixed; inset:0; z-index:50; background:rgba(10,15,22,.55); align-items:center; justify-content:center; padding:1rem; }
          .gd .calc-modal.open{ display:flex; }
          .gd .calc-box{ position:relative; width:min(560px,100%); max-height:90vh; overflow:auto; background:var(--surface); border:1px solid var(--line); border-radius:16px; box-shadow:var(--gd-shadow); padding:1.3rem 1.4rem; color:var(--ink); }
          .gd .calc-box h3{ margin:0 0 1rem; font-size:1.15rem; }
          .gd .calc-x{ position:absolute; top:.55rem; right:.7rem; border:none; background:none; font-size:1.5rem; line-height:1; cursor:pointer; color:var(--faint); }
          .gd .calc-row{ display:flex; flex-direction:column; gap:.7rem; margin-bottom:1rem; }
          .gd .calc-row label{ font-size:.85rem; color:var(--soft); font-weight:600; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
          .gd .calc-row input[type=number]{ width:6rem; font:inherit; padding:.3rem .5rem; border:1px solid var(--line); border-radius:7px; background:var(--panel); color:var(--ink); }
          .gd .calc-row input[type=range]{ flex:1; min-width:11rem; accent-color:var(--accent); }
          .gd .calc-cards{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem; }
          .gd .calc-card{ border:1px solid var(--line); border-radius:12px; padding:.8rem .9rem; background:var(--panel); text-align:center; }
          .gd .cc-h{ font-size:.66rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700; color:var(--faint); }
          .gd .cc-sell{ font-size:1.5rem; font-weight:800; margin:.2rem 0; letter-spacing:-.02em; }
          .gd .cc-sub{ font-size:.78rem; color:var(--soft); }
          .gd .calc-card.cc-margin .cc-sell{ color:var(--accent-ink); }
          .gd .calc-card.danger{ border-color:var(--err); background:var(--err-wash); }
          .gd .calc-card.danger .cc-sell{ color:var(--err); }
          .gd .calc-bars{ margin:.9rem 0 .3rem; display:flex; flex-direction:column; gap:.4rem; }
          .gd .bar{ height:12px; background:var(--line-2); border-radius:6px; overflow:hidden; }
          .gd .bar span{ display:block; height:100%; border-radius:6px; transition:width .15s; width:0; }
          .gd .bar-mk span{ background:var(--soft); }
          .gd .bar-mg span{ background:var(--accent); }
          .gd .calc-note{ font-size:.9rem; color:var(--ink); margin-top:.7rem; line-height:1.5; }
          .gd .calc-eq{ font-size:.88rem; color:var(--soft); margin-top:.5rem; }
          .gd .calc-eq .boom{ display:block; margin-top:.4rem; color:var(--err); font-weight:600; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Default margins</div>
                <div class="mlabel">Enter your margins as</div>
                <div class="mradios"><span class="radio rmk"><span class="dot"></span> Markup&nbsp;%</span><span class="radio rmg"><span class="dot"></span> Margin&nbsp;%</span></div>
                <div class="frow mrates">
                  <div class="fld"><label>Default price-table <span class="wmk">markup</span><span class="wmg">margin</span> %</label>
                    <div class="box filled"><span class="vmk">100</span><span class="vmg">50</span></div>
                    <div class="mhint"><span class="hmk">&asymp; 50% margin</span><span class="hmg">&asymp; 100% markup</span></div></div>
                  <div class="fld"><label>Default options &amp; extras <span class="wmk">markup</span><span class="wmg">margin</span> %</label>
                    <div class="box filled"><span class="vmk">100</span><span class="vmg">50</span></div>
                    <div class="mhint"><span class="hmk">&asymp; 50% margin</span><span class="hmg">&asymp; 100% markup</span></div></div>
                </div>
                <div class="exwrap">
                  <div class="ex e0"><span class="exc">Cost &pound;100</span> &rarr; <b class="exs">Sell &pound;150</b> <span class="exp">profit &pound;50</span></div>
                  <div class="ex e1"><span class="exc">Cost &pound;100</span> &rarr; <b class="exs">Sell &pound;200</b> <span class="exp">profit &pound;100</span></div>
                  <div class="ex e2"><span class="exc">Cost &pound;100</span> &rarr; <b class="exs">Sell &pound;500</b> <span class="exp">profit &pound;400</span></div>
                  <div class="ex e3 danger"><span class="exc">Cost &pound;100</span> &rarr; <b class="exs">Sell &pound;1,000</b> <span class="exp">profit &pound;900</span></div>
                </div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Markup: profit added on top of cost.</b>
                  <b class="c1"><span class="n">2</span> Same 50% as margin? That&rsquo;s &pound;200, not &pound;150.</b>
                  <b class="c2"><span class="n">3</span> Push margin up and the price climbs fast.</b>
                  <b class="c3 err"><span class="n">4</span> 90% margin = ten times cost. Careful!</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>On the <b>Quoting</b> tab, open <b>Default margins</b>. This sets the profit the pricing engine adds &mdash;
             once, for everything &mdash; so you don&rsquo;t have to set it on every product.</p>
          <p><b>The one choice that matters</b> is &ldquo;Enter your margins as&rdquo;: <b>Markup&nbsp;%</b> or <b>Margin&nbsp;%</b>.
             They are <b>not</b> the same number, and mixing them up quietly under- or over-charges every quote.</p>
          <ul class="steps">
            <li><b>Markup</b> is added <b>on top of your cost</b>. Cost &pound;100 + 50% markup = <b>&pound;150</b>.</li>
            <li><b>Margin</b> is profit as a slice of the <b>sell price</b>. A 50% margin on &pound;100 cost = <b>&pound;200</b>
                (your cost is half the sell price).</li>
            <li>So the <b>same &ldquo;50%&rdquo;</b> means &pound;150 as markup but &pound;200 as margin &mdash; pick the one you actually mean.</li>
            <li>Set your <b>price-table</b> rate and your <b>options &amp; extras</b> rate, then <b>Save margins</b>. The little
                blue hint under each box shows the equivalent (e.g. &ldquo;&asymp; 100% markup&rdquo;) so you can sense-check it.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Why margin is the dangerous one:</b> as a margin climbs past about
             <b>70&ndash;80%</b> the sell price runs away &mdash; an <b>80% margin</b> is 5&times; your cost, <b>90%</b> is 10&times;,
             <b>95%</b> is 20&times;. A tiny change near the top swings the price wildly. Markup is far steadier (80% markup is only
             1.8&times; cost). If your figures suddenly look mad, check you haven&rsquo;t typed a margin where you meant a markup.</div></div>
          <p><b>Not sure which you want?</b> Have a play &mdash; this shows both side by side and lets you watch the margin figure take off.</p>
          <button type="button" class="calc-open" id="mmOpen">&#128200; Open the markup vs margin calculator</button>
          <div class="calc-modal" id="mmModal">
            <div class="calc-box" role="dialog" aria-modal="true" aria-label="Markup versus margin calculator">
              <button type="button" class="calc-x" id="mmClose" aria-label="Close">&times;</button>
              <h3>Markup vs margin &mdash; see the difference</h3>
              <div class="calc-row">
                <label>Your cost &pound; <input id="mmCost" type="number" value="100" min="0" step="1"></label>
                <label>The % you enter: <b id="mmPctlbl">50%</b> <input id="mmPct" type="range" min="0" max="99" value="50"></label>
              </div>
              <div class="calc-cards">
                <div class="calc-card"><div class="cc-h">As markup</div><div class="cc-sell" id="mmMkSell">&pound;150.00</div><div class="cc-sub">profit <span id="mmMkProfit">&pound;50.00</span></div></div>
                <div class="calc-card cc-margin" id="mmMgCard"><div class="cc-h">As margin</div><div class="cc-sell" id="mmMgSell">&pound;200.00</div><div class="cc-sub">profit <span id="mmMgProfit">&pound;100.00</span></div></div>
              </div>
              <div class="calc-bars"><div class="bar bar-mk"><span id="mmBarMk"></span></div><div class="bar bar-mg"><span id="mmBarMg"></span></div></div>
              <div class="calc-note" id="mmNote"></div>
              <div class="calc-eq" id="mmEq"></div>
            </div>
          </div>',
        'js'      => <<<'JS'
(function(){
  var open = document.getElementById('mmOpen'), modal = document.getElementById('mmModal');
  if (!open || !modal) return;
  var q = function(id){ return document.getElementById(id); };
  var cost=q('mmCost'), pct=q('mmPct'), pctl=q('mmPctlbl'),
      mkSell=q('mmMkSell'), mkPro=q('mmMkProfit'), mgSell=q('mmMgSell'), mgPro=q('mmMgProfit'),
      mgCard=q('mmMgCard'), barMk=q('mmBarMk'), barMg=q('mmBarMg'), note=q('mmNote'), eq=q('mmEq');
  function money(n){ if(!isFinite(n)) return 'off the chart'; return '£' + n.toLocaleString('en-GB',{minimumFractionDigits:2, maximumFractionDigits:2}); }
  function calc(){
    var c = Math.max(0, parseFloat(cost.value)||0), p = parseFloat(pct.value)||0;
    pctl.textContent = p + '%';
    var mk = c*(1 + p/100), mkP = mk - c;
    var mg = (p>=100) ? Infinity : c/(1 - p/100), mgP = (mg===Infinity) ? Infinity : mg - c;
    mkSell.textContent = money(mk); mkPro.textContent = money(mkP);
    mgSell.textContent = money(mg); mgPro.textContent = money(mgP);
    var ref = Math.max(mk, isFinite(mg)?mg:mk, c) || 1;
    barMk.style.width = (mk/ref*100) + '%';
    barMg.style.width = ((isFinite(mg)?mg/ref:1)*100) + '%';
    mgCard.classList.toggle('danger', p >= 70);
    note.innerHTML = 'Type <b>' + p + '</b> and mean <b>markup</b> → you charge <b>' + money(mk) +
                     '</b>. Mean <b>margin</b> → you charge <b>' + money(mg) + '</b>. Same number, very different price.';
    var eqMk = (p>=100) ? null : (p<=0 ? 0 : p*100/(100-p));
    var txt = 'A <b>' + p + '% margin</b> is the same as <b>' + (eqMk===null ? '∞' : Math.round(eqMk)) + '% markup</b>.';
    if (isFinite(mg) && c>0 && p>=75){
      txt += '<span class="boom">⚠ At ' + p + '% margin the sell price is ' + (mg/c).toFixed(1) +
             '× your cost — margins explode as you near 100%.</span>';
    }
    eq.innerHTML = txt;
  }
  function show(){ modal.classList.add('open'); calc(); }
  function hide(){ modal.classList.remove('open'); }
  open.addEventListener('click', show);
  q('mmClose').addEventListener('click', hide);
  modal.addEventListener('click', function(e){ if (e.target === modal) hide(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') hide(); });
  cost.addEventListener('input', calc); pct.addEventListener('input', calc);
})();
JS,
        'script'  => [
            ['0:00', 'Default margins; markup; £100 → £150.', 'First choice: do you enter your profit as markup, or as margin? They\'re not the same thing.', 0],
            ['0:07', 'Switch to margin; £100 → £200.',        'Fifty percent markup on a hundred-pound cost is a hundred and fifty. But fifty percent margin is two hundred. Same number, bigger price.', 1],
            ['0:16', 'Margin 80; £100 → £500.',               'And margin climbs fast. Push it to eighty percent and you\'re already at five hundred.', 2],
            ['0:23', 'Margin 90; £100 → £1,000; danger.',     'Ninety percent margin is ten times your cost. Near the top the figures go wild, so pick the one you really mean.', 3],
        ],
];
