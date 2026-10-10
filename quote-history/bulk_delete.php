<?php
declare(strict_types=1);

/**
 * Bulk-delete quotes from /quote-history/index.php. POST-only.
 *
 * Body shape:
 *   csrf_token
 *   quote_ids[] = N, M, ...
 *
 * Tenant-scoped: the DELETE has WHERE client_id = ? so a crafted form
 * can't reach into another tenant's quotes even with a valid CSRF.
 * ON DELETE CASCADE on quote_items + quote_item_extras + appointments
 * handles the children.
 *
 * Redirects back to /quote-history/index.php with a flash message
 * stating how many were removed. Idempotent w.r.t. already-gone rows
 * (DELETE just returns 0 rowCount for them).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../quote-builder/_helpers.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];

// Deleting quotes/orders is a back-office action — gate on admin or
// quote-creation rights (a plain fitter must not be able to bulk-destroy
// orders).
//
// can_create_orders is admitted too, because quote-builder/delete.php:27 does:
// admin, or can_create_quotes, OR a direct order with can_create_orders. This
// file's comment used to claim it matched that file and didn't — a
// direct-order-only client (the #916 persona: Create orders, no Create quotes)
// could delete one of their draft direct orders from the order page but got a
// blank "Not permitted." from "Delete selected" on the very same rows.
//
// Which rows they may actually delete is enforced per row below, since
// can_create_orders alone must not reach an ordinary retail quote.
$perms = function_exists('current_user_permissions') ? current_user_permissions() : [];
$isAdminUser    = ($user['role'] ?? '') === 'admin';
$canAllQuotes   = $isAdminUser || !empty($perms['can_create_quotes']);
$canOrdersOnly  = !$canAllQuotes && !empty($perms['can_create_orders']);
if (!$canAllQuotes && !$canOrdersOnly) {
    http_response_code(403);
    exit('Not permitted.');
}

$ids = $_POST['quote_ids'] ?? [];
if (!is_array($ids)) $ids = [];
$ids = array_values(array_unique(array_filter(
    array_map('intval', $ids),
    static fn ($n) => $n > 0
)));

// Preserve the filter / search the user was on so the redirect lands
// them back on the same view — useful when they're cleaning up a
// "drafts only" list and want to keep filtering after the delete.
//
// This reads ALL the return_* fields the list sends. It used to take only
// status and q, so deleting from the Archived view or the Quotes scope
// dumped you back on the default active-orders list — while "Archive
// selected", posted from the SAME form, kept them. Keep this in step with
// orders/archive.php, which builds the identical URL.
$scope  = (($_POST['return_scope'] ?? '') === 'quotes') ? 'quotes' : 'orders';
$status = trim((string) ($_POST['return_status'] ?? ''));
$q      = trim((string) ($_POST['return_q'] ?? ''));
$view   = (($_POST['return_view'] ?? '') === 'archived') ? 'archived' : 'active';
$type   = in_array($_POST['return_type'] ?? '', ['retail', 'trade'], true)
        ? (string) $_POST['return_type'] : '';
// Redirects to the unified Order history page (the old quote-history
// URL is now just a 301 to /orders/).
$back   = '/orders/index.php?scope=' . urlencode($scope)
        . ($type   !== ''         ? '&type='   . rawurlencode($type)   : '')
        . ($status !== ''         ? '&status=' . urlencode($status)    : '')
        . ($q      !== ''         ? '&q='      . urlencode($q)         : '')
        . ($view   === 'archived' ? '&view=archived'                   : '');

if (!$ids) {
    $_SESSION['flash_error'] = 'No quotes selected.';
    header('Location: ' . $back);
    exit;
}

$pdo = db();
$ph  = implode(',', array_fill(0, count($ids), '?'));

// Refuse to silently bin quotes that have payment rows attached —
// payments.quote_id is ON DELETE SET NULL, so the rows would become
// orphans in /accounts (linked to no order). Surface them to the
// user so they can decide: delete the payments first, OR keep the
// quote for the audit trail.
//
// Defensive: if the payments table doesn't exist yet (migration not
// run), skip this check entirely. The DELETE below works either way.
$blocked = [];

// The same refusals the single Delete button and quote-builder/delete.php
// apply — qb_delete_block_reason(): the factory has already started it, it's
// a remake (theirs, not ours), or it's a placed direct order that has to be
// reopened as a draft first.
//
// Those rules landed on the single-delete path only (#927/#939). The payments
// guard below was copied the other way, from here into delete.php, which is
// why the mirroring reads as done and isn't: tick a live order in the Order
// history list and Delete and it goes, blinds on the factory floor and all.
// One ticked row must not be able to do what its own Delete button refuses.
$ruleBlocked = [];
// Also the set of posted ids that genuinely belong to this tenant. $ids comes
// from the form, and only the quotes DELETE is scoped by client_id — so the
// child cleanups need a verified id list of their own, or they'd be reaching
// into rows for quotes they aren't allowed to touch.
$ownedIds = [];
$chk = $pdo->prepare(
    "SELECT id, quote_number, status, direct_order, remake_of_quote_id
       FROM quotes WHERE id IN ($ph) AND client_id = ?"
);
$chk->execute(array_merge($ids, [$clientId]));
foreach ($chk->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $ownedIds[] = (int) $row['id'];
    // A can_create_orders-only user may delete their direct orders and nothing
    // else — the same line quote-builder/delete.php:28 draws.
    if ($canOrdersOnly && !qb_is_direct_order($row)) {
        $blocked[(int) $row['id']] = (string) $row['quote_number'];
        $ruleBlocked[(string) $row['quote_number']] = 'You can only delete your own direct orders.';
        continue;
    }
    $why = qb_delete_block_reason($pdo, $row);
    if ($why !== '') {
        $blocked[(int) $row['id']] = (string) $row['quote_number'];
        $ruleBlocked[(string) $row['quote_number']] = $why;
    }
}
try {
    $payStmt = $pdo->prepare(
        "SELECT q.id, q.quote_number, COUNT(p.id) AS n_payments
           FROM quotes q
           JOIN payments p ON p.quote_id = q.id
          WHERE q.id IN ($ph) AND q.client_id = ?
       GROUP BY q.id, q.quote_number"
    );
    $payStmt->execute(array_merge($ids, [$clientId]));
    foreach ($payStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $blocked[(int) $r['id']] = (string) $r['quote_number'];
    }
} catch (Throwable $e) {
    // payments table missing — skip the check, proceed with all.
}

// Deletable = posted, owned by this tenant, and not blocked. Intersecting with
// $ownedIds is what lets the child cleanups below drop their own client_id
// filter — see the supplier_orders note there for why they have to.
$deletable = array_values(array_intersect(
    array_diff(array_map('intval', $ids), array_keys($blocked)),
    $ownedIds
));

$deleted = 0;
if ($deletable) {
    $delPh  = implode(',', array_fill(0, count($deletable), '?'));
    // All three deletes are one unit of work. The children go first and the
    // quotes DELETE is the only one not wrapped in a try, so if it threw —
    // a lock timeout, a deadlock, an FK added later — the send-log rows and
    // the appointments were already gone while the quotes themselves
    // survived, and the fatal meant no redirect and no message either. The
    // order would then be sitting in the list with its fitting silently
    // missing from the calendar.
    $owned = !$pdo->inTransaction();
    if ($owned) $pdo->beginTransaction();
    try {
        // supplier_orders has no FK to quotes — clean its send-log rows so they
        // don't outlive the deleted quotes.
        //
        // Keyed on quote_id ONLY, deliberately. When the factory orders the
        // bought-in lines of a tenant's order, the log row is stamped with the
        // FACTORY's client_id, not the tenant's (factory_boughtin.php:185 passes
        // 'client_id' => $factoryId into supplier_send_group()). Filtering on the
        // signed-in tenant therefore missed exactly the rows this cleanup exists
        // for: the factory's send stayed behind pointing at a deleted quote, and
        // is still matched by factory_boughtin.php:37 and order-suppliers.php:155.
        // Safe without the filter because $deletable is now intersected with
        // $ownedIds, so every id here is one this tenant owns.
        try {
            $pdo->prepare(
                "DELETE FROM supplier_orders WHERE quote_id IN ($delPh)"
            )->execute($deletable);
        } catch (Throwable $e) { /* table absent — nothing to clean */ }
        // Remove the deleted orders' calendar appointments (e.g. pending fittings) so
        // they don't linger as phantoms. Scoped to the tenant: $deletable is the
        // posted id list, so without client_id another tenant's quote ids would
        // wipe THEIR appointments even though their quotes survive.
        try {
            $pdo->prepare("DELETE FROM appointments WHERE quote_id IN ($delPh) AND client_id = ?")
                ->execute(array_merge($deletable, [$clientId]));
        } catch (Throwable $e) { /* appointments table absent — nothing to clean */ }
        $stmt   = $pdo->prepare(
            "DELETE FROM quotes WHERE id IN ($delPh) AND client_id = ?"
        );
        $stmt->execute(array_merge($deletable, [$clientId]));
        $deleted = $stmt->rowCount();
        if ($owned) $pdo->commit();
    } catch (Throwable $e) {
        if ($owned && $pdo->inTransaction()) $pdo->rollBack();
        $deleted = 0;
        $_SESSION['flash_error'] = 'Nothing was deleted — the delete could not be completed. '
            . 'Everything has been left as it was. Try again, and if it keeps happening '
            . 'pass this on to whoever looks after your site.';
        header('Location: ' . $back);
        exit;
    }
}

