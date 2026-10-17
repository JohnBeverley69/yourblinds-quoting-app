# YourBlinds

Trade quoting, ordering and manufacturing platform for the UK made-to-measure blinds
trade, run by Beverley Blinds. It is built with PHP 8 and MySQL, with no framework:
server-rendered pages, vanilla JavaScript and one shared stylesheet. Live at
https://yourblinds.uk.

- **Companies (retailers and trade accounts):** InstaPrice, quotes, online acceptance,
  orders, supplier ordering, customers, calendar and fitting, payments, invoices,
  texts, plans and billing.
- **The factory:** the Pipeline, Process order, the production floor and barcode
  scanning, worksheets and labels, build rules, production areas, remakes, dispatch,
  delivery notes, invoices, statements, the bank feed and QuickBooks.

## Requirements

- **PHP 8.2**, as the live server and CI use. Extensions needed: `pdo_mysql`,
  `mysqli` (tests), `mbstring`, `gd`, `zip`, `intl`.
- **MySQL 8.0.** Live backups use MySQL 8's `utf8mb4_0900_ai_ci` collation, so they
  restore only onto MySQL 8. MariaDB (e.g. XAMPP) refuses them unless that
  collation is first swapped for `utf8mb4_unicode_ci`.
- **Composer**, for dompdf, PHPMailer and PhpSpreadsheet. Run `composer install` to
  fill `vendor/`, which isn't in git.
- **For full features, set in `.env`:** SMTP for email (AuthSMTP), PayPal, The SMS
  Works, Google Maps, getAddress, Anthropic (the support assistant), QuickBooks and
  Lunch Flow. Each feature switches itself off cleanly when its key is missing.

## Project layout

| Folder | What's in it |
|---|---|
| `auth/` | Login, sessions, roles, CSRF. `middleware.php` holds every access gate (`requireAdmin`, `requireFactory`, `requireFactoryAccounts`, …) |
| `quote-builder/` | The quote and order editor (`edit.php`), live pricing, saving lines, placing orders |
| `_partials/` | Shared logic: `pricing_engine.php`, `catalogue_push.php`, `order_parts.php`, `order_stage.php`, `blind_jobs.php`, `factory_ar.php`, `billing_helpers.php`, `plan_gate.php`, … |
| `admin/` | A company's own setup: products, options, price tables, users, settings |
| `factory/` | Factory screens: Pipeline, Incoming orders, floor, scanning, worksheets, build rules |
| `master-admin/` | The platform owner and the main factory's accounts: trade accounts, dispatch, invoices, bank, QuickBooks, pricing, plan features, backups |
| `calendar/` `customer-manager/` `orders/` `quote-history/` `instaprice/` `messages/` `billing/` `library/` | Company tools |
| `help/` | Animated help guides; the audio is in `media/guides/` |
| `offline/` | Offline quoting (the pricing engine as PHP-WebAssembly, plus an outbox) |
| `setup/migrations/` | `migrate_*.php`: every schema change. Idempotent, so safe to re-run |
| `setup/seeds/`, `setup/tools/` | Catalogue seeds and one-off repair/admin tools (super-admin) |
| `tests/` | The automated suite. `tests/schema.sql` is the full current schema (structure only) |
| `cron_backup.php`, `cron_messages.php` | Scheduled jobs: the nightly database dump; texts, follow-ups and tidy-ups |

## Local development

```sh
composer install
cp .env.example .env        # set DB_*, APP_ENV=development
mysql -u root -e "CREATE DATABASE yourblinds CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root yourblinds < tests/schema.sql
php setup/tools/install_fresh.php --company="Beverley Blinds Trade" --name="Your Name" --email=you@example.com
php -S localhost:8000       # then open http://localhost:8000/auth/login.php
```

## Fresh install (new environment from git alone)

Use this for staging, a new host, or a rebuild when **no backup can be restored**.
It gives you an empty, working system. For a real recovery, use **Recovery**
below instead.

1. **Create** an empty utf8mb4 database and a user with full rights on it.
2. **Load the schema:** `mysql -u <user> -p <db> < tests/schema.sql`. That's every
   table, with no data.
3. **Fill in `.env`:** DB settings, `APP_URL`, `FACTORY_CLIENT_ID` (default 3), and
   the integration keys you need.
4. **Create the first company and login:**
   `php setup/tools/install_fresh.php --company="…" --name="…" --email=…`
   - It creates the factory company at `FACTORY_CLIENT_ID` and a super-admin with
     a verified email.
   - It asks for the password at the prompt.
   - It refuses if any company already exists.
5. **Run the migrations.** They're a no-op on a fresh schema, but this proves the
   environment:
   `for f in setup/migrations/*.php; do php "$f"; done`
6. **Run the system check:** sign in and open `/system_check.php`.
7. **Schedule the cron jobs:**
   - `php cron_backup.php` nightly
   - `php cron_messages.php` every 15 minutes

This procedure was last checked on 2026-10-17 on an empty MySQL database: the
schema loaded, the install tool ran, and all the migrations ran clean.

## Recovery (restoring the live system)

