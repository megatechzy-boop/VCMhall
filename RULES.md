# RULES

Standing rules for the venutaihall.com repo. Read this and DEPLOY.md before any push or after any sandbox reset.

1. Work on main. After each finished, verified group, commit and push main to megatechzy-boop/VCMhall only, plain push, never --force. Pushing to GitHub does NOT change the live site — the live site only changes when the owner clicks Update from Remote then Deploy HEAD Commit in cPanel (folder /home/venutaihall/VCMhall-deploy). GLM never deploys.
2. megatechzy-boop/VCMhall is the only repo (since 2026-11-16; OmKardile/venutaihall is no longer pushed to and OmKardile has no access). cPanel deploys from its main via the folder /home/venutaihall/VCMhall-deploy.
3. Never modify .cpanel.yml unless I explicitly ask. Never add any file to the repo that exists only on the server: booking-config.php, booking-store.php, admin/, error_log, .htaccess edits. Never copy backend files in .cpanel.yml.
4. Before every push to main, print and check: git diff --stat against the current main (list every changed file); confirm no .php, .htaccess, .cpanel.yml, robots.txt or sitemap.xml changed unless I asked; build clean; _check.py clean; token-pattern grep on staged changes = 0 (a real token = ghp_ or github_pat_ followed by 20+ word characters). If anything is unexpected, ABORT and tell me.
5. A deploy only adds or overwrites files; it never deletes. If a page or asset is removed or renamed in the repo, tell me the exact file names so I delete them in public_html by hand.
6. Do not rename or delete root .html pages or assets without telling me the list first.
7. No cron jobs, scheduled tasks, loops, or background reviewers.
8. Never print tokens, passwords or hashes in any reply, file or commit message.
9. Do not delete files without asking. Stop after each group of work and wait for my "go".
10. After any sandbox reset: read WORKLOG.md, RULES.md, DEPLOY.md and git log before doing anything.
