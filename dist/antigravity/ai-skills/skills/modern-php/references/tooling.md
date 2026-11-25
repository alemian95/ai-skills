# Tools

Sources: [PHP The Right Way — Dependency Management](https://phptherightway.com/#dependency_management), [Testing](https://phptherightway.com/#testing), [Xdebug](https://phptherightway.com/#xdebug), [Caching](https://phptherightway.com/#caching), [Documenting](https://phptherightway.com/#documenting), [PER Coding Style](https://www.php-fig.org/per/coding-style/).

General rule: use the tools already configured in the project and their commands (`composer` scripts, `Makefile`, `vendor/bin/*`). Do not introduce new ones and do not change existing configurations without asking.

## Contents
1. Composer
2. Style: PHP-CS-Fixer and Pint
3. Static analysis: PHPStan
4. Upgrades: Rector
5. Tests: PHPUnit and Pest
6. Debugging and profiling: Xdebug 3
7. Production: OPcache
8. PHPDoc

## 1. Composer

- **Constraints**: `^` for dependencies (`^3.8` = ≥3.8 <4.0). Avoid `*` and `dev-main` in production.
- **Lock**: `composer.lock` is committed for applications (reproducible installs); for libraries usually not.
- **Platform**: `config.platform.php` set to the production PHP version, so resolution does not pick packages that require a newer version than the one running.
- **Deploy**: `composer install --no-dev --optimize-autoloader --no-interaction` (with `--classmap-authoritative` if no class is generated at runtime). Never `composer update` on servers.
- **Security**: `composer audit` in CI (replaces Local PHP Security Checker, archived in 2024).
- **Useful checks**: `composer validate --strict`, `composer why <pkg>`, `composer why-not <pkg> <version>`, `composer outdated --direct`.
- **Scripts**: expose the quality commands as scripts (`composer test`, `composer stan`, `composer cs`, `composer check`) so CI and developers run the same things.
- **Direct dependencies**: explicitly require the packages whose classes you use (including the `psr/*` interfaces); do not rely on transitive dependencies.

## 2. Style: PHP-CS-Fixer and Pint

Style is applied with a tool, not by hand and not in code review.

**Default** (no framework, libraries, frameworks without their own preset) — PHP-CS-Fixer with PER Coding Style:

```php
return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS3x0' => true,
        '@PER-CS3x0:risky' => true,
        '@PHP8x4Migration' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'no_unused_imports' => true,
    ])
    ->setFinder(PhpCsFixer\Finder::create()->in([__DIR__ . '/src', __DIR__ . '/tests']));
```

In recent versions the sets are named `@PER-CS3x0`, `@PHP8x4Migration` (the dotted forms, e.g. `@PER-CS2.0`, remain as aliases). `@PER-CS` without a version always follows the latest revision and can change the style on an update: pin the version.

**Framework presets** — if the project uses one, that one wins; do not add a second fixer alongside it.
- **Laravel**: Pint (`vendor/bin/pint`), `laravel` preset in `pint.json`; in CI `pint --test`.
- **Symfony**: PHP-CS-Fixer with `@Symfony` (and `@Symfony:risky`).
- **Laminas / Mezzio**: `laminas/laminas-coding-standard` (PHP_CodeSniffer, `phpcs`/`phpcbf`).

## 3. Static analysis: PHPStan

- New projects: `level: max` from the start; it is much cheaper than raising it later.
- Existing projects: pick the highest sustainable level and generate a baseline (`vendor/bin/phpstan analyse --generate-baseline`); new code must not add errors, the baseline shrinks over time.
- **Laravel**: `larastan/larastan`, including `vendor/larastan/larastan/extension.neon`.
- Use the PHPDoc types that PHP cannot express (`list<T>`, `array<K, V>`, `array{...}`, `non-empty-string`, `positive-int`, `@template` generics) to give information to the analysis, not to duplicate native types.
- Do not silence errors with `@phpstan-ignore` without an identifier and a reason (`@phpstan-ignore argument.type (reason)`).
- Patterns the analysis appreciates: native types everywhere, `assert()` or `instanceof` after `$container->get()` and after `require` of configuration files, explicit `null` returns.

Psalm is a valid alternative; do not use both.

## 4. Upgrades: Rector

Rector applies automatic transformations: PHP version upgrades, adding types, code cleanup, framework and PHPUnit migrations.

```php
// rector.php
return Rector\Config\RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withPhpSets()                 // reads the php constraint from composer.json
    ->withPreparedSets(deadCode: true, codeQuality: true, typeDeclarations: true)
    ->withImportNames(removeUnusedImports: true);
```

Procedure for legacy code: characterization tests on current behavior → PHPStan with a baseline → `vendor/bin/rector process --dry-run`, review of the diff → apply in small sets with separate commits → style fixer at the end. For Laravel there is `driftingly/rector-laravel` for migrations between framework versions.

## 5. Tests: PHPUnit and Pest

**PHPUnit** (12 requires PHP 8.3, 13 requires PHP 8.4.1)
- Metadata with attributes: `#[Test]`, `#[DataProvider('name')]`, `#[CoversClass(Foo::class)]`, `#[Group('slow')]`. Docblock annotations (`@test`, `@dataProvider`, `@covers`) are no longer supported as of PHPUnit 12.
- `public static` data providers, preferably with descriptive keys (`yield 'edge case' => [...]`).
- Strict configuration in `phpunit.xml.dist`: `failOnWarning`, `failOnDeprecation`, `failOnNotice`, `failOnRisky`, `beStrictAboutOutputDuringTests`, `executionOrder="random"` (uncovers hidden dependencies between tests).
- `final` test classes, one behavior per test, names that describe the behavior.

**Pest** — de facto standard in recent Laravel projects; it is built on PHPUnit, so the same strictness rules apply. Follow the tool already present in the project.

**Strategy**
- Domain: unit tests without the container, building objects with `new`.
- Boundaries (HTTP, database, queues): integration tests with real implementations or hand-written fakes.
- Prefer fakes to configured mocks: mocks couple the test to the implementation.
- Time and randomness injected (PSR-20 `ClockInterface`, `Random\Randomizer` with a deterministic engine) for repeatable tests.

## 6. Debugging and profiling: Xdebug 3

The Xdebug 2 configuration (`xdebug.remote_enable`, `remote_host`, port 9000), still found in many guides, no longer works.

```ini
zend_extension = xdebug
xdebug.mode = debug,develop            ; add coverage or profile only when needed
xdebug.start_with_request = trigger    ; activates only with XDEBUG_TRIGGER / XDEBUG_SESSION
xdebug.client_host = 127.0.0.1         ; in Docker: host.docker.internal (or the host's IP)
xdebug.client_port = 9003
```

- Web: browser extension or `XDEBUG_TRIGGER` parameter/cookie. CLI: `XDEBUG_TRIGGER=1 php script.php`.
- `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html var/coverage` for coverage without keeping the mode enabled; PCOV is faster if you only need coverage.
- Xdebug slows execution down considerably: never in production, and with `mode=off` when you are not using it.
- Profiling: `xdebug.mode=profile` (cachegrind files) locally; in shared environments dedicated tools (SPX, Blackfire, Tideways).

## 7. Production: OPcache

```ini
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0        ; no file checks: restart/reload PHP-FPM on every deploy
opcache.interned_strings_buffer = 16
```

- With `validate_timestamps=0` the deploy must reload PHP-FPM (or invalidate the cache), otherwise the old code keeps running.
- Preloading (`opcache.preload`) gives measurable benefits only on large frameworks and requires a restart on every change: evaluate it with a benchmark.
- The JIT helps CPU-intensive code; typical web applications are I/O-bound and gain little from it. Enable it only after measuring.
- In development leave `validate_timestamps=1`.

## 8. PHPDoc

- Write PHPDoc only when it adds information: types that PHP cannot express (see §3), `@throws` for exceptions the caller must handle, an explanation of the *why* behind a non-obvious choice.
- No docblock that repeats the signature (`@param string $name The name`).
- Comments describe intent and constraints, not what the code already says.
