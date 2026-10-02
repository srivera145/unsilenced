# Launch checklist

Everything to do and check before Unsilenced goes on the public internet, in
order. Tick each box. Commands assume the project is at `/var/www/unsilenced`
and the web server runs as `www-data`; adjust for your server.

## 1. Server

- [ ] PHP 8.2 or newer, with `pdo_mysql`, `mbstring` and `sodium` (the
      evidence vault; built into most PHP packages and the official Docker
      image, but commented out in XAMPP's `php.ini`: `extension=sodium`).
      Password hashing needs Argon2id support (`php -r "var_dump(defined('PASSWORD_ARGON2ID'));"`).
- [ ] MySQL 8.0 or MariaDB 10.11 (CI tests against MySQL 8.0; development
      uses MariaDB 10.11).
- [ ] Apache 2.4 with `mod_rewrite`, or nginx with PHP-FPM.
- [ ] Composer, cron, and systemd (or Supervisor) for the queue worker.
- [ ] The web server's document root is **`public_html/` only**. Nothing else
      in the project may be reachable over the web (`.env`, `storage/`,
      `vendor/`).

## 2. Code

```bash
git clone https://github.com/srivera145/unsilenced.git /var/www/unsilenced
cd /var/www/unsilenced
composer install --no-dev --optimize-autoloader
chown -R www-data:www-data storage
```

- [ ] `storage/logs/` is writable by the web server and by the user that runs
      cron and the queue worker.

## 3. `.env`

Copy `.env.example` to `.env` and set every value below. Do not copy a
development `.env`: it may carry keys this site no longer uses.

| Setting | Production value | Notes |
|---|---|---|
| `APP_NAME` | `Unsilenced` | Used in sign-in emails. |
| `APP_ENV` | `production` | Turns PHP error reporting off. |
| `APP_DEBUG` | **`false`** | `true` shows error messages and file paths on the 500 page. |
| `APP_URL` | `https://<final domain>` | No trailing slash. Every absolute URL comes from it: canonical links, Open Graph tags and the share image, the sitemap, `robots.txt`, `/llms.txt`, sign-in links. |
| `CORRECTIONS_EMAIL` | an address someone reads | Shown on `/corrections`. Until it is set, that page says the address has not been set up. |
| `TRUST_PROXY` | `false`, unless... | `true` only behind a proxy or CDN that ends HTTPS and forwards plain HTTP with `X-Forwarded-Proto` (a load balancer, Cloudflare "Flexible"). It decides HSTS and the admin cookie's `Secure` flag. Never `true` when visitors can reach PHP directly. |
| `DB_HOST`, `DB_PORT` | | |
| `DB_DATABASE` | `unsilenced` | |
| `DB_USERNAME`, `DB_PASSWORD` | a dedicated user | Not root. `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES` on this database only (migrations create and change tables). Backups need nothing more: they use `--single-transaction`, not table locks. |
| `AUTH_METHOD` | `both`, `otp` or `magic_link` | How admins sign in. |
| `MAIL_MAILER` | **`smtp`** | `log` writes sign-in codes to a file instead of sending them. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` | your provider's | |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | | The sender on sign-in emails, and on survivors' optional status emails: anyone who sees their inbox sees this name. |
| `SUBMISSIONS_ENABLED` | **`false`** until section 13 is done | Survivor reports. `false`: `/submit`, `/my-report` and `/share` say "coming soon". |
| `VAULT_MASTER_KEY` | from `php database/console.php vault:keygen` | Encrypts every evidence file and account. Never in git or a backup; one offline copy. See section 13. |
| `VAULT_PATH` | a persistent directory outside `public_html` | Default `storage/vault`. |

- [ ] `chmod 640 .env` and owned by the deploy user, group `www-data`.
- [ ] Use either `.env` or real environment variables for a setting, not both.

## 4. Database and data

```bash
php database/migrate.php                          # creates the tables
php database/console.php schools:purge-fixtures --dry-run   # must list no schools
```

- [ ] Load the data: either restore a backup from development
      (`docs/RESTORE.md`, "Restoring onto a new server"), or import the files
      (`docs/DATA-REFRESH.md`, steps 2 to 6). Importing needs the queue
      worker (section 9) or `php database/queue-work.php --once`.
- [ ] `/methodology` lists the expected years (currently 2020 to 2024) and a
      few school pages show figures.

## 5. The first admin

```bash
php database/console.php admin:grant you@your-organization.org
```

- [ ] Sign in at `/login` with a code, and (if `AUTH_METHOD` allows it) with
      an emailed link opened from your webmail. Both should land on `/admin`.
- [ ] An admin left idle for 2 hours is signed out (nothing to set; this is
      for awareness).

## 6. HTTPS

- [ ] A certificate for the domain (Let's Encrypt or your host's), renewing
      automatically.
- [ ] The web server redirects every `http://` request to `https://`.
- [ ] Behind Cloudflare: SSL mode **Full (strict)**, so the origin also uses
      HTTPS. In "Flexible" mode the origin sees HTTP; then set `TRUST_PROXY=true`.
- [ ] Check, once HTTPS works on every page:

  ```bash
  curl -sI https://<domain>/ | grep -i strict-transport-security   # max-age=31536000
  curl -s -D - -o /dev/null https://<domain>/admin | grep -i set-cookie
  # set-cookie: PHPSESSID=...; path=/; secure; HttpOnly; SameSite=Strict
  ```

  HSTS tells browsers to use HTTPS for a year. Don't enable HTTPS on a
  temporary domain you will abandon. `includeSubDomains` and `preload` are
  deliberately not set; add them only when the domain and every subdomain are
  settled and on HTTPS.

## 7. Web server privacy

- [ ] Apply `docs/DEPLOY-PRIVACY.md` (Apache or nginx): the access log holds
      no IP, Referer, User-Agent or query string; the error log no client
      address; no second access log; `/server-status` without client
      addresses.
- [ ] Apache: `ServerTokens Prod` and `ServerSignature Off`, so responses do
      not announce the Apache, OS and PHP versions (the Docker image does this).
- [ ] Check the real log. Make requests that carry all four, then run the
      same check CI runs:

  ```bash
  curl -s -o /dev/null -A "launch-check-agent" -e "https://referer.example/" "https://<domain>/schools?q=launchcheck"
  sudo tail -n 200 /var/log/apache2/access.log > /tmp/access.log
  sh scripts/check-access-log.sh /tmp/access.log launch-check-agent referer.example launchcheck
  ```

  It must end with `OK`. It fails on any line that is not exactly time,
  request (without query string), status, bytes and duration, so an IP
  address anywhere is caught.
- [ ] CDN or host logs you cannot configure: see "Things the app cannot
      control" in `docs/DEPLOY-PRIVACY.md`.

## 8. Backups

- [ ] Choose where backups go. The default, `storage/backups/`, is on the same
      disk as the database: if the server is lost, so are they. Set
      `BACKUP_DIR` to another disk, and copy the folder off the server daily
      (rsync, rclone, your host's snapshot service).
- [ ] Cron, as the deploy user (`crontab -e`):

  ```
  15 3 * * * cd /var/www/unsilenced && bash scripts/backup.sh >> storage/logs/backup.log 2>&1
  ```

- [ ] The next morning, `tail storage/logs/backup.log` shows a line ending in
      `kept`. Once there is a vault, the line also names `vault-<time>.tar.gz`:
      the encrypted evidence files. The master key is never in a backup, so
      restoring needs the offline copy of `VAULT_MASTER_KEY` (`RESTORE.md`).
- [ ] Do one test restore now, into a scratch database (`docs/RESTORE.md`,
      steps 3 and 4), and confirm the row counts match.

## 9. Queue worker as a service

Imports run in the queue. `/etc/systemd/system/unsilenced-queue.service`:

```ini
[Unit]
Description=Unsilenced queue worker (data imports)
After=network.target mysql.service mariadb.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/unsilenced
ExecStart=/usr/bin/php database/queue-work.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now unsilenced-queue
systemctl status unsilenced-queue       # active (running)
```

- [ ] Running, and restarted after every deploy
      (`sudo systemctl restart unsilenced-queue`): it loads the code once.
- [ ] Without systemd: cron every minute instead,
      `* * * * * cd /var/www/unsilenced && php database/queue-work.php --once`.

## 10. Logs

- [ ] Rotate `storage/logs/*.log`. `/etc/logrotate.d/unsilenced`:

  ```
  /var/www/unsilenced/storage/logs/*.log {
      weekly
      rotate 8
      compress
      missingok
      notifempty
      copytruncate
  }
  ```

## 11. Last checks on the live site

- [ ] `https://<domain>/up` returns `{"status":"ok","database":true}`. Point an
      uptime monitor at it if you use one (it accepts GET and HEAD). With
      submissions on it also says `"vault":true`, and answers 503 if sodium,
      the master key or a safe `VAULT_PATH` goes missing. Alert on the status
      code, not on the word "ok": the body says `"status":"ok"` even on a 503.
- [ ] On a phone: tap **Quick exit** on a school page and land on weather.com;
      press Back and you are not on the site. With a keyboard, Esc twice does
      the same.
- [ ] `/no-such-page` shows the branded page with the quick exit and the
      hotline.
- [ ] `/corrections` shows the corrections address.
- [ ] `https://<domain>/sitemap.xml`, `/robots.txt` and `/llms.txt` use the
      real domain.
- [ ] Paste a school page's link into a private message to yourself: the
      preview shows the navy "Enough." card and the school's name.
- [ ] CI is green on the commit you deployed.

## 12. Still needs a lawyer

The site is built to show only what has been reviewed; these are the parts
waiting on review.

- [ ] **Resource pages** (`/resources/get-help`, `/resources/your-options`,
      `/resources/save-evidence`): medical, legal and reporting guidance.
      Admin → Resource pages.
- [ ] **State pages**: statute-of-limitations and legal-resource text. Each
      stays unpublished until a reviewer and review date are recorded in
      Admin → State pages; the public `/states/xx` pages show no legal text
      until then.
- [ ] **Corrections page** (`/corrections`, new in Phase 1.3): what we
      promise to check and correct, and what we ask people not to send.
- [ ] **Accountability records**: the summary policy (our own words, no
      individual names) before the first record is published.
- [ ] Whether the site needs a privacy notice or terms of use page. With
      survivor reports it almost certainly does: what is stored, for how
      long (backups keep a withdrawn report until they rotate out), who can
      read what, and how to withdraw.
- [ ] **Survivor reports** (Phase 2), everything in section 13's "Legal
      review" box, before `SUBMISSIONS_ENABLED=true`.

## 13. Survivor reports (Phase 2)

How it works: `docs/SURVIVOR-REPORTS.md`. Leave `SUBMISSIONS_ENABLED=false`
until every box here is ticked.

**Legal review**

- [ ] `docs/ILLEGAL-CONTENT.md` completed and approved: the legal contact,
      NCMEC/CyberTipline registration and reporting, preservation, who may
      decrypt and send a quarantined file. Until then it is a placeholder.
- [ ] `evidence.preserve_quarantined` (keep a quarantined file when its
      author withdraws) confirmed or switched off.
- [ ] The form's wording: what we publish and never publish, the intimate
      images attestation, the email warning, "true to the best of my
      knowledge".
- [ ] The school-page wording ("What survivors have told us", the Clery
      comparison note) and the published-account format.
- [ ] Private reports, which no admin can read: acceptable to host?
- [ ] How subpoenas and law-enforcement requests are answered.
- [ ] The privacy notice (section 12).

**Server**

- [ ] `php -m | grep sodium` lists it, for the web server's PHP and the CLI.
      `composer install` refuses to run without it (`ext-sodium` in
      `composer.json`), and `/up` answers 503 while submissions are on and
      sodium or the master key is missing.
- [ ] Generate the master key once:

  ```bash
  php database/console.php vault:keygen     # prints VAULT_MASTER_KEY=...; put it in .env
  ```

- [ ] **Store `VAULT_MASTER_KEY` in a password manager the operators share,
      and keep one offline copy** (printed or on an encrypted USB drive, in a
      locked place). It is never in git or a backup, so these two copies are
      the only way back.
  - **If it is lost, every evidence file is unrecoverable**, and so is every
    account, note and email: nothing and no one can decrypt them.
  - **If it leaks** (a `.env` copied somewhere it should not be, a server
    compromise, someone who had it leaves), **rotate it**, the same day:

    ```bash
    # 1. .env: SUBMISSIONS_ENABLED=false. Ask the moderators to stop.
    # 2. A fresh backup: bash scripts/backup.sh
    php database/console.php vault:keygen      # 3. put the value in .env as VAULT_NEW_MASTER_KEY=...
    php database/console.php vault:rotate      # 4. shows what it will do
    php database/console.php vault:rotate --confirm
    # 5. .env, as the command then says: VAULT_MASTER_KEY = the new key,
    #    delete VAULT_NEW_MASTER_KEY, add the VAULT_LOOKUP_KEY line it prints.
    php database/console.php vault:check       # 6. Ready
    # 7. A new backup; replace the password-manager entry and the offline
    #    copy; SUBMISSIONS_ENABLED=true.
    ```

    Rotation re-encrypts every evidence file with new file keys and
    re-seals every encrypted text, so the old key opens nothing current. If
    it stops part-way, run it again: it skips what is done. It cannot reach
    copies already made: **every backup from before the rotation still opens
    with the old key**. Delete those you can, and treat the rest as exposed
    (whoever has the old key and a backup can read it).

- [ ] `VAULT_PATH` on persistent storage outside `public_html`, owned by the
      web server's user, mode 700, and the same for `storage/sessions`:

  ```bash
  mkdir -p storage/vault storage/sessions
  chown www-data:www-data storage/vault storage/sessions
  chmod 700 storage/vault storage/sessions
  ```

- [ ] PHP upload limits: `upload_max_filesize=20M`, `post_max_size=64M`,
      `max_file_uploads=20` (the Docker image sets these). nginx also needs
      `client_max_body_size 64m;`.
- [ ] HTTPS everywhere (section 6). With `APP_ENV=production` the survivor
      pages refuse to open over plain HTTP.
- [ ] Cron, as the web server's user:

  ```
  20 3 * * * cd /var/www/unsilenced && php database/console.php survivor:purge-rejected >> storage/logs/maintenance.log 2>&1
  5 * * * *  cd /var/www/unsilenced && php database/console.php survivor:expire-share-links >> storage/logs/maintenance.log 2>&1
  ```

- [ ] SMTP set up (`MAIL_MAILER=smtp`): status emails go out as they happen.

**Check, with `SUBMISSIONS_ENABLED=true`**

- [ ] `php database/console.php vault:check` ends with `Ready`.
- [ ] On a phone: send a test report with a photo, save the key, open
      `/my-report`, make a share link and open it in a private window;
      the download's SHA-256 matches the one on the page.
- [ ] Sign in as an admin, view the photo (it asks for a code if the last one
      is older than 15 minutes), redact, approve; the school page shows
      "Fewer than 3 survivor reports so far."
- [ ] Withdraw the test report from `/my-report`; it disappears from the queue
      and the school page.
- [ ] `curl -sI https://<domain>/submit | grep -i set-cookie` shows
      `sid=...; path=/submit; secure; HttpOnly; SameSite=Strict`, and a
      public page still sets none.
- [ ] Search the web server's and the app's logs for the test report's key and
      share token: nothing.
