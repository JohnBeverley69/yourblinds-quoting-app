<?php
declare(strict_types=1);

/**
 * Who may open / change a customer record — one rule for the customer list,
 * the edit page and delete, so they can't drift apart.
 *
 * Admins and users with can_view_all_customer_jobs see every customer in the
 * tenant. A restricted user (typical fitter) only reaches customers they're
 * working for: one of their assigned appointments is for that customer
 * (directly, or via the customer's quote), or they added the customer
 * themselves.
 *
 * Tenant scoping is separate — callers still filter customers by client_id.
 */

if (function_exists('cm_can_view_all_customers')) {
    return;
}

function cm_can_view_all_customers(array $user): bool
{
    if (($user['role'] ?? '') === 'admin') return true;
    return !empty(current_user_permissions()['can_view_all_customer_jobs']);
}

/**
 * Is customers.created_by_user_id there? (setup/migrations/migrate_customer_created_by.php)
 * Without it, "I added this one" is remembered only in the session, which is
 * what it used to be — so an unmigrated install keeps the old behaviour rather
 * than erroring.
 */
function cm_created_col_ready(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        db()->query('SELECT created_by_user_id FROM customers LIMIT 0');
        $ok = true;
    } catch (Throwable $e) { $ok = false; }
    return $ok;
}

/**
 * SQL fragment (for `customers c`) limiting to a restricted user's customers.
 * Takes two or three `?` depending on the column above, so build the values
 * with cm_mine_params() rather than by hand.
 */
function cm_mine_sql(): string
{
    $mine = '(EXISTS (SELECT 1 FROM appointments a
                       WHERE a.customer_id = c.id AND a.client_id = c.client_id
                         AND a.client_user_id = ?)
           OR EXISTS (SELECT 1 FROM quotes q
                        JOIN appointments a ON a.quote_id = q.id
                       WHERE q.customer_id = c.id AND q.client_id = c.client_id
                         AND a.client_user_id = ?)';
    if (cm_created_col_ready()) {
        $mine .= '
           OR c.created_by_user_id = ?';
    }
    return $mine . ')';
}

/** The values cm_mine_sql() wants, in order. */
function cm_mine_params(int $userId): array
{
    return cm_created_col_ready() ? [$userId, $userId, $userId] : [$userId, $userId];
}

function cm_user_can_access_customer(int $customerId, array $user): bool
{
    if (cm_can_view_all_customers($user)) return true;
    if (in_array($customerId, (array) ($_SESSION['cm_created'] ?? []), true)) return true;
    $uid = (int) ($user['user_id'] ?? 0);
    try {
        $st = db()->prepare(
            'SELECT 1 FROM customers c WHERE c.id = ? AND c.client_id = ? AND ' . cm_mine_sql() . ' LIMIT 1'
        );
        $st->execute(array_merge([$customerId, (int) $user['client_id']], cm_mine_params($uid)));
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/** 404 (not 403 — don't confirm the customer exists) if out of bounds. */
function cm_require_customer_access(int $customerId, array $user): void
{
    if (!cm_user_can_access_customer($customerId, $user)) {
        http_response_code(404);
        exit('Customer not found.');
    }
}

/**
 * Record a customer this user just added, so they can open it straight away
 * and still find it after logging out. Called from every creation path:
 * customer-manager/new.php, calendar/new.php and the quote builder's
 * auto-create in quote-builder/_helpers.php.
 *
 * The session list stays as the fallback for an unmigrated install, and as the
 * answer while this request is still inside its own transaction.
 */
function cm_remember_created(int $customerId): void
{
    $list   = (array) ($_SESSION['cm_created'] ?? []);
    $list[] = $customerId;
    $_SESSION['cm_created'] = array_slice(array_values(array_unique($list)), -50);

    if ($customerId <= 0 || !cm_created_col_ready()) return;
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) return;
    try {
        db()->prepare('UPDATE customers SET created_by_user_id = ?
                        WHERE id = ? AND created_by_user_id IS NULL')
            ->execute([$uid, $customerId]);
    } catch (Throwable $e) { /* never block creating a customer */ }
}
