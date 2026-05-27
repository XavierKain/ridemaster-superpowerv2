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
version=$(python3 -c "import json; print(json.load(open('/tmp/rm-pong.json')).get('version', 'NO_VERSION'))")

if [ "$response" = "200" ] && [ "$status" = "pong" ] && [ "$version" = "0.1.0" ]; then
    echo "PASS: HTTP 200, status=pong, version=0.1.0"
    exit 0
else
    echo "FAIL: HTTP $response, status=$status, version=$version"
    cat /tmp/rm-pong.json
    exit 1
fi
