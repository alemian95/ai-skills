# 0005. Antigravity plugin generated from the Claude Code plugins

Date: 2026-11-25

## Status

Accepted

## Context

The skills, the agents and the `guardrails` checks must also work in Google Antigravity, without breaking the Claude Code marketplace or `claude plugin validate .`.

The `SKILL.md` format is shared, but the rest differs:

- Antigravity reads `plugin.json` at the plugin root, has no dependencies between plugins and cannot add this repository as a marketplace.
- Antigravity agents need `subagent`, `mainAgent`, an explicit `tools` list with Antigravity tool names, and skill paths (`skills/<name>`). Claude Code agents use `skills: [plugin:skill]` and the Skill tool. In Claude Code, a `tools` list with Antigravity names would leave the agent without working tools, and `claude plugin validate .` does not catch it: the same agent file cannot serve both.
- Claude Code mods are TypeScript hooks modules with a command registry, a store and UI. Antigravity hooks are shell commands that read JSON on stdin and answer on stdout; a hook that fails is ignored and the call goes through. There is no way for a hook to register a command or keep state.
- Antigravity's `ask` respects "Always Allow"; only `force_ask` always prompts.

## Decision

We will generate the Antigravity plugin from the Claude Code plugins with `scripts/build-antigravity.mjs` (Node, no dependencies) and version the output in `dist/antigravity/ai-skills/`.

- `engineering`, `php`, `laravel` and `guardrails` become one plugin, `ai-skills`, because Antigravity has no dependencies between plugins. `cost` stays Claude Code only.
- The plugin carries skills, agents and hooks only, never rules: the build fails if an `AGENTS.md` or `GEMINI.md` would end up in it.
- Skills are copied unchanged except for `<plugin>:<name>` references, which lose the prefix. Names of skills and agents must be unique across the plugins.
- Agents are rewritten by the script; it fails when a Claude-only instruction it does not know survives, so a change in the source cannot go out half-converted.
- `guardrails` keeps one source of rules, `hooks/rules.ts`: the build strips its types into `rules.mjs`, used by an Antigravity `PreToolUse` hook (`plugins/guardrails/antigravity/hook.mjs`) on `run_command` and on file writes. "Ask" verdicts are sent as `force_ask`, as in Claude Code they prompt regardless of allow rules. The hook answers `force_ask` when it cannot check a call, instead of failing.
- `/git-lock` becomes a script the user runs in their own terminal, with its state in `~/.gemini/config/guardrails/git-lock.json`; the hook denies the agent running the script or touching the state. Intercepting a typed `/git-lock` with a `PreInvocation` hook was rejected: the docs do not say whether an unknown slash command reaches the hook.
- The output is versioned so that it can be installed on Windows and WSL without running the build; `--check` reports when it is behind the sources.

## Consequences

The Claude Code plugins stay the only place where skills, agents and guardrails rules are edited, and the Claude Code side is unchanged.

Every change to a skill, an agent or the guardrails rules needs a rebuild and appears twice in the diff. Forgetting the rebuild leaves `dist/` stale until `--check` is run. The build needs Node 22.13+ (`stripTypeScriptTypes`), the hook Node 18+ where Antigravity runs: without Node the hook cannot start and Antigravity lets every call through.

The rm-outside-the-project rule only understands POSIX shells: on Windows, `Remove-Item -Recurse` is not checked.

The Antigravity plugin has its own version, bumped by hand in the script.

Antigravity users get all stacks and the guardrails at once, without the choice the Claude Code marketplace gives.

The Antigravity tool names, hook payload and Windows install path come from the Antigravity documentation and were not verified on a running Antigravity install.
