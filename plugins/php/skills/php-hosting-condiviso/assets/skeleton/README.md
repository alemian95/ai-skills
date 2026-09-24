# Scheletro PHP 8.4 per hosting condiviso

Sito PHP moderno che funziona senza SSH, senza cambiare la document root e senza strumenti sul server: si costruisce in locale e si carica via FTP. Stack PSR (PSR-7/11/15/17/20) con Twig, Doctrine DBAL e sessioni `mezzio-session`.

## Struttura

```
document root/            ← coincide con la radice del progetto
├── index.php             unico file PHP pubblico (sintassi compatibile con qualsiasi PHP)
├── .htaccess             routing + blocchi di sicurezza a più livelli
├── .user.ini             impostazioni PHP (PHP-FPM/CGI)
├── assets/               file statici; .htaccess impedisce l'esecuzione di script
└── app/                  tutto il resto — bloccata da app/.htaccess e dalla regola in .htaccess
    ├── bootstrap.php     ambiente PHP, errori → eccezioni, container
    ├── web.php           pipeline HTTP
    ├── bin/              migrate.php (cron), build.php, check-exposure.php, dev-router.php
    ├── config/           settings.php (predefiniti), settings.local.php (non versionato), container, rotte
    ├── migrations/       migrazioni numerate
    ├── src/  templates/  tests/
    └── var/              scrivibile: SQLite, log, sessioni, cache Twig
```

Se l'hosting permette di caricare file sopra la document root, sposta `app/` lì. Poi cambia `$appDir` in `index.php` e imposta `publicDir` in `settings.local.php`: è la configurazione più sicura.

## Sviluppo locale

```bash
cd app
cp config/settings.local.php.dist config/settings.local.php   # imposta debug/https per localhost
composer install
composer migrate
composer serve        # http://localhost:8080 (router che imita .htaccess)
composer check        # stile + PHPStan max + test
```

In `composer.json` la voce `config.platform.php` deve corrispondere alla versione PHP del server. Serve a far scegliere a Composer dipendenze compatibili con il server, non con il tuo computer.

## Deploy via FTP

1. `cd app && composer build`: crea `build/` con dipendenze senza dev e autoload ottimizzato.
2. Carica il contenuto di `build/` nella document root, **senza** eliminare file remoti. `app/var/` e `app/config/settings.local.php` sul server non vanno toccati.
3. Solo al primo deploy: crea `app/config/settings.local.php` sul server con i dati del database e verifica che `app/var/` sia scrivibile.
4. Migrazioni: un cron dal pannello, ad esempio ogni 5 minuti, con il percorso completo del PHP 8.4:
   `/percorso/php84 /home/utente/public_html/app/bin/migrate.php`
   Lo script è idempotente e silenzioso quando non c'è nulla da applicare.
5. Dal tuo computer: `php app/bin/check-exposure.php https://www.example.com` deve riportare "Nessun file privato esposto".

## Aggiungere una pagina

1. Handler `final readonly` che implementa `RequestHandlerInterface` e usa `View::render()`.
2. Rotta in `config/routes.php`.
3. Template in `templates/` che estende `layout.html.twig`. Per i link usa `path()`, per gli asset `asset()`, nei form con metodo POST `csrf_field()`.

## Sottocartella

Se il sito sta in `example.com/sito/`, link e asset si adattano da soli (`BasePathMiddleware`). In `.htaccess` aggiorna le righe marcate `[SOTTOCARTELLA]`, cioè `RedirectMatch 404 ^/sito/app(/|$)` e `FallbackResource /sito/index.php`.
