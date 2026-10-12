export type Verdict = { decision: 'ask' | 'deny'; reason: string }

const GIT_WRITE =
  /\bgit\s+(?:-C\s+\S+\s+)?(?:commit|add|push|pull|mv|rm|stash|reset|tag|merge|rebase|cherry-pick|revert|restore|checkout|switch|am|apply)\b/
// `git branch` alone, or with a listing flag, only reads.
const GIT_BRANCH_WRITE = /\bgit\s+(?:-C\s+\S+\s+)?branch\s+(?!(?:-a|-r|-v|-vv|--list|--show-current|--all)\b)\S/
const GH_WRITE = /\bgh\s+(?:pr\s+(?:create|merge|close|edit)|release\s+(?:create|delete|edit))\b/

const DESTRUCTIVE_ARTISAN = /\bartisan\s+(?:migrate:(?:fresh|reset|rollback|refresh)|db:wipe)\b/
const DESTRUCTIVE_SQL = /\b(?:DROP|TRUNCATE)\s+(?:TABLE|DATABASE|SCHEMA)\b/i
const MAKE_SCHEMA = /\bartisan\s+make:(?:migration|seeder)\b/
const RM_RECURSIVE = /(?:^|[;&|]\s*)rm\s+((?:-\S+\s+)*)(.+)/

export const MIGRATION = /(?:^|\/)database\/migrations\/[^/]+\.php$/
export const SEEDER = /(?:^|\/)database\/seeders\/.+\.php$/

export const isGitWrite = (command: string): boolean =>
  GIT_WRITE.test(command) || GIT_BRANCH_WRITE.test(command) || GH_WRITE.test(command)

// ponytail: relative targets are taken as inside the project; a command that cd's out first is not followed.
const isOutside = (target: string, root: string): boolean => {
  const path = target.replace(/^["']|["']$/g, '')
  if (/^(?:~|\$HOME|\.\.)(?:\/|$)/.test(path) || path.includes('/../')) return true
  return path.startsWith('/') && path !== root && !path.startsWith(`${root}/`)
}

const rmOutsideProject = (command: string, root: string): boolean => {
  const match = RM_RECURSIVE.exec(command)
  if (match === null) return false
  const flags = match[1]
  const isRecursiveForce = /-\S*r/i.test(flags) && /-\S*f/.test(flags)
  return isRecursiveForce && match[2].split(/\s+/).some(target => isOutside(target, root))
}

/** What guardrails says about a Bash command; undefined lets the engine decide. */
export const checkBash = (command: string, root: string, isGitLocked: boolean): Verdict | undefined => {
  if (isGitLocked && isGitWrite(command)) {
    return { decision: 'deny', reason: 'guardrails: git is locked in this project, the user makes every git write here. Leave the changes uncommitted and list them.' }
  }
  if (DESTRUCTIVE_ARTISAN.test(command) || DESTRUCTIVE_SQL.test(command)) {
    return { decision: 'ask', reason: 'guardrails: destructive database command, it can erase data.' }
  }
  if (rmOutsideProject(command, root)) {
    return { decision: 'ask', reason: 'guardrails: recursive delete outside the project.' }
  }
  if (MAKE_SCHEMA.test(command)) {
    return { decision: 'ask', reason: 'guardrails: creates a migration or seeder; confirm the user wants one.' }
  }
  return undefined
}

/** What guardrails says about writing a file; `isTracked` is whether git already holds it. */
export const checkFileWrite = (path: string, isTracked: boolean): Verdict | undefined => {
  if (MIGRATION.test(path)) {
    return isTracked
      ? { decision: 'deny', reason: 'guardrails: this migration is already in git and may have run in production. Create a new migration instead.' }
      : { decision: 'ask', reason: 'guardrails: new migration; confirm the user wants one.' }
  }
  if (SEEDER.test(path)) {
    return { decision: 'ask', reason: 'guardrails: changes a seeder; confirm the user wants it.' }
  }
  return undefined
}
