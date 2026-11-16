# DEPLOY

The cPanel deployment steps. Read RULES.md alongside this.

**Pushing to GitHub does NOT change the live site.** The live site only changes when the owner clicks Update from Remote then Deploy HEAD Commit in cPanel. GLM never deploys — GLM only commits and pushes to GitHub.

- Repo folder used by cPanel: /home/venutaihall/VCMhall-deploy. Never edit, upload or delete files inside it by hand; that makes the tree "dirty" and blocks deploys. Only use Update from Remote, then Deploy HEAD Commit.
- Before each deploy: back up public_html (Compress). After: hard-refresh venutaihall.com on a phone, one test booking, booked dates, admin login.
- Rollback: copy files back from public_html(backup-copy), or GLM makes a git revert commit (never a force push), then Update + Deploy.
- .cpanel.yml copies the root *.html pages, styles.css/script.js and their .min files, assets/, admin/, server-patch/ and booking.php into public_html. Server-only files (booking-config.php, the root .htaccess, the database, admin/sessions contents) stay untouched.
- A deploy only adds or overwrites files; it never deletes. Files removed from the repo must be deleted from public_html by hand.
- Removed on 2026-11-16 (delete from public_html if still there): admin-old/ (old admin login without attempt limits), deploy-test.html, dummy.html.
- Public-site security headers live in the server's root .htaccess; the block to paste is in htaccess-security-snippet.txt.
- server-patch/ is backend code only; its .htaccess blocks browser access. The daily reminder cron runs it with the php command, not a URL.
