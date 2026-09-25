---
name: "modern-php"
description: Binding rules and references for writing, reviewing and refactoring modern PHP code (8.4+), valid with or without a framework (plain PHP, Laminas/Zend, Mezzio, Slim, Symfony, Laravel, CodeIgniter…) and based on PHP The Right Way and the PHP-FIG standards (PSR, PER Coding Style). Covers strict types, 8.x features, security, errors and exceptions, PDO, dependency injection, PSR interfaces, tools (Composer, PHPStan, PHP-CS-Fixer/Pint, Rector, PHPUnit/Pest, Xdebug 3), text, dates and i18n. Use it for any PHP coding, review, refactoring or upgrade task — whenever you write or modify PHP code, do a code review or security review of PHP code, upgrade legacy PHP 5/7 code or configure quality tools. Combines with framework- or environment-specific skills (e.g. php-shared-hosting).
---

# Modern PHP

Claude already knows PHP. This skill sets **general rules** that apply in any context (scripts, framework-less code, any framework) and **corrects outdated habits** that circulate in tutorials and legacy code.

## Sources

The rules rely on two sources. Cite them in reviews and explanations when they justify a choice.
- **[PHP The Right Way](https://phptherightway.com/)**: community best practices, framework-independent. Every file in `references/` links to its sections.
- **[PHP-FIG](https://www.php-fig.org/psr/)**: the interoperability standards (PSR) and the coding style ([PER Coding Style](https://www.php-fig.org/per/coding-style/), which replaces PSR-12). Status and usage in `references/psr.md`.

Where the skill is stricter or more up to date than these sources (minimum version 8.4, recent tools, deprecated APIs), the skill wins.

## Scope and precedence

1. **Reference version: PHP 8.4.** Before writing code, read the `php` constraint in `composer.json` (and `config.platform.php` if present). If it is lower than 8.4, do not use unavailable features: the per-version table is in `references/language-8x.md`. Use 8.5 features only if the constraint allows it.
2. **Rules about the language and the standards, not about packages.** The core rules apply everywhere. The packages mentioned in the references are implementation examples, not mandatory choices.
3. **With a framework, the framework decides** architecture, DI, HTTP, ORM, validation, configuration, i18n, exception handling and style preset (Laravel: service providers, Eloquent, Form Requests, Pint; Symfony: `services.yaml`, Doctrine, Form/Validator; Laminas/Mezzio: service manager factories, PSR-15 middleware). This skill covers what the framework leaves to the code: language, types, domain exceptions, code-level security, tools. Do not introduce patterns that contradict the project's conventions; if a framework-specific skill exists (e.g. `laravel-action-vs-service`), it takes precedence within its scope.
4. **Classes managed by the framework through reflection or magic** (Active Record models such as Eloquent, entities hydrated by an ORM or serializer): do not apply `readonly`, property hooks or constructor promotion without verifying that the library supports them.
5. **Without a framework:** compose implementations of the chosen PSR interfaces in a single place (`references/psr.md` §5-6). Shared hosting without SSH or without the ability to change the document root: use the `php-shared-hosting` skill, if present.
6. **Repository conventions** (configured tools, style, folder structure) prevail over this skill's preferences when they are explicit and consistent.

## Core rules

They always apply, unless there is a version constraint or an explicit project convention.

**Types and structure**
- `declare(strict_types=1);` in every file: without it, PHP silently coerces scalars in arguments (`"5 apples"` → `5` fails only with strict).
- Native types on parameters, returns and properties. `mixed` only when the value is truly arbitrary. For structured arrays use a value object; if the array stays, document it for PHPStan (`list<User>`, `array{id: int, name: string}`).
- Classes `final` by default and `readonly` for value objects, DTOs and stateless services. Inheritance is designed, not granted by accident.
- Constructor property promotion; no setters for mandatory dependencies.
- `enum` instead of string or integer constants for closed sets of values; `match` instead of `switch` when producing a value (strict comparison, `UnhandledMatchError` on missing cases).
- Strict comparisons: `===`, `in_array($x, $list, true)`, `array_search(..., true)`.
- `include`/`require` only for the autoloader and for configuration files that return a value. Everything else goes through PSR-4 autoloading.
- At the boundaries between modules or towards infrastructure, depend on the PSR interfaces (logger, cache, clock, HTTP client, events) or on the framework's contracts, not on concrete implementations (`references/psr.md` §3).

**Errors and exceptions** (details in `references/errors-exceptions.md`)
- Never the `@` operator: it also hides the errors you do not expect. Use `??`, explicit checks or exceptions.
- In framework-less entry points, convert warnings and notices into `ErrorException`.
- Catch only what you know how to handle, at the right boundary. When rethrowing, wrap with `previous: $e`. Never an empty `catch (\Throwable)`.
- Prefer SPL exceptions (`InvalidArgumentException`, `DomainException`, `RuntimeException`, …) before creating new ones. Create domain exceptions when the caller must tell them apart.
- `json_encode`/`json_decode` always with `JSON_THROW_ON_ERROR`.

**Security** (details in `references/security.md`)
- External input is never trustworthy: validate it at the boundary and turn it into types or value objects; internal code works only with already-valid data.
- Escape output according to context: HTML → `htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` (or the template engine with automatic escaping), JS → `json_encode` with the `JSON_HEX_*` flags, URL → `rawurlencode`.
- SQL only with prepared statements and bound parameters. Dynamic column names, `ORDER BY` directions and tables are taken exclusively from a whitelist.
- Passwords: `password_hash(PASSWORD_DEFAULT)`, `password_verify`, `password_needs_rehash`. Never `md5`, `sha1`, `hash()` or hand-rolled salts.
- Tokens and secrets: `random_bytes`/`random_int` and comparison with `hash_equals`. Never `rand`, `mt_rand` or `uniqid`.
- Never `unserialize` on external data, never `eval`, `extract` or `$$variable`.
- Secrets outside the repository and outside the document root. In production `display_errors=Off` and logging enabled.

**Text and dates** (details in `references/text-dates-i18n.md`)
- `mb_*` functions for length, truncation and case on user text; `utf8mb4` on MySQL.
- `DateTimeImmutable` with an explicit time zone; intervals with `DateInterval`, never arithmetic with 86400 seconds.

**Dependencies and architecture** (details in `references/dependency-injection.md`)
- Constructor injection. The container is used only in the composition root (bootstrap, factories, service providers): `$container->get()` inside a domain class is a service locator.
- Interfaces at the boundaries (I/O, external services, clock, persistence), not for every class.
- No global state: no `global` and no mutable `static` properties. Superglobals are read only in the entry point or through the request object.

**Tools** (details in `references/tooling.md`)
- PER Coding Style (or the framework's preset: Pint in Laravel, `@Symfony` in Symfony, `laminas-coding-standard` in Laminas), applied by the fixer and not by hand. Symbol names in English.
- Static analysis with PHPStan: at level `max` in new projects, with a baseline in legacy ones.
- Composer: commit `composer.lock` for applications; on deploy `composer install --no-dev --optimize-autoloader`, never `update`; `composer audit` in CI.

## Outdated constructs to fix

When you find them in the code you touch, replace them (or flag them in review) with the modern form:

| Outdated | Modern |
|---|---|
| `mysql_*`, concatenated queries | PDO / query builder with bound parameters |
| `md5($password)`, `sha1`, manual `crypt` | `password_hash` / `password_verify` |
| `rand()`, `uniqid()` for tokens | `bin2hex(random_bytes(32))` |
| `@fopen(...)`, `@$arr['k']` | explicit check, `??`, exception |
| `isset($a['k']) ? $a['k'] : null` | `$a['k'] ?? null` |
| `strpos($h, $n) !== false` | `str_contains` / `str_starts_with` / `str_ends_with` |
| `switch` that assigns a value | `match` |
| string/integer `STATUS_*` constants | `enum` (backed if persisted) |
| trivial getters/setters on DTOs | `public readonly` properties (or property hooks, 8.4) |
| `foreach` to find an element | `array_find`, `array_any`, `array_all` (8.4) |
| `(new Foo())->bar()` | `new Foo()->bar()` (8.4) |
| `Foo $x = null` (implicit nullable) | `?Foo $x = null` (deprecated in 8.4) |
| `call_user_func_array([$o, 'm'], $args)` | `$o->m(...$args)`, first-class callable `$o->m(...)` |
| mutable `DateTime` passed around | `DateTimeImmutable` |
| `json_decode` + `json_last_error()` | `JSON_THROW_ON_ERROR` |
| `mb_internal_encoding('UTF-8')` in every script | unnecessary: `default_charset` has been UTF-8 since 5.6 |
| `FILTER_SANITIZE_STRING` | removed: validate the format, escape on output |
| `xdebug.remote_*`, port 9000 | Xdebug 3: `xdebug.mode`, `client_host`, port 9003 |
| `@test`, `@dataProvider` docblocks in PHPUnit | `#[Test]`, `#[DataProvider]` attributes (PHPUnit ≥ 12) |
| Local PHP Security Checker | `composer audit` |
| PSR-0 autoload, `classmap` for new code | PSR-4 |
| PSR-2 / PSR-12 style | PER Coding Style |
| `zendframework/*` | `laminas/*` (Zend Framework became Laminas in 2020) |
| `narrowspark/http-emitter` | `laminas/laminas-httphandlerrunner` |

## Workflow

**Writing or modifying code**
1. Read the `php` constraint, the tools present (`phpstan.neon*`, `.php-cs-fixer*`, `pint.json`, `phpunit.xml*`, `pest`, `rector.php`) and a couple of nearby files to align with the conventions.
2. Write following the core rules; open the relevant reference only if the task touches that area in a non-trivial way.
3. If you have terminal access, run the project's tools (fixer → static analysis → tests) and fix issues before delivering. Do not introduce new tools without asking.

**Code review** — report in this order, stopping at the level that matters:
1. security (injection, escaping, secrets, missing authorization, deserialization);
2. correctness (types, loose comparisons, ignored errors, edge cases, time zones, multibyte);
3. contracts and design (weak types, global state, service locator, generic exceptions);
4. outdated constructs (table above).
Style is not commented on by hand: that is the fixer's job.

**Upgrading legacy code** — proceed in verifiable steps: first PHPStan with a baseline and characterization tests, then Rector for mechanical version jumps, then manual fixes. Details in `references/tooling.md`.

## References

Read only the file needed for the current task.

| File | When to read it | PHP The Right Way |
|---|---|---|
| `references/language-8x.md` | choosing constructs, checking which version a feature exists in, property hooks, asymmetric visibility, what's new in 8.4/8.5 | [Language Highlights](https://phptherightway.com/#language_highlights), [Use the Current Stable Version](https://phptherightway.com/#use_the_current_stable_version) |
| `references/security.md` | input, output, SQL, passwords, cryptography, uploads, sessions, files, security review | [Security](https://phptherightway.com/#security), [Templating](https://phptherightway.com/#templating) |
| `references/errors-exceptions.md` | exception hierarchies, error handlers, logging, what to catch and where | [Errors and Exceptions](https://phptherightway.com/#errors_and_exceptions) |
| `references/database-pdo.md` | PDO without an ORM, connection options, transactions, dynamic queries, `utf8mb4` | [Databases](https://phptherightway.com/#databases) |
| `references/dependency-injection.md` | container, autowiring, composition root, what to inject | [Dependency Injection](https://phptherightway.com/#dependency_injection) |
| `references/psr.md` | PSR and PER standards in force, interfaces to use, PSR in frameworks, PSR-7/15 HTTP pipeline, framework-less code | [Code Style Guide](https://phptherightway.com/#code_style_guide) |
| `references/tooling.md` | Composer, PHP-CS-Fixer/Pint, PHPStan/Larastan, Rector, PHPUnit/Pest, Xdebug 3, OPcache, PHPDoc | [Dependency Management](https://phptherightway.com/#dependency_management), [Testing](https://phptherightway.com/#testing), [Caching](https://phptherightway.com/#caching), [Documenting](https://phptherightway.com/#documenting) |
| `references/text-dates-i18n.md` | UTF-8, `mb_*`, `intl`, dates and time zones, translations | [UTF-8](https://phptherightway.com/#php_and_utf8), [Date and Time](https://phptherightway.com/#date_and_time), [i18n](https://phptherightway.com/#i18n_l10n) |
