<?php
declare(strict_types=1);

/**
 * Clean up duplicate-label option choices on a MASTER product (default the
 * vertical, id 8). Some labels got triplicated over time (e.g. "Corded" ×3,
 * "Centre Left" ×3) — a mix of the Centre spelling migration and repeated edits.
 * The engine already treats same-label choices as one (label expansion), but the
 * dropdown shows the dupes. This keeps the LOWEST-id choice per (extra,label),
 * repoints any parent-gate wiring to it, and deletes the rest.
 *
 * MASTER ONLY: mirrors carry these as source-id copies, so after this runs a
 * catalogue push prunes the mirror dupes (source gone) and re-wires their gates.
 * Idempotent (re-run finds no dupes). Run as super-admin: /migrate_dedup_vertical_choices.php[?product_id=8]
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
$pid = (int) ($_GET['product_id'] ?? 8);

$tryExec = static function (string $sql, array $args) use ($pdo): void {
    try { $pdo->prepare($sql)->execute($args); } catch (Throwable $e) { /* table/col absent */ }
};

$exs = $pdo->prepare('SELECT id, name FROM product_extras WHERE product_id = ? ORDER BY id');
$exs->execute([$pid]);
$extras = $exs->fetchAll(PDO::FETCH_ASSOC);

$deleted = []; // [choiceId => "label (extra)"]
$pdo->beginTransaction();
try {
    foreach ($extras as $ex) {
        $eid = (int) $ex['id'];
        $cs = $pdo->prepare('SELECT id, label, system_id FROM product_extra_choices WHERE product_extra_id = ? ORDER BY id');
        $cs->execute([$eid]);
        $rows = $cs->fetchAll(PDO::FETCH_ASSOC);

        // Group by label AND system scope. A same-label choice on a DIFFERENT
        // system (e.g. "Black" on Vogue vs Nova) is a legitimate per-system
        // option, NOT a duplicate — only merge choices that share both.
        $byKey = [];
        foreach ($rows as $r) {
            $key = strtolower(trim((string) $r['label'])) . '|'
                 . ($r['system_id'] === null ? 'ALL' : (string) (int) $r['system_id']);
            if (!isset($byKey[$key])) $byKey[$key] = ['label' => trim((string) $r['label']), 'ids' => []];
            $byKey[$key]['ids'][] = (int) $r['id'];
        }

        foreach ($byKey as $grp) {
            $ids   = $grp['ids'];
            $label = $grp['label'];
            if (count($ids) < 2) continue;
            sort($ids);                 // lowest id first
            $survivor = array_shift($ids);
            foreach ($ids as $dup) {
                // 1) Repoint parent-gate wiring (join table) to the survivor,
                //    skipping collisions, then drop any leftover rows on the dup.
                $tryExec('UPDATE IGNORE product_extra_parent_choices SET product_extra_choice_id = ? WHERE product_extra_choice_id = ?', [$survivor, $dup]);
                $tryExec('DELETE FROM product_extra_parent_choices WHERE product_extra_choice_id = ?', [$dup]);
                // 2) Legacy single primary-parent column on child extras.
                $tryExec('UPDATE product_extras SET parent_choice_id = ? WHERE parent_choice_id = ?', [$survivor, $dup]);
                // 3) Test quotes reference the dup — repoint (all test data).
                $tryExec('UPDATE quote_item_extras SET choice_id = ? WHERE choice_id = ?', [$survivor, $dup]);
                // 4) Drop the dup's own scoping / pricing (empty for these, but defensive).
                $tryExec('DELETE FROM product_extra_choice_bands   WHERE choice_id = ?', [$dup]);
                $tryExec('DELETE FROM product_extra_choice_options WHERE choice_id = ?', [$dup]);
                $tryExec('DELETE FROM extra_choice_price_rows      WHERE product_extra_choice_id = ?', [$dup]);
                // 5) Remove the duplicate choice.
                $pdo->prepare('DELETE FROM product_extra_choices WHERE id = ?')->execute([$dup]);
                $deleted[$dup] = $label . '  (extra #' . $eid . ' ' . $ex['name'] . ')';
            }
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "FAILED — rolled back: " . $e->getMessage() . "\n";
    exit;
}

echo "Product #$pid — removed " . count($deleted) . " duplicate choice(s):\n";
foreach ($deleted as $id => $desc) echo sprintf("  - #%d  %s\n", $id, $desc);
echo "\nNow run a catalogue push for this product so the mirrors prune their copies.\n";
