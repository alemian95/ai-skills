# Architettura del progetto

Queste regole integrano le guideline di Laravel Boost, non le sostituiscono.

- **Boost** decide convenzioni del framework, versioni dei pacchetti, comandi Artisan, stile (Pint), test (Pest/PHPUnit), uso di Inertia e Wayfinder. Prima di scrivere codice usa i suoi tool (`search-docs`, `database-schema`, `list-routes`, `tinker`...).
- **Questo file** decide *dove* sta la logica e *come* sono separate le responsabilità.
- Se le due fonti sembrano in conflitto, vale Boost sul "come si scrive" e questo file sul "dove si mette". Se il conflitto resta, fermati e chiedi.

Non ripetere qui regole già presenti in Boost: una regola scritta in due posti prima o poi diverge.

## Principi

Si applicano sia al codice nuovo sia a review e refactoring.

- **SOLID**, con queste precisazioni:
  - *Single Responsibility*: una classe che fa due cose distinte va spezzata.
  - *Dependency Inversion*: le dipendenze si iniettano (constructor injection, Service Container), mai `new` di un servizio o facade dentro la logica di dominio.
  - *Interface Segregation*: interfacce piccole, costruite sul chiamante.
- **YAGNI**: niente parametri, astrazioni o layer per requisiti ipotetici. Un'astrazione nasce alla seconda occorrenza reale, o quando un requisito già deciso la richiede.
- **DRY**: una logica ha una sola rappresentazione. Codice simile ma con scopi diversi non va unificato a forza.
- **SSOT**: DRY riguarda la logica, SSOT riguarda stato, dati e regole di dominio.
  - Ogni regola di dominio (autorizzazione, calcolo, transizione di stato) è definita in un solo posto e richiamata ovunque serva.
  - Ogni dato condiviso ha una sola origine: chi ne ha bisogno lo prende da lì, non lo ricostruisce.
  - Ogni costante o valore di configurazione è definito una volta (Enum, `config/`) e importato, mai riscritto inline.
- **Composizione prima dell'ereditarietà.**
- **Design pattern** (Repository, Strategy, Factory, Observer...) solo quando risolvono un problema presente nel codice, non uno immaginato.

Quando un'astrazione e YAGNI tirano in direzioni opposte, vince YAGNI, salvo i casi elencati sotto in *Contracts*.

## Backend: dove va la logica

| Livello | Responsabilità | Non deve |
|---|---|---|
| Controller | Riceve la request, delega, restituisce la response | Contenere logica di business, query Eloquent complesse, chiamate a SDK esterni |
| Form Request | Validazione e autorizzazione d'ingresso (delegando alla Policy) | Contenere logica di dominio |
| Action | Un'operazione di business completa, context-free (HTTP, console, Job la chiamano allo stesso modo) | Conoscere `Request`, sessione o response |
| Service | Logica pura riusabile da 2+ chiamanti, oppure wrapper di una dipendenza esterna | Orchestrare un caso d'uso (quello è compito dell'Action) |
| Policy / Gate | Unica fonte delle regole di autorizzazione | Essere replicata in controller, frontend o query |
| Event / Listener | Side-effect disaccoppiati (email, notifiche, analytics, integrazioni) | Contenere la logica principale dell'operazione |
| Job | Lavoro asincrono o lento; di norma invoca un'Action | Duplicare la logica dell'Action |
| Model | Relazioni, cast, scope, accessor | Diventare un contenitore di logica di business |

Per i casi dubbi tra Action, Service, Event e Job usa la skill `laravel-action-vs-service`, se installata.

### Regole

- **Input e output tipizzati.** Le Action ricevono un DTO (`readonly` class) o parametri tipizzati, mai l'array `$request->all()`, e restituiscono un tipo preciso.
- **Transazioni esplicite.** Un'operazione che scrive su più tabelle gira in `DB::transaction()` dentro l'Action. Gli eventi che producono side-effect esterni partono dopo il commit (`ShouldDispatchAfterCommit` o `afterCommit()`).
- **Contracts.** Un'interfaccia si introduce quando:
  - incapsula una dipendenza esterna (API, gateway di pagamento, storage) che va sostituita nei test;
  - esistono già due o più implementazioni;
  - un modulo espone un punto di estensione ad altri moduli.

  Negli altri casi si inietta la classe concreta: il container la risolve comunque.
