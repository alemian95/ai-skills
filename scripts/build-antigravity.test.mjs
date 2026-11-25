// Run: node --test scripts/build-antigravity.test.mjs
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { build, diffTrees, SUBAGENT_TOOLS, toAntigravityAgent, unqualify } from './build-antigravity.mjs';

const known = new Set(['engineering:clean-code', 'php:modern-php', 'php:php-developer', 'laravel:laravel-developer']);

const claudeAgent = `---
name: php-developer
description: Implements PHP code (for Laravel use laravel:laravel-developer).
skills:
  - engineering:clean-code
  - php:modern-php
---

You implement PHP code. If the content of a skill listed in \`skills\` is not in your context, invoke it with the Skill tool before starting; if it is not installed, say so in your report. Environment-specific skills are not preloaded: invoke them when the project needs them.
`;

test('unqualify strips the plugin prefix only from known skills and agents', () => {
  assert.equal(unqualify('use `engineering:clean-code` and /php:modern-php', known), 'use `clean-code` and /modern-php');
  assert.equal(unqualify('php:8.4, php:unknown, http://x, legacy-php:modern-php', known), 'php:8.4, php:unknown, http://x, legacy-php:modern-php');
});

test('toAntigravityAgent rewrites frontmatter and Claude-only instructions', () => {
  const agent = toAntigravityAgent(claudeAgent, known);
  const [, frontmatter, body] = agent.split('---\n');

  assert.match(frontmatter, /^name: php-developer$/m);
  assert.match(frontmatter, /^description: Implements PHP code \(for Laravel use laravel-developer\)\.$/m);
  assert.match(frontmatter, /^subagent: true\nmainAgent: false\ntools:\n/m);
  for (const tool of SUBAGENT_TOOLS) assert.match(frontmatter, new RegExp(`^  - ${tool}$`, 'm'));
  assert.match(frontmatter, /skills:\n {2}- skills\/clean-code\n {2}- skills\/modern-php\n$/);
  assert.doesNotMatch(agent, /Skill tool|invoke them|engineering:/);
  assert.match(body, /read its `SKILL\.md` with `view_file`/);
  assert.match(body, /are not preloaded: read their `SKILL\.md` with `view_file` when the project needs them/);
});

test('toAntigravityAgent handles CRLF line endings', () => {
  const crlfAgent = claudeAgent.replaceAll('\n', '\r\n');
  assert.equal(toAntigravityAgent(crlfAgent, known), toAntigravityAgent(claudeAgent, known));
});

test('toAntigravityAgent fails when the Skill tool sentence changed in the source', () => {
  assert.throws(() => toAntigravityAgent(claudeAgent.replace('invoke it with the Skill tool', 'use the Skill tool'), known), /Skill tool sentence not found/);
});

test('diffTrees reports missing, extra and changed files', () => {
  const root = mkdtempSync(join(tmpdir(), 'ai-skills-test-'));
  try {
    const [a, b] = [join(root, 'a'), join(root, 'b')];
    for (const dir of [a, b]) mkdirSync(join(dir, 'sub'), { recursive: true });
    writeFileSync(join(a, 'same.md'), 'x');
    writeFileSync(join(b, 'same.md'), 'x');
    writeFileSync(join(a, 'sub/changed.md'), 'old');
    writeFileSync(join(b, 'sub/changed.md'), 'new');
    writeFileSync(join(a, 'only-expected.md'), '');
    writeFileSync(join(b, 'only-actual.md'), '');
    assert.deepEqual(diffTrees(a, b), ['only-actual.md', 'only-expected.md', join('sub', 'changed.md')]);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('the built plugin has skills, agents and a working guardrails hook, and no rule files', (t) => {
  const tmp = mkdtempSync(join(tmpdir(), 'ai-skills-plugin-'));
  t.after(() => rmSync(tmp, { recursive: true, force: true }));
  const plugin = join(tmp, 'plugin');
  const home = join(tmp, 'home');
  const repo = join(tmp, 'repo');
  build(plugin);

  assert.ok(existsSync(join(plugin, 'skills/clean-code/SKILL.md')));
  assert.ok(existsSync(join(plugin, 'agents/laravel-developer.md')));
  assert.ok(!existsSync(join(plugin, 'rules')));
  const hooks = JSON.parse(readFileSync(join(plugin, 'hooks.json'), 'utf8'));
  assert.equal(hooks.guardrails.PreToolUse[0].hooks[0].command, 'node ./scripts/guardrails/hook.mjs');

  const env = { ...process.env, HOME: home, USERPROFILE: home };
  // Antigravity runs plugin hooks from the plugin root.
  const hook = (CommandLine) =>
    JSON.parse(
      execFileSync('node', ['./scripts/guardrails/hook.mjs'], {
        cwd: plugin,
        env,
        input: JSON.stringify({ toolCall: { name: 'run_command', args: { CommandLine, Cwd: repo } }, workspacePaths: [repo] }),
      }),
    );
  const gitLock = (action) => execFileSync('node', [join(plugin, 'scripts/guardrails/git-lock.mjs'), action, repo], { env, encoding: 'utf8' });

  mkdirSync(repo);
  execFileSync('git', ['init', '-q', '-b', 'main'], { cwd: repo });
  assert.equal(hook('git commit -m x').decision, 'force_ask');
  assert.equal(hook('ls').decision, 'allow');

  execFileSync('git', ['checkout', '-q', '-b', 'feature'], { cwd: repo });
  assert.equal(hook('git commit -m x').decision, 'allow');
  assert.match(gitLock('on'), /^Git locked/);
  assert.equal(hook('git commit -m x').decision, 'deny');
  assert.match(gitLock('status'), /^Git is locked/);
  assert.match(gitLock('off'), /^Git unlocked/);
  assert.equal(hook('git commit -m x').decision, 'allow');

  const broken = execFileSync('node', ['./scripts/guardrails/hook.mjs'], { cwd: plugin, env, input: 'not json' });
  assert.equal(JSON.parse(broken).decision, 'force_ask');
});
