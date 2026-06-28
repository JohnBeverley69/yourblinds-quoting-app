<?php
declare(strict_types=1);

/**
 * Combine several single-size products into ONE product whose sizes become
 * SYSTEMS — e.g. "Arena 15/25/35/50mm Venetian" → one "Metal Venetian" with
 * four systems.
 *
 * The FIRST selected product is reused as the master (it keeps its id, group
 * and settings, so existing references survive); each other product is folded
 * in as a new system and then DEACTIVATED (left as an empty husk to delete
 * once you've checked the result). Price tables, fabrics (scoped to their
 * system), extras and markups/discounts all move across in one transaction.
 *
 * Reached from the Products list "Combine into product…" bulk button.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];

// Selected product ids (preserve submitted order; first = master base).
$ids = [];
foreach ((array) ($_POST['product_ids'] ?? []) as $raw) {
    $n = (int) $raw;
    if ($n > 0 && !in_array($n, $ids, true)) $ids[] = $n;
}
if (count($ids) < 2) {
    $_SESSION['flash_error'] = 'Pick at least two products to combine.';
    header('Location: /admin/products/index.php');
    exit;
}

// Load them (tenant scope), keeping the submitted order.
$place = implode(',', array_fill(0, count($ids), '?'));
$st = db()->prepare("SELECT * FROM products WHERE client_id = ? AND id IN ($place)");
$st->execute(array_merge([$clientId], $ids));
$byId = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $byId[(int) $r['id']] = $r; }
$products = [];
foreach ($ids as $id) { if (isset($byId[$id])) $products[] = $byId[$id]; }
if (count($products) < 2) {
    $_SESSION['flash_error'] = 'Some selected products were not found.';
    header('Location: /admin/products/index.php');
    exit;
}

// Which pricing-mode flags exist on this schema (so the "same mode" check and
// the form only consider columns that are really there).
$modeFlags = array_values(array_filter(
    ['requires_option', 'width_only', 'price_per_slat', 'price_per_sqm'],
    static fn ($c) => array_key_exists($c, $products[0])
));

// product_options.system_id is optional (migrate_option_system_scope.php).
$optHasSystem = false;
try {
    $c = db()->query(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_options'
            AND COLUMN_NAME = 'system_id'"
    );
    $optHasSystem = (bool) $c->fetchColumn();
} catch (Throwable $e) { /* keep false */ }

// Count systems per product (combine expects single-system products).
$sysCount = [];
$cs = db()->prepare('SELECT COUNT(*) FROM product_systems WHERE client_id = ? AND product_id = ?');
foreach ($products as $p) {
    $cs->execute([$clientId, (int) $p['id']]);
    $sysCount[(int) $p['id']] = (int) $cs->fetchColumn();
}

$error = null;
$action = (string) ($_POST['_action'] ?? '');

