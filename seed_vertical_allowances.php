<?php
declare(strict_types=1);

/**
 * Seed: vertical headrail cut allowances (the "vertical_headrail" table).
 *
 * From Beverley's "Vertical Cutt allowances" sheet — the mm deducted from the
 * width for the headrail cut, keyed by system + control + (draw) + Recess/Exact.
 * "All minus sums" — every value is a deduction, so it is stored as a NEGATIVE
 * figure (signed-allowance convention: the build rule ADDS the value, so a
 * deduction is negative and any future addition would be positive). Used by
 * Vertical Blinds and Vertical Head Rail Only, referenced from a build rule as
 *   H_Cut = Width_conversion + LOOKUP("vertical_headrail", HeadRail, ControlOptions, DrawOption, ExactorRecess)
 * (Nova is the 2026 system and uses 3 keys: system, control, Recess/Exact.)
 *
 * Idempotent (upsert). Run via web: /seed_vertical_allowances.php (super-admin).
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

$TABLE = 'vertical_headrail';

// [keys[], recessValue, exactValue] — a row is emitted for each of Recess/Exact.
$defs = [];
$add = static function (array &$defs, array $keyPrefix, string $basisless, float $recess, float $exact) {
    $defs[] = [array_merge($keyPrefix, [$basisless]), 'Recess', $recess];
    $defs[] = [array_merge($keyPrefix, [$basisless]), 'Exact',  $exact];
};

// -- Slimline, Wand --
foreach (['Left Stack'=>[12,2], 'Right Stack'=>[12,2], 'Center Left'=>[20,10], 'Center Right'=>[20,10], 'Split Draw 2 Wands'=>[12,2]] as $draw => $v) {
    $add($defs, ['Slimline', 'Wand'], $draw, (float)$v[0], (float)$v[1]);
}
// -- Slimline, Cord and Chain (all draws 30/20) --
foreach (['R/R','L/L','C/L','C/R','L Ctrl / Right Stack','R Ctrl / Left Stack'] as $draw) {
    $add($defs, ['Slimline', 'Cord and Chain'], $draw, 30, 20);
}
// -- Vouge, Wand --
foreach (['Left Stack'=>[22,12], 'Right Stack'=>[22,12], 'Center Left'=>[32,22], 'Center Right'=>[32,22], 'Split Draw 2 Wands'=>[22,12]] as $draw => $v) {
    $add($defs, ['Vouge', 'Wand'], $draw, (float)$v[0], (float)$v[1]);
}
// -- Vouge, Cord and Chain (all draws 33/23) --
foreach (['R/R','L/L','C/L','C/R','L Ctrl / Right Stack','R Ctrl / Left Stack'] as $draw) {
    $add($defs, ['Vouge', 'Cord and Chain'], $draw, 33, 23);
}
// -- Nova (2026 system): 3 keys — system, control, basis --
foreach (['Corded'=>[25,15], 'Wand'=>[15,5], 'Centre wand'=>[15,5]] as $ctrl => $v) {
    $defs[] = [['Nova', $ctrl], 'Recess', (float)$v[0]];
    $defs[] = [['Nova', $ctrl], 'Exact',  (float)$v[1]];
};

// Flatten to rows: keys = keyPrefix + basis.
$rows = [];
foreach ($defs as [$keyPrefix, $basis, $value]) {
    $keys = array_merge($keyPrefix, [$basis]);
    $rows[] = [
        'key_norm'     => strtolower(implode('|', $keys)),
        'keys_display' => implode(' · ', $keys),
        // Signed-allowance convention: every headrail value is a deduction,
        // so store it negative. The build rule adds it (Width + LOOKUP(...)).
        'value'        => $value == 0.0 ? 0.0 : -abs($value),
    ];
}

echo "Seeding {$TABLE} allowances (" . count($rows) . " rows)…\n\n";

$upsert = $pdo->prepare(
    "INSERT INTO allowance_rows (table_name, key_norm, keys_display, value, seq)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE keys_display = VALUES(keys_display), value = VALUES(value), seq = VALUES(seq)"
);
$seq = 0;
foreach ($rows as $r) {
    $upsert->execute([$TABLE, $r['key_norm'], $r['keys_display'], $r['value'], $seq++]);
    echo sprintf("  %-48s = %s\n", $r['keys_display'], rtrim(rtrim(number_format($r['value'], 2, '.', ''), '0'), '.'));
}

echo "\nDone. Reference in a build rule with LOOKUP(\"{$TABLE}\", …).\n";
