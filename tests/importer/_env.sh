#!/usr/bin/env bash
# Source this before running any test script: source tests/importer/_env.sh
export RM_URL="${RM_URL:-https://ridemaster.eu}"
export RM_USER="${RM_USER:-xavierkain.consulting@gmail.com}"
# IMPORTANT: export RM_PASS in your shell before sourcing.
# Do NOT commit a real password to this file.
if [ -z "${RM_PASS}" ]; then
    echo "FATAL: RM_PASS not set. export RM_PASS='xxxx xxxx xxxx xxxx xxxx xxxx' first." >&2
    return 1 2>/dev/null || exit 1
fi
export RM_AUTH=( -u "${RM_USER}:${RM_PASS}" )
