<?php
declare(strict_types=1);

/**
 * Guide: customers-add — "Adding & managing customers" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors the Customers area: the list (/customer-manager/index.php) with its
 * search, Edit · Book appointment links and the 100-name cap; Add customer
 * (/customer-manager/new.php) and its soft same-name check; the record page
 * (/customer-manager/edit.php) with Book appointment, Recent quotes and the
 * Danger zone; Find duplicates (/customer-manager/dedupe.php); and the New
 * quote type-ahead (/quote-builder/new.php) that reads the address book.
 * Every label, button and message is copied from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with its
 * own animation timeline (a-* classes, start times in --d seconds, stretched
 * to the recorded line's length via data-len).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// The customer list — [name, email, phone, town, postcode, quotes]
$people = [
    ['Aisha Khan',    'aisha.k@outlook.com',      '01926 401122', 'Warwick',         'CV34 4AB', 2],
    ['Ben Carter',    'ben@carterhome.co.uk',     '01926 853310', 'Kenilworth',      'CV8 1AA',  1],
    ['David Oakes',   'd.oakes@gmail.com',        '024 7622 9001', 'Coventry',       'CV1 2GH',  0],
    ['Emma Fletcher', 'emma.fletcher@gmail.com',  '01926 334455', 'Leamington Spa',  'CV32 5PJ', 3],
    ['Tom Hughes',    'tom.hughes@btinternet.com','01789 220145', 'Stratford',       'CV37 6BA', 1],
];

/**
 * The list table. $row($i, $p) returns [extraClass, style] for each row.
 */
$list = static function (callable $row) use ($people): string {
    $h = '<div class="tbl"><div class="tr th"><span>Name</span><span class="hm">Email</span><span class="hm">Phone</span><span>Town</span><span>Postcode</span><span class="n">Quotes</span><span></span></div>';
    foreach ($people as $i => $p) {
        [$c, $s] = $row($i, $p);
        $h .= '<div class="tr ' . $c . '" style="' . $s . '"><span><b>' . $p[0] . '</b></span><span class="hm">' . $p[1] . '</span><span class="hm">' . $p[2] . '</span>'
            . '<span>' . $p[3] . '</span><span>' . $p[4] . '</span><span class="n">' . $p[5] . '</span>'
            . '<span class="acts"><i class="lk">Edit</i> &middot; <i class="lk">Book appointment</i></span></div>';
    }
    return $h . '</div>';
};
$plainRow = static fn ($i, $p) => ['', ''];

/** One labelled input box. $val may carry animation markup. */
$fld = static fn (string $label, string $val = '', string $cls = '', string $style = ''): string =>
    '<div class="fg ' . $cls . '" style="' . $style . '"><span class="fl">' . $label . '</span><span class="ib">' . $val . '</span></div>';

$waTick = static fn (string $inner = '', string $cls = '', string $style = ''): string =>
    '<span class="ck ' . $cls . '" style="' . $style . '"><i>' . $inner . '</i>Mobile is on WhatsApp</span>';

// The whole Add customer form, filled in (used as a finished still in a few scenes).
$formFilled = '
    <div class="fm">
      ' . $fld('Name <em class="rq">*</em>', 'Emma Fletcher', 'full') . '
      <div class="g3">
        ' . $fld('Email', 'emma.fletcher@gmail.com') . '
        ' . $fld('Phone <small>(landline)</small>', '01926 334455') . '
        <div class="fg">' . '<span class="fl">Mobile</span><span class="ib">07700 900123</span>' . $waTick('&#10003;', 'on') . '</div>
      </div>
    </div>';

