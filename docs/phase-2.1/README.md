# Phase 2.1: small-number protection

Verified on 2026-10-01: PHP 8.2.32 (Helm; sodium on in `php.ini`, exif and zip
with `-d`), MariaDB 10.11.18, PHP's built-in web server, headless Chrome 154
driven by puppeteer-core. How the feature works:
[../SURVIVOR-REPORTS.md](../SURVIVOR-REPORTS.md) (section 8 for the figures,
section 7 for the checklist, section 13 for the key).

As in Phase 2, the screenshots come from a separate database,
`unsilenced_walk`, holding only the fictional fixture schools (UNITID
990001-990004).

## What changed

**1. Every figure needs 3 responses of its own** (the existing
`survivor_reports.min_reports_to_show` setting, default 3).

| Figure | Responses that count toward it | Below 3 |
|---|---|---|
| Reported it to the school, % | every report | the whole section shows only "Fewer than 3 survivor reports so far." |
| Discouraged or pressured, % | every report | as above |
| Average rating | reports that gave a rating | "Not enough responses yet." |
| Each year | reports from that year | grouped into "Earlier years" (or "Other years" if a newer year is also too small); a group under 3 shows "Fewer than 3"; no year with 3 means "Not enough responses yet." |
| "The N people who did not" | reports not made to the school | the sentence drops the number |
| Each reason for not reporting | reports giving that reason | the reason is not listed; a note says "Reasons given by fewer than 3 people are not shown." |
| Home-page total | every report | hidden; a setting below 3 is raised to 3 |

The source note under the figures now says: "A figure is shown only when at
least 3 responses contribute to it."

**2. Roles that point to one person.** A new required checklist item: "No
role, title, team or position that could point to one person (such as RA,
coach, TA, team captain or chapter officer): each is replaced with a general
category". The name check (`NameScanService`) now flags roles with the words
that make them specific ("my RA", "the head coach", "the chapter president",
"a professor of biology", "the soccer team"), for the survivor at step 6 and
for the admin on the review page. "The Title IX office" is not flagged. Four
placeholders were added: `[a student employee]`, `[an instructor]`,
`[role removed]`, `[a club or organization]`.

**3. Sodium and the master key.**

- `composer.json` requires `ext-sodium`, so `composer install` stops on a
  server without it.
- `/up` answers 503 while `SUBMISSIONS_ENABLED=true` if sodium, a valid
  `VAULT_MASTER_KEY` or a safe `VAULT_PATH` is missing. The body names the
  failing part (`"vault":false`), never a setting or a path.
- [LAUNCH-CHECKLIST.md](../LAUNCH-CHECKLIST.md) section 13 says to keep the
  key in a password manager and one offline copy, that losing it makes every
  evidence file unrecoverable, and how to rotate it if it leaks.

**Beyond the spec: `php console vault:rotate`.** The spec says to rotate a
leaked key. Without a tool, rotating would have meant losing every file. The
command re-encrypts every file and every encrypted column under
`VAULT_NEW_MASTER_KEY`. It deletes the old encrypted files, can be run again
safely, and refuses to run while submissions are on. It prints the `.env`
changes (including `VAULT_LOOKUP_KEY`, which keeps existing case keys working)
and never prints a key.

**Also fixed: gendered wording.** The admin pages and docs from Phase 2 called
every survivor "she" ("Note to her", "Her publishing choice is respected").
Survivors can be any gender. The copy, docs, code comments, SQL comments and
test names now say "the survivor" or "they".

## 1. Tests

**215 tests, 9,570 assertions, all passing** (203 before this phase). New or
changed:

| Test | What it proves |
|---|---|
| `SurvivorStatsFeatureTest::testEachFigureNeedsThreeResponsesOfItsOwn` | **each figure at 2 (hidden) and at 3 (shown)**: the rating average, the "not reported" count, a reason, a year; a reason given by 1 is left out with the note |
| `…::testSmallYearsAreGroupedAndAShortGroupIsNotCounted` | 2024 ×3 shown; 2025 and 2021 (1 each) become "Other years: Fewer than 3" |
| `…::testTheSectionNeverPrintsACountUnderThree` | a mix of answers: no "(1)", "(2)", "the 1 person", "the 2 people", "From 2 ratings" or a table cell of 1 or 2 |
| `…::testTheHomeTotalNeverAppearsBelowTheFigureMinimum` | a home-page setting of 1 still waits for 3 |
| `…::testBelowTheThresholdOnlyTheCountMessageShows`, `…::testOnlyApprovedCountedReportsMakeUpTheFigures` (updated) | the per-figure output |
| `NameScanServiceTest::testRolesAndTitles` | "my RA", "the coach", "My chemistry professor", "a professor of biology", "the head coach", "team captain", "the chapter president", "a former RA", "TAs", "our resident advisor", "my academic advisor", "the soccer team", "the women's lacrosse team" |
| `NameScanServiceTest::testRolesDoNotHideANameOrMatchInsideWords` | "Coach Miller" stays a name; "My RA, Tyler" gives a role and a name; nothing in "theatre", "tarmac", "Ramadan", "data", "area"; the Title IX office is not a role |
| `ModerationFeatureTest::testApprovalNeedsTheRoleItemAndTheReviewHighlightsRoles` | approval refused without the role item; the review page highlights roles |
| `VaultOperationsFeatureTest::testUpReportsTheVaultOnlyWhileSubmissionsAreOn` | `/up`: 200 with submissions off and no key; 503 with them on and no key, a short key, or a vault inside `public_html`; 200 when ready; the body never names a setting |
| `…::testComposerRequiresSodium` | `ext-sodium` in `composer.json` and in the lock file's platform list |
| `…::testRotationMovesEveryFileAndTextAndKeepsCaseKeysWorking` | after rotation: the case key still opens the report; the account, published version, note, email, file name and link label open; **the original file is byte-identical with its SHA-256**; the admin copy still has no GPS; old encrypted files deleted; a second run finds nothing to do; the old key opens nothing |
| `…::testTheCommandInsistsOnTheNewKeyAndOnSubmissionsBeingOff` | refuses without a new key, with the same key, or with submissions on; without `--confirm` it changes nothing; it never prints a key |
| `…::testEveryEncryptedColumnIsInTheRotationList` | every `*_encrypted` column in the database is in the rotation list, so a future column cannot be left behind |

