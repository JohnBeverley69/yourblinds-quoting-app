<?php
declare(strict_types=1);

/**
 * Guide: products-options — "Adding options" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors admin/products/extras.php (the options list + Add option form),
 * admin/products/extra.php (the live choices grid — _partials/choices_grid.php,
 * choices_grid_js.php, choices_bulk_add.php — and the Sub-options section),
 * admin/products/extra-edit.php (Edit option: the toggle stack),
 * admin/products/extra-choice-edit.php (Edit choice: per-unit, per-metre basis,
 * thumbnail, width-based price table), admin/products/extras-copy.php and the
 * product edit page's Options tile + Live preview. Every label, button and
 * message is copied from those files.
 *
 * v2: one SCENE per script line. Beat times are worked out from where the
 * matching words fall in the voice-over ($at); data-len = line length ÷ 13.6.
 */

// ── The voice-over, by step ───────────────────────────────────────────
$S = [
    1  => ['1',  'Options and choices',
        'Options are the extras your salesperson picks for each blind. Control type, bottom weight, bracket colour. They are not the fabrics, which live on a page of their own. An option has no price of its own. The price sits on its choices. So the option Control type might hold two choices, Cord and Motorised, and only Motorised costs extra.'],
    2  => ['2',  'Reading the options list',
        'To get there, open the product, and click the Options tile. Here is the list. A Required pill means the customer must pick one. A row pushed in under an arrow only appears once another choice is picked. A pencil and the word number means the option just asks for a typed measurement. Drag the handle on the left to change the order your salesperson sees.'],
    3  => ['3',  'Add an option',
        'Above the list is the Add option form. Name it after what the customer is choosing, like Control type. Required is ticked already. Leave it ticked if they must choose, and untick it for an extra they can skip. Allow multiple choices turns the list into tick boxes, so they can pick more than one. Then press Add option, and you go straight to its choices.'],
    4  => ['4',  'Asking for a number',
        'Some options need a measurement, not a list. A wand length, for example. Tick Also show a number input. A box appears, already saying Length, in millimetres, ready for you to type over. Keep the unit in the name, so the salesperson knows what to type. An option that only asks for a number needs no choices, so you stay on the list, and it tells you it is ready to use.'],
    5  => ['5',  'Adding choices',
        'This is the choices page. It works like a spreadsheet, and there is no Save button. Type a label in the bottom row, and press Enter. It appears above, ready to price. Click any cell to change it. Tab or Enter saves it, and Escape cancels. The badge at the top is your receipt. It says Saving while it works, then All changes saved.'],
    6  => ['6',  'A whole list at once',
        'Got a list? Click Bulk add, type one label per line, like Left and then Right, and press Add. Any label the option already has is skipped, so nothing doubles up. Type a repeat in the bottom row instead, and it asks you first, in case you want a second one on purpose. Duplicate copies a choice, and the cross deletes it.'],
    7  => ['7',  'Pricing a choice',
        'Now the prices. Each choice has three price boxes, and nought in all three means free. Flat pounds is a set amount, the same at every size. A hundred and twenty pounds for Motorised, say. Percent is a share of the blind\'s own price, not of the other extras. Pounds per metre charges by length. Fill in more than one, and they are added together.'],
    8  => ['8',  'Where a choice is offered',
        'Next, where a choice is offered. Available on limits it to certain systems, so a motor can stay off your corded system. Bands limits it to certain fabric bands. Leave both alone, and the choice shows everywhere. Each heading has a Set all link. Pick a value once, press Apply to all, and every row gets it. On a long list, that saves a lot of clicks.'],
    9  => ['9',  'Default, Active and Face value',
        'Three tick boxes finish the row. Default is the choice that comes already picked, one for each system. Untick Active to hide a choice from quotes, without deleting it. Face value starts ticked, and means the price you type is the price charged. Untick it only for a supplier\'s add on, so it goes through your buying discount and markup, like the blind does.'],
    10 => ['10', 'The choice\'s own page',
        'Some settings need more room, so click Edit on a row. Price per unit is for things sold by the piece, like brackets. The salesperson types how many, and the price is multiplied. Give that box a name under Ask for a number on this choice. Say, Number of brackets. This page also sets what a per metre price runs along, and can add a picture of the choice.'],
    11 => ['11', 'Prices that change with width',
        'Further down is a price table by width, for an extra that costs more on a wider blind. Type one row per line, a width and then a price, or upload the supplier\'s sheet. It uses the first row that is at least as wide as the blind, so your last row is the widest it can go. Back in the grid, a small badge shows the choice is priced by width.'],
    12 => ['12', 'Sub-options',
        'A sub-option waits its turn. Motor type should only appear once Motorised is picked. Click Sub-option on the Motorised row. The Add option form opens, with Motorised already ticked under Appears when. Name it, and press Add option. Tick more than one parent, and it shows when any of them is picked. It is a full option, with choices and prices of its own.'],
    13 => ['13', 'Settings on the option',
        'Each option has settings of its own. Click Edit beside it on the options list. As well as Required and multiple choices, Show above the size fields puts the option before width and drop. Splits the blind into equal panels is for a panel count, like a shutter\'s number of panels. And untick Active to hide the whole option, without deleting it.'],
    14 => ['14', 'Renaming safely',
        'A word about renaming. The factory\'s build rules find options and choices by their names. Rename on the Edit page and press Save changes, and the rules are updated for you. Type over a label in the grid, and they are not, so the rules can stop working. Use the grid for prices and ticks, and the Edit page for names.'],
    15 => ['15', 'Copying from another product',
        'Setting up a similar product? Copy from another product, at the top of the options page, saves starting again. Pick the product to copy from, and untick any options you do not want. Their choices come too, and any sub-options are brought along with them. An option this product already has, with the same name, is skipped.'],
    16 => ['16', 'Check it in Live preview',
        'Last of all, see it the way your salesperson will. On the product\'s edit page, open Live preview. Pick Motorised, and Motor type appears underneath, and the price goes up. Pick Cord, and it stays hidden. If something is missing, check that the option and its choices are ticked Active.'],
];

/** "Ns" — when the words $p are spoken in line $n (13.6 characters a second). */
$at = static function (int $n, string $p, float $plus = 0.0) use ($S): string {
    $i = mb_strpos($S[$n][2], $p);
    if ($i === false) { $GLOBALS['gd_at_miss'][] = "$n: $p"; $i = 0; }
    return round($i / 13.6 + $plus, 1) . 's';
};
$len = static fn (int $n): string => (string) max(8, (int) round(mb_strlen($S[$n][2]) / 13.6));

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

/**
 * The choices grid (_partials/choices_grid.php), with only the columns a scene
 * needs. $rows: each row is [colKey => innerHtml, '_cls' => extra class,
 * '_st' => style]. $head[colKey] adds html after a heading.
 */
