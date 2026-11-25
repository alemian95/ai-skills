# ai-skills

Plugin marketplace for [Claude Code](https://code.claude.com): skills, commands and agents. The skills, the agents and the `guardrails` checks are also available as a single plugin for [Google Antigravity](https://antigravity.google) ([below](#antigravity)).

## Installation

### Claude Code

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

### Antigravity

Antigravity has no marketplace for this repository and no dependencies between plugins, so `engineering`, `php`, `laravel` and `guardrails` ship as one plugin, `ai-skills`, generated in `dist/antigravity/ai-skills/` ([ADR 0005](docs/adr/0005-antigravity-plugin-generated-from-claude-code-plugins.md)). It contains:

- the 8 skills;
- the agents `developer`, `php-developer` and `laravel-developer` as subagents, with their skills preloaded;
- the `guardrails` checks as a `PreToolUse` hook on `run_command` and on file writes (`hooks.json`, `scripts/guardrails/`). Antigravity has no `/git-lock` command: lock git from your own terminal (below).

It ships no rules (`AGENTS.md`, `GEMINI.md`). `cost` is Claude Code only: it is a mod built on Claude Code's UI.

The hook needs [Node.js](https://nodejs.org) 18 or later on the `PATH` of the machine where Antigravity runs (on WSL: the side where Antigravity runs, see below). **Without Node the hook cannot start and Antigravity lets every call through**: check with `node --version`.

Clone the repository (or download it as a ZIP), then install the folder `dist/antigravity/ai-skills` with the Antigravity CLI:

```
agy plugin install <path-to-repo>/dist/antigravity/ai-skills
```

or copy it into the global plugins folder, `~/.gemini/config/plugins/` (every workspace), or into `.agents/plugins/` at the root of a project (that project only). Then restart Antigravity, or reload the plugins.

**Windows** (PowerShell, from the repository folder):

```powershell
New-Item -ItemType Directory -Force "$env:USERPROFILE\.gemini\config\plugins" | Out-Null
Copy-Item -Recurse -Force dist\antigravity\ai-skills "$env:USERPROFILE\.gemini\config\plugins\"
```

**WSL**: where the plugin goes depends on where Antigravity runs, not on where the repository is cloned.

- Antigravity installed on Windows (the app, also with a workspace opened in WSL): use the Windows folder. From the WSL shell:

  ```bash
  WIN_HOME="$(wslpath "$(cmd.exe /c 'echo %USERPROFILE%' 2>/dev/null | tr -d '\r')")"
  mkdir -p "$WIN_HOME/.gemini/config/plugins"
  cp -r dist/antigravity/ai-skills "$WIN_HOME/.gemini/config/plugins/"
  ```

- `agy` installed inside the WSL distribution: it is a Linux install, use `agy plugin install` or `~/.gemini/config/plugins/` inside WSL.

The Antigravity docs show the global folder only as `~/.gemini/config/plugins/`: on Windows `~` is `%USERPROFILE%`. If the plugin does not show up, check the folder in the Antigravity settings or run `agy plugin list`.

**Git lock.** `guardrails` denies git and `gh` writes in a locked project. Only you can change the lock: the hook denies the script and its state file (`~/.gemini/config/guardrails/git-lock.json`) to the agent. From the project folder, in your own terminal:

```bash
node ~/.gemini/config/plugins/ai-skills/scripts/guardrails/git-lock.mjs on
```

`off` unlocks, `status` shows the state; an optional second argument names the project folder instead of the current one. A lock covers the subfolders too. On Windows (PowerShell): `node "$env:USERPROFILE\.gemini\config\plugins\ai-skills\scripts\guardrails\git-lock.mjs" on`. If you installed with `agy plugin install`, the script is in the folder `agy plugin list` shows.

Differences from Claude Code: an "ask" is sent as `force_ask`, so it prompts even when the command is in "Always Allow"; in PowerShell `rm -rf` outside the project is not recognized (`Remove-Item -Recurse` is not checked); the git, database, deploy and migration checks are the same.

Update: `git pull`, then run `agy plugin install` again (an installed copy is read-only and replaced on update), or delete the copied `ai-skills` folder and copy it again, so skills removed upstream do not linger.

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
| `engineering` | skill `clean-code` (SOLID, DRY, YAGNI, SSOT) · skill `adr-writer` (Nygard-format ADRs) · skill `brainstorming` (from a rough idea to a non-technical project spec, one question at a time, no code) · agent `developer` (implements and reviews code with `clean-code` preloaded) |
| `php` | skill `modern-php` (modern PHP 8.4+) · skill `php-shared-hosting` (shared hosting without SSH, FTP deploy) · agent `php-developer` (`clean-code` + `modern-php` preloaded). Requires `engineering` |
| `laravel` | skill `laravel-architecture` (layers, modules, best practices per framework component; complements Laravel Boost) with the Action vs Service boundary and Larastan up to level 10 as references · skill `laravel-internal-packages` (optional features as in-repo packages the core never names; Inertia React/Vue/Svelte, Blade, Livewire, Filament) · skill `/laravel:migrate-ziggy-to-wayfinder` (one reference per phase) · agent `laravel-developer` (`clean-code` + `modern-php` + `laravel-architecture` preloaded; verifies with PHPStan at the project level and proposes raising it). Requires `engineering` and `php` |
| `cost` | Claude Code only. mod: the session's API-equivalent cost (e.g. `$1.26`, list price, as `/cost` totals it) above the prompt, also on a subscription plan; click it for cost and tokens per model (subagents apart), estimated from a list-price table in `hooks/prices.ts` |
| `guardrails` | mod: `/git-lock on\|off\|status` blocks git and `gh` writes in that project (kept across sessions) · denies edits to migrations already in git, asks before new migrations and seeder changes · asks before `migrate:fresh/reset/rollback/refresh`, `db:wipe`, `DROP`/`TRUNCATE` and `rm -rf` outside the project · asks before commit/merge/rebase/cherry-pick on protected branches (`main`, `master`, `develop`, `stage`, `staging`, `prod`, `production`), `push`/`fetch`/`pull`, deploy and publish commands, `DELETE FROM` without `WHERE` |

## CLAUDE.md snippets

`claude-md/` contains rules to copy by hand into `~/.claude/CLAUDE.md` or a project's `CLAUDE.md` (a Claude Code plugin cannot install them):

- `development-principles.md`: global development principles (not shipped to Antigravity)

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
plugins/guardrails/antigravity/     # guardrails as an Antigravity hook, on the same rules.ts (`node --test plugins/guardrails/antigravity/hook.test.mjs`)
docs/adr/                           # architecture decision records
scripts/build-antigravity.mjs       # generates dist/antigravity/ai-skills (Node 22.13+, no dependencies)
dist/antigravity/ai-skills/         # generated Antigravity plugin: do not edit by hand
```

To add something, create the file in the right folder of an existing plugin and bump `version` in its `plugin.json`. A decision with a lasting impact on the repository's structure gets an ADR in `docs/adr/`. A new plugin must also be registered in `marketplace.json`.

After changing a skill, an agent, `plugins/guardrails/hooks/rules.ts`, `plugins/guardrails/antigravity/` or the list of plugins, regenerate the Antigravity plugin and bump `version` in `MANIFEST` in `scripts/build-antigravity.mjs`:

```
node scripts/build-antigravity.mjs
```

Agents must stay agent-neutral where they can (no "Claude", no "Skill tool"): the script rewrites the agent instructions it knows and fails on anything Claude-only it does not.

Check before pushing:

```
claude plugin validate .
node scripts/build-antigravity.mjs --check
node --test scripts/build-antigravity.test.mjs plugins/guardrails/antigravity/hook.test.mjs
```
