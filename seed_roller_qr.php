<?php
declare(strict_types=1);

/**
 * Seed: put a scannable QR on the Bev Roller Blinds label.
 *
 * The vertical worksheet already has the qr field; the roller one doesn't, so
 * roller labels print with no code and can't be scanned. This adds it.
 *
 * Safe to run: idempotent (won't add a second QR), and it only APPENDS the
 * field — the QR is pinned to the bottom-right by CSS whatever order it's in,
 * so it can't disturb the rest of the layout.
 *
 * Run via web: /seed_roller_qr.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$p = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
$p->execute([$MASTER]);
$pid = (int) $p->fetchColumn();
if ($pid === 0) { echo "Bev Roller Blinds not found on the master account.\n"; exit; }

$t = $pdo->prepare('SELECT id, name, layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id');
$t->execute([$pid]);
$templates = $t->fetchAll(PDO::FETCH_ASSOC);
if (!$templates) { echo "No worksheet template for Bev Roller Blinds — build one on Factory → Worksheets first.\n"; exit; }

$upd = $pdo->prepare('UPDATE worksheet_templates SET layout_json = ? WHERE id = ?');

foreach ($templates as $tpl) {
    $layout = json_decode((string) $tpl['layout_json'], true);
    if (!is_array($layout) || empty($layout['labels'])) { echo "  ! '{$tpl['name']}' has no labels — skipped.\n"; continue; }

    // Already has a QR somewhere? Leave it be.
    $hasQr = false;
    foreach ($layout['labels'] as $lab) {
        foreach ($lab['fields'] ?? [] as $f) { if (($f['source'] ?? '') === 'qr') { $hasQr = true; break 2; } }
    }
    if ($hasQr) { echo "  = '{$tpl['name']}' already has a QR — left alone.\n"; continue; }

    // Append to the first label. A roller has one label; if a template had more,
    // the first is the one that travels with the blind.
    $layout['labels'][0]['fields'][] = ['source' => 'qr', 'caption' => '', 'show' => 'always', 'align' => 'left'];
    $upd->execute([json_encode($layout, JSON_UNESCAPED_UNICODE), (int) $tpl['id']]);
    echo "  + Added a QR to '{$tpl['name']}'.\n";
}

echo "\nDone. Reprint a roller worksheet — the QR prints 20mm, bottom-right.\n";
