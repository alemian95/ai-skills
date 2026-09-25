# PHP 8.x language features

Sources: [PHP The Right Way — Language Highlights](https://phptherightway.com/#language_highlights), [Use the Current Stable Version](https://phptherightway.com/#use_the_current_stable_version), PHP manual (per-version migration appendices).

## Contents
1. Availability by version
2. Usage guidelines
3. Property hooks and asymmetric visibility (8.4)
4. Deprecations that affect existing code

## 1. Availability by version

Use a feature only if the project's `php` constraint includes it.

| Version | Main features |
|---|---|
| 8.0 | named arguments, union types, `mixed`, `static` as return type, nullsafe `?->`, `match`, constructor promotion, attributes, `throw` as an expression, `catch` without a variable, `str_contains`/`str_starts_with`/`str_ends_with`, `Stringable`, `WeakMap`; internal functions throw `TypeError`/`ValueError` |
| 8.1 | `enum` (pure and backed), `readonly` properties, first-class callable `strlen(...)`, `never`, `new` in initializers, intersection types, `final` class constants, `array_is_list`, Fiber |
| 8.2 | `readonly` classes, DNF types `(A&B)\|null`, `true`/`false`/`null` as standalone types, constants in traits, `#[\SensitiveParameter]`, Random extension (`Random\Randomizer`); dynamic properties deprecated |
| 8.3 | typed class constants, `#[\Override]`, `json_validate()`, dynamic class constant fetch `Foo::{$name}`, deep cloning of readonly properties in `__clone`, `mb_str_pad`, `Randomizer::getBytesFromString` |
| 8.4 | property hooks, asymmetric visibility `public private(set)`, `new Foo()->method()` without parentheses, `array_find`/`array_find_key`/`array_any`/`array_all`, `#[\Deprecated]`, lazy objects, `mb_trim`/`mb_ltrim`/`mb_rtrim`/`mb_ucfirst`/`mb_lcfirst`, HTML5 DOM API (`Dom\HTMLDocument`), `BcMath\Number`, per-driver PDO subclasses (`Pdo\Mysql`, `Pdo\Pgsql`, `Pdo\Sqlite`) with `PDO::connect()`, `request_parse_body()`, default bcrypt cost 12 |
| 8.5 | pipe operator `\|>`, `clone($obj, ['prop' => $value])`, `#[\NoDiscard]`, URI extension (`Uri\Rfc3986\Uri`, `Uri\WhatWg\Url`), `array_first`/`array_last`, closures in constant expressions, backtraces in fatal errors |

## 2. Usage guidelines

**Named arguments**: use them when a boolean or optional argument is not clear from its position (`new Settings(debug: false, ...)`, `json_decode($s, true, flags: JSON_THROW_ON_ERROR)`). They make parameter names part of the public API: renaming them is a breaking change in libraries.

**Enums**: backed (`: string`/`: int`) when the value is persisted or exchanged; pure otherwise. Put case-specific behavior in the enum (`$status->label()` with `match ($this)`). Convert input with `tryFrom()` and handle `null` at the boundary; `from()` only when an invalid value is a bug.

**readonly**: `readonly` class for value objects and stateless services. Readonly properties are initialized only once, from inside the class. To get a modified copy use `with...()` methods that return a new instance (8.5: `clone($this, ['x' => $x])`).

**match**: exhaustive over enums. Do not add `default` when you list all cases: without `default`, a new case produces `UnhandledMatchError` instead of a silently wrong value.

**First-class callable**: `array_map($this->normalize(...), $items)` instead of `[$this, 'normalize']` or `'strtoupper'`: it is verifiable by static analysis and does not break on renames.

**Nullsafe `?->`**: only when `null` is a legitimate outcome of the chain, not to silence a data-model error.

**Attributes**: `#[\Override]` on overriding methods (error if the parent method disappears); `#[\SensitiveParameter]` on passwords and tokens to exclude them from backtraces; `#[\Deprecated]` (8.4) on APIs being phased out.

**8.4 array functions**: `array_find($items, fn($x) => ...)` returns the first value or `null`; `array_any`/`array_all` return `bool`. They replace `foreach` with `break` and `array_filter(...)[0] ?? null`.

**Pipe operator (8.5)**: `$slug = $title |> trim(...) |> strtolower(...) |> fn($s) => preg_replace('/\W+/', '-', $s);`. Useful for linear transformations with single-argument callables; do not use it for branching logic.

## 3. Property hooks and asymmetric visibility (8.4)

```php
final class Product
{
    public private(set) string $sku;          // readable from outside, writable only by the class

    public string $name {
        set => mb_trim($value);               // normalization on write
    }

    public string $label {
        get => "{$this->sku} — {$this->name}"; // computed virtual property
    }

    public function __construct(string $sku, string $name)
    {
        $this->sku = $sku;
        $this->name = $name;
    }
}
```

When to use them:
- **Asymmetric visibility** instead of the private property + getter pair, when the object is mutable but mutation must go through the class's methods.
- **`set` hook** to normalize or validate on write; **`get` hook** for cheap derived properties.
- Interfaces can declare hooked properties (`public string $name { get; }`).

Constraints and caveats:
- A hooked property cannot be `readonly`, and a `readonly` class cannot have hooked properties. For immutable value objects, `readonly` with validation in the constructor remains preferable.
- No I/O, queries or expensive logic in hooks: whoever reads `$obj->x` expects a property access.
- `$value` is the implicit name of the incoming value in `set`; inside a hook, `$this->prop` refers to the backing property.
- Do not use them on Eloquent models or on classes hydrated by ORMs or serializers that write properties via reflection, unless you verify that the library supports them.

## 4. Deprecations that affect existing code

- **8.1**: passing `null` to non-nullable parameters of internal functions (e.g. `strlen(null)`); `FILTER_SANITIZE_STRING`; `Serializable` interface without `__serialize`/`__unserialize`.
- **8.2**: dynamic properties (use declared properties; `#[\AllowDynamicProperties]` only as a temporary bridge); `"${var}"` interpolation.
- **8.3**: `get_class()`/`get_parent_class()` without arguments.
- **8.4**: implicitly nullable parameters (`Foo $x = null` → `?Foo $x = null`); the `E_STRICT` constant; `trigger_error()` with `E_USER_ERROR`; `exit()` is now a real function.
- **8.5**: backtick operator (use `shell_exec` or better `proc_open` with an array); non-canonical casts `(integer)`, `(boolean)`, `(double)`, `(binary)`; `__sleep`/`__wakeup` in favor of `__serialize`/`__unserialize`.

Rector (`references/tooling.md`) automatically fixes most of these cases.
