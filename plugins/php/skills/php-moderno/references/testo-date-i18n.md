# Testo, date e internazionalizzazione

Fonti: [PHP The Right Way — UTF-8](https://phptherightway.com/#php_and_utf8), [Date and Time](https://phptherightway.com/#date_and_time), [Internationalization and Localization](https://phptherightway.com/#i18n_l10n).

## Indice
1. UTF-8 lungo tutta la catena
2. Funzioni multibyte
3. Estensione intl
4. Date e fusi orari
5. Traduzioni

## 1. UTF-8 lungo tutta la catena

UTF-8 deve essere coerente in ogni punto: sorgenti, PHP, database, HTTP, HTML.
- **PHP**: `default_charset` vale `UTF-8` dalla 5.6; non serve chiamare `mb_internal_encoding()` o `mb_http_output()` in ogni script.
- **Database**: `utf8mb4` su tabelle, colonne e connessione (`charset=utf8mb4` nel DSN, vedi `database-pdo.md`).
- **HTTP**: `Content-Type: text/html; charset=utf-8` (o `application/json; charset=utf-8`).
- **HTML**: `<meta charset="utf-8">` come primo elemento di `<head>`.
- **Funzioni con parametro di encoding** (`htmlspecialchars`, `mb_*`): passa `'UTF-8'` esplicitamente quando il codice deve essere indipendente dalla configurazione.

## 2. Funzioni multibyte

Le funzioni stringa di base operano su byte. Su testo fornito dall'utente o comunque non ASCII usa le varianti `mb_*`:

| Byte (solo ASCII/binario) | Caratteri |
|---|---|
| `strlen`, `substr`, `strpos`, `str_pad` | `mb_strlen`, `mb_substr`, `mb_strpos`, `mb_str_pad` (8.3) |
| `strtolower`, `strtoupper`, `ucfirst` | `mb_strtolower`, `mb_strtoupper`, `mb_ucfirst` (8.4) |
| `trim`, `ltrim`, `rtrim` | `mb_trim`, `mb_ltrim`, `mb_rtrim` (8.4) |
| `str_split` | `mb_str_split` |
| `wordwrap` | nessun equivalente diretto: usa `IntlBreakIterator` |

- Concatenazione, `str_contains`, `str_starts_with`, `str_replace` con stringhe UTF-8 valide funzionano correttamente anche a livello di byte.
- Regex: modificatore `/u` per trattare pattern e soggetto come UTF-8; classi Unicode `\p{L}` (lettere), `\p{M}` (segni diacritici combinati), `\p{N}` (cifre).
- Valida l'encoding dell'input esterno con `mb_check_encoding($s, 'UTF-8')` se proviene da fonti non affidabili.
- Normalizza prima di confrontare o indicizzare testo che può arrivare in forme diverse (`é` precomposto o `e` + accento): `Normalizer::normalize($s, Normalizer::FORM_C)`.
- Per stringhe binarie (hash, dati cifrati) usa le funzioni a byte.

## 3. Estensione intl

Formattazione e confronto sensibili alla lingua (basati su ICU):
- `NumberFormatter` per numeri, valute e percentuali (`new NumberFormatter('it_IT', NumberFormatter::CURRENCY)`).
- `IntlDateFormatter` per date localizzate (nomi di mesi e giorni, formati regionali).
- `Collator` per ordinare stringhe secondo le regole della lingua (`sort` ordina per byte).
- `MessageFormatter` per messaggi con plurali e argomenti (sintassi ICU: `{count, plural, one {# elemento} other {# elementi}}`).
- `Transliterator` per slug e traslitterazioni.

## 4. Date e fusi orari

- `DateTimeImmutable` al posto di `DateTime`: i metodi restituiscono una nuova istanza, niente modifiche a distanza su oggetti condivisi. In Laravel `CarbonImmutable` (configurabile come predefinito con `Date::use(CarbonImmutable::class)`).
- Fuso orario sempre esplicito: `date.timezone` impostato in `php.ini` e `DateTimeZone` passato quando conta. Memorizza in UTC, converti nel fuso dell'utente in visualizzazione.
- Parsing di formati noti con `DateTimeImmutable::createFromFormat('!Y-m-d', $s)` (il `!` azzera i campi non specificati) e verifica il risultato (`false` e `DateTimeImmutable::getLastErrors()`).
- Aritmetica con `DateInterval` e `modify()`, mai sommando 86400 secondi: ora legale e cambi di fuso rendono i giorni lunghi 23 o 25 ore.
- Differenze con `diff()`; serie ricorrenti con `DatePeriod`.
- Scambio di date tra sistemi in ISO 8601 (`DateTimeInterface::ATOM`) con offset esplicito.
- Ora corrente iniettata tramite PSR-20 `ClockInterface` nei servizi, per test deterministici (`dependency-injection.md` §3).
- Per i tipi di sola data (compleanni, scadenze) considera un value object dedicato invece di un `DateTimeImmutable` con ora e fuso impliciti.

## 5. Traduzioni

- Con un framework usa il suo sistema (Laravel `__()`, `trans_choice()` e file in `lang/`; Symfony Translation; Laminas `laminas-i18n`).
- Senza framework: `symfony/translation` (formati multipli, ICU MessageFormat) o gettext (`ext-gettext` + file `.po`/`.mo`, strumenti come Poedit).
- Chiavi strutturate (`checkout.errors.card_declined`) facilitano l'organizzazione; frasi reali come chiave facilitano i traduttori. Scegli una convenzione e applicala in modo coerente.
- Mai comporre frasi traducibili concatenando pezzi: l'ordine delle parole cambia tra lingue. Usa segnaposto con nome.
- I plurali variano per lingua (una forma in giapponese, due in italiano, tre o più in lingue slave): usa sempre il meccanismo di plurali della libreria, mai `$n === 1 ? ... : ...`.
