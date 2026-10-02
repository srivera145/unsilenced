# Survivor reports and the evidence vault (Phase 2)

How the feature works, what it stores and where, and how to run it. For
turning it on in production, see section 13 of `LAUNCH-CHECKLIST.md`. For what
to do with illegal content, `ILLEGAL-CONTENT.md`.

The purpose: show the gap between what schools report under the Clery Act and
what survivors say happened, and how their schools responded. Everything is
about the **school's handling**. The person who did it is only ever a category
("fellow student"), never a name.

## 1. Off until legal review

`SUBMISSIONS_ENABLED=false` (the default) makes every survivor route
(`/submit`, `/my-report`, `/share` and everything under them, GET or POST) a
"coming soon" page with the hotline. Those pages start no session, set no
cookie and store nothing. The school-page section, the home-page total and the
footer links are hidden. The admin queue (`/admin/reports`) works either way,
for testing.

With `SUBMISSIONS_ENABLED=true` the pages stay "coming soon" unless the vault
can work safely (`Submissions::problem()`, logged once per process):

- the `sodium` PHP extension is loaded;
- `VAULT_MASTER_KEY` is 32 bytes of base64;
- `VAULT_PATH` is outside `public_html`;
- with `APP_ENV=production`, the request came over HTTPS.

`php database/console.php vault:check` says which of these fails.

## 2. What a survivor does

1. **`/submit`**: one page, nine steps, shown one at a time by
   `public_html/js/report-form.js`. Nothing is sent until the final submit,
   except that leaving step 6 posts the account to `/submit/scan` for the
   name check, which stores and logs nothing. An abandoned form leaves nothing
   on the server. The hotline is on every step; the quick exit is on every
   page.
2. On submit the survivor is shown a **six-word case key**, once, and asked to type the
   last two words back (`public_html/js/case-key.js`), after which the key is
   removed from the page.
3. **`/my-report`**: their key opens their report: its status, any note from us,
   their answers, their evidence, share links, email updates, and withdrawal.
4. **`/share#token`**: what the person they send a link to sees.

Without JavaScript every step of the form shows at once, but sending needs
JavaScript (the proof of work, section 9); a `<noscript>` note says so.
`/my-report` and `/share` work without it.

## 3. What is stored, and how

Six tables (`database/migrations/021_create_survivor_report_tables.sql`). All
times are UTC, written by PHP.

| Table | Holds | Never holds |
|---|---|---|
| `survivor_cases` | HMAC lookup id and Argon2id hash of the case key; optional email, encrypted | the key, a name, an IP |
| `survivor_reports` | the answers (keys from config), status, consent; the survivor's account, the published version and the admin's note, **encrypted** | the account in plain text |
| `evidence_files` | SHA-256 and UTC upload time of the original; size and type; file name, **encrypted**; the names of the two encrypted files on disk and their keys, **wrapped** by the master key; whether an admin viewed or quarantined it | the file, the file's key in the clear |
| `share_links` | SHA-256 of the token; label, encrypted; passcode, Argon2id; expiry; failed passcode count; last opened (a time) | the token, anything about who opened it |
| `share_link_files` | which files a link shares | |
| `moderation_events` | the report's history: event, from/to status, admin id, JSON of keys and ids (the approval checklist) | anything the survivor or an admin wrote |

Text encryption (`SealedText`): XChaCha20-Poly1305 with a random nonce, under a
key derived from the master key, with the column name as associated data, so a
value copied into another column will not decrypt there.

The session for each survivor area holds only ids, the CSRF token, the
proof-of-work challenge and timestamps. Never the key, the account, a file
name or a share token.

## 4. Case keys

`CaseKeyService`. Six words drawn with `random_int` from
`src/App/Services/Survivor/case-key-words.txt`: **5,600 words, 74.7 bits**.

