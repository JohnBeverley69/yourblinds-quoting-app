<?php
declare(strict_types=1);

/**
 * SUPPLIER CATALOGUES (John, 2026-10-16): a company asks for a supplier from
 * its Supplier catalogues page — supplier, contact, account number, who's
 * asking, and any number of price list / option / EDI files. Requests reach
 * the super-admin's Supplier requests page; a company sees only its own, and
 * nobody else can read the files.
 */

$clean = static function (): void {
    foreach (t_rows('SELECT file_path FROM supplier_request_files') as $f) {
        @unlink(APP_ROOT . '/' . $f['file_path']);
    }
    db()->exec('DELETE FROM supplier_request_files');
    db()->exec('DELETE FROM supplier_requests');
};
$clean();

$form = static fn (array $extra = []): array => $extra + [
    'supplier_name'    => 'Zed Blinds Ltd',
    'website'          => 'zedblinds.example',
    'supplier_contact' => 'Pat Rep',
    'supplier_email'   => 'pat@zedblinds.example',
    'supplier_phone'   => '01234 567890',
    'account_number'   => 'ACC-77',
    'requester_name'   => 'Alpha Admin',
    'requester_email'  => 'alpha.admin@example.test',
    'notes'            => 'Rollers and verticals',
];

test('a company admin can open Supplier catalogues and it is in their menu', function () {
    $a = as_user('alpha.admin');
    $r = $a->get('/library/index.php');
    check_page_ok($r, 'Supplier catalogues as a shop admin');
    check_contains('Request a supplier', $r->body, 'request form on the page');
    check_contains('href="/library/index.php"', $r->body, 'Supplier catalogues in the menu');
});

test('a request is logged with the supplier\'s contact details and its files', function () use ($form) {
    $a   = as_user('alpha.admin');
    $tmp = tempnam(sys_get_temp_dir(), 'ybsr');
    file_put_contents($tmp, "Width,Drop,Price\n600,600,10.00\n");
    $tmp2 = tempnam(sys_get_temp_dir(), 'ybsr');
    file_put_contents($tmp2, '%PDF-1.4 test');
    $r = t_submit($a, '/library/request-supplier.php', $form([
        'files[0]' => new CURLFile($tmp, 'text/csv', 'Zed prices 2026.csv'),
        'files[1]' => new CURLFile($tmp2, 'application/pdf', 'Zed options.pdf'),
    ]));
    @unlink($tmp); @unlink($tmp2);
    check_same(302, $r->status, 'request submit redirects back');
    check($r->phpErrors === [], 'PHP problems: ' . implode(' | ', $r->phpErrors));

    $row = t_row('SELECT * FROM supplier_requests WHERE supplier_name = ?', ['Zed Blinds Ltd']);
    check($row !== null, 'the request was not logged');
    check_same(101, (int) $row['client_id'], 'request company (Alpha)');
    check_same('Pat Rep', $row['supplier_contact'], 'supplier contact');
    check_same('ACC-77', $row['account_number'], 'account number');
    check_same('Alpha Admin', $row['requester_name'], 'requester name');

    $files = t_rows('SELECT * FROM supplier_request_files WHERE request_id = ? ORDER BY id', [(int) $row['id']]);
    check_same(2, count($files), 'files saved');
    check_same('Zed prices 2026.csv', $files[0]['file_name'], 'first file name');
    check(is_file(APP_ROOT . '/' . $files[0]['file_path']), 'the uploaded file is not on disk');
    check(strpos($files[0]['file_path'], 'uploads/supplier-requests/') === 0, 'file stored outside the request folder');

    $page = $a->get('/library/index.php');
    check_contains('Zed Blinds Ltd', $page->body, 'the request in "Your requests"');
});

test('a file type we don\'t take is refused and nothing is logged', function () use ($form) {
    $a   = as_user('alpha.admin');
    $tmp = tempnam(sys_get_temp_dir(), 'ybsr');
    file_put_contents($tmp, '<?php echo 1;');
    $before = (int) t_val('SELECT COUNT(*) FROM supplier_requests');
    t_submit($a, '/library/request-supplier.php', $form([
        'supplier_name' => 'Bad File Blinds',
        'files[0]'      => new CURLFile($tmp, 'text/plain', 'shell.php'),
    ]));
    @unlink($tmp);
    check_same($before, (int) t_val('SELECT COUNT(*) FROM supplier_requests'), 'requests after a refused file');
});

test('your name and email are required', function () use ($form) {
    $a = as_user('alpha.admin');
    $before = (int) t_val('SELECT COUNT(*) FROM supplier_requests');
    t_submit($a, '/library/request-supplier.php', $form(['supplier_name' => 'No Email Blinds', 'requester_email' => 'not-an-email']));
    check_same($before, (int) t_val('SELECT COUNT(*) FROM supplier_requests'), 'requests after a bad email');
});

test('another company sees none of it; only the super-admin reads requests and files', function () {
    $b = as_user('bravo.admin');
    check_not_contains('Zed Blinds Ltd', $b->get('/library/index.php')->body, 'Bravo seeing Alpha\'s request');
    $fileId = (int) t_val('SELECT MIN(id) FROM supplier_request_files');
    check_refused($b->get('/master-admin/supplier-requests.php'), 'company admin opening Supplier requests');
    check_refused($b->get('/master-admin/supplier-request-file.php?file=' . $fileId), 'company admin downloading a request file');

    $s = as_user('super');
    $page = $s->get('/master-admin/supplier-requests.php');
    check_page_ok($page, 'Supplier requests as super-admin');
    check_contains('Zed Blinds Ltd', $page->body, 'request listed');
    check_contains('Pat Rep', $page->body, 'supplier contact shown');
    check_contains('supplier-request-file.php?file=' . $fileId, $page->body, 'file download link');
    check_same(200, $s->get('/master-admin/supplier-request-file.php?file=' . $fileId)->status, 'super-admin downloading a request file');
});

$clean();
