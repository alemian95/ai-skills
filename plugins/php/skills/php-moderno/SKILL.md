---
name: "php-moderno"
description: Regole vincolanti e riferimenti per scrivere, rivedere e rifattorizzare codice PHP moderno (8.4+), valide con o senza framework (PHP puro, Laminas/Zend, Mezzio, Slim, Symfony, Laravel, CodeIgniter…) e basate su PHP The Right Way e sugli standard PHP-FIG (PSR, PER Coding Style). Copre tipi stretti, funzionalità 8.x, sicurezza, errori ed eccezioni, PDO, dependency injection, interfacce PSR, strumenti (Composer, PHPStan, PHP-CS-Fixer/Pint, Rector, PHPUnit/Pest, Xdebug 3), testo, date e i18n. Usala ogni volta che scrivi o modifichi codice PHP, fai code review o security review di codice PHP, aggiorni codice legacy PHP 5/7 o configuri strumenti di qualità. Si combina con skill specifiche di framework o di ambiente (es. php-hosting-condiviso). Use for any PHP coding, review, refactoring or upgrade task.
---

# PHP moderno

Claude conosce già PHP. Questa skill fissa **regole generali** che valgono in qualsiasi contesto (script, codice senza framework, qualunque framework) e **corregge abitudini superate** che circolano in tutorial e codice legacy.

## Fonti