The list is the EFF large wordlist (CC BY 3.0 US, Electronic Frontier
Foundation) with every word removed that a survivor should not be handed on
this screen: violence, sex, the body, clothing close to the body, drink,
drugs, sleep, illness, crime and the courts, shame, fear, religion, family
words, "consent", "willing", "survivor", and Greek letters (a key must never
read like a chapter name). The rules are in `scripts/case-key-words/`; rebuild
with `php scripts/case-key-words/build.php eff_large_wordlist.txt`.
`CaseKeyServiceTest` fails if six words fall under 70 bits.

Stored:

- `lookup_id` = HMAC-SHA256(normalised key, key derived from the master key,
  or `VAULT_LOOKUP_KEY` after a rotation, section 13). Finds the row without
  storing the key; useless without that lookup key.
- `key_hash` = Argon2id(normalised key). Confirms it. Argon2 only runs when
  the lookup id matches, so wrong keys cost one HMAC.

Normalising: lower case, words split on anything but letters. Capitals,
hyphens and extra spaces do not matter. A mistyped word that is not in the
list is named in the error ("our keys never use ..."), which reveals nothing
about whether a case exists.

**There is no recovery.** We cannot look a key up or reset it. The survivor can send a
new report; nothing connects the two.

Key entry is not rate-limited, on purpose. At 74.7 bits, guessing a key is out
of reach at any request rate a web server can answer; and without IP addresses
a limit could only be global, which would let anyone lock every survivor out
of their page by sending wrong keys.

## 5. The evidence vault

`VaultService`, `FileInspector`, `MetadataStripper`.

**Allowed**: JPEG, PNG, HEIC, PDF, plain UTF-8 text, M4A, MP3. Up to 20 MB a
file and 20 files a report (`config evidence.*`). The type comes from the
file's first bytes; the name and the browser's claim are ignored. A JPEG
named `.pdf` is stored as a JPEG. **No video ever**: an MP4 is accepted as
audio only if every track is a sound track, so a video renamed `.m4a` is
refused. GIF, WebP, AVIF, Word, ZIP, UTF-16 text and the like are refused with
advice.

Before choosing files the survivor must tick: "I am not uploading nude, sexual or
intimate images or video. Those should go only to police or an attorney",
next to a link to the Save your evidence page. The file input is disabled
until they do, and the server refuses files without it.

**On upload**:

1. SHA-256 of the **original bytes** and the server's UTC time are recorded:
   the fingerprint the share page and its manifest show.
2. The original is encrypted with a new random key using libsodium's
   secretstream (XChaCha20-Poly1305, 64 KiB chunks, final tag: a cut-short
   file fails instead of decrypting short). The file key is wrapped with a
   key derived from `VAULT_MASTER_KEY`, bound to the file's name on disk, and
   stored in the row.
3. A second copy with metadata removed is encrypted the same way, under its
   own key. **Admins only ever see this copy.** The original, metadata and
   all, goes out only through the survivor's share links (it may matter in court).

Files are written to `VAULT_PATH/xx/<32 hex>` (default `storage/vault`),
outside `public_html`, written to a `.part` file and renamed.

**Metadata removal** (pure PHP, no GD or Imagick, lossless):

| Type | What is kept | Removed |
|---|---|---|
| JPEG | tables, frame and scan headers, the image data; Adobe colour marker | every APPn (EXIF with GPS, XMP, ICC, JFIF thumbnails, maker data) and COM; anything after the end marker |
| PNG | IHDR, PLTE, IDAT, IEND, transparency, colour and animation chunks | text, eXIf, tIME, ICC, everything else; anything after IEND |
| HEIC | the image items | the Exif and XMP items' bytes are zeroed in place and their type renamed, so every offset stays valid |
| M4A | the audio | udta, meta, ilst, uuid (XMP) boxes become zero-filled `free` boxes of the same size; creation times zeroed |
| MP3 | the audio frames | ID3v2, ID3v1, Enhanced TAG, APE, Lyrics3 |
| PDF | everything | **best effort**: every string in the document information dictionary (author, creator, dates, custom keys) and every uncompressed XMP packet is overwritten in place. Compressed object streams, compressed XMP or encryption make the copy **partial**, and the admin page says some metadata may remain |
| text | everything | nothing to remove |

