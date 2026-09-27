---
description: Migrate a Laravel + Inertia + React project from Ziggy to Wayfinder, phase by phase
---

# Migration Plan: Ziggy → Wayfinder
## Laravel + Inertia.js + React

> **Document for Claude Code** — Follow the phases in the order given. Each phase includes exact commands, patterns to search for, and files to modify. The phases are idempotent: you can re-run them without harm.

---

## Key differences to keep in mind

| Aspect | Ziggy | Wayfinder |
|---|---|---|
| Mechanism | Exports routes at runtime via `@routes` in the DOM | Generates static TypeScript files at build time |
| Main API | `route('route.name', params)` → URL string | `import { method } from '@/actions/Controller'` → `{ url, method }` object |
| Parameters | String or object with arbitrary keys | Typed arguments, generated from the controller |
| Query string | `_query: { key: value }` | `{ query: { key: value } }` as an option |
| Current route | `route().current('name.*')` | Does not exist — use `usePage().url` or share the route name from the backend |
| Inertia `useForm` | `form.post(route('name'))` | `form.submit(store())` — Wayfinder resolves URL and method automatically |
| Inertia `router` | `router.visit(route('name'))` | `router.visit(controller.method(params))` — object passed directly |
| Generated folders | none | `resources/js/wayfinder/`, `resources/js/actions/`, `resources/js/routes/` |
| `.gitignore` | n/a | **Yes** — the generated folders go in `.gitignore` |

> ⚠️ **Wayfinder is still in beta** (v0.x). The API may change before v1.0. For existing projects, assess the size of the codebase before proceeding.

> 🔴 **Do not skip Phase 8.** Removing Ziggy from the code is not enough: if Laravel Boost is not updated with `boost:update --discover`, the guidelines in `.ai/` remain the Ziggy ones and agents will keep generating `route('route.name')`.

---

## Phase 0 — Analysis and inventory

### 0.1 Check prerequisites

```bash
sail artisan --version                                          # Requires Laravel 10+ (12+ recommended)
cat composer.json | grep -E "laravel/framework|tightenco/ziggy"
cat package.json | grep -E "ziggy-js|wayfinder"
```

### 0.2 Inventory of Ziggy usages

```bash
# Count and locate every route() in the frontend
grep -rn "route(" resources/js --include="*.tsx" --include="*.ts" --include="*.jsx" --include="*.js" \
  | grep -v "node_modules" \
  | tee /tmp/ziggy-occurrences.txt

wc -l /tmp/ziggy-occurrences.txt   # total number of occurrences

# Files with the most occurrences (migration priority)
grep -rn "route(" resources/js --include="*.tsx" --include="*.ts" -l \
  | xargs -I{} sh -c 'echo "$(grep -c "route(" {}) {}"' \
  | sort -rn

# Search for route().current() — special case, no direct equivalent
grep -rn "route()\.current\|\.current(" resources/js --include="*.tsx" --include="*.ts"

# Search for _query (Ziggy query string syntax)
grep -rn "_query:" resources/js --include="*.tsx" --include="*.ts"

# Search for useForm with route()
grep -rn "useForm\|\.post(\|\.put(\|\.patch(\|\.delete(" resources/js --include="*.tsx" --include="*.ts" \
  | grep "route("

# Blade: @routes and route() on the PHP side
grep -rn "@routes" resources/views --include="*.blade.php"
grep -rn "ziggy\|Ziggy" app/ --include="*.php"
```

### 0.3 Route → controller map

You have two ways to get the full route map:

**Option A — Read `routes/web.php` directly** (and the other files in `routes/`):

```bash
cat routes/web.php
# If other route files exist:
ls routes/
cat routes/auth.php    # if present
cat routes/api.php     # if present
```

**Option B — Artisan command** (more readable tabular view):

```bash
# Full view with controller, HTTP method and route name
sail artisan route:list

# Filter by name (e.g. only "posts" routes)
sail artisan route:list --name=posts

# Export as JSON and save to a file for reference during the migration
sail artisan route:list --json > /tmp/routes-list.json
```

This map is the **Rosetta Stone** of the migration: for every `route('route.name')` found in the frontend, look up the route name in `routes/web.php` or in the `route:list` output to trace it back to the corresponding controller and method.