if ($action === 'combine') {
    csrf_check();

    $masterName = trim((string) ($_POST['master_name'] ?? ''));
    $sysNames   = (array) ($_POST['system_name'] ?? []);   // [product_id => name]

    // Validate.
    if ($masterName === '' || strlen($masterName) > 150) {
        $error = 'Give the combined product a valid name (1–150 chars).';
    }
    foreach ($products as $p) {
        $nm = trim((string) ($sysNames[(int) $p['id']] ?? ''));
        if ($nm === '' || strlen($nm) > 150) {
            $error = 'Each product needs a system name (1–150 chars).';
        }
    }
    // All products must share the same pricing mode — a product is exactly one.
    foreach ($modeFlags as $col) {
        $first = (int) ($products[0][$col] ?? 0);
        foreach ($products as $p) {
            if ((int) ($p[$col] ?? 0) !== $first) {
                $error = 'These products are priced differently (e.g. per-slat vs '
                       . 'width×drop), so they can\'t be systems of one product.';
            }
        }
    }
    // Combine needs single-system products so each maps cleanly to one new system.
    foreach ($products as $p) {
        if ($sysCount[(int) $p['id']] > 1) {
            $error = '"' . $p['name'] . '" already has more than one system — '
                   . 'combine only works on single-size products.';
        }
    }

    if ($error === null) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $base   = $products[0];
            $baseId = (int) $base['id'];

            // 1. Master = the first product, renamed.
            $pdo->prepare('UPDATE products SET name = ? WHERE id = ? AND client_id = ?')
                ->execute([$masterName, $baseId, $clientId]);

            // Helper: ensure a product has a system, returning its id. A product
            // with no systems prices off NULL-system rows — give it a real system
            // and repoint those rows so everything keys on a system uniformly.
            $ensureSystem = function (PDO $pdo, int $pid, string $name, int $sort, int $isDefault) use ($clientId, $optHasSystem): int {
                $f = $pdo->prepare('SELECT id FROM product_systems WHERE client_id = ? AND product_id = ? ORDER BY sort_order, id LIMIT 1');
                $f->execute([$clientId, $pid]);
                $existing = $f->fetchColumn();
                if ($existing !== false) {
                    $pdo->prepare('UPDATE product_systems SET name = ?, sort_order = ?, is_default = ? WHERE id = ?')
                        ->execute([$name, $sort, $isDefault, (int) $existing]);
                    return (int) $existing;
                }
                $pdo->prepare('INSERT INTO product_systems (client_id, product_id, name, sort_order, active, is_default) VALUES (?,?,?,?,1,?)')
                    ->execute([$clientId, $pid, $name, $sort, $isDefault]);
                $newId = (int) $pdo->lastInsertId();
                // adopt any NULL-system rows on this product into the new system
                $pdo->prepare('UPDATE price_tables SET system_id = ? WHERE client_id = ? AND product_id = ? AND system_id IS NULL')
                    ->execute([$newId, $clientId, $pid]);
                if ($optHasSystem) {
                    $pdo->prepare('UPDATE product_options SET system_id = ? WHERE client_id = ? AND product_id = ? AND system_id IS NULL')
                        ->execute([$newId, $clientId, $pid]);
                }
                return $newId;
            };

            // 2. The base keeps its own data; just (re)name its system.
            $baseSysName = trim((string) ($sysNames[$baseId] ?? 'System 1'));
            $baseSysId   = $ensureSystem($pdo, $baseId, $baseSysName, 0, 1);

            $movedTables = 0; $movedFabrics = 0; $foldedIn = 0;

            // 3. Fold each other product in as a new system under the base.
            $sort = 1;
            foreach (array_slice($products, 1) as $src) {
                $srcId   = (int) $src['id'];
                $sysName = trim((string) ($sysNames[$srcId] ?? ('System ' . ($sort + 1))));

                // new system on the master
                $pdo->prepare('INSERT INTO product_systems (client_id, product_id, name, sort_order, active, is_default) VALUES (?,?,?,?,1,0)')
                    ->execute([$clientId, $baseId, $sysName, $sort]);
                $newSysId = (int) $pdo->lastInsertId();

                // move price tables → (master, newSystem)
                $u = $pdo->prepare('UPDATE price_tables SET product_id = ?, system_id = ? WHERE client_id = ? AND product_id = ?');
                $u->execute([$baseId, $newSysId, $clientId, $srcId]);
                $movedTables += $u->rowCount();

                // move fabrics → master, SCOPED to this system (so each size shows
                // only its own colours in the quote builder). Falls back to a plain
                // product-level move when the system_id column isn't present.
                if ($optHasSystem) {
                    $u = $pdo->prepare('UPDATE product_options SET product_id = ?, system_id = ? WHERE client_id = ? AND product_id = ?');
                    $u->execute([$baseId, $newSysId, $clientId, $srcId]);
                } else {
                    $u = $pdo->prepare('UPDATE product_options SET product_id = ? WHERE client_id = ? AND product_id = ?');
                    $u->execute([$baseId, $clientId, $srcId]);
                }
                $movedFabrics += $u->rowCount();

                // move markups / discounts → (master, newSystem). Optional tables.
                foreach (['client_markups', 'client_discounts'] as $t) {
                    try {
                        $pdo->prepare("UPDATE $t SET product_id = ?, system_id = ? WHERE client_id = ? AND product_id = ?")
                            ->execute([$baseId, $newSysId, $clientId, $srcId]);
                    } catch (Throwable $e) { /* table/col absent — skip */ }
                }

                // move extras → master, de-duped by name; remap any choice/extra
                // system scoping to the new system. Optional + best-effort.
                try {
                    $srcExtras = $pdo->prepare('SELECT id, name FROM product_extras WHERE client_id = ? AND product_id = ?');
                    $srcExtras->execute([$clientId, $srcId]);
                    $haveExtra = $pdo->prepare('SELECT id FROM product_extras WHERE client_id = ? AND product_id = ? AND name = ? LIMIT 1');
                    $delChoices = $pdo->prepare('DELETE FROM product_extra_choices WHERE product_extra_id = ?');
                    $delExtra   = $pdo->prepare('DELETE FROM product_extras WHERE id = ?');
                    foreach ($srcExtras->fetchAll(PDO::FETCH_ASSOC) as $ex) {
                        $haveExtra->execute([$clientId, $baseId, (string) $ex['name']]);
                        if ($haveExtra->fetchColumn() !== false) {
                            // master already has this extra → drop the duplicate
                            $delChoices->execute([(int) $ex['id']]);
                            $delExtra->execute([(int) $ex['id']]);
                        } else {
                            $pdo->prepare('UPDATE product_extras SET product_id = ? WHERE id = ?')
                                ->execute([$baseId, (int) $ex['id']]);
                            try {
                                $pdo->prepare(
                                    'UPDATE product_extra_choices SET system_id = ?
                                      WHERE product_extra_id = ? AND system_id IS NOT NULL'
                                )->execute([$newSysId, (int) $ex['id']]);
                            } catch (Throwable $e) { /* no system_id on choices */ }
                        }
                    }
                } catch (Throwable $e) { /* no extras tables — skip */ }

                // deactivate the now-empty source product
                $pdo->prepare('UPDATE products SET active = 0 WHERE id = ? AND client_id = ?')
                    ->execute([$srcId, $clientId]);
                $foldedIn++;
                $sort++;
            }

            $pdo->commit();

            // Audit (best-effort).
            try {
                require_once __DIR__ . '/../../_partials/catalogue_audit.php';
                if (function_exists('catalogue_audit_log')) {
                    catalogue_audit_log(
                        'product', $baseId, 'combine', $masterName, null,
                        ['systems' => $foldedIn + 1, 'price_tables_moved' => $movedTables,
                         'fabrics_moved' => $movedFabrics], $baseId
                    );
                }
            } catch (Throwable $e) { /* ignore */ }

            $names = array_map(static fn ($p) => (string) $p['name'], array_slice($products, 1));
            $_SESSION['flash_success'] =
                'Combined into "' . $masterName . '" with ' . ($foldedIn + 1) . ' systems. '
                . implode(', ', $names) . ' ' . (count($names) === 1 ? 'is' : 'are')
                . ' now empty and deactivated — delete once you\'ve checked the new product.';
            header('Location: /admin/products/edit.php?id=' . $baseId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Could not combine: ' . $e->getMessage();
        }
    }
}

