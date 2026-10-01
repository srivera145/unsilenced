#!/usr/bin/env bash
# Database backup: one consistent mysqldump, gzipped and timestamped, keeping
# the newest 14. Restore steps: docs/RESTORE.md.
#
#   bash scripts/backup.sh
#
# Settings (environment variables; DB_* also from .env):
#   BACKUP_DIR   where backups go. Default storage/backups (outside the web
#                root). Use a disk or mount other than the database's own.
#   BACKUP_KEEP  how many to keep. Default 14.
#   MYSQLDUMP    the dump program. Default mysqldump (mariadb-dump works too).
#
# Cron, every night at 03:15 as the user that owns the project:
#   15 3 * * * cd /var/www/unsilenced && bash scripts/backup.sh >> storage/logs/backup.log 2>&1
#
# --single-transaction reads every InnoDB table at one moment without locking
# them, so the site and the queue keep running during the dump.
#
# Two tables are backed up as structure only: rate_limits (keys are the IP
# addresses of sign-in attempts, deleted once they expire, a minute later) and
# auth_tokens (sign-in codes and links that expire within 15 minutes). Nothing in them is worth
# restoring, and leaving them out keeps visitor addresses out of two weeks of
# backup files. They restore empty.
set -euo pipefail
umask 077

source "$(dirname "$0")/lib/mysql-client.sh"

database=$(env_value DB_DATABASE)
if [ -z "$database" ]; then
    echo "DB_DATABASE is not set (environment or .env)." >&2
    exit 1
fi

dir=${BACKUP_DIR:-$project_root/storage/backups}
keep=${BACKUP_KEEP:-14}
mysqldump=${MYSQLDUMP:-mysqldump}
structure_only=(rate_limits auth_tokens)

mkdir -p "$dir"
stamp=$(date -u +%Y%m%dT%H%M%SZ)
file="$dir/$database-$stamp.sql.gz"
partial="$file.partial"
options=$(mktemp)
trap 'rm -f "$options" "$partial"' EXIT
write_client_options "$options"

ignore=()
for table in "${structure_only[@]}"; do
    ignore+=("--ignore-table=$database.$table")
done

dump=("$mysqldump" "--defaults-extra-file=$options" --single-transaction --quick --no-tablespaces --hex-blob)
{
    "${dump[@]}" "${ignore[@]}" "$database"
    "${dump[@]}" --no-data "$database" "${structure_only[@]}"
} | gzip -9 > "$partial"

# A dump cut short (disk full, connection lost) has no "Dump completed" line.
completed=$(gzip -dc "$partial" | grep -c '^-- Dump completed' || true)
if [ "$completed" -ne 2 ]; then
    echo "The dump did not complete; $file not written." >&2
    exit 1
fi
mv "$partial" "$file"

# Keep the newest $keep. The UTC timestamp in the name sorts oldest first.
count=$(find "$dir" -maxdepth 1 -name "$database-*.sql.gz" | wc -l | tr -d ' ')
if [ "$count" -gt "$keep" ]; then
    find "$dir" -maxdepth 1 -name "$database-*.sql.gz" | sort | head -n "$((count - keep))" | while IFS= read -r old; do
        rm -f -- "$old"
    done
fi

echo "$(date -u '+%Y-%m-%d %H:%M:%S') backed up $database to $file ($(du -h "$file" | cut -f1)); $(find "$dir" -maxdepth 1 -name "$database-*.sql.gz" | wc -l | tr -d ' ') kept"
