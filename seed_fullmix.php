<?php
declare(strict_types=1);

/**
 * Seed: full-mix dummy orders on the ABC test account, for EITHER product
 * (roller or vertical), with no dependency on a fixed template quote.
 *
 * Clones the column STRUCTURE from any existing ABC quote/item/extra (captured
 * up-front, so it survives a fresh-wipe), then swaps in the chosen product, a
 * real fabric valid for the system, and a system-valid choice for every option
 * group that has choices — so the worksheets exercise the full range.
 *
 *   /seed_fullmix.php?product=roller&n=50&fresh=1   → wipe all ABC-TEST dummies, make 50 roller
 *   /seed_fullmix.php?product=vertical&n=50         → append 50 vertical
 *   /seed_fullmix.php?product=roller&probe=1        → report the product's groups
 *   /seed_fullmix.php?delete=1                      → remove ALL ABC-TEST dummies
 *
 * Super-admin only.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL); set_time_limit(300);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$productArg = strtolower(trim((string) ($_GET['product'] ?? 'roller')));
if (isset($_GET['name']) && trim((string) $_GET['name']) !== '') { $targetName = trim((string) $_GET['name']); }
elseif (str_contains($productArg, 'vert')) { $targetName = 'Bev Vertical Blinds'; }
elseif (str_contains($productArg, 'pf'))   { $targetName = 'Bev PF Roller'; }
else                                       { $targetName = 'Bev Roller Blinds'; }
$n = isset($_GET['n']) && ctype_digit((string) $_GET['n']) ? max(1, min(200, (int) $_GET['n'])) : 50;

// ABC client.
$clientId = (int) ($pdo->query("SELECT client_id FROM quotes WHERE quote_number LIKE 'ABC-TEST-%' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0);
if ($clientId === 0) { $clientId = (int) ($pdo->query("SELECT id FROM clients WHERE company_name LIKE '%ABC%' ORDER BY id LIMIT 1")->fetchColumn() ?: 0); }
if ($clientId === 0) { exit("Could not determine the ABC client.\n"); }
$abcName = (string) ($pdo->query("SELECT company_name FROM clients WHERE id = {$clientId}")->fetchColumn() ?: 'ABC Blinds');

// Capture a structural template — ANY quote/item/extra in the system (columns
// are schema-wide; we overwrite client/product/fabric/extras anyway).
$baseItem = $pdo->query("SELECT qi.* FROM quote_items qi
                          WHERE EXISTS (SELECT 1 FROM quote_item_extras e WHERE e.quote_item_id = qi.id)
                       ORDER BY qi.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$tplQuote = $baseItem ? $pdo->query('SELECT * FROM quotes WHERE id = ' . (int) $baseItem['quote_id'])->fetch(PDO::FETCH_ASSOC) : null;
$baseExtra = $baseItem ? $pdo->query('SELECT * FROM quote_item_extras WHERE quote_item_id = ' . (int) $baseItem['id'] . ' ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC) : null;

$wipeAll = static function (PDO $pdo, int $clientId): int {
    $q = $pdo->prepare("SELECT id FROM quotes WHERE client_id = ? AND customer_reference = 'DUMMY' AND quote_number LIKE 'ABC-TEST-%'");
    $q->execute([$clientId]);
    $ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) return 0;
    $in = implode(',', $ids);
    $pdo->exec("DELETE FROM quote_item_extras WHERE quote_item_id IN (SELECT id FROM quote_items WHERE quote_id IN ($in))");
    $pdo->exec("DELETE FROM quote_items WHERE quote_id IN ($in)");
    $pdo->exec("DELETE FROM quotes WHERE id IN ($in)");
    return count($ids);
};

// Delete-all mode.
if (($_GET['delete'] ?? '0') !== '0' && ($_GET['delete'] ?? '') !== '') {
    $pdo->beginTransaction(); $c = $wipeAll($pdo, $clientId); $pdo->commit();
    exit("Deleted {$c} ABC-TEST dummy order(s).\n");
}

// Target product on ABC (exact name, source-mapped from master).
$prod = $pdo->prepare("SELECT id, name FROM products WHERE client_id = ? AND source_client_id = ? AND name = ? ORDER BY id LIMIT 1");
$prod->execute([$clientId, $MASTER, $targetName]);
$prow = $prod->fetch(PDO::FETCH_ASSOC);
if (!$prow) { exit("ABC has no product named '{$targetName}' (source-mapped). Push the catalogue first.\n"); }
$productId = (int) $prow['id']; $productName = (string) $prow['name'];

$sysSt = $pdo->prepare('SELECT id, name FROM product_systems WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, id');
$sysSt->execute([$productId, $clientId]);
$systems = $sysSt->fetchAll(PDO::FETCH_ASSOC);

$grpSt = $pdo->prepare('SELECT id, name, parent_choice_id FROM product_extras WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, id');
$grpSt->execute([$productId, $clientId]);
$groups = $grpSt->fetchAll(PDO::FETCH_ASSOC);

// Conditional gating: the parent choice ids that make each group appear
// (product_extra_parent_choices junction + legacy parent_choice_id). A group
// with no parents is unconditional. A dummy only fills a conditional group when
// one of its parent choices was actually selected — so a "No Braid" scallop
// never gets a Braid Colour, an end-cap colour only appears for its fascia, etc.
$parentsOf = [];
foreach ($groups as $g) {
    $parentsOf[(int) $g['id']] = [];
    if ($g['parent_choice_id'] !== null) $parentsOf[(int) $g['id']][(int) $g['parent_choice_id']] = true;
}
try {
    $jq = $pdo->prepare('SELECT pep.product_extra_id AS eid, pep.product_extra_choice_id AS cid
                           FROM product_extra_parent_choices pep JOIN product_extras e ON e.id = pep.product_extra_id
                          WHERE e.product_id = ? AND e.client_id = ?');
    $jq->execute([$productId, $clientId]);
    foreach ($jq->fetchAll(PDO::FETCH_ASSOC) as $r) { $parentsOf[(int) $r['eid']][(int) $r['cid']] = true; }
} catch (Throwable $e) { /* no junction table */ }

