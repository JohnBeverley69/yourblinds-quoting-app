<?php
declare(strict_types=1);

/**
 * Seeder: Arena Vertical Blinds (89mm + 127mm) — pilot for the Arena range.
 *
 * Builds a complete, quotable "Arena Vertical" product in the CURRENT
 * tenant (the logged-in super-admin's client_id) from two committed data
 * files under seed_data/:
 *
 *   arena_vertical_prices.csv   system,band,width_mm,drop_mm,price
 *                               (Bands A–E + Elements, both louvre widths;
 *                                4,416 cells. Arena TRADE/ex-VAT prices —
 *                                the engine applies the tenant's markup.)
 *   arena_vertical_fabrics.csv  system,name,colour,band
 *                               (998 fabrics, system-scoped by louvre width;
 *                                band '?' = no Arena band → imported INACTIVE.)
 *
 * Source of truth: Arena's live members configurator (current fabric/colour
 * availability) married to the May-2026 trade price list (band grids). See
 * memory note reference_arena_configurator_extract.
 *
 * Idempotent BY NAME: re-running matches the product + systems by name and
 * reuses their ids, then fully rebuilds the price tables and fabric options.
 * Product-level extras/choices (a later "guided journey" phase) are left
 * untouched, so this Phase-1 seeder can be re-run without wiping Phase-2
 * work. "Delete and do fresh" = just run it again.
 *
 * Pricing mode: width × drop (products.width_only / price_per_slat /
 * price_per_sqm all left at their 0 default).
 *
 * Run via web (super-admin): /seed_arena_vertical.php
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

ini_set('display_errors', '1');
error_reporting(E_ALL);
@set_time_limit(300);
@ini_set('memory_limit', '512M');

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Seed FAILED: " . $e->getMessage() . "\n\n";
    echo "Steps completed before failure (rolled back if mid-transaction):\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
$PRODUCT_NAME = 'Arena Vertical';
$OPTION_LABEL = 'Fabric';
$SUPPLIER     = 'Arena';
$SYSTEMS      = ['89mm', '127mm'];   // first = default
$DATA_DIR     = __DIR__ . '/seed_data';
$PRICES_CSV   = $DATA_DIR . '/arena_vertical_prices.csv';
$FABRICS_CSV  = $DATA_DIR . '/arena_vertical_fabrics.csv';

$user     = current_user();
$clientId = (int) ($user['client_id'] ?? 0);
if ($clientId <= 0) {
    throw new RuntimeException('Could not determine your client_id — are you logged in?');
}

echo "Seeding \"{$PRODUCT_NAME}\" into client_id {$clientId}\n";
echo str_repeat('=', 60) . "\n\n";

// ---------------------------------------------------------------------------
// Read the committed CSVs
// ---------------------------------------------------------------------------
$readCsv = static function (string $path): array {
    if (!is_file($path)) {
        throw new RuntimeException("Missing data file: {$path}");
    }
    $fh = fopen($path, 'r');
    if (!$fh) throw new RuntimeException("Cannot open: {$path}");
    $header = fgetcsv($fh);
    $rows = [];
    while (($r = fgetcsv($fh)) !== false) {
        if ($r === [null] || $r === false) continue;       // blank line
        $rows[] = array_combine($header, $r);
    }
    fclose($fh);
    return $rows;
};

$priceRows  = $readCsv($PRICES_CSV);
$fabricRows = $readCsv($FABRICS_CSV);
$ops[] = sprintf('Read %d price cells, %d fabrics from seed_data/.', count($priceRows), count($fabricRows));
echo $ops[count($ops) - 1] . "\n";

// ---------------------------------------------------------------------------
// Everything in one transaction — all-or-nothing.
// ---------------------------------------------------------------------------
$pdo->beginTransaction();
try {
    // --- Product: upsert by (client_id, name) -----------------------------
    $find = $pdo->prepare('SELECT id FROM products WHERE client_id = ? AND name = ?');
    $find->execute([$clientId, $PRODUCT_NAME]);
    $productId = (int) ($find->fetchColumn() ?: 0);

    $scf = 1;   // show colour field — Arena fabrics carry a colour
    if ($productId === 0) {
        $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM products WHERE client_id = ?');
        $sortStmt->execute([$clientId]);
        $nextSort = (int) $sortStmt->fetchColumn();
        try {
            $pdo->prepare(
                'INSERT INTO products (client_id, name, option_label, show_colour_field, sort_order, active)
                 VALUES (?, ?, ?, ?, ?, 1)'
            )->execute([$clientId, $PRODUCT_NAME, $OPTION_LABEL, $scf, $nextSort]);
        } catch (Throwable $e) {
            $pdo->prepare(
                'INSERT INTO products (client_id, name, option_label, sort_order, active)
                 VALUES (?, ?, ?, ?, 1)'
            )->execute([$clientId, $PRODUCT_NAME, $OPTION_LABEL, $nextSort]);
        }
        $productId = (int) $pdo->lastInsertId();
        $ops[] = "Created product #{$productId} \"{$PRODUCT_NAME}\".";
    } else {
        $ops[] = "Reusing existing product #{$productId} \"{$PRODUCT_NAME}\".";
    }
    echo $ops[count($ops) - 1] . "\n";

    // --- Systems: upsert by (product_id, name), reuse ids -----------------
    $sysId = [];
    foreach ($SYSTEMS as $i => $sysName) {
        $isDefault = $i === 0 ? 1 : 0;
        $fs = $pdo->prepare('SELECT id FROM product_systems WHERE client_id = ? AND product_id = ? AND name = ?');
        $fs->execute([$clientId, $productId, $sysName]);
        $id = (int) ($fs->fetchColumn() ?: 0);
        if ($id === 0) {
            $pdo->prepare(
                'INSERT INTO product_systems (client_id, product_id, name, sort_order, active, is_default)
                 VALUES (?, ?, ?, ?, 1, ?)'
            )->execute([$clientId, $productId, $sysName, $i, $isDefault]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE product_systems SET active = 1, is_default = ?, sort_order = ? WHERE id = ?')
                ->execute([$isDefault, $i, $id]);
        }
        $sysId[$sysName] = $id;
    }
    $ops[] = 'Systems: ' . implode(', ', array_map(fn ($n) => "{$n}=#{$sysId[$n]}", $SYSTEMS)) . '.';
    echo $ops[count($ops) - 1] . "\n";

    // --- Wipe THIS product's price tables + fabric options (Phase-1 data) --
    // Extras/choices are intentionally left alone (Phase-2 owns them).
    $pdo->prepare(
        'DELETE r FROM price_table_rows r
           JOIN price_tables t ON t.id = r.price_table_id
          WHERE t.client_id = ? AND t.product_id = ?'
    )->execute([$clientId, $productId]);
    $pdo->prepare('DELETE FROM price_tables   WHERE client_id = ? AND product_id = ?')->execute([$clientId, $productId]);
    $pdo->prepare('DELETE FROM product_options WHERE client_id = ? AND product_id = ?')->execute([$clientId, $productId]);
    $ops[] = 'Cleared existing price tables + fabric options for a fresh rebuild.';
    echo $ops[count($ops) - 1] . "\n";

    // --- Price tables: one per (system, band) -----------------------------
    // Group cells by system|band.
    $grouped = [];
    foreach ($priceRows as $pr) {
        $grouped[$pr['system'] . '|' . $pr['band']][] = $pr;
    }
    $tblIns = $pdo->prepare(
        'INSERT INTO price_tables (client_id, product_id, system_id, band_code, name, active)
         VALUES (?, ?, ?, ?, ?, 1)'
    );
    $rowIns = $pdo->prepare(
        'INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price) VALUES (?, ?, ?, ?)'
    );
    $tableCount = 0; $cellCount = 0;
    foreach ($grouped as $key => $cells) {
        [$sysName, $band] = explode('|', $key, 2);
        if (!isset($sysId[$sysName])) continue;   // unknown system — skip defensively
        $tblIns->execute([$clientId, $productId, $sysId[$sysName], $band, "Arena Band {$band}"]);
        $tableId = (int) $pdo->lastInsertId();
        foreach ($cells as $c) {
            $rowIns->execute([$tableId, (int) $c['width_mm'], (int) $c['drop_mm'], (float) $c['price']]);
            $cellCount++;
        }
        $tableCount++;
    }
    $ops[] = "Built {$tableCount} price tables ({$cellCount} cells).";
    echo $ops[count($ops) - 1] . "\n";

    // --- Fabric options ----------------------------------------------------
    // band_code / code / colour are NOT NULL on product_options, so:
    //   Banded fabrics (A–E, Elements) → active, scoped to band + system.
    //   Unbanded '?' fabrics → band_code 'TBC' + active = 0 (visible to the
    //     admin, flagged, never quotable — avoids a £0 / "no price table"
    //     quote-time error). When Arena publishes a band, set it + tick active.
    $optIns = $pdo->prepare(
        'INSERT INTO product_options
            (client_id, product_id, system_id, band_code, supplier_name, name, colour, code, sort_order, active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $added = 0; $inactive = 0; $sortBy = [];
    foreach ($fabricRows as $f) {
        $sysName = (string) $f['system'];
        if (!isset($sysId[$sysName])) continue;
        $band   = trim((string) $f['band']);
        $banded = $band !== '' && $band !== '?';
        $bandCode = $banded ? $band : 'TBC';
        $active   = $banded ? 1 : 0;
        if (!$banded) $inactive++; else $added++;

        $sortBy[$sysName] = ($sortBy[$sysName] ?? -1) + 1;
        $optIns->execute([
            $clientId, $productId, $sysId[$sysName], $bandCode, $SUPPLIER,
            (string) $f['name'],
            (string) $f['colour'],   // NOT NULL — '' if blank (none are here)
            '',                      // code — Arena fabrics carry no separate code
            $sortBy[$sysName], $active,
        ]);
    }
    $ops[] = "Imported fabrics: {$added} active (banded), {$inactive} inactive (no Arena band).";
    echo $ops[count($ops) - 1] . "\n";

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

// ---------------------------------------------------------------------------
// Done
// ---------------------------------------------------------------------------
echo "\n" . str_repeat('=', 60) . "\n";
echo "Seed complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nNext: open the quote builder, pick \"{$PRODUCT_NAME}\", and spot-check\n";
echo "a few width/drop/band prices against the Arena trade list.\n";
