<?php
declare(strict_types=1);

/**
 * Help & guide — step-by-step guided walkthroughs ("dummies' guide").
 *
 * A guide is a full page: an animated walkthrough of the real screen, the
 * written steps, and a narration script you can play aloud (free browser
 * text-to-speech, defaulting to the "Google UK English Female" voice).
 *
 * Guides live in the $GUIDES registry below, keyed by slug (?g=slug). Add a
 * section by adding an entry — the Help & guide index links to any guide whose
 * slug it references. Keep the written steps matched to the real screen.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user    = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$isSuper = function_exists('is_super_admin') && is_super_admin();

/** can the current user open a guide of this audience? */
$canSee = static function (string $aud) use ($isAdmin, $isSuper): bool {
    if ($aud === 'super') return $isSuper;
    if ($aud === 'admin') return $isAdmin || $isSuper;
    return true;
};

// Guide registry (shared with help/index.php's guide list).
$GUIDES = require __DIR__ . '/_guides.php';

$slug = (string) ($_GET['g'] ?? '');
$g    = $GUIDES[$slug] ?? null;
if ($g === null || !$canSee($g['aud'])) {
    http_response_code($g === null ? 404 : 403);
    $notFound = true;
}

$activeNav = 'help';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($notFound) ? 'Guide not found' : e($g['title']) ?> &middot; Help &amp; guide</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
      /* Guide styles are scoped under .gd so they can't leak into the app.
         The palette maps to the app's own theme tokens, so a guide follows
         light/dark with everything else. Status colours (nudge red, saved
         green, the mock's navy sidebar) stay literal on purpose — they must
         read the same on both themes. */
      .gd{
        --surface:var(--bg-card,#fff); --panel:var(--bg-subtle,#f6f8fb);
        --ink:var(--text-primary,#1c2733); --soft:var(--text-muted,#5b6b7b); --faint:var(--text-faint,#8a9aa8);
        --line:var(--border,#e2e8ef); --line-2:var(--border-faint,#eef2f6);
        --accent:var(--link,#2563eb); --accent-ink:var(--link,#1d4ed8); --accent-wash:var(--link-ring,#e5edff);
        --good:#159d5c; --good-wash:rgba(21,157,92,.14); --err:#dc2626; --err-wash:rgba(220,38,38,.12);
        --nav:#1f2a37; --nav-ink:#c3d0dc;
        --gd-shadow:var(--shadow-sm,0 1px 2px rgba(20,30,45,.05)), 0 14px 34px -18px rgba(20,30,45,.28);
        max-width:820px;
      }
      .gd .backlink{ display:inline-flex; align-items:center; gap:.35rem; font-size:.85rem; color:var(--accent);
        text-decoration:none; margin-bottom:1rem; }
      .gd .backlink:hover{ text-decoration:underline; }
      .gd .eyebrow{ font-size:.72rem; letter-spacing:.14em; text-transform:uppercase; color:var(--accent-ink); font-weight:700; }
      .gd .lede{ color:var(--soft); max-width:62ch; font-size:1.02rem; line-height:1.6; margin:.5rem 0 0; }
      .gd .lede b{ color:var(--ink); }
      .gd .openbtn{ display:inline-flex; align-items:center; gap:.4rem; margin-top:1rem; background:var(--accent);
        color:#fff; text-decoration:none; border-radius:9px; padding:.5rem .9rem; font-size:.9rem; font-weight:700; }
      .gd .openbtn:hover{ background:var(--accent-ink); }

      .gd section{ margin:2.2rem 0; }
      .gd .sec-h{ display:flex; align-items:baseline; gap:.6rem; margin-bottom:.9rem; flex-wrap:wrap; }
      .gd .sec-h h2{ font-size:1.2rem; font-weight:700; margin:0; letter-spacing:-.01em; }
      .gd .sec-h .num{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; color:var(--accent); font-weight:600; font-size:.9rem; }
      .gd .sec-h p{ margin:0; color:var(--soft); font-size:.86rem; }

      /* animated walkthrough */
      .gd .demo-shell{ background:var(--surface); border:1px solid var(--line); border-radius:16px; box-shadow:var(--gd-shadow); overflow:hidden; }
      .gd .demo-bar{ display:flex; align-items:center; gap:.4rem; padding:.6rem .9rem; border-bottom:1px solid var(--line); background:var(--panel); }
      .gd .demo-bar i{ width:11px; height:11px; border-radius:50%; background:var(--line); display:inline-block; }
      .gd .demo-bar span{ margin-left:.5rem; font-size:.8rem; color:var(--faint); font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
      .gd .app{ display:grid; grid-template-columns:132px 1fr; min-height:330px; }
      .gd .side{ background:var(--nav); color:var(--nav-ink); padding:.9rem .8rem; }
      .gd .side .logo{ font-weight:800; color:#fff; font-size:.95rem; letter-spacing:-.01em; }
      .gd .side .logo b{ color:#5b9bff; }
      .gd .side small{ display:block; font-size:.56rem; letter-spacing:.14em; color:#6a7d8c; margin:.1rem 0 1rem; }
      .gd .side a{ display:block; font-size:.78rem; color:var(--nav-ink); padding:.3rem .5rem; border-radius:7px; margin:.05rem 0; text-decoration:none; }
      .gd .side a.on{ background:rgba(91,155,255,.16); color:#fff; }
      .gd .stage{ position:relative; padding:1.1rem 1.2rem; background:var(--surface); }
      .gd .card-t{ font-weight:700; font-size:.95rem; margin-bottom:.9rem; color:var(--ink); }
      .gd .frow{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem .8rem; }
      .gd .fld label{ display:block; font-size:.64rem; color:var(--faint); font-weight:600; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.2rem; }
      .gd .fld .box{ height:30px; border:1px solid var(--line); border-radius:7px; background:var(--panel); display:flex; align-items:center; padding:0 .5rem; font-size:.8rem; color:var(--ink); overflow:hidden; }
      .gd .req{ color:var(--err); }
      /* State-driven walkthrough: the stage's data-step (0..3) is advanced by
         the narration (or an idle loop), so the visuals stay in sync with the
         voice. Everything transitions between states rather than running on a
         fixed timeline. */
      .gd #nameBox{ position:relative; transition:border-color .25s, box-shadow .25s; }
      .gd #nameBox .ph{ color:var(--faint); transition:opacity .2s; }
      .gd #nameBox .val{ position:absolute; left:.5rem; color:var(--ink); opacity:0; transition:opacity .2s; }
      .gd .hint{ grid-column:1 / -1; font-size:.72rem; color:var(--err); font-weight:600; opacity:0; margin-top:-.2rem; transition:opacity .2s; }
      .gd .save{ margin-top:1rem; display:inline-flex; align-items:center; gap:.4rem; background:var(--nav); color:#fff; border-radius:8px; padding:.42rem .8rem; font-size:.82rem; font-weight:600; transition:transform .12s, filter .12s; }
      .gd .save.pressed{ transform:scale(.96); filter:brightness(1.25); }
      .gd .toast{ position:absolute; top:.7rem; right:.9rem; background:var(--good-wash); color:var(--good); border:1px solid color-mix(in srgb,var(--good) 35%,transparent); border-radius:8px; padding:.35rem .7rem; font-size:.78rem; font-weight:700; opacity:0; transform:translateY(6px); transition:opacity .3s, transform .3s; }
      .gd .caps{ position:relative; height:2.2rem; margin-top:.4rem; }
      .gd .caps b{ position:absolute; inset:0; display:flex; align-items:center; gap:.5rem; font-size:.9rem; color:var(--soft); font-weight:500; opacity:0; transition:opacity .25s; }
      .gd .caps b .n{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-weight:600; color:var(--accent); font-size:.8rem; }
      .gd .caps b.c1{ color:var(--err); }
      .gd .caps b.c3{ color:var(--good); }

      /* step 1 — Save pressed, name still blank: error border + hint + caption */
      .gd .stage[data-step="1"] #nameBox{ border-color:var(--err); box-shadow:0 0 0 3px var(--err-wash); }
      .gd .stage[data-step="1"] .hint{ opacity:1; }
      /* step 2 & 3 — name filled in: value replaces placeholder */
      .gd .stage[data-step="2"] #nameBox .ph,
      .gd .stage[data-step="3"] #nameBox .ph{ opacity:0; }
      .gd .stage[data-step="2"] #nameBox .val,
      .gd .stage[data-step="3"] #nameBox .val{ opacity:1; }
      /* step 3 — saved: toast in */
      .gd .stage[data-step="3"] .toast{ opacity:1; transform:none; }
      /* captions follow the step */
      .gd .stage[data-step="0"] .caps b.c0,
      .gd .stage[data-step="1"] .caps b.c1,
      .gd .stage[data-step="2"] .caps b.c2,
      .gd .stage[data-step="3"] .caps b.c3{ opacity:1; }
      @media (prefers-reduced-motion:reduce){
        .gd #nameBox, .gd #nameBox .ph, .gd #nameBox .val, .gd .hint, .gd .save, .gd .toast, .gd .caps b{ transition:none !important; }
      }

      /* written steps */
      .gd .steps{ list-style:none; padding:0; margin:.2rem 0 .9rem; counter-reset:s; }
      .gd .steps li{ position:relative; padding:.4rem 0 .4rem 2rem; border-bottom:1px solid var(--line-2); color:var(--soft); }
      .gd .steps li:last-child{ border-bottom:none; }
      .gd .steps li b{ color:var(--ink); }
      .gd .steps li::before{ counter-increment:s; content:counter(s); position:absolute; left:0; top:.4rem; width:1.35rem; height:1.35rem; border-radius:50%; background:var(--accent); color:#fff; font-size:.75rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
      .gd .prose{ color:var(--soft); line-height:1.6; }
      .gd .prose p{ margin:0 0 .8rem; } .gd .prose p:last-child{ margin-bottom:0; } .gd .prose b{ color:var(--ink); }
      .gd .oops{ background:var(--err-wash); border-left:3px solid var(--err); border-radius:8px; padding:.6rem .85rem; font-size:.94rem; color:var(--ink); margin-top:.4rem; }
      .gd .oops b{ color:var(--err); }

      /* narration */
      .gd .ttsrow{ display:flex; align-items:center; gap:.8rem; flex-wrap:wrap; margin:.9rem 0 .5rem; }
      .gd .ttsbtn{ font:inherit; font-weight:700; cursor:pointer; border:none; border-radius:9px; padding:.5rem 1rem; background:var(--accent); color:#fff; font-size:.9rem; }
      .gd .ttsbtn:hover{ background:var(--accent-ink); }
      .gd .ttsbtn:disabled{ background:var(--line); color:var(--faint); cursor:not-allowed; }
      .gd .ttsbtn.ghost{ background:transparent; color:var(--accent); border:1px solid var(--line); padding:.4rem .8rem; font-size:.84rem; font-weight:600; }
      .gd .ttsbtn.ghost:hover{ background:var(--accent-wash); border-color:var(--accent); }
      .gd .ttsbtn.speaking{ color:var(--err); }
      .gd .ttsnote{ font-size:.8rem; color:var(--soft); max-width:62ch; margin:.1rem 0 0; }
      .gd .vsel{ display:inline-flex; align-items:center; gap:.4rem; font-size:.8rem; color:var(--soft); }
      .gd .vsel span{ font-weight:600; }
      .gd .vsel select{ font:inherit; font-size:.82rem; padding:.35rem .5rem; border:1px solid var(--line); border-radius:7px; background:var(--surface); color:var(--ink); max-width:230px; }
      @media(max-width:620px){ .gd .frow{ grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
    <?php if (isset($notFound)): ?>
        <div class="page-header"><div><h1 class="page-title">Guide not found</h1></div></div>
        <p><a href="/help/index.php">&larr; Back to Help &amp; guide</a></p>
    <?php else: ?>
        <div class="gd">
            <a class="backlink" href="/help/index.php">&larr; Help &amp; guide</a>
            <div class="page-header" style="margin-bottom:.4rem">
                <div>
                    <div class="eyebrow"><?= e($g['eyebrow']) ?></div>
                    <h1 class="page-title" style="margin:.35rem 0 0"><?= e($g['title']) ?></h1>
                </div>
            </div>
            <p class="lede"><?= $g['lede'] ?></p>
            <?php if (!empty($g['open'])): ?>
                <a class="openbtn" href="<?= e($g['open']) ?>">Open the screen &rarr;</a>
            <?php endif; ?>

            <section>
                <div class="sec-h"><span class="num">01</span><h2>Watch it</h2><p>plays with a voice-over — turn your volume down for quiet</p></div>
                <?= $g['demo'] ?>
                <div class="ttsrow">
                    <button id="gdPlay" class="ttsbtn ghost">&#9654; Replay narration</button>
                    <label class="vsel"><span>Voice</span><select id="gdVoice"></select></label>
                </div>
                <p class="ttsnote">The voice-over plays automatically in <b>Google UK English Female</b> (Chrome / Edge). Some browsers hold the sound until you tap the page first — if you hear nothing, click anywhere or press replay.</p>
            </section>

            <section>
                <div class="sec-h"><span class="num">02</span><h2>Step by step</h2></div>
                <div class="prose"><?= $g['body'] ?></div>
            </section>
        </div>

        <script>
        (function(){
            var lines = <?= json_encode(array_map(static fn($r) => $r[2], $g['script']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            var steps = <?= json_encode(array_map(static fn($r) => (int) ($r[3] ?? 0), $g['script'])) ?>;
            var btn = document.getElementById('gdPlay');
            var sel = document.getElementById('gdVoice');
            var stage = document.getElementById('gdStage');
            var saveEl = document.querySelector('.gd .save');
            var synth = window.speechSynthesis;
            if (!btn) return;

            var STEP_COUNT = 4, idleTimer = null;
            function setStep(n){
                if (!stage) return;
                stage.setAttribute('data-step', String(n));
                if (saveEl && (n === 1 || n === 3)){ saveEl.classList.add('pressed'); setTimeout(function(){ saveEl.classList.remove('pressed'); }, 200); }
            }
            function stopIdle(){ if (idleTimer){ clearInterval(idleTimer); idleTimer = null; } }
            function startIdle(){ stopIdle(); var s = 0; setStep(0); idleTimer = setInterval(function(){ s = (s + 1) % STEP_COUNT; setStep(s); }, 2600); }
            startIdle(); // visuals loop on their own until (and after) narration plays

            if (!synth){ btn.disabled = true; btn.textContent = 'Text-to-speech not available here'; if (sel) sel.style.display = 'none'; return; }

            var playing = false, i = 0, voices = [], spokeAny = false;

            function loadVoices(){
                voices = synth.getVoices() || [];
                if (!sel || !voices.length) return;
                var en = voices.filter(function(v){ return /^en/i.test(v.lang); });
                var rest = voices.filter(function(v){ return !/^en/i.test(v.lang); });
                function score(v){ var s = 0; if (/google uk english female/i.test(v.name)) s += 10; if (/google uk english/i.test(v.name)) s += 4; if (/en[-_]GB/i.test(v.lang)) s += 2; if (/natural|online|neural|premium|enhanced/i.test(v.name)) s += 3; return s; }
                en.sort(function(a, b){ return score(b) - score(a); });
                var ordered = en.concat(rest), keep = sel.value;
                sel.innerHTML = '';
                ordered.forEach(function(v){ var o = document.createElement('option'); o.value = v.name; o.textContent = v.name.replace(/\s*\(.*?\)\s*$/, '') + ' · ' + v.lang; sel.appendChild(o); });
                var gukf = ordered.filter(function(v){ return /google uk english female/i.test(v.name); })[0];
                sel.value = keep && ordered.some(function(v){ return v.name === keep; }) ? keep : (gukf ? gukf.name : (ordered[0] ? ordered[0].name : ''));
            }
            function currentVoice(){ if (!sel) return voices[0]; return voices.filter(function(v){ return v.name === sel.value; })[0] || voices[0]; }
            function speakNext(){
                if (!playing) return;
                if (i >= lines.length){ stop(); return; }
                var idx = i;
                var u = new SpeechSynthesisUtterance(lines[idx]);
                var v = currentVoice(); if (v) u.voice = v;
                u.rate = 1; u.pitch = 1;
                // Advance the walkthrough as each line BEGINS — this is what keeps
                // the visuals locked to the voice, whatever its speed.
                u.onstart = function(){ spokeAny = true; setStep(steps[idx]); btn.textContent = '⏹ Stop'; btn.classList.add('speaking'); };
                u.onend = function(){ i++; speakNext(); };
                u.onerror = function(){ i++; speakNext(); };
                synth.speak(u);
            }
            function play(){ playing = true; i = 0; btn.textContent = '⏹ Stop'; btn.classList.add('speaking'); stopIdle(); setStep(0); synth.cancel(); setTimeout(speakNext, 60); }
            function stop(){ playing = false; btn.textContent = '▶ Replay narration'; btn.classList.remove('speaking'); synth.cancel(); startIdle(); }
            btn.addEventListener('click', function(){ playing ? stop() : play(); });

            // Sound is on by default: try to start narration on load. Browsers that
            // block autoplay until a user gesture are covered by the first-gesture
            // fallback below (which fires only if nothing has spoken yet).
            var autoStarted = false;
            function autoStart(){
                if (autoStarted) return; autoStarted = true; play();
                // If the browser silently blocked autoplay, nothing will have spoken —
                // let the visuals keep looping until the first tap starts the sound.
                setTimeout(function(){ if (playing && !spokeAny) startIdle(); }, 1400);
            }
            function onFirstGesture(e){
                document.removeEventListener('pointerdown', onFirstGesture, true);
                document.removeEventListener('keydown', onFirstGesture, true);
                var controls = document.querySelector('.gd .ttsrow');
                if (controls && controls.contains(e.target)) return; // let the button handle itself
                if (!spokeAny) play();                                // autoplay was blocked — start now
            }

            loadVoices();
            if (typeof synth.onvoiceschanged !== 'undefined') synth.onvoiceschanged = loadVoices;
            document.addEventListener('pointerdown', onFirstGesture, true);
            document.addEventListener('keydown', onFirstGesture, true);
            window.addEventListener('pagehide', stop);
            // Give voices a moment to load so the very first line uses the chosen voice.
            setTimeout(autoStart, voices.length ? 350 : 700);
        })();
        </script>
    <?php endif; ?>
    </main>
</div>
</body>
</html>
