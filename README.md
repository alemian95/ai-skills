# ai-skills

Plugin marketplace for [Claude Code](https://code.claude.com): skills, commands and agents.

## Installation

```
/plugin marketplace add alemian95/ai-skills
/plugin install engineering@ai-skills
/plugin install php@ai-skills
/plugin install laravel@ai-skills
/plugin install cost@ai-skills
/plugin install guardrails@ai-skills
```

Update: `/plugin marketplace update ai-skills`, then `/plugin update <plugin>@ai-skills` for each installed plugin (refreshing the marketplace does not update the installed plugins).

Pre-release: pick a tag from the [releases](https://github.com/alemian95/ai-skills/releases), add `"ref": "<tag>"` to the `source` of `ai-skills` under `extraKnownMarketplaces` in `~/.claude/settings.json` (`/plugin marketplace add` declares it there), then:

```
/plugin marketplace add alemian95/ai-skills#<tag>
/plugin update guardrails@ai-skills
```

Use the same source the declaration lists (the `owner/repo` shorthand or the git URL) and update each installed plugin. To go back to stable, remove `ref`, run `/plugin marketplace add alemian95/ai-skills` and update the plugins again.

## What's new in 2.0

`engineering`, `php`, `laravel` and `guardrails` are released together as `2.0.0`.

**New**

- Agents with their rules preloaded, for implementation and review: `developer` (`engineering`), `php-developer` (`php`), `laravel-developer` (`laravel`). When a decision belongs to the user they stop and return the question instead of choosing ([ADR 0004](docs/adr/0004-code-work-delegated-to-subagents-with-preloaded-skills.md)).
- `guardrails` asks before commit, merge, rebase and cherry-pick on protected branches, `push`/`fetch`/`pull`, deploy and publish commands, and `DELETE FROM` without `WHERE`. Git global options, multi-line commands, `cd`/`-C` and search commands (`grep`, `rg`, `git log`...) are taken into account ([ADR 0001](docs/adr/0001-mechanically-checkable-safety-rules-enforced-by-guardrails.md)).
- `clean-code` has a protocol for running as a subagent.

**Breaking changes**

- The skill `laravel:laravel-action-vs-service` is gone: its content is a reference of `laravel:laravel-architecture`, which now also triggers on its cases ([ADR 0002](docs/adr/0002-action-service-boundary-as-reference-of-laravel-architecture.md)). Update anything that names the old skill.
- `/laravel:migrate-ziggy-to-wayfinder` is a skill with one reference per phase instead of a single command; the name is unchanged ([ADR 0003](docs/adr/0003-long-procedures-as-skills-with-one-reference-per-phase.md)).
- `php` now requires `engineering`; `laravel` requires `engineering` and `php`. Installing them installs the dependencies too.
- The `claude-md/git-safety-rules.md` snippet is removed: install `guardrails` instead.

## Plugins

| Plugin | Content |
|---|---|
| `engineering` | skill `clean-code` (SOLID, DRY, YAGNI, SSOT) · skill `adr-writer` (Nygard-format ADRs) · agent `developer` (implements and reviews code with `clean-code` preloaded) |
| `php` | skill `modern-php` (modern PHP 8.4+) · skill `php-shared-hosting` (shared hosting without SSH, FTP deploy) · agent `php-developer` (`clean-code` + `modern-php` preloaded). Requires `engineering` |
| `laravel` | skill `laravel-architecture` (layers, modules, best practices per framework component; complements Laravel Boost) with the Action vs Service boundary as a reference · skill `laravel-internal-packages` (optional features as in-repo packages the core never names; Inertia React/Vue/Svelte, Blade, Livewire, Filament) · skill `/laravel:migrate-ziggy-to-wayfinder` (one reference per phase) · agent `laravel-developer` (`clean-code` + `modern-php` + `laravel-architecture` preloaded). Requires `engineering` and `php` |
| `cost` | mod: the session's API-equivalent cost (e.g. `$1.26`, list price, as `/cost` totals it) above the prompt, also on a subscription plan; click it for cost and tokens per model (subagents apart), estimated from a list-price table in `hooks/prices.ts` |
| `guardrails` | mod: `/git-lock on\|off\|status` blocks git and `gh` writes in that project (kept across sessions) · denies edits to migrations already in git, asks before new migrations and seeder changes · asks before `migrate:fresh/reset/rollback/refresh`, `db:wipe`, `DROP`/`TRUNCATE` and `rm -rf` outside the project · asks before commit/merge/rebase/cherry-pick on protected branches (`main`, `master`, `develop`, `stage`, `staging`, `prod`, `production`), `push`/`fetch`/`pull`, deploy and publish commands, `DELETE FROM` without `WHERE` |

## CLAUDE.md snippets

`claude-md/` contains rules to copy by hand into `~/.claude/CLAUDE.md` or a project's `CLAUDE.md` (a plugin cannot install them):

- `development-principles.md`: global development principles

Git, database and deploy safety rules are enforced by the `guardrails` mod ([ADR 0001](docs/adr/0001-mechanically-checkable-safety-rules-enforced-by-guardrails.md)).

## Structure

```
.claude-plugin/marketplace.json     # plugin list
plugins/<plugin>/
  .claude-plugin/plugin.json        # manifest (name, version)
  skills/<skill>/SKILL.md           # skill, with optional references/ and assets/
  commands/<command>.md             # slash command
  agents/<agent>.md                 # subagent
  hooks/hooks.json, hooks/*.ts      # mod: hooks module, with *.test.ts (`claude plugin test plugins/<plugin>`)
docs/adr/                           # architecture decision records
```

To add something, create the file in the right folder of an existing plugin and bump `version` in its `plugin.json`. A decision with a lasting impact on the repository's structure gets an ADR in `docs/adr/`. A new plugin must also be registered in `marketplace.json`.

Check before pushing: `claude plugin validate .`
