# Text, dates and internationalization

Sources: [PHP The Right Way — UTF-8](https://phptherightway.com/#php_and_utf8), [Date and Time](https://phptherightway.com/#date_and_time), [Internationalization and Localization](https://phptherightway.com/#i18n_l10n).

## Contents
1. UTF-8 across the whole chain
2. Multibyte functions
3. intl extension
4. Dates and time zones
5. Translations

## 1. UTF-8 across the whole chain

UTF-8 must be consistent at every point: sources, PHP, database, HTTP, HTML.
- **PHP**: `default_charset` is `UTF-8` since 5.6; there is no need to call `mb_internal_encoding()` or `mb_http_output()` in every script.
- **Database**: `utf8mb4` on tables, columns and connection (`charset=utf8mb4` in the DSN, see `database-pdo.md`).
- **HTTP**: `Content-Type: text/html; charset=utf-8` (or `application/json; charset=utf-8`).
- **HTML**: `<meta charset="utf-8">` as the first element of `<head>`.
- **Functions with an encoding parameter** (`htmlspecialchars`, `mb_*`): pass `'UTF-8'` explicitly when the code must be independent of the configuration.

## 2. Multibyte functions

The basic string functions operate on bytes. On user-supplied or otherwise non-ASCII text use the `mb_*` variants:

| Bytes (ASCII/binary only) | Characters |
|---|---|
| `strlen`, `substr`, `strpos`, `str_pad` | `mb_strlen`, `mb_substr`, `mb_strpos`, `mb_str_pad` (8.3) |
| `strtolower`, `strtoupper`, `ucfirst` | `mb_strtolower`, `mb_strtoupper`, `mb_ucfirst` (8.4) |
| `trim`, `ltrim`, `rtrim` | `mb_trim`, `mb_ltrim`, `mb_rtrim` (8.4) |
| `str_split` | `mb_str_split` |
| `wordwrap` | no direct equivalent: use `IntlBreakIterator` |

- Concatenation, `str_contains`, `str_starts_with`, `str_replace` with valid UTF-8 strings work correctly even at the byte level.
- Regex: `/u` modifier to treat pattern and subject as UTF-8; Unicode classes `\p{L}` (letters), `\p{M}` (combining diacritical marks), `\p{N}` (digits).
- Validate the encoding of external input with `mb_check_encoding($s, 'UTF-8')` if it comes from untrusted sources.
- Normalize before comparing or indexing text that can arrive in different forms (precomposed `é` or `e` + accent): `Normalizer::normalize($s, Normalizer::FORM_C)`.
- For binary strings (hashes, encrypted data) use the byte functions.

## 3. intl extension

Locale-sensitive formatting and comparison (based on ICU):
- `NumberFormatter` for numbers, currencies and percentages (`new NumberFormatter('it_IT', NumberFormatter::CURRENCY)`).
- `IntlDateFormatter` for localized dates (month and day names, regional formats).
- `Collator` to sort strings according to the language's rules (`sort` sorts by bytes).
- `MessageFormatter` for messages with plurals and arguments (ICU syntax: `{count, plural, one {# elemento} other {# elementi}}`).
- `Transliterator` for slugs and transliterations.

## 4. Dates and time zones

- `DateTimeImmutable` instead of `DateTime`: methods return a new instance, no action-at-a-distance changes on shared objects. In Laravel `CarbonImmutable` (configurable as the default with `Date::use(CarbonImmutable::class)`).
- Time zone always explicit: `date.timezone` set in `php.ini` and `DateTimeZone` passed when it matters. Store in UTC, convert to the user's time zone for display.
- Parse known formats with `DateTimeImmutable::createFromFormat('!Y-m-d', $s)` (the `!` resets unspecified fields) and check the result (`false` and `DateTimeImmutable::getLastErrors()`).
- Arithmetic with `DateInterval` and `modify()`, never by adding 86400 seconds: daylight saving time and time zone changes make days 23 or 25 hours long.
- Differences with `diff()`; recurring series with `DatePeriod`.
- Exchange dates between systems in ISO 8601 (`DateTimeInterface::ATOM`) with an explicit offset.
- Current time injected via PSR-20 `ClockInterface` into services, for deterministic tests (`dependency-injection.md` §3).
- For date-only types (birthdays, due dates) consider a dedicated value object instead of a `DateTimeImmutable` with implicit time and time zone.

## 5. Translations

- With a framework use its system (Laravel `__()`, `trans_choice()` and files in `lang/`; Symfony Translation; Laminas `laminas-i18n`).
- Without a framework: `symfony/translation` (multiple formats, ICU MessageFormat) or gettext (`ext-gettext` + `.po`/`.mo` files, tools such as Poedit).
- Structured keys (`checkout.errors.card_declined`) make organization easier; real sentences as keys make translators' work easier. Choose a convention and apply it consistently.
- Never compose translatable sentences by concatenating pieces: word order changes between languages. Use named placeholders.
- Plurals vary by language (one form in Japanese, two in Italian, three or more in Slavic languages): always use the library's plural mechanism, never `$n === 1 ? ... : ...`.
