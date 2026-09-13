<?php
declare(strict_types=1);

/**
 * TEMP one-off: add a fixed MIXED set of blinds to an existing ABC draft quote,
 * for an end-to-end flow test — 2 Bev Vertical Blinds + 3 Bev Roller Blinds +
 * 1 Bev PF Venetian, each with a valid system, a real fabric, and a
 * system-valid choice for every option group. Clones column structure from any
 * existing quote_item (like seed_fullmix.php), overwrites product/fabric/extras.
 *
 *   /_mixorder.php?quote=817          → add the mix to quote 817 (must be ABC)
 *
 * Super-admin only. Delete after use (git rm _mixorder.php).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
if (PHP_SAPI !== 'cli') { requireLogin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL); set_time_limit(120);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$quoteId = (int) ($_GET['quote'] ?? 0);
if ($quoteId <= 0) exit("Pass ?quote=N (your draft quote id).\n");
$qs = $pdo->prepare('SELECT * FROM quotes WHERE id = ? LIMIT 1');
$qs->execute([$quoteId]);
$quote = $qs->fetch(PDO::FETCH_ASSOC);
if (!$quote) exit("Quote {$quoteId} not found.\n");
$clientId = (int) $quote['client_id'];
// Tenant-scoped: you can only seed onto your OWN client's quote.
if (PHP_SAPI !== 'cli') {
    $me = (int) (current_user()['client_id'] ?? 0);
    if ($clientId !== $me) exit("Quote {$quoteId} isn't on your account.\n");
}
echo "Target: quote {$quoteId} ({$quote['quote_number']}), client {$clientId}, status {$quote['status']}.\n";

// The mix: product name => how many blinds.
$mix = ['Bev Vertical Blinds' => 2, 'Bev Roller Blinds' => 3, 'Bev PF Venetian' => 1];

// Structural template — any quote_item that carries extras (schema-wide columns).
$baseItem = $pdo->query("SELECT qi.* FROM quote_items qi
                          WHERE EXISTS (SELECT 1 FROM quote_item_extras e WHERE e.quote_item_id = qi.id)
                       ORDER BY qi.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$baseExtra = $baseItem ? $pdo->query('SELECT * FROM quote_item_extras WHERE quote_item_id = ' . (int) $baseItem['id'] . ' ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC) : null;
if (!$baseItem || !$baseExtra) exit("No template quote_item to clone structure from.\n");

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
$rooms = ['Lounge', 'Bedroom', 'Kitchen', 'Bathroom', 'Hall', 'Office', 'Dining Room', 'Study'];

$pdo->beginTransaction();
try {
    $lineNo = (int) ($pdo->query('SELECT COALESCE(MAX(line_no),0) FROM quote_items WHERE quote_id = ' . $quoteId)->fetchColumn());
    $added  = 0;

    foreach ($mix as $pname => $count) {
        $prod = $pdo->prepare("SELECT id, name FROM products WHERE client_id = ? AND source_client_id = ? AND name = ? ORDER BY id LIMIT 1");
        $prod->execute([$clientId, $MASTER, $pname]);
        $prow = $prod->fetch(PDO::FETCH_ASSOC);
        if (!$prow) exit("ABC has no product named '{$pname}' (source-mapped).\n");
        $productId = (int) $prow['id'];

        $systems = $pdo->prepare('SELECT id, name FROM product_systems WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, id');
        $systems->execute([$productId, $clientId]);
        $systems = $systems->fetchAll(PDO::FETCH_ASSOC);
        if (!$systems) exit("Product {$pname} has no systems on ABC.\n");

        $fabs = $pdo->prepare('SELECT id, band_code, supplier_name, name, colour, code FROM product_options WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY RAND() LIMIT 200');
        $fabs->execute([$productId, $clientId]);
        $fabs = $fabs->fetchAll(PDO::FETCH_ASSOC);
        if (!$fabs) exit("Product {$pname} has no fabrics on ABC.\n");

        $groups = $pdo->prepare('SELECT id, name, parent_choice_id FROM product_extras WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, id');
        $groups->execute([$productId, $clientId]);
        $groups = $groups->fetchAll(PDO::FETCH_ASSOC);
        $parentsOf = [];
        foreach ($groups as $g) { $parentsOf[(int) $g['id']] = []; if ($g['parent_choice_id'] !== null) $parentsOf[(int) $g['id']][(int) $g['parent_choice_id']] = true; }
        try {
            $jq = $pdo->prepare('SELECT pep.product_extra_id eid, pep.product_extra_choice_id cid FROM product_extra_parent_choices pep JOIN product_extras e ON e.id = pep.product_extra_id WHERE e.product_id = ? AND e.client_id = ?');
            $jq->execute([$productId, $clientId]);
            foreach ($jq->fetchAll(PDO::FETCH_ASSOC) as $r) { $parentsOf[(int) $r['eid']][(int) $r['cid']] = true; }
        } catch (Throwable $e) { /* no junction */ }

        for ($k = 0; $k < $count; $k++) {
            $sys = $systems[array_rand($systems)];
            $fab = $fabs[array_rand($fabs)];
            $lineNo++;
            $it = $baseItem;
            $it['quote_id']                 = $quoteId;
            $it['line_no']                  = $lineNo;
            $it['quantity']                 = 1;
            $it['product_id']               = $productId;
            $it['product_name_snapshot']    = $pname;
            $it['system_id']                = (int) $sys['id'];
            $it['system_name_snapshot']     = (string) $sys['name'];
            $it['width_mm']                 = mt_rand(50, 220) * 10;
            $it['drop_mm']                  = mt_rand(50, 280) * 10;
            $it['room_name']                = $rooms[array_rand($rooms)];
            $it['option_id']                = (int) $fab['id'];
            $it['fabric_band_snapshot']     = $fab['band_code'];
            $it['fabric_supplier_snapshot'] = $fab['supplier_name'];
            $it['fabric_name_snapshot']     = $fab['name'];
            $it['fabric_colour_snapshot']   = $fab['colour'];
            $it['fabric_code_snapshot']     = $fab['code'];
            if (array_key_exists('notes', $it)) $it['notes'] = '';
            $it = $freshTokens($it);
            $newItemId = $insertRow($pdo, 'quote_items', $it);

            // Fill option groups to a fixpoint (respecting conditional parents).
            $selected = []; $decided = [];
            do {
                $changed = false;
                foreach ($groups as $g) {
                    $gid = (int) $g['id'];
                    if (array_key_exists($gid, $decided)) continue;
                    $parents = $parentsOf[$gid] ?? [];
                    if ($parents) { $ok = false; foreach ($parents as $pcid => $_) { if (isset($selected[$pcid])) { $ok = true; break; } } if (!$ok) continue; }
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
            $added++;
            echo "  + {$pname} — {$sys['name']} — {$fab['name']} {$it['width_mm']}x{$it['drop_mm']}\n";
        }
    }

    $pdo->commit();
    echo "\nAdded {$added} blinds to quote {$quoteId}. Recalculating price is done live in the editor.\n";
    echo "Open: /quote-builder/edit.php?id={$quoteId}\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    exit('FAILED (rolled back): ' . $e->getMessage() . "\n");
}
