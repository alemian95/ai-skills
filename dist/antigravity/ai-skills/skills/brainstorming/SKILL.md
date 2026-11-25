---
name: brainstorming
description: Run a brainstorming session that takes a rough idea to a project specification of what and why, one question at a time, without writing code or editing files. Use when the user wants to brainstorm, think an idea through, weigh options before deciding what to build, check whether an idea is feasible, or turn a vague goal into a spec ("let's brainstorm", "I have an idea", "help me think about", "is this doable?", "what are my options?"). Not for implementing something already decided.
---

# Brainstorming

A thinking-partner session: move the user from a rough idea to a project specification. The spec says what the project must achieve and why; how it is built is a later design step.

## Ground rules

- **Read-only until the spec is agreed.** Read code, docs and the web freely to check what is realistic. Files are created only in the wrap-up, on the user's yes.
- **One question per turn.** Ask the question whose answer most changes what comes next and hold the rest. Two only when they are inseparable.
- **Candid partner.** When an idea has a weak spot, name it as soon as you see it, with a concrete case where it breaks. Agreement is earned by the idea.
- **Concrete scenarios.** Ground every judgement in a realistic example: who does what, when, and what happens next.
- **What and why, in plain words.** Talk in behaviours, outcomes and constraints, in the words the project's users and stakeholders would use. Technology, architecture and implementation choices belong to the design step that follows. When a technical fact decides feasibility (a regulation, a hard limit, a cost), state it as a constraint in plain terms. When the user brings technical detail, discuss it, then record only the constraint it implies.

## Phases

Announce each phase as you enter it and get the user's go before the next. The user can go back to an earlier phase at any time.

### 1. Frame

Find out the problem, who has it, why now, and what "it worked" looks like. When a solution comes up, log it in the ledger as a candidate for Diverge and return to the problem.

Done when the user confirms a one- or two-sentence problem statement and a success criterion someone could check.

### 2. Diverge

Lay out 2–4 options that differ in approach, not just in detail. Always include the smallest version that could work, and "do nothing / solve it another way" when credible. Invite the user's own options. Per option give strengths and open questions only: verdicts wait for Evaluate.

Done when the user picks the options to evaluate.

### 3. Evaluate

For each option kept:

- walk one realistic scenario end to end;
- name the hardest part and its cost (time, money, people, risk);
- give a feasibility verdict with your confidence (high / medium / low) and the assumptions it rests on. Check now the assumptions you can check (read the code, search the web).

When an option fails, propose the variant that would survive, if there is one.

Done when the user chooses an option or a combination.

### 4. Specify

Write the specification in the conversation:

- **Context and problem**, from Frame
- **Goals**, each with a success criterion someone could check
- **Users and stakeholders**, and what each needs from the project
- **Scope**: what is in, and what is explicitly out
- **Requirements**: atomic, grouped by area, each tagged functional / non-functional / constraint, with the dependencies between them. Each states a behaviour or a quality, in plain words.
- **Key scenarios**: 2–3 realistic walkthroughs of the project in use
- **Chosen approach** at concept level, plus the discarded options and why
- **Assumptions and risks**
- **Open questions**

Done when every requirement traces back to a goal, every goal has at least one requirement, and the user accepts the spec.

## Ledger

Keep a ledger with three lists: **decided**, **discarded** (with the reason), **open**. Show it at every phase change and whenever the user asks. The ledger is the session's memory: Specify builds the spec from it.

## Wrap-up

Once the spec is accepted, offer once to save it to a file (ask where). Act only on a yes. The spec is the input for the technical design: when the user moves on to it, the brainstorming ends.
