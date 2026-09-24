# Standard PHP-FIG (PSR e PER)

Fonti: [php-fig.org/psr](https://www.php-fig.org/psr/), [PER Coding Style](https://www.php-fig.org/per/coding-style/), [PHP The Right Way — Code Style Guide](https://phptherightway.com/#code_style_guide).

## Indice
1. Perché dipendere dagli standard
2. Stato degli standard
3. Regole d'uso
4. Gli standard nei framework
5. Pipeline HTTP PSR-7/15
6. Senza framework

## 1. Perché dipendere dagli standard

Il valore dei PSR è la sostituibilità. Il codice dipende dalle interfacce `psr/*` e l'implementazione (Monolog, Diactoros, Nyholm, Guzzle, il container del framework) si sceglie in un punto solo, la composition root. Lo stesso servizio di dominio che riceve un `LoggerInterface` o un `ClockInterface` funziona senza modifiche in uno script, in Laminas, in Symfony o in Laravel.

## 2. Stato degli standard

Settembre 2026. Prima di citarne uno controlla lo stato su php-fig.org.

| Standard | Oggetto | Stato |
|---|---|---|
| [PSR-1](https://www.php-fig.org/psr/psr-1/) | stile di base: tag PHP, UTF-8, nomi di classi/metodi/costanti | accettato |
| [PER Coding Style](https://www.php-fig.org/per/coding-style/) (3.x) | stile esteso | sostituisce PSR-12 |
| [PSR-12](https://www.php-fig.org/psr/psr-12/) | stile esteso | superato da PER-CS |
| [PSR-3](https://www.php-fig.org/psr/psr-3/) | logger | accettato |
| [PSR-4](https://www.php-fig.org/psr/psr-4/) | autoload | accettato |
| [PSR-6](https://www.php-fig.org/psr/psr-6/) / [PSR-16](https://www.php-fig.org/psr/psr-16/) | cache (pool / semplice) | accettati |
| [PSR-7](https://www.php-fig.org/psr/psr-7/) | messaggi HTTP immutabili | accettato |
| [PSR-11](https://www.php-fig.org/psr/psr-11/) | container | accettato |
| [PSR-13](https://www.php-fig.org/psr/psr-13/) | link ipermediali | accettato |
| [PSR-14](https://www.php-fig.org/psr/psr-14/) | dispatcher di eventi | accettato |
| [PSR-15](https://www.php-fig.org/psr/psr-15/) | handler e middleware HTTP lato server | accettato |
| [PSR-17](https://www.php-fig.org/psr/psr-17/) | factory per i messaggi PSR-7 | accettato |
| [PSR-18](https://www.php-fig.org/psr/psr-18/) | client HTTP | accettato |
| [PSR-20](https://www.php-fig.org/psr/psr-20/) | orologio | accettato |
| PSR-5, PSR-19 | PHPDoc e tag PHPDoc | bozza: non vincolanti, ma sono la sintassi che PHPStan e Psalm leggono |
| PSR-21, PSR-22 | internazionalizzazione, tracing | bozza: non dipenderne |
| PSR-0, PSR-2 | autoload, stile | deprecati: sostituiti da PSR-4 e PER-CS |

## 3. Regole d'uso

- **Stile**: PER Coding Style applicato da un fixer, con la versione fissata. Se il framework ha un preset proprio (Laravel Pint, set `@Symfony`, `laminas/laminas-coding-standard`), vale il preset. Dettagli in `strumenti.md` §2.
- **Autoload**: solo PSR-4 in `composer.json`, un namespace radice per progetto (`App\` o `Vendor\Pacchetto\`), un simbolo per file. Niente `classmap` o `files` se non per codice legacy o funzioni pure.
- **Dipendi dall'interfaccia, non dall'implementazione**: tipa i parametri con `Psr\Log\LoggerInterface`, `Psr\Clock\ClockInterface`, `Psr\SimpleCache\CacheInterface`, `Psr\Http\Client\ClientInterface`, `Psr\EventDispatcher\EventDispatcherInterface` quando il servizio deve restare portabile tra progetti o framework. Dentro un'applicazione legata a un framework, anche il contratto del framework va bene, se il progetto lo usa già.
- **Richiedi i pacchetti `psr/*` che usi direttamente** in `composer.json`, non affidarti alle dipendenze transitive.
- **PSR-11 non è un'autorizzazione al service locator**: `ContainerInterface` si usa nella composition root, non nelle classi applicative (`dependency-injection.md` §2).
- **PSR-7 è immutabile ma lo stream del corpo no**: non condividere istanze di `ResponseInterface` pre-costruite, crea le risposte con le factory PSR-17.
- **PSR-3**: messaggi con segnaposto `{chiave}` e dati nel contesto, eccezione sotto la chiave `exception` (`errori-eccezioni.md` §6).
- **PSR-20**: inietta l'orologio nei servizi che dipendono dall'ora corrente, così i test usano un orologio fisso.

## 4. Gli standard nei framework

Il framework resta il riferimento per la sua architettura. Questa tabella serve a scrivere codice che attraversa il confine (pacchetti condivisi, moduli di dominio portabili, integrazioni).

| Framework | Cosa espone o implementa |
|---|---|
| **Laminas / Mezzio** (ex Zend Framework) | PSR-7/17 (`laminas-diactoros`), PSR-15 nativo (Mezzio, Stratigility), PSR-11 (`laminas-servicemanager`), PSR-3 via Monolog |
| **Symfony** | PSR-3 (Monolog), PSR-6/16 (Cache), PSR-11, PSR-14 (EventDispatcher), PSR-18 (`Psr18Client` di HttpClient), PSR-20 (Clock). HttpFoundation non è PSR-7: per convertire c'è `symfony/psr-http-message-bridge` |
| **Laravel** | PSR-3 (logger), PSR-11 (container), PSR-16 (cache repository), PSR-18 tramite Guzzle. La richiesta non è PSR-7: un parametro `ServerRequestInterface` nei controller funziona installando `symfony/psr-http-message-bridge` e `nyholm/psr7` |
| **Slim 4** | PSR-7/15/17 nativi, PSR-11 per il container scelto |

Se il framework non implementa uno standard, non forzarlo: usa il meccanismo del framework e isola la dipendenza dietro un adattatore solo se il codice deve davvero essere portabile.

## 5. Pipeline HTTP PSR-7/15

Vale per Mezzio, Slim e applicazioni senza framework. Laravel e Symfony hanno pipeline proprie con concetti equivalenti (middleware, kernel events).

**Ciclo.** Con PHP-FPM o mod_php ogni richiesta riparte da zero: front controller → autoload → conversione degli errori in eccezioni → costruzione del container → `ServerRequest` creata dalle superglobali (unico punto in cui si leggono) → middleware → router → handler → `Response` → emitter verso la SAPI.

**Document root.** Il web server espone solo la cartella pubblica (`public/`), che contiene il front controller e gli asset. Codice, `vendor/`, configurazione e dati restano fisicamente fuori. È l'unica protezione che resiste ai guasti di configurazione (`.htaccess` ignorato, gestore PHP rotto che serve i sorgenti, `.env` o `.git/` dimenticati, script eseguibili in `vendor/`). Se un file esiste il web server lo serve, altrimenti passa la richiesta al front controller: per questo in `public/` c'è un solo file PHP. Se non puoi cambiare la document root (hosting condiviso) usa la skill `php-hosting-condiviso`.

**Ordine dei middleware**, dall'esterno verso l'interno:
1. gestione errori, per primo: intercetta le eccezioni di tutti gli altri, registra nel log, espone dettagli solo in debug;
2. trasversali: intestazioni di sicurezza, CORS, parsing del corpo, sessione, autenticazione;
3. router: 404/405, parametri di rotta come attributi della richiesta;
4. middleware legati alla rotta (autorizzazione);
5. esecuzione dell'handler.

**Handler / controller.**
- Estrae e valida l'input, lo converte in tipi del dominio, chiama il servizio, traduce il risultato in risposta. Nessuna logica di business.
- Errori di validazione → risposta 4xx; eccezioni impreviste → lasciale salire al gestore degli errori.
- Mai `echo`, `header()` o `exit`: scavalcano la pipeline e impediscono ai middleware di agire sulla risposta.

**Runtime persistenti** (FrankenPHP in modalità worker, RoadRunner, Swoole, Laravel Octane): il container sopravvive tra le richieste, quindi servizi senza stato di richiesta e nessuna lettura delle superglobali fuori dalla creazione della richiesta (`dependency-injection.md` §6). Per download grandi usa un emitter a stream.

## 6. Senza framework

- Componi implementazioni PSR interoperabili, scegliendo ciascuna in un solo punto: messaggi PSR-7/17, container PSR-11, dispatcher PSR-15, router, emitter, logger PSR-3. Qualsiasi implementazione conforme va bene; preferisci pacchetti mantenuti e verifica con `composer audit`.
- Configurazione tipizzata e immutabile costruita nel bootstrap; nessuna lettura di `getenv()` o `$_ENV` nelle classi.
- Quando servono sessioni, autenticazione, validazione strutturata, ORM, migrazioni, code, scheduler, mail e traduzioni, riassemblarli a mano costa più di quanto rende: passa a un micro-framework PSR (Slim, Mezzio), dove handler e middleware si portano quasi invariati, o a un framework completo.
