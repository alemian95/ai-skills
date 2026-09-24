# Struttura delle cartelle e `.htaccess`

## Indice
1. Perché i framework usano `public/`
2. Due strutture possibili
3. File o rotta: chi decide
4. `.htaccess` principale, blocco per blocco
5. `app/.htaccess` e `assets/.htaccess`
6. Web server: Apache, LiteSpeed, nginx
7. Sito in una sottocartella
8. Routing senza mod_rewrite
9. Errori 500 dopo una modifica

## 1. Perché i framework usano `public/`

Il web server può servire solo ciò che sta nella document root. Se la document root è `public/`, codice, configurazione, `vendor/`, `.env`, log e database sono fisicamente fuori dalla sua portata: nessuna regola li protegge, semplicemente non esiste un URL che li raggiunga. Questo resiste ai guasti che rendono inutili le regole:
- `.htaccess` ignorato (`AllowOverride None`, passaggio a nginx, file cancellato durante un deploy);
- gestore PHP rotto (cambio di versione dal pannello, configurazione errata): il server consegna i `.php` come testo. Con `public/` l'unico sorgente esposto è `index.php`. Senza, esce tutto, compresa la configurazione in file `.php`, che protegge solo quando falliscono le regole di accesso ma PHP funziona;
- `vendor/` raggiungibile: contiene file eseguibili non pensati per il web. Il caso più noto è `phpunit/src/Util/PHP/eval-stdin.php` (CVE-2017-9841), ancora cercato in massa dagli scanner;
- file dimenticati: `.env`, dump SQL, `.git/`, backup.

Con il front controller tutte le richieste passano da `index.php`, quindi nella document root non serve altro che quel file e gli asset. La struttura A qui sotto è lo stesso modello; la B lo imita con whitelist e blocchi, e per questo va sempre verificata. Su cPanel e Plesk la document root del dominio principale spesso è fissa, ma quella di **sottodomini e domini aggiuntivi di solito si può scegliere**: se il sito può stare su uno di questi, puntala su una cartella tipo `.../sito/public` e ottieni la struttura dei framework senza compromessi.

## 2. Due strutture possibili

