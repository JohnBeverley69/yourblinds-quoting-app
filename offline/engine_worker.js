// Offline pricing engine — runs in a Web Worker on the tablet (offline/engine.js
// starts it). Hosts PHP 8.3 compiled to WebAssembly and runs the SAME PHP files
// the server uses (offline/_engine_files.php) against the tablet's SQLite copy
// of the catalogue, so a price with no signal is worked out exactly as the
// server would. Everything it needs is handed over by the page from the
// tablet's own storage, so it runs with no signal at all.
//
// Fast start (cheap tablets): building the catalogue database from the JSON
// download takes ~6–7 s on a £55 tablet, so it's done ONCE per catalogue
// version. The finished database file (+ its meta.json) is handed back to the
// page to keep, and on later starts it's simply loaded (no build).
//
// Messages in:  {id, type:'init', universalUrl, loaderSrc, wasmUrl, files,
//                 sqlite?: ArrayBuffer, meta?: string,   ← a kept, ready database
//                 catalogue?: string}                     ← or build from the JSON
//               {id, type:'catalogue', catalogue}          (rebuild with a newer copy)
//               {id, type:'preview', qs, canCosts, forAccountId}
// Messages out: {id, ok:true, result} | {id, ok:false, error}
//   init/catalogue results after a BUILD carry {sqlite: ArrayBuffer, meta: string}
//   for the page to keep.

let php = null;

async function start(msg) {
  const { PHP, loadPHPRuntime } = await import(msg.universalUrl);
  // The loader imports its .wasm the bundler way; point it at the copy on the tablet.
  const src = msg.loaderSrc.replace(
    /import dependencyFilename from '\.\/8_3_33\/php_8_3\.wasm';/,
    `const dependencyFilename = ${JSON.stringify(msg.wasmUrl)};`
  );
  const loader = await import(URL.createObjectURL(new Blob([src], { type: 'text/javascript' })));
  php = new PHP(await loadPHPRuntime(loader));
  for (const [path, code] of Object.entries(msg.files)) {
    php.mkdir('/app/' + path.split('/').slice(0, -1).join('/'));
    php.writeFile('/app/' + path, code);
  }
  if (msg.sqlite && msg.meta) {
    // A database this tablet built before: just put it back.
    php.mkdir('/data');
    php.writeFile('/data/catalogue.sqlite', new Uint8Array(msg.sqlite));
    php.writeFile('/data/meta.json', msg.meta);
    return { ok: true, restored: true };
  }
  return build(msg.catalogue);
}

async function build(catalogueText) {
  php.writeFile('/app/catalogue.json', catalogueText);
  const r = await php.run({ scriptPath: '/app/offline/_device_build.php' });
  try { php.unlink('/app/catalogue.json'); } catch (e) { /* already gone */ }
  const out = JSON.parse(r.text || '{}');
  if (out.error) throw new Error(out.error);
  // Hand the finished database back so it never has to be built again.
  const bytes = php.readFileAsBuffer('/data/catalogue.sqlite');
  out.sqlite = bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength);
  out.meta = php.readFileAsText('/data/meta.json');
  return out;
}

async function preview(msg) {
  php.writeFile('/app/req.json', JSON.stringify({
    qs: msg.qs, can_costs: !!msg.canCosts, for_account_id: msg.forAccountId || 0
  }));
  const r = await php.run({ scriptPath: '/app/offline/_device_preview.php' });
  if (!r.text) throw new Error(r.errors || 'No answer from the offline engine.');
  return JSON.parse(r.text);
}

// One request at a time — PHP is single-threaded.
let chain = Promise.resolve();
self.onmessage = (ev) => {
  const msg = ev.data;
  chain = chain.then(async () => {
    try {
      let result;
      if (msg.type === 'init') result = await start(msg);
      else if (!php) throw new Error('Offline engine not started.');
      else if (msg.type === 'catalogue') result = await build(msg.catalogue);
      else if (msg.type === 'preview') result = await preview(msg);
      else throw new Error('Unknown request ' + msg.type);
      // Move (not copy) a database file back to the page.
      self.postMessage({ id: msg.id, ok: true, result }, result && result.sqlite ? [result.sqlite] : []);
    } catch (e) {
      self.postMessage({ id: msg.id, ok: false, error: String((e && e.message) || e) });
    }
  });
};
