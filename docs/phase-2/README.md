# Phase 2: survivor reports and the evidence vault

Verified on 2026-10-01: PHP 8.2.12 (with `-d extension=sodium`), MariaDB
10.11, PHP's built-in web server, headless Chrome 154 driven by
puppeteer-core. How the feature works: [../SURVIVOR-REPORTS.md](../SURVIVOR-REPORTS.md).

Everything here ran against a separate database, `unsilenced_walk`, holding
only the fictional fixture schools (UNITID 990001-990004), so no screenshot
pairs a test report with a real school.

## 1. Tests

**203 tests, 9,471 assertions, all passing** (105 before this phase). New:

| Test | What it proves |
|---|---|
| `Unit/CaseKeyServiceTest` | 5,600 words, 74.7 bits for six; no word from the sensitive lists; keys are six listed words; HMAC lookup id depends on the master key and never contains the key; Argon2id hash verifies only the right key; normalising |
| `Unit/VaultServiceTest` | master-key format; sealed text round trip, refused in another column, tampered or under another key; files of 1 byte, exactly one chunk and several chunks round-trip; **SHA-256 of the decrypted file equals the recorded fingerprint**; ciphertext holds no plaintext or file name; a swapped key, a cut-short file, a flipped bit and another master key all fail; deleting removes both copies; size limit; a vault inside `public_html` is refused |
| `Unit/FileInspectorTest` | each allowed type by its bytes; an MP4 with video refused, also when relabelled as M4A; GIF, ZIP, UTF-16, AVIF, binary refused with advice; AAC not mistaken for MP3 |
| `Unit/MetadataStripperTest` | **JPEG: EXIF, GPS, XMP, comment gone (`exif_read_data` finds none), image data byte-identical**, orientation kept aside; anything after the image dropped; PNG reduced to exactly the original image chunks; HEIC Exif item zeroed in place with the image item untouched; a real libheif file loses its Exif item; M4A tags become free space with the audio unchanged and still parsing as sound; MP3 loses ID3v2 and ID3v1; PDF author, dates and XMP blanked with every xref offset still valid; compressed PDFs marked partial |
| `Unit/NameScanServiceTest` | first names, full names, titles, "named X", phone formats, emails, addresses, room numbers, Greek letters and chapter abbreviations, handles; ordinary words at a sentence start left alone; the school's name ignored; segments rejoin to the exact text |
| `Unit/RedactionAndDiffTest` | removals and placeholders allowed; an added word, a moved word or an unknown bracket refused; the diff marks and escapes; a full-length account |
| `Unit/ProofOfWorkAndZipTest` | solve and verify; spent challenges remembered; the streamed ZIP opens with `ZipArchive` (consistency check on), deduplicates names, carries the manifest |
| `Unit/SessionRoutesTest` | survivor areas and their cookie paths; no session with submissions off; every route's middleware agrees |
| `Feature/SubmissionsFlagFeatureTest` | **flag off: 9 GET and 9 POST survivor routes show "coming soon" with the hotline and store nothing**; no school-page section; a bad vault key keeps it closed; the admin queue still works |
| `Feature/SurvivorReportFlowFeatureTest` | the nine-step form; school search and the name check as JSON storing nothing; submitting stores only hashes and ciphertext; the same form twice; a timed-out session keeps her answers; no proof of work, the honeypot and the global rate limit; validation reopens at the first problem; names must be removed or confirmed; private reports; file attestation and type by bytes; her key in any case and spacing; the 30-minute idle timeout; editing; publishing less after approval; her own evidence; another case's file id opens nothing; **withdrawal leaves 0 rows in every table and 0 files** |
| `Feature/ShareLinkFeatureTest` | the token shown once and only hashed; **originals with fingerprints and times; the download's SHA-256 equals the one shown**; the ZIP and manifest; passcode and lock-out; **revoke works at once**; **expiry**, then `ExpireShareLinks` deletes; quarantined files leave links; every failure looks the same |
| `Feature/ModerationFeatureTest` | private reports never in the queue; no file names for admins; status email says nothing about the report; redaction rules and the diff; **evidence needs a code from the last 15 minutes (full emailed-code round trip), and the copy has no GPS**; the checklist; approval effects; changes requested; **rejected reports purged after 30 days**; quarantine stops every view and survives withdrawal |
| `Feature/SurvivorStatsFeatureTest` | **below 3: "Fewer than 3 survivor reports so far."; from 3: the figures**; only approved, counted reports; withdrawal drops a report from the figures at once; accounts show year, setting and category only; the home-page total |
| `Feature/SurvivorPrivacyFeatureTest` | **a whole visit, logging redirected to a file: no key, account text, file name, share token or IP in any log**, nor IP in the database; the session holds ids only; cookie flags and paths; production needs HTTPS |
| `Feature/ContentSecurityFeatureTest` (added) | 20 survivor and moderation pages: no inline script, handler or style; neutral tab titles; the quick exit; **no repeated id** |

