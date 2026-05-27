#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 01: pong endpoint ==="
response=$(curl -s -o /tmp/rm-pong.json -w "%{http_code}" \
    "${RM_AUTH[@]}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d '{}' \
    "${RM_URL}/wp-json/ridemaster/v1/import-camp")

status=$(python3 -c "import json; print(json.load(open('/tmp/rm-pong.json')).get('status', 'NO_STATUS'))")

if [ "$response" = "200" ] && [ "$status" = "pong" ]; then
    echo "PASS: HTTP 200, status=pong"
    exit 0
else
    echo "FAIL: HTTP $response, status=$status"
    cat /tmp/rm-pong.json
    exit 1
fi
