<?php
declare(strict_types=1);

/**
 * Factory · Scan log.
 *
 * Every scan-in, newest first: when, which scanner, which blind and part, and
 * whether it landed. Three jobs in one page —
 *   - makes the scanner id (the &s= in each scanner's URL) actually visible,
 *   - the "true factory logging" for when the admin page isn't open,
 *   - the window we watch when a new scanner first fires, since a code it can't
 *     parse is logged with exactly what it sent.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireFactory();

$pdo = db();

$ready = true;
try { $pdo->query('SELECT 1 FROM factory_scan_log LIMIT 0'); }
catch (Throwable $e) { $ready = false; }

$rows = [];
if ($ready) {
    $rows = $pdo->query(
        "SELECT sl.created_at, sl.code, sl.stream_digit, sl.result, sl.detail, sl.source,
                q.quote_number, qi.line_no, qi.product_name_snapshot
           FROM factory_scan_log sl
           LEFT JOIN quote_items qi ON qi.id = sl.quote_item_id
           LEFT JOIN quotes q       ON q.id = qi.quote_id
          ORDER BY sl.id DESC
          LIMIT 300"
    )->fetchAll(PDO::FETCH_ASSOC);
}

// Each result's pill: [label, text colour, background].
$RES = [
    'ok'        => ['done',       '#166534', '#dcfce7'],
    'already'   => ['already',    '#3730a3', '#e0e7ff'],
    'dup'       => ['double tap', '#6b7280', '#e5e7eb'],
    'not_found' => ['not on floor','#991b1b', '#fee2e2'],
    'bad_code'  => ['unreadable', '#92600a', '#fef3c7'],
    'bad_key'   => ['bad key',    '#991b1b', '#fee2e2'],
    'error'     => ['error',      '#991b1b', '#fee2e2'],
];
$partOf = static fn (?int $d): string => $d === null ? '' : ($d === 0 ? 'whole blind' : 'part ' . $d);
$fmt = static function (?string $ts): string {
    if (!$ts) return '';
    try { return (new DateTimeImmutable($ts))->format('j M, H:i:s'); }
    catch (Throwable $e) { return (string) $ts; }
};

$factoryTitle = 'Scan log';
$factoryNav   = 'scan';
$factoryWide  = true;
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
  .sl-h { font-size:1.5rem; font-weight:700; margin:0 0 .3rem; }
  .sl-sub { color:var(--text-muted,#667); margin:0 0 1.1rem; font-size:.92rem; }
  .sl-empty { background:var(--bg-subtle,#f8fafc); border:1px dashed var(--border,#e5e7eb); border-radius:12px; padding:1.75rem; color:var(--text-faint,#94a3b8); text-align:center; }
  .sl-tw { border:1px solid var(--border,#e5e7eb); border-radius:12px; background:var(--bg-card,#fff); overflow-x:auto; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  table.sl { width:100%; border-collapse:collapse; }
  .sl th { text-align:left; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint,#94a3b8); font-weight:700; padding:.5rem .7rem; background:var(--bg-subtle,#f8fafc); border-bottom:1px solid var(--border,#e5e7eb); white-space:nowrap; }
  .sl td { padding:.45rem .7rem; border-bottom:1px solid var(--border,#eef1f5); font-size:.875rem; vertical-align:top; }
  .sl tr:last-child td { border-bottom:none; }
  .sl .when { color:var(--text-muted,#667); white-space:nowrap; font-variant-numeric:tabular-nums; }
  .sl .code { font-family:ui-monospace,Consolas,monospace; font-variant-numeric:tabular-nums; }
  .sl .src { font-weight:600; }
  .sl .src.none { color:var(--text-faint,#94a3b8); font-weight:400; }
  .sl .ref { font-weight:700; font-variant-numeric:tabular-nums; white-space:nowrap; }
  .sl .detail { color:var(--text-muted,#667); }
  .respill { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding:.15rem .55rem; border-radius:999px; white-space:nowrap; }
  .sl-bar { display:flex; gap:.9rem; align-items:baseline; margin:0 0 1rem; }
  .sl-refresh { font-size:.85rem; color:var(--text-muted,#667); }
</style>

<div class="sl-bar">
    <h1 class="sl-h">Scan log</h1>
    <span class="sl-refresh" id="sl-refresh">live &mdash; refreshes every 15s</span>
</div>
<p class="sl-sub">Every scan the benches send, newest first. The <strong>scanner</strong> column is the id baked into each scanner's URL, so you can see which bench a scan came from without anyone logging in.</p>

<?php if (!$ready): ?>
    <div class="sl-empty">Scan logging isn't set up yet &mdash; run <code>/migrate_factory_scan_in.php</code>.</div>
<?php elseif (!$rows): ?>
    <div class="sl-empty">No scans yet. When a WiFi scanner fires a worksheet's barcode, it lands here.</div>
<?php else: ?>
    <div class="sl-tw">
    <table class="sl">
        <thead><tr><th>When</th><th>Result</th><th>Scanner</th><th>Blind</th><th>Part</th><th>Code</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $res  = (string) $r['result'];
            $pill = $RES[$res] ?? [$res, '#334155', '#e2e8f0'];
            $ref  = $r['quote_number'] !== null ? ($r['quote_number'] . '-' . (int) $r['line_no']) : '';
        ?>
            <tr>
                <td class="when"><?= e($fmt($r['created_at'])) ?></td>
                <td><span class="respill" style="color:<?= e($pill[1]) ?>;background:<?= e($pill[2]) ?>"><?= e($pill[0]) ?></span></td>
                <td class="src <?= trim((string) $r['source']) === '' ? 'none' : '' ?>"><?= e(trim((string) $r['source']) !== '' ? (string) $r['source'] : '—') ?></td>
                <td><span class="ref"><?= e($ref) ?></span><?php if ($r['product_name_snapshot']): ?> <span class="detail"><?= e((string) $r['product_name_snapshot']) ?></span><?php endif; ?></td>
                <td class="detail"><?= e($partOf($r['stream_digit'] !== null ? (int) $r['stream_digit'] : null)) ?></td>
                <td class="code"><?= e((string) $r['code']) ?></td>
                <td class="detail"><?= e((string) ($r['detail'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<script>
// A quiet auto-refresh — this is a monitor, nobody clicks buttons on it, so
// (unlike the floor) it's safe to just reload.
(function () {
    if (document.querySelector('.sl-empty')) return;   // nothing to watch yet
    setInterval(function () { if (!document.hidden) location.reload(); }, 15000);
})();
</script>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
