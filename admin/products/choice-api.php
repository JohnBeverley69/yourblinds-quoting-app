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
$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
$expected = (string) ($_SESSION['csrf_token'] ?? '');
if ($expected === '' || !hash_equals($expected, $token)) {
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

            $systemIdToStore = $validateSystemId($_POST['system_id'] ?? null);

            $sortOrder = $nextSortOrder();

            $ins = $pdo->prepare(
                'INSERT INTO product_extra_choices
                   (product_extra_id, system_id, label,
                    price_delta, price_percent, price_per_metre,
                    is_default, sort_order, active)
                 VALUES (?, ?, ?, 0, 0, 0, 0, ?, 1)'
            );
            $ins->execute([$extraId, $systemIdToStore, $label, $sortOrder]);
            $newId = (int) $pdo->lastInsertId();

            echo json_encode(['ok' => true, 'choice' => $fetchChoice($newId)]);
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

            echo json_encode(['ok' => true]);
            break;

        // -----------------------------------------------------------------
        case 'duplicate':
            $sourceId = (int) ($_POST['choice_id'] ?? 0);

            $srcSt = $pdo->prepare(
                'SELECT label, image_path,
                        price_delta, price_percent, price_per_metre,
                        sort_order, active
                   FROM product_extra_choices
                  WHERE id = ? AND product_extra_id = ? LIMIT 1'
            );
            $srcSt->execute([$sourceId, $extraId]);
            $src = $srcSt->fetch();
            if (!$src) throw new RuntimeException('Source choice not found.');

            $pdo->beginTransaction();

            // Sort just after the source so the clone lands next to it.
            $newSort = (int) $src['sort_order'] + 1;

            $ins = $pdo->prepare(
                'INSERT INTO product_extra_choices
                   (product_extra_id, system_id, label, image_path,
                    price_delta, price_percent, price_per_metre,
                    is_default, sort_order, active)
                 VALUES (?, NULL, ?, ?, ?, ?, ?, 0, ?, ?)'
            );
            $ins->execute([
                $extraId,
                (string) $src['label'],
                $src['image_path'],
                $src['price_delta'], $src['price_percent'], $src['price_per_metre'],
                $newSort,
                (int) $src['active'],
            ]);
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
            echo json_encode(['ok' => true, 'choice' => $fetchChoice($newId)]);
            break;

        // -----------------------------------------------------------------
        case 'delete':
            $choiceId = (int) ($_POST['choice_id'] ?? 0);
            $cSt = $pdo->prepare(
                'SELECT id FROM product_extra_choices
                  WHERE id = ? AND product_extra_id = ? LIMIT 1'
            );
            $cSt->execute([$choiceId, $extraId]);
            if (!$cSt->fetch()) throw new RuntimeException('Choice not found.');
            $pdo->prepare('DELETE FROM product_extra_choices WHERE id = ?')
                ->execute([$choiceId]);
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
