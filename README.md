# Unsilenced

Public data on how U.S. colleges handle sexual assault. Headline: **Enough.** Phase 1 uses public data only: IPEDS institutions and enrollment, Clery Act statistics, and admin-entered public accountability records. No survivor accounts, submissions or uploads.

Built on Keel (below). Phase 1.1 removed the Keel features this site does not use: Stripe billing, file uploads, API tokens, organizations, onboarding, the Keel docs, dashboard, settings, super-admin and welcome pages, and their tables (`database/migrations/018_drop_unused_keel_tables.sql`).

## Run it

```bash
composer install
cp .env.example .env                       # set DB_*, APP_URL, MAIL_*, CORRECTIONS_EMAIL
php database/migrate.php                   # schools, Clery, accountability, resource + state pages
php database/console.php admin:grant you@example.org
```

Sign in at `/login` (admins only; there are no public accounts). The admin panel is at `/admin`.

Going live: **[docs/LAUNCH-CHECKLIST.md](docs/LAUNCH-CHECKLIST.md)** (every `.env` value, HTTPS, the web server's privacy settings, backups, the queue worker). The yearly data update: **[docs/DATA-REFRESH.md](docs/DATA-REFRESH.md)**.

## Import data

Imports are CLI commands that check the file's columns, record an import run and queue a job. The files go in `storage/imports/` (git-ignored):

```bash
sh scripts/queue_all_imports.sh        # queues everything below, in order (the newest hd*/drvef* files)
php database/queue-work.php --once     # process the queue (about 5 minutes for the 2020-2024 files)
```

or one file at a time:

```bash
php database/console.php import:schools storage/imports/hd2025.csv                 # IPEDS directory
php database/console.php import:schools storage/imports/drvef2024.csv              # IPEDS enrollment (year from the name)
php database/console.php import:clery storage/imports/Oncampuscrime222324.csv      # every year in the file
php database/console.php import:clery storage/imports/Oncampuscrime222324.csv 2024 # one year only
php database/console.php clery:spot-check 190415                                   # stored figures next to the raw rows
```

- `--headers` prints a file's columns; `--now` runs the import without the queue.
- Column names live in `config/unsilenced.php` (`ipeds.columns`, `clery.*`) and were checked against the real 2020-2024 files in Phase 1.2 (`docs/phase-1.2/`). A missing required column stops the import with a message naming it.
- **Clery files cover three years each** (`Oncampuscrime222324.csv` has 2022, 2023 and 2024), so every year is in up to three files. Figures are stored per campus (UNITID_P) in `clery_campus_stats`, and for each campus the newest file that has a figure wins; a blank never erases a figure, and a campus that drops out of later files keeps what earlier files reported. Each file's school totals are stored too (`clery_file_totals`). School figures (`clery_stats`, what the site shows) are rebuilt after every import as the sum of each school's campuses, **capped at the highest total any single file reported**: when a later file moves a dropped campus's reports to another campus, they are not counted twice (Austin Community College, 2022; Phase 1.3). The files can be imported in any order.
- Only the crime and VAWA files for the four locations are imported. The hate-crime, arrest, discipline, fire, unfounded and "Reported" files in the same download are refused by name: the hate-crime files have `RAPE22`-style columns that count hate crimes only.
- Imports are idempotent: schools key on UNITID, Clery rows on campus + year + location. Re-running every file adds and changes nothing.
- Only schools with Clery figures are listed in search, state lists and the sitemap (`schools.has_clery_data`, rebuilt after each import). Others keep their page, marked `noindex`.
- Fixtures with fictional schools (UNITID 990000-990999) are in `tests/fixtures/`; see `tests/Feature/ImportFeatureTest.php`. If you imported them into a real database to try things out, remove them and their Clery and accountability rows with:

  ```bash
  php database/console.php schools:purge-fixtures --dry-run   # lists the schools and counts the rows
  php database/console.php schools:purge-fixtures             # deletes them
  ```

Every run is listed under Admin → Imports with rows added, updated, unchanged, skipped and the first 200 row errors.

## Safety design

- **No session or cookie on public pages.** Only `/admin`, `/login`, `/logout` and `/auth/*` start a session (`src/App/Support/SessionRoutes.php`). `SessionRoutesTest` fails if a route's middleware disagrees.
- **Nothing third-party, nothing inline.** The `Content-Security-Policy` header (`src/Core/SecurityHeaders.php`) restricts scripts, styles, fonts, images and requests to this origin, with no `'unsafe-inline'`: an injected script, event handler or `style=""` does not run. Behaviour lives in `public_html/js/`, per-element layout values are classes in `keel.css`, and `ContentSecurityFeatureTest` fails if any page uses an inline script, handler or style. `Referrer-Policy: no-referrer` means outbound links and the quick exit don't reveal where the visitor came from. Over HTTPS, `Strict-Transport-Security: max-age=31536000`.
- **Quick exit** on every page, error pages included (`views/partials/quick-exit.php`, `public_html/js/quick-exit.js`): click it or press Esc twice and the page blanks and becomes weather.com through `location.replace()`. Without JavaScript it is an ordinary link to the same place. Public pages also keep the whole visit to one Back-history entry (`single_history_entry` in config, `public_html/js/single-history.js`), so Back after a quick exit never returns to the site. It cannot erase global browser history; the Get help page explains private browsing.
- **Admin session.** The cookie is `HttpOnly`, `SameSite=Strict`, and `Secure` on HTTPS; an admin idle for 2 hours is signed out. A Strict cookie is left off requests that start on another site, so a sign-in link opened from webmail first loads a one-line page that reloads the same address from this site (`src/App/Support/SameSiteHop.php`).
- **No IPs in logs.** PHP errors go to `storage/logs/app.log` rather than Apache's error log, and `ErrorHandler` scrubs IP addresses and query strings from what it logs. Public routes don't use the throttle, which stores IPs. The web server's own logs are server configuration: the Docker image logs no client IP, Referer or User-Agent (`docker/apache/`), and **`docs/DEPLOY-PRIVACY.md` has the Apache and nginx settings for any other server**, plus what a CDN such as Cloudflare logs that the app cannot control.
- **Neutral tab titles.** Resource pages have a separate `browser_title`. Admin saves are rejected if a tab title contains a word from `neutral_title_blocklist`.
- **No names.** Accountability summaries are checked by `NameDetector`; a flagged summary is not saved until an admin confirms, and the confirmation is recorded.
- **No unreviewed legal text.** State pages show legal fields only once `published`, and can only be published once a reviewer and review date are recorded.

## Backups

`scripts/backup.sh` dumps the database with `mysqldump --single-transaction`, gzips it to `storage/backups/` (or `BACKUP_DIR`) with a UTC timestamp, and keeps the newest 14. Nightly from cron:

```
15 3 * * * cd /var/www/unsilenced && bash scripts/backup.sh >> storage/logs/backup.log 2>&1
```

Restoring, and checking a restore with `scripts/row-counts.sh`: [docs/RESTORE.md](docs/RESTORE.md).

## Corrections and sharing

- `/corrections` tells schools, journalists and the public how to report an error, with the address in `CORRECTIONS_EMAIL`. No form; nothing is collected. Linked from the footer and the methodology page.
- Every public page has Open Graph and Twitter card tags. The image is `public_html/share-card.png` (1200x630, rendered from the logo by `php scripts/share-card/render.php`), at an absolute URL built from `APP_URL`. School pages use the school's name as the title.

## Brand

- **Colours** are tokens in `public_html/css/keel.css` (`app.base`), never named in views: navy `#0B4F7C` (primary; Deck's brand ramp) and teal `#14B8B0` (accent; the logo mark and large shapes only, since it is 2.47:1 on white). Text in teal uses `#047873` in light mode and `#3ACCC4` in dark. Every text colour pair is listed with its contrast ratio in `docs/phase-1.1/CONTRAST.md`.
- **Logo**: `views/partials/logo.php`, inline SVG, `horizontal` (mark and wordmark) or `icon`. Its colours come from `--logo-mark` and `--logo-wordmark`.
- **Wordmark font**: Anton, self-hosted in `public_html/fonts/anton/` with its OFL licence. Used for the wordmark and the "Enough." headline only.
- **Favicons**: `public_html/favicon.svg` is the logo mark with its colour written in; `favicon-32x32.png`, `favicon-16x16.png`, `favicon.ico` and the 180px `apple-touch-icon.png` are rendered from it. Redraw them if the mark changes.

## Tests

```bash
composer test:all     # creates/migrates unsilenced_test, then runs PHPUnit
```

---

# Keel

Santos Rivera's PHP starter kit, which Unsilenced is built on: custom MVC, OTP + Magic Link auth (no passwords, ever), a mailer, a database queue, and an interface built on Deck, installed by Composer with no build step. This copy keeps only what Unsilenced uses.

## Stack

- PHP 8.2, custom MVC (no framework dependency)
- MySQL or MariaDB via PDO
- [Deck](https://get-deck.dev) CSS, published by Composer. No npm, no bundler, no build step.
- Vanilla JS
- PHPMailer (SMTP + log driver)
- DB-backed queue worker for async jobs (the imports)
- OTP and/or Magic Link auth, toggled in `.env`
- CSRF protection, throttling on the sign-in routes, an activity log of admin actions, and branded error pages

## Directory structure

```
public_html/        Web root. Only this folder is exposed by the server.
  index.php         Front controller: every request enters here.
  css/keel.css      The app's CSS and brand tokens, in the app.* layers Deck reserves.
  js/               quick-exit.js and single-history.js (public pages), login.js,
                    admin-theme.js and keel.js (admin). No build step, and no
                    inline scripts anywhere (Content-Security-Policy).
  share-card.png    The link-preview image.
  fonts/            The self-hosted wordmark font and its licence.
  deck/             Deck, published by `composer install`. Git-ignored.
src/
  Core/             Framework internals: Router, Request, Response, Database,
                    Session, View, Mailer, Env, Controller, Middleware, Csrf,
                    RateLimiter, Queue, Activity, ErrorHandler.
  App/
    Console/        database/console.php commands.
    Controllers/    Route handlers; Admin/ is the admin panel.
    Jobs/           Queued jobs.
    Middleware/     Route guards.
    Models/         Thin data-access classes.
    Services/       Business logic (sign-in, imports, school profiles).
routes/web.php      Every route.
views/              Plain PHP templates: views/partials/head.php + one file per page.
config/             unsilenced.php: site settings and the import column maps.
database/
  migrations/       Plain .sql files, run with `php database/migrate.php`.
  migrate.php       Runs pending migrations.
  queue-work.php    Queue worker.
  console.php       CLI commands.
docker/apache/      Apache config for the Docker image (privacy logging).
scripts/            Imports, backups and restore checks, the access-log
                    privacy check CI runs, the share-card renderer.
docs/               Launch checklist, data refresh, restore, deployment privacy.
storage/logs/       app.log (PHP errors) and mail.log (MAIL_MAILER=log).
```

## Setup

1. **Install dependencies**: `composer install`. This also publishes Deck's stylesheet, icon sprite and scripts into `public_html/deck/`.

2. **Environment**: `cp .env.example .env`, then fill in `DB_*`, `MAIL_*` and `APP_URL`. For a local sign-in without SMTP, set `MAIL_MAILER=log` and read the code or link from `storage/logs/mail.log`:

   ```text
   [2026-07-10 02:58:48] MAIL_MAILER=log
   To: you@example.com <you@example.com>
   Subject: Your verification code
   ...
   315638
   ```

3. **Database**: `php database/migrate.php` creates the database if it does not exist (the DB user needs permission to), runs every pending file in `database/migrations/` and records it in a `migrations` table.

4. **Auth method**: `AUTH_METHOD=otp`, `magic_link` or `both` (a tab switcher on the sign-in page).

5. **Local vhost (XAMPP or similar)**: point a vhost's `DocumentRoot` at `public_html/` with `AllowOverride All`. Without it `public_html/.htaccess` is ignored and every route except `/` 404s.

   ```apache
   <VirtualHost *:80>
       ServerName unsilenced.local
       DocumentRoot "C:/path/to/unsilenced/public_html"
       <Directory "C:/path/to/unsilenced/public_html">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

## Docker (optional)

```bash
docker compose up --build
docker compose exec app php database/migrate.php
```

Then visit `http://localhost:8080`. The app's database host is `db`. The image installs its Composer dependencies itself (the `Dockerfile`'s first stage), and `.dockerignore` keeps `.env`, `.git`, tests and local data out of it. Its Apache logs no client IP, Referer, User-Agent or query string; see `docs/DEPLOY-PRIVACY.md`. CI builds and checks this image on every push.

## How auth works

- **OTP**: 6-digit code, hashed with `password_hash()`, expires in 10 minutes, rate-limited to 5 requests per 15 minutes per user.
- **Magic Link**: 32-byte random token, hashed with SHA-256, expires in 15 minutes, single-use, same rate limit.
- Both write to `auth_tokens`. Only users with `is_admin = 1` get a code or link (`php database/console.php admin:grant`); every other address gets the same response, so the form does not reveal who has access. A successful verify regenerates the session ID and redirects to `/admin`.

## Queue worker

A database-backed queue (`jobs` and `failed_jobs`), no Redis or broker. Imports are pushed with `Keel\Core\Queue::push(...)`. Run `php database/queue-work.php --once` from cron every minute, or `php database/queue-work.php` under systemd or Supervisor.

## Security and errors

- State-changing requests carry a CSRF token: the head partial outputs a `csrf-token` meta tag on session pages, and forms use `\Keel\Core\Csrf::field()`.
- The sign-in routes sit behind a throttle keyed by client IP and path.
- Missing routes render a branded 404 page, and uncaught exceptions and PHP fatal errors a branded 500 page (`ErrorHandler::registerFatalHandler()`). Both carry the quick exit, the help bar, the hotline and a link home; with `APP_DEBUG=false` neither says anything about the error.
- `SecurityHeaders` sets `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, a same-origin `Content-Security-Policy` without `'unsafe-inline'`, a `Permissions-Policy`, and over HTTPS `Strict-Transport-Security` on every response. `HEAD` requests get the same status as `GET`.

## Health checks

`GET /up` returns `200` with `{"status":"ok","database":true}` when the database is reachable, and `503` with `"database":false` when it is not.

## Testing and CI

- Unit and feature tests live in `tests/` and run with PHPUnit: `composer test:all` sets up the test database and runs them all.
- GitHub Actions (`.github/workflows/ci.yml`) runs two jobs on every push and pull request:
  - **tests**: PHP 8.2 and MySQL 8.0; every migration on an empty database, then the full PHPUnit suite.
  - **docker**: builds the image, starts it with MySQL 8.0 and the fixture data, checks that `/up`, `/`, `/schools`, a school page, `/llms.txt` and `/corrections` return 200 and a missing page 404, checks the security headers and the admin cookie, stops the database to check the 500 page, then fails if Apache's access log (`scripts/check-access-log.sh`), its error log or the app's log holds an IP, a User-Agent, a Referer or a query string.

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution and PR expectations.
