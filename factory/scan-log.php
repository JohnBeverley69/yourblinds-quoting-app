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
require_once __DIR__ . '/../_partials/factory_kv.php';

requireFactory();

$pdo = db();

// Maintenance (delete/auto-prune) is a super-admin action — a bench (factory-role)
// login can watch the log but must not be able to wipe it.
$isSuper = function_exists('is_super_admin') && is_super_admin();

// Retention windows offered in the UI (days). Kept in one list so the POST
// validation and the dropdowns can't drift apart.
$RETAIN_OPTS = [30, 90, 180, 365];

// Super-admin maintenance actions (POST + CSRF): delete by age, clear all, or set
// the auto-prune window. Post/redirect/get so the 15s auto-refresh can't replay a
// delete.
if ($isSuper && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string) ($_POST['_action'] ?? '');
    try {
        if ($act === 'prune_age') {
            $days = (int) ($_POST['days'] ?? 0);
            if (in_array($days, $RETAIN_OPTS, true)) {
                $st = $pdo->prepare('DELETE FROM factory_scan_log WHERE created_at < (NOW() - INTERVAL ? DAY)');
                $st->execute([$days]);
                $_SESSION['flash_success'] = 'Deleted ' . $st->rowCount() . ' scan' . ($st->rowCount() === 1 ? '' : 's') . ' older than ' . $days . ' days.';
            }
        } elseif ($act === 'clear_all') {
            $n = (int) $pdo->exec('DELETE FROM factory_scan_log');
            $_SESSION['flash_success'] = 'Cleared the scan log (' . $n . ' row' . ($n === 1 ? '' : 's') . ' removed).';
        } elseif ($act === 'save_retention') {
            $days = (int) ($_POST['retention_days'] ?? 0);
            if ($days === 0 || in_array($days, $RETAIN_OPTS, true)) {
                fx_kv_set($pdo, 'scan_log_retention_days', (string) $days);
                $_SESSION['flash_success'] = $days === 0
                    ? 'Auto-delete turned off — scans are kept until you clear them.'
                    : 'Scans will now delete automatically once they are older than ' . $days . ' days.';
            }
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not update the scan log: ' . $e->getMessage();
    }
    header('Location: /factory/scan-log.php');
    exit;
}

$flashOk  = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
// Scope the log to THIS factory (white-label): a scan belongs to the factory that
// owns the scanned blind's product (owning factory = COALESCE(source_client_id,
// client_id)) — the same rule the queue routes on. Without this every factory saw
// every other factory's scans. Orphan scans (bad code / bad key — no blind to
// attribute) show only to the canonical factory.
$MASTER    = function_exists('current_factory_id') ? current_factory_id()
           : (function_exists('factory_client_id') ? factory_client_id() : 3);
$CANONICAL = function_exists('factory_client_id') ? factory_client_id() : 3;

$ready = true;
try { $pdo->query('SELECT 1 FROM factory_scan_log LIMIT 0'); }
catch (Throwable $e) { $ready = false; }

$rows = [];
if ($ready) {
    $where  = 'COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?';
    $params = [$MASTER];
    if ($MASTER === $CANONICAL) {
        $where .= ' OR sl.quote_item_id IS NULL';   // unattributable scans → primary factory
    }
    $st = $pdo->prepare(
        "SELECT sl.created_at, sl.code, sl.stream_digit, sl.result, sl.detail, sl.source,
                q.quote_number, qi.line_no, qi.product_name_snapshot
           FROM factory_scan_log sl
           LEFT JOIN quote_items qi ON qi.id = sl.quote_item_id
           LEFT JOIN quotes q       ON q.id = qi.quote_id
           LEFT JOIN products p     ON p.id = qi.product_id
          WHERE $where
          ORDER BY sl.id DESC
          LIMIT 300"
    );
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}