Le regole si appoggiano a due fonti. Citale in review e nelle spiegazioni quando giustificano una scelta.
- **[PHP The Right Way](https://phptherightway.com/)**: buone pratiche della comunità, indipendenti dal framework. Ogni file in `references/` rimanda alle sue sezioni.
- **[PHP-FIG](https://www.php-fig.org/psr/)**: gli standard di interoperabilità (PSR) e lo stile di codice ([PER Coding Style](https://www.php-fig.org/per/coding-style/), che sostituisce PSR-12). Stato e uso in `references/psr.md`.

Dove la skill è più restrittiva o più aggiornata di queste fonti (versione minima 8.4, strumenti recenti, API deprecate), vale la skill.

## Ambito e precedenza

1. **Versione di riferimento: PHP 8.4.** Prima di scrivere codice leggi il vincolo `php` in `composer.json` (e `config.platform.php` se presente). Se è inferiore a 8.4, non usare funzionalità non disponibili: la tabella per versione è in `references/linguaggio-8x.md`. Usa funzionalità 8.5 solo se il vincolo lo consente.
2. **Regole sul linguaggio e sugli standard, non sui pacchetti.** Le regole di base valgono ovunque. I pacchetti citati nei riferimenti sono esempi di implementazione, non scelte obbligate.
3. **Con un framework, il framework decide** architettura, DI, HTTP, ORM, validazione, configurazione, i18n, gestione eccezioni e preset di stile (Laravel: service provider, Eloquent, Form Request, Pint; Symfony: `services.yaml`, Doctrine, Form/Validator; Laminas/Mezzio: factory del service manager, middleware PSR-15). Questa skill copre ciò che il framework lascia al codice: linguaggio, tipi, eccezioni di dominio, sicurezza a livello di codice, strumenti. Non introdurre pattern che contraddicono le convenzioni del progetto; se esiste una skill specifica del framework (es. `laravel-action-vs-service`), ha la precedenza nel suo ambito.
4. **Classi gestite dal framework per riflessione o magia** (modelli Active Record come Eloquent, entità idratate da ORM o serializer): non applicare `readonly`, property hooks o promozione nel costruttore senza verificare che la libreria li supporti.
5. **Senza framework:** componi implementazioni delle interfacce PSR scelte in un solo punto (`references/psr.md` §5-6). Hosting condiviso senza SSH o senza poter cambiare la document root: usa la skill `php-hosting-condiviso`, se presente.
6. **Convenzioni del repository** (strumenti configurati, stile, struttura cartelle) prevalgono sulle preferenze di questa skill quando sono esplicite e coerenti.

## Regole di base

Si applicano sempre, salvo vincolo di versione o convenzione esplicita del progetto.

**Tipi e struttura**
- `declare(strict_types=1);` in ogni file: senza, PHP converte silenziosamente gli scalari negli argomenti (`"5 mele"` → `5` fallisce solo con strict).
- Tipi nativi su parametri, ritorni e proprietà. `mixed` solo quando il valore è davvero arbitrario. Per gli array strutturati usa un value object; se l'array resta, documentalo per PHPStan (`list<User>`, `array{id: int, name: string}`).
- Classi `final` di default e `readonly` per value object, DTO e servizi senza stato. L'ereditarietà si progetta, non si concede per caso.
- Promozione dei parametri nel costruttore; nessun setter per dipendenze obbligatorie.
- `enum` al posto di costanti stringa o intere per insiemi chiusi di valori; `match` al posto di `switch` quando si produce un valore (confronto stretto, `UnhandledMatchError` sui casi mancanti).
- Confronti stretti: `===`, `in_array($x, $list, true)`, `array_search(..., true)`.
- `include`/`require` solo per l'autoloader e per file di configurazione che restituiscono un valore. Tutto il resto passa dall'autoload PSR-4.
- Ai confini tra moduli o verso l'infrastruttura, dipendi dalle interfacce PSR (logger, cache, orologio, client HTTP, eventi) o dai contratti del framework, non dalle implementazioni concrete (`references/psr.md` §3).

**Errori ed eccezioni** (dettagli in `references/errori-eccezioni.md`)
- Mai l'operatore `@`: nasconde anche gli errori che non ti aspetti. Usa `??`, controlli espliciti o eccezioni.
- Nei punti di ingresso senza framework converti warning e notice in `ErrorException`.
- Cattura solo ciò che sai gestire, al confine giusto. Quando rilanci, avvolgi con `previous: $e`. Mai `catch (\Throwable)` vuoto.
- Preferisci le eccezioni SPL (`InvalidArgumentException`, `DomainException`, `RuntimeException`, …) prima di crearne di nuove. Crea eccezioni di dominio quando il chiamante deve distinguerle.
- `json_encode`/`json_decode` sempre con `JSON_THROW_ON_ERROR`.

**Sicurezza** (dettagli in `references/sicurezza.md`)
- L'input esterno non è mai affidabile: validalo al confine e trasformalo in tipi o value object; il codice interno lavora solo con dati già validi.
- Escape in output secondo il contesto: HTML → `htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` (o il motore di template con escape automatico), JS → `json_encode` con i flag `JSON_HEX_*`, URL → `rawurlencode`.
- SQL solo con prepared statement e parametri legati. Nomi di colonne, direzioni `ORDER BY` e tabelle dinamiche si prendono esclusivamente da una whitelist.
- Password: `password_hash(PASSWORD_DEFAULT)`, `password_verify`, `password_needs_rehash`. Mai `md5`, `sha1`, `hash()` o sale fatto a mano.
- Token e segreti: `random_bytes`/`random_int` e confronto con `hash_equals`. Mai `rand`, `mt_rand` o `uniqid`.
- Mai `unserialize` su dati esterni, mai `eval`, `extract` o `$$variabile`.
- Segreti fuori dal repository e fuori dalla document root. In produzione `display_errors=Off` e log attivo.

**Testo e date** (dettagli in `references/testo-date-i18n.md`)
- Funzioni `mb_*` per lunghezza, taglio e maiuscole su testo utente; `utf8mb4` su MySQL.
- `DateTimeImmutable` con fuso orario esplicito; intervalli con `DateInterval`, mai aritmetica con 86400 secondi.

**Dipendenze e architettura** (dettagli in `references/dependency-injection.md`)
- Iniezione nel costruttore. Il container si usa solo nella composition root (bootstrap, factory, service provider): `$container->get()` dentro una classe di dominio è un service locator.
- Interfacce ai confini (I/O, servizi esterni, orologio, persistenza), non per ogni classe.
- Niente stato globale: niente `global` né proprietà `static` mutabili. Le superglobali si leggono solo nel punto di ingresso o tramite l'oggetto richiesta.

**Strumenti** (dettagli in `references/strumenti.md`)
- Stile PER Coding Style (o il preset del framework: Pint in Laravel, `@Symfony` in Symfony, `laminas-coding-standard` in Laminas), applicato dal fixer e non a mano. Nomi dei simboli in inglese.
- Analisi statica con PHPStan: nei nuovi progetti al livello `max`, nei legacy con baseline.
- Composer: committa `composer.lock` per le applicazioni; in deploy `composer install --no-dev --optimize-autoloader`, mai `update`; `composer audit` in CI.

## Costrutti superati da correggere

Quando li trovi nel codice che tocchi, sostituiscili (o segnalali in review) con la forma moderna:

| Superato | Moderno |
|---|---|
| `mysql_*`, query concatenate | PDO / query builder con parametri legati |
| `md5($password)`, `sha1`, `crypt` manuale | `password_hash` / `password_verify` |
| `rand()`, `uniqid()` per token | `bin2hex(random_bytes(32))` |
| `@fopen(...)`, `@$arr['k']` | controllo esplicito, `??`, eccezione |
| `isset($a['k']) ? $a['k'] : null` | `$a['k'] ?? null` |
| `strpos($h, $n) !== false` | `str_contains` / `str_starts_with` / `str_ends_with` |
| `switch` che assegna un valore | `match` |
| costanti `STATUS_*` stringa/intere | `enum` (backed se persistito) |
| getter/setter banali su DTO | proprietà `public readonly` (o property hooks, 8.4) |
| `foreach` per trovare un elemento | `array_find`, `array_any`, `array_all` (8.4) |
| `(new Foo())->bar()` | `new Foo()->bar()` (8.4) |
| `Foo $x = null` (nullable implicito) | `?Foo $x = null` (deprecato in 8.4) |
| `call_user_func_array([$o, 'm'], $args)` | `$o->m(...$args)`, first-class callable `$o->m(...)` |
| `DateTime` mutabile passato in giro | `DateTimeImmutable` |
| `json_decode` + `json_last_error()` | `JSON_THROW_ON_ERROR` |
| `mb_internal_encoding('UTF-8')` in ogni script | superfluo: `default_charset` vale UTF-8 dalla 5.6 |
| `FILTER_SANITIZE_STRING` | rimosso: valida il formato, fai escape in output |
| `xdebug.remote_*`, porta 9000 | Xdebug 3: `xdebug.mode`, `client_host`, porta 9003 |
| docblock `@test`, `@dataProvider` in PHPUnit | attributi `#[Test]`, `#[DataProvider]` (PHPUnit ≥ 12) |
| Local PHP Security Checker | `composer audit` |
| autoload PSR-0, `classmap` per codice nuovo | PSR-4 |
| stile PSR-2 / PSR-12 | PER Coding Style |
| `zendframework/*` | `laminas/*` (Zend Framework è diventato Laminas nel 2020) |
| `narrowspark/http-emitter` | `laminas/laminas-httphandlerrunner` |

## Flusso di lavoro

**Scrivere o modificare codice**
1. Leggi il vincolo `php`, gli strumenti presenti (`phpstan.neon*`, `.php-cs-fixer*`, `pint.json`, `phpunit.xml*`, `pest`, `rector.php`) e un paio di file vicini per allinearti alle convenzioni.
2. Scrivi seguendo le regole di base; apri il riferimento pertinente solo se il compito tocca quell'area in modo non banale.
3. Se hai accesso al terminale, esegui gli strumenti del progetto (fixer → analisi statica → test) e correggi prima di consegnare. Non introdurre strumenti nuovi senza chiedere.

**Code review** — segnala in quest'ordine, fermandoti al livello che conta:
1. sicurezza (injection, escape, segreti, autorizzazione mancante, deserializzazione);
2. correttezza (tipi, confronti laschi, errori ignorati, casi limite, fusi orari, multibyte);
3. contratti e progettazione (tipi deboli, stato globale, service locator, eccezioni generiche);
4. costrutti superati (tabella sopra).
Lo stile non si commenta a mano: è compito del fixer.

**Aggiornare codice legacy** — procedi a passi verificabili: prima PHPStan con baseline e test di caratterizzazione, poi Rector per salti di versione meccanici, poi correzioni manuali. Dettagli in `references/strumenti.md`.

## Riferimenti

Leggi solo il file che serve al compito corrente.

| File | Quando leggerlo | PHP The Right Way |
|---|---|---|
| `references/linguaggio-8x.md` | scegliere costrutti, verificare in che versione esiste una funzionalità, property hooks, visibilità asimmetrica, novità 8.4/8.5 | [Language Highlights](https://phptherightway.com/#language_highlights), [Use the Current Stable Version](https://phptherightway.com/#use_the_current_stable_version) |
| `references/sicurezza.md` | input, output, SQL, password, crittografia, upload, sessioni, file, security review | [Security](https://phptherightway.com/#security), [Templating](https://phptherightway.com/#templating) |
| `references/errori-eccezioni.md` | gerarchie di eccezioni, error handler, logging, cosa catturare e dove | [Errors and Exceptions](https://phptherightway.com/#errors_and_exceptions) |
| `references/database-pdo.md` | PDO senza ORM, opzioni di connessione, transazioni, query dinamiche, `utf8mb4` | [Databases](https://phptherightway.com/#databases) |
| `references/dependency-injection.md` | container, autowiring, composition root, cosa iniettare | [Dependency Injection](https://phptherightway.com/#dependency_injection) |
| `references/psr.md` | standard PSR e PER in vigore, interfacce da usare, PSR nei framework, pipeline HTTP PSR-7/15, codice senza framework | [Code Style Guide](https://phptherightway.com/#code_style_guide) |
| `references/strumenti.md` | Composer, PHP-CS-Fixer/Pint, PHPStan/Larastan, Rector, PHPUnit/Pest, Xdebug 3, OPcache, PHPDoc | [Dependency Management](https://phptherightway.com/#dependency_management), [Testing](https://phptherightway.com/#testing), [Caching](https://phptherightway.com/#caching), [Documenting](https://phptherightway.com/#documenting) |
| `references/testo-date-i18n.md` | UTF-8, `mb_*`, `intl`, date e fusi orari, traduzioni | [UTF-8](https://phptherightway.com/#php_and_utf8), [Date and Time](https://phptherightway.com/#date_and_time), [i18n](https://phptherightway.com/#i18n_l10n) |
