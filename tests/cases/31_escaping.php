<?php
declare(strict_types=1);

/**
 * ESCAPING. Names a company (or the factory, via the catalogue push) types are
 * shown back as text, never run as page code (overnight audit 2026-10-17).
 */

test('a product\'s option label is shown as text on the product page, not run as code', function () {
    $pid = (int) t_val('SELECT id FROM products WHERE client_id = 101 ORDER BY id LIMIT 1');
    $old = (string) t_val('SELECT option_label FROM products WHERE id = ?', [$pid]);
    db()->prepare('UPDATE products SET option_label = ? WHERE id = ?')->execute(['<img src=x onerror=zzpwn>', $pid]);
    try {
        $r = as_user('alpha.admin')->get('/admin/products/edit.php?id=' . $pid);
        check_page_ok($r, 'product edit page');
        check_not_contains('<img src=x onerror=zzpwn>', $r->body, 'the product edit page');
    } finally {
        db()->prepare('UPDATE products SET option_label = ? WHERE id = ?')->execute([$old, $pid]);
    }
});

test('the quote builder escapes option names it writes into the live price box', function () {
    $src = (string) file_get_contents(APP_ROOT . '/quote-builder/edit.php');
    check(!preg_match('/bits\.push\(ex\.choice_label\b/', $src), 'edit.php pushes a raw choice_label into the live price box');
});
