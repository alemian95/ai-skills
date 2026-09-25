# ai-skills

**English** · [Italiano](README.it.md)

Plugin marketplace for [Claude Code](https://code.claude.com): skills, commands and agents.

> The skill and command content is written in Italian. Claude follows it regardless of the language you work in.

## Installation

```
/plugin marketplace add alemian95/ai-skills
/plugin install engineering@ai-skills
/plugin install php@ai-skills
/plugin install laravel@ai-skills
```

Update: `/plugin marketplace update ai-skills`.

## Plugins

| Plugin | Content |
|---|---|
| `engineering` | skill `clean-code` (SOLID, DRY, YAGNI, SSOT) · skill `adr-writer` (Nygard-format ADRs) |
| `php` | skill `php-moderno` (modern PHP 8.4+) · skill `php-hosting-condiviso` (shared hosting without SSH, FTP deploy) |
| `laravel` | skill `laravel-action-vs-service` · command `/laravel:migrate-ziggy-to-wayfinder` |

## CLAUDE.md snippets

`claude-md/` contains rules to copy by hand into `~/.claude/CLAUDE.md` or a project's `CLAUDE.md` (a plugin cannot install them):

- `principi-sviluppo.md`: global development principles
- `regole-sicurezza.md`: Git workflow rules
- `laravel-architettura.md`: architecture of a Laravel + React project, meant to be used with [Laravel Boost](https://github.com/laravel/boost). Don't paste it into `CLAUDE.md`, because Boost regenerates that file: copy it to the project's `.ai/guidelines/architettura.md` and run `php artisan boost:update` (or `boost:install`). Boost merges it with its own guidelines.

## Structure

```
.claude-plugin/marketplace.json     # plugin list
plugins/<plugin>/
  .claude-plugin/plugin.json        # manifest (name, version)
  skills/<skill>/SKILL.md           # skill, with optional references/ and assets/
  commands/<command>.md             # slash command
  agents/<agent>.md                 # subagent
```

To add something, create the file in the right folder of an existing plugin and bump `version` in its `plugin.json`. A new plugin must also be registered in `marketplace.json`.

Check before pushing: `claude plugin validate .`
