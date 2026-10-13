# Phase 3 — Understanding the Wayfinder API

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
