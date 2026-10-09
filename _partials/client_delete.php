<?php
declare(strict_types=1);

/**
 * Delete a business (client / trade account) completely — John 2026-10-09: "no
 * actual delete" on the trade account page.
 *
 * Deleting the clients row cascades through everything keyed to it with a foreign
 * key (users, settings, customers, products, its own quotes → lines → options …).
 * What does NOT cascade is the factory's side of the relationship, which is keyed
 * on account_client_id with no foreign key: the orders the factory raised for the
 * account, delivery notes, invoices, credit notes, payments, statements sent,
 * remakes, office-calendar callbacks, trade discounts/commissions/promotions, and
 * the floor jobs of the account's orders. Left behind those would be orphans that
 * still count in Wholesale totals and statement runs, so they go first, in one
 * transaction, then the client.
 *
 * Bank-feed lines are the bank's record and are kept; any that were matched to a
 * deleted payment become unmatched again.
 *
 * Returns ['ok' => bool, 'message' => string, 'counts' => [label => n]].
 */

function cl_delete_preview(PDO $pdo, int $clientId): array
{
    $n = static function (string $sql, array $args) use ($pdo): int {
        try { $s = $pdo->prepare($sql); $s->execute($args); return (int) $s->fetchColumn(); }
        catch (Throwable $e) { return 0; }
    };
    return [
        'orders and quotes' => $n('SELECT COUNT(*) FROM quotes WHERE client_id = ? OR account_client_id = ?', [$clientId, $clientId]),
        'invoices'          => $n('SELECT COUNT(*) FROM factory_ar_invoices WHERE account_client_id = ?', [$clientId]),
        'payments'          => $n('SELECT COUNT(*) FROM factory_ar_payments WHERE account_client_id = ?', [$clientId]),
        'delivery notes'    => $n('SELECT COUNT(*) FROM factory_ar_delivery_notes WHERE account_client_id = ?', [$clientId]),
        'logins'            => $n('SELECT COUNT(*) FROM client_users WHERE client_id = ?', [$clientId]),
    ];
}

/** Why this client can't be deleted, or '' when it can. */
function cl_delete_block_reason(PDO $pdo, int $clientId, int $myClientId): string
{
    if ($clientId <= 0) return 'No account specified.';
    if ($clientId === $myClientId) return 'You can’t delete the account you’re signed in as.';
    if (function_exists('is_factory_client') && is_factory_client($clientId)) return 'This is a factory account — it can’t be deleted from here.';
    $s = $pdo->prepare('SELECT COUNT(*) FROM client_users WHERE client_id = ? AND is_super_admin = 1');
    $s->execute([$clientId]);
    if ((int) $s->fetchColumn() > 0) return 'This account has a master admin login. Clear the super-admin flag on that login first.';
    return '';
}

