<?php
declare(strict_types=1);

/**
 * Migration — record who added a customer.
 *
 * Adds to customers:
 *   created_by_user_id INT NULL   -- client_users.id of whoever created the row
 *   + index (client_id, created_by_user_id)
 *
 * Why: a restricted user (typical fitter / salesperson without
 * can_view_all_customer_jobs) could only reach a customer they had added for
 * the rest of that LOGIN — _partials/customer_access.php remembered new ids in
 * $_SESSION['cm_created'] because there was nothing on the row to ask. Log out
 * and the customer they had just typed in vanished from their own list, and
 * the quote-builder's auto-created customers were never reachable at all.
 *
 * Nothing is backfilled: existing rows keep created_by_user_id NULL, which
 * means "nobody in particular" and grants no one extra access. Admins and
 * can_view_all_customer_jobs users see every customer either way.
 *
 * Super-admin only; safe to run more than once.
 */

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/auth/middleware.php';

requireLogin();
if (!function_exists('is_super_admin') || !is_super_admin()) {
    http_response_code(403);
    exit('Super-admin only.');
}

require_run_confirmation();

$pdo = db();
$ops = [];

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $st->execute([$table, $col]);
    return (int) $st->fetchColumn() > 0;
};

$idxExists = static function (string $table, string $idx) use ($pdo): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
    );
    $st->execute([$table, $idx]);
    return (int) $st->fetchColumn() > 0;
};

try {
    if (!$colExists('customers', 'created_by_user_id')) {
        $pdo->exec('ALTER TABLE customers ADD COLUMN created_by_user_id INT NULL');
        $ops[] = 'Added customers.created_by_user_id.';
    } else {
        $ops[] = 'customers.created_by_user_id already exists — skipped.';
    }

    if (!$idxExists('customers', 'idx_customers_client_creator')) {
        $pdo->exec('ALTER TABLE customers
                      ADD INDEX idx_customers_client_creator (client_id, created_by_user_id)');
        $ops[] = 'Added index idx_customers_client_creator.';
    } else {
        $ops[] = 'Index idx_customers_client_creator already exists — skipped.';
    }
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Migration FAILED: " . $e->getMessage() . "\n\nDone so far:\n - " . implode("\n - ", $ops);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "Customer created-by migration complete.\n\n - " . implode("\n - ", $ops) . "\n";
