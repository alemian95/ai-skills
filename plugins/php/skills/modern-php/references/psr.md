# PHP-FIG standards (PSR and PER)

Sources: [php-fig.org/psr](https://www.php-fig.org/psr/), [PER Coding Style](https://www.php-fig.org/per/coding-style/), [PHP The Right Way — Code Style Guide](https://phptherightway.com/#code_style_guide).

## Contents
1. Why depend on standards
2. Status of the standards
3. Usage rules
4. Standards in frameworks
5. PSR-7/15 HTTP pipeline
6. Without a framework

## 1. Why depend on standards

The value of the PSRs is substitutability. The code depends on the `psr/*` interfaces and the implementation (Monolog, Diactoros, Nyholm, Guzzle, the framework's container) is chosen in a single place, the composition root. The same domain service that receives a `LoggerInterface` or a `ClockInterface` works unchanged in a script, in Laminas, in Symfony or in Laravel.

## 2. Status of the standards

September 2026. Before citing one, check its status on php-fig.org.

| Standard | Subject | Status |
|---|---|---|
| [PSR-1](https://www.php-fig.org/psr/psr-1/) | basic style: PHP tags, UTF-8, class/method/constant names | accepted |
| [PER Coding Style](https://www.php-fig.org/per/coding-style/) (3.x) | extended style | replaces PSR-12 |
| [PSR-12](https://www.php-fig.org/psr/psr-12/) | extended style | superseded by PER-CS |
| [PSR-3](https://www.php-fig.org/psr/psr-3/) | logger | accepted |
| [PSR-4](https://www.php-fig.org/psr/psr-4/) | autoload | accepted |
| [PSR-6](https://www.php-fig.org/psr/psr-6/) / [PSR-16](https://www.php-fig.org/psr/psr-16/) | cache (pool / simple) | accepted |
| [PSR-7](https://www.php-fig.org/psr/psr-7/) | immutable HTTP messages | accepted |
| [PSR-11](https://www.php-fig.org/psr/psr-11/) | container | accepted |
| [PSR-13](https://www.php-fig.org/psr/psr-13/) | hypermedia links | accepted |
| [PSR-14](https://www.php-fig.org/psr/psr-14/) | event dispatcher | accepted |
| [PSR-15](https://www.php-fig.org/psr/psr-15/) | server-side HTTP handlers and middleware | accepted |
| [PSR-17](https://www.php-fig.org/psr/psr-17/) | factories for PSR-7 messages | accepted |
| [PSR-18](https://www.php-fig.org/psr/psr-18/) | HTTP client | accepted |
| [PSR-20](https://www.php-fig.org/psr/psr-20/) | clock | accepted |
| PSR-5, PSR-19 | PHPDoc and PHPDoc tags | draft: not binding, but they are the syntax PHPStan and Psalm read |
| PSR-21, PSR-22 | internationalization, tracing | draft: do not depend on them |
| PSR-0, PSR-2 | autoload, style | deprecated: replaced by PSR-4 and PER-CS |

## 3. Usage rules

- **Style**: PER Coding Style applied by a fixer, with the version pinned. If the framework has its own preset (Laravel Pint, the `@Symfony` set, `laminas/laminas-coding-standard`), the preset wins. Details in `tooling.md` §2.
- **Autoload**: only PSR-4 in `composer.json`, one root namespace per project (`App\` or `Vendor\Package\`), one symbol per file. No `classmap` or `files` except for legacy code or pure functions.
- **Depend on the interface, not the implementation**: type parameters with `Psr\Log\LoggerInterface`, `Psr\Clock\ClockInterface`, `Psr\SimpleCache\CacheInterface`, `Psr\Http\Client\ClientInterface`, `Psr\EventDispatcher\EventDispatcherInterface` when the service must remain portable across projects or frameworks. Inside an application tied to a framework, the framework's contract is fine too, if the project already uses it.
- **Require the `psr/*` packages you use directly** in `composer.json`; do not rely on transitive dependencies.
- **PSR-11 is not a license for the service locator**: `ContainerInterface` is used in the composition root, not in application classes (`dependency-injection.md` §2).
- **PSR-7 is immutable but the body stream is not**: do not share pre-built `ResponseInterface` instances; create responses with the PSR-17 factories.
- **PSR-3**: messages with `{key}` placeholders and data in the context, exception under the `exception` key (`errors-exceptions.md` §6).
- **PSR-20**: inject the clock into services that depend on the current time, so tests use a fixed clock.

## 4. Standards in frameworks

The framework remains the reference for its own architecture. This table is for writing code that crosses the boundary (shared packages, portable domain modules, integrations).

| Framework | What it exposes or implements |
|---|---|
| **Laminas / Mezzio** (formerly Zend Framework) | PSR-7/17 (`laminas-diactoros`), native PSR-15 (Mezzio, Stratigility), PSR-11 (`laminas-servicemanager`), PSR-3 via Monolog |
| **Symfony** | PSR-3 (Monolog), PSR-6/16 (Cache), PSR-11, PSR-14 (EventDispatcher), PSR-18 (HttpClient's `Psr18Client`), PSR-20 (Clock). HttpFoundation is not PSR-7: to convert there is `symfony/psr-http-message-bridge` |
| **Laravel** | PSR-3 (logger), PSR-11 (container), PSR-16 (cache repository), PSR-18 through Guzzle. The request is not PSR-7: a `ServerRequestInterface` parameter in controllers works by installing `symfony/psr-http-message-bridge` and `nyholm/psr7` |
| **Slim 4** | native PSR-7/15/17, PSR-11 for the chosen container |

If the framework does not implement a standard, do not force it: use the framework's mechanism and isolate the dependency behind an adapter only if the code truly needs to be portable.

## 5. PSR-7/15 HTTP pipeline

Applies to Mezzio, Slim and framework-less applications. Laravel and Symfony have their own pipelines with equivalent concepts (middleware, kernel events).

**Lifecycle.** With PHP-FPM or mod_php every request starts from scratch: front controller → autoload → conversion of errors into exceptions → container construction → `ServerRequest` created from the superglobals (the only place where they are read) → middleware → router → handler → `Response` → emitter to the SAPI.

**Document root.** The web server exposes only the public folder (`public/`), which contains the front controller and the assets. Code, `vendor/`, configuration and data stay physically outside it. It is the only protection that survives configuration failures (ignored `.htaccess`, a broken PHP handler serving the sources, forgotten `.env` or `.git/`, executable scripts in `vendor/`). If a file exists the web server serves it, otherwise it passes the request to the front controller: this is why there is only one PHP file in `public/`. If you cannot change the document root (shared hosting), use the `php-shared-hosting` skill.

**Middleware order**, from outermost to innermost:
1. error handling, first: it intercepts the exceptions of all the others, logs them, exposes details only in debug;
2. cross-cutting: security headers, CORS, body parsing, session, authentication;
3. router: 404/405, route parameters as request attributes;
4. route-specific middleware (authorization);
5. handler execution.

**Handler / controller.**
- Extracts and validates the input, converts it into domain types, calls the service, translates the result into a response. No business logic.
- Validation errors → 4xx response; unexpected exceptions → let them bubble up to the error handler.
- Never `echo`, `header()` or `exit`: they bypass the pipeline and prevent middleware from acting on the response.

**Persistent runtimes** (FrankenPHP in worker mode, RoadRunner, Swoole, Laravel Octane): the container survives between requests, so services must hold no request state and there must be no reading of superglobals outside request creation (`dependency-injection.md` §6). For large downloads use a streaming emitter.

## 6. Without a framework

- Compose interoperable PSR implementations, choosing each one in a single place: PSR-7/17 messages, PSR-11 container, PSR-15 dispatcher, router, emitter, PSR-3 logger. Any compliant implementation is fine; prefer maintained packages and check with `composer audit`.
- Typed, immutable configuration built in the bootstrap; no reading of `getenv()` or `$_ENV` in classes.
- When you need sessions, authentication, structured validation, ORM, migrations, queues, scheduler, mail and translations, reassembling them by hand costs more than it returns: move to a PSR micro-framework (Slim, Mezzio), where handlers and middleware carry over almost unchanged, or to a full framework.
