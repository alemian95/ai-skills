# 0002. Action/Service boundary as a reference of laravel-architecture

Date: 2026-10-13

## Status

Accepted

## Context

`laravel-action-vs-service` and `laravel-architecture` are separate skills whose descriptions match the same requests ("fat controller", "where should this logic go"), so they compete for activation. The Action/Service boundary is described in both: `laravel-architecture` and its `components.md#action` defer to the other skill but restate part of it.

An outdated copy of `laravel-action-vs-service` was also installed by hand in `~/.claude/skills/` and loaded alongside the plugin version; that copy has been removed.

## Decision

We will merge `laravel-action-vs-service` into `laravel-architecture` as a reference document, read when the choice between Controller, Action, Service, Event and Job is not obvious. Only one Laravel architecture skill will exist.

## Consequences

There is one entry point and one home for the boundary rules.

The skill `laravel:laravel-action-vs-service` disappears: references to it inside the repository (`laravel-architecture`, `components.md`, `laravel-internal-packages`, `modern-php`) must be updated, and the `laravel` plugin gets a major version bump. Anything outside the repository that names the old skill, such as a user's own instructions, stops resolving.

The detailed rules no longer trigger on their own: they are read only if the main skill points to them at the right moment.
