# Phase 1.2: real data import

Run on 2026-10-01 against the dev database (MariaDB 10.11, PHP 8.2, XAMPP on Windows, opcache off).

## Files

`storage/imports/` holds 89 files. Imported: `hd2025.csv` (IPEDS directory), `drvef2024.csv` (IPEDS fall 2024 enrollment), and the 24 Campus Safety crime and VAWA files for on campus, student housing (`Residencehall*`), noncampus and public property, in three editions: `*202122.csv`, `*212223.csv` and `*222324.csv`. Not imported: hate-crime, arrest, discipline, fire, unfounded and `Reported*` files (no offense this site shows, or no location). The `Reported*` crime/VAWA files hold 1 to 4 non-blank rows each, all zeros.

`--headers` output for every file: [headers.txt](headers.txt). Column map check: [column-check.txt](column-check.txt).

## What the real files changed

- **Column names were right.** `UNITID`, `INSTNM`, `CITY`, `STABBR`, `CONTROL`, `ENRTOT`, `UNITID_P`, `RAPE{yy}`, `FONDL{yy}`, `INCES{yy}`, `STATR{yy}`, `DATING{yy}`, `DOMEST{yy}`, `STALK{yy}` are all present. The fixtures now copy the real headers, encodings (UTF-8 with BOM, CRLF) and file names; a small Windows-1252 fixture keeps the old-IPEDS encoding path tested.
- **Each Clery file covers three years**, and the editions overlap: 2022 is in all three. The importer now reads every year in a file (a single year can still be asked for).
- **Schools revise earlier years** in later editions (about 1% of schools per year). Rule: for each campus, the newest edition that has a figure wins.
- **A blank does not erase a figure.** Example: BYU-Idaho reported 19 noncampus rapes for 2023 in the 2021–23 file; the 2022–24 file leaves that cell blank. We keep 19. Erasing it would have shown 0 rapes for 2023 and triggered the zero-rape context note.
- **Campuses drop out of later editions** (closed sites, for example Bard College at Simon's Rock and the University of Minnesota's Saint Paul campus row). In 33 of the 34 cases, the remaining campuses' figures did not grow, so the reports were not moved elsewhere. Figures are therefore kept per campus (`clery_campus_stats`), and school figures (`clery_stats`) are rebuilt as the sum of campuses after each import. Under a per-school rule, 84 figures at 37 schools would have lost reports.
- **Hate-crime files have `RAPE22`-style columns** (hate-crime rapes only). Under the old filename patterns, `Oncampushate222324.csv` would have imported as on-campus rapes. Patterns now match only `<location>(crime|vawa)<digits>.csv`, and a test covers it.

## Import totals

| | |
|---|--:|
| Schools imported (hd2025.csv) | 5,985 |
| with an enrollment figure (drvef2024.csv) | 5,802 |
| **with Clery data: listed publicly** | **5,680** |
| without Clery data: hidden from search, state lists, sitemap; page kept, `noindex` | 305 |
| Campuses (UNITID_P) | 10,647 |
| Campus rows / school rows | 207,636 / 112,532 |

Clery rows skipped because their institution is not in hd2025.csv (mostly closed schools): 235 per 2020–22 file, 66 per 2021–23 file, 2 per 2022–24 file. 41 drvef2024.csv rows have no matching school. Rows per year and location, with totals per offense: [import-totals.md](import-totals.md).

## Second run

All 26 imports queued again and processed by the worker: **0 added, 0 updated** for every file, and no row in `schools`, `clery_stats` or `clery_campus_stats` changed (`updated_at`). Results: [import-run-1-results.txt](import-run-1-results.txt), [import-run-2-results.txt](import-run-2-results.txt).

Independent check: every stored figure (787,724) recomputed from the raw CSVs with separate code (Python) using the same rule: 0 mismatches.

## Spot-check

[SPOT-CHECK.md](SPOT-CHECK.md): Cornell (190415, 3 campus rows), Ohio State–Main Campus (204796, large public), Swarthmore (216287, small private), Mt. San Antonio College (119164, community college), Miami Dade College (135717, 8 campus rows). Every year 2020–2024 and every location, with each campus's stored figure, the file it came from, and its raw cells in every file. Re-run with `php database/console.php clery:spot-check <unitid> ...`. 2024 at a glance: [spot-check-2024-summary.md](spot-check-2024-summary.md).

**Student housing above on campus:** none of the five. Across all schools, 5 school-years ([housing-exceeds-on-campus.md](housing-exceeds-on-campus.md)), each with both figures from the same file, so the inconsistency is in the source data. School totals never add student housing, so nothing is double-counted.

## Performance

Server time = curl time-to-first-byte minus connection setup, local Apache, 10 requests each, **opcache off**:

| Page | Status | Median ms | p90 ms | Max ms |
|---|---|--:|--:|--:|
| Home `/` | 200 | 22 | 43 | 45 |
| Search `/schools?q=university` (1,482 matches) | 200 | 29 | 48 | 51 |
| State list `/schools/ca` (600 schools, largest) | 200 | 22 | 26 | 35 |
| School `/schools/ny/cornell-university` | 200 | 61 | 69 | 75 |

The comparison queries cost 3–31 ms each (the under-1,000-students band is the slowest), so no cache table was added. New index: `schools (has_clery_data, state, name)`.

Sitemap: 5,798 URLs, 0.68 MB, one file (limits 50,000 / 50 MB).

## Not verified

- Figures against the Campus Safety website itself (no external requests from here). SPOT-CHECK.md is set up for doing that by hand.
- MySQL 8.0, which CI uses. Everything ran on MariaDB 10.11. `VALUES()` in `ON DUPLICATE KEY UPDATE` is deprecated in MySQL 8 but still works.
- Timings with opcache on, or on production hardware.
- The one possible absorbed campus: Austin Community College's Health Science Academy (1 report, 2022) may now be counted twice.
