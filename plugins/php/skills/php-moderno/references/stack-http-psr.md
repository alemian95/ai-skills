# Applicazione HTTP senza framework (stack PSR)

Lo scheletro in `assets/skeleton/` implementa quanto descritto qui ed è verificato (PHP 8.4, PHPUnit 13, PHPStan livello max, PER-CS 3.0). Parti da lì invece di riscrivere il bootstrap.

## Indice
1. Standard PSR rilevanti
2. Ciclo richiesta/risposta
3. Componenti scelti
4. Ordine dei middleware
5. Handler
6. Differenze rispetto al tutorial di Kevin Smith (2018)
7. Runtime persistenti e streaming
8. Quando passare a un framework

## 1. Standard PSR rilevanti

| PSR | Oggetto |
|---|---|
| PSR-1, PER Coding Style (sostituisce PSR-12) | stile del codice |
| PSR-3 | logger |
| PSR-4 | autoload |
| PSR-6 / PSR-16 | cache (pool / semplice) |
| PSR-7 | messaggi HTTP immutabili (richiesta, risposta, stream, URI, file caricati) |
| PSR-11 | container |
| PSR-14 | eventi |
| PSR-15 | handler (`RequestHandlerInterface`) e middleware (`MiddlewareInterface`) lato server |
| PSR-17 | factory per i messaggi PSR-7 |
| PSR-18 | client HTTP |
| PSR-20 | orologio |

Il valore degli standard è la sostituibilità: il codice dipende dalle interfacce `psr/*`, l'implementazione (Diactoros, Nyholm, Guzzle PSR-7) si sceglie in un punto solo.

## 2. Ciclo richiesta/risposta

Con PHP-FPM o mod_php ogni richiesta avvia l'applicazione da zero:

```
web server → public/index.php (front controller)
  → autoload Composer
  → conversione errori in eccezioni
  → costruzione del container (composition root)
  → creazione della ServerRequest dalle superglobali (unico punto in cui si leggono)
  → pipeline PSR-15: middleware → router → handler
  → Response
  → emitter: status, header e corpo verso la SAPI
```

La document root del web server punta a `public/`, che contiene solo `index.php` e gli asset. Tutto il resto (codice, `vendor/`, configurazione, dati) è fisicamente fuori dalla portata del browser. Non serve nessuna regola di blocco, e la protezione resiste anche ai guasti di configurazione: `.htaccess` ignorato, gestore PHP rotto che consegna i sorgenti come testo, file dimenticati come `.env`, dump e `.git/`, script eseguibili in `vendor/` come l'`eval-stdin.php` di PHPUnit (CVE-2017-9841).

**File o rotta.** La distinzione tra `/assets/app.css` e `/api/v1/users` la fa il web server prima di PHP, e dipende solo dall'esistenza di un file a quel percorso. Se il file esiste lo serve direttamente; altrimenti passa la richiesta al front controller e il router la confronta con le rotte, che sono URL senza un file corrispondente. Con Apache: `RewriteCond %{REQUEST_FILENAME} !-f` + `RewriteRule ^ index.php [L]`, oppure `FallbackResource /index.php`. Con nginx: `try_files $uri /index.php$is_args$args;`. In sviluppo: `php -S localhost:8080 -t public`, che serve i file esistenti e passa il resto a `index.php`. Ne seguono due conseguenze: se un file e una rotta coincidono vince il file, e ogni `.php` presente in `public/` è eseguibile senza passare dalla pipeline. Per questo in `public/` c'è un solo file PHP.

## 3. Componenti scelti

Versioni verificate a settembre 2026.

| Ruolo | Pacchetto | Note |
|---|---|---|
| PSR-7 + PSR-17 | `laminas/laminas-diactoros` ^3.8 | anche `nyholm/psr7` è una scelta valida e più piccola |
| Container PSR-11 | `php-di/php-di` ^7.1 | autowiring attivo, attributi disattivati; alternative: `league/container` |
| Dispatcher PSR-15 | `relay/relay` ^3.0 | coda di middleware minimale |
| Routing | `nikic/fast-route` ^1.3 + `middlewares/fast-route` ^2.1 | il middleware imposta 404/405 e gli attributi di rotta |
| Esecuzione handler | `middlewares/request-handler` ^2.1 | risolve la classe dell'handler dal container |
| Emitter | `laminas/laminas-httphandlerrunner` ^2.14 | `SapiEmitter`, `SapiStreamEmitter`, `EmitterStack` |
| Logger PSR-3 | `monolog/monolog` ^3.9 | |

