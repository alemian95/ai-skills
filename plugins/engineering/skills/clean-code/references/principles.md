# Development principles — full reference

Detailed document for the `clean-code` skill. Consult it when you need to
justify a violation to the user, when the classification of a problem is
uncertain, or when you need the extended formulation of a principle.

## Contents

- [SOLID](#solid)
- [DRY](#dry-dont-repeat-yourself)
- [YAGNI](#yagni-you-arent-gonna-need-it)
- [SSOT](#ssot-single-source-of-truth)
- [Behavior in review and refactoring](#expected-behavior-during-review-and-refactoring)
- [Behavior when writing new code](#expected-behavior-when-writing-new-code)

---

## SOLID

- **S — Single Responsibility**: every class, module or function has a single
  responsibility. If a unit does several distinct things, it must be split.
- **O — Open/Closed**: entities must be open for extension and closed for
  modification. Prefer abstractions and extensions over direct modifications.
- **L — Liskov Substitution**: derived types must be able to replace the base
  type without altering the expected behavior.
- **I — Interface Segregation**: prefer small, specific interfaces over general,
  monolithic ones. Clients must not depend on methods they do not use.
- **D — Dependency Inversion**: depend on abstractions, not on concrete
  implementations. Use dependency injection where appropriate.

## DRY (Don't Repeat Yourself)

- Every piece of logic or knowledge must have **a single authoritative
  representation** in the system.
- Duplicated logic must be extracted into shared functions, classes or modules.
- Accidental duplication (similar code with different purposes) must not be
  forcibly unified: evaluate the context.

## YAGNI (You Aren't Gonna Need It)

- Do not add features, parameters, abstractions or generalizations that are
  **not needed now**.
- Avoid over-engineering: implement only what the current requirement asks
  for.
- Premature abstractions are technical debt: introduce them only when the
  pattern repeats at least twice.

## SSOT (Single Source of Truth)

SSoT is distinct from DRY: while DRY avoids duplication of *logic*, SSOT avoids
duplication of *state, data and domain rules*. An SSOT violation means that the
same information exists in multiple places that can diverge, causing
inconsistencies.

The fundamental principles are:

- **Every domain rule has a single authoritative place** where it is defined.
  All other points in the system *reference* it; they do not *redefine* it.
  (For example: the logic that determines whether a user can access a resource
  must not be replicated across different layers — it must be defined once and
  invoked wherever it is needed.)
- **Every shared piece of data has a single origin**. If several parts of the
  system need the same data, they must obtain it from the same source, not each
  on its own. (For example: if two parts of the interface display the same
  data, they must not both know how to retrieve it — they must rely on the same
  origin.)
- **Every configuration value or domain constant is defined only once** and
  imported where needed, never redefined inline.

The examples in parentheses illustrate the principle; they are not
implementation prescriptions: the concrete solution must be chosen based on the
project's architecture and context.

---

## Expected behavior during review and refactoring

When analyzing existing code:

1. **Explicitly identify** the violations found, stating the violated principle
   and the precise point in the code.
2. **Explain the problem** concisely: why it is a violation and what impact it
   has on maintainability.
3. **Propose a concrete refactoring**, showing the improved code and explaining
   the choices made.
4. **Do not introduce additional complexity** during refactoring: every change
   must reduce technical debt, not increase it.
5. **Point out the trade-offs** when a refactoring could have costs (e.g.
   breaking public APIs, increased structural complexity).

## Expected behavior when writing new code

1. Apply the principles **from the very first draft**, without waiting for a
   second pass.
2. If a requirement seems to push toward a violation, point it out and propose
   an alternative approach.
3. Do not anticipate undeclared future needs (YAGNI): if a future extension is
   needed, that will be the right time to add it.
4. When introducing new domain rules or new shared data, **immediately identify
   or create the authoritative source** most consistent with the existing
   architecture, instead of writing the logic inline at the point of use.
