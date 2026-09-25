# Folder structure and `.htaccess`

## Contents
1. Why frameworks use `public/`
2. Two possible structures
3. File or route: who decides
4. Main `.htaccess`, block by block
5. `app/.htaccess` and `assets/.htaccess`
6. Web servers: Apache, LiteSpeed, nginx
7. Site in a subfolder
8. Routing without mod_rewrite
9. 500 errors after a change

## 1. Why frameworks use `public/`

The web server can serve only what is inside the document root. If the document root is `public/`, code, configuration, `vendor/`, `.env`, logs and database are physically out of its reach: no rule protects them, there simply is no URL that reaches them. This withstands the failures that make rules useless:
- `.htaccess` ignored (`AllowOverride None`, switch to nginx, file deleted during a deploy);
- broken PHP handler (version change from the panel, wrong configuration): the server delivers `.php` files as text. With `public/` the only exposed source is `index.php`. Without it, everything leaks, including configuration in `.php` files, which only protects when access rules fail but PHP works;
- `vendor/` reachable: it contains executable files not meant for the web. The best-known case is `phpunit/src/Util/PHP/eval-stdin.php` (CVE-2017-9841), still probed en masse by scanners;
- forgotten files: `.env`, SQL dumps, `.git/`, backups.

With the front controller all requests go through `index.php`, so the document root needs nothing but that file and the assets. Structure A below is the same model; B imitates it with whitelists and blocks, and for this reason must always be verified. On cPanel and Plesk the main domain's document root is often fixed, but that of **subdomains and addon domains can usually be chosen**: if the site can live on one of these, point it to a folder like `.../sito/public` and get the framework structure without compromises.

## 2. Two possible structures

**A — `app/` above the document root** (preferred when the hosting allows it via FTP):
```
/home/user/
├── app/                 code, vendor, configuration, data: out of the web server's reach
└── public_html/         document root
    ├── index.php        $appDir = dirname(__DIR__) . '/app';
    ├── .htaccess
    ├── .user.ini
    └── assets/
```
With this structure set `publicDir` in `settings.local.php` to the path of `public_html`, and move the `RedirectMatch 404 ^/app` rule because it is no longer needed (it is harmless if it stays).

**B — everything in the document root** (when you cannot go outside `public_html`, or do not know whether you can):
```
public_html/
├── index.php            $appDir = __DIR__ . '/app';
├── .htaccess            blocks app/ (second line)
├── .user.ini
├── assets/
└── app/
    └── .htaccess        Require all denied (first line)
```
In B security depends on the web server reading `.htaccess`: always verify it with `check-exposure.php`.

How to tell whether A is possible: with the FTP client, after logging in, go up one level from `public_html` (or `httpdocs`, `www`, `htdocs`). If you see the user's home and can create folders, A is possible. Some hosting providers start FTP directly at the document root: in that case B remains.

## 3. File or route: who decides

The distinction between `/assets/css/app.css` (file) and `/api/v1/users` (route) is not made by the router: it is made by **Apache, before PHP**, based on one thing only, namely whether a file exists on disk at that path.

- `GET /assets/css/app.css` → Apache looks for `…/public_html/assets/css/app.css`, the file exists and it serves it directly. PHP is not executed.
- `GET /api/v1/users` → `…/public_html/api/v1/users` does not exist → `RewriteCond %{REQUEST_FILENAME} !-f` is true → Apache internally executes `index.php`, with no redirect visible to the browser. The original URL stays in `REQUEST_URI`, Diactoros builds the request, FastRoute matches it against `config/routes.php` and executes the handler or responds 404.

Routes are therefore **URLs that do not exist as files**, entries in FastRoute's table. Three consequences follow:
- **if a file and a route have the same path, the file wins**: only `index.php` and `assets/` must be in the document root;
- **every `.php` file present in the document root is directly executable**, without routing or middleware (no CSRF, session, error handling): the only public PHP file must be `index.php`;
- **the files inside `app/` exist**, and only the access rules, evaluated before serving the file, prevent their delivery. This is the delicate point of structure B.

`FallbackResource` behaves the same way: it kicks in only if the file does not exist. In development `bin/dev-router.php` redoes the same check by hand, because the built-in server ignores `.htaccess`. The `RewriteRule ^assets/ - [L]` rule excludes all paths under `assets/` from being passed to PHP: a missing asset gets Apache's 404 without booting the application. Without mod_rewrite, i.e. with `FallbackResource`, it gets it from the application instead.

## 4. Main `.htaccess`, block by block

```apache
Options -Indexes
DirectoryIndex index.php
AddDefaultCharset UTF-8
```
No file listing in folders without an index; `index.php` as the default page.

```apache
<FilesMatch "^\.">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>
```
Blocks files whose name starts with a dot: `.htaccess`, `.user.ini`, `.env`, `.gitignore`. The double syntax covers Apache 2.4 (`mod_authz_core`) and servers that emulate 2.2.

