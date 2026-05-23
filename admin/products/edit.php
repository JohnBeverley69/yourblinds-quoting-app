<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /admin/products/index.php');
    exit;
}

// cost_price is optional (migrate_product_costs.php). Gracefully fall
// back to no column if the migration hasn't run on this server.
try {
    $loadStmt = db()->prepare(
        'SELECT id, name, option_label, sort_order, active, cost_price
           FROM products WHERE id = ? AND client_id = ?'
    );
    $loadStmt->execute([$id, $clientId]);
    $product = $loadStmt->fetch();
    $hasCostColumn = true;
} catch (Throwable $e) {
    $loadStmt = db()->prepare(
        'SELECT id, name, option_label, sort_order, active
           FROM products WHERE id = ? AND client_id = ?'
    );
    $loadStmt->execute([$id, $clientId]);
    $product = $loadStmt->fetch();
    $hasCostColumn = false;
}

if (!$product) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Product not found</h1>'
       . '<p><a href="/admin/products/index.php">Back to products</a></p>';
    exit;
}

// Markup / discount are now per (product, system). A product with
// systems carries one row per system; one without systems carries a
// single row keyed by system_id IS NULL.
//
// The pricing form only needs id+name. The inline Systems section
// at the bottom of this page wants more (sort_order, is_default,
// price-table count per system). One query feeds both.
$sysStmt = db()->prepare(
    'SELECT s.id, s.name, s.sort_order, s.active, s.is_default,
            (SELECT COUNT(*) FROM price_tables t
              WHERE t.system_id = s.id AND t.active = 1) AS table_count
       FROM product_systems s
      WHERE s.product_id = ? AND s.client_id = ? AND s.active = 1
   ORDER BY s.sort_order, s.name'
);
$sysStmt->execute([$id, $clientId]);
$systems = $sysStmt->fetchAll(PDO::FETCH_ASSOC);

// Fabrics, grouped by band_code, for the inline Fabrics section.
// We sort by band first then sort_order so each band's first few
// names give a representative sample for the summary view.
$fabStmt = db()->prepare(
    'SELECT id, band_code, supplier_name, name, colour, code,
            sort_order, active
       FROM product_options
      WHERE product_id = ? AND client_id = ? AND active = 1
   ORDER BY band_code, sort_order, name'
);
$fabStmt->execute([$id, $clientId]);
$fabricsByBand = [];
foreach ($fabStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $band = (string) ($r['band_code'] ?? '');
    if ($band === '') $band = '(no band)';
    $fabricsByBand[$band][] = $r;
}
ksort($fabricsByBand);
$fabricsTotal = array_sum(array_map('count', $fabricsByBand));

// Options + their choices for the inline Options section.
// Two queries (options + choices) then a fold by extra_id — cheaper
// than one big JOIN that duplicates option rows per choice.
$optStmt = db()->prepare(
    'SELECT id, name, is_required, sort_order, active
       FROM product_extras
      WHERE product_id = ? AND client_id = ? AND active = 1
   ORDER BY sort_order, name'
);
$optStmt->execute([$id, $clientId]);
$options = $optStmt->fetchAll(PDO::FETCH_ASSOC);

$choicesByOption = [];
if ($options) {
    $optIds = array_map(static fn ($o) => (int) $o['id'], $options);
    $ph     = implode(',', array_fill(0, count($optIds), '?'));
    // Include ALL the price + toggle fields the inline grid editor
    // needs. We also include inactive choices because the grid shows
    // an "Active" toggle for each row.
    $cStmt  = db()->prepare(
        "SELECT id, product_extra_id, label, system_id, is_default,
                price_delta, price_percent, price_per_metre, active,
                sort_order
           FROM product_extra_choices
          WHERE product_extra_id IN ($ph)
       ORDER BY product_extra_id, sort_order, label"
    );
    $cStmt->execute($optIds);
    foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $choicesByOption[(int) $r['product_extra_id']][] = $r;
    }
}

// Helper closure for the inline-edit grid's per-row "Available on"
// multi-select widget. Shared with extra.php — same source of truth.
require_once __DIR__ . '/../../_partials/choices_grid_helpers.php';
$renderSystemMultiSelect = make_render_system_multi_select($systems);

// Price tables, grouped by system, with cell count per table for the
// "at a glance" row count. Tables with 0 cells get a "needs filling"
// warning pill in the render.
$ptStmt = db()->prepare(
    'SELECT t.id, t.system_id, t.band_code, t.name,
            (SELECT COUNT(*) FROM price_table_rows r WHERE r.price_table_id = t.id) AS cell_count,
            s.name AS system_name
       FROM price_tables t
       LEFT JOIN product_systems s ON s.id = t.system_id
      WHERE t.product_id = ? AND t.client_id = ? AND t.active = 1
   ORDER BY s.sort_order, s.name, t.band_code'
);
$ptStmt->execute([$id, $clientId]);
$priceTablesBySystem = [];
foreach ($ptStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $sysName = $r['system_name'] !== null ? (string) $r['system_name'] : '(no system)';
    $priceTablesBySystem[$sysName][] = $r;
}
$priceTablesTotal = array_sum(array_map('count', $priceTablesBySystem));

$mStmt = db()->prepare(
    'SELECT system_id, markup_percent FROM client_markups
      WHERE client_id = ? AND product_id = ?'
);
$mStmt->execute([$clientId, $id]);
$markupsBySystem = [];   // string key: '' for NULL, otherwise system_id
foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $key = $r['system_id'] === null ? '' : (string) (int) $r['system_id'];
    $markupsBySystem[$key] = (string) $r['markup_percent'];
}

$dStmt = db()->prepare(
    'SELECT system_id, discount_percent FROM client_discounts
      WHERE client_id = ? AND product_id = ?'
);
$dStmt->execute([$clientId, $id]);
$discountsBySystem = [];
foreach ($dStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $key = $r['system_id'] === null ? '' : (string) (int) $r['system_id'];
    $discountsBySystem[$key] = (string) $r['discount_percent'];
}

