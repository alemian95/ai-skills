# Configurazione di PHP senza accesso al server

## Indice
1. FPM/CGI o mod_php?
2. Tre livelli di configurazione
3. Cosa si può cambiare e dove
4. Versione PHP: sito, cron e Composer
5. Funzioni disabilitate e limiti

## 1. FPM/CGI o mod_php?

Il meccanismo di configurazione dipende da come l'hosting esegue PHP:

| SAPI (`php_sapi_name()`) | `.user.ini` | `php_value` in `.htaccess` |
|---|---|---|
| `fpm-fcgi`, `cgi-fcgi` (la grande maggioranza degli hosting attuali, anche LiteSpeed con LSAPI → `litespeed`) | letto | **errore 500** |
| `apache2handler` (mod_php, ormai raro) | ignorato | letto |

Per scoprirlo: crea un file temporaneo con un nome non indovinabile, ad esempio `sapi-8f3k2.php`, che stampa `php_sapi_name()`. Aprilo nel browser e **cancellalo subito**. Evita `phpinfo()` completo nei file pubblici: espone percorsi, versioni e variabili d'ambiente.

LiteSpeed con LSAPI (`litespeed`) legge sia `.user.ini` sia `php_value`: usa comunque `.user.ini` per coerenza.

## 2. Tre livelli di configurazione

1. **Pannello (php.ini o selettore delle opzioni)**: quando esiste, è il livello più affidabile. Spesso permette solo un sottoinsieme di direttive.
2. **`.user.ini` nella document root**: vale per gli script eseguiti in quella cartella e nelle sottocartelle. Viene riletto ogni `user_ini.cache_ttl` secondi (predefinito 300), quindi le modifiche non sono immediate.
3. **`ini_set()` a runtime** in `bootstrap.php`: funziona per le direttive `PHP_INI_ALL` ovunque e indipendentemente dalla SAPI. Lo scheletro imposta qui ciò che conta per la sicurezza (`display_errors`, `error_log`, tutte le `session.*`), così non dipende dai primi due livelli.

Lo scheletro usa tutti e tre: `.user.ini` per i valori che servono prima dell'esecuzione dello script (limiti di upload, `display_startup_errors`), `ini_set()` per il resto.

## 3. Cosa si può cambiare e dove

| Direttiva | Livello | Note |
|---|---|---|
| `display_errors`, `log_errors`, `error_log`, `error_reporting` | ALL | anche con `ini_set` |
| `session.*` (save_path, cookie_*, gc_*, use_strict_mode) | ALL | prima di avviare la sessione |
| `date.timezone` | ALL | lo scheletro usa `date_default_timezone_set()` |
| `memory_limit`, `max_execution_time` | ALL | l'hosting può imporre un tetto più basso |
| `upload_max_filesize`, `post_max_size` | PERDIR | solo `.user.ini`/pannello: servono prima che lo script parta |
| `max_input_vars` | PERDIR | form molto grandi |
| `expose_php`, `opcache.*` (quasi tutte), `disable_functions`, `open_basedir`, `allow_url_include` | SYSTEM | non modificabili: se servono, chiedi all'hosting |

Per controllare il valore effettivo usa `ini_get()`. Per sapere se un valore è modificabile, `ini_get_all(null, false)` restituisce i valori, mentre `ini_get_all()` riporta anche il livello di accesso. Se `ini_set()` restituisce `false`, l'impostazione è bloccata: lo scheletro lancia un'eccezione per le sessioni invece di proseguire con valori insicuri.

## 4. Versione PHP: sito, cron e Composer

Tre punti devono corrispondere:
1. **La versione del sito**, scelta nel pannello. Talvolta si imposta per cartella, e il pannello scrive in `.htaccess` un blocco da non cancellare.
2. **Il PHP del cron**: il comando `php` del cron è spesso la versione predefinita del sistema, non quella del sito. Usa il percorso completo indicato dal pannello, ad esempio `/usr/local/bin/php84`, `/opt/cpanel/ea-php84/root/usr/bin/php` o `/usr/bin/php8.4`. Con una versione sbagliata l'autoloader di Composer si ferma con un messaggio che arriva nella mail del cron.
3. **`config.platform.php` in `composer.json`**: la versione con cui Composer risolve le dipendenze in locale. Deve essere uguale o inferiore a quella del server, altrimenti Composer può installare pacchetti che sul server non funzionano.

Quando cambi versione PHP dal pannello: aggiorna `config.platform.php`, rigenera la build, carica tutta la cartella `vendor/`.

**Estensioni**: `composer.json` dichiara quelle necessarie (`ext-mbstring`, `ext-pdo`, `ext-session`, …). Se nel server ne manca una, `platform_check.php` di Composer lo segnala. Verifica anche che sia attivo il driver PDO giusto (`pdo_mysql` o `pdo_sqlite`): di solito si abilita dal selettore delle estensioni del pannello.

## 5. Funzioni disabilitate e limiti

Molti hosting disabilitano tramite `disable_functions`:
- `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`: il codice che gira sul server non deve dipenderne. Gli script di build usano `proc_open`, ma solo in locale.
- `mail()` a volte è attivo ma con limiti o senza SPF/DKIM: invia le email via SMTP (vedi `sessioni-autenticazione.md`).
- `symlink`, `set_time_limit`, `ini_set` (raramente).

Verifica con `function_exists('nome')`: dalla PHP 8.0 le funzioni disabilitate risultano inesistenti.

Altri limiti tipici:
- **Tempo di esecuzione** 30–60 s e memoria 128–256 MB: le operazioni lunghe vanno suddivise in lotti eseguiti dal cron.
- **`open_basedir`**: limita i file accessibili alla home dell'utente, e a volte alla sola document root. In quel caso la struttura A non funziona: il sito risponde con errori "open_basedir restriction in effect".
- **Processi e inode**: evita di generare migliaia di file piccoli, per esempio cache senza pulizia o sessioni senza garbage collection.
