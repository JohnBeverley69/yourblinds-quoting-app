<?php
declare(strict_types=1);

/**
 * Guide: platform-accounting-apps
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * SUPER-ADMIN ONLY. The one-off, platform-owner half of the accounting link:
 * registering YourBlinds as an app with Intuit and putting its keys into
 * YourBlinds, so every tenant then gets a working "Connect to QuickBooks"
 * button (the tenant half is the settings-accounting guide).
 *
 * Four scenes, driven by the stage's data-step:
 *   .scDev   (steps 0, 1, 2, 6) — Intuit's developer portal (their site, drawn plainly)
 *   .scCred  (steps 3, 4, 7)    — Settings → Accounting → "QuickBooks app credentials"
 *   .scHlth  (steps 5, 8)       — /master-admin/accounting-health.php (plain text)
 *
 * Sources: _partials/quickbooks.php (SCOPE com.intuit.quickbooks.accounting,
 * redirectUri(): QUICKBOOKS_REDIRECT_URI, else APP_URL + /admin/accounting/callback.php;
 * environment() sandbox|production), _partials/accounting.php (pc_get: the
 * platform_config DB value wins over .env), admin/settings.php
 * (accounting_platform action + the <details> form: Environment select,
 * Client ID text, Client Secret password — blank keeps the stored one —,
 * Redirect URI text, "Save credentials"; flash "QuickBooks app credentials
 * saved."; badge "Sandbox (test)" / "Live"; card "Not set up" / "Not connected"),
 * master-admin/accounting-health.php, migrate_platform_config.php.
 *
 * PUBLIC REPO: never put a real Client ID, secret or realm ID in here — the
 * values shown are made-up placeholders.
 *
 * Xero / Sage are NOT built. The body only sketches their developer-side
 * registration, clearly marked; revisit when a provider class exists.
 */

