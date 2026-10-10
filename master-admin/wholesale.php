<?php
declare(strict_types=1);

/**
 * Master Admin · Wholesale (Layer B A/R hub).
 *
 * Beverley billing its trade accounts: placed orders that contain Beverley-owned
 * lines, and the documents raised against them. Phase 2B adds delivery notes;
 * invoices / credit notes / payments / statements follow.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';
require_once __DIR__ . '/../_partials/app_settings.php';

requireFactoryOffice();

$user    = current_user();
$pdo     = db();
$factory = ar_factory_id();

$dnReady  = ar_table_ready($pdo, 'factory_ar_delivery_notes');
$invReady = ar_table_ready($pdo, 'factory_ar_invoices');
$cnReady  = ar_table_ready($pdo, 'factory_ar_credit_notes');

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    // Resolve an order's trade account the same way discovery does: the account's
    // own quote (client_id = account) OR a Beverley "New order" quote tagged with
    // account_client_id. Loading + the account passed to doc creation both use it.
    $acctExpr = ar_account_expr($pdo, '');

    if ($action === 'dn_raise') {
        $qid = (int) ($_POST['quote_id'] ?? 0);
        try {
            if (!$dnReady) throw new RuntimeException('Run /setup/migrations/migrate_ar_delivery_notes.php first.');

            $q = $pdo->prepare(
                "SELECT id, quote_number, {$acctExpr} AS account_id FROM quotes
                  WHERE id = ? AND status IN ('ordered','fitted','invoiced','paid') AND {$acctExpr} <> ? LIMIT 1"
            );
            $q->execute([$qid, $factory]);
            $order = $q->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Order not found, not placed, or not a trade-account order.');

            $dn = ar_create_delivery_note($pdo, $factory, $qid, (int) $order['account_id'], (int) ($user['user_id'] ?? 0), false);
            $_SESSION['flash_success'] = 'Delivery note ' . $dn['number'] . ' created (draft). View/print it below, then mark it dispatched.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not raise delivery note: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'dn_dispatch' || $action === 'dn_cancel') {
        $dnId = (int) ($_POST['dn_id'] ?? 0);
        require_once __DIR__ . '/../_partials/order_stage.php';

        // Resolve the order behind this delivery note.
        $dnQid = 0;
        try {
            $dq = $pdo->prepare('SELECT source_quote_id FROM factory_ar_delivery_notes WHERE id = ? AND factory_client_id = ? LIMIT 1');
            $dq->execute([$dnId, $factory]);
            $dnQid = (int) $dq->fetchColumn();
        } catch (Throwable $e) { /* ignore */ }

        // Phase 1: can't dispatch a delivery note before the order is Ready.
        if ($action === 'dn_dispatch' && $dnQid > 0 && !os_is_ready($pdo, $dnQid, $factory)) {
            $_SESSION['flash_error'] = "Can't dispatch — the order isn't ready yet (every blind made and every bought-in item received).";
            header('Location: /master-admin/wholesale.php'); exit;
        }

        try {
            if ($action === 'dn_dispatch') {
                $pdo->prepare("UPDATE factory_ar_delivery_notes SET status = 'dispatched', dispatched_at = NOW() WHERE id = ? AND factory_client_id = ? AND status = 'draft'")
                    ->execute([$dnId, $factory]);
                // Phase 1: reflect the dispatch on the floor side too (one dispatch).
                os_set_factory_dispatched($pdo, $dnQid, (int) ($user['user_id'] ?? 0) ?: null);
                // Phase 2: auto-invoice on dispatch if the setting is on (default off).
                os_auto_invoice_on_dispatch($pdo, $dnQid, $factory, (int) ($user['user_id'] ?? 0) ?: null);
                $_SESSION['flash_success'] = 'Delivery note marked dispatched.';
            } else {
                // Read the delivery's grouping keys BEFORE cancelling, so the
                // charge can be re-worked for whatever is left in it.
                $dnGrp = null;
                try {
                    $g = $pdo->prepare(
                        'SELECT account_client_id, delivery_date, delivery_method
                           FROM factory_ar_delivery_notes
                          WHERE id = ? AND factory_client_id = ? LIMIT 1'
                    );
                    $g->execute([$dnId, $factory]);
                    $dnGrp = $g->fetch(PDO::FETCH_ASSOC) ?: null;
                } catch (Throwable $e) { /* delivery charges not migrated */ }

                $pdo->prepare("UPDATE factory_ar_delivery_notes SET status = 'cancelled' WHERE id = ? AND factory_client_id = ? AND status <> 'cancelled'")
                    ->execute([$dnId, $factory]);

                // dc_recalc_delivery parks the whole delivery charge on ONE bearer
                // note and zeroes the rest, and its docblock says it must run
                // "whenever a note joins, leaves or changes group". Cancelling is
                // leaving, and this branch never ran it — it only called
                // recompute_order_stage. Since dc_recalc_delivery and
                // dc_order_carriage both skip cancelled notes, cancelling the
                // bearer took the charge with it: the remaining order invoiced
                // with no carriage line and nobody was billed for the delivery.
                if ($dnGrp && function_exists('dc_recalc_delivery')) {
                    try {
                        dc_recalc_delivery(
                            $pdo, $factory,
                            (int) $dnGrp['account_client_id'],
                            (string) $dnGrp['delivery_date'],
                            (string) $dnGrp['delivery_method']
                        );
                    } catch (Throwable $e) { /* leave the charge as it stands */ }
                }
                $_SESSION['flash_success'] = 'Delivery note cancelled.';
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not update delivery note: ' . $e->getMessage();
        }
        if ($dnQid > 0) recompute_order_stage($pdo, $dnQid);
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'inv_raise') {
        $qid = (int) ($_POST['quote_id'] ?? 0);
        try {
            if (!$invReady) throw new RuntimeException('Run /setup/migrations/migrate_ar_invoices.php first.');

            $q = $pdo->prepare(
                "SELECT id, quote_number, {$acctExpr} AS account_id FROM quotes
                  WHERE id = ? AND status IN ('ordered','fitted','invoiced','paid') AND {$acctExpr} <> ? LIMIT 1"
            );
            $q->execute([$qid, $factory]);
            $order = $q->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Order not found, not placed, or not a trade-account order.');

            // Guard: don't double-invoice an order (a non-void invoice already covers it).
            if ($existingInv = ar_order_invoice_number($pdo, $qid)) {
                throw new RuntimeException('Order already invoiced on ' . $existingInv . ' (void it first to re-invoice).');
            }

            // Reissue of a voided invoice: must be a void invoice of THIS order.
            $reissueOf = (int) ($_POST['reissue_of'] ?? 0);
            $oldNum = '';
            if ($reissueOf > 0) {
                $ro = $pdo->prepare(
                    "SELECT i.inv_number FROM factory_ar_invoices i
                       JOIN factory_ar_invoice_orders io ON io.invoice_id = i.id
                      WHERE i.id = ? AND i.factory_client_id = ? AND i.status = 'void' AND io.quote_id = ? LIMIT 1"
                );
                $ro->execute([$reissueOf, $factory, $qid]);
                $oldNum = (string) ($ro->fetchColumn() ?: '');
                if ($oldNum === '') $reissueOf = 0;
            }

            $inv = ar_create_invoice($pdo, $factory, $qid, (int) $order['account_id'], (int) ($user['user_id'] ?? 0), false);
            $msg = 'Invoice ' . $inv['number'] . ' raised (£' . number_format($inv['total'], 2) . ').';
            if ($reissueOf > 0) {
                try {
                    $pdo->prepare('UPDATE factory_ar_invoices SET replaced_by_id = ? WHERE id = ? AND factory_client_id = ?')
                        ->execute([(int) $inv['id'], $reissueOf, $factory]);
                } catch (Throwable $e) { /* chain link is informational */ }
                $msg = 'Invoice ' . $inv['number'] . ' reissued in place of ' . $oldNum . ' (£' . number_format($inv['total'], 2) . ').';
                $onAcc = ar_unallocated_total($pdo, $factory, (int) $order['account_id']);
                if ($onAcc > 0.004) {
                    $msg .= ' The account has £' . number_format($onAcc, 2) . ' on account — use "Apply credit" on it to put that against the new invoice.';
                }
            }
            $_SESSION['flash_success'] = $msg . ' View/send it below.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not raise invoice: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    // One-step commit: dispatch the delivery note AND create + send the invoice in a
    // single action. Idempotent per order — a reprint or a duplicate delivery note
    // never re-invoices (the non-void-invoice guard below fires once). Used by the
    // "Print DN & invoice" button when auto-invoice mode is on.
    if ($action === 'deliver_invoice') {
        $qid = (int) ($_POST['quote_id'] ?? 0);
        // Phase 1: can't dispatch (and invoice) before the order is Ready.
        require_once __DIR__ . '/../_partials/order_stage.php';
        if ($qid > 0 && !os_is_ready($pdo, $qid, $factory)) {
            $_SESSION['flash_error'] = "Can't dispatch & invoice — the order isn't ready yet (every blind made and every bought-in item received).";
            header('Location: /master-admin/wholesale.php'); exit;
        }
        try {
            if (!$dnReady)  throw new RuntimeException('Run /setup/migrations/migrate_ar_delivery_notes.php first.');
            if (!$invReady) throw new RuntimeException('Run /setup/migrations/migrate_ar_invoices.php first.');

            $q = $pdo->prepare(
                "SELECT id, quote_number, {$acctExpr} AS account_id FROM quotes
                  WHERE id = ? AND status IN ('ordered','fitted','invoiced','paid') AND {$acctExpr} <> ? LIMIT 1"
            );
            $q->execute([$qid, $factory]);
            $order = $q->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Order not found, not placed, or not a trade-account order.');
            $accId = (int) $order['account_id'];

            $pdo->beginTransaction();

            // 1) Ensure a dispatched delivery note exists (reuse the newest live one).
            $ex = $pdo->prepare(
                "SELECT id, dn_number, status FROM factory_ar_delivery_notes
                  WHERE source_quote_id = ? AND factory_client_id = ? AND status <> 'cancelled'
               ORDER BY id DESC LIMIT 1"
            );
            $ex->execute([$qid, $factory]);
            $dnRow = $ex->fetch(PDO::FETCH_ASSOC);
            if ($dnRow) {
                $dnNum = (string) $dnRow['dn_number'];
                if ($dnRow['status'] === 'draft') {
                    $pdo->prepare("UPDATE factory_ar_delivery_notes SET status = 'dispatched', dispatched_at = NOW() WHERE id = ? AND factory_client_id = ?")
                        ->execute([(int) $dnRow['id'], $factory]);
                }
            } else {
                $dn    = ar_create_delivery_note($pdo, $factory, $qid, $accId, (int) ($user['user_id'] ?? 0), true);
                $dnNum = $dn['number'];
            }

            // 2) Create the invoice — once. If already invoiced (non-void), skip
            //    silently: this is the reprint / duplicate case the user called out.
            //    It is EMAILED after the commit below (never from inside the
            //    transaction, or a rollback would leave an emailed invoice that
            //    doesn't exist).
            $invNum = ar_order_invoice_number($pdo, $qid);
            $newInvId = 0;
            if ($invNum === '') {
                $inv      = ar_create_invoice($pdo, $factory, $qid, $accId, (int) ($user['user_id'] ?? 0), false);
                $invNum   = $inv['number'];
                $newInvId = (int) $inv['id'];
                $msg      = 'Delivery note ' . $dnNum . ' dispatched · invoice ' . $invNum . ' created (£' . number_format($inv['total'], 2) . ')';
            } else {
                $msg = 'Delivery note ' . $dnNum . ' dispatched. Order was already invoiced on ' . $invNum . ' — no second invoice raised.';
            }

            $pdo->commit();
            if ($newInvId > 0) {
                $sent = ar_send_invoice($pdo, $factory, $newInvId);
                if ($sent['ok']) {
                    $msg .= ' & emailed.';
                } else {
                    $msg .= '.';
                    $_SESSION['flash_error'] = $sent['message'] . ' It stays "Raised" until it is emailed.';
                }
            }
            // Phase 1: reflect dispatch on the floor side, then roll the stage up.
            try {
                require_once __DIR__ . '/../_partials/order_stage.php';
                os_set_factory_dispatched($pdo, $qid, (int) ($user['user_id'] ?? 0) ?: null);
                recompute_order_stage($pdo, $qid);
            } catch (Throwable $e) { /* non-fatal */ }
            $_SESSION['flash_success'] = $msg;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not print & invoice: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    // Flip the delivery-note flow mode (one-step auto-invoice vs two-step manual).
    if ($action === 'wh_mode') {
        $mode = (string) ($_POST['mode'] ?? '');
        $ok = app_setting_set('wholesale_dn_auto_invoice', $mode === 'two_step' ? '0' : '1');
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok
            ? ($mode === 'two_step'
                ? 'Switched to two-step: raise a delivery note, then invoice separately.'
                : 'Switched to one-step: printing a delivery note creates & sends the invoice automatically.')
            : "Couldn't save the setting — run /setup/migrations/migrate_app_settings.php (super-admin) and try again.";
        header('Location: /master-admin/wholesale.php'); exit;
    }

    // Auto-invoice-on-dispatch rule: when ON, dispatching an order (floor OR
    // delivery note) also raises & sends its invoice. Default OFF — dispatch just
    // marks the order ready to invoice.
    // Delivery-charge rules (net threshold + net charge, per method).
    if ($action === 'delivery_rules') {
        $in = (array) ($_POST['rules'] ?? []);
        $ok = dc_save_rules([
            'carrier' => ['under' => $in['carrier']['under'] ?? 0, 'charge' => $in['carrier']['charge'] ?? 0],
            'van'     => ['under' => $in['van']['under'] ?? 0,     'charge' => $in['van']['charge'] ?? 0],
        ]);
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok
            ? 'Delivery charges saved. They apply to deliveries raised from now on.'
            : "Couldn't save the delivery charges — run /setup/migrations/migrate_app_settings.php (super-admin) and try again.";
        header('Location: /master-admin/wholesale.php#delivery-charges'); exit;
    }

    if ($action === 'auto_dispatch_mode') {
        $on = (string) ($_POST['on'] ?? '') === '1';
        $ok = app_setting_set('auto_invoice_on_dispatch', $on ? '1' : '0');
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok
            ? ($on
                ? 'Auto-invoice ON: dispatching an order now raises & sends its invoice automatically.'
                : 'Auto-invoice OFF: dispatching an order marks it ready to invoice — you raise it when ready.')
            : "Couldn't save the setting — run /setup/migrations/migrate_app_settings.php (super-admin) and try again.";
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'inv_send' || $action === 'inv_void') {
        $invId = (int) ($_POST['inv_id'] ?? 0);
        try {
            if ($action === 'inv_send') {
                // Actually EMAIL the PDF to the account; only a successful email
                // marks it sent (no email on file / paused / mail error → stays put).
                $sent = ar_send_invoice($pdo, $factory, $invId);
                $_SESSION[$sent['ok'] ? 'flash_success' : 'flash_error'] = $sent['message'];
            } else {
                $reason = trim((string) ($_POST['void_reason'] ?? '')) ?: 'Voided';
                // Never mutate a sent invoice's figures — void keeps its number (gap-free).
                // ar_void_invoice also voids its credit notes (a credit against a
                // void invoice is one the account never earned) and RELEASES its
                // payment allocations, so money paid against it becomes credit on
                // account instead of vanishing with the void invoice.
                $v = ar_void_invoice($pdo, $factory, $invId, $reason);
                $cnVoided = (int) $v['cn_voided'];

                $_SESSION['flash_success'] = 'Invoice voided (its number is kept).'
                    . ($cnVoided > 0
                        ? ' Its ' . $cnVoided . ' credit note' . ($cnVoided === 1 ? ' was' : 's were') . ' voided with it.'
                        : '')
                    . ($v['released'] > 0.004
                        ? ' £' . number_format($v['released'], 2) . ' already paid against it is now credit on the account — reissue, then "Apply credit" to put it against the new invoice.'
                        : ' Reissue it from the order row if needed.');
            }
        } catch (Throwable $e) {
            // The void now spans two tables, so a half-done void must not stand.
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not update invoice: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'cn_raise') {
        // FULL credit of whatever is left on the invoice (the whole invoice, or the
        // remainder after earlier part-credits). Part credits: credit-note.php.
        // ar_create_credit_note refuses once the invoice is fully credited, so a
        // double press can't raise a second full-value credit note.
        $invId = (int) ($_POST['inv_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        try {
            if (!$cnReady) throw new RuntimeException('Run /setup/migrations/migrate_ar_credit_notes.php first.');
            $cn = ar_create_credit_note($pdo, $factory, $invId, [], $reason, 'credit', (int) ($user['user_id'] ?? 0));
            $_SESSION['flash_success'] = 'Credit note ' . $cn['number'] . ' raised (£' . number_format($cn['total'], 2) . '). Email it from the order row.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not raise credit note: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'cn_send') {
        $cnId = (int) ($_POST['cn_id'] ?? 0);
        $sent = ar_send_credit_note($pdo, $factory, $cnId);
        $_SESSION[$sent['ok'] ? 'flash_success' : 'flash_error'] = $sent['message'];
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'cn_void') {
        $cnId = (int) ($_POST['cn_id'] ?? 0);
        try {
            $g = $pdo->prepare('SELECT against_invoice_id FROM factory_ar_credit_notes WHERE id = ? AND factory_client_id = ? LIMIT 1');
            $g->execute([$cnId, $factory]);
            $againstId = (int) $g->fetchColumn();
            $pdo->prepare("UPDATE factory_ar_credit_notes SET status = 'void', voided_at = NOW() WHERE id = ? AND factory_client_id = ? AND status <> 'void'")
                ->execute([$cnId, $factory]);
            // The invoice it credited is open again (was settled by the credit).
            if ($againstId > 0) ar_recompute_invoice_paid($pdo, $againstId);
            $_SESSION['flash_success'] = 'Credit note voided.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not void credit note: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    header('Location: /master-admin/wholesale.php'); exit;
}

// ── Load ─────────────────────────────────────────────────────────────────────
$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$fAccount = (int) ($_GET['account'] ?? 0) ?: null;
$fFrom    = trim((string) ($_GET['from'] ?? ''));
$fTo      = trim((string) ($_GET['to'] ?? ''));

$accounts = ar_account_options($pdo, $factory);
$orders   = ar_placed_orders($pdo, $factory, $fAccount, $fFrom !== '' ? $fFrom : null, $fTo !== '' ? $fTo : null);

$notes = [];
if ($dnReady) {
    try {
        $ns = $pdo->prepare(
            "SELECT dn.*, c.company_name AS account_name, q.quote_number AS order_number
               FROM factory_ar_delivery_notes dn
               JOIN clients c  ON c.id = dn.account_client_id
          LEFT JOIN quotes q   ON q.id = dn.source_quote_id
              WHERE dn.factory_client_id = ?
           ORDER BY dn.id DESC LIMIT 100"
        );
        $ns->execute([$factory]);
        $notes = $ns->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* leave empty */ }
}

$invoices = [];
if ($invReady) {
    try {
        $is = $pdo->prepare(
            "SELECT i.*, c.company_name AS account_name,
                    io.quote_id AS order_quote_id, q.quote_number AS order_number
               FROM factory_ar_invoices i
               JOIN clients c ON c.id = i.account_client_id
          LEFT JOIN factory_ar_invoice_orders io ON io.invoice_id = i.id
          LEFT JOIN quotes q ON q.id = io.quote_id
              WHERE i.factory_client_id = ?
           ORDER BY i.id DESC LIMIT 100"
        );
        $is->execute([$factory]);
        $invoices = $is->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* leave empty */ }
}

$creditNotes = [];
if ($cnReady) {
    try {
        $cs = $pdo->prepare(
            "SELECT cn.*, c.company_name AS account_name, i.inv_number AS against_number
               FROM factory_ar_credit_notes cn
               JOIN clients c ON c.id = cn.account_client_id
          LEFT JOIN factory_ar_invoices i ON i.id = cn.against_invoice_id
              WHERE cn.factory_client_id = ?
           ORDER BY cn.id DESC LIMIT 100"
        );
        $cs->execute([$factory]);
        $creditNotes = $cs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* leave empty */ }
}

$money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
$fmtD  = static function (?string $dt): string {
    if (!$dt) return '&mdash;';
    $ts = strtotime($dt);
    return $ts ? date('j M Y', $ts) : '&mdash;';
};
$dnPill = static function (string $s): array {
    return $s === 'dispatched' ? ['Dispatched', '#065f46', '#d1fae5']
        : ($s === 'cancelled'  ? ['Cancelled', '#6b7280', '#e5e7eb']
        : ['Draft', '#92400e', '#fef3c7']);
};
$invPill = static function (string $s): array {
    return $s === 'paid'      ? ['Paid', '#065f46', '#d1fae5']
        : ($s === 'part_paid' ? ['Part paid', '#1e40af', '#dbeafe']
        : ($s === 'void'      ? ['Void', '#6b7280', '#e5e7eb']
        : ($s === 'sent'      ? ['Sent', '#1e40af', '#dbeafe']
        : ['Raised', '#92400e', '#fef3c7'])));
};

// ── Unify: index every document by the order it belongs to ───────────────────
$dnByOrder = [];
foreach ($notes as $dn) {
    $oid = (int) ($dn['source_quote_id'] ?? 0);
    if ($oid) $dnByOrder[$oid][] = $dn;
}
$invByOrder = [];
foreach ($invoices as $inv) {
    $oid = (int) ($inv['order_quote_id'] ?? 0);
    if ($oid) $invByOrder[$oid][] = $inv;
}
$cnByInvoice = [];
$creditedBy  = [];   // invoice id → Σ live credit-note totals
foreach ($creditNotes as $cn) {
    $iid = (int) ($cn['against_invoice_id'] ?? 0);
    if ($iid) {
        $cnByInvoice[$iid][] = $cn;
        if ($cn['status'] !== 'void') $creditedBy[$iid] = round(($creditedBy[$iid] ?? 0) + (float) $cn['total'], 2);
    }
}
/** Is this invoice fully credited (credit notes ≥ its total)? */
$fullyCredited = static function (array $iv) use ($creditedBy): bool {
    return ($creditedBy[(int) $iv['id']] ?? 0) >= round((float) $iv['total'], 2) - 0.005 && (float) $iv['total'] > 0.005;
};
/** Pill for an invoice, aware of credit notes (a fully credited invoice reads "Credited"). */
$invPillFor = static function (array $iv) use ($invPill, $fullyCredited, $creditedBy): array {
    if ($iv['status'] !== 'void' && $fullyCredited($iv)) return ['Credited', '#6b21a8', '#f3e8ff'];
    [$l, $f, $b] = $invPill((string) $iv['status']);
    if ($iv['status'] !== 'void' && ($creditedBy[(int) $iv['id']] ?? 0) > 0.005) $l .= ' · part credited';
    return [$l, $f, $b];
};
/** Lines still editable (draft/raised, never sent, nothing paid, no live credit)? */
$invEditable = static function (array $iv) use ($creditedBy): bool {
    return in_array($iv['status'], ['draft', 'raised'], true) && empty($iv['sent_at'])
        && (float) ($iv['amount_paid'] ?? 0) <= 0.004 && ($creditedBy[(int) $iv['id']] ?? 0) <= 0.005;
};

// TWO-step (print the note, raise the invoice yourself) is the default. One-step
// invoices off the back of printing, which does not survive how the office
// actually works: they do not always print every delivery note, and invoice
// numbers have to come out in sequence — so a part-printed batch would silently
// raise invoices and leave gaps. Switchable on this page once there is more
// confidence in it.
$autoInvoice = app_setting_get('wholesale_dn_auto_invoice', '0') === '1';
// Phase 2: auto-invoice when an order is dispatched (any path). Default OFF.
$autoInvoiceDispatch = app_setting_get('auto_invoice_on_dispatch', '0') === '1';

/**
 * The live (non-void) invoice covering an order, or null. Void invoices remain
 * visible in the expander but don't set the order's stage.
 */
$liveInvoiceFor = static function (int $qid) use ($invByOrder) {
    foreach ($invByOrder[$qid] ?? [] as $iv) if ($iv['status'] !== 'void') return $iv;
    return null;
};

/**
 * Derive an order's lifecycle stage from the documents raised against it.
 * Returns [key, label, textColour, bgColour] — drives the status pill + row tint.
 */
$orderStage = static function (int $qid) use ($dnByOrder, $liveInvoiceFor, $fullyCredited, $invByOrder): array {
    if ($iv = $liveInvoiceFor($qid)) {
        if ($fullyCredited($iv))      return ['credited', 'Credited', '#6b21a8', '#f3e8ff'];
        if ($iv['status'] === 'paid') return ['paid',     'Paid',     '#065f46', '#d1fae5'];
        if (in_array($iv['status'], ['sent', 'part_paid'], true) || !empty($iv['sent_at'])) {
            return ['invoiced', 'Invoiced', '#1e40af', '#dbeafe'];
        }
        return ['inv_raised', 'Invoiced (draft)', '#92400e', '#fef3c7'];
    }
    // Only void invoices → needs reissuing.
    if (!empty($invByOrder[$qid])) return ['inv_void', 'Invoice void', '#6b7280', '#e5e7eb'];
    $delivered = false; $draft = false;
    foreach ($dnByOrder[$qid] ?? [] as $dn) {
        if ($dn['status'] === 'dispatched') $delivered = true;
        elseif ($dn['status'] === 'draft')  $draft = true;
    }
    if ($delivered) return ['delivered', 'Delivered', '#0f766e', '#ccfbf1'];
    if ($draft)     return ['dn_draft',  'DN draft',  '#92400e', '#fef3c7'];
    return ['ordered', 'Ordered', '#3730a3', '#e0e7ff'];
};

$activeNav = 'wholesale';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wholesale &middot; Master admin</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .wh-filter { display:flex; gap:0.625rem; align-items:flex-end; flex-wrap:wrap; margin:0 0 0.75rem; }
        .wh-filter label { font-size:0.75rem; color:var(--text-faint); display:block; margin-bottom:0.15rem; }
        .wh-filter select, .wh-filter input { padding:0.4rem 0.55rem; border:1px solid var(--border-strong); border-radius:8px; font:inherit; background:var(--bg-input); }
        .wh-pill { display:inline-block; padding:0.05rem 0.5rem; font-size:0.7rem; font-weight:700; border-radius:999px; white-space:nowrap; }
        .wh-money { font-variant-numeric:tabular-nums; text-align:right; }
        .wh-muted { color:var(--text-faint); font-size:0.8125rem; }

        /* Mode toggle */
        .wh-mode { display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; margin:0 0 1rem; padding:0.6rem 0.8rem;
                   background:var(--bg-subtle,#f8fafc); border:1px solid var(--border); border-radius:10px; font-size:0.85rem; }
        .wh-seg { display:inline-flex; border:1px solid var(--border-strong); border-radius:999px; overflow:hidden; }
        .wh-seg button { border:0; background:transparent; padding:0.3rem 0.85rem; font:inherit; font-size:0.8rem; cursor:pointer; color:var(--text-faint); }
        .wh-seg button.on { background:var(--accent,#2563eb); color:#fff; font-weight:700; }

        /* Dense order grid (BM-style: one row per order) */
        .wh-orders { width:100%; border-collapse:collapse; font-size:0.83rem; }
        .wh-orders thead th { text-align:left; font-size:0.68rem; letter-spacing:0.04em; text-transform:uppercase;
                              color:var(--text-faint); font-weight:700; padding:0.4rem 0.55rem; border-bottom:2px solid var(--border-strong); white-space:nowrap; }
        .wh-orders tbody td { padding:0.4rem 0.55rem; border-bottom:1px solid var(--border); vertical-align:middle; }
        .wh-orders .wh-row > td { border-left:3px solid transparent; }
        .wh-orders .wh-row:hover > td { background:var(--bg-hover,#f1f5f9); }
        .wh-orders .wh-num { font-weight:700; white-space:nowrap; }
        .wh-orders .wh-caret { background:none; border:0; cursor:pointer; color:var(--text-faint); font-size:0.9rem; line-height:1; padding:0.1rem 0.25rem; transition:transform .12s; }
        .wh-orders .wh-caret[aria-expanded="true"] { transform:rotate(90deg); }
        .wh-filters input, .wh-filters select { width:100%; box-sizing:border-box; padding:0.28rem 0.4rem; font:inherit; font-size:0.78rem;
                              border:1px solid var(--border); border-radius:6px; background:var(--bg-input); }
        .wh-filters td { padding:0.3rem 0.4rem 0.55rem; border-bottom:2px solid var(--border-strong); }
        .wh-orders tr[hidden] { display:none; }
        .wh-detail > td { background:var(--bg-subtle,#f8fafc); padding:0.7rem 1rem 0.9rem 1.6rem; border-bottom:1px solid var(--border); }
        .wh-doc { display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; padding:0.28rem 0; font-size:0.82rem; }
        .wh-doc + .wh-doc { border-top:1px dashed var(--border); }
        .wh-doc .wh-dnum { font-weight:700; min-width:8.5rem; }
        .wh-act { background:none; border:0; padding:0; font:inherit; font-size:0.82rem; cursor:pointer; color:var(--link); text-decoration:underline; }
        .wh-act.danger { color:#b91c1c; }
        .wh-link { color:var(--link); font-size:0.82rem; text-decoration:underline; }
        .wh-primary { display:inline-flex; margin:0; }
        .wh-none { text-align:center; color:var(--text-faint); padding:1.2rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Wholesale</h1>
                <p class="page-subtitle">
                    <?php if (is_super_admin()): ?><a href="/master-admin/index.php">&larr; Master Admin</a><?php endif; ?>
                    &middot; billing your trade accounts for the orders they place &mdash; delivery notes, invoices and statements.
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>
        <?php if (!$dnReady): ?>
            <div class="alert alert-error" role="alert">
                The wholesale tables aren't set up yet — run
                <a href="/setup/migrations/migrate_ar_delivery_notes.php"><code>/setup/migrations/migrate_ar_delivery_notes.php</code></a> (super-admin), then reload.
            </div>
        <?php endif; ?>

        <!-- Delivery-note flow mode -->
        <form method="post" action="/master-admin/wholesale.php" class="wh-mode">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="wh_mode">
            <strong style="font-weight:700">Delivery-note flow:</strong>
            <span class="wh-seg">
                <button type="submit" name="mode" value="one_step" class="<?= $autoInvoice ? 'on' : '' ?>">One-step · print &amp; invoice</button>
                <button type="submit" name="mode" value="two_step" class="<?= $autoInvoice ? '' : 'on' ?>">Two-step · manual</button>
            </span>
            <span class="wh-muted" style="font-size:0.8rem">
                <?= $autoInvoice
                    ? 'Printing a delivery note dispatches it and creates &amp; sends the invoice automatically (once per order).'
                    : 'Raise a delivery note, then raise and send the invoice yourself.' ?>
            </span>
        </form>

        <!-- Auto-invoice on dispatch (any path) -->
        <form method="post" action="/master-admin/wholesale.php" class="wh-mode" style="margin-top:0.6rem">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="auto_dispatch_mode">
            <strong style="font-weight:700">Auto-invoice on dispatch:</strong>
            <span class="wh-seg">
                <button type="submit" name="on" value="1" class="<?= $autoInvoiceDispatch ? 'on' : '' ?>">On</button>
                <button type="submit" name="on" value="0" class="<?= $autoInvoiceDispatch ? '' : 'on' ?>">Off</button>
            </span>
            <span class="wh-muted" style="font-size:0.8rem">
                <?= $autoInvoiceDispatch
                    ? 'Dispatching an order (on the floor or via a delivery note) raises &amp; sends its invoice automatically, once per order.'
                    : 'Dispatching an order marks it ready to invoice — you raise the invoice yourself. Turn on once you\'re happy it prices correctly.' ?>
            </span>
        </form>

        <!-- Delivery charges: one rule per method, tested against a whole delivery -->
        <?php if (dc_ready($pdo)): $dcRules = dc_rules(); ?>
        <form method="post" action="/master-admin/wholesale.php" class="wh-mode" id="delivery-charges" style="margin-top:0.6rem;align-items:center">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="delivery_rules">
            <strong style="font-weight:700">Delivery charges:</strong>
            <?php foreach (['carrier' => 'Carrier', 'van' => 'Van'] as $mk => $ml): ?>
                <span style="display:inline-flex;align-items:center;gap:0.3rem;flex-wrap:wrap">
                    <?= e($ml) ?> £<input type="number" name="rules[<?= $mk ?>][charge]" min="0" step="0.01" style="width:5.5rem"
                           value="<?= e(number_format($dcRules[$mk]['charge'], 2, '.', '')) ?>" aria-label="<?= e($ml) ?> charge">
                    + VAT under £<input type="number" name="rules[<?= $mk ?>][under]" min="0" step="0.01" style="width:6.5rem"
                           value="<?= e(number_format($dcRules[$mk]['under'], 2, '.', '')) ?>" aria-label="<?= e($ml) ?> threshold">
                </span>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-secondary btn-sm">Save</button>
            <span class="wh-muted" style="font-size:0.8rem">
                Net values. Collected is always free. The threshold is the whole delivery &mdash; every order going out to
                an account on the same day by the same method. A charge of £0 switches it off. Set each account's method
                (or "No delivery charge") on its <a href="/master-admin/trade-accounts.php">account page</a>.
            </span>
        </form>
        <?php endif; ?>

        <!-- One order, one row: its whole lifecycle -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.4rem">Orders</h2>
            <p class="wh-muted" style="margin:0 0 0.75rem;max-width:78ch">
                Every trade-account order that contains your products, with the delivery note, invoice and any credit note
                raised against it. Open a row (&#9656;) for the documents and their actions.
            </p>

            <form method="get" action="/master-admin/wholesale.php" class="wh-filter">
                <div>
                    <label>Load account</label>
                    <select name="account">
                        <option value="">All accounts</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= $fAccount === (int) $a['id'] ? 'selected' : '' ?>><?= e((string) $a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>From</label><input type="date" name="from" value="<?= e($fFrom) ?>"></div>
                <div><label>To</label><input type="date" name="to" value="<?= e($fTo) ?>"></div>
                <button type="submit" class="btn btn-secondary btn-sm">Load</button>
                <?php if ($fAccount || $fFrom !== '' || $fTo !== ''): ?>
                    <a href="/master-admin/wholesale.php" class="wh-muted" style="margin-left:0.25rem">clear</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table class="wh-orders">
                    <thead>
                        <tr>
                            <th style="width:1.4rem"></th>
                            <th>Order</th><th>Account</th><th>Placed</th>
                            <th class="wh-money">Qty</th><th class="wh-money">Wholesale</th>
                            <th>Status</th><th style="text-align:right">Action</th>
                        </tr>
                        <tr class="wh-filters">
                            <td></td>
                            <td><input id="f-order" type="text" placeholder="Filter…" oninput="whFilter()"></td>
                            <td><input id="f-account" type="text" placeholder="Filter…" oninput="whFilter()"></td>
                            <td></td><td></td><td></td>
                            <td>
                                <select id="f-status" onchange="whFilter()">
                                    <option value="">All</option>
                                    <option value="ordered">Ordered</option>
                                    <option value="dn_draft">DN draft</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="inv_void">Invoice void</option>
                                    <option value="inv_raised">Invoiced (draft)</option>
                                    <option value="invoiced">Invoiced</option>
                                    <option value="paid">Paid</option>
                                    <option value="credited">Credited</option>
                                </select>
                            </td>
                            <td></td>
                        </tr>
                    </thead>
                    <tbody id="wh-body">
                        <?php if (!$orders): ?>
                            <tr><td colspan="8" class="wh-none">No placed trade-account orders<?= $fAccount || $fFrom !== '' || $fTo !== '' ? ' for this filter' : '' ?>.</td></tr>
                        <?php else: foreach ($orders as $o):
                            $qid   = (int) $o['id'];
                            $ordNo = (string) ($o['quote_number'] ?: ('#' . $qid));
                            [$sKey, $sLbl, $sFg, $sBg] = $orderStage($qid);

                            $dns = $dnByOrder[$qid] ?? [];
                            $ivs = $invByOrder[$qid] ?? [];
                            $cns = [];
                            foreach ($ivs as $iv) foreach ($cnByInvoice[(int) $iv['id']] ?? [] as $c) $cns[] = $c;

                            $liveInv = $liveInvoiceFor($qid);
                            $liveDn  = null;
                            foreach ($dns as $d) { if ($d['status'] !== 'cancelled') { $liveDn = $d; break; } }
                            $dnDispatched = false;
                            foreach ($dns as $d) { if ($d['status'] === 'dispatched') { $dnDispatched = true; break; } }
                            $dnDraftLive = ($liveDn && $liveDn['status'] === 'draft');
                            $hasDocs = $dns || $ivs;
                            // Newest void invoice (ivs are newest-first) — offered for reissue
                            // when the order has no live invoice.
                            $voidInv = null;
                            if (!$liveInv) { foreach ($ivs as $x) { if ($x['status'] === 'void') { $voidInv = $x; break; } } }
                        ?>
                            <tr class="wh-row" data-order="<?= e(strtolower($ordNo)) ?>" data-account="<?= e(strtolower((string) $o['account_name'])) ?>" data-status="<?= e($sKey) ?>">
                                <td style="border-left-color:<?= $sFg ?>">
                                    <button type="button" class="wh-caret" aria-expanded="false" aria-label="Show documents" onclick="whToggle(this)">&#9656;</button>
                                </td>
                                <td class="wh-num"><?= e($ordNo) ?></td>
                                <td><a href="/master-admin/account.php?id=<?= (int) $o['account_id'] ?>" title="Account overview"><?= e((string) $o['account_name']) ?></a></td>
                                <td style="white-space:nowrap"><?= $fmtD($o['created_at']) ?></td>
                                <td class="wh-money"><?= (int) $o['bev_qty'] ?></td>
                                <td class="wh-money"><?= $money($o['wholesale_total']) ?></td>
                                <td><span class="wh-pill" style="background:<?= $sBg ?>;color:<?= $sFg ?>"><?= e($sLbl) ?></span></td>
                                <td style="text-align:right;white-space:nowrap">
                                    <?php if ($voidInv && $invReady): /* voided, nothing live → reissue */ ?>
                                        <form method="post" action="/master-admin/wholesale.php" class="wh-primary">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="inv_raise">
                                            <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                            <input type="hidden" name="reissue_of" value="<?= (int) $voidInv['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">Reissue invoice</button>
                                        </form>
                                    <?php elseif ($autoInvoice): ?>
                                        <?php if (!$liveInv): ?>
                                            <form method="post" action="/master-admin/wholesale.php" class="wh-primary">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="deliver_invoice">
                                                <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                                <button type="submit" class="btn btn-primary btn-sm" <?= ($dnReady && $invReady) ? '' : 'disabled' ?>>Print DN &amp; invoice</button>
                                            </form>
                                        <?php else: ?>
                                            <a class="wh-link" href="/master-admin/invoice-pdf.php?id=<?= (int) $liveInv['id'] ?>" target="_blank">Invoice PDF</a>
                                        <?php endif; ?>
                                    <?php else: /* two-step */ ?>
                                        <?php if (!$liveInv && !$liveDn): ?>
                                            <form method="post" action="/master-admin/wholesale.php" class="wh-primary">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="dn_raise">
                                                <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" <?= $dnReady ? '' : 'disabled' ?>>Raise delivery note</button>
                                            </form>
                                        <?php elseif (!$liveInv && $dnDraftLive): ?>
                                            <form method="post" action="/master-admin/wholesale.php" class="wh-primary">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="dn_dispatch">
                                                <input type="hidden" name="dn_id" value="<?= (int) $liveDn['id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">Mark dispatched</button>
                                            </form>
                                        <?php elseif (!$liveInv && $dnDispatched): ?>
                                            <form method="post" action="/master-admin/wholesale.php" class="wh-primary">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="inv_raise">
                                                <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                                <button type="submit" class="btn btn-primary btn-sm" <?= $invReady ? '' : 'disabled' ?>>Raise invoice</button>
                                            </form>
                                        <?php elseif ($liveInv && $liveInv['status'] === 'raised'): ?>
                                            <form method="post" action="/master-admin/wholesale.php" class="wh-primary" data-confirm="Email invoice <?= e((string) $liveInv['inv_number']) ?> to <?= e((string) $o['account_name']) ?>?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="inv_send">
                                                <input type="hidden" name="inv_id" value="<?= (int) $liveInv['id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">Send (email)</button>
                                            </form>
                                        <?php elseif ($liveInv): ?>
                                            <a class="wh-link" href="/master-admin/invoice-pdf.php?id=<?= (int) $liveInv['id'] ?>" target="_blank">Invoice PDF</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="wh-detail" hidden>
                                <td colspan="8">
                                    <?php if (!$hasDocs): ?>
                                        <span class="wh-muted">No documents raised yet.</span>
                                    <?php else: ?>
                                        <?php foreach ($dns as $d): [$dl, $df, $db] = $dnPill((string) $d['status']); ?>
                                            <div class="wh-doc">
                                                <span class="wh-dnum"><?= e((string) $d['dn_number']) ?></span>
                                                <span class="wh-pill" style="background:<?= $db ?>;color:<?= $df ?>"><?= e($dl) ?></span>
                                                <span class="wh-muted"><?= $d['status'] === 'dispatched' ? $fmtD($d['dispatched_at']) : $fmtD($d['created_at']) ?></span>
                                                <a class="wh-link" href="/master-admin/delivery-note-pdf.php?id=<?= (int) $d['id'] ?>" target="_blank">View / print</a>
                                                <?php if ($d['status'] === 'draft'): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="dn_dispatch">
                                                        <input type="hidden" name="dn_id" value="<?= (int) $d['id'] ?>">
                                                        <button type="submit" class="wh-act">Mark dispatched</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($d['status'] !== 'cancelled'): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Cancel delivery note <?= e((string) $d['dn_number']) ?>?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="dn_cancel">
                                                        <input type="hidden" name="dn_id" value="<?= (int) $d['id'] ?>">
                                                        <button type="submit" class="wh-act danger">Cancel</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php foreach ($ivs as $iv):
                                            [$il, $if, $ib] = $invPillFor($iv);
                                            $ivVoid   = $iv['status'] === 'void';
                                            $ivLeft   = round((float) $iv['total'] - ($creditedBy[(int) $iv['id']] ?? 0), 2);
                                        ?>
                                            <div class="wh-doc">
                                                <span class="wh-dnum"><?= e((string) $iv['inv_number']) ?></span>
                                                <span class="wh-pill" style="background:<?= $ib ?>;color:<?= $if ?>"><?= e($il) ?></span>
                                                <span class="wh-money" style="min-width:5rem"><?= $money($iv['total']) ?></span>
                                                <a class="wh-link" href="/master-admin/invoice-pdf.php?id=<?= (int) $iv['id'] ?>" target="_blank">View / print</a>
                                                <?php if (!$ivVoid && $invEditable($iv)): ?>
                                                    <a class="wh-link" href="/master-admin/invoice-edit.php?id=<?= (int) $iv['id'] ?>">Edit lines / carriage</a>
                                                <?php endif; ?>
                                                <?php if (in_array($iv['status'], ['draft', 'raised'], true)): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Email invoice <?= e((string) $iv['inv_number']) ?> to <?= e((string) $o['account_name']) ?>?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="inv_send">
                                                        <input type="hidden" name="inv_id" value="<?= (int) $iv['id'] ?>">
                                                        <button type="submit" class="wh-act">Send (email)</button>
                                                    </form>
                                                <?php elseif (!$ivVoid): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Email invoice <?= e((string) $iv['inv_number']) ?> to <?= e((string) $o['account_name']) ?> again?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="inv_send">
                                                        <input type="hidden" name="inv_id" value="<?= (int) $iv['id'] ?>">
                                                        <button type="submit" class="wh-act">Email again</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if (!$ivVoid && $iv['status'] !== 'paid'): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Void invoice <?= e((string) $iv['inv_number']) ?>? Its number is kept; any payment on it becomes credit on the account. Reissue a fresh invoice to replace it.">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="inv_void">
                                                        <input type="hidden" name="inv_id" value="<?= (int) $iv['id'] ?>">
                                                        <button type="submit" class="wh-act danger">Void</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($cnReady && !$ivVoid && $ivLeft > 0.005): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Raise a credit note for <?= e((string) $iv['inv_number']) ?> for everything left on it (£<?= e(number_format($ivLeft, 2)) ?>)?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="cn_raise">
                                                        <input type="hidden" name="inv_id" value="<?= (int) $iv['id'] ?>">
                                                        <button type="submit" class="wh-act">Full credit</button>
                                                    </form>
                                                    <a class="wh-link" href="/master-admin/credit-note.php?inv_id=<?= (int) $iv['id'] ?>">Part credit&hellip;</a>
                                                <?php endif; ?>
                                                <?php if ($ivVoid && $voidInv && (int) $voidInv['id'] === (int) $iv['id']): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="inv_raise">
                                                        <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                                        <input type="hidden" name="reissue_of" value="<?= (int) $iv['id'] ?>">
                                                        <button type="submit" class="wh-act">Reissue</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php foreach ($cns as $c): $cvoid = $c['status'] === 'void'; $cRefund = ($c['settle_mode'] ?? 'credit') === 'refund'; ?>
                                            <div class="wh-doc">
                                                <span class="wh-dnum"><?= e((string) $c['cn_number']) ?></span>
                                                <span class="wh-pill" style="background:<?= $cvoid ? '#e5e7eb' : '#f3e8ff' ?>;color:<?= $cvoid ? '#6b7280' : '#6b21a8' ?>"><?= $cvoid ? 'Void' : ($cRefund ? 'Refund' : 'Credit') ?></span>
                                                <span class="wh-money" style="min-width:5rem">&minus;<?= $money($c['total']) ?></span>
                                                <a class="wh-link" href="/master-admin/credit-note-pdf.php?id=<?= (int) $c['id'] ?>" target="_blank">View / print</a>
                                                <?php if (!$cvoid): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Email credit note <?= e((string) $c['cn_number']) ?> to <?= e((string) $o['account_name']) ?>?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="cn_send">
                                                        <input type="hidden" name="cn_id" value="<?= (int) $c['id'] ?>">
                                                        <button type="submit" class="wh-act">Email</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if (!$cvoid): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Void credit note <?= e((string) $c['cn_number']) ?>?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="cn_void">
                                                        <input type="hidden" name="cn_id" value="<?= (int) $c['id'] ?>">
                                                        <button type="submit" class="wh-act danger">Void</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if ($dnReady): ?>
                                            <div class="wh-doc" style="border-top:1px dashed var(--border)">
                                                <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Raise another delivery note for order <?= e($ordNo) ?>? This does not create a second invoice.">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="_action" value="dn_raise">
                                                    <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                                    <button type="submit" class="wh-act">+ Duplicate delivery note</button>
                                                </form>
                                                <span class="wh-muted">(reprints don't re-invoice)</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
<script>
function whToggle(btn){
    var row = btn.closest('tr');
    var detail = row.nextElementSibling;
    if (!detail || !detail.classList.contains('wh-detail')) return;
    var open = detail.hidden;            // currently hidden → open it
    detail.hidden = !open;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
}
function whFilter(){
    var o = (document.getElementById('f-order').value || '').toLowerCase();
    var a = (document.getElementById('f-account').value || '').toLowerCase();
    var s = document.getElementById('f-status').value || '';
    document.querySelectorAll('#wh-body .wh-row').forEach(function(row){
        var show = (!o || (row.dataset.order || '').indexOf(o) >= 0)
                && (!a || (row.dataset.account || '').indexOf(a) >= 0)
                && (!s || row.dataset.status === s);
        row.hidden = !show;
        var detail = row.nextElementSibling;
        if (detail && detail.classList.contains('wh-detail') && !show) {
            detail.hidden = true;
            var caret = row.querySelector('.wh-caret');
            if (caret) caret.setAttribute('aria-expanded', 'false');
        }
    });
}
</script>
</body>
</html>
