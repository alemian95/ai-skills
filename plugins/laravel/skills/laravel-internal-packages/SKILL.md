---
name: laravel-internal-packages
description: Use when adding, extracting or reviewing an optional feature in a Laravel application that must live as an internal package/module/plugin under `packages/` in the same repository — a feature sold separately, enabled per customer instance, piloted with one client, or removable from the product. Triggers include "make it a package", "plugin", "module", "feature flag per instance", "the core must not know about X", a single deploy artifact serving many customers, `packages/*` with its own ServiceProvider, and adding a slot, render hook, event or registry to the core so a package can hook in. Applies to Inertia starter kits (React, Vue, Svelte), Livewire, Blade and Filament.
---

# Laravel internal packages

## Principle

**The core never names a package.** No import, no injected service, no route, no `if`, no column, no view reference carries a package's name. Every other rule exists to keep this one true, and one `import` or one `if` is enough to turn the package back into a core feature living in a different folder.

A package lives in `packages/<name>/`, depends on the core, and never the other way round. It hooks into the core only through **generic hook points** owned by the core: frontend slots, Filament render hooks, domain events, and contribution registries. When a hook point is missing, the only change allowed in the core is adding a new *generic* one, which stays inert while nobody uses it.

These are internal packages in a monorepo, not packages published to their own repositories: one commit can cross core and package, and no private registry is needed. Move to separate repositories only if a package needs its own release cycle or brings heavy dependencies that every customer would pay for.

## The two switches

| Switch | Where | Decides |
|---|---|---|
| Product | `composer require` / `composer remove` of the path package | whether the code ships in the artifact |
| Instance | `<NAME>_ENABLED` in the instance `.env`, read from `config/<name>.php` → `enabled` | whether that customer's instance registers it |

Default is `false`. A disabled package has the same observable effect as a removed one: no routes, no migrations, no listeners, no contributions, no UI. Its files stay on disk and its frontend chunks stay in the bundle, but nothing can reach them.

## Hook points by stack

| Need | Inertia (React/Vue/Svelte) | Blade / Livewire | Filament |
|---|---|---|---|
| UI piece inside a core page | `<PackageSlot name="…">` + `slots.{ts,tsx}` | `<x-package-slot name="…">` + slot registry | render hook (`FilamentView::registerRenderHook`) |
| Own page | `Inertia::render('<name>::<page>')` | `view('<name>::…')` / Livewire full-page | Filament `Plugin` (resources, pages, widgets) |
| React to a core fact | domain event emitted by the core | same | same |
| Add to a core output (export, menu, list) | contribution registry (container tag + core contract) | same | same |

Filament has its own slot mechanism (render hooks) and its own extension unit (`Plugin`): use them and don't reinvent them. The only core change is one generic line in the panel provider that loads tagged plugins.

## Implementation

Load only the reference files for the stack in front of you:

- **Always:** `references/backend.md`. It covers the base provider with the gate, composer path packages, config, routes, migrations and schema rules, events, contribution registries, and shared data.
- Inertia starter kits: `references/inertia.md` (React, Vue, Svelte: slots, page resolution, Vite entry, Wayfinder, TypeScript, Tailwind).
- Blade or Livewire: `references/blade-livewire.md`.
- Filament: `references/filament.md`.
- **Always, before calling it done:** `references/verification.md` (arch tests, neutrality tests, shutdown proof, CI).

If the project already has package infrastructure (a base provider, a slot component, registries), read it first and extend it. Don't add a second mechanism next to it. If it differs from this skill, point out the difference and ask before migrating.

## Checklist: new package

1. `packages/<name>/composer.json` with PSR-4 `Packages\<Name>\` and `extra.laravel.providers`; `composer require packages/<name>:@dev` (path repository `packages/*` in the root).
2. `<Name>ServiceProvider extends App\Packages\PackageServiceProvider`, with everything inside `registerPackage()` / `bootPackage()`.
3. `config/<name>.php` with `'enabled' => (bool) env('<NAME>_ENABLED', false)`; document the variable in the package README.
4. Routes with explicit `web` + auth/authorization middleware, and route names that follow the host's conventions.
5. Own tables only; relations to core models via `resolveRelationUsing()`.
6. UI only through existing slots, render hooks or the package's own pages; data through the package's own endpoints/props.
7. Core facts through events, core outputs through registries; missing hook point → add a generic one plus its neutrality test.
8. Tests in `packages/<name>/tests/`.
9. Run `references/verification.md` end to end.

## Checklist: adding a hook point to the core

A core change is acceptable only if it is **essential** (without it something does not work, it is not just less convenient), **generic** (it would serve any package, and its name describes the core concept, never the consumer), and **neutral** (with no consumer, behaviour is byte-for-byte identical, proven by a test). Changing a behaviour already in use is not forbidden, but it must be stated and proven with a test, because "nothing changes today" is a property of today's data and not of the change.

## Common mistakes

| Mistake | Fix |
|---|---|
| Gate only in `boot()` while `register()` binds/tags | The base provider gates both; never override `register()`/`boot()` |
| Package migration adds a column to a core table | Package tables only; if the core really needs a column, the core adds it, with a generic name |
| `Inertia::share` with package data | Share only the enabled-package list (done once by the core); data comes from package endpoints, deferred props or slot context props |
| Build fails on an instance where the package is off | Wayfinder generates with `PACKAGES_FORCE_ALL=true` at build time |
| `Unable to locate file in Vite manifest` for `<name>::page` | The Blade `@vite` per-page entry must skip `::` pages |
| Listeners never fire | Event discovery doesn't scan `packages/`: register listeners in `bootPackage()` |
| Package A imports package B | Forbidden. Shared code moves up into the core and must be justified there on its own merits |
| Sync listener throws inside the core's request | Queue it, or catch and log, and decide which one explicitly; it never joins the core's transaction |
| "Removing it doesn't break anything" asserted, not proven | Arch test + neutrality tests + shutdown proof |
