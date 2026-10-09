<?php
declare(strict_types=1);

/**
 * Retire the Owner, Agent and Readonly user roles (John, 2026-10-09). The
 * roles are now Admin, Office, Sales, Fitter (+ Factory on the factory
 * account). The three removed ones never granted or restricted anything —
 * only admin / sales / fitter are ever checked — so nobody's access changes:
 *   - their rows are removed from client_user_roles;
 *   - a user left with no role at all gets Office;
 *   - client_users.role (the "primary" role) is re-picked from what's left,
 *     highest first: admin, office, sales, fitter, factory.
 *
 * Idempotent, super-admin, web-runnable: /setup/migrations/migrate_retire_roles.php
 * Shows a dry-run listing first; ?run=1 applies it.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$retired  = ['owner', 'agent', 'readonly'];
$priority = ['admin', 'office', 'sales', 'fitter', 'factory'];
$in       = implode(',', array_fill(0, count($retired), '?'));

$hasJunction = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_user_roles' LIMIT 1"
)->fetchColumn();

// Everyone touched: a retired role in the junction OR as their primary role.
$sql = "SELECT u.id, u.client_id, u.full_name, u.role FROM client_users u WHERE u.role IN ($in)";
$args = $retired;
if ($hasJunction) {
    $sql .= " OR u.id IN (SELECT user_id FROM client_user_roles WHERE role IN ($in))";
    $args = array_merge($args, $retired);
}
$st = $pdo->prepare($sql . ' ORDER BY u.client_id, u.id');
$st->execute($args);
$users = $st->fetchAll(PDO::FETCH_ASSOC);

$apply = (isset($_GET['run']) && $_GET['run'] === '1') || PHP_SAPI === 'cli';
echo ($apply ? "APPLYING" : "DRY RUN (add ?run=1 to apply)") . ' — ' . count($users) . " user(s) affected.\n\n";

foreach ($users as $u) {
    $uid   = (int) $u['id'];
    $roles = [];
    if ($hasJunction) {
        $r = $pdo->prepare('SELECT role FROM client_user_roles WHERE user_id = ?');
        $r->execute([$uid]);
        $roles = $r->fetchAll(PDO::FETCH_COLUMN);
    }
    if (!$roles && (string) $u['role'] !== '') $roles = [(string) $u['role']];
    $keep = array_values(array_diff($roles, $retired));
    $addOffice = !$keep;
    if ($addOffice) $keep = ['office'];
    usort($keep, static fn ($a, $b)
        => (array_search($a, $priority, true) === false ? 99 : array_search($a, $priority, true))
       <=> (array_search($b, $priority, true) === false ? 99 : array_search($b, $priority, true)));
    $primary = $keep[0];

    printf("client %d  user %d  %-28s  [%s] -> [%s]  primary %s -> %s\n",
        (int) $u['client_id'], $uid, mb_substr((string) $u['full_name'], 0, 28),
        implode(', ', $roles), implode(', ', $keep), (string) $u['role'], $primary);

    if (!$apply) continue;
    $pdo->beginTransaction();
    try {
        if ($hasJunction) {
            $pdo->prepare("DELETE FROM client_user_roles WHERE user_id = ? AND role IN ($in)")
                ->execute(array_merge([$uid], $retired));
            if ($addOffice) {
                $pdo->prepare('INSERT INTO client_user_roles (user_id, role) VALUES (?, ?)')
                    ->execute([$uid, 'office']);
            }
        }
        $pdo->prepare('UPDATE client_users SET role = ? WHERE id = ?')->execute([$primary, $uid]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "   FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\nDone." . ($apply ? '' : ' Nothing changed (dry run).') . "\n";
