---
name: laravel-architecture
description: Use when defining, reviewing or refactoring the architecture of a Laravel application (optionally with React/TypeScript via Inertia) — deciding where logic, data or a domain rule belongs, how to organize folders, functional areas and bounded-context modules, which framework component fits a job (Form Request, Policy, Middleware, Action, Service, DTO, Value Object, Enum, Model, scope or custom Builder, Observer, Event, Listener, Job, Command, Resource, Service Provider, exception), or when code shows fat controllers, business logic in models or observers, authorization repeated in several places, magic strings instead of Enums, missing transactions, side-effects fired before commit, modules reaching into each other's models, or hand-written URLs in the frontend. Complements Laravel Boost, does not replace it.
---

# Laravel architecture

## Scope

This skill decides **where** logic lives and **how** responsibilities are split. Laravel Boost decides **how** code is written: framework conventions, package versions, Artisan, Pint, Pest/PHPUnit, Inertia, Wayfinder. Before writing code, use Boost's tools (`search-docs`, `database-schema`, `list-routes`, `tinker`...).

If the two seem to conflict, Boost wins on "how it is written" and this skill wins on "where it goes". If the conflict persists, stop and ask. Do not restate Boost rules here or in the code review: a rule written in two places diverges.

Related skills, if installed:
- `laravel-action-vs-service`: detailed boundary between Action, Service, Event/Listener and Job.
- `laravel-internal-packages`: optional features as internal packages under `packages/`.
- `engineering:clean-code`: SOLID, DRY, YAGNI, SSOT in general.

Best practices component by component (Controller, Form Request, Policy, Model, Observer, Job, Exception, Service Provider...): [references/components.md](references/components.md). Read it when you create, use or review a specific component.

## Principles, read through Laravel

- **Single Responsibility**: one layer, one job (table below). A class that does two distinct things is split.
- **Dependency Inversion**: dependencies are injected (constructor injection, Service Container), never `new` of a service or a facade inside domain logic.
- **Interface Segregation**: small interfaces, shaped around the caller.
- **YAGNI**: no parameters, layers or abstractions for hypothetical requirements. An abstraction is born at the second real occurrence, or when an already-decided requirement calls for it. When an abstraction and YAGNI pull in opposite directions, YAGNI wins, except for the *Contracts* cases below.
- **DRY**: one piece of logic, one representation. Similar code with different purposes is not forcibly unified.
- **Composition over inheritance**; design patterns only for a problem present in the code.

### SSOT: the single home of each rule

| Knowledge | Single home | Everyone else |
|---|---|---|
| Who may do X | Policy / Gate | Routes, Form Requests, frontend (`can` flags) call it |
| Whether X is possible in the current state | Enum method or state machine (`$order->status->canBeCancelled()`) | Policy and Action both call the same predicate |
| Statuses, types, permission and queue names | Backed Enum | Imported, never written as a string |
| Configuration values | `config/<area>.php` | Read through `config()`, never inline |
| Input validation | Form Request | Not re-validated by hand in the Action |
| Output shape | API Resource / Inertia props | The controller does not assemble arrays by hand |
| URLs | Named routes + Wayfinder | No hand-written URL, backend or frontend |
| Shared TS types and Enum values | Generated from the backend, or written once in `resources/js/types` | Imported |

## Backend: where logic goes

