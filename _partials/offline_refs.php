<?php
declare(strict_types=1);

/**
 * Offline outbox resend guard, kept in the database.
 *
 * Something saved on a tablet with no signal (a blind, a new quote, a quote
 * email) carries a one-off client_ref. If the signal drops after the server has
 * done it but before the tablet hears back, the tablet sends it again. The PHP
 * session used to be the only record of refs already done, so a resend after the
 * session expired (tablet signed in again) did it twice. Each ref is now claimed
 * in offline_refs: UNIQUE (client_id, kind, ref), so a second claim fails, even
 * from two requests racing.
 *
 * Kinds: 'item' (add_item.php), 'quote' (new.php), 'email' (email_pdf.php).
 * Table from setup/migrations/migrate_offline_refs.php. Until it exists these
 * helpers do nothing and the session check stays the only guard.
 */

/** The quote/target id an earlier request recorded for this ref, or null if the ref is new. */
function oref_seen(PDO $pdo, int $clientId, string $kind, string $ref): ?int
{
    if ($ref === '') return null;
    try {
        $st = $pdo->prepare('SELECT COALESCE(target_id, 0) FROM offline_refs WHERE client_id = ? AND kind = ? AND ref = ? LIMIT 1');
        $st->execute([$clientId, $kind, $ref]);
        $v = $st->fetchColumn();
        return $v === false ? null : (int) $v;
    } catch (PDOException $e) {
        return null;   // table not migrated yet
    }
}

/**
 * Claim a ref. True = first time (go ahead); false = already claimed (a resend:
 * don't do it again). Call it inside the same transaction as the work, so a
 * rollback releases the claim.
 */
function oref_claim(PDO $pdo, int $clientId, string $kind, string $ref, ?int $targetId = null): bool
{
    if ($ref === '') return true;
    try {
        $pdo->prepare('INSERT INTO offline_refs (client_id, kind, ref, target_id, created_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute([$clientId, $kind, $ref, $targetId]);
        if (random_int(1, 200) === 1) {
            // Resends come within minutes; a quarter of a year is plenty.
            $pdo->exec('DELETE FROM offline_refs WHERE created_at < NOW() - INTERVAL 90 DAY');
        }
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') return false;   // duplicate key: already done
        if ($e->getCode() === '42S02') return true;    // table not migrated yet
        throw $e;
    }
}

/** Give a claim back, e.g. when the email it guarded failed to send, so a retry can go. */
function oref_release(PDO $pdo, int $clientId, string $kind, string $ref): void
{
    if ($ref === '') return;
    try {
        $pdo->prepare('DELETE FROM offline_refs WHERE client_id = ? AND kind = ? AND ref = ?')
            ->execute([$clientId, $kind, $ref]);
    } catch (PDOException $e) {
        // table not migrated yet
    }
}
