import type { EngineInterface, Register } from 'claude-code'

import { MIGRATION, checkBash, checkFileWrite, gitDirectory, isBranchSensitive } from './rules'

const LOCKED = 'gitLocked'
const FILE_TOOLS = ['Edit', 'Write', 'MultiEdit', 'NotebookEdit'] as const
// A guard that throws must not wave the call through.
const FAIL_SAFE = { decision: 'ask', reason: 'guardrails could not check this call.' } as const

const asRoots = (value: unknown): string[] =>
  Array.isArray(value) ? value.filter((v): v is string => typeof v === "string") : []

// null when the branch cannot be read: an unresolved path or a git error. A detached HEAD reads as ''.
const currentBranch = async ($: EngineInterface, cwd: string | null): Promise<string | null> => {
  if (cwd === null) return null
  const result = await $.process.run(['git', 'branch', '--show-current'], { cwd })
  return result.exitCode === 0 ? result.stdout.trim() : null
}

export const register: Register = on => {

  on('session.start', async ($, e, next) => {
    await $.command.register({
      name: 'git-lock',
      description: 'Lock or unlock git writes for this project (on | off | status)',
      argumentHint: 'on | off | status',
    })
    return next(e)
  })

  on('command.run', { command: 'git-lock' }, async ($, e) => {
    const root = await $.session.root()
    const roots = asRoots(await $.store.get(LOCKED))
    const arg = e.args.trim()
    if (arg === 'on' || arg === 'off') {
      const next = roots.filter(r => r !== root)
      await $.store.set(LOCKED, arg === 'on' ? [...next, root] : next)
      return { text: `Git ${arg === 'on' ? 'locked' : 'unlocked'} for ${root}.` }
    }
    return { text: `Git is ${roots.includes(root) ? 'locked' : 'unlocked'} for ${root}. Use /git-lock on or /git-lock off.` }
  })

  on('tool.check', { tool: 'Bash' }, async ($, e, next) => {
    const { command } = e.input as { command?: unknown }
    if (typeof command !== 'string') return next(e)
    const root = await $.session.root()
    const branch = isBranchSensitive(command) ? await currentBranch($, gitDirectory(command, root)) : undefined
    const verdict = checkBash(command, root, (asRoots(await $.store.get(LOCKED))).includes(root), branch)
    return verdict ?? next(e)
  }).catch(() => FAIL_SAFE)

  for (const tool of FILE_TOOLS) {
    on('tool.check', { tool }, async ($, e, next) => {
      const { file_path: path, notebook_path: notebook } = e.input as { file_path?: unknown; notebook_path?: unknown }
      const file = typeof path === 'string' ? path : typeof notebook === 'string' ? notebook : undefined
      if (file === undefined || !/database\/(?:migrations|seeders)\//.test(file)) return next(e)

      const isTracked = MIGRATION.test(file)
        ? (await $.process.run(['git', 'ls-files', '--error-unmatch', file], { cwd: await $.session.root() })).exitCode === 0
        : false
      return checkFileWrite(file, isTracked) ?? next(e)
    }).catch(() => FAIL_SAFE)
  }
}