$fabSt = $pdo->prepare('SELECT id, band_code, supplier_name, name, colour, code FROM product_options
                         WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY RAND() LIMIT 400');
$fabSt->execute([$productId, $clientId]);
$fabrics = $fabSt->fetchAll(PDO::FETCH_ASSOC);

if (($_GET['probe'] ?? '0') !== '0' && ($_GET['probe'] ?? '') !== '') {
    echo "ABC client {$clientId}; product {$productId} ({$productName})\n";
    echo 'Template order captured: ' . ($baseItem ? "yes (quote {$baseItem['quote_id']})" : 'NO — need an existing ABC order to clone') . "\n";
    echo 'Systems: ' . implode(', ', array_map(fn($s)=>$s['name'], $systems)) . "\n";
    echo 'Fabrics: ' . count($fabrics) . "\n";
    $cc = $pdo->prepare('SELECT COUNT(*) FROM product_extra_choices WHERE product_extra_id = ? AND active = 1');
    foreach ($groups as $g) { $cc->execute([(int)$g['id']]); echo '  ' . $g['name'] . ': ' . (int)$cc->fetchColumn() . " choices\n"; }
    exit;
}

// Verify: no dummy blind carries a Braid Colour without a with-braid scallop parent.
if (($_GET['verify'] ?? '0') !== '0' && ($_GET['verify'] ?? '') !== '') {
    $braidGid = 0;
    foreach ($groups as $g) { if (mb_strtolower(trim((string) $g['name'])) === 'braid colour') { $braidGid = (int) $g['id']; break; } }
    if (!$braidGid) { exit("No 'Braid Colour' group on {$productName}.\n"); }
    $braidParents = $parentsOf[$braidGid] ?? [];
    echo "Product {$productId} ({$productName}); Braid Colour group {$braidGid} gated by " . count($braidParents) . " parent choice(s):\n";
    if ($braidParents) {
        $in = implode(',', array_map('intval', array_keys($braidParents)));
        foreach ($pdo->query("SELECT c.id, c.label, e.name grp FROM product_extra_choices c JOIN product_extras e ON e.id = c.product_extra_id WHERE c.id IN ($in) ORDER BY e.name, c.label") as $r) {
            echo "    \"{$r['label']}\" (under {$r['grp']})\n";
        }
    }
    $items = $pdo->prepare("SELECT qi.id, q.quote_number FROM quote_items qi JOIN quotes q ON q.id = qi.quote_id
                             WHERE q.client_id = ? AND q.customer_reference = 'DUMMY' AND qi.product_id = ?");
    $items->execute([$clientId, $productId]);
    $exSt = $pdo->prepare("SELECT product_extra_choice_id cid, extra_name_snapshot nm, choice_label_snapshot lbl FROM quote_item_extras WHERE quote_item_id = ?");
    $checked = 0; $bad = 0; $examples = [];
    foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $it) {
        $exSt->execute([(int) $it['id']]);
        $sel = []; $hasBraid = false; $braidLbl = ''; $scallopLbl = '(none)';
        foreach ($exSt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $sel[(int) $r['cid']] = true;
            $nm = mb_strtolower(trim((string) $r['nm']));
            if ($nm === 'braid colour')       { $hasBraid = true; $braidLbl = (string) $r['lbl']; }
            if ($nm === 'scallops and trims')  { $scallopLbl = (string) $r['lbl']; }
        }
        if (!$hasBraid) continue;
        $checked++;
        $ok = false; foreach ($braidParents as $pc => $_) { if (isset($sel[$pc])) { $ok = true; break; } }
        if (!$ok) { $bad++; if (count($examples) < 8) $examples[] = "{$it['quote_number']}: scallop '{$scallopLbl}' + braid '{$braidLbl}'"; }
    }
    echo "Blinds with a Braid Colour: {$checked}\n";
    echo "Impossible (braid on a non-braid scallop): {$bad}\n";
    foreach ($examples as $e) echo "  {$e}\n";
    exit;
}

if (!$baseItem || !$tplQuote || !$baseExtra) { exit("No existing ABC order to clone structure from — create one order in ABC first.\n"); }
if (!$systems) { exit("Product {$productId} has no systems on ABC.\n"); }
if (!$fabrics) { exit("Product {$productId} has no fabrics on ABC.\n"); }

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
$choiceCache = [];
$pickChoice = static function (int $extraId, int $sysId) use ($pdo, &$choiceCache) {
    $key = $extraId . '|' . $sysId;
    if (!array_key_exists($key, $choiceCache)) {
        $st = $pdo->prepare('SELECT id, label FROM product_extra_choices WHERE product_extra_id = ? AND active = 1 AND (system_id IS NULL OR system_id = ?) ORDER BY id');
        $st->execute([$extraId, $sysId]);
        $choiceCache[$key] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $list = $choiceCache[$key];
    return $list ? $list[array_rand($list)] : null;
};

$rooms = ['Lounge', 'Bedroom', 'Kitchen', 'Bathroom', 'Hall', 'Office', 'Dining Room', 'Master Bedroom', 'Cloakroom', 'Study', 'Landing', 'Conservatory', 'Nursery', 'Utility'];

$pdo->beginTransaction();
try {
    // Fresh wipe (template already captured in memory).
    $wiped = 0;
    if (($_GET['fresh'] ?? '0') !== '0' && ($_GET['fresh'] ?? '') !== '') { $wiped = $wipeAll($pdo, $clientId); }

    $maxNum = 0;
    foreach ($pdo->query("SELECT quote_number FROM quotes WHERE quote_number LIKE 'ABC-TEST-%'") as $r) {
        if (preg_match('/(\d+)$/', (string) $r['quote_number'], $m)) $maxNum = max($maxNum, (int) $m[1]);
    }

    $made = 0; $blinds = 0;
    for ($i = 0; $i < $n; $i++) {
        $q = $tplQuote;
        $q['client_id']          = $clientId;
        if (array_key_exists('company_name', $q)) $q['company_name'] = $abcName;
        if (array_key_exists('status', $q))       $q['status'] = 'ordered';   // placed → shows in the factory incoming queue
        $q['quote_number']       = 'ABC-TEST-' . str_pad((string) ($maxNum + $i + 1), 4, '0', STR_PAD_LEFT);
        $q['customer_reference'] = 'DUMMY';
        // Scrub end-customer PII inherited from the structural template.
        foreach ($q as $qk => $qv) {
            if (in_array($qk, ['company_name', 'customer_reference'], true)) continue;
            if (is_string($qv) && preg_match('/customer|address|postcode|post_code|email|phone|contact/i', $qk)) {
                $q[$qk] = (stripos($qk, 'end_customer_name') !== false) ? 'Dummy Customer' : '';
            }
        }
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
            $it['product_id']            = $productId;
            $it['product_name_snapshot'] = $productName;
            $it['system_id']             = (int) $sys['id'];
            $it['system_name_snapshot']  = (string) $sys['name'];
            $it['width_mm']              = mt_rand(40, 240) * 10;
            $it['drop_mm']               = mt_rand(40, 300) * 10;
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

            // Decide choices ORDER-INDEPENDENTLY: fill unconditional groups,
            // then cascade conditional groups as their parent choices become
            // selected, repeating to a fixpoint. (A single list-order pass
            // missed conditionals whose parent group sorts after them — e.g.
            // Braid Colour, gated by Scallops, which sorts later.) A conditional
            // group whose parent is never selected stays unfilled.
            $selected = [];   // choice ids chosen on this blind
            $decided  = [];   // gid => ['name'=>, 'ch'=>row|null]
            do {
                $changed = false;
                foreach ($groups as $g) {
                    $gid = (int) $g['id'];
                    if (array_key_exists($gid, $decided)) continue;
                    $parents = $parentsOf[$gid] ?? [];
                    if ($parents) {
                        $applies = false;
                        foreach ($parents as $pcid => $_) { if (isset($selected[$pcid])) { $applies = true; break; } }
                        if (!$applies) continue;   // parent not selected yet — retry a later pass
                    }
                    $ch = $pickChoice($gid, (int) $sys['id']);
                    $decided[$gid] = ['name' => (string) $g['name'], 'ch' => $ch ?: null];
                    if ($ch) $selected[(int) $ch['id']] = true;
                    $changed = true;
                }
            } while ($changed);

            foreach ($decided as $gid => $d) {
                if (!$d['ch']) continue;
                $ex = $baseExtra;
                $ex['quote_item_id']           = $newItemId;
                $ex['product_extra_id']        = $gid;
                $ex['product_extra_choice_id'] = (int) $d['ch']['id'];
                $ex['extra_name_snapshot']     = $d['name'];
                $ex['choice_label_snapshot']   = (string) $d['ch']['label'];
                if (array_key_exists('user_value', $ex)) $ex['user_value'] = null;
                $ex = $freshTokens($ex);
                $insertRow($pdo, 'quote_item_extras', $ex);
            }
            $blinds++;
        }
        $made++;
    }
    $pdo->commit();
    echo ($wiped ? "Wiped {$wiped} old dummy order(s).\n" : '');
    echo "Created {$made} {$productName} orders ({$blinds} blinds) on ABC (client {$clientId}, product {$productId}).\n";
    echo 'Numbered ABC-TEST-' . str_pad((string) ($maxNum + 1), 4, '0', STR_PAD_LEFT) . ' .. ABC-TEST-' . str_pad((string) ($maxNum + $made), 4, '0', STR_PAD_LEFT) . ".\n";
    echo 'Full mix across ' . count($groups) . " option groups.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    exit('FAILED (rolled back): ' . $e->getMessage() . "\n");
}
