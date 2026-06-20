<?php
declare(strict_types=1);

/**
 * Fabric Library supplier-group endpoint — create groups, file RANGES
 * (fabric_suppliers) into them, delete, reorder. Non-destructive: only
 * touches fabric_supplier_groups and fabric_suppliers.group_id.
 *
 * A "group" is the real supplier (Decora, Eclipse…); the things filed into it
 * are the ranges that currently show as top-level rows.
 *
 * POST actions:
 *   create          — name [, supplier_id]          → new group
 *   assign          — supplier_id, group_id (0 = ungroup)
 *   rename          — group_id, name
 *   delete          — group_id   → remove group; its ranges become ungrouped
 *   reorder_groups  — order[]    → set sort_order
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
try { $pdo->query('SELECT 1 FROM fabric_supplier_groups LIMIT 0'); }
catch (Throwable $e) { $tableOk = false; }

try {
    if (!$tableOk) {
        $_SESSION['flash_error'] = 'Supplier groups aren\'t set up yet — run /migrate_fabric_supplier_groups.php first.';
        header('Location: /master-admin/fabric-library.php');
        exit;
    }

    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $_SESSION['flash_error'] = 'Give the supplier group a name.';
        } else {
            $dup = $pdo->prepare('SELECT id FROM fabric_supplier_groups WHERE LOWER(name) = LOWER(?) LIMIT 1');
            $dup->execute([$name]);
            if ($dup->fetchColumn() !== false) {
                $_SESSION['flash_error'] = 'There is already a group called “' . $name . '”.';
            } else {
                $sortStmt = $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM fabric_supplier_groups');
                $nextSort = (int) $sortStmt->fetchColumn();
                $pdo->prepare('INSERT INTO fabric_supplier_groups (name, sort_order) VALUES (?, ?)')
                    ->execute([$name, $nextSort]);
                $newId = (int) $pdo->lastInsertId();
                // File a range straight in, if one was named.
                $sidIn = (int) ($_POST['supplier_id'] ?? 0);
                if ($sidIn > 0) {
                    $pdo->prepare('UPDATE fabric_suppliers SET group_id = ? WHERE id = ?')->execute([$newId, $sidIn]);
                }
                $_SESSION['flash_success'] = 'Supplier group “' . $name . '” created.';
            }
        }
    } elseif ($action === 'rename') {
        $gid  = (int) ($_POST['group_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($gid > 0 && $name !== '') {
            $clash = $pdo->prepare('SELECT id FROM fabric_supplier_groups WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1');
            $clash->execute([$name, $gid]);
            if ($clash->fetchColumn() !== false) {
                $_SESSION['flash_error'] = 'Another group already has that name.';
            } else {
                $pdo->prepare('UPDATE fabric_supplier_groups SET name = ? WHERE id = ?')->execute([$name, $gid]);
                $_SESSION['flash_success'] = 'Group renamed.';
            }
        } else {
            $_SESSION['flash_error'] = 'A group needs a name.';
        }
    } elseif ($action === 'delete') {
        $gid = (int) ($_POST['group_id'] ?? 0);
        if ($gid > 0) {
            $pdo->prepare('UPDATE fabric_suppliers SET group_id = NULL WHERE group_id = ?')->execute([$gid]);
            $pdo->prepare('DELETE FROM fabric_supplier_groups WHERE id = ?')->execute([$gid]);
            $_SESSION['flash_success'] = 'Supplier group removed. Its ranges are now ungrouped.';
        }
    } elseif ($action === 'reorder_groups') {
        $order = $_POST['order'] ?? [];
        if (!is_array($order)) $order = [];
        $upd = $pdo->prepare('UPDATE fabric_supplier_groups SET sort_order = ? WHERE id = ?');
        $i = 0;
        foreach ($order as $gid) {
            $gid = (int) $gid;
            if ($gid > 0) { $upd->execute([$i, $gid]); $i++; }
        }
        $_SESSION['flash_success'] = 'Group order updated.';
    } else { // assign
        $sid = (int) ($_POST['supplier_id'] ?? 0);
        $gid = (int) ($_POST['group_id'] ?? 0);   // 0 = ungroup
        if ($sid <= 0) throw new RuntimeException('Missing range.');

        if ($gid > 0) {
            $chk = $pdo->prepare('SELECT 1 FROM fabric_supplier_groups WHERE id = ? LIMIT 1');
            $chk->execute([$gid]);
            if ($chk->fetchColumn() === false) throw new RuntimeException('Unknown supplier group.');
            $pdo->prepare('UPDATE fabric_suppliers SET group_id = ? WHERE id = ?')->execute([$gid, $sid]);
        } else {
            $pdo->prepare('UPDATE fabric_suppliers SET group_id = NULL WHERE id = ?')->execute([$sid]);
        }
        $_SESSION['flash_success'] = 'Range filed.';
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Could not update: ' . $e->getMessage();
}

header('Location: /master-admin/fabric-library.php');
exit;
