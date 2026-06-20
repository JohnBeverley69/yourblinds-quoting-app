<?php
declare(strict_types=1);

/**
 * Fabric Library grouping endpoint — create groups, file fabrics into them,
 * rename/delete groups, reorder. Non-destructive: only touches
 * library_fabric_categories and library_fabrics.category_id.
 *
 * Groups are scoped to a MANUFACTURER (fabric_supplier_id), so each
 * manufacturer has its own set. A fabric can only join a group that belongs
 * to its own manufacturer.
 *
 * POST actions:
 *   create          — fabric_supplier_id, name [, fabric_id]  → new group
 *   assign          — fabric_id, category_id (0 = ungroup)    → file a fabric
 *   rename          — category_id, name                       → rename a group
 *   delete          — category_id        → remove group; its fabrics ungroup
 *   reorder_groups  — fabric_supplier_id, order[]             → set sort_order
 *
 * Super-admin only (the library lives on the master tenant), CSRF-checked.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /master-admin/fabric-library.php');
    exit;
}
csrf_check();

$pdo    = db();
$action = (string) ($_POST['_action'] ?? 'assign');

$tableOk = true;
try { $pdo->query('SELECT 1 FROM library_fabric_categories LIMIT 0'); }
catch (Throwable $e) { $tableOk = false; }

try {
    if (!$tableOk) {
        $_SESSION['flash_error'] = 'Fabric grouping isn\'t set up yet — run /migrate_fabric_library_categories.php first.';
        header('Location: /master-admin/fabric-library.php');
        exit;
    }

    if ($action === 'create') {
        $sid  = (int) ($_POST['fabric_supplier_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($sid <= 0 || $name === '') {
            $_SESSION['flash_error'] = 'Give the group a name.';
        } else {
            $dup = $pdo->prepare('SELECT id FROM library_fabric_categories WHERE fabric_supplier_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
            $dup->execute([$sid, $name]);
            if ($dup->fetchColumn() !== false) {
                $_SESSION['flash_error'] = 'This manufacturer already has a group called “' . $name . '”.';
            } else {
                $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM library_fabric_categories WHERE fabric_supplier_id = ?');
                $sortStmt->execute([$sid]);
                $nextSort = (int) $sortStmt->fetchColumn();
                $pdo->prepare('INSERT INTO library_fabric_categories (fabric_supplier_id, name, sort_order) VALUES (?, ?, ?)')
                    ->execute([$sid, $name, $nextSort]);
                $newId = (int) $pdo->lastInsertId();
                // File a fabric straight in, if one was named.
                $fid = (int) ($_POST['fabric_id'] ?? 0);
                if ($fid > 0) {
                    $pdo->prepare('UPDATE library_fabrics SET category_id = ? WHERE id = ? AND fabric_supplier_id = ?')
                        ->execute([$newId, $fid, $sid]);
                }
                $_SESSION['flash_success'] = 'Group “' . $name . '” created.';
            }
        }
    } elseif ($action === 'rename') {
        $catId = (int) ($_POST['category_id'] ?? 0);
        $name  = trim((string) ($_POST['name'] ?? ''));
        if ($catId > 0 && $name !== '') {
            // Block a rename that collides with another group in the same manufacturer.
            $clash = $pdo->prepare(
                'SELECT 1 FROM library_fabric_categories c
                   JOIN library_fabric_categories t ON t.fabric_supplier_id = c.fabric_supplier_id
                  WHERE c.id = ? AND t.id <> c.id AND LOWER(t.name) = LOWER(?) LIMIT 1'
            );
            $clash->execute([$catId, $name]);
            if ($clash->fetchColumn() !== false) {
                $_SESSION['flash_error'] = 'That manufacturer already has a group with that name.';
            } else {
                $pdo->prepare('UPDATE library_fabric_categories SET name = ? WHERE id = ?')->execute([$name, $catId]);
                $_SESSION['flash_success'] = 'Group renamed.';
            }
        } else {
            $_SESSION['flash_error'] = 'A group needs a name.';
        }
    } elseif ($action === 'delete') {
        $catId = (int) ($_POST['category_id'] ?? 0);
        if ($catId > 0) {
            // Ungroup its fabrics first, then drop the group.
            $pdo->prepare('UPDATE library_fabrics SET category_id = NULL WHERE category_id = ?')->execute([$catId]);
            $pdo->prepare('DELETE FROM library_fabric_categories WHERE id = ?')->execute([$catId]);
            $_SESSION['flash_success'] = 'Group removed. Its fabrics are now ungrouped.';
        }
    } elseif ($action === 'reorder_groups') {
        $sid   = (int) ($_POST['fabric_supplier_id'] ?? 0);
        $order = $_POST['order'] ?? [];
        if (!is_array($order)) $order = [];
        // Only reorder groups that belong to the named manufacturer.
        $upd = $pdo->prepare('UPDATE library_fabric_categories SET sort_order = ? WHERE id = ? AND fabric_supplier_id = ?');
        $i = 0;
        foreach ($order as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) { $upd->execute([$i, $cid, $sid]); $i++; }
        }
        $_SESSION['flash_success'] = 'Group order updated.';
    } else { // assign
        $fid   = (int) ($_POST['fabric_id']   ?? 0);
        $catId = (int) ($_POST['category_id'] ?? 0);   // 0 = ungroup
        if ($fid <= 0) throw new RuntimeException('Missing fabric.');

        if ($catId > 0) {
            // The group must belong to the SAME manufacturer as the fabric.
            $chk = $pdo->prepare(
                'SELECT 1
                   FROM library_fabric_categories c
                   JOIN library_fabrics f ON f.fabric_supplier_id = c.fabric_supplier_id
                  WHERE c.id = ? AND f.id = ? LIMIT 1'
            );
            $chk->execute([$catId, $fid]);
            if ($chk->fetchColumn() === false) throw new RuntimeException('That group belongs to a different manufacturer.');
            $pdo->prepare('UPDATE library_fabrics SET category_id = ? WHERE id = ?')->execute([$catId, $fid]);
        } else {
            $pdo->prepare('UPDATE library_fabrics SET category_id = NULL WHERE id = ?')->execute([$fid]);
        }
        $_SESSION['flash_success'] = 'Fabric filed.';
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Could not update: ' . $e->getMessage();
}

header('Location: /master-admin/fabric-library.php');
exit;
