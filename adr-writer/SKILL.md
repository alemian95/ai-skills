---
name: adr-writer
description: Turn decisions made during an implementation or brainstorming session into Architecture Decision Records (Nygard format), reconstructing context and rationale from the conversation, from git history, and from the existing docs. Use this skill whenever the user asks to document a decision, write an ADR, record why something was chosen, capture the outcome of a design discussion, or says things like "documentiamo questa scelta", "scrivi un ADR", "teniamo traccia di questa decisione", "perché abbiamo fatto così?" — and also proactively offer it at the end of any session where a non-trivial architectural choice was settled, even if the user never says the word "ADR".
---

# ADR Writer

Reconstruct decisions that were actually made during a working session and record them as Architecture Decision Records in Nygard format.

The value of an ADR archive is that a reader six months from now can trust it. That trust breaks in two ways: recording options that were discussed but never adopted, and inventing rationale that nobody ever articulated. Both are worse than having no ADR at all, because they look authoritative. Every rule below exists to prevent one of those two failures.

## Core rule: no inference

Write only what the session, the repository, or the user actually established.

- If the decision is clear but the *reason* was never stated, do not reconstruct a plausible reason. Ask.
- If a constraint is implied but never confirmed (deadlines, client requirements, performance targets), ask.
- If an alternative was mentioned in passing with no explanation of why it lost, either ask why or leave it out.
- Never soften this by writing hedged filler ("likely because...", "presumably for maintainability"). An ADR with a gap flagged to the user is recoverable; an ADR with invented rationale is not.

Collect all gaps and ask them in a single batch before writing, not one at a time.

## Step 1 — Isolate the decision

A session usually contains several candidate decisions. Filter them.

Write an ADR when a choice is **architecturally significant**: it constrains future work, is expensive to reverse, affects more than one part of the system, or a future maintainer would reasonably ask "why is it like this?".

Do not write an ADR for: bug fixes, renamings, dependency version bumps, formatting, or anything a single commit could undo without consequence.

Then separate the decision from its surroundings:

- **Decided** — the option that was adopted. Goes in Decision.
- **Discarded** — options that were considered and rejected *with a stated reason*. Go in Context, as part of the forces at play. Nygard format has no "Considered Options" section; discarded options belong in the narrative of Context or they do not appear at all.
- **Open** — things still under discussion. These are not part of this ADR. Mention them to the user; do not smuggle them into Consequences.

One decision per ADR. If a session produced three independent decisions, produce three files and say so before writing. If a decision only makes sense as part of a larger one, it is not independent — fold it into the parent ADR.

## Step 2 — Gather evidence

Use whatever sources are available; degrade gracefully when they are not.

**The conversation.** The primary source. Look for the moment the user chose, not the moment an option was proposed. Statements from the user carry decisional weight; suggestions made by the assistant do not, unless the user accepted them.

**The repository.** When a git repo is available, use it to confirm that the decision was actually implemented and to date it:

```bash
git log --oneline -30
git diff --stat HEAD~5..HEAD          # scope of recent work
git log -S "<identifier>" --oneline   # when a specific construct appeared
git log --oneline -- <path>           # history of the affected area
```

Cite commits by short hash in Context when they establish a fact ("introdotto in `a1b2c3d`"). Do not paste diffs into the ADR — an ADR records the decision, not the implementation.

If the code contradicts what the session concluded (the decision was discussed but not implemented, or implemented differently), stop and report the discrepancy to the user. This is a common and important catch.

**The existing docs.** Read the ADR directory before writing: it establishes numbering, tone, language, and whether this decision supersedes an earlier one. Read adjacent project documentation to pick up the correct domain vocabulary — an ADR that invents its own terms for concepts the project already names is hard to search.

**Degraded mode (chat, no repository).** Work from the conversation and from anything the user pastes. Say once, briefly, which sources were unavailable, and be more aggressive about asking rather than assuming. Output the ADR as a file the user can drop into their repo, and state the filename you would have used.

## Step 3 — Write the ADR

Use this exact structure:

```markdown
# NNNN. <Titolo: sostantivo, la decisione, non il problema>

Date: YYYY-MM-DD

## Status

<Proposed | Accepted | Deprecated | Superseded by [NNNN](NNNN-slug.md)>

## Context

<The forces at play. Neutral, present tense.>

## Decision

<The choice. Active voice, "Adottiamo..." / "We will...">

## Consequences

<What becomes easier, harder, or newly required — after this is applied.>
```

**Title.** Names the decision, not the problem area. `0007. Uso di LTI 1.3 per l'autenticazione dei contenuti SCORM`, not `0007. Problemi di sicurezza SCORM`. No verbs like "Decidere di".

