#!/usr/bin/env sh
# Queues every import in storage/imports: the IPEDS directory, IPEDS
# enrollment, then the Clery crime and VAWA files for the four locations,
# oldest file first. Then process the queue:
#
#   sh scripts/queue_all_imports.sh
#   php database/queue-work.php --once
#
# The directory file must come first (Clery rows need a school). The Clery
# files themselves can go in any order: for each campus the newest file's
# figure wins. Edit the two IPEDS file names when a new year arrives.
set -e
cd "$(dirname "$0")/.."

php database/console.php import:schools storage/imports/hd2025.csv
php database/console.php import:schools storage/imports/drvef2024.csv

# The years in the name (202122, 212223, 222324) sort oldest first.
for years in $(ls storage/imports | sed -nE 's/^(oncampus|residencehall|noncampus|publicproperty)(crime|vawa)([0-9]+)\.csv$/\3/ip' | sort -u); do
    for file in storage/imports/*"${years}".csv; do
        case "$(basename "$file" | tr 'A-Z' 'a-z')" in
            oncampuscrime*|oncampusvawa*|residencehallcrime*|residencehallvawa*|noncampuscrime*|noncampusvawa*|publicpropertycrime*|publicpropertyvawa*)
                php database/console.php import:clery "$file" ;;
        esac
    done
done
