---
description: commit and push changes to the GitHub repository
---

# Commit to GitHub

Remote: `https://github.com/Munyah17/HostFlow-App.git`
Branch: `main`
Git identity: `Munyah17 <mmuzvi@gmail.com>` (already configured globally)

## Steps

1. Check status of working tree
// turbo
2. `git status --short`
3. If the repo is not initialized (fatal: not a git repository):
   - `git init -b main`
   - `git remote add origin https://github.com/Munyah17/HostFlow-App.git`
   - `git pull origin main` (if remote already has commits)
// turbo
4. `git add .`
5. Verify sensitive files are not staged: run `git status --short` and confirm `.env`, `vendor/`, and `storage/` runtime files are absent (`.gitignore` already excludes them).
// turbo
6. `git commit -m "<descriptive message summarizing the changes>"`
7. Push: `git push -u origin main`
   - If push is rejected (remote has newer commits): `git pull --rebase origin main` then push again.
   - Note: PowerShell may show a red "RemoteException" for git output on stderr even on success — verify with `git log --oneline -1` and `git status -sb`.
