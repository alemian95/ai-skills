# Protocollo delle bozze differite

Da leggere quando l'utente sceglie l'opzione **"rimandare"** davanti a una
violazione bloccante.

## Destinazione

Predefinita: `docs/tech-debt/` nella radice del progetto, un file per violazione,
con nome `AAAA-MM-GG-descrizione-breve.md`.

Motivo della scelta: il debito tecnico è informazione di progetto, non
preferenza personale. Nel repository è versionato, visibile al team, e sopravvive
al cambio di macchina o di strumento.

Se la cartella non esiste, chiedi conferma prima di crearla. Se il progetto ha
già un tracciamento del debito tecnico — una cartella ADR, un file
`TECHNICAL_DEBT.md`, una convenzione di commento nel codice — adeguati a quella
invece di introdurne una nuova. Sarebbe una violazione SSOT applicata alla
documentazione.

## Formato della bozza

```markdown
---
data: AAAA-MM-GG
principio: SSOT | SRP | OCP | LSP | ISP | DIP | DRY | YAGNI
gravità: bloccante | rilevante
stato: aperto
---

# Titolo sintetico

## Posizione
File, classe, metodo. Elenca tutti i punti coinvolti se la violazione è distribuita.

## Problema
Cosa non va e perché è una violazione del principio indicato.

## Impatto
Cosa costa lasciarlo così: rischio di divergenza, costo di modifica futura,
superficie di errore.

## Refactoring proposto
L'intervento concreto. Includi il codice quando chiarisce più della prosa.

## Trade-off
Costi dell'intervento: rottura di API, test da riscrivere, migrazione dati.
Ometti la sezione se non ce ne sono.

## Contesto
Perché è stato rimandato e in quale attività è emerso. Serve a chi riprende in
mano la bozza tra sei mesi.
```

## Regole operative

- **Una violazione, un file.** Bozze cumulative diventano illeggibili e nessuno
  le chiude mai.
- **Non modificare il codice** quando l'utente sceglie di rimandare. La bozza
  sostituisce l'intervento, non lo anticipa.
- **Non lasciare commenti `TODO` nel codice** in aggiunta alla bozza: sarebbero
  due fonti della stessa informazione, destinate a divergere.
- **Prima di creare una bozza, verifica se ne esiste già una** per la stessa
  violazione. In quel caso aggiornala aggiungendo il nuovo punto di occorrenza,
  non crearne una seconda.
- **Alla chiusura**, imposta `stato: risolto` e aggiungi il riferimento al commit
  che ha applicato il refactoring. Non cancellare il file: la storia delle
  decisioni ha valore.