**A — `app/` sopra la document root** (preferita quando l'hosting lo permette via FTP):
```
/home/utente/
├── app/                 codice, vendor, configurazione, dati: fuori dalla portata del web server
└── public_html/         document root
    ├── index.php        $appDir = dirname(__DIR__) . '/app';
    ├── .htaccess
    ├── .user.ini
    └── assets/
```
Con questa struttura imposta `publicDir` in `settings.local.php` con il percorso di `public_html`, e sposta la regola `RedirectMatch 404 ^/app` perché non serve più (è innocua se resta).

**B — tutto nella document root** (quando non si può uscire da `public_html`, o non lo si sa):
```
public_html/
├── index.php            $appDir = __DIR__ . '/app';
├── .htaccess            blocca app/ (seconda linea)
├── .user.ini
├── assets/
└── app/
    └── .htaccess        Require all denied (prima linea)
```
In B la sicurezza dipende dal web server che legge `.htaccess`: verificala sempre con `check-exposure.php`.

Come capire se A è possibile: col client FTP, dopo il login, sali di un livello rispetto a `public_html` (o `httpdocs`, `www`, `htdocs`). Se vedi la home dell'utente e puoi creare cartelle, A è possibile. Alcuni hosting fanno partire l'FTP direttamente dalla document root: in quel caso resta B.

## 3. File o rotta: chi decide

La distinzione tra `/assets/css/app.css` (file) e `/api/v1/users` (rotta) non la fa il router: la fa **Apache, prima di PHP**, in base a una sola cosa, cioè se sul disco esiste un file a quel percorso.

- `GET /assets/css/app.css` → Apache cerca `…/public_html/assets/css/app.css`, il file esiste e lo serve direttamente. PHP non viene eseguito.
- `GET /api/v1/users` → `…/public_html/api/v1/users` non esiste → `RewriteCond %{REQUEST_FILENAME} !-f` è vera → Apache esegue internamente `index.php`, senza redirect visibile al browser. L'URL originale resta in `REQUEST_URI`, Diactoros costruisce la richiesta, FastRoute la confronta con `config/routes.php` ed esegue l'handler oppure risponde 404.

Le rotte sono quindi **URL che non esistono come file**, voci della tabella di FastRoute. Ne seguono tre conseguenze:
- **se un file e una rotta hanno lo stesso percorso, vince il file**: nella document root devono stare solo `index.php` e `assets/`;
- **ogni file `.php` presente nella document root è eseguibile direttamente**, senza routing né middleware (niente CSRF, sessione, gestione errori): l'unico file PHP pubblico deve essere `index.php`;
- **i file dentro `app/` esistono**, e a impedirne la consegna sono solo le regole di accesso, valutate prima di servire il file. È il punto delicato della struttura B.

`FallbackResource` ha lo stesso comportamento: interviene solo se il file non esiste. In sviluppo `bin/dev-router.php` rifà a mano lo stesso controllo, perché il server integrato ignora `.htaccess`. La regola `RewriteRule ^assets/ - [L]` esclude dal passaggio a PHP tutti i percorsi sotto `assets/`: un asset mancante riceve il 404 di Apache senza avviare l'applicazione. Senza mod_rewrite, cioè con `FallbackResource`, lo riceve invece dall'applicazione.

## 4. `.htaccess` principale, blocco per blocco

```apache
Options -Indexes
DirectoryIndex index.php
AddDefaultCharset UTF-8
```
Niente elenco dei file nelle cartelle senza index; `index.php` come pagina predefinita.

```apache
<FilesMatch "^\.">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>
```
Blocca i file il cui nome inizia con un punto: `.htaccess`, `.user.ini`, `.env`, `.gitignore`. La doppia sintassi copre Apache 2.4 (`mod_authz_core`) e i server che emulano la 2.2.

```apache
<IfModule mod_alias.c>
    RedirectMatch 404 ^/app(/|$)
    RedirectMatch 404 /\.(?!well-known/)
</IfModule>
```
- La prima regola è la seconda linea di difesa per `app/`: se `app/.htaccess` venisse cancellato per errore durante un deploy, la cartella resterebbe comunque inaccessibile. Risponde 404 anziché 403 per non confermare l'esistenza della cartella.
- La seconda blocca qualsiasi percorso con un segmento nascosto, come `/.git/config`, che `FilesMatch` non intercetta perché lì il nome del file è `config`. Fa eccezione `/.well-known/`, che serve ai certificati Let's Encrypt e ad altri standard.

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^app(/|$) - [R=404,L]
    RewriteRule (^|/)\.(?!well-known/) - [R=404,L]
    RewriteRule ^assets/ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L,QSA]
</IfModule>
<IfModule !mod_rewrite.c>
    FallbackResource /index.php
</IfModule>
```
Le prime due regole ripetono i blocchi di `mod_alias`, così basta che sia attivo uno dei due moduli. Il resto manda al front controller, che fa il routing, ogni richiesta che non corrisponde a un file esistente.

Verificato su Apache 2.4 disattivando i moduli uno alla volta:

| Moduli attivi | `/.git/config` (cartella reale) | `app/` | Routing |
|---|---|---|---|
| alias + rewrite | 404 | 403 | ok |
| solo rewrite | 404 | 403 | ok |
| solo alias | 404 | 403 | ok (FallbackResource) |
| nessuno dei due | **servito** | 403 | ok (FallbackResource) |

Senza nessuno dei due moduli, cosa quasi impossibile su un hosting reale, le cartelle nascoste restano esposte: `FilesMatch` blocca solo i nomi di file che iniziano con un punto. È un motivo in più per non caricare mai `.git/` sul server (la build la esclude). Per un percorso inesistente come `/.git/config` senza la cartella, Apache risponde comunque 403: considera `.git` il nome del file e gli applica `FilesMatch`. Non serve la condizione `!-d`: le cartelle esistenti senza `index` finirebbero comunque in 403, ed è meglio che l'applicazione risponda 404.

**Cosa non mettere**: `php_value`/`php_flag` (errore 500 con PHP-FPM, vedi `configurazione-php.md`), `Options +FollowSymLinks` (spesso vietato), `<Directory>` (non è ammesso nei `.htaccess`).

**Blocchi aggiunti dal pannello**: cPanel e altri inseriscono sezioni come `# php -- BEGIN cPanel-generated handler` per scegliere la versione di PHP. Non cancellarle quando sostituisci il file: copiale in cima al nuovo `.htaccess`.

