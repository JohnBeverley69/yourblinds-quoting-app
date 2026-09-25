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
