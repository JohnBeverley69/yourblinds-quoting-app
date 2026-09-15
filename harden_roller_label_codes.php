<?php
declare(strict_types=1);

/**
 * Harden the Bev Roller Blinds worksheet label against option renames.
 *
 *  1. Give every roller option a stable machine code (product_extras.code) where
 *     it is currently NULL — canonical codes for the label-referenced options
 *     (roller_label_code_map()), a slug for anything else, deduped within the
 *     product. Existing codes (the fascia cascade) are never clobbered.
 *  2. Rewrite the roller worksheet template's field sources from opt:<name> to
 *     opt:<code>, so the label no longer depends on the option's caption. The
 *     current layout is snapshotted into worksheet_template_versions first.
 *
 * After this, renaming an option in the option editor cannot blank its label
 * cell — worksheet-print resolves opt:<code> from the live code, with the old
 * opt:<name> kept as a fallback for anything not yet migrated.
 *
 * Idempotent + transactional. Web-runnable: /harden_roller_label_codes.php
 * (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
require_once __DIR__ . '/_partials/roller_label_codes.php';
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Bev Roller Blinds not found for client {$MASTER}.\n"); }

$hasCode = false; try { $pdo->query('SELECT code FROM product_extras LIMIT 1'); $hasCode = true; } catch (Throwable $e) {}
if (!$hasCode) { exit("product_extras.code column absent — nothing to harden.\n"); }

$map = roller_label_code_map();

$pdo->beginTransaction();
try {
    // ---- 1. Assign codes -----------------------------------------------------
    $rows = $pdo->prepare("SELECT id, name, code FROM product_extras WHERE product_id = ? AND client_id = ? ORDER BY sort_order, id");
    $rows->execute([$productId, $MASTER]);
    $all = $rows->fetchAll(PDO::FETCH_ASSOC);

    // Codes already in use in this product (so a new code never collides).
    $used = [];
    foreach ($all as $r) { $c = (string) ($r['code'] ?? ''); if ($c !== '') $used[strtolower($c)] = true; }

    $set = $pdo->prepare("UPDATE product_extras SET code = ? WHERE id = ?");
    $assigned = [];
    foreach ($all as $r) {
        if ((string) ($r['code'] ?? '') !== '') continue;   // never clobber an existing code
        $nm   = strtolower(trim((string) $r['name']));
        $base = $map[$nm] ?? roller_label_slug((string) $r['name']);
        if ($base === '') $base = 'opt';
        $code = $base; $i = 2;
        while (isset($used[strtolower($code)])) { $code = $base . '_' . $i; $i++; }
        $used[strtolower($code)] = true;
        $set->execute([$code, (int) $r['id']]);
        $assigned[] = "#{$r['id']}  {$r['name']}  ->  {$code}";
    }

    // ---- 1b. Mirror the codes onto tenant copies -----------------------------
    // Portal orders carry the tenant's MIRROR of the roller product, whose
    // options point back to the master via source_extra_id. worksheet-print
    // JOINs the order's product_extra_id (the mirror) for its code, so the
    // mirrors need the master's codes too or opt:<code> resolves to nothing.
    $mirrored = 0;
    $hasSrc = false; try { $pdo->query('SELECT source_extra_id FROM product_extras LIMIT 1'); $hasSrc = true; } catch (Throwable $e) {}
    if ($hasSrc) {
        $mir = $pdo->prepare(
            "UPDATE product_extras m
               JOIN product_extras src ON src.id = m.source_extra_id AND src.product_id = ?
                SET m.code = src.code
              WHERE (m.code IS NULL OR m.code = '')
                AND src.code IS NOT NULL AND src.code <> ''"
        );
        $mir->execute([$productId]);
        $mirrored = $mir->rowCount();
    }

    // ---- 2. Rewrite the stored layout field sources to codes ------------------
    // Build a name->code lookup from the (now fully coded) option list.
    $rows->execute([$productId, $MASTER]);
    $nameToCode = [];
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $c = (string) ($r['code'] ?? '');
        if ($c !== '') $nameToCode[strtolower(trim((string) $r['name']))] = $c;
    }

    $t = $pdo->prepare('SELECT id, name, layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id LIMIT 1');
    $t->execute([$productId]);
    $tpl = $t->fetch(PDO::FETCH_ASSOC);
    $migratedSources = [];
    if ($tpl) {
        $layout = json_decode((string) $tpl['layout_json'], true);
        if (is_array($layout)) {
            $before = json_encode($layout, JSON_UNESCAPED_UNICODE);
            $rewriteFields = static function (&$fields) use ($map, $nameToCode, &$migratedSources) {
                if (!is_array($fields)) return;
                foreach ($fields as &$f) {
                    if (!is_array($f)) continue;
                    $src = (string) ($f['source'] ?? '');
                    if (strncmp($src, 'opt:', 4) !== 0) continue;
                    $name = strtolower(trim(substr($src, 4)));
                    $code = $map[$name] ?? ($nameToCode[$name] ?? '');
                    if ($code !== '' && $code !== $name) {
                        $migratedSources[] = $src . ' -> opt:' . $code;
                        $f['source'] = 'opt:' . $code;
                    }
                }
                unset($f);
            };
            if (isset($layout['header']) && is_array($layout['header']) && isset($layout['header']['fields'])) {
                $rewriteFields($layout['header']['fields']);
            }
            if (isset($layout['header']['fields']) === false && isset($layout['headerFields']) && is_array($layout['headerFields'])) {
                $rewriteFields($layout['headerFields']);
            }
            // Iterate by index (NOT `foreach (($layout['labels'] ?? []) as &$lab)`
            // — the `?? []` makes a temporary copy, so a by-ref alias would mutate
            // the copy, not $layout).
            if (!empty($layout['labels']) && is_array($layout['labels'])) {
                foreach (array_keys($layout['labels']) as $li) {
                    if (is_array($layout['labels'][$li]) && isset($layout['labels'][$li]['fields'])) {
                        $rewriteFields($layout['labels'][$li]['fields']);
                    }
                }
            }

            $after = json_encode($layout, JSON_UNESCAPED_UNICODE);
            if ($after !== $before) {
                // Snapshot the pre-migration layout (undoable) if history exists.
                try {
                    $cnt = 0;
                    foreach (($layout['labels'][0]['fields'] ?? []) as $f) {
                        $s = is_array($f) ? (string) ($f['source'] ?? '') : '';
                        if ($s !== '' && $s !== '__break__') $cnt++;
                    }
                    $pdo->prepare(
                        'INSERT INTO worksheet_template_versions (template_id, product_id, name, layout_json, field_count, saved_by_user_id)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([(int) $tpl['id'], $productId, (string) $tpl['name'], (string) $tpl['layout_json'], $cnt, null]);
                } catch (Throwable $e) { /* history table absent — proceed */ }
                $pdo->prepare('UPDATE worksheet_templates SET layout_json = ? WHERE id = ?')->execute([$after, (int) $tpl['id']]);
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    exit("\nFAILED: " . $e->getMessage() . " (no changes saved)\n");
}

echo "Roller label code hardening complete.\n\n";
echo 'Codes assigned to ' . count($assigned) . " previously-uncoded master option(s):\n";
foreach ($assigned as $a) echo "  {$a}\n";
if (!$assigned) echo "  (none — all master options already had codes)\n";
echo "\nTenant mirror options coded from master (by source_extra_id): {$mirrored}\n";
echo "\nLayout field sources migrated to codes: " . count($migratedSources) . "\n";
foreach ($migratedSources as $m) echo "  {$m}\n";
if (!$migratedSources) echo "  (none — layout already on codes or no template)\n";
echo "\nPrint a roller label to confirm it still renders, then rename an option to prove it no longer blanks.\n";
