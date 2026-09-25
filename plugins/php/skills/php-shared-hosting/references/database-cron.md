# Database, migrations and cron

## Contents
1. SQLite or MySQL
2. Migrations
3. Running migrations without SSH
4. Cron: practical rules
5. Backup

## 1. SQLite or MySQL

| | SQLite | MySQL/MariaDB |
|---|---|---|
| Configuration | none: a file in `app/var/` | database and user from the panel |
| Concurrent writes | one at a time (lock on the file) | concurrent |
| Suitable for | sites with few writes: brochure sites, personal blogs, small forms | e-commerce, multi-user private areas, frequent writes |
| Management | file downloadable via FTP, to be copied for backup | phpMyAdmin, exports from the panel |
| Specific risk | the file **must** stay out of the web's reach (`app/var/`) | credentials in `settings.local.php` |

With SQLite the folder containing the file must be writable, not just the file: SQLite creates temporary journal files next to the database. There is no need to create the file in advance (no `touch` or `exec`): the first connection creates it.

On MySQL the skeleton uses `charset=utf8mb4`. Tables created by DBAL use the database's default encoding: create the database in `utf8mb4` (in phpMyAdmin collation `utf8mb4_unicode_ci` or `utf8mb4_0900_ai_ci`).

The ORM (Doctrine ORM) can be used, but without a CLI its schema management (`orm:schema-tool`) is awkward and risky in production. With this skeleton's explicit migrations the schema stays under control; DBAL offers a query builder and portability, often sufficient.

## 2. Migrations

- One file per migration in `app/migrations/`, named `YYYYMMDD_NNNN_description.php`, returning an anonymous class with `up(Connection $db)`.
- Applied versions are recorded in the `schema_migrations` table. Each file is applied only once, in alphabetical order, i.e. chronological.
- Forward only: no `down()`. In production a schema rollback is done with a new migration, never by modifying an already applied file.
- Compatibility with the previous code: add first, remove later (see `deploy-ftp.md` §8).
- SQLite/MySQL portability: DBAL's schema API (`Table::editor()`, `Column::editor()`) generates the right SQL for both. If the project uses only one database, hand-written SQL with `$db->executeStatement()` is simpler and equally valid.
- MySQL performs an implicit commit on DDL statements: a migration that fails halfway leaves partial changes. Keep migrations small: one schema change per file.
- Data migrations on large tables: in batches, respecting the cron's maximum execution time.

## 3. Running migrations without SSH

**Cron (recommended)** — in the panel:
```
*/5 * * * *   /path/php84 /home/user/public_html/app/bin/migrate.php
```
- `migrate.php` is idempotent: with no pending migrations it does nothing and prints nothing, so the cron generates no email.
- A file lock (`app/var/migrate.lock`) prevents overlapping runs.
- On error it writes to stderr and exits with code 1: the panel sends the output by email if an address is configured.
- After a deploy with new migrations, wait for the next run before using the features that require them, or temporarily set the cron to every minute.
- `--status` shows the status: useful as a one-off cron with the output sent by email.

**phpMyAdmin (alternative, MySQL only)** — generate the SQL locally against an empty database with the same MySQL/MariaDB version as the server, export it and import it from phpMyAdmin. Record the version manually in `schema_migrations`, otherwise the cron will reapply it. It is more error-prone: use it only if the cron is not available.

**Web maintenance endpoint** — discouraged: it is a permanent attack surface, to be protected with a token, rate limiting and access logging, and to be remembered to disable. If it really is the only way, it must be disabled by default, accept only POST with a long token compared via `hash_equals`, and must be removed immediately after use.

## 4. Cron: practical rules

- **Full path to the PHP** of the right version (see `php-configuration.md` §4) and absolute path to the script.
- The script must not depend on the current directory: the skeleton uses `__DIR__`.
- Cron scripts in `app/bin/`, not reachable from the web, with `if (PHP_SAPI !== 'cli') exit` as an additional defense.
- Output only when there are problems; application log in `app/var/log/`.
- Long jobs (sending newsletters, imports): process one batch per run and save progress in the database; never a single script that exceeds the maximum time.
- File lock for every job that must not overlap (`flock` with `LOCK_EX | LOCK_NB`).
- Periodic cleanup: old logs (Monolog `RotatingFileHandler` handles them), temporary files, expired records.

## 5. Backup

- Use the panel's backups, but do not rely on them alone: check that they include the database and try restoring at least once.
- **SQLite**: copy `app/var/database.sqlite` via FTP when the site is not writing, or from a cron with `VACUUM INTO '/path/backup.sqlite'`, which produces a consistent copy even while the site is live.
- **MySQL**: export from phpMyAdmin or from the panel; `mysqldump` from cron only if the hosting makes it available.
- Backups never go in the document root: a public `backup/` folder with `.sql` files is among the first things automated scanners look for.