---

## Phase 1 — Installing Wayfinder

### 1.1 PHP package

```bash
sail composer require laravel/wayfinder
```

### 1.2 Node package

```bash
sail pnpm add @laravel/wayfinder
```

### 1.3 Vite plugin

Wayfinder integrates with Vite through the `@laravel/vite-plugin-wayfinder` plugin. In `vite.config.ts`:

```typescript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { wayfinder } from '@laravel/vite-plugin-wayfinder'; // ← add

export default defineConfig({
    plugins: [
        wayfinder(),   // ← before the other plugins
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
    ],
});
```

> The plugin takes care of regenerating the TypeScript files every time you modify a controller or a route file during `sail pnpm dev`.

### 1.4 First generation

```bash
sail artisan wayfinder:generate
```

Check the created folders:

```bash
ls resources/js/wayfinder/   # base helpers
ls resources/js/actions/     # functions for each controller
ls resources/js/routes/      # functions for named routes
```

Useful command options:

```bash
# Custom path (default: resources/js)
sail artisan wayfinder:generate --path=resources/js/wayfinder

# Actions only (without named routes)
sail artisan wayfinder:generate --skip-routes

# Named routes only (without actions)
sail artisan wayfinder:generate --skip-actions
```

### 1.5 Add to `.gitignore`

```bash
echo "resources/js/wayfinder" >> .gitignore
echo "resources/js/actions" >> .gitignore
echo "resources/js/routes" >> .gitignore
```

The files are fully regenerated on every build; they do not belong in version control.

---

## Phase 2 — TypeScript configuration

### 2.1 Check the `@` alias in `tsconfig.json`

Wayfinder uses the paths `@/actions/...` and `@/routes/...`. Make sure the `@` alias points to `resources/js`:

```json
{
    "compilerOptions": {
        "baseUrl": ".",
        "paths": {
            "@/*": ["resources/js/*"]
        }
    }
}
```

### 2.2 `package.json` scripts

Add a script to regenerate the types manually:

```json
{
    "scripts": {
        "wayfinder": "php artisan wayfinder:generate",
        "dev": "vite",
        "build": "vite build"
    }
}
```

To run them through Sail:

```bash
sail pnpm dev
sail pnpm build
sail pnpm wayfinder    # manual type regeneration
```

