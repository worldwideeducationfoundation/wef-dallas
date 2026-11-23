# Worldwide Education Foundation website: project README

Last updated: 2026-10-14.

This folder holds everything a coding agent needs to work on the site. Start
with [AGENTS.md](AGENTS.md) (the rules), then [context.md](context.md) (goals and
constraints), [HANDOFF.md](HANDOFF.md) (where the last session stopped) and
[TASKS.md](TASKS.md) (what is open).

## What is in this folder

| File | Read it for |
|---|---|
| [AGENTS.md](AGENTS.md) | Rules every agent follows |
| [context.md](context.md) | Goals, programmes, ethics, current state, constraints |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Folder layout, routing, the three PHP layers, uploads, deployment |
| [BUSINESS_RULES.md](BUSINESS_RULES.md) | Project rules, names, map and roles |
| [DATABASE.md](DATABASE.md) | Tables and columns, conventions, schema changes, scripts |
| [API.md](API.md) | Public endpoints, admin handlers, request and response formats |
| [SECURITY.md](SECURITY.md) | Secrets, private folders, headers and the policy, accounts, uploads |
| [CODING_STANDARDS.md](CODING_STANDARDS.md) | Naming, PHP, CSS, JavaScript, admin modules, git |
| [TESTING.md](TESTING.md) | Checks to run, the responsive audit, the manual pass |
| [REGRESSION_CHECKLIST.md](REGRESSION_CHECKLIST.md) | What must keep working |
| [KNOWN_ISSUES.md](KNOWN_ISSUES.md) | Open bugs, limits and decisions waiting for the owner |
| [TASKS.md](TASKS.md) | Pending and finished work |
| [CHANGELOG.md](CHANGELOG.md) | Dated history of significant changes |
| [HANDOFF.md](HANDOFF.md) | Current state, changed files, test results, next steps |
| [docs/](docs/) | Older reference documents. Where they disagree with these files, these files win. |

## Requirements

- Windows with XAMPP (Apache, MySQL/MariaDB 10.4, PHP 8.0). The site also
  needs the PHP extensions `pdo_mysql`, `gd`, `fileinfo`, `exif` and `mbstring`.
- PowerShell (for the build script) and Node.js (only for the responsive audit).
- Git.

The live site runs on Hostinger (PHP 8.2 or newer, MySQL). It has no shell.

## Setup (local, once)

Run from `C:\xampp\htdocs\WEF`. Start Apache and MySQL in the XAMPP control panel first.

1. **Create the database** (creates `wef_admin` and all tables):
   ```powershell
   cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < website\public\admin\database\schema.sql"
   ```
2. **Create the local config.** Copy `website\config\config.sample.php` to
   `website\config\config.php`. The `Windows` block is the local one and works as
   it is. Fill in the `LIVE` block only when you build the upload.
3. **Create an admin account:**
   ```powershell
   C:\xampp\php\php.exe website\public\admin\database\create-admin.php "Your Name" you@example.org
   ```
   It asks for the password without echoing it.
4. **Open the site:** http://localhost/WEF/ . The admin is at http://localhost/WEF/admin/ .

Optional, for a database that predates a change: `php website\public\admin\database\migrate.php`.
The seed scripts in `website\public\admin\database\` are one-off loaders; check
their header before running any of them (`DATABASE.md`).

## Run and check

```powershell
# Syntax check one PHP file
C:\xampp\php\php.exe -l website\public\index.php

# Rebuild the CSS that is inlined above the fold, after any CSS change
C:\xampp\php\php.exe extras\tools\build-critical-css.php

# Check the Content-Security-Policy hashes, after any inline script change
C:\xampp\php\php.exe extras\tools\csp-hashes.php

# Build the Hostinger upload (zip and database export) into extras\deploy\
powershell -ExecutionPolicy Bypass -File extras\tools\build-deploy.ps1
```

The responsive audit, its setup and the manual pass are in [TESTING.md](TESTING.md).

## Repository layout in one line each

- `website/`: the site. Only this folder is uploaded.
- `extras/`: everything else. Never uploaded.
- `extras/agent/`: this folder.
- `extras/tools/`: build and maintenance scripts.
- `extras/deploy/`: build output. Gitignored; holds secrets.
- `.claude/skills/`: Claude Code skills. Managed by the tool; do not move them.

The deployment guide for the owner is [../DEPLOY-HOSTINGER.md](../DEPLOY-HOSTINGER.md).
