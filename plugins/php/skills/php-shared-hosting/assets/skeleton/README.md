# PHP 8.4 skeleton for shared hosting

A modern PHP site that works without SSH, without changing the document root and without tools on the server: it is built locally and uploaded via FTP. PSR stack (PSR-7/11/15/17/20) with Twig, Doctrine DBAL and `mezzio-session` sessions.

## Structure

```
document root/            ← coincides with the project root
├── index.php             the only public PHP file (syntax compatible with any PHP)
├── .htaccess             routing + multi-level security blocks
├── .user.ini             PHP settings (PHP-FPM/CGI)
├── assets/               static files; .htaccess prevents script execution
└── app/                  everything else — blocked by app/.htaccess and by the rule in .htaccess
    ├── bootstrap.php     PHP environment, errors → exceptions, container
    ├── web.php           HTTP pipeline
    ├── bin/              migrate.php (cron), build.php, check-exposure.php, dev-router.php
    ├── config/           settings.php (defaults), settings.local.php (not versioned), container, routes
    ├── migrations/       numbered migrations
    ├── src/  templates/  tests/
    └── var/              writable: SQLite, logs, sessions, Twig cache
```

If the hosting allows uploading files above the document root, move `app/` there. Then change `$appDir` in `index.php` and set `publicDir` in `settings.local.php`: it is the most secure configuration.

## Local development

```bash
cd app
cp config/settings.local.php.dist config/settings.local.php   # set debug/https for localhost
composer install
composer migrate
composer serve        # http://localhost:8080 (router that mimics .htaccess)
composer check        # style + PHPStan max + tests
```

In `composer.json` the `config.platform.php` entry must match the server's PHP version. It makes Composer choose dependencies compatible with the server, not with your computer.

## Deploy via FTP

1. `cd app && composer build`: creates `build/` with no-dev dependencies and an optimized autoloader.
2. Upload the contents of `build/` to the document root, **without** deleting remote files. `app/var/` and `app/config/settings.local.php` on the server must not be touched.
3. Only on the first deploy: create `app/config/settings.local.php` on the server with the database details and check that `app/var/` is writable.
4. Migrations: a cron job from the panel, for example every 5 minutes, with the full path of PHP 8.4:
   `/path/php84 /home/user/public_html/app/bin/migrate.php`
   The script is idempotent and silent when there is nothing to apply.
5. From your computer: `php app/bin/check-exposure.php https://www.example.com` must report "No private files exposed".

## Adding a page

1. A `final readonly` handler that implements `RequestHandlerInterface` and uses `View::render()`.
2. A route in `config/routes.php`.
3. A template in `templates/` that extends `layout.html.twig`. Use `path()` for links, `asset()` for assets, and `csrf_field()` in POST forms.

## Subfolder

If the site lives in `example.com/sito/`, links and assets adapt on their own (`BasePathMiddleware`). In `.htaccess` update the lines marked `[SUBFOLDER]`, i.e. `RedirectMatch 404 ^/sito/app(/|$)` and `FallbackResource /sito/index.php`.
