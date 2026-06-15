<?php
declare(strict_types=1);

/**
 * Master Admin: catalogue spell-check / find-text (read-only).
 *
 * Scans every catalogue label — product names, systems, fabrics, option groups
 * and choice labels — for a search term and shows exactly where each match
 * lives, with a link to the owning product. Defaults to a set of common typos;
 * type any word to search for it. Changes nothing.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$user = current_user();
$pdo  = db();

// Common misspellings to scan for by default (includes the QA-reported four).
$commonTypos = ['Venetain', 'Reccess', 'Controll', 'Wheight', 'Recieve', 'Seperate', 'Accessor', 'Aluminuim'];

$q     = trim((string) ($_GET['q'] ?? ''));
$terms = $q !== '' ? [$q] : $commonTypos;

// (table, column, optional join to the owning product, label, where-to-fix hint)
$targets = [
    ['products',              'name',  '',                                                       'Product name',  'Products → open the product → rename it'],
    ['product_systems',       'name',  'JOIN products p ON p.id = t.product_id',                  'System name',   'Products → the product → Systems → rename'],
    ['product_options',       'name',  'JOIN products p ON p.id = t.product_id',                  'Fabric name',   'Products → the product → Fabrics → edit'],
    ['product_extras',        'name',  'JOIN products p ON p.id = t.product_id',                  'Option group',  'Products → the product → Options → edit the group'],
    ['product_extra_choices', 'label', 'JOIN product_extras e ON e.id = t.product_extra_id
                                        JOIN products p ON p.id = e.product_id',                  'Option choice', 'Products → the product → Options → open the group → edit the choice'],
];

$hits = [];
foreach ($terms as $term) {
    foreach ($targets as [$table, $col, $join, $label, $hint]) {
        try {
            $sql = "SELECT t.$col AS value, p.id AS product_id, p.name AS product_name
                      FROM $table t $join
                     WHERE t.$col LIKE ?
                     LIMIT 50";
            $st = $pdo->prepare($sql);
            $st->execute(['%' . $term . '%']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $hits[] = [
                    'term'    => $term,
                    'kind'    => $label,
                    'value'   => (string) ($r['value'] ?? ''),
                    'product' => (string) ($r['product_name'] ?? ''),
                    'pid'     => (int) ($r['product_id'] ?? 0),
                    'hint'    => $hint,
                ];
            }
        } catch (Throwable $e) {
            // table/column absent on this install — skip quietly
        }
    }
}

$activeNav = 'spell-check';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Catalogue spell-check &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Catalogue spell-check</h1>
                <p class="page-subtitle">
                    Find a word anywhere in your catalogue — product names, systems, fabrics,
                    option groups and choices — so typos are easy to track down and fix. Read-only.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/master-admin/index.php" class="btn btn-secondary">&larr; Master Admin</a>
            </div>
        </div>

        <section class="section">
            <form method="get" action="/master-admin/spell-check.php"
                  style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                <input type="text" name="q" value="<?= e($q) ?>" maxlength="60"
                       placeholder="Search for a word (e.g. Venetain)…"
                       class="form-control" style="max-width:22rem">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($q !== ''): ?>
                    <a href="/master-admin/spell-check.php" class="btn btn-secondary">Scan common typos</a>
                <?php endif; ?>
            </form>
            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.75rem 0 0">
                <?= $q !== ''
                    ? 'Showing matches for “' . e($q) . '”.'
                    : 'Showing matches for common misspellings. Type a word above to search for anything.' ?>
            </p>
        </section>

        <section class="section">
            <?php if (!$hits): ?>
                <p style="color:var(--alert-success-text);font-weight:600">
                    ✓ No matches found<?= $q === '' ? ' for the common typos — your catalogue looks clean.' : '.' ?>
                </p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Found</th><th>Where</th><th>In product</th><th>How to fix</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hits as $h): ?>
                                <tr>
                                    <td><strong><?= e($h['value']) ?></strong></td>
                                    <td><?= e($h['kind']) ?></td>
                                    <td>
                                        <?php if ($h['product'] !== ''): ?>
                                            <?php if ($h['pid'] > 0): ?>
                                                <a href="/admin/products/edit.php?id=<?= (int) $h['pid'] ?>"><?= e($h['product']) ?></a>
                                            <?php else: ?><?= e($h['product']) ?><?php endif; ?>
                                        <?php else: ?>&mdash;<?php endif; ?>
                                    </td>
                                    <td style="color:var(--text-muted);font-size:0.8125rem"><?= e($h['hint']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
