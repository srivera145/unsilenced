#!/usr/bin/env sh
# Fails if an Apache access log holds anything that identifies a visitor: an
# IP address, a User-Agent, a Referer or a query string. CI runs it on the
# Docker image's log after requests that send all four
# (.github/workflows/ci.yml); run it on a server's log the same way.
#
#   sh scripts/check-access-log.sh access.log [string-that-must-not-appear ...]
#
# The private format (docker/apache/unsilenced-privacy.conf) is
#   [01/Oct/2026:20:15:03 +0000] "GET /schools HTTP/1.1" 200 5120 1834
# Every line must have exactly that shape; any extra field is a failure.
set -eu

if [ $# -lt 1 ] || [ ! -f "$1" ]; then
    echo "Usage: sh scripts/check-access-log.sh <access log> [forbidden string ...]" >&2
    exit 2
fi

log=$1
shift
failed=0

fail() {
    echo "FAIL: $1"
    failed=1
}

requests=$(grep -c . "$log" || true)
if [ "$requests" -eq 0 ]; then
    echo "FAIL: $log is empty, so nothing was checked."
    exit 1
fi
echo "Checking $requests access log lines in $log"

# 1. Every line is exactly time, request line (path without query), status,
#    bytes and duration.
bad=$(grep -vcE '^\[[0-9]{2}/[A-Z][a-z]{2}/[0-9]{4}:[0-9]{2}:[0-9]{2}:[0-9]{2} [+-][0-9]{4}\] "[A-Z]+ [^ ?"]* HTTP/[0-9.]+" [0-9]{3} ([0-9]+|-) [0-9]+$' "$log" || true)
if [ "$bad" -ne 0 ]; then
    grep -vE '^\[[0-9]{2}/[A-Z][a-z]{2}/[0-9]{4}:[0-9]{2}:[0-9]{2}:[0-9]{2} [+-][0-9]{4}\] "[A-Z]+ [^ ?"]* HTTP/[0-9.]+" [0-9]{3} ([0-9]+|-) [0-9]+$' "$log" | head -n 5
    fail "$bad line(s) are not in the private format (an extra field such as an IP, User-Agent or Referer)"
fi

# 2. The same, by category, on everything after the timestamp (which has colons).
rest=$(sed -E 's/^\[[^]]*\] //' "$log")

if printf '%s\n' "$rest" | grep -qE '(^|[^0-9.])([0-9]{1,3}\.){3}[0-9]{1,3}([^0-9.]|$)'; then
    fail "an IPv4 address"
fi
if printf '%s\n' "$rest" | grep -qiE '([0-9a-f]{0,4}:){2,7}[0-9a-f]{0,4}'; then
    fail "an IPv6 address"
fi
if printf '%s\n' "$rest" | grep -qE '\?'; then
    fail "a query string"
fi
if printf '%s\n' "$rest" | grep -qiE 'mozilla/|curl/|wget/|python-requests|"-" "'; then
    fail "a User-Agent"
fi
if printf '%s\n' "$rest" | grep -qiE 'https?://'; then
    fail "a Referer (a URL)"
fi

# 3. Values the caller sent and knows must not appear: a test User-Agent,
#    Referer and query value, and the client address Apache saw.
for forbidden in "$@"; do
    if grep -qF -- "$forbidden" "$log"; then
        fail "\"$forbidden\" appears in the log"
    fi
done

if [ "$failed" -ne 0 ]; then
    exit 1
fi

echo "OK: no IP address, User-Agent, Referer or query string."
