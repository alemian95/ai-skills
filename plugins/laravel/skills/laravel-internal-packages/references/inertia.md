# Inertia starter kits (React, Vue, Svelte)

Examples use `.vue`. For React use `.tsx`, for Svelte use `.svelte`. The mechanism is identical, and only the slot component differs by framework.

```
packages/<name>/resources/js/
├── slots.ts          # slots.tsx for React: { slotName: Component }
├── pages/*.vue       # Inertia pages rendered as '<name>::<page>'
└── components/
```

## Core wiring (written once)

### 1. Page resolution: one module for client and SSR

`app.ts` and `ssr.ts` both import the same resolver. The page naming convention then lives in a single place.

```ts
// resources/js/lib/resolve-page.ts
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';

const corePages = import.meta.glob<DefineComponent>('../pages/**/*.vue');
const packagePages = import.meta.glob<DefineComponent>('/packages/*/resources/js/pages/**/*.vue');

/** `page` → resources/js/pages/page.vue · `pkg::page` → packages/pkg/resources/js/pages/page.vue */
export function resolvePage(name: string) {
    const separator = name.indexOf('::');

    if (separator === -1) {
        return resolvePageComponent(`../pages/${name}.vue`, corePages);
    }

    const pkg = name.slice(0, separator);
    const page = name.slice(separator + 2);

    return resolvePageComponent(`/packages/${pkg}/resources/js/pages/${page}.vue`, packagePages);
}
```

```ts
// app.ts and ssr.ts
createInertiaApp({ resolve: resolvePage, /* … */ });
```

### 2. Blade root view: skip the per-page Vite entry for package pages

Starter kits add the current page as a Vite entry in `resources/views/app.blade.php` to preload it. For `billing::settings` that produces `resources/js/pages/billing::settings.vue`, which fails with *Unable to locate file in Vite manifest*, and HTTP tests don't catch it because they run `withoutVite()`. Skip the entry for package pages rather than re-encoding the path convention in PHP:

```blade
@vite(['resources/js/app.ts', ...(str_contains($page['component'], '::') ? [] : ["resources/js/pages/{$page['component']}.vue"])])
```

Trade-off: package pages lose the modulepreload hint and load through the dynamic import instead, one extra round trip. The alternative (translating `pkg::page` into a path in Blade) duplicates the convention in a third place.

### 3. Slots: one registry, one tiny component per framework

```ts
// resources/js/lib/package-slots.ts
/** Exactly the slots the core mounts, no more. New slot = mount <PackageSlot name="…"> in a core page + add it here. */
export type SlotName = 'dashboard-widgets';

export type SlotMap = Partial<Record<SlotName, unknown>>;

const modules = import.meta.glob<{ default: SlotMap }>('/packages/*/resources/js/slots.{ts,tsx}', { eager: true });

const registry = Object.entries(modules).map(([path, module]) => ({
    pkg: path.split('/')[2],
    slots: module.default,
}));

/**
 * Components registered on `name` by **enabled** packages. Files on disk are not enough:
 * the backend shares the enabled list, so a disabled package renders nothing.
 */
export function slotComponents<C>(name: SlotName, enabled: readonly string[] = []): { key: string; component: C }[] {
    return registry.flatMap(({ pkg, slots }) => {
        const component = slots[name];

        return component && enabled.includes(pkg) ? [{ key: `${pkg}:${name}`, component: component as C }] : [];
    });
}
```

Type the shared prop once, next to the kit's shared data type (`resources/js/types`): `packages: string[]`. The backend shares it in `HandleInertiaRequests` (see `backend.md` → *Shared data*).

**Vue** (`resources/js/components/PackageSlot.vue`)

```vue
<script setup lang="ts">
import { slotComponents, type SlotName } from '@/lib/package-slots';
import { usePage } from '@inertiajs/vue3';
import { computed, type Component } from 'vue';

defineOptions({ inheritAttrs: false }); // context props are forwarded to every contribution
const props = defineProps<{ name: SlotName }>();
const page = usePage();
const entries = computed(() => slotComponents<Component>(props.name, page.props.packages));
</script>

<template>
    <component :is="entry.component" v-for="entry in entries" :key="entry.key" v-bind="$attrs" />
</template>
```

**React** (`resources/js/components/package-slot.tsx`)

```tsx
import { slotComponents, type SlotName } from '@/lib/package-slots';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import type { ComponentType } from 'react';

type Props = { name: SlotName } & Record<string, unknown>;

export function PackageSlot({ name, ...context }: Props) {
    const { packages } = usePage<SharedData>().props;

    return (
        <>
            {slotComponents<ComponentType<Record<string, unknown>>>(name, packages).map(({ key, component: Component }) => (
                <Component key={key} {...context} />
            ))}
        </>
    );
}
```

**Svelte 5** (`resources/js/components/PackageSlot.svelte`)

```svelte
<script lang="ts">
    import { slotComponents, type SlotName } from '@/lib/package-slots';
    import { page } from '@inertiajs/svelte';
    import type { Component } from 'svelte';

    let { name, ...context }: { name: SlotName } & Record<string, unknown> = $props();
    const entries = $derived(slotComponents<Component<Record<string, unknown>>>(name, $page.props.packages as string[]));
</script>

{#each entries as { key, component: SlotComponent } (key)}
    <SlotComponent {...context} />
{/each}
```

Mount point in a core page, with no condition on who fills it:

```vue
<PackageSlot name="dashboard-widgets" :user="user" />
```

Context props say **what** the slot is rendering for (the user on this page), not the package's data. The contribution fetches its own data from its own endpoint (Wayfinder route, `fetch`/`useHttp`/axios as the kit does). Core page props are not extended for it.

## Package side

```ts
// packages/billing/resources/js/slots.ts
import type { SlotMap } from '@/lib/package-slots';
import BillingWidget from './components/BillingWidget.vue';

export default { 'dashboard-widgets': BillingWidget } satisfies SlotMap;
```

- `satisfies SlotMap` turns a typo in a slot name into a type error instead of a silent no-show.
- The eager glob puts slot components in the main chunk. For heavy widgets, register a lazy wrapper (`defineAsyncComponent`, `React.lazy`, a dynamic `import()` in Svelte).
- Package pages and components freely use core layouts and UI (`@/layouts/…`, `@/components/…`), since the dependency runs package → core.
- Controllers render `Inertia::render('billing::settings', [...])`.

## Build and tooling

| Concern | What to do |
|---|---|
| Wayfinder | Packages' routes exist only where enabled, but their frontend is always bundled. Generate with all packages on, so new packages need no change here: `wayfinder({ formVariants: true, command: 'PACKAGES_FORCE_ALL=true php artisan wayfinder:generate' })` (the plugin appends its own flags to `command`; on native Windows use `cross-env`) |
| Routes in frontend | Package code imports its routes from Wayfinder (`@/actions/Packages/Billing/...`), never hand-written URLs. **Core code never imports package routes.** A core menu entry pointing into a package is a slot contribution |
| TypeScript | Add `packages/*/resources/js/**/*` to `tsconfig.json` → `include`. The `@/` alias already works |
| Tailwind v4 | Scans the project root automatically, `packages/` included, unless it is git-ignored or outside the Vite root: only then `@source '../../packages';` in `app.css`. Tailwind v3: add the path to `content` |
| ESLint | In core files, `no-restricted-imports` with pattern `**/packages/**`. `import.meta.glob` is not an import, so the two registries above stay legal |
| SSR | Covered by the shared `resolve-page.ts`. Slot components must be SSR-safe like any core component |
