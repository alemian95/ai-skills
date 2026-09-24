# Scheletro PHP 8.4 senza framework

Applicazione HTTP minima composta da componenti PSR interoperabili. Verificata con PHP 8.4.12, PHPUnit 13.3, PHPStan 2.2 (livello max) e PHP-CS-Fixer 3.95 (PER-CS 3.0).

## Avvio

```bash
composer install
composer serve            # http://localhost:8080/health
composer check            # stile + analisi statica + test
```

Variabili d'ambiente: `APP_DEBUG` (booleano, predefinito `false`), `APP_LOG_LEVEL` (nome livello PSR-3, predefinito `warning`, `debug` se `APP_DEBUG=true`), `APP_LOG_STREAM` (predefinito `php://stderr`).

## Flusso di una richiesta

```
public/index.php
  └─ error handler → ErrorException
  └─ config/bootstrap.php → container PHP-DI (definizioni in config/container.php)
  └─ Relay (PSR-15)
       1. ErrorHandlerMiddleware   Throwable non gestiti → 500 JSON + log
       2. FastRoute                404 / 405, attributi di rotta sulla richiesta
       3. RequestHandler           risolve l'handler dal container e lo esegue
  └─ SapiEmitter (laminas-httphandlerrunner)
```

## Struttura

| Percorso | Ruolo |
|---|---|
| `public/` | unica cartella esposta dal web server (document root) |
| `config/` | composition root: container, rotte. Nessuna logica applicativa |
| `src/Config/` | configurazione tipizzata e immutabile |
| `src/Greeting/` | dominio: nessuna dipendenza da HTTP o dal container |
| `src/Http/` | adattatori HTTP: handler, middleware, risposte |
| `tests/` | unitari sul dominio, integrazione sull'intera pipeline |

## Aggiungere una rotta

1. Crea un handler `final readonly` che implementa `RequestHandlerInterface` in `src/Http/Handler/`.
2. Registralo in `config/routes.php`.
3. Le dipendenze concrete sono autocablate; lega le nuove interfacce in `config/container.php`.

## Quando smettere di estendere lo scheletro

Se servono sessioni, autenticazione, validazione strutturata, code, ORM e migrazioni, non ricostruirli a mano: passa a un framework (Laravel, Symfony) o a un micro-framework PSR (Slim 4, Mezzio), dove questo codice si porta quasi senza modifiche.