Richiedi in `composer.json` anche le interfacce `psr/*` che il tuo codice usa direttamente, non solo i pacchetti che le portano in modo transitivo.

## 4. Ordine dei middleware

La coda va dall'esterno verso l'interno; la risposta ripercorre la coda all'indietro.

1. **Gestione errori**: per primo, così intercetta le eccezioni di tutti gli altri. Converte `Throwable` in 500, registra nel log, espone dettagli solo in debug.
2. Middleware trasversali: intestazioni di sicurezza, CORS, negoziazione del contenuto, parsing del corpo JSON, sessione, autenticazione.
3. **Router**: risolve la rotta, risponde 404/405, aggiunge i parametri come attributi della richiesta.
4. Middleware che dipendono dalla rotta (autorizzazione per rotta).
5. **Esecuzione dell'handler**: ultimo della coda.

## 5. Handler

- Una classe `final readonly` per rotta, che implementa `RequestHandlerInterface` e riceve le dipendenze nel costruttore.
- Compito dell'handler: estrarre e validare l'input (attributi di rotta, query, corpo), convertirlo in tipi del dominio, chiamare il servizio, tradurre il risultato in risposta. Nessuna logica di business.
- Errori di validazione → risposta 4xx dall'handler; eccezioni impreviste → lasciale salire al middleware degli errori.
- Le risposte si creano con `ResponseFactoryInterface` + `StreamFactoryInterface` (nello scheletro incapsulate in `JsonResponder`).
- Mai `echo`, `header()` o `exit` in un handler: rompono la pipeline e impediscono ai middleware di agire sulla risposta.

## 6. Differenze rispetto al tutorial di Kevin Smith (2018)

Il tutorial *Modern PHP Without a Framework* resta valido nei concetti; lo scheletro corregge:
- **Response iniettata dal container** → factory PSR-17. PHP-DI condivide l'istanza e lo stream del corpo è mutabile.
- **`narrowspark/http-emitter`** (archiviato il 1° marzo 2023) → `laminas/laminas-httphandlerrunner`.
- **Autowiring disattivato** → attivo (lo stesso autore ha poi cambiato posizione).
- **Nessuna gestione degli errori** → error handler nel punto di ingresso + middleware dedicato.
- **Codice PHP 7.2** (proprietà non tipizzate, assegnazioni manuali) → classi `final readonly`, promozione nel costruttore, tipi ovunque.
- **Definizioni e rotte nel front controller** → file separati in `config/`, configurazione tipizzata in `Settings`.
- **Nessun test né analisi statica** → PHPUnit, PHPStan livello max, PHP-CS-Fixer configurati.

## 7. Runtime persistenti e streaming

- Con FrankenPHP (modalità worker), RoadRunner o Swoole il container sopravvive tra le richieste: servizi senza stato di richiesta (`dependency-injection.md` §6), nessuna lettura di superglobali fuori dalla creazione della richiesta.
- Download grandi o risposte in streaming: `SapiStreamEmitter` invece di `SapiEmitter`, oppure `EmitterStack` che sceglie l'emitter in base alla risposta (es. presenza di `Content-Range` o `Content-Disposition`).

## 8. Quando passare a un framework

Lo stack PSR è adatto a servizi piccoli, API mirate, strumenti interni e per capire cosa fa un framework. Quando servono sessioni, autenticazione, validazione strutturata, ORM, migrazioni, code, scheduler, mail e internazionalizzazione, riassemblarli a mano costa più di quanto rende: usa Laravel o Symfony, oppure un micro-framework basato su PSR (Slim 4, Mezzio) in cui handler e middleware dello scheletro si portano quasi invariati.
