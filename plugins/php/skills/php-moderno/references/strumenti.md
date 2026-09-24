# Strumenti

Fonti: [PHP The Right Way — Dependency Management](https://phptherightway.com/#dependency_management), [Testing](https://phptherightway.com/#testing), [Xdebug](https://phptherightway.com/#xdebug), [Caching](https://phptherightway.com/#caching), [Documenting](https://phptherightway.com/#documenting), [PER Coding Style](https://www.php-fig.org/per/coding-style/).

Regola generale: usa gli strumenti già configurati nel progetto e i loro comandi (`composer` scripts, `Makefile`, `vendor/bin/*`). Non introdurne di nuovi e non cambiare le configurazioni esistenti senza chiedere.

## Indice
1. Composer
2. Stile: PHP-CS-Fixer e Pint
3. Analisi statica: PHPStan
4. Aggiornamenti: Rector
5. Test: PHPUnit e Pest
6. Debug e profilazione: Xdebug 3
7. Produzione: OPcache
8. PHPDoc

## 1. Composer

- **Vincoli**: `^` per le dipendenze (`^3.8` = ≥3.8 <4.0). Evita `*` e `dev-main` in produzione.
- **Lock**: `composer.lock` si committa per le applicazioni (installazioni riproducibili); per le librerie di solito no.
- **Piattaforma**: `config.platform.php` alla versione di PHP di produzione, così la risoluzione non sceglie pacchetti che richiedono una versione più recente di quella in esercizio.
- **Deploy**: `composer install --no-dev --optimize-autoloader --no-interaction` (con `--classmap-authoritative` se nessuna classe viene generata a runtime). Mai `composer update` sui server.
- **Sicurezza**: `composer audit` in CI (sostituisce Local PHP Security Checker, archiviato nel 2024).
- **Controlli utili**: `composer validate --strict`, `composer why <pkg>`, `composer why-not <pkg> <versione>`, `composer outdated --direct`.
- **Scripts**: esponi i comandi di qualità come script (`composer test`, `composer stan`, `composer cs`, `composer check`) così CI e sviluppatori eseguono le stesse cose.
- **Dipendenze dirette**: richiedi esplicitamente i pacchetti di cui usi le classi (incluse le interfacce `psr/*`), non affidarti alle dipendenze transitive.

## 2. Stile: PHP-CS-Fixer e Pint

Lo stile si applica con uno strumento, non a mano e non in code review.

**Predefinito** (senza framework, librerie, framework senza preset proprio) — PHP-CS-Fixer con PER Coding Style:

```php
return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS3x0' => true,
        '@PER-CS3x0:risky' => true,
        '@PHP8x4Migration' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'no_unused_imports' => true,
    ])
    ->setFinder(PhpCsFixer\Finder::create()->in([__DIR__ . '/src', __DIR__ . '/tests']));
```

Nelle versioni recenti i set sono nominati `@PER-CS3x0`, `@PHP8x4Migration` (le forme con il punto, es. `@PER-CS2.0`, restano come alias). `@PER-CS` senza versione segue sempre la revisione più recente e può cambiare lo stile a un aggiornamento: fissa la versione.

**Preset di framework** — se il progetto ne usa uno, vale quello; non affiancare un secondo fixer.
- **Laravel**: Pint (`vendor/bin/pint`), preset `laravel` in `pint.json`; in CI `pint --test`.
- **Symfony**: PHP-CS-Fixer con `@Symfony` (e `@Symfony:risky`).
- **Laminas / Mezzio**: `laminas/laminas-coding-standard` (PHP_CodeSniffer, `phpcs`/`phpcbf`).

## 3. Analisi statica: PHPStan

- Nuovi progetti: `level: max` da subito, è molto più economico che alzarlo dopo.
- Progetti esistenti: scegli il livello più alto sostenibile e genera una baseline (`vendor/bin/phpstan analyse --generate-baseline`); il nuovo codice non deve aggiungere errori, la baseline si riduce nel tempo.
- **Laravel**: `larastan/larastan`, includendo `vendor/larastan/larastan/extension.neon`.
- Usa i tipi PHPDoc che PHP non esprime (`list<T>`, `array<K, V>`, `array{...}`, `non-empty-string`, `positive-int`, generici `@template`) per dare informazioni all'analisi, non per duplicare i tipi nativi.
- Non zittire errori con `@phpstan-ignore` senza un identificatore e un motivo (`@phpstan-ignore argument.type (motivo)`).
- Pattern che l'analisi apprezza: tipi nativi ovunque, `assert()` o `instanceof` dopo `$container->get()` e dopo `require` di file di configurazione, ritorni espliciti di `null`.

Psalm è un'alternativa valida; non usare entrambi.

## 4. Aggiornamenti: Rector

Rector applica trasformazioni automatiche: aggiornamenti di versione PHP, aggiunta di tipi, pulizia del codice, migrazioni di framework e PHPUnit.

```php
// rector.php
return Rector\Config\RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withPhpSets()                 // legge il vincolo php da composer.json
    ->withPreparedSets(deadCode: true, codeQuality: true, typeDeclarations: true)
    ->withImportNames(removeUnusedImports: true);
```

Procedura per codice legacy: test di caratterizzazione sul comportamento attuale → PHPStan con baseline → `vendor/bin/rector process --dry-run`, revisione del diff → applicazione per insiemi piccoli e commit separati → fixer di stile alla fine. In Laravel esiste `driftingly/rector-laravel` per le migrazioni tra versioni del framework.

## 5. Test: PHPUnit e Pest

**PHPUnit** (12 richiede PHP 8.3, 13 richiede PHP 8.4.1)
- Metadati con attributi: `#[Test]`, `#[DataProvider('nome')]`, `#[CoversClass(Foo::class)]`, `#[Group('lento')]`. Le annotazioni nei docblock (`@test`, `@dataProvider`, `@covers`) non sono più supportate da PHPUnit 12.
- Data provider `public static`, preferibilmente con chiavi descrittive (`yield 'caso limite' => [...]`).
- Configurazione rigorosa in `phpunit.xml.dist`: `failOnWarning`, `failOnDeprecation`, `failOnNotice`, `failOnRisky`, `beStrictAboutOutputDuringTests`, `executionOrder="random"` (scopre dipendenze nascoste tra test).
- Classi di test `final`, un comportamento per test, nomi che descrivono il comportamento.

**Pest** — standard de facto nei progetti Laravel recenti; si basa su PHPUnit, quindi le stesse regole di rigore valgono. Segui lo strumento già presente nel progetto.

**Strategia**
- Dominio: test unitari senza container, costruendo gli oggetti con `new`.
- Confini (HTTP, database, code): test di integrazione con implementazioni reali o fake scritti a mano.
- Preferisci fake alle mock configurate: le mock accoppiano il test all'implementazione.
- Tempo e casualità iniettati (PSR-20 `ClockInterface`, `Random\Randomizer` con engine deterministico) per test ripetibili.

## 6. Debug e profilazione: Xdebug 3

La configurazione Xdebug 2 (`xdebug.remote_enable`, `remote_host`, porta 9000), ancora presente in molte guide, non funziona più.

```ini
zend_extension = xdebug
xdebug.mode = debug,develop            ; aggiungi coverage o profile solo quando servono
xdebug.start_with_request = trigger    ; si attiva solo con XDEBUG_TRIGGER / XDEBUG_SESSION
xdebug.client_host = 127.0.0.1         ; in Docker: host.docker.internal (o l'IP dell'host)
xdebug.client_port = 9003
```

- Web: estensione del browser o parametro/cookie `XDEBUG_TRIGGER`. CLI: `XDEBUG_TRIGGER=1 php script.php`.
- `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html var/coverage` per la copertura senza tenere il modo attivo; PCOV è più veloce se serve solo la copertura.
- Xdebug rallenta sensibilmente l'esecuzione: mai in produzione, e con `mode=off` quando non lo usi.
- Profilazione: `xdebug.mode=profile` (file cachegrind) in locale; in ambienti condivisi strumenti dedicati (SPX, Blackfire, Tideways).

## 7. Produzione: OPcache

```ini
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0        ; nessun controllo dei file: riavvia/ricarica PHP-FPM a ogni deploy
opcache.interned_strings_buffer = 16
```

- Con `validate_timestamps=0` il deploy deve ricaricare PHP-FPM (o invalidare la cache), altrimenti resta in esecuzione il codice vecchio.
- Il preloading (`opcache.preload`) dà benefici misurabili solo su framework grandi e richiede un riavvio a ogni modifica: valutalo con un benchmark.
- Il JIT aiuta il codice CPU-intensivo; le applicazioni web tipiche sono dominate dall'I/O e ne traggono poco. Attivalo solo dopo aver misurato.
- In sviluppo lascia `validate_timestamps=1`.

## 8. PHPDoc

- Scrivi PHPDoc solo quando aggiunge informazione: tipi che PHP non esprime (vedi §3), `@throws` per eccezioni che il chiamante deve gestire, spiegazione del *perché* di una scelta non ovvia.
- Nessun docblock che ripete la firma (`@param string $name Il nome`).
- I commenti descrivono intenzione e vincoli, non quello che il codice dice già.
