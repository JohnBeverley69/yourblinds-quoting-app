<?php
declare(strict_types=1);
/** TEMP read-only: inspect ABC's (client 19) appointments + quote linkage. Delete after use. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

// Columns present on appointments?
$cols = [];
foreach ($pdo->query("SHOW COLUMNS FROM appointments") as $c) $cols[] = $c['Field'];
echo "appointments columns: " . implode(', ', $cols) . "\n\n";

$hasKind = in_array('appt_kind', $cols, true);
$kindSel = $hasKind ? 'a.appt_kind' : "'?' AS appt_kind";

$sql = "SELECT a.id, a.client_id, $kindSel, a.status, a.quote_id,
               (a.quote_id IS NULL) AS q_null,
               EXISTS(SELECT 1 FROM quotes q WHERE q.id = a.quote_id) AS q_exists
          FROM appointments a
         WHERE a.client_id = 19
      ORDER BY a.id DESC";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "ABC (client 19) appointments: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  id {$r['id']} kind={$r['appt_kind']} status={$r['status']} quote_id="
       . ($r['quote_id'] === null ? 'NULL' : $r['quote_id'])
       . " quoteExists=" . ($r['quote_id'] === null ? '-' : ($r['q_exists'] ? 'yes' : 'NO')) . "\n";
}