- **Binding nel container** in un Service Provider dedicato al dominio o al modulo, non sparsi nel codice.
- **CQRS leggero.** Le operazioni che modificano stato (Action) restano separate dalle letture complesse (query class o scope dedicati) quando la separazione chiarisce; per un CRUD semplice non serve.
- **Costanti di dominio come Enum** backed, con i metodi di dominio (`label()`, `canTransitionTo()`...) sull'Enum stesso.
- **Errori di dominio** come eccezioni dedicate, gestite in modo centralizzato in `bootstrap/app.php` (`withExceptions`). Mai `catch` vuoti; il messaggio mostrato all'utente non contiene dati sensibili né dettagli interni.
- **Type safety.** `declare(strict_types=1)`, tipi su parametri, proprietà e ritorni, generics via PHPDoc dove servono all'analisi statica.

## Struttura e moduli

Default: cartelle standard di Laravel, raggruppate per area funzionale.

```
app/
  Actions/<Area>/CreateOrder.php
  Data/<Area>/CreateOrderData.php
  Services/<Area>/...
  Contracts/...
  Enums/...
  Events/<Area>/...
  Policies/...
```

Quando un'area diventa un bounded context con regole proprie (tante Action, eventi dedicati, un team che la segue), si sposta in un modulo:

```
app/Domain/<Context>/{Actions,Data,Events,Models,Policies,...}
```

Regole per i moduli:

- Un modulo non tocca modelli o tabelle di un altro modulo. Comunica tramite le sue Action pubbliche, i suoi Contract o i suoi eventi.
- Il core non dipende dai moduli: sono i moduli ad agganciarsi al core con eventi, listener e binding nel proprio Service Provider.
- Non creare un modulo per un'area con due classi: la struttura segue il dominio reale, non quello previsto.

## Frontend (React + TypeScript)

- **Componenti piccoli e puri**: ricevono props, rendono UI. La logica non banale (stato derivato, side-effect, fetch, form complessi) va in custom hook o funzioni di utilità testabili senza render.
- **Stato unidirezionale**: i dati arrivano dal server (props Inertia o API) e scendono; lo stato locale solo per ciò che è davvero locale. Non duplicare in stato client un dato che il server fornisce già.
- **SSOT tra backend e frontend**:
  - le route arrivano da Wayfinder, niente URL scritti a mano;
  - le regole di autorizzazione arrivano dal backend (es. flag `can` calcolati dalla Policy e passati come props), il frontend non le ricalcola;
  - valori di Enum e tipi dei dati condivisi hanno una sola definizione, generata dal backend o scritta una volta in `resources/js/types`.
- **TypeScript rigoroso**: `strict` attivo, niente `any` (se è inevitabile, un commento spiega perché); preferire `unknown` con narrowing.

## Test

- Se una unità è difficile da testare, il problema è il design: rifattorizzala invece di aggirarla con mock complicati.
- Le Action hanno test di feature sul comportamento; le dipendenze esterne si sostituiscono tramite il loro Contract o i fake di Laravel (`Http::fake()`, `Queue::fake()`, `Event::fake()`...).

## Modo di lavorare

- Per le soluzioni non banali spiega in 2-3 righe l'architettura scelta e il perché, poi scrivi il codice. Per i task semplici, scrivi direttamente.
- Codice pronto per la produzione: robusto, tipizzato, con docblock solo dove aggiungono informazione che i tipi non danno.
- In review indica il principio violato, il punto preciso, l'impatto e un refactoring concreto che non aggiunga complessità. Segnala i trade-off (rottura di API, più file da mantenere).
- Rispondi in italiano; termini tecnici e identificatori restano in inglese.
