# Filament

Filament already has both mechanisms a package needs. Use them and don't build parallel ones:

- **`Filament\Contracts\Plugin`** carries resources, pages, widgets and panel configuration into a panel.
- **Render hooks** (`FilamentView::registerRenderHook`) are Filament's slots: they inject views into panel pages.

The core only has to let tagged plugins in.

## Core wiring (one generic line per panel)

```php
final class AdminPanelProvider extends PanelProvider
{
    /** Container tag for Filament plugins contributed by packages. */
    public const PLUGINS = 'filament.admin.plugins';

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            // …core configuration…
            ->plugins([...$this->app->tagged(self::PLUGINS)]);
    }
}
```

With several panels, each gets its own constant and tag. A package tags the panels it targets.

## Package side

```php
// BillingServiceProvider: tags go in registerPackage(). Discovered package providers
// register before App\Providers\*, so the tag exists when the panel is built.
protected function registerPackage(): void
{
    $this->app->tag([BillingPlugin::class], AdminPanelProvider::PLUGINS);
}

protected function bootPackage(): void
{
    $this->loadViewsFrom($this->path('resources/views'), 'billing');
    Gate::policy(Invoice::class, InvoicePolicy::class);
}
```

```php
namespace Packages\Billing\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

final class BillingPlugin implements Plugin
{
    public function getId(): string
    {
        return 'billing';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([InvoiceResource::class])
            ->pages([BillingSettings::class])
            ->widgets([RevenueWidget::class]); // shown on the panel dashboard
        // or: ->discoverResources(in: __DIR__.'/Resources', for: 'Packages\\Billing\\Filament\\Resources')
    }

    /** Runs only when this panel is the one serving the request. */
    public function boot(Panel $panel): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_START,
            fn (): \Illuminate\Contracts\View\View => view('billing::filament.overdue-banner'),
            scopes: ListCustomers::class, // a core resource page: the core doesn't know about it
        );
    }
}
```

Rules:

- **Authorization.** Register policies explicitly with `Gate::policy()` in `bootPackage()`. Every resource, page and widget of the package enforces its own access (`canAccess()`, `canView()`, policies). Security belongs to the package.
- **Core resources are not edited for a package.** Add to a core page through a render hook. If you need to add a column or action to a core resource table, the core resource must first expose a generic contribution registry (see `backend.md` → *Contribution registries*): same shape, same append-only rule, same neutrality test.
- **Livewire components** used inside package Filament pages are registered like any package Livewire component (`blade-livewire.md`).
- **Caches.** If the deploy caches Filament components (`filament:optimize` / `php artisan optimize`), rebuild the cache after toggling a package flag, together with `config:cache`.
- **Verify on the installed version.** Check the plugin, render hook and panel APIs against the installed Filament docs (Boost `search-docs` if available) before writing them: hook names and discovery signatures change between major versions.