## 2. Walkthrough at 375px

Every step of a submission with a GPS-tagged photo, light mode (and the form
again in dark mode). Screenshots in [screenshots/](screenshots/).

| Step | Screenshot |
|---|---|
| 1 Before you start | `01-step-1-before-you-start.png` (dark: `dark-01-...`) |
| 2 The school: search, then chosen | `02a-...`, `02b-step-2-school-chosen.png` |
| 3 When and where | `03-step-3-when-and-where.png` |
| 4 Who, before and after choosing | `04a-step-4-who-unanswered.png`, `04b-step-4-who.png` |
| 5 Reporting | `05-step-5-reporting.png` |
| 6 Her account, then the name check highlighting "Tyler" | `06a-...`, `06b-step-6-name-check.png` (dark: `dark-06b-...`) |
| 7 Publishing | `07-step-7-publishing.png` |
| 8 Evidence (file input disabled until the attestation is ticked) | `08-step-8-evidence.png` |
| 9 Check and send | `09-step-9-check-and-send.png` (dark: `dark-09-...`) |
| The key, shown once; a wrong word; confirmed and removed from the page | `10-key-shown-once.png`, `11a-...`, `11b-...` |
| Her page: key entry, then the report | `12a-...`, `12b-my-report.png` |
| A share link, shown once | `13-share-link-created.png` |
| What the recipient sees | `14-share-files.png` |
| Withdrawal: two confirmations, then done | `40-...`, `41-...`, `42-withdrawn.png` |

Measured in the run: Send with the proof of work, upload and page load
**1.4 s** on this desktop. The share link landed on `/share/files` with the
token gone from the address bar. Pressing Esc twice on `/my-report` reached
the quick-exit page, and opening `/my-report` again showed the key form:
the beacon had signed her out.

Fixed because of the walkthrough: a section and the file input shared
`id="evidence"` (the file list failed on her page; a test now forbids repeated
ids); Deck drew unanswered radio buttons as if answered (`:indeterminate`);
the warning alert's heading was near-white on its pastel fill in dark mode;
the name check showed an error before she had seen the highlights; the review
summary ran a choice's label into its explanation; the share page's ZIP button
overflowed at 375px; admin answer rows were spread apart.

## 3. The GPS photo

