#!/usr/bin/env node
// Antigravity PreToolUse hook: applies the guardrails rules to run_command and to file writes.
// Reads the hook payload on stdin and writes the decision on stdout. The build ships rules.ts as rules.mjs next to it.
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

import { MIGRATION, checkBash, checkFileWrite, gitDirectory, isBranchSensitive } from '../hooks/rules.ts';

export const LOCK_FILE = join(homedir(), '.gemini', 'config', 'guardrails', 'git-lock.json');

const FILE_TOOLS = new Set(['write_to_file', 'replace_file_content', 'multi_replace_file_content', 'write_file', 'edit_file']);
// Antigravity ignores a hook that fails, so a check that cannot run must still answer, and never let the call through.
const FAIL_SAFE = { decision: 'ask', reason: 'guardrails could not check this call.' };
const SELF_UNLOCK = { decision: 'deny', reason: 'guardrails: only the user changes the git lock, from their own terminal.' };
// The agent must not run the lock script nor touch its state: unlocking is the user's call.
const LOCK_COMMAND = /git-lock\.(?:mjs|json)\b/;
const LOCK_STATE = /\/\.gemini\/config\/guardrails\/git-lock\.json$/;

/** Windows paths with `/` and no trailing slash, as the rules expect. */
export const toPosix = (path) => path.replaceAll('\\', '/').replace(/(?<=.)\/+$/, '');
export const isInside = (path, dir) => path === dir || path.startsWith(`${dir}/`);

/** Directories where the user locked git writes; a lock covers the subfolders too. */
export const readLockedDirs = (file = LOCK_FILE) => {
  if (!existsSync(file)) return [];
  const value = JSON.parse(readFileSync(file, 'utf8'));
  if (!Array.isArray(value)) throw new Error(`${file} is not a list of directories`);
  return value.filter((dir) => typeof dir === 'string').map(toPosix);
};

/**
 * The guardrails verdict ({ decision: 'ask' | 'deny', reason }) on an Antigravity tool call; undefined lets Antigravity decide.
 * `deps` reads the outside world: lockedDirs, branchOf(dir) (null when unreadable), isTracked(file, root).
 */
export function decide({ toolCall, workspacePaths }, deps) {
  const args = toolCall?.args ?? {};
  const workspaces = (Array.isArray(workspacePaths) ? workspacePaths : []).filter((p) => typeof p === 'string').map(toPosix);
  const rootOf = (path) => workspaces.find((workspace) => isInside(path, workspace)) ?? workspaces[0];

  if (toolCall?.name === 'run_command') {
    const command = args.CommandLine;
    if (typeof command !== 'string') return undefined;
    if (LOCK_COMMAND.test(command)) return SELF_UNLOCK;
    const cwd = typeof args.Cwd === 'string' ? toPosix(args.Cwd) : workspaces[0];
    const root = cwd === undefined ? undefined : rootOf(cwd);
    if (root === undefined) return FAIL_SAFE;

    const isLocked = deps.lockedDirs.some((dir) => isInside(cwd, dir) || isInside(root, dir));
    let branch;
    if (isBranchSensitive(command)) {
      const dir = gitDirectory(command, cwd);
      branch = dir === null ? null : deps.branchOf(dir);
    }
    return checkBash(command, root, isLocked, branch);
  }

  if (FILE_TOOLS.has(toolCall?.name)) {
    const targetFile = args.TargetFile ?? args.Path;
    if (typeof targetFile !== 'string') return undefined;
    const file = toPosix(targetFile);
    if (LOCK_STATE.test(file)) return SELF_UNLOCK;
    if (!/database\/(?:migrations|seeders)\//.test(file)) return undefined;
    const root = rootOf(file);
    return checkFileWrite(file, MIGRATION.test(file) && root !== undefined && deps.isTracked(file, root));
  }

  return undefined;
}

/** Antigravity's `ask` respects "Always Allow"; guardrails must prompt regardless, like in Claude Code. */
export const toAntigravity = (verdict) =>
  verdict === undefined ? { decision: 'allow' } : { ...verdict, decision: verdict.decision === 'ask' ? 'force_ask' : verdict.decision };

const git = (args, cwd) => execFileSync('git', args, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });

const systemDeps = () => ({
  lockedDirs: readLockedDirs(),
  branchOf: (dir) => {
    try {
      return git(['branch', '--show-current'], dir).trim();
    } catch {
      return null;
    }
  },
  isTracked: (file, root) => {
    try {
      git(['ls-files', '--error-unmatch', file], root);
      return true;
    } catch {
      return false;
    }
  },
});

async function main() {
  let verdict;
  try {
    const chunks = [];
    for await (const chunk of process.stdin) chunks.push(chunk);
    verdict = decide(JSON.parse(Buffer.concat(chunks).toString('utf8')), systemDeps());
  } catch {
    verdict = FAIL_SAFE;
  }
  process.stdout.write(`${JSON.stringify(toAntigravity(verdict))}\n`);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) await main();
