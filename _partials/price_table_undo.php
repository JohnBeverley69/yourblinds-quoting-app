<?php
declare(strict_types=1);

/**
 * Undo for "Adjust all prices by %" on the price-table editor.
 *
 * A bump rounds every cell to 2dp, so bumping back by the opposite % does
 * NOT restore the original prices. Instead, before each bump we snapshot the
 * table's prices (width, drop, price) and, after it commits, a hash of the
 * resulting prices. Undo restores the newest snapshot — but only while the
 * table still holds exactly the prices that bump produced, so it can never
 * silently overwrite edits made afterwards. Undoing one bump leaves the table
 * matching the previous bump's result, so several bumps unwind in order.
 *
 * Only price is touched on restore; cost and the set of cells are left alone.
 * The table self-creates (no migration needed); everything is best-effort so
 * a missing/broken history never blocks the bump itself.
 */

const PTU_KEEP = 10;   // snapshots kept per price table

function ptu_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    // DDL implicitly commits — only ever call this OUTSIDE a transaction.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS price_table_undo (
            id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id      INT UNSIGNED NOT NULL,
            price_table_id INT UNSIGNED NOT NULL,
            label          VARCHAR(120) NOT NULL,
            prices_json    MEDIUMTEXT   NOT NULL,
            after_hash     CHAR(40)     NULL,
            created_by     INT UNSIGNED NULL,
            created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_table (price_table_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $done = true;
}

/** Current prices as [[w, d, price], ...] in a stable order. */
function ptu_prices(PDO $pdo, int $tableId): array
{
    $st = $pdo->prepare(
        'SELECT width_mm, drop_mm, price FROM price_table_rows
          WHERE price_table_id = ? ORDER BY width_mm, drop_mm'
    );
    $st->execute([$tableId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$w, $d, $p]) {
        $out[] = [(int) $w, (int) $d, number_format((float) $p, 4, '.', '')];
    }
    return $out;
}

function ptu_hash(array $prices): string
{
    return sha1(json_encode($prices));
}

