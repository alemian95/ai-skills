# Phase 1 — Installing Wayfinder

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
