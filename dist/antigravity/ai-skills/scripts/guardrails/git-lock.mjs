#!/usr/bin/env node
// Locks or unlocks git writes for a project in Antigravity. The user runs it in their own terminal: the hook denies it to the agent.
// Usage: node git-lock.mjs on|off|status [project-dir]   (project-dir defaults to the current directory)
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

import { LOCK_FILE, isInside, readLockedDirs, toPosix } from './hook.mjs';

const [action = 'status', dir = process.cwd()] = process.argv.slice(2);
const project = toPosix(resolve(dir));
const locked = readLockedDirs();

if (action === 'on' || action === 'off') {
  const others = locked.filter((lockedDir) => lockedDir !== project);
  mkdirSync(dirname(LOCK_FILE), { recursive: true });
  writeFileSync(LOCK_FILE, `${JSON.stringify(action === 'on' ? [...others, project] : others, null, 2)}\n`);
  console.log(`Git ${action === 'on' ? 'locked' : 'unlocked'} for ${project}.`);
} else if (action === 'status') {
  const isLocked = locked.some((lockedDir) => isInside(project, lockedDir));
  console.log(`Git is ${isLocked ? 'locked' : 'unlocked'} for ${project}. Use: node git-lock.mjs on | off [project-dir]`);
} else {
  console.error('Usage: node git-lock.mjs on | off | status [project-dir]');
  process.exit(2);
}
