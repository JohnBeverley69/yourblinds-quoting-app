<?php
declare(strict_types=1);

/**
 * Seed: one dummy ROLLER order on the ABC test account, to see the 102x76 roll
 * label with real roller data (cut values, fascia, scallop drop).
 *
 * Builds a single ABC-TEST-#### / customer_reference='DUMMY' order with a few
 * curated roller lines covering the cut behaviours (None/Senses/Grip Fix
 * fascias, Recess/Exact/Cloth fits, a scallop shape for the +400 drop). Clones
 * quote 237's quote + an item + an extra row for structural validity, then
 * swaps in the tenant roller product, a valid roller fabric, and the fascia /
 * fit / scallop extras looked up by label.
 *
 *   /seed_roller_dummy.php?probe=1   → report what ABC has (no writes)
 *   /seed_roller_dummy.php           → create the roller order
 *   /seed_roller_dummy.php?delete=1  → remove ABC-TEST roller dummies
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

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

// Master roller product (where build rules live).
$masterRoller = (int) $pdo->query("SELECT id FROM products WHERE client_id = {$MASTER} AND name = 'Bev Roller Blinds' LIMIT 1")->fetchColumn();
if ($masterRoller === 0) { exit("No master 'Bev Roller Blinds' (client {$MASTER}).\n"); }

// ABC client — the account the existing dummies live on.
$clientId = (int) ($pdo->query("SELECT client_id FROM quotes WHERE quote_number LIKE 'ABC-TEST-%' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0);
if ($clientId === 0) { $clientId = (int) ($pdo->query('SELECT client_id FROM quotes WHERE id = 237')->fetchColumn() ?: 0); }
if ($clientId === 0) { exit("Could not determine the ABC client.\n"); }

// The tenant's roller product (maps back to the master roller).
$rp = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND source_client_id = ? AND source_product_id = ? LIMIT 1");
$rp->execute([$clientId, $MASTER, $masterRoller]);
$rollerPid = (int) $rp->fetchColumn();
if ($rollerPid === 0) {
    $rp = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name LIKE '%Roller%' AND source_client_id = ? LIMIT 1");
    $rp->execute([$clientId, $MASTER]);
    $rollerPid = (int) $rp->fetchColumn();
}
if ($rollerPid === 0) { exit("ABC client {$clientId} has no roller product (catalogue not pushed?). Cannot build a roller order.\n"); }

// Systems, option groups + choices, fabrics for the tenant roller product.
$rollerName = (string) $pdo->query("SELECT name FROM products WHERE id = {$rollerPid}")->fetchColumn();

$sys = $pdo->prepare('SELECT id, name FROM product_systems WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, id');
$sys->execute([$rollerPid, $clientId]);
$systems = $sys->fetchAll(PDO::FETCH_ASSOC);

$grp = $pdo->prepare('SELECT id, name FROM product_extras WHERE product_id = ? AND client_id = ? AND active = 1');
$grp->execute([$rollerPid, $clientId]);
$groups = [];   // lower(name) => ['id'=>, choices=>[lower(label)=>['id'=>,'label'=>]]]
foreach ($grp->fetchAll(PDO::FETCH_ASSOC) as $g) {
    $ch = $pdo->prepare('SELECT id, label FROM product_extra_choices WHERE product_extra_id = ? AND active = 1');
    $ch->execute([(int) $g['id']]);
    $choices = [];
    foreach ($ch->fetchAll(PDO::FETCH_ASSOC) as $c) $choices[mb_strtolower(trim((string) $c['label']))] = ['id' => (int) $c['id'], 'label' => (string) $c['label']];
    $groups[mb_strtolower(trim((string) $g['name']))] = ['id' => (int) $g['id'], 'name' => (string) $g['name'], 'choices' => $choices];
}

$fab = $pdo->prepare('SELECT id, band_code, supplier_name, name, colour, code FROM product_options
                       WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY RAND() LIMIT 200');
$fab->execute([$rollerPid, $clientId]);
$fabrics = $fab->fetchAll(PDO::FETCH_ASSOC);

// ---- Probe -----------------------------------------------------------------
if (($_GET['probe'] ?? '') !== '' && ($_GET['probe'] ?? '0') !== '0') {
    echo "ABC client: {$clientId}\n";
    echo "Roller product: {$rollerPid} ({$rollerName})\n";
    echo 'Systems: ' . (count($systems) ? implode(', ', array_map(static fn ($s) => $s['name'], $systems)) : '(none)') . "\n";
    echo 'Fabrics available: ' . count($fabrics) . "\n";
    echo "Option groups: \n";
    foreach (['exact or recess', 'fascia options', 'scallops and trims'] as $need) {
        $g = $groups[$need] ?? null;
        echo '  ' . $need . ': ' . ($g ? (count($g['choices']) . ' choices — ' . implode(' / ', array_map(static fn ($c) => $c['label'], $g['choices']))) : 'MISSING') . "\n";
    }
    exit;
}

// ---- Delete mode -----------------------------------------------------------
if (($_GET['delete'] ?? '') !== '' && ($_GET['delete'] ?? '0') !== '0') {
    $q = $pdo->prepare("SELECT id FROM quotes WHERE client_id = ? AND customer_reference = 'DUMMY' AND quote_number LIKE 'ABC-TEST-%' AND id IN (SELECT quote_id FROM quote_items WHERE product_id = ?)");
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

// ---- Guards ----------------------------------------------------------------
if (!$systems) { exit("Roller product {$rollerPid} has no systems on ABC. Push the catalogue first.\n"); }
if (!$fabrics) { exit("Roller product {$rollerPid} has no fabrics on ABC. Push the catalogue first.\n"); }
foreach (['exact or recess', 'fascia options', 'scallops and trims'] as $need) {
    if (empty($groups[$need])) { exit("Roller product {$rollerPid} is missing option group '{$need}' on ABC.\n"); }
}

// ---- Base rows to clone (structure) ----------------------------------------
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

$tplQuote = $pdo->query('SELECT * FROM quotes WHERE id = 237')->fetch(PDO::FETCH_ASSOC);
$baseItem = $pdo->query('SELECT * FROM quote_items WHERE quote_id = 237 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$baseExtra = $pdo->query('SELECT * FROM quote_item_extras WHERE quote_item_id = ' . (int) $baseItem['id'] . ' ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$tplQuote || !$baseItem || !$baseExtra) { exit("Template quote 237 (quote/item/extra) not available to clone structure from.\n"); }

// Curated combos: [fit, fascia, scallop, room, width, drop].
$combos = [
    ['Recess',     'None',              'Not Required',            'Lounge',   1200, 1500],
    ['Recess',     'Senses',            'Scallops 1-4 With Braid', 'Bedroom',  1400, 1800],
    ['Cloth Size', 'Grip Fix Cassette', 'Standard Braid On Bottom','Kitchen',   900, 1200],
    ['Exact',      'LL 70mm Cassette',  'Not Required',            'Study',    2000, 1600],
];

$getChoice = static function (string $groupKey, string $label) use ($groups): ?array {
    $g = $groups[$groupKey] ?? null;
    if (!$g) return null;
    $c = $g['choices'][mb_strtolower($label)] ?? null;
    return $c ? ['extra_id' => $g['id'], 'extra_name' => $g['name'], 'choice_id' => $c['id'], 'label' => $c['label']] : null;
};

$maxNum = 0;
foreach ($pdo->query("SELECT quote_number FROM quotes WHERE quote_number LIKE 'ABC-TEST-%'") as $r) {
    if (preg_match('/(\d+)$/', (string) $r['quote_number'], $m)) $maxNum = max($maxNum, (int) $m[1]);
}
$sysRow = $systems[0];

$pdo->beginTransaction();
try {
    $q = $tplQuote;
    $q['client_id']          = $clientId;
    $q['quote_number']       = 'ABC-TEST-' . str_pad((string) ($maxNum + 1), 4, '0', STR_PAD_LEFT);
    $q['customer_reference'] = 'DUMMY';
    $q['created_at']         = date('Y-m-d H:i:s');
    if (array_key_exists('updated_at', $q)) $q['updated_at'] = $q['created_at'];
    $q = $freshTokens($q);
    $newQid = $insertRow($pdo, 'quotes', $q);

    $line = 0;
    foreach ($combos as [$fit, $fascia, $scallop, $room, $w, $d]) {
        $line++;
        $fabRow = $fabrics[array_rand($fabrics)];

        $it = $baseItem;
        $it['quote_id']             = $newQid;
        $it['line_no']              = $line;
        $it['quantity']             = 1;
        $it['product_id']           = $rollerPid;
        $it['product_name_snapshot']= $rollerName;
        $it['system_id']            = (int) $sysRow['id'];
        $it['system_name_snapshot'] = (string) $sysRow['name'];
        $it['width_mm']             = $w;
        $it['drop_mm']              = $d;
        $it['room_name']            = $room;
        $it['option_id']                = (int) $fabRow['id'];
        $it['fabric_band_snapshot']     = $fabRow['band_code'];
        $it['fabric_supplier_snapshot'] = $fabRow['supplier_name'];
        $it['fabric_name_snapshot']     = $fabRow['name'];
        $it['fabric_colour_snapshot']   = $fabRow['colour'];
        $it['fabric_code_snapshot']     = $fabRow['code'];
        if (array_key_exists('notes', $it)) $it['notes'] = $fascia . ' / ' . $fit;
        $it = $freshTokens($it);
        $newItemId = $insertRow($pdo, 'quote_items', $it);

        $picks = [
            $getChoice('exact or recess', $fit),
            $getChoice('fascia options', $fascia),
            $getChoice('scallops and trims', $scallop),
        ];
        foreach ($picks as $p) {
            if (!$p) continue;
            $ex = $baseExtra;
            $ex['quote_item_id']           = $newItemId;
            $ex['product_extra_id']        = $p['extra_id'];
            $ex['product_extra_choice_id'] = $p['choice_id'];
            $ex['extra_name_snapshot']     = $p['extra_name'];
            $ex['choice_label_snapshot']   = $p['label'];
            if (array_key_exists('user_value', $ex)) $ex['user_value'] = null;
            $ex = $freshTokens($ex);
            $insertRow($pdo, 'quote_item_extras', $ex);
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit('FAILED (rolled back): ' . $e->getMessage() . "\n");
}

echo "Created roller order {$q['quote_number']} on ABC (client {$clientId}, product {$rollerPid} {$rollerName}) — " . count($combos) . " blinds.\n";
echo "Combos: None/Recess · Senses/Recess+scallop · Grip Fix/Cloth · LL 70mm/Exact.\n";
echo "Open it in Incoming Orders → Worksheet → Roll label print. Remove with ?delete=1.\n";