A photo's EXIF orientation is recorded (it identifies nothing) so the admin
copy is shown upright with a CSS class. A file that cannot be parsed gets no
admin copy ("unavailable") and cannot be viewed by admins.

File names are metadata too: admins never see them ("File 1 · Photo (JPEG)").

**The survivor can** view (their original), add and delete their files at any time,
before and after approval, unless an admin quarantined one.

## 6. Share links

`ShareLinkService`, `ShareLinkController`.

- From `/my-report` the survivor picks files, an optional label ("my attorney", only
  they see it), an expiry (24 hours, 7 days, 30 days) and an optional passcode
  (6+ characters, Argon2id). At most 25 links per case.
- The link is `https://<domain>/share#<token>`: 32 random bytes, shown **once**.
  Only its SHA-256 is stored. The token is in the **fragment**, which browsers
  never send to a server, so it cannot appear in any access log, proxy or CDN
  log, or Referer. `/share`'s script reads it, removes it from the address bar
  and history, and posts it. Without JavaScript, the page has a box to paste
  the link into.
- The recipient sees each file's name, type, size, SHA-256 and upload time,
  downloads originals one by one, or "Download all": a ZIP streamed as it is
  built (decrypted files never touch the server's disk) with `manifest.txt`
  listing every file's SHA-256 and upload time and how to check them.
- Ten wrong passcodes lock the link. Every failure (unknown, expired, revoked,
  locked) looks the same: "This link is not available".
- They see every link: files, expiry, passcode or not, and when it was last
  opened (a time; nothing about who). **Turn off** deletes it at once, even for
  someone looking at it.
- Quarantined or deleted files drop out of every link.

## 7. Moderation

`Admin\ModerationController`, at `/admin/reports`.

- **Queue**: submitted → in review → approved, changes requested or rejected.
  Private reports (consent c) are not in the queue and cannot be opened by an
  admin; the queue shows only how many there are.
- **Published version**: admins never edit the survivor's account. They edit a separate
  published version, which starts as the survivor's text, and may only **remove** words
  and put one of the configured placeholders (`[name removed]`, `[a residence
  hall]` ...) where something was taken out. `RedactionCheck` refuses any word
  not in the original at that point, and any bracketed text that is not a
  placeholder. A side-by-side diff shows what was removed, because removing
  "not" changes meaning and no rule can catch that.
- **Approval checklist**, all required: no names or identifying details of
  anyone; no role, title, team or position that could point to one person
  (RA, coach, TA, team captain, chapter officer), each replaced with a general
  category such as `[a student employee]`; nothing that could identify the
  survivor; the school is correct;
  the survivor's publishing choice is respected; evidence "reviewed" (every viewable file
  has been opened) or "none provided" (only when there is none). A report
  publishing the account cannot be approved without a saved published version.
- **Note to the survivor**: shown on their page. Required to request changes.
- **Evidence** opens only from the metadata-free copy, and only if the admin
  entered an emailed code in the last **15 minutes** (signing in by code
  counts; a magic link does not). Otherwise `/admin/verify` emails a new code
  and returns them to the file.
- **Report illegal content** quarantines a file at once (no further viewing by
  anyone, still encrypted, kept on withdrawal) and opens
  `docs/ILLEGAL-CONTENT.md`.
- **Activity log**: every evidence view, published-version edit, note,
  status change, approval (with the checklist), rejection and quarantine, with
  ids only. The report's own history (`moderation_events`) records the same,
  plus the survivor's own edits.
- Editing a report (allowed until approval) puts it back to "submitted" and
  clears the published version, which no longer matches.

**Status emails** (optional, given on the form or on /my-report): subject "An
update is ready", body "there is an update on the page you asked us to tell
you about; go to /my-report and enter your six-word key". Nothing about the
school, the status or the report. Sent on every status change and note. The
address is encrypted, never queued (a queued job would keep it in the jobs
table), and deleted when the report is approved, rejected or withdrawn, or
made private.

