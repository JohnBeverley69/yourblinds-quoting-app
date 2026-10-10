<?php
declare(strict_types=1);

/**
 * Re-point option choices scoped to a band name that no longer exists.
 *
 * A choice can be limited to some price bands (product_extra_choice_bands) —
 * Forest Wood's Tape Width and tape Colour show only on the tape bands. The
 * scope stores the band NAME, and renaming a band on the Price tables page
 * used to rename the fabrics but not these scopes. Renaming "50mm Tape" to
 * "Tape" therefore hid the (required) Tape Width on every tape blind.
 * price-table.php now carries the rename through; this mends the ones from
 * before that.
 *
 * For each scope naming a band no price table on its product has, it looks for
 * the ONE band on that product the old name ends with ("50mm Tape" → "Tape",
 * "50mm Gloss Tape" → "Gloss Tape"). Anything it can't place is listed and left
 * alone. Says what it would do before doing it.
 *
 * Super-admin, web-runnable: /setup/tools/fix_orphan_choice_bands.php
 *   (add ?apply=1 to actually change — without it, it only reports)
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
$apply = PHP_SAPI === 'cli' ? in_array('--apply', $argv ?? [], true) : !empty($_GET['apply']);

$orphans = $pdo->query(
    "SELECT cb.choice_id, cb.band_code, c.label AS choice, pe.name AS opt,
            pe.product_id, pe.client_id, p.name AS product
       FROM product_extra_choice_bands cb
       JOIN product_extra_choices c ON c.id = cb.choice_id
       JOIN product_extras pe        ON pe.id = c.product_extra_id
       JOIN products p               ON p.id = pe.product_id
      WHERE NOT EXISTS (SELECT 1 FROM price_tables t
                         WHERE t.product_id = pe.product_id AND t.client_id = pe.client_id
                           AND t.band_code = cb.band_code)
   ORDER BY pe.client_id, p.name, pe.name, c.label"
)->fetchAll(PDO::FETCH_ASSOC);

$bandsSt = $pdo->prepare('SELECT DISTINCT band_code FROM price_tables WHERE product_id = ? AND client_id = ?');
$upd = $pdo->prepare('UPDATE IGNORE product_extra_choice_bands SET band_code = ? WHERE choice_id = ? AND band_code = ?');
$del = $pdo->prepare('DELETE FROM product_extra_choice_bands WHERE choice_id = ? AND band_code = ?');

$fixed = 0; $left = 0;
foreach ($orphans as $o) {
    $bandsSt->execute([(int) $o['product_id'], (int) $o['client_id']]);
    $old = (string) $o['band_code'];
    $hits = [];
    foreach ($bandsSt->fetchAll(PDO::FETCH_COLUMN) as $b) {
        $b = (string) $b;
        if (strlen($old) > strlen($b) && strcasecmp(substr($old, -strlen($b) - 1), ' ' . $b) === 0) $hits[] = $b;
    }
    // Longest match wins: "50mm Gloss Tape" is "Gloss Tape", not "Tape".
    usort($hits, static fn ($a, $b) => strlen($b) <=> strlen($a));
    $new = ($hits && (count($hits) === 1 || strlen($hits[0]) > strlen($hits[1]))) ? $hits[0] : null;

    $where = "client {$o['client_id']} · {$o['product']} · {$o['opt']} = {$o['choice']}";
    if ($new === null) {
        echo "LEFT   $where — scoped to \"$old\", no single band to move it to\n";
        $left++;
        continue;
    }
    echo ($apply ? 'FIXED  ' : 'WOULD  ') . "$where — \"$old\" → \"$new\"\n";
    if ($apply) {
        $upd->execute([$new, (int) $o['choice_id'], $old]);
        $del->execute([(int) $o['choice_id'], $old]);   // leftover if it already had $new
    }
    $fixed++;
}

echo "\n" . count($orphans) . " orphaned scope(s): $fixed " . ($apply ? 'fixed' : 'fixable') . ", $left left alone.\n";
if (!$apply && $fixed) echo "Run again with ?apply=1 to make the changes.\n";