$cg = static function (array $cols, array $rows, array $head = []): string {
    $W = ['drag' => '1rem', 'lbl' => 'minmax(4.6rem,1fr)', 'sys' => '5.6rem', 'bands' => '4.4rem', 'flat' => '3.5rem',
          'pct' => '2.9rem', 'pm' => '2.9rem', 'def' => '2.6rem', 'act' => '2.6rem', 'fv' => '2.8rem', 'acts' => '9.2rem'];
    $H = ['drag' => '', 'lbl' => 'Label', 'sys' => 'Available on', 'bands' => 'Bands', 'flat' => 'Flat &pound;', 'pct' => '%',
          'pm' => '&pound;/m', 'def' => 'Default', 'act' => 'Active', 'fv' => 'Face value', 'acts' => ''];
    $tpl = implode(' ', array_map(static fn ($c) => $W[$c], $cols));
    $h = '<div class="cg"><div class="r hd" style="grid-template-columns:' . $tpl . '">';
    foreach ($cols as $c) $h .= '<span class="c c-' . $c . '">' . $H[$c] . ($head[$c] ?? '') . '</span>';
    $h .= '</div>';
    foreach ($rows as $r) {
        $h .= '<div class="r ' . ($r['_cls'] ?? '') . '" style="grid-template-columns:' . $tpl . ';' . ($r['_st'] ?? '') . '">';
        foreach ($cols as $c) $h .= '<span class="c c-' . $c . '">' . ($r[$c] ?? ($c === 'drag' ? '&#8942;&#8942;' : '')) . '</span>';
        $h .= '</div>';
    }
    return $h . '</div>';
};
$n   = static fn (string $v, string $cls = '', string $st = ''): string => '<span class="ni ' . $cls . '" style="' . $st . '">' . $v . '</span>';
$tk  = static fn (bool $on, string $cls = '', string $st = ''): string => '<span class="tick ' . ($on ? 'on ' : '') . $cls . '" style="' . $st . '">&#10003;</span>';
$sys = static fn (string $v = 'All systems', string $cls = '', string $st = ''): string => '<span class="selb sm ' . $cls . '" style="' . $st . '">' . $v . '</span>';
$rowAct = '<span class="ra"><u>Edit</u> <u>+ Sub-option</u> <u>Duplicate</u> <b>&times;</b></span>';
$bdg = static fn (string $bandsTxt = 'All bands'): string => '<span class="selb sm">' . $bandsTxt . '</span>';

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Adding options',
        'eyebrow' => 'Products',
        'v'       => 2,
        'blurb'   => 'Options and their choices: adding them, the live choices grid, the ways to price a choice, where it is offered, sub-options, the option\'s own settings, copying — and checking it in Live preview.',
        'lede'    => 'Options are the <b>extras</b> your salesperson picks for each blind &mdash; <em>Control type</em>, <em>Bottom weight</em>,
                      <em>Bracket colour</em>. The option itself carries <b>no price</b>; the price lives on its <b>choices</b>. This guide goes
                      slowly, one idea per chapter &mdash; watch it through once, then use <b>Jump to a chapter</b> to go back over any part.
                      To get there: <b>Products</b> &rarr; the product &rarr; the <b>Options</b> tile.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .app{ grid-template-columns:132px minmax(0,1fr); }
          .gd .sc{ position:relative; min-height:405px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }

          /* common bits */
          .gd .wc{ border:1px solid var(--line); border-radius:12px; padding:.65rem .8rem; background:var(--surface); max-width:33rem; }
          .gd .wc h3{ margin:0 0 .2rem; font-size:.86rem; color:var(--ink); }
          .gd .fl{ display:block; font-size:.56rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin:.45rem 0 .2rem; }
          .gd .inp{ display:grid; align-items:center; min-height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:0 .5rem;
                    font-size:.76rem; background:var(--surface); color:var(--ink); position:relative; }
          .gd .inp > span{ grid-area:1/1; }
          .gd .ph{ color:var(--faint); }
          .gd .tx{ display:block; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.3rem .5rem; font-family:ui-monospace,Menlo,Consolas,monospace;
                   font-size:.68rem; min-height:3rem; background:var(--surface); color:var(--ink); line-height:1.5; }
          .gd .cbr{ display:flex; gap:.45rem; align-items:flex-start; font-size:.7rem; color:var(--ink); margin:.3rem 0; line-height:1.35; }
          .gd .cbr small{ display:block; color:var(--faint); font-size:.6rem; }
          .gd .cbr .tick{ flex:0 0 auto; margin-top:.05rem; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.28rem .7rem; font-size:.68rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink);
                     border-radius:7px; padding:.24rem .6rem; font-size:.66rem; font-weight:600; white-space:nowrap; }
          .gd .alr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:6px; padding:.32rem .55rem; font-size:.66rem; font-weight:600;
                    color:var(--ink); margin:0 0 .45rem; max-width:33rem; box-sizing:border-box; line-height:1.4; }
          .gd .alr.inf{ background:color-mix(in srgb,#3b82f6 10%,var(--surface)); border-left-color:#3b82f6; font-weight:500; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.22rem .6rem; font-size:.68rem; font-weight:700; color:var(--ink); }
          .gd .chips{ display:flex; flex-wrap:wrap; gap:.35rem; margin:.5rem 0; }
          .gd .sw{ display:inline-grid; } .gd .sw > *{ grid-area:1/1; }
          .gd .swb{ display:grid; align-items:start; } .gd .swb > *{ grid-area:1/1; }
          .gd .arrow{ color:var(--faint); font-weight:800; margin:0 .15rem; }
          .gd .kbd{ display:inline-block; background:#0f172a; color:#fff; border-radius:6px; padding:.15rem .4rem; font-size:.62rem; font-weight:800;
                    font-family:ui-monospace,Menlo,monospace; box-shadow:0 3px 0 #334155; }
          .gd .selb{ display:inline-flex; align-items:center; justify-content:space-between; gap:.5rem; min-width:8rem; border:1px solid var(--border-strong,#c7ccd4);
                     border-radius:6px; padding:.18rem .45rem; font-size:.7rem; background:var(--surface); color:var(--ink); box-sizing:border-box; }
          .gd .selb::after{ content:"\25BE"; color:var(--faint); font-size:.58rem; }
          .gd .selb.sm{ min-width:0; width:100%; font-size:.58rem; padding:.1rem .3rem; gap:.2rem; }
          .gd .crumbs{ display:flex; align-items:center; flex-wrap:wrap; gap:.2rem; margin:0 0 .6rem; }
          .gd .ttl{ font-size:.95rem; font-weight:800; color:var(--ink); margin:0 0 .45rem; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
          .gd .badge{ display:inline-flex; font-size:.58rem; font-weight:700; border-radius:999px; padding:.1rem .5rem; }
          .gd .badge.ok{ background:var(--good-wash); color:var(--good); } .gd .badge.wip{ background:color-mix(in srgb,#f59e0b 18%,transparent); color:#b45309; }

          /* the options list (extras.php) */
          .gd .ol{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:33rem; }
          .gd .ol > div{ display:grid; grid-template-columns:1.2rem 1fr 3.5rem; align-items:center; gap:.3rem; padding:.36rem .55rem; border-top:1px solid var(--line-2); font-size:.72rem; color:var(--ink); }
          .gd .ol > div:first-child{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.58rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .ol .dg{ color:var(--faint); letter-spacing:-.1em; }
          .gd .ol .num{ text-align:right; color:var(--accent); }
          .gd .ol .sub{ padding-left:1.4rem; position:relative; }
          .gd .ol .sub::before{ content:"\21B3"; position:absolute; left:.2rem; top:-.05rem; color:var(--faint); }
          .gd .ol .pc{ display:block; color:var(--faint); font-size:.6rem; }
          .gd .rq{ display:inline-block; padding:.02rem .4rem; font-size:.52rem; font-weight:700; color:#fff; background:#1f3b5b; border-radius:999px; margin-left:.35rem;
                   text-transform:uppercase; letter-spacing:.05em; vertical-align:middle; }
          .gd .op{ display:inline-block; padding:.02rem .4rem; font-size:.52rem; font-weight:700; color:var(--faint); background:var(--line); border-radius:999px; margin-left:.35rem;
                   text-transform:uppercase; letter-spacing:.05em; vertical-align:middle; }

          /* the choices grid */
          .gd .cg{ border:1px solid var(--line); border-radius:8px; overflow:hidden; max-width:34rem; font-size:.66rem; background:var(--surface); }
          .gd .cg .r{ display:grid; align-items:center; border-top:1px solid var(--line-2); min-height:1.75rem; }
          .gd .cg .r.hd{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.56rem; min-height:1.5rem; }
          .gd .cg .c{ padding:.18rem .3rem; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; color:var(--ink); }
          .gd .cg .hd .c{ color:var(--soft); overflow:visible; white-space:normal; line-height:1.1; }
          .gd .cg .c-drag{ color:var(--faint); letter-spacing:-.1em; }
          .gd .cg .c-lbl{ white-space:normal; line-height:1.3; }
          .gd .cg .c-def, .gd .cg .c-act, .gd .cg .c-fv{ text-align:center; }
          .gd .cg .r.nw .c-lbl{ color:var(--faint); font-style:italic; }
          .gd .cg .tick{ width:14px; height:14px; font-size:.52rem; }
          .gd .ni{ display:block; border:1px solid var(--line); border-radius:4px; padding:.05rem .25rem; text-align:right; font-variant-numeric:tabular-nums; background:var(--surface); }
          .gd .ra{ font-size:.58rem; color:var(--accent); display:flex; gap:.3rem; align-items:center; }
          .gd .ra b{ color:var(--err); font-size:.75rem; }
          .gd .sa{ color:#2563eb; font-weight:400; font-size:.52rem; margin-left:.2rem; border-radius:3px; }
          .gd .wtb{ display:inline-flex; align-items:center; gap:.2rem; margin-left:.25rem; padding:0 .3rem; font-size:.54rem; font-weight:600; color:var(--soft);
                    background:var(--panel); border:1px solid var(--line); border-radius:4px; }
          .gd .ghint{ font-size:.6rem; color:var(--faint); margin:.35rem 0 0; display:flex; justify-content:space-between; align-items:center; gap:.5rem; flex-wrap:wrap; max-width:34rem; }

          /* pop-overs / dialogs */
          .gd .pop{ position:absolute; z-index:4; background:var(--surface); border:1px solid var(--line); border-radius:10px; box-shadow:var(--gd-shadow);
                    padding:.5rem .6rem; font-size:.66rem; color:var(--ink); }
          .gd .pop label{ display:flex; align-items:center; gap:.35rem; margin:.15rem 0; }
          .gd .pop hr{ border:0; border-top:1px solid var(--line); margin:.3rem 0; }
          .gd .dlg{ border:1px solid var(--line); border-radius:12px; padding:.65rem .8rem; background:var(--surface); box-shadow:var(--gd-shadow); max-width:22rem; }
          .gd .dlg h4{ margin:0 0 .3rem; font-size:.8rem; color:var(--ink); }
          .gd .dlg p{ margin:0 0 .45rem; font-size:.62rem; color:var(--soft); line-height:1.45; }
          .gd .dlgact{ display:flex; gap:.4rem; justify-content:flex-end; margin-top:.5rem; }
          .gd .cfm{ position:absolute; z-index:5; max-width:19rem; background:var(--surface); border:1px solid var(--line); border-radius:12px; box-shadow:var(--gd-shadow);
                    padding:.6rem .75rem; font-size:.68rem; color:var(--ink); line-height:1.45; }
          .gd .cfm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.5rem; }

          /* edit pages */
          .gd .fs{ border:1px solid var(--line); border-radius:10px; padding:.45rem .65rem .55rem; margin:.45rem 0; max-width:33rem; }
          .gd .fs .lg{ font-size:.56rem; text-transform:uppercase; letter-spacing:.05em; font-weight:700; color:var(--soft); margin-bottom:.25rem; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem; max-width:33rem; }
          .gd .tog{ border:1px solid var(--line); border-radius:10px; padding:.3rem .65rem; max-width:33rem; }
          .gd .tog .cbr{ margin:.32rem 0; }
          .gd .lookup{ display:grid; grid-template-columns:auto auto; gap:.2rem .8rem; font-size:.66rem; font-family:ui-monospace,Menlo,Consolas,monospace; }
          .gd .lookup span{ padding:.08rem .35rem; border-radius:4px; }
          .gd .hit{ background:var(--accent-wash); outline:2px solid var(--accent); outline-offset:-2px; font-weight:700; }
          .gd .gate{ display:inline-block; font-size:.58rem; background:var(--accent-wash); color:var(--accent-ink); border-radius:999px; padding:.04rem .45rem; margin-left:.25rem; }

          /* quote / preview mocks */
          .gd .qb{ border:1px solid var(--line); border-radius:12px; padding:.55rem .7rem; background:var(--panel); max-width:22rem; }
          .gd .qb .qr{ display:grid; grid-template-columns:7rem 1fr; gap:.4rem; align-items:center; margin:.3rem 0; font-size:.68rem; color:var(--soft); }
          .gd .qb .qr b{ color:var(--ink); }
          .gd .tree{ display:flex; align-items:center; flex-wrap:wrap; gap:.4rem; margin:.7rem 0 0; }
          .gd .tree .opt{ border:2px solid var(--ink); border-radius:10px; padding:.35rem .6rem; font-size:.74rem; font-weight:800; color:var(--ink); background:var(--surface); }
          .gd .tree .opt small{ display:block; font-weight:600; color:var(--faint); font-size:.56rem; }
          .gd .tree .chs{ display:flex; flex-direction:column; gap:.3rem; }
          .gd .tree .ch{ border:1px solid var(--line); border-radius:999px; padding:.18rem .6rem; font-size:.68rem; font-weight:700; color:var(--ink); background:var(--surface); }
          .gd .tree .ch em{ font-style:normal; color:var(--good); margin-left:.3rem; }
          .gd .price{ font-size:1.05rem; font-weight:800; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .drw{ border:1px solid var(--line); border-radius:12px; background:var(--surface); box-shadow:var(--gd-shadow); max-width:20rem; overflow:hidden; }
          .gd .drw .dh{ display:flex; justify-content:space-between; align-items:center; padding:.4rem .65rem; border-bottom:1px solid var(--line); font-size:.78rem; font-weight:800; color:var(--ink); }
          .gd .drw .pin{ padding:.45rem .65rem; background:var(--good-wash); border-bottom:1px solid var(--line); }
          .gd .drw .bd{ padding:.35rem .65rem .6rem; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; }
            .gd .side{ display:none; }
            .gd .sc{ min-height:470px; }
            .gd .two{ grid-template-columns:1fr; }
            .gd .cg{ overflow-x:auto; }
            .gd .cg .c-acts, .gd .cg .c-drag{ display:none; }
            .gd .cg .r{ grid-template-columns:none !important; grid-auto-flow:column; grid-auto-columns:minmax(2.5rem,auto); }
            .gd .cg .c{ box-sizing:border-box; }
            .gd .cg .c-lbl{ width:5.6rem; } .gd .cg .c-sys{ width:5rem; } .gd .cg .c-bands{ width:4rem; } .gd .cg .c-flat{ width:3.4rem; }
            .gd .cg .c-pct, .gd .cg .c-pm{ width:2.9rem; } .gd .cg .c-def, .gd .cg .c-act, .gd .cg .c-fv{ width:2.9rem; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / options</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a class="on">Products</a><a>Users</a><a>Settings</a><a>Trade terms</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="ttl">Roller Blind &mdash; Options</div>
                  <p class="scs"><b>Options</b> are the things your salesperson picks for each blind when building a quote. Add an option, then click
                     into it to set up its <b>choices</b>.</p>
                  <div class="ol">
                    <div><span></span><span>Name</span><span style="text-align:right">Choices</span></div>
                    <div><span class="dg">&#8942;&#8942;</span><span><b>Control type</b><span class="rq">Required</span></span><span class="num">2</span></div>
                    <div><span class="dg">&#8942;&#8942;</span><span><b>Bottom weight</b><span class="op">Optional</span></span><span class="num">3</span></div>
                    <div><span class="dg">&#8942;&#8942;</span><span><b>Bracket colour</b><span class="rq">Required</span></span><span class="num">4</span></div>
                  </div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; sixteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — options and choices -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  <div class="sct a-fade" style="--d:.2s">On a quote line, the salesperson picks:</div>
                  <div class="qb a-rise" style="--d:.5s">
                    <div class="qr a-fly" style="--d:' . $at(1, 'Control type, bottom') . '"><b>Control type</b><span class="selb">Motorised</span></div>
                    <div class="qr a-fly" style="--d:' . $at(1, 'bottom weight') . '"><b>Bottom weight</b><span class="selb">Chained</span></div>
                    <div class="qr a-fly" style="--d:' . $at(1, 'bracket colour') . '"><b>Bracket colour</b><span class="selb">White</span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(1, 'They are not the fabrics') . '">&#10007; not the fabrics &mdash; they have their own page</span></div>
                  <div class="tree">
                    <div class="opt a-pop" style="--d:' . $at(1, 'An option has no price') . '">Control type<small>an option &middot; no price</small></div>
                    <span class="arrow a-fade" style="--d:' . $at(1, 'The price sits') . '">&rarr;</span>
                    <div class="chs">
                      <span class="ch a-fly" style="--d:' . $at(1, 'Cord and') . '">Cord <em style="color:var(--faint)">&pound;0.00</em></span>
                      <span class="ch a-fly" style="--d:' . $at(1, 'Motorised, and') . '"><span class="a-ring" style="--d:' . $at(1, 'only Motorised') . ';border-radius:999px;padding:0 .2rem">Motorised <em>+&pound;120.00</em></span></span>
                    </div>
                    <span class="chip a-pop" style="--d:' . $at(1, 'The price sits') . '">&larr; the choices carry the prices</span>
                  </div>
                </div>

                <!-- 2 — reading the list -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="crumbs">
                    <span class="chip a-pop" style="--d:' . $at(2, 'open the product') . '">Roller Blind</span><span class="arrow a-fade" style="--d:' . $at(2, 'click the Options') . '">&rarr;</span>
                    <span class="chip a-pop" style="--d:' . $at(2, 'click the Options') . ';border-color:var(--accent)">Options &middot; click to manage</span>
                  </div>
                  <div class="ttl a-fade" style="--d:' . $at(2, 'Here is the list') . '">Roller Blind &mdash; Options</div>
                  <div class="ol a-rise" style="--d:' . $at(2, 'Here is the list') . '">
                    <div><span></span><span>Name</span><span style="text-align:right">Choices</span></div>
                    <div><span class="dg">&#8942;&#8942;</span><span><b>Control type</b><span class="rq a-ring" style="--d:' . $at(2, 'A Required pill') . '">Required</span></span><span class="num">2</span></div>
                    <div><span class="dg">&#8942;&#8942;</span><span class="sub a-ring" style="--d:' . $at(2, 'A row pushed') . ';border-radius:6px"><b>Motor type</b><span class="rq">Required</span>
                      <span class="pc">Appears when <b>Control type = Motorised</b> is selected</span></span><span class="num">3</span></div>
                    <div><span class="dg">&#8942;&#8942;</span><span><b>Bottom weight</b><span class="op">Optional</span></span><span class="num">2</span></div>
                    <div><span class="dg a-ring" style="--d:' . $at(2, 'Drag the handle') . ';border-radius:4px">&#8942;&#8942;</span><span><b>Wand length</b><span class="op">Optional</span></span>
                      <span class="num a-ring" style="--d:' . $at(2, 'A pencil') . ';border-radius:4px;font-size:.62rem;color:var(--soft)">&#9998; number</span></div>
                  </div>
                  <p class="scs a-fade" style="--d:' . $at(2, 'Drag the handle') . ';margin-top:.5rem">Drag the <b>&#8942;&#8942;</b> handle to reorder. <i>Saving&hellip;</i></p>
                </div>

                <!-- 3 — add an option -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="wc a-rise" style="--d:.3s">
                    <h3>Add option</h3>
                    <p class="scs" style="margin:0 0 .3rem">Examples: Control side, Control type, Draw side, Lining, Motor type, Headrail colour.</p>
                    <div class="two" style="grid-template-columns:1.3fr 1fr">
                      <div><span class="fl">Name <span class="req">*</span></span>
                        <span class="inp a-ring" style="--d:' . $at(3, 'Name it after') . '"><span class="ph a-out" style="--d:' . $at(3, 'like Control type') . '">e.g. Control side</span>
                          <span><span class="a-type" style="--d:' . $at(3, 'like Control type') . ';--ts:12;--tt:.9s">Control type</span></span></span></div>
                      <div style="padding-top:.9rem">
                        <div class="cbr"><span class="a-ring" style="--d:' . $at(3, 'Required is ticked') . ';border-radius:5px;display:inline-flex">' . $tk(true) . '</span><span>Required</span></div>
                        <div class="cbr"><span class="a-ring" style="--d:' . $at(3, 'Allow multiple') . ';border-radius:5px;display:inline-flex">' . $tk(false) . '</span><span>Allow multiple choices
                          <small>renders as tick-boxes &mdash; salesperson can pick any combination</small></span></div>
                      </div>
                    </div>
                    <span class="fl">Appears when (optional)</span>
                    <p class="scs" style="margin:0">No other choices on this product yet. Add some options + choices first if you want this option to be gated.</p>
                    <div style="margin-top:.5rem"><span class="btnp a-press a-ring" style="--d:' . $at(3, 'press Add option') . '">Add option</span></div>
                  </div>
                  <div class="a-move" style="--fx:60%;--fy:22rem;--tx:9%;--ty:14.7rem;--d:' . $at(3, 'press Add option', -1.6) . ';--md:1.4s">' . $ptr . '</div>
                  <div class="ttl a-fade" style="--d:' . $at(3, 'straight to its choices') . ';margin-top:.7rem"><span class="arrow">&rarr;</span> Control type &mdash; Choices</div>
                </div>

                <!-- 4 — asking for a number -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="wc">
                    <span class="fl" style="margin-top:0">Name <span class="req">*</span></span><span class="inp">Wand length</span>
                    <div class="cbr" style="margin-top:.55rem"><span class="tick a-sel" style="--d:' . $at(4, 'Tick Also') . '">&#10003;</span><span><b>Also show a number input next to this option</b>
                      <small>For things like wand length, cable length, etc. &mdash; the salesperson types a value alongside picking a choice. Recorded on the quote line for supplier docs.</small></span></div>
                    <div class="a-rise" style="--d:' . $at(4, 'A box appears') . ';margin-left:1.6rem">
                      <span class="fl">What to call this field</span>
                      <span class="inp a-ring" style="--d:' . $at(4, 'Keep the unit') . '"><span class="a-out" style="--d:' . $at(4, 'type over') . '">Length (mm)</span>
                        <span><span class="a-type" style="--d:' . $at(4, 'type over', .5) . ';--ts:16;--tt:1s">Wand length (mm)</span></span></span>
                    </div>
                    <div style="margin-top:.5rem"><span class="btnp a-press" style="--d:' . $at(4, 'An option that only') . '">Add option</span></div>
                  </div>
                  <div class="alr a-pop" style="--d:' . $at(4, 'you stay on the list') . ';margin-top:.6rem">Option &ldquo;Wand length&rdquo; added &mdash; it captures a typed number, so it needs no choices and is ready to use.
                    Open it only if you also want pickable choices alongside the number.</div>
                  <div class="ol a-rise" style="--d:' . $at(4, 'it tells you') . '">
                    <div><span></span><span>Name</span><span style="text-align:right">Choices</span></div>
                    <div><span class="dg">&#8942;&#8942;</span><span><b>Wand length</b><span class="op">Optional</span></span><span class="num" style="font-size:.62rem;color:var(--soft)">&#9998; number</span></div>
                  </div>
                </div>

                <!-- 5 — adding choices -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="ttl">Control type &mdash; Choices
                    <span class="sw"><span class="badge ok a-out" style="--d:' . $at(5, 'Tab or Enter') . '">All changes saved</span>
                      <span class="badge wip a-mid" style="--d:' . $at(5, 'Tab or Enter') . ';--d2:' . $at(5, 'then All changes') . '">Saving&hellip;</span>
                      <span class="badge ok a-fade a-ring" style="--d:' . $at(5, 'then All changes') . '">All changes saved</span></span></div>
                  <p class="scs">Click any cell to edit. Tab or Enter saves; Escape cancels. Type a label in the last row to add a new choice.</p>
                  ' . $cg(['drag', 'lbl', 'sys', 'flat', 'pct', 'pm', 'acts'], [
                        ['lbl' => '<span class="a-fade" style="--d:' . $at(5, 'press Enter', .3) . '">Cord</span>', 'drag' => '<span class="a-fade" style="--d:' . $at(5, 'press Enter', .3) . '">&#8942;&#8942;</span>',
                         'sys' => '<span class="a-fade" style="--d:' . $at(5, 'press Enter', .3) . ';display:block">' . $sys() . '</span>', 'flat' => '<span class="a-fade" style="--d:' . $at(5, 'press Enter', .3) . ';display:block">' . $n('0.00') . '</span>',
                         'pct' => '<span class="a-fade" style="--d:' . $at(5, 'press Enter', .3) . ';display:block">' . $n('0.00') . '</span>', 'pm' => '<span class="a-fade" style="--d:' . $at(5, 'press Enter', .3) . ';display:block">' . $n('0.00') . '</span>'],
                        ['lbl' => '<span class="a-fade" style="--d:' . $at(5, 'ready to price', 1) . '"><span class="sw"><span class="a-out" style="--d:' . $at(5, 'Click any cell', 1) . '">Motorised</span><span class="a-fade a-ring" style="--d:' . $at(5, 'Click any cell', 1) . ';border-radius:3px">Motorised</span></span></span>',
                         'drag' => '<span class="a-fade" style="--d:' . $at(5, 'ready to price', 1) . '">&#8942;&#8942;</span>',
                         'sys' => '<span class="a-fade" style="--d:' . $at(5, 'ready to price', 1) . ';display:block">' . $sys() . '</span>', 'flat' => '<span class="a-fade" style="--d:' . $at(5, 'ready to price', 1) . ';display:block">' . $n('0.00') . '</span>',
                         'pct' => '<span class="a-fade" style="--d:' . $at(5, 'ready to price', 1) . ';display:block">' . $n('0.00') . '</span>', 'pm' => '<span class="a-fade" style="--d:' . $at(5, 'ready to price', 1) . ';display:block">' . $n('0.00') . '</span>'],
                        ['_cls' => 'nw', 'drag' => '+', 'lbl' => '<span class="sw"><span class="a-out" style="--d:' . $at(5, 'Type a label') . '">Type new label and press Enter&hellip;</span>
                            <span class="a-mid" style="--d:' . $at(5, 'Type a label') . ';--d2:' . $at(5, 'press Enter') . ';font-style:normal;color:var(--ink)"><span class="a-type" style="--d:' . $at(5, 'Type a label') . ';--ts:4;--tt:.5s">Cord</span></span>
                            <span class="a-mid" style="--d:' . $at(5, 'press Enter', .4) . ';--d2:' . $at(5, 'ready to price', 1) . ';font-style:normal;color:var(--ink)"><span class="a-type" style="--d:' . $at(5, 'press Enter', .5) . ';--ts:9;--tt:.8s">Motorised</span></span>
                            <span class="a-fade" style="--d:' . $at(5, 'ready to price', 1) . '">Type new label and press Enter&hellip;</span></span>', 'sys' => $sys()],
                    ]) . '
                  <div class="chips">
                    <span class="kbd a-pop" style="--d:' . $at(5, 'press Enter') . '">Enter</span><span class="scs a-fade" style="--d:' . $at(5, 'press Enter') . ';margin:0;align-self:center">adds the row</span>
                    <span class="kbd a-pop" style="--d:' . $at(5, 'Tab or Enter') . '">Tab</span><span class="scs a-fade" style="--d:' . $at(5, 'Tab or Enter') . ';margin:0;align-self:center">saves</span>
                    <span class="kbd a-pop" style="--d:' . $at(5, 'Escape') . '">Esc</span><span class="scs a-fade" style="--d:' . $at(5, 'Escape') . ';margin:0;align-self:center">cancels</span>
                  </div>
                </div>

                <!-- 6 — bulk add -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="ttl">Control side &mdash; Choices</div>
                  <div class="ghint" style="margin:0 0 .5rem"><span><b>Tip:</b> type a label and press <b>Enter</b> to add one row at a time. For multiple, use <b>Bulk add</b> &rarr;</span>
                    <span class="btns a-press a-ring" style="--d:' . $at(6, 'Click Bulk add') . '">+ Bulk add</span></div>
                  <div class="swb">
                    <div class="dlg a-mid" style="--d:' . $at(6, 'Click Bulk add', .6) . ';--d2:' . $at(6, 'Any label', .8) . '">
                      <h4>Add choices to &ldquo;Control side&rdquo;</h4>
                      <p>One label per line. Each becomes a choice on this option &mdash; e.g. <code>Left</code> then <code>Right</code> on a Cord option.
                         New rows start with no price differences; edit prices in the grid afterwards if needed.</p>
                      <div class="tx"><div><span class="a-type" style="--d:' . $at(6, 'like Left') . ';--ts:4;--tt:.4s">Left</span></div><div><span class="a-type" style="--d:' . $at(6, 'then Right') . ';--ts:5;--tt:.4s">Right</span></div></div>
                      <div class="dlgact"><span class="btns">Cancel</span><span class="btnp a-press" style="--d:' . $at(6, 'press Add') . '">Add</span></div>
                    </div>
                    <div class="a-fade" style="--d:' . $at(6, 'Any label', .8) . '">
                    ' . $cg(['drag', 'lbl', 'flat', 'pct', 'pm', 'acts'], [
                          ['lbl' => 'Left', 'flat' => $n('0.00'), 'pct' => $n('0.00'), 'pm' => $n('0.00'),
                           'acts' => '<span class="ra"><u>Edit</u> <u>+ Sub-option</u> <u class="a-ring" style="--d:' . $at(6, 'Duplicate copies') . ';border-radius:3px">Duplicate</u> <b class="a-ring" style="--d:' . $at(6, 'the cross') . ';border-radius:3px">&times;</b></span>'],
                          ['lbl' => 'Right', 'flat' => $n('0.00'), 'pct' => $n('0.00'), 'pm' => $n('0.00'), 'acts' => $rowAct],
                          ['_cls' => 'nw', 'drag' => '+', 'lbl' => '<span class="sw"><span class="a-out" style="--d:' . $at(6, 'Type a repeat') . '">Type new label&hellip;</span><span style="font-style:normal;color:var(--ink)"><span class="a-type" style="--d:' . $at(6, 'Type a repeat') . ';--ts:4;--tt:.4s">Left</span></span></span>'],
                      ]) . '
                    </div>
                  </div>
                  <div class="cfm a-mid" style="--d:' . $at(6, 'it asks you first') . ';--d2:' . $at(6, 'Duplicate copies', -.3) . ';left:22%;top:9rem">A choice called &ldquo;Left&rdquo; already exists in this option for that system. Add it again anyway?
                    <div class="act"><span class="btns">Cancel</span><span class="btnp">OK</span></div></div>
                  <span class="chip a-pop" style="--d:' . $at(6, 'Any label') . ';margin-top:.5rem">Already there? Bulk add skips it</span>
                </div>

                <!-- 7 — pricing -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="ttl">Control type &mdash; Choices</div>
                  ' . $cg(['drag', 'lbl', 'sys', 'flat', 'pct', 'pm', 'acts'], [
                        ['lbl' => 'Cord', 'sys' => $sys(), 'flat' => $n('0.00'), 'pct' => $n('0.00'), 'pm' => $n('0.00'), 'acts' => $rowAct],
                        ['lbl' => 'Motorised', 'sys' => $sys(),
                         'flat' => '<span class="ni a-ring" style="--d:' . $at(7, 'Flat pounds') . '"><span class="sw"><span class="a-out" style="--d:' . $at(7, 'A hundred and twenty') . '">0.00</span><span class="a-fade" style="--d:' . $at(7, 'A hundred and twenty') . '">120.00</span></span></span>',
                         'pct' => '<span class="ni a-ring" style="--d:' . $at(7, 'Percent is') . '">0.00</span>', 'pm' => '<span class="ni a-ring" style="--d:' . $at(7, 'Pounds per metre') . '">0.00</span>', 'acts' => $rowAct],
                    ], ['flat' => '', 'pct' => '', 'pm' => '']) . '
                  <div class="chips" style="flex-direction:column;align-items:flex-start;max-width:30rem">
                    <span class="chip a-pop" style="--d:' . $at(7, 'nought in all three') . '">0.00 &middot; 0.00 &middot; 0.00 = free</span>
                    <span class="chip a-pop" style="--d:' . $at(7, 'Flat pounds') . '"><b>Flat &pound;</b>&nbsp;&mdash; the same at every size</span>
                    <span class="chip a-pop" style="--d:' . $at(7, 'Percent is') . '"><b>%</b>&nbsp;&mdash; of the blind&rsquo;s own price (not of other extras)</span>
                    <span class="chip a-pop" style="--d:' . $at(7, 'Pounds per metre') . '"><b>&pound;/m</b>&nbsp;&mdash; by length: wider blind, more cost</span>
                    <span class="chip a-pop" style="--d:' . $at(7, 'Fill in more than one') . ';border-color:var(--good);color:var(--good)">Flat + % + &pound;/m &rarr; added together</span>
                  </div>
                </div>

                <!-- 8 — where it is offered -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="ttl">Control type &mdash; Choices</div>
                  ' . $cg(['drag', 'lbl', 'sys', 'bands', 'flat'], [
                        ['lbl' => 'Cord', 'sys' => '<span class="a-ring" style="--d:' . $at(8, 'Leave both alone') . ';display:block;border-radius:5px">' . $sys() . '</span>',
                         'bands' => '<span class="sw" style="display:grid"><span class="selb sm a-out" style="--d:' . $at(8, 'press Apply', .5) . '">All bands</span><span class="a-fade" style="--d:' . $at(8, 'press Apply', .5) . '">' . $bdg('2 bands') . '</span></span>', 'flat' => $n('0.00')],
                        ['lbl' => 'Motorised', 'sys' => '<span class="sw" style="display:grid">' . $sys('All systems', 'a-out', '--d:' . $at(8, 'so a motor')) . '<span class="a-fade" style="--d:' . $at(8, 'so a motor') . '">' . $sys('Motorised') . '</span></span>',
                         'bands' => '<span class="a-ring" style="--d:' . $at(8, 'Bands limits') . ';display:block;border-radius:5px"><span class="sw" style="display:grid"><span class="selb sm a-out" style="--d:' . $at(8, 'press Apply', .6) . '">All bands</span><span class="a-fade" style="--d:' . $at(8, 'press Apply', .6) . '">' . $bdg('2 bands') . '</span></span></span>', 'flat' => $n('120.00')],
                        ['lbl' => 'Battery', 'sys' => $sys(),
                         'bands' => '<span class="sw" style="display:grid"><span class="selb sm a-out" style="--d:' . $at(8, 'press Apply', .7) . '">All bands</span><span class="a-fade" style="--d:' . $at(8, 'press Apply', .7) . '">' . $bdg('2 bands') . '</span></span>', 'flat' => $n('95.00')],
                    ], ['sys' => '<span class="sa">Set all</span>', 'bands' => '<span class="sa a-ring" style="--d:' . $at(8, 'Set all link') . '">Set all</span>']) . '
                  <div class="pop a-mid" style="--d:' . $at(8, 'Available on limits') . ';--d2:' . $at(8, 'Bands limits') . ';left:20.3rem;top:6.9rem">
                    <label>' . $tk(false) . ' <b>All systems</b></label><hr>
                    <label>' . $tk(false) . ' Standard</label><label><span class="tick a-sel" style="--d:' . $at(8, 'so a motor') . '">&#10003;</span> Motorised</label></div>
                  <div class="pop a-mid" style="--d:' . $at(8, 'Pick a value') . ';--d2:' . $at(8, 'On a long list') . ';left:25.6rem;top:3.4rem">
                    <label>' . $tk(false) . ' <b>All bands</b></label><hr>
                    <label>' . $tk(true) . ' A</label><label>' . $tk(true) . ' B</label><label>' . $tk(false) . ' C</label>
                    <div style="text-align:right;margin-top:.35rem"><span class="btnp a-press" style="--d:' . $at(8, 'press Apply') . ';font-size:.58rem;padding:.18rem .5rem">Apply to all</span></div></div>
                  <div class="chips" style="margin-top:.6rem"><span class="chip a-pop" style="--d:' . $at(8, 'Leave both alone') . '">All systems + All bands = shows everywhere</span></div>
                </div>

                <!-- 9 — default / active / face value -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="ttl">Control type &mdash; Choices</div>
                  ' . $cg(['drag', 'lbl', 'flat', 'def', 'act', 'fv'], [
                        ['lbl' => 'Cord', 'flat' => $n('0.00'), 'def' => '<span class="a-ring" style="--d:' . $at(9, 'Default is') . ';border-radius:5px;display:inline-flex">' . $tk(true) . '</span>', 'act' => $tk(true), 'fv' => $tk(true)],
                        ['lbl' => 'Motorised', 'flat' => $n('120.00'), 'def' => $tk(false), 'act' => $tk(true), 'fv' => '<span class="a-ring" style="--d:' . $at(9, 'Face value starts') . ';border-radius:5px;display:inline-flex">' . $tk(true) . '</span>'],
                        ['lbl' => '<span class="a-fade" style="--d:0s;opacity:.5">Spring</span>', 'flat' => $n('15.00'), 'def' => $tk(false),
                         'act' => '<span class="sw">' . $tk(true, 'a-out', '--d:' . $at(9, 'Untick Active', .6)) . $tk(false, 'a-fade', '--d:' . $at(9, 'Untick Active', .6)) . '</span>', 'fv' => $tk(true)],
                        ['lbl' => 'Remote (supplier list)', 'flat' => $n('40.00'), 'def' => $tk(false), 'act' => $tk(true),
                         'fv' => '<span class="sw">' . $tk(true, 'a-out', '--d:' . $at(9, 'Untick it only', .6)) . $tk(false, 'a-fade', '--d:' . $at(9, 'Untick it only', .6)) . '</span>'],
                    ]) . '
                  <div class="chips" style="flex-direction:column;align-items:flex-start">
                    <span class="chip a-pop" style="--d:' . $at(9, 'Default is') . '"><b>Default</b>&nbsp;&mdash; comes already picked &middot; one per system</span>
                    <span class="chip a-pop" style="--d:' . $at(9, 'Untick Active') . '"><b>Active</b> off&nbsp;&mdash; hidden from quotes, not deleted</span>
                    <span class="chip a-pop" style="--d:' . $at(9, 'Face value starts') . '"><b>Face value</b> on&nbsp;&mdash; the price you type is the price charged</span>
                    <span class="chip a-pop" style="--d:' . $at(9, 'Untick it only') . ';border-color:#f59e0b"><b>Face value</b> off&nbsp;&mdash; supplier add-on: through discount + markup</span>
                  </div>
                </div>

                <!-- 10 — the choice\'s own page -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  <div class="ttl a-fade" style="--d:' . $at(10, 'click Edit') . '">Edit choice: Face-fix bracket</div>
                  <div class="fs a-rise" style="--d:' . $at(10, 'Give that box') . '"><div class="lg">Ask for a number on this choice</div>
                    <div class="cbr"><span class="tick a-sel" style="--d:' . $at(10, 'Give that box', .5) . '">&#10003;</span><span>Show a number input when this choice is picked</span></div>
                    <span class="fl">What to call this field</span>
                    <span class="inp"><span class="a-out" style="--d:' . $at(10, 'Say, Number') . '">Size (mm)</span><span><span class="a-type" style="--d:' . $at(10, 'Say, Number', .3) . ';--ts:18;--tt:1s">Number of brackets</span></span></span></div>
                  <div class="fs a-rise" style="--d:' . $at(10, 'Price per unit') . '"><span class="fl" style="margin-top:0">Price per unit (&pound;) &mdash; &times; quantity</span>
                    <span class="inp a-ring" style="--d:' . $at(10, 'the price is multiplied') . '"><span><span class="a-type" style="--d:' . $at(10, 'sold by the piece') . ';--ts:4;--tt:.4s">2.50</span></span></span>
                    <span class="chip a-pop" style="--d:' . $at(10, 'the price is multiplied') . ';margin-top:.35rem">5 brackets &times; &pound;2.50 = &pound;12.50</span></div>
                  <div class="two">
                    <div class="a-rise" style="--d:' . $at(10, 'This page also') . '"><span class="fl">Per-metre length is measured along</span><span class="selb" style="width:100%">Width</span></div>
                    <div class="a-rise" style="--d:' . $at(10, 'add a picture') . '"><span class="fl">Thumbnail image (optional)</span><span class="btns">Choose file</span> <span style="font-size:.6rem;color:var(--faint)">JPG, PNG or GIF, up to 2&nbsp;MB</span></div>
                  </div>
                </div>

                <!-- 11 — width table -->
                <div class="sc" data-scene="11" data-len="' . $len(11) . '">
                  <div class="fs a-rise" style="--d:.3s"><div class="lg">Width-based price table (optional)</div>
                    <p class="scs" style="margin:0 0 .3rem">Pricing engine looks up the smallest entry &ge; the customer&rsquo;s width (round-up). <b>Combined</b> with the flat / percent / per-metre fields above.</p>
                    <div class="two" style="grid-template-columns:1fr 1fr">
                      <div><span class="fl">Option A &mdash; paste rows</span>
                        <div class="tx"><div><span class="a-type" style="--d:' . $at(11, 'one row per line') . ';--ts:11;--tt:.7s">800, 15.00</span></div>
                          <div><span class="a-type" style="--d:' . $at(11, 'a width and then') . ';--ts:11;--tt:.7s">1200, 22.50</span></div>
                          <div><span class="a-type" style="--d:' . $at(11, 'then a price') . ';--ts:11;--tt:.7s">1600, 30.00</span></div></div></div>
                      <div class="a-fade" style="--d:' . $at(11, 'or upload') . '"><span class="fl">Option B &mdash; upload Excel</span><span class="btns a-ring" style="--d:' . $at(11, 'or upload') . '">Choose file</span>
                        <p class="scs" style="margin:.3rem 0 0">Vertical or horizontal &mdash; auto-detected.</p></div>
                    </div></div>
                  <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;margin-top:.4rem">
                    <span class="chip a-pop" style="--d:' . $at(11, 'It uses the first') . '">Blind 1000&nbsp;mm wide</span><span class="arrow a-fade" style="--d:' . $at(11, 'It uses the first') . '">&rarr;</span>
                    <div class="lookup a-fade" style="--d:' . $at(11, 'It uses the first') . '"><span>800</span><span>15.00</span><span class="a-sel" style="--d:' . $at(11, 'at least as wide') . '">1200</span><span class="a-sel" style="--d:' . $at(11, 'at least as wide') . '">22.50</span>
                      <span class="a-ring" style="--d:' . $at(11, 'your last row') . '">1600</span><span>30.00</span></div>
                    <span class="chip a-pop" style="--d:' . $at(11, 'your last row') . ';border-color:var(--err);color:var(--err)">1600 = the widest it can go</span>
                  </div>
                  <div class="a-rise" style="--d:' . $at(11, 'Back in the grid') . ';margin-top:.6rem">' . $cg(['drag', 'lbl', 'flat', 'pct', 'pm'], [
                        ['lbl' => 'Cassette <span class="wtb a-ring" style="--d:' . $at(11, 'a small badge') . '">&#9638; &pound; by width &middot; 3 sizes</span>', 'flat' => $n('0.00'), 'pct' => $n('0.00'), 'pm' => $n('0.00')],
                    ]) . '</div>
                </div>

                <!-- 12 — sub-options -->
                <div class="sc" data-scene="12" data-len="' . $len(12) . '">
                  <div class="swb">
                    <div class="a-mid" style="--d:0s;--d2:' . $at(12, 'The Add option form', .6) . '">
                      <div class="ttl">Control type &mdash; Choices</div>
                      ' . $cg(['drag', 'lbl', 'flat', 'acts'], [
                            ['lbl' => 'Cord', 'flat' => $n('0.00'), 'acts' => $rowAct],
                            ['lbl' => 'Motorised', 'flat' => $n('120.00'), 'acts' => '<span class="ra"><u>Edit</u> <u class="a-ring" style="--d:' . $at(12, 'Click Sub-option') . ';border-radius:3px">+ Sub-option</u> <u>Duplicate</u> <b>&times;</b></span>'],
                        ]) . '
                    </div>
                    <div class="a-fade" style="--d:' . $at(12, 'The Add option form', .6) . '">
                      <div class="alr inf">Adding a <b>follow-up option</b> that appears when <b>Control type = Motorised</b> is selected.</div>
                      <div class="wc">
                        <span class="fl" style="margin-top:0">Name <span class="req">*</span></span>
                        <span class="inp"><span><span class="a-type" style="--d:' . $at(12, 'Name it') . ';--ts:10;--tt:.8s">Motor type</span></span></span>
                        <span class="fl">Appears when (optional)</span>
                        <div class="cbr"><span class="a-ring" style="--d:' . $at(12, 'already ticked') . ';border-radius:5px;display:inline-flex">' . $tk(true) . '</span><span>Control type = Motorised</span></div>
                        <div class="cbr"><span class="tick a-sel" style="--d:' . $at(12, 'Tick more than one') . '">&#10003;</span><span>Control type = Battery</span></div>
                        <div class="cbr">' . $tk(false) . '<span>Control type = Cord</span></div>
                        <div style="margin-top:.35rem"><span class="btnp a-press" style="--d:' . $at(12, 'press Add option') . '">Add option</span></div>
                      </div>
                    </div>
                  </div>
                  <div class="a-move" style="--fx:20%;--fy:15rem;--tx:69%;--ty:6.1rem;--d:' . $at(12, 'Click Sub-option', -1.5) . ';--md:1.4s"><span class="a-mid" style="display:block;--d:0s;--d2:' . $at(12, 'The Add option form', .6) . '">' . $ptr . '</span></div>
                  <span class="chip a-pop" style="--d:' . $at(12, 'any of them') . ';margin-top:.5rem">Shows when <b>&nbsp;any&nbsp;</b> ticked choice is picked</span>
                </div>

                <!-- 13 — settings on the option -->
                <div class="sc" data-scene="13" data-len="' . $len(13) . '">
                  <div class="ttl a-fade" style="--d:' . $at(13, 'Click Edit') . '">Edit option: Fascia type</div>
                  <div class="tog a-rise" style="--d:' . $at(13, 'Click Edit', .4) . '">
                    <div class="cbr a-fade" style="--d:' . $at(13, 'As well as Required') . '">' . $tk(true) . '<span>Required <small>customer must pick a choice</small></span></div>
                    <div class="cbr a-fade" style="--d:' . $at(13, 'multiple choices') . '">' . $tk(false) . '<span>Allow multiple choices <small>renders as tick-boxes instead of a dropdown</small></span></div>
                    <div class="cbr a-fade" style="--d:' . $at(13, 'Show above') . '"><span class="tick a-sel" style="--d:' . $at(13, 'puts the option') . '">&#10003;</span><span><b>Show above the size fields</b>
                      <small>renders this option (and anything nested under it) before Width / Drop in the quote builder</small></span></div>
                    <div class="cbr a-fade" style="--d:' . $at(13, 'Splits the blind') . '"><span class="a-ring" style="--d:' . $at(13, 'Splits the blind') . ';border-radius:5px;display:inline-flex">' . $tk(false) . '</span><span><b>Splits the blind into equal panels</b>
                      <small>each choice&rsquo;s label is the number of panels (e.g. 2, 3, 4). The Width is shared equally</small></span></div>
                    <div class="cbr">' . $tk(false) . '<span>Can be joined when longer than its width table</span></div>
                    <div class="cbr a-fade" style="--d:' . $at(13, 'untick Active') . '"><span class="a-ring" style="--d:' . $at(13, 'untick Active') . ';border-radius:5px;display:inline-flex">' . $tk(true) . '</span><span>Active <small>uncheck to hide from quote builder</small></span></div>
                  </div>
                  <div class="chips"><span class="btnp">Save changes</span><span class="btns">Cancel</span></div>
                </div>

                <!-- 14 — renaming -->
                <div class="sc" data-scene="14" data-len="' . $len(14) . '">
                  <div class="sct a-fade" style="--d:.2s">Rename on the Edit page &mdash; not in the grid</div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(14, 'find options and choices') . '">&#9881; Factory build rules find options &amp; choices <b>&nbsp;by name</b></span></div>
                  <div class="two" style="margin-top:.4rem">
                    <div class="wc a-rise" style="--d:' . $at(14, 'Rename on the Edit') . '"><h3>Edit choice: Motorised</h3>
                      <span class="fl">Label <span class="req">*</span></span>
                      <span class="inp"><span class="a-out" style="--d:' . $at(14, 'press Save', -.8) . '">Motorised</span><span><span class="a-type" style="--d:' . $at(14, 'press Save', -.6) . ';--ts:8;--tt:.6s">Motor</span></span></span>
                      <div style="margin-top:.4rem"><span class="btnp a-press" style="--d:' . $at(14, 'press Save') . '">Save changes</span></div>
                      <span class="chip a-pop" style="--d:' . $at(14, 'the rules are updated') . ';margin-top:.45rem;border-color:var(--good);color:var(--good)">&#10003; build rules updated too</span></div>
                    <div class="wc a-rise" style="--d:' . $at(14, 'Type over a label') . '"><h3>Choices grid &middot; Label cell</h3>
                      <span class="fl">Label</span>
                      <span class="inp"><span class="a-out" style="--d:' . $at(14, 'Type over a label', 1) . '">Motorised</span><span><span class="a-type" style="--d:' . $at(14, 'Type over a label', 1.2) . ';--ts:5;--tt:.5s">Motor</span></span></span>
                      <span class="chip a-pop" style="--d:' . $at(14, 'they are not') . ';margin-top:.45rem;border-color:var(--err);color:var(--err)">&#10007; build rules still look for &ldquo;Motorised&rdquo;</span></div>
                  </div>
                  <div class="chips" style="margin-top:.7rem"><span class="chip a-pop" style="--d:' . $at(14, 'Use the grid') . '">Grid &rarr; prices &amp; ticks</span><span class="chip a-pop" style="--d:' . $at(14, 'the Edit page for names') . '">Edit page &rarr; names</span></div>
                </div>

                <!-- 15 — copying -->
                <div class="sc" data-scene="15" data-len="' . $len(15) . '">
                  <div class="ttl">Copy options into Vertical Blind</div>
                  <div class="wc a-rise" style="--d:' . $at(15, 'Pick the product') . '"><h3>1. Copy from</h3><span class="fl">Source product</span>
                    <span class="selb" style="min-width:14rem"><span class="sw"><span class="a-out" style="--d:' . $at(15, 'Pick the product', 1) . '">&mdash; Choose a product &mdash;</span><span class="a-fade" style="--d:' . $at(15, 'Pick the product', 1) . '">Roller Blind (4 options)</span></span></span></div>
                  <div class="wc a-rise" style="--d:' . $at(15, 'and untick', -.5) . ';margin-top:.5rem"><h3>2. Options in Roller Blind</h3>
                    <div class="cbr">' . $tk(true) . '<span style="color:var(--soft)">Select all</span></div>
                    <div class="cbr">' . $tk(true) . '<span><b>Control type</b> <span style="color:var(--faint)">&middot; 3 choices &middot; required</span></span></div>
                    <div class="cbr">' . $tk(true) . '<span><b>Motor type</b> <span style="color:var(--faint)">&middot; 4 choices &middot; required</span></span></div>
                    <div class="cbr"><span class="sw">' . $tk(true, 'a-out', '--d:' . $at(15, 'you do not want')) . $tk(false, 'a-fade', '--d:' . $at(15, 'you do not want')) . '</span><span><b>Fascia type</b> <span style="color:var(--faint)">&middot; 5 choices</span></span></div>
                    <div style="margin-top:.4rem"><span class="btnp a-press" style="--d:' . $at(15, 'Their choices come', -.3) . '">Copy selected into Vertical Blind &rarr;</span></div></div>
                  <div class="alr a-pop" style="--d:' . $at(15, 'Their choices come') . ';margin-top:.5rem">Copied 2 options (7 choices) from &ldquo;Roller Blind&rdquo;. <span class="a-fade" style="--d:' . $at(15, 'An option this product') . '">Skipped 1 already on this product (same name).</span></div>
                </div>

                <!-- 16 — live preview -->
                <div class="sc" data-scene="16" data-len="' . $len(16) . '">
                  <div style="display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap">
                    <span class="btns a-press a-ring" style="--d:' . $at(16, 'open Live preview') . '">&#128065; Live preview</span>
                    <div class="drw a-fly" style="--d:' . $at(16, 'open Live preview', .7) . '">
                      <div class="dh">Live preview <span style="font-size:.62rem;color:var(--faint);font-weight:600">&#8635; Refresh &nbsp;&#10005;</span></div>
                      <div class="pin"><span class="price"><span class="sw"><span class="a-out" style="--d:' . $at(16, 'the price goes up') . '">&pound;84.00</span><span class="a-mid" style="--d:' . $at(16, 'the price goes up') . ';--d2:' . $at(16, 'Pick Cord') . '">&pound;204.00</span><span class="a-fade" style="--d:' . $at(16, 'Pick Cord') . '">&pound;84.00</span></span></span></div>
                      <div class="bd qb" style="border:0;background:transparent;max-width:none">
                        <div class="qr"><b>Width / Drop</b><span class="selb" style="min-width:0">1200 &times; 1600</span></div>
                        <div class="qr"><b>Control type</b><span class="selb a-ring" style="--d:' . $at(16, 'Pick Motorised') . ';min-width:0"><span class="sw"><span class="a-out" style="--d:' . $at(16, 'Pick Motorised', .5) . '">Cord</span><span class="a-mid" style="--d:' . $at(16, 'Pick Motorised', .5) . ';--d2:' . $at(16, 'Pick Cord', .3) . '">Motorised</span><span class="a-fade" style="--d:' . $at(16, 'Pick Cord', .3) . '">Cord</span></span></span></div>
                        <div class="qr a-mid" style="--d:' . $at(16, 'Motor type appears') . ';--d2:' . $at(16, 'it stays hidden', -.5) . '"><b>&#8627; Motor type</b><span class="selb" style="min-width:0">&mdash; Select &mdash;</span></div>
                      </div>
                    </div>
                  </div>
                  <div class="chips" style="margin-top:.7rem"><span class="chip a-pop" style="--d:' . $at(16, 'If something is missing') . '">Missing? Check the option + choices are <b>&nbsp;Active</b></span></div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Options</b> (some screens call them &ldquo;extras&rdquo;) are the things your salesperson picks for each blind when building a
             quote &mdash; <em>Control type</em>, <em>Bottom Weight</em>, <em>Bracket colour</em>, <em>Control side</em>. They are <b>not</b> the
             product&rsquo;s fabrics &mdash; those have their own page. An option has <b>no price of its own</b>: the price sits on the
             <b>choices</b> inside it. Get there from the product&rsquo;s <b>Edit</b> page: the <b>Options</b> tile, or <b>Full manage &raquo;</b>
             on its <b>Options</b> section. The page header has <b>&larr; Back to setup wizard</b>, <b>Copy from another product</b> and
             <b>Finish &rarr;</b>.</p>

          <p class="prose"><b>1) Reading the options list</b> &mdash; <b>Options (N)</b>.</p>
          <ul class="steps">
            <li>A navy <b>REQUIRED</b> pill means the customer must pick one; a grey <b>OPTIONAL</b> pill means they can leave it.</li>
            <li>A row pushed in behind <b>&#8627;</b> is a <b>sub-option</b>: under its name it says
                <em>&ldquo;Appears when Control type = Motorised is selected&rdquo;</em>.</li>
            <li><b>Choices</b> shows how many choices the option holds; <b>&#9998; number</b> means it captures a typed number instead.</li>
            <li>Drag the <b>&#8942;&#8942;</b> handle to reorder &mdash; that is the order the salesperson sees (<em>Saving&hellip;</em> shows while it stores).</li>
            <li>Row links: <b>Edit</b> (the option&rsquo;s settings), <b>Duplicate</b> (<em>Clone this option with all its choices, pricing and
                gating</em>) and <b>Delete</b>, which asks first: <code>Delete option Control type? Removes its 2 choices too.</code></li>
          </ul>

          <p class="prose"><b>2) Add option</b> &mdash; the form above the list.</p>
          <ul class="steps">
            <li><b>Name *</b> &mdash; what the customer is choosing (placeholder <em>e.g. Control side</em>), up to 150 characters.</li>
            <li><b>Required</b> &mdash; <b>already ticked</b>. Untick it for an extra they can skip.</li>
            <li><b>Allow multiple choices</b> &mdash; <em>renders as tick-boxes &mdash; salesperson can pick any combination</em>; every ticked
                choice adds its price.</li>
            <li><b>Appears when (optional)</b> &mdash; one tick box per choice already on this product (<em>Control type = Motorised</em>).
                Tick one or more and the option shows when <b>any</b> of them is selected; tick none for an always-visible option.</li>
            <li><b>Also show a number input next to this option</b> &mdash; reveals <b>What to call this field</b>, pre-filled with
                <b>Length (mm)</b> (max 60 characters). Include the unit. The typed value is recorded on the quote line.</li>
            <li>Press <b>Add option</b>. A normal option takes you straight into its choices. A number-only option stays on the list:
                <code>Option &ldquo;Wand length&rdquo; added &mdash; it captures a typed number, so it needs no choices and is ready to use. Open
                it only if you also want pickable choices alongside the number.</code></li>
          </ul>

          <p class="prose"><b>3) The choices page</b> (<em>&lt;Option&gt; &mdash; Choices</em>) is a <b>live grid with no Save button</b>.</p>
          <ul class="steps">
            <li>Type in the bottom row (<em>Type new label and press Enter&hellip;</em>) and press <b>Enter</b> to add a choice. Click any cell to
                edit; <b>Tab</b> or <b>Enter</b> saves, <b>Escape</b> cancels.</li>
            <li>The badge by the heading reads <b>All changes saved</b>, <b>Saving&hellip;</b> while it works, or red with the reason
                (<b>Save failed</b> if none) &mdash; a refused cell goes back to its last saved value.</li>
            <li><b>+ Bulk add</b> opens <em>Add choices to &ldquo;&hellip;&rdquo;</em>: one label per line, then <b>Add</b>. Labels already on the
                option are skipped; if nothing was added it says <code>Nothing added &mdash; 3 labels already existed.</code></li>
            <li>Typing a repeat in the bottom row asks first:
                <code>A choice called &ldquo;Cord&rdquo; already exists in this option for that system. Add it again anyway?</code></li>
            <li>Row links: <b>Edit</b> (the full choice page), <b>+ Sub-option</b>, <b>Duplicate</b> (e.g. the same label on a different system at
                a different price) and <b>&times;</b> to delete. Drag <b>&#8942;&#8942;</b> to reorder.</li>
            <li><b>Done &mdash; back to options</b> at the bottom only walks you back: <em>Every change is saved automatically as you make it
                &mdash; you don&rsquo;t have to click anything to save.</em></li>
          </ul>

          <p class="prose"><b>4) Pricing a choice.</b> All the ways <b>add together</b>; nought everywhere means the choice is <b>free</b>.</p>
          <ul class="steps">
            <li><b>Flat &pound;</b> (grid) &mdash; a set surcharge, the same at every size.</li>
            <li><b>%</b> (grid) &mdash; a percentage of the <b>base blind price</b>, not of the other options.</li>
            <li><b>&pound;/m</b> (grid) &mdash; charged by length. On the choice&rsquo;s Edit page, <b>Per-metre length is measured along</b>:
                <b>Width</b>, <b>Drop</b>, <b>Width + Drop</b> or <b>Perimeter (2 &times; W + 2 &times; D)</b> &mdash; perimeter for a trim
                that runs all the way round.</li>
            <li><b>Price per unit (&pound;) &mdash; &times; quantity</b> (Edit page) &mdash; for brackets, fixings and the like. The salesperson
                types how many; the line adds price &times; quantity. It adds a <em>Quantity</em> box to the quote &mdash; give it your own
                name under <b>Ask for a number on this choice</b> &rarr; <b>What to call this field</b> (e.g. <em>Number of brackets</em>).</li>
            <li><b>Width-based price table (optional)</b> (Edit page) &mdash; <b>Option A &mdash; paste rows</b>: one row per line, <b>width then
                price</b>, separated by space, comma or tab, in mm (<code>800</code>) or metres (<code>0.800</code>). <b>Option B &mdash; upload
                Excel</b>: vertical or horizontal, auto-detected; a file overrides the box. The engine uses the smallest row &ge; the blind&rsquo;s
                width, so the last row is the ceiling (wider gives <code>Width 2400 mm exceeds the largest entry in the width table for
                &lsquo;Motorised&rsquo;.</code>). Emptying the box and saving clears the table. A choice priced this way shows
                <b>&#9638; &pound; by width &middot; N sizes</b> in the grid.</li>
          </ul>

          <p class="prose"><b>5) Where a choice is offered.</b> Leave these alone and the choice shows everywhere.</p>
          <ul class="steps">
            <li><b>Available on</b> &mdash; systems (<em>All systems</em> by default). On the Edit page: <em>All systems</em> or
                <em>&lt;System&gt; only</em>.</li>
            <li><b>Bands</b> &mdash; tick the fabric bands it is offered on; none ticked = every band. (Edit page: <b>Available for bands</b>.)</li>
            <li><b>Available for specific fabrics</b> (Edit page only) &mdash; <em>Only needed when a band can&rsquo;t say it</em>, e.g. a 38mm slat
                offered on Snow and Cool White where Snow shares its band with colours that don&rsquo;t have it.</li>
            <li><b>Set all</b> in the <b>Available on</b>, <b>Bands</b>, <b>Flat &pound;</b>, <b>%</b> and <b>&pound;/m</b> headings: pick or type
                once, <b>Apply to all</b>, confirm, and every row gets it.</li>
          </ul>

          <p class="prose"><b>6) Default, Active, Face value.</b> <b>Default</b> = pre-selected for the customer, one per system. <b>Active</b>
             unticked hides a choice from the quote builder without deleting it. <b>Face value</b> is ticked by default &mdash; the price you
             set is what&rsquo;s charged. Untick it only for a <b>supplier list add-on</b>: on a supplier-priced product it is then run through
             the buying discount + markup with the base.</p>

          <p class="prose"><b>7) Sub-options</b> &mdash; an option that waits for a choice (<em>Motor type</em> only once <em>Motorised</em> is picked).</p>
          <ul class="steps">
            <li><b>+ Sub-option</b> on a choice row opens <b>Add option</b> with that choice already ticked under <b>Appears when</b>
                (<em>Adding a follow-up option that appears when Control type = Motorised is selected.</em>).</li>
            <li>Or open <b>+ Add sub-option</b> at the bottom of the choices page: <b>Sub-option name *</b>, <b>Appears when *</b>, a
                <b>Required</b> tick, then <b>Save &amp; open choices</b> or <b>Save &amp; stay here</b>. Nothing ticked gives
                <code>Pick at least one parent choice to gate the sub-option.</code></li>
            <li>Several parents = shows when <b>any</b> is picked, so one sub-option can serve <em>Chained</em> and <em>Chainless</em>.</li>
            <li>Each sub-option shows as a card with <b>Appears when</b> pills, <b>Edit gates</b>, <b>Delete</b>
                (<code>Delete sub-option Motor type? Removes its choices too. Cannot be undone.</code>) and its own choices grid.</li>
          </ul>

          <p class="prose"><b>8) The option&rsquo;s own settings</b> &mdash; <b>Edit</b> on the options list (<em>Edit option: &hellip;</em>):
             <b>Name</b>, <b>Appears when</b>, <b>Capture a number from the salesperson</b>, then <b>Required</b>, <b>Allow multiple choices</b>,
             <b>Show above the size fields</b> (before Width / Drop &mdash; e.g. the roller fascia group), <b>Splits the blind into equal panels</b>
             (each choice&rsquo;s label is the number of panels; 1200 wide &times; 2 panels prices as 2 &times; the grid price at 600 &times; drop),
             <b>Can be joined when longer than its width table</b> (past the last row of a choice&rsquo;s price-by-width table it is made in equal
             pieces, each charged at its length &mdash; e.g. a 4000 fascia with a 3500 longest = 2 &times; the 2000 price) and <b>Active</b>.
             Then <b>Save changes</b>.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Rename on the Edit pages, not in the grid.</b> The factory&rsquo;s build rules
             find options and choices by <em>name</em>. <b>Save changes</b> on <b>Edit option</b> or a choice&rsquo;s <b>Edit</b> page carries a
             rename into those rules; typing over the <b>Label</b> cell in the grid does not, so the rules can stop firing. Use the grid for
             prices and ticks.</div></div>

          <p class="prose"><b>9) Copy from another product</b> (<em>Copy options into &hellip;</em>): <b>1. Copy from</b> &rarr;
             <b>Source product</b>; <b>2. Options in &hellip;</b> &mdash; all ticked (<b>Select all</b>), untick what you don&rsquo;t want, then
             <b>Copy selected into &hellip; &rarr;</b>. Choices, band scoping and width-table pricing come across; linked sub-options are pulled
             in automatically; systems are matched by name (no match = &ldquo;all systems&rdquo;); an option with the same name already on this
             product is skipped: <code>Copied 4 options (17 choices) from &ldquo;Roller Blind&rdquo;. Pulled in 2 linked sub-options automatically.
             Skipped 1 already on this product (same name).</code></p>

          <div class="oops"><b>Messages you may see:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><code>Name is required.</code> / <code>Name is too long (max 150 chars).</code> / <code>Label is required.</code> /
                   <code>Sub-option name is required.</code> / <code>Number-input label is too long (max 60 chars).</code></li>
               <li>A price cell cleared in the grid &rarr; <code>Must be a number.</code> On the Edit page:
                   <code>Flat surcharge must be a number.</code>, <code>Percent surcharge must be a number.</code>,
                   <code>Per-metre surcharge must be a number.</code>, <code>Price per unit must be a number.</code></li>
               <li>Pictures: <code>Thumbnail must be a JPG, PNG, or GIF image.</code> / <code>Thumbnail too large (2 MB max).</code>;
                   spreadsheets: <code>File too large (5 MB max).</code></li>
             </ul></div>

          <p><b>Check it before a salesperson does:</b> <b>&#128065; Live preview</b> on the product&rsquo;s edit page opens a mini quote builder
             with the same gating as the real one &mdash; pick choices, watch sub-options appear and the price update. Missing something? Check
             the option and its choices are <b>Active</b>, and that a sub-option&rsquo;s <b>Appears when</b> parents are choices that get picked.</p>',
        'script'  => array_map(static fn ($k, $l) => [$l[0], $l[1], $l[2], $k], array_keys($S), $S),
];
