<?php
declare(strict_types=1);

/**
 * Guide: platform-accounting-apps — "Set up the accounting app keys (platform owner)" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * SUPER-ADMIN ONLY. The one-off, platform-owner half of the accounting link:
 * registering YourBlinds as an app with Intuit and putting its keys into
 * YourBlinds, so every tenant then gets a working "Connect to QuickBooks"
 * button (the tenant half is the settings-accounting guide).
 *
 * Sources: _partials/quickbooks.php (SCOPE com.intuit.quickbooks.accounting,
 * redirectUri(): QUICKBOOKS_REDIRECT_URI, else APP_URL + /admin/accounting/callback.php;
 * environment() sandbox|production; isConfigured() = ID + secret present),
 * _partials/accounting.php (pc_get: the platform_config DB value wins over .env),
 * admin/settings.php (accounting_platform action + the <details> form:
 * "QuickBooks app credentials (super-admin — sets up the connection for
 * everyone)", Environment select Sandbox (test) / Production (live), Client ID,
 * Client Secret — blank keeps the stored one —, Redirect URI, "Save
 * credentials", flash "QuickBooks app credentials saved.", "Currently
 * configured ✓"; card badge "Sandbox (test)" / "Live"; "Not set up" /
 * "Not connected"), master-admin/accounting-health.php (plain-text report).
 * Intuit's developer portal is Intuit's, drawn plainly and in general terms.
 *
 * PUBLIC REPO: never put a real Client ID, secret or realm ID in here — the
 * values shown are made-up placeholders.
 *
 * KEEP TRUE: pushing paid sales to QuickBooks is NOT built (on hold), and
 * Xero / Sage have no provider code — there is nothing to paste in for them.
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

$tabs = '<div class="sttabs"><span class="sttab">Company</span><span class="sttab">Quoting</span><span class="sttab">Legal</span>'
      . '<span class="sttab">Status colours</span><span class="sttab">Suppliers</span><span class="sttab on">Accounting</span><span class="sttab">Back up data</span></div>';

$card = static fn (string $badge, string $state): string =>
    '<div class="qcard"><div class="qtop"><div><span class="qname">QuickBooks Online</span><span class="qbadge">' . $badge . '</span></div>'
  . '<span class="qstate">' . $state . '</span></div></div>';

$sw = static fn (string $old, string $new, float $d): string =>
    '<span class="sw"><span class="a-out" style="--d:' . $d . 's">' . $old . '</span><span class="a-fade" style="--d:' . ($d + .1) . 's">' . $new . '</span></span>';

$redirect = 'https://www.yourblinds.uk/admin/accounting/callback.php';

// The super-admin credentials form. $v = [env, id, secret, redirect] inner HTML; $save = extra class on Save.
$form = static fn (array $v, string $save = '', string $saveStyle = ''): string =>
    '<div class="det"><div class="sum">&#9662; QuickBooks app credentials <span>(super-admin &mdash; sets up the connection for everyone)</span></div>'
  . '<p class="dh">Paste the keys from your Intuit app (developer.intuit.com &rarr; your app &rarr; Keys &amp; credentials &rarr; Development). One app serves every tenant; each account then connects their own company with the button above.</p>'
  . '<div class="ff"><label>Environment</label><span class="selectbox sb">' . $v[0] . '</span></div>'
  . '<div class="ff"><label>Client ID</label><span class="ib">' . $v[1] . '</span></div>'
  . '<div class="ff"><label>Client Secret</label><span class="ib">' . $v[2] . '</span></div>'
  . '<div class="ff"><label>Redirect URI <span>(must match the one registered at Intuit)</span></label><span class="ib">' . $v[3] . '</span></div>'
  . '<span class="btnp ' . $save . '" style="' . $saveStyle . '">Save credentials</span></div>';

return [
        'aud'     => 'super',
        'section' => 'Settings',
        'title'   => 'Set up the accounting app keys (platform owner)',
        'eyebrow' => 'Settings · Accounting (platform)',
        'v'       => 2,
        'blurb'   => 'The one-off job behind every client\'s "Connect to QuickBooks" button: register YourBlinds with Intuit, paste its keys into YourBlinds, check it, then get approved for live. Xero and Sage are not built yet.',
        'lede'    => 'Before any client can press <b>Connect to QuickBooks</b>, YourBlinds itself has to be registered with Intuit as an
                      <b>app</b>. You do that <b>once, for the whole platform</b>: Intuit gives you a <b>Client ID</b> and <b>Client Secret</b>,
                      you paste them into YourBlinds, and every account can then link its own QuickBooks company. You start on Intuit&rsquo;s
                      <b>sandbox</b> (test companies) and move to <b>production</b> (real companies) once Intuit has approved the app.
                      Remember: sending paid sales into QuickBooks is <b>not built yet</b> &mdash; this only gets the connection working.
                      Only super-admins see this guide and the keys form.',
        'open'    => '/admin/settings.php#accounting',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .sttabs{ display:flex; flex-wrap:wrap; gap:.1rem .2rem; border-bottom:1px solid var(--line); margin:0 0 .6rem; }
          .gd .sttab{ font-size:.66rem; font-weight:600; color:var(--faint); padding:.28rem .45rem; border-bottom:2px solid transparent; white-space:nowrap; }
          .gd .sttab.on{ color:var(--accent); border-bottom-color:var(--accent); }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.72rem; font-weight:700; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.7rem; }
          .gd .bnr{ display:flex; align-items:center; gap:.5rem; background:var(--good-wash); border-left:3px solid var(--good);
                    border-radius:8px; padding:.4rem .65rem; font-size:.72rem; font-weight:700; color:var(--ink); margin:0 0 .5rem; max-width:31rem; }
          .gd .sw{ display:inline-grid; } .gd .sw > span{ grid-area:1/1; }
          .gd code.u{ font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.66rem; background:var(--panel); border:1px solid var(--line);
                      border-radius:6px; padding:.25rem .45rem; color:var(--ink); word-break:break-all; display:inline-block; }

          /* card */
          .gd .qcard{ border:1px solid var(--border-strong,#c7ccd4); border-radius:10px; padding:.5rem .75rem; background:var(--surface); max-width:31rem; margin-bottom:.5rem; }
          .gd .qtop{ display:flex; align-items:center; justify-content:space-between; gap:.6rem; flex-wrap:wrap; }
          .gd .qname{ font-weight:800; font-size:.82rem; color:var(--ink); }
          .gd .qbadge{ margin-left:.4rem; font-size:.6rem; padding:.08rem .38rem; border-radius:6px; background:var(--panel); color:var(--soft); font-weight:600; }
          .gd .qstate{ font-size:.68rem; color:var(--faint); }

          /* 1 — one app, many clients */
          .gd .hub{ display:grid; grid-template-columns:1fr auto 1fr; gap:.6rem; align-items:center; max-width:31rem; margin:.8rem 0; }
          .gd .node{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--surface); font-size:.74rem; font-weight:700; color:var(--ink); text-align:center; }
          .gd .node small{ display:block; font-weight:500; color:var(--faint); font-size:.6rem; }
          .gd .keys{ display:flex; flex-direction:column; gap:.3rem; align-items:center; }
          .gd .clients{ display:flex; flex-direction:column; gap:.3rem; }
          .gd .clients span{ border:1px solid var(--line); border-radius:7px; padding:.25rem .5rem; font-size:.66rem; color:var(--soft); background:var(--panel); }

          /* Intuit (their site) */
          .gd .dev{ border:1px solid var(--line); border-radius:12px; background:var(--surface); max-width:31rem; padding:.65rem .8rem; box-shadow:var(--gd-shadow); }
          .gd .dev .who{ font-size:.58rem; text-transform:uppercase; letter-spacing:.06em; color:var(--faint); font-weight:700; }
          .gd .dev h4{ margin:.25rem 0 .5rem; font-size:.86rem; color:var(--ink); }
          .gd .dev .row{ display:flex; align-items:center; gap:.5rem; font-size:.72rem; color:var(--ink); margin:.3rem 0; flex-wrap:wrap; }
          .gd .dev .row b{ min-width:6.5rem; font-size:.64rem; color:var(--soft); }
          .gd .ck{ display:inline-flex; align-items:center; gap:.35rem; font-size:.72rem; color:var(--ink); margin-right:.9rem; }
          .gd .ck .tick{ width:15px; height:15px; }
          .gd .secret{ font-family:ui-monospace,Menlo,monospace; font-size:.68rem; background:var(--panel); border:1px solid var(--line); border-radius:6px; padding:.2rem .45rem; }

          /* the credentials form */
          .gd .det{ border:1px solid var(--line); border-radius:10px; padding:.5rem .7rem; max-width:31rem; background:var(--surface); }
          .gd .det .sum{ font-weight:700; font-size:.74rem; color:var(--soft); }
          .gd .det .sum span{ font-weight:400; color:var(--faint); font-size:.64rem; }
          .gd .dh{ font-size:.6rem; color:var(--soft); margin:.35rem 0 .45rem; line-height:1.4; }
          .gd .ff{ margin:0 0 .35rem; }
          .gd .ff label{ display:block; font-size:.64rem; font-weight:700; color:var(--ink); margin-bottom:.15rem; }
          .gd .ff label span{ font-weight:400; color:var(--faint); }
          .gd .ib{ display:flex; align-items:center; height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface);
                   padding:0 .45rem; font-size:.66rem; color:var(--ink); overflow:hidden; white-space:nowrap; font-family:ui-monospace,Menlo,monospace; }
          .gd .sb{ min-width:0; width:100%; box-sizing:border-box; font-size:.68rem; padding:.22rem .45rem; }
          .gd .ph{ color:var(--faint); }

          /* health page (plain text) */
          .gd .term{ background:#0f172a; color:#e2e8f0; border-radius:10px; padding:.6rem .75rem; font-family:ui-monospace,Menlo,Consolas,monospace;
                     font-size:.64rem; line-height:1.55; max-width:31rem; white-space:pre-wrap; word-break:break-all; }
          .gd .term .g{ color:#4ade80; font-weight:700; } .gd .term .d{ color:#94a3b8; }

          /* 8 — production checklist */
          .gd .list{ display:grid; gap:.35rem; max-width:31rem; }
          .gd .list div{ display:flex; gap:.5rem; align-items:center; border:1px solid var(--line); border-radius:8px; padding:.35rem .55rem; font-size:.7rem; color:var(--ink); background:var(--surface); }

          @media (max-width:640px){
            .gd .sc{ min-height:440px; }
            .gd .hub{ grid-template-columns:1fr; }
            .gd .sttab{ font-size:.6rem; padding:.22rem .3rem; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings / accounting</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a>
                <div class="navh">Platform <span class="chev">&#9662;</span></div>
                <a>Overview</a><a>Monitor</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $tabs . '
                  ' . $card('Sandbox (test)', 'Not set up') . '
                  ' . $form(['Sandbox (test)', '<span class="ph">ABxxx&hellip;</span>', '<span class="ph">paste the secret</span>', $redirect]) . '
                  <p class="scs" style="margin-top:.7rem">Press <b>&#9654; Play</b> below &mdash; ten short chapters. Super-admins only.</p>
                </div>

                <!-- 1 — what this is -->
                <div class="sc" data-scene="1" data-len="19">
                  <div class="sct a-fade" style="--d:.2s">One app, for the whole platform</div>
                  <div class="hub">
                    <div class="node a-pop" style="--d:3s">Intuit<small>registers YourBlinds as an app</small></div>
                    <div class="keys"><span class="chip a-pop" style="--d:6s">&#128273; Client ID</span><span class="chip a-pop" style="--d:6.6s">&#128274; Client Secret</span></div>
                    <div class="node a-pop" style="--d:8s">YourBlinds<small>keys pasted in once</small></div>
                  </div>
                  <div class="clients">
                    <span class="a-fly" style="--d:11s">Client A &rarr; Connect to QuickBooks</span>
                    <span class="a-fly" style="--d:11.6s">Client B &rarr; Connect to QuickBooks</span>
                    <span class="a-fly" style="--d:12.2s">Client C &rarr; Connect to QuickBooks</span>
                  </div>
                  <div class="chips"><span class="chip ok a-pop" style="--d:14.5s">Never one app per client</span></div>
                </div>

                <!-- 2 — create the app -->
                <div class="sc" data-scene="2" data-len="15">
                  <div class="sct a-fade" style="--d:.2s">At Intuit: create the app</div>
                  <div class="dev a-rise" style="--d:1s">
                    <div class="who">developer.intuit.com &middot; Intuit&rsquo;s site</div>
                    <h4>Create an app</h4>
                    <div class="row"><b>Platform</b><span class="a-type" style="--d:4s;--ts:30;--tt:1s">QuickBooks Online and Payments</span></div>
                    <div class="row"><b>App name</b><span class="a-type" style="--d:6.5s;--ts:10;--tt:.6s">YourBlinds</span></div>
                    <div class="row"><b>Scopes</b>
                      <span class="ck"><span class="tick a-sel" style="--d:12.5s">&#10003;</span>Accounting</span>
                      <span class="ck"><span class="tick">&#10003;</span>Payments</span></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:7.5s">Clients see this name when they approve</span>
                    <span class="chip ok a-pop" style="--d:13s">Accounting only &mdash; com.intuit.quickbooks.accounting</span>
                  </div>
                </div>

                <!-- 3 — redirect URI -->
                <div class="sc" data-scene="3" data-len="18">
                  <div class="sct a-fade" style="--d:.2s">The redirect address &mdash; character for character</div>
                  <div class="dev a-rise" style="--d:1s">
                    <div class="who">developer.intuit.com &middot; Intuit&rsquo;s site</div>
                    <h4>Development settings &rarr; Redirect URIs</h4>
                    <code class="u"><span class="a-type" style="--d:3s;--ts:52;--tt:3.5s">' . $redirect . '</span></code>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:8s">https</span>
                    <span class="chip ok a-pop" style="--d:9.5s">with the <b>www</b></span>
                    <span class="chip a-pop" style="--d:11s">no slash on the end</span>
                    <span class="chip bad a-pop" style="--d:15s">Mismatch &rarr; &ldquo;state mismatch&rdquo; for clients</span>
                  </div>
                </div>

                <!-- 4 — copy the keys -->
                <div class="sc" data-scene="4" data-len="18">
                  <div class="sct a-fade" style="--d:.2s">Copy the keys &mdash; and keep them out of the code</div>
                  <div class="dev a-rise" style="--d:1s">
                    <div class="who">developer.intuit.com &middot; Intuit&rsquo;s site</div>
                    <h4>Keys and credentials (Development)</h4>
                    <div class="row"><b>Client ID</b><span class="secret a-fade" style="--d:3s">ABcD3fGh1jK&hellip; (example)</span></div>
                    <div class="row"><b>Client Secret</b><span class="secret a-fade" style="--d:5s">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:7s">Development keys = sandbox only</span>
                    <span class="chip bad a-pop" style="--d:11s">&#10007; Not in an email, document or chat</span>
                    <span class="chip bad a-pop" style="--d:15s">&#10007; Never in the code &mdash; the repo is public</span>
                  </div>
                </div>

                <!-- 5 — the fold-out box -->
                <div class="sc" data-scene="5" data-len="16">
                  <div class="a-fade" style="--d:.2s">' . $tabs . '</div>
                  <div class="a-fade" style="--d:1.5s">' . $card('Sandbox (test)', 'Not set up') . '</div>
                  <div class="a-rise" style="--d:5s">' . $form(['Sandbox (test)', '<span class="ph">ABxxx&hellip;</span>', '<span class="ph">paste the secret</span>', $redirect]) . '</div>
                  <div class="chips"><span class="chip a-pop" style="--d:9s">Super-admins only &mdash; ordinary admins never see it</span>
                    <span class="chip ok a-pop" style="--d:14s">Opens by itself until QuickBooks is set up</span></div>
                </div>

                <!-- 6 — fill it in and save -->
                <div class="sc" data-scene="6" data-len="19">
                  <div class="bnr a-drop" style="--d:16s">&#10003; QuickBooks app credentials saved.</div>
                  <div class="a-fade" style="--d:.2s">' . $card('Sandbox (test)', $sw('Not set up', 'Not connected', 17.5)) . '</div>
                  ' . $form([
                        '<span class="a-ring" style="--d:2s;border-radius:4px">Sandbox (test)</span>',
                        $sw('<span class="ph">ABxxx&hellip;</span>', '<span class="a-type" style="--d:5.1s;--ts:14;--tt:.6s">ABcD3fGh1jK&hellip;</span>', 5),
                        $sw('<span class="ph">paste the secret</span>', '<span class="a-type" style="--d:7.1s;--ts:12;--tt:.6s">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>', 7),
                        '<span class="a-ring" style="--d:10s;border-radius:4px">' . $redirect . '</span>',
                    ], 'a-press', '--d:14s') . '
                </div>

                <!-- 7 — check it -->
                <div class="sc" data-scene="7" data-len="19">
                  <div class="sct a-fade" style="--d:.2s">Check it: the accounting health page</div>
                  <p class="scs a-fade" style="--d:.8s"><code>/master-admin/accounting-health.php</code> &mdash; read-only, safe any time.</p>
                  <div class="term a-rise" style="--d:2s"><span class="d">--- QuickBooks config (DB first, then .env; values never shown) ---</span>
QUICKBOOKS_ENV          : sandbox
QUICKBOOKS_CLIENT_ID    : <span class="g a-pop" style="--d:6s">PRESENT</span>
QUICKBOOKS_CLIENT_SECRET: <span class="g a-pop" style="--d:6.6s">PRESENT</span>
<span class="d">--- Provider view ---</span>
isConfigured()          : <span class="g a-pop" style="--d:9.5s">YES &#10003;</span>
redirectUri()           : <span class="a-ring" style="--d:12s">' . $redirect . '</span></div>
                  <div class="chips"><span class="chip a-pop" style="--d:4s">Never shows the keys &mdash; PRESENT or MISSING</span>
                    <span class="chip ok a-pop" style="--d:16s">Then connect a sandbox company yourself</span></div>
                </div>

                <!-- 8 — production at Intuit -->
                <div class="sc" data-scene="8" data-len="17">
                  <div class="sct a-fade" style="--d:.2s">Going live: Intuit&rsquo;s production settings</div>
                  <div class="list">
                    <div class="a-fly" style="--d:3s">&#128221; App details &amp; links &mdash; host domain, launch and disconnect URLs, licence, privacy</div>
                    <div class="a-fly" style="--d:7s">&#9989; App assessment questionnaire</div>
                    <div class="a-fly" style="--d:10s">&#8634; The same redirect address again &mdash; production has its own list</div>
                    <div class="a-fly" style="--d:13s">&#128273; New production Client ID and Secret</div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:16s">&#9203; Allow a week or two</span></div>
                </div>

                <!-- 9 — switch YourBlinds to live -->
                <div class="sc" data-scene="9" data-len="18">
                  <div class="chips" style="margin:0 0 .5rem"><span class="chip bad a-pop" style="--d:2s">1 &middot; Disconnect sandbox links first</span></div>
                  <div class="a-fade" style="--d:.2s">' . $card($sw('Sandbox (test)', 'Live', 16.5), 'Not connected') . '</div>
                  ' . $form([
                        $sw('Sandbox (test)', 'Production (live)', 7),
                        $sw('ABcD3fGh1jK&hellip;', '<span class="a-type" style="--d:9.6s;--ts:14;--tt:.6s">PrQ9sT2uVw&hellip;</span>', 9.5),
                        $sw('<span class="ph">&bull;&bull;&bull;&bull;&bull;&bull; (stored &mdash; leave blank to keep)</span>', '<span class="a-type" style="--d:12.1s;--ts:12;--tt:.6s">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>', 12),
                        $redirect,
                    ], 'a-press', '--d:15s') . '
                </div>

                <!-- 10 — what it is not, yet -->
                <div class="sc" data-scene="10" data-len="15">
                  <div class="sct a-fade" style="--d:.2s">What this does &mdash; and doesn&rsquo;t &mdash; yet</div>
                  ' . $card('Live', 'Not connected') . '
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:2s">&#10003; Every client can connect and map</span>
                    <span class="chip bad a-pop" style="--d:6s">&#10007; Sending paid sales &mdash; not built yet</span>
                    <span class="chip bad a-pop" style="--d:11s">&#10007; Xero &amp; Sage &mdash; nothing to paste in yet</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <div class="heads"><span class="hi">&#9888;</span><div><b>Two halves &mdash; this is the platform half.</b> This guide is the
             <b>one-off setup you do as the owner of YourBlinds</b>. Once it&rsquo;s done, each client links their <em>own</em> QuickBooks
             company from their Settings &rarr; Accounting tab &mdash; that&rsquo;s the guide <b>&ldquo;Link your accounts package (QuickBooks
             Online)&rdquo;</b>. Sending paid sales to QuickBooks is <b>not built yet</b>: this setup gets the connection working, ready for
             when it is.</div></div>

          <p><b>What you&rsquo;re setting up, in plain words.</b> QuickBooks won&rsquo;t talk to just any website. YourBlinds has to be
             registered with Intuit (the company behind QuickBooks) as an <b>app</b>. Intuit then gives YourBlinds two keys &mdash; a
             <b>Client ID</b> (like a username) and a <b>Client Secret</b> (like a password). When a client presses <b>Connect to
             QuickBooks</b>, the client signs in on Intuit&rsquo;s own site and approves, and Intuit sends them back to YourBlinds at an
             agreed address &mdash; the <b>redirect URI</b>. <b>One app serves every client.</b></p>

          <p><b>Sandbox and production.</b> Every Intuit app has <b>Development</b> keys, which only work with Intuit&rsquo;s <b>sandbox</b>
             test companies, and <b>Production</b> keys, which work with real companies and unlock only once Intuit has accepted your app.
             YourBlinds has one <b>Environment</b> switch, so the whole platform is on one or the other.</p>

          <p><b>Part 1 &mdash; register the app with Intuit.</b> (Intuit&rsquo;s screens change from time to time; the names below are a
             guide.)</p>
          <ul class="steps">
            <li>Go to <code>developer.intuit.com</code> and sign in or create a free developer account, with a business email you&rsquo;ll keep.</li>
            <li>Create an app for <b>QuickBooks Online</b> and name it <b>YourBlinds</b> (clients see this name when they approve the link).</li>
            <li>For <b>scopes</b>, tick <b>Accounting</b> only &mdash; <code>com.intuit.quickbooks.accounting</code>, the single scope
                YourBlinds asks for. Leave <b>Payments</b> unticked.</li>
            <li>Under the development <b>Redirect URIs</b>, add exactly <code>https://www.yourblinds.uk/admin/accounting/callback.php</code>
                &mdash; https, <b>with</b> the <code>www</code>, no slash on the end. If a connection starts on one address and returns on
                the other (with or without <code>www</code>), clients get <em>&ldquo;Security check failed on the accounting connection
                (state mismatch)&rdquo;</em>.</li>
            <li>Open the development <b>Keys and credentials</b> and copy the <b>Client ID</b> and <b>Client Secret</b>. Treat the secret
                like a password &mdash; not in an email, a document or a chat. The YourBlinds code is on a <b>public</b> GitHub page, so keys
                must <b>never</b> go into the code or any file in it.</li>
            <li>Optional: add a <b>United Kingdom</b> sandbox company, so the mapping page shows UK VAT codes like <code>20.0% S</code>.</li>
          </ul>

          <p><b>Part 2 &mdash; put the keys into YourBlinds.</b></p>
          <ul class="steps">
            <li>Logged in as a super-admin, go to <b>Setup &rarr; Settings &rarr; Accounting</b>. Under the QuickBooks Online card is a
                fold-out box, <b>QuickBooks app credentials</b> <em>(super-admin &mdash; sets up the connection for everyone)</em>.
                Ordinary admins never see it. It opens by itself while QuickBooks isn&rsquo;t set up; once it is, it says
                <b>Currently configured &#10003;</b>.</li>
            <li><b>Environment</b>: <b>Sandbox (test)</b>. <b>Client ID</b>: paste it. <b>Client Secret</b>: paste it. The secret box always
                shows empty after saving, with the hint <em>&ldquo;&bull;&bull;&bull;&bull;&bull;&bull; (stored &mdash; leave blank to
                keep)&rdquo;</em>: leaving it blank keeps the stored secret; typing in it replaces it.</li>
            <li><b>Redirect URI</b> <em>(must match the one registered at Intuit)</em> is already filled in with the address YourBlinds will
                use. Check it matches Intuit exactly.</li>
            <li>Press <b>Save credentials</b>. The green bar reads <b>&ldquo;QuickBooks app credentials saved.&rdquo;</b>, the card badge says
                <b>Sandbox (test)</b>, and the card changes from <b>Not set up</b> to <b>Not connected</b>, with a <b>Connect to
                QuickBooks</b> button.</li>
            <li>If you get <em>&ldquo;Could not save: &hellip; &mdash; have you run migrate_platform_config.php?&rdquo;</em>, the settings
                table is missing: run <code>/migrate_platform_config.php</code> once, then save again.</li>
          </ul>
          <p>The keys are stored in the <b>database</b> (the <code>platform_config</code> table), not in a file. If a key is set in both the
             database and the server&rsquo;s <code>.env</code>, the one saved here wins. The key that encrypts each client&rsquo;s QuickBooks
             connection is made automatically the first time anyone connects.</p>

          <p><b>Part 3 &mdash; check it works.</b></p>
          <ul class="steps">
            <li>Open <code>/master-admin/accounting-health.php</code> (super-admin only; it only reads). It <b>never shows key values</b>,
                only <b>PRESENT</b> or <b>MISSING</b>. Look for <b>QUICKBOOKS_CLIENT_ID: PRESENT</b>, <b>QUICKBOOKS_CLIENT_SECRET: PRESENT</b>
                and <b>isConfigured(): YES &#10003;</b>. The <b>redirectUri()</b> line must match Intuit exactly, and both
                <b>platform_config table</b> and <b>connections table</b> should say <b>exists</b>.</li>
            <li>Then test for real: on your own account, browsing the <b>www</b> address, press <b>Connect to QuickBooks</b>, sign in with
                your Intuit developer login, pick the sandbox company and approve. You should land back with <b>&#9679; Connected</b>. Press
                <b>Set up mapping</b> and check the lists fill from the sandbox company.</li>
          </ul>

          <p><b>Part 4 &mdash; going live.</b> Allow <b>a week or two</b> in case Intuit asks questions.</p>
          <ul class="steps">
            <li>At Intuit, open your app&rsquo;s <b>production</b> settings and work through getting production keys: app details, host
                domain <code>www.yourblinds.uk</code>, a launch and a disconnect URL
                (<code>https://www.yourblinds.uk/admin/settings.php#accounting</code> for both), and links to YourBlinds&rsquo; own
                <b>licence</b> and <b>privacy</b> pages (<code>https://www.yourblinds.uk/legal/licence.php</code>,
                <code>https://www.yourblinds.uk/legal/privacy.php</code>).</li>
            <li>Complete Intuit&rsquo;s <b>app assessment questionnaire</b>. Honest answers for YourBlinds: sign-in goes through Intuit and we
                never see QuickBooks passwords; connection tokens are stored encrypted; everything runs over HTTPS; a client can disconnect
                any time, and disconnecting revokes access at Intuit.</li>
            <li>Add the <b>same redirect address</b> to the production redirect list &mdash; production has its own.</li>
            <li>Once accepted, Intuit shows a <b>new</b> production Client ID and Secret, different from the sandbox ones.</li>
            <li><b>Disconnect any sandbox links first</b> (your own test account, at least) &mdash; a link to a sandbox company would show as
                connected but fail against live QuickBooks.</li>
            <li>In <b>QuickBooks app credentials</b>: <b>Environment</b> &rarr; <b>Production (live)</b>, paste the production Client ID,
                <b>type</b> the production secret (don&rsquo;t leave it blank, or the sandbox secret stays), and <b>Save credentials</b>.
                The card badge now reads <b>Live</b>.</li>
            <li>Re-check the health page (<b>QUICKBOOKS_ENV: production</b>), then connect your own real company first.</li>
          </ul>

          <p><b>Looking after it.</b> If the secret has ever been exposed, generate a new one at Intuit and paste it in straight away.
             <b>Never</b> put keys, secrets or a company&rsquo;s ID number (&ldquo;realm ID&rdquo;) into the code, a help page or a GitHub
             issue.</p>

          <p><b>Xero and Sage &mdash; not built yet.</b> The Accounting tab shows them as <b>Coming soon</b>, and YourBlinds has no
             connection code for them, so there is <b>nothing to paste in</b> today. When one is built, the platform setup will follow the
             same pattern (one developer app, a Client ID and Secret, a registered redirect address) and this guide will be updated. Until
             then, clients on Xero or Sage use the CSV exports on the Payments page.</p>',
        'script'  => [
            ['1',  'One app, for the whole platform', 'This is the one off job behind every client\'s Connect to QuickBooks button. YourBlinds has to be registered with Intuit, the company behind QuickBooks, as an app. Intuit gives you two keys. You paste them in once, and every client can then link their own company.', 1],
            ['2',  'Create the app at Intuit',        'Start at developer dot intuit dot com, and create an app for QuickBooks Online. Call it YourBlinds, because clients see that name when they approve the link. When it asks for scopes, tick Accounting only.', 2],
            ['3',  'The redirect address',            'Next, add the redirect address. It is https, www dot yourblinds dot uk, slash admin, slash accounting, slash callback dot p h p. It must match exactly: with the www, and nothing on the end. If it does not, clients get a state mismatch error.', 3],
            ['4',  'Copy the keys',                   'Then open Keys and credentials, and copy the Client ID and the Client Secret. These development keys only work with sandbox test companies. Treat the secret like a password. Never email it, and never put it in the code, because the code is public.', 4],
            ['5',  'The credentials box',             'Now in YourBlinds, go to Settings, the Accounting tab. Under the QuickBooks card is a fold out box called QuickBooks app credentials. Only super admins can see it, and it opens by itself until QuickBooks is set up.', 5],
            ['6',  'Paste and save',                  'Leave Environment on Sandbox. Paste the Client ID, and the Client Secret. Check the Redirect URI matches the one at Intuit. Then press Save credentials. The green bar confirms it, and the card changes from Not set up to Not connected, with a Connect button.', 6],
            ['7',  'Check it works',                  'To check it, open the accounting health page. It never shows the keys, just present or missing. You want both keys present, isConfigured yes, and a redirect address that matches Intuit\'s. Then connect a sandbox company yourself, to prove it end to end.', 7],
            ['8',  'Going live at Intuit',            'When you are ready for real companies, go back to Intuit and open the production settings. Fill in the app details and links, answer the app assessment questionnaire, and add the same redirect address again. Allow a week or two.', 8],
            ['9',  'Switch YourBlinds to live',       'Once Intuit approves, you get new production keys. Disconnect any sandbox links first. Then switch Environment to Production, paste the new Client ID, and type in the new secret. Do not leave it blank. Save, and the badge now says Live.', 9],
            ['10', 'What it does, and does not, yet', 'After that, every client can connect and map their QuickBooks. Remember, sending paid sales across is not built yet. And Xero and Sage have no connection yet either, so there is nothing to paste in for them.', 10],
        ],
];
