# Phase 7 — Removing Ziggy

Run this phase only when **all** `route()` calls in the frontend have been replaced and `sail pnpm exec tsc --noEmit` reports no errors.

### 7.1 Remove `@routes` from the Blade layouts

```blade
{{-- BEFORE --}}
<head>
    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>

{{-- AFTER --}}
<head>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
```

### 7.2 Remove Ziggy from `HandleInertiaRequests`

```php
// BEFORE
use Tightenco\Ziggy\Ziggy;

public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'ziggy' => fn () => [
            ...(new Ziggy)->toArray(),
            'location' => $request->url(),
        ],
    ]);
}

// AFTER — remove the ziggy key (and the use statement)
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        // If you implemented Option A for route().current():
        'currentRoute' => $request->route()?->getName(),
    ]);
}
```

### 7.3 Remove Ziggy from `app.tsx`

Find and remove all of these patterns:

```typescript
import route from 'ziggy-js';
import { route } from 'ziggy-js';
import { ZiggyVue } from 'ziggy-js';
window.route = route;
(window as any).route = route;
```

### 7.4 Remove the global Ziggy type declarations

```bash
grep -rn "ziggy\|RouteParam\|ZiggyConfig\|route: typeof routeFn" \
  resources/js --include="*.d.ts" --include="*.ts"
```

Remove the sections found, typically:

```typescript
// To be removed from .d.ts files
import { route as routeFn } from 'ziggy-js';
declare global {
    var route: typeof routeFn;
}
```

### 7.5 Uninstall the packages

```bash
sail composer remove tightenco/ziggy
sail pnpm remove ziggy-js
```
