<?php
declare(strict_types=1);

/**
 * Guide: users-add
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers BOTH screens: /admin/users.php (the Add user form + the Existing
 * users table) and /admin/users_edit.php (roles, permissions, the Dashboard
 * panel box, Active, Home address and the Danger zone). Two scenes in one
 * stage, swapped by data-step: scene A = steps 1-6, scene B = steps 7-8.
 */

return [
        'aud'     => 'admin',
        'section' => 'Users',
        'title'   => 'Adding users & permissions',
        'eyebrow' => 'Users',
        'blurb'   => 'Add a login, tick the right roles and permissions, then set which Dashboard panels they see.',
        'lede'    => 'This page does <b>two jobs</b>. At the top, the <b>Add user</b> form makes a new login. Underneath,
                      <b>Existing users</b> lists everyone you have, with an <b>Edit</b> link on every row &mdash; and the Edit
                      page is where you fine-tune what that person actually sees: their <b>Dashboard panels</b>, their
                      <b>Active</b> switch, their <b>home address</b> for the calendar&rsquo;s run map &mdash; and the <b>Danger zone</b>
                      that deletes them.',
        'open'    => '/admin/users.php',
        'css'     => '
          .gd .muted{ color:var(--faint); font-weight:400; }
          /* a statically-filled field (looks like .fld .box, but always shows its value) */
          .gd .boxv{ height:30px; border:1px solid var(--line); border-radius:7px; background:var(--panel); display:flex; align-items:center; padding:0 .5rem; font-size:.8rem; color:var(--ink); overflow:hidden; }
          .gd .plbl{ font-size:.72rem; font-weight:600; color:var(--ink); margin:.75rem 0 .3rem; }
          .gd .ghint{ font-size:.68rem; color:var(--faint); margin:.35rem 0 .1rem; line-height:1.45; }
          .gd .permbox{ border:1px solid var(--border-strong,#c7ccd4); border-radius:8px; padding:.5rem .65rem; display:flex; flex-wrap:wrap; gap:.4rem .9rem; background:var(--surface); transition:box-shadow .2s, border-color .2s; }
          .gd .permrow{ display:flex; flex-wrap:wrap; gap:.4rem 1rem; padding:.35rem .4rem; border:1px solid transparent; border-radius:8px; transition:box-shadow .2s, border-color .2s; }
          .gd .chkopt{ display:inline-flex; align-items:center; gap:.35rem; font-size:.78rem; color:var(--soft); }

          /* two scenes in one stage: A = the Users page, B = the Edit page */
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scA, .gd .stage[data-step="1"] .scA, .gd .stage[data-step="2"] .scA,
          .gd .stage[data-step="3"] .scA, .gd .stage[data-step="4"] .scA, .gd .stage[data-step="5"] .scA,
          .gd .stage[data-step="6"] .scA{ display:block; }
          .gd .stage[data-step="7"] .scB, .gd .stage[data-step="8"] .scB{ display:block; }
          /* scene A runs to step 6, so carry the f2 / f3 fills one step further */
          .gd .stage[data-step="6"] .f2 .ph, .gd .stage[data-step="6"] .f3 .ph{ opacity:0; }
          .gd .stage[data-step="6"] .f2 .val, .gd .stage[data-step="6"] .f3 .val{ opacity:1; }

          /* the two ticks that come ON during the walkthrough (Sales + Create quotes are already on in the poster) */
          .gd .stage[data-step="4"] .t-fitter, .gd .stage[data-step="5"] .t-fitter, .gd .stage[data-step="6"] .t-fitter{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="5"] .t-fitonly, .gd .stage[data-step="6"] .t-fitonly{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="7"] .t-rev, .gd .stage[data-step="8"] .t-rev{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="4"] .roles-box{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="5"] .perm-row{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="7"] .dash-fs{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }

          /* the Home address boxes fill in as the voice reaches them at step 8 */
          .gd .stage[data-step="8"] .f8 .ph{ opacity:0; }
          .gd .stage[data-step="8"] .f8 .val{ opacity:1; animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="8"] .f8{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }

          .gd .addbtn{ margin-top:.85rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.42rem .9rem; font-size:.82rem; font-weight:700; transition:transform .1s, filter .1s; }
          .gd .stage[data-step="6"] .addbtn{ transform:scale(.97); filter:brightness(1.12); }
          .gd .stage[data-step="8"] .save{ transform:scale(.96); filter:brightness(1.25); }

          /* the green flash the real page prints at the TOP, under the heading */
          .gd .flash{ display:none; margin:0 0 .7rem; }
          .gd .stage[data-step="6"] .scA .flash, .gd .stage[data-step="8"] .scB .flash{ display:block; }
          /* the result of pressing Add user: the new row in the real table */
          .gd .aftr{ display:none; margin-top:.9rem; }
          .gd .stage[data-step="6"] .aftr{ display:block; }
          .gd .utitle{ font-size:.72rem; font-weight:700; color:var(--ink); margin:.7rem 0 .35rem; }
          .gd .utab{ display:grid; grid-template-columns:5.6rem 5.2rem 5rem 3.6rem 3.2rem 2.2rem; gap:1px; background:var(--line); border:1px solid var(--line); border-radius:8px; overflow:hidden; }
          .gd .uc{ background:var(--surface); padding:.28rem .32rem; font-size:.66rem; color:var(--ink); }
          .gd .uc.hd{ background:var(--panel); color:var(--faint); font-weight:700; font-size:.58rem; text-transform:uppercase; letter-spacing:.03em; }
          .gd .uc .nvr{ color:var(--faint); }
          .gd .uc .you{ color:var(--faint); }
          .gd .uc.rl{ text-transform:capitalize; }
          .gd .uc .lnk{ color:var(--accent); }
          .gd .ubadge{ display:inline-block; border-radius:999px; padding:.02rem .38rem; font-size:.58rem; font-weight:700; background:var(--good-wash); color:var(--good); }

          /* the bordered fieldsets on the Edit page (Dashboard, Home address) */
          .gd .gdfs{ border:1px solid var(--line); border-radius:10px; padding:.55rem .7rem .65rem; margin:.7rem 0 0; transition:box-shadow .2s, border-color .2s; }
          .gd .gdlg{ font-size:.58rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.35rem; }
          .gd .fscol{ display:flex; flex-direction:column; gap:.3rem; }
          .gd .fsnote{ font-size:.62rem; color:var(--faint); margin-left:.35rem; }
          .gd .back{ font-size:.66rem; color:var(--faint); margin:-.6rem 0 .7rem; }
          .gd .addr3{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:.4rem; margin-top:.4rem; }
          /* Save changes / Cancel: Cancel is a real secondary BUTTON, not a text link */
          .gd .acts{ display:flex; align-items:center; gap:.5rem; }
          .gd .acts .save{ margin-top:1rem; }
          .gd .cancelbtn{ margin-top:1rem; display:inline-flex; align-items:center; border:1px solid var(--border-strong,#c7ccd4); background:var(--surface); color:var(--soft); border-radius:8px; padding:.42rem .8rem; font-size:.82rem; font-weight:600; }
          /* Danger zone */
          .gd .dz{ margin-top:1rem; padding-top:.7rem; border-top:1px solid var(--line); }
          .gd .dzh{ font-size:.8rem; font-weight:700; color:#b91c1c; margin:0 0 .25rem; }
          .gd .delbtn{ margin-top:.5rem; display:inline-flex; background:var(--err); color:#fff; border-radius:8px; padding:.38rem .8rem; font-size:.8rem; font-weight:700; }
          @media(max-width:620px){ .gd .utab{ grid-template-columns:1fr 1fr; } .gd .addr3{ grid-template-columns:1fr; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / admin / users</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Users</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- Scene A: /admin/users.php -->
                <div class="osc scA">
                  <div class="flash"><div class="okbanner"><b>&check;</b> User added.</div></div>
                  <div class="card-t">Add user</div>
                  <div class="frow">
                    <div class="fld"><label>First name</label><div class="box f1"><span class="ph">First name</span><span class="val">Dave</span></div></div>
                    <div class="fld"><label>Last name</label><div class="box f1"><span class="ph">Last name</span><span class="val">Miller</span></div></div>
                  </div>
                  <p class="ghint">Leave both blank for a <b>workstation</b> &mdash; &ldquo;Vertical Head Rail&rdquo; isn&rsquo;t a person and hasn&rsquo;t got a surname. It&rsquo;ll be known by its username.</p>
                  <div class="frow">
                    <div class="fld"><label>Email <span class="muted">(optional)</span></label><div class="box f2"><span class="ph"></span><span class="val">dave@demoblinds.example</span></div></div>
                    <div class="fld"><label>Username <span class="muted">(use this for staff with no email)</span></label><div class="box"><span class="ph"></span></div></div>
                  </div>
                  <div class="frow" style="margin-top:.7rem">
                    <div class="fld"><label>Password <span class="req">*</span></label>
                      <div class="box f3"><span class="ph"></span><span class="val">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span></div></div>
                    <div class="fld"><label>Roles</label>
                      <div class="permbox roles-box">
                        <span class="chkopt"><span class="tick">&check;</span> Admin</span>
                        <span class="chkopt"><span class="tick">&check;</span> Owner</span>
                        <span class="chkopt"><span class="tick">&check;</span> Office</span>
                        <span class="chkopt"><span class="tick on">&check;</span> Sales</span>
                        <span class="chkopt"><span class="tick">&check;</span> Agent</span>
                        <span class="chkopt"><span class="tick t-fitter">&check;</span> Fitter</span>
                        <span class="chkopt"><span class="tick">&check;</span> Readonly</span>
                      </div>
                    </div>
                  </div>
                  <div class="plbl">Permissions</div>
                  <div class="permrow perm-row">
                    <span class="chkopt"><span class="tick on">&check;</span> Create quotes</span>
                    <span class="chkopt"><span class="tick">&check;</span> Create orders</span>
                    <span class="chkopt"><span class="tick">&check;</span> View all customer jobs</span>
                    <span class="chkopt"><span class="tick">&check;</span> View costs</span>
                    <span class="chkopt"><span class="tick t-fitonly">&check;</span> Fittings only</span>
                  </div>
                  <p class="ghint"><b>Fittings only</b> limits the calendar to fitting jobs (hides measures / sales visits) &mdash; handy for fitters.</p>
                  <div class="addbtn">Add user</div>

                  <div class="aftr">
                    <div class="utitle">Existing users (4)</div>
                    <div class="utab">
                      <span class="uc hd">Name</span><span class="uc hd">Email</span><span class="uc hd">Roles</span><span class="uc hd">Status</span><span class="uc hd">Last login</span><span class="uc hd"></span>
                      <span class="uc">Anna Shaw <span class="you">(you)</span></span><span class="uc">anna@&hellip;</span><span class="uc rl">admin, sales</span><span class="uc"><span class="ubadge">active</span></span><span class="uc">18 Sep 2026 08:14</span><span class="uc"><span class="lnk">Edit</span></span>
                      <span class="uc">Dave Miller</span><span class="uc">dave@&hellip;</span><span class="uc rl">sales, fitter</span><span class="uc"><span class="ubadge">active</span></span><span class="uc"><span class="nvr">never</span></span><span class="uc"><span class="lnk">Edit</span></span>
                      <span class="uc">Jo Patel</span><span class="uc">jo@&hellip;</span><span class="uc rl">office</span><span class="uc"><span class="ubadge">active</span></span><span class="uc">17 Sep 2026 16:02</span><span class="uc"><span class="lnk">Edit</span></span>
                      <span class="uc">Sam Reed</span><span class="uc">sam@&hellip;</span><span class="uc rl">fitter</span><span class="uc"><span class="ubadge">active</span></span><span class="uc">12 Sep 2026 07:48</span><span class="uc"><span class="lnk">Edit</span></span>
                    </div>
                  </div>
                </div>

                <!-- Scene B: /admin/users_edit.php -->
                <div class="osc scB">
                  <div class="card-t">Dave Miller</div>
                  <p class="back">&larr; Back to users</p>
                  <div class="flash"><div class="okbanner"><b>&check;</b> User updated.</div></div>

                  <div class="frow">
                    <div class="fld"><label>First name</label><div class="boxv">Dave</div></div>
                    <div class="fld"><label>Last name</label><div class="boxv">Miller</div></div>
                  </div>
                  <div class="frow" style="margin-top:.55rem">
                    <div class="fld"><label>Email <span class="muted">(optional)</span></label><div class="boxv">dave@demoblinds.example</div></div>
                    <div class="fld"><label>Username</label><div class="boxv">&nbsp;</div></div>
                  </div>
                  <div class="frow" style="margin-top:.55rem">
                    <div class="fld"><label>New password</label>
                      <div class="box"><span class="ph">Leave blank to keep current</span></div></div>
                    <div class="fld"><label>Roles</label>
                      <div class="permbox">
                        <span class="chkopt"><span class="tick">&check;</span> Admin</span>
                        <span class="chkopt"><span class="tick">&check;</span> Owner</span>
                        <span class="chkopt"><span class="tick">&check;</span> Office</span>
                        <span class="chkopt"><span class="tick on">&check;</span> Sales</span>
                        <span class="chkopt"><span class="tick">&check;</span> Agent</span>
                        <span class="chkopt"><span class="tick on">&check;</span> Fitter</span>
                        <span class="chkopt"><span class="tick">&check;</span> Readonly</span>
                      </div>
                    </div>
                  </div>
                  <p class="ghint">Tick every role this person fills &mdash; e.g. someone who fits and also closes sales should have both
                     ticked. The most privileged role drives admin-only access.</p>

                  <div class="plbl">Permissions</div>
                  <div class="permrow">
                    <span class="chkopt"><span class="tick on">&check;</span> Create quotes</span>
                    <span class="chkopt"><span class="tick">&check;</span> Create orders</span>
                    <span class="chkopt"><span class="tick">&check;</span> View all customer jobs</span>
                    <span class="chkopt"><span class="tick">&check;</span> View costs</span>
                    <span class="chkopt"><span class="tick on">&check;</span> Fittings only</span>
                  </div>
                  <p class="ghint"><b>Fittings only</b> limits a user&rsquo;s calendar to fitting jobs (hides measures / sales visits)
                     &mdash; handy for fitters.</p>

                  <fieldset class="gdfs dash-fs">
                    <div class="gdlg">Dashboard</div>
                    <p class="ghint" style="margin-top:0">Which Dashboard panels this user can see. Admins always see everything; these checkboxes only apply to non-admin users. Tick none to hide the Dashboard menu entry entirely for this user. <b>Gross profit</b> also requires the <b>View costs</b> permission above.</p>
                    <div class="fscol">
                      <span class="chkopt"><span class="tick t-rev">&check;</span> Revenue &amp; KPIs</span>
                      <span class="chkopt"><span class="tick">&check;</span> Sales-team leaderboard</span>
                      <span class="chkopt"><span class="tick">&check;</span> Product mix</span>
                      <span class="chkopt"><span class="tick">&check;</span> Gross profit <span class="fsnote">needs View costs too</span></span>
                      <span class="chkopt"><span class="tick">&check;</span> Recent wins</span>
                    </div>
                  </fieldset>

                  <div style="margin-top:.7rem"><span class="chkopt"><span class="tick on">&check;</span> Active (can sign in)</span></div>

                  <fieldset class="gdfs">
                    <div class="gdlg">Home address</div>
                    <p class="ghint" style="margin-top:0">Used as the start and end point for the calendar&rsquo;s &ldquo;Today&rsquo;s run&rdquo; map. Leave blank if the user works out of the office only.</p>
                    <div class="fld"><label>Address line 1</label>
                      <div class="box f8"><span class="ph"></span><span class="val">12 Sample Terrace</span></div></div>
                    <div class="fld" style="margin-top:.4rem"><label>Address line 2</label>
                      <div class="box"><span class="ph"></span></div></div>
                    <div class="addr3">
                      <div class="fld"><label>Town</label>
                        <div class="box f8"><span class="ph"></span><span class="val">Leeds</span></div></div>
                      <div class="fld"><label>County</label>
                        <div class="box f8"><span class="ph"></span><span class="val">West Yorkshire</span></div></div>
                      <div class="fld"><label>Postcode</label>
                        <div class="box f8"><span class="ph"></span><span class="val">LS9 8AB</span></div></div>
                    </div>
                  </fieldset>

                  <div class="acts"><span class="save">Save changes</span><span class="cancelbtn">Cancel</span></div>

                  <div class="dz">
                    <p class="dzh">Danger zone</p>
                    <p class="ghint" style="margin-top:0">Deleting this user is permanent. Their existing quotes will be kept (link cleared).</p>
                    <div class="delbtn">Delete user</div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> First and last name &mdash; or leave blank for a workstation.</b>
                  <b class="c2"><span class="n">2</span> An email <em>or</em> a username &mdash; one is enough.</b>
                  <b class="c3"><span class="n">3</span> A password, at least 8 characters.</b>
                  <b class="c4"><span class="n">4</span> Roles &mdash; Sales is already on; tick Fitter too.</b>
                  <b class="c5"><span class="n">5</span> Permissions &mdash; Fittings only on, View costs off.</b>
                  <b class="c6 good"><span class="n">6</span> Add user &mdash; he&rsquo;s in the list, last login &ldquo;never&rdquo;.</b>
                  <b class="c7"><span class="n">7</span> Edit &rarr; the same fields, plus Dashboard: which panels he may see.</b>
                  <b class="c8 good"><span class="n">8</span> Active, home address typed in, Save changes.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Users</b> page lives under <b>Setup</b> in the sidebar, and only an <b>Admin</b> can open it. It does two jobs on one
             screen: the <b>Add user</b> form at the top creates a login, and <b>Existing users</b> underneath lists everyone you have &mdash;
             with an <b>Edit</b> link on every row. The Edit page has everything the add form has, <b>plus</b> the <b>Dashboard</b> panel box,
             the <b>Active</b> switch, a <b>Home address</b> and a <b>Danger zone</b> for deleting them. So: add them here, then open
             <b>Edit</b> to finish the job.</p>

          <p class="prose"><b>1) Who they are, and how they get in</b></p>
          <ul class="steps">
            <li><b>First name</b> and <b>Last name</b>. Under them sits a grey note worth reading:
                &ldquo;<em>Leave both blank for a <b>workstation</b> &mdash; &ldquo;Vertical Head Rail&rdquo; isn&rsquo;t a person and hasn&rsquo;t
                got a surname. It&rsquo;ll be known by its username.</em>&rdquo; A bench in the workshop isn&rsquo;t a person, so don&rsquo;t
                invent one &mdash; leave the names empty and give it a username instead.</li>
            <li><b>Email (optional)</b> or <b>Username (use this for staff with no email)</b> &mdash; you need <b>one or the other</b>, not both.
                The login box asks for &ldquo;<b>Username or email</b>&rdquo;, so a workshop login with no email address signs in perfectly
                happily with just its username.</li>
            <li><b>Password *</b> &mdash; required, and at least <b>8 characters</b>. Because <em>you</em> added them and set the password, the
                account is treated as trusted: <b>no confirmation email goes out</b> and they can sign in straight away. Tell them the
                password and they are in.</li>
          </ul>

          <p class="prose"><b>2) The roles &mdash; and what they really do</b></p>
          <ul class="steps">
            <li>Tick <b>every</b> role this person fills. The seven are <b>Admin, Owner, Office, Sales, Agent, Fitter, Readonly</b>, and on a
                fresh page <b>Sales is already ticked</b> &mdash; so untick it if it&rsquo;s wrong. Someone who fits <em>and</em> closes sales
                gets both. The highest one ticked becomes their <b>primary</b> role.</li>
            <li><b>Admin</b> is the one that matters. It is the <b>only</b> role that opens <b>Products, Users, Settings, Trade terms</b> and
                <b>Billing</b>. Everything else in the app is governed by the permissions below, not by the role.</li>
            <li><b>Sales</b> and <b>Fitter</b> have exactly one job each: they decide who is offered in an appointment&rsquo;s
                <b>&ldquo;Assigned to&rdquo;</b> list. A <b>measure</b> offers your <b>Sales</b> people; a <b>fitting</b> offers your
                <b>Fitter</b>s. (If nobody holds the role at all, everyone is offered, so nothing ever gets stuck.)</li>
            <li><b>Owner, Office, Agent and Readonly</b> are <b>labels</b> today &mdash; handy for knowing who&rsquo;s who, but they don&rsquo;t
                lock anything down on their own. In particular, <b>Readonly does not make someone read-only</b>. Use the
                <b>Permissions</b> for that.</li>
            <li>On the <b>factory account</b> there is an eighth tick, <b>Factory</b>. It doesn&rsquo;t appear anywhere else.</li>
          </ul>

          <p class="prose"><b>3) Permissions &mdash; who sees what</b></p>
          <ul class="steps">
            <li><b>Create quotes</b> (ticked by default) and <b>Create orders</b> &mdash; whether they can raise new work.</li>
            <li><b>View all customer jobs</b> &mdash; this is a <b>filter</b>, not just a viewing toggle. Without it, the <b>Calendar</b>,
                <b>Customers</b>, <b>Orders</b> and <b>Payments</b> all shrink to &ldquo;only the jobs assigned to me&rdquo;.</li>
            <li><b>View costs</b> &mdash; unlocks your <b>cost and profit</b> figures. Leave it <b>off</b> for anyone who shouldn&rsquo;t be
                looking at your margins.</li>
            <li><b>Fittings only</b> &mdash; their calendar shows <b>fitting jobs only</b>; measures and sales visits are hidden.</li>
            <li><b>The menu fact:</b> with <b>none</b> of <em>Create quotes</em>, <em>Create orders</em> or <em>View all customer jobs</em>
                ticked, <b>Customers, Quotes, Orders, Payments and Pipeline all disappear</b> from that person&rsquo;s menu. That&rsquo;s exactly
                right for a fitter &mdash; they reach their job through <b>My Schedule</b> instead.</li>
          </ul>

          <p class="prose"><b>4) The Edit page &mdash; the same form again, with four extras</b></p>
          <ul class="steps">
            <li>Click <b>Edit</b> on anybody&rsquo;s row and you get the whole form back, already filled in: <b>First name</b>,
                <b>Last name</b>, <b>Email (optional)</b>, <b>Username</b>, <b>Roles</b> and <b>Permissions</b>. Change what you like and
                press <b>Save changes</b>. (One small difference: on the Edit page <b>Username</b> is plain &mdash; it loses the
                &ldquo;use this for staff with no email&rdquo; note the add form carries.)</li>
            <li><b>New password</b> replaces the add form&rsquo;s <b>Password *</b>, and it is <em>not</em> required. The box says
                &ldquo;<em>Leave blank to keep current</em>&rdquo; &mdash; so leave it alone and their existing password carries on
                working. Type in it only when you are actually resetting it for them, and it still has to be <b>8 characters</b> or
                more. Under the <b>Roles</b> box sits the same note the add form doesn&rsquo;t show: &ldquo;<em>Tick every role this
                person fills &mdash; e.g. someone who fits and also closes sales should have both ticked. The most privileged role
                drives admin-only access.</em>&rdquo;</li>
            <li>Then the four things the add form hasn&rsquo;t got at all: the <b>Dashboard</b> panel box, the <b>Active</b> switch,
                the <b>Home address</b> fieldset and, right at the bottom, the <b>Danger zone</b>. They are covered next.</li>
          </ul>

          <p class="prose"><b>5) The Dashboard box (Edit page only)</b></p>
          <p>The screen says it plainly: &ldquo;<em>Which Dashboard panels this user can see. Admins always see everything; these checkboxes only
             apply to non-admin users. Tick none to hide the Dashboard menu entry entirely for this user. Gross profit also requires the
             View costs permission above.</em>&rdquo;</p>
          <ul class="steps">
            <li><b>Revenue &amp; KPIs</b> &mdash; the four tiles at the top: <em>Revenue (won)</em>, <em>Average order value</em>,
                <em>Close rate</em> and <em>Jobs in period</em>.</li>
            <li><b>Sales-team leaderboard</b> &mdash; the <em>Sales team</em> table.</li>
            <li><b>Product mix</b> &mdash; the <em>What&rsquo;s selling</em> panel.</li>
            <li><b>Gross profit</b> &mdash; the <em>Gross profit</em> panel. It needs <b>View costs</b> as well; tick this alone and nothing
                appears.</li>
            <li><b>Recent wins</b> &mdash; the recent-wins list.</li>
            <li><b>Tick none</b> and the <b>Dashboard vanishes from their menu</b> &mdash; they land on the <b>Calendar</b> instead, and typing
                the dashboard address just bounces them there too.</li>
          </ul>

          <p class="prose"><b>6) Active, home address, and getting rid of someone</b></p>
          <ul class="steps">
            <li><b>Active (can sign in)</b> is the on/off switch. Untick it and they can&rsquo;t log in &mdash; but be warned, they just get the
                ordinary <code>Invalid username/email or password.</code> message, <b>not</b> &ldquo;your account is off&rdquo;. So tell them,
                or you&rsquo;ll get a phone call about a broken password.</li>
            <li><b>Home address</b> &mdash; <b>Address line 1</b>, <b>Address line 2</b>, <b>Town</b>, <b>County</b>, <b>Postcode</b>. It is
                &ldquo;used as the start and end point for the calendar&rsquo;s <b>Today&rsquo;s run</b> map&rdquo;. Leave it blank for anyone who
                works out of the office only. If postcode lookup is switched on for you, a <b>Find by postcode</b> box and a <b>Find address</b>
                button appear above the fields &mdash; type the postcode, press the button, then <b>Pick an address</b>.</li>
            <li><b>Save changes</b> &mdash; the blue button at the foot of the form, with a grey <b>Cancel</b> button beside it that takes
                you back to the users list without saving. Below the form, under a red <b>Danger zone</b> heading, sits
                &ldquo;<em>Deleting this user is permanent. Their existing quotes will be kept (link cleared).</em>&rdquo; and the red
                <b>Delete user</b> button. It really is permanent.</li>
            <li><b>You are protected from yourself.</b> On your own row the <b>Admin</b> tick and the <b>Active</b> tick are greyed out, and
                there is no Danger zone &mdash; you can&rsquo;t untick your own admin, switch yourself off, or delete yourself.</li>
          </ul>

          <p class="prose"><b>7) If you&rsquo;re on the factory account</b></p>
          <ul class="steps">
            <li>As well as the extra <b>Factory</b> role, a <b>Production area</b> box of tick-boxes appears on <b>both</b> screens. This is the
                <b>one place</b> a workshop login&rsquo;s work is set: it drives that login&rsquo;s <b>scan screen</b> (the blinds it can finish)
                and the <b>Floor</b>&rsquo;s default area. Tick as many areas as the bench covers. It needs the <b>Factory</b> role ticked too.</li>
            <li>It is the <b>same list</b> as <b>Factory &rarr; Production areas</b>, so the two can never drift apart. Empty box? You&rsquo;ll see
                <code>No areas set up yet &mdash; add them on Factory &rarr; Production areas first.</code></li>
            <li>A bench login that has an area lands <b>straight on the factory Floor</b> when it signs in &mdash; no hunting through menus.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>These decide who sees the money.</b> <b>View costs</b> is what shows your cost
             and profit figures &mdash; leave it <b>off</b> for fitters and for sales staff who shouldn&rsquo;t see your margins. The Dashboard&rsquo;s
             <b>Gross profit</b> panel needs <b>View costs</b> as well, so ticking it on its own does nothing. And remember
             <b>Settings &rarr; Calendar</b> has &ldquo;<b>Show order value + balance on the calendar</b>&rdquo; &mdash; that one overrides
             <b>View costs</b> <em>on the calendar</em>, for everybody who can open it.</div></div>

          <div class="oops"><b>Common trips &mdash; and what it says:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li>No email <em>and</em> no username &rarr; <code>Enter an email address or a username &mdash; workshop staff can log in with just a username.</code></li>
               <li>A typo in the email &rarr; <code>Please enter a valid email address.</code></li>
               <li>Password too short &rarr; <code>Password must be at least 8 characters.</code> (on Edit: <code>New password must be at least 8 characters (or leave blank to keep the current one).</code>)</li>
               <li>No role ticked &rarr; <code>Pick at least one role.</code> (on Edit: <code>Pick at least one role for this user.</code>)</li>
               <li>Address already used by another login &rarr; <code>That email address is already in use.</code> or <code>That username is already in use.</code></li>
               <li>Trying it on your own row &rarr; <code>You cannot remove admin from your own account.</code> and <code>You cannot deactivate your own account.</code></li>
               <li>Deleting &rarr; it asks <code>Delete &lt;Name&gt;? This cannot be undone.</code>, then confirms <code>User deleted. Their existing quotes are kept but unlinked.</code></li>
             </ul></div>

          <p>Press <b>Add user</b> and the page comes back with a green <b>User added.</b> across the <b>top</b>, and the new row in
             <b>Existing users</b> below. That heading counts everybody &mdash; <b>Existing users (4)</b> &mdash; and the rows are listed
             <b>by name</b>, so your new person slots in alphabetically rather than at the bottom. Each row shows their name (your own is
             marked <b>(you)</b>), their email, their roles comma-joined (<em>Sales, Fitter</em>), an <b>active</b> badge, and a grey
             <b>never</b> under <b>Last login</b>. When they first
             sign in they land on the <b>Dashboard</b> if they have any panel ticked, otherwise the <b>Calendar</b> &mdash; and a factory bench
             login with an area goes straight to the <b>Floor</b>. That grey <b>never</b> is the quickest way to spot a login nobody has picked
             up yet.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Names fill in; the workstation hint sits under them.',
                     'One login for each person who uses the system. Start with their first name and last name. If you\'re setting up a bench in the workshop rather than a person, leave both blank — it\'ll be known by its username instead.', 1],
            ['0:11', 'Email fills; Username stays empty.',
                     'Then an email address, or a username. You need one or the other, not both. Workshop staff with no email log in with just a username — that\'s what the login box means by "username or email".', 2],
            ['0:21', 'Password fills.',
                     'Give them a password, at least eight characters. They can sign in with it straight away — no confirmation email, because you added them yourself.', 3],
            ['0:30', 'Fitter ticked; Sales already ticked.',
                     'Now the roles. Sales is already ticked when the page opens, so untick it if it\'s wrong. Tick every role this person fills — Dave fits and he sells, so he gets both. Only Admin opens the setup screens; Sales and Fitter decide who\'s offered when you book a measure or a fitting.', 4],
            ['0:44', 'Fittings only ticked; View costs left off.',
                     'Permissions are what really bite. Create quotes is ticked by default. View costs is the one that shows your cost and profit figures, so leave it off for a fitter. Fittings only keeps his calendar to fitting jobs, and hides your measure and sales visits.', 5],
            ['0:58', 'Add user pressed; green "User added." at the top; the row appears in the list.',
                     'Press Add user. A green "User added." comes up at the top of the page, and he joins the list below — in name order, active, with his roles beside him, and his last login showing "never" until he first signs in.', 6],
            ['1:09', 'Edit page: the same fields filled in, New password blank, Dashboard fieldset lights up.',
                     'Open Edit and you get the whole form back, already filled in — names, email, username, roles and permissions. The only one that changes is the password box: it now says "New password", and leaving it blank keeps the one he\'s already got. Underneath is the part the add form hasn\'t got. The Dashboard box picks which panels of the dashboard he\'s allowed to see. Tick none at all and the Dashboard disappears from his menu completely. Gross profit only works if you\'ve also given him View costs.', 7],
            ['1:30', 'Home address typed in; Save changes; green "User updated."; Danger zone below.',
                     'Underneath, "Active" is the on-off switch for signing in, and his home address is where Today\'s run starts and ends on the map — address line one, town, county and postcode. Press Save changes and you get a green "User updated." at the top. Cancel, beside it, backs out without saving. And right at the bottom, in the Danger zone, "Delete user" — permanent, so take care.', 8],
        ],
];
