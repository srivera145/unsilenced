# Unsilenced

Public data on how U.S. colleges handle sexual assault. Headline: **Enough.** Phase 1 uses public data only: IPEDS institutions and enrollment, Clery Act statistics, and admin-entered public accountability records. No survivor accounts, submissions or uploads.

Built on Keel (below). Phase 1.1 removed the Keel features this site does not use: Stripe billing, file uploads, API tokens, organizations, onboarding, the Keel docs, dashboard, settings, super-admin and welcome pages, and their tables (`database/migrations/018_drop_unused_keel_tables.sql`).

## Run it

```bash
composer install
cp .env.example .env                       # set DB_*, APP_URL, MAIL_*
php database/migrate.php                   # schools, Clery, accountability, resource + state pages
php database/console.php admin:grant you@example.org
```

Sign in at `/login` (admins only; there are no public accounts). The admin panel is at `/admin`.

## Import data

Imports are CLI commands that check the file's columns, record an import run and queue a job:

```bash
php database/console.php import:schools storage/imports/HD2023.csv                 # IPEDS directory
php database/console.php import:schools storage/imports/DRVEF2023.csv --year=2023  # IPEDS enrollment
php database/console.php import:clery storage/imports/oncampuscrime.csv 2023       # one Clery file, one year
php database/queue-work.php --once                                                  # process the queue
```

- `--headers` prints a file's columns; `--now` runs the import without the queue.
- Column names live in `config/unsilenced.php` (`ipeds.columns`, `clery.*`). **They were written without a real file to check against**, so run `--headers` on the first real download and fix the map. A missing required column stops the import with a message naming it.
- Imports are idempotent: schools key on UNITID, Clery rows on school + year + location. Re-running a file changes nothing.
- Clery files can each carry some offenses (crime files: sex offenses; VAWA files: dating violence, domestic violence, stalking). Import both for a location and year and they fill one row. The location comes from `--location=`, a configured column, or the file name.
- Fixtures with fictional schools (UNITID 990000-990999) are in `tests/fixtures/`; see `tests/Feature/ImportFeatureTest.php`. If you imported them into a real database to try things out, remove them and their Clery and accountability rows with:

  ```bash
  php database/console.php schools:purge-fixtures --dry-run   # lists the schools and counts the rows
  php database/console.php schools:purge-fixtures             # deletes them
  ```

Every run is listed under Admin → Imports with rows added, updated, unchanged, skipped and the first 200 row errors.

## Safety design

- **No session or cookie on public pages.** Only `/admin`, `/login`, `/logout` and `/auth/*` start a session (`src/App/Support/SessionRoutes.php`). `SessionRoutesTest` fails if a route's middleware disagrees.
- **Nothing third-party.** A `Content-Security-Policy` header restricts scripts, styles, fonts, images and requests to this origin. `Referrer-Policy: no-referrer` means outbound links and the quick exit don't reveal where the visitor came from.
- **Quick exit** on every page (`views/partials/quick-exit.php`): click it or press Esc twice and the page blanks and becomes weather.com through `location.replace()`. Public pages also keep the whole visit to one Back-history entry (`single_history_entry` in config), so Back after a quick exit never returns to the site. It cannot erase global browser history; the Get help page explains private browsing.
- **No IPs in logs.** PHP errors go to `storage/logs/app.log` rather than Apache's error log, and `ErrorHandler` scrubs IP addresses and query strings from what it logs. Public routes don't use the throttle, which stores IPs. The web server's own logs are server configuration: the Docker image logs no client IP, Referer or User-Agent (`docker/apache/`), and **`docs/DEPLOY-PRIVACY.md` has the Apache and nginx settings for any other server**, plus what a CDN such as Cloudflare logs that the app cannot control.
- **Neutral tab titles.** Resource pages have a separate `browser_title`. Admin saves are rejected if a tab title contains a word from `neutral_title_blocklist`.
- **No names.** Accountability summaries are checked by `NameDetector`; a flagged summary is not saved until an admin confirms, and the confirmation is recorded.
- **No unreviewed legal text.** State pages show legal fields only once `published`, and can only be published once a reviewer and review date are recorded.

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
  js/keel.js        Admin theme sync and confirmation dialogs. No build step.
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
docs/               Deployment notes.
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
docker compose exec app composer install
docker compose exec app php database/migrate.php
```

Then visit `http://localhost:8080`. The app's database host is `db`. The image's Apache logs no client IP, Referer or User-Agent; see `docs/DEPLOY-PRIVACY.md`.

## How auth works

- **OTP**: 6-digit code, hashed with `password_hash()`, expires in 10 minutes, rate-limited to 5 requests per 15 minutes per user.
- **Magic Link**: 32-byte random token, hashed with SHA-256, expires in 15 minutes, single-use, same rate limit.
- Both write to `auth_tokens`. Only users with `is_admin = 1` get a code or link (`php database/console.php admin:grant`); every other address gets the same response, so the form does not reveal who has access. A successful verify regenerates the session ID and redirects to `/admin`.

## Queue worker

A database-backed queue (`jobs` and `failed_jobs`), no Redis or broker. Imports are pushed with `Keel\Core\Queue::push(...)`. Run `php database/queue-work.php --once` from cron every minute, or `php database/queue-work.php` under systemd or Supervisor.

## Security and errors

- State-changing requests carry a CSRF token: the head partial outputs a `csrf-token` meta tag on session pages, and forms use `\Keel\Core\Csrf::field()`.
- The sign-in routes sit behind a throttle keyed by client IP and path.
- Missing routes render a branded 404 page and uncaught exceptions a branded 500 page.
- `public_html/index.php` sets `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, a same-origin `Content-Security-Policy` and a `Permissions-Policy` on every response.

## Health checks

`GET /up` returns `200` with `{"status":"ok","database":true}` when the database is reachable, and `503` with `"database":false` when it is not.

## Testing and CI

- Unit and feature tests live in `tests/` and run with PHPUnit: `composer test:all` sets up the test database and runs them all.
- GitHub Actions (`.github/workflows/ci.yml`) runs on every push and pull request: PHP 8.2, a MySQL service, `composer install`, the migrations, then PHPUnit.

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution and PR expectations.
