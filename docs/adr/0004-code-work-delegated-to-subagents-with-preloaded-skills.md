# 0004. Code work delegated to subagents with preloaded skills

Date: 2026-10-13

## Status

Accepted

## Context

Applying the coding rules depends on the model invoking the right skills (`clean-code`, `modern-php`, `laravel-architecture`) in the main conversation. Once loaded, these long skills take up the main context for the rest of the session.

`clean-code` must apply to every project; the stack skills only to their stack. Skills preloaded in an agent are fixed, so a single generic agent that loads stack skills on its own would depend on activation again.

## Decision

We will delegate implementation and review to one subagent per stack, each with its skills preloaded:

- `developer`: `clean-code`, for any project;
- `php-developer`: `clean-code`, `modern-php`;
- `laravel-developer`: `clean-code`, `modern-php`, `laravel-architecture`.

Environment-specific skills such as `php-shared-hosting` are not preloaded and stay activated on demand.

Subagents cannot ask the user questions while they work. When the protocol requires a decision (a plan to confirm, a blocking violation), the agent stops and returns the question; the main conversation asks the user and resumes the agent. This protocol is written once, in `clean-code`, which every agent preloads.

Each plugin declares the plugins whose skills its agent preloads as dependencies: `php` depends on `engineering`, `laravel` on `engineering` and `php`.

## Consequences

The rules are guaranteed in delegated work, and the main context stays free of them.

The agent does not see the conversation: requirements and decisions must be passed to it explicitly. Each interruption costs a round trip between agent and main conversation.

Work done directly in the main conversation still depends on skill activation.

Installing `php` or `laravel` also installs the plugins they depend on, even for users who only want one of their skills.

Every new stack needs its own agent, and each agent's skill list must be kept aligned with the skills it preloads.