// ── Render the confirm form ───────────────────────────────────────────────
$activeNav = 'products';
$defaultSysName = static function (string $productName): string {
    // Pull a slat/size hint out of the product name for the system name.
    if (preg_match('/(\d+\s?mm)/i', $productName, $m)) return strtolower(str_replace(' ', '', $m[1]));
    return $productName;
};
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Combine products &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <?php
                    require_once __DIR__ . '/../../_partials/breadcrumb.php';
                    echo render_breadcrumb([
                        ['Products', '/admin/products/index.php'],
                        ['Combine',  null],
                    ]);
                ?>
                <h1 class="page-title">Combine into one product</h1>
                <p class="page-subtitle">Each product below becomes a <strong>system</strong>
                   of a single master product. Their fabrics, price tables and settings move across.</p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.875rem 1.125rem;margin-bottom:1rem">
            <p style="margin:0;color:#0c4a6e;font-size:0.9375rem;line-height:1.5">
                The <strong>first</strong> product is reused as the master (it keeps its group and
                settings). The others fold in as systems and are then deactivated &mdash; their data
                isn't lost, it moves onto the master. You can delete the empty husks afterwards.
            </p>
        </section>

        <form method="post" action="/admin/products/combine.php" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="combine">
            <?php foreach ($products as $p): ?>
                <input type="hidden" name="product_ids[]" value="<?= (int) $p['id'] ?>">
            <?php endforeach; ?>

            <div class="form-row full">
                <div class="form-group">
                    <label for="master_name">Master product name <span class="required">*</span></label>
                    <input id="master_name" name="master_name" type="text" required maxlength="150"
                           value="<?= e(trim((string) ($_POST['master_name'] ?? ''))) ?>"
                           placeholder="e.g. Metal Venetian">
                </div>
            </div>

            <div class="table-wrap" style="margin-top:0.5rem">
                <table class="table">
                    <thead><tr><th>Product</th><th>Becomes system</th><th class="num">Fabrics</th></tr></thead>
                    <tbody>
                        <?php foreach ($products as $i => $p):
                            $pid = (int) $p['id'];
                            $posted = trim((string) ($_POST['system_name'][$pid] ?? ''));
                            $val = $posted !== '' ? $posted : $defaultSysName((string) $p['name']);
                        ?>
                            <tr>
                                <td>
                                    <?= e((string) $p['name']) ?>
                                    <?php if ($i === 0): ?>
                                        <span class="default-pill" style="display:inline-block;padding:0.0625rem 0.5rem;font-size:0.6875rem;font-weight:700;color:#fff;background:#0a58ca;border-radius:999px;margin-left:0.5rem;text-transform:uppercase;letter-spacing:0.05em">Master</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="text" name="system_name[<?= $pid ?>]" required maxlength="150"
                                           value="<?= e($val) ?>" style="width:12rem">
                                </td>
                                <td class="num">
                                    <?php
                                        $fc = db()->prepare('SELECT COUNT(*) FROM product_options WHERE client_id = ? AND product_id = ?');
                                        $fc->execute([$clientId, $pid]);
                                        echo (int) $fc->fetchColumn();
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions" style="margin-top:1rem">
                <button type="submit" class="btn btn-primary">Combine into one product</button>
                <a href="/admin/products/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </main>
</div>
</body>
</html>