function cl_delete_client(PDO $pdo, int $clientId): array
{
    $st = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
    $st->execute([$clientId]);
    $name = $st->fetchColumn();
    if ($name === false) return ['ok' => false, 'message' => 'Account not found.', 'counts' => []];
    $counts = cl_delete_preview($pdo, $clientId);

    // Run a statement; a missing table (feature never migrated) is fine, anything
    // else aborts the whole delete.
    $run = static function (string $sql, array $args = []) use ($pdo): void {
        try { $pdo->prepare($sql)->execute($args); }
        catch (PDOException $e) {
            if (($e->errorInfo[0] ?? '') === '42S02' || strpos($e->getMessage(), '1146') !== false) return;
            throw $e;
        }
    };
    $ids = static function (string $sql, array $args) use ($pdo): array {
        try { $s = $pdo->prepare($sql); $s->execute($args); return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN)); }
        catch (Throwable $e) { return []; }
    };
    $in = static fn (array $list): string => implode(',', array_map('intval', $list ?: [0]));

    $own = !$pdo->inTransaction();   // join a caller's transaction (e.g. a test that rolls back)
    if ($own) $pdo->beginTransaction();
    try {
        $a = [$clientId];
        $quoteIds = $ids('SELECT id FROM quotes WHERE client_id = ? OR account_client_id = ?', [$clientId, $clientId]);
        $q = $in($quoteIds);

        // Money: invoices, credit notes, payments (+ their lines / allocations).
        $inv = $in($ids('SELECT id FROM factory_ar_invoices WHERE account_client_id = ?', $a));
        $cns = $in($ids('SELECT id FROM factory_ar_credit_notes WHERE account_client_id = ?', $a));
        $pay = $in($ids('SELECT id FROM factory_ar_payments WHERE account_client_id = ?', $a));
        $dns = $in($ids('SELECT id FROM factory_ar_delivery_notes WHERE account_client_id = ? OR source_quote_id IN (' . $q . ')', $a));
        $run("UPDATE factory_bank_transactions SET payment_id = NULL WHERE payment_id IN ($pay)");
        $run("DELETE FROM factory_ar_payment_allocations WHERE payment_id IN ($pay) OR invoice_id IN ($inv)");
        $run("DELETE FROM factory_ar_credit_note_lines WHERE credit_note_id IN ($cns)");
        $run("DELETE FROM factory_ar_credit_notes WHERE id IN ($cns)");
        $run("DELETE FROM factory_ar_invoice_lines WHERE invoice_id IN ($inv)");
        $run("DELETE FROM factory_ar_invoice_orders WHERE invoice_id IN ($inv) OR quote_id IN ($q)");
        $run("DELETE FROM factory_ar_invoices WHERE id IN ($inv)");
        $run("DELETE FROM factory_ar_payments WHERE id IN ($pay)");
        $run("DELETE FROM factory_ar_delivery_note_lines WHERE delivery_note_id IN ($dns)");
        $run("DELETE FROM factory_ar_delivery_notes WHERE id IN ($dns)");
        foreach (['factory_ar_statement_emails', 'factory_ar_account_delivery', 'factory_bank_payer_aliases', 'factory_office_entries'] as $t) {
            $run("DELETE FROM $t WHERE account_client_id = ?", $a);
        }

        // Remakes on or for this account.
        $rm = $in($ids('SELECT id FROM factory_remakes WHERE account_client_id = ? OR source_quote_id IN (' . $q . ') OR remake_quote_id IN (' . $q . ')', $a));
        $run("DELETE FROM factory_remake_items WHERE remake_id IN ($rm)");
        $run("DELETE FROM factory_remakes WHERE id IN ($rm)");

        // The floor and suppliers for its orders.
        $bj = $in($ids("SELECT id FROM factory_blind_jobs WHERE quote_id IN ($q)", []));
        $run("DELETE FROM factory_blind_streams WHERE blind_job_id IN ($bj)");
        $run("DELETE FROM factory_scan_log WHERE quote_item_id IN (SELECT id FROM quote_items WHERE quote_id IN ($q))");
        $run("DELETE FROM factory_blind_jobs WHERE id IN ($bj)");
        $run("DELETE FROM factory_jobs WHERE quote_id IN ($q)");
        $run("DELETE FROM supplier_orders WHERE quote_id IN ($q)");
        $run("DELETE FROM appointments WHERE quote_id IN ($q)");
        $run("DELETE FROM payments WHERE quote_id IN ($q)");

        // Orders the FACTORY raised for this account (its own quotes cascade with it).
        $run('DELETE FROM quotes WHERE account_client_id = ? AND client_id <> ?', [$clientId, $clientId]);

        // Per-account trade terms.
        foreach (['trade_discounts', 'trade_discount_audit', 'trade_commissions', 'trade_promotions'] as $t) {
            $run("DELETE FROM $t WHERE client_id = ?", $a);
        }

        $pdo->prepare('DELETE FROM clients WHERE id = ?')->execute($a);
        if ($own) $pdo->commit();
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        error_log('cl_delete_client: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not delete “' . $name . '” — nothing was removed. (' . $e->getMessage() . ')', 'counts' => $counts];
    }
    return ['ok' => true, 'message' => '“' . $name . '” has been deleted, with everything held for it.', 'counts' => $counts];
}
