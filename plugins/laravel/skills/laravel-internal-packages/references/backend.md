# Backend

## Layout

```
packages/<name>/
├── composer.json
├── config/<name>.php          # 'enabled' + the package's own parameters
├── database/migrations/
├── lang/
├── routes/web.php
├── resources/
│   ├── js/                    # Inertia: slots.{ts,tsx}, pages/  → inertia.md
│   └── views/                 # Blade/Livewire views           → blade-livewire.md
├── src/                       # Packages\<Name>\
│   ├── <Name>ServiceProvider.php
│   ├── Actions/ Http/ Listeners/ Models/ ...
│   └── Filament/              # → filament.md
└── tests/                     # → verification.md
```

A package declares only the folders it really has.

## Composer path packages

Each package is a real Composer package installed from a path repository. Autoload, provider discovery and third-party dependencies then belong to the package, and `composer remove` is the product switch.

```jsonc
// composer.json (root): the glob also covers future packages
"repositories": [{ "type": "path", "url": "packages/*", "options": { "symlink": true } }],
"require": { "packages/billing": "@dev" }
```

```json
{
    "name": "packages/billing",
    "type": "library",
    "require": { "php": "^8.4" },
    "autoload": { "psr-4": { "Packages\\Billing\\": "src/" } },
    "extra": { "laravel": { "providers": ["Packages\\Billing\\BillingServiceProvider"] } }
}
```

- The package lists its own third-party dependencies in its own `require`. Relying on a library that only arrives as a transitive dependency of the core is a latent break.
- `autoload-dev` of a path package is not loaded by the root. Test support classes go in the root `autoload-dev`.
- After adding a package: `composer require packages/<name>:@dev`.

## Base provider (core, written once)

The gate, the enabled-list and the path helper live in the core, in a single place. Each package provider only fills two hooks.

```php
<?php

declare(strict_types=1);

namespace App\Packages;

use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base for every internal package under `packages/`.
 *
 * Owns the instance switch: when a package is disabled, registerPackage() and
 * bootPackage() never run, so its routes, migrations, listeners, tags and UI
 * do not exist. The core never lists packages: it reads `packages.enabled`,
 * which is filled only from here.
 */
abstract class PackageServiceProvider extends ServiceProvider
{
    /** Directory name, config key, view/translation namespace, Inertia page prefix. */
    abstract protected function name(): string;

    final public function register(): void
    {
        $this->mergeConfigFrom($this->path("config/{$this->name()}.php"), $this->name());

        if (! $this->enabled()) {
            return;
        }

        // config:cache boots the providers and caches the list already filled: don't push twice.
        if (! in_array($this->name(), config('packages.enabled', []), true)) {
            config()->push('packages.enabled', $this->name());
        }

        $this->registerPackage();
    }

    final public function boot(): void
    {
        if ($this->enabled()) {
            $this->bootPackage();
        }
    }

    /** Bindings and container tags: anything other providers may resolve while registering. */
    protected function registerPackage(): void {}

    /** Routes, migrations, views, listeners, relations, Livewire, render hooks. */
    protected function bootPackage(): void {}

    /** Absolute path inside the package root (the directory holding composer.json). */
    protected function path(string $relative = ''): string
    {
        $root = dirname((string) (new ReflectionClass($this))->getFileName(), 2);

        return rtrim("{$root}/{$relative}", '/');
    }

    private function enabled(): bool
    {
        return (bool) config("{$this->name()}.enabled") || (bool) config('packages.force_all');
    }
}
```

```php
// config/packages.php (core): names no package
return [
    // Build/CI only: registers every package whatever its own flag (wayfinder:generate, full test runs).
    'force_all' => (bool) env('PACKAGES_FORCE_ALL', false),

    // Filled at runtime by App\Packages\PackageServiceProvider. Never list packages here.
    'enabled' => [],
];
```

```php
// packages/billing/config/billing.php
return [
    'enabled' => (bool) env('BILLING_ENABLED', false),
    'currency' => env('BILLING_CURRENCY', 'EUR'),
];
```

