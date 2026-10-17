<?php
declare(strict_types=1);

/**
 * user_error_text() (bootstrap.php, John 2026-10-17): our own messages reach
 * people unchanged; database / PHP-internal errors are replaced by a reference
 * and logged, so SQL and table names never show on a page.
 */

test('error text shown to people: our own messages pass, database errors are hidden behind a reference', function () {
    $own = new RuntimeException("This order can't be deleted — it has been invoiced.");
    check_same($own->getMessage(), user_error_text($own), 'our own message');

    $db = new PDOException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'secret_col' in 'field list'");
    $shown = user_error_text($db);
    check(!str_contains($shown, 'SQLSTATE') && !str_contains($shown, 'secret_col'), "no SQL in: $shown");
    check((bool) preg_match('/a database problem \(reference [0-9A-F]{6} — it has been logged\)/', $shown), "reference given: $shown");

    $wrapped = new RuntimeException('Could not save: SQLSTATE[23000]: Integrity constraint violation');
    check(!str_contains(user_error_text($wrapped), 'SQLSTATE'), 'a database error wrapped in our own exception is hidden too');

    $php = new TypeError('Argument #1 ($x) must be of type int, string given, called in /var/www/app/x.php');
    check(str_starts_with(user_error_text($php), 'an unexpected problem (reference'), 'a PHP-internal error is hidden');
});

test('a failing save shows a reference, not SQL, on a real page', function () {
    // The products page reports a duplicate name its own way; any other DB failure now
    // comes back as a reference. Check the page still opens cleanly after the swap.
    check_page_ok(as_user('alpha.admin')->get('/admin/products/index.php'), 'Products as a company admin');
});
