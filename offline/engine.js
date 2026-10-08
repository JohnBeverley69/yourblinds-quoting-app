// Offline prices on the tablet — window.ybEngine.
//
// "Set up this device for offline" downloads, once, over WiFi:
//   • PHP 8.3 as WebAssembly (the same build as the /offline/proof.php check),
//   • the pricing code itself (offline/engine_bundle.php: the server's own files),
//   • this user's catalogue (offline/catalogue.php).
// It keeps them in the browser's storage (Cache Storage) for THIS user. When a
// live-price request fails for lack of signal, the quote screen asks
// ybEngine.preview() instead. That runs the server's pricing code in a Web
// Worker against the tablet's copy, so the price matches what the server will
// say when the blind is sent (the server still re-prices it then).
//
// Kept fresh: while there's signal, the catalogue is re-checked at most every
// 30 minutes (a cheap 304 when unchanged). The copy also expires each day, so
// promotions stay right, and the status shows its date.
(function () {
  'use strict';
  if (window.ybEngine) return;

  var V        = '3.1.55';
  var PHPWASM  = 'https://cdn.jsdelivr.net/npm/@php-wasm/web-8-3@' + V + '/asyncify/';
  var WASM_URL = PHPWASM + '8_3_33/php_8_3.wasm';
  var LOADER   = PHPWASM + 'php_8_3.js';
  var ESM      = 'https://esm.sh';
  var UNIVERSAL = ESM + '/@php-wasm/universal@' + V + '?bundle';
  var WORKER   = '/offline/engine_worker.js';
  var CACHE    = 'yb-engine-v1';
  var CHECK_EVERY = 30 * 60 * 1000;
  // Screens saved on the tablet at set-up (and refreshed each check) so they
  // open with no signal. Quote pages are also saved as they're opened.
  var SAVE_ON_SETUP = ['/quote-builder/new.php', '/orders/index.php?scope=quotes&type=retail',
                       '/quote-builder/edit.php?offline_template=1'];   // the blank "new quote" screen

  function uid() { return (window.ybOffline && window.ybOffline.userId) || 0; }
  function k(name) { return 'yb.engine.' + name + '.' + uid(); }
  function bundleKey() { return '/offline/engine_bundle.php?u=' + uid(); }
  function catKey()    { return '/offline/catalogue.php?u=' + uid(); }
  function lsGet(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { return null; } }
  function lsSet(key, v) { try { localStorage.setItem(key, JSON.stringify(v)); } catch (e) {} }
  function online() { return window.ybOffline ? window.ybOffline.online : navigator.onLine; }
  function todayIso() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  // ---- ES module graph from esm.sh, kept on the tablet --------------------
  // esm.sh modules import each other by absolute path ("/node/buffer.mjs").
  // Keep every module in the graph, then load it back from storage with each
  // import rewritten to a local blob: URL, so it needs no network at all.
  // Absolute ("/node/x.mjs"), relative ("./chunk-X.mjs") and full esm.sh specifiers.
  var IMPORT_RE = /(\bfrom\s*|\bimport\s*\(?\s*)"((?:\/|\.\.?\/|https:\/\/esm\.sh\/)[^"]+)"/g;
  function abs(spec, base) { return spec.charAt(0) === '/' ? ESM + spec : new URL(spec, base).href; }
  function specsOf(src) {
    var out = [], m;
    IMPORT_RE.lastIndex = 0;
    while ((m = IMPORT_RE.exec(src))) if (out.indexOf(m[2]) === -1) out.push(m[2]);
    return out;
  }

  async function keepGraph(cache, url, seen) {
    if (seen[url]) return;
    seen[url] = 1;
    var res = await fetch(url, { mode: 'cors' });
    if (!res.ok) throw new Error('Could not download ' + url + ' (' + res.status + ')');
    // Relative imports resolve against where the module really came from
    // (esm.sh redirects to a pinned build path).
    var base = res.url || url;
    var text = await res.clone().text();
    await cache.put(url, res);
    lsSet(k('base.' + url), base);
    for (var s of specsOf(text)) await keepGraph(cache, abs(s, base), seen);
  }
  async function graphUrl(cache, url, memo) {
    if (memo[url]) return memo[url];
    memo[url] = url;   // a cycle falls back to the network URL (none in practice)
    var hit = await cache.match(url);
    if (!hit) throw new Error('Offline files incomplete — set this device up again with signal.');
    var base = lsGet(k('base.' + url)) || url;
    var text = await hit.text();
    for (var s of specsOf(text)) {
      var local = await graphUrl(cache, abs(s, base), memo);
      text = text.split('"' + s + '"').join('"' + local + '"');
    }
    return (memo[url] = URL.createObjectURL(new Blob([text], { type: 'text/javascript' })));
  }

  // ---- Downloads ----------------------------------------------------------
  async function keep(cache, url, key) {
    var res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
    if (res.status === 401) throw new Error('Signed out — sign in again first.');
    if (!res.ok) throw new Error('Could not download ' + url + ' (' + res.status + ')');
    var copy = res.clone();   // put() consumes the body
    await cache.put(key || url, res);
    return copy;
  }

  async function downloadCatalogue(cache, force) {
    var meta = lsGet(k('meta')) || {};
    var headers = {};
    if (!force && meta.version) headers['If-None-Match'] = '"' + meta.version + '"';
    var res = await fetch('/offline/catalogue.php', { credentials: 'same-origin', cache: 'no-store', headers: headers });
    if (res.status === 304) { meta.checkedAt = Date.now(); lsSet(k('meta'), meta); return false; }
    if (res.status === 401) throw new Error('Signed out — sign in again first.');
    if (!res.ok) throw new Error('Could not download the price list (' + res.status + ')');
    var text = await res.text();
    var cat = JSON.parse(text);
    await cache.put(catKey(), new Response(text, { headers: { 'Content-Type': 'application/json' } }));
    lsSet(k('meta'), { version: cat.version, today: cat.today, savedAt: Date.now(), checkedAt: Date.now() });
    return text;
  }

  // ---- Product + fabric pickers, kept for no-signal use -------------------
  // The quote form's pickers come from api/product-data.php and
  // api/fabrics-search.php. Every product's copy is saved here at set-up and on
  // each price update, so ANY product can be picked with no signal, not just
  // the ones someone happened to open earlier (John, £55 tablet: "Vertical
  // blinds — system failed to load"). In Cache Storage, not localStorage: a
  // big range's fabric list is too large for localStorage.
  function pickerKey(kind, pid) { return '/offline/pickers/' + kind + '?u=' + uid() + '&p=' + pid; }
  async function activeProductIds(cache) {
    var hit = await cache.match(catKey());
    if (!hit) return [];
    var cat = JSON.parse(await hit.text());
    var cols = Object.keys((cat.schema || {}).products || {});
    var iId = cols.indexOf('id'), iActive = cols.indexOf('active');
    return ((cat.data || {}).products || [])
      .filter(function (r) { return iActive < 0 || Number(r[iActive]) === 1; })
      .map(function (r) { return r[iId]; });
  }
  async function keepPickers(cache, progress) {
    var ids = await activeProductIds(cache);
    var saved = 0;
    for (var i = 0; i < ids.length; i++) {
      var pid = ids[i];
      if (progress) progress('Saving the product lists… ' + (i + 1) + ' of ' + ids.length);
      try {
        var pd = await fetch('/quote-builder/api/product-data.php?product_id=' + encodeURIComponent(pid) + '&_=' + Date.now(),
                             { credentials: 'same-origin', cache: 'no-store' });
        var pdText = pd.ok ? await pd.text() : '';
        if (pdText && !JSON.parse(pdText).error) {
          await cache.put(pickerKey('pd', pid), new Response(pdText, { headers: { 'Content-Type': 'application/json' } }));
        }
        var fb = await fetch('/quote-builder/api/fabrics-search.php?product_id=' + encodeURIComponent(pid)
                             + '&q=&limit=2000&_=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' });
        if (fb.ok) {
          await cache.put(pickerKey('fab', pid), new Response(await fb.text(), { headers: { 'Content-Type': 'application/json' } }));
          if (pdText) saved++;
        }
      } catch (e) { /* signal dropped: the rest are saved on the next update */ }
    }
    // Shown in the status line, and "never saved" makes the next page with
    // signal save them automatically (tablets set up before this existed).
    lsSet(k('pickers'), { saved: saved, total: ids.length, at: Date.now() });
  }
  var pickers = {
    productData: async function (pid) {
      try { var h = await (await caches.open(CACHE)).match(pickerKey('pd', pid)); return h ? await h.json() : null; }
      catch (e) { return null; }
    },
    fabrics: async function (pid) {
      try { var h = await (await caches.open(CACHE)).match(pickerKey('fab', pid)); return h ? ((await h.json()).fabrics || []) : []; }
      catch (e) { return []; }
    }
  };

  async function setup(progress) {
    progress = progress || function () {};
    if (!online()) throw new Error('Needs signal (WiFi is best) to set up.');
    if (navigator.storage && navigator.storage.persist) { try { await navigator.storage.persist(); } catch (e) {} }
    var cache = await caches.open(CACHE);
    progress('Downloading the pricing engine (about 20 MB, once)…');
    await keepGraph(cache, UNIVERSAL, {});
    await keep(cache, LOADER);
    await keep(cache, WASM_URL);
    await keep(cache, WORKER);
    progress('Downloading the pricing code…');
    await keep(cache, '/offline/engine_bundle.php', bundleKey());
    progress('Downloading your price list…');
    await downloadCatalogue(cache, true);
    await keepPickers(cache, progress);
    lsSet(k('enabled'), 1);
    progress('Checking it works…');
    stopWorker();
    var built = await startWorker();
    // Quote pages open with no signal too: switch on the page-saving helper and
    // save the screens a salesperson starts from.
    if (window.ybOffline && ybOffline.swEnable) {
        progress('Saving the quote screens on this device…');
        // Plus the quote that's open now, if it is one (set-up can start from any page).
        var here = /^\/quote-builder\/edit\.php$/.test(location.pathname) && /[?&]id=\d+/.test(location.search)
          ? [location.pathname + location.search] : [];
        await ybOffline.swEnable(SAVE_ON_SETUP.concat(here));
    }
    return built;
  }

  async function refresh(force, progress) {
    if (!enabled() || !online()) return;
    var meta = lsGet(k('meta')) || {};
    var pk = lsGet(k('pickers'));
    var pickersMissing = !pk || pk.saved < pk.total;
    if (!force && !pickersMissing && meta.checkedAt && Date.now() - meta.checkedAt < CHECK_EVERY && meta.today === todayIso()) return;
    try {
      var cache = await caches.open(CACHE);
      // Pricing code: small; re-fetch and restart the engine if it changed.
      var old = await cache.match(bundleKey());
      var oldVer = old ? (await old.json()).version : '';
      var res = await keep(cache, '/offline/engine_bundle.php', bundleKey());
      var newVer = (await res.clone().json()).version;
      // Our own worker script is tiny: always take the latest.
      var oldWorker = await cache.match(WORKER);
      var oldWorkerSrc = oldWorker ? await oldWorker.text() : '';
      var newWorkerSrc = await (await keep(cache, WORKER)).text();
      if (newWorkerSrc !== oldWorkerSrc) newVer += '+w';
      // Anything the browser threw away (storage pressure) comes back now.
      for (var u of [LOADER, WASM_URL]) { if (!(await cache.match(u))) await keep(cache, u); }
      if (!(await cache.match(UNIVERSAL))) await keepGraph(cache, UNIVERSAL, {});
      var text = await downloadCatalogue(cache, force);
      // Prices changed, "Update now", or the lists were never (fully) saved:
      // save the product + fabric lists too.
      if (text || force || pickersMissing) await keepPickers(cache, progress);
      if (newVer !== oldVer) stopWorker();
      else if (text && worker) await keepDatabase(cache, await call('catalogue', { catalogue: text }));
      // New prices or new pricing code: build the database NOW, while there's
      // signal and nobody's waiting, not at the first no-signal price.
      if ((text && !worker) || newVer !== oldVer) startWorker().catch(function () {});
      // Keep the starting screens' saved copies current too.
      if (window.ybOffline && ybOffline.swEnable) ybOffline.swEnable(SAVE_ON_SETUP);
      changed();
    } catch (e) {
      console.warn('Offline price list not refreshed:', e);
    }
  }

  // ---- The worker ---------------------------------------------------------
  var worker = null, starting = null, seq = 0, pending = {}, bundle = null;
  var ready = false, lastStartMs = 0;
  function call(type, payload, transfer) {
    return new Promise(function (resolve, reject) {
      var id = ++seq;
      pending[id] = { resolve: resolve, reject: reject };
      worker.postMessage(Object.assign({ id: id, type: type }, payload), transfer || []);
    });
  }
  function stopWorker() {
    if (worker) worker.terminate();
    worker = null; starting = null; bundle = null; ready = false;
    Object.keys(pending).forEach(function (id) { pending[id].reject(new Error('Offline engine restarted.')); delete pending[id]; });
  }

  // The built catalogue database, kept per user + catalogue version, so a
  // tablet builds it once (on WiFi, at set-up / when prices change) instead of
  // every time the engine starts: ~6–7 s saved per start on a £55 tablet.
  function dbKey(version)   { return '/offline/catalogue.sqlite?u=' + uid() + '&v=' + version; }
  function metaKey(version) { return '/offline/catalogue.meta?u=' + uid() + '&v=' + version; }
  async function keepDatabase(cache, built) {
    var version = (lsGet(k('meta')) || {}).version;
    if (!built || !built.sqlite || !version) return;
    await cache.put(dbKey(version), new Response(built.sqlite));
    await cache.put(metaKey(version), new Response(built.meta));
    // Older versions of this user's database aren't needed any more.
    var prefix = location.origin + '/offline/catalogue.';
    for (var req of await cache.keys()) {
      var u = new URL(req.url);
      if (req.url.indexOf(prefix) === 0 && u.searchParams.get('u') === String(uid())
          && u.searchParams.get('v') !== version) await cache.delete(req);
    }
  }

  function startWorker() {
    if (starting) return starting;
    starting = (async function () {
      var t0 = performance.now();
      var cache = await caches.open(CACHE);
      var get = async function (key) {
        var hit = await cache.match(key);
        if (!hit) throw new Error('Offline files missing — set this device up again with signal.');
        return hit;
      };
      var workerSrc = await (await get(WORKER)).text();
      bundle = await (await get(bundleKey())).json();
      var msg = {
        universalUrl: await graphUrl(cache, UNIVERSAL, {}),
        loaderSrc:    await (await get(LOADER)).text(),
        wasmUrl:      URL.createObjectURL(await (await get(WASM_URL)).blob()),
        files:        bundle.files
      };
      // A database already built for this catalogue version? Load that instead.
      var version = (lsGet(k('meta')) || {}).version;
      var keptDb = version ? await cache.match(dbKey(version)) : null;
      var keptMeta = keptDb ? await cache.match(metaKey(version)) : null;
      var transfer = [];
      if (keptDb && keptMeta) {
        msg.sqlite = await keptDb.arrayBuffer();
        msg.meta = await keptMeta.text();
        transfer = [msg.sqlite];
      } else {
        msg.catalogue = await (await get(catKey())).text();
      }
      worker = new Worker(URL.createObjectURL(new Blob([workerSrc], { type: 'text/javascript' })), { type: 'module' });
      worker.onmessage = function (ev) {
        var p = pending[ev.data.id];
        if (!p) return;
        delete pending[ev.data.id];
        ev.data.ok ? p.resolve(ev.data.result) : p.reject(new Error(ev.data.error));
      };
      worker.onerror = function (ev) { console.error('Offline engine:', ev.message); };
      var result = await call('init', msg, transfer);
      if (result && result.sqlite) await keepDatabase(cache, result);
      ready = true;
      lastStartMs = Math.round(performance.now() - t0);
      changed();
      return result;
    })();
    starting.catch(function () { starting = null; });
    return starting;
  }

  // qs: the preview.php query string. Resolves to preview.php's JSON + offline:true.
  async function preview(qs) {
    await startWorker();
    var accountId = 0;
    if (bundle && bundle.is_super_admin) accountId = parseInt(new URLSearchParams(qs).get('account_id') || '0', 10) || 0;
    return call('preview', { qs: qs, canCosts: !!(bundle && bundle.can_costs), forAccountId: accountId });
  }

  function enabled() { return !!lsGet(k('enabled')) && !!window.caches && !!window.Worker; }
  function status() {
    var meta = lsGet(k('meta')) || {};
    return { enabled: enabled(), today: meta.today || '', savedAt: meta.savedAt || 0,
             stale: !!meta.today && meta.today < todayIso() };
  }
  async function turnOff() {
    stopWorker();
    // Saved quote pages go too (agreed: "Turn off wipes the lot"). Blinds still
    // waiting to send are NOT touched: that's unsent work, not a saved copy.
    if (window.ybOffline && ybOffline.swWipe) { try { await ybOffline.swWipe(); } catch (e) {} }
    try { localStorage.removeItem(k('enabled')); localStorage.removeItem(k('meta')); } catch (e) {}
    // "Turn off wipes the lot": the price list, the pricing code and the engine
    // itself. Setting up again downloads them afresh.
    try { await caches.delete(CACHE); } catch (e) {}
    try {
      Object.keys(localStorage).forEach(function (key) { if (key.indexOf('yb.engine.') === 0) localStorage.removeItem(key); });
    } catch (e) {}
    changed();
  }

  // ---- Status + switch ------------------------------------------------------
  var mounts = [], watchers = [];
  function changed() {
    mounts.forEach(render);
    watchers.forEach(function (fn) { try { fn(); } catch (e) {} });
  }
  function fmtDay(iso) {
    if (!iso) return '';
    var d = new Date(iso + 'T12:00:00');
    return d.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' });
  }
  function render(el) {
    var s = status();
    el.innerHTML = '';
    el.className = 'yb-engine-status';
    var txt = document.createElement('span');
    var btns = [];
    if (!s.enabled) {
      txt.textContent = 'Prices with no signal: not set up on this device.';
      btns.push(['Set up for offline', async function (b) {
        b.disabled = true;
        try {
          var r = await setup(function (m) { txt.textContent = m; });
          txt.textContent = '✓ Ready — ' + (r.rows || 0).toLocaleString() + ' prices kept on this device.';
          setTimeout(changed, 2500);
        } catch (e) { txt.textContent = 'Not set up: ' + e.message; b.disabled = false; }
      }]);
    } else {
      var pk = lsGet(k('pickers'));
      var listsOk = pk && pk.total && pk.saved >= pk.total;
      txt.textContent = (s.stale
        ? '⚠ Offline prices are from ' + fmtDay(s.today) + ' — update them when you have signal.'
        : '✓ Works offline — prices from ' + fmtDay(s.today) + '.')
        + (listsOk ? ' ' + pk.saved + ' products and their fabrics saved.'
                   : ' ⚠ Product lists not fully saved yet — tap Update now with signal.')
        + (ready && lastStartMs ? ' Engine ready (started in ' + (lastStartMs / 1000).toFixed(1) + ' s).' : '');
      if (s.stale || !listsOk) el.classList.add('is-stale');
      btns.push(['Update now', async function (b) {
        // Updating needs signal: say so instead of silently doing nothing.
        if (!online()) {
          txt.textContent = 'No signal — Update now needs WiFi or a signal. Turn flight mode off, reload the page, then try again.';
          return;
        }
        b.disabled = true; txt.textContent = 'Updating…';
        await refresh(true, function (m) { txt.textContent = m; });
        changed();
      }]);
      btns.push(['Turn off', function () {
        if (confirm('Stop keeping prices on this device? You can set it up again any time with signal.')) turnOff();
      }]);
    }
    el.appendChild(txt);
    btns.forEach(function (b) {
      var btn = document.createElement('button');
      btn.type = 'button'; btn.textContent = b[0];
      btn.addEventListener('click', function () { b[1](btn); });
      el.appendChild(btn);
    });
  }
  function mountStatus(el) { if (!el) return; mounts.push(el); render(el); }

  var st = document.createElement('style');
  st.textContent = '.yb-engine-status{display:flex;flex-wrap:wrap;gap:8px;align-items:center;font-size:13px;color:#4b5563;margin:0 0 10px}'
    + '.yb-engine-status button{padding:3px 10px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;cursor:pointer;font:inherit}'
    + '.yb-engine-status.is-stale{color:#8a4b00}';
  document.head.appendChild(st);

  window.ybEngine = {
    enabled: enabled, status: status, setup: setup, refresh: refresh, preview: preview,
    turnOff: turnOff, mountStatus: mountStatus,
    onChange: function (fn) { watchers.push(fn); },
    pickers: pickers,
    // True once the engine is running on this page (the first offline price is instant).
    isReady: function () { return ready; },
    lastStartMs: function () { return lastStartMs; },
    warm: function () { return enabled() ? startWorker() : Promise.resolve(); }
  };

  // Keep the copy current while there's signal.
  if (enabled()) setTimeout(function () { refresh(false); }, 4000);

  // Warm up on a quote page as soon as there's no signal (opened with none, or
  // it drops), so the engine is running before anyone types a size. With
  // signal it isn't started at all: no point loading a cheap tablet for nothing.
  if (enabled() && location.pathname === '/quote-builder/edit.php') {
    var warmIfOffline = function () { if (!online() && !starting) startWorker().catch(function () {}); };
    setTimeout(warmIfOffline, 300);
    if (window.ybOffline && ybOffline.onChange) ybOffline.onChange(warmIfOffline);
  }
})();
