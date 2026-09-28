// YourBlinds offline helper (service worker). Only registered on a tablet that
// has been set up for offline (offline/engine.js → _partials/offline_guard.php).
//
// What it does:
//   • Quote pages (and the quote lists / new-quote form) are saved on the tablet
//     each time they open with signal, per signed-in user. With no signal the
//     saved copy is shown, marked with when it was saved. Kept 14 days.
//   • The app's own CSS/JS/images/fonts are kept so saved pages look right.
//   • Everything else goes to the network exactly as before. The app's form
//     posts and API calls are never touched (the page itself handles no-signal
//     for those: the outbox and the tablet price engine).
// "Turn off" on the tablet (or turning offline off) wipes every saved page.
//
// Agreed with John 2026-09-28: set-up tablets only · last 14 days · Turn off wipes.

const STATIC = 'yb-static-v1';
const KEEP_MS = 14 * 24 * 3600 * 1000;
const META = 'yb-sw-meta';

let currentUser = null;   // the user whose pages we save / serve
// Test / demo aid: {type:'simulate-offline', on:true} makes page loads behave as
// if there's no signal (a desktop browser can't be put in flight mode). Set from
// the page by ybOffline.simulate(); remembered across helper restarts.
let simulateOffline = null;
async function simulating() {
  if (simulateOffline === null) {
    const m = await (await caches.open(META)).match('/__yb_sim');
    simulateOffline = m ? (await m.text()) === '1' : false;
  }
  return simulateOffline;
}
async function net(req, init) {
  if (await simulating()) throw new TypeError('Failed to fetch (simulated no signal)');
  return fetch(req, init);
}

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

function pagesCache(uid) { return 'yb-pages-' + uid; }
async function getUser() {
  if (currentUser) return currentUser;
  const m = await (await caches.open(META)).match('/__yb_user');
  currentUser = m ? await m.text() : null;
  return currentUser;
}
async function setUser(uid) {
  currentUser = String(uid);
  await (await caches.open(META)).put('/__yb_user', new Response(currentUser));
}

// Pages worth keeping for no-signal use.
function keepable(url) {
  if (url.origin !== self.location.origin) return false;
  const p = url.pathname;
  if (p === '/quote-builder/edit.php') return url.searchParams.has('id') || url.searchParams.has('offline_template');
  return p === '/quote-builder/new.php' || p === '/orders/index.php';
}
function pageKey(url) {
  // One copy per quote: ?id=N (an &edit_item=M visit refreshes the same copy).
  const u = new URL(url.origin + url.pathname);
  for (const k of ['id', 'offline_template', 'scope', 'type']) {
    if (url.searchParams.has(k)) u.searchParams.set(k, url.searchParams.get(k));
  }
  return u.href;
}

// The page's own stylesheets and scripts (same site), kept alongside it. Without
// this a saved page opened with no signal is unstyled and its price engine
// script is missing, because a page loaded BEFORE the helper started never
// passed its files through here.
async function keepAssets(html, base) {
  const cache = await caches.open(STATIC);
  const re = /<(?:link[^>]+href|script[^>]+src)="([^"]+)"/gi;
  let m;
  while ((m = re.exec(html))) {
    try {
      const u = new URL(m[1].replace(/&amp;/g, '&'), base);
      if (u.origin !== self.location.origin || !/\.(css|js)$/i.test(u.pathname)) continue;
      if (u.pathname === '/offline/engine_worker.js' || (await cache.match(u.href))) continue;
      const r = await fetch(u.href, { credentials: 'same-origin' });
      if (r.ok) await cache.put(u.href, r);
    } catch (e) { /* no signal: next time */ }
  }
}

async function savePage(uid, key, res) {
  const html = await res.text();
  await keepAssets(html, key);
  const title = (html.match(/<title>([^<]*)<\/title>/i) || [])[1] || key;
  const cache = await caches.open(pagesCache(uid));
  await cache.put(key, new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8',
    'X-YB-Saved': String(Date.now()), 'X-YB-Title': encodeURIComponent(title.trim()) } }));
  // Forget anything older than 14 days.
  for (const req of await cache.keys()) {
    const hit = await cache.match(req);
    if (hit && Date.now() - Number(hit.headers.get('X-YB-Saved') || 0) > KEEP_MS) await cache.delete(req);
  }
}

async function savedCopy(uid, key) {
  const hit = await (await caches.open(pagesCache(uid))).match(key);
  if (!hit) return null;
  const saved = Number(hit.headers.get('X-YB-Saved') || 0);
  if (Date.now() - saved > KEEP_MS) return null;
  // Tell the page it's the saved copy (offline_guard shows a note).
  const html = (await hit.text()).replace(/<head([^>]*)>/i,
    `<head$1><script>window.__ybSavedCopy=${saved};</script>`);
  return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}

async function page(event, url) {
  const uid = await getUser();
  const key = pageKey(url);
  try {
    const res = await net(event.request);
    // Signed out (sent to the login page) or an error: never save that.
    if (uid && res.ok && !res.redirected && keepable(url)
        && (res.headers.get('Content-Type') || '').includes('text/html')) {
      event.waitUntil(savePage(uid, key, res.clone()));
    }
    return res;
  } catch (err) {
    if (uid) {
      const copy = await savedCopy(uid, key);
      if (copy) return copy;
    }
    return noSignalPage(uid);
  }
}

