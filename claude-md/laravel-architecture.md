# Project architecture

These rules complement the Laravel Boost guidelines; they do not replace them.

- **Boost** decides framework conventions, package versions, Artisan commands, style (Pint), tests (Pest/PHPUnit), use of Inertia and Wayfinder. Before writing code, use its tools (`search-docs`, `database-schema`, `list-routes`, `tinker`...).
- **This file** decides *where* logic lives and *how* responsibilities are separated.
- If the two sources seem to conflict, Boost wins on "how it is written" and this file wins on "where it goes". If the conflict persists, stop and ask.

Do not repeat here rules already present in Boost: a rule written in two places will sooner or later diverge.

## Principles

They apply both to new code and to review and refactoring.

- **SOLID**, with these clarifications:
  - *Single Responsibility*: a class that does two distinct things must be split.
  - *Dependency Inversion*: dependencies are injected (constructor injection, Service Container), never `new` of a service or facade inside domain logic.
  - *Interface Segregation*: small interfaces, shaped around the caller.
- **YAGNI**: no parameters, abstractions or layers for hypothetical requirements. An abstraction is born at the second real occurrence, or when an already-decided requirement calls for it.
- **DRY**: a piece of logic has a single representation. Similar code with different purposes must not be forcibly unified.
- **SSOT**: DRY is about logic, SSOT is about state, data and domain rules.
  - Every domain rule (authorization, calculation, state transition) is defined in a single place and referenced wherever it is needed.
  - Every shared piece of data has a single origin: whoever needs it takes it from there, does not rebuild it.
  - Every constant or configuration value is defined once (Enum, `config/`) and imported, never rewritten inline.
- **Composition over inheritance.**
- **Design patterns** (Repository, Strategy, Factory, Observer...) only when they solve a problem present in the code, not an imagined one.

When an abstraction and YAGNI pull in opposite directions, YAGNI wins, except for the cases listed below under *Contracts*.

## Backend: where logic goes

| Layer | Responsibility | Must not |
|---|---|---|
| Controller | Receives the request, delegates, returns the response | Contain business logic, complex Eloquent queries, calls to external SDKs |
| Form Request | Input validation and authorization (delegating to the Policy) | Contain domain logic |
| Action | A complete, context-free business operation (HTTP, console, Job call it the same way) | Know about `Request`, session or response |
| Service | Pure logic reusable by 2+ callers, or a wrapper around an external dependency | Orchestrate a use case (that is the Action's job) |
| Policy / Gate | Single source of authorization rules | Be replicated in controllers, frontend or queries |
| Event / Listener | Decoupled side-effects (email, notifications, analytics, integrations) | Contain the main logic of the operation |
| Job | Asynchronous or slow work; normally invokes an Action | Duplicate the Action's logic |
| Model | Relationships, casts, scopes, accessors | Become a container for business logic |

For uncertain cases between Action, Service, Event and Job, use the `laravel-action-vs-service` skill, if installed.

### Rules

- **Typed input and output.** Actions receive a DTO (`readonly` class) or typed parameters, never the `$request->all()` array, and return a precise type.
- **Explicit transactions.** An operation that writes to multiple tables runs in `DB::transaction()` inside the Action. Events that produce external side-effects fire after the commit (`ShouldDispatchAfterCommit` or `afterCommit()`).
- **Contracts.** An interface is introduced when:
  - it encapsulates an external dependency (API, payment gateway, storage) that must be replaced in tests;
  - two or more implementations already exist;
  - a module exposes an extension point to other modules.

  In all other cases, inject the concrete class: the container resolves it anyway.
- **Container bindings** in a Service Provider dedicated to the domain or module, not scattered through the code.
- **Lightweight CQRS.** Operations that modify state (Actions) stay separate from complex reads (query classes or dedicated scopes) when the separation adds clarity; for a simple CRUD it is not needed.
- **Domain constants as backed Enums**, with the domain methods (`label()`, `canTransitionTo()`...) on the Enum itself.
- **Domain errors** as dedicated exceptions, handled centrally in `bootstrap/app.php` (`withExceptions`). Never empty `catch` blocks; the message shown to the user contains no sensitive data or internal details.
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

## Frontend (React + TypeScript)

- **Small, pure components**: they receive props and render UI. Non-trivial logic (derived state, side-effects, fetching, complex forms) goes into custom hooks or utility functions that can be tested without rendering.
- **Unidirectional state**: data comes from the server (Inertia props or API) and flows down; local state only for what is truly local. Do not duplicate in client state data that the server already provides.
- **SSOT between backend and frontend**:
  - routes come from Wayfinder, no hand-written URLs;
  - authorization rules come from the backend (e.g. `can` flags computed by the Policy and passed as props), the frontend does not recompute them;
  - Enum values and shared data types have a single definition, generated by the backend or written once in `resources/js/types`.
- **Strict TypeScript**: `strict` enabled, no `any` (if it is unavoidable, a comment explains why); prefer `unknown` with narrowing.

## Tests

- If a unit is hard to test, the problem is the design: refactor it instead of working around it with complicated mocks.
- Actions have feature tests on behavior; external dependencies are replaced via their Contract or Laravel's fakes (`Http::fake()`, `Queue::fake()`, `Event::fake()`...).

## Way of working

- For non-trivial solutions, explain in 2-3 lines the chosen architecture and why, then write the code. For simple tasks, write it directly.
- Production-ready code: robust, typed, with docblocks only where they add information the types do not provide.
- In reviews, state the violated principle, the precise location, the impact and a concrete refactoring that does not add complexity. Point out the trade-offs (API breakage, more files to maintain).
- Reply in Italian; technical terms and identifiers stay in English.