## 2. Three reports, one reason given by one person

[Fixture State University](screenshots/01-three-reports-one-reason-under-three-375.png)
has three approved reports, none made to the school, from 2024, 2023 and 2023.
All three gave "I was afraid I would not be believed". One each gave "I was
afraid of retaliation", "I didn't know how" and "The school discouraged me".

| What shows | Why |
|---|---|
| From 3 survivor reports: 0% reported, 33% discouraged | 3 reports |
| Average rating: "Not enough responses yet." | no ratings |
| Years: "Not enough responses yet. No single year has 3 or more reports." | 2023 has 2 |
| "The most common reasons given by the 3 people who did not", then only "I was afraid I would not be believed (3)" | 3 gave it |
| Retaliation, didn't know how, school discouraged: **not shown, no count** | 1 each |
| "Reasons given by fewer than 3 people are not shown." | |

Screenshots: `01-three-reports-one-reason-under-three-375.png`, the same at
1280px (`03-three-reports-1280.png`), and Fixture Polytechnic Institute with
seven reports (`02-seven-reports-years-grouped-375.png`): 2024 (3) and
"Earlier years" (4), an average from 5 ratings, and two non-reporters, so no
reasons and no count.

## 3. The name check: "my RA" and "the coach"

The text "After a party my RA walked me back to my room. I told the coach the
next week and he said to let it go." highlights exactly **"my RA"** and **"the
coach"**:

- for the survivor at step 6: `04-survivor-role-check-375.png`
- for the admin on the review page ("The name and role check flags these in
  the version below"), where "The Title IX office never called me back." is
  left alone: `05-admin-role-check-1280.png`
- the checklist with the new role item: `06-admin-checklist-1280.png`

No page errors in the browser during the run.

## 4. Without sodium

`composer install` in a copy of the project, on PHP 8.2.12 (XAMPP) with sodium
off. Exit code 2:

```
Installing dependencies from lock file (including require-dev)
Verifying lock file contents can be installed on current platform.
Your lock file does not contain a compatible set of packages. Please run composer update.

  Problem 1
    - Root composer.json requires PHP extension ext-sodium * but it is missing from your system. Install or enable PHP's sodium extension.
```

The XAMPP install was later removed from this machine. Everything after that
ran on Helm's PHP.

`/up`, from the walkthrough site with `SUBMISSIONS_ENABLED=true`, served once
by PHP with every extension except sodium, and once with it:

```
without sodium:  HTTP/1.1 503 Service Unavailable   {"status":"ok","database":true,"vault":false}
with sodium:     HTTP/1.1 200 OK                    {"status":"ok","database":true,"vault":true}
```

The body keeps Phase 1's `"status":"ok"` even on a 503. The launch checklist
now says to alert on the status code.

## 5. Not verified

- **Rotation on real data**: only on test data (one report, one file, every
  encrypted column). It has not been run against a large vault, a real
  backup or a server under load. Restoring a backup taken before rotation
  needs the old key (the checklist says so).
- **Composer without sodium on Helm**: the composer check ran on XAMPP's PHP
  before it was removed. Helm's PHP has sodium on, and the `/up` check above
  turned it off for one process only.
- **Real phones, screen readers, Safari and Firefox**: headless Chrome only.
- **Helm's Apache and MySQL 8**: the site ran on PHP's built-in server with
  MariaDB 10.11. The Docker/CI job (MySQL 8, Apache log checks) was not run.
- **The role check's reach**: it catches common US campus roles in English. It
  will miss unusual titles ("the band's drum major"), nicknames, and roles
  described without a title. The admin's checklist item is the safeguard for
  those.
- **Large schools**: the per-figure rules were checked with 3-7 reports per
  school, not hundreds.
