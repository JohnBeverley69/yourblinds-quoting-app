<?php
declare(strict_types=1);

/**
 * Duplicate an entire option (product_extras) — clones the option row,
 * all its choices, and per-choice band scoping + width-table pricing,
 * plus the option's parent gating. The copy lands gated to the SAME
 * parent choice(s) as the source; the admin then re-points it via
 * extra-edit.php's "Appears when" picker (e.g. clone the 25mm tape-colour
 * set, then re-point the copy to 38mm).
 *
 * POST: id (extra id), product_id (for the redirect). CSRF + admin gated,
 * tenant-scoped via client_id.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /admin/products/index.php');
    exit;
}

csrf_check();

$user      = current_user();
$clientId  = (int) $user['client_id'];
$id        = (int) ($_POST['id']         ?? 0);
$productId = (int) ($_POST['product_id'] ?? 0);

$redirect = $productId > 0
    ? '/admin/products/extras.php?product_id=' . $productId
    : '/admin/products/index.php';

if ($id <= 0) {
    header('Location: ' . $redirect);
    exit;
}

$pdo = db();

// Load + tenant-verify the source option.
$src = $pdo->prepare('SELECT * FROM product_extras WHERE id = ? AND client_id = ? LIMIT 1');
$src->execute([$id, $clientId]);
$extra = $src->fetch(PDO::FETCH_ASSOC);
if (!$extra) {
    $_SESSION['flash_error'] = 'Option not found.';
    header('Location: ' . $redirect);
    exit;
}
$srcProductId = (int) $extra['product_id'];

// Generic "copy all columns except these" helper — copies optional
// schema columns automatically without hardcoding the column list.
$copyRow = static function (array $row, array $skip, array $override): array {
    $cols = [];
    $vals = [];
    foreach ($row as $col => $val) {
        if (isset($skip[$col])) continue;
        if (array_key_exists($col, $override)) $val = $override[$col];
        $cols[] = '`' . $col . '`';
        $vals[] = $val;
    }
    return [$cols, $vals];
};

$pdo->beginTransaction();
try {
    // Append to the end of the option list.
    $sortSt = $pdo->prepare(
        'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_extras
          WHERE product_id = ? AND client_id = ?'
    );
    $sortSt->execute([$srcProductId, $clientId]);
    $nextSort = (int) $sortSt->fetchColumn();

    $copyName = (string) $extra['name'] . ' (copy)';
    $copyName = function_exists('mb_substr')
        ? mb_substr($copyName, 0, 150)
        : substr($copyName, 0, 150);

    [$eCols, $eVals] = $copyRow(
        $extra,
        ['id' => true, 'updated_at' => true, 'created_at' => true],
        ['name' => $copyName, 'sort_order' => $nextSort]
    );
    $pdo->prepare(
        'INSERT INTO product_extras (' . implode(',', $eCols) . ') VALUES ('
        . implode(',', array_fill(0, count($eCols), '?')) . ')'
    )->execute($eVals);
    $newExtraId = (int) $pdo->lastInsertId();

    // Copy the option's parent gating (junction) — same parents as source.
    $pg = $pdo->prepare(
        'SELECT product_extra_choice_id FROM product_extra_parent_choices
          WHERE product_extra_id = ?'
    );
    $pg->execute([$id]);
    $parentIds = $pg->fetchAll(PDO::FETCH_COLUMN);
    if ($parentIds) {
        $pgIns = $pdo->prepare(
            'INSERT INTO product_extra_parent_choices
               (product_extra_id, product_extra_choice_id) VALUES (?, ?)'
        );
        foreach ($parentIds as $pcid) {
            $pgIns->execute([$newExtraId, (int) $pcid]);
        }
    }

    // Copy every choice, mapping old choice id → new choice id.
    $ch = $pdo->prepare(
        'SELECT * FROM product_extra_choices
          WHERE product_extra_id = ? ORDER BY sort_order, id'
    );
    $ch->execute([$id]);
    $idMap = [];
    foreach ($ch->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $oldCid = (int) $c['id'];
        [$cCols, $cVals] = $copyRow(
            $c,
            ['id' => true, 'updated_at' => true, 'created_at' => true],
            ['product_extra_id' => $newExtraId]
        );
        $pdo->prepare(
            'INSERT INTO product_extra_choices (' . implode(',', $cCols) . ') VALUES ('
            . implode(',', array_fill(0, count($cCols), '?')) . ')'
        )->execute($cVals);
        $idMap[$oldCid] = (int) $pdo->lastInsertId();
    }

    // Per-choice: copy band scoping + width-table pricing. Both wrapped
    // defensively — the band table is an optional migration.
    foreach ($idMap as $oldCid => $newCid) {
        try {
            $bs = $pdo->prepare(
                'SELECT band_code FROM product_extra_choice_bands WHERE choice_id = ?'
            );
            $bs->execute([$oldCid]);
            $bands = $bs->fetchAll(PDO::FETCH_COLUMN);
            if ($bands) {
                $bIns = $pdo->prepare(
                    'INSERT INTO product_extra_choice_bands (choice_id, band_code) VALUES (?, ?)'
                );
                foreach ($bands as $bc) {
                    $bIns->execute([$newCid, (string) $bc]);
                }
            }
        } catch (Throwable $e) { /* band table missing — skip */ }

        try {
            $ws = $pdo->prepare(
                'SELECT width_mm, price FROM extra_choice_price_rows
                  WHERE product_extra_choice_id = ?'
            );
            $ws->execute([$oldCid]);
            $rows = $ws->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $wIns = $pdo->prepare(
                    'INSERT INTO extra_choice_price_rows
                       (product_extra_choice_id, width_mm, price) VALUES (?, ?, ?)'
                );
                foreach ($rows as $w) {
                    $wIns->execute([$newCid, (int) $w['width_mm'], $w['price']]);
                }
            }
        } catch (Throwable $e) { /* skip */ }
    }

    $pdo->commit();

    require_once __DIR__ . '/../../_partials/catalogue_audit.php';
    catalogue_audit_log(
        'extra', $newExtraId, 'create', $copyName, null,
        ['duplicated_from' => $id, 'name' => $copyName],
        $srcProductId
    );

    $_SESSION['flash_success'] = 'Option duplicated as "' . $copyName
        . '". Open it to re-point its "Appears when" gating if needed.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('extra-duplicate failed (client ' . $clientId . ', extra ' . $id . '): ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not duplicate the option — please try again.';
}

header('Location: ' . $redirect);
exit;
