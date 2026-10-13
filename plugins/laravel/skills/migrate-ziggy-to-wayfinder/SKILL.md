---
name: migrate-ziggy-to-wayfinder
description: Migrate a Laravel + Inertia + React project from Ziggy to Wayfinder, phase by phase. Use only when the user explicitly asks to migrate from Ziggy to Wayfinder or to remove Ziggy; do not use it for questions or bugs about `route()` or Ziggy in a project that keeps Ziggy.
---

# Migration: Ziggy → Wayfinder (Laravel + Inertia + React)

Follow the phases in order. Read each phase file only when that phase starts: it holds the exact commands, the patterns to search for and the files to modify. The phases are idempotent: you can re-run them without harm.

The commands assume Laravel Sail and pnpm. Adapt them to the project before running them: without Sail drop the `sail` prefix (`php artisan`, `composer`), and use the package manager the project's lockfile shows (`npm`, `yarn`, `bun`).

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

## Phases

- [Phase 0 — Analysis and inventory](references/phase-0-inventory.md)
- [Phase 1 — Installing Wayfinder](references/phase-1-install.md)
- [Phase 2 — TypeScript configuration](references/phase-2-typescript.md)
- [Phase 3 — Understanding the Wayfinder API](references/phase-3-wayfinder-api.md)
- [Phase 4 — Pattern conversion table](references/phase-4-conversion-table.md)
- [Phase 5 — Special case: `route().current()`](references/phase-5-current-route.md)
- [Phase 6 — File-by-file migration](references/phase-6-file-by-file.md). Keep phases 3 and 4 at hand: they hold the API and the conversion table.
- [Phase 7 — Removing Ziggy](references/phase-7-remove-ziggy.md)
- [Phase 8 — Laravel Boost: updating guidelines and skills](references/phase-8-boost.md)
- [Phase 9 — Final verification](references/phase-9-verification.md)

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
[ ] No references to Ziggy in .ai/, CLAUDE.md and AGENTS.md, where present (verified with grep)
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
