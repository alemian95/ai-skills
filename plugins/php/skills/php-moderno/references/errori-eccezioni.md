# Errori ed eccezioni

## Indice
1. Il modello di PHP 8
2. Gerarchia e scelta dell'eccezione
3. Eccezioni di dominio
4. Dove catturare
5. Error handler nei punti di ingresso
6. Logging
7. Nei framework

## 1. Il modello di PHP 8

PHP non è più il linguaggio "che continua comunque" descritto nelle guide datate:
- le funzioni interne lanciano `TypeError` per tipi errati e `ValueError` per valori fuori dominio (es. `str_repeat('a', -1)`), invece di restituire `false`/`null` con un warning;
- accedere a una variabile non definita genera un **Warning** (non più un Notice); anche accedere a una chiave di array inesistente è un Warning;
- restano funzioni che segnalano il fallimento con `false` + warning (`file_get_contents`, `fopen`, `mkdir`, molte funzioni di rete): controlla sempre il valore di ritorno, oppure converti i warning in eccezioni (sezione 5).

`error_reporting(E_ALL)` sempre, in ogni ambiente. Cambia solo **dove** finiscono gli errori: schermo in sviluppo, log in produzione.

## 2. Gerarchia e scelta dell'eccezione

```
Throwable
├── Error                      bug o condizione del motore: non catturare nel codice applicativo
│   ├── TypeError, ArgumentCountError, ValueError
│   ├── ArithmeticError → DivisionByZeroError
│   └── UnhandledMatchError
└── Exception
    ├── ErrorException         warning/notice convertiti
    ├── JsonException          JSON_THROW_ON_ERROR
    ├── LogicException         errore del chiamante: si corregge il codice
    │   ├── InvalidArgumentException, DomainException, LengthException, OutOfRangeException
    │   └── BadFunctionCallException → BadMethodCallException
    └── RuntimeException       condizione esterna/imprevedibile: si gestisce a runtime
        └── UnexpectedValueException, OutOfBoundsException, OverflowException, RangeException, UnderflowException
```

Scelta pratica:
- argomento non valido passato dal codice chiamante → `InvalidArgumentException`;
- regola di dominio violata (saldo insufficiente, stato non ammesso) → eccezione di dominio che estende `DomainException`;
- dato letto da una fonte esterna con forma inattesa → `UnexpectedValueException`;
- risorsa esterna non disponibile (rete, file, servizio) → eccezione che estende `RuntimeException`;
- metodo chiamato in uno stato che non lo consente → `LogicException` o `BadMethodCallException`.

## 3. Eccezioni di dominio

Crea un tipo proprio quando il chiamante deve reagire in modo diverso a quel caso. Costruttori nominati rendono i punti di lancio leggibili e centralizzano il messaggio:

```php
final class InsufficientCredit extends DomainException
{
    public static function forCourse(CourseId $course, int $required, int $available): self
    {
        return new self(sprintf(
            'Crediti insufficienti per il corso %s: richiesti %d, disponibili %d',
            $course, $required, $available,
        ));
    }
}
```

- Estendi l'eccezione SPL più vicina, così chi cattura la categoria generica continua a funzionare.
- In un pacchetto, un'interfaccia marcatore (`interface BillingException extends Throwable`) permette di catturare tutte le eccezioni del modulo.
- I messaggi servono a chi legge i log: includi gli identificativi utili, mai dati personali o segreti.
- Non usare eccezioni per il flusso normale (es. "utente non trovato" in una ricerca è spesso `null` o un risultato vuoto, non un'eccezione).

## 4. Dove catturare

- Cattura al **confine** dove sai cosa fare: traduzione in risposta HTTP, retry, fallback, messaggio all'utente, rollback di transazione.
- Cattura il tipo più specifico possibile. `catch (\Throwable)` solo nel gestore di ultimo livello (middleware degli errori, handler del framework, worker di coda), e lì si registra sempre nel log.
- Quando traduci un'eccezione di infrastruttura in una di dominio, conserva la causa:

```php
try {
    $response = $this->http->sendRequest($request);
} catch (ClientExceptionInterface $e) {
    throw new PaymentGatewayUnavailable('Gateway non raggiungibile', previous: $e);
}
```

- `finally` per rilasciare risorse (lock, file, transazioni) indipendentemente dall'esito.
- Non catturare per registrare e rilanciare a ogni livello: produce lo stesso errore N volte nel log. Registra una volta, al confine esterno.
- Non catturare `Error` (`TypeError` & co.) nel codice applicativo: indica un bug da correggere.

## 5. Error handler nei punti di ingresso

Senza framework, ogni punto di ingresso (front controller web, script CLI, worker) converte warning, notice e deprecation in eccezioni:

```php
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
```

- Registralo nel punto di ingresso, non nel bootstrap del container: i test (PHPUnit) usano il bootstrap e segnalano come rischioso un handler non ripristinato.
- `set_exception_handler()` come ultima rete per le eccezioni non catturate fuori dalla pipeline HTTP (es. errore nella costruzione del container); `register_shutdown_function()` + `error_get_last()` per registrare gli errori fatali.
- In produzione `display_errors=Off`: la pagina d'errore è generata dall'applicazione (risposta 500 generica), i dettagli vanno nel log.

## 6. Logging

- Usa PSR-3 (`Psr\Log\LoggerInterface`, implementazione Monolog) iniettato nel costruttore.
- Passa l'eccezione nel contesto con chiave `exception` (convenzione PSR-3): Monolog registra classe, messaggio, file, riga, traccia e catena `previous`.
- Messaggi statici con segnaposto e dati nel contesto (`'Ordine {id} rifiutato'`, `['id' => $id]`): aggregabili e ricercabili.
- Livelli: `error` per eccezioni non gestite, `warning` per anomalie gestite, `info` per eventi di business rilevanti, `debug` solo in sviluppo.
- Mai password, token, numeri di carta o dati personali non necessari nel contesto.

## 7. Nei framework

Il framework possiede il gestore di ultimo livello: non registrare `set_error_handler` propri. In Laravel (11+) la configurazione sta in `bootstrap/app.php` → `withExceptions()`, con `report()`/`render()` per tipo di eccezione; le eccezioni di dominio possono implementare `report()`/`render()` o essere mappate lì. Il resto di questo file (scelta del tipo, eccezioni di dominio, dove catturare, `previous`) vale identico.
