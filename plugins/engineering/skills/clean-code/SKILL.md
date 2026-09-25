---
name: clean-code
description: >
  Applies SOLID, DRY, YAGNI and SSOT when writing new code and when analyzing
  existing code, in any language or framework. Use this skill whenever the user
  asks to implement a non-trivial feature, class, module, service or component —
  even if they do not explicitly name the design principles. Also use it for
  reviews, refactoring, architectural audits, opinions on maintainability or code
  quality, and when the user points out code smells: classes that do too many
  things, repeated logic, scattered constants, dependencies on concrete
  implementations, nested conditionals, premature abstractions. Typical phrases
  that must trigger it: "implementa X", "scrivi la classe che gestisce Y",
  "aggiungi la funzionalità Z", "rivedi questo codice", "si può semplificare?",
  "questa funzione fa troppe cose", "c'è duplicazione?", "è ben strutturato?",
  "come organizzeresti questo modulo?".
---

# Clean Code — SOLID, DRY, YAGNI, SSOT

This skill governs two distinct activities: **writing new code** and
**analyzing existing code**. The principles are the same; the moment at which
they apply and the way the user is involved are not.

First of all, determine which of the two modes you are in, then follow the
corresponding path. If the task is mixed (implementing a feature inside code
that already exists), apply both: the implementation mode for the new code, the
analysis mode for whatever you touch.

For the full detail of the principles, read `references/principles.md`. The
summary below is enough for everyday use; consult the reference when you need
to justify a violation to the user or when the classification is uncertain.

---

## The four principles in operational form

### SOLID

| | Question to ask | Symptom of violation |
|---|---|---|
| **S** Single Responsibility | How many distinct reasons does this unit have to change? | The name contains "and"/"Manager"/"Utils"; the class imports from unrelated domains |
| **O** Open/Closed | To add a case, do I have to modify existing code? | Chains of `if`/`switch` on the type that grow with every requirement |
| **L** Liskov Substitution | Does the subtype honor the base type's contract? | Inherited methods that throw "not supported"; strengthened preconditions |
| **I** Interface Segregation | Does the client use all the methods the interface imposes on it? | Implementations full of empty methods or stubs |
| **D** Dependency Inversion | Does this unit name a concrete class that might change? | Direct instantiation of dependencies inside business logic |

### DRY

Every piece of logic has a single authoritative representation. Watch out for
*accidental* duplication: similar code with different purposes must not be
unified, because unification creates coupling between independent requirements
that later diverge.

### YAGNI

Implement only the current requirement. Premature abstractions are technical
debt: introduce an abstraction when the pattern has repeated at least twice,
not when you imagine it will repeat.

### SSOT

Every domain rule, shared piece of data and constant has a single authoritative
origin. Other places reference it; they do not redefine it.

### Telling DRY apart from SSOT

They appear to overlap but call for different interventions. Use this test:

- **If I change this rule, how many places do I have to touch?** → DRY problem,
  solved by extracting the logic.
- **If these two places diverged, would the system be inconsistent?** → SSOT
  problem, solved by identifying or creating the authoritative source.

A violation can be both. In that case classify it as SSOT: it is the more
serious one, because it produces inconsistent behavior and not just duplicated
work.

---

## Severity scale

It is used to decide when to interrupt the user. Without a scale, a method to
rename and a domain rule duplicated across three layers end up in the same
list, and the user stops reading the reports.

**Blocking** — compromises correctness or makes change risky:
- SSOT broken: the same rule or the same data defined in places that can diverge
- Severe SRP violation: a unit that mixes responsibilities from different architectural layers
- Domain logic duplicated across multiple modules
- Liskov violation that can produce runtime errors

**Significant** — breaks nothing today, but maintenance cost grows:
- Dependencies on concrete implementations where an abstraction is needed
- Monolithic interfaces that force empty implementations
- Conditional chains that grow with every new case
- Premature abstractions introduced before the pattern has repeated

**Cosmetic** — minor friction:
- Imprecise naming, local micro-duplications, inline constants used only once

---

## Mode A — Writing new code

The principles apply **before** producing code, not in a second pass. A
refactoring avoided costs less than a refactoring done well.

1. **Before writing**, identify the domain rules and shared data that the
   feature introduces or consumes. For each one, look for the existing
   authoritative source in the project. If it does not exist, decide where to
   create it consistently with the current architecture — do not write the
   logic inline at the point of use.
2. **Apply YAGNI to the requirement, not to quality.** Do not add parameters,
   layers or extension points that were not requested. This is not permission
   to write coupled code: it means the abstraction must be proportionate to
   what is needed now.
3. **If the requirement pushes toward a violation**, stop before writing. Do
   not produce code you know is wrong only to then propose refactoring it.
   Expose the conflict and propose the alternative:

   > The requirement as it stands would lead me to duplicate the calculation
   > rule that is already defined in `X`. I can: (a) reference the existing
   > source by adapting the interface, (b) extract the rule into a shared
   > place, (c) proceed as requested, accepting the duplication. Which do you
   > prefer?

4. **At the end of the implementation**, state in two lines the non-obvious
   design choices: where you put the authoritative source, which abstractions
   you deliberately avoided and why.

---

## Mode B — Analyzing existing code

### Interruption protocol

The threshold exists so that every session does not turn into unrequested
architectural consulting.

- **Blocking violation** → stop immediately and ask the user how to proceed,
  with the three options described below.
- **Significant and cosmetic violations** → collect them and present them in a
  single summary at the end of the task, on which the user decides as a whole.

### The three options

When you detect a blocking violation, present the problem and ask:

1. **Act now** — proceed with the refactoring in the current session.
2. **Defer** — record the technical debt and continue with the original task.
   See `references/draft-protocol.md` for the format and destination of the
   draft.
3. **Ignore** — the user judges that it is not a problem in their context.
   Accept the decision without raising it again in the same session.

Do not decide on the user's behalf and do not start the refactoring while you
are asking the question. An unrequested refactoring in the middle of another
task is more harmful than the violation it fixes.

### Report format

For each violation use this structure:

```markdown
### [Severity] Violated principle — location
**Problem:** what is wrong, in one or two sentences.
**Impact:** what it costs in maintenance, evolution or correctness.
**Proposed refactoring:** the concrete intervention, with code when it helps.
**Trade-offs:** what is lost or risked. Omit this item if there are none.
```

Rules on content:

- Give the precise location: file, class, method, line if available.
- The proposed refactoring must **reduce** overall complexity. If the
  intervention adds layers, interfaces or indirection, justify it explicitly or
  do not propose it.
- Always state the real trade-offs: breaking public APIs, impact on existing
  tests, increased structural complexity, data migration cost.
- Do not report hypothetical violations based on undeclared future
  requirements. That would be a YAGNI violation disguised as a review.

---

## What not to do

- **Do not apply the principles as blind rules.** They are maintainability
  heuristics. In throwaway code, prototypes or one-off migration scripts, rigid
  adherence is itself over-engineering.
- **Do not unify accidental duplication.** Two similar fragments that serve
  different purposes must stay separate.
- **Do not propose chain refactorings.** Fix the detected violation, not the
  surrounding architecture, unless the user asks for it.
- **Do not report cosmetic violations as if they were blocking.** It erodes the
  report's credibility and causes even the findings that matter to be ignored.
