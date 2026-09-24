# Database, migrazioni e cron

## Indice
1. SQLite o MySQL
2. Migrazioni
3. Eseguire le migrazioni senza SSH
4. Cron: regole pratiche
5. Backup

## 1. SQLite o MySQL

| | SQLite | MySQL/MariaDB |
|---|---|---|
| Configurazione | nessuna: un file in `app/var/` | database e utente dal pannello |
| Scritture concorrenti | una alla volta (lock sul file) | concorrenti |
| Adatto a | siti con poche scritture: vetrine, blog personali, piccoli form | e-commerce, aree riservate multiutente, scritture frequenti |
| Gestione | file scaricabile via FTP, da copiare per il backup | phpMyAdmin, esportazioni dal pannello |
| Rischio specifico | il file **deve** stare fuori dalla portata del web (`app/var/`) | credenziali in `settings.local.php` |

Con SQLite la cartella che contiene il file deve essere scrivibile, non solo il file: SQLite crea file temporanei di journal accanto al database. Non serve creare il file in anticipo (niente `touch` né `exec`): lo crea la prima connessione.

Su MySQL lo scheletro usa `charset=utf8mb4`. Le tabelle create da DBAL usano la codifica predefinita del database: crea il database in `utf8mb4` (in phpMyAdmin collation `utf8mb4_unicode_ci` o `utf8mb4_0900_ai_ci`).

L'ORM (Doctrine ORM) è utilizzabile, ma senza CLI la sua gestione dello schema (`orm:schema-tool`) è scomoda e rischiosa in produzione. Con le migrazioni esplicite di questo scheletro lo schema resta sotto controllo; DBAL offre query builder e portabilità, spesso sufficienti.

## 2. Migrazioni

- Un file per migrazione in `app/migrations/`, con nome `AAAAMMGG_NNNN_descrizione.php`, che restituisce una classe anonima con `up(Connection $db)`.
- Le versioni applicate sono registrate nella tabella `schema_migrations`. Ogni file viene applicato una sola volta, in ordine alfabetico, cioè cronologico.
- Solo in avanti: niente `down()`. In produzione un rollback dello schema si fa con una nuova migrazione, mai modificando un file già applicato.
- Compatibilità col codice precedente: aggiungi prima, rimuovi dopo (vedi `deploy-ftp.md` §8).
- Portabilità SQLite/MySQL: l'API di schema di DBAL (`Table::editor()`, `Column::editor()`) genera l'SQL giusto per entrambi. Se il progetto usa un solo database, SQL scritto a mano con `$db->executeStatement()` è più semplice e altrettanto valido.
- MySQL esegue un commit implicito sulle istruzioni DDL: una migrazione che fallisce a metà lascia modifiche parziali. Tieni le migrazioni piccole: una modifica di schema per file.
- Migrazioni di dati su tabelle grandi: a lotti, rispettando il tempo massimo di esecuzione del cron.

## 3. Eseguire le migrazioni senza SSH

**Cron (consigliato)** — nel pannello:
```
*/5 * * * *   /percorso/completo/php84 /home/utente/public_html/app/bin/migrate.php
```
- `migrate.php` è idempotente: senza migrazioni in sospeso non fa nulla e non stampa nulla, quindi il cron non genera email.
- Un lock su file (`app/var/migrate.lock`) impedisce esecuzioni sovrapposte.
- In caso di errore scrive su stderr ed esce con codice 1: il pannello invia l'output per email se è configurato un indirizzo.
- Dopo un deploy con migrazioni nuove, attendi l'esecuzione successiva prima di usare le funzioni che le richiedono, oppure imposta temporaneamente il cron a ogni minuto.
- `--status` mostra lo stato: utile come cron una tantum con output inviato via email.

**phpMyAdmin (alternativa, solo MySQL)** — genera l'SQL in locale contro un database vuoto con la stessa versione di MySQL/MariaDB del server, esportalo e importalo da phpMyAdmin. Registra a mano la versione in `schema_migrations`, altrimenti il cron la riapplicherà. È più soggetto a errori: usalo solo se il cron non è disponibile.

**Endpoint web di manutenzione** — sconsigliato: è una superficie d'attacco permanente, da proteggere con token, limitazione dei tentativi e registrazione degli accessi, e da ricordarsi di disattivare. Se è davvero l'unica strada, deve essere disattivato per impostazione predefinita, accettare solo POST con un token lungo confrontato tramite `hash_equals`, e va rimosso subito dopo l'uso.

## 4. Cron: regole pratiche

- **Percorso completo del PHP** della versione giusta (vedi `configurazione-php.md` §4) e percorso assoluto dello script.
- Lo script non deve dipendere dalla cartella corrente: lo scheletro usa `__DIR__`.
- Script cron in `app/bin/`, non raggiungibili dal web, con `if (PHP_SAPI !== 'cli') exit` come ulteriore difesa.
- Output solo in caso di problemi; log applicativo in `app/var/log/`.
- Lavori lunghi (invio newsletter, importazioni): elabora un lotto per esecuzione e salva l'avanzamento nel database; mai un unico script che supera il tempo massimo.
- Lock su file per ogni lavoro che non deve sovrapporsi (`flock` con `LOCK_EX | LOCK_NB`).
- Pulizia periodica: log vecchi (Monolog `RotatingFileHandler` li gestisce), file temporanei, record scaduti.

## 5. Backup

- Usa i backup del pannello, ma non affidarti solo a quelli: verifica che includano il database e prova almeno una volta a ripristinare.
- **SQLite**: copia `app/var/database.sqlite` via FTP quando il sito non sta scrivendo, oppure da un cron con `VACUUM INTO '/percorso/backup.sqlite'`, che produce una copia consistente anche a sito attivo.
- **MySQL**: esportazione da phpMyAdmin o dal pannello; `mysqldump` da cron solo se l'hosting lo mette a disposizione.
- I backup non stanno mai nella document root: una cartella `backup/` pubblica con file `.sql` è tra le prime cose cercate dagli scanner automatici.
