# Phase 6 — File-by-file migration

### 6.1 Recommended order

1. **Navigation and layout components** (`resources/js/Layouts/`) — used everywhere, high impact
2. **Pages** (`resources/js/Pages/`) — from the simplest (fewest `route()`) to the most complex
3. **Shared components** (`resources/js/Components/`)
4. **Hooks and utilities** (`resources/js/hooks/`, `resources/js/lib/`)

### 6.2 Procedure for each file

For each file with `route()` usages:

1. **Identify the routes used** — `grep "route(" resources/js/path/to/File.tsx`
2. **Find the controller** — look up the route name in `routes/web.php` or with `sail artisan route:list --name=route.name`
3. **Check the generated file** — `cat resources/js/actions/App/Http/Controllers/NameController.ts`
4. **Replace** every `route('name', params)` with the correct import and call
5. **Add the imports** at the top of the file
6. **Remove** any `ziggy-js` imports from the file
7. **Check TypeScript** — `sail pnpm exec tsc --noEmit`

### 6.3 Handling routes without a controller (closures / Route::inertia)

```php
// routes/web.php
Route::get('/about', fn() => inertia('About'))->name('about');
Route::inertia('/faq', 'Faq')->name('faq');
```

For these routes Wayfinder generates nothing in `actions/` (no controller). Use the files in `routes/`:

```typescript
import { about } from '@/routes/about';
import { faq } from '@/routes/faq';

<Link href={about()}>About us</Link>
<Link href={faq()}>FAQ</Link>
```

If the route has no name, you have two options: give it a name, or pass the URL as a hardcoded string (`href="/about"`).
