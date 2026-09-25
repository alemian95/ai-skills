# Local build and deploy via FTP

## Contents
1. Principle
2. Build
3. First deploy
4. Updates
5. What never to upload
6. Permissions
7. Protocol and tools
8. Rollback

## 1. Principle

Nothing runs on the server: no `composer install`, no asset build, no commands. The server receives a ready-made copy, built locally in a reproducible way:
- `composer.lock` under version control;
- `config.platform.php` equal to the server's version;
- the same build for every environment, with only the configuration values differing, in `settings.local.php`.

## 2. Build

```bash
cd app
composer check          # style, static analysis, tests: code that does not pass them is not published
composer build          # → ../build/
```

`bin/build.php`:
- copies the project into `build/` excluding `vendor/`, tests, development tool configurations, `settings.local.php` and the contents of `app/var/` (only the folders with their `.gitignore` remain);
- runs `composer install --no-dev --classmap-authoritative` inside `build/app`, with the arguments passed as an array to `proc_open` (no shell).

If the project has assets to compile (CSS/JS with Vite, esbuild…), add that step before the copy and put only the compiled files in `assets/`.

## 3. First deploy

1. In the panel:
   - choose PHP version 8.4 for the domain;
   - enable the required extensions (`pdo_mysql` or `pdo_sqlite`, `mbstring`, `intl` if used);
   - create the MySQL database and user, if needed.
2. Upload the contents of `build/` into the document root. Check that the hidden files have arrived too (`.htaccess`, `.user.ini`, `app/.htaccess`, `assets/.htaccess`): some FTP clients hide them.
3. Create `app/config/settings.local.php` on the server starting from `settings.local.php.dist`: database details, `debug => false`. Create it directly on the server with the panel's file manager, or upload it and then delete it from your computer: it must not end up in the repository.
4. Check that `app/var/` and its subfolders are writable by PHP (see §6).
5. Configure the migrations cron (see `database-cron.md`) and wait for the first run, or import the schema from phpMyAdmin.
6. From your computer: `php app/bin/check-exposure.php https://www.example.com`.
7. Open the site and check the panel's error log and `app/var/log/`.

## 4. Updates

1. `composer build`.
2. Upload `build/` **overwriting** the existing files but **without deleting** the remote ones that are missing locally. The exceptions, which must never be overwritten, are `app/var/` and `app/config/settings.local.php`, because they are not in the build.
3. If `composer.lock` has changed, upload `app/vendor/` in full. A half-uploaded `vendor/` breaks autoloading: during the upload the site may throw errors for a few tens of seconds. To reduce downtime see §8.
4. New migrations are applied by the cron at its next run.
5. Files deleted from the project: delete them manually from the server too, or use a sync with deletion that excludes `app/var/` and `settings.local.php`.
6. `check-exposure.php` if you touched `.htaccess` or the structure.

The Twig cache updates itself (`auto_reload`). OPcache on shared hosting normally checks file timestamps; if a modified file is not reloaded, the cause is `opcache.validate_timestamps=0` set by the hosting: ask how to clear it, or restart PHP from the panel if the option exists.

## 5. What never to upload

- `.git/`, `.idea/`, `.vscode/`, `node_modules/`, `.DS_Store`;
- `app/tests/`, PHPUnit, PHPStan and PHP-CS-Fixer configurations;
- `.env` files, database dumps, backups (`*.sql`, `*.zip`, `*.bak`, `*~`) in the document root: they are among the first files automated scanners look for;
- the development `settings.local.php` in place of the production one;
- `phpinfo()` or diagnostic scripts left on the server.

## 6. Permissions

On shared hosting PHP normally runs as the user who owns the files (per-user PHP-FPM, suPHP, LSAPI). Consequently:
- files `0644`, folders `0755`: PHP can write into the folders because it owns them;
- `settings.local.php` `0600` or `0640` if the hosting allows it;
- **never `0777`**: on a shared server it means writable by other users, and some hosting providers refuse to run scripts in world-writable folders (500 error).

If PHP cannot write into `app/var/` even with `0755`, the hosting runs PHP as a user other than the owner: ask support how to handle writable folders instead of using `0777`.

## 7. Protocol and tools

- Use **SFTP** or **FTPS** (FTP over TLS). Plain FTP transmits credentials and files unencrypted.
- Graphical clients: FileZilla, Cyberduck, Transmit. Enable showing hidden files and comparison by date and size.
- From the command line, repeatable, with `lftp`:
  ```bash
  lftp -u user sftp://ftp.example.com -e "mirror --reverse --only-newer --verbose \
      --exclude-glob app/var/** --exclude-glob app/config/settings.local.php \
      build/ public_html/; quit"
  ```
  Add `--delete` only after verifying the exclusions with `--dry-run`.
- Some panels (cPanel "Git Version Control", Plesk Git) can deploy from a repository. This works only if `vendor/` is in the deploy repository, because Composer does not run on the server. Keep a separate repository or branch with the builds, not the source code with `vendor/`.

## 8. Rollback

- Keep previous builds locally (`build-YYYYMMDD/`): going back means re-uploading the previous one.
- Migrations do not undo themselves: a schema change incompatible with the previous version of the code requires a corrective migration. Design migrations so that the old code keeps working: first add columns and tables, remove only in a later deploy.
- For updates with substantial changes to `vendor/` downtime can be reduced like this:
  1. upload the new version into `app-new/`;
  2. via FTP rename `app/` to `app-old/` and `app-new/` to `app/`. The rename is almost instantaneous.
  3. copy the `var/` folders and `config/settings.local.php` from `app-old/` into `app/`, or, better, place `varDir` outside `app/` if structure A allows it.