```php
// packages/billing/src/BillingServiceProvider.php
final class BillingServiceProvider extends PackageServiceProvider
{
    protected function name(): string
    {
        return 'billing';
    }

    protected function registerPackage(): void
    {
        $this->app->tag([BillingUserColumns::class], UsersExport::CONTRIBUTORS);
    }

    protected function bootPackage(): void
    {
        $this->loadRoutesFrom($this->path('routes/web.php'));
        $this->loadMigrationsFrom($this->path('database/migrations'));
        $this->loadViewsFrom($this->path('resources/views'), 'billing');
        $this->loadTranslationsFrom($this->path('lang'), 'billing');

        Event::listen(UserCreated::class, CreateBillingProfile::class);

        User::resolveRelationUsing('billingProfile', fn (User $user) => $user->hasOne(BillingProfile::class));
    }
}
```

Notes:

- The flag is read from config, so it follows `config:cache`. With one artifact for many customers, `config:cache` / `route:cache` / `optimize` run **on the instance** (release or container start), never in the build: otherwise the artifact carries one customer's flags. After changing `.env`, run `php artisan optimize` and restart queues/Octane.
- Discovered package providers register **before** `App\Providers\*`, so a package can tag services the core resolves later. Tags and bindings go in `registerPackage()`, never in `bootPackage()`.
- If the project already uses `spatie/laravel-package-tools`, the same gate applies. Wrap `register()`/`boot()` before calling `parent::`. The `registeringPackage()`/`bootingPackage()` hooks run inside the flow and cannot stop it.
- Settings libraries with their own registries (e.g. `spatie/laravel-settings`): the package pushes its classes and migration paths in `registerPackage()` (`config()->push('settings.settings', …)`), so the core config never names it. Same `in_array` guard as above, because of `config:cache`.

## Routes

`loadRoutesFrom()` applies no middleware group. The package route file declares `web`, authentication and authorization explicitly, exactly as a core route would. Security belongs to the package.

```php
Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('billing/invoices', [InvoiceController::class, 'index'])->name('billing.invoices.index');

    Route::middleware('can:manage-settings')->group(function (): void {
        Route::get('admin/billing', [BillingSettingsController::class, 'edit'])->name('admin.billing.edit');
    });
});
```

**Route names follow the host's conventions, not a free `<name>.*` prefix.** Before naming routes, look for middleware or redirects in the core that branch on route names (e.g. "learners only on `app.*`"): a package route with the wrong prefix becomes unreachable, and no error is raised.

## Schema

| Case | Allowed |
|---|---|
| Package tables (prefix `<name>_`), with foreign keys to core tables declared `cascadeOnDelete()` or `nullOnDelete()` | ✅ a `restrict` FK would make the core fail to delete its own rows, even after the package is disabled |
| Relation from a core model to package data via `Model::resolveRelationUsing()` in `bootPackage()` | ✅ |
| Package migration that alters a core table | ❌ the core schema is changed only by the core |
| Generic nullable column on a core table, added by a **core** migration, whose name describes a core concept | ✅ existing rows stay null |
| The same column as `is_<package>` or `<package>_id` | ❌ it names the package without writing the name |
| One more case in a core enum | ⚠️ only if all core code that switches on the enum handles it |

Migrations are loaded only when the package is enabled. Disabling it later leaves its tables in place, inert. Full removal: `php artisan migrate:reset --path=packages/<name>/database/migrations` (not `rollback`, which only looks at the last batch) on every instance that had it, then `composer remove packages/<name>` **and** delete `packages/<name>/` (git history keeps it). A folder left on disk is still picked up by the `packages/*` globs of Vite, the test suite and the arch tests.

## Reacting to core facts: events

