# Framework components: how to use each one well

Architectural choices for each component. Syntax, signatures and version-specific details come from Boost (`search-docs`): check them there before writing code.

## Contents

- [Routes and Controllers](#routes-and-controllers)
- [Form Request](#form-request)
- [Policy and Gate](#policy-and-gate)
- [Middleware](#middleware)
- [Action](#action)
- [DTO and Value Object](#dto-and-value-object)
- [Enum and State Machine](#enum-and-state-machine)
- [Model, scopes and custom Builder](#model-scopes-and-custom-builder)
- [Observer](#observer)
- [Events and Listeners](#events-and-listeners)
- [Jobs, Commands and Scheduler](#jobs-commands-and-scheduler)
- [Resources and Inertia props](#resources-and-inertia-props)
- [External integrations](#external-integrations)
- [Exceptions](#exceptions)
- [Service Providers](#service-providers)

## Routes and Controllers

- **Resource controllers use only the RESTful methods** (`index`, `show`, `create`, `store`, `edit`, `update`, `destroy`; APIs without `create`/`edit`). An operation that is not CRUD (`cancel`, `publish`, `approve`) gets its own **single-action invokable controller** (`CancelOrderController`), not an extra method on the resource controller.
- **Route model binding** instead of manual `findOrFail`. For nested resources use scoped bindings (`scopeBindings()`), so `/users/{user}/posts/{post}` cannot resolve another user's post.
- **Every route is named**: names are what Wayfinder, redirects and tests refer to.
- A controller method is roughly: Form Request → DTO → Action → Resource/redirect. Anything after the Action call other than mapping the response belongs in the Action.

## Form Request

- **Use for**: input validation, normalization before validation (`prepareForValidation`: trim, lowercase email, cast checkboxes), authorization via `authorize()` delegating to the Policy.
- **Pick one authorization convention per project**: either `authorize()` in the Form Request or the `can` middleware on the route (`->can('update', 'order')`). Both call the Policy; mixing them means a reader never knows where the check is.
- **Mapping to the DTO lives here** (`toData(): CreateOrderData`, built from `validated()`): the HTTP layer knows the domain, the domain does not know HTTP.
- **Keep out**: DB writes, domain rules that depend on current state (those are Action guards), queries beyond the `exists`/`unique` rules.

## Policy and Gate

- **Use for**: *who* may do something. Typically combines permission (role or permission package) + ownership (`$order->user_id === $user->id`) + a state predicate.
- The state predicate is **called, not reimplemented**: `$order->status->canBeCancelled()` is the same method the Action guard uses.
- Permission names, if you use a permission package, are a backed Enum.
- `before()` only for real global exceptions (super-admin), not for business shortcuts.
- The frontend never recomputes authorization: pass the result as `can` flags in Resource/props.
- Policy for model-bound abilities; Gate only for abilities with no model (`viewAdminPanel`).

## Middleware

- **Use for**: cross-cutting HTTP concerns applied to groups of routes: authentication, locale, tenant resolution, security headers, throttling, sharing Inertia data.
- **Keep out**: rules specific to one operation (belong in the Policy or Action), queries whose result the controller needs again (resolve once, e.g. with binding).

## Action

Detailed boundary with Services, Events and Jobs: `laravel-action-vs-service`. Architectural points:

- Order inside the Action: **guards** (throw domain exception) → **transaction** (writes) → **events** after commit → typed return.
- **Essential external calls** (charge, refund) are never made inside an open transaction that could still roll back, and never left to a fire-and-forget listener. Pick one order and make the failure explicit: call first and write only on success; or write a pending state, commit, call, then mark done/failed with a retryable Job. Never a state where the DB says "refunded" and the provider did not refund.
- Composition: an Action may inject and call other Actions. A chain of 3+ levels means the boundaries need revisiting. A single transaction at the outermost Action; inner Actions must not assume they own it.
- An Action called by Jobs or webhooks must be **idempotent** (check the current state before acting, or use unique keys), because queues retry.
- Group by functional area (`Actions/Orders/`), never by type (`CreateActions/`).

## DTO and Value Object

| | DTO | Value Object |
|---|---|---|
| Purpose | Carry data between layers | Represent a domain concept |
| Behavior | None | Invariants and operations (`Money::plus()`, `DateRange::overlaps()`) |
| Validation | Already done by the Form Request | In the constructor: an invalid VO cannot exist |
| Identity | Irrelevant | Equality by value (`equals()`) |

- Both `final readonly`. The VO has a private constructor + named constructors (`Money::ofMinor(1500, 'EUR')`), and operations return new instances.
- Create a VO when the same primitive travels with the same rules in 2+ places (amount + currency, a normalized email, a period). A single `string $email` with one `email` rule is not a VO.
- A VO is persisted with a custom cast (`Castable` / `CastsAttributes`), so the model exposes the VO, not the columns.
- Money is never a `float`: integer minor units or a VO over a money library.

## Enum and State Machine

- **Backed Enum** for any finite set: statuses, types, queue names, permissions, mail channels. Cast on the model, validated with `Rule::enum()`, exported to TypeScript.
- Domain methods on the Enum: `label()`, predicates (`isFinal()`), `canTransitionTo(self $to)`. Keep presentation details (CSS colors, icons) out of the domain Enum when the frontend can map them.
- **Move to a state machine** (e.g. `spatie/laravel-model-states`) when at least one holds: transitions have guards depending on other data, a transition produces side-effects, behavior differs per state, transition history is required. Until then an Enum with `canTransitionTo()` is enough.
- A state transition is an Action (`CancelOrder`), even when a state machine exists: the Action guards, transitions and dispatches the event.

## Model, scopes and custom Builder

- **Use for**: relationships, casts (Enum, VO, DTO for JSON columns), accessors/mutators, scopes, predicates reading only its own attributes (`isOverdue()`).
- **Keep out**: methods that save other models, send mail, call APIs, open transactions. `$order->cancel()` doing refund + notification is an Action (`CancelOrder`).
- Keep mass-assignment protection (`$fillable`): it is the last guard when someone passes an unfiltered array.
- **Scopes** for a few reusable filters. Move to a **custom Builder** (`OrderBuilder extends Builder`, registered on the model) when the model has many query methods or you need typed closures in `whereHas()`. Do not create empty Builders "for later".
- **Query Object** for index endpoints with user-driven filters/sorting/includes (e.g. `spatie/laravel-query-builder`): the controller receives it, the allowed filters live in one place.
- Polymorphic relations only with an enforced morph map (see Service Providers).

## Observer

- **Use for**: invariants of the record itself that must hold however it is saved: generating uuid/slug, keeping a denormalized counter in sync, clearing a cache key.
- **Keep out**: business side-effects (email, payments, webhooks, analytics). An observer fires on every save, including seeders, imports and tinker, and is invisible from the call site: the Action dispatches an explicit event instead.
- If the logic needs to know *why* the model changed, it is not an observer.

## Events and Listeners

- An **event is a fact in the past tense** (`OrderPlaced`, `InvoicePaid`) dispatched by the Action. It carries the model or the IDs needed, not a derived payload built for a specific listener.
- A **listener is thin**: it reacts and delegates (to an Action, a Notification, a Job). Listeners with external side-effects implement `ShouldQueue`; the operation does not fail if the side-effect fails.
- Events whose listeners touch the outside world implement `ShouldDispatchAfterCommit`.
- Events are also the core's extension point towards modules and packages: the core dispatches, the module listens.
- Do not model the main flow as a chain of events: if B must happen for A to be correct, B is a step of the Action.

## Jobs, Commands and Scheduler

- **Thin**: `handle()` resolves what it needs and invokes an Action. The same Action serves HTTP, Job, Command and scheduler.
- Pass the model with `#[WithoutRelations]` or just the ID: never a serialized graph of relations.
- **Idempotency by design**: `ShouldBeUnique` / `WithoutOverlapping` for jobs that must not run in parallel on the same resource; the Action checks state before acting.
- Retry policy (`tries`, `backoff`, `timeout`, `retryUntil`) declared on the job, not left to worker defaults. `failed()` for compensation or alerting.
- Queue names are a backed Enum; separate queues for slow or bulk work.
- Artisan commands: parse the arguments, build the DTO, invoke the Action, print the result. Scheduled tasks call Actions or dispatch Jobs, never inline closures with logic.

## Resources and Inertia props

- **One output shape per resource**: API Resource (or a Data object) is the single place that decides which fields leave the backend. Never return raw models from a public API.
- Inertia props are built from the same Resources/Data, including the `can` flags computed by the Policy.
- Relationships are included only when loaded (`whenLoaded`), so the Resource never triggers queries.

## External integrations

- **Always behind a Contract** bound in the domain Service Provider; Actions inject the Contract. The implementation returns DTOs, not the vendor's response objects.
- One provider: implementation over Laravel's `Http` client, tested with `Http::fake()`.
- Two or more interchangeable providers, or one chosen per environment: Laravel's `Manager` with one driver per provider plus a fake/null driver for tests and local.
- Vendor exceptions are translated into the area's domain exception at the boundary.
- Webhooks: controller verifies the signature, stores or dispatches a Job, returns 2xx fast; the Job invokes an idempotent Action.

## Exceptions

- **One exception class per area** (`OrderException`, `BillingException`) with **named constructors** (`OrderException::cannotBeCancelled($order)`): the call site reads as the business reason and the message lives in one place.
- Domain exceptions carry **no HTTP status or response**: the mapping (status, JSON/Inertia response, user-facing message) is centralized in `bootstrap/app.php` → `withExceptions()`. The domain stays usable from console and queue.
- Add context for logs through `context()`; noisy expected exceptions go in `dontReport` or are throttled.
- Never an empty `catch`; catch only to translate, compensate or add context, then rethrow.

## Service Providers

- `register()` only binds in the container; `boot()` configures (observers, morph map, rate limiters, Gates). One provider per domain or module, owning that area's bindings and listeners.
- Keep `boot()` readable with named private methods (`configureModels()`, `configureDates()`).
- Strict defaults worth enabling in `AppServiceProvider::boot()` of a new project:

```php
Model::shouldBeStrict(! $this->app->isProduction()); // lazy loading, discarded and missing attributes fail loudly
Relation::enforceMorphMap([
    'order' => Order::class,
    'user' => User::class,
]);
Date::use(CarbonImmutable::class);
DB::prohibitDestructiveCommands($this->app->isProduction());
```

- The morph map aliases are SSOT for polymorphic types: stored in the DB, so changing one needs a migration.
