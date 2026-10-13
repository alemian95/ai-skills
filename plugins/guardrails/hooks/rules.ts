export type Verdict = { decision: 'ask' | 'deny'; reason: string }

// A git subcommand ends at whitespace or the end of the command, so `merge-base` or `commit-graph` are not `merge` or `commit`.
const END = '(?![\\w-])'
// `git` followed by any global options: `-C <path>`, `-c <key=value>`, `--git-dir <path>`, `--no-pager`, `--work-tree=<path>`...
const GIT = '\\bgit(?:\\s+(?:-[Cc]\\s+\\S+|--(?:git-dir|work-tree|namespace)\\s+\\S+|--[\\w-]+(?:=\\S+)?))*\\s+'

const GIT_WRITE = new RegExp(
  `${GIT}(?:commit|add|push|pull|mv|rm|stash|reset|tag|merge|rebase|cherry-pick|revert|restore|checkout|switch|am|apply)${END}`,
)
// `git branch` alone, or with a listing flag, only reads.
const GIT_BRANCH_WRITE = new RegExp(`${GIT}branch\\s+(?!(?:-a|-r|-v|-vv|--list|--show-current|--all)\\b)\\S`)
const GH_WRITE = /\bgh\s+(?:pr\s+(?:create|merge|close|edit)|release\s+(?:create|delete|edit))\b/

// Writes that land on whatever branch is checked out.
const GIT_BRANCH_SENSITIVE = new RegExp(`${GIT}(?:commit|merge|rebase|cherry-pick)${END}`)
const GIT_REMOTE = new RegExp(`${GIT}(?:push|fetch|pull|remote\\s+update)${END}`)
const PROTECTED_BRANCH = /^(?:main|master|develop|stage|staging|prod|production)$/
const DEPLOY_PUBLISH =
  /\b(?:fly\s+deploy|kubectl\s+apply|terraform\s+apply|ansible-playbook|(?:npm|pnpm|yarn)\s+publish|cargo\s+publish|poetry\s+publish|twine\s+upload)\b/
const DELETE_FROM = /\bDELETE\s+FROM\b/i
const WHERE = /\bWHERE\b/i

const DESTRUCTIVE_ARTISAN = /\bartisan\s+(?:migrate:(?:fresh|reset|rollback|refresh)|db:wipe)\b/
const DESTRUCTIVE_SQL = /\b(?:DROP|TRUNCATE)\s+(?:TABLE|DATABASE|SCHEMA)\b/i
const MAKE_SCHEMA = /\bartisan\s+make:(?:migration|seeder)\b/
const RM_RECURSIVE = /(?:^|[;&|]\s*)rm\s+((?:-\S+\s+)*)(.+)/

// Read-only commands whose arguments are search text, not something that runs.
const SEARCH = new RegExp(`^\\s*(?:grep|egrep|fgrep|rg|ag|ack|${GIT}(?:log|grep|show|diff))\\b`)
const GIT_DIR = /\bgit\s+(?:\S+\s+)*?-C\s+(\S+)/
const LEADING_CD = /^\s*cd\s+(\S+)\s*&&\s*/
// A path the shell would expand: guardrails cannot tell where it points.
const SHELL_EXPANDED = /^~|\$|`/

export const MIGRATION = /(?:^|\/)database\/migrations\/[^/]+\.php$/
export const SEEDER = /(?:^|\/)database\/seeders\/.+\.php$/

const unquote = (value: string): string => value.replace(/^["']|["']$/g, '')

// ponytail: splitting ignores quoting; it also splits SQL statements inside `-e "..."`, which the DELETE check needs.
const SEPARATOR = /(;|&&|\|\|?|\n)/

/** The command without its search statements, so text inside a `grep` pattern does not count as a command. Separators are kept. */
const runnable = (command: string): string =>
  command.split(SEPARATOR).map(part => (SEARCH.test(part) ? '' : part)).join('')

// SQL statements end at `;`, not at a newline: `DELETE FROM t` and its `WHERE` may sit on separate lines.
const hasUnboundedDelete = (command: string): boolean =>
  command.split(/;|&&|\|\|?/).some(s => DELETE_FROM.test(s) && !WHERE.test(s))

/** Whether the verdict on this command may depend on the branch checked out; checkBash decides. */
export const isBranchSensitive = (command: string): boolean => GIT_BRANCH_SENSITIVE.test(command)

const resolve = (target: string, base: string): string | null => {
  const path = unquote(target)
  if (SHELL_EXPANDED.test(path)) return null
  return path.startsWith('/') ? path : `${base}/${path}`
}

/** The directory git runs in: leading `cd`s, then `-C`, from the project root; null when a path cannot be resolved. */
export const gitDirectory = (command: string, root: string): string | null => {
  let base: string | null = root
  let rest = command
  for (let cd = LEADING_CD.exec(rest); cd !== null && base !== null; cd = LEADING_CD.exec(rest)) {
    base = resolve(cd[1], base)
    rest = rest.slice(cd[0].length)
  }
  const dir = GIT_DIR.exec(rest)?.[1]
  return base === null || dir === undefined ? base : resolve(dir, base)
}

export const isGitWrite = (command: string): boolean =>
  GIT_WRITE.test(command) || GIT_BRANCH_WRITE.test(command) || GH_WRITE.test(command)

// ponytail: relative targets are taken as inside the project; a command that cd's out first is not followed.
const isOutside = (target: string, root: string): boolean => {
  const path = unquote(target)
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

/**
 * What guardrails says about a Bash command; undefined lets the engine decide.
 * `branch` is the one checked out where git runs: undefined when not looked up, null when it could not be read.
 */
export const checkBash = (command: string, root: string, isGitLocked: boolean, branch?: string | null): Verdict | undefined => {
  const code = runnable(command)
  if (isGitLocked && isGitWrite(code)) {
    return { decision: 'deny', reason: 'guardrails: git is locked in this project, the user makes every git write here. Leave the changes uncommitted and list them.' }
  }
  if (GIT_BRANCH_SENSITIVE.test(code)) {
    if (branch === null) return { decision: 'ask', reason: 'guardrails: git writes to a branch that could not be read; it may be protected.' }
    if (branch !== undefined && PROTECTED_BRANCH.test(branch)) {
      return { decision: 'ask', reason: `guardrails: writes to the protected branch ${branch}.` }
    }
  }
  if (GIT_REMOTE.test(code)) {
    return { decision: 'ask', reason: 'guardrails: talks to the git remote.' }
  }
  if (DEPLOY_PUBLISH.test(code)) {
    return { decision: 'ask', reason: 'guardrails: deploys or publishes outside this machine.' }
  }
  if (DESTRUCTIVE_ARTISAN.test(code) || DESTRUCTIVE_SQL.test(code) || hasUnboundedDelete(code)) {
    return { decision: 'ask', reason: 'guardrails: destructive database command, it can erase data.' }
  }
  if (rmOutsideProject(command, root)) {
    return { decision: 'ask', reason: 'guardrails: recursive delete outside the project.' }
  }
  if (MAKE_SCHEMA.test(code)) {
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
