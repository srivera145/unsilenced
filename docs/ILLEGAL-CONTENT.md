## Illegal content: what to do

**Status: PLACEHOLDER. This procedure must be completed and approved by the site's lawyer before SUBMISSIONS_ENABLED is turned on.** Items marked [LAWYER] are decisions or facts only they can supply. Until then, follow the first section and call the legal contact.

This page opens in the admin panel right after a file is quarantined (Admin → Survivor reports → a report → Report illegal content), and is kept in the code at docs/ILLEGAL-CONTENT.md.

## Right now

1. The file is already quarantined. No one can view or download it any more: not you, not the survivor, not anyone holding a share link. It stays encrypted on the server. If the survivor withdraws their report, the quarantined file is kept, encrypted and no longer connected to them (setting evidence.preserve_quarantined in config/unsilenced.php, see below).
2. **Do not** download, screenshot, copy, print, forward or show the file to anyone, including colleagues and the police, except as this procedure says. Possessing or distributing child sexual abuse material is a crime even when the intent is to report it.
3. **Do not** contact the survivor about the file until the lawyer advises. Do not reject, approve or otherwise change the report until then.
4. Note, somewhere other than the report or the admin note: the report number, the time you quarantined the file (shown on the report, in UTC), and in a few words why. Do not describe the image.
5. Call the legal contact now: [LAWYER: name, phone, email, and a backup contact].

## Apparent child sexual abuse material (NCMEC)

**For the lawyer to confirm and complete.** What we understand today, which must be checked:

- U.S. electronic communication and remote computing service providers that obtain actual knowledge of apparent child sexual abuse material must report it to the National Center for Missing & Exploited Children's CyberTipline as soon as reasonably possible (18 U.S.C. § 2258A). [LAWYER: whether Unsilenced is such a provider, and what triggers the duty.]
- Providers report through the CyberTipline's provider (ESP) reporting process, which needs a registered account: [https://report.cybertip.org/](https://report.cybertip.org/). [LAWYER: register before launch; who holds the account; what a report must include.]
- A provider that reports must preserve the reported content and related records for a set period (one year since the REPORT Act of 2024, previously 90 days). [LAWYER: confirm the period and what "related records" means for us.] This is why evidence.preserve_quarantined is true: withdrawal would otherwise delete the file.
- How the file reaches NCMEC or law enforcement without anyone viewing it: [LAWYER and engineering: there is no export command yet. One option is a console command that decrypts a quarantined file straight into an encrypted archive for transmission; it must be designed with the lawyer, logged, and limited to named people.]
- Who may decrypt and send it: [LAWYER].
- What, if anything, to tell the survivor, and when: [LAWYER].

## Other unlawful material

- Intimate images of adults shared without consent: the upload form requires survivors to confirm they are not uploading intimate images, and points them to the police or an attorney. If one is uploaded anyway: [LAWYER: whether to keep, delete or report it, and state-law obligations].
- Threats, or personal details of a third party (doxxing): [LAWYER].
- Anything else a lawyer considers unlawful to host: [LAWYER].

## Requests from police, courts or other parties

[LAWYER: how to handle subpoenas, warrants and preservation letters; who answers them; what can be produced.] For their information: Unsilenced stores no IP addresses for survivors, no account names, and no case keys. Reports, notes, file names and evidence are encrypted under a key held only in the server's .env; database backups alone cannot be read.

## Releasing a quarantined file

There is deliberately no button to undo a quarantine. If the lawyer decides a file was quarantined in error, an operator can clear quarantined_at for that file in the database, recording the decision outside the system. [LAWYER: who may authorise this.]

## What the system records

The admin activity log records who quarantined which file number, and when. The report's own history records the same event. Neither records anything about the file's contents. If the survivor withdraws, the file is kept as above and the log entries lose their link to the report.
