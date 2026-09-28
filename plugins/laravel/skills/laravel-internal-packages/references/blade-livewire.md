# Blade and Livewire

```
packages/<name>/
├── resources/views/
│   ├── slots/…             # views contributed to core slots
│   ├── components/…        # <x-<name>::…>
│   └── livewire/…          # Livewire views
└── src/Livewire/…
```

## Core wiring (written once)

### Slot names: one enum

```php
namespace App\Packages;

/** Every slot the core mounts. New slot = <x-package-slot name="…"> in a core view + a case here. */
enum Slot: string
{
    case DashboardCards = 'dashboard-cards';
    case NavItems = 'nav-items';
}
```

### Slot registry

```php
namespace App\Packages;

/** Views contributed to core slots by enabled packages. Disabled packages never reach add(). */
final class ViewSlots
{
    /** @var array<string, list<array{view: string, order: int}>> */
    private array $entries = [];

    public function add(Slot $slot, string $view, int $order = 100): void
    {
        $this->entries[$slot->value][] = ['view' => $view, 'order' => $order];
    }

    /** @return list<string> */
    public function views(Slot $slot): array
    {
        $entries = $this->entries[$slot->value] ?? [];
        usort($entries, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_column($entries, 'view');
    }
}
```

Bind it as a singleton in `AppServiceProvider::register()` (`$this->app->singleton(ViewSlots::class)`). Every `register()` runs before any `boot()`, so packages can safely add in `bootPackage()`.

### Mount point

```blade
{{-- resources/views/components/package-slot.blade.php ("x-slot" is reserved by Blade) --}}
@props(['name'])
@foreach (app(\App\Packages\ViewSlots::class)->views(\App\Packages\Slot::from($name)) as $view)
    @include($view, $attributes->getAttributes())
@endforeach
```

```blade
{{-- core view: no condition on who fills it --}}
<div class="grid gap-4 md:grid-cols-3">
    {{-- core cards --}}
    <x-package-slot name="dashboard-cards" :user="$user" />
</div>
```

`Slot::from()` turns a misspelled slot name into an exception instead of an empty space. Context attributes (`:user`) say what the slot renders for. The contribution loads its own data (a Livewire component, or a view composer registered by the package on **its own** view, never on core layouts).

## Package side

```php
protected function bootPackage(): void
{
    $this->loadViewsFrom($this->path('resources/views'), 'billing');
    Blade::anonymousComponentPath($this->path('resources/views/components'), 'billing'); // <x-billing::card>

    // Livewire 4: registers every class component under the namespace → <livewire:billing::redeem-points />
    Livewire::addNamespace(
        namespace: 'billing',
        classNamespace: 'Packages\\Billing\\Livewire',
        classPath: $this->path('src/Livewire'),
        classViewPath: $this->path('resources/views/livewire'),
    );
    // Livewire 3: one explicit registration per component (auto-discovery covers only app/Livewire)
    // Livewire::component('billing.redeem-points', RedeemPoints::class);

    $this->app->make(ViewSlots::class)->add(Slot::DashboardCards, 'billing::slots.dashboard-card', order: 50);
}
```

```blade
{{-- packages/billing/resources/views/slots/dashboard-card.blade.php --}}
<x-billing::card :title="__('billing::ui.balance')">
    <livewire:billing::redeem-points :user="$user" />
</x-billing::card>
```

Rules:

- **Livewire public methods are HTTP endpoints.** Authorize inside every action (`$this->authorize(...)`) and don't rely on the view having been reached. Security belongs to the package.
- Full-page Livewire components are routed from the package route file, with explicit `web`/auth middleware (see `backend.md` → *Routes*).
- Livewire 4 filenames: avoid the ⚡ prefix inside packages, because it can break Composer.
- Menu entries into package pages are slot contributions (`Slot::NavItems`), not core links.

## Assets

Tailwind scans `packages/` automatically (v4, unless git-ignored), so utility classes in package Blade views work. Package JS/CSS enter through a **glob in the core**, never by name:

```js
// resources/js/app.js (core)
import.meta.glob('/packages/*/resources/js/index.js', { eager: true });
```

The glob loads code from every package on disk, so a package's `index.js` must only register things (Alpine components, Livewire hooks) and do nothing until its markup is on the page. Its markup is on the page only when the package is enabled.
