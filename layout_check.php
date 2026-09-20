<?php
declare(strict_types=1);

/**
 * READ-ONLY layout check. Loads every screen at a range of window widths and
 * reports any that push the page sideways. Makes NO changes to any data.
 *
 * WHY THIS ISN'T IN system_check.php: that one reads the database. This one has
 * to measure a rendered page, which only a browser can do — so it runs the
 * sweep in your browser, in hidden frames, and reports what it measured.
 *
 * WHAT IT'S LOOKING FOR: a page whose content is wider than the window, so the
 * whole thing shifts left and a horizontal scrollbar appears. It is nearly
 * always one of two mistakes:
 *
 *   1. A wide table with no wrapper. Put it in <div class="table-wrap"> and the
 *      table scrolls inside its own box instead of moving the page.
 *   2. A flex or grid child with min-width:auto (the default). Such a child
 *      refuses to be narrower than its content, so instead of the content
 *      scrolling, the child sets its container's width and the page goes with
 *      it. Give the children min-width:0. Watch for a fixed min-width on an
 *      input or select too — that's a floor the element can never go under.
 *
 * WHICH PAGES: found automatically — anything that renders one of the two
 * shells (_partials/factory_head.php or _partials/sidebar.php) is a screen
 * somebody can open, so it gets swept. That means a page added later is
 * covered without anyone remembering to add it here.
 *
 * SAFETY: it only ever GETs, and only pages that draw a shell. Anything whose
 * name suggests it acts rather than shows (delete, wipe, save, act-as…) is
 * skipped and listed as skipped, because a GET could otherwise do something.
 *
 * Run as super-admin: /layout_check.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';

requireSuperAdmin();

$ROOT = __DIR__;

/* ---- Which files are screens? -------------------------------------------- */
// A screen draws one of the two shells. An action endpoint redirects and exits
// long before it would draw anything, so it never mentions them.
$SHELLS = ['_partials/factory_head.php', '_partials/sidebar.php'];

// Directories that hold no screens: partials, generators, endpoints, assets.
$SKIP_DIRS = ['_partials', '_lib', '_backups', 'pdf-generator', 'api', 'vendor',
              'node_modules', '.git', '.github', 'assets', 'uploads', 'logs'];

// A GET on one of these could DO something rather than show something.
$UNSAFE = '/(^|[_-])(delete|wipe|purge|destroy|reset|logout|act-as|go-live|save|
             set-status|toggle|send|received|blind-action|scan-in|poll|
             record-payment|email)([_-]|$)/x';

// Not screens, whatever they mention:
//   this page   — it would load itself, inside itself, forever;
//   *-print     — sized to a sheet of paper, so "too wide for a phone" is the
//                 whole point and flagging it would be noise;
//   help/guides — data files the guide engine reads, not pages you can open.
$NOT_A_SCREEN = static function (string $rel, string $base): bool {
    return $base === 'layout_check'
        || substr($base, -6) === '-print'
        || strpos($rel, '/help/guides/') === 0;
};

$pages = [];   // url => ['url'=>, 'file'=>, 'shell'=>]
$skipped = []; // url => reason

$scan = static function (string $dir) use (&$scan, $ROOT, $SKIP_DIRS, $SHELLS, $UNSAFE, $NOT_A_SCREEN, &$pages, &$skipped): void {
    foreach ((array) scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . '/' . $entry;
        if (is_dir($path)) {
            if (in_array($entry, $SKIP_DIRS, true) || $entry[0] === '.') continue;
            $scan($path);
            continue;
        }
        if (substr($entry, -4) !== '.php') continue;

        $rel  = str_replace('\\', '/', substr($path, strlen($ROOT)));
        $base = basename($entry, '.php');
        if ($NOT_A_SCREEN($rel, $base)) continue;

        $src = (string) @file_get_contents($path);
        $shell = null;
        foreach ($SHELLS as $s) { if (strpos($src, $s) !== false) { $shell = $s; break; } }
        if ($shell === null) continue;                       // not a screen

        if (preg_match($UNSAFE, $base)) { $skipped[$rel] = 'not GET-safe by name'; continue; }

        $pages[$rel] = ['url' => $rel, 'file' => $rel,
                        'shell' => $shell === '_partials/factory_head.php' ? 'factory' : 'app'];
    }
};
$scan($ROOT);
ksort($pages);
ksort($skipped);