| Layer | Responsibility | Must not |
|---|---|---|
| Route / Middleware | Binding, cross-cutting HTTP concerns (auth, locale, tenant, throttling) | Contain domain rules |
| Controller | Receives the request, delegates, returns the response | Contain business logic, complex Eloquent queries, SDK calls |
| Form Request | Input validation, authorization delegated to the Policy, mapping to the DTO | Write to the DB, contain domain logic |
| Action | A complete, context-free business operation (HTTP, console, Job call it the same way) | Know about `Request`, session or response |
| Service | Pure logic reused by 2+ callers, or a wrapper around an external dependency | Orchestrate a use case (that is the Action's job) |
| Query class / Query Object | Complex or reusable reads, filters and sorting of an index | Modify state |
| Policy / Gate | Single source of authorization rules | Be replicated in controllers, frontend or queries |
| Event / Listener | Decoupled side-effects (email, notifications, analytics, integrations) | Contain the main logic of the operation |
| Job / Command | Asynchronous, slow or scheduled work; invokes an Action | Duplicate the Action's logic |
| Model | Relationships, casts, scopes, accessors, predicates on its own attributes | Become a container for business logic or side-effects |
| Observer | Persistence invariants of the model (uuid, slug, denormalized counters) | Trigger business side-effects |
| Resource | Shape of the output, API or Inertia props | Run queries or domain logic |

For uncertain cases between Action, Service, Event and Job, use `laravel-action-vs-service`.

### Rules

- **Typed input and output.** Actions receive a DTO (`readonly` class, or `spatie/laravel-data` if already installed) or typed parameters, never `$request->all()`, and return a precise type. The HTTP layer builds the DTO (e.g. `$request->toData()` on the Form Request); the DTO does not know `Request`.
- **Guards first.** The Action checks domain invariants before writing anything and throws a dedicated domain exception (`OrderException::cannotBeCancelled($order)`), calling the same predicate the Policy uses.
- **Action shape** (public method, interface, naming) follows `laravel-action-vs-service`; this skill does not redefine it.
- **Explicit transactions.** An operation that writes to multiple tables runs in `DB::transaction()` inside the Action. Events that produce external side-effects fire after commit (`ShouldDispatchAfterCommit` or `afterCommit()`).
- **Essential vs optional side-effects.** An external call the operation cannot be correct without (charge, refund, reserving stock at a supplier) is a step of the Action, through its Contract, with an explicit failure path ([components](references/components.md#action)). An optional one (email, analytics, CRM sync) is a queued listener: if it fails, the operation still stands.
- **Contracts.** An interface is introduced when:
  - it encapsulates an external dependency (API, payment gateway, storage) that must be replaced in tests;
  - two or more implementations already exist;
  - a module exposes an extension point to other modules.

  In all other cases, inject the concrete class: the container resolves it anyway.
- **Container bindings** in a Service Provider dedicated to the domain or module, not scattered through the code.
- **Lightweight CQRS.** Actions that modify state stay separate from complex reads (query classes, scopes, custom Builders) when the separation adds clarity; a simple CRUD does not need it.
- **Domain constants as backed Enums**, with domain methods (`label()`, `canTransitionTo()`...) on the Enum. When transitions gain guards, side-effects or per-state behavior, move to a state machine ([components](references/components.md#enum-and-state-machine)).
- **Domain errors** as dedicated exceptions with named constructors, free of HTTP details, mapped centrally in `bootstrap/app.php` (`withExceptions`). Never an empty `catch`; the message shown to the user contains no sensitive data or internal details.
- **Type safety.** `declare(strict_types=1)`, types on parameters, properties and return values, generics via PHPDoc where static analysis needs them.

## Structure and modules

Default: standard Laravel folders, grouped by functional area.

```
app/
  Actions/<Area>/CreateOrder.php
  Data/<Area>/CreateOrderData.php
  Services/<Area>/...
  Contracts/...
  Enums/...
  Events/<Area>/...
  Exceptions/<Area>/...
  Policies/...
```

When an area becomes a bounded context with its own rules (many Actions, dedicated events, a team looking after it), it moves into a module:

```
app/Domain/<Context>/{Actions,Data,Events,Models,Policies,...}
```

Rules for modules:

- A module does not touch another module's models or tables. It communicates through its public Actions, its Contracts or its events.
- The core does not depend on modules: modules hook into the core through events, listeners and bindings in their own Service Provider.
- Do not create a module for an area with two classes: the structure follows the real domain, not the anticipated one.

A module lives in `app/` and is always present. An **optional** feature (sold separately, enabled per instance, removable) becomes an internal package under `packages/` that the core never names: use `laravel-internal-packages`.

**Web vs public API.** Controllers for your own frontend (Inertia, private, unversioned) and controllers for a public API (versioned, stable contract, Resources) live in separate namespaces and route files. Both call the same Actions: the domain does not know who calls it.

## Frontend (React + TypeScript)

- **Small, pure components**: they receive props and render UI. Non-trivial logic (derived state, side-effects, fetching, complex forms) goes into custom hooks or utility functions that can be tested without rendering.
- **Unidirectional state**: data comes from the server (Inertia props or API) and flows down; local state only for what is truly local. Do not duplicate in client state data that the server already provides.
- **SSOT between backend and frontend**: routes from Wayfinder; authorization as `can` flags computed by the Policy and passed as props; Enum values and shared types with a single definition (table above).
- **Strict TypeScript**: `strict` enabled, no `any` (if unavoidable, a comment explains why); prefer `unknown` with narrowing.

## Tests

- If a unit is hard to test, the problem is the design: refactor it instead of working around it with complicated mocks.
- Actions have feature tests on behavior. External dependencies are replaced at their Contract or with Laravel's fakes (`Http::fake()`, `Queue::fake()`, `Event::fake()`...); do not mock classes you do not own.
- **The architecture is enforced by tests, not by memory.** Add Pest architecture tests for the rules the project adopts:

```php
arch()->preset()->laravel();

arch('the app uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('controllers delegate, they do not query')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('actions are context-free')
    ->expect('App\Actions')
    ->not->toUse(['Illuminate\Http\Request', 'Illuminate\Support\Facades\Session']);

arch('modules talk through public Actions and events')
    ->expect('App\Domain\Billing')
    ->not->toUse('App\Domain\Catalog\Models');
```

## Reviewing

For each finding state: the violated principle or rule, the precise location (`file:line`), the concrete impact, and a refactoring that does not add complexity. Point out the trade-offs (API breakage, more files to maintain). Do not propose an abstraction the code does not need yet.

### Red flags

| Symptom | Where it goes |
|---|---|
| `Model::where(...)` chains or `DB::` in a controller | Scope, custom Builder or query class |
| `if ($user->role === 'admin')` in a controller, Resource or React component | Policy; frontend reads a `can` flag |
| The same state check in Policy, Action and component | One Enum or model predicate, called by all three |
| Model method that saves, sends mail or calls an API | Action; the side-effect becomes an event |
| Observer sending notifications or charging payments | Explicit event dispatched by the Action |
| Job or Listener with business logic in `handle()` | Action, invoked by the Job |
| `'pending'`, `'emails'`, `'orders.view'` as literals | Backed Enum |
| Event dispatched inside a transaction that can roll back | `ShouldDispatchAfterCommit` |
| `catch (\Exception $e) {}` or `$e->getMessage()` shown to the user | Domain exception, mapped in `withExceptions` |
| Interface with one implementation and no external dependency | Inject the concrete class |
| `app/Domain/X` with two classes | Back to functional-area folders |
| `href="/orders/" + id` in a component | Wayfinder route |
