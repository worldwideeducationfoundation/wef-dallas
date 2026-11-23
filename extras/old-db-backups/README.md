# Old database backups

These four archives used to live in `storage/backups/` and were committed to
git, and through it pushed to GitHub. They are full dumps: admin password
hashes, every contact/volunteer submission, the Constant Contact tokens.

They are here only so the website folder no longer carries them. Moving them
does **not** remove them from git history. That needs a history rewrite and a
force-push, which has not been done (security audit, finding 1). Until then,
treat every password that existed when they were made as exposed if the
GitHub repository is or ever was public, and change it.

New backups are no longer committed: `website/storage/backups/*` is in
`.gitignore`, and on Hostinger backups go to `wef-backups/` beside
`public_html` (APP_BACKUP_DIR in config.php).
