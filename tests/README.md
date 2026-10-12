# Automatic tests

A set of checks that run by themselves on GitHub for **every pull request** and
every push to `main` (`.github/workflows/tests.yml`). They build a throw-away
database from scratch, so they never touch the live site, and they never send
an email, a text or anything to QuickBooks.

On a pull request you'll see **Tests / Regression tests** with a green tick
(everything still works) or a red cross. Click **Details** and then **Summary**
for a plain-English list of what broke.

## What's checked

| File | Area | What it proves |
|---|---|---|
| `cases/00_setup_sanity.php` | Set-up | The test database built properly, contains no real e-mail addresses, and a person can log in. |
| `cases/10_prices.php` | **Prices** | 19 real-world lines are priced from 4 points of view (the factory, a trade account, a salesperson without costs, the factory keying an order *for* the trade account). There are also 240 random lines covering every product. Every figure must match `goldens/prices.json` to the penny. The lines include a roller with a motor, sizes typed in cm/m, Senses fascia oversize, 3 blinds on one fascia, a Vertical with per-metre options, Forest Wood with per-unit accessories and a custom pelmet size, a Venetian, a PF Shutter split into 3 panels, Infusions tape (a supplier list add-on), a trade discount, and supplier price lists converted to trade. |
| `cases/20_pages_open.php` | **Pages open** | About 20 key screens open with no PHP errors or warnings for the people who use them, and refuse the people who shouldn't see them. Every help guide and every menu link is opened as six kinds of user. |
| `cases/30_no_peeking.php` | **No peeking** | One company tries to read 19 of another company's screens and change 35 of its handlers using the other company's ids; nothing leaks or changes. A white-label factory can't open any of the 19 accounts pages, doesn't "own" the main factory's products, and never sees the main factory's orders. The super-admin can. |
| `cases/40_copies_match.php` | **Copies match** | A probe product has *every column* filled in, with the column list read from the database. It is then copied by the push, by "new company with seeded catalogue" and by Duplicate, and each copy is compared column by column. A column added in future is checked automatically. `KNOWN_GAPS` lists today's known differences with reasons. The test also checks that seed and push give the same catalogue, that every row has its source marker, and that a second push changes nothing. |
| `cases/50_order_journey.php` | **Order journey** | A trade account places an order with a made roller and a bought-in Forest Wood blind. It then goes through Process order (floor jobs + a PO to the supplier), floor finished, bought-in received, Ready, Dispatched (delivery note), Invoice and Payment. The stage and money are checked at every step, and the invoice must match `goldens/journey.json`. |

## Running them on your own computer

You need PHP 8 and a local MySQL or MariaDB (XAMPP is fine). Then:

```bash
composer install
php tests/run.php
```

The first command is only needed once. `php tests/run.php` takes about 40 seconds. It **wipes and rebuilds** a database called `yb_ci` on
127.0.0.1 (user `root`, no password, unless `DB_HOST`/`DB_USER`/`DB_PASS`/`DB_NAME`
are set). It refuses to run against any other host, or a database not named
`yb_ci`/`yb_test_*`, and it ignores your `.env` file completely.

These options are useful:

- `php tests/run.php --only=prices` runs only the files whose name contains "prices".
- `php tests/run.php --keep-db --only=journey` skips the rebuild, which is quicker for re-running one file.
- `php tests/run.php --update-goldens` re-captures the expected prices and invoice.

### When a price test goes red

Either the change broke a price (fix the code), or the price was *meant* to
change (a new rule, a corrected grid). In the second case run
`php tests/run.php --update-goldens`, then read the diff of
`tests/goldens/*.json` line by line. Only the lines you meant to change should
move. Commit the new goldens with the change.

## How it's built

There's no framework, just a small custom runner (`run.php`) and about 150 lines of helpers.

- `schema.sql` is the database structure with no data: every table, after every migration.
  To regenerate it, run all `setup/migrations/*.php` against a scratch copy, then run
  `mysqldump --no-data --skip-add-drop-table yb_ci | sed -E 's/ AUTO_INCREMENT=[0-9]+//' > tests/schema.sql`.
  **A new migration must also be added to `schema.sql`.** Otherwise the tests run without the new column. Add the `CREATE`/`ALTER` by hand, or regenerate the file.
- The fixtures come in three files:
  - `fixtures/10_factory_catalogue.sql` is the factory (client 3, renamed "Test Factory Ltd") and its 14 active products, scrubbed. It is generated from the local PII-free test copy by `tools/make_catalogue_fixture.php`.
  - `fixtures/15_catalogue_additions.php` adds the newer features the copy predates: per-unit accessories, a custom pelmet and the panel split.
  - `fixtures/20_companies_and_people.php` creates the fake companies: Alpha and Bravo (trade accounts) and Charlie (a white-label factory). Their catalogues come from the real `seed_client_from_template()`. Their people are `super`, `factory.office`, `factory.floor`, `alpha.admin`, `alpha.sales`, `alpha.fitter`, `bravo.admin` and `charlie.office`. Every password is `yb-test-password`, and it only exists in the test database.
- `lib/run_case.php` runs one test file in its own PHP process. Page tests talk to
  the PHP built-in web server (`php -S 127.0.0.1:8765`). PHP warnings or errors
  logged while serving a page fail that test. So do any raised while a test calls
  app code directly.
- Mail: `APP_ENV=testing` with no `MAIL_INTERCEPT_TO` means `mailer_deliver()`
  drops every message and reports success, so flows carry on as if sent. No
  SMS, QuickBooks, Lunch Flow, ElevenLabs or Postcoder keys exist in a test run.

## Adding a test

Put it in the right `cases/NN_*.php`, or make a new file. Files run in name order.

```php
test('what it checks, in plain English', function () {
    $h = as_user('alpha.admin');                      // logs in through the real form
    $qid = flow_new_quote($h, fx('customer_alpha'));  // helpers in lib/flows.php
    check_page_ok($h->get("/quote-builder/edit.php?id=$qid"));
    check_same('draft', t_val('SELECT status FROM quotes WHERE id = ?', [$qid]), 'status');
});
```

The checks available are `check()`, `check_same()`, `check_money()`,
`check_contains()`, `check_not_contains()`, `check_page_ok()`, `check_refused()`,
`fail()` and `skip()`. For data there are `t_val()`, `t_row()` and `t_rows()`, and `fx('name')` gives a
fixture id. `t_act_as('user')` acts as a person in-process, for calling app functions
directly. `t_submit($h, $path, $fields)` posts a form with its CSRF token.

To add a price line, add it to `price_scenarios()` in `lib/prices.php`, run
`--update-goldens` and check that only the new line appeared in the diff.

## Not covered (yet)

- JavaScript in the browser: the live-price box, the offline tablet engine, and drag-and-drop on the calendar.
- PDFs are only checked for opening. Their content isn't checked.
- Remakes, credit notes, statements runs, bank import, commissions, the calendar and appointments, SMS, QuickBooks sync, and PayPal billing are not checked.
- Only the factory's 14 products are covered, with a few fabrics per band. A product that exists only on the live site isn't covered.
