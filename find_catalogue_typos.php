<?php
declare(strict_types=1);

/**
 * Catalogue typo finder (read-only). Scans every catalogue label — product
 * names, systems, fabrics, option groups and choice labels — for a set of
 * search terms and tells you exactly WHERE each one lives, with a link to the
 * product so you can fix it. Defaults to the QA-reported typos; add your own
 * with ?q=word.
 *
 * Super-admin only. Changes nothing. Delete this file once you're done.
 *   /find_catalogue_typos.php
 *   /find_catalogue_typos.php?q=Venetain
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';

requireSuperAdmin();

$pdo = db();

$default = ['Venetain', 'Reccess', 'Controll', 'Wheight'];
$qParam  = trim((string) ($_GET['q'] ?? ''));
$terms   = $qParam !== '' ? [$qParam] : $default;

// (table, column, join to get the owning product, label, where-to-fix hint)
$targets = [
    ['products',              'name',  '',                                                       'Product name',  'Products → open the product → rename it'],
    ['product_systems',       'name',  'JOIN products p ON p.id = t.product_id',                  'System name',   'Products → the product → Systems → rename'],
    ['product_options',       'name',  'JOIN products p ON p.id = t.product_id',                  'Fabric name',   'Products → the product → Fabrics → edit'],
    ['product_extras',        'name',  'JOIN products p ON p.id = t.product_id',                  'Option group',  'Products → the product → Options → edit the group'],
    ['product_extra_choices', 'label', 'JOIN product_extras e ON e.id = t.product_extra_id
                                        JOIN products p ON p.id = e.product_id',                  'Option choice', 'Products → the product → Options → open the group → edit the choice'],
];

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><title>Catalogue typo finder</title>';
echo '<style>body{font-family:system-ui,Arial,sans-serif;max-width:60rem;margin:2rem auto;padding:0 1rem;color:#1f2937}'
   . 'h1{color:#1f3b5b}h2{margin-top:1.5rem;color:#15294a;font-size:1.05rem}'
   . '.hit{border:1px solid #e5e7eb;border-left:4px solid #b91c1c;border-radius:8px;padding:0.6rem 0.9rem;margin:0.5rem 0}'
   . '.where{color:#6b7280;font-size:0.875rem}a{color:#2563eb}code{background:#f3f4f6;padding:0 4px;border-radius:4px}</style>';
echo '<h1>Catalogue typo finder</h1>';
echo '<p>Searching for: ' . e(implode(', ', array_map(fn ($t) => '“' . $t . '”', $terms))) . '. '
   . 'Read-only — fix each one in Products, then delete this file.</p>';

$found = 0;
foreach ($terms as $term) {
    echo '<h2>“' . e($term) . '”</h2>';
    $any = false;
    foreach ($targets as [$table, $col, $join, $label, $hint]) {
        try {
            $sql = "SELECT t.id AS row_id, t.$col AS value, p.id AS product_id, p.name AS product_name
                      FROM $table t $join
                     WHERE t.$col LIKE ?
                     LIMIT 50";
            $st = $pdo->prepare($sql);
            $st->execute(['%' . $term . '%']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $any = true; $found++;
                $prodId   = (int) ($r['product_id'] ?? 0);
                $prodName = (string) ($r['product_name'] ?? '');
                echo '<div class="hit"><strong>' . e($label) . ':</strong> <code>' . e((string) $r['value']) . '</code>';
                if ($prodName !== '') {
                    echo ' &middot; in product <strong>' . e($prodName) . '</strong>';
                    if ($prodId > 0) {
                        echo ' (<a href="/admin/products/edit.php?id=' . $prodId . '">open product</a>)';
                    }
                }
                echo '<div class="where">Fix: ' . e($hint) . '</div></div>';
            }
        } catch (Throwable $e) {
            // table/column not present on this install — skip quietly
        }
    }
    if (!$any) echo '<p style="color:#16a34a">None found. ✓</p>';
}

echo '<hr><p>' . ($found === 0 ? 'Nothing to fix — all clear. 🎉' : 'Found ' . $found . ' place' . ($found === 1 ? '' : 's') . ' to correct.')
   . ' Add a custom search with <code>?q=word</code>. Delete this file when finished.</p>';
