#!/usr/bin/env sh
# Queues every import in storage/imports: the newest IPEDS directory file
# (hdYYYY.csv), the newest IPEDS enrollment file (drvefYYYY.csv), then every
# Clery crime and VAWA file for the four locations, oldest file first. Then
# process the queue:
#
#   sh scripts/queue_all_imports.sh
#   php database/queue-work.php --once
#
# The directory file must come first (Clery rows need a school). The Clery
# files themselves can go in any order: for each campus the newest file's
# figure wins. Keep last year's Clery files in the folder: re-importing them
# changes nothing, and each file's school totals are part of the check that
# stops a report being counted twice (docs/DATA-REFRESH.md).
set -e
cd "$(dirname "$0")/.."

# The newest file whose name matches, by the year in its name ("" if none).
newest() {
    ls storage/imports | grep -iE "$1" | sort | tail -n 1
}

directory=$(newest '^hd[0-9]{4}(_rv)?\.csv$')
enrollment=$(newest '^drvef[0-9]{4}(_rv)?\.csv$')

if [ -z "$directory" ]; then
    echo "No IPEDS directory file (hdYYYY.csv) in storage/imports. See docs/DATA-REFRESH.md." >&2
    exit 1
fi

php database/console.php import:schools "storage/imports/$directory"
if [ -n "$enrollment" ]; then
    php database/console.php import:schools "storage/imports/$enrollment"
else
    echo "No IPEDS enrollment file (drvefYYYY.csv) in storage/imports; enrollment not updated." >&2
fi

# The years in the name (202122, 212223, 222324) sort oldest first.
for years in $(ls storage/imports | sed -nE 's/^(oncampus|residencehall|noncampus|publicproperty)(crime|vawa)([0-9]+)\.csv$/\3/ip' | sort -u); do
    for file in storage/imports/*"${years}".csv; do
        case "$(basename "$file" | tr 'A-Z' 'a-z')" in
            oncampuscrime*|oncampusvawa*|residencehallcrime*|residencehallvawa*|noncampuscrime*|noncampusvawa*|publicpropertycrime*|publicpropertyvawa*)
                php database/console.php import:clery "$file" ;;
        esac
    done
done
