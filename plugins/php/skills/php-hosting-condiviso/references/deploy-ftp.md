# Build locale e deploy via FTP

## Indice
1. Principio
2. Build
3. Primo deploy
4. Aggiornamenti
5. Cosa non caricare mai
6. Permessi
7. Protocollo e strumenti
8. Rollback

## 1. Principio

Sul server non si esegue nulla: niente `composer install`, niente build degli asset, niente comandi. Il server riceve una copia già pronta, costruita in locale in modo riproducibile:
- `composer.lock` versionato;
- `config.platform.php` uguale alla versione del server;
- stessa build per ogni ambiente, con i soli valori di configurazione diversi, in `settings.local.php`.

## 2. Build

```bash
cd app
composer check          # stile, analisi statica, test: non si pubblica codice che non li supera
composer build          # → ../build/
```

`bin/build.php`:
- copia il progetto in `build/` escludendo `vendor/`, test, configurazioni degli strumenti di sviluppo, `settings.local.php` e il contenuto di `app/var/` (restano solo le cartelle con i loro `.gitignore`);
- esegue `composer install --no-dev --classmap-authoritative` dentro `build/app`, con gli argomenti passati come array a `proc_open` (senza shell).

Se il progetto ha asset da compilare (CSS/JS con Vite, esbuild…), aggiungi quella fase prima della copia e metti solo i file compilati in `assets/`.

## 3. Primo deploy

1. Nel pannello:
   - scegli la versione PHP 8.4 per il dominio;
   - abilita le estensioni necessarie (`pdo_mysql` o `pdo_sqlite`, `mbstring`, `intl` se usata);
   - crea database e utente MySQL, se servono.
2. Carica il contenuto di `build/` nella document root. Controlla che siano arrivati anche i file nascosti (`.htaccess`, `.user.ini`, `app/.htaccess`, `assets/.htaccess`): alcuni client FTP li nascondono.
3. Crea sul server `app/config/settings.local.php` partendo da `settings.local.php.dist`: dati del database, `debug => false`. Crealo direttamente sul server con il file manager del pannello, o caricalo e poi cancellalo dal computer: non deve finire nel repository.
4. Verifica che `app/var/` e le sue sottocartelle siano scrivibili da PHP (vedi §6).
5. Configura il cron delle migrazioni (vedi `database-cron.md`) e attendi la prima esecuzione, oppure importa lo schema da phpMyAdmin.
6. Dal tuo computer: `php app/bin/check-exposure.php https://www.example.com`.
7. Apri il sito e controlla il log degli errori del pannello e `app/var/log/`.

## 4. Aggiornamenti

1. `composer build`.
2. Carica `build/` **sovrascrivendo** i file esistenti ma **senza eliminare** quelli remoti mancanti. Le eccezioni, che non devono mai essere sovrascritte, sono `app/var/` e `app/config/settings.local.php`, perché non stanno nella build.
3. Se `composer.lock` è cambiato, carica per intero `app/vendor/`. Un `vendor/` caricato a metà rompe l'autoload: durante il caricamento il sito può dare errori per qualche decina di secondi. Per ridurre il disservizio vedi §8.
4. Le migrazioni nuove vengono applicate dal cron alla successiva esecuzione.
5. File eliminati dal progetto: cancellali a mano anche dal server, oppure usa una sincronizzazione con eliminazione che escluda `app/var/` e `settings.local.php`.
6. `check-exposure.php` se hai toccato `.htaccess` o la struttura.

La cache di Twig si aggiorna da sola (`auto_reload`). OPcache sugli hosting condivisi controlla normalmente la data dei file; se un file modificato non viene ricaricato, la causa è `opcache.validate_timestamps=0` impostato dall'hosting: chiedi come si svuota, oppure riavvia PHP dal pannello se c'è l'opzione.

## 5. Cosa non caricare mai

- `.git/`, `.idea/`, `.vscode/`, `node_modules/`, `.DS_Store`;
- `app/tests/`, configurazioni di PHPUnit, PHPStan e PHP-CS-Fixer;
- file `.env`, dump del database, backup (`*.sql`, `*.zip`, `*.bak`, `*~`) nella document root: sono tra i primi file cercati dagli scanner automatici;
- `settings.local.php` di sviluppo al posto di quello di produzione;
- `phpinfo()` o script di diagnostica lasciati sul server.

## 6. Permessi

Sugli hosting condivisi PHP gira normalmente con l'utente del proprietario dei file (PHP-FPM per utente, suPHP, LSAPI). Di conseguenza:
- file `0644`, cartelle `0755`: PHP può scrivere nelle cartelle perché ne è il proprietario;
- `settings.local.php` `0600` o `0640` se l'hosting lo permette;
- **mai `0777`**: su un server condiviso significa scrivibile da altri utenti, e alcuni hosting rifiutano di eseguire script in cartelle scrivibili da tutti (errore 500).

Se PHP non riesce a scrivere in `app/var/` pur con `0755`, l'hosting esegue PHP con un utente diverso dal proprietario: chiedi all'assistenza come gestire le cartelle scrivibili invece di usare `0777`.

## 7. Protocollo e strumenti

- Usa **SFTP** o **FTPS** (FTP su TLS). L'FTP in chiaro trasmette credenziali e file senza cifratura.
- Client grafici: FileZilla, Cyberduck, Transmit. Imposta la visualizzazione dei file nascosti e il confronto per data e dimensione.
- Da riga di comando, ripetibile, con `lftp`:
  ```bash
  lftp -u utente sftp://ftp.example.com -e "mirror --reverse --only-newer --verbose \
      --exclude-glob app/var/** --exclude-glob app/config/settings.local.php \
      build/ public_html/; quit"
  ```
  Aggiungi `--delete` solo dopo aver verificato le esclusioni con `--dry-run`.
- Alcuni pannelli (cPanel "Git Version Control", Plesk Git) possono fare deploy da un repository. Funziona solo se `vendor/` è nel repository di deploy, perché sul server Composer non gira. Tieni un repository o un branch separato con le build, non il codice sorgente con `vendor/`.

## 8. Rollback

- Conserva le build precedenti in locale (`build-AAAAMMGG/`): tornare indietro significa ricaricare quella precedente.
- Le migrazioni non si annullano da sole: una modifica allo schema incompatibile con la versione precedente del codice richiede una migrazione correttiva. Progetta le migrazioni in modo che il codice vecchio continui a funzionare: prima aggiungi colonne e tabelle, rimuovi solo in un deploy successivo.
- Per aggiornamenti con modifiche consistenti a `vendor/` si può ridurre il disservizio così:
  1. carica la nuova versione in `app-new/`;
  2. rinomina via FTP `app/` in `app-old/` e `app-new/` in `app/`. La rinomina è quasi istantanea.
  3. copia in `app/` le cartelle `var/` e `config/settings.local.php` da `app-old/`, oppure, meglio, prevedi `varDir` fuori da `app/` se la struttura A lo permette.