## 5. `app/.htaccess` e `assets/.htaccess`

- `app/.htaccess` contiene solo `Require all denied` (con la variante 2.2). È la protezione principale di tutta la cartella e delle sottocartelle.
- `assets/.htaccess` nega i file eseguibili (`.php`, `.phtml`, `.phar`, `.cgi`…). Se per errore o per un upload un file PHP finisse in `assets/`, non verrebbe eseguito. Contiene anche la cache lunga per gli asset: gli URL generati da `asset()` hanno `?v=<data modifica>`, quindi il browser scarica la nuova versione dopo ogni caricamento.
- Qualsiasi cartella in cui l'applicazione salva file caricati dagli utenti va in `app/var/` e i file si servono tramite un handler, oppure va dentro `assets/` protetta allo stesso modo. Mai una cartella di upload in cui PHP possa essere eseguito.

## 6. Web server: Apache, LiteSpeed, nginx

| Server | `.htaccess` | Note |
|---|---|---|
| Apache 2.4 | letto se `AllowOverride` lo permette (sugli hosting di solito `All`) | riferimento per lo scheletro |
| LiteSpeed Enterprise | compatibile con le direttive usate (`RewriteRule`, `Require`, `FilesMatch`, `RedirectMatch`) | molto diffuso negli hosting condivisi; le modifiche a `.htaccess` a volte si applicano dopo qualche secondo |
| OpenLiteSpeed | supporto parziale, rewrite ricaricate solo al riavvio | raro negli hosting condivisi |
| nginx (senza Apache) | **ignorato** | serve la struttura A oppure regole configurate dall'hosting; senza nessuna delle due il sito non è sicuro |
| nginx + Apache (proxy) | letto da Apache | configurazione comune: nginx serve i file statici, Apache esegue PHP |

In ogni caso, l'esito si verifica con `check-exposure.php`, non si deduce dalla documentazione dell'hosting.

## 7. Sito in una sottocartella

Con il sito in `example.com/sito/`:
- l'applicazione si adatta da sola: `BasePathMiddleware` ricava il prefisso da `SCRIPT_NAME` e lo toglie prima del routing; `path()` e `asset()` lo aggiungono ai link;
- in `.htaccess` aggiorna `RedirectMatch 404 ^/sito/app(/|$)` e `FallbackResource /sito/index.php`;
- `RewriteRule ^ index.php` funziona senza modifiche, perché nei `.htaccess` la sostituzione relativa si risolve rispetto alla cartella;
- lancia `check-exposure.php https://example.com/sito`.

## 8. Routing senza mod_rewrite

In ordine di preferenza:
1. `mod_rewrite` (quasi sempre presente);
2. `FallbackResource` (mod_dir, Apache ≥ 2.2.16): stesso risultato con una sola riga;
3. URL con PATH_INFO: `/index.php/contatti`. `BasePathMiddleware` li riconosce già; i link vanno generati con il prefisso `/index.php` (configurazione da aggiungere solo se davvero necessaria);
4. parametro in query string (`/?r=/contatti`): ultima risorsa, peggiora URL e SEO.

## 9. Errori 500 dopo una modifica

Un 500 su tutto il sito subito dopo aver caricato un `.htaccess` indica quasi sempre una direttiva non permessa dall'hosting. Procedi così:
1. controlla il log degli errori del pannello: riporta il nome della direttiva rifiutata;
2. commenta i blocchi uno alla volta (prima `Options`, poi eventuali `php_value`, poi `mod_expires`);
3. ricarica e riprova.

Tieni sempre una copia del `.htaccess` funzionante prima di modificarlo.
