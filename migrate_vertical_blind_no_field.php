<?php
declare(strict_types=1);

/**
 * One-off: on the Bev Vertical Blinds worksheet template, replace the per-label
 * "Line no" field (order:line_no — prints "1/1" identically on every label of a
 * single multi-qty line) with the new "Blind no" field (order:blind_seq — the
 * running "N of M" across the order, e.g. "3 of 48"). Header fields are left
 * alone; only the per-blind LABELS are changed. Idempotent (re-running is a
 * no-op once swapped). Restricted to "Bev Vertical Blinds" so one-per-line
 * templates (Fabrics Only) are untouched. Run as super-admin.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$factory = function_exists('current_factory_id') ? (int) current_factory_id()
         : (function_exists('factory_client_id') ? (int) factory_client_id() : 3);

$FROM = 'order:line_no';
$TO   = 'order:blind_seq';

// Find the master Bev Vertical Blinds product for this factory.
$ps = $pdo->prepare("SELECT id, name FROM products WHERE client_id = ? AND name = 'Bev Vertical Blinds' LIMIT 1");
$ps->execute([$factory]);
$prod = $ps->fetch(PDO::FETCH_ASSOC);
if (!$prod) { echo "Product 'Bev Vertical Blinds' not found for factory {$factory}.\n"; exit; }
$pid = (int) $prod['id'];

$ts = $pdo->prepare('SELECT id, layout_json FROM worksheet_templates WHERE product_id = ?');
$ts->execute([$pid]);
$rows = $ts->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) { echo "No worksheet template rows for Bev Vertical Blinds (product {$pid}).\n"; exit; }

$upd = $pdo->prepare('UPDATE worksheet_templates SET layout_json = ? WHERE id = ?');

foreach ($rows as $row) {
    $tid    = (int) $row['id'];
    $layout = json_decode((string) $row['layout_json'], true);
    if (!is_array($layout)) { echo "Template {$tid}: layout_json not decodable — skipped.\n"; continue; }

    $changed = 0;
    foreach (($layout['labels'] ?? []) as $li => $label) {
        foreach (($label['fields'] ?? []) as $fi => $field) {
            if (($field['source'] ?? '') === $FROM) {
                $layout['labels'][$li]['fields'][$fi]['source'] = $TO;
                $changed++;
            }
        }
    }

    // Report header occurrences (left as-is) so nothing is silently missed.
    $hdr = 0;
    foreach (($layout['header']['fields'] ?? []) as $field) {
        if (($field['source'] ?? '') === $FROM) $hdr++;
    }

    echo "Template {$tid}: {$changed} label field(s) swapped line_no -> blind_seq";
    echo $hdr ? " ({$hdr} in header left unchanged).\n" : ".\n";

    if ($changed > 0) {
        $upd->execute([json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $tid]);
    }
}

echo "\nDone. The Bev Vertical Blinds labels now print the running blind number (N of M).\n";
