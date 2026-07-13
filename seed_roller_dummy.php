<?php
declare(strict_types=1);

/**
 * Seed: dummy ROLLER orders on the ABC test account — a full mixture of every
 * roller option (fascia, fit, scallops, bottom bar, control, chain, mech
 * colour, end caps, profile, braid…) so the worksheets/roll labels exercise
 * everything.
 *
 * Each generated order: 1..10 blinds (spread), each with a random system, size,
 * room, a REAL roller fabric valid for that system, and a system-valid choice
 * for every option group that has choices. Stamped ABC-TEST-#### /
 * customer_reference='DUMMY'. Structure is cloned from quote 237 (quote + item
 * + extra rows) and the roller product/fabric/extras swapped in.
 *
 *   /seed_roller_dummy.php            → make 50
 *   /seed_roller_dummy.php?n=10       → make 10
 *   /seed_roller_dummy.php?probe=1    → report what ABC's roller product has
 *   /seed_roller_dummy.php?delete=1   → remove the ROLLER dummies
 *
 * Super-admin only.
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;
$n = isset($_GET['n']) && ctype_digit((string) $_GET['n']) ? max(1, min(200, (int) $_GET['n'])) : 50;

$masterRoller = (int) $pdo->query("SELECT id FROM products WHERE client_id={$MASTER} AND name='Bev Roller Blinds' LIMIT 1")->fetchColumn();
if ($masterRoller === 0) { exit("No master 'Bev Roller Blinds'.\n"); }

$clientId = (int) ($pdo->query("SELECT client_id FROM quotes WHERE quote_number LIKE 'ABC-TEST-%' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0);
if ($clientId === 0) { $clientId = (int) ($pdo->query('SELECT client_id FROM quotes WHERE id = 237')->fetchColumn() ?: 0); }
if ($clientId === 0) { exit("Could not determine the ABC client.\n"); }

$rp = $pdo->prepare("SELECT id, name FROM products WHERE client_id = ? AND source_client_id = ? AND source_product_id = ? LIMIT 1");
$rp->execute([$clientId, $MASTER, $masterRoller]);
$rprow = $rp->fetch(PDO::FETCH_ASSOC);
if (!$rprow) { exit("ABC client {$clientId} has no roller product (push the catalogue first).\n"); }
$rollerPid  = (int) $rprow['id'];
$rollerName = (string) $rprow['name'];

// Systems, option groups (+ system-valid choices), fabrics.
$sysSt = $pdo->prepare('SELECT id, name FROM product_systems WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, id');
$sysSt->execute([$rollerPid, $clientId]);
$systems = $sysSt->fetchAll(PDO::FETCH_ASSOC);

$grpSt = $pdo->prepare('SELECT id, name FROM product_extras WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, id');
$grpSt->execute([$rollerPid, $clientId]);
$groups = $grpSt->fetchAll(PDO::FETCH_ASSOC);

$fabSt = $pdo->prepare('SELECT id, band_code, supplier_name, name, colour, code FROM product_options
                         WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY RAND() LIMIT 400');
$fabSt->execute([$rollerPid, $clientId]);
$fabrics = $fabSt->fetchAll(PDO::FETCH_ASSOC);

// ---- Probe -----------------------------------------------------------------
if (($_GET['probe'] ?? '0') !== '0' && ($_GET['probe'] ?? '') !== '') {
    echo "ABC client {$clientId}, roller product {$rollerPid} ({$rollerName})\n";
    echo 'Systems: ' . implode(', ', array_map(fn($s)=>$s['name'], $systems)) . "\n";
    echo 'Fabrics: ' . count($fabrics) . "\n";
    echo "Option groups (with choice counts):\n";
    $cc = $pdo->prepare('SELECT COUNT(*) FROM product_extra_choices WHERE product_extra_id = ? AND active = 1');
    foreach ($groups as $g) { $cc->execute([(int)$g['id']]); echo '  ' . $g['name'] . ': ' . (int)$cc->fetchColumn() . " choices\n"; }
    exit;
}

// ---- Delete (roller dummies only) ------------------------------------------
if (($_GET['delete'] ?? '0') !== '0' && ($_GET['delete'] ?? '') !== '') {
    $q = $pdo->prepare("SELECT DISTINCT q.id FROM quotes q JOIN quote_items i ON i.quote_id = q.id
                         WHERE q.client_id = ? AND q.customer_reference = 'DUMMY' AND q.quote_number LIKE 'ABC-TEST-%' AND i.product_id = ?");
    $q->execute([$clientId, $rollerPid]);
    $ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) { exit("No roller dummy orders to delete.\n"); }
    $in = implode(',', $ids);
    $pdo->beginTransaction();
    $pdo->exec("DELETE FROM quote_item_extras WHERE quote_item_id IN (SELECT id FROM quote_items WHERE quote_id IN ($in))");
    $pdo->exec("DELETE FROM quote_items WHERE quote_id IN ($in)");
    $pdo->exec("DELETE FROM quotes WHERE id IN ($in)");
    $pdo->commit();
    exit('Deleted ' . count($ids) . " roller dummy order(s).\n");
}

if (!$systems) { exit("Roller product {$rollerPid} has no systems on ABC.\n"); }
if (!$fabrics) { exit("Roller product {$rollerPid} has no fabrics on ABC.\n"); }

// ---- Structure to clone + helpers ------------------------------------------
$freshTokens = static function (array $row): array {
    foreach ($row as $k => $v) { if ($v !== null && stripos((string) $k, 'token') !== false) $row[$k] = bin2hex(random_bytes(32)); }
    return $row;
};
$insertRow = static function (PDO $pdo, string $table, array $row): int {
    unset($row['id']);
    $cols = array_keys($row);
    $sql  = 'INSERT INTO `' . $table . '` (' . implode(',', array_map(static fn ($c) => "`$c`", $cols)) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($row));
    return (int) $pdo->lastInsertId();
};

$tplQuote  = $pdo->query('SELECT * FROM quotes WHERE id = 237')->fetch(PDO::FETCH_ASSOC);
$baseItem  = $pdo->query('SELECT * FROM quote_items WHERE quote_id = 237 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$baseExtra = $pdo->query('SELECT * FROM quote_item_extras WHERE quote_item_id = ' . (int) $baseItem['id'] . ' ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$tplQuote || !$baseItem || !$baseExtra) { exit("Template quote 237 not available to clone structure from.\n"); }

// A system-valid choice for a group, cached per (extra, system).
$choiceCache = [];
$pickChoice = static function (int $extraId, int $sysId) use ($pdo, &$choiceCache) {
    $key = $extraId . '|' . $sysId;
    if (!array_key_exists($key, $choiceCache)) {
        $st = $pdo->prepare('SELECT id, label FROM product_extra_choices
                              WHERE product_extra_id = ? AND active = 1 AND (system_id IS NULL OR system_id = ?) ORDER BY id');
        $st->execute([$extraId, $sysId]);
        $choiceCache[$key] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $list = $choiceCache[$key];
    return $list ? $list[array_rand($list)] : null;
};

$rooms = ['Lounge', 'Bedroom', 'Kitchen', 'Bathroom', 'Hall', 'Office', 'Dining Room', 'Master Bedroom', 'Cloakroom', 'Study', 'Landing', 'Conservatory', 'Nursery', 'Utility'];

$maxNum = 0;
foreach ($pdo->query("SELECT quote_number FROM quotes WHERE quote_number LIKE 'ABC-TEST-%'") as $r) {
    if (preg_match('/(\d+)$/', (string) $r['quote_number'], $m)) $maxNum = max($maxNum, (int) $m[1]);
}

$made = 0; $blinds = 0;
$pdo->beginTransaction();
try {
    for ($i = 0; $i < $n; $i++) {
        $q = $tplQuote;
        $q['client_id']          = $clientId;
        $q['quote_number']       = 'ABC-TEST-' . str_pad((string) ($maxNum + $i + 1), 4, '0', STR_PAD_LEFT);
        $q['customer_reference'] = 'DUMMY';
        $q['created_at']         = date('Y-m-d H:i:s', time() - mt_rand(0, 14 * 86400));
        if (array_key_exists('updated_at', $q)) $q['updated_at'] = $q['created_at'];
        $q = $freshTokens($q);
        $newQid = $insertRow($pdo, 'quotes', $q);

        $lineCount = ($i % 10) + 1;
        for ($j = 0; $j < $lineCount; $j++) {
            $sys = $systems[array_rand($systems)];
            $fab = $fabrics[array_rand($fabrics)];

            $it = $baseItem;
            $it['quote_id']              = $newQid;
            $it['line_no']               = $j + 1;
            $it['quantity']              = 1;
            $it['product_id']            = $rollerPid;
            $it['product_name_snapshot'] = $rollerName;
            $it['system_id']             = (int) $sys['id'];
            $it['system_name_snapshot']  = (string) $sys['name'];
            $it['width_mm']              = mt_rand(40, 240) * 10;   // 400..2400
            $it['drop_mm']               = mt_rand(40, 300) * 10;   // 400..3000
            $it['room_name']             = $rooms[array_rand($rooms)];
            $it['option_id']                = (int) $fab['id'];
            $it['fabric_band_snapshot']     = $fab['band_code'];
            $it['fabric_supplier_snapshot'] = $fab['supplier_name'];
            $it['fabric_name_snapshot']     = $fab['name'];
            $it['fabric_colour_snapshot']   = $fab['colour'];
            $it['fabric_code_snapshot']     = $fab['code'];
            if (array_key_exists('notes', $it)) $it['notes'] = '';
            $it = $freshTokens($it);
            $newItemId = $insertRow($pdo, 'quote_items', $it);

            // A system-valid choice for every option group that has choices.
            foreach ($groups as $g) {
                $ch = $pickChoice((int) $g['id'], (int) $sys['id']);
                if (!$ch) continue;
                $ex = $baseExtra;
                $ex['quote_item_id']           = $newItemId;
                $ex['product_extra_id']        = (int) $g['id'];
                $ex['product_extra_choice_id'] = (int) $ch['id'];
                $ex['extra_name_snapshot']     = (string) $g['name'];
                $ex['choice_label_snapshot']   = (string) $ch['label'];
                if (array_key_exists('user_value', $ex)) $ex['user_value'] = null;
                $ex = $freshTokens($ex);
                $insertRow($pdo, 'quote_item_extras', $ex);
            }
            $blinds++;
        }
        $made++;
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit('FAILED (rolled back): ' . $e->getMessage() . "\n");
}

echo "Created {$made} roller orders ({$blinds} blinds) on ABC (client {$clientId}, product {$rollerPid} {$rollerName}).\n";
echo 'Numbered ABC-TEST-' . str_pad((string) ($maxNum + 1), 4, '0', STR_PAD_LEFT) . ' .. ABC-TEST-' . str_pad((string) ($maxNum + $made), 4, '0', STR_PAD_LEFT) . ".\n";
echo 'Full mix across ' . count($groups) . " option groups. Remove roller dummies with ?delete=1.\n";
