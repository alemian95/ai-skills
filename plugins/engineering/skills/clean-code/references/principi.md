# Principi di sviluppo — riferimento completo

Documento di dettaglio della skill `clean-code`. Consultalo quando devi motivare
una violazione all'utente, quando la classificazione di un problema è dubbia, o
quando serve la formulazione estesa di un principio.

## Indice

- [SOLID](#solid)
- [DRY](#dry-dont-repeat-yourself)
- [YAGNI](#yagni-you-arent-gonna-need-it)
- [SSOT](#ssot-single-source-of-truth)
- [Comportamento in review e refactoring](#comportamento-atteso-durante-la-review-e-il-refactoring)
- [Comportamento nella scrittura di nuovo codice](#comportamento-atteso-durante-la-scrittura-di-nuovo-codice)

---

## SOLID

- **S — Single Responsibility**: ogni classe, modulo o funzione ha una sola
  responsabilità. Se una unità fa più cose distinte, va spezzata.
- **O — Open/Closed**: le entità devono essere aperte all'estensione e chiuse
  alla modifica. Preferire astrazioni ed estensioni a modifiche dirette.
- **L — Liskov Substitution**: i tipi derivati devono poter sostituire il tipo
  base senza alterare il comportamento atteso.
- **I — Interface Segregation**: preferire interfacce piccole e specifiche a
  interfacce generali e monolitiche. I client non devono dipendere da metodi che
  non usano.
- **D — Dependency Inversion**: dipendere da astrazioni, non da implementazioni
  concrete. Usare iniezione di dipendenze dove appropriato.

## DRY (Don't Repeat Yourself)

- Ogni logica o conoscenza deve avere **una sola rappresentazione autorevole**
  nel sistema.
- Duplicazioni di logica vanno estratte in funzioni, classi o moduli condivisi.
- La duplicazione accidentale (codice simile ma con scopi diversi) non va
  forzatamente unificata: valutare il contesto.

## YAGNI (You Aren't Gonna Need It)

- Non aggiungere funzionalità, parametri, astrazioni o generalizzazioni che
  **non servono adesso**.
- Evitare over-engineering: implementare solo ciò che è richiesto dal requisito
  corrente.
- Le astrazioni premature sono un debito tecnico: introdurle solo quando il
  pattern si ripete almeno due volte.

## SSOT (Single Source of Truth)

SSoT è distinto da DRY: mentre DRY evita la duplicazione di *logica*, SSOT evita
la duplicazione di *stato, dati e regole di dominio*. Una violazione SSOT
significa che la stessa informazione esiste in più posti che possono divergere,
causando incongruenze.

I principi fondamentali sono:

- **Ogni regola di dominio ha un solo posto autorevole** dove viene definita.
  Tutti gli altri punti del sistema la *richiamano*, non la *ridefiniscono*.
  (Ad esempio: la logica che determina se un utente può accedere a una risorsa
  non va replicata in layer diversi — va definita una volta e invocata ovunque
  serva.)
- **Ogni dato condiviso ha una sola origine**. Se più parti del sistema
  necessitano degli stessi dati, devono ottenerli dalla stessa fonte, non ognuna
  per conto proprio. (Ad esempio: se due parti dell'interfaccia mostrano gli
  stessi dati, non devono sapere entrambe come recuperarli — devono affidarsi
  alla stessa origine.)
- **Ogni valore di configurazione o costante di dominio è definito una volta
  sola** e importato dove necessario, mai ridefinito inline.

Gli esempi tra parentesi sono illustrativi del principio, non prescrizioni
sull'implementazione: la soluzione concreta va scelta in base all'architettura e
al contesto del progetto.

---

## Comportamento atteso durante la review e il refactoring

Quando si analizza codice esistente:

1. **Identificare esplicitamente** le violazioni rilevate, indicando il principio
   violato e il punto preciso nel codice.
2. **Spiegare il problema** in modo conciso: perché è una violazione e quale
   impatto ha sulla manutenibilità.
3. **Proporre un refactoring concreto**, mostrando il codice migliorato e
   spiegando le scelte fatte.
4. **Non introdurre complessità aggiuntiva** durante il refactoring: ogni
   modifica deve ridurre il debito tecnico, non aumentarlo.
5. **Segnalare i trade-off** quando un refactoring potrebbe avere costi (es.
   rottura di API pubbliche, aumento della complessità strutturale).

## Comportamento atteso durante la scrittura di nuovo codice

1. Applicare i principi **fin dalla prima stesura**, senza aspettare un secondo
   passaggio.
2. Se un requisito sembra spingere verso una violazione, segnalarlo e proporre un
   approccio alternativo.
3. Non anticipare esigenze future non dichiarate (YAGNI): se serve un'estensione
   futura, sarà il momento giusto per aggiungerla.
4. Quando si introducono nuove regole di dominio o nuovi dati condivisi,
   **individuare o creare subito la fonte autorevole** più coerente con
   l'architettura esistente, anziché scrivere la logica inline nel punto di
   utilizzo.
