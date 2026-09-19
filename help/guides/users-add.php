<?php
declare(strict_types=1);

/**
 * Guide: users-add
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Users',
        'title'   => 'Adding users & permissions',
        'eyebrow' => 'Users',
        'blurb'   => 'Add login accounts and set roles + permissions — including who can see your costs.',
        'lede'    => 'Your login accounts &mdash; one per person who uses the system. Set who they are, what <b>roles</b>
                      they fill, and what they&rsquo;re allowed to do and see. Get the permissions right and everyone sees just
                      what they should.',
        'open'    => '/admin/users.php',
        'css'     => '
          .gd .muted{ color:var(--faint); font-weight:400; }
          .gd .plbl{ font-size:.72rem; font-weight:600; color:var(--ink); margin:.75rem 0 .3rem; }
          .gd .permbox{ border:1px solid var(--line); border-radius:8px; padding:.5rem .65rem; display:flex; flex-wrap:wrap; gap:.4rem .9rem; background:var(--surface); transition:box-shadow .2s, border-color .2s; }
          .gd .chkopt{ display:inline-flex; align-items:center; gap:.35rem; font-size:.78rem; color:var(--soft); }
          .gd .stage[data-step="2"] .roles-box{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="3"] .perm-box{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="2"] .t-fitter, .gd .stage[data-step="3"] .t-fitter, .gd .stage[data-step="4"] .t-fitter{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="3"] .t-fitonly, .gd .stage[data-step="4"] .t-fitonly{ background:var(--accent); color:#fff; }
          .gd .addbtn{ margin-top:.85rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.42rem .9rem; font-size:.82rem; font-weight:700; transition:transform .1s, filter .1s; }
          .gd .stage[data-step="4"] .addbtn{ transform:scale(.97); filter:brightness(1.12); }
          .gd .userrow{ display:none; align-items:center; gap:.5rem; margin-top:.9rem; border:1px solid var(--line); border-radius:8px; padding:.45rem .65rem; font-size:.78rem; background:var(--panel); color:var(--ink); }
          .gd .stage[data-step="4"] .userrow{ display:flex; }
          .gd .userrow .grn{ color:var(--good); font-weight:700; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / users</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Users</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Add user</div>
                <div class="frow">
                  <div class="fld"><label>First name</label><div class="box f1"><span class="ph">First name</span><span class="val">Dave</span></div></div>
                  <div class="fld"><label>Last name</label><div class="box f1"><span class="ph">Last name</span><span class="val">Miller</span></div></div>
                </div>
                <div class="frow">
                  <div class="fld"><label>Email <span class="muted">(optional)</span></label><div class="box"><span class="ph">you@&hellip; (or use a username)</span></div></div>
                  <div class="fld"><label>Username <span class="muted">(for staff with no email)</span></label><div class="box f1"><span class="ph">username</span><span class="val">dave</span></div></div>
                </div>
                <div class="fld" style="margin-top:.7rem"><label>Password <span class="req">*</span></label>
                  <div class="box f1"><span class="ph">at least 8 characters</span><span class="val">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span></div></div>
                <div class="plbl">Roles</div>
                <div class="permbox roles-box">
                  <span class="chkopt"><span class="tick">&check;</span> Admin</span>
                  <span class="chkopt"><span class="tick">&check;</span> Owner</span>
                  <span class="chkopt"><span class="tick">&check;</span> Office</span>
                  <span class="chkopt"><span class="tick">&check;</span> Sales</span>
                  <span class="chkopt"><span class="tick">&check;</span> Agent</span>
                  <span class="chkopt"><span class="tick t-fitter">&check;</span> Fitter</span>
                  <span class="chkopt"><span class="tick">&check;</span> Readonly</span>
                </div>
                <div class="plbl">Permissions</div>
                <div class="permbox perm-box">
                  <span class="chkopt"><span class="tick">&check;</span> Create quotes</span>
                  <span class="chkopt"><span class="tick">&check;</span> Create orders</span>
                  <span class="chkopt"><span class="tick">&check;</span> View all customer jobs</span>
                  <span class="chkopt"><span class="tick">&check;</span> View costs</span>
                  <span class="chkopt"><span class="tick t-fitonly">&check;</span> Fittings only</span>
                </div>
                <div class="addbtn">Add user</div>
                <div class="userrow">&#128100; Dave Miller &middot; <span class="muted">Fitter</span> <span class="grn">&check; added</span></div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Name and a login &mdash; email, or a username.</b>
                  <b class="c2"><span class="n">2</span> Tick every role &mdash; here, Fitter.</b>
                  <b class="c3"><span class="n">3</span> Permissions &mdash; what they can do &amp; see.</b>
                  <b class="c4 good"><span class="n">4</span> Add user &mdash; they can log in now.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Users</b> page (in the admin area) holds your login accounts &mdash; add one for each person who uses the system.</p>
          <ul class="steps">
            <li><b>Name + login</b> &mdash; first and last name, then <b>either an email or a username</b>. Workshop staff with no
                email can log in with just a <b>username</b>. Set a <b>password</b> (at least 8 characters).</li>
            <li><b>Roles</b> &mdash; tick <b>every</b> role this person fills (someone who fits and also sells gets both). The most
                privileged role drives admin access. The roles are <b>Admin, Owner, Office, Sales, Agent, Fitter, Readonly</b>.</li>
            <li><b>Permissions</b> &mdash; fine-tune what they can do and see: <b>Create quotes</b>, <b>Create orders</b>,
                <b>View all customer jobs</b>, <b>View costs</b>, and <b>Fittings only</b> (their calendar shows only fitting jobs).</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>These decide who sees the money.</b> <b>View costs</b> is what lets
             someone see your cost and profit figures &mdash; leave it <b>off</b> for fitters and sales staff who shouldn&rsquo;t.
             (Remember the Calendar &ldquo;show order value&rdquo; toggle overrides this <em>on the calendar</em>, so mind that one too.)
             <b>Fittings only</b> keeps a fitter&rsquo;s calendar to just their jobs. Get these wrong and people see too much &mdash; or too little.</div></div>
          <p>Click <b>Add user</b> and they appear in <b>Existing users</b> below, where you can <b>Edit</b> them later &mdash; change
             roles or permissions, reset the password, or switch the account off.</p>',
        'script'  => [
            ['0:00', 'Name, username, password fill in.',      'Add a login for each person. Their name, then an email — or just a username for workshop staff with no email — and a password.', 1],
            ['0:09', 'Tick the Fitter role.',                  'Tick every role they fill. Dave here is a fitter, so we tick Fitter.', 2],
            ['0:15', 'Tick Fittings only; View costs stays off.', 'Then permissions — what they can do and see. Fittings only keeps his calendar to fittings, and we leave View costs off so he doesn\'t see your figures.', 3],
            ['0:24', 'Add user; appears in the list.',         'Add user, and he can log in. You can edit his roles or reset his password any time.', 4],
        ],
];
