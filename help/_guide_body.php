<?php
/**
 * Help guide body — the walkthrough, its narration player and the written
 * steps. Included by help/guide.php (inside the app shell) and by
 * tools/guide_preview.php (local preview). Expects $g and $slug.
 *
 * The player plays the guide's script line by line: Alice's recorded clip
 * for a line when one exists (tools/guide_voice.php), otherwise the browser
 * voice, sentence by sentence. Each line's step index drives the stage's
 * data-step; v2 guides ('v' => 2) also get scenes that replay their
 * animation timeline per step, a caption bar, chapter buttons, a breathing
 * pause between lines and Pause/Resume.
 */

require_once __DIR__ . '/../_partials/guide_clips.php';

$gdV2     = (int) ($g['v'] ?? 1) >= 2;
$gdLines  = array_map(static fn ($r) => (string) ($r[2] ?? ''), $g['script']);
$gdTitles = array_map(static fn ($r) => trim(strip_tags((string) ($r[1] ?? ''))), $g['script']);
$gdSteps  = array_map(static fn ($r) => (int) ($r[3] ?? 0), $g['script']);
$gdClips  = array_map(static fn ($r) => guide_clip_url($slug, (string) ($r[2] ?? '')), $g['script']);
$gdHasRec = (bool) array_filter($gdClips);
$gdJson   = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
?>
        <div class="gd<?= $gdV2 ? ' gd-v2' : '' ?>">
            <a class="backlink" href="/help/index.php">&larr; Help &amp; guide</a>
            <div class="page-header" style="margin-bottom:.4rem">
                <div>
                    <div class="eyebrow"><?= e($g['eyebrow']) ?></div>
                    <h1 class="page-title" style="margin:.35rem 0 0"><?= e($g['title']) ?></h1>
                </div>
            </div>
            <p class="lede"><?= $g['lede'] ?></p>
            <?php if (!empty($g['open'])):
                // A Factory Console user is redirected away from the retail-shaped
                // screens (factory_console_screens()), so for them this button
                // never reached the screen the guide is about. Say where it really
                // goes rather than bouncing them somewhere unexplained.
                $gOpenPath = explode('?', (string) $g['open'], 2)[0];
                $gBounce   = function_exists('factory_console_screens') && factory_console_user()
                           ? (factory_console_screens()[$gOpenPath] ?? null) : null;
            ?>
                <?php if ($gBounce !== null): ?>
                    <a class="openbtn" href="<?= e($gBounce) ?>">Open the console version &rarr;</a>
                    <p class="ui-hint" style="margin:.4rem 0 0">This guide shows the sales-side screen. Your login opens the Factory Console version instead.</p>
                <?php else: ?>
                    <a class="openbtn" href="<?= e($g['open']) ?>">Open the screen &rarr;</a>
                <?php endif; ?>
            <?php endif; ?>

            <section>
                <div class="sec-h play-head" id="gdWatch" role="button" tabindex="0" aria-label="Play the walkthrough">
                    <span class="num">01</span><h2><span class="playicon" id="gdIcon">&#9654;</span> Watch it</h2>
                    <p><?= $gdV2 ? 'click to play — ' . count($gdLines) . ' short chapters, with the voice-over' : 'click to play — with the voice-over' ?></p>
                </div>
                <?= $g['demo'] ?>
                <?php if ($gdV2): ?>
                    <div class="gd-progress"><i id="gdBar"></i></div>
                    <div class="gd-capbar" id="gdCapbar" aria-live="polite">
                        <span class="n" id="gdCapN">&#9654;</span>
                        <span class="t" id="gdCapT">Press play to start the walkthrough</span>
                        <span class="of" id="gdCapOf"><?= count($gdLines) ?> chapters</span>
                    </div>
                <?php endif; ?>
                <div class="ttsrow">
                    <button id="gdPlay" class="ttsbtn<?= $gdV2 ? '' : ' ghost' ?>"><?= $gdV2 ? '&#9654; Play' : '&#9654; Replay' ?></button>
                    <?php if ($gdV2): ?>
                        <button id="gdRestart" class="ttsbtn ghost" type="button">&#8634; Start again</button>
                    <?php endif; ?>
                    <label class="vsel"><span>Voice</span><select id="gdVoice"></select></label>
                </div>
                <?php if ($gdV2): ?>
                    <div class="gd-chaps-h">Jump to a chapter</div>
                    <nav class="gd-chaps" id="gdChaps" aria-label="Chapters">
                        <?php foreach ($gdTitles as $k => $t): ?>
                            <button type="button" class="gd-chap" data-row="<?= $k ?>"><b><?= $k + 1 ?></b><?= e(rtrim($t, '.')) ?></button>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
                <?php if ($gdHasRec): ?>
                    <p class="ttsnote">Narrated by <b>Alice</b>, a natural recorded voice. Pick another voice from the list to hear your browser&rsquo;s own instead.</p>
                <?php else: ?>
                    <p class="ttsnote">Plays with a voice-over in <b>Google UK English Female</b> where your browser has it (Chrome / Edge) — otherwise the nearest British voice. Turn your volume down for quiet.</p>
                <?php endif; ?>
            </section>

            <section>
                <div class="sec-h"><span class="num">02</span><h2>Step by step</h2></div>
                <div class="prose"><?= $g['body'] ?></div>
            </section>
        </div>

        <script>
        (function(){
            var lines  = <?= json_encode($gdLines, $gdJson) ?>;
            var titles = <?= json_encode($gdTitles, $gdJson) ?>;
            var steps  = <?= json_encode($gdSteps) ?>;
            var clips  = <?= json_encode($gdClips, $gdJson) ?>;   // recorded voice per line ('' = none)
            var V2     = <?= $gdV2 ? 'true' : 'false' ?>;
            var GAP    = V2 ? 1400 : 0;                      // breathing space between lines (ms)
            var hasClips = clips.some(function(c){ return !!c; });
            var REC = '__recorded';
            var audio = new Audio();

            var btn = document.getElementById('gdPlay');
            var btnRestart = document.getElementById('gdRestart');
            var sel = document.getElementById('gdVoice');
            var stage = document.getElementById('gdStage');
            var watchEl = document.getElementById('gdWatch');
            var iconEl = document.getElementById('gdIcon');
            var capN = document.getElementById('gdCapN'), capT = document.getElementById('gdCapT'), capOf = document.getElementById('gdCapOf');
            var bar = document.getElementById('gdBar');
            var chaps = Array.prototype.slice.call(document.querySelectorAll('.gd-chap'));
            var synth = window.speechSynthesis;
            if (!btn) return;
            var scenes = stage ? Array.prototype.slice.call(stage.querySelectorAll('[data-scene]')) : [];

            var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
            // The mock can be much taller than the screen. Scroll the active
            // zone/field into view as each step lights up, but only when it's
            // actually out of sight, so a guide that already fits never jumps.
            function scrollStepIntoView(n){
                if (!stage || n < 1) return;
                var el = stage.querySelector('.z' + n) || stage.querySelector('.f' + n)
                      || stage.querySelector('[data-scene~="' + n + '"]');
                if (!el) return;
                var r = el.getBoundingClientRect();
                if (r.top < 70 || r.bottom > window.innerHeight - 20){
                    try { el.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' }); }
                    catch (e) { el.scrollIntoView(); }
                }
            }

            // Show step n. animate = replay the step's scene timelines from
            // the start; otherwise show them finished (poster / stopped).
            var curStep = -1;
            function setStep(n, animate){
                if (!stage) return;
                stage.setAttribute('data-step', String(n));
                scenes.forEach(function(sc){
                    var on = (' ' + sc.getAttribute('data-scene') + ' ').indexOf(' ' + n + ' ') !== -1;
                    sc.hidden = !on;
                    if (!on){ sc.classList.remove('gd-play', 'gd-done'); return; }
                    if (animate && n !== curStep){
                        sc.classList.remove('gd-play', 'gd-done');
                        sc.style.removeProperty('--k');
                        void sc.offsetWidth;            // restart the CSS animations
                        sc.classList.add('gd-play');
                    } else if (!animate){
                        sc.classList.remove('gd-play');
                        sc.classList.add('gd-done');
                    }
                });
                curStep = n;
                scrollStepIntoView(n);
            }
            // Stretch the visible scenes' timelines to the real clip length.
            function fitScenes(seconds){
                scenes.forEach(function(sc){
                    if (sc.hidden || !sc.classList.contains('gd-play')) return;
                    var len = parseFloat(sc.getAttribute('data-len') || '0');
                    if (len > 0 && seconds > 0) sc.style.setProperty('--k', Math.max(.6, Math.min(1.8, seconds / len)).toFixed(3));
                });
            }
            var curRow = -1;
            function setRow(row){
                curRow = row;
                if (capT){ capN.textContent = String(row + 1); capT.textContent = titles[row] || ''; capOf.textContent = (row + 1) + ' of ' + lines.length; }
                if (bar) bar.style.width = ((row + 1) / lines.length * 100) + '%';
                chaps.forEach(function(c, k){ c.classList.toggle('on', k === row); c.classList.toggle('seen', k < row); });
            }
            setStep(0, false); // resting poster — nothing plays until asked (no looping)

            if (!synth && !hasClips){ btn.disabled = true; btn.textContent = 'Voice-over not available here'; if (sel) sel.style.display = 'none'; return; }

            var playing = false, paused = false, i = 0, voices = [];
            function paint(){
                btn.textContent = playing ? (V2 ? '❚❚ Pause' : '⏹ Stop') : (V2 ? (paused ? '▶ Resume' : '▶ Play') : '▶ Replay');
                btn.classList.toggle('speaking', playing && !V2);
                if (iconEl) iconEl.textContent = playing ? '❚❚' : '▶';
                if (stage) stage.classList.toggle('gd-paused', paused);
            }

            function loadVoices(){
                voices = synth ? (synth.getVoices() || []) : [];
                if (!sel || (!voices.length && !hasClips)) return;
                var en = voices.filter(function(v){ return /^en/i.test(v.lang); });
                var rest = voices.filter(function(v){ return !/^en/i.test(v.lang); });
                function score(v){ var s = 0; if (/google uk english female/i.test(v.name)) s += 10; if (/google uk english/i.test(v.name)) s += 4; if (/en[-_]GB/i.test(v.lang)) s += 2; if (/natural|online|neural|premium|enhanced/i.test(v.name)) s += 3; return s; }
                en.sort(function(a, b){ return score(b) - score(a); });
                var ordered = en.concat(rest), keep = sel.value;
                sel.innerHTML = '';
                if (hasClips){ var r = document.createElement('option'); r.value = REC; r.textContent = 'Alice · natural voice'; sel.appendChild(r); }
                ordered.forEach(function(v){ var o = document.createElement('option'); o.value = v.name; o.textContent = v.name.replace(/\s*\(.*?\)\s*$/, '') + ' · ' + v.lang; sel.appendChild(o); });
                var gukf = ordered.filter(function(v){ return /google uk english female/i.test(v.name); })[0];
                sel.value = keep && (keep === REC || ordered.some(function(v){ return v.name === keep; })) ? keep
                          : (hasClips ? REC : (gukf ? gukf.name : (ordered[0] ? ordered[0].name : '')));
            }
            function currentVoice(){ if (!sel) return voices[0]; return voices.filter(function(v){ return v.name === sel.value; })[0] || voices[0]; }

            // Chrome will not speak a long utterance reliably (past ~15s it cuts
            // out and stops firing events), so browser-voice lines are cut into
            // sentence-sized pieces, and a watchdog stops a swallowed piece from
            // stalling the walkthrough.
            function chunk(text){
                var parts = text.match(/[^.!?]+[.!?]*\s*/g) || [text];
                var out = [], buf = '';
                parts.forEach(function(s){
                    if (buf && (buf + s).length > 180){ out.push(buf.trim()); buf = ''; }
                    buf += s;
                });
                if (buf.trim()) out.push(buf.trim());
                return out;
            }
            // The play list: a recorded clip per line when the Alice voice is
            // chosen and recorded, otherwise sentence-sized browser-voice pieces.
            var queue = [];
            function ttsItems(row){ return chunk(lines[row]).map(function(part){ return { text: part, row: row }; }); }
            function buildQueue(){
                var rec = hasClips && (!sel || sel.value === REC);
                queue = [];
                lines.forEach(function(line, row){
                    if (rec && clips[row]) queue.push({ clip: clips[row], text: line, row: row });
                    else if (synth) queue = queue.concat(ttsItems(row));
                });
            }

            var watchdog = null, gapTimer = null;
            function clearWatch(){ if (watchdog){ clearTimeout(watchdog); watchdog = null; } if (gapTimer){ clearTimeout(gapTimer); gapTimer = null; } }
            function advance(from){
                if (from !== i) return;   // the event and the watchdog both fired
                clearWatch();
                i++;
                // A pause between lines (not between a line's own sentences).
                var nextRow = i < queue.length ? queue[i].row : -1;
                if (GAP && nextRow !== queue[from].row){ gapTimer = setTimeout(speakNext, GAP); }
                else speakNext();
            }
            function speakNext(){
                clearWatch();
                if (!playing) return;
                if (i >= queue.length){ finish(); return; }
                var idx = i, item = queue[idx];
                if (item.row !== curRow){ setRow(item.row); setStep(steps[item.row], true); }
                if (item.clip){
                    // Recorded line; if it can't play, say it with the browser voice instead.
                    var failed = false;
                    var fallback = function(){
                        if (failed || idx !== i || !playing) return; failed = true; clearWatch();
                        audio.onended = audio.onerror = null;
                        if (synth){ queue.splice.apply(queue, [idx, 1].concat(ttsItems(item.row))); speakNext(); }
                        else advance(idx);
                    };
                    audio.onended = function(){ advance(idx); };
                    audio.onerror = fallback;
                    audio.onloadedmetadata = function(){
                        if (idx !== i || !isFinite(audio.duration)) return;
                        fitScenes(audio.duration);
                        if (watchdog) clearTimeout(watchdog);
                        watchdog = setTimeout(function(){ advance(idx); }, audio.duration * 1000 + 3000);
                    };
                    audio.src = item.clip;
                    var p = audio.play(); if (p && p.catch) p.catch(fallback);
                    watchdog = setTimeout(function(){ advance(idx); }, Math.max(8000, item.text.length * 120));
                    return;
                }
                var u = new SpeechSynthesisUtterance(item.text);
                var v = currentVoice(); if (v) u.voice = v;
                u.rate = V2 ? .95 : 1; u.pitch = 1;
                u.onend   = function(){ advance(idx); };
                u.onerror = function(){ advance(idx); };
                synth.speak(u);
                // Roughly twice as long as the piece could plausibly take to say.
                watchdog = setTimeout(function(){ advance(idx); }, Math.max(4000, item.text.length * 160));
            }
            function silence(){ clearWatch(); if (synth) synth.cancel(); audio.onended = audio.onerror = audio.onloadedmetadata = null; audio.pause(); }

            // Play from a line (default: the start). Plays once through — no looping.
            function playFrom(row){
                silence();
                buildQueue();
                i = 0;
                for (var k = 0; k < queue.length; k++){ if (queue[k].row >= row){ i = k; break; } }
                playing = true; paused = false; curRow = -1; curStep = -1;
                paint();
                setTimeout(speakNext, 150);
            }
            function pause(){
                if (!playing) return;
                silence(); playing = false; paused = true; paint();
            }
            function resume(){ playFrom(curRow >= 0 ? curRow : 0); }
            function finish(){
                silence(); playing = false; paused = false; paint();
                if (V2){ setStep(curStep < 0 ? 0 : curStep, false); if (capT){ capN.textContent = '✓'; capT.textContent = 'That’s the whole walkthrough — pick a chapter to watch a part again'; capOf.textContent = lines.length + ' chapters'; } }
            }
            function stop(){ silence(); playing = false; paused = false; paint(); }

            function toggle(){
                if (playing) { V2 ? pause() : stop(); }
                else if (V2 && paused) resume();
                else playFrom(0);
            }

            btn.addEventListener('click', toggle);
            if (btnRestart) btnRestart.addEventListener('click', function(){ playFrom(0); });
            chaps.forEach(function(c){ c.addEventListener('click', function(){ playFrom(+c.getAttribute('data-row')); }); });
            // The "Watch it" heading is the play button.
            if (watchEl){
                watchEl.addEventListener('click', toggle);
                watchEl.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' '){ e.preventDefault(); toggle(); } });
            }

            loadVoices();
            if (synth && typeof synth.onvoiceschanged !== 'undefined') synth.onvoiceschanged = loadVoices;
            window.addEventListener('pagehide', stop);
        })();
        </script>
        <?php if (!empty($g['js'])): ?>
        <script>/* interactive for this guide */
        <?= $g['js'] ?>
        </script>
        <?php endif; ?>
