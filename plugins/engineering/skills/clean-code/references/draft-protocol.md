# Deferred drafts protocol

Read this when the user chooses the **"defer"** option in response to a
blocking violation.

## Destination

Default: `docs/tech-debt/` in the project root, one file per violation, named
`YYYY-MM-DD-short-description.md`.

Reason for this choice: technical debt is project information, not personal
preference. In the repository it is versioned, visible to the team, and
survives a change of machine or tool.

If the folder does not exist, ask for confirmation before creating it. If the
project already tracks technical debt — an ADR folder, a `TECHNICAL_DEBT.md`
file, a code comment convention — adapt to that instead of introducing a new
one. Doing otherwise would be an SSOT violation applied to documentation.

## Draft format

```markdown
---
date: YYYY-MM-DD
principle: SSOT | SRP | OCP | LSP | ISP | DIP | DRY | YAGNI
severity: blocking | significant
status: open
---

# Concise title

## Location
File, class, method. List all the points involved if the violation is spread out.

## Problem
What is wrong and why it is a violation of the stated principle.

## Impact
What it costs to leave it as is: risk of divergence, cost of future changes,
error surface.

## Proposed refactoring
The concrete intervention. Include code when it clarifies more than prose.

## Trade-offs
Costs of the intervention: API breakage, tests to rewrite, data migration.
Omit the section if there are none.

## Context
Why it was deferred and during which task it emerged. It serves whoever picks
the draft up again six months from now.
```

## Operating rules

- **One violation, one file.** Cumulative drafts become unreadable and nobody
  ever closes them.
- **Do not modify the code** when the user chooses to defer. The draft replaces
  the intervention; it does not anticipate it.
- **Do not leave `TODO` comments in the code** in addition to the draft: they
  would be two sources of the same information, bound to diverge.
- **Before creating a draft, check whether one already exists** for the same
  violation. In that case update it by adding the new occurrence, do not create
  a second one.
- **On closure**, set `status: resolved` and add a reference to the commit that
  applied the refactoring. Do not delete the file: the history of decisions has
  value.
