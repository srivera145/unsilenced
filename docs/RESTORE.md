# Restoring the database from a backup

`scripts/backup.sh` writes one file per run, `storage/backups/<database>-<UTC time>.sql.gz`
(or under `BACKUP_DIR`), and keeps the newest 14. Each file is a complete,
consistent `mysqldump` of the database, gzipped.

Two tables come back **empty by design**: `rate_limits` (IP addresses of
sign-in attempts, kept for a minute) and `auth_tokens` (sign-in codes and
links that expire within 15 minutes). Their structure is in the backup; their
rows are not. Admins who were signed in will need to sign in again.

The steps below restore into a **new** database first and switch the site to
it only once it checks out, so a bad backup never replaces good data.

Commands assume the project is at `/var/www/unsilenced`, the database is
`unsilenced`, and you can run `mysql` as a user that may create databases.
Replace the backup file name with the one you choose.

## 1. Choose the backup

```bash
cd /var/www/unsilenced
ls -lh storage/backups/
```

Names sort oldest to newest; the time is UTC. To check a file is readable
and complete before going further:

```bash
gzip -t storage/backups/unsilenced-20261001T031500Z.sql.gz && echo "gzip OK"
gzip -dc storage/backups/unsilenced-20261001T031500Z.sql.gz | grep -c '^-- Dump completed'   # must print 2
```

## 2. Stop the queue worker

So no import writes to the database while you switch.

```bash
sudo systemctl stop unsilenced-queue
```

## 3. Restore into a new database

```bash
mysql -u root -p -e "CREATE DATABASE unsilenced_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c storage/backups/unsilenced-20261001T031500Z.sql.gz | mysql -u root -p unsilenced_restore
```

If the site's database user is not `root`, give it access to the new database:

```bash
mysql -u root -p -e "GRANT ALL PRIVILEGES ON unsilenced_restore.* TO 'unsilenced'@'localhost'"
```

A database of the current size (about 330,000 Clery rows; a 3.6 MB backup)
restores in under a minute.

## 4. Check it

Compare the row counts of every table with the live database:

```bash
bash scripts/row-counts.sh unsilenced > /tmp/live.txt
bash scripts/row-counts.sh unsilenced_restore > /tmp/restored.txt
diff /tmp/live.txt /tmp/restored.txt
```

When restoring because the live data is damaged or gone, the live counts are
not a guide: compare `/tmp/restored.txt` with what you expect (Admin → Imports
lists every import with its row counts). Every table should have rows except
`rate_limits`, `auth_tokens`, `jobs` and `failed_jobs`, which may be empty.
For reference, the database on 2026-10-01 had:

```
schools              5985
clery_stats        112532
clery_campus_stats 207636
clery_file_totals  201264
resource_pages          3
state_pages            51
migrations             20
```

Then spot-check a school against the source files:

```bash
DB_DATABASE=unsilenced_restore php database/console.php clery:spot-check 190415
```

## 5. Switch the site to it

Point the site at the restored database by editing `.env`:

```
DB_DATABASE=unsilenced_restore
```

The site reads `.env` on every request, so this takes effect at once. If the
code is newer than the backup, apply any migrations the backup predates:

```bash
php database/migrate.php        # "No pending migrations." if none
sudo systemctl start unsilenced-queue
```

Open the home page, a school page and `/admin` to confirm.

Keep the old database until you are sure, then drop it:

```bash
mysql -u root -p -e "DROP DATABASE unsilenced"
```

(The site now runs on `unsilenced_restore`. Leave the name as it is: MySQL
cannot rename a database, and nothing depends on the name.)

## The evidence vault (Phase 2)

Each run of `backup.sh` also writes `vault-<UTC time>.tar.gz`: the encrypted
evidence files under `VAULT_PATH`. Restore the vault archive **from the same
run** as the database dump, so every evidence row has its file:

```bash
sudo -u www-data mkdir -p /var/www/unsilenced/storage/vault
sudo -u www-data tar -xzf storage/backups/vault-20261001T031500Z.tar.gz -C /var/www/unsilenced/storage/vault
chmod 700 /var/www/unsilenced/storage/vault
```

Neither backup can be read without `VAULT_MASTER_KEY`, which is never in a
backup: put the offline copy back into `.env` (the same key the backup was made
with). Then `php database/console.php vault:check`, and open one report's
evidence in the admin panel to confirm it decrypts.

A restore brings back reports that were withdrawn or purged after the backup
was made. Survivors were told withdrawal deletes everything: after restoring,
treat any report missing from the live site's history as withdrawn, and do not
restore an older backup than you need.

## Restoring onto a new server

1. Install the site as in the README (`composer install`, `.env`), but do
   **not** run `php database/migrate.php` yet.
2. Create the empty database and restore into it (step 3 above, with the
   database name from `.env`).
3. `php database/migrate.php` (applies only migrations newer than the backup).
4. Follow `docs/LAUNCH-CHECKLIST.md` from "Web server" on.

## If something goes wrong

- **`ERROR 1049 Unknown database`**: create the database first (step 3).
- **`ERROR 1045 Access denied`**: the user in the command or in `.env` lacks
  rights on the new database; run the `GRANT` in step 3.
- **The backup is from MariaDB and the server is MySQL 8, or the other way
  round**: the dump is plain SQL and restores either way. If MySQL 8's
  `mysqldump` was used against a MariaDB server and failed with
  `Unknown table 'COLUMN_STATISTICS'`, set `MYSQLDUMP=mariadb-dump` (or use the
  `mysqldump` that came with the server) for `scripts/backup.sh`.
- **"The dump did not complete"** from `backup.sh`: no file was kept. The
  usual causes are a full disk or a lost connection; the message above it says
  which.
