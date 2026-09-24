# Dependency injection

Fonti: [PHP The Right Way — Dependency Injection](https://phptherightway.com/#dependency_injection), [PSR-11](https://www.php-fig.org/psr/psr-11/).

## Indice
1. Regole
2. Composition root e service locator
3. Cosa iniettare e cosa no
4. Autowiring
5. Interfacce: quando servono
6. Stato e runtime persistenti
7. Nei framework

## 1. Regole

- Le dipendenze obbligatorie arrivano nel **costruttore**, con promozione e tipi: la firma del costruttore è la documentazione di ciò che la classe richiede.
- Una classe non crea le proprie dipendenze con `new` quando queste fanno I/O, hanno configurazione o vanno sostituite nei test. `new` va benissimo per value object, DTO, eccezioni e oggetti puramente computazionali.
- Nessun accesso a stato globale dall'interno delle classi: niente `getenv()`, `$_ENV`, `$_SERVER`, singleton statici, `global`.
- Iniezione tramite setter solo per dipendenze davvero opzionali (e preferibilmente mai: un parametro nullable nel costruttore o un null object sono più chiari).

## 2. Composition root e service locator

Un container (PSR-11) è uno strumento per **costruire il grafo di oggetti**, non una dipendenza da passare in giro.

- Il container si usa solo nella composition root: bootstrap, file di definizioni, factory, service provider, il dispatcher che risolve gli handler.
- Iniettare `ContainerInterface` in una classe applicativa e chiamare `$container->get()` al suo interno è il pattern **service locator**: nasconde le dipendenze reali, le rende visibili solo a runtime e complica i test. Crea una dipendenza più forte di quella che si voleva rimuovere.
- Eccezione legittima: componenti di infrastruttura che devono risolvere classi note solo a runtime (router → handler, bus di comandi → handler). Anche lì, limita l'accesso al container a quel solo punto.

## 3. Cosa iniettare e cosa no

| Inietta | Non iniettare |
|---|---|
| servizi (repository, client HTTP, mailer, logger, orologio) | la richiesta HTTP dentro servizi di dominio: passa i valori estratti |
| configurazione tipizzata (`Settings`, o i singoli valori) | l'intero array di configurazione "per comodità" |
| factory, quando l'oggetto dipende da valori noti solo a runtime | il container |
| interfacce PSR ai confini (`LoggerInterface`, `ClockInterface`, `ResponseFactoryInterface`) | istanze mutabili condivise pensate per "una sola richiesta" (es. una `Response` PSR-7 pre-costruita) |

Sulla `Response` iniettata: PHP-DI e la maggior parte dei container condividono l'istanza risolta; in PSR-7 il messaggio è immutabile ma lo **stream del corpo è mutabile**, quindi scritture da parti diverse si sommano. Inietta `ResponseFactoryInterface` (PSR-17) e crea una risposta nuova quando serve.

**Orologio**: inietta `Psr\Clock\ClockInterface` (PSR-20) invece di chiamare `new DateTimeImmutable()` nei servizi che dipendono dall'ora corrente; nei test usi un orologio fisso.

## 4. Autowiring

L'autowiring risolve le dipendenze leggendo i tipi dei parametri del costruttore. È la scelta predefinita nei progetti moderni:
- riduce la configurazione a ciò che è davvero significativo (legare interfacce a implementazioni, valori scalari, factory);
- abbassa il costo di aggiungere una dipendenza, quindi incentiva classi piccole;
- funziona solo con parametri tipizzati, scoraggiando l'uso di primitive al posto di tipi.

Cautele:
- PHP-DI autocabla solo classi concrete: ogni interfaccia va legata esplicitamente. Symfony segnala le ambiguità; Laravel risolve classi concrete e richiede binding per le interfacce.
- Parametri scalari (`string $apiKey`) non sono autocablabili: definiscili nel container o, meglio, raggruppali in un oggetto di configurazione tipizzato.
- In produzione, se il container lo supporta, abilita la compilazione o la cache delle definizioni (PHP-DI `enableCompilation()`, Symfony container compilato).

## 5. Interfacce: quando servono

Crea un'interfaccia quando:
- esiste o esisterà concretamente più di un'implementazione (gateway di pagamento, storage);
- la dipendenza fa I/O e vuoi sostituirla nei test con un fake;
- definisci un confine tra moduli o un punto di estensione di un pacchetto.

Non crearla per ogni classe "per principio": un'interfaccia con una sola implementazione e nessun confine è solo indirezione. Per i test, una classe `final` concreta senza I/O si usa direttamente; un servizio con I/O si sostituisce tramite la sua interfaccia con un fake scritto a mano (più leggibile e robusto di un mock configurato).

Principi SOLID in breve, nella forma utile per decidere:
- **Responsabilità singola**: una classe ha un solo motivo per cambiare.
- **Aperto/chiuso**: estendi aggiungendo implementazioni di un'interfaccia, non modificando `if` sparsi.
- **Liskov**: un'implementazione deve rispettare il contratto dell'interfaccia, eccezioni comprese.
- **Segregazione**: interfacce piccole, orientate a chi le usa.
- **Inversione delle dipendenze**: il dominio definisce le interfacce di cui ha bisogno; l'infrastruttura le implementa.

## 6. Stato e runtime persistenti

Con runtime che mantengono il processo tra le richieste (FrankenPHP in modalità worker, RoadRunner, Swoole, Laravel Octane), i servizi condivisi dal container sopravvivono alla richiesta:
- i servizi devono essere privi di stato legato alla richiesta (utente corrente, lingua, richiesta stessa): passa questi valori come argomenti o usa servizi con ambito di richiesta (Laravel `scoped()`);
- le classi `readonly` senza stato sono sicure per costruzione;
- attenzione a cache in memoria e proprietà statiche che crescono senza limite.

## 7. Nei framework

Le regole sopra valgono ovunque; cambia solo dove si scrive la composition root.

- **Senza framework**: un file di definizioni del container PSR-11 scelto (o costruzione manuale con `new` per applicazioni piccole: un container non è obbligatorio).
- **Laminas / Mezzio**: factory registrate nel service manager (`ConfigProvider` → `dependencies.factories`); `ReflectionBasedAbstractFactory` o `ConfigAbstractFactory` per evitare factory banali. La factory riceve il container: è composition root, la classe costruita no.
- **Laravel**: binding nei service provider (`bind`, `singleton`, `scoped`), iniezione nel costruttore di controller, action, job, listener e comandi. Helper `app()`, `resolve()` e le facade sono service locator: seguendo la convenzione del progetto sono accettabili nel codice di glue (controller, provider), ma nelle classi di dominio preferisci l'iniezione per esplicitare le dipendenze e semplificare i test.
- **Symfony**: servizi privati, autowiring e autoconfigurazione in `services.yaml`; niente `$this->container->get()` nei controller.