> ⚠️ **Known bug ([issue #55](https://github.com/laravel/wayfinder/issues/55))**: in some environments the Vite plugin does not generate all the files before the bundler imports them. If `sail pnpm build` fails with `ENOENT: no such file or directory`, run the manual generation first:
>
> ```bash
> sail artisan wayfinder:generate && sail pnpm build
> ```

---

## Phase 3 — Understanding the Wayfinder API

Before touching the code, read how Wayfinder works.

### 3.1 Structure of a generated file

For a `PostController` controller with a `show` method on the `GET /posts/{post}` route:

```typescript
// resources/js/actions/App/Http/Controllers/PostController.ts

export const show = (post: number | string, options?: { query?: QueryParams }) => ({
    url: `/posts/${post}`,
    method: 'get' as const,
})

show.url = (post: number | string) => `/posts/${post}`
```

### 3.2 Two ways to import

**Named import (recommended — optimal tree-shaking):**
```typescript
import { show, index, store } from '@/actions/App/Http/Controllers/PostController';

show(1)          // { url: '/posts/1', method: 'get' }
show.url(1)      // '/posts/1'  ← URL only, without { method }
```

**Default import (imports the whole controller — watch the bundle size):**
```typescript
import PostController from '@/actions/App/Http/Controllers/PostController';

PostController.show(1)
PostController.index()
```

### 3.3 Parameters and route model binding

```typescript
// Single parameter
show(1)
show('my-slug')

// Route model binding with a custom key (route: /posts/{post:slug})
show('my-new-post')
show({ slug: 'my-new-post' })

// Multiple parameters (route: /venues/{venue}/events/{event})
showEvent({ venue: 1, event: 2 })

// Query string — equivalent of _query in Ziggy
index({ query: { page: 2, filter: 'active' } })
```

### 3.4 Invokable controllers (single action)

```typescript
// Route::post('/contact', SendContactEmailController::class)
import SendContactEmailController from '@/actions/App/Http/Controllers/SendContactEmailController';

// Called directly, without a method
SendContactEmailController()   // { url: '/contact', method: 'post' }
```

### 3.5 Explicit methods and form variants

```typescript
// Explicit method (to override the one inferred from the controller)
update.patch({ post: 1 })   // { url: '/posts/1', method: 'patch' }
update.put({ post: 1 })     // { url: '/posts/1', method: 'put' }

// HTML form — adds _method for Laravel's method spoofing
update.form({ post: 1 })    // { action: '/posts/1?_method=PATCH', method: 'post' }
destroy.form({ post: 1 })   // { action: '/posts/1?_method=DELETE', method: 'post' }
update.form.put({ post: 1 }) // { action: '/posts/1?_method=PUT', method: 'post' }
```

### 3.6 Named routes (alternative to controllers)

Wayfinder also generates files based on the route name in `resources/js/routes/`:

```typescript
// Route: Route::get('/posts/{post}', ...)->name('posts.show')
import { show } from '@/routes/posts';

show(1)  // { url: '/posts/1', method: 'get' }
```

Use this form when you prefer to work by name rather than by controller, or for routes without a controller (closures, `Route::inertia`).

---

## Phase 4 — Pattern conversion table

### 4.1 `<Link>` with Inertia

```tsx
// ❌ BEFORE (Ziggy)
import { Link } from '@inertiajs/react';
<Link href={route('posts.index')}>List</Link>
<Link href={route('posts.show', 1)}>Details</Link>
<Link href={route('posts.show', { post: post.id })}>Details</Link>
<Link href={route('posts.edit', post.id)}>Edit</Link>

// ✅ AFTER (Wayfinder) — named import
import { Link } from '@inertiajs/react';
import { index, show, edit } from '@/actions/App/Http/Controllers/PostController';

<Link href={index()}>List</Link>
<Link href={show(1)}>Details</Link>
<Link href={show({ post: post.id })}>Details</Link>
<Link href={edit(post.id)}>Edit</Link>
```

> `<Link href={show(1)}>` works because Inertia (v2.1.2+) accepts either a string or a `{ url, method }` object as `href`.

### 4.2 `router.visit` / `router.get` / `router.post`

Inertia (v2.1.2+) accepts the Wayfinder object directly in all `router.*` methods:

```tsx
// ❌ BEFORE (Ziggy)
import { router } from '@inertiajs/react';
router.visit(route('dashboard'));
router.get(route('posts.index', { _query: { filter: 'active' } }));
router.post(route('posts.store'), formData);
router.put(route('posts.update', post.id), formData);
router.delete(route('posts.destroy', post.id));

// ✅ AFTER (Wayfinder)
import { router } from '@inertiajs/react';
import { index, store, update, destroy } from '@/actions/App/Http/Controllers/PostController';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';

router.visit(DashboardController.index());
router.visit(index({ query: { filter: 'active' } }));
router.visit(store(), { data: formData });
router.visit(update(post.id), { data: formData }); // the method (PUT/PATCH) comes from the object
router.visit(destroy(post.id));                     // the method (DELETE) comes from the object
```

### 4.3 `useForm`

Wayfinder integrates natively with `useForm` through `form.submit()`:

```tsx
// ❌ BEFORE (Ziggy)
import { useForm } from '@inertiajs/react';
const form = useForm({ title: '', body: '' });

form.post(route('posts.store'));
form.put(route('posts.update', post.id));
form.patch(route('posts.update', post.id));
form.delete(route('posts.destroy', post.id));

// ✅ AFTER (Wayfinder)
import { useForm } from '@inertiajs/react';
import { store, update, destroy } from '@/actions/App/Http/Controllers/PostController';

const form = useForm({ title: '', body: '' });

form.submit(store());                        // POST to /posts
form.submit(update({ post: post.id }));     // PUT/PATCH to /posts/{id}
form.submit(destroy({ post: post.id }));    // DELETE to /posts/{id}
```

> `form.submit()` reads `method` directly from the Wayfinder object: no ambiguity about GET/POST/PUT/DELETE.

### 4.4 Query string (`_query` → `query`)

```tsx
// ❌ BEFORE (Ziggy) — _query for extra parameters not present in the route
route('posts.index', { _query: { page: 2, search: 'foo', sort: 'asc' } })
// → /posts?page=2&search=foo&sort=asc

// ✅ AFTER (Wayfinder)
import { index } from '@/actions/App/Http/Controllers/PostController';

index({ query: { page: 2, search: 'foo', sort: 'asc' } })
// → { url: '/posts?page=2&search=foo&sort=asc', method: 'get' }

// URL only (without { method }):
index.url({ query: { page: 2, search: 'foo', sort: 'asc' } })
// → '/posts?page=2&search=foo&sort=asc'
```

### 4.5 Route model binding with an Eloquent object

```tsx
// ❌ BEFORE (Ziggy) — accepts the object, uses the 'id' key automatically
const post = { id: 1, title: 'Hello' };
route('posts.show', post)  // → /posts/1

// ✅ AFTER (Wayfinder) — pass the value explicitly
import { show } from '@/actions/App/Http/Controllers/PostController';

show(post.id)              // → { url: '/posts/1', method: 'get' }
show({ post: post.id })    // → { url: '/posts/1', method: 'get' }
```

### 4.6 `fetch` / `axios`

```tsx
// ❌ BEFORE (Ziggy)
const res = await fetch(route('api.posts.index'));
const res = await axios.get(route('api.posts.show', id));
await axios.post(route('api.posts.store'), payload);

// ✅ AFTER (Wayfinder) — use .url to extract just the string
import { index, show, store } from '@/actions/App/Http/Controllers/Api/PostController';

const res = await fetch(index().url);
const res = await axios.get(show(id).url);
await axios.post(store().url, payload);

// Or, with axios, pass the whole object (url + method):
const { url, method } = store();
await axios({ method, url, data: payload });
```

### 4.7 Invokable controllers (single action)

```tsx
// Route: Route::post('/contact', SendContactEmailController::class)->name('contact.send')

// ❌ BEFORE (Ziggy)
router.post(route('contact.send'), formData);

// ✅ AFTER (Wayfinder)
import SendContactEmailController from '@/actions/App/Http/Controllers/SendContactEmailController';

router.visit(SendContactEmailController(), { data: formData });
// or with useForm:
form.submit(SendContactEmailController());
```

### 4.8 HTML forms (non-Inertia, with method spoofing)

```tsx
// ✅ WAYFINDER — .form() handles _method spoofing automatically
import { update, destroy } from '@/actions/App/Http/Controllers/PostController';

// PATCH with _method spoofing
<form {...update.form({ post: 1 })}>
  {/* renders: action="/posts/1?_method=PATCH" method="post" */}
</form>

// Explicit PUT
<form {...update.form.put({ post: 1 })}>
  {/* renders: action="/posts/1?_method=PUT" method="post" */}
</form>

// DELETE
<form {...destroy.form({ post: 1 })}>
  {/* renders: action="/posts/1?_method=DELETE" method="post" */}
</form>
```

---

## Phase 5 — Special case: `route().current()`

Ziggy provides `route().current('route.name')` to detect the active route. **Wayfinder has no direct equivalent.** Choose one of the three alternatives:

### Option A — Share the route name from the backend (recommended)

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'currentRoute' => $request->route()?->getName(),
    ]);
}
```

```tsx
// In the React component
import { usePage } from '@inertiajs/react';

