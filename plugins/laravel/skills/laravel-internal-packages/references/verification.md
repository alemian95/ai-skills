# Verification

"Removing it doesn't break the core" is a claim to prove, not to state. Arch tests and a scan catch references, neutrality tests prove that hook points are inert, and the shutdown proof demonstrates the two switches.

## Test layout

Package tests live in the package, so `composer remove` takes them away too.

```xml
<!-- phpunit.xml -->
<testsuites>
    <testsuite name="Core">
        <directory>tests/Unit</directory>
        <directory>tests/Feature</directory>
        <directory>tests/Arch</directory>
    </testsuite>
    <testsuite name="Packages">
        <directory>packages/*/tests</directory>
    </testsuite>
</testsuites>
<php>
    <!-- not force="true": a CI job must be able to override it from the shell -->
    <env name="PACKAGES_FORCE_ALL" value="true"/>
</php>
```

```php
// tests/Pest.php: Pest resolves `in()` paths relative to tests/ and expands globs
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', '../packages/*/tests');
```

## 1. Arch tests: the dependency direction

```php
// tests/Arch/PackagesTest.php
use Illuminate\Support\Str;

// toUse() matches by namespace prefix: `Packages\…` is caught, the core's `App\Packages\…` is not.
arch('the core never uses a package')
    ->expect(['App', 'Database'])
    ->not->toUse('Packages');

// The app is not booted while Pest loads this file: no base_path() here.
$namespaces = array_map(
    fn (string $dir): string => 'Packages\\'.Str::studly(basename($dir)),
    glob(dirname(__DIR__, 2).'/packages/*', GLOB_ONLYDIR) ?: [],
);

foreach ($namespaces as $namespace) {
    $others = array_values(array_diff($namespaces, [$namespace]));

    if ($others !== []) {
        arch("{$namespace} does not depend on other packages")->expect($namespace)->not->toUse($others);
    }
}
```

## 2. Reference scan: what arch tests can't see

Arch tests see PHP symbols. They miss strings: `'billing::settings'`, `<livewire:billing::…>`, `view('billing::…')`, config keys, JS imports. Scan the tracked core files. `git grep` skips generated, git-ignored output such as Wayfinder's `resources/js/{actions,routes,wayfinder}`:

```bash
for dir in packages/*/; do
    name=$(basename "$dir")
    git grep -niw "$name" -- app bootstrap config database routes resources tests \
        ':(glob)*.config.*' tsconfig.json phpunit.xml && echo "core references '$name'"
done
```

Review every hit. A natural-language word that happens to match is not a reference, so note it and move on. With composer path packages, the **only** host files that name a package are the root `composer.json` / `composer.lock` (product switch) and each instance's `.env` (instance switch).

Frontend: ESLint `no-restricted-imports` on `**/packages/**` for core files (see `inertia.md`).

## 3. Gate test (core, once for all packages)

The base provider's gate is tested once, with a fake package, and not re-tested in every package:

```php
// tests/Feature/Packages/PackageServiceProviderTest.php (needs the app: Feature, not Unit)
use App\Packages\PackageServiceProvider;

function fakePackage(): PackageServiceProvider
{
    return new class(app()) extends PackageServiceProvider
    {
        public bool $registered = false;

        protected function name(): string { return 'fake'; }

        protected function registerPackage(): void { $this->registered = true; }

        protected function path(string $relative = ''): string { return __DIR__.'/fixtures/'.$relative; } // fixtures/config/fake.php → return [];
    };
}

it('registers a disabled package as if it were not installed', function () {
    config(['packages.force_all' => false, 'packages.enabled' => [], 'fake.enabled' => false]);

    $provider = fakePackage();
    $provider->register();

    expect($provider->registered)->toBeFalse()
        ->and(config('packages.enabled'))->toBe([]);
});

it('registers an enabled package and lists it', function () {
    config(['packages.force_all' => false, 'packages.enabled' => [], 'fake.enabled' => true]);

    $provider = fakePackage();
    $provider->register();

    expect($provider->registered)->toBeTrue()
        ->and(config('packages.enabled'))->toBe(['fake']);
});
```

Per package, a smoke test with the package on (it is on in the test suite via `PACKAGES_FORCE_ALL`): its name is in the shared `packages` prop, its main page renders, and its contributions show up where expected.

## 4. Neutrality test: one per core hook point

Every hook point in the core (event, registry, slot mount, panel line) needs proof that, with no consumer, the core behaves exactly as before. Without that proof, "the hook is additive" stays a promise.

- **Registry:** capture the core output (e.g. the export file) as a golden fixture **before** introducing the hook, and assert it stays identical with an empty registry, built directly (`new UsersExport([])`), because in the test suite packages are on and the container would inject their contributors. Add a second test with a fake contributor: its columns come last, padded or cut to its headings.
- **Event:** `Event::fake()` + assert it is dispatched on every channel that produces the fact (form, import, API), and the core flow's result is unchanged with no listener.
- **Slot / render hook:** with packages off, the core page renders without errors and shows no contribution. Inertia slots are client-side, so this needs a browser test (Pest 4):

  ```php
  // run with PACKAGES_FORCE_ALL=false
  visit('/dashboard')->assertNoJavaScriptErrors()->assertDontSee('Billing');
  ```

  Blade slots: assert the response HTML of the host page against the version rendered with an empty `ViewSlots` (`app()->instance(ViewSlots::class, new ViewSlots)`).

## 5. Inertia package pages

HTTP tests run `withoutVite()`, and `assertInertia()->component()` looks under `resources/js` only:

```php
$response->assertInertia(fn ($page) => $page->component('billing::settings', shouldExist: false));
expect(base_path('packages/billing/resources/js/pages/settings.vue'))->toBeFile();
```

## 6. Shutdown proof (part of "done")

Run both switches.

**Instance switch.** Set `<NAME>_ENABLED=false` with no `PACKAGES_FORCE_ALL`, then:

1. `php artisan optimize:clear`
2. `php artisan route:list --name=<route-prefix>` must return nothing.
3. `PACKAGES_FORCE_ALL=false php artisan test --testsuite=Core` must pass.
4. The UI is clean: no slot contribution, no menu entry, no Filament resource or widget.

**Product switch** (on a throwaway branch or in CI). Run `composer remove packages/<name>` and delete `packages/<name>/` (the `packages/*` globs of Vite, test suite and arch tests would still find a folder left on disk), then:

1. `php artisan test --testsuite=Core` must pass.
2. The frontend type check and `npm run build` must pass.

## Definition of done

- Core suite passes with packages off, and the full suite passes with packages on.
- Pint and static analysis are clean, and the frontend type check and build pass.
- Arch tests pass, and the reference scan finds no reference to the package.
- Every new core hook point is generic and has its neutrality test.
- The shutdown proof has been run on both switches.
- The package README documents its env variables, slots, events listened to and registries contributed to. The package mechanism itself is documented once, in the core, and not repeated in each package.