/* ---- Real ids for the screens that need one ------------------------------ */
// Loaded bare, these pages show an empty state — which tests far less of the
// layout than a screen with content on it. Give them something real to draw.
$ids = ['order' => 0, 'quote' => 0, 'product' => 0, 'system' => 0];
try {
    $pdo = db();
    $factory = defined('FACTORY_CLIENT_ID') ? (int) FACTORY_CLIENT_ID : 3;

    $q = $pdo->prepare(
        "SELECT q.id FROM quotes q
           JOIN quote_items qi ON qi.quote_id = q.id
           JOIN products p     ON p.id = qi.product_id
          WHERE COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
       GROUP BY q.id ORDER BY q.id DESC LIMIT 1"
    );
    $q->execute([$factory]);
    $ids['order'] = (int) ($q->fetchColumn() ?: 0);

    $ids['quote'] = (int) ($pdo->query('SELECT id FROM quotes ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);

    // A product that actually has build rules, so the rules editor draws its tables.
    $p = $pdo->prepare('SELECT product_id FROM build_variables GROUP BY product_id ORDER BY COUNT(*) DESC LIMIT 1');
    $p->execute();
    $ids['product'] = (int) ($p->fetchColumn() ?: 0);

    if ($ids['product'] > 0) {
        $s = $pdo->prepare('SELECT id FROM product_systems WHERE product_id = ? ORDER BY id LIMIT 1');
        $s->execute([$ids['product']]);
        $ids['system'] = (int) ($s->fetchColumn() ?: 0);
    }
} catch (Throwable $e) {
    // No ids — every page still gets swept, just in its empty state. Said below.
}

// page => query string built from the ids above.
$PARAMS = [
    '/factory/edit-order.php'            => 'order=%order%',
    '/factory/order-areas.php'           => 'order=%order%',
    '/factory/order-suppliers.php'       => 'id=%order%',
    '/factory/build-rules-v2.php'        => 'product_id=%product%',
    '/factory/build-rules.php'           => 'product_id=%product%',
    '/factory/allowances.php'            => 'product_id=%product%',
    '/factory/worksheets.php'            => 'product_id=%product%',
    '/quote-builder/edit.php'            => 'id=%quote%',
    '/admin/products/edit.php'           => 'id=%product%',
    '/admin/products/price-tables.php'   => 'system_id=%system%',
];

$bare = [];
foreach ($pages as $rel => &$pg) {
    if (!isset($PARAMS[$rel])) continue;
    $qs = strtr($PARAMS[$rel], [
        '%order%' => (string) $ids['order'], '%quote%' => (string) $ids['quote'],
        '%product%' => (string) $ids['product'], '%system%' => (string) $ids['system'],
    ]);
    if (preg_match('/=0$|=$/', $qs)) { $bare[] = $rel; continue; }   // no id to give it
    $pg['url'] = $rel . '?' . $qs;
}
unset($pg);

$pageList = array_values($pages);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Layout check · YourBlinds</title>
<style>
  :root { --ink:#111827; --soft:#4b5563; --faint:#9ca3af; --line:#e5e7eb;
          --bg:#f6f7f9; --card:#fff; --bad:#b91c1c; --badbg:#fef2f2; --good:#166534; }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--ink);
         font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
  main { max-width:1100px; margin:0 auto; padding:1.5rem 1rem 4rem; }
  h1 { font-size:1.5rem; margin:0 0 .3rem; }
  p.lede { color:var(--soft); margin:0 0 1.2rem; max-width:62ch; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:12px;
          padding:1rem 1.2rem; margin:0 0 1rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  .row { display:flex; gap:.8rem; align-items:center; flex-wrap:wrap; }
  .row > * { min-width:0; }
  button { font:inherit; font-weight:600; cursor:pointer; border-radius:8px;
           border:1px solid var(--line); background:var(--card); padding:.5rem .9rem; }
  button.go { background:#1f2a37; color:#fff; border-color:#1f2a37; }
  button:disabled { opacity:.5; cursor:default; }
  label.w { display:inline-flex; align-items:center; gap:.35rem; font-size:.9rem; color:var(--soft); }
  progress { width:100%; height:.6rem; }
  table { width:100%; border-collapse:collapse; font-size:.9rem; }
  th { text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em;
       color:var(--faint); font-weight:700; padding:.4rem .5rem; border-bottom:1px solid var(--line); }
  td { padding:.4rem .5rem; border-bottom:1px solid #f1f3f6; vertical-align:top; }
  td.w { font-variant-numeric:tabular-nums; white-space:nowrap; }
  tr.bad td { background:var(--badbg); }
  tr.bad td.page { color:var(--bad); font-weight:600; }
  .ok { color:var(--good); font-weight:600; }
  .muted { color:var(--faint); }
  code { font:0.85em ui-monospace,Consolas,monospace; background:#f3f4f6;
         border-radius:4px; padding:.05rem .3rem; }
  .tail { font-size:.85rem; color:var(--soft); }
  .tail li { margin:.15rem 0; }
  #sandbox { position:fixed; left:-10000px; top:0; width:0; height:0; overflow:hidden; }
</style>
</head>
<body>
<main>
  <h1>Layout check</h1>
  <p class="lede">
    Opens every screen at a range of window widths and reports any that are wider than the
    window — the fault that shifts a page sideways and puts a scrollbar under it. Read-only:
    it only ever loads pages, exactly as you would.
  </p>

  <div class="card">
    <div class="row">
      <button class="go" id="run">Run the check</button>
      <button id="stop" disabled>Stop</button>
      <label class="w"><input type="radio" name="wset" value="quick" checked> Quick (4 widths)</label>
      <label class="w"><input type="radio" name="wset" value="full"> Full (8 widths, down to a phone)</label>
    </div>
    <div class="row" style="margin-top:.5rem">
      <label class="w"><input type="radio" name="scope" value="all" checked> Every screen</label>
      <label class="w"><input type="radio" name="scope" value="factory"> Factory only</label>
      <span class="muted" id="count"></span>
    </div>
    <div class="row" style="margin-top:.8rem">
      <progress id="prog" value="0" max="1"></progress>
    </div>
    <div class="row" style="margin-top:.4rem"><span class="muted" id="status">Ready.</span></div>
  </div>

  <div class="card" id="resultCard" hidden>
    <h2 style="font-size:1rem;margin:0 0 .6rem">Results</h2>
    <table id="results">
      <thead><tr><th>Screen</th><th>Width</th><th>Overflow</th><th>What's too wide</th></tr></thead>
      <tbody></tbody>
    </table>
    <p class="tail" id="summary" style="margin-top:.8rem"></p>
  </div>

  <div class="card">
    <h2 style="font-size:1rem;margin:0 0 .4rem">What it covers</h2>
    <p class="tail" style="margin:0 0 .5rem">
      Screens are found by looking for the two shells, so anything you add later is swept
      without touching this file. <?= count($pageList) ?> found.
      <?php if ($bare): ?>
        <?= count($bare) ?> of them had no record to show, so they were loaded empty —
        that tests less than a screen with content on it:
        <?= htmlspecialchars(implode(', ', $bare), ENT_QUOTES) ?>.
      <?php endif; ?>
    </p>
    <?php if ($skipped): ?>
      <p class="tail" style="margin:0">
        Skipped, because a plain GET on them could do something rather than show something:
        <?= htmlspecialchars(implode(', ', array_keys($skipped)), ENT_QUOTES) ?>.
      </p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="font-size:1rem;margin:0 0 .4rem">If something fails</h2>
    <ul class="tail" style="margin:0;padding-left:1.1rem">
      <li><b>A wide table.</b> Wrap it in <code>&lt;div class="table-wrap"&gt;</code> and it
          scrolls inside its own box instead of moving the page.</li>
      <li><b>A flex or grid child.</b> They are <code>min-width:auto</code> by default, so a
          child refuses to be narrower than its content and sets its container's width
          instead. Give the children <code>min-width:0</code>.</li>
      <li><b>A fixed <code>min-width</code> on an input or select.</b> That's a floor the
          element can never go under. Use <code>width</code> plus
          <code>max-width:100%</code> so it's a preference, not a floor.</li>
      <li><b>A row of controls that won't wrap.</b> Add <code>flex-wrap:wrap</code>.</li>
      <li><b>Stacked flex columns.</b> <code>align-items:flex-start</code> is right side by
          side, but once a layout stacks, the cross axis is the horizontal one and the items
          size to their own content. Use <code>align-items:stretch</code> when stacked.</li>
    </ul>
  </div>
</main>

<div id="sandbox"><iframe id="frame" title="layout check sandbox"></iframe></div>

<script>
(function () {
    var PAGES = <?= json_encode($pageList, JSON_UNESCAPED_SLASHES) ?>;
    var WIDTHS = { quick: [1440, 1024, 768, 420], full: [1920, 1440, 1280, 1024, 768, 600, 420, 360] };

    var runBtn = document.getElementById('run');
    var stopBtn = document.getElementById('stop');
    var prog = document.getElementById('prog');
    var statusEl = document.getElementById('status');
    var card = document.getElementById('resultCard');
    var tbody = document.querySelector('#results tbody');
    var summary = document.getElementById('summary');
    var frame = document.getElementById('frame');
    var sandbox = document.getElementById('sandbox');

    var cancelled = false;
    var countEl = document.getElementById('count');

    function selected() {
        var scope = document.querySelector('input[name=scope]:checked').value;
        var pages = scope === 'factory'
            ? PAGES.filter(function (p) { return p.shell === 'factory'; })
            : PAGES;
        var widths = WIDTHS[document.querySelector('input[name=wset]:checked').value];
        return { pages: pages, widths: widths };
    }

    // Each load is a page fetch plus time to settle, so say how long this will
    // take before someone starts it and wonders whether it has hung.
    function updateEstimate() {
        var s = selected(), n = s.pages.length * s.widths.length;
        var mins = Math.max(1, Math.round(n * 1.1 / 60));
        countEl.textContent = s.pages.length + ' screens × ' + s.widths.length + ' widths = '
                            + n + ' checks, roughly ' + mins + ' minute' + (mins === 1 ? '' : 's');
    }
    Array.prototype.forEach.call(
        document.querySelectorAll('input[name=scope], input[name=wset]'),
        function (el) { el.addEventListener('change', updateEstimate); }
    );
    updateEstimate();

    function describe(el) {
        var s = el.tagName.toLowerCase();
        if (el.id) s += '#' + el.id;
        var cls = (typeof el.className === 'string' ? el.className : '').trim().split(/\s+/)[0];
        if (cls) s += '.' + cls;
        return s;
    }

    // The element actually pushing the page out. Anything inside a box that
    // scrolls is doing the right thing already, so it isn't the culprit.
    function culprit(doc, win) {
        function scrollsSomewhere(el) {
            var p = el.parentElement;
            while (p && p !== doc.body) {
                var ov = win.getComputedStyle(p).overflowX;
                if (ov === 'auto' || ov === 'scroll' || ov === 'hidden') return true;
                p = p.parentElement;
            }
            return false;
        }
        var best = null, bestW = 0;
        var all = doc.querySelectorAll('body *');
        for (var i = 0; i < all.length; i++) {
            var el = all[i], r = el.getBoundingClientRect();
            if (r.right <= win.innerWidth + 2 || r.width < 24) continue;
            if (scrollsSomewhere(el)) continue;
            if (r.width > bestW) { bestW = r.width; best = el; }
        }
        if (!best) return '(inside a scrolling box — nothing to fix)';
        var chain = [describe(best)], p = best.parentElement, n = 0;
        while (p && p !== doc.body && n < 2) { chain.push(describe(p)); p = p.parentElement; n++; }
        var cs = win.getComputedStyle(best);
        var extra = [];
        if (cs.minWidth !== '0px' && cs.minWidth !== 'auto') extra.push('min-width:' + cs.minWidth);
        return chain.reverse().join(' › ') + '  ' + Math.round(bestW) + 'px'
             + (extra.length ? '  (' + extra.join(', ') + ')' : '');
    }

    function measure(url, width) {
        return new Promise(function (resolve) {
            var done = false;
            function finish(v) { if (!done) { done = true; resolve(v); } }
            sandbox.style.width = width + 'px';
            frame.style.width = width + 'px';
            frame.style.height = '900px';
            var bail = setTimeout(function () { finish({ err: 'timed out' }); }, 12000);
            frame.onload = function () {
                // Let fonts, images and any on-load script settle before measuring.
                setTimeout(function () {
                    clearTimeout(bail);
                    try {
                        var doc = frame.contentDocument, win = frame.contentWindow;
                        var spill = Math.max(0, doc.documentElement.scrollWidth - win.innerWidth);
                        finish(spill > 2 ? { spill: spill, who: culprit(doc, win) } : { spill: 0 });
                    } catch (e) {
                        finish({ err: 'could not read the page' });
                    }
                }, 420);
            };
            frame.src = url;
        });
    }

    function addRow(page, width, res) {
        var tr = document.createElement('tr');
        var bad = res.spill > 0 || res.err;
        if (bad) tr.className = 'bad';
        tr.innerHTML =
            '<td class="page"></td><td class="w"></td><td class="w"></td><td></td>';
        tr.cells[0].textContent = page;
        tr.cells[1].textContent = width + 'px';
        tr.cells[2].textContent = res.err ? '—' : (res.spill > 0 ? '+' + res.spill + 'px' : 'ok');
        tr.cells[3].textContent = res.err ? res.err : (res.who || '');
        if (!bad) tr.cells[2].className += ' ok';
        tbody.appendChild(tr);
    }

    async function run() {
        cancelled = false;
        runBtn.disabled = true; stopBtn.disabled = false;
        tbody.innerHTML = ''; card.hidden = false; summary.textContent = '';
        var s = selected(), pages = s.pages, widths = s.widths;
        var total = pages.length * widths.length, done = 0, fails = 0, checked = 0;
        prog.max = total; prog.value = 0;

        for (var i = 0; i < pages.length && !cancelled; i++) {
            for (var j = 0; j < widths.length && !cancelled; j++) {
                statusEl.textContent = 'Checking ' + pages[i].file + ' at ' + widths[j] + 'px … ('
                                     + (done + 1) + ' of ' + total + ')';
                var res = await measure(pages[i].url, widths[j]);
                done++; prog.value = done; checked++;
                if (res.spill > 0 || res.err) { fails++; addRow(pages[i].file, widths[j], res); }
            }
        }

        // Reset the frame so a finished run isn't holding a page open.
        frame.onload = null; frame.src = 'about:blank';
        runBtn.disabled = false; stopBtn.disabled = true;
        statusEl.textContent = cancelled ? 'Stopped.' : 'Finished.';
        if (fails === 0 && !cancelled) {
            tbody.innerHTML = '<tr><td colspan="4" class="ok">Nothing overflows. '
                            + checked + ' checks across ' + pages.length + ' screens.</td></tr>';
        }
        summary.textContent = fails === 0
            ? (cancelled ? 'Stopped after ' + checked + ' checks — nothing had failed yet.' : '')
            : fails + ' of ' + checked + ' checks found a page wider than its window. '
              + 'Only failures are listed; everything else passed.';
    }

    runBtn.addEventListener('click', run);
    stopBtn.addEventListener('click', function () { cancelled = true; statusEl.textContent = 'Stopping …'; });
})();
</script>
</body>
</html>
