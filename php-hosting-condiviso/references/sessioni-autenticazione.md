# Sessioni, autenticazione, upload, email

## Indice
1. Sessioni
2. CSRF
3. Login con password
4. Upload di file
5. Invio di email
6. Limitazione dei tentativi

## 1. Sessioni

Lo scheletro usa `mezzio/mezzio-session` (middleware PSR-15) con `mezzio-session-ext`, cioè le sessioni native di PHP. I messaggi flash sono una piccola classe (`App\Http\Flash`) che legge e scrive nella sessione: `Flash::add($session, '…')` nell'handler, `flashes()` nel layout. Le configura in `SessionPersistenceFactory`:
- **`save_path` in `app/var/sessions`**: sugli hosting condivisi la cartella predefinita può essere comune a più clienti, con file di sessione leggibili da altri script sullo stesso server;
- **garbage collection attiva** (`gc_probability=1`, `gc_divisor=100`): alcune distribuzioni la azzerano e affidano la pulizia a un cron di sistema, che però non conosce la cartella personalizzata. Senza GC i file si accumulano fino a esaurire gli inode;
- **cookie** `HttpOnly`, `SameSite=Lax`, `Secure` (se `https` è attivo), nome personalizzato;
- **`use_strict_mode`**: PHP rifiuta identificativi di sessione non generati dal server.

La sessione è pigra: viene avviata solo se il codice la legge o la scrive, quindi le pagine che non la usano non inviano cookie.

Uso negli handler:
```php
$session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
assert($session instanceof SessionInterface);
$session->set('cart', $items);
$session = $session->regenerate(); // a ogni cambio di privilegio (login, logout)
```

`regenerate()` restituisce l'istanza da usare: riassegnala sempre. Con la `LazySession` fornita dal middleware funzionerebbe anche senza, ma il contratto di `SessionInterface` non lo garantisce.

## 2. CSRF

`CsrfMiddleware` dello scheletro:
- genera un token per sessione (`random_bytes(32)`), valido per tutta la sessione: più schede aperte non si invalidano a vicenda;
- verifica automaticamente POST, PUT, PATCH e DELETE, con il campo `_csrf` o l'intestazione `X-CSRF-Token` per le richieste JavaScript;
- confronta con `hash_equals` e risponde 403 se il token non corrisponde;
- viene eseguito dopo il router, così le rotte inesistenti rispondono 404 e non 403.

Nei template: `{{ csrf_field() }}` in ogni form con `method="post"`.

Ho scelto di non usare `mezzio-csrf` per due motivi: usa token monouso con una sola chiave, per cui un secondo form aperto in un'altra scheda fallisce, e confronta con `===` invece che con un confronto a tempo costante.

## 3. Login con password

Schema consigliato, basato sulla sessione e senza cookie personalizzati:
```php
// Registrazione
$hash = password_hash($password, PASSWORD_DEFAULT);

// Login
$user = $users->findByEmail($email);
if ($user === null || !password_verify($password, $user->passwordHash)) {
    // stesso messaggio per utente inesistente e password errata
    return $this->view->render($request, 'login.html.twig', ['error' => 'Credenziali non valide'], 401);
}
if (password_needs_rehash($user->passwordHash, PASSWORD_DEFAULT)) {
    $users->updatePasswordHash($user->id, password_hash($password, PASSWORD_DEFAULT));
}
$session = $session->regenerate();           // nuovo identificativo: impedisce il session fixation
$session->set('user_id', $user->id);
$session->set('auth_version', $user->authVersion);

// Logout
$session->clear();
$session = $session->regenerate();
```
- Un middleware di autenticazione per le rotte protette legge `user_id` dalla sessione, carica l'utente e confronta `auth_version`. Incrementando `auth_version` nel database, per esempio al cambio password o con "esci da tutti i dispositivi", tutte le sessioni esistenti vengono invalidate.
- Se preferisci un token firmato in un cookie, per esempio HMAC su id e scadenza, ricorda che senza uno stato lato server (come `auth_version`) non si può revocare prima della scadenza, e che il segreto HMAC diventa una credenziale critica da proteggere come la password del database.
- `#[\SensitiveParameter]` sui parametri che ricevono password.
- Mai salvare in sessione la password o l'hash.

## 4. Upload di file

- Limiti in `.user.ini` (`upload_max_filesize`, `post_max_size`), più un controllo esplicito della dimensione nel codice.
- PSR-7: `$request->getUploadedFiles()` → `UploadedFileInterface`. Controlla `getError() === UPLOAD_ERR_OK`.
- Tipo reale dal contenuto: salva prima in una posizione temporanea, poi `finfo_file($path, FILEINFO_MIME_TYPE)` confrontato con una whitelist.
- Nome generato (`bin2hex(random_bytes(16))` + estensione dalla whitelist), mai il nome inviato dal client.
- **Destinazione**:
  - file privati (documenti, allegati) in `app/var/uploads/`, serviti da un handler che verifica i permessi e imposta `Content-Type` e `Content-Disposition: attachment`;
  - file pubblici (immagini di contenuto) in `assets/uploads/`, dove `assets/.htaccess` impedisce l'esecuzione di script.
- Non fidarti dell'estensione da sola: un file `immagine.jpg.php` o un file con contenuto PHP e nome `.jpg` sono tentativi comuni.

## 5. Invio di email

- La funzione `mail()` sugli hosting condivisi è spesso limitata, e i messaggi inviati così finiscono facilmente nello spam per la mancanza di autenticazione SPF/DKIM coerente.
- Usa SMTP con le credenziali della casella creata nel pannello, tramite una libreria come `symfony/mailer` o PHPMailer. Host, porta, utente e password vanno in `settings.local.php`.
- Porta 587 con STARTTLS o 465 con TLS; alcuni hosting bloccano le connessioni SMTP in uscita verso server esterni e permettono solo il proprio.
- Invii multipli (notifiche, newsletter): mettili in una tabella di coda e inviali a lotti dal cron, rispettando i limiti orari dell'hosting.

## 6. Limitazione dei tentativi

Per login, form di contatto e recupero password, senza Redis né servizi esterni:
- una tabella `attempts (key, attempted_at)`, dove la chiave è per esempio l'hash di IP ed email;
- prima di elaborare conta i tentativi recenti (`WHERE key = ? AND attempted_at > ?`); oltre la soglia rispondi 429 con `Retry-After`;
- pulizia dei record vecchi dal cron;
- l'IP si prende da `REMOTE_ADDR`. Considera `X-Forwarded-For` solo se l'hosting documenta un proxy fidato davanti a PHP, altrimenti chiunque può falsificarlo.
