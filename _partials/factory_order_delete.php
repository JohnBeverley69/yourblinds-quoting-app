<?php
declare(strict_types=1);

/**
 * Deleting a factory order — the guard and the deletion sequence, in ONE place.
 *
 * factory/save-order.php's `del_order` grew this logic inline, and cancelling a
 * remake has to delete the same kind of order in exactly the same way. A second
 * copy would drift: the audit found that happening repeatedly, and the standing
 * rule is that a guard on one destructive path belongs on all of them.
 *
 * The order of the deletes matters and is NOT obvious — see the comments inside
 * fod_delete_order(). Anything that deletes a factory order should call this
 * rather than writing its own DELETEs.
 */

require_once __DIR__ . '/../quote-builder/_helpers.php';   // qb_factory_paperwork_tests
require_once __DIR__ . '/blind_jobs.php';                  // bj_tables_ready / bj_clear_order

/**
 * Why this order can't be deleted (a short clause, e.g. "it has been invoiced"),
 * or '' when it can be.
 *
 * The invoice and delivery-note tests come from qb_factory_paperwork_tests(), so
 * this and the tenant's own delete guard read one definition (#946, #958 were the
 * same tenant-vs-factory drift twice over). A VOIDED invoice does not block:
 * voiding is how an invoice raised in error is unpicked (#1028).
 *
 * Each lookup is guarded for installs where the AR tables were never migrated.
 */
function fod_delete_block_reason(PDO $pdo, int $quoteId): string
{
    if ($quoteId <= 0) return 'that order no longer exists';
    $tests = [];
    foreach (qb_factory_paperwork_tests() as $t) { $tests[$t['sql']] = $t['short']; }
    // Retail deposits taken against the order. Not part of the shared tests: the
    // tenant's own guard has no business refusing on money it took itself, and
    // `payments` has no voided_at — a retail payment is deleted, not voided.
    $tests['SELECT 1 FROM payments WHERE quote_id = ? LIMIT 1'] = 'payments are recorded against it';
    foreach ($tests as $sql => $why) {
        try {
            $chk = $pdo->prepare($sql);
            $chk->execute([$quoteId]);
            if ($chk->fetchColumn()) return $why;
        } catch (Throwable $e) { /* table not migrated on this install */ }
    }
    return '';
}

/**
 * Has any blind on this order actually been MADE — started or finished on the
 * floor? Returns a short clause ("a blind has already been made") or ''.
 *
 * Deliberately not the same question as qb_factory_has_received(), which is true
 * the moment an order is RELEASED to the floor. Releasing is reversible —
 * bj_clear_order() takes the jobs and streams away cleanly — so being released is
 * not a reason to refuse. A blind someone has physically worked on is.
 *
 * Used by the remake cancel, which must not throw away work already done at the
 * bench. Plain order delete does not call this: its long-standing behaviour is to
 * delete and clear the floor, and a part-made order deleted there still leaves the
 * remake in a sane state (back to 'requested').
 */
function fod_floor_progress_reason(PDO $pdo, int $quoteId): string
{
    if ($quoteId <= 0) return '';
    // factory_jobs: the order-level tracker.
    try {
        $s = $pdo->prepare('SELECT status FROM factory_jobs WHERE quote_id = ? LIMIT 1');
        $s->execute([$quoteId]);
        if (in_array((string) ($s->fetchColumn() ?: ''), ['in_production', 'made', 'dispatched'], true)) {
            return 'it has already been started on the floor';
        }
    } catch (Throwable $e) { /* not migrated */ }
    // Per-blind streams: queued is untouched, in_progress / done is real work.
    try {
        $s = $pdo->prepare(
            "SELECT 1 FROM factory_blind_streams s
               JOIN factory_blind_jobs j ON j.id = s.blind_job_id
              WHERE j.quote_id = ?
                AND (s.status <> 'queued' OR s.started_at IS NOT NULL OR s.completed_at IS NOT NULL)
              LIMIT 1"
        );
        $s->execute([$quoteId]);
        if ($s->fetchColumn()) return 'a blind on it has already been made';
    } catch (Throwable $e) { /* floor tables missing */ }
    // A scan is the hardest evidence of all: someone stood at a bench with it.
    try {
        $s = $pdo->prepare(
            'SELECT 1 FROM factory_scan_log WHERE quote_item_id IN (SELECT id FROM quote_items WHERE quote_id = ?) LIMIT 1'
        );
        $s->execute([$quoteId]);
        if ($s->fetchColumn()) return 'a blind on it has already been scanned on the floor';
    } catch (Throwable $e) { /* scan log not migrated */ }
    return '';
}