## 8. On school pages

Below the Clery data, while submissions are enabled: "What survivors have told
us" (`ReportStatsService`, `views/schools/_survivor-section.php`).

- Counted: **approved** reports whose author chose (a) statistics or (b)
  statistics and account. Never private ones.
- Below `stats_min_reports` (3): "Fewer than 3 survivor reports so far."
- From 3: reports by the year it happened; % who reported to the school; %
  who say the school discouraged them or pressured them to stay quiet (after
  reporting, or from reporting at all); average rating of the school's
  response (from those who gave one); the top three reasons for not
  reporting.
- **Every figure needs 3 responses of its own** (the same setting; Phase
  2.1), because a school can pass the threshold while a single figure rests on
  one person:
  - the two percentages: every counted report answers that question, so they
    show from 3 reports;
  - the average rating: 3 ratings, or "Not enough responses yet" (and no
    "From N ratings" under 3);
  - each reason for not reporting: 3 people giving it. Reasons given by fewer
    are **left out**, not listed as "not enough": even the label would say
    someone gave that reason. "The N people who did not" appears only for 3 or
    more; with none to show, "Not enough responses yet";
  - each year: 3 reports. The other years are one row, "Earlier years" (or
    "Other years" when one is later than a year shown), with its total, or
    "Fewer than 3" when that is under 3 too. Small years are never listed one
    by one: with the total shown, they could be added back up. With no year
    at 3, "Not enough responses yet".
  `ReportStatsService` returns null for anything short, so no count under 3
  ever reaches the page; `SurvivorStatsFeatureTest` checks each figure at 2
  and at 3, and that the section never prints "(1)", "(2)", "the 1 person" or
  "From 2 ratings".
- Published accounts (consent b only), newest first: the year, the kind of
  place, the category of person, and the published text. Never the season, a
  date or a name. "Evidence on file" only when an admin has viewed at least one
  of its files and none of those is quarantined.
- A neutral note beside it: "Clery figures count reports made to the school.
  Survivor reports here include assaults that were never reported to the
  school." Nothing says a school failed to report a particular crime.
- The home page shows the national total from `homepage_min_reports` (25),
  and never below `stats_min_reports` whatever that setting says.

Withdrawal takes a report out of every figure at once: figures are counted
from the table on each page view, with no cache.

## 9. Spam, without tracking

- **Honeypot**: a field hidden from people (off screen, out of the tab order,
  `aria-hidden`). Filled in, the form is shown again and nothing is stored.
- **Proof of work**: on Send the browser finds a number n such that
  SHA-256("challenge:n") starts with `proof_of_work_bits` (18) zero bits:
  about 262,000 hashes, a second or two on a laptop, a few seconds on a phone
  (`public_html/js/pow.js`, our own single-block SHA-256). The challenge is in
  the session and used once; the five last-spent ones are remembered, so the
  same form arriving again (reload, Back then Forward) only says "already
  sent".
- **Global rate limit**: `submissions_per_minute` (10) across everyone, in
  `rate_limits` under the key `survivor-submissions`. No IP is recorded
  anywhere, so the limit cannot be per person.

Every refusal before the answers are checked (timed-out session, failed proof,
busy minute) shows the form again with the answers, at the last step. Chosen
files cannot be kept by a browser across a reload; the page asks the survivor to choose
them again.

## 10. Sessions and cookies

`SessionRoutes`, `SurvivorSession`, `SurvivorSessionMiddleware`.

- Each area (`/submit`, `/my-report`, `/share`) has its own cookie named
  `sid` with the **area's path**, so it is sent only to that area's pages and
  never to a public page. HttpOnly, SameSite=Strict, Secure over HTTPS (and
  production refuses plain HTTP), gone when the browser closes.
