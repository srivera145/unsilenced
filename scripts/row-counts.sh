#!/usr/bin/env bash
# The exact number of rows in every table of a database, one "table count"
# line each, sorted by table name. Used to check a restore (docs/RESTORE.md):
#
#   bash scripts/row-counts.sh unsilenced > live.txt
#   bash scripts/row-counts.sh unsilenced_restore > restored.txt
#   diff live.txt restored.txt
#
# Defaults to DB_DATABASE. MYSQL overrides the client program (mariadb works too).
set -euo pipefail

source "$(dirname "$0")/lib/mysql-client.sh"

database=${1:-$(env_value DB_DATABASE)}
mysql=${MYSQL:-mysql}
options=$(mktemp)
trap 'rm -f "$options"' EXIT
write_client_options "$options"

run() {
    "$mysql" "--defaults-extra-file=$options" --batch --skip-column-names "$database" -e "$1" | tr -d '\r'
}

tables=$(run "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name")
if [ -z "$tables" ]; then
    echo "No tables in $database." >&2
    exit 1
fi

query=""
for table in $tables; do
    query+="${query:+ UNION ALL }SELECT '$table', COUNT(*) FROM \`$table\`"
done
run "$query" | sort