`photo-gps.jpg`: a generated gradient with planted EXIF (camera "TestCam", GPS
N 40°42'46.08", W 74°0'21.6"), uploaded through the form. From
[verification.txt](verification.txt):

| | Size | SHA-256 | EXIF sections | GPS | Camera |
|---|--:|---|---|---|---|
| Original, downloaded through the share link | 841 B | `54db44e6...3c946a` | IFD0, COMMENT, GPS | N 40/1 42/1 4608/100, W 74/1 0/1 2160/100 | TestCam |
| Admin copy, fetched from the admin evidence page | 432 B | `9d3f7c46...ebeda2` | none | none | none |

The share page showed `54db44e6e29e89965442e0f8f78f75f3c61b7c5a36fa5dcdee85bc133b3c946a`,
the same as the downloaded original and the file on disk. The admin copy
still decodes (`magick identify`: JPEG 96x64) and is shown upright
(`22-admin-evidence-copy.png`).

## 4. Moderation and the school page

- Queue, review, the code-checked evidence view: `20-admin-queue.png`,
  `21-admin-review.png`, `22-admin-evidence-copy.png`.
- Redaction ("I was a sophomore." removed, "Tyler" replaced by
  `[name removed]`) and the side-by-side diff: `23-admin-redaction-diff.png`.
  Adding "The school covered it up." was refused, naming the new words.
- Checklist and approval: `24a-...`, `24b-admin-approved.png`.
- School section with one approved report: `30-school-section-below-threshold-375.png`
  ("Fewer than 3 survivor reports so far."). With three:
  `31-school-section-above-threshold-375.png` and `32-...-1280.png`: 67%
  reported to the school, 67% discouraged or pressured, average rating 2.0
  from 2 ratings, reports by year, top reasons, and the published account
  with its year, setting, category and "Evidence on file". After the
  withdrawal, back below the threshold: `33-...`.

## 5. Withdrawal

After withdrawing in the browser, for that case: 0 rows in `survivor_cases`,
`survivor_reports`, `evidence_files`, `share_links`, `share_link_files`,
`moderation_events`, and 0 activity-log rows pointing at it (5 admin entries
remain, without ids); both encrypted files gone from the vault.

## 6. Logs

After the walkthrough, 22 files searched (the PHP server's request log,
`storage/logs/*.log`, every session file) for the case key, the share token,
two phrases of the account, the file name and the redacted first name:
**0 matches each**. Session files hold only CSRF tokens, ids and timestamps.

## 7. Public pages

With submissions enabled: `/`, `/schools`, a search, a state, a school page,
`/resources` and two resource pages, `/states`, a state page, `/methodology`,
`/corrections`, a 404, `robots.txt`, `sitemap.xml` and `llms.txt`: **no
Set-Cookie, and no script, style, font or image from another origin**. Over
the whole browser walkthrough the only origins requested were the local server
and weather.com, the quick-exit destination, which the test answered locally.
`robots.txt` now disallows `/submit`, `/my-report` and `/share`.

## 8. Not verified

- **Real HEIC photos.** No HEIC encoder here: the HEIC code was checked on a
  HEIC-shaped container and on a real AVIF that libheif wrote with an Exif
  item (same boxes). Try an iPhone photo before launch.
- **Real phones and screen readers.** Headless Chrome at 375px only. No
  VoiceOver, TalkBack or NVDA pass and no automated accessibility audit; the
  WCAG work is structural (labels, fieldsets with legends, focus moved to each
  step's heading, live regions, no repeated ids, existing colour tokens). The
  proof of work's time on a slow phone is not measured.
- **Apache, nginx and Docker.** The walkthrough used PHP's built-in server,
  because XAMPP's Apache loads `php.ini` with `sodium` commented out. The
  Dockerfile and CI changes were not run (no Docker here).
- **MySQL 8.0**: migration 021 and the tests ran on MariaDB 10.11 only.
- **SMTP**: status emails were checked in the log mailer only.
- **HTTPS**: the `Secure` cookie flag is unit-tested, not observed over TLS.
- **Real-world PDFs, M4A location tags, large files**: the PDF and audio
  stripping was checked on synthetic and ffmpeg-made files; Word or Acrobat
  PDFs with object streams will be "partial". Uploads near 20 MB and ZIP
  downloads in a real browser were not tried.
- **`scripts/backup.sh`** with a vault was syntax-checked, not run.
- **The 30-minute timeout** was tested by moving the clock in tests, not by
  waiting.
- **Everything marked [LAWYER]** in `ILLEGAL-CONTENT.md`, and section 13's
  legal review in the launch checklist.
