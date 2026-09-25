<?php
declare(strict_types=1);

/**
 * Seed: roller "Fascia Sizing" multi-mode flow for Bev Roller Blinds.
 *
 * Adds a data-driven cascade layered UNDER the real fascia types (LL / Senses),
 * so a salesperson orders several blinds under one shared fascia from a single
 * form. Everything here is DATA (option groups + choices + parent gates); no
 * behaviour is hardcoded in PHP. Captions are freely editable — the form keys
 * off the stable `code` machine keys (see migrate_extra_code.php), not names.
 *
 *   Fascia Options = <real fascia>   (existing type picker; drives cut allowance)
 *     └─ Fascia Sizing               (code fascia_sizing) — shown only when a
 *          • Standard fascia   (standard, default)  real fascia is chosen
 *          • Over size for single blind (oversize)  → reveals the Fascia width box
 *          • Multi blind       (multi)              → reveals:
 *             └─ Number of Blinds?  (fascia_blind_count) 2..5
 *             └─ Blind 1..5 Width   (fascia_blind_width) number boxes
 *
 * "Blind N Width" boxes use parent_match_all (AND): visible when Multi is
 * chosen AND the count is >= N, so 3/4/5 appear as the count grows while all
 * five stay ordered siblings. The 2..5 range is data — add a "6" choice to
 * raise the max, no code change.
 *
 * The manual fascia width stays on the "Fascia Options" extra's length box
 * (worksheet Fascia_Width / Fascia_Cut and qb_reconcile_fascia_groups read it
 * there) — this seed just tags that extra with code `fascia_options`, and tags
 * the internal "Multiple Blinds in One Fascia" extra with code
 * `multiple_internal` so the builder hides it (reconcile still sets it).
 *
 * Idempotent (find-or-create / upsert). Run AFTER migrate_extra_code.php.
 * Web-runnable: /seed_roller_fascia_modes.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Could not find product 'Bev Roller Blinds' for client {$MASTER}.\n"); }

// Guard: the code column must exist (run migrate_extra_code.php first).
try { $pdo->query('SELECT code FROM product_extras LIMIT 1'); $pdo->query('SELECT code FROM product_extra_choices LIMIT 1'); }
catch (Throwable $e) { exit("The `code` columns are missing — run /migrate_extra_code.php first.\n"); }

// Does product_extras carry length_input_label / parent_match_all? (they do on
// this schema, but stay defensive.)
$hasLen = false; try { $pdo->query('SELECT length_input_label FROM product_extras LIMIT 1'); $hasLen = true; } catch (Throwable $e) {}
$hasMatchAll = false; try { $pdo->query('SELECT parent_match_all FROM product_extras LIMIT 1'); $hasMatchAll = true; } catch (Throwable $e) {}
$hasWidthSrc = false; try { $pdo->query('SELECT is_width_source FROM product_extras LIMIT 1'); $hasWidthSrc = true; } catch (Throwable $e) {}
if (!$hasWidthSrc) { exit("The is_width_source column is missing — run /migrate_extra_width_source.php first.\n"); }

$pdo->beginTransaction();
try {
    // --- Resolve the existing Fascia Options extra + its real fascia choices ---
    $fe = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND name = 'Fascia Options' LIMIT 1");
    $fe->execute([$productId, $MASTER]);
    $fasciaExtraId = (int) $fe->fetchColumn();
    if ($fasciaExtraId === 0) { throw new RuntimeException("Missing 'Fascia Options' on product {$productId}."); }

    // Tag Fascia Options with its code, and REMOVE its old manual-width box —
    // the fascia width now lives in its own "Fascia width" option (a child of
    // Fascia Sizing), so it must not double up on the Fascia Options group.
    if ($hasLen) {
        $pdo->prepare("UPDATE product_extras SET code = 'fascia_options', length_input_label = NULL WHERE id = ?")->execute([$fasciaExtraId]);
    } else {
        $pdo->prepare("UPDATE product_extras SET code = 'fascia_options' WHERE id = ?")->execute([$fasciaExtraId]);
    }
    $pdo->prepare("UPDATE product_extras SET code = 'multiple_internal' WHERE product_id = ? AND client_id = ? AND name = 'Multiple Blinds in One Fascia'")
        ->execute([$productId, $MASTER]);
    echo "Tagged Fascia Options (fascia_options) + Multiple (multiple_internal).\n";

    // Real fascia choices = every Fascia Options choice except "No Fascia".
    $ch = $pdo->prepare("SELECT id, label FROM product_extra_choices WHERE product_extra_id = ? AND active = 1");
    $ch->execute([$fasciaExtraId]);
    $realFasciaChoiceIds = [];
    foreach ($ch->fetchAll(PDO::FETCH_ASSOC) as $c) {
        if (strcasecmp(trim((string) $c['label']), 'No Fascia') !== 0) $realFasciaChoiceIds[] = (int) $c['id'];
    }
    if (!$realFasciaChoiceIds) { throw new RuntimeException("No real fascia choices found on 'Fascia Options'."); }
    echo "Real fascia choices: " . implode(',', $realFasciaChoiceIds) . "\n";

    // Fixings should only appear with a real fascia — drop the "No Fascia" gate
    // link from any Fixings group so it hides when No Fascia is chosen.
    $nf = $pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id = ? AND label = 'No Fascia' LIMIT 1");
    $nf->execute([$fasciaExtraId]);
    $noFasciaId = (int) $nf->fetchColumn();
    if ($noFasciaId > 0) {
        $fx = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND name = 'Fixings'");
        $fx->execute([$productId, $MASTER]);
        $fxIds = array_map('intval', $fx->fetchAll(PDO::FETCH_COLUMN));
        foreach ($fxIds as $fid) {
            $pdo->prepare("DELETE FROM product_extra_parent_choices WHERE product_extra_id = ? AND product_extra_choice_id = ?")
                ->execute([$fid, $noFasciaId]);
            // If the primary parent pointed at No Fascia, repoint to a remaining one.
            $pc = $pdo->prepare("SELECT parent_choice_id FROM product_extras WHERE id = ?");
            $pc->execute([$fid]);
            if ((int) $pc->fetchColumn() === $noFasciaId) {
                $rem = $pdo->prepare("SELECT product_extra_choice_id FROM product_extra_parent_choices WHERE product_extra_id = ? LIMIT 1");
                $rem->execute([$fid]);
                $newPrimary = $rem->fetchColumn();
                $pdo->prepare("UPDATE product_extras SET parent_choice_id = ? WHERE id = ?")
                    ->execute([$newPrimary !== false ? (int) $newPrimary : null, $fid]);
            }
        }
        echo "Removed 'No Fascia' gate from Fixings (" . count($fxIds) . " group(s)) — hidden without a fascia.\n";
    }

    // --- Helpers: find-or-create extra / choice; reset parent gates ----------
    $findExtra = function (string $name) use ($pdo, $productId, $MASTER): int {
        $q = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND name = ? LIMIT 1");
        $q->execute([$productId, $MASTER, $name]);
        return (int) $q->fetchColumn();
    };
    $upsertExtra = function (string $name, string $code, bool $required, int $sort, ?string $lenLabel, bool $isWidthSource = false)
                    use ($pdo, $productId, $MASTER, $findExtra, $hasLen, $hasWidthSrc): int {
        $id = $findExtra($name);
        if ($id > 0) {
            $sql = "UPDATE product_extras SET code = ?, is_required = ?, sort_order = ?, active = 1"
                 . ($hasLen ? ", length_input_label = ?" : "") . " WHERE id = ?";
            $args = $hasLen ? [$code, $required ? 1 : 0, $sort, $lenLabel, $id]
                            : [$code, $required ? 1 : 0, $sort, $id];
            $pdo->prepare($sql)->execute($args);
        } else {
            if ($hasLen) {
                $pdo->prepare("INSERT INTO product_extras (client_id, product_id, parent_choice_id, name, code, is_required, sort_order, active, length_input_label) VALUES (?,?,NULL,?,?,?,?,1,?)")
                    ->execute([$MASTER, $productId, $name, $code, $required ? 1 : 0, $sort, $lenLabel]);
            } else {
                $pdo->prepare("INSERT INTO product_extras (client_id, product_id, parent_choice_id, name, code, is_required, sort_order, active) VALUES (?,?,NULL,?,?,?,?,1)")
                    ->execute([$MASTER, $productId, $name, $code, $required ? 1 : 0, $sort]);
            }
            $id = (int) $pdo->lastInsertId();
        }
        if ($hasWidthSrc) {
            $pdo->prepare("UPDATE product_extras SET is_width_source = ? WHERE id = ?")->execute([$isWidthSource ? 1 : 0, $id]);
        }
        return $id;
    };
    $upsertChoice = function (int $extraId, string $label, string $code, bool $isDefault, int $sort) use ($pdo): int {
        $q = $pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id = ? AND label = ? LIMIT 1");
        $q->execute([$extraId, $label]);
        $id = (int) $q->fetchColumn();
        if ($id > 0) {
            $pdo->prepare("UPDATE product_extra_choices SET code = ?, is_default = ?, sort_order = ?, active = 1 WHERE id = ?")
                ->execute([$code, $isDefault ? 1 : 0, $sort, $id]);
            return $id;
        }
        $pdo->prepare("INSERT INTO product_extra_choices (product_extra_id, system_id, label, code, image_path, price_delta, price_percent, price_per_metre, is_default, sort_order, active) VALUES (?,NULL,?,?,NULL,0,0,0,?,?,1)")
            ->execute([$extraId, $label, $code, $isDefault ? 1 : 0, $sort]);
        return (int) $pdo->lastInsertId();
    };
    $setGates = function (int $extraId, array $parentIds, bool $matchAll) use ($pdo, $hasMatchAll): void {
        $parentIds = array_values(array_unique(array_map('intval', $parentIds)));
        $primary = $parentIds[0] ?? null;
        if ($hasMatchAll) {
            $pdo->prepare("UPDATE product_extras SET parent_choice_id = ?, parent_match_all = ? WHERE id = ?")
                ->execute([$primary, $matchAll ? 1 : 0, $extraId]);
        } else {
            $pdo->prepare("UPDATE product_extras SET parent_choice_id = ? WHERE id = ?")->execute([$primary, $extraId]);
        }
        $pdo->prepare("DELETE FROM product_extra_parent_choices WHERE product_extra_id = ?")->execute([$extraId]);
        $ins = $pdo->prepare("INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)");
        foreach ($parentIds as $pid) $ins->execute([$extraId, $pid]);
    };

    // --- 1. Fascia Sizing (gated on any real fascia) -------------------------
    $sizingId = $upsertExtra('Fascia Sizing', 'fascia_sizing', false, 40, null);
    $cStandard = $upsertChoice($sizingId, 'Standard fascia',             'standard', true,  0);
    $cOversize = $upsertChoice($sizingId, 'Over size for single blind',  'oversize', false, 1);
    $cMulti    = $upsertChoice($sizingId, 'Multi blind',                 'multi',    false, 2);
    $setGates($sizingId, $realFasciaChoiceIds, false);
    echo "Fascia Sizing (#{$sizingId}): standard/oversize/multi, gated on real fascias.\n";

    // --- 1b. Fascia width (own option, child of Fascia Sizing) ---------------
    // A real, reorderable option — number-only, flagged is_width_source so its
    // typed value drives the fascia PRICE (width-table lookup) and CUT
    // (worksheet Fascia_Width). Shown for Over size OR Multi.
    $fwId = $upsertExtra('Fascia width', 'fascia_width', false, 41,
                         'Fascia width (mm)', true);
    $setGates($fwId, [$cOversize, $cMulti], false);
    echo "Fascia width (#{$fwId}): number box, is_width_source, gated on Over size / Multi.\n";

    // --- 2. Number of Blinds? (gated on Multi) -------------------------------
    $countId = $upsertExtra('Number of Blinds?', 'fascia_blind_count', false, 42, null);
    $countChoiceIds = [];   // n => choice id
    foreach ([2 => true, 3 => false, 4 => false, 5 => false] as $n => $def) {
        $countChoiceIds[$n] = $upsertChoice($countId, (string) $n, 'n' . $n, $def, $n);
    }
    $setGates($countId, [$cMulti], false);
    echo "Number of Blinds? (#{$countId}): 2..5, gated on Multi.\n";

    // --- 3. Blind 1..5 Width (number boxes) ----------------------------------
    // Blind 1 & 2: gated on Multi (min 2). Blind N (3..5): Multi AND count>=N
    // via parent_match_all — [multi] owned by Fascia Sizing, [nN..n5] owned by
    // Number of Blinds; AND across the two owner groups.
    for ($n = 1; $n <= 5; $n++) {
        $wId = $upsertExtra("Blind {$n} Width", 'fascia_blind_width', false, 42 + $n, "Blind {$n} width (mm)");
        if ($n <= 2) {
            $setGates($wId, [$cMulti], false);
        } else {
            $countGate = [];
            for ($k = $n; $k <= 5; $k++) $countGate[] = $countChoiceIds[$k];
            // primary parent = multi (owner Fascia Sizing) so it nests as a
            // sibling of Blind 1/2; AND with the count-choice group.
            $setGates($wId, array_merge([$cMulti], $countGate), true);
        }
        echo "Blind {$n} Width (#{$wId}) gated" . ($n <= 2 ? " on Multi.\n" : " Multi AND count>={$n}.\n");
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    exit("\nFAILED: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n(no changes saved)\n");
}

echo "\nDone — Fascia Sizing multi-blind flow on product {$productId}.\n";
echo "Captions are editable on the Roller Options page; the form keys off the codes.\n";