/** Snapshot before a bump. Returns the snapshot id, or 0 if it couldn't be taken. */
function ptu_before(PDO $pdo, int $clientId, int $tableId, string $label): int
{
    try {
        ptu_ensure($pdo);
        $userId = null;
        if (function_exists('current_user')) {
            $u = current_user();
            $userId = isset($u['id']) ? (int) $u['id'] : null;
        }
        $pdo->prepare(
            'INSERT INTO price_table_undo (client_id, price_table_id, label, prices_json, created_by)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$clientId, $tableId, mb_substr($label, 0, 120), json_encode(ptu_prices($pdo, $tableId)), $userId]);
        $id = (int) $pdo->lastInsertId();

        // Prune to the newest PTU_KEEP for this table.
        $old = $pdo->prepare(
            'SELECT id FROM price_table_undo WHERE price_table_id = ? ORDER BY id DESC LIMIT 1000 OFFSET ' . PTU_KEEP
        );
        $old->execute([$tableId]);
        $ids = array_map('intval', $old->fetchAll(PDO::FETCH_COLUMN));
        if ($ids) {
            $pdo->exec('DELETE FROM price_table_undo WHERE id IN (' . implode(',', $ids) . ')');
        }
        return $id;
    } catch (Throwable $e) {
        return 0;
    }
}

/** After the bump commits: record what the prices became. Drops the snapshot on failure. */
function ptu_after(PDO $pdo, int $snapId, int $tableId, bool $ok): void
{
    if ($snapId <= 0) return;
    try {
        if ($ok) {
            $pdo->prepare('UPDATE price_table_undo SET after_hash = ? WHERE id = ?')
                ->execute([ptu_hash(ptu_prices($pdo, $tableId)), $snapId]);
        } else {
            $pdo->prepare('DELETE FROM price_table_undo WHERE id = ?')->execute([$snapId]);
        }
    } catch (Throwable $e) { /* best-effort */ }
}

/**
 * The newest undoable bump for this table, or null. 'stale' = true when the
 * prices have been changed since (undo would clobber those edits → refused).
 */
function ptu_latest(PDO $pdo, int $clientId, int $tableId): ?array
{
    try {
        $st = $pdo->prepare(
            'SELECT id, label, after_hash, created_at FROM price_table_undo
              WHERE price_table_id = ? AND client_id = ? AND after_hash IS NOT NULL
              ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$tableId, $clientId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;   // table not created yet → nothing to undo
    }
    if (!$row) return null;
    $row['stale'] = $row['after_hash'] !== ptu_hash(ptu_prices($pdo, $tableId));
    return $row;
}

/**
 * Restore the newest bump's snapshot. Returns [true, label] or [false, message].
 */
function ptu_undo(PDO $pdo, int $clientId, int $tableId): array
{
    $latest = ptu_latest($pdo, $clientId, $tableId);
    if (!$latest) return [false, 'Nothing to undo.'];
    if ($latest['stale']) {
        return [false, 'Can’t undo “' . $latest['label'] . '” — the prices have been edited since. Change them back by hand.'];
    }
    $st = $pdo->prepare('SELECT prices_json FROM price_table_undo WHERE id = ?');
    $st->execute([(int) $latest['id']]);
    $prices = json_decode((string) $st->fetchColumn(), true);
    if (!is_array($prices)) return [false, 'Undo data is damaged — nothing changed.'];

    $pdo->beginTransaction();
    try {
        $up = $pdo->prepare(
            'UPDATE price_table_rows SET price = ? WHERE price_table_id = ? AND width_mm = ? AND drop_mm = ?'
        );
        foreach ($prices as [$w, $d, $p]) {
            $up->execute([$p, $tableId, (int) $w, (int) $d]);
        }
        $pdo->prepare('UPDATE price_tables SET updated_at = NOW() WHERE id = ?')->execute([$tableId]);
        $pdo->prepare('DELETE FROM price_table_undo WHERE id = ?')->execute([(int) $latest['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, 'Undo failed: ' . $e->getMessage()];
    }
    return [true, (string) $latest['label']];
}

// ===========================================================================
// Multi-table undo — the Master Catalogue's "Apply %" per product and per
// supplier bumps every price table under that product/supplier in one go.
// Same idea as above, keyed by a scope string ('product:12', 'supplier:bev')
// and covering the set of tables that existed at bump time. Staleness is
// checked when Undo is pressed (hashing a whole supplier on every page load
// would be wasteful); a stale scope's snapshots are dropped so its button
// goes away.
// ===========================================================================

const PBU_KEEP = 5;   // snapshots kept per scope (a supplier snapshot can be large)

function pbu_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    // DDL implicitly commits — only ever call this OUTSIDE a transaction.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS price_bump_undo (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id   INT UNSIGNED NOT NULL,
            scope       VARCHAR(80)  NOT NULL,
            label       VARCHAR(200) NOT NULL,
            table_ids   MEDIUMTEXT   NOT NULL,
            prices_json MEDIUMTEXT   NOT NULL,
            after_hash  CHAR(40)     NULL,
            created_by  INT UNSIGNED NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_scope (client_id, scope, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $done = true;
}

/** Prices across a set of tables as [[table, w, d, price], ...], stable order. */
function pbu_prices(PDO $pdo, array $tableIds): array
{
    $tableIds = array_values(array_filter(array_map('intval', $tableIds)));
    if (!$tableIds) return [];
    $st = $pdo->query(
        'SELECT price_table_id, width_mm, drop_mm, price FROM price_table_rows
          WHERE price_table_id IN (' . implode(',', $tableIds) . ')
          ORDER BY price_table_id, width_mm, drop_mm'
    );
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$t, $w, $d, $p]) {
        $out[] = [(int) $t, (int) $w, (int) $d, number_format((float) $p, 4, '.', '')];
    }
    return $out;
}

/** Snapshot before a multi-table bump. Returns the snapshot id, or 0. */
function pbu_before(PDO $pdo, int $clientId, string $scope, array $tableIds, string $label): int
{
    try {
        pbu_ensure($pdo);
        $tableIds = array_values(array_unique(array_map('intval', $tableIds)));
        $userId = null;
        if (function_exists('current_user')) {
            $u = current_user();
            $userId = isset($u['id']) ? (int) $u['id'] : null;
        }
        $pdo->prepare(
            'INSERT INTO price_bump_undo (client_id, scope, label, table_ids, prices_json, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $clientId, $scope, mb_substr($label, 0, 200), json_encode($tableIds),
            json_encode(pbu_prices($pdo, $tableIds)), $userId,
        ]);
        $id = (int) $pdo->lastInsertId();

        $old = $pdo->prepare(
            'SELECT id FROM price_bump_undo WHERE client_id = ? AND scope = ?
              ORDER BY id DESC LIMIT 1000 OFFSET ' . PBU_KEEP
        );
        $old->execute([$clientId, $scope]);
        $ids = array_map('intval', $old->fetchAll(PDO::FETCH_COLUMN));
        if ($ids) {
            $pdo->exec('DELETE FROM price_bump_undo WHERE id IN (' . implode(',', $ids) . ')');
        }
        return $id;
    } catch (Throwable $e) {
        return 0;
    }
}

/** After the bump: record what the prices became, or drop the snapshot on failure. */
function pbu_after(PDO $pdo, int $snapId, array $tableIds, bool $ok): void
{
    if ($snapId <= 0) return;
    try {
        if ($ok) {
            $pdo->prepare('UPDATE price_bump_undo SET after_hash = ? WHERE id = ?')
                ->execute([ptu_hash(pbu_prices($pdo, $tableIds)), $snapId]);
        } else {
            $pdo->prepare('DELETE FROM price_bump_undo WHERE id = ?')->execute([$snapId]);
        }
    } catch (Throwable $e) { /* best-effort */ }
}

/** Newest undoable bump per scope for this client: scope => [id, scope, label, created_at]. */
function pbu_latest_all(PDO $pdo, int $clientId): array
{
    try {
        $st = $pdo->prepare(
            'SELECT u.id, u.scope, u.label, u.created_at
               FROM price_bump_undo u
               JOIN (SELECT scope, MAX(id) AS id FROM price_bump_undo
                      WHERE client_id = ? AND after_hash IS NOT NULL GROUP BY scope) m
                 ON m.id = u.id'
        );
        $st->execute([$clientId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(string) $r['scope']] = $r;
        return $out;
    } catch (Throwable $e) {
        return [];   // table not created yet
    }
}

/**
 * Restore the newest bump for a scope. Returns [true, label, cells] or
 * [false, message, 0]. If prices were edited since, the scope's snapshots are
 * discarded (they can never apply cleanly again) and the undo is refused.
 */
function pbu_undo(PDO $pdo, int $clientId, string $scope): array
{
    try {
        $st = $pdo->prepare(
            'SELECT id, label, table_ids, prices_json, after_hash FROM price_bump_undo
              WHERE client_id = ? AND scope = ? AND after_hash IS NOT NULL
              ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$clientId, $scope]);
        $snap = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $snap = false;
    }
    if (!$snap) return [false, 'Nothing to undo.', 0];

    $tableIds = json_decode((string) $snap['table_ids'], true) ?: [];
    if ($snap['after_hash'] !== ptu_hash(pbu_prices($pdo, $tableIds))) {
        $pdo->prepare('DELETE FROM price_bump_undo WHERE client_id = ? AND scope = ?')->execute([$clientId, $scope]);
        return [false, 'Can’t undo “' . $snap['label'] . '” — some of those prices have been changed since. Change them back by hand.', 0];
    }
    $prices = json_decode((string) $snap['prices_json'], true);
    if (!is_array($prices)) return [false, 'Undo data is damaged — nothing changed.', 0];

    // A supplier can be tens of thousands of cells — load the snapshot into a
    // temp table and restore with one joined UPDATE rather than row by row.
    // (CREATE TEMPORARY TABLE does not implicitly commit.)
    $pdo->exec('DROP TEMPORARY TABLE IF EXISTS tmp_pbu_restore');
    $pdo->exec(
        'CREATE TEMPORARY TABLE tmp_pbu_restore (
            t INT UNSIGNED NOT NULL, w INT NOT NULL, d INT NOT NULL, p DECIMAL(14,4) NOT NULL,
            PRIMARY KEY (t, w, d)
        ) ENGINE=InnoDB'
    );
    $pdo->beginTransaction();
    try {
        foreach (array_chunk($prices, 500) as $chunk) {
            $pdo->prepare(
                'INSERT INTO tmp_pbu_restore (t, w, d, p) VALUES '
                . implode(',', array_fill(0, count($chunk), '(?,?,?,?)'))
            )->execute(array_merge(...$chunk));
        }
        $pdo->exec(
            'UPDATE price_table_rows r
               JOIN tmp_pbu_restore x
                 ON x.t = r.price_table_id AND x.w = r.width_mm AND x.d = r.drop_mm
                SET r.price = x.p'
        );
        if ($tableIds) {
            $pdo->exec('UPDATE price_tables SET updated_at = NOW() WHERE id IN ('
                . implode(',', array_map('intval', $tableIds)) . ')');
        }
        $pdo->prepare('DELETE FROM price_bump_undo WHERE id = ?')->execute([(int) $snap['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->exec('DROP TEMPORARY TABLE IF EXISTS tmp_pbu_restore');
        return [false, 'Undo failed: ' . $e->getMessage(), 0];
    }
    $pdo->exec('DROP TEMPORARY TABLE IF EXISTS tmp_pbu_restore');
    return [true, (string) $snap['label'], count($prices)];
}
