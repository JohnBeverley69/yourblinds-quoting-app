<?php
declare(strict_types=1);

/**
 * Audit: product behaviour flags.
 *
 * Read-only. Lists every product with the four behaviour flags that
 * control how it prices and what the quote wizard shows:
 *
 *   requires_option  1 = needs a fabric (normal); 0 = no-fabric line
 *   width_only       1 = priced on width alone (headrail/track), no drop
 *   price_per_slat   1 = price table is width→rate, price = rate × drop
 *   show_colour_field 1 = show the dedicated Colour sub-field
 *
 * Why this exists: duplicate.php and catalogue_push.php historically did
 * NOT carry these flags onto a copied product, so a product built by
 * "duplicate / push then tweak" silently started with all flags at their
 * column default (width_only=0, requires_option=1, …). The server move
 * could also have reset them. This script shows the blast radius so the
 * affected products can be re-ticked.
 *
 * It also flags products that LOOK like they were meant to be width-only
 * (name contains headrail/track/valance/pelmet/rail) but aren't — a
 * likely-misconfigured shortlist to eyeball first.
 *
 * Read-only — touches nothing. Run via web: /audit_product_flags.php
 * (super-admin login) or CLI: php audit_product_flags.php
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

// Which optional flag columns actually exist on this schema?
$colExists = static function (string $col) use ($pdo): bool {
    return (bool) $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'products'
            AND COLUMN_NAME  = " . $pdo->quote($col)
    )->fetchColumn();
};

$flagCols = [];
foreach (['requires_option', 'width_only', 'price_per_slat', 'show_colour_field'] as $c) {
    if ($colExists($c)) $flagCols[] = $c;
}

if (!$flagCols) {
    echo "None of the behaviour-flag columns exist on this database.\n";
    echo "Run the migrate_*.php scripts first.\n";
    exit;
}

$select = 'id, client_id, name, active, ' . implode(', ', $flagCols);
$rows = $pdo->query(
    "SELECT $select FROM products ORDER BY client_id, name"
)->fetchAll(PDO::FETCH_ASSOC);

echo "Product behaviour-flag audit\n";
echo "Columns present: " . implode(', ', $flagCols) . "\n";
echo str_repeat('=', 72) . "\n\n";

// Header row — fixed-width so it lines up in a mono terminal / plain page.
$hdr = sprintf('%-5s %-7s %-34s', 'id', 'client', 'name');
foreach ($flagCols as $c) {
    // short labels so the table stays narrow
    $short = [
        'requires_option'   => 'req_opt',
        'width_only'        => 'width_o',
        'price_per_slat'    => 'perslat',
        'show_colour_field' => 'colour',
    ][$c] ?? $c;
    $hdr .= sprintf(' %-7s', $short);
}
echo $hdr . "\n";
echo str_repeat('-', strlen($hdr)) . "\n";

// Words in a product name that suggest it SHOULD be width-only.
$widthOnlyHints = ['headrail', 'head rail', 'track', 'valance', 'valence',
                   'pelmet', 'rail only', 'rail ', 'lath', 'fascia'];

$suspects = [];

foreach ($rows as $r) {
    $line = sprintf(
        '%-5d %-7d %-34s',
        (int) $r['id'],
        (int) $r['client_id'],
        mb_strimwidth((string) $r['name'], 0, 34)
    );
    foreach ($flagCols as $c) {
        $line .= sprintf(' %-7s', (int) $r[$c] === 1 ? 'YES' : '.');
    }
    if ((int) $r['active'] !== 1) $line .= '  (inactive)';
    echo $line . "\n";

    // Heuristic: name hints width-only but width_only is off → suspect.
    if (in_array('width_only', $flagCols, true) && (int) $r['width_only'] !== 1) {
        $name = mb_strtolower((string) $r['name']);
        foreach ($widthOnlyHints as $h) {
            if (str_contains($name, $h)) {
                $suspects[] = $r;
                break;
            }
        }
    }
}

echo "\n" . str_repeat('=', 72) . "\n";
echo "Total products: " . count($rows) . "\n";

if ($suspects) {
    echo "\nLIKELY MISCONFIGURED — name suggests width-only but width_only is OFF:\n";
    echo str_repeat('-', 60) . "\n";
    foreach ($suspects as $s) {
        echo sprintf("  #%-5d (client %d)  %s\n",
            (int) $s['id'], (int) $s['client_id'], (string) $s['name']);
    }
    echo "\nReview each: if it's a headrail/track/rail line, open its edit page\n";
    echo "and tick 'Priced by width only'. (This script changes nothing.)\n";
} else {
    echo "\nNo obvious width-only suspects by name. Still eyeball the table above\n";
    echo "for any copied product whose flags look wrong.\n";
}
