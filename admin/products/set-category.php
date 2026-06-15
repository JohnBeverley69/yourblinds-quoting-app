<?php
declare(strict_types=1);

/**
 * Assign a product to a category, or create a category. Non-destructive —
 * only touches products.category_id and the product_categories table.
 *
 * POST actions:
 *   create  — name            → make a new category for this tenant
 *   assign  — product_id, category_id (0/'' = ungroup) → file the product
 *   delete  — category_id     → remove the category; its products become ungrouped
 *
 * Admin-only, tenant-scoped, CSRF-checked.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /admin/products/index.php');
    exit;
}
csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();
$action   = (string) ($_POST['_action'] ?? 'assign');

$catTableOk = true;
try {
    $pdo->query('SELECT 1 FROM product_categories LIMIT 0');
} catch (Throwable $e) {
    $catTableOk = false;
}

try {
    if (!$catTableOk) {
        $_SESSION['flash_error'] = 'Categories aren\'t set up yet — run /migrate_product_categories.php first.';
        header('Location: /admin/products/index.php');
        exit;
    }

    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $_SESSION['flash_error'] = 'Give the category a name.';
        } else {
            // Avoid duplicate names for the same tenant (case-insensitive).
            $dup = $pdo->prepare('SELECT id FROM product_categories WHERE client_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
            $dup->execute([$clientId, $name]);
            if ($dup->fetchColumn() !== false) {
                $_SESSION['flash_error'] = 'You already have a category called "' . $name . '".';
            } else {
                $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_categories WHERE client_id = ?');
                $sortStmt->execute([$clientId]);
                $nextSort = (int) $sortStmt->fetchColumn();
                $pdo->prepare('INSERT INTO product_categories (client_id, name, sort_order) VALUES (?, ?, ?)')
                    ->execute([$clientId, $name, $nextSort]);
                $newId = (int) $pdo->lastInsertId();
                // If a product was named, file it straight into the new category.
                $pid = (int) ($_POST['product_id'] ?? 0);
                if ($pid > 0) {
                    $pdo->prepare('UPDATE products SET category_id = ? WHERE id = ? AND client_id = ?')
                        ->execute([$newId, $pid, $clientId]);
                }
                $_SESSION['flash_success'] = 'Category "' . $name . '" created.';
            }
        }
    } elseif ($action === 'delete') {
        $catId = (int) ($_POST['category_id'] ?? 0);
        if ($catId > 0) {
            // Ungroup its products first, then remove the category. Tenant-scoped.
            $pdo->prepare('UPDATE products SET category_id = NULL WHERE category_id = ? AND client_id = ?')
                ->execute([$catId, $clientId]);
            $pdo->prepare('DELETE FROM product_categories WHERE id = ? AND client_id = ?')
                ->execute([$catId, $clientId]);
            $_SESSION['flash_success'] = 'Category removed. Its products are now ungrouped.';
        }
    } else { // assign
        $pid   = (int) ($_POST['product_id']  ?? 0);
        $catId = (int) ($_POST['category_id'] ?? 0);   // 0 = ungroup
        if ($pid <= 0) throw new RuntimeException('Missing product.');

        if ($catId > 0) {
            // Validate the category belongs to this tenant.
            $chk = $pdo->prepare('SELECT 1 FROM product_categories WHERE id = ? AND client_id = ? LIMIT 1');
            $chk->execute([$catId, $clientId]);
            if ($chk->fetchColumn() === false) throw new RuntimeException('Unknown category.');
            $pdo->prepare('UPDATE products SET category_id = ? WHERE id = ? AND client_id = ?')
                ->execute([$catId, $pid, $clientId]);
        } else {
            $pdo->prepare('UPDATE products SET category_id = NULL WHERE id = ? AND client_id = ?')
                ->execute([$pid, $clientId]);
        }
        $_SESSION['flash_success'] = 'Product filed.';
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Could not update: ' . $e->getMessage();
}

header('Location: /admin/products/index.php');
exit;
