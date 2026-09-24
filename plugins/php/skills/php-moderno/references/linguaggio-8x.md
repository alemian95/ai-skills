# Funzionalità del linguaggio PHP 8.x

Fonti: [PHP The Right Way — Language Highlights](https://phptherightway.com/#language_highlights), [Use the Current Stable Version](https://phptherightway.com/#use_the_current_stable_version), manuale PHP (appendici di migrazione per versione).

## Indice
1. Disponibilità per versione
2. Linee guida d'uso
3. Property hooks e visibilità asimmetrica (8.4)
4. Deprecazioni che toccano codice esistente

## 1. Disponibilità per versione

Usa una funzionalità solo se il vincolo `php` del progetto la include.

| Versione | Funzionalità principali |
|---|---|
| 8.0 | argomenti nominati, tipi unione, `mixed`, `static` come ritorno, nullsafe `?->`, `match`, promozione nel costruttore, attributi, `throw` come espressione, `catch` senza variabile, `str_contains`/`str_starts_with`/`str_ends_with`, `Stringable`, `WeakMap`; le funzioni interne lanciano `TypeError`/`ValueError` |
| 8.1 | `enum` (pure e backed), proprietà `readonly`, first-class callable `strlen(...)`, `never`, `new` negli inizializzatori, tipi intersezione, costanti di classe `final`, `array_is_list`, Fiber |
| 8.2 | classi `readonly`, tipi DNF `(A&B)\|null`, `true`/`false`/`null` come tipi autonomi, costanti nei trait, `#[\SensitiveParameter]`, estensione Random (`Random\Randomizer`); proprietà dinamiche deprecate |
| 8.3 | costanti di classe tipizzate, `#[\Override]`, `json_validate()`, accesso dinamico alle costanti `Foo::{$nome}`, clonazione profonda di proprietà readonly in `__clone`, `mb_str_pad`, `Randomizer::getBytesFromString` |
| 8.4 | property hooks, visibilità asimmetrica `public private(set)`, `new Foo()->metodo()` senza parentesi, `array_find`/`array_find_key`/`array_any`/`array_all`, `#[\Deprecated]`, lazy objects, `mb_trim`/`mb_ltrim`/`mb_rtrim`/`mb_ucfirst`/`mb_lcfirst`, API DOM HTML5 (`Dom\HTMLDocument`), `BcMath\Number`, sottoclassi PDO per driver (`Pdo\Mysql`, `Pdo\Pgsql`, `Pdo\Sqlite`) con `PDO::connect()`, `request_parse_body()`, costo bcrypt predefinito 12 |
| 8.5 | operatore pipe `\|>`, `clone($obj, ['prop' => $valore])`, `#[\NoDiscard]`, estensione URI (`Uri\Rfc3986\Uri`, `Uri\WhatWg\Url`), `array_first`/`array_last`, closure nelle espressioni costanti, backtrace negli errori fatali |

## 2. Linee guida d'uso

**Argomenti nominati**: usali quando un argomento booleano o opzionale non si capisce dalla posizione (`new Settings(debug: false, ...)`, `json_decode($s, true, flags: JSON_THROW_ON_ERROR)`). Rendono i nomi dei parametri parte dell'API pubblica: rinominarli è una modifica incompatibile nelle librerie.

**Enum**: backed (`: string`/`: int`) quando il valore viene persistito o scambiato; puro altrimenti. Metti il comportamento legato al caso nell'enum (`$status->label()` con `match ($this)`). Converti l'input con `tryFrom()` e gestisci `null` al confine; `from()` solo quando un valore non valido è un bug.

**readonly**: classe `readonly` per value object e servizi senza stato. Le proprietà readonly si inizializzano una volta sola, dall'interno della classe. Per ottenere una copia modificata usa metodi `with...()` che restituiscono una nuova istanza (8.5: `clone($this, ['x' => $x])`).

**match**: esaustivo sugli enum. Non aggiungere `default` quando elenchi tutti i casi: senza `default`, un caso nuovo produce `UnhandledMatchError` invece di un valore sbagliato silenzioso.

**First-class callable**: `array_map($this->normalize(...), $items)` al posto di `[$this, 'normalize']` o `'strtoupper'`: è verificabile dall'analisi statica e non si rompe con i rename.

**Nullsafe `?->`**: solo quando `null` è un esito legittimo della catena, non per zittire un errore di modello dei dati.

**Attributi**: `#[\Override]` sui metodi che sovrascrivono (errore se il metodo padre sparisce); `#[\SensitiveParameter]` su password e token per escluderli dai backtrace; `#[\Deprecated]` (8.4) sulle API in dismissione.

**Funzioni array 8.4**: `array_find($items, fn($x) => ...)` restituisce il primo valore o `null`; `array_any`/`array_all` restituiscono `bool`. Sostituiscono `foreach` con `break` e `array_filter(...)[0] ?? null`.

**Operatore pipe (8.5)**: `$slug = $title |> trim(...) |> strtolower(...) |> fn($s) => preg_replace('/\W+/', '-', $s);`. Utile per trasformazioni lineari con callable a un argomento; non usarlo per logica con rami.

## 3. Property hooks e visibilità asimmetrica (8.4)

```php
final class Product
{
    public private(set) string $sku;          // leggibile da fuori, scrivibile solo dalla classe

    public string $name {
        set => mb_trim($value);               // normalizzazione in scrittura
    }

    public string $label {
        get => "{$this->sku} — {$this->name}"; // proprietà virtuale calcolata
    }

    public function __construct(string $sku, string $name)
    {
        $this->sku = $sku;
        $this->name = $name;
    }
}
```

Quando usarli:
- **Visibilità asimmetrica** al posto della coppia proprietà privata + getter, quando l'oggetto è mutabile ma la mutazione deve passare dai metodi della classe.
- **Hook `set`** per normalizzare o validare in scrittura; **hook `get`** per proprietà derivate economiche.
- Le interfacce possono dichiarare proprietà con hook (`public string $name { get; }`).

Vincoli e cautele:
- Una proprietà con hook non può essere `readonly`, e una classe `readonly` non può avere proprietà con hook. Per value object immutabili resta preferibile `readonly` con validazione nel costruttore.
- Negli hook niente I/O, query o logica costosa: chi legge `$obj->x` si aspetta un accesso a una proprietà.
- `$value` è il nome implicito del valore in ingresso di `set`; dentro un hook, `$this->prop` si riferisce alla proprietà sottostante.
- Non usarli sui modelli Eloquent né su classi idratate da ORM o serializer che scrivono le proprietà per riflessione, se non verifichi che la libreria li supporti.

## 4. Deprecazioni che toccano codice esistente

- **8.1**: passare `null` a parametri non nullable delle funzioni interne (es. `strlen(null)`); `FILTER_SANITIZE_STRING`; interfaccia `Serializable` senza `__serialize`/`__unserialize`.
- **8.2**: proprietà dinamiche (usa proprietà dichiarate; `#[\AllowDynamicProperties]` solo come ponte temporaneo); interpolazione `"${var}"`.
- **8.3**: `get_class()`/`get_parent_class()` senza argomenti.
- **8.4**: parametri nullable impliciti (`Foo $x = null` → `?Foo $x = null`); costante `E_STRICT`; `trigger_error()` con `E_USER_ERROR`; `exit()` è ora una funzione vera.
- **8.5**: operatore backtick (usa `shell_exec` o meglio `proc_open` con array); cast non canonici `(integer)`, `(boolean)`, `(double)`, `(binary)`; `__sleep`/`__wakeup` a favore di `__serialize`/`__unserialize`.

Rector (`references/strumenti.md`) corregge automaticamente la maggior parte di questi casi.
