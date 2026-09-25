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
 * themselves this session (so "Add customer" can land on the edit page).
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

/** SQL fragment (for `customers c`) limiting to a restricted user's customers. */
function cm_mine_sql(): string
{
    return '(EXISTS (SELECT 1 FROM appointments a
                      WHERE a.customer_id = c.id AND a.client_id = c.client_id
                        AND a.client_user_id = ?)
          OR EXISTS (SELECT 1 FROM quotes q
                       JOIN appointments a ON a.quote_id = q.id
                      WHERE q.customer_id = c.id AND q.client_id = c.client_id
                        AND a.client_user_id = ?))';
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
        $st->execute([$customerId, (int) $user['client_id'], $uid, $uid]);
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

/** Remember a customer this user just added, so they can open it straight away. */
function cm_remember_created(int $customerId): void
{
    $list   = (array) ($_SESSION['cm_created'] ?? []);
    $list[] = $customerId;
    $_SESSION['cm_created'] = array_slice(array_values(array_unique($list)), -50);
}