const { currentRoute } = usePage().props as { currentRoute: string | null };

// Ziggy → Wayfinder equivalents:
// route().current('posts.index')  →  currentRoute === 'posts.index'
// route().current('posts.*')      →  currentRoute?.startsWith('posts.')
```

### Option B — Compare with `usePage().url`

```tsx
import { usePage } from '@inertiajs/react';

const { url } = usePage();
const isPostsPage = url.startsWith('/posts');
```

### Option C — Compare the current URL with the one generated by Wayfinder

```tsx
import { usePage } from '@inertiajs/react';
import { index } from '@/actions/App/Http/Controllers/PostController';

const { url } = usePage();
const isPostsIndex = url === index().url;
```

---

## Phase 6 — File-by-file migration

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

---

## Phase 7 — Removing Ziggy

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

---

## Phase 8 — Laravel Boost: updating guidelines and skills

> ⚠️ **Critical phase, do not skip it.** Boost generates guidelines and skills based on the packages present in `composer.json`. Until you update them, they remain the Ziggy ones and agents will keep writing `route('route.name')` even though Ziggy has already been uninstalled.

Boost includes both an **AI guideline** for Wayfinder (`core` version) and a dedicated **skill** `wayfinder-development`. Updating Boost is therefore sufficient: the correct instructions on how to use Wayfinder come from the package; they do not need to be written by hand.

### 8.1 Update the dependency

```bash
sail composer update laravel/boost
```

If Boost is not installed in the project:

```bash
sail composer require laravel/boost --dev
sail artisan boost:install
```

During the `boost:install` wizard, select the agents in use (Claude Code) and enable guidelines and skills.

### 8.2 Update the Boost resources

The `boost:update` command regenerates the local guidelines and skills, aligning them with the installed packages:

```bash
sail artisan boost:update
```

> 🔑 **In the context of this migration you need `--discover`.** By default `boost:update` only updates the resources already published in the application. Wayfinder is a newly installed package, so you must use the option that makes it scan the project for new packages and offer to publish their guidelines and skills:

```bash
sail artisan boost:update --discover
```

Accept publishing the Wayfinder resources when the command offers them.

### 8.3 Automate future updates

Add `boost:update` to the Composer scripts, so the guidelines stay aligned on every `composer update`:

```json
{
  "scripts": {
    "post-update-cmd": [
      "@php artisan boost:update --ansi"
    ]
  }
}
```

### 8.4 Verify that the Ziggy references are gone

```bash
# Boost guidelines and skills
grep -rn "ziggy\|Ziggy" .ai/ 2>/dev/null