**Status.** `Accepted` when the decision is in force. `Proposed` when it was agreed in principle but not implemented — check the repo before choosing. If it replaces an earlier ADR, set this one to `Accepted` and update the old one to `Superseded by`; never silently delete or rewrite a superseded ADR.

**Context.** Describe the situation and the pressures that made a decision necessary — technical constraints, business constraints, what the current implementation does, what was tried. Present tense, neutral: describe facts, not the conclusion. This is where rejected alternatives live, each with the reason it lost. A reader who stops after Context should already understand why a decision was unavoidable, without yet knowing which one was taken.

**Decision.** One or two paragraphs. State what will be done, in the active voice, as a commitment. Include the specific shape of the choice (pattern, library, boundary, convention) but not implementation detail that will drift — no code, no signatures, no file lists.

**Consequences.** The section most often written badly. Report outcomes of every sign: what improves, what gets worse, what new obligations appear (migrations, work on other clients' instances, documentation to keep aligned, skills to acquire). Do not editorialize on whether the decision was good — the decision is already made. A Consequences section with only positives is a sign the ADR was written to justify rather than to record.

**Length.** One page. If Context runs past a few paragraphs, the ADR is probably covering more than one decision.

**Language.** Match the language of the existing ADRs; if there are none, match the language of the surrounding project documentation; if there is none, match the conversation. The section headings stay in English (`Status`, `Context`, `Decision`, `Consequences`) — they are the format's identity and keep the archive greppable.

## Step 4 — Place the file

`docs/adr/` is the common convention, but it is a fallback, not an assumption. Projects organize documentation in ways that are deliberate — parallel functional and technical tracks, one directory per domain, per-client separation — and an ADR dropped outside that scheme is documentation nobody finds. **Inspect the repository before deciding, every time.** Never assume the layout from a previous session.

**4a. Look for existing ADRs.** They settle the question outright:

```bash
git ls-files | grep -Ei '(adr|decision)' 
git ls-files 'docs/**' 'doc/**' | head -50
```

Also look for files matching `NNNN-*.md` anywhere — ADRs are sometimes kept without a directory named after them.

**4b. If there are none, read the project's documentation layout and replicate it.** List the docs tree and work out the local grammar:

```bash
git ls-files -- '*.md' | grep -v node_modules | head -80
```

Determine, from what is actually there:
- the root of the documentation and whether it is subdivided (by domain, by audience, by functional/technical track);
- where a cross-cutting, non-feature-specific document sits in that scheme;
- the file naming convention: separators, casing, numeric prefixes, language of the filenames;
- whether directories carry a `README.md` or `index.md` that indexes their contents.

Then place the ADRs in the position that scheme implies, following its naming style. Concretely: in a project whose docs are split into parallel tracks, ADRs are technical and belong in the technical track, in an `adr/` directory that mirrors the sibling directories' naming.

**4c. If the layout is ambiguous, or there is no documentation structure at all, ask the developer where to put them.** Propose the option that best fits what was found, explain in one line why, and wait. The first ADR fixes a convention for the whole project — that is the developer's call, not a side effect of a documentation task. The same applies when the discovered structure suggests two equally defensible positions: ask instead of picking.

Once the location is settled, state it explicitly in the final report so it is on the record for later sessions.

**Filename.** Default `NNNN-slug-del-titolo.md`: four digits, zero-padded, sequential. Take the number from the highest existing ADR, not from a count of files — gaps happen. Slug from the title, lowercase, hyphenated, no accents. Adapt casing, separator, and language to the local convention when the project has one, but keep the numeric prefix regardless: supersede references and ordering depend on it.

If an `index.md` or `README.md` lists the ADRs, or indexes the directory the ADR lands in, add the new entry. Do not create such an index unprompted.

## Step 5 — Report

After writing, tell the user in a few lines:
- the file created and its status;
- which claims came from the conversation and which from the repository;
- anything left out because it was still open, and any other decisions from the session that would deserve their own ADR.

## Example

Session: the user and the assistant work through where business logic should live, try putting orchestration in controllers, hit duplication across two entry points, and settle on Actions that call reusable Services.

Extracted:
- Decided → business orchestration lives in Actions, reusable logic in Services.
- Discarded → logic in controllers, rejected because duplicated between HTTP and console entry points.
- Open → whether Actions may call other Actions. Not in the ADR; reported to the user.
- Gap → `handle()` vs `__invoke()` was settled without a stated reason. Asked; the user answered that `handle()` allows named secondary methods. Now recordable.

Result: `docs/adr/0012-actions-per-l-orchestrazione-e-services-per-la-logica-riusabile.md`, Status `Accepted`, with `HEAD~3..HEAD` cited in Context as evidence the pattern is already applied.
