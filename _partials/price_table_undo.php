<?php
declare(strict_types=1);

/**
 * Undo for price-table changes — Save grid, uploads/imports, "Adjust all
 * prices by %" (single table and Master Catalogue product/supplier).
 *
 * Before a change we snapshot every row (width, drop, price, cost) of the
 * tables it can touch, keyed by a SCOPE:
 *   'table:163'     one price table (the editor page)
 *   'system:12'     every band table on a system (band imports, cost import)
 *   'product:119'   every table on a product (width/rate imports, Master bump)
 *   'supplier:bev'  every table under a supplier prefix (Master bump)
 * When the request ends the snapshot is finalised with the set of tables that
 * exist afterwards and a hash of their rows; if nothing actually changed
 * (validation error, no-op) the snapshot is thrown away.
 *
 * Undo puts back EXACTLY the before rows — so added/removed cells, cost and
 * 2dp rounding all come back — and deletes any band tables the change
 * created. It only runs while those tables still hold exactly what the change
 * left behind, so it can never overwrite edits made afterwards. Undoing one
 * change leaves things as the previous change left them, so a scope's history
 * unwinds newest-first.
 *
 * Self-creating table, best-effort throughout: a history failure never
 * blocks the change itself.
 */

const PU_KEEP = 10;   // snapshots kept per scope

function pu_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    // DDL implicitly commits — only ever reached OUTSIDE a transaction.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS price_undo (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id   INT UNSIGNED NOT NULL,
            scope       VARCHAR(80)  NOT NULL,
            label       VARCHAR(200) NOT NULL,
            before_ids  MEDIUMTEXT   NOT NULL,
            rows_json   MEDIUMTEXT   NOT NULL,
            after_ids   MEDIUMTEXT   NULL,
            after_hash  CHAR(40)     NULL,
            created_by  INT UNSIGNED NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_scope (client_id, scope, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $done = true;
}

function pu_has_cost(PDO $pdo): bool
{
    static $has = null;
    if ($has === null) {
        try { $pdo->query('SELECT cost FROM price_table_rows LIMIT 0'); $has = true; }
        catch (Throwable $e) { $has = false; }
    }
    return $has;
}

function pu_ids(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    sort($ids);
    return $ids;
}

/** Every row of the given tables as [[table, w, d, price, cost|null], ...], stable order. */
function pu_rows(PDO $pdo, array $tableIds): array
{
    $tableIds = pu_ids($tableIds);
    if (!$tableIds) return [];
    $st = $pdo->query(
        'SELECT price_table_id, width_mm, drop_mm, price, '
        . (pu_has_cost($pdo) ? 'cost' : 'NULL') . '
           FROM price_table_rows
          WHERE price_table_id IN (' . implode(',', $tableIds) . ')
          ORDER BY price_table_id, width_mm, drop_mm'
    );
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$t, $w, $d, $p, $c]) {
        $out[] = [
            (int) $t, (int) $w, (int) $d,
            $p === null ? null : number_format((float) $p, 4, '.', ''),
            $c === null ? null : number_format((float) $c, 4, '.', ''),
        ];
    }
    return $out;
}

function pu_hash(array $ids, array $rows): string
{
    return sha1(json_encode([pu_ids($ids), $rows]));
}

