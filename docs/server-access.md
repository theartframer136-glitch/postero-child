# How changes reach the live site (since 23 Nov 2026)

theartframer.us moved from the Agency plan to a shared Hostinger Cloud plan.
There is no SSH, SFTP or hPanel login for this team any more, by design: the
plan hosts other sites too.

- **Theme code:** merge to `main` here and the server pulls it into
  `wp-content/themes/postero-child` within about a minute, restarts PHP and
  purges the LiteSpeed cache. Nothing in this repo deploys; do not add an
  SSH, FTP or rsync deploy (it would fight the server's own copy).
- **Server tasks** (WP-CLI, running a script from `tools/`, logs, backups,
  cache purge): a request file in the private repo
  `theartframer136-glitch/artframer-ops`. Its README lists the actions.
  Restores, PHP-limit changes and hosting changes wait for the owner's
  approval there.
- **Checks from outside** (page HTML, purchase path, security, SEO) still run
  from this repo's GitHub Actions: they only fetch the public site.

The 59 workflows that logged in to the old server over SSH were removed on
23 Nov 2026; they are in git history if one is ever needed as a reference.
One of them, Fetch Reels, refreshed the Products In Motion videos daily; it
has no replacement yet, so new reels are not picked up automatically.
