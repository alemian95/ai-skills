# Larastan: code that passes static analysis

How to write Laravel code that passes PHPStan with Larastan up to level 10 (`max`), and how to verify it. The general PHPStan rules (levels, baseline, `@phpstan-ignore` with an identifier) are in `modern-php` (`references/tooling.md`); they are not repeated here. Signatures change between versions: check them with Boost (`search-docs`) before writing.

## Contents

- [Which level](#which-level)
- [Models](#models)
- [Queries and collections](#queries-and-collections)
- [Mixed values from the framework](#mixed-values-from-the-framework)
- [Resources, factories and the rest](#resources-factories-and-the-rest)
- [Verifying](#verifying)

## Which level

The level is the project's, read from `phpstan.neon` / `phpstan.neon.dist`. Code you write or modify passes at that level without growing the baseline. Write it already compatible with level 10 when it costs nothing more: the rules below are cheap at any level and make raising it later painless.

Level 10 adds one thing to level 9: values whose type is **implicitly** `mixed` (no declared type, framework helpers returning `mixed`) are checked like explicit `mixed`. Most Laravel errors at 9–10 come from the helpers in [Mixed values](#mixed-values-from-the-framework).

## Models

- Every column, cast and accessor read by the code is declared on the class: `@property` for writable columns, `@property-read` for accessors, `id`, timestamps and aggregates (`posts_count`). Types follow the casts (`@property CarbonImmutable $published_at`, `@property OrderStatus $status`). Larastan reads migrations, but the PHPDoc is what makes the type explicit and survives schema changes made outside migrations.
- Relations declare both generics: `@return HasMany<Post, $this>`, `@return BelongsTo<User, $this>`, `@return BelongsToMany<Role, $this>`; plus a native return type (`: HasMany`).
- `use HasFactory` carries `@use HasFactory<UserFactory>`; the factory declares `@extends Factory<User>`.
- Casts in the `casts(): array` method, with enum and value-object classes, so attribute types are not `mixed`.

## Queries and collections

- Custom Builders declare `@extends Builder<Order>` and the model points to them (`#[UseEloquentBuilder]` or `newEloquentBuilder()`); scopes receive and return `Builder<Order>`.
- `first()`, `find()`, relation access on a nullable FK return `?Model`: use `firstOrFail()` / `findOrFail()` when the record must exist, otherwise handle `null` explicitly. Never `->first()->name`.
- Collections in signatures are typed: `Collection<int, User>` (Eloquent) or `Collection<int, OrderData>` (support). Closures passed to `map`, `filter`, `each` type their parameter and return.
- `pluck()`, `value()`, `DB::table()` and raw queries return weak types: narrow them (cast, `assert`, a DTO) before they leave the query class.

## Mixed values from the framework

These return `mixed` and are the main source of errors at levels 9–10. Use the typed alternative:

| Instead of | Use |
|---|---|
| `config('billing.vat')` | `Config::integer('billing.vat')`, `Config::string(...)`, `Config::boolean(...)`, `Config::array(...)` |
| `$request->input('qty')`, `$request->get(...)`, `request('x')` | `$request->integer('qty')`, `->string(...)->toString()`, `->boolean(...)`, `->date(...)`, `->enum('status', OrderStatus::class)` |
| `$request->validated()` passed around as an array | A DTO built in the Form Request with typed accessors (`toData()`), as in [action-vs-service.md](action-vs-service.md) |
| `env('X')` outside `config/` | Read through `Config::*()`; `env()` only in config files |
| `$request->user()`, `Auth::user()`, `auth()->user()` used as non-null | A null check, or the authenticated user from the route/Form Request after the `auth` middleware, narrowed with `assert($user instanceof User)` |
| `json_decode()`, `Cache::get()`, `session()`, `$job->payload` | Narrow immediately (`is_array`, `assert`, mapping to a DTO) |
| `app(Foo::class)` / `resolve()` inside domain code | Constructor injection |

## Resources, factories and the rest

- `JsonResource` subclasses declare `@mixin Order` (and `@property Order $resource` when it is read directly); `toArray()` returns a documented shape (`array{id: int, total: string}`) when the Resource is the contract of an API.
- Arrays that cross a method boundary have a shape (`array{...}`, `list<T>`, `array<string, T>`) or become a DTO.
- Middleware, listeners and jobs type `handle()` parameters and returns; `Closure(Request): Response` for `$next`.
- Do not use `@var` to overwrite a wrong type: narrow it (`assert`, `instanceof`) so the check also happens at runtime.

## Verifying

Only if the project has PHPStan configured (`phpstan.neon*` and `vendor/bin/phpstan`). If it does not, say so in the report and do not install it.

1. Run the analysis on the files you touched, at the project level: `vendor/bin/phpstan analyse --no-progress --memory-limit=1G <files>`. Fix every error you introduced; do not add entries to the baseline or `@phpstan-ignore` to make it pass. Pre-existing errors in the touched files are reported, not fixed silently, unless the task asks for it.
2. When the touched files are clean and the configured level is below 10, run the whole configured analysis one level higher: `vendor/bin/phpstan analyse --no-progress --memory-limit=1G --level=<N+1>`. Do not change `phpstan.neon` yourself.
   - Zero errors: propose raising `level` to N+1 in the configuration.
   - Errors: report their count and the most frequent identifiers (`--error-format=json` groups them), so the user can decide whether to fix them, raise the level with a regenerated baseline, or stay.
