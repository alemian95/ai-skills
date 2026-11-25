// Run: node --test plugins/guardrails/antigravity/hook.test.mjs
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { decide, toAntigravity, toPosix } from './hook.mjs';

const ROOT = '/work/app';
const deps = (overrides = {}) => ({ lockedDirs: [], branchOf: () => 'feature', isTracked: () => false, ...overrides });
const run = (CommandLine, Cwd = ROOT) => ({ toolCall: { name: 'run_command', args: { CommandLine, Cwd } }, workspacePaths: [ROOT] });
const write = (TargetFile, name = 'write_to_file') => ({ toolCall: { name, args: { TargetFile } }, workspacePaths: [ROOT] });

test('commands get the guardrails verdict, ask becoming force_ask', () => {
  assert.deepEqual(toAntigravity(decide(run('git push origin feat'), deps())), {
    decision: 'force_ask',
    reason: 'guardrails: talks to the git remote.',
  });
  assert.deepEqual(toAntigravity(decide(run('php artisan test'), deps())), { decision: 'allow' });
  assert.equal(decide(run('php artisan migrate:fresh'), deps())?.decision, 'ask');
});

test('git writes are denied when the workspace or the command directory is locked, subfolders included', () => {
  const locked = deps({ lockedDirs: [ROOT] });
  assert.equal(decide(run('git commit -m x'), locked)?.decision, 'deny');
  assert.equal(decide(run('git add .', `${ROOT}/src`), locked)?.decision, 'deny');
  // Like in Claude Code, the lock follows the session's project, wherever the command runs.
  assert.equal(decide(run('git commit -m x', '/other'), locked)?.decision, 'deny');
  assert.equal(decide(run('git commit -m x'), deps({ lockedDirs: ['/other'] })), undefined);
  assert.equal(decide(run('git commit -m x', '/other/repo'), deps({ lockedDirs: ['/other'] }))?.decision, 'deny');
});

test('the agent cannot run the lock script or write its state', () => {
  assert.equal(decide(run('node ~/.gemini/config/plugins/ai-skills/scripts/guardrails/git-lock.mjs off'), deps())?.decision, 'deny');
  assert.equal(decide(run('rm ~/.gemini/config/guardrails/git-lock.json'), deps())?.decision, 'deny');
  assert.equal(decide(write('/home/me/.gemini/config/guardrails/git-lock.json'), deps())?.decision, 'deny');
  assert.equal(decide(write('C:\\Users\\me\\.gemini\\config\\guardrails\\git-lock.json'), deps())?.decision, 'deny');
});

test('branch-sensitive writes read the branch where git runs', () => {
  const seen = [];
  const onMain = deps({ branchOf: (dir) => (seen.push(dir), 'main') });
  assert.equal(decide(run('git -C sub commit -m x', `${ROOT}/pkg`), onMain)?.decision, 'ask');
  assert.deepEqual(seen, [`${ROOT}/pkg/sub`]);
  assert.equal(decide(run('git commit -m x'), deps({ branchOf: () => null }))?.decision, 'ask');
  assert.equal(decide(run('git commit -m x'), deps()), undefined);
});

test('migrations in git are denied, new ones and seeders ask, other files pass', () => {
  const migration = `${ROOT}/database/migrations/2024_01_01_000000_create_users.php`;
  assert.equal(decide(write(migration, 'replace_file_content'), deps({ isTracked: () => true }))?.decision, 'deny');
  assert.equal(decide(write(migration), deps())?.decision, 'ask');
  assert.equal(decide(write(`${ROOT}/database/seeders/UserSeeder.php`, 'multi_replace_file_content'), deps())?.decision, 'ask');
  assert.equal(decide({ toolCall: { name: 'edit_file', args: { Path: migration } }, workspacePaths: [ROOT] }, deps({ isTracked: () => true }))?.decision, 'deny');
  assert.equal(decide({ toolCall: { name: 'write_file', args: { Path: migration } }, workspacePaths: [ROOT] }, deps())?.decision, 'ask');
  assert.equal(decide(write(`${ROOT}/app/Models/User.php`), deps()), undefined);
});

test('Windows paths are matched like POSIX ones', () => {
  assert.equal(toPosix('C:\\work\\app\\'), 'C:/work/app');
  const payload = {
    toolCall: { name: 'write_to_file', args: { TargetFile: 'C:\\work\\app\\database\\migrations\\2024_x.php' } },
    workspacePaths: ['C:\\work\\app'],
  };
  const calls = [];
  assert.equal(decide(payload, deps({ isTracked: (...args) => (calls.push(args), true) }))?.decision, 'deny');
  assert.deepEqual(calls, [['C:/work/app/database/migrations/2024_x.php', 'C:/work/app']]);
});

test('a command with no workspace to anchor it asks, other tools and malformed args pass', () => {
  assert.equal(decide({ toolCall: { name: 'run_command', args: { CommandLine: 'ls' } }, workspacePaths: [] }, deps())?.decision, 'ask');
  assert.equal(decide({ toolCall: { name: 'view_file', args: { AbsolutePath: '/x' } }, workspacePaths: [ROOT] }, deps()), undefined);
  assert.equal(decide({ toolCall: { name: 'run_command', args: {} }, workspacePaths: [ROOT] }, deps()), undefined);
});
