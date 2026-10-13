# 0003. Long multi-phase procedures as skills with one reference per phase

Date: 2026-10-13

## Status

Accepted

## Context

`/laravel:migrate-ziggy-to-wayfinder` is an 872-line command: the whole procedure enters the context on the first invocation, even though only one phase is executed at a time.

## Decision

We will turn long procedures into skills: a short `SKILL.md` describing the phases and their order, plus one reference file per phase, read when that phase starts.

## Consequences

The context holds only the phase in progress.

The procedure is split across several files: the order of the phases and the links between them must be kept consistent by hand.
