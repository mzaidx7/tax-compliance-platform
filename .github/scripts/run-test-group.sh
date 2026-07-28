#!/usr/bin/env bash

set -uo pipefail

label="$1"
shift
log_file="$(mktemp)"

set +e
php artisan test --compact "$@" 2>&1 | tee "$log_file"
status="${PIPESTATUS[0]}"
set -e

if [ "$status" -ne 0 ]; then
    summary="$(tail -n 40 "$log_file" | awk '{gsub(/%/, "%25"); gsub(/\r/, "%0D"); printf "%s%%0A", $0}')"
    echo "::error title=${label}::${summary}"
fi

exit "$status"
