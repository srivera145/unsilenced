# The yearly data update

Once a year the U.S. Department of Education publishes a new year of campus
crime figures (the Campus Safety and Security data, from the Clery Act), and
the National Center for Education Statistics publishes new college details
and enrollment (IPEDS). This guide adds them to Unsilenced. It takes about
an hour, most of it downloading and waiting.

You will need:

- a way to type commands on the server (an SSH session, or your host's
  "terminal" page), in the site's folder. These steps assume
  `/var/www/unsilenced`;
- a way to copy files onto the server (SFTP, your host's file manager, or
  `scp`);
- an admin sign-in for `/admin`.

Type each command exactly as shown and press Enter. A line starting with `#`
is a note, not a command.

## Step 1. Back up first

```bash
cd /var/www/unsilenced
bash scripts/backup.sh
```

It prints a line ending in `kept`. If anything below goes wrong,
`docs/RESTORE.md` puts everything back the way it was.

## Step 2. Download the college details (IPEDS)

1. Go to the IPEDS Data Center's complete data files:
   <https://nces.ed.gov/ipeds/datacenter/DataFiles.aspx>
2. Choose the newest year offered, and in the list of files find the two whose
   names start with:
   - **HD** followed by a year, for example `HD2026`: "Directory information".
     Take the newest one.
   - **DRVEF** followed by a year, for example `DRVEF2025`: derived variables
     for fall enrollment. This one usually runs a year behind HD; take the
     newest one listed.
3. Download the **data file** for each (a `.zip`, not the dictionary). Unzip
   them. Each holds a `.csv`, such as `hd2026.csv` and `drvef2025.csv`. If a
   zip holds two, one ending in `_rv` ("revised"), use that one.

## Step 3. Download the crime figures (Campus Safety)

1. Go to <https://ope.ed.gov/campussafety/> and open the page for
   downloading the data.
2. Download the newest year's data files (a `.zip`). Unzip it.
3. Inside are many files. You need the **eight** whose names start with these
   words and end with six digits, for example `Oncampuscrime232425.csv`:

   | File name starts with | What it covers |
   |---|---|
   | `Oncampuscrime`, `Oncampusvawa` | On campus |
   | `Residencehallcrime`, `Residencehallvawa` | On-campus student housing |
   | `Noncampuscrime`, `Noncampusvawa` | Noncampus property |
   | `Publicpropertycrime`, `Publicpropertyvawa` | Public property next to campus |

   The six digits are the three years the file covers (`232425` is 2023, 2024
   and 2025). The other files in the zip (hate, arrest, discipline, fire,
   unfounded, Reported...) are not used; copying them anyway does no harm, as
   they are skipped.

## Step 4. Put the files on the server

Copy the IPEDS `.csv` files and the eight Campus Safety files into the
site's `storage/imports/` folder. Keep their names exactly as downloaded.

**Do not delete last year's files from that folder.** They are imported
again (which changes nothing), and the import uses them to check that no
report is counted twice.

Check they are there:

```bash
ls storage/imports/
```

## Step 5. Run the import

```bash
sh scripts/queue_all_imports.sh
```

This picks up the newest HD and DRVEF files and every Campus Safety file in
the folder, checks each file's columns and lines them up to be imported. It
prints one or two lines per file. Then:

- **If the queue worker runs as a service** (it does on a server set up from
  `docs/LAUNCH-CHECKLIST.md`), it starts on them within seconds. Go to step 6.
- **If not**, process them yourself:

  ```bash
  php database/queue-work.php --once
  ```

  This takes about five minutes and prints nothing until it is done.

**If a file is refused**, the message names the file and the column it
expected but did not find. That means the government renamed a column this
year. Stop here and send the message to your developer: the fix is a
one-line change in `config/unsilenced.php`. Nothing was changed by the
refused file, and the site keeps showing last year's figures.

## Step 6. Check the results

1. Sign in at `/admin` and open **Imports**. Every run in the list should say
   **complete**:
   - the new files: many rows **added**;
   - last year's files: **0 added, 0 updated** (or a handful updated, when the
     new data corrected an older year);
   - **skipped** rows are normal: they are colleges that have closed since,
     and are not in the new HD file.
   Open a run to see its first errors, if it had any.
2. Open the **Our data** page (`/methodology`). Under "Clery Act crime
   statistics" it lists the years the site now has; the new year should be at
   the end.
3. Open two or three school pages you know, for example a large university
   and a community college. The charts should have a point for the new year.
4. For one of them, compare with the source. Run, with the school's IPEDS
   number (UNITID, shown in the address of its page on the College Navigator
   site, or in Admin → Schools):

   ```bash
   php database/console.php clery:spot-check 190415
   ```

   It prints a table: for each year and place, the figure the site shows and,
   under it, each campus's figure and the raw numbers from every file. Pick the
   newest year and check two or three numbers against the Campus Safety
   website (<https://ope.ed.gov/campussafety/>, search for the school).

If any of this looks wrong, restore the backup from step 1
(`docs/RESTORE.md`) and contact your developer before trying again.

## Step 7. Tidy up

Nothing to do: the import files stay in `storage/imports/` for next year, and
backups older than the newest 14 are removed automatically.

## Why figures can change for earlier years

Each Campus Safety file covers three years, and colleges sometimes correct an
earlier year in a later file. The site always uses the newest file's figure.
So after an update, a school's figure for, say, 2023 may change slightly.
That is expected; the Imports page shows it as "updated" rows.
