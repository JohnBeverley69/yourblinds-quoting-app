<?php
declare(strict_types=1);

/**
 * Guide: users-add — "Adding users & permissions" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Covers BOTH screens: /admin/users.php (the Add user form + the Existing
 * users table) and /admin/users_edit.php (roles, permissions incl. "Can see
 * money" — stored in dash_view_revenue — the Dashboard panel box, Active,
 * Home address and the Danger zone). Behaviour checked against
 * auth/middleware.php (user_can_see_money, post-login landing),
 * _partials/sidebar.php (which menu entries each permission shows),
 * _partials/bookable_users.php (Sales / Fitter = who is offered on a
 * measure / fitting) and dashboard/index.php (panel gating).
 * Every label, button and message is copied from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// A checkbox + label. $state: '' off, 'on' ticked, or a start time (string, seconds) at which it ticks on.
$cb = static function (string $label, string $state = '', string $extra = ''): string {
    if ($state === 'on')      $t = '<span class="tick on">&#10003;</span>';
    elseif ($state === '')    $t = '<span class="tick">&#10003;</span>';
    else                      $t = '<span class="tick a-sel" style="--d:' . $state . 's">&#10003;</span>';
    return '<span class="cb ' . $extra . '">' . $t . $label . '</span>';
};

$roleNames = ['Admin', 'Owner', 'Office', 'Sales', 'Agent', 'Fitter', 'Readonly'];
// $set = [roleName => state]
$roles = static function (array $set = ['Sales' => 'on'], string $cls = '') use ($cb, $roleNames): string {
    $h = '<div class="rolebox ' . $cls . '">';
    foreach ($roleNames as $r) $h .= $cb($r, $set[$r] ?? '');
    return $h . '</div>';
};

// Permissions row; $set = [label => state]; $money adds "Can see money" (Edit page only).
$perms = static function (array $set = ['Create quotes' => 'on'], bool $money = false, string $cls = '') use ($cb): string {
    $labels = ['Create quotes', 'Create orders', 'View all customer jobs', 'View costs'];
    if ($money) $labels[] = 'Can see money';
    $labels[] = 'Fittings only';
    $h = '<div class="permrow ' . $cls . '">';
    foreach ($labels as $l) $h .= $cb($l, $set[$l] ?? '');
    return $h . '</div>';
};

$field = static fn (string $label, string $inner, string $cls = '', string $style = ''): string =>
    '<div class="uf"><label>' . $label . '</label><span class="ib ' . $cls . '" style="' . $style . '">' . $inner . '</span></div>';
$opt = '<span class="muted">(optional)</span>';

$row = static fn (string $name, string $email, string $roles, string $last, string $cls = '', string $style = ''): string =>
    '<div class="tr ' . $cls . '" style="' . $style . '"><span><b>' . $name . '</b></span><span class="em">' . $email . '</span><span>' . $roles
  . '</span><span><span class="badge">active</span></span><span class="ll">' . $last . '</span><span class="lnk">Edit</span></div>';
$thead = '<div class="tr th"><span>Name</span><span class="em">Email</span><span>Roles</span><span>Status</span><span class="ll">Last login</span><span></span></div>';

$sideUsers = '
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a><a>Pipeline</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a>Orders</a><a>Payments</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a class="on">Users</a><a>Settings</a><a>Trade terms</a><a>Billing</a>
              </div>';

return [
        'aud'     => 'admin',
        'section' => 'Users',
        'title'   => 'Adding users & permissions',
        'eyebrow' => 'Users',
        'v'       => 2,
        'blurb'   => 'Add a login, tick the right roles and permissions, decide who can see money, then set which Dashboard panels they see.',
        'lede'    => 'Everyone who uses YourBlinds gets their <b>own login</b>, and what they can see depends on what you tick. The
                      <b>Users</b> page does two jobs: the <b>Add user</b> form at the top makes a new login, and <b>Existing users</b>
                      underneath lists everyone, with an <b>Edit</b> link on every row. The <b>Edit</b> page is where you finish the job:
                      <b>Can see money</b>, the <b>Dashboard</b> panels, the <b>Active</b> switch, a <b>home address</b> for the
                      calendar&rsquo;s run map, and the <b>Danger zone</b>. To get there: <b>Setup</b> &rarr; <b>Users</b> (admins only).',
        'open'    => '/admin/users.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:380px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .pt{ font-size:1rem; font-weight:800; color:var(--ink); }
          .gd .psub{ font-size:.68rem; color:var(--faint); margin:.05rem 0 .6rem; }
          .gd .secttl{ font-size:.8rem; font-weight:800; color:var(--ink); margin:.2rem 0 .45rem; }
          .gd .muted{ color:var(--faint); font-weight:400; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.3rem .7rem; font-size:.72rem; font-weight:600; }
          .gd .btnd{ display:inline-flex; background:#b91c1c; color:#fff; border-radius:7px; padding:.32rem .75rem; font-size:.72rem; font-weight:700; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.6rem; }
          .gd .bnr{ display:flex; align-items:center; gap:.5rem; background:var(--good-wash); border-left:3px solid var(--good);
                    border-radius:8px; padding:.4rem .65rem; font-size:.74rem; font-weight:700; color:var(--ink); margin:0 0 .55rem; max-width:32rem; }
          .gd .sw{ display:inline-grid; } .gd .sw > span{ grid-area:1/1; }

          /* form bits */
          .gd .g2{ display:grid; grid-template-columns:1fr 1fr; gap:.45rem .7rem; max-width:32rem; }
          .gd .uf label{ display:block; font-size:.68rem; font-weight:600; color:var(--ink); margin-bottom:.18rem; }
          .gd .ib{ display:flex; align-items:center; height:28px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; background:var(--surface);
                   padding:0 .5rem; font-size:.74rem; color:var(--ink); overflow:hidden; white-space:nowrap; }
          .gd .ib .ph{ color:var(--faint); }
          .gd .note{ font-size:.64rem; color:var(--faint); margin:.3rem 0 .5rem; line-height:1.45; max-width:32rem; }
          .gd .note b{ color:var(--soft); }
          .gd .cb{ display:inline-flex; align-items:center; gap:.35rem; font-size:.72rem; color:var(--ink); white-space:nowrap; }
          .gd .cb .tick{ width:15px; height:15px; font-size:.6rem; }
          .gd .rolebox{ display:flex; flex-wrap:wrap; gap:.4rem .8rem; padding:.45rem .55rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:8px; background:var(--surface); max-width:32rem; }
          .gd .permrow{ display:flex; flex-wrap:wrap; gap:.45rem .9rem; max-width:32rem; padding:.2rem 0; }
          .gd .lab{ font-size:.68rem; font-weight:600; color:var(--ink); margin:.55rem 0 .25rem; }
          .gd .fs{ border:1px solid var(--line); border-radius:10px; padding:.55rem .7rem; max-width:32rem; margin:.5rem 0; position:relative; }
          .gd .fs .lg{ position:absolute; top:-.5rem; left:.6rem; background:var(--surface); padding:0 .35rem; font-size:.62rem; font-weight:700; color:#1f3b5b; letter-spacing:.05em; text-transform:uppercase; }
          :root[data-theme="dark"] .gd .fs .lg{ color:#93c5fd; }
          .gd .fs p{ font-size:.62rem; color:var(--soft); margin:.1rem 0 .45rem; line-height:1.45; }

          /* users table */
          .gd .tbl{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:34rem; }
          .gd .tr{ display:grid; grid-template-columns:1.2fr 1.5fr 1fr .8fr 1fr .5fr; gap:.4rem; align-items:center; padding:.35rem .55rem;
                   border-top:1px solid var(--line); font-size:.68rem; color:var(--soft); }
          .gd .tr.th{ border-top:0; background:var(--panel); font-weight:700; font-size:.6rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); }
          .gd .tr b{ color:var(--ink); }
          .gd .badge{ display:inline-block; font-size:.6rem; font-weight:700; padding:.08rem .4rem; border-radius:999px; background:var(--good-wash); color:var(--good); }
          .gd .lnk{ color:var(--accent); font-weight:600; }
          .gd .ll{ white-space:nowrap; }

          /* 1 — two jobs */
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem; max-width:32rem; }
          .gd .blk{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); }
          .gd .blk h4{ margin:0 0 .3rem; font-size:.8rem; color:var(--ink); }
          .gd .blk p{ margin:0; font-size:.66rem; color:var(--soft); line-height:1.45; }

          /* 6 — assign lists */
          .gd .assign{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem; max-width:32rem; }
          .gd .assign .blk div{ font-size:.68rem; padding:.2rem 0; color:var(--ink); }

          /* 8 — menu that shrinks */
          .gd .menu{ border-radius:9px; background:var(--nav); color:var(--nav-ink); padding:.5rem .6rem; width:10rem; font-size:.68rem; }
          .gd .menu div{ padding:.18rem .3rem; }
          .gd .menu .h{ font-size:.52rem; letter-spacing:.12em; text-transform:uppercase; color:#6a7d8c; font-weight:700; margin-top:.3rem; }
          .gd .side2{ display:flex; gap:1rem; align-items:flex-start; flex-wrap:wrap; }

          /* 14 — danger */
          .gd .dz h5{ margin:.8rem 0 .25rem; color:#b91c1c; font-size:.8rem; }
          .gd .dz p{ margin:0 0 .4rem; font-size:.66rem; color:var(--soft); }
          .gd .confirm{ position:absolute; z-index:5; left:10%; top:40%; max-width:19rem; background:var(--surface); border:1px solid var(--line);
                        border-radius:12px; box-shadow:var(--gd-shadow); padding:.7rem .8rem; font-size:.72rem; color:var(--ink); }
          .gd .confirm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.55rem; }

          @media (max-width:640px){
            .gd .sc{ min-height:470px; }
            .gd .g2, .gd .two, .gd .assign{ grid-template-columns:1fr; }
            .gd .tr{ grid-template-columns:1.2fr 1fr .8fr .5fr; }
            .gd .tr .em, .gd .tr .ll{ display:none; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / admin / users</span></div>
            <div class="app">' . $sideUsers . '
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="pt">Users</div><div class="psub">Login accounts for Demo Blinds Ltd.</div>
                  <div class="secttl">Add user</div>
                  <div class="g2">' . $field('First name', '') . $field('Last name', '') . '</div>
                  <div class="lab">Roles</div>' . $roles() . '
                  <div class="lab">Permissions</div>' . $perms() . '
                  <span class="btnp" style="margin-top:.4rem">Add user</span>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; fourteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — the page, two jobs -->
                <div class="sc" data-scene="1" data-len="24">
                  <div class="pt a-fade" style="--d:.2s">Users</div><div class="psub a-fade" style="--d:.5s">Login accounts for Demo Blinds Ltd.</div>
                  <div class="two">
                    <div class="blk a-rise" style="--d:13s"><h4>Add user</h4><p>The form at the top makes a new login.</p></div>
                    <div class="blk a-rise" style="--d:16.5s"><h4>Existing users (3)</h4><p>Everyone you have &mdash; with an <span class="lnk">Edit</span> link on every row.</p></div>
                  </div>
                  <div class="tbl a-rise" style="--d:18s;margin-top:.7rem">' . $thead
                    . $row('Jane Weller', 'jane@demoblinds.co.uk', 'Admin', '7 Oct 2026 08:12')
                    . $row('Sam Okafor', 'sam@demoblinds.co.uk', 'Sales', '6 Oct 2026 17:40') . '</div>
                  <div class="chips"><span class="chip a-pop" style="--d:21.5s">Add here &rarr; finish on <b>Edit</b></span>
                    <span class="chip a-pop" style="--d:8.5s">&#128274; Admins only</span></div>
                </div>

                <!-- 2 — names -->
                <div class="sc" data-scene="2" data-len="19">
                  <div class="sct a-fade" style="--d:.2s">Who are they?</div>
                  <div class="g2">
                    ' . $field('First name', '<span class="a-type" style="--d:2.5s;--ts:4;--tt:.5s">Dave</span>', 'a-ring', '--d:2s') . '
                    ' . $field('Last name', '<span class="a-type" style="--d:4s;--ts:5;--tt:.5s">Perry</span>') . '
                  </div>
                  <p class="note a-fade" style="--d:7s">Leave both blank for a <b>workstation</b> &mdash; &ldquo;Vertical Head Rail&rdquo; isn&rsquo;t a person and hasn&rsquo;t got a surname. It&rsquo;ll be known by its username.</p>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:10s">&#128119; A person &rarr; their name</span>
                    <span class="chip a-pop" style="--d:13s">&#128295; A workshop bench &rarr; no name, just a username</span>
                  </div>
                </div>

                <!-- 3 — email or username -->
                <div class="sc" data-scene="3" data-len="16">
                  <div class="sct a-fade" style="--d:.2s">How they sign in: email <i>or</i> username</div>
                  <div class="g2">
                    ' . $field('Email ' . $opt, '<span class="a-type" style="--d:3s;--ts:21;--tt:1s">dave@demoblinds.co.uk</span>', 'a-ring', '--d:2.5s') . '
                    ' . $field('Username <span class="muted">(use this for staff with no email)</span>', '', 'a-ring', '--d:7s') . '
                  </div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:5.5s">One or the other &mdash; not both</span>
                    <span class="chip a-pop" style="--d:10s">No email? A username is enough</span>
                  </div>
                  <div class="blk a-rise" style="--d:13.5s;max-width:16rem;margin-top:.8rem"><h4>Sign in</h4>
                    <div class="uf"><label>Username or email</label><span class="ib">dave@demoblinds.co.uk</span></div></div>
                </div>

                <!-- 4 — password -->
                <div class="sc" data-scene="4" data-len="15">
                  <div class="sct a-fade" style="--d:.2s">A password</div>
                  <div class="g2">' . $field('Password <span class="req">*</span>', '<span class="a-type" style="--d:2s;--ts:10;--tt:.7s">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>', 'a-ring', '--d:1.5s') . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:4s">At least <b>8</b> characters</span>
                    <span class="chip ok a-pop" style="--d:8s">&#10003; No confirmation email</span>
                    <span class="chip ok a-pop" style="--d:11s">They can sign in straight away</span>
                  </div>
                  <p class="scs a-fade" style="--d:13s;margin-top:.8rem">You added them, so the account is trusted. Just tell them the password.</p>
                </div>

                <!-- 5 — roles -->
                <div class="sc" data-scene="5" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">Roles &mdash; tick every one they fill</div>
                  ' . $roles(['Sales' => 'on', 'Fitter' => '9.5'], 'a-rise') . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:4s"><b>Sales</b> is already ticked</span>
                    <span class="chip ok a-pop" style="--d:8s">Dave fits <i>and</i> sells &rarr; both</span>
                  </div>
                  <div class="blk a-rise" style="--d:14s;margin-top:.8rem;max-width:32rem"><h4>Admin is the one that matters</h4>
                    <p>Only <b>Admin</b> opens <b>Products</b>, <b>Users</b>, <b>Settings</b>, <b>Trade terms</b> and <b>Billing</b>. If more than one role is ticked, the highest one drives admin-only access.</p></div>
                </div>

                <!-- 6 — Sales / Fitter, and the labels -->
                <div class="sc" data-scene="6" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">What Sales and Fitter actually do</div>
                  <div class="assign">
                    <div class="blk a-rise" style="--d:2s"><h4>Booking a measure &mdash; Assign to</h4><div>Sam Okafor</div><div>Dave Perry</div><p style="margin-top:.2rem">people with <b>Sales</b></p></div>
                    <div class="blk a-rise" style="--d:6s"><h4>Booking a fitting &mdash; Assign to</h4><div>Dave Perry</div><p style="margin-top:.2rem">people with <b>Fitter</b></p></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:13s">Owner</span><span class="chip a-pop" style="--d:13.4s">Office</span>
                    <span class="chip a-pop" style="--d:13.8s">Agent</span><span class="chip a-pop" style="--d:14.2s">Readonly</span>
                    <span class="chip a-pop" style="--d:15s">= labels only, today</span>
                    <span class="chip bad a-pop" style="--d:18.5s">Readonly does <b>not</b> lock anything</span>
                  </div>
                </div>

                <!-- 7 — permissions: create + view all -->
                <div class="sc" data-scene="7" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">Permissions &mdash; this is what really bites</div>
                  <div class="lab a-fade" style="--d:.8s">Permissions</div>
                  ' . $perms(['Create quotes' => 'on', 'Create orders' => '8.5', 'View all customer jobs' => '12.5']) . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:3s">Create quotes &mdash; ticked to start with</span>
                    <span class="chip a-pop" style="--d:9s">Create orders</span>
                  </div>
                  <div class="blk a-rise" style="--d:14s;max-width:32rem;margin-top:.7rem"><h4>View all customer jobs is a filter</h4>
                    <p>Without it, the <b>Calendar</b>, <b>Customers</b>, <b>Orders</b> and <b>Payments</b> shrink to just the jobs assigned to them.</p></div>
                </div>

                <!-- 8 — costs, fittings only, the menu -->
                <div class="sc" data-scene="8" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">View costs, Fittings only &mdash; and a fitter&rsquo;s menu</div>
                  ' . $perms(['Fittings only' => '8']) . '
                  <div class="chips" style="margin-top:.3rem">
                    <span class="chip bad a-pop" style="--d:3s">View costs = your cost &amp; profit &mdash; off for fitters</span>
                    <span class="chip a-pop" style="--d:9s">Fittings only = fitting jobs only on the calendar</span>
                  </div>
                  <div class="side2" style="margin-top:.7rem">
                    <div class="menu a-rise" style="--d:15s">
                      <div class="h">Work</div><div>Calendar</div>
                      <div class="h">Retail</div>
                      <div class="a-out" style="--d:19.5s">Customers</div><div class="a-out" style="--d:19.8s">Quotes</div>
                      <div class="a-out" style="--d:20.1s">Orders</div><div class="a-out" style="--d:20.4s">Payments</div>
                    </div>
                    <p class="scs a-fade" style="--d:16.5s;max-width:15rem">None of <b>Create quotes</b>, <b>Create orders</b> or <b>View all customer jobs</b>? Customers, Quotes, Orders, Payments and Pipeline leave the menu. A fitter reaches the job from the calendar instead.</p>
                  </div>
                </div>

                <!-- 9 — Add user -->
                <div class="sc" data-scene="9" data-len="19">
                  <div class="bnr a-drop" style="--d:3s">User added.</div>
                  <span class="btnp a-press" style="--d:1.5s">Add user</span>
                  <div class="secttl" style="margin-top:.7rem">Existing users (<span class="sw"><span class="a-out" style="--d:5s">3</span><span class="a-fade" style="--d:5.1s">4</span></span>)</div>
                  <div class="tbl">' . $thead
                    . $row('Dave Perry', 'dave@demoblinds.co.uk', 'Sales, Fitter', '<span class="muted a-ring" style="--d:15s">never</span>', 'a-fly', '--d:6s')
                    . $row('Jane Weller', 'jane@demoblinds.co.uk', 'Admin', '7 Oct 2026 08:12')
                    . $row('Sam Okafor', 'sam@demoblinds.co.uk', 'Sales', '6 Oct 2026 17:40') . '</div>
                  <div class="chips"><span class="chip a-pop" style="--d:8s">In name order</span>
                    <span class="chip a-pop" style="--d:15.5s">&ldquo;never&rdquo; = not signed in yet</span></div>
                </div>

                <!-- 10 — the edit page -->
                <div class="sc" data-scene="10" data-len="16">
                  <div class="pt a-fade" style="--d:.2s">Dave Perry</div><div class="psub"><span class="lnk">&larr; Back to users</span></div>
                  <div class="g2 a-rise" style="--d:1.5s">' . $field('First name', 'Dave') . $field('Last name', 'Perry')
                    . $field('Email ' . $opt, 'dave@demoblinds.co.uk') . $field('Username', '') . '
                    ' . $field('New password', '<span class="ph">Leave blank to keep current</span>', 'a-ring', '--d:8s') . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:5s">Everything you typed, filled back in</span>
                    <span class="chip ok a-pop" style="--d:10s">Blank password = keeps the one they have</span>
                  </div>
                  <p class="scs a-fade" style="--d:13.5s;margin-top:.6rem">Further down: <b>Can see money</b>, the <b>Dashboard</b> box, <b>Active</b>, <b>Home address</b> and the <b>Danger zone</b>.</p>
                </div>

                <!-- 11 — can see money -->
                <div class="sc" data-scene="11" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">Can see money &mdash; on the Edit page only</div>
                  ' . $perms(['Create quotes' => 'on', 'Fittings only' => 'on'], true) . '
                  <div class="a-move" style="--fx:85%;--fy:90%;--tx:5%;--ty:3.3rem;--d:2s;--md:1.6s">' . $ptr . '</div>
                  <p class="note a-fade" style="--d:4s"><b>Can see money</b> &mdash; order values, payments, balances and revenue: the Payments page, taking payments / deposits, values on Orders, Pipeline and the calendar, and the Dashboard&rsquo;s Revenue panel. Untick for fitters. Quote prices stay visible to anyone building a quote. Admins can always see money.</p>
                  <div class="chips">
                    <span class="chip bad a-pop" style="--d:11s">A new user starts <b>without</b> it</span>
                    <span class="chip a-pop" style="--d:14.5s">Office or sales staff who take money &rarr; tick it</span>
                    <span class="chip ok a-pop" style="--d:19s">Admins always can</span>
                  </div>
                </div>

                <!-- 12 — dashboard box -->
                <div class="sc" data-scene="12" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">The Dashboard box</div>
                  <div class="fs a-rise" style="--d:1s"><span class="lg">Dashboard</span>
                    <p>Which Dashboard panels this user can see. Admins always see everything; these checkboxes only apply to non-admin users. Tick none to hide the Dashboard menu entry entirely for this user. <b>Gross profit</b> also requires the <i>View costs</i> permission above. The <b>Revenue &amp; KPIs</b> panel follows <i>Can see money</i> above.</p>
                    <div class="permrow">' . $cb('Sales-team leaderboard', '6') . $cb('Product mix', '7.5') . $cb('Gross profit', '9') . $cb('Recent wins', '10.5') . '</div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:13s">Gross profit also needs View costs</span>
                    <span class="chip bad a-pop" style="--d:17.5s">Every panel shows money &rarr; needs Can see money too</span>
                    <span class="chip a-pop" style="--d:20.5s">No Can see money &rarr; no Dashboard, they land on the Calendar</span>
                  </div>
                </div>

                <!-- 13 — active + home address -->
                <div class="sc" data-scene="13" data-len="18">
                  <div class="sct a-fade" style="--d:.2s">Active, and a home address</div>
                  <div class="a-rise" style="--d:1s">' . $cb('Active (can sign in)', 'on') . '</div>
                  <div class="chips"><span class="chip a-pop" style="--d:3s">Untick &rarr; they can&rsquo;t sign in</span>
                    <span class="chip bad a-pop" style="--d:6.5s">They only see &ldquo;Invalid username/email or password.&rdquo; &mdash; so tell them</span></div>
                  <div class="fs a-rise" style="--d:10s;margin-top:.9rem"><span class="lg">Home address</span>
                    <p>Used as the start and end point for the calendar&rsquo;s &ldquo;Today&rsquo;s run&rdquo; map. Leave blank if the user works out of the office only.</p>
                    <div class="g2">' . $field('Address line 1', '<span class="a-type" style="--d:13s;--ts:14;--tt:.8s">12 Station Road</span>')
                      . $field('Postcode', '<span class="a-type" style="--d:15s;--ts:7;--tt:.5s">TA1 3QS</span>') . '</div>
                  </div>
                </div>

                <!-- 14 — save, danger zone, protected from yourself -->
                <div class="sc" data-scene="14" data-len="20">
                  <div class="bnr a-drop" style="--d:2.5s">User updated.</div>
                  <div><span class="btnp a-press" style="--d:1.2s">Save changes</span> <span class="btns">Cancel</span></div>
                  <div class="dz a-rise" style="--d:6s"><h5>Danger zone</h5>
                    <p>Deleting this user is permanent. Their existing quotes will be kept (link cleared).</p>
                    <span class="btnd a-press" style="--d:8.5s">Delete user</span></div>
                  <div class="confirm a-mid" style="--d:9s;--d2:13.5s">Delete Dave Perry? This cannot be undone.
                    <div class="act"><span class="btns">Cancel</span><span class="btnd">Yes, continue</span></div></div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:14.5s">On your own login: can&rsquo;t untick your Admin</span>
                    <span class="chip ok a-pop" style="--d:16.5s">can&rsquo;t switch yourself off</span>
                    <span class="chip ok a-pop" style="--d:18.5s">no Danger zone</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Users</b> page lives under <b>Setup</b> in the sidebar, and only an <b>Admin</b> can open it. It does two jobs on one
             screen: the <b>Add user</b> form at the top creates a login, and <b>Existing users</b> underneath lists everyone you have &mdash;
             with an <b>Edit</b> link on every row. The Edit page has everything the add form has, <b>plus</b> <b>Can see money</b>, the
             <b>Dashboard</b> panel box, the <b>Active</b> switch, a <b>Home address</b> and a <b>Danger zone</b>. So: add them here, then open
             <b>Edit</b> to finish the job.</p>

          <p class="prose"><b>1) Who they are, and how they get in</b></p>
          <ul class="steps">
            <li><b>First name</b> and <b>Last name</b>. Under them sits a grey note:
                &ldquo;<em>Leave both blank for a <b>workstation</b> &mdash; &ldquo;Vertical Head Rail&rdquo; isn&rsquo;t a person and hasn&rsquo;t
                got a surname. It&rsquo;ll be known by its username.</em>&rdquo;</li>
            <li><b>Email (optional)</b> or <b>Username (use this for staff with no email)</b> &mdash; you need <b>one or the other</b>.
                The login box asks for &ldquo;<b>Username or email</b>&rdquo;, so a workshop login with no email signs in with just its
                username. A username can&rsquo;t contain <b>@</b>.</li>
            <li><b>Password *</b> &mdash; required, and at least <b>8 characters</b>. Because <em>you</em> added them and set the password, the
                account is treated as trusted: <b>no confirmation email goes out</b> and they can sign in straight away.</li>
          </ul>

          <p class="prose"><b>2) The roles &mdash; and what they really do</b></p>
          <ul class="steps">
            <li>Tick <b>every</b> role this person fills. The seven are <b>Admin, Owner, Office, Sales, Agent, Fitter, Readonly</b>, and on a
                fresh form <b>Sales is already ticked</b> &mdash; untick it if it&rsquo;s wrong. Someone who fits <em>and</em> sells gets both.
                The highest one ticked becomes their <b>primary</b> role (&ldquo;The most privileged role drives admin-only access.&rdquo;).</li>
            <li><b>Admin</b> is the one that matters. It is the <b>only</b> role that opens <b>Products, Users, Settings, Trade terms</b> and
                <b>Billing</b>, and an admin always sees money and the whole Dashboard.</li>
            <li><b>Sales</b> and <b>Fitter</b> decide who is offered in an appointment&rsquo;s assign-to list. A <b>measure</b> offers your
                <b>Sales</b> people; a <b>fitting</b> offers your <b>Fitter</b>s. (If nobody holds the role at all, everyone is offered, so
                booking never gets stuck.)</li>
            <li><b>Owner, Office, Agent and Readonly</b> are <b>labels</b> today &mdash; handy for knowing who&rsquo;s who, but they don&rsquo;t
                lock anything down on their own. In particular, <b>Readonly does not make someone read-only</b>. Use the <b>Permissions</b>.</li>
            <li>On the <b>factory account</b> there is an eighth tick, <b>Factory</b>, and a <b>Production area</b> box (see below).</li>
          </ul>

          <p class="prose"><b>3) Permissions &mdash; who sees what</b></p>
          <ul class="steps">
            <li><b>Create quotes</b> (ticked by default) and <b>Create orders</b> &mdash; whether they can raise new work.</li>
            <li><b>View all customer jobs</b> &mdash; a <b>filter</b>, not just a viewing toggle. Without it, the <b>Calendar</b>,
                <b>Customers</b>, <b>Orders</b> and <b>Payments</b> shrink to &ldquo;only the jobs assigned to me&rdquo;.</li>
            <li><b>View costs</b> &mdash; unlocks your <b>cost and profit</b> figures. Leave it <b>off</b> for anyone who shouldn&rsquo;t see
                your margins.</li>
            <li><b>Fittings only</b> &mdash; &ldquo;limits the calendar to fitting jobs (hides measures / sales visits) &mdash; handy for
                fitters.&rdquo;</li>
            <li><b>The menu fact:</b> with <b>none</b> of <em>Create quotes</em>, <em>Create orders</em> or <em>View all customer jobs</em>
                ticked, <b>Customers, Quotes, Orders, Payments and Pipeline</b> all disappear from that person&rsquo;s menu &mdash; right for
                a fitter, who reaches the job from the calendar instead.</li>
          </ul>

          <p>Press <b>Add user</b> and the page comes back with a green <b>User added.</b>, and the new row in <b>Existing users</b>
             below. That heading counts everybody &mdash; <b>Existing users (4)</b> &mdash; and rows are listed <b>by name</b>. Each row shows
             their name (yours is marked <b>(you)</b>), email, roles comma-joined (<em>Sales, Fitter</em>), an <b>active</b> badge, and
             <b>never</b> under <b>Last login</b> until they first sign in.</p>

          <p class="prose"><b>4) The Edit page</b></p>
          <ul class="steps">
            <li>Click <b>Edit</b> on a row and you get the form back, filled in. <b>New password</b> replaces <b>Password *</b> and isn&rsquo;t
                required &mdash; the box says &ldquo;<em>Leave blank to keep current</em>&rdquo;. Type in it only to reset their password (still
                8 characters or more); doing so signs them out of their other sessions.</li>
            <li><b>Can see money</b> (Permissions row, <b>Edit page only</b>): &ldquo;<em>order values, payments, balances and revenue: the
                Payments page, taking payments / deposits, values on Orders, Pipeline and the calendar, and the Dashboard&rsquo;s Revenue panel.
                Untick for fitters. Quote prices stay visible to anyone building a quote. Admins can always see money.</em>&rdquo;
                A newly added (non-admin) user starts <b>without</b> it, so tick it here for office or sales staff who handle money.</li>
            <li><b>The Dashboard box</b>: <b>Sales-team leaderboard</b>, <b>Product mix</b>, <b>Gross profit</b>, <b>Recent wins</b>.
                The <b>Revenue &amp; KPIs</b> tiles follow <b>Can see money</b>. <b>Gross profit</b> also needs <b>View costs</b>. And because
                every Dashboard panel shows money, <b>all</b> of them need <b>Can see money</b> &mdash; without it the person has no Dashboard
                in their menu and lands on the <b>Calendar</b> when they sign in. With <b>Can see money</b> but no panel ticked, they get the
                Dashboard with just the Revenue tiles and Upcoming jobs.</li>
            <li><b>Active (can sign in)</b> is the on/off switch. Untick it and they can&rsquo;t log in &mdash; they just get the ordinary
                <code>Invalid username/email or password.</code>, not &ldquo;your account is off&rdquo;, so tell them.</li>
            <li><b>Home address</b> &mdash; <b>Address line 1</b>, <b>Address line 2</b>, <b>Town</b>, <b>County</b>, <b>Postcode</b>:
                &ldquo;used as the start and end point for the calendar&rsquo;s &lsquo;Today&rsquo;s run&rsquo; map&rdquo;. Leave it blank for
                office-only staff. If postcode lookup is switched on for you, a find-by-postcode box appears above the fields.</li>
            <li><b>Save changes</b> &rarr; green <b>User updated.</b> <b>Cancel</b> goes back without saving. Under the red <b>Danger zone</b>:
                &ldquo;<em>Deleting this user is permanent. Their existing quotes will be kept (link cleared).</em>&rdquo; and <b>Delete user</b>,
                which asks <code>Delete &lt;Name&gt;? This cannot be undone.</code></li>
            <li><b>You are protected from yourself.</b> On your own login the <b>Admin</b> and <b>Active</b> ticks are greyed out (&ldquo;You
                can&rsquo;t untick your own admin.&rdquo;), and there is no Danger zone.</li>
          </ul>

          <p class="prose"><b>5) If you&rsquo;re on the factory account</b></p>
          <ul class="steps">
            <li>As well as the <b>Factory</b> role, a <b>Production area</b> box of tick-boxes appears on <b>both</b> screens &mdash; the one place
                a workshop login&rsquo;s work is set. It drives that login&rsquo;s <b>scan screen</b> and the <b>Floor</b>&rsquo;s default area,
                and needs the <b>Factory</b> role ticked. It is the same list as <b>Factory &rarr; Production areas</b>; if that&rsquo;s empty you
                see <code>No areas set up yet &mdash; add them on Factory &rarr; Production areas first.</code></li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Two ticks decide who sees the money.</b> <b>Can see money</b> covers
             what customers pay &mdash; order values, payments, balances and revenue. <b>View costs</b> covers what <em>you</em> pay &mdash;
             your cost and profit. Leave both <b>off</b> for fitters. The Dashboard&rsquo;s <b>Gross profit</b> panel needs <b>both</b>.</div></div>

          <div class="oops"><b>Common trips &mdash; and what it says:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li>No email <em>and</em> no username &rarr; <code>Enter an email address or a username &mdash; workshop staff can log in with just a username.</code></li>
               <li>An @ in the username &rarr; <code>A username can&rsquo;t contain @ &mdash; put email addresses in the Email field.</code></li>
               <li>A typo in the email &rarr; <code>Please enter a valid email address.</code></li>
               <li>Password too short &rarr; <code>Password must be at least 8 characters.</code> (on Edit: <code>New password must be at least 8 characters (or leave blank to keep the current one).</code>)</li>
               <li>No role ticked &rarr; <code>Pick at least one role.</code> (on Edit: <code>Pick at least one role for this user.</code>)</li>
               <li>Already used by another login &rarr; <code>That email address is already in use.</code> or <code>That username is already in use.</code></li>
               <li>On your own row &rarr; <code>You cannot remove admin from your own account.</code> / <code>You cannot deactivate your own account.</code></li>
               <li>After deleting &rarr; <code>User deleted. Their existing quotes are kept but unlinked.</code></li>
             </ul></div>',
        'script'  => [
            ['1',  'One page, two jobs',              'Everyone who uses the system gets their own login. You make them on the Users page, under Setup. Only an admin can open it. The page does two jobs. At the top, the Add user form makes a new login. Underneath, Existing users lists everyone, with an Edit link on every row. You add someone at the top, then finish the job on Edit.', 1],
            ['2',  'Who are they?',                   'Start with who they are: first name and last name. There is one exception. If you are setting up a bench in the workshop, rather than a person, leave both names blank. A bench is not a person, and has no surname. It will be known by its username instead.', 2],
            ['3',  'Email or username',               'Next, how they sign in. Give them an email address, or a username. You need one or the other, not both. Workshop staff with no email can sign in with just a username. That is why the sign in box says username or email.', 3],
            ['4',  'A password',                      'Then a password, at least eight characters long. Because you added them yourself, the account is trusted. No confirmation email goes out, and they can sign in straight away. Just tell them the password.', 4],
            ['5',  'Roles',                           'Now the roles. Sales is already ticked when the form opens, so untick it if it is wrong. Tick every role this person fills. Dave fits blinds, and he sells, so he gets both. Admin is the one that matters most. Only an admin can open Products, Users, Settings, Trade terms and Billing.', 5],
            ['6',  'What Sales and Fitter do',        'Sales and Fitter have one job each. When you book a measure, the people offered are your sales people. When you book a fitting, they are your fitters. Owner, office, agent and readonly are just labels for now. They do not lock anything down. Readonly does not make someone read only.', 6],
            ['7',  'Permissions: creating, and seeing all', 'Permissions are what really decide what people can do. Create quotes is ticked to start with. Create orders lets them turn work into orders. View all customer jobs is really a filter. Without it, the calendar, customers, orders and payments only show the jobs assigned to that person.', 7],
            ['8',  'Costs, fittings, and the menu',   'View costs shows your cost and profit figures, so leave it off for a fitter. Fittings only keeps their calendar to fitting jobs, and hides your measures. And if none of the first three boxes is ticked, Customers, Quotes, Orders, Payments and Pipeline all disappear from their menu. A fitter gets to the job from the calendar instead.', 8],
            ['9',  'Add user',                        'Press Add user. A green bar says User added, and they join the list below, in name order. You can see their roles, an active badge, and their last login. That says never, until they first sign in. It is a quick way to spot a login nobody has used yet.', 9],
            ['10', 'The Edit page',                   'Click Edit, and you get the whole form back, filled in. The password box now says New password. Leave it blank, and they keep the one they have. Further down are the parts the add form does not have. Let us go through them.', 10],
            ['11', 'Can see money',                   'First, Can see money. It sits with the permissions, but only on the Edit page. It covers order values, payments, balances and revenue. A new user starts without it. So for office or sales staff who take payments, tick it here. Leave it off for fitters. Admins can always see money.', 11],
            ['12', 'The Dashboard box',               'Next, the Dashboard box. Tick the panels this person may see: the sales team leaderboard, product mix, gross profit, and recent wins. Gross profit also needs View costs. And because every panel shows money, they all need Can see money too. Without it, there is no Dashboard at all, and they land on the calendar.', 12],
            ['13', 'Active, and home address',        'Active is the on and off switch for signing in. If you untick it, they just see the usual wrong password message, so do tell them. Their home address is where Today\'s run starts and ends on the calendar map. Leave it blank for office staff.', 13],
            ['14', 'Save, delete, and your own login', 'Press Save changes, and a green bar says User updated. At the very bottom, in the Danger zone, is Delete user. It asks first, and it is permanent, though their quotes are kept. And on your own login, you cannot untick your own admin, switch yourself off, or delete yourself.', 14],
        ],
];
