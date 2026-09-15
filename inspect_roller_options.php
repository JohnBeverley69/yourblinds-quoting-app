<?php
declare(strict_types=1);

/**
 * READ-ONLY inspection: dump the live Bev Roller Blinds option list (id, name,
 * code, sort, parent gating) and the current roller worksheet template's field
 * sources, so we can design a collision-safe code map for label hardening.
 * Web-runnable: /inspect_roller_options.php (super-admin). Changes nothing.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Bev Roller Blinds not found for client {$MASTER}.\n"); }
echo "Product id: {$productId}\n\n";

echo "== OPTIONS (product_extras) ==\n";
$hasCode = false; try { $pdo->query('SELECT code FROM product_extras LIMIT 1'); $hasCode = true; } catch (Throwable $e) {}
$q = $pdo->prepare("SELECT id, name, " . ($hasCode ? 'code' : 'NULL AS code') . ", sort_order, parent_choice_id, active
                      FROM product_extras WHERE product_id = ? AND client_id = ? ORDER BY sort_order, id");
$q->execute([$productId, $MASTER]);
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
    printf("  #%-4d sort=%-4s active=%s code=%-28s name=%s\n",
        (int)$r['id'], (string)$r['sort_order'], (string)$r['active'],
        ($r['code'] === null ? '(null)' : (string)$r['code']), (string)$r['name']);
}

echo "\n== WORKSHEET TEMPLATE field sources ==\n";
$t = $pdo->prepare('SELECT id, name, layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id LIMIT 1');
$t->execute([$productId]);
$tpl = $t->fetch(PDO::FETCH_ASSOC);
if (!$tpl) { echo "  (no template)\n"; }
else {
    echo "  template id={$tpl['id']} name=" . (string)$tpl['name'] . "\n";
    $layout = json_decode((string)$tpl['layout_json'], true);
    $labels = $layout['labels'] ?? [];
    foreach ($labels as $li => $lab) {
        echo "  label[$li] fields:\n";
        foreach (($lab['fields'] ?? []) as $f) {
            $src = is_array($f) ? (string)($f['source'] ?? '') : '';
            if ($src === '') continue;
            echo "    - " . $src . (isset($f['caption']) && $f['caption'] !== '' ? "   (cap: {$f['caption']})" : '') . "\n";
        }
    }
}
echo "\nDone (read-only).\n";
