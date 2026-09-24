---
name: clean-code
description: >
  Applica SOLID, DRY, YAGNI e SSOT nella scrittura di nuovo codice e nell'analisi
  di codice esistente, in qualsiasi linguaggio o framework. Usa questa skill ogni
  volta che l'utente chiede di implementare una funzionalità, una classe, un modulo,
  un servizio o un componente non banale — anche se non nomina esplicitamente i
  principi di progettazione. Usala anche per review, refactoring, audit
  architetturali, pareri sulla manutenibilità o sulla qualità del codice, e quando
  l'utente segnala odori di codice: classi che fanno troppe cose, logica ripetuta,
  costanti sparse, dipendenze da implementazioni concrete, condizionali annidati,
  astrazioni premature. Frasi tipiche che devono attivarla: "implementa X",
  "scrivi la classe che gestisce Y", "aggiungi la funzionalità Z", "rivedi questo
  codice", "si può semplificare?", "questa funzione fa troppe cose", "c'è
  duplicazione?", "è ben strutturato?", "come organizzeresti questo modulo?".
---

# Clean Code — SOLID, DRY, YAGNI, SSOT

Questa skill governa due attività distinte: **scrivere codice nuovo** e
**analizzare codice esistente**. I principi sono gli stessi, il momento in cui si
applicano e il modo di coinvolgere l'utente no.

Determina prima di tutto in quale delle due modalità ti trovi, poi segui il
percorso corrispondente. Se il compito è misto (implementare una funzionalità
dentro codice già scritto), applica entrambi: la modalità implementativa per il
nuovo codice, quella analitica per ciò che tocchi.

Per il dettaglio completo dei principi leggi `references/principi.md`. Il
riepilogo qui sotto basta per l'uso corrente; consulta il riferimento quando devi
motivare una violazione all'utente o quando la classificazione è dubbia.

---

## I quattro principi in forma operativa

### SOLID

| | Domanda da porsi | Sintomo di violazione |
|---|---|---|
| **S** Single Responsibility | Quante ragioni distinte ha questa unità per cambiare? | Il nome contiene "e"/"Manager"/"Utils"; la classe importa da domini scorrelati |
| **O** Open/Closed | Per aggiungere un caso devo modificare codice esistente? | Catene di `if`/`switch` sul tipo che crescono a ogni requisito |
| **L** Liskov Substitution | Il sottotipo rispetta il contratto del tipo base? | Metodi ereditati che lanciano "non supportato"; precondizioni irrigidite |
| **I** Interface Segregation | Il client usa tutti i metodi che l'interfaccia gli impone? | Implementazioni piene di metodi vuoti o stub |
| **D** Dependency Inversion | Questa unità nomina una classe concreta che potrebbe cambiare? | Istanziazione diretta di dipendenze dentro la logica di business |

### DRY

Ogni logica ha una sola rappresentazione autorevole. Attenzione alla duplicazione
*accidentale*: codice simile con scopi diversi non va unificato, perché
l'unificazione crea accoppiamento tra requisiti indipendenti che poi divergono.

### YAGNI

Implementa solo il requisito corrente. Le astrazioni premature sono debito
tecnico: introduci un'astrazione quando il pattern si è ripetuto almeno due volte,
non quando immagini che si ripeterà.

### SSOT

Ogni regola di dominio, dato condiviso e costante ha una sola origine autorevole.
Gli altri punti la richiamano, non la ridefiniscono.

### Distinguere DRY da SSOT

Si sovrappongono in apparenza ma richiedono interventi diversi. Usa questo test:

- **Se cambio questa regola, quanti punti devo toccare?** → problema DRY, si
  risolve estraendo la logica.
- **Se questi due punti divergessero, il sistema sarebbe incoerente?** → problema
  SSOT, si risolve individuando o creando la fonte autorevole.

Una violazione può essere entrambe le cose. In quel caso classificala come SSOT:
è la più grave, perché produce comportamenti incongruenti e non solo lavoro
duplicato.

---

## Scala di gravità

Serve a decidere quando interrompere l'utente. Senza una scala, un metodo da
rinominare e una regola di dominio duplicata in tre livelli finiscono nello stesso
elenco, e l'utente smette di leggere i report.

**Bloccante** — compromette la correttezza o rende il cambiamento rischioso:
- SSOT infranta: la stessa regola o lo stesso dato definiti in punti che possono divergere
- SRP grave: un'unità che mescola responsabilità di livelli architetturali diversi
- Logica di dominio duplicata in più moduli
- Violazione Liskov che può produrre errori a runtime