# Agent context files generated by Boost
grep -rn "ziggy\|Ziggy" CLAUDE.md AGENTS.md 2>/dev/null

# Verify that the Wayfinder resources have been published
ls .ai/skills/ | grep wayfinder
grep -rln "wayfinder\|Wayfinder" .ai/ CLAUDE.md 2>/dev/null
```

You expect to find the `wayfinder-development` skill in `.ai/skills/` and no occurrences of Ziggy.

> The files generated by Boost (`.mcp.json`, `CLAUDE.md`, `AGENTS.md`, `boost.json`) can be regenerated with `boost:install` / `boost:update`: the documentation suggests putting them in `.gitignore`. If they are versioned in your project, remember to commit the changes after the update.

### 8.5 If the Boost guidelines are not enough

Only if, after `boost:update --discover`, the agent keeps generating `route()` — typically because the project has a hand-written `CLAUDE.md` with pre-existing Ziggy instructions, independent of Boost — add custom guidelines.

Boost automatically loads the `.md` or `.blade.php` files present in `.ai/guidelines/`, including them in its own guidelines on the next `boost:install`. Create `.ai/guidelines/wayfinder-project.md`:

```markdown
## Frontend routing — this project uses Wayfinder, NOT Ziggy

Ziggy has been removed from the project. The global `route()` function no longer exists
in the TypeScript/React code.

- **DO NOT use** `route('route.name')` or any import from `ziggy-js`.
- Import the pre-generated functions: `import { show } from '@/actions/App/Http/Controllers/PostController'`
- For routes without a controller (closures, `Route::inertia`): `import { name } from '@/routes/name'`
- To detect the active route use the shared `currentRoute` prop, not `route().current()`.
- Before writing code that generates URLs, check the function signature in the file
  generated in `resources/js/actions/`.
- The `resources/js/wayfinder/`, `actions/` and `routes/` folders are in `.gitignore`
  and regenerated on every build: never modify them by hand.
