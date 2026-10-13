# Phase 4 — Pattern conversion table

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
