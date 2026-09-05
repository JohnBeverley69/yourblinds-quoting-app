<?php
declare(strict_types=1);

/**
 * Migration: fix stale rule cell values (and one mis-typed worksheet field) that
 * system_check.php flagged — rule cells that match no active option choice, so the
 * row never fires and the blind sizes wrong or blank. Targeted, no re-seed, idempotent.
 *
 * Works on the DECODED JSON (not a raw REPLACE) so it is immune to JSON slash-escaping
 * — the reason the earlier migrate_draw_slash_uniform.php silently changed nothing
 * ("C / L" is stored as "C \/ L"). This supersedes that migration.
 *
 *   build_variables cells:
 *     - any column        : "C / L" -> "C/L", "C / R" -> "C/R"
 *     - Fascia Options     : "None" -> "No Fascia"           (No-Fascia roller had no tube cut)
 *     - Scallops and Trims : "Not Required" -> "No Scallop"  (No-Scallop roller used the catch-all)
 *   worksheet_templates:
 *     - field source "var:Hem" -> "var:Hem_To_Hem"           (the "Cut" field was rendering blank)
 *
 * Run as super-admin: /migrate_fix_rule_values.php
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

// column label (lower) => [from cell value => to]. '*' applies to every column.
$byCol = [
    '*'                  => ['C / L' => 'C/L', 'C / R' => 'C/R'],
    'fascia options'     => ['None' => 'No Fascia'],
    'scallops and trims' => ['Not Required' => 'No Scallop'],
];

echo "== build_variables cell fixes ==\n";
$sel = $pdo->query('SELECT id, name, columns_json, rows_json FROM build_variables');
$upd = $pdo->prepare('UPDATE build_variables SET rows_json = ? WHERE id = ?');
$bvChanged = 0;
foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $bv) {
    $cols = json_decode((string) $bv['columns_json'], true) ?: [];
    $rows = json_decode((string) $bv['rows_json'], true);
    if (!is_array($rows)) continue;
    $labels = [];
    foreach ($cols as $i => $c) { $labels[$i] = strtolower(trim((string) ($c['label'] ?? ''))); }

    $dirty = false;
    foreach ($rows as &$row) {
        if (!isset($row['cells']) || !is_array($row['cells'])) continue;
        foreach ($row['cells'] as $i => &$cell) {
            $val = (string) $cell;
            foreach (['*', $labels[$i] ?? ''] as $key) {
                if ($key !== '' && isset($byCol[$key][$val])) { $cell = $byCol[$key][$val]; $val = (string) $cell; $dirty = true; }
            }
        }
        unset($cell);
    }
    unset($row);

    if ($dirty) {
        $upd->execute([json_encode($rows, JSON_UNESCAPED_UNICODE), (int) $bv['id']]);
        $bvChanged++;
        echo "  fixed rule '{$bv['name']}' (build_variables id {$bv['id']})\n";
    }
}
echo "  {$bvChanged} build-variable row(s) updated.\n\n";

echo "== worksheet_templates: field source \"var:Hem\" -> \"var:Hem_To_Hem\" ==\n";
try {
    $ws = $pdo->query('SELECT id, product_id, layout_json FROM worksheet_templates');
    $wu = $pdo->prepare('UPDATE worksheet_templates SET layout_json = ? WHERE id = ?');
    $wsChanged = 0;
    $walk = static function (&$node) use (&$walk, &$dirty) {
        if (!is_array($node)) return;
        if (isset($node['source']) && $node['source'] === 'var:Hem') { $node['source'] = 'var:Hem_To_Hem'; $dirty = true; }
        foreach ($node as &$child) { if (is_array($child)) $walk($child); }
        unset($child);
    };
    foreach ($ws->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $lay = json_decode((string) $t['layout_json'], true);
        if (!is_array($lay)) continue;
        $dirty = false;
        $walk($lay);
        if ($dirty) {
            $wu->execute([json_encode($lay, JSON_UNESCAPED_UNICODE), (int) $t['id']]);
            $wsChanged++;
            echo "  fixed worksheet template id {$t['id']} (product {$t['product_id']})\n";
        }
    }
    echo "  {$wsChanged} worksheet template(s) updated.\n";
} catch (Throwable $e) {
    echo "  skipped (worksheet_templates absent?): " . $e->getMessage() . "\n";
}

echo "\nDone. Re-run /system_check.php to confirm these clear.\n";