```

Then regenerate:

```bash
sail artisan boost:install
```

> Boost also supports **overriding** its built-in guidelines: by creating a file with the same path as a Boost guideline, your version replaces the default one. Use it only if you need to change the official Wayfinder instructions, not to add to them.

### 8.6 Verify that the agent follows the new instructions

Open a **new session** of Claude Code (guidelines are loaded at startup) and test with a request that requires a route:

```
Add a link to the post edit page in the posts table
```

The output must import from `@/actions/...` and must **not** contain `route(`. If it still uses `route()`, in order:

1. Verify that `boost:update --discover` completed successfully and that `.ai/skills/wayfinder-development/` exists
2. Check that `.ai/` and `CLAUDE.md` contain no leftover Ziggy guidelines (grep from step 8.4)
3. Add the custom guideline from step 8.5 and re-run `sail artisan boost:install`

---

## Phase 9 — Final verification

### 9.1 No Ziggy leftovers

```bash
# Frontend — no route() or ziggy imports
grep -rn "ziggy\|route(" resources/js \
  --include="*.tsx" --include="*.ts" --include="*.jsx" --include="*.js" \
  | grep -v "node_modules"

# Backend and Blade
grep -rn "@routes\|Ziggy\|ziggy" \
  resources/views app \
  --include="*.php" --include="*.blade.php"

# Package files
grep -i "ziggy" composer.json package.json

# Boost guidelines and agent context files
grep -rn "ziggy\|Ziggy" .ai/ CLAUDE.md AGENTS.md 2>/dev/null
```

### 9.2 TypeScript with no errors

```bash
sail pnpm exec tsc --noEmit
```

### 9.3 Production build

```bash
# Generate the types before building (workaround for bug #55)
sail artisan wayfinder:generate && sail pnpm build
```

### 9.4 Functional smoke test

```bash
sail up -d
sail pnpm dev
```

Verify manually in the browser:
- navigation between pages (Link)
- form submission (`useForm`)
- API calls (`fetch` / `axios`)
- active state in the navigation (if you implemented Option A/B/C for `route().current()`)

---

## Final checklist

```
[ ] Wayfinder installed (sail composer require + sail pnpm add)
[ ] Vite plugin (@laravel/vite-plugin-wayfinder) configured in vite.config.ts
[ ] First generation run: sail artisan wayfinder:generate
[ ] Generated folders (wayfinder/, actions/, routes/) added to .gitignore
[ ] @ alias in tsconfig.json points to resources/js
[ ] All route('name', params) replaced with imports from @/actions/ or @/routes/
[ ] _query: {} replaced with { query: {} }
[ ] form.post/put/patch/delete(route()) replaced with form.submit(controller.method())
[ ] router.visit/get/post/delete(route()) updated with Wayfinder objects
[ ] route().current() replaced with currentRoute prop or url comparison
[ ] Routes without a controller (closure/inertia) handled via @/routes/
[ ] @routes removed from the Blade layouts
[ ] HandleInertiaRequests cleaned up (use Ziggy removed, ziggy key removed)
[ ] Ziggy imports and declarations removed from app.tsx and .d.ts files
[ ] sail composer remove tightenco/ziggy run
[ ] sail pnpm remove ziggy-js run
[ ] laravel/boost updated: sail composer update laravel/boost
[ ] Boost resources updated: sail artisan boost:update --discover
[ ] Wayfinder resources published (.ai/skills/wayfinder-development/ present)
[ ] No references to Ziggy in .ai/ and CLAUDE.md (verified with grep)
[ ] boost:update added to post-update-cmd in composer.json (optional)
[ ] Verified in a new session that the agent generates imports from @/actions/ and not route()
[ ] sail pnpm exec tsc --noEmit with no errors
[ ] sail artisan wayfinder:generate && sail pnpm build with no errors
[ ] Functional smoke test passed
```

---

## References

- [laravel/wayfinder — GitHub (stable)](https://github.com/laravel/wayfinder)
- [laravel/wayfinder — GitHub (next beta)](https://github.com/laravel/wayfinder/tree/next)
- [tighten/ziggy — GitHub](https://github.com/tighten/ziggy)
- [Inertia.js — Manual Visits with Wayfinder](https://inertiajs.com/manual-visits)
- [Inertia.js — Routing](https://inertiajs.com/routing)
- [Laravel Boost — Keeping Boost Resources Updated](https://laravel.com/docs/13.x/boost#keeping-boost-resources-updated)
- [Laravel Boost — AI Guidelines](https://laravel.com/docs/13.x/boost#ai-guidelines)
- [Known issue #55 — Vite build bug](https://github.com/laravel/wayfinder/issues/55)
