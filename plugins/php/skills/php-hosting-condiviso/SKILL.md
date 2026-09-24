---
name: "php-hosting-condiviso"
description: Progettare, sviluppare e pubblicare siti e applicazioni PHP moderni (8.4+) su hosting condiviso senza SSH e senza poter cambiare la document root — struttura delle cartelle sicura, .htaccess a più livelli, routing con front controller, impostazioni PHP via .user.ini, build locale e deploy via FTP, migrazioni tramite cron, sessioni e CSRF, verifica dell'esposizione dei file. Usala ogni volta che il codice PHP deve girare su hosting condiviso, cPanel, Plesk, Aruba, SiteGround, Register o simili, quando l'utente cita FTP, public_html, .htaccess o l'assenza di SSH, o quando rivedi un template o un sito PHP destinato a questo tipo di hosting. Use for any PHP project deployed to shared hosting without shell access.
---

# PHP moderno su hosting condiviso

Per tutte le regole sul codice (tipi, sicurezza, eccezioni, DI, strumenti) vale la skill `php-moderno`: leggila se è disponibile. Questa skill aggiunge solo ciò che cambia quando il server è un hosting condiviso: **niente shell, document root fissa, configurazione PHP limitata, deploy via FTP**.

## Vincoli da cui partire

- **Nessuna CLI sul server** tranne, spesso, il **cron del pannello**. Composer, migrazioni e build si eseguono in locale o tramite cron, mai con SSH.
- **La document root coincide con la radice del progetto**, a meno che l'utente confermi di poter caricare file sopra di essa. Tutto ciò che sta nella document root è potenzialmente raggiungibile dal web.
- **Configurazione PHP parziale**: `.user.ini` solo con PHP-FPM/CGI, `php_value` in `.htaccess` solo con mod_php (con FPM produce un errore 500), direttive `PHP_INI_SYSTEM` non modificabili. Parte delle impostazioni si applica a runtime con `ini_set()`.
- **Apache o LiteSpeed con `.htaccess`** nella quasi totalità dei casi. Se l'hosting usa solo nginx, `.htaccess` viene ignorato: l'unica struttura sicura è quella con `app/` sopra la document root.
- **Versione PHP selezionabile dal pannello**, ma il `php` usato dal cron può essere diverso da quello del sito.

## Regole vincolanti

