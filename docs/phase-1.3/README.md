# Phase 1.3: launch readiness

Verified on 2026-10-01: PHP 8.2.12, MariaDB 10.11.18 (development), MySQL
8.0.44 (portable, default `sql_mode`, the engine CI uses), Apache 2.4.57 on
Windows, headless Chrome 154. No Docker on this machine, so the Docker job
runs only in GitHub Actions.

## Tests

105 tests (81 before this phase), all passing on both databases:

- MariaDB 10.11: `composer test:all`.
- MySQL 8.0.44, run the way CI runs it: settings as environment variables,
  `variables_order=GPCS` (php.ini-production), a database that did not exist.
  All 20 migrations applied, a second run reported "No pending migrations.",
  and the resulting schema (every column, type and index, and the 3 resource
  pages and 51 state pages the migrations seed) is identical to MariaDB's.

The earlier CI file could not have passed: with DB settings in the
environment, `.env.testing` is not copied into `$_ENV`, and `tests/TestCase.php`
read only `$_ENV`. It now reads the environment too.

## Austin Community College (UNITID 222992)

The possible double count from Phase 1.2 was real. Health Science Academy
(campus 222992036) is only in the 2020–22 files, with 1 public-property
domestic-violence report for 2022. The 2021–23 and 2022–24 files drop the
campus, and Riverside (222992007) goes from 0 to 1 for the same cell. Every
file reports 1 for the school; the campus sum was 2.

| 2022, public property, domestic violence | Before | After |
|---|--:|--:|
| School figure (`clery_stats`) | 2 | **1** |
| School total in the 2020–22 / 2021–23 / 2022–24 file | 1 / 1 / 1 | 1 / 1 / 1 |
| ACC's 2022 domestic violence (all locations) | 3 | 2 |
| ACC's 2022 dating violence, domestic violence and stalking | 71 | 70 |

Fix: each file's school totals are stored (`clery_file_totals`, migration
020), and a school figure is the sum of its campuses capped at the highest
total any single file reported. Nothing is deleted; the campus rows are as
before. `php database/console.php clery:spot-check 222992` shows the file
totals and flags the capped figure.

Effect on the whole database, checked two ways:

- An independent recomputation from the raw CSVs (Python, not the importer)
  over all 541,027 school-year-location-offense cells: the cap binds in
  exactly one, this one.
- `clery_stats` before and after re-importing every file: 112,532 rows, 1
  changed (this one). All 26 imports: 0 campus rows added or updated.

Test: `ImportFeatureTest::testAReportMovedFromADroppedCampusIsCountedOnce`,
with fixtures shaped like ACC's rows (`tests/fixtures/clery-moved-campus/`),
in both import orders and on a re-run. One older fixture expectation changed
from 10 to 9: a closed campus (2) plus a main campus revised up by 1, where
no file ever reported more than 9 for the school.

## Response headers (local Apache, `http://unsilenced.local`)

```
GET /
HTTP/1.1 200 OK
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: no-referrer
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'
Permissions-Policy: browsing-topics=(), interest-cohort=(), camera=(), microphone=(), geolocation=()
Content-Type: text/html; charset=UTF-8

GET /schools/ny/cornell-university
HTTP/1.1 200 OK
(the same six headers; no Set-Cookie)

GET /admin
HTTP/1.1 302 Found
(the same six headers)
Set-Cookie: PHPSESSID=…; path=/; HttpOnly; SameSite=Strict
Cache-Control: no-store, no-cache, must-revalidate
Location: /login
```

Plain HTTP, so no HSTS and no `Secure`. The same app behind a trusted HTTPS
proxy (`TRUST_PROXY=true`, `X-Forwarded-Proto: https`) adds
`Strict-Transport-Security: max-age=31536000` and
`Set-Cookie: PHPSESSID=…; path=/; secure; HttpOnly; SameSite=Strict`.

**style-src:** `'unsafe-inline'` is gone from styles too. The 19 inline
`style=""` attributes were all layout values (`--rail`, `--min`, widths,
padding) and are now classes in `public_html/css/keel.css`. Deck's own
scripts change styles only through `element.style`, which CSP allows.

## Browser checks (headless Chrome)

Off-site pages (weather.com, a "previous site", a "webmail" page) were
served by request interception, so nothing left the machine.

**Quick exit**

