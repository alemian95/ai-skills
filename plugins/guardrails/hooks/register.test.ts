import { test, expect } from 'claude-code/testing'

import { checkBash, checkFileWrite, gitDirectory, isGitWrite } from './rules'

const ROOT = '/work/app'

test('git writes are told from reads', () => {
  for (const write of ['git commit -m x', 'git add .', 'git push origin main', 'git checkout -b feat', 'git branch -d old', 'git -C sub stash', 'gh pr create --fill', 'gh release create v1'])
    expect(isGitWrite(write)).toBe(true)
  for (const read of ['git status', 'git diff HEAD', 'git log --oneline', 'git branch', 'git branch -a', 'git show HEAD', 'gh pr view 3'])
    expect(isGitWrite(read)).toBe(false)
})

test('git writes are denied only where git is locked', () => {
  expect(checkBash('git commit -m x', ROOT, true)?.decision).toBe('deny')
  expect(checkBash('git commit -m x', ROOT, false)).toBeUndefined()
})

test('destructive commands ask', () => {
  for (const command of [
    'php artisan migrate:fresh --seed',
    'php artisan db:wipe',
    './vendor/bin/sail artisan migrate:rollback',
    'mysql -e "DROP TABLE users"',
    'psql -c "truncate table jobs"',
    'rm -rf /tmp/build',
    'rm -fr ~/old',
    'rm -rf ../other-project',
    'php artisan make:migration add_x_to_y',
  ])
    expect(checkBash(command, ROOT, false)?.decision).toBe('ask')

  for (const command of ['php artisan migrate', 'rm -rf node_modules', `rm -rf ${ROOT}/storage/cache`, 'rm /tmp/one-file', 'php artisan test'])
    expect(checkBash(command, ROOT, false)).toBeUndefined()
})

test('writes to protected branches, remote, deploy and unbounded deletes ask', () => {
  for (const branch of ['main', 'master', 'develop', 'staging', 'production'])
    for (const command of ['git commit -m x', 'git merge feat', 'git rebase main', 'git cherry-pick abc'])
      expect(checkBash(command, ROOT, false, branch)?.decision).toBe('ask')
  expect(checkBash('git commit -m x', ROOT, false, 'feature/x')).toBeUndefined()

  for (const command of [
    'git push origin feat',
    'git pull',
    'git fetch --all',
    'fly deploy',
    'kubectl apply -f k8s.yaml',
    'terraform apply',
    'npm publish',
    'cargo publish',
    'mysql -e "DELETE FROM users"',
  ])
    expect(checkBash(command, ROOT, false)?.decision).toBe('ask')

  for (const command of ['mysql -e "DELETE FROM users WHERE id = 1"', 'npm run build', 'terraform plan', 'git remote -v'])
    expect(checkBash(command, ROOT, false)).toBeUndefined()

  // Read-only lookalikes: subcommands sharing a prefix, and search text that only mentions a command.
  for (const command of [
    'git merge-base HEAD origin/main',
    'git commit-graph verify',
    'grep -rn "DELETE FROM" app/',
    'rg "DROP TABLE" database',
    'git log -S "npm publish"',
    'git log --grep "git push"',
  ])
    expect(checkBash(command, ROOT, false, 'main')).toBeUndefined()
  expect(checkBash('git merge-base HEAD main', ROOT, true)).toBeUndefined()

  // Each SQL statement needs its own WHERE; a search before a real command does not hide it.
  expect(checkBash('mysql -e "DELETE FROM users; SELECT * FROM t WHERE id = 1"', ROOT, false)?.decision).toBe('ask')
  expect(checkBash('grep -rn x app && git push', ROOT, false)?.decision).toBe('ask')

  // A search on the first line does not hide the commands on the next ones.
  expect(checkBash('git log -1\ngit commit -m x', ROOT, true)?.decision).toBe('deny')
  expect(checkBash('grep -rn x app\ngit push', ROOT, false)?.decision).toBe('ask')
  // A WHERE inside an SQL comment does not bound the DELETE; a shell `--option` is not a comment.
  expect(checkBash('mysql -e "DELETE FROM users -- WHERE later"', ROOT, false)?.decision).toBe('ask')
  expect(checkBash('mysql -e "DELETE FROM users /* WHERE */"', ROOT, false)?.decision).toBe('ask')
  expect(checkBash('mysql --execute="DELETE FROM users WHERE id = 1"', ROOT, false)).toBeUndefined()
  // `--output` makes git write a file, so the statement is checked like any other.
  expect(checkBash('git log --grep "npm publish"', ROOT, false)).toBeUndefined()
  expect(checkBash('git log --output=out.txt --grep "npm publish"', ROOT, false)?.decision).toBe('ask')
  // A multi-line SQL statement keeps its WHERE.
  expect(checkBash('mysql <<SQL\nDELETE FROM users\nWHERE id = 1;\nSQL', ROOT, false)).toBeUndefined()

  // Git global options do not hide the subcommand.
  expect(checkBash('git -c user.name=x commit -m y', ROOT, true)?.decision).toBe('deny')
  expect(checkBash('git --no-pager push', ROOT, false)?.decision).toBe('ask')
  expect(checkBash('git --git-dir /srv/app/.git commit -m x', ROOT, false, 'main')?.decision).toBe('ask')

  // A branch that could not be read may be protected.
  expect(checkBash('git commit -m x', ROOT, false, null)?.decision).toBe('ask')
  expect(checkBash('git commit -m x', ROOT, false, '')).toBeUndefined()

  // A locked project still denies, it does not just ask.
  expect(checkBash('git push', ROOT, true)?.decision).toBe('deny')
})

