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

test('Build Rules buttons pass rule names to the page script as data, not code', function () {
    $pid = (int) t_val("SELECT p.id FROM products p JOIN build_variables v ON v.product_id = p.id
                         WHERE p.client_id = 3 AND COALESCE(NULLIF(p.source_client_id, 0), p.client_id) = 3 ORDER BY p.id LIMIT 1");
    if ($pid <= 0) skip('fixture: no factory product with build rules');
    $v = t_row('SELECT id, label FROM build_variables WHERE product_id = ? ORDER BY seq, id LIMIT 1', [$pid]);
    db()->prepare('UPDATE build_variables SET label = ? WHERE id = ?')->execute(["x');zzpwn('", $v['id']]);
    try {
        $r = as_user('factory.office')->get('/factory/build-rules-v2.php?product_id=' . $pid);
        check_page_ok($r, 'Build Rules v2');
        check_not_contains("'x&#039;);zzpwn", $r->body, 'Build Rules v2 (a name inside a quoted script string)');
    } finally {
        db()->prepare('UPDATE build_variables SET label = ? WHERE id = ?')->execute([$v['label'], $v['id']]);
    }
});

test('a per-company data export keeps the company name inside its comment line', function () {
    require_once APP_ROOT . '/_partials/db_backup.php';
    $old = (string) t_val('SELECT company_name FROM clients WHERE id = 102');
    db()->prepare('UPDATE clients SET company_name = ? WHERE id = 102')->execute(["Bravo\nDROP TABLE zz_pwn; --"]);
    try {
        $fh = fopen('php://memory', 'w+');
        pe_stream_tenant_dump(db(), $fh, 102, (string) t_val('SELECT company_name FROM clients WHERE id = 102'));
        rewind($fh);
        $head = (string) fread($fh, 4000);
        check(!preg_match('/^DROP TABLE zz_pwn/m', $head), 'the company name started a line of SQL in the export');
    } finally {
        db()->prepare('UPDATE clients SET company_name = ? WHERE id = 102')->execute([$old]);
    }
});

test('the product wizard shows the option label as text', function () {
    $src = (string) file_get_contents(APP_ROOT . '/admin/products/wizard.php');
    check(!preg_match('/<\?= strtolower\(\(string\) \(\$product\[\'option_label\'\]/', $src), 'wizard.php echoes option_label unescaped');
});
