# setup/

One-off scripts that build and adjust the database. Each has been run once on the
live site; they're kept as the record of how the schema came to be and for setting
up a new installation (e.g. another factory).

- `migrations/` — `migrate_*.php`: add tables/columns. Idempotent (safe to re-run).
- `seeds/` — `seed_*.php` (+ `seed_data/`): load product options, build rules, test data.
- `tools/` — other one-offs (key rotation, label fixes, layout checks, grant super-admin).

Run one in the browser as the super-admin, e.g.
`https://yourblinds.uk/setup/migrations/migrate_remakes.php` (it asks you to confirm),
or from the command line: `php setup/migrations/migrate_remakes.php`.

New migrations go in `migrations/`; include app files with `dirname(__DIR__, 2) . '/…'`.
