# YourBlinds Quoting App

Internal trade-blinds quoting application. PHP 8 + MySQL, no JS framework — server-rendered pages, vanilla JS where needed, custom CSS.

## Modules

- **Auth** — login, logout, forgot/reset password
- **Customer manager** — CRUD over trade customers
- **Quote builder** — create/edit quotes, add line items, live pricing preview
- **Quote history** — list and view past quotes, public share link
- **Pricing engine** — JSON APIs for suppliers, fabrics, colours
- **Admin** — settings, users, pricing tables
- **Calendar** — monthly view, book/view appointments, route planning via Google Maps

## Prerequisites

- **PHP 8.0+** (CLI + web SAPI)
- **MySQL 5.7+ or 8.x**
- **Composer** ([getcomposer.org](https://getcomposer.org))
- A local web server. Anything that can serve PHP works — XAMPP, Laragon, or PHP's built-in server (`php -S`).
- An **AuthSMTP** account (or any SMTP server) for outbound email — only needed for password reset and quote sending.
- A **Google Maps API key** with the JavaScript API + Directions API enabled — only needed for the calendar's route planning. The app runs without it; that feature just goes dark.

## Setup

### 1. Clone

```sh
git clone https://github.com/JohnBeverley69/yourblinds-quoting-app.git
cd yourblinds-quoting-app
```

### 2. Install PHP dependencies

```sh
composer install
```

This populates `vendor/` with PHPMailer and dompdf.

### 3. Create the database

In MySQL, create an empty database and a user with full access to it:

```sql
CREATE DATABASE yourblinds CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'yourblinds_app'@'localhost' IDENTIFIED BY 'pick-a-password';
GRANT ALL PRIVILEGES ON yourblinds.* TO 'yourblinds_app'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Load schema and seed data

```sh
mysql -u yourblinds_app -p yourblinds < database/install.sql
mysql -u yourblinds_app -p yourblinds < database/seed.sql
```

`install.sql` is idempotent (safe to re-run); it drops in dependency order then re-creates all tables. `seed.sql` populates initial reference data (suppliers, fabrics, default pricing).

### 5. Run the calendar migration

The calendar module added tables and columns after the initial schema. Run once:

```sh
php migrate_calendar.php
```

It's also idempotent — safe to re-run anytime.

### 6. Configure environment

Copy the template and fill in real values:

```sh
cp .env.example .env
```

Edit `.env` and set:

- `DB_*` — match the database/user you created in step 3
- `MAIL_*` — your SMTP credentials (AuthSMTP or similar)
- `GOOGLE_MAPS_API_KEY` — only if you want calendar route planning
- `APP_ENV=development` while you're working locally (turns on error display)

`.env` is gitignored — never commit it.

### 7. Serve the app

Pick one:

**Built-in PHP server** (easiest for local dev):
```sh
php -S localhost:8000
```
Then open http://localhost:8000/auth/login.php

**XAMPP / Laragon / Apache**: drop the repo into your web root (`htdocs/yourblinds`) and visit http://localhost/yourblinds/auth/login.php. The included `.htaccess` blocks public access to sensitive folders (`database/`, `.env`, etc.).

### 8. Log in

The first admin user is created by `seed.sql`. Check the seed file for the username and initial password, then change the password immediately via **Admin → Users**.

## Project layout

```
admin/             Admin pages (products, settings, users)
auth/              Login, logout, password reset, middleware
calendar/          Monthly view, book/view appointments, today's run
customer-manager/  Customer CRUD
master-admin/      Cross-tenant admin (super-admin only)
pdf-generator/     Quote PDF rendering (dompdf)
quote-builder/     New/edit quote, add blinds, live-price API
quote-history/     List, view, public share link
_partials/         Shared includes (sidebar, pricing engine, parsers)
vendor/            Composer dependencies (gitignored)
uploads/           Runtime user uploads, e.g. company logos
bootstrap.php      Loaded by every page — env, sessions, DB
db.php             PDO factory exposed as db()
mailer.php         PHPMailer wrapper
migrate_*.php      One-off migration scripts
.env.example       Template — copy to .env
.htaccess          Blocks public access to sensitive paths
```

## Conventions

- **Every page** starts with `require_once __DIR__ . '/../bootstrap.php';` (adjust depth). Bootstrap loads `.env`, configures sessions, and exposes `db()`.
- **Authenticated pages** include `auth/middleware.php` after bootstrap to enforce login.
- **DB access** uses PDO via `db()` — always parameterised queries, never string concatenation.
- **Secrets** live in `.env` only. Never hardcode credentials, never commit `.env`.
- **Database dumps** (`*_dump*.sql`, `live_dump*.sql`) are gitignored — keep production data out of the repo.

## Workflow

- `main` is the live branch — pushes here go to production.
- For non-trivial changes, work on a branch and open a PR for review before merging.
- Run any new migration scripts on the live database after merging.

## Troubleshooting

- **Blank page / 500 on first load** — set `APP_ENV=development` in `.env` to see the error, or check the PHP error log.
- **`Class PHPMailer\PHPMailer\PHPMailer not found`** — you skipped `composer install`.
- **`SQLSTATE[HY000] [1045] Access denied`** — `DB_USER` / `DB_PASS` in `.env` don't match the MySQL user from step 3.
- **Calendar map is blank** — `GOOGLE_MAPS_API_KEY` missing or the key doesn't have Maps JavaScript API + Directions API enabled in Google Cloud Console.

## Contact

Owner: John Beverley. Open an issue or ping John directly with questions.