1. **Whitelist, non blacklist.** Nella document root solo `index.php`, `.htaccess`, `.user.ini` e `assets/`. Tutto il resto (codice, `vendor/`, configurazione, template, database, log, sessioni) sta in `app/`, bloccata da `app/.htaccess` (`Require all denied`) e da una seconda regola nel `.htaccess` principale. Elencare le cartelle da bloccare è l'errore tipico: basta dimenticarne una, e di solito è quella con il database o i segreti.
2. **Se l'hosting lo permette, `app/` va sopra la document root.** È l'unica protezione che non dipende dal web server. La struttura dello scheletro lo consente cambiando una riga.
3. **Configurazione e segreti in file `.php` che restituiscono array**, mai YAML, `.env`, JSON o INI dentro la document root. Se una protezione fallisse, il server eseguirebbe il file PHP invece di mostrarlo. Il file locale (`settings.local.php`) non si versiona e sul server si crea una volta sola.
4. **Un solo front controller** (`index.php`) con sintassi compatibile con qualsiasi PHP. Carica per primo l'autoloader di Composer, il cui `platform_check.php` segnala in modo chiaro una versione di PHP troppo vecchia. Il codice 8.4 si carica solo dopo.
5. **`vendor/` si costruisce in locale** con `config.platform.php` uguale alla versione del server, `--no-dev` e `--classmap-authoritative`. `composer.lock` si versiona.
6. **Il deploy non tocca i dati del server**: `app/var/` e `app/config/settings.local.php` sono esclusi dalla build e dalla sincronizzazione (niente "elimina file remoti" senza esclusioni).
7. **Migrazioni idempotenti con lock**, eseguibili dal cron (`bin/migrate.php`, silenzioso quando non c'è nulla da fare). Nessun endpoint web di manutenzione: è superficie d'attacco in più. In alternativa: SQL generato in locale e importato da phpMyAdmin.
8. **Sessioni in `app/var/sessions`** con cookie `Secure`, `HttpOnly`, `SameSite=Lax`, `use_strict_mode` e garbage collection abilitata. La cartella predefinita può essere condivisa con altri clienti dell'hosting.
9. **Twig con `autoescape: 'html'`** e `auto_reload: true` (la cache non si può svuotare a ogni deploy). Dettagli degli errori solo con `debug` attivo, mai `$e->getMessage()` verso l'utente.
10. **CSRF su ogni metodo che modifica stato**, token per sessione confrontato con `hash_equals`, verificato dopo il routing (così 404/405 restano corretti).
11. **URL indipendenti dalla cartella di installazione**: link e asset generati tramite il base path (`path()`, `asset()`), mai percorsi relativi come `href="assets/…"`, che si rompono con le rotte annidate.
12. **Nessuna dipendenza da funzioni spesso disabilitate** (`exec`, `shell_exec`, `proc_open`, `mail` configurato, `symlink`) nel codice che gira sul server. Per le email usa SMTP con credenziali dal pannello.
13. **Verifica dopo ogni deploy** con `bin/check-exposure.php <url>` dal proprio computer: ogni percorso privato deve rispondere 403 o 404.

## Flusso di lavoro

**Nuovo progetto** → copia `assets/skeleton/` e segui il suo `README.md`. Prima di scrivere codice chiedi all'utente, se non è già chiaro:
- se può caricare file sopra la document root;
- quale database offre l'hosting (MySQL/MariaDB o solo SQLite);
- la versione di PHP selezionabile;
- se il sito va in una sottocartella;
- se il pannello offre il cron.

**Revisione di un progetto esistente** → in quest'ordine:
1. **Esposizione dei file**: elenca il contenuto della document root e verifica, file per file, cosa è raggiungibile. Se possibile, prova davvero: Apache locale, oppure `check-exposure.php` sul sito pubblicato.
2. **Segreti**: dove stanno, in che formato, se sono versionati.
3. **Deploy**: come arriva `vendor/`, quale versione di PHP è bloccata, cosa viene sovrascritto sul server.
4. **Configurazione PHP**: errori mostrati, sessioni, eventuale `php_value` con FPM.
5. **Codice**, secondo la skill `php-moderno`.

**Modifiche a `.htaccess`** → dopo ogni modifica verifica sia il routing sia i blocchi (`check-exposure.php`); una direttiva non permessa dall'hosting produce un 500 su tutto il sito.

## Riferimenti

| File | Quando leggerlo |
|---|---|
| `references/struttura-htaccess.md` | perché i framework usano `public/`, struttura delle cartelle, come Apache distingue un file da una rotta, `.htaccess` riga per riga, Apache/LiteSpeed/nginx, sottocartelle, routing senza mod_rewrite, errori 500 |
| `references/configurazione-php.md` | capire se l'hosting usa FPM o mod_php, `.user.ini`, `ini_set`, direttive modificabili, versione PHP del cron, funzioni disabilitate |
| `references/deploy-ftp.md` | build locale, cosa caricare e cosa no, primo deploy, permessi, aggiornamenti, rollback, FTP vs SFTP |
| `references/database-cron.md` | SQLite vs MySQL, migrazioni via cron o phpMyAdmin, backup, lavori pianificati |
| `references/sessioni-autenticazione.md` | sessioni isolate, CSRF, login con password, upload di file, invio email |

## Scheletro

`assets/skeleton/` è un sito funzionante che contiene:
- front controller e `.htaccess` a più livelli;
- sessioni, messaggi flash e CSRF;
- Twig con escape automatico;
- DBAL con migrazioni portabili SQLite/MySQL;
- pagine d'errore HTML e intestazioni di sicurezza;
- supporto alle sottocartelle;
- script `build`, `migrate` e `check-exposure`.

È verificato con PHPUnit 13, PHPStan al livello massimo, PHP-CS-Fixer PER-CS 3.0 e su Apache 2.4 con PHP-FPM 8.4 e MariaDB 10.11, anche senza mod_rewrite o senza mod_alias. Il form di contatto d'esempio mostra il flusso completo: form → validazione → CSRF → database → flash → redirect. Rimuovilo quando non serve più (`src/Contact/`, rotta, template, migrazione).