**Rilevante** — non rompe nulla oggi, ma il costo di manutenzione cresce:
- Dipendenze da implementazioni concrete dove servirebbe un'astrazione
- Interfacce monolitiche che costringono a implementazioni vuote
- Catene condizionali che crescono a ogni nuovo caso
- Astrazioni premature introdotte senza che il pattern si sia ripetuto

**Cosmetica** — attrito minore:
- Naming impreciso, micro-duplicazioni locali, costanti inline usate una volta sola

---

## Modalità A — Scrittura di nuovo codice

I principi si applicano **prima** di produrre codice, non in un secondo
passaggio. Un refactoring evitato costa meno di un refactoring fatto bene.

1. **Prima di scrivere**, individua le regole di dominio e i dati condivisi che la
   funzionalità introduce o consuma. Per ognuno, cerca la fonte autorevole
   esistente nel progetto. Se non esiste, decidi dove crearla in coerenza con
   l'architettura presente — non scrivere la logica inline nel punto di utilizzo.
2. **Applica YAGNI al requisito, non alla qualità.** Non aggiungere parametri,
   livelli o punti di estensione non richiesti. Questo non è un permesso per
   scrivere codice accoppiato: significa che l'astrazione deve essere
   proporzionata a ciò che serve adesso.
3. **Se il requisito spinge verso una violazione**, fermati prima di scrivere.
   Non è il caso di produrre codice che sai essere sbagliato per poi proporne il
   refactoring. Esponi il conflitto e proponi l'alternativa:

   > Il requisito così com'è mi porterebbe a duplicare la regola di calcolo che
   > è già definita in `X`. Posso: (a) richiamare la fonte esistente adattando
   > l'interfaccia, (b) estrarre la regola in un punto condiviso, (c) procedere
   > come richiesto accettando la duplicazione. Quale preferisci?

4. **A fine implementazione**, dichiara in due righe le scelte di progettazione
   non ovvie: dove hai messo la fonte autorevole, quali astrazioni hai
   deliberatamente evitato e perché.

---

## Modalità B — Analisi di codice esistente

### Protocollo di interruzione

La soglia esiste per non trasformare ogni sessione in una consulenza
architetturale non richiesta.

- **Violazione bloccante** → fermati subito e chiedi all'utente come procedere,
  con le tre opzioni descritte sotto.
- **Violazioni rilevanti e cosmetiche** → accumulale e presentale in un unico
  riepilogo a fine attività, su cui l'utente decide in blocco.

### Le tre opzioni

Alla rilevazione di una violazione bloccante, presenta il problema e chiedi:

1. **Intervenire ora** — procedi con il refactoring nella sessione corrente.
2. **Rimandare** — registra il debito tecnico e prosegui con l'attività
   originale. Vedi `references/protocollo-bozze.md` per il formato e la
   destinazione della bozza.
3. **Ignorare** — l'utente valuta che non sia un problema nel suo contesto.
   Accetta la decisione senza riproporla nella stessa sessione.

Non decidere al posto dell'utente e non iniziare il refactoring mentre poni la
domanda. Un refactoring non richiesto in mezzo a un'altra attività è più dannoso
della violazione che corregge.

### Formato del report

Per ogni violazione usa questa struttura:

```markdown
### [Gravità] Principio violato — posizione
**Problema:** cosa non va, in una o due frasi.
**Impatto:** cosa costa in manutenzione, evoluzione o correttezza.
**Refactoring proposto:** l'intervento concreto, con il codice quando aiuta.
**Trade-off:** cosa si perde o si rischia. Ometti la voce se non ce ne sono.
```

Regole sul contenuto:

- Indica la posizione precisa: file, classe, metodo, riga se disponibile.
- Il refactoring proposto deve **ridurre** la complessità complessiva. Se
  l'intervento aggiunge livelli, interfacce o indirezioni, giustificalo
  esplicitamente o non proporlo.
- Dichiara sempre i trade-off reali: rottura di API pubbliche, impatto sui test
  esistenti, aumento della complessità strutturale, costo di migrazione dei dati.
- Non segnalare violazioni ipotetiche basate su requisiti futuri non dichiarati.
  Sarebbe una violazione di YAGNI travestita da review.

---

## Cosa non fare

- **Non applicare i principi come regole cieche.** Sono euristiche di
  manutenibilità. In codice usa e getta, in prototipi o in script di migrazione
  una tantum, l'aderenza rigida è essa stessa over-engineering.
- **Non unificare duplicazioni accidentali.** Due frammenti simili che servono
  scopi diversi devono restare separati.
- **Non proporre refactoring a catena.** Correggi la violazione rilevata, non
  l'architettura circostante, a meno che l'utente non lo chieda.
- **Non riportare le violazioni cosmetiche come se fossero bloccanti.** Erode la
  credibilità del report e fa ignorare anche le segnalazioni che contano.
