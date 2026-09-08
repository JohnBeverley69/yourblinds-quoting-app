<?php
declare(strict_types=1);

/**
 * READ-ONLY: for every product with a "Control Options" or "Wand Options"
 * option, report which systems the Corded / Centre Left choices cover. Lets me
 * confirm the per-system restore reached the tenant mirrors. No writes. Temp.
 * Super-admin only. /_verify_vertical_systems.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$systemsOf = static function (int $extraId, string $label) use ($pdo): string {
    $q = $pdo->prepare(
        "SELECT COALESCE(s.name, 'ALL') AS sn
           FROM product_extra_choices c
      LEFT JOIN product_systems s ON s.id = c.system_id
          WHERE c.product_extra_id = ? AND LOWER(TRIM(c.label)) = LOWER(?)
          ORDER BY sn"
    );
    $q->execute([$extraId, $label]);
    $names = $q->fetchAll(PDO::FETCH_COLUMN);
    return $names ? implode(', ', $names) : '(none)';
};

$rows = $pdo->query(
    "SELECT e.id AS eid, e.name AS ename, e.product_id AS pid, p.name AS pname, p.client_id AS cid
       FROM product_extras e
       JOIN products p ON p.id = e.product_id
      WHERE e.name IN ('Control Options', 'Wand Options')
      ORDER BY p.client_id, p.id, e.name"
)->fetchAll(PDO::FETCH_ASSOC);

echo "Per-system availability of Corded / Centre Left (all products)\n";
echo str_repeat('=', 70) . "\n";
$curPid = null;
foreach ($rows as $r) {
    $pid = (int) $r['pid'];
    if ($pid !== $curPid) { echo "\n#$pid  {$r['pname']}  (client {$r['cid']})\n"; $curPid = $pid; }
    if ($r['ename'] === 'Control Options') {
        echo "   Corded on: " . $systemsOf((int) $r['eid'], 'Corded') . "\n";
    } else {
        echo "   Centre Left on: " . $systemsOf((int) $r['eid'], 'Centre Left') . "\n";
    }
}
