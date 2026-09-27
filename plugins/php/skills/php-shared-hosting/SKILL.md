---
name: "php-shared-hosting"
description: Design, develop and publish modern PHP (8.4+) sites and applications on shared hosting without SSH and without the ability to change the document root — secure folder structure, multi-level .htaccess, front-controller routing, PHP settings via .user.ini, local build and FTP deploy, migrations via cron, sessions and CSRF, file exposure checks. Use it whenever PHP code must run on shared hosting, cPanel, Plesk, Aruba, SiteGround, Register or similar, when the user mentions FTP, public_html, .htaccess or the lack of SSH, or when you review a PHP template or site meant for this kind of hosting — i.e. for any PHP project deployed to shared hosting without shell access.
---

# Modern PHP on shared hosting

For all rules about the code (types, security, exceptions, DI, tools) the `modern-php` skill applies: read it if it is available. This skill only adds what changes when the server is a shared hosting: **no shell, fixed document root, limited PHP configuration, deploy via FTP**.

## Constraints to start from

- **No CLI on the server** except, often, the **panel's cron**. Composer, migrations and builds run locally or via cron, never over SSH.
- **The document root coincides with the project root**, unless the user confirms they can upload files above it. Everything in the document root is potentially reachable from the web.
- **Partial PHP configuration**: `.user.ini` only with PHP-FPM/CGI, `php_value` in `.htaccess` only with mod_php (with FPM it produces a 500 error), `PHP_INI_SYSTEM` directives cannot be changed. Some settings are applied at runtime with `ini_set()`.
- **Apache or LiteSpeed with `.htaccess`** in almost all cases. If the hosting uses nginx only, `.htaccess` is ignored: the only safe structure is the one with `app/` above the document root.
- **PHP version selectable from the panel**, but the `php` used by cron may differ from the site's.

## Binding rules

1. **Whitelist, not blacklist.** In the document root only `index.php`, `.htaccess`, `.user.ini` and `assets/`. Everything else (code, `vendor/`, configuration, templates, database, logs, sessions) lives in `app/`, blocked by `app/.htaccess` (`Require all denied`) and by a second rule in the main `.htaccess`. Listing the folders to block is the typical mistake: forgetting one is enough, and it is usually the one with the database or the secrets.
2. **If the hosting allows it, `app/` goes above the document root.** It is the only protection that does not depend on the web server. The skeleton's structure allows it by changing one line.
3. **Configuration and secrets in `.php` files that return arrays**, never YAML, `.env`, JSON or INI inside the document root. If a protection failed, the server would execute the PHP file instead of displaying it. The local file (`settings.local.php`) is not versioned and is created only once on the server.
4. **A single front controller** (`index.php`) with syntax compatible with any PHP. It loads Composer's autoloader first, whose `platform_check.php` clearly reports a PHP version that is too old. The 8.4 code is loaded only afterwards.
5. **`vendor/` is built locally** with `config.platform.php` equal to the server's version, `--no-dev` and `--classmap-authoritative`. `composer.lock` is versioned.
6. **The deploy does not touch the server's data**: `app/var/` and `app/config/settings.local.php` are excluded from the build and from synchronization (no "delete remote files" without exclusions).
7. **Idempotent migrations with a lock**, runnable from cron (`bin/migrate.php`, silent when there is nothing to do). No maintenance web endpoint: it is extra attack surface. Alternatively: SQL generated locally and imported from phpMyAdmin.
8. **Sessions in `app/var/sessions`** with `Secure`, `HttpOnly`, `SameSite=Lax` cookies, `use_strict_mode` and garbage collection enabled. The default folder may be shared with other customers of the hosting.
9. **Twig with `autoescape: 'html'`** and `auto_reload: true` (the cache cannot be cleared on every deploy). Error details only with `debug` enabled, never `$e->getMessage()` to the user.
10. **CSRF on every state-changing method**, per-session token compared with `hash_equals`, verified after routing (so 404/405 stay correct).
11. **URLs independent of the installation folder**: links and assets generated through the base path (`path()`, `asset()`), never relative paths like `href="assets/…"`, which break with nested routes.
12. **No dependency on functions that are often disabled** (`exec`, `shell_exec`, `proc_open`, configured `mail`, `symlink`) in code that runs on the server. For emails use SMTP with credentials from the panel.
13. **Verify after every deploy** with `bin/check-exposure.php <url>` from your own computer: every private path must respond 403 or 404.

## Workflow

**New project** → copy `assets/skeleton/` and follow its `README.md`. Before writing code ask the user, if it is not already clear:
- whether they can upload files above the document root;
- which database the hosting offers (MySQL/MariaDB or SQLite only);
- the selectable PHP version;
- whether the site goes in a subfolder;
- whether the panel offers cron.

**Review of an existing project** → in this order:
1. **File exposure**: list the contents of the document root and check, file by file, what is reachable. If possible, actually test it: local Apache, or `check-exposure.php` on the published site.
2. **Secrets**: where they are, in what format, whether they are versioned.
3. **Deploy**: how `vendor/` gets there, which PHP version is locked, what gets overwritten on the server.
4. **PHP configuration**: displayed errors, sessions, any `php_value` with FPM.
5. **Code**, according to the `modern-php` skill.

**Changes to `.htaccess`** → after every change verify both routing and blocks (`check-exposure.php`); a directive not permitted by the hosting produces a 500 on the whole site.

## References

| File | When to read it |
|---|---|
| `references/htaccess-structure.md` | why frameworks use `public/`, folder structure, how Apache tells a file from a route, `.htaccess` line by line, Apache/LiteSpeed/nginx, subfolders, routing without mod_rewrite, 500 errors |
| `references/php-configuration.md` | figuring out whether the hosting uses FPM or mod_php, `.user.ini`, `ini_set`, changeable directives, cron's PHP version, disabled functions |
| `references/deploy-ftp.md` | local build, what to upload and what not, first deploy, permissions, updates, rollback, FTP vs SFTP |
| `references/database-cron.md` | SQLite vs MySQL, migrations via cron or phpMyAdmin, backups, scheduled jobs |
| `references/sessions-authentication.md` | isolated sessions, CSRF, password login, file uploads, sending email |

## Skeleton

`assets/skeleton/` is a working site that contains:
- front controller and multi-level `.htaccess`;
- sessions, flash messages and CSRF;
- Twig with automatic escaping;
- DBAL with portable SQLite/MySQL migrations;
- HTML error pages and security headers;
- subfolder support;
- `build`, `migrate` and `check-exposure` scripts.

It is verified with PHPUnit 13, PHPStan at the maximum level, PHP-CS-Fixer PER-CS 3.0 and on Apache 2.4 with PHP-FPM 8.4 and MariaDB 10.11, even without mod_rewrite or without mod_alias. The example contact form shows the complete flow: form → validation → CSRF → database → flash → redirect. Remove it when it is no longer needed (`src/Contact/`, route, template, migration).
