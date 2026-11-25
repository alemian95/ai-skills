#!/usr/bin/env node
// Generates the Antigravity plugin in dist/antigravity/ai-skills from the Claude Code plugins.
// Usage: node scripts/build-antigravity.mjs [--check]
import { cpSync, existsSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { stripTypeScriptTypes } from 'node:module';
import { tmpdir } from 'node:os';
import { basename, dirname, join, relative } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const OUT = join(ROOT, 'dist/antigravity/ai-skills');
const GUARDRAILS = join(ROOT, 'plugins/guardrails');

// Skills and agents come from these plugins. cost is a Claude Code UI mod; guardrails ships as hooks (below).
const PLUGINS = ['engineering', 'php', 'laravel'];
// Antigravity loads these as always-on rules: the bundle carries skills, agents and hooks only.
const RULE_FILES = /^(?:AGENTS|GEMINI)\.md$/i;

const HOOKS = {
  guardrails: {
    PreToolUse: [
      {
        matcher: 'run_command|write_to_file|replace_file_content|multi_replace_file_content',
        hooks: [{ type: 'command', command: 'node ./scripts/guardrails/hook.mjs', timeout: 10 }],
      },
    ],
  },
};

const MANIFEST = {
  name: 'ai-skills',
  displayName: 'AI Skills',
  version: '1.0.0', // bump on every change to the generated content
  description:
    'Design principles (SOLID, DRY, YAGNI, SSOT), ADR writing, brainstorming, modern PHP 8.4+, PHP on shared hosting, Laravel architecture, developer subagents with the rules preloaded, and guardrails hooks for git, database and deploy commands.',
  author: { name: 'Alessandro Mian' },
  repository: 'https://github.com/alemian95/ai-skills',
};

// Without an explicit list the default is ambiguous in the docs (all tools vs none).
export const SUBAGENT_TOOLS = [
  'view_file',
  'list_dir',
  'find_by_name',
  'grep_search',
  'write_to_file',
  'replace_file_content',
  'multi_replace_file_content',
  'run_command',
  'manage_task',
  'search_web',
];

const SKILL_TOOL_SENTENCE =
  'If the content of a skill listed in `skills` is not in your context, invoke it with the Skill tool before starting; if it is not installed, say so in your report.';
const READ_SKILL_SENTENCE =
  'If the content of a skill listed in `skills` is not in your context, read its `SKILL.md` with `view_file` from the `skills/<skill>/` folder of the `ai-skills` plugin before starting; if it is missing, say so in your report.';

/** Replaces `<plugin>:<name>` with `<name>` for skills and agents that exist: Antigravity ships them as one plugin. */
export function unqualify(text, knownIds) {
  return text.replace(/(?<![\w-])([a-z][a-z0-9-]*):([a-z][a-z0-9-]*)(?![\w-])/g, (match, plugin, name) =>
    knownIds.has(`${plugin}:${name}`) ? name : match,
  );
}

/** Converts a Claude Code agent into an Antigravity subagent. Throws if a Claude-only instruction survives. */
export function toAntigravityAgent(source, knownIds) {
  const parts = /^---\n([\s\S]*?)\n---\n([\s\S]*)$/.exec(source);
  if (!parts) throw new Error('agent without frontmatter');
  let [, frontmatter, body] = parts;

  const skills = /^skills:\n((?: {2}- .+\n?)+)/m.exec(frontmatter);
  if (!skills) throw new Error('agent without a skills list');
  const skillPaths = [...skills[1].matchAll(/- (\S+)/g)].map(([, id]) => `  - skills/${id.split(':').pop()}`);
  frontmatter = [
    frontmatter.replace(skills[0], '').trimEnd(),
    'subagent: true',
    'mainAgent: false',
    'tools:',
    ...SUBAGENT_TOOLS.map((tool) => `  - ${tool}`),
    'skills:',
    ...skillPaths,
  ].join('\n');

  if (!body.includes(SKILL_TOOL_SENTENCE)) throw new Error('Skill tool sentence not found: update the build script');
  body = body
    .replace(SKILL_TOOL_SENTENCE, READ_SKILL_SENTENCE)
    .replaceAll('are not preloaded: invoke them', 'are not preloaded: read their `SKILL.md` with `view_file`');
  const leftover = /Skill tool|invoke (it|them)\b|\bClaude\b/.exec(body);
  if (leftover) throw new Error(`Claude-only instruction left in agent: "${leftover[0]}"`);

  return unqualify(`---\n${frontmatter}\n---\n${body}`, knownIds);
}

function listDir(dir) {
  return existsSync(dir) ? readdirSync(dir, { withFileTypes: true }) : [];
}

function filesUnder(dir) {
  return readdirSync(dir, { recursive: true, withFileTypes: true })
    .filter((entry) => entry.isFile())
    .map((entry) => join(entry.parentPath, entry.name));
}

/** The Antigravity hook with the guardrails rules, which stay single-sourced in rules.ts and ship as plain JavaScript. */
function buildGuardrails(outDir) {
  const rulesSource = readFileSync(join(GUARDRAILS, 'hooks/rules.ts'), 'utf8');
  writeFileSync(join(outDir, 'rules.mjs'), `// Generated from plugins/guardrails/hooks/rules.ts: do not edit.\n${stripTypeScriptTypes(rulesSource)}`);

  const hook = readFileSync(join(GUARDRAILS, 'antigravity/hook.mjs'), 'utf8');
  if (!hook.includes("from '../hooks/rules.ts'")) throw new Error('hook.mjs no longer imports ../hooks/rules.ts: update the build script');
  writeFileSync(join(outDir, 'hook.mjs'), hook.replace("from '../hooks/rules.ts'", "from './rules.mjs'"));
  cpSync(join(GUARDRAILS, 'antigravity/git-lock.mjs'), join(outDir, 'git-lock.mjs'));
}

export function build(outDir) {
  const skills = PLUGINS.flatMap((plugin) =>
    listDir(join(ROOT, 'plugins', plugin, 'skills'))
      .filter((entry) => entry.isDirectory())
      .map((entry) => ({ plugin, name: entry.name, dir: join(entry.parentPath, entry.name) })),
  );
  const agents = PLUGINS.flatMap((plugin) =>
    listDir(join(ROOT, 'plugins', plugin, 'agents'))
      .filter((entry) => entry.isFile() && entry.name.endsWith('.md'))
      .map((entry) => ({ plugin, name: entry.name.slice(0, -3), file: join(entry.parentPath, entry.name) })),
  );

  const names = [...skills, ...agents].map(({ name }) => name);
  const duplicate = names.find((name, i) => names.indexOf(name) !== i);
  if (duplicate) throw new Error(`"${duplicate}" exists in more than one plugin: names must be unique in the bundle`);
  const knownIds = new Set([...skills, ...agents].map(({ plugin, name }) => `${plugin}:${name}`));

  rmSync(outDir, { recursive: true, force: true });
  mkdirSync(join(outDir, 'agents'), { recursive: true });
  mkdirSync(join(outDir, 'scripts/guardrails'), { recursive: true });

  writeFileSync(join(outDir, 'plugin.json'), `${JSON.stringify(MANIFEST, null, 2)}\n`);

  writeFileSync(join(outDir, 'hooks.json'), `${JSON.stringify(HOOKS, null, 2)}\n`);
  buildGuardrails(join(outDir, 'scripts/guardrails'));

  for (const skill of skills) {
    const target = join(outDir, 'skills', skill.name);
    cpSync(skill.dir, target, { recursive: true, filter: (path) => basename(path) !== '.DS_Store' && !RULE_FILES.test(basename(path)) });
    for (const file of filesUnder(target).filter((path) => path.endsWith('.md'))) {
      writeFileSync(file, unqualify(readFileSync(file, 'utf8'), knownIds));
    }
  }

  for (const agent of agents) {
    writeFileSync(join(outDir, 'agents', `${agent.name}.md`), toAntigravityAgent(readFileSync(agent.file, 'utf8'), knownIds));
  }

  const ruleFile = filesUnder(outDir).find((file) => RULE_FILES.test(basename(file)));
  if (ruleFile) throw new Error(`${relative(outDir, ruleFile)} would load as an Antigravity rule`);

  return { skills: skills.length, agents: agents.length };
}

/** Lists the paths that differ between two trees (missing on either side or with different content). */
export function diffTrees(expectedDir, actualDir) {
  const read = (dir) =>
    existsSync(dir) ? new Map(filesUnder(dir).map((file) => [relative(dir, file), readFileSync(file)])) : new Map();
  const expected = read(expectedDir);
  const actual = read(actualDir);
  const paths = new Set([...expected.keys(), ...actual.keys()]);
  return [...paths]
    .filter((path) => !expected.has(path) || !actual.has(path) || !expected.get(path).equals(actual.get(path)))
    .sort();
}

function main(args) {
  if (args.includes('--check')) {
    const fresh = mkdtempSync(join(tmpdir(), 'ai-skills-antigravity-'));
    try {
      build(fresh);
      const stale = diffTrees(fresh, OUT);
      if (stale.length > 0) {
        console.error(`${relative(ROOT, OUT)} is out of date, run: node scripts/build-antigravity.mjs\n  ${stale.join('\n  ')}`);
        process.exit(1);
      }
      console.log(`${relative(ROOT, OUT)} is up to date`);
    } finally {
      rmSync(fresh, { recursive: true, force: true });
    }
    return;
  }
  const { skills, agents } = build(OUT);
  console.log(`Built ${relative(ROOT, OUT)}: ${skills} skills, ${agents} agents`);
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) main(process.argv.slice(2));
