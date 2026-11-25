# Errors and exceptions

Sources: [PHP The Right Way — Errors and Exceptions](https://phptherightway.com/#errors_and_exceptions), [PSR-3](https://www.php-fig.org/psr/psr-3/).

## Contents
1. The PHP 8 model
2. Hierarchy and choosing the exception
3. Domain exceptions
4. Where to catch
5. Error handler at entry points
6. Logging
7. In frameworks

## 1. The PHP 8 model

PHP is no longer the "keep going no matter what" language described in outdated guides:
- internal functions throw `TypeError` for wrong types and `ValueError` for out-of-domain values (e.g. `str_repeat('a', -1)`), instead of returning `false`/`null` with a warning;
- accessing an undefined variable raises a **Warning** (no longer a Notice); accessing a nonexistent array key is also a Warning;
- there are still functions that signal failure with `false` + warning (`file_get_contents`, `fopen`, `mkdir`, many network functions): always check the return value, or convert warnings into exceptions (section 5).

`error_reporting(E_ALL)` always, in every environment. Only **where** errors end up changes: screen in development, log in production.

## 2. Hierarchy and choosing the exception

```
Throwable
├── Error                      bug or engine condition: do not catch in application code
│   ├── TypeError, ArgumentCountError, ValueError
│   ├── ArithmeticError → DivisionByZeroError
│   └── UnhandledMatchError
└── Exception
    ├── ErrorException         converted warnings/notices
    ├── JsonException          JSON_THROW_ON_ERROR
    ├── LogicException         caller error: fix the code
    │   ├── InvalidArgumentException, DomainException, LengthException, OutOfRangeException
    │   └── BadFunctionCallException → BadMethodCallException
    └── RuntimeException       external/unpredictable condition: handle at runtime
        └── UnexpectedValueException, OutOfBoundsException, OverflowException, RangeException, UnderflowException
```

Practical choice:
- invalid argument passed by the calling code → `InvalidArgumentException`;
- violated domain rule (insufficient balance, disallowed state) → domain exception extending `DomainException`;
- data read from an external source with an unexpected shape → `UnexpectedValueException`;
- external resource unavailable (network, file, service) → exception extending `RuntimeException`;
- method called in a state that does not allow it → `LogicException` or `BadMethodCallException`.

## 3. Domain exceptions

Create a dedicated type when the caller must react differently to that case. Named constructors make throw sites readable and centralize the message:

```php
final class InsufficientCredit extends DomainException
{
    public static function forCourse(CourseId $course, int $required, int $available): self
    {
        return new self(sprintf(
            'Insufficient credits for course %s: required %d, available %d',
            $course, $required, $available,
        ));
    }
}
```

- Extend the closest SPL exception, so that code catching the generic category keeps working.
- In a package, a marker interface (`interface BillingException extends Throwable`) lets you catch all of the module's exceptions.
- Messages are for whoever reads the logs: include useful identifiers, never personal data or secrets.
- Do not use exceptions for normal flow (e.g. "user not found" in a search is often `null` or an empty result, not an exception).

## 4. Where to catch

- Catch at the **boundary** where you know what to do: translation into an HTTP response, retry, fallback, message to the user, transaction rollback.
- Catch the most specific type possible. `catch (\Throwable)` only in the last-resort handler (error middleware, framework handler, queue worker), and there it is always logged.
- When you translate an infrastructure exception into a domain one, preserve the cause:

```php
try {
    $response = $this->http->sendRequest($request);
} catch (ClientExceptionInterface $e) {
    throw new PaymentGatewayUnavailable('Gateway unreachable', previous: $e);
}
```

- `finally` to release resources (locks, files, transactions) regardless of the outcome.
- Do not catch to log and rethrow at every level: it produces the same error N times in the log. Log once, at the outer boundary.
- Do not catch `Error` (`TypeError` & co.) in application code: it signals a bug to fix.

## 5. Error handler at entry points

Without a framework, every entry point (web front controller, CLI script, worker) converts warnings, notices and deprecations into exceptions:

```php
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
```

- Register it in the entry point, not in the container bootstrap: tests (PHPUnit) use the bootstrap and flag a handler that is not restored as risky.
- `set_exception_handler()` as the last safety net for uncaught exceptions outside the HTTP pipeline (e.g. an error while building the container); `register_shutdown_function()` + `error_get_last()` to log fatal errors.
- In production `display_errors=Off`: the error page is generated by the application (generic 500 response), the details go to the log.

## 6. Logging

- Use PSR-3 (`Psr\Log\LoggerInterface`) injected in the constructor; the implementation (Monolog, the framework's logger) is chosen in the composition root.
- Pass the exception in the context under the `exception` key (PSR-3 convention): Monolog records class, message, file, line, trace and the `previous` chain.
- Static messages with placeholders and data in the context (`'Order {id} rejected'`, `['id' => $id]`): aggregatable and searchable.
- Levels: `error` for unhandled exceptions, `warning` for handled anomalies, `info` for relevant business events, `debug` only in development.
- Never passwords, tokens, card numbers or unnecessary personal data in the context.

## 7. In frameworks

The framework owns the last-resort handler: do not register your own `set_error_handler`. The translation from domain exception to response is configured at the place the framework provides:
- **Laravel** (11+): `bootstrap/app.php` → `withExceptions()`, with `report()`/`render()` per exception type; domain exceptions can implement `report()`/`render()` or be mapped there.
- **Symfony**: listener or subscriber on the `kernel.exception` event; in production the framework's error pages, never details.
- **Mezzio / Slim**: the error-handling middleware at the start of the pipeline (`psr.md` §5); for APIs, Problem Details responses (RFC 9457).

The rest of this file (choosing the type, domain exceptions, where to catch, `previous`) applies unchanged.
