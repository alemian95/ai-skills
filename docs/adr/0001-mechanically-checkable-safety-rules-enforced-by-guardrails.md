# 0001. Mechanically checkable safety rules enforced by the guardrails mod

Date: 2026-10-13

## Status

Accepted

## Context

The same git, database and deploy rules are written in several places: `claude-md/git-safety-rules.md`, an optional snippet to copy by hand into a CLAUDE.md; and the `guardrails` mod (`b8023c2`), which blocks git writes in locked projects, protects migrations already tracked by git and asks before destructive database and file commands.

A prose rule is followed most of the time, while a hook is applied on every call. The copies have already diverged: the snippet asks Claude to create working branches and commit on them, while `/git-lock` forbids every git write in locked projects.

## Decision

We will move every rule that a command or file path can verify into `guardrails` hooks, with tests. Commits, merges and rebases on protected branches, remote git operations (push, fetch, pull), deploy and publish commands, and `DELETE` without `WHERE` will ask for confirmation. The existing deny for git writes in locked projects stays as it is.

The plugins never rely on a CLAUDE.md being present: a rule that must hold is enforced by the mod. `git-safety-rules.md` will be removed.

## Consequences

These rules are enforced on every call and no longer depend on the model following them.

Detection is regex-based: it misses some forms (aliases, scripts, a `cd` in the middle of a command chain) and may raise unnecessary confirmations.

Users of the repository who copied the snippet lose the prose version and have to install `guardrails` to keep the protection.

Every new rule needs a test in the mod's test suite.
