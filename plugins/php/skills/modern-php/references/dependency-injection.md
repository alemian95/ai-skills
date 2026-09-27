# Dependency injection

Sources: [PHP The Right Way — Dependency Injection](https://phptherightway.com/#dependency_injection), [PSR-11](https://www.php-fig.org/psr/psr-11/).

## Contents
1. Rules
2. Composition root and service locator
3. What to inject and what not to
4. Autowiring
5. Interfaces: when they are needed
6. State and persistent runtimes
7. In frameworks

## 1. Rules

- Required dependencies come in through the **constructor**, with promotion and types: the constructor signature is the documentation of what the class requires.
- A class does not create its own dependencies with `new` when they do I/O, have configuration or must be replaced in tests. `new` is perfectly fine for value objects, DTOs, exceptions and purely computational objects.
- No access to global state from inside classes: no `getenv()`, `$_ENV`, `$_SERVER`, static singletons, `global`.
- Setter injection only for truly optional dependencies (and preferably never: a nullable constructor parameter or a null object is clearer).

## 2. Composition root and service locator

A container (PSR-11) is a tool for **building the object graph**, not a dependency to pass around.

- The container is used only in the composition root: bootstrap, definition files, factories, service providers, the dispatcher that resolves handlers.
- Injecting `ContainerInterface` into an application class and calling `$container->get()` inside it is the **service locator** pattern: it hides the real dependencies, makes them visible only at runtime and complicates testing. It creates a stronger dependency than the one you wanted to remove.
- Legitimate exception: infrastructure components that must resolve classes known only at runtime (router → handler, command bus → handler). Even there, limit container access to that single point.

## 3. What to inject and what not to

| Inject | Do not inject |
|---|---|
| services (repositories, HTTP clients, mailers, loggers, clocks) | the HTTP request into domain services: pass the extracted values |
| typed configuration (`Settings`, or the individual values) | the whole configuration array "for convenience" |
| factories, when the object depends on values known only at runtime | the container |
| PSR interfaces at the boundaries (`LoggerInterface`, `ClockInterface`, `ResponseFactoryInterface`) | shared mutable instances meant for "a single request" (e.g. a pre-built PSR-7 `Response`) |

On the injected `Response`: PHP-DI and most containers share the resolved instance; in PSR-7 the message is immutable but the **body stream is mutable**, so writes from different parts add up. Inject `ResponseFactoryInterface` (PSR-17) and create a new response when needed.

**Clock**: inject `Psr\Clock\ClockInterface` (PSR-20) instead of calling `new DateTimeImmutable()` in services that depend on the current time; in tests you use a fixed clock.

## 4. Autowiring

Autowiring resolves dependencies by reading the types of the constructor parameters. It is the default choice in modern projects:
- it reduces configuration to what is truly meaningful (binding interfaces to implementations, scalar values, factories);
- it lowers the cost of adding a dependency, and so encourages small classes;
- it works only with typed parameters, discouraging the use of primitives in place of types.

Caveats:
- PHP-DI autowires only concrete classes: every interface must be bound explicitly. Symfony reports ambiguities; Laravel resolves concrete classes and requires bindings for interfaces.
- Scalar parameters (`string $apiKey`) cannot be autowired: define them in the container or, better, group them into a typed configuration object.
- In production, if the container supports it, enable compilation or caching of definitions (PHP-DI `enableCompilation()`, Symfony compiled container).

## 5. Interfaces: when they are needed

Create an interface when:
- more than one implementation concretely exists or will exist (payment gateway, storage);
- the dependency does I/O and you want to replace it with a fake in tests;
- you are defining a boundary between modules or an extension point of a package.

Do not create one for every class "on principle": an interface with a single implementation and no boundary is just indirection. For tests, a concrete `final` class without I/O is used directly; a service with I/O is replaced through its interface with a hand-written fake (more readable and robust than a configured mock).

SOLID principles in brief, in the form useful for making decisions:
- **Single responsibility**: a class has only one reason to change.
- **Open/closed**: extend by adding implementations of an interface, not by modifying scattered `if`s.
- **Liskov**: an implementation must honor the interface's contract, exceptions included.
- **Segregation**: small interfaces, oriented to whoever uses them.
- **Dependency inversion**: the domain defines the interfaces it needs; the infrastructure implements them.

## 6. State and persistent runtimes

With runtimes that keep the process alive between requests (FrankenPHP in worker mode, RoadRunner, Swoole, Laravel Octane), services shared by the container outlive the request:
- services must be free of request-bound state (current user, locale, the request itself): pass these values as arguments or use request-scoped services (Laravel `scoped()`);
- stateless `readonly` classes are safe by construction;
- watch out for in-memory caches and static properties that grow without limit.

## 7. In frameworks

The rules above apply everywhere; only where the composition root is written changes.

- **Without a framework**: a definitions file for the chosen PSR-11 container (or manual construction with `new` for small applications: a container is not mandatory).
- **Laminas / Mezzio**: factories registered in the service manager (`ConfigProvider` → `dependencies.factories`); `ReflectionBasedAbstractFactory` or `ConfigAbstractFactory` to avoid trivial factories. The factory receives the container: it is the composition root, the class being built is not.
- **Laravel**: bindings in service providers (`bind`, `singleton`, `scoped`), constructor injection in controllers, actions, jobs, listeners and commands. The `app()` and `resolve()` helpers and facades are service locators: following the project's convention they are acceptable in glue code (controllers, providers), but in domain classes prefer injection to make dependencies explicit and simplify testing.
- **Symfony**: private services, autowiring and autoconfiguration in `services.yaml`; no `$this->container->get()` in controllers.
