import { test, expect } from 'claude-code/testing'

import { checkBash, checkFileWrite, isGitWrite } from './rules'

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

test('migrations in git are denied, new ones and seeders ask', () => {
  expect(checkFileWrite(`${ROOT}/database/migrations/2026_01_01_create_users.php`, true)?.decision).toBe('deny')
  expect(checkFileWrite(`${ROOT}/database/migrations/2026_10_12_add_flag.php`, false)?.decision).toBe('ask')
  expect(checkFileWrite(`${ROOT}/database/seeders/DatabaseSeeder.php`, false)?.decision).toBe('ask')
  expect(checkFileWrite(`${ROOT}/app/Models/User.php`, true)).toBeUndefined()
})

test('/git-lock locks this project for the Bash check, then unlocks it', async ($, on) => {
  on('session.root', () => ({ value: ROOT }))
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
