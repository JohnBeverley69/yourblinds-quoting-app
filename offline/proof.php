<?php
declare(strict_types=1);

/**
 * OFFLINE PRICING PROOF — super-admin only, read-only.
 *
 * Runs the REAL pricing engine (_partials/pricing_engine.php, unmodified) inside
 * this browser via PHP-in-WebAssembly, against a SQLite copy of one tenant's
 * catalogue, and checks every price against the live server penny-for-penny.
 * Open it on a tablet to see how quickly that tablet starts up and prices.
 *
 *   /offline/proof.php?client_id=N&n=300
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';
requireSuperAdmin();
header('Cache-Control: no-store');

$clientId = (int) ($_GET['client_id'] ?? 0);
$n        = max(1, min(2000, (int) ($_GET['n'] ?? 300)));

// The PHP the device runs, shipped as text. The repo is public, so this is no
// more than anyone can already read — and the page is super-admin only anyway.
$sources = [
    'pricing_engine.php'  => file_get_contents(__DIR__ . '/../_partials/pricing_engine.php'),
    'price_source.php'    => file_get_contents(__DIR__ . '/../_partials/price_source.php'),
    'parity_harness.php'  => file_get_contents(__DIR__ . '/_parity_harness.php'),
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Offline pricing proof</title>
<style>
  body { font: 15px/1.45 system-ui, sans-serif; margin: 0; padding: 16px; background: #f6f7f9; color: #1d2330; }
  h1 { font-size: 20px; margin: 0 0 4px; }
  .muted { color: #5b6475; }
  .card { background: #fff; border: 1px solid #dde1e8; border-radius: 10px; padding: 14px 16px; margin: 12px 0; max-width: 900px; }
  .big { font-size: 28px; font-weight: 700; }
  .ok { color: #137333; } .bad { color: #b3261e; }
  table { border-collapse: collapse; } td { padding: 3px 14px 3px 0; }
  pre { white-space: pre-wrap; word-break: break-word; font-size: 12px; background: #f1f3f6; padding: 10px; border-radius: 8px; max-height: 480px; overflow: auto; }
  input { width: 90px; } button { padding: 6px 14px; }
</style>
</head>
<body>
<h1>Offline pricing proof</h1>
<div class="muted">The real pricing engine running inside this browser (no server) — checked against the live server.</div>

<form class="card" method="get">
  Tenant id <input name="client_id" type="number" value="<?= $clientId ?: '' ?>" required>
  &nbsp; Test lines <input name="n" type="number" value="<?= $n ?>">
  &nbsp; <button>Run</button>
  &nbsp; <a href="parity_sample.php">list tenants</a>
</form>

<?php if ($clientId > 0): ?>
<div class="card"><div id="verdict" class="big">Running…</div><table id="steps"></table></div>
<div class="card"><b>Mismatches / full report</b><pre id="report"></pre></div>

<script type="module">
const SOURCES = <?= json_encode($sources, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
const SAMPLE  = <?= json_encode('parity_sample.php?client_id=' . $clientId . '&n=' . $n) ?>;
const V    = '3.1.55';
const WASM = `https://cdn.jsdelivr.net/npm/@php-wasm/web-8-3@${V}/asyncify/`;

const steps = document.getElementById('steps');
const step  = (label, value) => { const tr = steps.insertRow(); tr.insertCell().textContent = label; tr.insertCell().textContent = value; };
const t0 = performance.now();
const since = (t) => Math.round(performance.now() - t) + ' ms';

step('Device', `${navigator.hardwareConcurrency || '?'} cores, ${navigator.deviceMemory ? navigator.deviceMemory + ' GB' : '? GB'} memory`);
try {
  // 1. PHP runtime. The loader imports its .wasm the bundler way; swap that for
  //    a plain URL and load the module from a Blob (no build step needed).
  let t = performance.now();
  const { PHP, loadPHPRuntime } = await import(`https://esm.sh/@php-wasm/universal@${V}`);
  let src = await (await fetch(WASM + 'php_8_3.js')).text();
  src = src.replace(/import dependencyFilename from '\.\/8_3_33\/php_8_3\.wasm';/,
                    `const dependencyFilename = '${WASM}8_3_33/php_8_3.wasm';`);
  const loader = await import(URL.createObjectURL(new Blob([src], { type: 'text/javascript' })));
  const php = new PHP(await loadPHPRuntime(loader));
  step('Start PHP in the browser', since(t));

  // 2. Catalogue snapshot + the server's answers.
  t = performance.now();
  const res  = await fetch(SAMPLE, { credentials: 'same-origin' });
  const body = await res.text();
  if (!res.ok || body[0] !== '{') throw new Error('parity_sample.php: HTTP ' + res.status + ' ' + body.slice(0, 200));
  step('Download catalogue + test lines', `${(body.length / 1048576).toFixed(1)} MB in ${since(t)}`);

  // 3. Run the engine on the device.
  t = performance.now();
  php.mkdir('/app');
  for (const [name, code] of Object.entries(SOURCES)) php.writeFile('/app/' + name, code);
  php.writeFile('/app/data.json', body);
  const r = await php.run({ scriptPath: '/app/parity_harness.php' });
  step('Build catalogue + price every line', since(t));
  if (r.errors) step('PHP warnings', r.errors.slice(0, 500));

  const rep = JSON.parse(r.text);
  step('Catalogue rows', rep.catalogue_rows.toLocaleString());
  step('Building the catalogue', rep.ms_build_catalogue + ' ms');
  step('Pricing one line', `${rep.ms_per_line_avg} ms average, ${rep.ms_per_line_max} ms slowest`);
  step('Total', since(t0));
  const v = document.getElementById('verdict');
  v.textContent = `${rep.matched} / ${rep.cases} lines match the server to the penny`
    + ` (${rep.matched_priced} priced, ${rep.matched_errors} correctly refused)`;
  v.className = 'big ' + (rep.mismatched ? 'bad' : 'ok');
  document.getElementById('report').textContent = JSON.stringify(rep, null, 2);
} catch (e) {
  document.getElementById('verdict').textContent = 'Failed: ' + e.message;
  document.getElementById('verdict').className = 'big bad';
  document.getElementById('report').textContent = e.stack || String(e);
}
</script>
<?php endif; ?>
</body>
</html>
