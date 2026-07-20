<?php
declare(strict_types=1);

/**
 * AJAX endpoint for the inline-editable choices grid on extra.php.
 *
 * One field at a time — keeps the wire payload tiny, the server-side
 * validation focused, and the JS event wiring obvious (each input
 * fires its own request on change/blur).
 *
 * Actions:
 *   - create     create a new choice. Required: extra_id, label.
 *                Optional: system_id (0 / missing = "all systems").
 *                Returns the new row's id + default field values.
 *
 *   - update     change one field on one choice. Required: extra_id,
 *                choice_id, field, value. Whitelist of editable fields:
 *                label, system_id, price_delta, price_percent,
 *                price_per_metre, is_default, active.
 *
 *   - duplicate  clone label/prices/image/width-table for a choice;
 *                new row gets system_id NULL + is_default 0 so the user
 *                can pick the system inline. Returns the new row.
 *
 *   - delete     remove a choice. Required: extra_id, choice_id.
 *
 *   - set_bands_all  apply one band scope to EVERY choice on the extra
 *                in a single transaction. Required: extra_id. Optional:
 *                bands[] (empty = "all bands" for every choice). Powers
 *                the "Set all" control in the grid's Bands header.
 *
 *   - set_system_all set the "Available on" system for EVERY choice on
 *                the extra. Required: extra_id. Optional: system_id
 *                ('' / '0' = all systems). Powers the "Set all" control
 *                in the grid's "Available on" header.
 *
 *   - set_price_all  set one price field (price_delta / price_percent /
 *                price_per_metre) on EVERY choice on the extra. Required:
 *                extra_id, field, value. Powers the "Set all" controls in
 *                the grid's price column headers.
 *
 * CSRF: takes the token via X-CSRF-Token header (sent by the JS) or
 * a csrf_token form field (fallback for non-XHR clients). Same token
 * format as csrf_field() so the rest of the admin keeps working.
 *
 * All responses are JSON. HTTP status mirrors the outcome (200 ok,
 * 400 validation, 403 csrf/auth, 404 missing).
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// CSRF — accept either header (XHR) or form field (fallback).
// The session key is _csrf (set by csrf_token() in auth/middleware.php),
// not csrf_token. Using the helper keeps us aligned with the rest of
// the app even if that ever changes.
$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
if (!hash_equals(csrf_token(), $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token invalid — reload the page.']);
    exit;
}

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();

// Tenant-scoped extra lookup. Every action operates within one extra,
// so we resolve it once here rather than per-action.
$extraId = (int) ($_POST['extra_id'] ?? 0);
$extraSt = $pdo->prepare(
    'SELECT e.id, e.product_id FROM product_extras e
      WHERE e.id = ? AND e.client_id = ? LIMIT 1'
);
$extraSt->execute([$extraId, $clientId]);
$extra = $extraSt->fetch();
if (!$extra) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Option not found.']);
    exit;
}
$productId = (int) $extra['product_id'];

// Audit trail — log create / update / re-scope / delete / bulk "set all"
// so catalogue changes are traceable ("did this choice get removed or
// just re-scoped?"). Best-effort: never breaks the action.
require_once __DIR__ . '/../../_partials/catalogue_audit.php';

// Helper: validate that a system_id belongs to this product (or 0/null
// for "all systems"). Returns the int id to store, or null. Throws on
// an invalid id.
$validateSystemId = static function (mixed $raw) use ($pdo, $productId, $clientId): ?int {
    if ($raw === null || $raw === '' || (int) $raw === 0) {
        return null;
    }
    $sid = (int) $raw;
    $st = $pdo->prepare(
        'SELECT id FROM product_systems
          WHERE id = ? AND product_id = ? AND client_id = ?
          LIMIT 1'
    );
    $st->execute([$sid, $productId, $clientId]);
    if ($st->fetchColumn() === false) {
        throw new RuntimeException('That system does not belong to this product.');
    }
    return $sid;
};

// Helper: the next sort_order for a fresh row appended to this extra's
// choices list. Drag-and-drop owns ordering after that.
$nextSortOrder = static function () use ($pdo, $extraId): int {
    $st = $pdo->prepare(
        'SELECT COALESCE(MAX(sort_order), -1) + 1
           FROM product_extra_choices
          WHERE product_extra_id = ?'
    );
    $st->execute([$extraId]);
    return (int) $st->fetchColumn();
};

// Helper: re-fetch a choice row in the shape the JS expects so creates
// + duplicates can drop straight into the table.
$fetchChoice = static function (int $choiceId) use ($pdo): array {
    $st = $pdo->prepare(
        'SELECT id, system_id, label,
                price_delta, price_percent, price_per_metre,
                is_default, sort_order, active, image_path,
                (SELECT COUNT(*) FROM extra_choice_price_rows r
                  WHERE r.product_extra_choice_id = product_extra_choices.id) AS width_table_size
           FROM product_extra_choices WHERE id = ? LIMIT 1'
    );
    $st->execute([$choiceId]);
    $r = $st->fetch();
    if (!$r) {
        throw new RuntimeException('Choice missing after save.');
    }
    return [
        'id'                => (int)    $r['id'],
        'system_id'         => $r['system_id'] !== null ? (int) $r['system_id'] : null,
        'label'             => (string) $r['label'],
        'price_delta'       => number_format((float) $r['price_delta'],     2, '.', ''),
        'price_percent'     => number_format((float) $r['price_percent'],   2, '.', ''),
        'price_per_metre'   => number_format((float) $r['price_per_metre'], 2, '.', ''),
        'is_default'        => (int) $r['is_default'],
        'active'            => (int) $r['active'],
        'image_path'        => $r['image_path'] !== null ? (string) $r['image_path'] : null,
        'width_table_size'  => (int) $r['width_table_size'],
    ];
};

// Helper: enforce "one default per (extra, system) bucket" when a row
// is being marked default. NULL system_id forms its own bucket.
$clearOtherDefaults = static function (int $choiceId, ?int $systemId) use ($pdo, $extraId): void {
    if ($systemId === null) {
        $pdo->prepare(
            'UPDATE product_extra_choices SET is_default = 0
              WHERE product_extra_id = ? AND id != ? AND system_id IS NULL'
        )->execute([$extraId, $choiceId]);
    } else {
        $pdo->prepare(
            'UPDATE product_extra_choices SET is_default = 0
              WHERE product_extra_id = ? AND id != ? AND system_id = ?'
        )->execute([$extraId, $choiceId, $systemId]);
    }
};

$action = (string) ($_POST['action'] ?? '');

try {
    switch ($action) {
        // -----------------------------------------------------------------
        case 'create':
            $label = trim((string) ($_POST['label'] ?? ''));
            if ($label === '')             throw new RuntimeException('Label is required.');
            if (strlen($label) > 150)      throw new RuntimeException('Label too long (150 max).');

            // Accept system_ids[] (multi-select on the new-row, the
            // common case) or system_id (single, for any older clients
            // / scripts). Each entry becomes one row.
            //
            // Empty array, missing, or 0 / "" → one row with NULL
            // (= "all systems"). A 0/empty mixed in with specific ids
            // overrides the rest — "all systems" can't be combined with
            // a partial list since they'd be redundant.
            $rawList = [];
            if (isset($_POST['system_ids']) && is_array($_POST['system_ids'])) {
                $rawList = $_POST['system_ids'];
            } elseif (isset($_POST['system_id'])) {
                $rawList = [$_POST['system_id']];
            }

            $systemIdsToStore = [];
            $hasAll = (count($rawList) === 0);  // missing → all systems
            foreach ($rawList as $raw) {
                $sid = $validateSystemId($raw);
                if ($sid === null) { $hasAll = true; continue; }
                if (!in_array($sid, $systemIdsToStore, true)) {
                    $systemIdsToStore[] = $sid;
                }
            }
            if ($hasAll) {
                // "All systems" is dominant — collapse to a single NULL row.
                $systemIdsToStore = [null];
            }
            if (empty($systemIdsToStore)) {
                $systemIdsToStore = [null]; // defensive
            }

            // Insert one row per chosen system. sort_order increments so
            // they land in the same visual block.
            $created   = [];
            $sortOrder = $nextSortOrder();
            $ins = $pdo->prepare(
                'INSERT INTO product_extra_choices
                   (product_extra_id, system_id, label,
                    price_delta, price_percent, price_per_metre,
                    is_default, sort_order, active)
                 VALUES (?, ?, ?, 0, 0, 0, 0, ?, 1)'
            );
            foreach ($systemIdsToStore as $sid) {
                $ins->execute([$extraId, $sid, $label, $sortOrder++]);
                $newId = (int) $pdo->lastInsertId();
                $created[] = $fetchChoice($newId);
                catalogue_audit_log('choice', $newId, 'create', $label, null,
                    ['label' => $label, 'system_id' => $sid], $productId,
                    ['extra_id' => $extraId]);
            }

            echo json_encode([
                'ok'      => true,
                'choices' => $created,
                // Back-compat for any caller still expecting a single
                // 'choice' field (older JS during a brief deploy gap).
                'choice'  => $created[0] ?? null,
            ]);
            break;

        // -----------------------------------------------------------------
        case 'update':
            $choiceId = (int) ($_POST['choice_id'] ?? 0);
            $field    = (string) ($_POST['field'] ?? '');
            $value    = $_POST['value'] ?? null;

            // Verify the choice belongs to this extra (and so this tenant,
            // by transitive trust through the extra check above).
            $cSt = $pdo->prepare(
                'SELECT id, system_id FROM product_extra_choices
                  WHERE id = ? AND product_extra_id = ? LIMIT 1'
            );
            $cSt->execute([$choiceId, $extraId]);
            $choiceRow = $cSt->fetch();
            if (!$choiceRow) throw new RuntimeException('Choice not found.');

            switch ($field) {
                case 'label':
                    $val = trim((string) $value);
                    if ($val === '')          throw new RuntimeException('Label cannot be empty.');
                    if (strlen($val) > 150)   throw new RuntimeException('Label too long (150 max).');
                    $pdo->prepare('UPDATE product_extra_choices SET label = ? WHERE id = ?')
                        ->execute([$val, $choiceId]);
                    break;

                case 'system_id':
                    $newSystemId = $validateSystemId($value);
                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE product_extra_choices SET system_id = ? WHERE id = ?')
                        ->execute([$newSystemId, $choiceId]);
                    // If this row is the default and the system bucket
                    // changed, the new bucket may already have a default.
                    // Leave it — the user can reconcile by toggling. We
                    // only auto-clear when *creating* a new default.
                    $pdo->commit();
                    break;

                case 'price_delta':
                case 'price_percent':
                case 'price_per_metre':
                    if (!is_numeric($value)) throw new RuntimeException('Must be a number.');
                    $pdo->prepare("UPDATE product_extra_choices SET $field = ? WHERE id = ?")
                        ->execute([(float) $value, $choiceId]);
                    break;

                case 'is_default':
                    $on = !empty($value) && $value !== '0' ? 1 : 0;
                    $pdo->beginTransaction();
                    if ($on === 1) {
                        $sysId = $choiceRow['system_id'] !== null
                            ? (int) $choiceRow['system_id']
                            : null;
                        $clearOtherDefaults($choiceId, $sysId);
                    }
                    $pdo->prepare('UPDATE product_extra_choices SET is_default = ? WHERE id = ?')
                        ->execute([$on, $choiceId]);
                    $pdo->commit();
                    break;

                case 'active':
                    $on = !empty($value) && $value !== '0' ? 1 : 0;
                    $pdo->prepare('UPDATE product_extra_choices SET active = ? WHERE id = ?')
                        ->execute([$on, $choiceId]);
                    break;

                default:
                    throw new RuntimeException('Unknown field "' . $field . '".');
            }

            // Capture the "before" for a re-scope so the log reads
            // "system_id: 26 → null"; other fields just record the new value.
            $auditBefore = $field === 'system_id'
                ? ['system_id' => $choiceRow['system_id'] !== null ? (int) $choiceRow['system_id'] : null]
                : null;
            catalogue_audit_log('choice', $choiceId, 'update', null, $auditBefore,
                [$field => is_scalar($value) ? $value : null], $productId,
                ['extra_id' => $extraId, 'field' => $field]);

            echo json_encode(['ok' => true]);
            break;

        // -----------------------------------------------------------------
        case 'duplicate':
            $sourceId = (int) ($_POST['choice_id'] ?? 0);

            // Optional system_id override — when an existing-row multi-
            // select spawns a sibling for a specific system, we pass it
            // here. Default NULL ("all systems") matches the original
            // Dup-button behaviour.
            $cloneSystemId = null;
            if (isset($_POST['system_id'])) {
                $cloneSystemId = $validateSystemId($_POST['system_id']);
            }

            // per_metre_basis is optional (migrate_per_metre_basis.php) —
            // carry it onto the clone when the column is present.
            $apiHasBasis = false;
            try {
                $pdo->query('SELECT per_metre_basis FROM product_extra_choices LIMIT 1');
                $apiHasBasis = true;
            } catch (Throwable $e) { /* column absent */ }
            $basisSel = $apiHasBasis ? ', per_metre_basis' : '';

            $srcSt = $pdo->prepare(
                "SELECT label, image_path,
                        price_delta, price_percent, price_per_metre,
                        sort_order, active$basisSel
                   FROM product_extra_choices
                  WHERE id = ? AND product_extra_id = ? LIMIT 1"
            );
            $srcSt->execute([$sourceId, $extraId]);
            $src = $srcSt->fetch();
            if (!$src) throw new RuntimeException('Source choice not found.');

            $pdo->beginTransaction();

            // Sort just after the source so the clone lands next to it.
            $newSort = (int) $src['sort_order'] + 1;

            $basisCol = $apiHasBasis ? ', per_metre_basis' : '';
            $basisPh  = $apiHasBasis ? ', ?' : '';
            $ins = $pdo->prepare(
                "INSERT INTO product_extra_choices
                   (product_extra_id, system_id, label, image_path,
                    price_delta, price_percent, price_per_metre,
                    is_default, sort_order, active$basisCol)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?$basisPh)"
            );
            $insVals = [
                $extraId,
                $cloneSystemId,
                (string) $src['label'],
                $src['image_path'],
                $src['price_delta'], $src['price_percent'], $src['price_per_metre'],
                $newSort,
                (int) $src['active'],
            ];
            if ($apiHasBasis) $insVals[] = (string) ($src['per_metre_basis'] ?? 'width');
            $ins->execute($insVals);
            $newId = (int) $pdo->lastInsertId();

            // Copy width-table rows so per-clone editing stays isolated.
            $wSrc = $pdo->prepare(
                'SELECT width_mm, price FROM extra_choice_price_rows
                  WHERE product_extra_choice_id = ?'
            );
            $wSrc->execute([$sourceId]);
            $wIns = $pdo->prepare(
                'INSERT INTO extra_choice_price_rows
                   (product_extra_choice_id, width_mm, price)
                 VALUES (?, ?, ?)'
            );
            foreach ($wSrc->fetchAll(PDO::FETCH_ASSOC) as $w) {
                $wIns->execute([$newId, (int) $w['width_mm'], $w['price']]);
            }

            $pdo->commit();
            catalogue_audit_log('choice', $newId, 'duplicate', (string) $src['label'], null,
                ['label' => (string) $src['label'], 'system_id' => $cloneSystemId], $productId,
                ['extra_id' => $extraId, 'from_choice' => $sourceId]);
            echo json_encode(['ok' => true, 'choice' => $fetchChoice($newId)]);
            break;

        // -----------------------------------------------------------------
        // set_bands — replace the per-choice band scoping in one go.
        // Inputs: choice_id (int), bands[] (array of band_code strings).
        // Empty bands[] = "applies to every band" (clears the junction).
        // Cross-checked against the parent product's known bands so a
        // tampered request can't slip in arbitrary band codes; submitted
        // values are snapped to canonical case from the known list.
        // -----------------------------------------------------------------
        case 'set_bands':
            $choiceId = (int) ($_POST['choice_id'] ?? 0);

            // Confirm the choice belongs to this extra (already tenant-
            // scoped by the extra ownership check above).
            $cSt = $pdo->prepare(
                'SELECT c.id, e.product_id
                   FROM product_extra_choices c
                   JOIN product_extras e ON e.id = c.product_extra_id
                  WHERE c.id = ? AND c.product_extra_id = ? LIMIT 1'
            );
            $cSt->execute([$choiceId, $extraId]);
            $cRow = $cSt->fetch();
            if (!$cRow) throw new RuntimeException('Choice not found.');
            $cProductId = (int) $cRow['product_id'];

            // Junction-table existence check — same defensive pattern
            // as the rest of the band-scoping feature so pre-migration
            // tenants get a clear error rather than a fatal SQL.
            $hasTbl = false;
            try {
                $hasTbl = (bool) $pdo->query(
                    "SELECT 1 FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME   = 'product_extra_choice_bands'"
                )->fetchColumn();
            } catch (Throwable $e) { /* keep false */ }
            if (!$hasTbl) {
                throw new RuntimeException(
                    'Band scoping is not enabled — run migrate_choice_band_scoping.php first.'
                );
            }

            // Build the canonical-band list for this product so the
            // user can't submit garbage band codes via a tampered DOM.
            $kbSt = $pdo->prepare(
                "SELECT DISTINCT band_code FROM (
                    SELECT band_code FROM product_options
                     WHERE product_id = ? AND client_id = ?
                    UNION
                    SELECT band_code FROM price_tables
                     WHERE product_id = ? AND client_id = ?
                 ) x
                 WHERE band_code IS NOT NULL AND band_code != ''"
            );
            $kbSt->execute([$cProductId, $clientId, $cProductId, $clientId]);
            $knownBands      = array_map('strval', $kbSt->fetchAll(PDO::FETCH_COLUMN));
            $knownBandsLower = array_map('strtolower', $knownBands);

            $submitted = is_array($_POST['bands'] ?? null) ? $_POST['bands'] : [];
            $clean     = [];
            foreach ($submitted as $b) {
                $bs = trim((string) $b);
                if ($bs === '') continue;
                $idx = array_search(strtolower($bs), $knownBandsLower, true);
                if ($idx !== false) $clean[] = $knownBands[$idx];
            }
            $clean = array_values(array_unique($clean));

            $pdo->beginTransaction();
            $pdo->prepare(
                'DELETE FROM product_extra_choice_bands WHERE choice_id = ?'
            )->execute([$choiceId]);
            if ($clean) {
                $ib = $pdo->prepare(
                    'INSERT INTO product_extra_choice_bands
                       (choice_id, band_code) VALUES (?, ?)'
                );
                foreach ($clean as $b) {
                    $ib->execute([$choiceId, $b]);
                }
            }
            $pdo->commit();

            catalogue_audit_log('choice', $choiceId, 'update', null, null,
                ['bands' => $clean], $productId, ['extra_id' => $extraId, 'set' => 'bands']);

            echo json_encode([
                'ok'    => true,
                'bands' => $clean,
                'count' => count($clean),
            ]);
            break;

        // -----------------------------------------------------------------
        // set_fabrics — restrict a choice to specific FABRICS.
        //
        // Bands can't always say it: on Bev Infusions "38mm slat" applies to
        // Snow and Cool White, but Snow shares band C with ten other colours,
        // so a band scope would offer 38mm on all of them. Empty list = every
        // fabric, matching how a choice behaves before it is ever scoped.
        // -----------------------------------------------------------------
        case 'set_fabrics':
            $choiceId = (int) ($_POST['choice_id'] ?? 0);

            $cSt = $pdo->prepare(
                'SELECT c.id, e.product_id
                   FROM product_extra_choices c
                   JOIN product_extras e ON e.id = c.product_extra_id
                  WHERE c.id = ? AND c.product_extra_id = ? LIMIT 1'
            );
            $cSt->execute([$choiceId, $extraId]);
            $cRow = $cSt->fetch();
            if (!$cRow) throw new RuntimeException('Choice not found.');
            $cProductId = (int) $cRow['product_id'];

            $hasTbl = false;
            try {
                $hasTbl = (bool) $pdo->query(
                    "SELECT 1 FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME   = 'product_extra_choice_options'"
                )->fetchColumn();
            } catch (Throwable $e) { /* keep false */ }
            if (!$hasTbl) {
                throw new RuntimeException(
                    'Fabric scoping is not enabled — run migrate_choice_option_scoping.php first.'
                );
            }

            // Only fabrics that actually belong to THIS product/tenant, so a
            // tampered DOM can't scope a choice to someone else's fabric.
            $koSt = $pdo->prepare(
                'SELECT id FROM product_options WHERE product_id = ? AND client_id = ?'
            );
            $koSt->execute([$cProductId, $clientId]);
            $knownOpts = array_map('intval', $koSt->fetchAll(PDO::FETCH_COLUMN));

            $submitted = is_array($_POST['fabrics'] ?? null) ? $_POST['fabrics'] : [];
            $clean     = [];
            foreach ($submitted as $o) {
                $oid = (int) $o;
                if ($oid > 0 && in_array($oid, $knownOpts, true)) $clean[] = $oid;
            }
            $clean = array_values(array_unique($clean));

            $pdo->beginTransaction();
            $pdo->prepare(
                'DELETE FROM product_extra_choice_options WHERE choice_id = ?'
            )->execute([$choiceId]);
            if ($clean) {
                $io = $pdo->prepare(
                    'INSERT INTO product_extra_choice_options
                       (choice_id, option_id) VALUES (?, ?)'
                );
                foreach ($clean as $oid) { $io->execute([$choiceId, $oid]); }
            }
            $pdo->commit();

            catalogue_audit_log('choice', $choiceId, 'update', null, null,
                ['fabrics' => $clean], $productId, ['extra_id' => $extraId, 'set' => 'fabrics']);

            echo json_encode([
                'ok'      => true,
                'fabrics' => $clean,
                'count'   => count($clean),
            ]);
            break;

        // -----------------------------------------------------------------
        // set_bands_all — apply ONE band scope to every choice on this
        // extra at once. Same validation + canonicalisation as set_bands,
        // but the DELETE/INSERT spans all of the extra's choices in a
        // single transaction. Empty bands[] = "all bands" everywhere.
        // -----------------------------------------------------------------
        case 'set_bands_all':
            // Junction-table existence check — same defensive pattern as
            // set_bands so pre-migration tenants get a clear error.
            $hasTbl = false;
            try {
                $hasTbl = (bool) $pdo->query(
                    "SELECT 1 FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME   = 'product_extra_choice_bands'"
                )->fetchColumn();
            } catch (Throwable $e) { /* keep false */ }
            if (!$hasTbl) {
                throw new RuntimeException(
                    'Band scoping is not enabled — run migrate_choice_band_scoping.php first.'
                );
            }

            // Canonical band list for this product (same query as set_bands)
            // so a tampered DOM can't inject arbitrary band codes.
            $kbSt = $pdo->prepare(
                "SELECT DISTINCT band_code FROM (
                    SELECT band_code FROM product_options
                     WHERE product_id = ? AND client_id = ?
                    UNION
                    SELECT band_code FROM price_tables
                     WHERE product_id = ? AND client_id = ?
                 ) x
                 WHERE band_code IS NOT NULL AND band_code != ''"
            );
            $kbSt->execute([$productId, $clientId, $productId, $clientId]);
            $knownBands      = array_map('strval', $kbSt->fetchAll(PDO::FETCH_COLUMN));
            $knownBandsLower = array_map('strtolower', $knownBands);

            $submitted = is_array($_POST['bands'] ?? null) ? $_POST['bands'] : [];
            $clean     = [];
            foreach ($submitted as $b) {
                $bs = trim((string) $b);
                if ($bs === '') continue;
                $idx = array_search(strtolower($bs), $knownBandsLower, true);
                if ($idx !== false) $clean[] = $knownBands[$idx];
            }
            $clean = array_values(array_unique($clean));

            $pdo->beginTransaction();
            // Clear every band row for choices under this extra...
            $pdo->prepare(
                'DELETE FROM product_extra_choice_bands
                  WHERE choice_id IN (
                        SELECT id FROM product_extra_choices
                         WHERE product_extra_id = ?
                  )'
            )->execute([$extraId]);
            // ...then, per chosen band, insert a row for every choice.
            if ($clean) {
                $ib = $pdo->prepare(
                    'INSERT INTO product_extra_choice_bands (choice_id, band_code)
                     SELECT id, ? FROM product_extra_choices
                      WHERE product_extra_id = ?'
                );
                foreach ($clean as $b) {
                    $ib->execute([$b, $extraId]);
                }
            }
            // Count choices affected for the response.
            $cntSt = $pdo->prepare(
                'SELECT COUNT(*) FROM product_extra_choices WHERE product_extra_id = ?'
            );
            $cntSt->execute([$extraId]);
            $affected = (int) $cntSt->fetchColumn();
            $pdo->commit();

            catalogue_audit_log('extra', $extraId, 'update', null, null,
                ['bands' => $clean], $productId, ['set' => 'bands_all', 'affected' => $affected]);

            echo json_encode([
                'ok'       => true,
                'bands'    => $clean,
                'affected' => $affected,
            ]);
            break;

        // -----------------------------------------------------------------
        // set_system_all — set the "Available on" system for EVERY choice
        // on the extra in one go. Input: extra_id, system_id ('' / '0' =
        // "all systems" = NULL, else a system that must belong to this
        // product). Powers the "Set all" control in the grid's
        // "Available on" header.
        // -----------------------------------------------------------------
        case 'set_system_all':
            // Multi-target: accept system_ids[] (each = a system every choice
            // should be available on) or a single system_id (back-compat).
            // Empty / "all" → collapse every choice to one "All systems" row.
            // The model stores ONE system per row, so several target systems
            // means each label is fanned out to one row per system.
            $rawList = [];
            if (isset($_POST['system_ids']) && is_array($_POST['system_ids'])) {
                $rawList = $_POST['system_ids'];
            } elseif (isset($_POST['system_id'])) {
                $rawList = [$_POST['system_id']];
            }
            $targets = [];
            $hasAll  = (count($rawList) === 0);
            foreach ($rawList as $raw) {
                $sid = $validateSystemId($raw);          // null = "all systems"
                if ($sid === null) { $hasAll = true; continue; }
                if (!in_array($sid, $targets, true)) $targets[] = $sid;
            }
            if ($hasAll || empty($targets)) $targets = [null];

            $pdo->beginTransaction();
            try {
                if (count($targets) === 1) {
                    // Single scope (incl. "All systems") — just repoint every row.
                    $pdo->prepare('UPDATE product_extra_choices SET system_id = ? WHERE product_extra_id = ?')
                        ->execute([$targets[0], $extraId]);
                } else {
                    // Fan each label out to exactly the target systems: reuse
                    // existing rows where possible (lossless — keeps prices,
                    // width tables, thumbnails) and clone the template for any
                    // system not yet covered; drop rows for non-target systems.
                    $clone = static function (array $tpl, int $sysId) use ($pdo): void {
                        $tplId = (int) $tpl['id'];
                        unset($tpl['id']);
                        $tpl['system_id'] = $sysId;
                        $cols = array_keys($tpl);
                        $pdo->prepare(
                            'INSERT INTO product_extra_choices (' . implode(',', $cols) . ') VALUES ('
                            . implode(',', array_fill(0, count($cols), '?')) . ')'
                        )->execute(array_values($tpl));
                        $newId = (int) $pdo->lastInsertId();
                        // Carry the choice's child data so the clone is faithful.
                        foreach ([
                            ['extra_choice_price_rows', 'product_extra_choice_id', 'width_mm, price'],
                            ['product_extra_choice_bands', 'choice_id', 'band_code'],
                        ] as [$tbl, $fk, $copyCols]) {
                            try {
                                $pdo->prepare("INSERT INTO $tbl ($fk, $copyCols) SELECT ?, $copyCols FROM $tbl WHERE $fk = ?")
                                    ->execute([$newId, $tplId]);
                            } catch (Throwable $e) { /* table absent / different shape — skip */ }
                        }
                    };

                    $rowsStmt = $pdo->prepare('SELECT * FROM product_extra_choices WHERE product_extra_id = ? ORDER BY sort_order, id');
                    $rowsStmt->execute([$extraId]);
                    $byLabel = [];
                    foreach ($rowsStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        $byLabel[(string) $r['label']][] = $r;
                    }
                    $updSys = $pdo->prepare('UPDATE product_extra_choices SET system_id = ? WHERE id = ?');
                    $delRow = $pdo->prepare('DELETE FROM product_extra_choices WHERE id = ?');
                    foreach ($byLabel as $grp) {
                        $needed = $targets;                 // systems still to cover for this label
                        $spare  = [];                       // rows not already on a target system
                        foreach ($grp as $r) {
                            $sid = $r['system_id'] !== null ? (int) $r['system_id'] : null;
                            $idx = array_search($sid, $needed, true);
                            if ($idx !== false) { array_splice($needed, $idx, 1); }
                            else                { $spare[] = $r; }
                        }
                        foreach ($needed as $sysId) {
                            if ($spare) { $reuse = array_shift($spare); $updSys->execute([$sysId, (int) $reuse['id']]); }
                            else        { $clone($grp[0], (int) $sysId); }
                        }
                        foreach ($spare as $r) { $delRow->execute([(int) $r['id']]); }
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            $cntSt = $pdo->prepare('SELECT COUNT(*) FROM product_extra_choices WHERE product_extra_id = ?');
            $cntSt->execute([$extraId]);
            $affected = (int) $cntSt->fetchColumn();
            catalogue_audit_log('extra', $extraId, 'update', null, null,
                ['system_ids' => $targets], $productId, ['set' => 'system_all', 'affected' => $affected]);
            echo json_encode(['ok' => true, 'systems' => $targets, 'affected' => $affected]);
            break;

        // -----------------------------------------------------------------
        // set_price_all — set one price field (flat £ / % / £ per metre)
        // on EVERY choice of the extra. Required: extra_id, field, value.
        // Powers the "Set all" controls in the price column headers.
        // -----------------------------------------------------------------
        case 'set_price_all':
            $field = (string) ($_POST['field'] ?? '');
            $allowed = ['price_delta', 'price_percent', 'price_per_metre'];
            if (!in_array($field, $allowed, true)) {
                throw new RuntimeException('Invalid price field.');
            }
            $value = $_POST['value'] ?? '';
            if (!is_numeric($value)) {
                throw new RuntimeException('Value must be a number.');
            }
            // $field is whitelisted above, so safe to interpolate.
            $pdo->prepare(
                "UPDATE product_extra_choices SET $field = ? WHERE product_extra_id = ?"
            )->execute([(float) $value, $extraId]);
            $cntSt = $pdo->prepare(
                'SELECT COUNT(*) FROM product_extra_choices WHERE product_extra_id = ?'
            );
            $cntSt->execute([$extraId]);
            $affected = (int) $cntSt->fetchColumn();
            catalogue_audit_log('extra', $extraId, 'update', null, null,
                [$field => (float) $value], $productId, ['set' => 'price_all', 'affected' => $affected]);
            echo json_encode([
                'ok'       => true,
                'field'    => $field,
                'value'    => (float) $value,
                'affected' => $affected,
            ]);
            break;

        // -----------------------------------------------------------------
        case 'delete':
            $choiceId = (int) ($_POST['choice_id'] ?? 0);
            $cSt = $pdo->prepare(
                'SELECT id, label, system_id FROM product_extra_choices
                  WHERE id = ? AND product_extra_id = ? LIMIT 1'
            );
            $cSt->execute([$choiceId, $extraId]);
            $delRow = $cSt->fetch();
            if (!$delRow) throw new RuntimeException('Choice not found.');
            $pdo->prepare('DELETE FROM product_extra_choices WHERE id = ?')
                ->execute([$choiceId]);
            catalogue_audit_log('choice', $choiceId, 'delete', (string) $delRow['label'],
                ['label'     => (string) $delRow['label'],
                 'system_id' => $delRow['system_id'] !== null ? (int) $delRow['system_id'] : null],
                null, $productId, ['extra_id' => $extraId]);
            echo json_encode(['ok' => true]);
            break;

        // -----------------------------------------------------------------
        default:
            throw new RuntimeException('Unknown action "' . $action . '".');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
