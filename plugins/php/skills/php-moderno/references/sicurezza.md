# Sicurezza

## Indice
1. Principi
2. Input: validazione al confine
3. Output: escape per contesto
4. SQL
5. Password, token, crittografia
6. File, upload, percorsi, comandi
7. Sessioni, cookie, CSRF, intestazioni
8. Deserializzazione, XML, richieste in uscita
9. Configurazione di produzione e dipendenze
10. Nei framework

Fonti di riferimento: OWASP (Top 10, Cheat Sheet Series), Paragon Initiative, manuale PHP.

## 1. Principi

- **Separazione codice/dati**: quasi tutte le vulnerabilità (SQL injection, XSS, inclusione di file, command injection) nascono quando un dato viene interpretato come codice. La difesa è strutturale (parametri legati, escape automatico, array di argomenti), non filtrare caratteri "pericolosi".
- **Confine di fiducia**: tutto ciò che arriva da fuori è input esterno — `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`, molte chiavi di `$_SERVER` (`HTTP_*`, `REQUEST_URI`, `PHP_SELF`), il corpo della richiesta, header, dati letti da API di terzi, file caricati, perfino righe del database scritte da altri sistemi.
- **Valida in ingresso, fai escape in uscita**: la validazione garantisce che il dato abbia senso per il dominio; l'escape dipende da dove il dato finisce. Sono due operazioni diverse e servono entrambe.
- **Autorizzazione esplicita** su ogni azione e ogni risorsa (controllo che l'utente possa agire su *quella* riga, non solo che sia autenticato).

## 2. Input: validazione al confine

```php
$id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1], 'flags' => FILTER_NULL_ON_FAILURE]);
$email = filter_var($raw, FILTER_VALIDATE_EMAIL, FILTER_NULL_ON_FAILURE);
$flag = filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
$status = Status::tryFrom($raw);          // enum backed per insiemi chiusi
```

- Con `FILTER_NULL_ON_FAILURE`, `null` significa "non valido" e `false` resta un valore legittimo.
- Valida, non "sanificare": `FILTER_SANITIZE_*` altera il dato e dà un falso senso di sicurezza (`FILTER_SANITIZE_STRING` è deprecato dalla 8.1). Rifiuta ciò che non è valido.
- Trasforma subito l'input valido in tipi o value object; il codice interno non deve rivalidare.
- Controlla tipo e struttura dei dati JSON decodificati (`is_array`, `is_string`, chiavi attese): `json_decode` restituisce qualsiasi cosa.
- Limita dimensioni (lunghezza stringhe, numero di elementi, dimensione del corpo) per evitare esaurimento di risorse.

## 3. Output: escape per contesto

| Contesto | Tecnica |
|---|---|
| Corpo HTML e attributi quotati | `htmlspecialchars($s, ENT_QUOTES \| ENT_SUBSTITUTE \| ENT_HTML5, 'UTF-8')` o motore di template con escape automatico (Twig, Blade `{{ }}`, Plates `$this->e()`) |
| Dentro `<script>` o attributi `on*` | non inserire dati; passali via `data-*` o JSON: `json_encode($data, JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_HEX_APOS \| JSON_HEX_QUOT \| JSON_THROW_ON_ERROR)` |
| Parametro di URL | `rawurlencode()` per i segmenti, `http_build_query()` per la query string |
| `href`/`src` con URL fornito dall'utente | valida lo schema (`https`, `http`, `mailto`) prima dell'escape: `javascript:` passa indenne da `htmlspecialchars` |
| HTML ricco fornito dall'utente | sanificatore dedicato (HTML Purifier, `symfony/html-sanitizer`), mai regex |
| Header HTTP | rifiuta `\r` e `\n` nei valori (le implementazioni PSR-7 lo fanno già) |

Blade `{!! !!}`, Twig `|raw` e simili disattivano l'escape: accettabili solo su contenuto già sanificato o generato dall'applicazione.

## 4. SQL

- Sempre prepared statement con parametri legati (dettagli in `database-pdo.md`). L'escape manuale (`addslashes`, `real_escape_string`) non è una difesa accettabile.
- Gli **identificatori** non si possono legare come parametri: colonne, tabelle e direzioni di ordinamento vengono da una whitelist.

```php
$column = match ($request['sort'] ?? 'created_at') {
    'name' => 'name',
    'created_at' => 'created_at',
    default => throw new InvalidArgumentException('Ordinamento non ammesso'),
};
$direction = ($request['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
```

- In `LIKE`, fai escape di `%`, `_` e del carattere di escape nel valore legato, se l'utente non deve poter usare i caratteri jolly.
- Principio del minimo privilegio per l'utente del database dell'applicazione.

## 5. Password, token, crittografia

**Password**
```php
$hash = password_hash($password, PASSWORD_DEFAULT);           // bcrypt, costo 12 dalla 8.4
if (password_verify($password, $hash)) {
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        // aggiorna l'hash salvato: l'algoritmo o il costo predefinito è cambiato
    }
}
```
- `PASSWORD_ARGON2ID` se disponibile e se si vuole resistenza agli attacchi via GPU; configura costo memoria/tempo con un benchmark sul server.
- bcrypt considera solo i primi 72 byte: se le password possono essere più lunghe, preferisci Argon2id oppure imponi un limite; non pre-hashare con `hash()` senza sapere cosa si sta facendo.
- Il sale è incluso nell'hash: nessuna colonna separata. Mai hash veloci (`md5`, `sha*`) né cifratura reversibile per le password.
- `#[\SensitiveParameter]` sui parametri che ricevono password o segreti.

**Token e valori casuali**
- `bin2hex(random_bytes(32))` per token, `random_int($min, $max)` per numeri. `rand`, `mt_rand`, `uniqid`, `lcg_value` e `str_shuffle` non sono crittograficamente sicuri.
- Confronta segreti con `hash_equals($known, $userSupplied)` per evitare attacchi a tempo.
- Salva nel database l'hash dei token di lunga durata (reset password, API key), non il token in chiaro.

**Crittografia**
- Usa `ext-sodium` (`sodium_crypto_secretbox`, `sodium_crypto_aead_xchacha20poly1305_ietf_*`, `sodium_crypto_box` per chiave pubblica) o l'astrazione del framework (Laravel `Crypt`). Mai modalità ECB, mai cifratura senza autenticazione, mai algoritmi fatti a mano.
- Chiavi fuori dal codice, generate con `sodium_crypto_secretbox_keygen()` o equivalente, ruotabili.
- Hashing non è cifratura; base64 non è né l'una né l'altra.

## 6. File, upload, percorsi, comandi

**Upload**
- Non fidarti di nome file e MIME inviati dal client. Determina il tipo dal contenuto (`finfo_file(..., FILEINFO_MIME_TYPE)`) e confrontalo con una whitelist; verifica anche l'estensione.
- Salva fuori dalla document root con un nome generato (`bin2hex(random_bytes(16))`), servi il file tramite codice che imposta `Content-Type` e `Content-Disposition`.
- Controlla `UPLOAD_ERR_OK`, dimensione massima e usa `move_uploaded_file()` (o l'API PSR-7 `UploadedFileInterface::moveTo()`).

**Percorsi**
- Mai costruire percorsi con input grezzo. Se serve, risolvi con `realpath()` e verifica che il risultato inizi con la directory base consentita (più il separatore).
- Mai `include`/`require` con input dell'utente; `allow_url_include=Off`.

**Comandi di sistema**
- Evitali se esiste un'alternativa in PHP. Se servono, usa `proc_open` con **array** di argomenti (nessuna shell coinvolta) o `symfony/process`; in ultima istanza `escapeshellarg()` su ogni argomento. Mai `shell_exec`/backtick con dati esterni.

## 7. Sessioni, cookie, CSRF, intestazioni

- `session_regenerate_id(true)` a ogni cambio di privilegio (login, logout, elevazione).
- Impostazioni: `session.use_strict_mode=1`, `session.cookie_httponly=1`, `session.cookie_secure=1`, `session.cookie_samesite=Lax` (o `Strict`), `session.use_only_cookies=1`.
- Cookie applicativi con `setcookie($n, $v, ['secure' => true, 'httponly' => true, 'samesite' => 'Lax', 'path' => '/'])`.
- CSRF: token sincronizzato per sessione verificato con `hash_equals` su ogni richiesta che modifica stato; `SameSite` riduce il rischio ma non sostituisce il token. Le richieste `GET` non devono modificare stato.
- Intestazioni utili: `Content-Security-Policy`, `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `frame-ancestors` nella CSP (o `X-Frame-Options`). Impostale in un middleware.

## 8. Deserializzazione, XML, richieste in uscita

- `unserialize()` su dati esterni permette l'iniezione di oggetti (gadget chain). Usa JSON. Se `unserialize` è inevitabile su dati semi-fidati: `unserialize($s, ['allowed_classes' => false])` o una lista esplicita.
- XML: da PHP 8.0 con libxml ≥ 2.9 le entità esterne sono disattivate per impostazione predefinita. Non passare `LIBXML_NOENT` né `LIBXML_DTDLOAD` su XML non fidato.
- SSRF: se l'applicazione scarica URL forniti dall'utente, valida schema e host con una whitelist, risolvi l'host e rifiuta indirizzi privati/loopback/link-local, disattiva i redirect o rivalidali, imposta timeout.
- Espressioni regolari su input esterno: limita la lunghezza dell'input e evita pattern con backtracking catastrofico; controlla il ritorno di `preg_*` (`false` = errore, `preg_last_error_msg()`).

## 9. Configurazione di produzione e dipendenze

```ini
display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL
expose_php = Off
allow_url_include = Off
session.use_strict_mode = 1
session.cookie_httponly = 1
session.cookie_secure = 1
```

- La document root punta solo a `public/`: configurazione, `.env`, `vendor/`, log e sorgenti restano fuori.
- Segreti in variabili d'ambiente o in un secret manager, mai nel repository; `.env` in `.gitignore`, `.env.example` senza valori reali.
- Messaggi d'errore generici verso l'utente, dettagli solo nel log. Mai stampare `$e->getMessage()` di eccezioni di infrastruttura (possono contenere DSN, query, percorsi).
- `composer audit` in CI e prima di ogni rilascio; aggiorna PHP entro le versioni supportate.

## 10. Nei framework

Usa i meccanismi del framework invece di reimplementarli: in Laravel Form Request/validator, policy e gate per l'autorizzazione, middleware CSRF, `Hash`, `Crypt`, Eloquent/query builder con binding (attenzione a `whereRaw`, `orderByRaw`, `DB::raw`: legare sempre i valori, whitelist per gli identificatori), `$fillable` contro il mass assignment, Blade `{{ }}` per l'escape. Le regole di questo file restano valide per il codice che scrivi al di fuori di quei meccanismi.