// $f['markup'] and $f['discount'] are keyed by system_id (or '' for
// products without systems). Default missing entries to '0.00'.
$f = [
    'name'         => (string) $product['name'],
    'active'       => (int)    $product['active'],
    // option_label is the per-product wording for whatever the "fabric"
    // axis is called in this product's world. "Fabric" for rollers,
    // "Colour" for metal venetians, "Finish" for wood, etc.
    'option_label' => (string) ($product['option_label'] ?? 'Fabric'),
    'cost_price'   => isset($product['cost_price']) && $product['cost_price'] !== null
                          ? (string) $product['cost_price']
                          : '',
    'markup'       => [],
    'discount'     => [],
];
if ($systems) {
    foreach ($systems as $s) {
        $key = (string) (int) $s['id'];
        $f['markup'][$key]   = $markupsBySystem[$key]   ?? '0.00';
        $f['discount'][$key] = $discountsBySystem[$key] ?? '0.00';
    }
} else {
    $f['markup']['']   = $markupsBySystem['']   ?? '0.00';
    $f['discount'][''] = $discountsBySystem[''] ?? '0.00';
}
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['name']         = trim((string) ($_POST['name']         ?? ''));
    $f['active']       = !empty($_POST['active']) ? 1 : 0;
    $f['option_label'] = trim((string) ($_POST['option_label'] ?? '')) ?: 'Fabric';
    $f['cost_price']   = trim((string) ($_POST['cost_price']   ?? ''));

    // Two posting shapes depending on whether the product has systems:
    //   With systems:    markup[<sysid>] / discount[<sysid>]  (arrays)
    //   Without systems: markup / discount                    (scalars)
    // Keeping them shape-distinct avoids HTML-form gymnastics (you can't
    // easily post `array[''=>v]` from <input name="..."> markup).
    $f['markup']   = [];
    $f['discount'] = [];
    $validKeys     = [];
    if ($systems) {
        $postedMarkup   = is_array($_POST['markup']   ?? null) ? $_POST['markup']   : [];
        $postedDiscount = is_array($_POST['discount'] ?? null) ? $_POST['discount'] : [];
        foreach ($systems as $s) {
            $k = (string) (int) $s['id'];
            $f['markup'][$k]   = trim((string) ($postedMarkup[$k]   ?? '0'));
            $f['discount'][$k] = trim((string) ($postedDiscount[$k] ?? '0'));
            $validKeys[]       = $k;
        }
    } else {
        $f['markup']['']   = trim((string) ($_POST['markup']   ?? '0'));
        $f['discount'][''] = trim((string) ($_POST['discount'] ?? '0'));
        $validKeys[]       = '';
    }

    // Empty string is valid — it means "inherit the default" and the
    // save handler will DELETE the row. Non-empty must parse as a
    // non-negative number.
    $validateNum = static function (string $v, string $label) {
        if ($v === '') return null;
        if (!is_numeric($v) || (float) $v < 0) {
            return "$label must be a non-negative number.";
        }
        return null;
    };

    if ($f['name'] === '') {
        $error = 'Product name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = 'Product name is too long (max 150 characters).';
    } else {
        foreach ($validKeys as $k) {
            $error = $validateNum($f['markup'][$k],   'Markup %');
            if ($error) break;
            $error = $validateNum($f['discount'][$k], 'Discount %');
            if ($error) break;
        }
    }

    if ($error === null) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // option_label is now editable from the form — admins can
            // call it "Fabric" (rollers, romans), "Colour" (metal
            // venetians), "Finish" (wood venetians), etc. Blank coerces
            // back to "Fabric" so quote-builder UI never goes label-less.
            //
            // sort_order is intentionally not touched here — drag-and-drop
            // on the products list is the only writer.
            //
            // cost_price: empty string saves NULL (= "not set, treat as
            // 0 in dashboard"). A 0 entered explicitly also saves as 0
            // — they're equivalent for the gross-profit calc but NULL
            // is the cleaner "I haven't filled this in yet" signal.
            $costValue = ($f['cost_price'] === '' || !is_numeric($f['cost_price']))
                ? null
                : (float) $f['cost_price'];

            if ($hasCostColumn) {
                $u = $pdo->prepare(
                    "UPDATE products
                        SET name = ?, active = ?, option_label = ?,
                            cost_price = ?
                      WHERE id = ? AND client_id = ?"
                );
                $u->execute([$f['name'], $f['active'], $f['option_label'], $costValue, $id, $clientId]);
            } else {
                $u = $pdo->prepare(
                    "UPDATE products
                        SET name = ?, active = ?, option_label = ?
                      WHERE id = ? AND client_id = ?"
                );
                $u->execute([$f['name'], $f['active'], $f['option_label'], $id, $clientId]);
            }

            // Markup: 0 (or empty) DELETES the row so the engine falls
            // back to the tenant default (client_settings
            // .default_price_table_markup_pct). Non-zero values upsert
            // an explicit override row. Mirrors the discount semantics
            // below — keeps the table from accumulating stale 0% rows
            // that look like overrides but aren't.
            $insMarkup = $pdo->prepare(
                'INSERT INTO client_markups (client_id, product_id, system_id, markup_percent)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE markup_percent = VALUES(markup_percent)'
            );
            $delMarkup = $pdo->prepare(
                'DELETE FROM client_markups
                  WHERE client_id = ? AND product_id = ?
                    AND ((system_id IS NULL AND ? IS NULL) OR system_id = ?)'
            );
            // Discount: same shape, 0 also deletes the row.
            $insDiscount = $pdo->prepare(
                'INSERT INTO client_discounts (client_id, product_id, system_id, discount_percent)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE discount_percent = VALUES(discount_percent)'
            );
            $delDiscount = $pdo->prepare(
                'DELETE FROM client_discounts
                  WHERE client_id = ? AND product_id = ?
                    AND ((system_id IS NULL AND ? IS NULL) OR system_id = ?)'
            );

            foreach ($validKeys as $k) {
                $sysId = $k === '' ? null : (int) $k;

                $mp = (float) $f['markup'][$k];
                if ($mp > 0) {
                    $insMarkup->execute([$clientId, $id, $sysId, $mp]);
                } else {
                    // The "IS NULL ... = ?" trick needs the same value
                    // twice so the prepared statement covers both the
                    // NULL and non-NULL system_id cases.
                    $delMarkup->execute([$clientId, $id, $sysId, $sysId]);
                }

                $dp = (float) $f['discount'][$k];
                if ($dp > 0) {
                    $insDiscount->execute([$clientId, $id, $sysId, $dp]);
                } else {
                    $delDiscount->execute([$clientId, $id, $sysId, $sysId]);
                }
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Product updated.';
            header('Location: /admin/products/index.php');
            exit;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $msg = $e->getMessage();
            if (str_contains($msg, 'uniq_product_client_name')) {
                $error = 'A product with that name already exists.';
            } elseif (str_contains($msg, 'Out of range') || str_contains($msg, '1264')) {
                // MySQL 1264: numeric value out of range for the column.
                // Most likely a markup/discount past DECIMAL(8,2)'s
                // 999999.99 cap — re-prompt with something actionable
                // instead of the raw "Out of range value for column..."
                $error = 'A markup or discount % is too large for the column. '
                       . 'Maximum is 999999.99.';
            } else {
                $error = 'Could not save product: ' . $msg;
            }
        }
    }
}

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- csrf-token meta is required by the choices-grid inline JS
         (it sends X-CSRF-Token on every fetch to /choice-api.php). -->
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Edit product &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <?php
        // Phase 2B: choices-grid CSS shared with extra.php so each
        // Option below can embed a fully inline-editable grid.
        require __DIR__ . '/../../_partials/choices_grid_css.php';
    ?>
    <style>
        .form-group input[type="number"] {
            width: 100%; font: inherit; padding: 0.5625rem 0.75rem;
            border: 1px solid #d1d5db; border-radius: 8px; background: #fff;
        }
        /* Kill the browser's up/down spinner on the markup/discount
           inputs — pricing % is typed by hand, the spinner just adds
           noise. */
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none; margin: 0;
        }
        input[type="number"] { -moz-appearance: textfield; }
        .toggle-stack {
            display: flex; flex-direction: column; gap: 0.625rem;
            margin: 1.25rem 0;
        }
        .toggle-stack label {
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-size: 0.9375rem; color: #111827; cursor: pointer;
            margin: 0; padding: 0;
        }
        .toggle-stack input[type="checkbox"] { width: 18px; height: 18px; }
        .toggle-stack small {
            color: #6b7280; font-size: 0.8125rem; margin-left: 0.375rem;
        }
        .pricing-table {
            width: 100%; border-collapse: collapse;
            font-size: 0.9375rem;
        }
        .pricing-table th, .pricing-table td {
            padding: 0.5rem 0.625rem; text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        .pricing-table th {
            font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.04em; color: #6b7280; font-weight: 600;
        }
        .pricing-table td.system-name { font-weight: 500; color: #111827; }
        .pricing-table td.num { width: 7rem; }
        .pricing-table td.num input {
            width: 100%; padding: 0.4375rem 0.625rem;
            border: 1px solid #d1d5db; border-radius: 6px; background: #fff;
            font: inherit; box-sizing: border-box;
        }
    </style>
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
                        ['Products',                          '/admin/products/index.php'],
                        [(string) $product['name'],           null],
                    ]);
                ?>
                <h1 class="page-title">Edit <?= e((string) $product['name']) ?></h1>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                <!-- Duplicate clones this product (systems, fabrics, options,
                     choices, price tables, markups, discounts) and drops the
                     user on the new product's edit page. Saves a lot of
                     re-entry when building a "Premium" variant of a
                     "Standard" product. -->
                <form method="post"
                      action="/admin/products/duplicate.php"
                      style="display:inline;margin:0"
                      data-confirm="Duplicate <?= e((string) $product['name']) ?>? Creates a full copy (systems, fabrics, options, choices, price tables) with '(copy)' appended to the name.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <button type="submit" class="btn btn-secondary"
                            style="display:inline-flex;align-items:center;gap:0.4375rem">
                        <span aria-hidden="true">📋</span>
                        Duplicate product
                    </button>
                </form>

                <!-- "Preview as salesperson" launches a drawer that mounts
                     a mini quote-builder for this product. Lets the admin
                     verify their setup (cascading selects, conditional
                     options, computed price) without leaving the page. -->
                <button type="button" id="preview-open-btn" class="btn btn-secondary"
                        style="display:inline-flex;align-items:center;gap:0.4375rem">
                    <span aria-hidden="true">👁</span>
                    Preview as salesperson
                </button>
            </div>
        </div>

        <?php
            // Flash messages set by either this page's own save handler
            // or by sibling handlers (systems.php / options.php /
            // extras.php / etc.) when they redirect back here via
            // the return_to param the inline quick-add forms supply.
            $flashMsg = $_SESSION['flash_success'] ?? null;
            $flashErr = $_SESSION['flash_error']   ?? null;
            unset($_SESSION['flash_success'], $_SESSION['flash_error']);
        ?>
        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <!--
            Catalogue health. Runs the validator and surfaces anything
            broken / risky / nice-to-fix as colour-coded chips. Empty
            on a clean product so the page doesn't shout when there's
            nothing to say. Critical issues mean the product can't be
            quoted at all (no fabric / no price table / orphan tables);
            warnings mean something's half-built; hints are
            nice-to-haves.
        -->
        <?php
            require_once __DIR__ . '/../../_partials/catalogue_validator.php';
            $catalogueIssues = catalogue_validate_product((int) $id, (int) $clientId);
            if ($catalogueIssues):
                $worst = catalogue_worst_severity($catalogueIssues);
        ?>
            <details id="catalogue-health" open style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;
                                  padding:0.625rem 0.875rem;margin-bottom:0.875rem">
                <summary style="cursor:pointer;list-style:none;display:flex;
                                align-items:center;gap:0.5rem;font-weight:600;
                                color:#1f3b5b;font-size:0.9375rem">
                    <span aria-hidden="true">
                        <?= $worst === 'critical' ? '🚨' : ($worst === 'warning' ? '⚠️' : 'ℹ️') ?>
                    </span>
                    Catalogue health
                    <span style="font-weight:400;color:#6b7280;font-size:0.8125rem">
                        — <?= count($catalogueIssues) ?> issue<?= count($catalogueIssues) === 1 ? '' : 's' ?> to look at
                    </span>
                </summary>
                <div style="margin-top:0.625rem">
                    <?= catalogue_render_chips($catalogueIssues) ?>
                </div>
            </details>
        <?php else: ?>
            <!-- All clear — small green confirmation strip so the user
                 sees the validator is actually running and has nothing
                 to flag. -->
            <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;
                        padding:0.4375rem 0.75rem;border-radius:8px;
                        margin-bottom:0.875rem;font-size:0.8125rem;
                        display:flex;align-items:center;gap:0.5rem">
                <span aria-hidden="true">✓</span>
                <strong>Catalogue health:</strong> All checks pass — this product is ready to quote.
            </div>
        <?php endif; ?>

        <!--
            Setup checklist + jump-to links. A new tenant looking at a
            blank product needs to know what else to set up before quotes
            can be raised against it. This panel is that map.

            We compute "done"/"todo" indicators by counting rows for each
            piece — gives the user a sense of progress without forcing
            them through a wizard.
        -->
        <?php
            $cnt = static function (string $sql, array $params): int {
                $st = db()->prepare($sql);
                $st->execute($params);
                return (int) $st->fetchColumn();
            };
            $sysCount = $cnt('SELECT COUNT(*) FROM product_systems WHERE product_id = ? AND client_id = ? AND active = 1', [$id, $clientId]);
            $fabCount = $cnt('SELECT COUNT(*) FROM product_options WHERE product_id = ? AND client_id = ? AND active = 1', [$id, $clientId]);
            $extCount = $cnt('SELECT COUNT(*) FROM product_extras  WHERE product_id = ? AND client_id = ? AND active = 1', [$id, $clientId]);
            $ptCount  = $cnt('SELECT COUNT(*) FROM price_tables    WHERE product_id = ? AND client_id = ? AND active = 1', [$id, $clientId]);

            // price-tables.php is scoped per-system (each system has
            // its own width × drop grid per band). Decide where the
            // tile should link based on how many systems this product
            // has:
            //   0 systems → systems page (user needs to add one first)
            //   1 system  → straight to that system's price tables
            //   2+ systems → systems list (user picks which one)
            // Without this, the tile linked to ?product_id=N which the
            // price-tables page doesn't understand, so it 302'd back
            // to /admin/products/index.php — looking like the click
            // had been silently undone.
            $priceTablesHref = '/admin/products/systems.php?product_id=' . (int) $id;
            if ($sysCount === 1) {
                $soloSysSt = db()->prepare(
                    'SELECT id FROM product_systems
                      WHERE product_id = ? AND client_id = ? AND active = 1
                      LIMIT 1'
                );
                $soloSysSt->execute([$id, $clientId]);
                $soloSysId = (int) ($soloSysSt->fetchColumn() ?: 0);
                if ($soloSysId > 0) {
                    $priceTablesHref = '/admin/products/price-tables.php?system_id=' . $soloSysId;
                }
            }

            $tile = static function (
                string $label, string $href, int $count,
                string $emptyHint, string $doneHint
            ): string {
                $isDone  = $count > 0;
                $bg      = $isDone ? '#f0fdf4' : '#fefce8';
                $border  = $isDone ? '#86efac' : '#fde047';
                $badgeBg = $isDone ? '#16a34a' : '#ca8a04';
                $hint    = $isDone ? $doneHint : $emptyHint;
                $icon    = $isDone ? '✓' : '!';
                $countTxt = $count . ' ' . ($count === 1 ? 'item' : 'items');
                return '<a href="' . e($href) . '" '
                     . 'style="display:flex;flex-direction:column;gap:0.25rem;'
                     . 'padding:0.875rem;background:' . $bg . ';'
                     . 'border:1px solid ' . $border . ';border-radius:10px;'
                     . 'text-decoration:none;color:inherit">'
                     . '<div style="display:flex;align-items:center;gap:0.5rem;'
                     . 'font-weight:700;color:#111827">'
                     . '<span style="display:inline-flex;align-items:center;'
                     . 'justify-content:center;width:1.25rem;height:1.25rem;'
                     . 'background:' . $badgeBg . ';color:#fff;border-radius:999px;'
                     . 'font-size:0.75rem">' . $icon . '</span>'
                     . e($label)
                     . '</div>'
                     . '<div style="font-size:0.8125rem;color:#374151">'
                     . ($isDone ? e($countTxt) . ' &mdash; ' : '')
                     . e($hint)
                     . '</div></a>';
            };
        ?>
        <section class="section" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:1rem 1.125rem;margin-bottom:1rem">
            <h2 style="margin:0 0 0.625rem;font-size:1rem;color:#0c4a6e">
                Setting this product up
            </h2>
            <p style="margin:0 0 0.875rem;color:#0c4a6e;font-size:0.875rem;line-height:1.5">
                A product needs at least one fabric / option and one price table before salespeople
                can raise quotes against it. Systems and Options are how you handle variants and
                customer choices.
            </p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.625rem">
                <?= $tile(
                    'Systems',
                    '/admin/products/systems.php?product_id=' . (int) $id,
                    $sysCount,
                    'Add if this blind has style variants (motorised/corded, slat sizes, etc.). Skip if not.',
                    'click to manage'
                ) ?>
                <?= $tile(
                    'Fabrics',
                    '/admin/products/options.php?product_id=' . (int) $id,
                    $fabCount,
                    'Add at least one fabric/colour. Each carries a band code (A/B/C…) used by price tables.',
                    'click to manage'
                ) ?>
                <?= $tile(
                    'Options',
                    '/admin/products/extras.php?product_id=' . (int) $id,
                    $extCount,
                    'Add the things the salesperson picks per blind (control side, bracket colour, etc.). Optional.',
                    'click to manage'
                ) ?>
                <?= $tile(
                    'Price tables',
                    $priceTablesHref,
                    $ptCount,
                    'Width × drop pricing grids per band. At least one is needed to quote.',
                    'click to manage'
                ) ?>
            </div>
        </section>

        <section class="section">
            <form method="post" action="/admin/products/edit.php?id=<?= (int) $id ?>"
                  class="form" novalidate>
                <?= csrf_field() ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="name">Name <span class="required">*</span></label>
                        <input id="name" name="name" type="text"
                               required maxlength="150"
                               value="<?= e((string) $f['name']) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="option_label">Option label</label>
                        <input id="option_label" name="option_label" type="text"
                               maxlength="40"
                               value="<?= e((string) $f['option_label']) ?>"
                               placeholder="Fabric">
                        <small style="color:#6b7280;font-size:0.8125rem">
                            What this product's "option" axis is called &mdash; used as the
                            label on the quote builder and the options/list pages.
                            Common values: <em>Fabric</em> (rollers, romans),
                            <em>Colour</em> (metal venetians),
                            <em>Finish</em> (wood venetians).
                            Each option you add to this product (under Options) sits under this label.
                        </small>
                    </div>
                </div>

                <?php /* Per-product wholesale cost field removed — cost is
                         taken directly from your price tables (the price-
                         table cell is treated as the cost basis, with
                         Markup % adding the sell-side margin). The
                         products.cost_price column still exists in the
                         schema so we can resurrect this later if a tenant
                         wants per-product overhead, but no UI for now. */ ?>

                <fieldset style="border:1px solid #e5e7eb;border-radius:10px;padding:1rem 1.125rem;margin:1rem 0">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;font-weight:600;color:#1f3b5b;text-transform:uppercase;letter-spacing:0.05em">
                        Pricing per system
                    </legend>
                    <?php
                        // Read the tenant-wide default so we can show it
                        // as a hint here — "leave at 0 to inherit the
                        // default X%". Defensive against the migration
                        // not having run yet.
                        $_tenantDefaultMarkup = 0.0;
                        try {
                            $_dmSt = db()->prepare(
                                'SELECT default_price_table_markup_pct
                                   FROM client_settings WHERE client_id = ? LIMIT 1'
                            );
                            $_dmSt->execute([$clientId]);
                            $_tenantDefaultMarkup = (float) ($_dmSt->fetchColumn() ?: 0);
                        } catch (Throwable $e) { /* migration not yet run */ }
                    ?>
                    <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.75rem">
                        Margin and discount can be tuned per system (premium / motorised /
                        standard are usually priced differently). Markup is applied on top
                        of the price-table base; discount comes off after that.
                        <strong>Set markup to 0 to inherit the tenant default
                        (<?= number_format($_tenantDefaultMarkup, 2) ?>%
                        — change on
                        <a href="/admin/settings.php#default_price_table_markup_pct"
                           style="color:#1f3b5b">Settings</a>).</strong>
                        <?php if (!$systems): ?>
                            <br><em>No systems on this product yet — values below apply to
                            every quote. Add systems on the
                            <a href="/admin/products/systems.php?product_id=<?= (int) $id ?>"
                               style="color:#1f3b5b">Systems</a> page to split them out.</em>
                        <?php endif; ?>
                    </p>

                    <?php if ($systems): ?>
                        <table class="pricing-table">
                            <thead>
                                <tr>
                                    <th>System</th>
                                    <th style="width:7rem">Markup %</th>
                                    <th style="width:7rem">Discount %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($systems as $s):
                                    $key = (string) (int) $s['id'];
                                    $markupVal = (float) ($f['markup'][$key] ?? 0);
                                    // 0 (or missing) = inheriting the default. Show as
                                    // empty input with a "using default (X%)" tag so the
                                    // row's actual effective rate is visible at a glance.
                                    $isMarkupDefault = $markupVal === 0.0;
                                    $markupDisplay   = $isMarkupDefault
                                        ? ''
                                        : (string) ($f['markup'][$key] ?? '');
                                ?>
                                    <tr>
                                        <td class="system-name"><?= e((string) $s['name']) ?></td>
                                        <td class="num">
                                            <input type="number" step="0.01" min="0"
                                                   name="markup[<?= (int) $s['id'] ?>]"
                                                   value="<?= e($markupDisplay) ?>"
                                                   placeholder="<?= e(number_format($_tenantDefaultMarkup, 2)) ?>"
                                                   title="<?= $isMarkupDefault
                                                       ? 'Inheriting tenant default — leave empty / 0 to keep using it.'
                                                       : 'Override of the tenant default. Clear or set 0 to revert.' ?>">
                                            <?php if ($isMarkupDefault): ?>
                                                <div style="font-size:0.6875rem;color:#6b7280;margin-top:0.125rem;line-height:1.2">
                                                    using default
                                                    (<?= number_format($_tenantDefaultMarkup, 2) ?>%)
                                                </div>
                                            <?php else: ?>
                                                <div style="font-size:0.6875rem;color:#9333ea;margin-top:0.125rem;line-height:1.2;font-weight:600">
                                                    override
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="num">
                                            <input type="number" step="0.01" min="0"
                                                   name="discount[<?= (int) $s['id'] ?>]"
                                                   value="<?= e((string) ($f['discount'][$key] ?? '0.00')) ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else:
                        // Same default-inheritance UX for products without
                        // systems: empty/0 input → inherit default; non-zero
                        // → explicit override.
                        $nsMarkupVal = (float) ($f['markup'][''] ?? 0);
                        $nsIsDefault = $nsMarkupVal === 0.0;
                        $nsMarkupDisplay = $nsIsDefault ? '' : (string) ($f['markup'][''] ?? '');
                    ?>
                        <div class="form-row cols-2">
                            <div class="form-group">
                                <label for="markup_no_sys">Markup %</label>
                                <input id="markup_no_sys" name="markup" type="number"
                                       step="0.01" min="0"
                                       value="<?= e($nsMarkupDisplay) ?>"
                                       placeholder="<?= e(number_format($_tenantDefaultMarkup, 2)) ?>">
                                <?php if ($nsIsDefault): ?>
                                    <small style="color:#6b7280;font-size:0.75rem;display:block;margin-top:0.1875rem">
                                        Using default (<?= number_format($_tenantDefaultMarkup, 2) ?>%)
                                    </small>
                                <?php else: ?>
                                    <small style="color:#9333ea;font-size:0.75rem;font-weight:600;display:block;margin-top:0.1875rem">
                                        Override
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="discount_no_sys">Discount %</label>
                                <input id="discount_no_sys" name="discount" type="number"
                                       step="0.01" min="0"
                                       value="<?= e((string) ($f['discount'][''] ?? '0.00')) ?>">
                            </div>
                        </div>
                    <?php endif; ?>
                </fieldset>

                <div class="toggle-stack">
                    <label for="active">
                        <input type="checkbox" id="active" name="active" value="1"
                               <?= (int) $f['active'] === 1 ? 'checked' : '' ?>>
                        Active
                        <small>uncheck to hide from quote builder</small>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="/admin/products/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>

        <!--
            CATALOGUE SECTIONS
            ──────────────────
            Phase 2 of the products-UX rebuild: every part of a product's
            catalogue surfaced on one page so the admin doesn't bounce
            between systems.php / options.php / extras.php / price-
            tables.php for routine inspection. Each section is a
            collapsible <details>, expanded by default. Deep-edit links
            into the dedicated pages remain as the canonical "manage"
            path for anything not handled inline here.
        -->
        <style>
            details.cat-section {
                background: #fff; border: 1px solid #e5e7eb;
                border-radius: 12px; padding: 0; margin-bottom: 1rem;
                overflow: hidden;
            }
            details.cat-section > summary {
                list-style: none; cursor: pointer;
                padding: 0.875rem 1.125rem;
                background: #f8fafc; border-bottom: 1px solid #e5e7eb;
                display: flex; align-items: center; gap: 0.625rem;
                font-size: 1.0625rem; font-weight: 600; color: #1f3b5b;
            }
            details.cat-section[open] > summary { background: #eef2f7; }
            details.cat-section > summary::-webkit-details-marker { display: none; }
            details.cat-section > summary::before {
                content: '▸'; display: inline-block;
                transition: transform 150ms;
                color: #6b7280; font-size: 0.875rem;
            }
            details.cat-section[open] > summary::before { transform: rotate(90deg); }
            details.cat-section > summary .count {
                color: #6b7280; font-weight: 500; font-size: 0.9375rem;
            }
            details.cat-section > summary .actions {
                margin-left: auto; display: flex; gap: 0.375rem;
            }
            details.cat-section > summary .actions a,
            details.cat-section > summary .actions form button {
                font-size: 0.8125rem; padding: 0.3125rem 0.625rem;
                background: #fff; color: #1f3b5b;
                border: 1px solid #d1d5db; border-radius: 6px;
                text-decoration: none; font-weight: 500;
                cursor: pointer;
            }
            details.cat-section > summary .actions a:hover,
            details.cat-section > summary .actions form button:hover {
                border-color: #1f3b5b; background: #f3f4f6;
            }
            details.cat-section > .body { padding: 0.875rem 1.125rem 1rem; }
            details.cat-section .item-row {
                display: grid; gap: 0.625rem;
                grid-template-columns: 1.5rem 1fr auto auto auto;
                align-items: center;
                padding: 0.5rem 0.5rem;
                border-bottom: 1px solid #f3f4f6;
            }
            details.cat-section .item-row:last-of-type { border-bottom: 0; }
            details.cat-section .item-row .drag {
                color: #9ca3af; cursor: grab; user-select: none;
                text-align: center;
            }
            details.cat-section .item-row .drag:active { cursor: grabbing; }
            details.cat-section .item-row .name {
                font-weight: 500; color: #111827;
            }
            details.cat-section .item-row .pill {
                display: inline-block; padding: 0.0625rem 0.5rem;
                border-radius: 999px; font-size: 0.6875rem; font-weight: 700;
                text-transform: uppercase; letter-spacing: 0.05em;
            }
            details.cat-section .item-row .pill-default {
                background: #d1fae5; color: #065f46;
            }
            details.cat-section .item-row .pill-tables {
                background: #e0e7ff; color: #3730a3;
            }
            details.cat-section .item-row .pill-empty {
                background: #fef3c7; color: #92400e;
            }
            details.cat-section .item-row .row-actions {
                display: flex; gap: 0.5rem;
            }
            details.cat-section .item-row .row-actions a,
            details.cat-section .item-row .row-actions button {
                font-size: 0.8125rem; padding: 0.1875rem 0.4375rem;
                background: transparent; border: 0; cursor: pointer;
                color: #1f3b5b; text-decoration: none;
            }
            details.cat-section .item-row .row-actions .delete {
                color: #b91c1c;
            }
            details.cat-section .empty-note {
                color: #9ca3af; font-style: italic; padding: 0.5rem 0.25rem;
            }
            details.cat-section .quick-add {
                background: #f9fafb; border: 1px dashed #d1d5db;
                border-radius: 8px; padding: 0.75rem 0.875rem;
                margin-top: 0.625rem;
                display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: end;
            }
            details.cat-section .quick-add label {
                font-size: 0.6875rem; text-transform: uppercase;
                letter-spacing: 0.05em; color: #6b7280; font-weight: 600;
                display: block; margin-bottom: 0.1875rem;
            }
            details.cat-section .quick-add input[type="text"] {
                padding: 0.375rem 0.5rem; border: 1px solid #d1d5db;
                border-radius: 6px; font: inherit;
            }
            details.cat-section .quick-add button {
                padding: 0.4375rem 0.875rem; background: #1f3b5b;
                color: #fff; border: 0; border-radius: 6px;
                font-weight: 600; cursor: pointer; font-size: 0.875rem;
            }
        </style>

        <!-- ─── Systems ─────────────────────────────────────────── -->
        <details class="cat-section">
            <summary>
                Systems
                <span class="count">(<?= count($systems) ?>)</span>
                <span class="actions">
                    <a href="/admin/products/systems.php?product_id=<?= (int) $id ?>">Full manage &raquo;</a>
                </span>
            </summary>
            <div class="body">
                <?php if (!$systems): ?>
                    <div class="empty-note">
                        No systems yet. Add one below if this blind has variants with different price grids
                        (e.g. <em>Standard</em> vs <em>Motorised</em>, or <em>25mm</em> vs <em>50mm slat</em>).
                        Skip if not — products without systems still quote.
                    </div>
                <?php else: foreach ($systems as $s): ?>
                    <div class="item-row">
                        <div class="drag" title="Drag to reorder (use Full manage page)">⋮⋮</div>
                        <div class="name">
                            <a href="/admin/products/price-tables.php?system_id=<?= (int) $s['id'] ?>"
                               style="color:#1f3b5b;text-decoration:none">
                                <?= e((string) $s['name']) ?>
                            </a>
                            <?php if ((int) $s['is_default'] === 1): ?>
                                <span class="pill pill-default">Default</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="pill <?= (int) $s['table_count'] === 0 ? 'pill-empty' : 'pill-tables' ?>">
                                <?= (int) $s['table_count'] ?>
                                price table<?= (int) $s['table_count'] === 1 ? '' : 's' ?>
                            </span>
                        </div>
                        <div></div>
                        <div class="row-actions">
                            <a href="/admin/products/price-tables.php?system_id=<?= (int) $s['id'] ?>">Open</a>
                        </div>
                    </div>
                <?php endforeach; endif; ?>

                <!-- Quick-add a system inline. Posts to the existing
                     handler on systems.php which already validates +
                     redirects back here with a flash. -->
                <form method="post" action="/admin/products/systems.php?product_id=<?= (int) $id ?>"
                      class="quick-add">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="create">
                    <!-- Return path so the systems handler can bounce
                         straight back here instead of its own list. -->
                    <input type="hidden" name="return_to" value="/admin/products/edit.php?id=<?= (int) $id ?>">
                    <div style="flex:1 1 16rem">
                        <label for="qa-sys-name">Add a new system</label>
                        <input id="qa-sys-name" name="name" type="text" maxlength="150"
                               placeholder="e.g. Slim Line / Motorised / 25mm slat"
                               style="width:100%">
                    </div>
                    <button type="submit">+ Add system</button>
                </form>
            </div>
        </details>

        <!-- ─── Fabrics ───────────────────────────────────────── -->
        <details class="cat-section">
            <summary>
                <?= e((string) ($product['option_label'] ?? 'Fabric')) ?>s
                <span class="count">(<?= (int) $fabricsTotal ?>)</span>
                <span class="actions">
                    <a href="/admin/products/options-import.php?product_id=<?= (int) $id ?>">Import &raquo;</a>
                    <a href="/admin/products/options.php?product_id=<?= (int) $id ?>">Full manage &raquo;</a>
                </span>
            </summary>
            <div class="body">
                <?php if (!$fabricsByBand): ?>
                    <div class="empty-note">
                        No <?= e(strtolower((string) ($product['option_label'] ?? 'fabric'))) ?>s yet.
                        Add at least one before this product can be quoted &mdash; or use the
                        <strong>Import</strong> button to bulk-load from a supplier spreadsheet.
                    </div>
                <?php else: foreach ($fabricsByBand as $band => $items):
                    // Per-band block — header + first 6 names inline so
                    // the user can confirm without expanding the full
                    // list. Click through to options.php for editing.
                    $shown = array_slice($items, 0, 6);
                    $more  = count($items) - count($shown);
                ?>
                    <div style="padding:0.5rem 0.25rem;border-bottom:1px solid #f3f4f6">
                        <div style="display:flex;align-items:baseline;gap:0.625rem;flex-wrap:wrap">
                            <span class="pill pill-tables">Band <?= e((string) $band) ?></span>
                            <span style="color:#6b7280;font-size:0.875rem">
                                <?= count($items) ?>
                                <?= count($items) === 1 ? (strtolower((string) ($product['option_label'] ?? 'fabric'))) : (strtolower((string) ($product['option_label'] ?? 'fabric')) . 's') ?>
                            </span>
                        </div>
                        <div style="margin-top:0.375rem;color:#374151;font-size:0.875rem;line-height:1.5">
                            <?php
                                $names = array_map(static function ($f) {
                                    $bits = array_filter([
                                        (string) ($f['supplier_name'] ?? ''),
                                        (string) ($f['name'] ?? ''),
                                        (string) ($f['colour'] ?? ''),
                                    ], static fn ($s) => $s !== '');
                                    return implode(' / ', $bits);
                                }, $shown);
                                echo e(implode(' · ', $names));
                                if ($more > 0) {
                                    echo ' <span style="color:#9ca3af">… +' . $more . ' more</span>';
                                }
                            ?>
                        </div>
                    </div>
                <?php endforeach; endif; ?>

                <!-- Quick-add a single fabric inline. Same handler as
                     options.php; we pass return_to so the bounce comes
                     back here rather than dropping the user on the
                     fabrics list. -->
                <form method="post" action="/admin/products/options.php?product_id=<?= (int) $id ?>"
                      class="quick-add" style="margin-top:0.875rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="create">
                    <input type="hidden" name="return_to" value="/admin/products/edit.php?id=<?= (int) $id ?>">
                    <div style="flex:0 0 4rem">
                        <label for="qa-fab-band">Band *</label>
                        <input id="qa-fab-band" name="band_code" type="text" required maxlength="20"
                               placeholder="A" style="width:100%">
                    </div>
                    <div style="flex:1 1 12rem">
                        <label for="qa-fab-name">Name *</label>
                        <input id="qa-fab-name" name="name" type="text" required maxlength="150"
                               placeholder="e.g. Cream Slats" style="width:100%">
                    </div>
                    <div style="flex:1 1 8rem">
                        <label for="qa-fab-colour">Colour</label>
                        <input id="qa-fab-colour" name="colour" type="text" maxlength="150"
                               style="width:100%">
                    </div>
                    <div style="flex:1 1 8rem">
                        <label for="qa-fab-supplier">Supplier</label>
                        <input id="qa-fab-supplier" name="supplier_name" type="text" maxlength="150"
                               style="width:100%">
                    </div>
                    <button type="submit">+ Add</button>
                </form>
            </div>
        </details>
        <!-- ─── Options ──────────────────────────────────────── -->
        <details class="cat-section">
            <summary>
                Options
                <span class="count">(<?= count($options) ?>)</span>
                <!-- Save indicator for the inline choices grids
                     embedded inside each option below. Same widget
                     as on extra.php; persistent green pill at rest. -->
                <span id="save-indicator" class="save-indicator">All changes saved</span>
                <span class="actions">
                    <a href="/admin/products/extras.php?product_id=<?= (int) $id ?>">Full manage &raquo;</a>
                </span>
            </summary>
            <div class="body">
                <?php if (!$options): ?>
                    <div class="empty-note">
                        No options yet. Options are the per-blind picks your salesperson makes when quoting &mdash;
                        e.g. <em>Bottom Weight</em>, <em>Control side</em>, <em>Bracket colour</em>.
                        Add one below to start.
                    </div>
                <?php else: foreach ($options as $opt):
                    $oid     = (int) $opt['id'];
                    $choices = $choicesByOption[$oid] ?? [];
                    // De-duplicate labels for the COLLAPSED summary
                    // line — same conceptual choice can have N rows
                    // (system-specific spawns).
                    $uniqueLabels = [];
                    foreach ($choices as $c) {
                        $lbl = (string) ($c['label'] ?? '');
                        if ($lbl === '' || (int) ($c['active'] ?? 1) !== 1) continue;
                        if (!in_array($lbl, $uniqueLabels, true)) $uniqueLabels[] = $lbl;
                    }
                ?>
                    <!--
                        One <details> per option, collapsed by default
                        so the page doesn't render as a giant scroll.
                        When expanded, the full inline-edit choices
                        grid renders below — same widget as extra.php,
                        powered by the shared choices_grid partial +
                        the shared JS at the bottom of this page.
                    -->
                    <details class="option-inline" style="border:1px solid #e5e7eb;border-radius:8px;margin-bottom:0.5rem;background:#fff">
                        <summary style="list-style:none;cursor:pointer;padding:0.5625rem 0.75rem;display:flex;align-items:center;gap:0.625rem;font-weight:500">
                            <span style="color:#6b7280;font-size:0.75rem">▸</span>
                            <span style="color:#111827"><?= e((string) $opt['name']) ?></span>
                            <?php if ((int) $opt['is_required'] === 1): ?>
                                <span class="pill pill-default" style="background:#dbeafe;color:#1e40af">Required</span>
                            <?php endif; ?>
                            <span style="color:#6b7280;font-size:0.8125rem;font-weight:400">
                                <?= count($choices) ?> choice<?= count($choices) === 1 ? '' : 's' ?>
                            </span>
                            <?php if ($uniqueLabels): ?>
                                <span style="color:#9ca3af;font-size:0.8125rem;font-weight:400;
                                             white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                                             flex:1 1 auto;min-width:0">
                                    <?= e(implode(' · ', array_slice($uniqueLabels, 0, 6))) ?>
                                </span>
                            <?php endif; ?>
                            <span style="margin-left:auto;display:flex;gap:0.5rem;font-size:0.8125rem;font-weight:500">
                                <a href="/admin/products/extra-edit.php?id=<?= $oid ?>"
                                   style="color:#1f3b5b;text-decoration:none">Settings</a>
                                <a href="/admin/products/extra.php?id=<?= $oid ?>"
                                   style="color:#1f3b5b;text-decoration:none">Sub-options &amp; full edit &raquo;</a>
                            </span>
                        </summary>
                        <div style="padding:0.5rem 0.75rem 0.875rem;border-top:1px solid #f3f4f6">
                            <?php
                                // Render the shared choices grid for
                                // this option. $renderSystemMultiSelect
                                // is the page-level closure.
                                $gridExtraId = $oid;
                                $gridChoices = $choices;
                                $productId   = (int) $id;
                                require __DIR__ . '/../../_partials/choices_grid.php';
                            ?>
                        </div>
                    </details>
                <?php endforeach; endif; ?>

                <style>
                    details.option-inline > summary::-webkit-details-marker { display: none; }
                    details.option-inline[open] > summary > span:first-child {
                        transform: rotate(90deg); display: inline-block;
                    }
                </style>

                <!-- Quick-add a new option. Posts to extras.php's
                     create handler with return_to back here. -->
                <form method="post" action="/admin/products/extras.php?product_id=<?= (int) $id ?>"
                      class="quick-add" style="margin-top:0.875rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="create">
                    <input type="hidden" name="return_to" value="/admin/products/edit.php?id=<?= (int) $id ?>">
                    <div style="flex:1 1 16rem">
                        <label for="qa-opt-name">Add a new option</label>
                        <input id="qa-opt-name" name="name" type="text" maxlength="150"
                               placeholder="e.g. Bottom Weight / Control side / Bracket"
                               style="width:100%">
                    </div>
                    <label class="checkbox-row" style="display:flex;align-items:center;gap:0.375rem;font-size:0.875rem">
                        <input type="checkbox" name="is_required" value="1" checked>
                        Required
                    </label>
                    <button type="submit">+ Add option</button>
                </form>
            </div>
        </details>
        <!-- ─── Price tables ────────────────────────────────────── -->
        <details class="cat-section">
            <summary>
                Price tables
                <span class="count">(<?= (int) $priceTablesTotal ?>)</span>
            </summary>
            <div class="body">
                <?php if (!$priceTablesBySystem): ?>
                    <div class="empty-note">
                        No price tables yet. Each (system, band) combination needs its own
                        width × drop grid before this product can be quoted. Add a system above
                        (if you don't have one yet), then open its row to add tables.
                    </div>
                <?php else: foreach ($priceTablesBySystem as $sysName => $tables):
                    // Per-system block — system name as a sub-heading,
                    // then each band's table as a row with cell count.
                    // First table in the group links to that system's
                    // price-tables list page for bulk + single import.
                    $firstSystemId = (int) ($tables[0]['system_id'] ?? 0);
                ?>
                    <div style="padding:0.625rem 0.25rem;border-bottom:1px solid #f3f4f6">
                        <div style="display:flex;align-items:baseline;gap:0.625rem;flex-wrap:wrap;margin-bottom:0.375rem">
                            <strong style="color:#1f3b5b">
                                <?php if ($firstSystemId > 0): ?>
                                    <a href="/admin/products/price-tables.php?system_id=<?= $firstSystemId ?>"
                                       style="color:#1f3b5b;text-decoration:none">
                                        <?= e((string) $sysName) ?>
                                    </a>
                                <?php else: ?>
                                    <?= e((string) $sysName) ?>
                                <?php endif; ?>
                            </strong>
                            <span style="color:#6b7280;font-size:0.875rem">
                                <?= count($tables) ?>
                                table<?= count($tables) === 1 ? '' : 's' ?>
                            </span>
                            <?php if ($firstSystemId > 0): ?>
                                <a href="/admin/products/price-tables-bulk-import.php?system_id=<?= $firstSystemId ?>"
                                   style="color:#1f3b5b;font-size:0.8125rem;margin-left:auto">
                                    Bulk import &raquo;
                                </a>
                                <a href="/admin/products/price-tables.php?system_id=<?= $firstSystemId ?>"
                                   style="color:#1f3b5b;font-size:0.8125rem">
                                    Manage &raquo;
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php foreach ($tables as $t): ?>
                            <div class="item-row" style="grid-template-columns:auto 1fr auto auto;
                                                          padding:0.375rem 0.5rem">
                                <div>
                                    <span class="pill pill-tables">Band <?= e((string) $t['band_code']) ?></span>
                                </div>
                                <div class="name" style="font-weight:400;color:#374151">
                                    <?= e((string) ($t['name'] ?? '')) ?>
                                </div>
                                <div>
                                    <span class="pill <?= (int) $t['cell_count'] === 0 ? 'pill-empty' : 'pill-default' ?>">
                                        <?= (int) $t['cell_count'] ?> cells
                                    </span>
                                </div>
                                <div class="row-actions">
                                    <a href="/admin/products/price-table.php?id=<?= (int) $t['id'] ?>">Open</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; endif; ?>

                <?php if ($systems): ?>
                <p style="color:#6b7280;font-size:0.8125rem;margin:0.625rem 0 0;line-height:1.45">
                    To add a new band for an existing system, open the system above and use its
                    <em>Add price table</em> form. To bulk-import all bands at once from a single
                    spreadsheet, use the <em>Bulk import</em> link next to each system.
                </p>
                <?php endif; ?>
            </div>
        </details>

        <!--
            Recent changes feed. Reads from catalogue_audit (which the
            mutation handlers append to as they go). Collapsed by
            default — most editors don't need it in their face, but
            it's invaluable for the "who renamed this?" / "what did
            I just save?" moment.

            Defensive against the audit table not existing yet — the
            helper returns an empty array, and we just don't render
            anything if so.
        -->
        <?php
            require_once __DIR__ . '/../../_partials/catalogue_audit.php';
            $auditRows = catalogue_audit_recent_for_product((int) $id, (int) $clientId, 25);
        ?>
        <details class="cat-section">
            <summary>
                <span class="cat-section-title">
                    📜 Recent changes
                </span>
                <span class="cat-section-meta">
                    <?= $auditRows
                        ? count($auditRows) . ' event' . (count($auditRows) === 1 ? '' : 's') . ' shown'
                        : 'no changes recorded yet' ?>
                </span>
            </summary>
            <div class="cat-section-body">
                <?= catalogue_audit_render_feed($auditRows) ?>
                <p style="margin:0.625rem 0 0;color:#9ca3af;font-size:0.75rem;line-height:1.45">
                    Shows the 25 most recent changes affecting this product.
                    Click any row to see the field-by-field diff. The log is
                    append-only — events can't be edited or deleted.
                </p>
            </div>
        </details>
    </main>
</div>

<?php require __DIR__ . '/../../_partials/confirm_modal.php'; ?>
<?php require __DIR__ . '/../../_partials/sortable_init.php'; ?>
<?php
    // Inline-edit JS for every choices grid rendered above (one per
    // Option). $systems and $gridProductId are picked up by the
    // partial — both already in scope.
    $gridProductId = (int) $id;
    require __DIR__ . '/../../_partials/choices_grid_js.php';
?>

<!--
    ============================================================
    PHASE 2B-2 — Preview-as-salesperson drawer
    ============================================================

    A right-side slide-in panel that mounts a mini quote-builder
    for the current product. Mirrors what the salesperson sees:
    System / Fabric cascade, options with conditional gating,
    width/drop + calculate → live price.

    Data sources are the same APIs as /quote-builder/edit.php:
      /quote-builder/api/product-data.php
      /quote-builder/api/fabrics-search.php
      /quote-builder/api/preview.php

    Read-only — picking things doesn't write back to the product
    catalogue. Refresh button at the top reloads product-data so
    inline edits made on the main page show up.
-->
<style>
    .preview-backdrop {
        position: fixed; inset: 0; background: rgba(17, 24, 39, 0.45);
        z-index: 9000; display: none;
    }
    .preview-backdrop.is-open { display: block; }
    .preview-drawer {
        position: fixed; top: 0; right: 0; bottom: 0;
        width: min(440px, 100vw);
        background: #fff; box-shadow: -8px 0 30px rgba(0,0,0,0.15);
        z-index: 9001; display: flex; flex-direction: column;
        transform: translateX(100%); transition: transform 220ms ease-out;
    }
    .preview-drawer.is-open { transform: translateX(0); }
    .preview-drawer-head {
        display: flex; align-items: center; gap: 0.625rem;
        padding: 0.875rem 1rem; border-bottom: 1px solid #e5e7eb;
        background: #f8fafc;
    }
    .preview-drawer-head h2 {
        margin: 0; font-size: 1rem; color: #1f3b5b;
    }
    .preview-drawer-head .badge {
        font-size: 0.6875rem; font-weight: 600; color: #6b7280;
        background: #e5e7eb; padding: 0.0625rem 0.5rem;
        border-radius: 999px; text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .preview-drawer-head .head-btns {
        margin-left: auto; display: flex; gap: 0.375rem;
    }
    .preview-drawer-head .head-btns button {
        background: transparent; border: 0; cursor: pointer;
        padding: 0.3125rem 0.625rem; border-radius: 6px;
        font-size: 0.8125rem; color: #1f3b5b;
    }
    .preview-drawer-head .head-btns button:hover { background: #e5e7eb; }
    .preview-drawer-body {
        flex: 1 1 auto; overflow-y: auto; padding: 1rem;
    }
    .preview-drawer-body .pv-row {
        display: flex; flex-direction: column; gap: 0.25rem;
        margin-bottom: 0.875rem;
    }
    .preview-drawer-body .pv-row label {
        font-size: 0.75rem; font-weight: 600; color: #6b7280;
        text-transform: uppercase; letter-spacing: 0.05em;
    }
    .preview-drawer-body .pv-row input,
    .preview-drawer-body .pv-row select {
        padding: 0.4375rem 0.5625rem; border: 1px solid #d1d5db;
        border-radius: 6px; font: inherit; background: #fff;
    }
    .preview-drawer-body .pv-row .req-mark { color: #b91c1c; }
    .preview-drawer-body .pv-extras {
        background: #f9fafb; border: 1px solid #e5e7eb;
        border-radius: 8px; padding: 0.625rem 0.75rem;
        margin-bottom: 0.875rem;
    }
    .preview-drawer-body .pv-extras > .pv-row:last-child { margin-bottom: 0; }
    .preview-drawer-body .pv-extra-child {
        margin-left: 0.875rem; padding-left: 0.625rem;
        border-left: 2px solid #cbd5e1;
        margin-top: 0.5rem;
    }
    .preview-drawer-body .pv-dim-row {
        display: grid; gap: 0.5rem;
        grid-template-columns: 1fr 1fr 5rem;
    }
    .preview-drawer-foot {
        padding: 0.875rem 1rem; border-top: 1px solid #e5e7eb;
        background: #f8fafc;
    }
    .preview-drawer-foot button {
        width: 100%; padding: 0.5625rem 0.875rem;
        background: #1f3b5b; color: #fff; border: 0;
        border-radius: 8px; font-weight: 700; cursor: pointer;
        font-size: 0.9375rem;
    }
    .preview-drawer-foot button:hover { background: #15273d; }
    .preview-result {
        margin-top: 0.625rem; padding: 0.75rem 0.875rem;
        border-radius: 8px; font-size: 0.9375rem; line-height: 1.5;
    }
    .preview-result.is-ok { background: #d1fae5; color: #065f46; }
    .preview-result.is-err { background: #fee2e2; color: #991b1b; }
    .preview-result strong { font-size: 1.125rem; }
    .preview-empty {
        color: #9ca3af; font-style: italic; padding: 1rem 0.5rem;
        text-align: center;
    }
</style>

<div class="preview-backdrop" id="preview-backdrop" aria-hidden="true"></div>
<aside class="preview-drawer" id="preview-drawer"
       role="dialog" aria-modal="true" aria-labelledby="preview-title">
    <div class="preview-drawer-head">
        <h2 id="preview-title">Preview</h2>
        <span class="badge">as salesperson</span>
        <div class="head-btns">
            <button type="button" id="preview-refresh" title="Reload catalogue data — pick up any edits you've just made">
                ↻ Refresh
            </button>
            <button type="button" id="preview-close" title="Close (Esc)">✕</button>
        </div>
    </div>
    <div class="preview-drawer-body" id="preview-body">
        <div class="preview-empty">Loading…</div>
    </div>
    <div class="preview-drawer-foot">
        <button type="button" id="preview-calc">Calculate price</button>
        <div id="preview-result"></div>
    </div>
</aside>

<script>
(function () {
    'use strict';

    var openBtn  = document.getElementById('preview-open-btn');
    var drawer   = document.getElementById('preview-drawer');
    var backdrop = document.getElementById('preview-backdrop');
    var closeBtn = document.getElementById('preview-close');
    var refBtn   = document.getElementById('preview-refresh');
    var body     = document.getElementById('preview-body');
    var calcBtn  = document.getElementById('preview-calc');
    var resultEl = document.getElementById('preview-result');
    if (!openBtn || !drawer) return;

    var PRODUCT_ID = <?= (int) $id ?>;
    var productData = null;       // cached /api/product-data.php response

    // --- Open / close --------------------------------------------------
    function open() {
        drawer.classList.add('is-open');
        backdrop.classList.add('is-open');
        if (productData === null) loadData();
    }
    function close() {
        drawer.classList.remove('is-open');
        backdrop.classList.remove('is-open');
    }
    openBtn.addEventListener('click', open);
    closeBtn.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) close();
    });

    // --- Data load -----------------------------------------------------
    async function loadData() {
        body.innerHTML = '<div class="preview-empty">Loading…</div>';
        try {
            var r    = await fetch('/quote-builder/api/product-data.php?product_id=' + PRODUCT_ID,
                                   { credentials: 'same-origin' });
            var data = await r.json();
            if (data.error) throw new Error(data.error);
            productData = data;
            renderForm();
        } catch (err) {
            body.innerHTML = '<div class="preview-empty" style="color:#b91c1c">'
                           + 'Could not load catalogue: ' + (err.message || err) + '</div>';
        }
    }
    refBtn.addEventListener('click', function () {
        productData = null;
        loadData();
        resultEl.innerHTML = '';
    });

    // --- Form render ---------------------------------------------------
    //
    // Mirrors the cascade in /quote-builder/edit.php but compressed:
    //   System select → Fabric search (typeahead) → Options.
    // Options apply the same parent-choice gating as the real builder.
    function renderForm() {
        var html = '';

        // System
        var systems = productData.systems || [];
        if (systems.length) {
            html += '<div class="pv-row">'
                  + '<label for="pv-system">System</label>'
                  + '<select id="pv-system">';
            systems.forEach(function (s) {
                var sel = s.is_default ? ' selected' : '';
                html += '<option value="' + s.id + '"' + sel + '>' + esc(s.name) + '</option>';
            });
            html += '</select></div>';
        }

        // Fabric — typeahead like the main quote-builder. For brevity
        // here we use a simple <select> populated from fabrics-search
        // (no query = all fabrics). Same end result for verification.
        html += '<div class="pv-row">'
              + '<label for="pv-fabric">' + esc(productData.product.option_label || 'Fabric')
              + ' <span class="req-mark">*</span></label>'
              + '<select id="pv-fabric"><option value="">Loading…</option></select>'
              + '</div>';

        // Options
        html += '<div id="pv-extras-wrap"></div>';

        // Dimensions + quantity
        html += '<div class="pv-row">'
              + '<label>Dimensions (mm) &amp; quantity</label>'
              + '<div class="pv-dim-row">'
              +   '<input id="pv-width" type="number" placeholder="Width" min="1">'
              +   '<input id="pv-drop" type="number" placeholder="Drop" min="1">'
              +   '<input id="pv-qty" type="number" value="1" min="1">'
              + '</div>'
              + '</div>';

        body.innerHTML = html;

        // Load fabrics list once.
        loadFabrics();

        // Re-render extras whenever System or any extra select changes.
        var sysSel = document.getElementById('pv-system');
        if (sysSel) sysSel.addEventListener('change', renderExtras);

        renderExtras();
    }

    async function loadFabrics() {
        var fabSel = document.getElementById('pv-fabric');
        if (!fabSel) return;
        try {
            var r = await fetch('/quote-builder/api/fabrics-search.php?product_id=' + PRODUCT_ID + '&q=&limit=500',
                                { credentials: 'same-origin' });
            var data = await r.json();
            var rows = data.fabrics || [];
            if (!rows.length) {
                fabSel.innerHTML = '<option value="">(no fabrics)</option>';
                return;
            }
            fabSel.innerHTML = rows.map(function (f) {
                var label = 'Band ' + (f.band_code || '?')
                          + ' — ' + (f.supplier_name || '') + ' / '
                          + (f.name || '') + (f.colour ? ' / ' + f.colour : '');
                return '<option value="' + f.id + '">' + esc(label) + '</option>';
            }).join('');
        } catch (e) {
            fabSel.innerHTML = '<option value="">(failed to load)</option>';
        }
    }

    // Mirror the extras-rendering logic from the real quote-builder.
    function renderExtras() {
        var wrap = document.getElementById('pv-extras-wrap');
        if (!wrap) return;
        var extras = productData.extras || [];
        if (!extras.length) {
            wrap.innerHTML = '<div class="preview-empty">No options on this product</div>';
            return;
        }
        var sysSel = document.getElementById('pv-system');
        var systemId = sysSel ? parseInt(sysSel.value, 10) || 0 : 0;

        // Capture current selections to preserve across re-renders.
        var preset = {};
        wrap.querySelectorAll('[data-pv-extra]').forEach(function (div) {
            var eid = parseInt(div.dataset.pvExtra, 10);
            var sel = div.querySelector('select');
            if (sel) preset[eid] = sel.value;
            var multi = div.querySelectorAll('input[data-pv-multi]');
            if (multi.length) {
                preset[eid + '__m'] = Array.from(multi)
                    .filter(function (cb) { return cb.checked; })
                    .map(function (cb) { return parseInt(cb.dataset.pvMulti, 10); });
            }
        });

        function effectiveChoiceIds(extra) {
            if (extra.allow_multi) {
                var m = preset[extra.id + '__m'];
                if (m) return m.slice();
                return (extra.choices || []).filter(function (c) {
                    return c.is_default;
                }).map(function (c) { return c.id; });
            }
            var v = preset[extra.id];
            if (v !== undefined) return v ? [parseInt(v, 10)] : [];
            var def = (extra.choices || []).find(function (c) {
                if (c.system_id !== null && c.system_id !== systemId) return false;
                return c.is_default;
            });
            return def ? [def.id] : [];
        }

        function isVisible(extra) {
            var parents = extra.parent_choice_ids || [];
            if (parents.length) {
                var match = false;
                extras.forEach(function (other) {
                    if (other.id === extra.id) return;
                    effectiveChoiceIds(other).forEach(function (cid) {
                        if (parents.indexOf(cid) !== -1) match = true;
                    });
                });
                if (!match) return false;
            }
            var visible = (extra.choices || []).filter(function (c) {
                return c.system_id === null || c.system_id === systemId;
            });
            return visible.length > 0;
        }

        function renderOne(extra) {
            var visible = (extra.choices || []).filter(function (c) {
                return c.system_id === null || c.system_id === systemId;
            });
            var out = '<div class="pv-row" data-pv-extra="' + extra.id + '">';
            out += '<label>' + esc(extra.name)
                 + (extra.is_required ? ' <span class="req-mark">*</span>' : '')
                 + '</label>';

            if (extra.allow_multi) {
                var presetMulti = preset[extra.id + '__m'];
                var preIds = Array.isArray(presetMulti)
                    ? presetMulti.slice()
                    : visible.filter(function (c) { return c.is_default; }).map(function (c) { return c.id; });
                out += '<div style="display:flex;flex-direction:column;gap:0.25rem;background:#fff;padding:0.4375rem 0.5rem;border:1px solid #d1d5db;border-radius:6px">';
                visible.forEach(function (c) {
                    var ticked = preIds.indexOf(c.id) !== -1;
                    out += '<label style="display:inline-flex;align-items:center;gap:0.4375rem;cursor:pointer;font-weight:400">'
                         + '<input type="checkbox" data-pv-multi="' + c.id + '"' + (ticked ? ' checked' : '') + '>'
                         + ' ' + esc(c.label) + '</label>';
                });
                out += '</div>';
            } else {
                var presetVal = preset[extra.id];
                var hasDef = visible.some(function (c) { return c.is_default; });
                out += '<select>';
                if (!hasDef) {
                    out += '<option value=""' + (presetVal === '' ? ' selected' : '') + '>— Select —</option>';
                }
                visible.forEach(function (c) {
                    var isSel;
                    if (presetVal !== undefined && presetVal !== '') isSel = String(c.id) === presetVal;
                    else if (presetVal === '')                       isSel = false;
                    else                                             isSel = c.is_default;
                    out += '<option value="' + c.id + '"' + (isSel ? ' selected' : '') + '>' + esc(c.label) + '</option>';
                });
                out += '</select>';
            }

            if (extra.length_input_label) {
                out += '<label style="font-size:0.6875rem;font-weight:600;color:#6b7280;'
                     + 'text-transform:uppercase;letter-spacing:0.05em;margin-top:0.375rem">'
                     + esc(extra.length_input_label) + '</label>'
                     + '<input type="number" data-pv-uv="' + extra.id + '" min="0" step="1"'
                     + ' style="padding:0.375rem 0.5rem;border:1px solid #d1d5db;border-radius:6px">';
            }
            out += '</div>';
            return out;
        }

        // Group by top-level + render children inline.
        var childMap = {};
        extras.forEach(function (e) {
            (e.parent_choice_ids || []).forEach(function (pid) {
                extras.forEach(function (other) {
                    if ((other.choices || []).some(function (c) { return c.id === pid; })) {
                        if (!childMap[other.id]) childMap[other.id] = [];
                        if (childMap[other.id].indexOf(e.id) === -1) childMap[other.id].push(e.id);
                    }
                });
            });
        });

        function renderTree(extra, depth) {
            if (depth > 4) return '';
            var inner = renderOne(extra);
            (childMap[extra.id] || []).forEach(function (cid) {
                var child = extras.find(function (e) { return e.id === cid; });
                if (!child || !isVisible(child)) return;
                inner += '<div class="pv-extra-child">' + renderTree(child, depth + 1) + '</div>';
            });
            return inner;
        }

        var html = '';
        var anyVisible = false;
        extras.forEach(function (extra) {
            if ((extra.parent_choice_ids || []).length) return;
            if (!isVisible(extra)) return;
            anyVisible = true;
            html += '<div class="pv-extras">' + renderTree(extra, 0) + '</div>';
        });
        wrap.innerHTML = anyVisible ? html : '<div class="preview-empty">No options visible for the current selection</div>';

        // Re-render when any extra's selection changes (so conditional
        // children appear/disappear in real time).
        wrap.querySelectorAll('select, input[type="checkbox"][data-pv-multi]')
            .forEach(function (el) {
                el.addEventListener('change', renderExtras);
            });
    }

    // --- Calculate -----------------------------------------------------
    calcBtn.addEventListener('click', async function () {
        if (!productData) return;
        resultEl.innerHTML = '';
        resultEl.className = '';

        var sysSel = document.getElementById('pv-system');
        var fabSel = document.getElementById('pv-fabric');
        var widthIn = document.getElementById('pv-width');
        var dropIn  = document.getElementById('pv-drop');
        var qtyIn   = document.getElementById('pv-qty');

        if (!fabSel || !fabSel.value) {
            resultEl.className = 'preview-result is-err';
            resultEl.textContent = 'Pick a fabric first.';
            return;
        }
        if (!widthIn.value || !dropIn.value) {
            resultEl.className = 'preview-result is-err';
            resultEl.textContent = 'Enter width and drop.';
            return;
        }

        var params = new URLSearchParams({
            product_id: String(PRODUCT_ID),
            system_id:  sysSel ? sysSel.value : '0',
            option_id:  fabSel.value,
            width:      widthIn.value,
            drop:       dropIn.value,
            quantity:   qtyIn.value || '1',
            round_up:   '1'
        });

        // Extras → same shape the real builder POSTs.
        var i = 0;
        document.querySelectorAll('[data-pv-extra]').forEach(function (div) {
            var eid = parseInt(div.dataset.pvExtra, 10);
            var multiBoxes = div.querySelectorAll('input[data-pv-multi]');
            var uvIn = div.querySelector('input[data-pv-uv]');
            var uvVal = uvIn && uvIn.value !== '' ? parseFloat(uvIn.value) : null;
            if (multiBoxes.length) {
                multiBoxes.forEach(function (cb) {
                    if (!cb.checked) return;
                    var cid = parseInt(cb.dataset.pvMulti, 10);
                    if (cid > 0) {
                        params.append('extras[' + i + '][extra_id]',  eid);
                        params.append('extras[' + i + '][choice_id]', cid);
                        if (uvVal && uvVal > 0) params.append('extras[' + i + '][user_value]', uvVal);
                        i++;
                    }
                });
            } else {
                var sel = div.querySelector('select');
                if (!sel) return;
                var cid = parseInt(sel.value, 10);
                if (cid > 0) {
                    params.append('extras[' + i + '][extra_id]',  eid);
                    params.append('extras[' + i + '][choice_id]', cid);
                    if (uvVal && uvVal > 0) params.append('extras[' + i + '][user_value]', uvVal);
                    i++;
                }
            }
        });

        try {
            var r = await fetch('/quote-builder/api/preview.php?' + params,
                                { credentials: 'same-origin' });
            var data = await r.json();
            if (data.error) {
                resultEl.className = 'preview-result is-err';
                resultEl.textContent = data.error;
                return;
            }
            // Build the result block — per-blind price + line total.
            var perBlind = data.sell_price;
            var line     = data.line_total;
            var html = '<strong>£' + Number(perBlind).toFixed(2) + '</strong> per blind';
            if (data.quantity > 1) {
                html += ' &middot; £' + Number(line).toFixed(2) + ' for ' + data.quantity + ' blinds';
            }
            html += '<div style="margin-top:0.25rem;color:#374151;font-size:0.8125rem">'
                  + 'Base £' + Number(data.base_price).toFixed(2)
                  + (data.extras_total ? ' + extras £' + Number(data.extras_total).toFixed(2) : '')
                  + (Number(data.markup_percent) ? ' · markup ' + data.markup_percent + '%' : '')
                  + (Number(data.discount_percent) ? ' · discount ' + data.discount_percent + '%' : '')
                  + '</div>';
            resultEl.className = 'preview-result is-ok';
            resultEl.innerHTML = html;
        } catch (err) {
            resultEl.className = 'preview-result is-err';
            resultEl.textContent = 'Could not calculate: ' + (err.message || err);
        }
    });

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
        });
    }
})();
</script>

</body>
</html>