/** Table ids for the common scopes (tenant-scoped). */
function pu_system_table_ids(PDO $pdo, int $clientId, int $systemId): array
{
    $st = $pdo->prepare('SELECT id FROM price_tables WHERE client_id = ? AND system_id = ?');
    $st->execute([$clientId, $systemId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function pu_product_table_ids(PDO $pdo, int $clientId, int $productId): array
{
    $st = $pdo->prepare('SELECT id FROM price_tables WHERE client_id = ? AND product_id = ?');
    $st->execute([$clientId, $productId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Pending snapshots of this request: id => [before ids, callable|null for after ids]. */
function &pu_pending(): array
{
    static $pending = [];
    return $pending;
}

/**
 * Snapshot before a change. $afterIds re-lists the scope's tables once the
 * change is done (for changes that can create tables); omit it when the set
 * of tables can't change. Call OUTSIDE any transaction. Returns the id, or 0.
 */
function pu_begin(PDO $pdo, int $clientId, string $scope, array $tableIds, string $label, ?callable $afterIds = null): int
{
    try {
        pu_ensure($pdo);
        $tableIds = pu_ids($tableIds);
        $userId = null;
        if (function_exists('current_user')) {
            $u = current_user();
            $userId = isset($u['id']) ? (int) $u['id'] : null;
        }
        $pdo->prepare(
            'INSERT INTO price_undo (client_id, scope, label, before_ids, rows_json, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $clientId, $scope, mb_substr($label, 0, 200),
            json_encode($tableIds), json_encode(pu_rows($pdo, $tableIds)), $userId,
        ]);
        $id = (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        return 0;
    }
    $pending = &pu_pending();
    if (!$pending) register_shutdown_function('pu_finish_all');   // safety net for exit()/redirects
    $pending[$id] = [$tableIds, $afterIds];
    return $id;
}

/**
 * Finalise this request's snapshots: record what the tables hold now, or
 * discard the snapshot if nothing changed. Idempotent — called before a page
 * renders its Undo bar and again (harmlessly) at shutdown.
 */
function pu_finish_all(): void
{
    $pending = &pu_pending();
    if (!$pending) return;
    $pdo = db();
    if ($pdo->inTransaction()) return;   // a change still in flight — try again at shutdown
    foreach ($pending as $id => [$beforeIds, $afterFn]) {
        unset($pending[$id]);
        try {
            $afterIds = pu_ids($afterFn ? (array) $afterFn() : $beforeIds);
            $afterRows = pu_rows($pdo, $afterIds);
            $st = $pdo->prepare('SELECT client_id, scope, rows_json FROM price_undo WHERE id = ?');
            $st->execute([$id]);
            $snap = $st->fetch(PDO::FETCH_ASSOC);
            if (!$snap) continue;
            $beforeRows = json_decode((string) $snap['rows_json'], true) ?: [];
            if ($afterIds === $beforeIds && $afterRows === $beforeRows) {
                $pdo->prepare('DELETE FROM price_undo WHERE id = ?')->execute([$id]);   // nothing changed
                continue;
            }
            $pdo->prepare('UPDATE price_undo SET after_ids = ?, after_hash = ? WHERE id = ?')
                ->execute([json_encode($afterIds), pu_hash($afterIds, $afterRows), $id]);

            // Prune the scope to the newest PU_KEEP.
            $old = $pdo->prepare(
                'SELECT id FROM price_undo WHERE client_id = ? AND scope = ?
                  ORDER BY id DESC LIMIT 1000 OFFSET ' . PU_KEEP
            );
            $old->execute([(int) $snap['client_id'], (string) $snap['scope']]);
            $ids = array_map('intval', $old->fetchAll(PDO::FETCH_COLUMN));
            if ($ids) $pdo->exec('DELETE FROM price_undo WHERE id IN (' . implode(',', $ids) . ')');
        } catch (Throwable $e) { /* best-effort */ }
    }
}

/** Newest undoable change for a scope, or null. 'stale' = edited since (undo refused). */
function pu_latest(PDO $pdo, int $clientId, string $scope, bool $checkStale = true): ?array
{
    pu_finish_all();
    try {
        $st = $pdo->prepare(
            'SELECT id, scope, label, after_ids, after_hash, created_at FROM price_undo
              WHERE client_id = ? AND scope = ? AND after_hash IS NOT NULL
              ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$clientId, $scope]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;   // table not created yet → nothing to undo
    }
    if (!$row) return null;
    $row['stale'] = false;
    if ($checkStale) {
        $ids = json_decode((string) $row['after_ids'], true) ?: [];
        $row['stale'] = $row['after_hash'] !== pu_hash($ids, pu_rows($pdo, $ids));
    }
    return $row;
}

/** Newest undoable change per scope for this client (no staleness check): scope => row. */
function pu_latest_all(PDO $pdo, int $clientId): array
{
    pu_finish_all();
    try {
        $st = $pdo->prepare(
            'SELECT u.id, u.scope, u.label, u.created_at
               FROM price_undo u
               JOIN (SELECT scope, MAX(id) AS id FROM price_undo
                      WHERE client_id = ? AND after_hash IS NOT NULL GROUP BY scope) m
                 ON m.id = u.id'
        );
        $st->execute([$clientId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(string) $r['scope']] = $r;
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Undo the newest change for a scope. Returns [true, label, cells] or
 * [false, message, 0]. If the tables were edited since, the scope's history
 * is discarded (it can never apply cleanly again) and the undo refused.
 */
function pu_undo(PDO $pdo, int $clientId, string $scope): array
{
    pu_finish_all();
    try {
        $st = $pdo->prepare(
            'SELECT * FROM price_undo
              WHERE client_id = ? AND scope = ? AND after_hash IS NOT NULL
              ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$clientId, $scope]);
        $snap = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $snap = false;
    }
    if (!$snap) return [false, 'Nothing to undo.', 0];

    $beforeIds = pu_ids(json_decode((string) $snap['before_ids'], true) ?: []);
    $afterIds  = pu_ids(json_decode((string) $snap['after_ids'], true) ?: []);
    if ($snap['after_hash'] !== pu_hash($afterIds, pu_rows($pdo, $afterIds))) {
        $pdo->prepare('DELETE FROM price_undo WHERE client_id = ? AND scope = ?')->execute([$clientId, $scope]);
        return [false, 'Can’t undo “' . $snap['label'] . '” — those prices have been changed again since. Change them back by hand.', 0];
    }
    $rows = json_decode((string) $snap['rows_json'], true);
    if (!is_array($rows)) return [false, 'Undo data is damaged — nothing changed.', 0];

    $allIds  = pu_ids(array_merge($beforeIds, $afterIds));
    // Second guard: the snapshot's table ids were written by the server from
    // tenant-scoped lookups, but only ever touch tables this tenant owns.
    if ($allIds) {
        $own = $pdo->prepare('SELECT id FROM price_tables WHERE client_id = ? AND id IN (' . implode(',', $allIds) . ')');
        $own->execute([$clientId]);
        $allIds = pu_ids($own->fetchAll(PDO::FETCH_COLUMN));
        $beforeIds = array_values(array_intersect($beforeIds, $allIds));
        $afterIds  = array_values(array_intersect($afterIds, $allIds));
        $ownSet = array_flip($allIds);
        $rows   = array_values(array_filter($rows, static fn ($r) => isset($ownSet[(int) ($r[0] ?? 0)])));
    }
    $created = array_values(array_diff($afterIds, $beforeIds));
    $hasCost = pu_has_cost($pdo);

    $pdo->beginTransaction();
    try {
        if ($allIds) {
            $pdo->exec('DELETE FROM price_table_rows WHERE price_table_id IN (' . implode(',', $allIds) . ')');
        }
        if ($created) {
            // Band tables the change created didn't exist before — remove them.
            $pdo->prepare('DELETE FROM price_tables WHERE client_id = ? AND id IN (' . implode(',', $created) . ')')
                ->execute([$clientId]);
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            $args = [];
            foreach ($chunk as [$t, $w, $d, $p, $c]) {
                array_push($args, $t, $w, $d, $p);
                if ($hasCost) $args[] = $c;
            }
            $pdo->prepare(
                'INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price' . ($hasCost ? ', cost' : '') . ') VALUES '
                . implode(',', array_fill(0, count($chunk), $hasCost ? '(?,?,?,?,?)' : '(?,?,?,?)'))
            )->execute($args);
        }
        if ($beforeIds) {
            $pdo->exec('UPDATE price_tables SET updated_at = NOW() WHERE id IN (' . implode(',', $beforeIds) . ')');
        }
        $pdo->prepare('DELETE FROM price_undo WHERE id = ?')->execute([(int) $snap['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, 'Undo failed: ' . $e->getMessage(), 0];
    }
    return [true, (string) $snap['label'], count($rows)];
}

/**
 * Render the Undo strip for a scope (nothing when there's nothing to undo).
 * Posts to /admin/products/price-undo.php, which comes back to $returnTo.
 */
function pu_render_bar(int $clientId, string $scope, string $returnTo, string $what = ''): void
{
    $u = pu_latest(db(), $clientId, $scope);
    if (!$u) return;
    $when = date('j M H:i', strtotime((string) $u['created_at']));
    $lbl  = (string) $u['label'] . ($what !== '' ? ' (' . $what . ')' : '');
    ?>
    <div class="alert" role="status" style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;background:var(--bg-subtle);border:1px solid var(--border);color:var(--text-secondary);padding:.5rem .75rem;margin:0 0 .875rem">
        <?php if ($u['stale']): ?>
            <span style="font-size:.875rem">
                Last change: <strong><?= e($lbl) ?></strong> &middot; <?= e($when) ?>
                &mdash; can’t be undone, the prices have been edited since.
            </span>
        <?php else: ?>
            <span style="font-size:.875rem">
                Last change: <strong><?= e($lbl) ?></strong> &middot; <?= e($when) ?>
            </span>
            <form method="post" action="/admin/products/price-undo.php" style="margin:0 0 0 auto"
                  onsubmit="return confirm(<?= e(json_encode('Undo “' . $lbl . '”? Every price it changed goes back to exactly what it was before (' . $when . '). Unsaved edits on this page will be lost.')) ?>);">
                <?= csrf_field() ?>
                <input type="hidden" name="scope" value="<?= e($scope) ?>">
                <input type="hidden" name="return" value="<?= e($returnTo) ?>">
                <button type="submit" class="btn btn-secondary btn-sm">&#8630; Undo <?= e((string) $u['label']) ?></button>
            </form>
        <?php endif; ?>
    </div>
    <?php
}
