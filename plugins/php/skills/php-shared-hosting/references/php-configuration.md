# Configuring PHP without server access

## Contents
1. FPM/CGI or mod_php?
2. Three configuration levels
3. What can be changed and where
4. PHP version: site, cron and Composer
5. Disabled functions and limits

## 1. FPM/CGI or mod_php?

The configuration mechanism depends on how the hosting runs PHP:

| SAPI (`php_sapi_name()`) | `.user.ini` | `php_value` in `.htaccess` |
|---|---|---|
| `fpm-fcgi`, `cgi-fcgi` (the vast majority of current hosting, including LiteSpeed with LSAPI → `litespeed`) | read | **500 error** |
| `apache2handler` (mod_php, now rare) | ignored | read |

To find out: create a temporary file with an unguessable name, for example `sapi-8f3k2.php`, that prints `php_sapi_name()`. Open it in the browser and **delete it immediately**. Avoid a full `phpinfo()` in public files: it exposes paths, versions and environment variables.

LiteSpeed with LSAPI (`litespeed`) reads both `.user.ini` and `php_value`: use `.user.ini` anyway for consistency.

## 2. Three configuration levels

1. **Panel (php.ini or options selector)**: when it exists, it is the most reliable level. It often allows only a subset of directives.
2. **`.user.ini` in the document root**: applies to scripts executed in that folder and its subfolders. It is re-read every `user_ini.cache_ttl` seconds (default 300), so changes are not immediate.
3. **`ini_set()` at runtime** in `bootstrap.php`: works for `PHP_INI_ALL` directives everywhere and regardless of the SAPI. The skeleton sets here what matters for security (`display_errors`, `error_log`, all `session.*`), so it does not depend on the first two levels.

The skeleton uses all three: `.user.ini` for values needed before the script runs (upload limits, `display_startup_errors`), `ini_set()` for the rest.

## 3. What can be changed and where

| Directive | Level | Notes |
|---|---|---|
| `display_errors`, `log_errors`, `error_log`, `error_reporting` | ALL | also with `ini_set` |
| `session.*` (save_path, cookie_*, gc_*, use_strict_mode) | ALL | before starting the session |
| `date.timezone` | ALL | the skeleton uses `date_default_timezone_set()` |
| `memory_limit`, `max_execution_time` | ALL | the hosting may impose a lower ceiling |
| `upload_max_filesize`, `post_max_size` | PERDIR | only `.user.ini`/panel: they are needed before the script starts |
| `max_input_vars` | PERDIR | very large forms |
| `expose_php`, `opcache.*` (almost all), `disable_functions`, `open_basedir`, `allow_url_include` | SYSTEM | not modifiable: if you need them, ask the hosting |

To check the effective value use `ini_get()`. To find out whether a value is modifiable, `ini_get_all(null, false)` returns the values, while `ini_get_all()` also reports the access level. If `ini_set()` returns `false`, the setting is locked: the skeleton throws an exception for sessions instead of continuing with insecure values.

## 4. PHP version: site, cron and Composer

Three points must match:
1. **The site's version**, chosen in the panel. Sometimes it is set per folder, and the panel writes a block into `.htaccess` that must not be deleted.
2. **The cron's PHP**: the cron's `php` command is often the system default version, not the site's. Use the full path indicated by the panel, for example `/usr/local/bin/php84`, `/opt/cpanel/ea-php84/root/usr/bin/php` or `/usr/bin/php8.4`. With the wrong version Composer's autoloader stops with a message that arrives in the cron email.
3. **`config.platform.php` in `composer.json`**: the version Composer uses to resolve dependencies locally. It must be equal to or lower than the server's, otherwise Composer may install packages that do not work on the server.

When you change the PHP version from the panel: update `config.platform.php`, regenerate the build, upload the whole `vendor/` folder.

**Extensions**: `composer.json` declares the required ones (`ext-mbstring`, `ext-pdo`, `ext-session`, …). If one is missing on the server, Composer's `platform_check.php` reports it. Also check that the right PDO driver is active (`pdo_mysql` or `pdo_sqlite`): it is usually enabled from the panel's extension selector.

## 5. Disabled functions and limits

Many hosting providers disable via `disable_functions`:
- `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`: code running on the server must not depend on them. The build scripts use `proc_open`, but only locally.
- `mail()` is sometimes active but with limits or without SPF/DKIM: send emails via SMTP (see `sessions-authentication.md`).
- `symlink`, `set_time_limit`, `ini_set` (rarely).

Check with `function_exists('name')`: since PHP 8.0 disabled functions appear as non-existent.

Other typical limits:
- **Execution time** 30–60 s and memory 128–256 MB: long operations must be split into batches run by the cron.
- **`open_basedir`**: limits accessible files to the user's home, and sometimes to the document root only. In that case structure A does not work: the site responds with "open_basedir restriction in effect" errors.
- **Processes and inodes**: avoid generating thousands of small files, for example cache without cleanup or sessions without garbage collection.
