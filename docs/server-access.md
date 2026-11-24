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

## Products In Motion reels

Fetch Reels was replaced by **Reels to R2** (`.github/workflows/reels-to-r2.yml`):
daily, it asks the site which reels have no real video yet
(`/?af_pim_ids=missing`), downloads them with yt-dlp, and stores each in our
Cloudflare R2 bucket as `pim/<video id>.mp4`. The theme
(`inc/pim-r2-pull.php`) pulls waiting files into `uploads/pim/` every hour and
purges the home page; the card then plays the real reel instead of its
still-frame clip.

YouTube refuses downloads from GitHub's servers unless the request carries a
signed-in cookie. To give it one:

1. Open a **private/incognito** window and sign in to YouTube with a
   **secondary** Google account (not the channel owner's main account).
2. With a cookies.txt exporter extension, export the cookies for youtube.com
   (Netscape format), then close the window without signing out.
3. GitHub → this repo → Settings → Secrets and variables → Actions → secret
   `YT_COOKIES` → paste the whole file.

When YouTube stops accepting it (the run warns), export a fresh one.