The real recovery path is **restoring a backup**. Restoring brings back the data,
the schema and the settings together.

- **Nightly dump:** `cron_backup.php` writes a full SQL dump to `_backups/` at 03:00
  and keeps 14 days. **These live on the same server**, so they don't survive
  losing the server.
- **Off-site copies:**
  - The owner's office NAS pulls every nightly dump over SFTP at 04:00 and keeps
    30 days. It uses a key-only, file-transfer-only application user, with no shell.
  - Cloudways' own scheduled server backups (Cloudways panel → Backups).
  - Manual full downloads from **Master Admin → Backup & Restore**.
- **To restore:**
  1. Set up the server, the code and `.env` as in steps 1 and 3 of Fresh install.
     The database must be **MySQL 8**.
  2. Load the newest dump: `mysql -u <user> -p <db> < auto-daily-….sql`. Or, on a
     running site, use **Master Admin → Backup & Restore → Restore**, which takes
     its own snapshot first.
  3. Run the migrations, because the backup may predate the code:
     `for f in setup/migrations/*.php; do php "$f"; done`
  4. Sign in and run `/system_check.php`.
- **Not in the database:** uploaded files (logos, choice images) in `uploads/`.
  Back these up separately.

### Restore drill (proving a backup works, without touching live)

Requirements:
- PHP 8.2 CLI with `mysqli`.
- A local MySQL 8 or MariaDB server, using root with no password, or with
  `DB_HOST`, `DB_USER` and `DB_PASS` set.
- The `mysql` client on the PATH, or `MYSQL=…`.

It reads no `.env` and refuses any database host that isn't local.

```sh
php tests/tools/restore_drill.php /path/to/auto-daily-2026-10-17-030002.sql
```

What it does:
1. Loads the dump into a scratch `yb_restore_drill` database.
2. Runs every migration.
3. Compares every table and column with `tests/schema.sql`.
4. Counts the key rows and checks that the factory company exists.
5. Re-prices every saved quote line's base price with the current engine.
6. Drops the scratch databases, because the dump holds real data. Pass `--keep` to
   look around first.

Exit code 0 means the drill passed. Expected output, from the drill run on 2026-10-17
(the 13:15 live download, on XAMPP MariaDB 10.4):

```
PASS 1. Loaded: 110 tables in 85s (MariaDB: collation swapped for the drill)
PASS 2. Migrations: 123 of 123 ran clean
PASS 3. Schema: every table and column the code expects is present
     (info) only in the backup: tour_events.…   ← creates itself on first use; harmless
PASS 4. Data: 56 companies, 55 users, 1020 products, 15 quotes, 16 quote lines, 17 customers, 10 invoices; factory company #3 present
PASS 5. Re-price: 16 saved quote line(s); 16 match to the penny
6. Scratch databases dropped (the backup holds real data).
RESULT: PASS
```

How to read the result:
- **Step 1:** live dumps use MySQL 8's `utf8mb4_0900_ai_ci` collation. On MariaDB
  the drill swaps it while loading, and says so. A real restore must go onto
  MySQL 8.
- **Step 5:** if price lists have changed since the backup was taken, some lines
  will differ. They're listed for information, not counted as failures.
- **If any line says FAIL:** don't trust that backup until you know why. Try the
  previous night's dump.

Run the drill after any big schema change, and at least once a quarter.

## Tests

```sh
php tests/run.php                 # full suite: builds a throwaway database (yb_ci) on 127.0.0.1
php tests/run.php --only=prices   # just the files whose name contains "prices"
```

GitHub Actions runs the full suite on every pull request (PHP 8.2 and MySQL 8). A
pull request is merged only when it passes. After adding a migration, run
`php tests/tools/refresh_schema.php` so `tests/schema.sql` stays current.

## Deploying

- `main` is live. Cloudways pulls from GitHub on every merge.
- Work on a branch and open a pull request, then merge when the tests pass.
- After a merge that adds a migration, run it on the live site as the super-admin:
  `https://yourblinds.uk/setup/migrations/migrate_<name>.php`. It asks you to
  confirm.

## Conventions

- **Every page** starts with `require_once __DIR__ . '/…/bootstrap.php'`, then
  `auth/middleware.php` and the right `require…()` gate.
- **Every query** a company owns filters on `client_id`. Factory-side queries key
  on `current_factory_id()`.
- **Database access** uses PDO through `db()`, always parameterised.
- **Errors shown to people** go through `user_error_text($e)`, never
  `$e->getMessage()`, so raw database errors are never shown.
- **Secrets** live only in `.env`. The repository is **public**: never commit
  keys, dumps or `.env`.

## Troubleshooting

- **A blank page or a 500 error:** set `APP_ENV=development`, or read the PHP
  error log.
- **"a database problem (reference AB12CD)":** search the PHP error log for
  `[YourBlinds err AB12CD]`.
- **`Class … PHPMailer … not found`:** run `composer install`.
- **`SQLSTATE[HY000] [1045]`:** `DB_USER` / `DB_PASS` in `.env` are wrong.

## Contact

Owner: John Beverley.