// The page to show with no signal when that page isn't saved on the tablet:
// a list of what IS saved, so the salesperson can carry on.
async function noSignalPage(uid) {
  let rows = '';
  if (uid) {
    const cache = await caches.open(pagesCache(uid));
    const items = [];
    for (const req of await cache.keys()) {
      const hit = await cache.match(req);
      const saved = Number(hit.headers.get('X-YB-Saved') || 0);
      if (Date.now() - saved > KEEP_MS) continue;
      const u = new URL(req.url);
      if (u.searchParams.has('offline_template')) continue;
      items.push({ href: u.pathname + u.search, title: decodeURIComponent(hit.headers.get('X-YB-Title') || u.pathname), saved });
    }
    items.sort((a, b) => b.saved - a.saved);
    rows = items.map((i) => `<li><a href="${i.href.replace(/"/g, '&quot;')}">${i.title.replace(/</g, '&lt;')}</a>`
      + ` <small>saved ${new Date(i.saved).toLocaleString([], { weekday: 'short', hour: '2-digit', minute: '2-digit' })}</small></li>`).join('');
  }
  return new Response(`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>No signal · YourBlinds</title><style>body{font:16px/1.5 system-ui,sans-serif;margin:0;padding:24px;background:#f6f7f9;color:#1d2330}
.card{max-width:640px;background:#fff;border:1px solid #dde1e8;border-radius:12px;padding:18px 20px}li{margin:8px 0}a{color:#1d4ed8}small{color:#6b7280}</style></head>
<body><div class="card"><h1 style="font-size:20px;margin:0 0 6px">No signal</h1>
<p style="margin:0 0 12px">This page isn’t saved on the tablet. ${rows ? 'These are:' : 'Nothing is saved on this tablet yet.'}</p>
<ul style="padding-left:18px">${rows}</ul>
<div id="prov"></div>
<p><a href="/quote-builder/new.php">Start a new quote</a> · <a href="javascript:history.back()">Go back</a></p></div>
<script>
// New quotes started on this tablet that haven't been created yet (offline_guard.php).
try {
  var p = JSON.parse(localStorage.getItem('yb.prov.${uid || 0}') || '{}'), html = '';
  Object.keys(p).forEach(function (k) {
    if (p[k].serverId) return;
    var n = (p[k].details || []).find(function (x) { return x[0] === 'end_customer_name'; });
    var a = document.createElement('a');
    a.href = '/quote-builder/edit.php?offline_template=1#p=' + encodeURIComponent(k);
    a.textContent = 'New quote for ' + ((n && n[1]) || '(no name yet)');
    html += '<li>' + a.outerHTML + ' <small>started on this tablet</small></li>';
  });
  if (html) document.getElementById('prov').innerHTML = '<p style="margin:12px 0 0">Started on this tablet, not sent yet:</p><ul style="padding-left:18px">' + html + '</ul>';
} catch (e) {}
</script></body></html>`,
    { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}

// The app's static files: show the kept copy at once, refresh it in the background.
async function staticFile(event) {
  const cache = await caches.open(STATIC);
  const hit = await cache.match(event.request);
  const refresh = net(event.request).then((res) => {
    if (res.ok || res.type === 'opaque') cache.put(event.request, res.clone());
    return res;
  }).catch(() => null);
  if (hit) { event.waitUntil(refresh); return hit; }
  return (await refresh) || new Response('', { status: 504 });
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;   // form posts: untouched
  const url = new URL(req.url);
  if (req.mode === 'navigate') {
    event.respondWith(page(event, url));
    return;
  }
  const own = url.origin === self.location.origin;
  // (engine_worker.js is kept by offline/engine.js itself, which must always
  // see the latest one, so it's left out here.)
  if ((own && /\.(css|js|png|jpe?g|gif|svg|webp|ico|woff2?)$/i.test(url.pathname) && url.pathname !== '/offline/engine_worker.js')
      || url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
    event.respondWith(staticFile(event));
  }
  // Everything else (APIs, uploads, PDFs…): straight to the network, as before.
});

self.addEventListener('message', (event) => {
  const msg = event.data || {};
  if (msg.type === 'user') event.waitUntil(setUser(msg.uid));
  if (msg.type === 'simulate-offline') {
    simulateOffline = !!msg.on;
    event.waitUntil(caches.open(META).then((c) => c.put('/__yb_sim', new Response(simulateOffline ? '1' : '0'))));
  }
  if (msg.type === 'save' && msg.urls) {
    // Save pages now (e.g. the blank quote screen for new quotes with no signal).
    event.waitUntil((async () => {
      const uid = await getUser();
      if (!uid) return;
      for (const href of msg.urls) {
        try {
          const url = new URL(href, self.location.origin);
          const res = await fetch(url.href, { credentials: 'same-origin', redirect: 'follow' });
          if (res.ok && !res.redirected) await savePage(uid, pageKey(url), res);
        } catch (e) { /* no signal: try again next time */ }
      }
    })());
  }
  if (msg.type === 'wipe') {
    event.waitUntil((async () => {
      for (const name of await caches.keys()) {
        if (name.startsWith('yb-pages-') || name === STATIC || name === META) await caches.delete(name);
      }
      currentUser = null;
    })());
  }
});