return [
        'aud'     => 'super',
        'section' => 'Settings',
        'title'   => 'Set up the accounting app keys (platform owner)',
        'eyebrow' => 'Settings · Accounting (platform)',
        'blurb'   => 'The one-off job behind every client\'s "Connect to QuickBooks" button: register YourBlinds with Intuit, paste its keys into YourBlinds, check it, then get approved for live. Plus what Xero and Sage will need.',
        'lede'    => 'Before any client can press <b>Connect to QuickBooks</b>, YourBlinds itself has to be registered with Intuit as an
                      <b>app</b>. You do that <b>once, for the whole platform</b>: Intuit gives you a <b>Client ID</b> and <b>Client Secret</b>,
                      you paste them into YourBlinds, and every account can then link its own QuickBooks company. You start on Intuit&rsquo;s
                      <b>sandbox</b> (test companies, instant) and move to <b>production</b> (real companies) once Intuit has approved the app.
                      Only super-admins see this guide and the keys form.',
        'open'    => '/admin/settings.php#accounting',
        'css'     => '
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scDev, .gd .stage[data-step="1"] .scDev,
          .gd .stage[data-step="2"] .scDev, .gd .stage[data-step="6"] .scDev{ display:block; }
          .gd .stage[data-step="3"] .scCred, .gd .stage[data-step="4"] .scCred, .gd .stage[data-step="7"] .scCred{ display:block; }
          .gd .stage[data-step="5"] .scHlth, .gd .stage[data-step="8"] .scHlth{ display:block; }

          /* ---- Intuit developer portal (their site — drawn plainly) ---- */
          .gd .dev{ border:1px solid var(--line); border-radius:12px; background:var(--surface); max-width:32rem; padding:.75rem .9rem; }
          .gd .dev .who{ font-size:.6rem; text-transform:uppercase; letter-spacing:.06em; color:var(--faint); font-weight:700; }
          .gd .dev h4{ margin:.3rem 0 .55rem; font-size:.92rem; color:var(--ink); }
          .gd .dev .dnav{ display:flex; gap:.3rem; flex-wrap:wrap; margin-bottom:.55rem; }
          .gd .dev .dnav span{ font-size:.64rem; color:var(--soft); border:1px solid var(--line); border-radius:6px; padding:.12rem .4rem; }
          .gd .dev .dnav span.on{ border-color:#2ca01c; color:#1f7a13; font-weight:700; }
          .gd .dev .row{ display:flex; gap:.5rem; align-items:center; font-size:.72rem; color:var(--ink); border:1px solid var(--line); border-radius:7px; padding:.35rem .5rem; margin:.3rem 0; }
          .gd .dev .row .k{ color:var(--faint); min-width:6.5rem; font-size:.66rem; }
          .gd .dev .row code{ font-size:.68rem; word-break:break-all; }
          .gd .dev .tk{ width:.8rem; height:.8rem; border-radius:3px; border:2px solid var(--line); flex:none; }
          .gd .dev .tk.on{ background:#2ca01c; border-color:#2ca01c; }
          .gd .dev .gbtn{ display:inline-block; margin-top:.45rem; border-radius:8px; padding:.32rem .7rem; font-size:.72rem; font-weight:700; background:#2ca01c; color:#fff; }
          .gd .dev .note{ font-size:.64rem; color:var(--soft); margin:.45rem 0 0; line-height:1.45; }
          .gd .d0, .gd .d1, .gd .d2, .gd .d6{ display:none; }
          .gd .stage[data-step="0"] .d0, .gd .stage[data-step="1"] .d1,
          .gd .stage[data-step="2"] .d2, .gd .stage[data-step="6"] .d6{ display:block; }
          .gd .stage[data-step="1"] .d1 .hl, .gd .stage[data-step="2"] .d2 .hl{ border-color:#2ca01c; box-shadow:0 0 0 3px color-mix(in srgb,#2ca01c 18%,transparent); }

          /* ---- Settings → Accounting: badge + the super-admin keys form ---- */
          .gd .tabstrip{ display:flex; flex-wrap:wrap; gap:.1rem .25rem; border-bottom:1px solid var(--line); margin-bottom:.55rem; }
          .gd .tabx{ font-size:.68rem; color:var(--faint); padding:.28rem .45rem; border-bottom:2px solid transparent; white-space:nowrap; }
          .gd .tabx.on{ color:var(--accent); font-weight:700; border-bottom-color:var(--accent); }
          .gd .qcard{ border:1px solid var(--line); border-radius:10px; padding:.5rem .8rem; background:var(--surface); max-width:30rem; display:flex; justify-content:space-between; align-items:center; gap:.5rem; flex-wrap:wrap; }
          .gd .qname{ font-weight:700; font-size:.84rem; color:var(--ink); }
          .gd .qbadge{ margin-left:.4rem; font-size:.6rem; padding:.08rem .38rem; border-radius:6px; background:var(--panel); color:var(--soft); font-weight:600; }
          .gd .qstate{ font-size:.7rem; color:var(--faint); }
          .gd .bLive, .gd .sNot{ display:none; }
          .gd .stage[data-step="7"] .bSand, .gd .stage[data-step="4"] .sUnset, .gd .stage[data-step="7"] .sUnset{ display:none; }
          .gd .stage[data-step="7"] .bLive, .gd .stage[data-step="4"] .sNot, .gd .stage[data-step="7"] .sNot{ display:inline; }
          .gd .dets{ border:1px solid var(--line); border-radius:10px; padding:.45rem .7rem; max-width:30rem; margin-top:.6rem; background:var(--surface); }
          .gd .dets .sum{ font-size:.74rem; font-weight:600; color:var(--soft); }
          .gd .dets .sum i{ font-style:normal; font-weight:400; color:var(--faint); }
          .gd .dets .fhint{ font-size:.64rem; color:var(--soft); margin:.4rem 0 .5rem; line-height:1.45; }
          .gd .dets .fld{ margin-bottom:.45rem; }
          .gd .dets .fld .box{ height:26px; font-size:.7rem; }
          .gd .dets .fld .selectbox{ font-size:.72rem; padding:.28rem .5rem; }
          .gd .dets .fld .val{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.66rem; }
          .gd .stage[data-step="4"] .dets .ph, .gd .stage[data-step="7"] .dets .ph{ opacity:0; }
          .gd .stage[data-step="4"] .dets .val, .gd .stage[data-step="7"] .dets .val{ opacity:1; }
          .gd .vProd{ display:none; }
          .gd .stage[data-step="7"] .vSand{ display:none; } .gd .stage[data-step="7"] .vProd{ display:inline; }
          .gd .stage[data-step="3"] .dets, .gd .stage[data-step="7"] .fEnv .selectbox{ box-shadow:0 0 0 3px var(--accent-wash), 0 0 0 5px var(--accent); }
          .gd .cok{ display:none; margin-bottom:.55rem; }
          .gd .stage[data-step="4"] .cok, .gd .stage[data-step="7"] .cok{ display:flex; }

          /* ---- accounting-health.php: a plain-text page ---- */
          .gd .hlth{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.64rem; line-height:1.6; color:var(--ink); background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:.6rem .75rem; white-space:pre; overflow-x:auto; max-width:34rem; }
          .gd .hlth .ok{ color:var(--good); font-weight:700; }
          .gd .hlth .hl{ background:var(--accent-wash); }
          .gd .h5, .gd .h8{ display:none; }
          .gd .stage[data-step="5"] .h5, .gd .stage[data-step="8"] .h8{ display:inline; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>developer.intuit.com &nbsp;&middot;&nbsp; www.yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a>
                <div class="navh">Platform</div>
                <a>Master admin</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- ============ SCENE A: Intuit&rsquo;s developer portal ============ -->
                <div class="osc scDev">
                  <div class="dev d0">
                    <div class="who">Intuit Developer &middot; their website</div>
                    <h4>Create an app &rarr; QuickBooks Online and Payments</h4>
                    <div class="row"><span class="k">App name</span>YourBlinds</div>
                    <div class="row"><span class="tk on"></span>Accounting <code>com.intuit.quickbooks.accounting</code></div>
                    <div class="row"><span class="tk"></span>Payments (leave unticked)</div>
                    <div class="gbtn">Create app</div>
                    <p class="note">One app for the whole platform &mdash; not one per client.</p>
                  </div>
                  <div class="dev d1">
                    <div class="who">Intuit Developer &middot; your app</div>
                    <div class="dnav"><span class="on">Development settings</span><span>Production settings</span></div>
                    <h4>Redirect URIs</h4>
                    <div class="row hl"><span class="k">Redirect URI</span><code>https://www.yourblinds.uk/admin/accounting/callback.php</code></div>
                    <div class="gbtn">Save</div>
                    <p class="note">Exactly this &mdash; https, <b>www</b>, no slash on the end.</p>
                  </div>
                  <div class="dev d2">
                    <div class="who">Intuit Developer &middot; your app</div>
                    <div class="dnav"><span class="on">Development settings</span><span>Production settings</span></div>
                    <h4>Keys and credentials</h4>
                    <div class="row hl"><span class="k">Client ID</span><code>ABxxxxxxxxxxxxxxxxxxxxxxxx</code></div>
                    <div class="row hl"><span class="k">Client Secret</span><code>&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull; (Show)</code></div>
                    <p class="note">Development = sandbox keys. Copy both &mdash; keep the secret private.</p>
                  </div>
                  <div class="dev d6">
                    <div class="who">Intuit Developer &middot; your app</div>
                    <div class="dnav"><span>Development settings</span><span class="on">Production settings</span></div>
                    <h4>Get production keys</h4>
                    <div class="row"><span class="tk on"></span>Profile &amp; app details</div>
                    <div class="row"><span class="tk on"></span>Host domain, launch &amp; disconnect URLs</div>
                    <div class="row"><span class="tk on"></span>End-user licence &amp; privacy policy links</div>
                    <div class="row"><span class="tk"></span>App assessment questionnaire</div>
                    <div class="row"><span class="tk"></span>Production redirect URI</div>
                    <p class="note">Keys unlock once Intuit accepts the questionnaire.</p>
                  </div>
                </div>

                <!-- ============ SCENE B: Settings → Accounting (super-admin) ============ -->
                <div class="osc scCred">
                  <div class="okbanner cok"><span>&check;</span> QuickBooks app credentials saved.</div>
                  <div class="tabstrip">
                    <span class="tabx">Company</span><span class="tabx">Quoting</span><span class="tabx">Legal</span>
                    <span class="tabx">Status colours</span><span class="tabx">Suppliers</span><span class="tabx on">Accounting</span>
                    <span class="tabx">Back up data</span>
                  </div>
                  <div class="qcard">
                    <div><span class="qname">QuickBooks Online</span><span class="qbadge bSand">Sandbox (test)</span><span class="qbadge bLive">Live</span></div>
                    <span class="qstate sUnset">Not set up</span><span class="qstate sNot">Not connected</span>
                  </div>
                  <div class="dets">
                    <div class="sum">&#9662; QuickBooks app credentials <i>(super-admin &mdash; sets up the connection for everyone)</i></div>
                    <div class="fhint">Paste the keys from your Intuit app (developer.intuit.com &rarr; your app &rarr; Keys &amp; credentials).</div>
                    <div class="fld fEnv"><label>Environment</label>
                      <span class="selectbox"><span class="vSand">Sandbox (test)</span><span class="vProd">Production (live)</span></span></div>
                    <div class="fld"><label>Client ID</label>
                      <div class="box"><span class="ph">ABxxx&hellip;</span><span class="val">AB&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span></div></div>
                    <div class="fld"><label>Client Secret</label>
                      <div class="box"><span class="ph">paste the secret</span><span class="val">&bull;&bull;&bull;&bull;&bull;&bull; (stored &mdash; leave blank to keep)</span></div></div>
                    <div class="fld"><label>Redirect URI <i style="font-style:normal;text-transform:none;font-weight:400">(must match the one registered at Intuit)</i></label>
                      <div class="box"><span class="ph">https://www.yourblinds.uk/admin/accounting/callback.php</span><span class="val">https://www.yourblinds.uk/admin/accounting/callback.php</span></div></div>
                    <div class="save">Save credentials</div>
                  </div>
                </div>

                <!-- ============ SCENE C: the health check page ============ -->
                <div class="osc scHlth">
<div class="hlth">=== Accounting integration health ===

platform_config table   : exists
APP_ENCRYPTION_KEY      : PRESENT

--- QuickBooks config (values never shown) ---
QUICKBOOKS_ENV          : <span class="h5">sandbox</span><span class="h8">production</span>
QUICKBOOKS_CLIENT_ID    : PRESENT
QUICKBOOKS_CLIENT_SECRET: PRESENT

--- Provider view ---
isConfigured()          : <span class="ok">YES &check;</span>
<span class="hl">redirectUri()           : https://www.yourblinds.uk/admin/accounting/callback.php</span>

connections table       : exists</div>
                </div>

                <div class="caps">
                  <b class="c0"><span class="n">0</span> developer.intuit.com &rarr; create one app, Accounting scope only.</b>
                  <b class="c1"><span class="n">1</span> Add the redirect URI &mdash; the www address, exactly.</b>
                  <b class="c2"><span class="n">2</span> Copy the Development (sandbox) Client ID and Secret.</b>
                  <b class="c3"><span class="n">3</span> YourBlinds: Settings &rarr; Accounting &rarr; QuickBooks app credentials.</b>
                  <b class="c4 good"><span class="n">4</span> Sandbox, paste both keys, Save &mdash; card now says Not connected.</b>
                  <b class="c5 good"><span class="n">5</span> Check it: /master-admin/accounting-health.php &mdash; isConfigured YES.</b>
                  <b class="c6"><span class="n">6</span> Going live: Production settings &rarr; questionnaire &rarr; production keys.</b>
                  <b class="c7 good"><span class="n">7</span> Switch to Production (live), paste the NEW keys, Save &mdash; badge says Live.</b>
                  <b class="c8 good"><span class="n">8</span> Health page shows production. Clients can now connect real companies.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <div class="heads"><span class="hi">&#9888;</span><div><b>Two halves &mdash; this is the platform half.</b> This guide is the
             <b>one-off setup you do as the owner of YourBlinds</b>. Once it&rsquo;s done, each client links their <em>own</em> QuickBooks
             company from their Settings &rarr; Accounting tab &mdash; that&rsquo;s the guide <b>&ldquo;Link your accounts package (QuickBooks
             Online)&rdquo;</b>. Remember too that sending paid sales to QuickBooks is <b>not switched on yet</b>: this setup gets the
             connection working, ready for when it is.</div></div>

          <p><b>What you&rsquo;re setting up, in plain words.</b> QuickBooks won&rsquo;t talk to just any website. YourBlinds has to be
             registered with Intuit (the company behind QuickBooks) as an <b>app</b>. Intuit then gives YourBlinds two keys &mdash; a
             <b>Client ID</b> (like a username) and a <b>Client Secret</b> (like a password). When a client presses <b>Connect to
             QuickBooks</b>, YourBlinds shows Intuit those keys, the client signs in on Intuit&rsquo;s own site and approves, and Intuit sends
             them back to YourBlinds at an agreed address &mdash; the <b>redirect URI</b>. <b>One app serves every client.</b> You never
             register a separate app per client.</p>

          <p><b>Two sets of keys: sandbox and production.</b> Every Intuit app has <b>Development</b> keys, which only work with Intuit&rsquo;s
             <b>sandbox</b> test companies, and <b>Production</b> keys, which work with real companies. Development keys are available
             straight away. Production keys unlock only after you&rsquo;ve filled in Intuit&rsquo;s details and <b>app assessment
             questionnaire</b> and they&rsquo;ve accepted it. YourBlinds has one <b>Environment</b> switch, so the whole platform is on one or the
             other.</p>

          <p><b>Part 1 &mdash; register the app with Intuit (about 15 minutes).</b></p>
          <ul class="steps">
            <li>Go to <code>developer.intuit.com</code> and sign in or create a free developer account. Use a business email that
                you&rsquo;ll keep; Intuit sends app notices there.</li>
            <li>Create an app and choose <b>QuickBooks Online and Payments</b>. Name it <b>YourBlinds</b> (clients see this name when they
                approve the link).</li>
            <li>When it asks for <b>scopes</b> (what the app may do), tick <b>Accounting</b> only. That&rsquo;s
                <code>com.intuit.quickbooks.accounting</code>, the single scope YourBlinds asks for. Leave <b>Payments</b> unticked:
                YourBlinds doesn&rsquo;t take card payments through QuickBooks, and asking for more than you need makes Intuit&rsquo;s review
                longer.</li>
            <li>Under <b>Development settings &rarr; Redirect URIs</b>, add exactly:<br>
                <code>https://www.yourblinds.uk/admin/accounting/callback.php</code><br>
                It must match <b>character for character</b>: https, <b>with</b> the <code>www</code>, and no slash on the end. The
                <code>www</code> matters. YourBlinds answers on both <code>yourblinds.uk</code> and <code>www.yourblinds.uk</code>, and they
                keep separate logins. If the link starts on one and comes back on the other, clients get a &ldquo;state mismatch&rdquo;
                error.</li>
            <li>Open <b>Development settings &rarr; Keys and credentials</b> and copy the <b>Client ID</b> and <b>Client Secret</b>.
                Treat the secret like a password. Don&rsquo;t email it, put it in a document or paste it into a chat. The code for
                YourBlinds is on a <b>public</b> GitHub page, so keys must <b>never</b> go into the code or any file in it.</li>
            <li>Optional but useful: under <b>Sandbox</b>, add a test company and choose <b>United Kingdom</b>. The default sandbox company is
                American (US sales tax, dollars). A UK one shows real VAT codes like <code>20.0% S</code> on the mapping page, so testing looks
                like what clients will see.</li>
          </ul>

          <p><b>Part 2 &mdash; put the keys into YourBlinds.</b></p>
          <ul class="steps">
            <li>Logged in as a super-admin, go to <b>Setup &rarr; Settings &rarr; Accounting</b>. Under the QuickBooks Online card
                there&rsquo;s a fold-out box, <b>QuickBooks app credentials</b> (super-admin &mdash; sets up the connection for everyone).
                Ordinary admins never see it. It opens by itself while QuickBooks isn&rsquo;t set up yet.</li>
            <li><b>Environment</b>: choose <b>Sandbox (test)</b>.</li>
            <li><b>Client ID</b>: paste it. <b>Client Secret</b>: paste it. The secret box always shows empty after saving, for safety; it
                then says <em>&ldquo;stored &mdash; leave blank to keep&rdquo;</em>. Leaving it blank on a later save keeps the stored secret.
                Typing in it replaces the secret.</li>
            <li><b>Redirect URI</b>: it&rsquo;s already filled in with the address YourBlinds will use. Check it&rsquo;s exactly the one you
                registered at Intuit.</li>
            <li>Press <b>Save credentials</b>. The green bar reads <b>&ldquo;QuickBooks app credentials saved.&rdquo;</b> The card badge says
                <b>Sandbox (test)</b>, and the card changes from <b>Not set up</b> to <b>Not connected</b>, with a <b>Connect to
                QuickBooks</b> button.</li>
            <li>If instead you get <em>&ldquo;Could not save &hellip; have you run migrate_platform_config.php?&rdquo;</em>, the settings
                table is missing. Visit <code>/migrate_platform_config.php</code> once as super-admin, then save again. It has already been
                run on the live site.</li>
          </ul>
          <p>Where the keys live: they&rsquo;re stored in the <b>database</b> (a small <code>platform_config</code> table), not in a file.
             That&rsquo;s deliberate, because editing the server&rsquo;s <code>.env</code> file on Cloudways proved painful. If a key is
             set in both places, the one saved here wins. The key used to encrypt each client&rsquo;s QuickBooks login is made automatically
             the first time anyone connects.</p>

          <p><b>Part 3 &mdash; check it works.</b></p>
          <ul class="steps">
            <li>Open <code>/master-admin/accounting-health.php</code> (super-admin only; it only reads, and is safe to open any time). It
                <b>never shows key values</b>, only <b>PRESENT</b> or <b>MISSING</b>. Look for:
                <b>QUICKBOOKS_CLIENT_ID: PRESENT</b>, <b>QUICKBOOKS_CLIENT_SECRET: PRESENT</b>, <b>isConfigured(): YES</b>. The
                <b>redirectUri()</b> line must match Intuit exactly. Both tables should say <b>exists</b>.</li>
            <li>Then test for real: on your own account, press <b>Connect to QuickBooks</b> while browsing the <b>www</b> address, sign in
                with your <b>Intuit developer</b> login, pick the sandbox company and approve. You should land back on the Accounting tab with
                <b>&#9679; Connected</b> and the company name. Press <b>Set up mapping</b> and check the lists fill up from the sandbox
                company.</li>
          </ul>

          <p><b>Part 4 &mdash; going live (production keys).</b> Do this once you&rsquo;re ready for real clients&rsquo; companies. Allow
             <b>a week or two</b> in case Intuit asks questions.</p>
          <ul class="steps">
            <li>At Intuit, open your app&rsquo;s <b>Production settings</b> and work through <b>Get production keys</b>. It asks for your
                developer profile and app details, and for:
                <ul>
                  <li><b>Host domain</b>: <code>www.yourblinds.uk</code>.</li>
                  <li><b>Launch URL</b>, where a user starts from: <code>https://www.yourblinds.uk/admin/settings.php#accounting</code>.</li>
                  <li><b>Disconnect URL</b>, where they land after unlinking: the same page.</li>
                  <li><b>End-user licence agreement</b> and <b>privacy policy</b> links. These must be YourBlinds&rsquo; <em>own</em> public
                      pages, as the software company. Each client&rsquo;s own terms pages won&rsquo;t do. <b>YourBlinds doesn&rsquo;t have
                      platform-wide EULA and privacy pages yet, so they need writing before you apply.</b></li>
                  <li><b>Category</b>: accounting / invoicing.</li>
                  <li><b>Regulated industries</b>: none.</li>
                </ul></li>
            <li>Fill in the <b>app assessment questionnaire</b> (Production settings &rarr; App assessment questionnaire). Every app that
                connects to real companies must do it, even a private one that isn&rsquo;t listed in Intuit&rsquo;s app store. Allow
                20&ndash;30 minutes. It covers how you protect data. Honest answers for YourBlinds: logins go through Intuit and we never see
                QuickBooks passwords; tokens are stored <b>encrypted</b> (AES-256); everything runs over HTTPS; a client can disconnect any
                time, and disconnecting revokes access at Intuit.</li>
            <li>Under <b>Production settings &rarr; Redirect URIs</b>, add the <b>same</b> address again. Production has its own list.</li>
            <li>Once Intuit accepts, <b>Production settings &rarr; Keys and credentials</b> shows a <b>new</b> Client ID and Secret. They are
                different from the sandbox ones.</li>
            <li><b>Before switching YourBlinds over, disconnect any sandbox links</b> (your own test account, at least). A client still
                linked to a sandbox company would otherwise show as connected but fail when it talks to live QuickBooks.</li>
            <li>In YourBlinds, open <b>QuickBooks app credentials</b> again. Set <b>Environment</b> to <b>Production (live)</b>, paste the
                <b>production</b> Client ID, type the <b>production</b> Secret into the secret box (don&rsquo;t leave it blank, or the sandbox
                secret stays), and press <b>Save credentials</b>. The card badge now reads <b>Live</b>.</li>
            <li>Re-check <code>/master-admin/accounting-health.php</code> (<b>QUICKBOOKS_ENV: production</b>), then connect a real company
                (yours first) to prove it end to end.</li>
          </ul>

          <p><b>Costs.</b> Intuit runs an <b>App Partner Program</b>. Its free <b>Builder</b> tier is enough for YourBlinds as it stands:
             creating sales in QuickBooks counts as &ldquo;Core&rdquo; use, which is free and unlimited. Reading data (the lists on the mapping
             page) comes out of a monthly allowance that&rsquo;s far more than YourBlinds uses. Check the current terms on Intuit&rsquo;s site
             when you apply.</p>

          <p><b>Looking after it afterwards.</b></p>
          <ul class="steps">
            <li><b>Changing the secret.</b> If the secret has ever been exposed, generate a new one at Intuit (Keys and credentials) and paste
                it into YourBlinds straight away. The old one stops working, and existing client links carry on.</li>
            <li><b>Clients who go quiet.</b> Each client&rsquo;s link renews itself whenever it&rsquo;s used. If it goes unused for about
                <b>100 days</b>, it lapses and that client just presses Connect again.</li>
            <li><b>Never</b> put keys, secrets or a company&rsquo;s ID number (&ldquo;realm ID&rdquo;) into the code, a help page or a
                GitHub issue. The code for YourBlinds is on a public GitHub page.</li>
          </ul>

          <p><b>Xero, Sage and FreeAgent &mdash; not built yet.</b> On the Accounting tab they show as <b>Coming soon</b>, and YourBlinds has
             no connection code for them yet, so there is <b>nothing to paste in</b> today. When one is built, the platform setup will follow
             the same pattern: one developer app, a Client ID and Secret, and a registered redirect address. This guide will be updated with
             the exact settings at that point. Worth knowing in advance:</p>
          <ul class="steps">
            <li><b>Xero</b>: register at <code>developer.xero.com</code> &rarr; My Apps &rarr; new <b>web app</b>. Apps created since
                <b>2 March 2026</b> must use Xero&rsquo;s newer <b>granular scopes</b> (for example invoices only, rather than
                &ldquo;all transactions&rdquo;). Xero also now <b>charges developers by number of connected organisations</b>. The free
                <b>Starter</b> tier covers only a handful of connections, with paid tiers above it, so budget for that before offering Xero to
                every client.</li>
            <li><b>Sage Accounting</b>: register through Sage&rsquo;s <b>Developer Self Service</b> portal (<code>developer.sage.com</code>),
                giving an app name, contact email, homepage and callback (redirect) URL, to get a Client ID and Secret.</li>
            <li><b>FreeAgent</b>: register at <code>dev.freeagent.com</code>. It has its own sandbox for testing.</li>
          </ul>
          <p>Until those exist, clients on Xero or Sage use the CSV exports on the Payments page. The guide <b>&ldquo;Get your figures into
             Xero, QuickBooks or Sage&rdquo;</b> covers it.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Intuit developer portal: create an app, QuickBooks Online and Payments, Accounting scope ticked.', 'This is the one-off setup behind every client\'s Connect to QuickBooks button. Start at developer dot intuit dot com. Create an app for QuickBooks Online, call it YourBlinds, and tick the Accounting scope only. One app serves every client.', 0],
            ['0:15', 'Development settings → Redirect URIs: the www callback address.',                              'Next, add the redirect address: https, www dot yourblinds dot uk, slash admin, slash accounting, slash callback dot p h p. It must match exactly, with the www and nothing on the end.', 1],
            ['0:29', 'Keys and credentials: Client ID and Client Secret.',                                          'Then open Keys and credentials and copy the Client ID and the Client Secret. These are the sandbox keys. Treat the secret like a password, and never put it into the code.', 2],
            ['0:41', 'YourBlinds: Settings → Accounting → QuickBooks app credentials box.',                          'Now in YourBlinds, as a super-admin, go to Settings, the Accounting tab, and open the box called QuickBooks app credentials. Only super-admins can see it.', 3],
            ['0:51', 'Environment Sandbox; keys pasted; Save credentials; green bar; card "Not connected".',          'Set Environment to Sandbox, paste the Client ID and the Secret, check the redirect address matches, and press Save credentials. The card changes from Not set up to Not connected, with a Connect button.', 4],
            ['1:05', 'accounting-health.php: CLIENT_ID PRESENT, SECRET PRESENT, isConfigured YES, redirectUri.',       'To check it, open the accounting health page in master admin. It never shows the keys, just present or missing. You want isConfigured: yes, and a redirect address that matches Intuit\'s. Then connect a sandbox company yourself to prove it.', 5],
            ['1:20', 'Intuit: Production settings → Get production keys checklist.',                                'When you\'re ready for real companies, go back to Intuit and open Production settings. Fill in the app details and links, answer the app assessment questionnaire, and add the same redirect address again. Allow a week or two.', 6],
            ['1:35', 'YourBlinds: Environment Production (live), new keys, Save; badge "Live".',                    'Once Intuit approves, you get new production keys. Disconnect any sandbox links first, then switch Environment to Production, paste the new ID, type the new secret, and save. The badge now says Live.', 7],
            ['1:49', 'Health page: QUICKBOOKS_ENV production, isConfigured YES.',                                   'Check the health page shows production, then connect your own real company first. After that, every client can link their QuickBooks. Xero and Sage aren\'t built yet; this guide will cover them when they are.', 8],
        ],
];