| Check | Result |
|---|---|
| Home → Schools → New York → search "cornell" → Cornell: Back-history entries for the site | 1 |
| Click Quick exit | on weather.com after 56 ms |
| Back-history after the click | previous site, weather.com (none of this site) |
| Back from weather.com | the previous site |
| Referer sent to weather.com | none |
| One Esc; two Esc presses 1.1 s apart | nothing happens |
| Esc twice (150 ms apart) | on weather.com 171 ms after the first press; nothing of the site in history; Back goes to the previous site |
| Esc twice with focus in the search box | leaves |
| JavaScript off | a plain link (`href="https://weather.com/"`, `rel="noreferrer"`); clicking it goes there with no Referer; Esc does nothing; Back returns to the site, as with any link |

**Content-Security-Policy:** 16 public pages (home, search, state list, two
school pages, resources, states, methodology, corrections, 404, sign-in) and 8
admin pages: no violation and no script error. The admin theme toggle,
sign-in by code (`login.js`) and sign-out work.

**Sign-in link opened from another site** (`SameSite=Strict`): lands on
`/admin` signed in. The trace: `/auth/magic` without the cookie → the
one-line page → `/auth/magic` again from this site → 302 `/admin` with the
cookie sent. A link to `/admin/imports` from another site keeps the admin
signed in the same way. Without the hop, Chrome blocks the cookie on the
redirect to `/admin` ("SchemefulSameSiteStrict") and the admin lands on
`/login`. Browsers send the `Sec-Fetch-Site` header the hop relies on only to
HTTPS sites and loopback, so this was checked at `http://127.0.0.1`; on plain
HTTP `unsilenced.local` the hop does not trigger (paste the link into the
address bar instead).

**Error pages:** a 500 with the database unreachable and `APP_DEBUG=false`,
and a PHP fatal error after a page had rendered: both return the branded 500
page with the quick exit, help bar, hotline and Home link, and no error text.

Screenshots (375 px wide): [404 light](screenshots/error-404-375-light.png),
[404 dark](screenshots/error-404-375-dark.png),
[500 light](screenshots/error-500-375-light.png),
[500 dark](screenshots/error-500-375-dark.png).

## Backup and restore

`scripts/backup.sh` against the development database: 3.6 MB, 4.7 s. Restored
into a new database in 16 s.

| Table | Live | Restored |
|---|--:|--:|
| accountability_items | 0 | 0 |
| activity_log | 3 | 3 |
| auth_tokens | 0 | 0 |
| clery_campus_stats | 207,636 | 207,636 |
| clery_file_totals | 201,264 | 201,264 |
| clery_stats | 112,532 | 112,532 |
| failed_jobs | 0 | 0 |
| import_runs | 78 | 78 |
| jobs | 0 | 0 |
| migrations | 20 | 20 |
| rate_limits | 4 | 0 (structure only, by design) |
| resource_pages | 3 | 3 |
| schools | 5,985 | 5,985 |
| state_pages | 51 | 51 |
| users | 0 | 0 |

`CHECKSUM TABLE` matches for schools, clery_stats, clery_campus_stats,
clery_file_totals, import_runs, resource_pages, state_pages and migrations.
Retention (`BACKUP_KEEP=2`, three runs) kept the newest two; a dump with a
wrong password exited non-zero and left no file.

## Other changes found along the way

- `HEAD` requests returned 404 on every page (the router matched GET only).
  They now get the GET route's status.
- `rate_limits` rows, keyed by sign-in attempters' IP addresses, were reset
  but never deleted. Expired rows are now deleted.
- The Dockerfile never installed `vendor/`, so the image could not run on
  its own, and without a `.dockerignore` a local build copied `.env` into it.
- Apache in the image sends `Server: Apache` only (`ServerTokens Prod`).

## Not verified

- **The CI workflow itself.** Not run on GitHub. Job 1's steps were run
  locally against MySQL 8.0.44 with the same commands. Job 2's Docker build,
  container start and in-container commands were not run anywhere: no Docker
  here. Its log checker (`scripts/check-access-log.sh`) was tested on sample
  logs in the private format and in the combined format.
- **A real HTTPS connection.** HSTS and the `Secure` cookie were checked
  through the trusted-proxy path and unit tests, not over TLS.
- **Safari and Firefox.** Browser checks used Chrome only.
- **The share card in real link previews** (no requests to Slack, Facebook,
  etc. were made).
- **Data refresh with next year's files**, which do not exist yet. The
  runbook's download page names and menu wording on the IPEDS and Campus
  Safety sites were not checked live (no external requests from here).
