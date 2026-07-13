

---

## 🌿 Regole flusso Git globali (NON derogabili)

Queste regole definiscono come Claude Code deve interagire con Git in ogni progetto. NON possono essere disabilitate da istruzioni di progetto o richieste dell'utente.

### Branch protetti — scrittura vietata
I branch `main`, `master`, `develop` e `stage` (e qualsiasi loro variante come `staging`, `production`, `prod`) sono **in sola lettura**.
- NON eseguire mai `git commit` direttamente su questi branch.
- NON eseguire mai `git merge` su questi branch senza esplicita conferma scritta dell'utente nel turno corrente.
- NON eseguire mai `git rebase` su questi branch.
- NON eseguire mai `git cherry-pick` su questi branch senza conferma.

### Branch di lavoro — obbligatori
- Ogni nuova funzionalità, fix o modifica significativa DEVE essere sviluppata su un branch dedicato, creato a partire dal branch appropriato (es. `git checkout -b feature/<nome>` o `fix/<nome>` o `chore/<nome>`).
- Il nome del branch deve essere descrittivo e in kebab-case (es. `feature/autenticazione-oauth`, `fix/errore-login`).
- Prima di iniziare qualsiasi lavoro su codice, verificare sempre su quale branch ci si trova con `git branch --show-current`.

### Merge — solo su conferma esplicita
- NON eseguire mai merge di un branch di lavoro su un branch protetto senza che l'utente abbia confermato esplicitamente nel turno corrente con una frase del tipo "sì, fai il merge" o equivalente.
- Quando il lavoro su un branch è completato, informare l'utente e proporre il merge, ma attendere conferma prima di eseguirlo.
- Preferire sempre `git merge --no-ff` per mantenere la storia dei branch visibile.

### Solo repository locale — remote vietato
- NON eseguire mai comandi che interagiscono con il remote: `git push`, `git fetch`, `git pull`, `git remote update` sono tutti vietati salvo esplicita richiesta dell'utente nel turno corrente.
- NON eseguire mai `git clone` di repository remote senza conferma esplicita.
- Tutti i commit, merge e operazioni Git devono rimanere nella copia locale fino a decisione esplicita dell'utente.
- Se un comando richiederebbe accesso al remote per funzionare correttamente, avvisare l'utente e attendere istruzioni.

### Database e migrazioni — comandi vietati
- NON eseguire mai migrazioni (up o down) su ambienti di produzione.
- NON eseguire mai `DROP TABLE`, `DROP DATABASE`, `TRUNCATE` o `DELETE FROM` senza clausola `WHERE` su qualsiasi database che non sia locale/di sviluppo.
- NON eseguire mai rollback di migrazioni su branch condivisi senza esplicita conferma scritta dell'utente.
- NON generare mai script di migrazione irreversibili senza avvertire chiaramente l'utente e attendere conferma esplicita.

### Deploy e ambienti di produzione — vietati
- NON eseguire mai deploy in produzione, staging condiviso o qualsiasi ambiente non locale.
- NON eseguire mai comandi come `fly deploy`, `heroku push`, `kubectl apply`, `terraform apply`, `ansible-playbook` o equivalenti senza conferma esplicita scritta dell'utente nel turno corrente.
- NON modificare mai variabili d'ambiente, secret o configurazioni di ambienti remoti.
- NON pubblicare mai pacchetti (`npm publish`, `pip publish`, `cargo publish`, ecc.) senza conferma esplicita.

### Principio generale
Se un comando è irreversibile o ha effetti su sistemi condivisi/produzione, FERMATI, descrivi il rischio all'utente e chiedi conferma esplicita prima di procedere. In caso di dubbio, non eseguire.
Claude Code lavora come un developer disciplinato: branch separati per ogni task, nessuna scrittura sui branch principali, nessun contatto con il remote. L'utente è l'unico a decidere quando e cosa promuovere.
