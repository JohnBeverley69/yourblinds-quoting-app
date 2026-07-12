<?php
declare(strict_types=1);

/**
 * Seed: dummy trade orders on the ABC test account, for loading up the factory
 * incoming-orders queue + exercising worksheets/print.
 *
 * Generates N vertical-blind orders by CLONING an existing valid ABC order
 * (default quote 237, which has both a wand and a corded line), so every
 * record — quote, items, option extras — is structurally correct and flows
 * through the build engine. Per generated order: 1..10 blinds (spread), each
 * with a varied vertical system, size, room, fabric colour and control
 * (corded / wand). Every dummy quote is stamped customer_reference = 'DUMMY'
 * and numbered ABC-TEST-#### so they are trivially identifiable and deletable.
 *
 *   /seed_dummy_orders.php            → make ~50
 *   /seed_dummy_orders.php?n=10       → make 10
 *   /seed_dummy_orders.php?template=237
 *   /seed_dummy_orders.php?delete=1   → remove ALL dummy orders again
 *
 * Super-admin only (via browser). Not idempotent — each run adds more; use
 * ?delete=1 to clear them out.
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

$templateQid = isset($_GET['template']) && ctype_digit((string) $_GET['template']) ? (int) $_GET['template'] : 237;
$n           = isset($_GET['n']) && ctype_digit((string) $_GET['n']) ? max(1, min(200, (int) $_GET['n'])) : 50;

// Cloned rows carry the template's unique tokens (public_token etc.) — give
// every token column a fresh value so the UNIQUE constraints don't collide.
$freshTokens = static function (array $row): array {
    foreach ($row as $k => $v) {
        if ($v !== null && stripos((string) $k, 'token') !== false) $row[$k] = bin2hex(random_bytes(32));
    }
    return $row;
};

// Generic row clone/insert so we copy every column (incl. ones we don't name).
$insertRow = static function (PDO $pdo, string $table, array $row): int {
    unset($row['id']);
    $cols = array_keys($row);
    $sql  = 'INSERT INTO `' . $table . '` (' . implode(',', array_map(static fn ($c) => "`$c`", $cols)) . ') '
          . 'VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($row));
    return (int) $pdo->lastInsertId();
};

// ---- Load the template order ----------------------------------------------
$tplQuote = $pdo->query("SELECT * FROM quotes WHERE id = {$templateQid}")->fetch(PDO::FETCH_ASSOC);
if (!$tplQuote) { exit("Template quote {$templateQid} not found.\n"); }
$clientId = (int) $tplQuote['client_id'];

$tplItems = $pdo->query("SELECT * FROM quote_items WHERE quote_id = {$templateQid} ORDER BY line_no, id")->fetchAll(PDO::FETCH_ASSOC);
if (!$tplItems) { exit("Template quote {$templateQid} has no line items.\n"); }

// Extras per template item + classify each template item as wand or corded.
$tplExtrasByItem = [];
$controlOf = static function (array $extras): string {
    foreach ($extras as $e) {
        if (strtolower(trim((string) $e['extra_name_snapshot'])) === 'control options') return (string) $e['choice_label_snapshot'];
    }
    return '';
};
$wandTpl = null; $cordTpl = null;
foreach ($tplItems as $it) {
    $ex = $pdo->prepare('SELECT * FROM quote_item_extras WHERE quote_item_id = ? ORDER BY id');
    $ex->execute([(int) $it['id']]);
    $extras = $ex->fetchAll(PDO::FETCH_ASSOC);
    $tplExtrasByItem[(int) $it['id']] = $extras;
    $ctrl = strtolower($controlOf($extras));
    if ($ctrl === 'wand'  && !$wandTpl) $wandTpl = ['item' => $it, 'extras' => $extras];
    if ($ctrl === 'corded' && !$cordTpl) $cordTpl = ['item' => $it, 'extras' => $extras];
}
if (!$wandTpl || !$cordTpl) {
    exit("Template quote {$templateQid} needs both a wand line and a corded line to clone from (found wand=" . ($wandTpl ? 'y' : 'n') . ", corded=" . ($cordTpl ? 'y' : 'n') . ").\n");
}
$vertProductId = (int) $wandTpl['item']['product_id'];

// ---- Delete mode -----------------------------------------------------------
if (($_GET['delete'] ?? '') !== '' && ($_GET['delete'] ?? '0') !== '0') {
    $q = $pdo->prepare("SELECT id FROM quotes WHERE client_id = ? AND customer_reference = 'DUMMY' AND quote_number LIKE 'ABC-TEST-%'");
    $q->execute([$clientId]);
    $ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) { exit("No dummy orders to delete.\n"); }
    $in = implode(',', $ids);
    $pdo->beginTransaction();
    $pdo->exec("DELETE FROM quote_item_extras WHERE quote_item_id IN (SELECT id FROM quote_items WHERE quote_id IN ($in))");
    $pdo->exec("DELETE FROM quote_items WHERE quote_id IN ($in)");
    $pdo->exec("DELETE FROM quotes WHERE id IN ($in)");
    $pdo->commit();
    exit('Deleted ' . count($ids) . " dummy orders.\n");
}

// ---- Vertical systems on the ABC product ----------------------------------
$ss = $pdo->prepare('SELECT id, name FROM product_systems WHERE product_id = ? AND client_id = ? ORDER BY sort_order, id');
$ss->execute([$vertProductId, $clientId]);
$systems = $ss->fetchAll(PDO::FETCH_ASSOC);   // [ ['id'=>, 'name'=>], ... ]
if (!$systems) { exit("No systems found on product {$vertProductId} for client {$clientId}.\n"); }
$cordedSystems = array_values(array_filter($systems, static fn ($s) => stripos((string) $s['name'], 'thrill') === false)); // No Thrills = wand only
if (!$cordedSystems) $cordedSystems = $systems;

// ---- Generate --------------------------------------------------------------
$rooms   = ['Lounge', 'Bedroom', 'Kitchen', 'Bathroom', 'Hall', 'Office', 'Dining Room', 'Master Bedroom', 'Cloakroom', 'Study', 'Landing', 'Conservatory', 'Nursery', 'Utility'];

// A REAL fabric valid for the blind's system (so colour/name are genuine combos,
// never "SlimLine in Black"). Sampled + cached per system.
$fabricCache = [];
$pickFabric = static function (int $sysId) use ($pdo, $vertProductId, $clientId, &$fabricCache) {
    if (!array_key_exists($sysId, $fabricCache)) {
        $st = $pdo->prepare('SELECT id, band_code, supplier_name, name, colour, code FROM product_options
                              WHERE product_id = ? AND client_id = ? AND active = 1 AND (system_id IS NULL OR system_id = ?)
                           ORDER BY RAND() LIMIT 300');
        $st->execute([$vertProductId, $clientId, $sysId]);
        $fabricCache[$sysId] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $list = $fabricCache[$sysId];
    return $list ? $list[array_rand($list)] : null;
};

// A choice valid for THIS system, for a given option group. Cached per (extra, system).
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

// Continue numbering from any existing dummies.
$maxNum = 0;
foreach ($pdo->query("SELECT quote_number FROM quotes WHERE quote_number LIKE 'ABC-TEST-%'") as $r) {
    if (preg_match('/(\d+)$/', (string) $r['quote_number'], $m)) $maxNum = max($maxNum, (int) $m[1]);
}

$made = 0; $blinds = 0;
$pdo->beginTransaction();
try {
    for ($i = 0; $i < $n; $i++) {
        $seq = $maxNum + $i + 1;
        // New quote (clone template, restamp).
        $q = $tplQuote;
        $q['quote_number']       = 'ABC-TEST-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        $q['customer_reference'] = 'DUMMY';
        $q['created_at']         = date('Y-m-d H:i:s', time() - mt_rand(0, 14 * 86400));
        if (array_key_exists('updated_at', $q)) $q['updated_at'] = $q['created_at'];
        $q = $freshTokens($q);
        $newQid = $insertRow($pdo, 'quotes', $q);

        $lineCount = ($i % 10) + 1;   // spread 1..10 across the batch
        for ($j = 0; $j < $lineCount; $j++) {
            $wand = ($j % 2 === 0);   // alternate control for variety
            $tpl  = $wand ? $wandTpl : $cordTpl;
            $sys  = $wand ? $systems[array_rand($systems)] : $cordedSystems[array_rand($cordedSystems)];

            $it = $tpl['item'];
            $it['quote_id']              = $newQid;
            $it['line_no']               = $j + 1;
            $it['system_id']             = (int) $sys['id'];
            $it['system_name_snapshot']  = (string) $sys['name'];
            $it['width_mm']              = mt_rand(60, 300) * 10;    // 600..3000
            $it['drop_mm']               = mt_rand(60, 260) * 10;    // 600..2600
            $it['room_name']             = $rooms[array_rand($rooms)];
            // Real fabric valid for this system.
            $fab = $pickFabric((int) $sys['id']);
            if ($fab) {
                $it['option_id']                = (int) $fab['id'];
                $it['fabric_band_snapshot']     = $fab['band_code'];
                $it['fabric_supplier_snapshot'] = $fab['supplier_name'];
                $it['fabric_name_snapshot']     = $fab['name'];
                $it['fabric_colour_snapshot']   = $fab['colour'];
                $it['fabric_code_snapshot']     = $fab['code'];
            }
            $it = $freshTokens($it);
            $newItemId = $insertRow($pdo, 'quote_items', $it);

            foreach ($tpl['extras'] as $ex) {
                $ex['quote_item_id'] = $newItemId;
                $nm = strtolower(trim((string) $ex['extra_name_snapshot']));
                // Re-pick a choice valid for THIS system (keeps combos real). Leave
                // Control Options alone — it defines the wand/corded structure of
                // the cloned line.
                if ($nm !== 'control options') {
                    $ch = $pickChoice((int) $ex['product_extra_id'], (int) $sys['id']);
                    if ($ch) {
                        $ex['product_extra_choice_id'] = (int) $ch['id'];
                        $ex['choice_label_snapshot']   = (string) $ch['label'];
                    }
                }
                if ($nm === 'fit height')   $ex['user_value'] = (string) (mt_rand(180, 240) * 10);   // 1800..2400
                if ($nm === 'wand options') $ex['user_value'] = (string) (mt_rand(40, 90) * 10);      // 400..900 wand length
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

echo "Created {$made} dummy orders ({$blinds} blinds) on client {$clientId} (from template quote {$templateQid}).\n";
echo "Numbered ABC-TEST-" . str_pad((string) ($maxNum + 1), 4, '0', STR_PAD_LEFT) . " .. ABC-TEST-" . str_pad((string) ($maxNum + $made), 4, '0', STR_PAD_LEFT) . ", all customer_reference = 'DUMMY'.\n";
echo "Systems used: " . implode(', ', array_map(static fn ($s) => $s['name'], $systems)) . " (corded excludes No Thrills). Line counts spread 1..10.\n";
echo "Clean up any time with /seed_dummy_orders.php?delete=1\n";