- Sessions are stored in `storage/sessions`, apart from the admin's, with a
  30-minute lifetime. After **30 minutes** without a request an area forgets
  what it held; `/my-report` then says they were signed out.
- Every survivor page is `Cache-Control: no-store` and `noindex`, and the
  forms are `autocomplete="off"` (which also stops a browser restoring what
  was typed if someone presses Back).
- On `/my-report` and `/share` the quick exit also signs the area out as it
  leaves (`navigator.sendBeacon` with the CSRF token), so Back after a quick
  exit finds the report or the files closed.
- Public pages are unchanged: no session, no cookie.

## 11. Withdrawal and retention

`CaseDeletionService`.

- **Withdraw** (two confirmations: an explanation, then a box to tick with a
  one-time token): deletes the case, the report, every evidence row and both
  encrypted files of each, every share link, the moderation history and the
  email. Activity-log entries about the report or its files keep the admin,
  action and time but lose the ids and metadata, so nothing left points to
  the survivor. Their key opens nothing afterwards.
- Rows go first, in one transaction, then files. If a file fails to delete,
  what remains on disk is unreadable: its key went with its row.
- **Rejected** reports are deleted the same way 30 days after rejection
  (`survivor:purge-rejected`, daily).
- **Exception, for the lawyer to confirm**: a file an admin quarantined as
  illegal content is kept, encrypted, with its report link and name removed
  (`evidence.preserve_quarantined`, default true), because reporting law may
  require it to be preserved. See `ILLEGAL-CONTENT.md`.
- **Backups** keep what was in the database and the vault when they were made,
  for as long as backups are kept (14 nights by default). A withdrawn report is
  gone from the site at once and from backups when the last one holding it
  rotates out. Say so in the privacy notice, or keep backups for less time.

## 12. Logs

Never logged, anywhere: case keys, account text, file names, share tokens.

- Survivor routes never write the admin activity log (it stores IP addresses)
  and never use the throttle (it keys on IP addresses).
- Error messages shown for a file never repeat its name or contents, and
  nothing in this code passes report text, names or tokens to `error_log`.
- The share token never reaches the server (section 6); the case key and
  account travel only in POST bodies, which access logs do not record.
- `SurvivorPrivacyFeatureTest` walks a whole visit with logging redirected to a
  file and fails if any of those values, or the visitor's IP, shows up in the
  log, the mail log, the database or the session.

## 13. Operations

```bash
php database/console.php vault:keygen                 # a new VAULT_MASTER_KEY, printed, not saved
php database/console.php vault:check                  # is the vault ready to take reports?
php database/console.php vault:rotate [--confirm]     # move everything to a new master key
php database/console.php survivor:purge-rejected      # daily: rejected 30+ days ago
php database/console.php survivor:expire-share-links  # hourly: expired links
```

`--queue` on the last two pushes the job (`PurgeRejectedReports`,
`ExpireShareLinks`) to the queue instead of running it.

**The master key** encrypts every file key, every account and note, and
derives the case-key lookup. Lose it and all of that is unreadable; leak it and
anyone with a database backup can read it. Keep it in `.env` on the server, in
a password manager the operators share, and in one offline copy, and nowhere
else (`LAUNCH-CHECKLIST.md` section 13).

**Rotating it** (`vault:rotate`, `KeyRotationService`; the procedure is in
the launch checklist). With submissions off and the new key in `.env` as
`VAULT_NEW_MASTER_KEY`, it re-encrypts both copies of every evidence file with
**new** file keys under the new master (new names on disk; the old files are
deleted once each row points at the new ones) and re-seals every encrypted
text column (`SealedText::COLUMNS`; a test fails if a new `_encrypted` column
is missing from that list). It can be run again after stopping part-way: what
already opens under the new key is skipped. Case keys are never stored, so
their lookup ids cannot be recomputed; the command prints the old lookup key
for `.env` as `VAULT_LOOKUP_KEY`, which keeps them working. That key decrypts
nothing and adds nothing for someone who already has the database: the
Argon2id hashes are there to test guesses against anyway, and six-word keys
are out of reach either way. Backups made before the rotation still open with
the old key.