```apache
<IfModule mod_alias.c>
    RedirectMatch 404 ^/app(/|$)
    RedirectMatch 404 /\.(?!well-known/)
</IfModule>
```
- The first rule is the second line of defense for `app/`: if `app/.htaccess` were deleted by mistake during a deploy, the folder would still remain inaccessible. It responds 404 rather than 403 so as not to confirm that the folder exists.
- The second blocks any path with a hidden segment, such as `/.git/config`, which `FilesMatch` does not catch because there the file name is `config`. The exception is `/.well-known/`, which is needed for Let's Encrypt certificates and other standards.

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^app(/|$) - [R=404,L]
    RewriteRule (^|/)\.(?!well-known/) - [R=404,L]
    RewriteRule ^assets/ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L,QSA]
</IfModule>
<IfModule !mod_rewrite.c>
    FallbackResource /index.php
</IfModule>
```
The first two rules repeat the `mod_alias` blocks, so it is enough for either module to be active. The rest sends every request that does not match an existing file to the front controller, which does the routing.

Verified on Apache 2.4 by disabling the modules one at a time:

| Active modules | `/.git/config` (real folder) | `app/` | Routing |
|---|---|---|---|
| alias + rewrite | 404 | 403 | ok |
| rewrite only | 404 | 403 | ok |
| alias only | 404 | 403 | ok (FallbackResource) |
| neither | **served** | 403 | ok (FallbackResource) |

Without either module, which is almost impossible on real hosting, hidden folders remain exposed: `FilesMatch` blocks only file names that start with a dot. It is one more reason never to upload `.git/` to the server (the build excludes it). For a non-existent path such as `/.git/config` without the folder, Apache still responds 403: it treats `.git` as the file name and applies `FilesMatch` to it. The `!-d` condition is not needed: existing folders without an `index` would end up in 403 anyway, and it is better for the application to respond 404.

**What not to put in**: `php_value`/`php_flag` (500 error with PHP-FPM, see `php-configuration.md`), `Options +FollowSymLinks` (often forbidden), `<Directory>` (not allowed in `.htaccess` files).

**Blocks added by the panel**: cPanel and others insert sections such as `# php -- BEGIN cPanel-generated handler` to choose the PHP version. Do not delete them when you replace the file: copy them to the top of the new `.htaccess`.

## 5. `app/.htaccess` and `assets/.htaccess`

- `app/.htaccess` contains only `Require all denied` (with the 2.2 variant). It is the main protection for the whole folder and its subfolders.
- `assets/.htaccess` denies executable files (`.php`, `.phtml`, `.phar`, `.cgi`…). If by mistake or through an upload a PHP file ended up in `assets/`, it would not be executed. It also contains the long cache for assets: the URLs generated by `asset()` have `?v=<modification date>`, so the browser downloads the new version after every upload.
- Any folder where the application saves user-uploaded files goes in `app/var/` and the files are served through a handler, or goes inside `assets/` protected in the same way. Never an upload folder where PHP can be executed.

## 6. Web servers: Apache, LiteSpeed, nginx

| Server | `.htaccess` | Notes |
|---|---|---|
| Apache 2.4 | read if `AllowOverride` allows it (on hosting usually `All`) | reference for the skeleton |
| LiteSpeed Enterprise | compatible with the directives used (`RewriteRule`, `Require`, `FilesMatch`, `RedirectMatch`) | very widespread in shared hosting; changes to `.htaccess` sometimes apply after a few seconds |
| OpenLiteSpeed | partial support, rewrites reloaded only on restart | rare in shared hosting |
| nginx (without Apache) | **ignored** | requires structure A or rules configured by the hosting; without either the site is not secure |
| nginx + Apache (proxy) | read by Apache | common configuration: nginx serves static files, Apache runs PHP |

In any case, the outcome is verified with `check-exposure.php`, not inferred from the hosting's documentation.

## 7. Site in a subfolder

With the site at `example.com/sito/`:
- the application adapts by itself: `BasePathMiddleware` derives the prefix from `SCRIPT_NAME` and strips it before routing; `path()` and `asset()` add it to links;
- in `.htaccess` update `RedirectMatch 404 ^/sito/app(/|$)` and `FallbackResource /sito/index.php`;
- `RewriteRule ^ index.php` works without changes, because in `.htaccess` files the relative substitution is resolved relative to the folder;
- run `check-exposure.php https://example.com/sito`.

## 8. Routing without mod_rewrite

In order of preference:
1. `mod_rewrite` (almost always present);
2. `FallbackResource` (mod_dir, Apache ≥ 2.2.16): same result with a single line;
3. URLs with PATH_INFO: `/index.php/contatti`. `BasePathMiddleware` already recognizes them; links must be generated with the `/index.php` prefix (configuration to be added only if really necessary);
4. query string parameter (`/?r=/contatti`): last resort, worsens URLs and SEO.

## 9. 500 errors after a change

A 500 on the whole site right after uploading a `.htaccess` almost always indicates a directive not permitted by the hosting. Proceed as follows:
1. check the panel's error log: it reports the name of the rejected directive;
2. comment out the blocks one at a time (first `Options`, then any `php_value`, then `mod_expires`);
3. reload and try again.

Always keep a copy of the working `.htaccess` before modifying it.
