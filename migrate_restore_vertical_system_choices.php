<?php
declare(strict_types=1);

/**
 * Restore per-system option choices that an over-aggressive duplicate cleanup
 * (label-only grouping) wrongly merged down to a single system. John confirmed
 * the correct availability: Corded and the wand draws (Centre Left/Right, Split
 * Draw 2 Wands) belong on SlimLine, Nova and Vogue (NOT No Thrills).
 *
 * For each (product, option, label, systems), ensure a choice exists for every
 * named system — cloning an existing same-label choice as the template. Systems
 * are resolved by NAME per product, so it works for the master (8, 119) and could
 * be pointed at any product. Idempotent (existing per-system choices are skipped).
 * MASTER only — push afterwards so the mirrors get the restored per-system copies.
 *
 * Run as super-admin: /migrate_restore_vertical_system_choices.php
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

$log = [];

$ensure = static function (int $productId, string $extraName, string $label, array $systemNames) use ($pdo, &$log): void {
    $e = $pdo->prepare('SELECT id FROM product_extras WHERE product_id = ? AND name = ? LIMIT 1');
    $e->execute([$productId, $extraName]);
    $extraId = (int) ($e->fetchColumn() ?: 0);
    if (!$extraId) { $log[] = "p$productId: option '$extraName' not found — skipped"; return; }

    $sysMap = [];
    $ss = $pdo->prepare('SELECT id, name FROM product_systems WHERE product_id = ?');
    $ss->execute([$productId]);
    foreach ($ss->fetchAll(PDO::FETCH_ASSOC) as $s) $sysMap[strtolower(trim((string) $s['name']))] = (int) $s['id'];

    $t = $pdo->prepare('SELECT * FROM product_extra_choices WHERE product_extra_id = ? AND LOWER(TRIM(label)) = LOWER(?) ORDER BY id LIMIT 1');
    $t->execute([$extraId, $label]);
    $tpl = $t->fetch(PDO::FETCH_ASSOC);
    if (!$tpl) { $log[] = "p$productId: no '$label' in '$extraName' to clone — skipped"; return; }

    $so = (int) $pdo->query("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_extra_choices WHERE product_extra_id = $extraId")->fetchColumn();
    foreach ($systemNames as $sn) {
        $sid = $sysMap[strtolower(trim($sn))] ?? 0;
        if (!$sid) { $log[] = "p$productId: system '$sn' not on this product — skipped"; continue; }
        $c = $pdo->prepare('SELECT COUNT(*) FROM product_extra_choices WHERE product_extra_id = ? AND LOWER(TRIM(label)) = LOWER(?) AND system_id = ?');
        $c->execute([$extraId, $label, $sid]);
        if ((int) $c->fetchColumn() > 0) { continue; }   // already present
        $ins = $pdo->prepare(
            'INSERT INTO product_extra_choices
               (product_extra_id, system_id, label, price_delta, price_percent, price_per_metre,
                is_default, sort_order, active, image_path)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)'
        );
        $ins->execute([
            $extraId, $sid, (string) $tpl['label'],
            $tpl['price_delta'], $tpl['price_percent'], $tpl['price_per_metre'],
            $so++, (int) $tpl['active'], $tpl['image_path'] ?? null,
        ]);
        $log[] = "p$productId: added '$label' on '$sn' (option '$extraName')";
    }
};

$ONTO = ['SlimLine', 'Nova', 'Vogue'];

$pdo->beginTransaction();
try {
    // Bev Vertical Blinds (#8): Corded + the three wand draws.
    $ensure(8, 'Control Options', 'Corded', $ONTO);
    $ensure(8, 'Wand Options', 'Centre Left', $ONTO);
    $ensure(8, 'Wand Options', 'Centre Right', $ONTO);
    $ensure(8, 'Wand Options', 'Split Draw 2 Wands', $ONTO);

    // Bev Vertical Blind Head Rail Only (#119): only Corded was damaged.
    $ensure(119, 'Control Options', 'Corded', $ONTO);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "FAILED — rolled back: " . $e->getMessage() . "\n";
    exit;
}

echo "Restore complete — " . count($log) . " action(s):\n";
foreach ($log as $l) echo "  - $l\n";
echo "\nNow run a catalogue push (Bev -> tenants) so the mirrors get the restored per-system choices.\n";