`GET /up` answers 503 with `"vault": false` while submissions are on and
sodium, a valid master key (and lookup key, if set) or a safe `VAULT_PATH` is
missing, so an uptime monitor notices before survivors see "coming soon".
`composer install` refuses to run without sodium (`ext-sodium` in
`composer.json`).

**Backups** (`scripts/backup.sh`): the database dump, then
`vault-<UTC time>.tar.gz` of `VAULT_PATH`, both kept for `BACKUP_KEEP` runs.
The archive holds only encrypted files; `.env` is never included. Restoring
needs the database dump, the vault archive from the same run, and the master
key (`RESTORE.md`).

**Upload limits** in PHP: `upload_max_filesize=20M`, `post_max_size=64M` (or
more), `max_file_uploads=20`. The form warns before sending more than
`post_max_size` at once and suggests adding the rest from /my-report.

## 14. Settings

`.env`: `SUBMISSIONS_ENABLED`, `VAULT_MASTER_KEY`, `VAULT_PATH`; after a
rotation `VAULT_LOOKUP_KEY`, and during one `VAULT_NEW_MASTER_KEY`.

`config/unsilenced.php`:

| Key | Default | |
|---|---|---|
| `survivor_reports.stats_min_reports` | 3 | a school's figures from this many reports, and each figure from this many responses |
| `survivor_reports.homepage_min_reports` | 25 | the national total from this many |
| `survivor_reports.account_max_length` | 5000 | characters |
| `survivor_reports.rejected_retention_days` | 30 | |
| `survivor_reports.session_idle_minutes` | 30 | |
| `survivor_reports.admin_otp_fresh_minutes` | 15 | |
| `survivor_reports.submissions_per_minute` | 10 | global |
| `survivor_reports.proof_of_work_bits` | 18 | |
| `survivor_reports.share_link_expiry` | 24h, 7d, 30d | |
| `survivor_reports.share_passcode_max_attempts` | 10 | |
| `survivor_reports.share_links_max_per_case` | 25 | |
| `survivor_reports.redaction_placeholders` | 15 | the only bracketed text allowed in a published account |
| `evidence.max_file_bytes` | 20 MB | |
| `evidence.max_files` | 20 | per report |
| `evidence.preserve_quarantined` | true | for the lawyer to confirm |

The answer labels (settings, categories, reasons, outcomes, ratings, consents)
are there too. Never rename a key that reports already use.

## 15. Limits, and decisions for legal review

- **Not anonymous against the server operator's network**: the web server, a
  CDN or a hosting provider sees connections. `DEPLOY-PRIVACY.md` covers what
  the app cannot control.
- **The name check is a heuristic.** It catches common first names, two
  capitalised words, titles with names, phone numbers, emails, addresses, room
  numbers, Greek-letter chapters, handles, and (Phase 2.1) roles that point
  to one person with the words before them ("my RA", "the coach", "my
  chemistry professor", "team captain", "the soccer team"). It will miss some
  (an unusual first name alone, a nickname, a role it does not list), which is
  why admins redact the published version and tick "no names" and "no roles"
  before approving. The survivor and the admin see the same highlights.
- **Removing words can change meaning.** The diff is the safeguard; the rule
  cannot be.
- **PDF metadata removal is best effort** (section 5).
- **No admin export of evidence** for law enforcement yet (`ILLEGAL-CONTENT.md`).
- **Key rotation protects what is on the server, not copies already taken**:
  backups from before a rotation still open with the old key.
- **Decisions for the lawyer**: the illegal-content procedure and NCMEC
  obligations; keeping quarantined files after withdrawal; whether private
  reports (which no admin can read) create hosting obligations; backup
  retention of withdrawn reports and what the privacy notice says; the
  wording on the form (what we publish, the attestation, the email warning);
  answering subpoenas.