return [
        'aud'     => 'admin',
        'section' => 'Customers',
        'title'   => 'Adding & managing customers',
        'eyebrow' => 'Customers',
        'v'       => 2,
        'blurb'   => 'Your retail address book, end to end: finding people, every box on the form, the same-name check, the record page and its danger zone, merging duplicates, and how a new quote fills itself in from here.',
        'lede'    => '<b>Customers</b> is your <b>retail address book</b> &mdash; the households your company quotes, measures and fits for.
                      It sits under <b>Retail</b> in the menu, and the page says so at the top: &ldquo;End-customers belonging to
                      <i>your company</i>.&rdquo; Only the <b>Name</b> is required; everything else is you saving yourself typing later,
                      because a new quote can copy it all across. This guide goes <b>slowly</b>, one idea per chapter &mdash; watch it
                      through once, then use <b>Jump to a chapter</b> to go back over any part.',
        'open'    => '/customer-manager/index.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }

          /* page header */
          .gd .phd{ display:flex; align-items:flex-start; justify-content:space-between; gap:.6rem; flex-wrap:wrap; margin-bottom:.7rem; }
          .gd .pt{ font-size:1.05rem; font-weight:800; color:var(--ink); }
          .gd .psub{ font-size:.68rem; color:var(--soft); margin-top:.1rem; }
          .gd .psub .lk{ color:var(--accent); }
          .gd .acts2{ display:flex; gap:.35rem; flex-wrap:wrap; }

          /* buttons, chips, banners */
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px;
                     padding:.34rem .8rem; font-size:.74rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.3rem .7rem; font-size:.72rem; font-weight:600; white-space:nowrap; }
          .gd .btnd{ display:inline-flex; background:#dc2626; color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .bnr{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; background:var(--good-wash); border-left:3px solid var(--good);
                    border-radius:8px; padding:.45rem .65rem; font-size:.74rem; font-weight:700; color:var(--ink); margin:0 0 .6rem; }
          .gd .amb{ background:#fef3c7; border:1px solid #fde047; color:#78350f; border-radius:8px; padding:.55rem .7rem; font-size:.72rem; margin:0 0 .6rem; }
          .gd .amb ul{ margin:.4rem 0 0; padding-left:1.1rem; line-height:1.55; }
          .gd .amb a, .gd .amb .nm{ color:#78350f; font-weight:700; text-decoration:underline; }
          .gd .amb .mt{ color:#92400e; }
          .gd .lk{ color:var(--accent); font-style:normal; font-weight:600; }
          .gd .tagx{ display:inline-block; font-size:.6rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; border-radius:999px; padding:.1rem .45rem; }

          /* search */
          .gd .srch{ display:flex; gap:.4rem; align-items:center; flex-wrap:wrap; margin-bottom:.6rem; }
          .gd .srch .ib{ flex:1 1 14rem; min-width:10rem; }
          .gd .gph{ color:var(--faint); }
          .gd .swap{ display:inline-grid; } .gd .swap > span{ grid-area:1/1; }

          /* tables */
          .gd .tbl{ border:1px solid var(--line); border-radius:9px; overflow:hidden; font-size:.68rem; background:var(--surface); }
          .gd .tr{ display:grid; grid-template-columns:1.25fr 1.6fr 1.1fr 1fr .8fr .55fr 1.5fr; gap:.3rem; align-items:center;
                   padding:.36rem .55rem; border-top:1px solid var(--line); color:var(--ink); }
          .gd .tr:first-child{ border-top:0; }
          .gd .tr.th{ background:var(--panel); font-weight:700; color:var(--soft); font-size:.6rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .tr span{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
          .gd .tr .n{ text-align:right; }
          .gd .tr.hit{ background:var(--accent-wash); }
          .gd .tbl .more{ padding:.36rem .55rem; border-top:1px dashed var(--line); color:var(--faint); font-size:.66rem; text-align:center; }

          /* form */
          .gd .fm{ display:flex; flex-direction:column; gap:.5rem; max-width:34rem; }
          .gd .fg{ display:flex; flex-direction:column; gap:.2rem; min-width:0; }
          .gd .fl{ font-size:.66rem; font-weight:700; color:var(--soft); }
          .gd .fl small{ font-weight:400; color:var(--faint); }
          .gd .rq{ color:#dc2626; font-style:normal; }
          .gd .ib{ display:flex; align-items:center; min-height:28px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                   background:var(--surface); padding:0 .5rem; font-size:.74rem; color:var(--ink); overflow:hidden; white-space:nowrap; }
          .gd .ib.tall{ min-height:4.6rem; align-items:flex-start; padding-top:.35rem; white-space:normal; }
          .gd .g3{ display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; }
          .gd .ck{ display:inline-flex; align-items:center; gap:.35rem; font-size:.68rem; color:var(--soft); margin-top:.3rem; }
          .gd .ck > i{ width:14px; height:14px; border:1.5px solid var(--border-strong,#9aa3af); border-radius:3px; display:grid; place-items:center;
                       font-style:normal; font-size:.62rem; font-weight:900; color:#fff; background:var(--surface); }
          .gd .ck.on > i{ background:var(--accent); border-color:var(--accent); }
          .gd .ck > i > span{ color:var(--accent); font-size:.72rem; }
          .gd .ck.on > i > span{ color:#fff; }
          .gd .fact{ display:flex; gap:.4rem; margin-top:.2rem; }
          .gd .note{ font-size:.66rem; color:var(--faint); margin-top:.15rem; }

          /* why-missing cards */
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; }
          .gd .card{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); font-size:.7rem; color:var(--soft); line-height:1.45; }
          .gd .card h4{ margin:0 0 .4rem; font-size:.8rem; color:var(--ink); }
          .gd .alpha{ display:flex; flex-wrap:wrap; gap:3px; margin:.3rem 0 .4rem; }
          .gd .alpha i{ font-style:normal; width:1.15rem; text-align:center; font-size:.6rem; font-weight:800; border-radius:3px; padding:.1rem 0;
                        background:var(--accent-wash); color:var(--accent); }
          .gd .alpha i.off{ background:var(--panel); color:var(--faint); }
          .gd .who{ display:flex; gap:.5rem; align-items:flex-start; }
          .gd .who .col{ flex:1; border:1px solid var(--line); border-radius:8px; padding:.4rem .5rem; }
          .gd .who .col b{ display:block; font-size:.66rem; color:var(--ink); margin-bottom:.25rem; }
          .gd .who .col span{ display:block; font-size:.64rem; }

          /* books (scene 1) */
          .gd .books{ display:grid; grid-template-columns:1.2fr 1fr; gap:.8rem; margin-top:.4rem; }
          .gd .book{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); position:relative; }
          .gd .book h4{ margin:0 0 .35rem; font-size:.8rem; color:var(--ink); }
          .gd .book .ln{ font-size:.68rem; color:var(--soft); padding:.18rem 0; border-top:1px dashed var(--line); }
          .gd .book.trade{ background:var(--panel); }
          .gd .x{ display:inline-block; margin-top:.5rem; font-size:.7rem; font-weight:800; color:var(--err); border:2px solid var(--err); border-radius:6px; padding:.1rem .45rem; }
          .gd .mnu{ border:1px solid var(--line); border-radius:9px; padding:.4rem .5rem; background:var(--panel); font-size:.68rem; width:9rem; }
          .gd .mnu b{ display:block; font-size:.56rem; text-transform:uppercase; letter-spacing:.06em; color:var(--faint); margin:.25rem 0 .15rem; }
          .gd .mnu span{ display:block; padding:.15rem .35rem; border-radius:5px; color:var(--soft); }
          .gd .mnu span.on{ background:var(--accent-wash); color:var(--accent); font-weight:700; }
          .gd .hdrrow{ display:flex; gap:1rem; align-items:flex-start; flex-wrap:wrap; }

          /* record page */
          .gd .sech{ font-size:.82rem; font-weight:800; color:var(--ink); margin:.8rem 0 .4rem; }
          .gd .qt .tr{ grid-template-columns:1.4fr 1fr .9fr 1fr .6fr; }
          .gd .bdg{ display:inline-block; font-style:normal; font-size:.62rem; font-weight:700; border-radius:999px; padding:.08rem .5rem; }
          .gd .b-draft{ background:#f3f4f6; color:#4b5563; } .gd .b-sent{ background:#eff6ff; color:#1e40af; }
          .gd .b-accepted{ background:#f0fdf4; color:#166534; } .gd .b-ordered{ background:#fefce8; color:#854d0e; }
          .gd .b-rejected{ background:#fef2f2; color:#991b1b; }
          .gd .legend{ display:flex; gap:.35rem; flex-wrap:wrap; margin-top:.6rem; }
          .gd .dz{ border:1px solid #fecaca; border-radius:10px; padding:.6rem .7rem; background:var(--surface); max-width:30rem; }
          .gd .dz h4{ margin:0 0 .45rem; font-size:.82rem; color:#b91c1c; }
          .gd .cfm{ position:absolute; z-index:5; left:14%; top:34%; max-width:19rem; background:var(--surface); border:1px solid var(--line);
                    border-radius:12px; box-shadow:var(--gd-shadow); padding:.75rem .85rem; font-size:.74rem; color:var(--ink); }
          .gd .cfm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.6rem; }

          /* duplicates */
          .gd .doors{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; }
          .gd .door{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); font-size:.7rem; color:var(--soft); }
          .gd .door h4{ margin:0 0 .35rem; font-size:.78rem; color:var(--ink); }
          .gd .twin{ display:flex; flex-direction:column; gap:.3rem; margin-top:.8rem; max-width:22rem; }
          .gd .twin span{ border:1px solid var(--line); border-radius:7px; padding:.3rem .55rem; font-size:.7rem; background:var(--surface); color:var(--ink); }
          .gd .howm{ background:#f0f9ff; border:1px solid #bae6fd; color:#0c4a6e; border-radius:9px; padding:.45rem .6rem; font-size:.66rem; line-height:1.45; margin-bottom:.55rem; }
          :root[data-theme="dark"] .gd .howm{ background:rgba(14,165,233,.12); color:#bae6fd; border-color:rgba(14,165,233,.35); }
          .gd .grp{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--surface); }
          .gd .grp h5{ margin:0 0 .35rem; font-size:.78rem; color:var(--ink); }
          .gd .grp h5 small{ font-weight:400; color:var(--faint); }
          .gd .dt .tr{ grid-template-columns:1.15fr 1.7fr 1fr .9fr .55fr .5fr; }
          .gd .dt .tr > span:first-child{ overflow:visible; }
          .gd .tr .sw{ display:inline-grid; font-style:normal; } .gd .tr .sw > b{ grid-area:1/1; font-weight:700; }
          .gd .tr.keep{ background:var(--good-wash); }
          .gd .kp{ display:inline-block; font-style:normal; margin-left:.3rem; background:var(--good); color:#fff; font-size:.55rem; font-weight:800; border-radius:999px; padding:.05rem .4rem; }
          .gd .gone{ position:relative; }
          .gd .gd-play .strike{ animation:cmStrike .6s ease calc(var(--d,0s) * var(--k,1)) forwards; }
          .gd .gd-done .strike{ opacity:.35; text-decoration:line-through; }
          @keyframes cmStrike{ to{ opacity:.35; text-decoration:line-through; } }
          .gd .tst{ position:absolute; right:0; top:0; background:var(--good-wash); color:var(--good); font-weight:700; font-size:.72rem;
                    border:1px solid color-mix(in srgb,var(--good) 35%,transparent); border-radius:8px; padding:.4rem .65rem; max-width:17rem; z-index:4; }

          /* new quote payoff */
          .gd .dlw{ position:relative; }
          .gd .dl{ position:absolute; left:0; top:100%; width:min(22rem,100%); z-index:5; border:1px solid var(--line); border-radius:8px; background:var(--surface); box-shadow:var(--gd-shadow); font-size:.7rem; margin-top:.2rem; max-width:22rem; }
          .gd .dl div{ padding:.3rem .55rem; border-top:1px solid var(--line); color:var(--ink); }
          .gd .dl div:first-child{ border-top:0; }
          .gd .dl div.hit{ background:var(--accent-wash); font-weight:700; }

          @media (max-width:640px){
            .gd .sc{ min-height:430px; }
            .gd .two, .gd .books, .gd .doors{ grid-template-columns:1fr; }
            .gd .g3{ grid-template-columns:1fr 1fr; }
            .gd .tr{ grid-template-columns:1.3fr 1fr .9fr .5fr 1.1fr; }
            .gd .tr .hm{ display:none; }
            .gd .tr .acts i:last-child{ display:none; }
            .gd .qt .tr, .gd .dt .tr{ grid-template-columns:1.3fr 1fr .9fr .6fr; }
            .gd .qt .tr > :nth-child(4), .gd .dt .tr > :nth-child(3), .gd .dt .tr > :nth-child(4){ display:none; }
            .gd .cfm{ left:4%; right:4%; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / customers</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a class="on">Customers</a><a>Quotes</a><a>Orders</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="phd"><div><div class="pt">Customers</div><div class="psub">End-customers belonging to Beverley Blinds.</div></div>
                    <div class="acts2"><span class="btns">Find duplicates</span><span class="btnp">+ Add customer</span></div></div>
                  <div class="srch"><span class="ib"><span class="gph">Search by name, email, phone, town or postcode...</span></span><span class="btns">Search</span></div>
                  ' . $list($plainRow) . '
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; fourteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — what it is -->
                <div class="sc" data-scene="1" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">Your retail address book</div>
                  <div class="hdrrow">
                    <div class="mnu a-fly" style="--d:6.8s"><b>Work</b><span>Dashboard</span><span>Calendar</span><b>Retail</b>
                      <span class="on a-ring" style="--d:8.5s">Customers</span><span>Quotes</span><span>Orders</span></div>
                    <div style="flex:1;min-width:14rem">
                      <div class="phd a-rise" style="--d:1s"><div><div class="pt">Customers</div><div class="psub">End-customers belonging to Beverley Blinds.</div></div></div>
                      <div class="books">
                        <div class="book a-rise" style="--d:3.2s"><h4>&#127968; Households you quote, measure and fit</h4>
                          <div class="ln a-fly" style="--d:4s">Emma Fletcher &middot; Leamington Spa</div>
                          <div class="ln a-fly" style="--d:4.5s">Aisha Khan &middot; Warwick</div>
                          <div class="ln a-fly" style="--d:5s">Tom Hughes &middot; Stratford</div></div>
                        <div class="book trade a-rise" style="--d:11.5s"><h4>&#127970; Businesses you supply</h4>
                          <div class="ln">Trade accounts &mdash; kept separately, under <b>Trade</b></div>
                          <span class="x a-stamp" style="--d:13s">&#10007; not in here</span></div>
                      </div>
                    </div>
                  </div>
                  <span class="chip a-pop" style="--d:20.8s;margin-top:.9rem">Each list only shows the people it should</span>
                </div>

                <!-- 2 — finding someone -->
                <div class="sc" data-scene="2" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Finding someone</div>
                  <div class="srch">
                    <span class="ib a-ring" style="--d:1.5s"><span class="swap"><span class="gph a-out" style="--d:12.5s">Search by name, email, phone, town or postcode...</span><span class="a-type" style="--d:12.6s;--ts:4;--tt:.8s">CV32</span></span></span>
                    <span class="btns a-press" style="--d:15.5s">Search</span>
                    <span class="btns a-pop" style="--d:18.5s">Clear</span>
                  </div>
                  <div style="display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:.6rem">
                    <span class="chip a-pop" style="--d:3s">name</span><span class="chip a-pop" style="--d:3.6s">email</span>
                    <span class="chip a-pop" style="--d:4.2s">phone</span><span class="chip a-pop" style="--d:4.8s">town</span><span class="chip a-pop" style="--d:5.4s">postcode</span>
                  </div>
                  <div style="display:grid">
                    <div class="a-out" style="--d:16s;grid-area:1/1">' . $list($plainRow) . '</div>
                    <div class="a-fade" style="--d:16.2s;grid-area:1/1;align-self:start">' . $list(static fn ($i, $p) => [$p[4] === 'CV32 5PJ' ? 'hit' : '', $p[4] === 'CV32 5PJ' ? '' : 'display:none']) . '</div>
                  </div>
                  <div class="a-move" style="--fx:85%;--fy:90%;--tx:40%;--ty:1.7rem;--d:10.5s;--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 3 — why someone seems missing -->
                <div class="sc" data-scene="3" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Somebody seems to be missing?</div>
                  <div class="two">
                    <div class="card a-rise" style="--d:4.6s"><h4>1 &middot; Only the first hundred</h4>
                      The list stops at <b>100 names</b>, A to Z.
                      <div class="alpha">' . implode('', array_map(static fn ($l, $i) => '<i class="' . ($i > 15 ? 'off a-fade' : 'a-pop') . '" style="--d:' . ($i > 15 ? 8.2 + ($i - 16) * .08 : 5.2 + $i * .15) . 's">' . $l . '</i>', range('A', 'Z'), array_keys(range('A', 'Z')))) . '</div>
                      <span class="chip a-pop" style="--d:10s">&#128269; Search, don&rsquo;t scroll</span></div>
                    <div class="card a-rise" style="--d:13.5s"><h4>2 &middot; &ldquo;View all customer jobs&rdquo;</h4>
                      <div class="who">
                        <div class="col"><b>With the tick</b><span>Everyone</span><span>in the book</span></div>
                        <div class="col a-fade" style="--d:16s"><b>Without it</b><span>Only customers on</span><span>their own jobs</span></div>
                      </div>
                      <p style="margin:.45rem 0 0">Set per person in <b>Setup &rarr; Users</b>.</p></div>
                  </div>
                  <span class="chip a-pop" style="--d:20.5s;margin-top:.9rem">Neither one is a fault</span>
                </div>

                <!-- 4 — add: only the name -->
                <div class="sc" data-scene="4" data-len="18">
                  <div class="sct a-fade" style="--d:.2s">Adding someone: only the name is required</div>
                  <div class="phd"><div><div class="pt"><span class="swap"><span class="a-out" style="--d:3.2s">Customers</span><span class="a-fade" style="--d:3.3s">Add customer</span></span></div>
                    <div class="psub a-fade" style="--d:3.4s"><span class="lk">&larr; Back to customers</span></div></div>
                    <span class="a-out" style="--d:3.2s"><span class="btnp a-press" style="--d:2.4s">+ Add customer</span></span></div>
                  <div class="fm a-rise" style="--d:3.6s">
                    ' . $fld('Name <em class="rq a-ring" style="--d:4.5s">*</em>', '<span class="a-type" style="--d:7s;--ts:13;--tt:1s">Emma Fletcher</span>', 'full') . '
                    <div class="g3">' . $fld('Email') . $fld('Phone <small>(landline)</small>') . $fld('Mobile') . '</div>
                    ' . $fld('Address line 1') . '
                  </div>
                  <span class="chip a-pop" style="--d:11.5s;margin-top:.8rem">Every box you fill now = less typing on the next quote</span>
                  <div class="a-move" style="--fx:30%;--fy:80%;--tx:84%;--ty:2.4rem;--d:.6s;--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 5 — three ways to reach them -->
                <div class="sc" data-scene="5" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Three ways to reach them</div>
                  <div class="fm">
                    ' . $fld('Name <em class="rq">*</em>', 'Emma Fletcher', 'full') . '
                    <div class="g3">
                      ' . $fld('Email', '<span class="a-type" style="--d:4.6s;--ts:23;--tt:1.4s">emma.fletcher@gmail.com</span>', 'a-ring', '--d:4.3s') . '
                      ' . $fld('Phone <small class="a-pop" style="--d:5.6s">(landline)</small>', '<span class="a-type" style="--d:6.2s;--ts:12;--tt:1s">01926 334455</span>') . '
                      <div class="fg"><span class="fl">Mobile</span><span class="ib"><span class="a-type" style="--d:7s;--ts:12;--tt:1s">07700 900123</span></span>
                        ' . $waTick('<span class="a-pop" style="--d:15.8s">&#10003;</span>', 'a-ring', '--d:11.9s') . '</div>
                    </div>
                  </div>
                  <span class="chip a-pop" style="--d:8.2s;margin-top:.8rem">An email must be a proper address</span>
                  <div style="margin-top:.6rem"><span class="chip a-pop" style="--d:19s;border-color:#25d366">&#128172; Send via WhatsApp &mdash; on their quote, later</span></div>
                  <div class="a-move" style="--fx:20%;--fy:85%;--tx:58%;--ty:8.3rem;--d:12.5s;--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 6 — address -->
                <div class="sc" data-scene="6" data-len="20">
                  <div class="sct a-fade" style="--d:.2s">Where the blinds are going</div>
                  <div class="fm">
                    ' . $fld('Address line 1', '<span class="a-type" style="--d:2.5s;--ts:16;--tt:1s">14 Clarendon Ave</span>') . '
                    ' . $fld('Address line 2', '<span class="a-type" style="--d:4s;--ts:8;--tt:.6s">Flat 2</span>') . '
                    <div class="g3">
                      ' . $fld('Town', '<span class="a-type" style="--d:5.8s;--ts:14;--tt:.9s">Leamington Spa</span>') . '
                      ' . $fld('County', '<span class="a-type" style="--d:6.8s;--ts:12;--tt:.8s">Warwickshire</span>') . '
                      ' . $fld('Postcode', '<span class="a-type" style="--d:7.8s;--ts:8;--tt:.6s">CV32 5PJ</span>', 'a-ring', '--d:9s') . '
                    </div>
                  </div>
                  <span class="chip a-pop" style="--d:9.5s;margin-top:.8rem">No postcode finder here &mdash; type it in</span>
                  <div style="margin-top:.5rem;display:flex;gap:.4rem;flex-wrap:wrap">
                    <span class="chip a-pop" style="--d:15.8s">&#128205; Where you visit</span><span class="chip a-pop" style="--d:17.5s">&#9993; Where the paperwork goes</span></div>
                </div>

                <!-- 7 — notes + save -->
                <div class="sc" data-scene="7" data-len="20">
                  <div class="sct a-fade" style="--d:.2s">Notes, then save</div>
                  <div class="fm">
                    <div class="fg"><span class="fl">Notes</span><span class="ib tall"><span>
                      <span class="a-type" style="--d:1.8s;--ts:22;--tt:1.2s">Park on the drive.</span><br>
                      <span class="a-type" style="--d:3.4s;--ts:22;--tt:1.2s">Side door, not front.</span><br>
                      <span class="a-type" style="--d:5s;--ts:24;--tt:1.2s">Dog in the back garden.</span></span></span></div>
                    <span class="chip a-pop" style="--d:5.8s;align-self:flex-start">&#128274; Nobody outside the business sees this</span>
                    <div class="fact"><span class="btnp a-press a-ring" style="--d:8.6s">Save customer</span><span class="btns a-ring" style="--d:10.6s">Cancel</span></div>
                  </div>
                  <p class="scs a-fade" style="--d:14.5s;margin-top:.7rem">Any box left empty stays empty &mdash; fill it in later.</p>
                </div>

                <!-- 8 — same-name check -->
                <div class="sc" data-scene="8" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">The same-name check</div>
                  <div class="amb a-drop" style="--d:5.2s"><b>A customer with this name already exists.</b><br>
                    Pick the existing customer below, or scroll down and click <b>Save anyway</b> if this really is a different person with the same name.
                    <ul><li class="a-fly" style="--d:9s"><span class="nm a-ring" style="--d:15s">Emma Fletcher</span> <span class="mt">&mdash; Leamington Spa &middot; CV32 5PJ &middot; emma.fletcher@gmail.com &middot; 01926 334455</span></li></ul></div>
                  <div class="fm">' . $fld('Name <em class="rq">*</em>', 'Emma Fletcher', 'full') . '</div>
                  <div class="fact" style="margin-top:.6rem"><span class="btnp a-ring" style="--d:18.8s"><span class="swap"><span class="a-out" style="--d:5.2s">Save customer</span><span class="a-fade" style="--d:5.2s">Save anyway (it really is a different person)</span></span></span><span class="btns">Cancel</span></div>
                  <span class="chip a-pop" style="--d:21s;margin-top:.7rem">It checks the name only</span>
                </div>

                <!-- 9 — the record -->
                <div class="sc" data-scene="9" data-len="19">
                  <div class="sct a-fade" style="--d:.2s">Their record</div>
                  <div class="bnr a-pop" style="--d:4.3s">Customer added.</div>
                  <div class="phd a-rise" style="--d:1.5s"><div><div class="pt">Emma Fletcher</div><div class="psub"><span class="lk">&larr; Back to customers</span></div></div>
                    <span class="btnp a-ring" style="--d:13.5s">Book appointment</span></div>
                  <div class="fm">
                    <div class="g3">' . $fld('Town', 'Leamington Spa') . $fld('County', 'Warwickshire') . $fld('Postcode', '<span class="swap"><span class="a-out" style="--d:9s">CV32 5JP</span><span class="a-type" style="--d:9.1s;--ts:8;--tt:.6s">CV32 5PJ</span></span>', 'a-ring', '--d:8.4s') . '</div>
                    <div class="fact"><span class="btnp a-press" style="--d:11.4s">Save changes</span><span class="btns">Cancel</span></div>
                  </div>
                  <div class="bnr a-pop" style="--d:12.2s;margin-top:.6rem">Customer updated.</div>
                </div>

                <!-- 10 — recent quotes -->
                <div class="sc" data-scene="10" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Their quotes, at a glance</div>
                  <div class="sech a-fade" style="--d:1.5s">Recent quotes</div>
                  <div class="tbl qt">
                    <div class="tr th"><span>Quote #</span><span>Status</span><span class="n">Total</span><span>Created</span><span></span></div>
                    <div class="tr a-fly" style="--d:3s"><span><b>BEV-2026-0042</b></span><span><i class="bdg b-draft">draft</i></span><span class="n">&pound;1,140.00</span><span>6 Oct 2026</span><span class="lk a-ring" style="--d:9s">Open</span></div>
                    <div class="tr a-fly" style="--d:3.6s"><span><b>BEV-2026-0031</b></span><span><i class="bdg b-sent">sent</i></span><span class="n">&pound;486.00</span><span>18 Sep 2026</span><span class="lk">Open</span></div>
                    <div class="tr a-fly" style="--d:4.2s"><span><b>BEV-2026-0017</b></span><span><i class="bdg b-accepted">accepted</i></span><span class="n">&pound;912.50</span><span>2 Aug 2026</span><span class="lk">Open</span></div>
                  </div>
                  <div class="legend">
                    <i class="bdg b-draft a-pop" style="--d:14.9s">draft</i><i class="bdg b-sent a-pop" style="--d:15.5s">sent</i>
                    <i class="bdg b-accepted a-pop" style="--d:16.2s">accepted</i><i class="bdg b-ordered a-pop" style="--d:17s">ordered</i><i class="bdg b-rejected a-pop" style="--d:17.5s">rejected</i></div>
                  <span class="chip a-pop" style="--d:19s;margin-top:.7rem">Never quoted? This section isn&rsquo;t there at all.</span>
                </div>

                <!-- 11 — danger zone -->
                <div class="sc" data-scene="11" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">The Danger zone</div>
                  <div class="dz a-rise" style="--d:1s"><h4>Danger zone</h4><span class="btnd a-press" style="--d:4s">Delete customer</span></div>
                  <div class="cfm a-mid" style="--d:5.4s;--d2:9.2s">Delete Emma Fletcher? This cannot be undone.
                    <div class="act"><span class="btns">Cancel</span><span class="btnp">Yes, continue</span></div></div>
                  <div class="bnr a-pop" style="--d:9.6s;margin-top:.8rem;max-width:30rem">Customer deleted. Existing quotes have been kept but are no longer linked.</div>
                  <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                    <span class="chip a-pop" style="--d:14.2s">&#10003; Delete: a record made by mistake</span>
                    <span class="chip a-pop" style="--d:17.5s">&#8635; Someone with history: edit or merge</span></div>
                </div>

                <!-- 12 — how duplicates creep in -->
                <div class="sc" data-scene="12" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">How the same person ends up in twice</div>
                  <div class="doors">
                    <div class="door a-rise" style="--d:4s"><h4>&#128197; Calendar</h4>Booking a measure for someone not picked from the list creates a customer.</div>
                    <div class="door a-rise" style="--d:9.3s"><h4>&#128221; New quote</h4>A new name not picked from the list creates one too.</div>
                  </div>
                  <span class="chip a-pop" style="--d:13.2s;margin-top:.7rem;border-color:var(--err);color:var(--err)">Neither checks for the same name</span>
                  <div class="twin">
                    <span class="a-drop" style="--d:16.5s">Emma Fletcher &middot; Leamington Spa</span>
                    <span class="a-drop" style="--d:18s">Emma Fletcher &middot; Leamington Spa</span>
                  </div>
                </div>

                <!-- 13 — merging -->
                <div class="sc" data-scene="13" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Find duplicates &mdash; merge them</div>
                  <div class="howm a-rise" style="--d:1s"><b>How merging works:</b> the customer with the lowest id (the oldest record) is kept. All quotes, appointments and payments linked to the duplicate rows are re-pointed to the keeper, then the duplicate rows are deleted. <b>Cannot be undone</b>.</div>
                  <div class="grp a-rise" style="--d:5.3s"><h5>Emma Fletcher <small>(2 rows)</small></h5>
                    <div class="tbl dt">
                      <div class="tr th"><span>ID</span><span>Email</span><span>Town</span><span>Postcode</span><span>Quotes</span><span>Appts</span></div>
                      <div class="tr keep"><span>118<i class="kp a-pop" style="--d:8.5s">Keeper</i></span><span>emma.fletcher@gmail.com</span><span>Leamington Spa</span><span>CV32 5PJ</span><span class="a-ring" style="--d:20.8s"><i class="sw"><b class="a-out" style="--d:18.6s">3</b><b class="a-pop" style="--d:18.7s">4</b></i></span><span><i class="sw"><b class="a-out" style="--d:18.6s">1</b><b class="a-pop" style="--d:18.7s">2</b></i></span></div>
                      <div class="tr strike" style="--d:18.4s"><span>164</span><span>&nbsp;</span><span>Leamington Spa</span><span>CV32 5PJ</span><span class="a-ring" style="--d:20.8s">1</span><span>1</span></div>
                    </div>
                    <div style="margin-top:.45rem"><span class="btns a-press a-ring" style="--d:12s">Merge this group</span></div>
                  </div>
                  <div class="cfm a-mid" style="--d:12.6s;--d2:16s;top:46%">Merge this group? 1 duplicate row will be removed. Quote / appointment links are re-pointed to the keeper. This can&rsquo;t be undone.
                    <div class="act"><span class="btns">Cancel</span><span class="btnp">Yes, continue</span></div></div>
                  <span class="tst a-pop" style="--d:18.8s;top:auto;bottom:2.5rem;right:auto;left:0">Merged 1 group; removed 1 duplicate.</span>
                </div>

                <!-- 14 — payoff -->
                <div class="sc" data-scene="14" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">The payoff: a new quote fills itself in</div>
                  <div class="fm">
                    <div class="fg dlw"><span class="fl">Existing customer</span>
                      <span class="ib a-ring" style="--d:2s"><span class="swap"><span class="gph a-out" style="--d:3s">Type to search by name, town, or postcode...</span><span class="a-mid" style="--d:3s;--d2:7.8s"><span class="a-type" style="--d:3s;--ts:3;--tt:.5s">Emm</span></span><span class="a-fade" style="--d:7.8s">Emma Fletcher &mdash; Leamington Spa &mdash; CV32 5PJ</span></span></span>
                      <div class="dl a-mid" style="--d:4.5s;--d2:7.6s"><div class="hit">Emma Fletcher &mdash; Leamington Spa &mdash; CV32 5PJ</div><div>Emmett Price &mdash; Rugby &mdash; CV21 3AB</div></div>
                    </div>
                    ' . $fld('Customer name <em class="rq">*</em>', '<span class="a-fade" style="--d:8.8s">Emma Fletcher</span>', 'full') . '
                    <div class="g3">
                      ' . $fld('Email', '<span class="a-fade" style="--d:9.4s">emma.fletcher@gmail.com</span>') . '
                      ' . $fld('Phone <small>(landline)</small>', '<span class="a-fade" style="--d:10s">01926 334455</span>') . '
                      <div class="fg"><span class="fl">Mobile</span><span class="ib"><span class="a-fade" style="--d:10.6s">07700 900123</span></span>' . $waTick('<span class="a-pop" style="--d:11.2s">&#10003;</span>') . '</div>
                    </div>
                    <div class="g3">
                      ' . $fld('Address line 1', '<span class="a-fade" style="--d:12s">14 Clarendon Ave</span>') . '
                      ' . $fld('Town', '<span class="a-fade" style="--d:12.5s">Leamington Spa</span>') . '
                      ' . $fld('Postcode', '<span class="a-fade" style="--d:13s">CV32 5PJ</span>') . '
                    </div>
                  </div>
                  <span class="chip a-pop" style="--d:16.8s;margin-top:.7rem">Fill the customer in properly once &mdash; never retype it</span>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Customers</b> lives under <b>Retail</b> in the left-hand menu, and the line under the heading spells out what it holds:
             &ldquo;<b>End-customers belonging to &lt;your company&gt;</b>.&rdquo; These are the households you quote, measure and fit for.
             Businesses you supply are <b>not</b> in here &mdash; they are <b>trade accounts</b>, kept separately under <b>Trade</b>.
             The menu entry appears for anybody with <em>any one</em> of three permissions &mdash; <b>Create quotes</b>, <b>Create orders</b>
             or <b>View all customer jobs</b>.</p>

          <ul class="steps">
            <li><b>Find somebody.</b> The search box (<em>&ldquo;Search by name, email, phone, town or postcode...&rdquo;</em>) matches
                <b>name, email, phone, town or postcode</b>, so any scrap of what you remember will find them. Type it and press
                <b>Search</b>; a small <b>Clear</b> button then appears to take you back to everyone. The table shows <b>Name</b>, <b>Email</b>,
                <b>Phone</b>, <b>Town</b>, <b>Postcode</b>, a <b>Quotes</b> count, and two links: <b>Edit</b> opens the record, and
                <b>Book appointment</b> books a calendar visit against this customer. Empty-handed you will see either
                <em>&ldquo;No customers yet. Add your first customer &rarr;&rdquo;</em> or
                <em>&ldquo;No customers match &lsquo;&hellip;&rsquo;. View all &rarr;&rdquo;</em>.</li>
            <li><b>If somebody seems to be missing.</b> <b>The list stops at 100 names</b>, sorted A to Z &mdash; once you have more than that,
                the ones late in the alphabet are simply not on screen, so <b>search</b> rather than scroll. And a staff member
                <b>without</b> the <b>&ldquo;View all customer jobs&rdquo;</b> tick (set per person in <b>Setup &rarr; Users</b>) only sees
                customers who have a job <b>assigned to them</b>, plus any they added themselves. Two people can open the same page and see lists
                of different lengths; neither of them is broken. Admins see everyone.</li>
            <li><b>Name, and the three ways to reach them.</b> Click <b>+ Add customer</b> (the page is headed <b>Add customer</b>, with
                <b>&larr; Back to customers</b> under it). Only <b>Name</b> carries the red <span class="req">*</span> (up to 150 characters).
                Under it sit three boxes side by side: <b>Email</b> (it must be a proper address), <b>Phone <em>(landline)</em></b> and
                <b>Mobile</b>. Under the Mobile box is a tick-box, <b>&ldquo;Mobile is on WhatsApp&rdquo;</b>. Tick it when that number takes
                WhatsApp &mdash; it is what puts the <b>Send via WhatsApp</b> button on their quote later.</li>
            <li><b>Where the blinds are going.</b> <b>Address line 1</b> and <b>Address line 2</b> are two full-width boxes, then <b>Town</b>,
                <b>County</b> and <b>Postcode</b> share a row. There is <b>no postcode finder on this form</b> &mdash; type the address by hand.
                There is one address per customer; it is both the place you visit and the address on the paperwork.</li>
            <li><b>Notes, then save.</b> <b>Notes</b> is a four-line box for your own scribble &mdash; where to park, which door, the dog in the
                garden. Nobody outside the business sees it. Then <b>Save customer</b>; <b>Cancel</b> abandons everything and returns you to the
                list. A box you leave empty stores <b>nothing at all</b> &mdash; you can always come back and fill it in.</li>
            <li><b>The &ldquo;same name&rdquo; check.</b> If that name is already in the book, it does <b>not</b> save. An amber banner says
                <b>&ldquo;A customer with this name already exists.&rdquo;</b> &mdash; <em>&ldquo;Pick the existing customer below, or scroll down
                and click Save anyway if this really is a different person with the same name.&rdquo;</em> It lists up to five matches as
                <b>links</b>, each with their <b>town, postcode, email and phone</b>. Usually it is the same person: click their name and carry on
                with the record you already have. If it genuinely is a second person, the button now reads
                <b>&ldquo;Save anyway (it really is a different person)&rdquo;</b>. The check looks at the <b>name only</b>, and only when you
                <b>add</b> &mdash; renaming somebody on their record to an existing name is accepted.</li>
            <li><b>Their record.</b> Saving shows <b>&ldquo;Customer added.&rdquo;</b> and drops you on their page, headed with their name, with a
                <b>Book appointment</b> button at the top. It is the <b>same form again</b>, filled in &mdash; type over anything and press
                <b>Save changes</b> (<b>&ldquo;Customer updated.&rdquo;</b>).</li>
            <li><b>Recent quotes.</b> Below the form: their last five quotes, newest first &mdash; <b>Quote #</b> (your prefix, the year and a
                four-digit count, like <b>BEV-2026-0042</b>), a coloured <b>Status</b> badge, the <b>Total</b> (only for people allowed to see
                money), when it was <b>Created</b>, and <b>Open</b> to jump into the quote. The badge colours: <b>grey</b> draft, <b>blue</b> sent,
                <b>green</b> accepted, <b>yellow</b> ordered, <b>red</b> rejected. If they have never been quoted, the <b>whole section is not
                there</b> &mdash; it appears with their first quote.</li>
            <li><b>Find duplicates</b> (admins only &mdash; the button beside <b>+ Add customer</b>, <em>&ldquo;Find and merge customers with the
                same name&rdquo;</em>). Under the blue <b>How merging works</b> panel it counts what it found &mdash;
                <em>&ldquo;Found 1 duplicate name group with 1 redundant row in total.&rdquo;</em> &mdash; then shows each group as a card headed
                <b>&ldquo;Emma Fletcher (2 rows)&rdquo;</b>, listing <b>ID</b>, <b>Email</b>, <b>Phone</b>, <b>Town</b>, <b>Postcode</b>,
                <b>Quotes</b>, <b>Appts</b> and <b>Created</b>. The <b>oldest row is tinted green and badged &ldquo;Keeper&rdquo;</b>. Press
                <b>Merge this group</b> and confirm (<em>&ldquo;Merge this group? 1 duplicate row will be removed. Quote / appointment links are
                re-pointed to the keeper. This can&rsquo;t be undone.&rdquo;</em>): every quote, appointment and payment on the other rows moves
                onto the keeper and the spares are deleted &mdash; <em>&ldquo;Merged 1 group; removed 1 duplicate.&rdquo;</em> <b>Merge all
                duplicates</b> in the header does every group at once. Nothing to do? <em>&ldquo;No duplicates found &#127881; &mdash; Every
                customer in this tenant has a unique name. Nothing to merge.&rdquo;</em></li>
            <li><b>Where customers come from.</b> This form is one of three doors. Booking a <b>measure appointment</b> on the calendar for someone
                not picked from the list creates a customer (the installation address becomes their address), and starting a <b>New quote</b>
                with a name not picked from the list creates one too. <b>Neither of those checks for the same name</b> &mdash; which is how the
                same person ends up in the book twice, and why Find duplicates exists.</li>
            <li><b>The payoff.</b> On a <b>New quote</b>, the <b>Existing customer</b> box is a type-ahead &mdash;
                <em>&ldquo;Type to search by name, town, or postcode...&rdquo;</em>, hint <em>&ldquo;Type to filter &mdash; leave blank for a new
                customer.&rdquo;</em> Pick them and their name, email, landline, mobile, WhatsApp tick and full address all copy down into the same
                boxes you filled in here. The two extra boxes, <b>Customer reference (their order / PO)</b> and <b>Quote notes</b>, belong to that
                quote, not to the person.</li>
          </ul>
          <div class="oops"><b>The messages you will meet:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li>No name &rarr; <code>Name is required.</code></li>
               <li>A wonky email &rarr; <code>Please enter a valid email address.</code> (leave it blank if you do not have one)</li>
               <li>Name already in the book &rarr; <code>A customer with this name already exists.</code></li>
             </ul></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Deleting is permanent.</b> The red <b>Delete customer</b> button in the
             <b>Danger zone</b> (shown to admins and people with <b>View all customer jobs</b>) asks first &mdash;
             <em>&ldquo;Delete &lt;name&gt;? This cannot be undone.&rdquo;</em> with <b>Cancel</b> and <b>Yes, continue</b>. Go through with it and
             you are told: <em>&ldquo;Customer deleted. Existing quotes have been kept but are no longer linked.&rdquo;</em> The work survives but
             belongs to nobody. Only delete a record you created by mistake; for somebody with history, edit them or merge them instead.</div></div>
          <p><b>One last thing:</b> there is <b>no bulk customer import</b>. Every name in here arrived one of three ways &mdash; typed on this
             form, created by a calendar booking, or created by a New quote.</p>',
        'script'  => [
            ['1', 'Your retail address book',     'Customers is your address book for retail. These are the households you quote, measure and fit for. You will find it under Retail, in the menu on the left. If you supply other businesses, they are not in here. They are trade accounts, and they are kept separately, under Trade. Keeping the two apart means each list only shows the people it should.', 1],
            ['2', 'Finding someone',              'To find someone, use the search box at the top. It looks at the name, the email, the phone number, the town and the postcode. So any scrap you remember will do. Half a surname, or the start of a postcode. Type it in, and press Search. A Clear button then appears, to take you back to everyone.', 2],
            ['3', 'Somebody seems to be missing', 'If somebody seems to be missing, there are two usual reasons. First, the list only shows the first hundred names, from A to Z. Once you have more than that, search rather than scroll. Second, a staff member without View all customer jobs only sees the customers on their own jobs. Neither one is a fault.', 3],
            ['4', 'Only the name is required',    'To add someone new, click plus Add customer. Only the Name has a red star, so the name is the only thing you must give. Everything else is optional. But every box you fill in now saves typing later, because a new quote can copy it all across.', 4],
            ['5', 'Three ways to reach them',     'Under the name are three ways to reach them, side by side. Email, Phone, which is the landline, and Mobile. If you type an email, it must be a proper address. Under the mobile is a tick box: Mobile is on WhatsApp. Tick it when that number takes WhatsApp. That is what lets you send them their quote on WhatsApp later.', 5],
            ['6', 'Where the blinds are going',   'Next, where the blinds are going. Address line one and address line two, then Town, County and Postcode on one row. There is no postcode finder on this form, so type it in by hand. Each customer has one address. It is both where you visit, and where the paperwork goes.', 6],
            ['7', 'Notes, then save',             'Notes is your own scribble. Where to park, which door, the dog in the garden. Nobody outside the business sees it. Then click Save customer. Cancel throws it all away, and goes back to the list. Any box you leave empty is simply left empty, and you can fill it in later.', 7],
            ['8', 'The same-name check',          'If that name is already in the book, it does not save straight away. An amber banner says a customer with this name already exists, and lists who it found, with their town, postcode, email and phone. Usually it is the same person, so click their name. If it really is someone different, click Save anyway, and it saves.', 8],
            ['9', 'Their record',                 'Once saved, you land on their record, with a green message: Customer added. It is the same form again, filled in. To fix a postcode, type over it, and click Save changes. And at the top is a Book appointment button, to book a visit straight against this customer.', 9],
            ['10', 'Recent quotes',               'Below the form is Recent quotes. It shows their last five, newest first, with the quote number, the status, and the date. Click Open to jump into one. The status colours tell you where each job stands. Grey is a draft, blue is sent, and green is accepted. If they have never had a quote, this section is not there at all.', 10],
            ['11', 'The Danger zone',             'At the very bottom is the Danger zone, with a red Delete customer button. It asks you first, because deleting cannot be undone. Their quotes are kept, but they are no longer linked to anyone. So only delete a record you made by mistake. For someone with history, edit them, or merge them instead.', 11],
            ['12', 'How duplicates creep in',     'So how does the same person end up in the book twice? Booking a measure on the calendar, for someone new, creates a customer. Starting a new quote with a new name creates one too. Neither of those checks for the same name. That is handy when you are busy, but over time it leaves doubles behind.', 12],
            ['13', 'Merging duplicates',          'To tidy them up, an admin clicks Find duplicates on the Customers page. It groups customers with the same name. The oldest record is the keeper, marked in green. Click Merge this group, and every quote, appointment and payment moves onto the keeper. The spares are then deleted. Check the counts first, because a merge cannot be undone.', 13],
            ['14', 'The payoff',                  'And here is the payoff. On a new quote, type two or three letters into Existing customer, and pick them from the list. Their name, email, phone numbers, WhatsApp tick and full address all copy down the form. Nothing to retype. That is why it is worth filling the customer in properly, the first time.', 14],
        ],
];
