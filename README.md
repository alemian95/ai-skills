# Ai-Prompts

Marketplace di plugin per [Claude Code](https://code.claude.com): skill, comandi e agenti.

## Installazione

```
/plugin marketplace add alemian95/Ai-Prompts
/plugin install engineering@ai-prompts
/plugin install php@ai-prompts
/plugin install laravel@ai-prompts
```

Aggiornamento: `/plugin marketplace update ai-prompts`.

## Plugin

| Plugin | Contenuto |
|---|---|
| `engineering` | skill `clean-code` (SOLID, DRY, YAGNI, SSOT) · skill `adr-writer` (ADR in formato Nygard) |
| `php` | skill `php-moderno` (PHP 8.4+) · skill `php-hosting-condiviso` (hosting senza SSH, deploy FTP) |
| `laravel` | skill `laravel-action-vs-service` · comando `/laravel:migrate-ziggy-to-wayfinder` |

## Snippet per CLAUDE.md

`claude-md/` contiene regole da copiare a mano in `~/.claude/CLAUDE.md` o nel `CLAUDE.md` di progetto (un plugin non le può installare):

- `principi-sviluppo.md`: principi di sviluppo globali
- `regole-sicurezza.md`: regole sul flusso Git

## Struttura

```
.claude-plugin/marketplace.json     # elenco dei plugin
plugins/<plugin>/
  .claude-plugin/plugin.json        # manifest (nome, versione)
  skills/<skill>/SKILL.md           # skill, con eventuali references/ e assets/
  commands/<comando>.md             # slash command
  agents/<agente>.md                # subagent
```

Per aggiungere qualcosa basta creare il file nella cartella giusta di un plugin esistente e aumentare `version` nel suo `plugin.json`. Un plugin nuovo va anche registrato in `marketplace.json`.

Verifica prima del push: `claude plugin validate .`