// Total logged rows + the current auto-prune window, for the maintenance panel.
$totalRows     = 0;
$retentionDays = 0;
if ($ready) {
    try { $totalRows = (int) $pdo->query('SELECT COUNT(*) FROM factory_scan_log')->fetchColumn(); }
    catch (Throwable $e) { $totalRows = 0; }
    $retentionDays = (int) fx_kv_get($pdo, 'scan_log_retention_days', '0');
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
  .sl-flash { border-radius:10px; padding:.6rem .9rem; margin:0 0 1rem; font-size:.9rem; }
  .sl-flash.ok  { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
  .sl-flash.err { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
  .sl-maint { border:1px solid var(--border,#e5e7eb); border-radius:12px; background:var(--bg-card,#fff); margin:0 0 1.1rem; }
  .sl-maint > summary { cursor:pointer; padding:.7rem .9rem; font-weight:600; font-size:.92rem; list-style:none; display:flex; gap:.6rem; align-items:center; }
  .sl-maint > summary::-webkit-details-marker { display:none; }
  .sl-maint > summary::before { content:'⚙'; opacity:.6; }
  .sl-maint .count { color:var(--text-faint,#94a3b8); font-weight:400; }
  .sl-maint-body { padding:.2rem .9rem 1rem; display:flex; flex-direction:column; gap:1rem; border-top:1px solid var(--border,#eef1f5); }
  .sl-mrow { display:flex; gap:.6rem; align-items:center; flex-wrap:wrap; }
  .sl-mrow label { font-size:.85rem; color:var(--text-muted,#667); }
  .sl-maint select { font:inherit; padding:.35rem .5rem; border:1px solid var(--border-strong,#cbd5e1); border-radius:8px; background:var(--bg-input,#fff); }
  .sl-maint .btn { font:inherit; font-size:.85rem; font-weight:600; cursor:pointer; border-radius:8px; padding:.4rem .8rem; border:1px solid var(--border-strong,#cbd5e1); background:var(--bg-subtle,#f8fafc); color:inherit; }
  .sl-maint .btn.danger { background:#fee2e2; color:#991b1b; border-color:#fecaca; }
  .sl-maint .hint { font-size:.8rem; color:var(--text-faint,#94a3b8); margin:0; }
</style>

<div class="sl-bar">
    <h1 class="sl-h">Scan log</h1>
    <span class="sl-refresh" id="sl-refresh">live &mdash; refreshes every 15s</span>
</div>
<p class="sl-sub">Every scan the benches send, newest first. The <strong>scanner</strong> column is the id baked into each scanner's URL, so you can see which bench a scan came from without anyone logging in.</p>

<?php if ($flashOk !== null): ?><div class="sl-flash ok" role="status"><?= e((string) $flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== null): ?><div class="sl-flash err" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

<?php if ($isSuper && $ready): ?>
    <!-- Housekeeping (super-admin). Collapsed by default; while it's open the
         15s auto-refresh pauses so a reload can't interrupt a click. Scan rows
         are pure diagnostics — nothing reads history for correctness — so
         deleting old ones is safe. -->
    <details class="sl-maint">
        <summary>Housekeeping <span class="count"><?= number_format($totalRows) ?> scan<?= $totalRows === 1 ? '' : 's' ?> logged<?= $retentionDays > 0 ? ' · auto-deleting after ' . (int) $retentionDays . ' days' : ' · kept forever' ?></span></summary>
        <div class="sl-maint-body">
            <form method="post" class="sl-mrow">
                <?= csrf_field() ?><input type="hidden" name="_action" value="save_retention">
                <label for="sl-ret">Auto-delete scans older than</label>
                <select name="retention_days" id="sl-ret">
                    <option value="0"<?= $retentionDays === 0 ? ' selected' : '' ?>>Never (keep forever)</option>
                    <?php foreach ($RETAIN_OPTS as $d): ?>
                        <option value="<?= $d ?>"<?= $retentionDays === $d ? ' selected' : '' ?>><?= $d ?> days</option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Save</button>
                <p class="hint" style="flex-basis:100%;margin:.1rem 0 0">Set-and-forget: old scans are then tidied away automatically as new ones come in.</p>
            </form>

            <form method="post" class="sl-mrow">
                <?= csrf_field() ?><input type="hidden" name="_action" value="prune_age">
                <label for="sl-prune">Delete now — scans older than</label>
                <select name="days" id="sl-prune">
                    <?php foreach ($RETAIN_OPTS as $d): ?>
                        <option value="<?= $d ?>"<?= $d === 90 ? ' selected' : '' ?>><?= $d ?> days</option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Delete these</button>
            </form>

            <form method="post" class="sl-mrow" onsubmit="return confirm('Delete every logged scan? This clears the whole scan log and can\'t be undone.');">
                <?= csrf_field() ?><input type="hidden" name="_action" value="clear_all">
                <button class="btn danger" type="submit">Clear the entire scan log</button>
                <span class="hint">Removes all <?= number_format($totalRows) ?> rows. Production is unaffected — this is just the log.</span>
            </form>
        </div>
    </details>
<?php endif; ?>

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
    setInterval(function () {
        if (document.hidden) return;
        // Don't reload out from under a super-admin using the housekeeping panel.
        if (document.querySelector('.sl-maint[open]')) return;
        location.reload();
    }, 15000);
})();
</script>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