test('git runs where -C or a leading cd points', () => {
  expect(gitDirectory('git commit -m x', ROOT)).toBe(ROOT)
  expect(gitDirectory('git -C ../api commit -m x', ROOT)).toBe(`${ROOT}/../api`)
  expect(gitDirectory('cd /srv/other && git commit -m x', ROOT)).toBe('/srv/other')
  expect(gitDirectory('cd packages/api && git -C .. commit -m x', ROOT)).toBe(`${ROOT}/packages/api/..`)
  expect(gitDirectory('cd a && cd b && git commit -m x', ROOT)).toBe(`${ROOT}/a/b`)
  expect(gitDirectory('cd ~/proj && git commit -m x', ROOT)).toBeNull()
  expect(gitDirectory('git -C "$APP" commit -m x', ROOT)).toBeNull()
  // Only git's global -C sets the directory: not `commit -C <commit>`, `log -C`, or text in a message.
  expect(gitDirectory('git commit -C HEAD', ROOT)).toBe(ROOT)
  expect(gitDirectory('git log -C && git commit -m x', ROOT)).toBe(ROOT)
  expect(gitDirectory('git commit -m "handle -C flag"', ROOT)).toBe(ROOT)
  expect(gitDirectory('git -C a -C b commit -m x', ROOT)).toBe(`${ROOT}/a/b`)
  expect(gitDirectory('grep -rn "git -C vendor commit" docs && git commit -m x', ROOT)).toBe(ROOT)
})

test('migrations in git are denied, new ones and seeders ask', () => {
  expect(checkFileWrite(`${ROOT}/database/migrations/2026_01_01_create_users.php`, true)?.decision).toBe('deny')
  expect(checkFileWrite(`${ROOT}/database/migrations/2026_10_12_add_flag.php`, false)?.decision).toBe('ask')
  expect(checkFileWrite(`${ROOT}/database/seeders/DatabaseSeeder.php`, false)?.decision).toBe('ask')
  expect(checkFileWrite(`${ROOT}/app/Models/User.php`, true)).toBeUndefined()
})

test('a commit on a protected branch asks', async ($, on) => {
  on('session.root', () => ({ value: ROOT }))
  on('store.get', () => ({ value: undefined }))
  on('tool.check', () => ({ decision: 'allow' }))
  on('process.run', () => ({ value: { exitCode: 0, stdout: 'main\n', stderr: '', isStdoutTruncated: false } }) as never)

  expect((await $.tool.check({ tool: 'Bash', input: { command: 'git commit -m x' } })).decision).toBe('ask')
})

test('the branch is read where git runs', async ($, on) => {
  on('session.root', () => ({ value: ROOT }))
  on('store.get', () => ({ value: undefined }))
  on('tool.check', () => ({ decision: 'allow' }))
  on('process.run', (_$, e) => ({ value: { exitCode: 0, stdout: e.init?.cwd === ROOT ? 'feature/x\n' : 'main\n', stderr: '', isStdoutTruncated: false } }) as never)

  expect((await $.tool.check({ tool: 'Bash', input: { command: 'git commit -m x' } })).decision).toBe('allow')
  expect((await $.tool.check({ tool: 'Bash', input: { command: 'git -C ../api commit -m x' } })).decision).toBe('ask')
})

test('a git error reading the branch asks', async ($, on) => {
  on('session.root', () => ({ value: ROOT }))
  on('store.get', () => ({ value: undefined }))
  on('tool.check', () => ({ decision: 'allow' }))
  on('process.run', () => ({ value: { exitCode: 128, stdout: '', stderr: 'fatal: not a git repository', isStdoutTruncated: false } }) as never)

  expect((await $.tool.check({ tool: 'Bash', input: { command: 'git commit -m x' } })).decision).toBe('ask')
})

test('/git-lock locks this project for the Bash check, then unlocks it', async ($, on) => {
  on('session.root', () => ({ value: ROOT }))
  on('process.run', () => ({ value: { exitCode: 0, stdout: 'feature/x\n', stderr: '', isStdoutTruncated: false } }) as never)
  const store = new Map<string, unknown>()
  on('store.get', (_$, e) => ({ value: store.get(e.key) }))
  on('store.set', (_$, e) => (store.set(e.key, e.value), { value: undefined }))
  on('tool.check', () => ({ decision: 'allow' }))

  const commit = { tool: 'Bash', input: { command: 'git commit -m x' } }
  expect((await $.tool.check(commit)).decision).toBe('allow')

  await $.command.run({ command: 'git-lock', args: 'on' } as never)
  expect((await $.tool.check(commit)).decision).toBe('deny')
  expect((await $.tool.check({ tool: 'Bash', input: { command: 'git status' } })).decision).toBe('allow')

  await $.command.run({ command: 'git-lock', args: 'off' } as never)
  expect((await $.tool.check(commit)).decision).toBe('allow')
})

test('editing a migration git already holds is denied', async ($, on) => {
  on('session.root', () => ({ value: ROOT }))
  on('tool.check', () => ({ decision: 'allow' }))
  on('process.run', () => ({ value: { exitCode: 0, stdout: '', stderr: '', isStdoutTruncated: false } }) as never)

  const edit = { tool: 'Edit', input: { file_path: `${ROOT}/database/migrations/2026_01_01_create_users.php`, old_string: 'a', new_string: 'b' } }
  expect((await $.tool.check(edit)).decision).toBe('deny')
})
