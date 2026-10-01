# Launch checklist

Everything to do and check before Unsilenced goes on the public internet, in
order. Tick each box. Commands assume the project is at `/var/www/unsilenced`
and the web server runs as `www-data`; adjust for your server.

## 1. Server

- [ ] PHP 8.2 or newer, with `pdo_mysql` and `mbstring`.
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
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | | The sender on sign-in emails. |

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
      `kept`.
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
      uptime monitor at it if you use one (it accepts GET and HEAD).
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
- [ ] Whether the site needs a privacy notice or terms of use page.
