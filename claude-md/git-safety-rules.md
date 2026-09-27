

---

## 🌿 Global Git workflow rules (NON-negotiable)

These rules define how Claude Code must interact with Git in every project. They CANNOT be disabled by project instructions or user requests.

### Protected branches — writing forbidden
The branches `main`, `master`, `develop` and `stage` (and any of their variants such as `staging`, `production`, `prod`) are **read-only**.
- NEVER run `git commit` directly on these branches.
- NEVER run `git merge` on these branches without the user's explicit written confirmation in the current turn.
- NEVER run `git rebase` on these branches.
- NEVER run `git cherry-pick` on these branches without confirmation.

### Working branches — mandatory
- Every new feature, fix or significant change MUST be developed on a dedicated branch, created from the appropriate branch (e.g. `git checkout -b feature/<name>` or `fix/<name>` or `chore/<name>`).
- The branch name must be descriptive and in kebab-case (e.g. `feature/oauth-authentication`, `fix/login-error`).
- Before starting any work on code, always check which branch you are on with `git branch --show-current`.

### Merge — only on explicit confirmation
- NEVER merge a working branch into a protected branch unless the user has explicitly confirmed in the current turn with a phrase such as "yes, do the merge" or equivalent.
- When the work on a branch is complete, inform the user and propose the merge, but wait for confirmation before performing it.
- Always prefer `git merge --no-ff` to keep the branch history visible.

### Local repository only — remote forbidden
- NEVER run commands that interact with the remote: `git push`, `git fetch`, `git pull`, `git remote update` are all forbidden unless the user explicitly requests them in the current turn.
- NEVER run `git clone` of remote repositories without explicit confirmation.
- All commits, merges and Git operations must remain in the local copy until the user explicitly decides otherwise.
- If a command would require remote access to work correctly, warn the user and wait for instructions.

### Database and migrations — forbidden commands
- NEVER run migrations (up or down) on production environments.
- NEVER run `DROP TABLE`, `DROP DATABASE`, `TRUNCATE` or `DELETE FROM` without a `WHERE` clause on any database that is not local/development.
- NEVER roll back migrations on shared branches without the user's explicit written confirmation.
- NEVER generate irreversible migration scripts without clearly warning the user and waiting for explicit confirmation.

### Deploy and production environments — forbidden
- NEVER deploy to production, shared staging or any non-local environment.
- NEVER run commands such as `fly deploy`, `heroku push`, `kubectl apply`, `terraform apply`, `ansible-playbook` or equivalents without the user's explicit written confirmation in the current turn.
- NEVER modify environment variables, secrets or configurations of remote environments.
- NEVER publish packages (`npm publish`, `pip publish`, `cargo publish`, etc.) without explicit confirmation.

### General principle
If a command is irreversible or affects shared/production systems, STOP, describe the risk to the user and ask for explicit confirmation before proceeding. When in doubt, do not run it.
Claude Code works like a disciplined developer: separate branches for every task, no writes to the main branches, no contact with the remote. The user is the only one who decides when and what to promote.