/**
 * Delete a factory order and everything keyed to it. Call fod_delete_block_reason()
 * FIRST — this does not re-check it.
 *
 * Returns ['supplier_sends' => [names…]]: suppliers the order had already been sent
 * to. Deleting it here cancels nothing at the supplier, and once the supplier_orders
 * row is gone no record is left that a send ever happened, so the caller must tell
 * the office. Noted before the row is deleted, which is why it comes back here.
 *
 * $resetRemake: when this order IS a remake, put its remake row back to 'requested'
 * with the decision cleared — the documented initial state (migrate_remakes.php:66),
 * so the office can approve or decline it again. The account's own side (the request,
 * reason, note, photo and blinds ticked) is untouched: deleting the order un-approves
 * the remake, it doesn't discard what the account reported.
 *
 * Pass FALSE when the caller is itself setting the remake's status — the remake
 * cancel sets 'cancelled', and letting this reset it to 'requested' would undo
 * exactly the thing the cancel is for.
 *
 * Runs in a transaction, joining the caller's if there is one.
 */
function fod_delete_order(PDO $pdo, int $quoteId, int $factory, bool $resetRemake = true): array
{
    $supplierSends = [];
    try {
        $ss = $pdo->prepare('SELECT DISTINCT supplier_name FROM supplier_orders WHERE quote_id = ?');
        $ss->execute([$quoteId]);
        $supplierSends = array_values(array_filter(array_map('strval', $ss->fetchAll(PDO::FETCH_COLUMN))));
    } catch (Throwable $e) { /* table absent */ }

    $own = $pdo->inTransaction() ? false : $pdo->beginTransaction();
    try {
        // The factory's own job rows for the order go with it. bj_clear_order()
        // rather than a raw DELETE on factory_blind_jobs: factory_blind_streams
        // is keyed on blind_job_id, so deleting the jobs first orphans every
        // stream row for the order — invisible, because floor.php and scan.php
        // reach streams through jobs. The helper deletes the streams first and
        // is already the thing set-status.php uses for the same job.
        if (bj_tables_ready($pdo)) bj_clear_order($pdo, $quoteId);
        try { $pdo->prepare('DELETE FROM factory_jobs WHERE quote_id = ?')->execute([$quoteId]); }
        catch (Throwable $e) { /* not migrated */ }
        // Scan-log rows key on quote_item_id, which is about to go, leaving them
        // permanently unmatchable in the log.
        try {
            $pdo->prepare('DELETE FROM factory_scan_log WHERE quote_item_id IN (SELECT id FROM quote_items WHERE quote_id = ?)')
                ->execute([$quoteId]);
        } catch (Throwable $e) { /* scan log not migrated */ }
        // The supplier send-log has no FK to quotes, so its rows outlive the
        // order pointing at nothing. Not scoped by client_id on purpose: a
        // factory send is stamped with the FACTORY's client_id, which is the
        // bug #976 fixed on the tenant-side delete paths.
        try { $pdo->prepare('DELETE FROM supplier_orders WHERE quote_id = ?')->execute([$quoteId]); }
        catch (Throwable $e) { /* table absent */ }
        $pdo->prepare('DELETE FROM quote_item_extras WHERE quote_item_id IN (SELECT id FROM quote_items WHERE quote_id = ?)')->execute([$quoteId]);
        $pdo->prepare('DELETE FROM quote_items WHERE quote_id = ?')->execute([$quoteId]);
        // Remove the order's calendar appointments (e.g. the pending fitting) so
        // deleting the order doesn't leave phantom fittings on the account's calendar.
        $pdo->prepare('DELETE FROM appointments WHERE quote_id = ?')->execute([$quoteId]);
        // If this order IS a remake, put its remake back in the waiting queue
        // rather than stranding it. factory_remakes has no FK to quotes and the
        // only code that ever deleted from it is the whole-account delete, so
        // deleting a remake order left the row at status='approved' with
        // remake_quote_id pointing at a dead id. rm_list('open') LEFT JOINs that
        // quote, so COALESCE(rq.fulfilment_stage,'') <> 'dispatched' stayed true
        // for ever: a permanent In-progress row reading "—", counted by the
        // console tile, and unreachable — rm_approve() only accepts 'requested',
        // so it could never be approved or declined again either.
        if ($resetRemake) {
            try {
                $pdo->prepare(
                    "UPDATE factory_remakes
                        SET status = 'requested', remake_quote_id = NULL,
                            charge_mode = NULL, charge_amount = 0, supplier_name = NULL,
                            due_date = NULL, decided_by_user_id = NULL, decided_at = NULL
                      WHERE remake_quote_id = ? AND factory_client_id = ?"
                )->execute([$quoteId, $factory]);
            } catch (Throwable $e) { /* remakes not migrated — nothing to put back */ }
        }
        $pdo->prepare('DELETE FROM quotes WHERE id = ?')->execute([$quoteId]);
        if ($own) $pdo->commit();
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['supplier_sends' => $supplierSends];
}
