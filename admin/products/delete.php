<?php
declare(strict_types=1);

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

$user = current_user();

// Accept either a single id (per-row Delete button) or ids[] (bulk
// select). Merge, dedupe, drop non-positives — tenant scoping on the
// DELETE keeps it safe even with a crafted form.
$ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
if (($single = (int) ($_POST['id'] ?? 0)) > 0) $ids[] = $single;
$ids = array_values(array_unique(array_filter(
    array_map('intval', $ids),
    static fn ($n) => $n > 0
)));

if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));

    // Refuse to delete a product that a real quote or order is built on.
    //
    // There is no foreign key from quote_items to products (nothing in the
    // repo declares one), so the rows are not cascaded and not restricted —
    // they are simply left pointing at a product that no longer exists. Every
    // document builder then INNER JOINs products: factory/worksheet-print.php
    // (×2), factory/floor.php and _partials/factory_ar.php (×3). An inner join
    // drops the unmatched row, so the blinds quietly disappear from the work
    // ticket, the floor board and the invoice while the order still shows its
    // old total. Nothing errors and nothing says anything.
    //
    // Drafts are allowed through: losing a line on a draft is recoverable and
    // blocking them would stop ordinary catalogue tidying. A quote that has
    // been sent, accepted or placed is what documents are generated from.
    $inUse = [];
    try {
        $st = db()->prepare(
            "SELECT p.id, p.name, COUNT(DISTINCT q.id) AS n_quotes
               FROM products p
               JOIN quote_items qi ON qi.product_id = p.id
               JOIN quotes q       ON q.id = qi.quote_id
              WHERE p.id IN ($ph) AND p.client_id = ? AND q.status <> 'draft'
           GROUP BY p.id, p.name"
        );
        $st->execute(array_merge($ids, [$user['client_id']]));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $inUse[(int) $r['id']] = $r['name'] . ' (' . (int) $r['n_quotes']
                . ((int) $r['n_quotes'] === 1 ? ' order' : ' orders') . ')';
        }
    } catch (Throwable $e) { /* quote_items absent — nothing to protect */ }

    $ids = array_values(array_diff($ids, array_keys($inUse)));
    if ($inUse) {
        $_SESSION['flash_error'] = (count($inUse) === 1 ? 'This product is' : 'These products are')
            . ' used by quotes or orders, so ' . (count($inUse) === 1 ? 'it was' : 'they were')
            . ' kept: ' . implode(', ', $inUse)
            . '. Deleting would take those blinds off the work tickets and invoices.';
    }
}

if ($ids) {
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    // ON DELETE CASCADE on options/extras/systems/price_tables handles children.
    $stmt = db()->prepare("DELETE FROM products WHERE id IN ($ph) AND client_id = ?");
    $stmt->execute(array_merge($ids, [$user['client_id']]));
    $n = $stmt->rowCount();
    if ($n > 0) {
        $_SESSION['flash_success'] = $n === 1 ? 'Product deleted.' : "$n products deleted.";
    }
}

header('Location: /admin/products/index.php');
exit;