$msgs = [];
if ($deleted > 0) {
    $msgs[] = ($deleted === 1 ? '1 quote' : "$deleted quotes") . ' deleted.';
}
// Two different reasons a row was kept, so say which. Lumping them together
// told someone whose order is on the factory floor to "delete the payments
// first", which isn't the problem and wouldn't help.
if ($ruleBlocked) {
    foreach ($ruleBlocked as $num => $why) {
        $msgs[] = $num . ': ' . $why;
    }
}
$payBlocked = array_diff($blocked, array_keys($ruleBlocked));
if ($payBlocked) {
    $list = implode(', ', $payBlocked);
    $msgs[] = (count($payBlocked) === 1
        ? '1 quote was kept because it has payment(s) recorded against it: '
        : count($payBlocked) . ' quotes were kept because they have payments recorded against them: ')
        . $list
        . '. Delete the payments first if you really want to remove these.';
}

if (!$msgs) {
    $_SESSION['flash_error'] = 'No quotes deleted.';
} elseif ($blocked) {
    // Use error styling so the "kept" message is conspicuous; the
    // success bit is included in the same line so it doesn't get lost.
    $_SESSION['flash_error'] = implode(' ', $msgs);
} else {
    $_SESSION['flash_success'] = implode(' ', $msgs);
}

header('Location: ' . $back);
exit;