- The core emits a domain event on the **fact** (`UserCreated`, `OrderPaid`), not on the channel. `Registered` covers only self sign-up, while a user created by an admin or an import is the same fact. `$dispatchesEvents = ['created' => UserCreated::class]` on the model covers every channel in one place. Trade-off: factories and seeders fire it too.
- The core event implements `ShouldDispatchAfterCommit` when it is emitted inside a transaction.
- The payload carries core models and core concepts only. If packages need input the core does not understand (extra form fields, extra import columns), pass it as an opaque array built by one core helper. Don't add typed fields for concepts the core doesn't own.
- Listeners are registered in `bootPackage()` (`Event::listen`), because event discovery does not scan `packages/`.
- The listener never joins the core's transaction. Default to `ShouldQueue`. A sync listener that throws breaks the core request (or, in a row-by-row import, marks a written row as failed), so if it must stay sync, catch and log inside it and say so in its docblock.
- A listener that must catch up on data written before the package was enabled gets a backfill command in the package, sharing the listener's Action.
- Queued jobs outlive the switch: jobs already queued still run after the instance flag goes off (with the package's bindings and config gone), and fail with *class not found* after `composer remove`. Drain the package's jobs before switching it off.

## Adding to core outputs: contribution registries

For exports, menus, dashboards and lists: the core asks "who wants to add something?" through a container tag and a small core contract. The tag name is a constant on the core class that owns the output.

```php
// core: app/Exports/Contracts/UsersExportColumns.php
interface UsersExportColumns
{
    /** @return list<string> */
    public function headings(): array;

    /** @return list<scalar|null> one value per heading */
    public function values(User $user): array;

    /** @return list<string> relations to eager-load: package data almost always sits on a relation */
    public function relations(): array;
}
```

```php
// core: app/Exports/UsersExport.php (excerpt)
use Illuminate\Container\Attributes\Tag;

final class UsersExport
{
    public const CONTRIBUTORS = 'exports.users.columns';

    /** @var list<UsersExportColumns> */
    private readonly array $contributors;

    /**
     * Materialized once: a tagged iterable re-resolves its services on every foreach, i.e. on every row.
     *
     * @param iterable<object> $contributors
     */
    public function __construct(#[Tag(self::CONTRIBUTORS)] iterable $contributors)
    {
        $list = [];
        foreach ($contributors as $contributor) {
            $contributor instanceof UsersExportColumns
                || throw new InvalidArgumentException($contributor::class.' does not implement '.UsersExportColumns::class);
            $list[] = $contributor;
        }
        $this->contributors = $list;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [...self::BASE_HEADINGS, ...array_merge([], ...array_map(
            fn (UsersExportColumns $c): array => $c->headings(), $this->contributors,
        ))];
    }

    /** @return list<scalar|null> */
    public function row(User $user): array
    {
        // Each contribution is padded/cut to its own headings, so one wrong count can't shift the next.
        return [...$this->baseRow($user), ...array_merge([], ...array_map(
            fn (UsersExportColumns $c): array => array_pad(
                array_slice(array_values($c->values($user)), 0, count($c->headings())), count($c->headings()), null,
            ),
            $this->contributors,
        ))];
    }
}
```

Rules:

- **Append, never insert.** Core outputs often have positional meaning (column letters for formats and validation). A contribution placed in the middle shifts them without raising any error.
- Order is the provider registration order, and nothing is de-duplicated. With a single contributor this is theory. With two or more, decide it explicitly.
- The core query eager-loads every contributor's `relations()`. Otherwise the export goes N+1.
- The contract declares no constructor. Contributors are built by the container and inject what they need.
- **Neutrality test is mandatory**: with no contributor, the output is identical to the core's own. See `verification.md`.
- The second registry must follow this same shape. If a third hook point of a different shape appears, stop and ask whether the core needs one extension mechanism instead of three.

## Shared data

`Inertia::share` closures (and Blade view composers on layouts) run on **every** response. Share only flags and cheap aggregates. The core shares the enabled-package list once:

```php
// app/Http/Middleware/HandleInertiaRequests.php → share()
'packages' => config('packages.enabled', []),
```

A package's data reaches its UI this way:

- **Package pages:** their own controller props (deferred props included).
- **Slot contributions inside core pages:** the package's own endpoint, plus the context props the host slot passes ("render for *this* user"). A core page's props are not the package's to extend.

A package never adds a global share of its data.

**Authorization concepts belong to the core.** If the package needs "admin", the core must already have it (roles, abilities), and the package builds on it: `Gate::define('billing.manage', fn (User $u) => $u->can('manage-settings'))` in `bootPackage()`. Whether a slot contribution is visible to the current user is decided server-side, by the package endpoint or by abilities the core already shares, never by a new global share.

## Inside a package

The package's internal design follows the core's rules (Actions, Services, Policies: see the `laravel-action-vs-service` skill). When a package grows a real domain, split a `Domain/` layer that never uses `App\` from an `Adapters/` layer that translates to the core, and enforce the split with an arch test. Don't start with that split for a package of ten classes.
