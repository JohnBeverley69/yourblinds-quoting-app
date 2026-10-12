<?php
declare(strict_types=1);

/**
 * Move an option choice into another option group on the same product(s).
 *
 * First use (John, 2026-10-12): "Metal Toggles (set of 3)" belongs under
 * Accessories, not Motor & Parts, on Bev Forest Wood and Bev Embassy Faux Wood.
 * The choice keeps its id, so its price, its band/fabric scope and anything gated
 * on it (Metal Toggle Colour) carry on working; it just shows in the other group.
 *
 * Only the SIGNED-IN company's products. Tenants pick the move up on their next
 * catalogue push. Reports first, including any build-rule or worksheet text that
 * mentions the old group or the choice (those refer to options by name).
 *
 * Super-admin, web-runnable:
 *   /setup/tools/move_option_choice.php?products=Bev Forest Wood|Bev Embassy Faux Wood
 *        &choice=Metal Toggles (set of 3)&from=Motor %26 Parts&to=Accessories   (then &apply=1)
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$clientId = PHP_SAPI === 'cli' ? (int) ($argv[1] ?? 0) : (int) (current_user()['client_id'] ?? 0);
$products = array_values(array_filter(array_map('trim', explode('|', (string) ($_GET['products'] ?? '')))));
$choice   = trim((string) ($_GET['choice'] ?? ''));
$from     = trim((string) ($_GET['from'] ?? ''));
$to       = trim((string) ($_GET['to'] ?? ''));
$apply    = !empty($_GET['apply']);
if ($clientId <= 0 || !$products || $choice === '' || $from === '' || $to === '') {
    exit("Give products=A|B, choice=, from=, to= (signed in as the company that owns them).\n");
}

$findExtra = $pdo->prepare('SELECT id FROM product_extras WHERE client_id = ? AND product_id = ? AND name = ? LIMIT 1');
$findChoice = $pdo->prepare('SELECT id, sort_order FROM product_extra_choices WHERE product_extra_id = ? AND label = ? LIMIT 1');
$maxSort = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_extra_choices WHERE product_extra_id = ?');
$move = $pdo->prepare('UPDATE product_extra_choices SET product_extra_id = ?, sort_order = ? WHERE id = ?');

$moved = 0;
foreach ($products as $pname) {
    $p = $pdo->prepare('SELECT id FROM products WHERE client_id = ? AND name = ? LIMIT 1');
    $p->execute([$clientId, $pname]);
    $pid = (int) ($p->fetchColumn() ?: 0);
    if (!$pid) { echo "SKIP    $pname — no such product on this account\n"; continue; }

    $findExtra->execute([$clientId, $pid, $from]); $fromId = (int) ($findExtra->fetchColumn() ?: 0);
    $findExtra->execute([$clientId, $pid, $to]);   $toId   = (int) ($findExtra->fetchColumn() ?: 0);
    if (!$fromId || !$toId) { echo "SKIP    $pname — option '" . (!$fromId ? $from : $to) . "' not found\n"; continue; }
    $findChoice->execute([$fromId, $choice]);
    $c = $findChoice->fetch(PDO::FETCH_ASSOC);
    if (!$c) { echo "SKIP    $pname — '$choice' isn't in '$from' (already moved?)\n"; continue; }

    // Anything that names the old group or the choice in text (build rules,
    // worksheet layouts) — they refer to options by name, so flag them.
    $refs = [];
    foreach ([['build_variables', 'CONCAT(name, columns_json, rows_json)'], ['worksheet_templates', 'layout_json']] as [$t, $expr]) {
        try {
            $r = $pdo->prepare("SELECT COUNT(*) FROM $t WHERE product_id = ? AND ($expr LIKE ? OR $expr LIKE ?)");
            $r->execute([$pid, '%' . $from . '%', '%' . $choice . '%']);
            $n = (int) $r->fetchColumn();
            if ($n) $refs[] = "$n $t row(s)";
        } catch (Throwable $e) { /* table not migrated */ }
    }

    echo ($apply ? 'MOVED   ' : 'WOULD   ') . "$pname: '$choice' (#{$c['id']}) from '$from' to '$to'"
       . ($refs ? '  ⚠ check: ' . implode(', ', $refs) . " mention '$from' or the choice" : '  · no build-rule/worksheet text mentions it') . "\n";
    if ($apply) {
        $maxSort->execute([$toId]);
        $move->execute([$toId, (int) $maxSort->fetchColumn(), (int) $c['id']]);
        $moved++;
    }
}
echo "\n" . ($apply ? "$moved moved." : 'Nothing changed yet — add &apply=1 to move.') . "\n";
if ($apply && $moved) echo "Push these products to tenants so their copies follow.\n";
